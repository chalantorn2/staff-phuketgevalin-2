<?php
// api/holidaytaxis/booking-detail.php - Individual Booking Detail & Notes API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/holiday-taxis.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Parameters
    $bookingRef = $_GET['ref'] ?? null;
    $type = $_GET['type'] ?? 'detail';  // 'detail' or 'notes'

    // Validation
    if (!$bookingRef) {
        throw new Exception('Booking reference (ref) is required');
    }

    if (!in_array($type, ['detail', 'notes'])) {
        throw new Exception('Invalid type. Must be: detail or notes');
    }

    // Validate booking reference format
    if (!preg_match('/^[a-zA-Z0-9]{2,5}-[0-9]{6,}$/', $bookingRef)) {
        throw new Exception('Invalid booking reference format');
    }

    // Build API URL based on type
    if ($type === 'notes') {
        $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/notes/{$bookingRef}";
    } else {
        $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$bookingRef}";
    }

    // Headers
    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    // Make API call
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Handle cURL errors
    if ($curlError) {
        throw new Exception("cURL Error: " . $curlError);
    }

    // Handle HTTP errors
    if ($httpCode === 404) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Booking not found',
            'booking_ref' => $bookingRef
        ]);
        exit;
    }

    if ($httpCode !== 200) {
        throw new Exception("Holiday Taxis API error: HTTP $httpCode - " . $response);
    }

    // Parse response
    $data = json_decode($response, true);
    if (!$data) {
        throw new Exception('Invalid JSON response from Holiday Taxis API');
    }

    // Format response with additional processing
    $result = [
        'success' => true,
        'data' => $data,
        'meta' => [
            'booking_ref' => $bookingRef,
            'request_type' => $type,
            'api_url' => $apiUrl,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    // Add processed data for easier frontend consumption
    if ($type === 'detail' && isset($data['booking'])) {
        $booking = $data['booking'];

        $result['processed'] = [
            'general' => $booking['general'] ?? null,
            'arrival_info' => $booking['arrival'] ?? null,
            'departure_info' => $booking['departure'] ?? null,
            'has_arrival' => isset($booking['arrival']),
            'has_departure' => isset($booking['departure']),
            'booking_type' => $booking['general']['bookingtype'] ?? 'Unknown',
            'status' => $booking['general']['status'] ?? 'Unknown',
            'passenger_name' => $booking['general']['passengername'] ?? '-',
            'passenger_phone' => $booking['general']['passengertelno'] ?? '-',
            'total_pax' => (int)($booking['general']['pax'] ?? 0),
            'vehicle' => $booking['general']['vehicle'] ?? '-',
            'notes_url' => $booking['notes'] ?? null,
            'has_open_query' => ($booking['hasopenquery'] ?? '') === 'Y'
        ];
    }

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'booking_ref' => $bookingRef ?? null,
            'type' => $type ?? 'detail',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
