<?php
// api/dev/check-booking.php - Check booking from Holiday Taxis API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../config/holiday-taxis.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $bookingRef = $input['bookingRef'] ?? null;

    if (!$bookingRef) {
        throw new Exception('Booking reference is required');
    }

    // Use TEST API for checking bookings
    $useTest = true;
    $apiUrl = HolidayTaxisConfig::getApiEndpoint($useTest) . "/bookings/{$bookingRef}";

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::getApiKey($useTest),
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::getApiVersion($useTest)
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Holiday Taxis API error: HTTP $httpCode");
    }

    $data = json_decode($response, true);
    if (!$data) {
        throw new Exception('Invalid API response format');
    }

    // Extract key information
    $booking = $data['booking'] ?? [];
    $general = $booking['general'] ?? [];
    $arrival = $booking['arrival'] ?? [];
    $departure = $booking['departure'] ?? [];

    // Extract driver and vehicle info if available
    $driverInfo = null;
    $vehicleInfo = null;
    $trackingStatus = null;

    // Check for driver events (if sent)
    if (isset($booking['driverevents']) && is_array($booking['driverevents'])) {
        foreach ($booking['driverevents'] as $event) {
            if (isset($event['driver'])) {
                $driverInfo = [
                    'name' => $event['driver']['name'] ?? null,
                    'phone' => $event['driver']['phoneNumber'] ?? null,
                    'license' => $event['driver']['licenseNumber'] ?? null
                ];
            }
            if (isset($event['vehicle'])) {
                $vehicleInfo = [
                    'registration' => $event['vehicle']['registration'] ?? null,
                    'brand' => $event['vehicle']['brand'] ?? null,
                    'model' => $event['vehicle']['model'] ?? null,
                    'color' => $event['vehicle']['color'] ?? null
                ];
            }
            if (isset($event['status'])) {
                $trackingStatus = [
                    'status' => $event['status'],
                    'timestamp' => $event['timestamp'] ?? null
                ];
            }
        }
    }

    // Format response
    $result = [
        'ref' => $general['bookingref'] ?? $bookingRef,
        'status' => $general['bookingstatus'] ?? null,
        'passenger' => $general['leadpassenger'] ?? null,
        'vehicle' => $general['vehicle'] ?? null,
        'airport' => $general['airport'] ?? null,
        'resort' => $general['resort'] ?? null,
        'arrival_date' => $arrival['arrivaldate'] ?? null,
        'departure_date' => $departure['departuredate'] ?? null,
        'driver' => $driverInfo,
        'vehicleInfo' => $vehicleInfo,
        'tracking' => $trackingStatus,
        'raw' => $data
    ];

    echo json_encode([
        'success' => true,
        'data' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
