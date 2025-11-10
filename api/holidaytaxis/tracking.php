<?php
// api/holidaytaxis/tracking.php - Send Vehicle Location Updates
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/holiday-taxis.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $bookingRef = $_GET['ref'] ?? null;
    $vehicleIdentifier = $_GET['vehicle'] ?? null;

    if (!$bookingRef) {
        throw new Exception('Booking reference (ref) is required');
    }

    if (!$vehicleIdentifier) {
        throw new Exception('Vehicle identifier is required');
    }

    // Validate booking reference format
    if (!preg_match('/^[a-zA-Z0-9]{2,5}-[0-9]{6,}$/', $bookingRef)) {
        throw new Exception('Invalid booking reference format');
    }

    // Get request body
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);

    if (!$data) {
        throw new Exception('Invalid JSON in request body');
    }

    // Validate required fields
    if (!isset($data['location']) || !isset($data['location']['lat']) || !isset($data['location']['lng'])) {
        throw new Exception('Location data (lat, lng) is required');
    }

    if (!isset($data['status'])) {
        throw new Exception('Status is required');
    }

    // Validate status values
    $validStatuses = ['BEFORE_PICKUP', 'WAITING_FOR_CUSTOMER', 'AFTER_PICKUP', 'COMPLETED', 'NO_SHOW'];
    if (!in_array($data['status'], $validStatuses)) {
        throw new Exception('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
    }

    // Generate timestamp if not provided
    if (!isset($data['timestamp'])) {
        $data['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
    }

    // Build API URL
    $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$bookingRef}/vehicles/{$vehicleIdentifier}/location";

    // Headers
    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    // Prepare request data
    $requestData = [
        'timestamp' => $data['timestamp'],
        'location' => [
            'lat' => (float)$data['location']['lat'],
            'lng' => (float)$data['location']['lng']
        ],
        'status' => $data['status']
    ];

    // Make API call
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15, // Shorter timeout for location updates
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
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
            'error' => 'Booking or vehicle not found',
            'booking_ref' => $bookingRef
        ]);
        exit;
    }

    if ($httpCode === 400) {
        http_response_code(400);
        $errorData = json_decode($response, true);
        echo json_encode([
            'success' => false,
            'error' => 'Bad request',
            'details' => $errorData,
            'api_response' => $response
        ]);
        exit;
    }

    if (!in_array($httpCode, [200, 201, 204])) {
        throw new Exception("Holiday Taxis API error: HTTP $httpCode - " . $response);
    }

    // Parse response
    $responseData = json_decode($response, true);

    // Format success response
    $result = [
        'success' => true,
        'data' => $responseData ?? ['message' => 'Location updated successfully'],
        'meta' => [
            'booking_ref' => $bookingRef,
            'vehicle_identifier' => $vehicleIdentifier,
            'location' => $requestData['location'],
            'status' => $requestData['status'],
            'timestamp' => $requestData['timestamp'],
            'api_url' => $apiUrl,
            'http_code' => $httpCode,
            'request_timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    http_response_code($httpCode);
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'booking_ref' => $bookingRef ?? null,
            'vehicle_identifier' => $vehicleIdentifier ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
