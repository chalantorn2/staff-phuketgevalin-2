<?php
// api/sync/get-booking.php - Fetch single booking from Production API and save to database
error_reporting(E_ALL);
ini_set('display_errors', 1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';
require_once '../config/province-mapping.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get booking reference from query parameter
    $bookingRef = $_GET['booking_ref'] ?? null;

    if (!$bookingRef) {
        throw new Exception('Booking reference is required');
    }

    // Call Holiday Taxis API - GET /bookings/{bookingRef}
    $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/" . urlencode($bookingRef);

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("cURL error: " . $curlError);
    }

    if ($httpCode !== 200) {
        throw new Exception("API returned HTTP {$httpCode}");
    }

    $detailData = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from API");
    }

    // Check if booking was found
    if (!$detailData || (isset($detailData['error']) && $detailData['error'])) {
        throw new Exception($detailData['error'] ?? 'Booking not found');
    }

    // Debug: Log API response structure
    error_log("API Response Keys: " . json_encode(array_keys($detailData)));

    // Extract booking data - check if it's nested under 'booking' key
    if (isset($detailData['booking'])) {
        $general = $detailData['booking']['general'] ?? [];
        $arrival = $detailData['booking']['arrival'] ?? [];
        $departure = $detailData['booking']['departure'] ?? [];
        $quote = $detailData['booking']['quote'] ?? null;
    } else {
        $general = $detailData['general'] ?? [];
        $arrival = $detailData['arrival'] ?? [];
        $departure = $detailData['departure'] ?? [];
        $quote = $detailData['quote'] ?? null;
    }

    // Extract data
    $bookingType = $general['bookingtype'] ?? null;
    $status = $general['status'] ?? null;
    $passengerName = $general['passengername'] ?? null;
    $passengerPhone = $general['passengertelno'] ?? null;
    $vehicle = $general['vehicle'] ?? null;
    $adults = intval($general['adults'] ?? 1);
    $children = intval($general['children'] ?? 0);
    $infants = intval($general['infants'] ?? 0);

    // Calculate pax_total from adults + children + infants
    $paxTotal = $adults + $children + $infants;

    // Check if this is a Quote booking
    $isQuoteBooking = ($bookingType === 'Quote');

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

    // Process dates based on booking type
    if ($isQuoteBooking && $quote) {
        // Quote booking - use quote section
        $transferDate = $quote['transferdate'] ?? null;
        $pickupAddress1 = $quote['pickupaddress1'] ?? null;
        $pickupAddress2 = $quote['pickupaddress2'] ?? null;
        $pickupAddress3 = $quote['pickupaddress3'] ?? null;
        $pickupAddress4 = $quote['pickupaddress4'] ?? null;
        $dropoffAddress1 = $quote['dropoffaddress1'] ?? null;
        $dropoffAddress2 = $quote['dropoffaddress2'] ?? null;
        $dropoffAddress3 = $quote['dropoffaddress3'] ?? null;
        $dropoffAddress4 = $quote['dropoffaddress4'] ?? null;

        // For Quote: pickup_date = transfer_date, airport/resort = null
        $arrivalDate = null;
        $departureDate = null;
        $pickupDate = $transferDate;
        $airport = null;
        $airportCode = null;
        $resort = null;
    } else {
        // Airport booking - use arrival/departure sections
        $arrivalDate = $arrival['arrivaldate'] ?? null;
        $departureDate = $departure['departuredate'] ?? null;
        $pickupDate = $departure['pickupdate'] ?? $departureDate ?? $arrivalDate;
        $airport = $general['airport'] ?? null;
        $airportCode = $general['airportcode'] ?? null;
        $resort = $general['resort'] ?? null;
    }

    // Debug: Log extracted status
    error_log("Extracted status: " . ($status ?? 'NULL'));
    error_log("General keys: " . json_encode(array_keys($general)));

    // Accommodation data (prefer arrival, fallback to departure)
    $accommodationName = $arrival['accommodationname'] ?? $departure['accommodationname'] ?? null;
    $accommodationAddress1 = $arrival['accommodationaddress1'] ?? $departure['accommodationaddress1'] ?? null;
    $accommodationAddress2 = $arrival['accommodationaddress2'] ?? $departure['accommodationaddress2'] ?? null;
    $accommodationTel = $arrival['accommodationtel'] ?? $departure['accommodationtel'] ?? null;

    // Flight numbers
    $flightNoArrival = $arrival['flightno'] ?? null;
    $flightNoDeparture = $departure['flightno'] ?? null;

    // Validate required fields
    if (!$status) {
        $debugInfo = [
            'api_response_keys' => array_keys($detailData),
            'general_keys' => array_keys($general),
            'booking_ref' => $bookingRef
        ];
        error_log("Status validation failed. Debug info: " . json_encode($debugInfo));
        throw new Exception('Booking status is missing from API response. API may have returned unexpected format. Check error logs for details.');
    }

    if (!$bookingRef) {
        throw new Exception('Booking reference is missing from API response.');
    }

    // Check if booking exists in database
    $checkSql = "SELECT id FROM bookings WHERE booking_ref = :ref";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':ref' => $bookingRef]);
    $exists = $checkStmt->fetch();

    // Prepare combined raw data
    $combinedRawData = [
        'detail_data' => $detailData,
        'sync_timestamp' => date('Y-m-d H:i:s'),
        'sync_type' => 'single-booking-fetch'
    ];

    $action = '';

    if ($exists) {
        // Update existing booking

        // Re-detect province for existing bookings (in case data changed)
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
                'accommodation_address1' => $accommodationAddress1,
                'accommodation_address2' => $accommodationAddress2
            ]);
        }

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
                        province = :province,
                        province_source = :province_source,
                        province_confidence = :province_confidence,
                        raw_data = :raw_data,
                        synced_at = NOW(),
                        updated_at = NOW()
                      WHERE booking_ref = :ref";

        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':ref' => $bookingRef,
            ':status' => $status,
            ':passenger_name' => $passengerName,
            ':passenger_phone' => $passengerPhone,
            ':pax_total' => $paxTotal,
            ':booking_type' => $bookingType,
            ':vehicle_type' => $vehicle,
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
            ':raw_data' => json_encode($combinedRawData)
        ]);

        // Update assignment status based on booking status
        if ($status === 'ACAN') {
            $updateAssignmentSql = "UPDATE driver_vehicle_assignments
                                   SET status = 'cancelled',
                                       booking_status = :booking_status,
                                       cancelled_at = NOW()
                                   WHERE booking_ref = :ref";
            $updateAssignmentStmt = $pdo->prepare($updateAssignmentSql);
            $updateAssignmentStmt->execute([
                ':ref' => $bookingRef,
                ':booking_status' => $status
            ]);
        } else {
            $updateAssignmentStatusSql = "UPDATE driver_vehicle_assignments
                                          SET booking_status = :booking_status
                                          WHERE booking_ref = :ref";
            $updateAssignmentStatusStmt = $pdo->prepare($updateAssignmentStatusSql);
            $updateAssignmentStatusStmt->execute([
                ':ref' => $bookingRef,
                ':booking_status' => $status
            ]);
        }

        $action = 'updated';
    } else {
        // Auto-detect province for new booking
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
                        raw_data, synced_at
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
                        :raw_data, NOW()
                      )";

        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':ref' => $bookingRef,
            ':status' => $status,
            ':passenger_name' => $passengerName,
            ':passenger_phone' => $passengerPhone,
            ':pax_total' => $paxTotal,
            ':booking_type' => $bookingType,
            ':vehicle_type' => $vehicle,
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
            ':raw_data' => json_encode($combinedRawData)
        ]);

        $action = 'created';
    }

    // Return success response with FULL API data
    echo json_encode([
        'success' => true,
        'data' => [
            'booking_ref' => $bookingRef,
            'action' => $action,
            'status' => $status,
            'passenger' => $passengerName,
            'pickup_date' => $pickupDate,
            'province' => $exists ? null : $provinceData['province'],
            'booking_data' => $detailData, // Keep for backward compatibility
            'full_api_response' => $detailData, // Full API response for complete data access
            'raw_api_response' => $response // Original JSON string from API
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
