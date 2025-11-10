<?php
// api/sync/holiday-taxis.php - Enhanced Sync with Individual Booking Details
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';
require_once '../config/province-mapping.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Parameters
    $days = $_POST['days'] ?? 7;
    $detailSync = $_POST['detail_sync'] ?? true; // Get individual booking details

    // Calculate date range
    $dateFrom = date('Y-m-d\TH:i:s', strtotime("-{$days} days"));
    $dateTo = date('Y-m-d\TH:i:s');

    // Step 1: Get bookings from Holiday Taxis search API
    $searchUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/since/{$dateFrom}/until/{$dateTo}/page/1";

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $searchUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Holiday Taxis Search API error: HTTP $httpCode");
    }

    $searchData = json_decode($response, true);
    if (!$searchData || !isset($searchData['bookings'])) {
        throw new Exception('Invalid API response format');
    }

    // Convert bookings object to array
    $bookingsData = $searchData['bookings'];
    if (is_object($bookingsData) || (is_array($bookingsData) && isset($bookingsData['booking_0']))) {
        $bookings = array_values((array)$bookingsData);
    } else {
        $bookings = $bookingsData;
    }

    $totalFound = count($bookings);
    $totalNew = 0;
    $totalUpdated = 0;
    $totalDetailed = 0;

    foreach ($bookings as $booking) {
        // Check if booking exists
        $checkSql = "SELECT id FROM bookings WHERE booking_ref = :ref";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':ref' => $booking['ref']]);
        $exists = $checkStmt->fetch();

        // Get detailed booking info if enabled (with retry)
        $detailData = null;
        if ($detailSync) {
            $maxRetries = 3;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $detailUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$booking['ref']}";

                $detailCh = curl_init();
                curl_setopt_array($detailCh, [
                    CURLOPT_URL => $detailUrl,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);

                $detailResponse = curl_exec($detailCh);
                $detailHttpCode = curl_getinfo($detailCh, CURLINFO_HTTP_CODE);
                $curlError = curl_error($detailCh);
                curl_close($detailCh);

                // Success
                if ($detailHttpCode === 200 && !empty($detailResponse)) {
                    $data = json_decode($detailResponse, true);
                    if ($data !== null) {
                        $detailData = $data;
                        $totalDetailed++;
                        if ($attempt > 1) {
                            error_log("✓ Detail API SUCCESS for {$booking['ref']} on attempt $attempt/$maxRetries");
                        }
                        break;
                    }
                }

                // Log failure
                $errorMsg = "HTTP $detailHttpCode";
                if (!empty($curlError)) {
                    $errorMsg .= " | cURL Error: $curlError";
                }
                error_log("✗ Detail API FAILED for {$booking['ref']} (attempt $attempt/$maxRetries) - $errorMsg");

                // Retry with exponential backoff
                if ($attempt < $maxRetries) {
                    $delay = $attempt * 500000; // 0.5s, 1s, 1.5s
                    usleep($delay);
                }
            }

            if ($detailData === null) {
                error_log("✗✗✗ Detail API FINAL FAILURE for {$booking['ref']} after $maxRetries attempts");
            }
        }

        // Process dates from both search and detail data
        $arrivalDate = null;
        $departureDate = null;
        $pickupDate = null;

        // From search data
        if (!empty($booking['arrivaldate'])) {
            $arrivalDate = $booking['arrivaldate'];
        }
        if (!empty($booking['departuredate'])) {
            $departureDate = $booking['departuredate'];
        }

        // Extract additional data from detail response
        $passengerEmail = null;
        $bookingType = null;
        $accommodationName = null;
        $accommodationAddress1 = null;
        $accommodationAddress2 = null;
        $accommodationTel = null;
        $airport = null;
        $airportCode = null;
        $resort = null;
        $flightNoArrival = null;
        $flightNoDeparture = null;
        $adults = 1;
        $children = 0;
        $infants = 0;

        // Initialize Quote-specific fields
        $pickupAddress1 = null;
        $pickupAddress2 = null;
        $pickupAddress3 = null;
        $pickupAddress4 = null;
        $dropoffAddress1 = null;
        $dropoffAddress2 = null;
        $dropoffAddress3 = null;
        $dropoffAddress4 = null;
        $transferDate = null;

        if ($detailData && isset($detailData['booking']['general'])) {
            $general = $detailData['booking']['general'];
            $bookingType = $general['bookingtype'] ?? null;
            $airport = $general['airport'] ?? null;
            $airportCode = $general['airportcode'] ?? null;
            $resort = $general['resort'] ?? null;
            $adults = intval($general['adults'] ?? 1);
            $children = intval($general['children'] ?? 0);
            $infants = intval($general['infants'] ?? 0);
        }

        // Calculate pax_total from adults + children + infants
        $paxTotal = $adults + $children + $infants;

        // Check if this is a Quote booking
        $isQuoteBooking = ($bookingType === 'Quote');

        // Process data based on booking type
        if ($isQuoteBooking && $detailData && isset($detailData['booking']['quote'])) {
            // Quote booking - use quote section
            $quote = $detailData['booking']['quote'];
            $transferDate = $quote['transferdate'] ?? null;
            $pickupAddress1 = $quote['pickupaddress1'] ?? null;
            $pickupAddress2 = $quote['pickupaddress2'] ?? null;
            $pickupAddress3 = $quote['pickupaddress3'] ?? null;
            $pickupAddress4 = $quote['pickupaddress4'] ?? null;
            $dropoffAddress1 = $quote['dropoffaddress1'] ?? null;
            $dropoffAddress2 = $quote['dropoffaddress2'] ?? null;
            $dropoffAddress3 = $quote['dropoffaddress3'] ?? null;
            $dropoffAddress4 = $quote['dropoffaddress4'] ?? null;

            // For Quote: pickup_date = transfer_date, no arrival/departure dates
            $pickupDate = $transferDate;
            $arrivalDate = null;
            $departureDate = null;
            $airport = null;
            $airportCode = null;
            $resort = null;
        } else {
            // Airport booking - use arrival/departure sections
            // From detail data (more accurate)
            if ($detailData && isset($detailData['booking'])) {
                $bookingDetail = $detailData['booking'];

                if (isset($bookingDetail['arrival']['arrivaldate'])) {
                    $arrivalDate = $bookingDetail['arrival']['arrivaldate'];
                }
                if (isset($bookingDetail['departure']['departuredate'])) {
                    $departureDate = $bookingDetail['departure']['departuredate'];
                }
                if (isset($bookingDetail['departure']['pickupdate'])) {
                    $pickupDate = $bookingDetail['departure']['pickupdate'];
                }
            }

            // Determine pickup date if not set
            if (!$pickupDate) {
                $pickupDate = $departureDate ?: $arrivalDate;
            }

            if ($detailData && isset($detailData['booking']['arrival'])) {
                $arrival = $detailData['booking']['arrival'];
                $accommodationName = $arrival['accommodationname'] ?? null;
                $accommodationAddress1 = $arrival['accommodationaddress1'] ?? null;
                $accommodationAddress2 = $arrival['accommodationaddress2'] ?? null;
                $accommodationTel = $arrival['accommodationtel'] ?? null;
                $flightNoArrival = $arrival['flightno'] ?? null;
            }

            if ($detailData && isset($detailData['booking']['departure'])) {
                $departure = $detailData['booking']['departure'];
                $flightNoDeparture = $departure['flightno'] ?? null;
                // Use departure accommodation if arrival not available
                if (!$accommodationName) {
                    $accommodationName = $departure['accommodationname'] ?? null;
                    $accommodationAddress1 = $departure['accommodationaddress1'] ?? null;
                    $accommodationAddress2 = $departure['accommodationaddress2'] ?? null;
                    $accommodationTel = $departure['accommodationtel'] ?? null;
                }
            }
        }

        // Prepare combined raw data
        $combinedRawData = [
            'search_data' => $booking,
            'detail_data' => $detailData,
            'sync_timestamp' => date('Y-m-d H:i:s')
        ];

        if ($exists) {
            // Update existing booking
            $updateSql = "UPDATE bookings SET
                            ht_status = :status,
                            passenger_name = :passenger_name,
                            passenger_phone = :passenger_phone,
                            pax_total = :pax_total,
                            booking_type = :booking_type,
                            vehicle_type = :vehicle_type,
                            airport = :airport,
                            airport_code = :airport_code,
                            resort = :resort,
                            accommodation_name = :accommodation_name,
                            accommodation_address1 = :accommodation_address1,
                            accommodation_address2 = :accommodation_address2,
                            accommodation_tel = :accommodation_tel,
                            arrival_date = :arrival_date,
                            departure_date = :departure_date,
                            pickup_date = :pickup_date,
                            flight_no_arrival = :flight_no_arrival,
                            flight_no_departure = :flight_no_departure,
                            pickup_address1 = :pickup_address1,
                            pickup_address2 = :pickup_address2,
                            pickup_address3 = :pickup_address3,
                            pickup_address4 = :pickup_address4,
                            dropoff_address1 = :dropoff_address1,
                            dropoff_address2 = :dropoff_address2,
                            dropoff_address3 = :dropoff_address3,
                            dropoff_address4 = :dropoff_address4,
                            transfer_date = :transfer_date,
                            last_action_date = :last_action_date,
                            raw_data = :raw_data,
                            synced_at = NOW(),
                            updated_at = NOW()
                          WHERE booking_ref = :ref";

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':ref' => $booking['ref'],
                ':status' => $booking['status'],
                ':passenger_name' => $booking['passengername'] ?? null,
                ':passenger_phone' => $booking['passengertelno'] ?? null,
                ':pax_total' => $paxTotal,
                ':booking_type' => $bookingType,
                ':vehicle_type' => $booking['vehicle'] ?? null,
                ':airport' => $airport,
                ':airport_code' => $airportCode,
                ':resort' => $resort,
                ':accommodation_name' => $accommodationName,
                ':accommodation_address1' => $accommodationAddress1,
                ':accommodation_address2' => $accommodationAddress2,
                ':accommodation_tel' => $accommodationTel,
                ':arrival_date' => $arrivalDate,
                ':departure_date' => $departureDate,
                ':pickup_date' => $pickupDate,
                ':flight_no_arrival' => $flightNoArrival,
                ':flight_no_departure' => $flightNoDeparture,
                ':pickup_address1' => $pickupAddress1,
                ':pickup_address2' => $pickupAddress2,
                ':pickup_address3' => $pickupAddress3,
                ':pickup_address4' => $pickupAddress4,
                ':dropoff_address1' => $dropoffAddress1,
                ':dropoff_address2' => $dropoffAddress2,
                ':dropoff_address3' => $dropoffAddress3,
                ':dropoff_address4' => $dropoffAddress4,
                ':transfer_date' => $transferDate,
                ':last_action_date' => $booking['lastactiondate'] ?? date('Y-m-d H:i:s'),
                ':raw_data' => json_encode($combinedRawData)
            ]);

            // Update assignment status based on booking status
            if ($booking['status'] === 'ACAN') {
                // If booking is cancelled, update assignment to cancelled
                $updateAssignmentSql = "UPDATE driver_vehicle_assignments
                                       SET status = 'cancelled',
                                           booking_status = :booking_status,
                                           cancelled_at = NOW()
                                       WHERE booking_ref = :ref";
                $updateAssignmentStmt = $pdo->prepare($updateAssignmentSql);
                $updateAssignmentStmt->execute([
                    ':ref' => $booking['ref'],
                    ':booking_status' => $booking['status']
                ]);
            } else {
                // Update booking_status in assignment
                $updateAssignmentStatusSql = "UPDATE driver_vehicle_assignments
                                              SET booking_status = :booking_status
                                              WHERE booking_ref = :ref";
                $updateAssignmentStatusStmt = $pdo->prepare($updateAssignmentStatusSql);
                $updateAssignmentStatusStmt->execute([
                    ':ref' => $booking['ref'],
                    ':booking_status' => $booking['status']
                ]);
            }

            $totalUpdated++;
        } else {
            // Auto-detect province
            if ($isQuoteBooking) {
                // For Quote bookings - use pickup/dropoff addresses
                $provinceData = ProvinceMapping::detectProvinceForQuote([
                    'pickup_address1' => $pickupAddress1,
                    'pickup_address2' => $pickupAddress2,
                    'pickup_address3' => $pickupAddress3,
                    'pickup_address4' => $pickupAddress4,
                    'dropoff_address1' => $dropoffAddress1,
                    'dropoff_address2' => $dropoffAddress2,
                    'dropoff_address3' => $dropoffAddress3,
                    'dropoff_address4' => $dropoffAddress4,
                ]);
            } else {
                // For Airport bookings - use airport/accommodation
                $provinceData = ProvinceMapping::detectProvince([
                    'airport' => $airport,
                    'airport_code' => $airportCode,
                    'from_airport' => $fromAirport ?? null,
                    'to_airport' => $toAirport ?? null,
                    'accommodation_address1' => $accommodationAddress1,
                    'accommodation_address2' => $accommodationAddress2
                ]);
            }

            // Insert new booking
            $insertSql = "INSERT INTO bookings (
                            booking_ref, ht_status, passenger_name, passenger_phone,
                            pax_total, booking_type, vehicle_type,
                            airport, airport_code, resort,
                            accommodation_name, accommodation_address1, accommodation_address2, accommodation_tel,
                            arrival_date, departure_date, pickup_date,
                            flight_no_arrival, flight_no_departure,
                            pickup_address1, pickup_address2, pickup_address3, pickup_address4,
                            dropoff_address1, dropoff_address2, dropoff_address3, dropoff_address4,
                            transfer_date,
                            province, province_source, province_confidence,
                            last_action_date, raw_data, synced_at
                          ) VALUES (
                            :ref, :status, :passenger_name, :passenger_phone,
                            :pax_total, :booking_type, :vehicle_type,
                            :airport, :airport_code, :resort,
                            :accommodation_name, :accommodation_address1, :accommodation_address2, :accommodation_tel,
                            :arrival_date, :departure_date, :pickup_date,
                            :flight_no_arrival, :flight_no_departure,
                            :pickup_address1, :pickup_address2, :pickup_address3, :pickup_address4,
                            :dropoff_address1, :dropoff_address2, :dropoff_address3, :dropoff_address4,
                            :transfer_date,
                            :province, :province_source, :province_confidence,
                            :last_action_date, :raw_data, NOW()
                          )";

            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ':ref' => $booking['ref'],
                ':status' => $booking['status'],
                ':passenger_name' => $booking['passengername'] ?? null,
                ':passenger_phone' => $booking['passengertelno'] ?? null,
                ':pax_total' => $paxTotal,
                ':booking_type' => $bookingType,
                ':vehicle_type' => $booking['vehicle'] ?? null,
                ':airport' => $airport,
                ':airport_code' => $airportCode,
                ':resort' => $resort,
                ':accommodation_name' => $accommodationName,
                ':accommodation_address1' => $accommodationAddress1,
                ':accommodation_address2' => $accommodationAddress2,
                ':accommodation_tel' => $accommodationTel,
                ':arrival_date' => $arrivalDate,
                ':departure_date' => $departureDate,
                ':pickup_date' => $pickupDate,
                ':flight_no_arrival' => $flightNoArrival,
                ':flight_no_departure' => $flightNoDeparture,
                ':pickup_address1' => $pickupAddress1,
                ':pickup_address2' => $pickupAddress2,
                ':pickup_address3' => $pickupAddress3,
                ':pickup_address4' => $pickupAddress4,
                ':dropoff_address1' => $dropoffAddress1,
                ':dropoff_address2' => $dropoffAddress2,
                ':dropoff_address3' => $dropoffAddress3,
                ':dropoff_address4' => $dropoffAddress4,
                ':transfer_date' => $transferDate,
                ':province' => $provinceData['province'],
                ':province_source' => $provinceData['source'],
                ':province_confidence' => $provinceData['confidence'],
                ':last_action_date' => $booking['lastactiondate'] ?? date('Y-m-d H:i:s'),
                ':raw_data' => json_encode($combinedRawData)
            ]);

            $totalNew++;
        }
    }

    // Log sync status
    $syncSql = "INSERT INTO sync_status (
                    sync_type, date_from, date_to, 
                    total_found, total_new, total_updated, 
                    status, completed_at
                ) VALUES (
                    'manual', :date_from, :date_to,
                    :total_found, :total_new, :total_updated,
                    'completed', NOW()
                )";

    $syncStmt = $pdo->prepare($syncSql);
    $syncStmt->execute([
        ':date_from' => $dateFrom,
        ':date_to' => $dateTo,
        ':total_found' => $totalFound,
        ':total_new' => $totalNew,
        ':total_updated' => $totalUpdated
    ]);

    $response = [
        'success' => true,
        'data' => [
            'totalFound' => $totalFound,
            'totalNew' => $totalNew,
            'totalUpdated' => $totalUpdated,
            'totalDetailed' => $totalDetailed,
            'dateRange' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'detailSync' => $detailSync,
            'syncedAt' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
