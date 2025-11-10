<?php
// api/sync/auto-sync.php - Auto-sync with Dual Query Strategy
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

    // Strategy 1: Dual Query for Auto-sync
    // Query 1: Last Action Date (±7 days) - Catch new/updated bookings
    // Query 2: Pickup Date (today to +14 days) - Catch upcoming bookings

    $totalFound = 0;
    $totalNew = 0;
    $totalUpdated = 0;
    $totalDetailed = 0;
    $processedRefs = []; // Track processed booking refs to avoid duplicates

    // =================================================================
    // QUERY 1: Last Action Date - New/Updated Bookings
    // =================================================================

    $dateFromLastAction = date('Y-m-d\TH:i:s', strtotime('-7 days'));
    $dateToLastAction = date('Y-m-d\TH:i:s');

    $searchUrl1 = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/since/{$dateFromLastAction}/until/{$dateToLastAction}/page/1";

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    $ch1 = curl_init();
    curl_setopt_array($ch1, [
        CURLOPT_URL => $searchUrl1,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response1 = curl_exec($ch1);
    $httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
    curl_close($ch1);

    $bookings1 = [];
    if ($httpCode1 === 200) {
        $searchData1 = json_decode($response1, true);
        if ($searchData1 && isset($searchData1['bookings'])) {
            $bookingsData1 = $searchData1['bookings'];
            if (is_object($bookingsData1) || (is_array($bookingsData1) && isset($bookingsData1['booking_0']))) {
                $bookings1 = array_values((array)$bookingsData1);
            } else {
                $bookings1 = $bookingsData1;
            }
        }
    }

    // =================================================================
    // QUERY 2: Pickup Date (Arrivals) - Upcoming Bookings
    // =================================================================

    $dateFromPickup = date('Y-m-d\TH:i:s');
    $dateToPickup = date('Y-m-d\TH:i:s', strtotime('+14 days'));

    $searchUrl2 = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/arrivals/since/{$dateFromPickup}/until/{$dateToPickup}/page/1";

    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => $searchUrl2,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    $bookings2 = [];
    if ($httpCode2 === 200) {
        $searchData2 = json_decode($response2, true);
        if ($searchData2 && isset($searchData2['bookings'])) {
            $bookingsData2 = $searchData2['bookings'];
            if (is_object($bookingsData2) || (is_array($bookingsData2) && isset($bookingsData2['booking_0']))) {
                $bookings2 = array_values((array)$bookingsData2);
            } else {
                $bookings2 = $bookingsData2;
            }
        }
    } elseif ($httpCode2 === 204) {
        // No content - no upcoming bookings
        $bookings2 = [];
    }

    // Merge both results
    $allBookings = array_merge($bookings1, $bookings2);
    $totalFound = count($allBookings);

    // =================================================================
    // Process All Bookings
    // =================================================================

    foreach ($allBookings as $booking) {
        $bookingRef = $booking['ref'];

        // Skip if already processed (avoid duplicates from dual query)
        if (in_array($bookingRef, $processedRefs)) {
            continue;
        }
        $processedRefs[] = $bookingRef;

        // Check if booking exists
        $checkSql = "SELECT id FROM bookings WHERE booking_ref = :ref";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':ref' => $bookingRef]);
        $exists = $checkStmt->fetch();

        // Get detailed booking info
        $detailData = null;
        $detailUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$bookingRef}";

        $detailCh = curl_init();
        curl_setopt_array($detailCh, [
            CURLOPT_URL => $detailUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20
        ]);

        $detailResponse = curl_exec($detailCh);
        $detailHttpCode = curl_getinfo($detailCh, CURLINFO_HTTP_CODE);
        curl_close($detailCh);

        if ($detailHttpCode === 200) {
            $detailData = json_decode($detailResponse, true);
            $totalDetailed++;
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

        if ($detailData && isset($detailData['booking']['general'])) {
            $general = $detailData['booking']['general'];
            $bookingType = $general['bookingtype'] ?? null;
            $airport = $general['airport'] ?? null;
            $airportCode = $general['airportcode'] ?? null;
            $resort = $general['resort'] ?? null;
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

        // Prepare combined raw data
        $combinedRawData = [
            'search_data' => $booking,
            'detail_data' => $detailData,
            'sync_timestamp' => date('Y-m-d H:i:s'),
            'sync_type' => 'auto-dual-query'
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
                            last_action_date = :last_action_date,
                            raw_data = :raw_data,
                            synced_at = NOW(),
                            updated_at = NOW()
                          WHERE booking_ref = :ref";

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':ref' => $bookingRef,
                ':status' => $booking['status'],
                ':passenger_name' => $booking['passengername'] ?? null,
                ':passenger_phone' => $booking['passengertelno'] ?? null,
                ':pax_total' => 1,
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
                    ':ref' => $bookingRef,
                    ':booking_status' => $booking['status']
                ]);
            } else {
                // Update booking_status in assignment
                $updateAssignmentStatusSql = "UPDATE driver_vehicle_assignments
                                              SET booking_status = :booking_status
                                              WHERE booking_ref = :ref";
                $updateAssignmentStatusStmt = $pdo->prepare($updateAssignmentStatusSql);
                $updateAssignmentStatusStmt->execute([
                    ':ref' => $bookingRef,
                    ':booking_status' => $booking['status']
                ]);
            }

            $totalUpdated++;
        } else {
            // Auto-detect province
            $provinceData = ProvinceMapping::detectProvince([
                'airport' => $airport,
                'airport_code' => $airportCode,
                'from_airport' => $fromAirport ?? null,
                'to_airport' => $toAirport ?? null,
                'accommodation_address1' => $accommodationAddress1,
                'accommodation_address2' => $accommodationAddress2
            ]);

            // Insert new booking
            $insertSql = "INSERT INTO bookings (
                            booking_ref, ht_status, passenger_name, passenger_phone,
                            pax_total, booking_type, vehicle_type,
                            airport, airport_code, resort,
                            accommodation_name, accommodation_address1, accommodation_address2, accommodation_tel,
                            arrival_date, departure_date, pickup_date,
                            flight_no_arrival, flight_no_departure,
                            province, province_source, province_confidence,
                            last_action_date, raw_data, synced_at
                          ) VALUES (
                            :ref, :status, :passenger_name, :passenger_phone,
                            :pax_total, :booking_type, :vehicle_type,
                            :airport, :airport_code, :resort,
                            :accommodation_name, :accommodation_address1, :accommodation_address2, :accommodation_tel,
                            :arrival_date, :departure_date, :pickup_date,
                            :flight_no_arrival, :flight_no_departure,
                            :province, :province_source, :province_confidence,
                            :last_action_date, :raw_data, NOW()
                          )";

            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ':ref' => $bookingRef,
                ':status' => $booking['status'],
                ':passenger_name' => $booking['passengername'] ?? null,
                ':passenger_phone' => $booking['passengertelno'] ?? null,
                ':pax_total' => 1,
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
                    'auto-dual-query', :date_from, :date_to,
                    :total_found, :total_new, :total_updated,
                    'completed', NOW()
                )";

    $syncStmt = $pdo->prepare($syncSql);
    $syncStmt->execute([
        ':date_from' => $dateFromLastAction,
        ':date_to' => $dateToPickup,
        ':total_found' => count($processedRefs), // Actual unique bookings
        ':total_new' => $totalNew,
        ':total_updated' => $totalUpdated
    ]);

    $response = [
        'success' => true,
        'data' => [
            'strategy' => 'dual-query',
            'query1' => [
                'type' => 'last-action',
                'from' => $dateFromLastAction,
                'to' => $dateToLastAction,
                'found' => count($bookings1)
            ],
            'query2' => [
                'type' => 'pickup-arrivals',
                'from' => $dateFromPickup,
                'to' => $dateToPickup,
                'found' => count($bookings2)
            ],
            'totalFound' => $totalFound,
            'uniqueBookings' => count($processedRefs),
            'totalNew' => $totalNew,
            'totalUpdated' => $totalUpdated,
            'totalDetailed' => $totalDetailed,
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
