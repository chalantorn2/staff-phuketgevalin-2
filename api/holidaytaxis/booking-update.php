<?php
// api/holidaytaxis/booking-update.php - Booking Update & Reconfirm API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/holiday-taxis.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);

    if (!$data) {
        throw new Exception('Invalid JSON in request body');
    }

    $apiUrl = '';
    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    // Determine API endpoint based on request type and data structure
    if ($method === 'PUT') {
        // Single booking update: PUT /bookings/{bookingRef}
        $bookingRef = $_GET['ref'] ?? null;
        if (!$bookingRef) {
            throw new Exception('Booking reference (ref) is required for single booking update');
        }

        if (!preg_match('/^[a-zA-Z0-9]{2,5}-[0-9]{6,}$/', $bookingRef)) {
            throw new Exception('Invalid booking reference format');
        }

        $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$bookingRef}";
    } elseif ($method === 'POST') {
        // Determine if it's multiple update or reconfirm
        $type = $_GET['type'] ?? 'update';

        if ($type === 'reconfirm') {
            // Reconfirm: POST /bookings/reconfirm
            $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/reconfirm";

            // Validate reconfirm data structure
            if (!isset($data['bookings']) || !is_array($data['bookings'])) {
                throw new Exception('Reconfirm request must have "bookings" array');
            }
        } else {
            // Multiple booking update: POST /bookings
            $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings";

            // Validate multiple update data structure
            if (!isset($data['bookings']) || !is_array($data['bookings'])) {
                throw new Exception('Multiple update request must have "bookings" array');
            }
        }
    } else {
        throw new Exception('Only PUT and POST methods are allowed');
    }

    // Make API call
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $requestBody,
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
            'booking_ref' => $_GET['ref'] ?? '-'
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

    if (!in_array($httpCode, [200, 202])) {
        throw new Exception("Holiday Taxis API error: HTTP $httpCode - " . $response);
    }

    // Parse response
    $responseData = json_decode($response, true);
    if (!$responseData) {
        throw new Exception('Invalid JSON response from Holiday Taxis API');
    }

    // Format success response
    $result = [
        'success' => true,
        'data' => $responseData,
        'meta' => [
            'method' => $method,
            'type' => $_GET['type'] ?? 'update',
            'booking_ref' => $_GET['ref'] ?? 'multiple',
            'api_url' => $apiUrl,
            'http_code' => $httpCode,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    // Add processed information for easier frontend handling
    if ($method === 'PUT') {
        $result['processed'] = [
            'single_booking_updated' => true,
            'booking_ref' => $_GET['ref'],
            'new_status' => $data['status'] ?? 'unchanged'
        ];
    } elseif (isset($data['bookings'])) {
        $result['processed'] = [
            'multiple_bookings' => true,
            'total_bookings' => count($data['bookings']),
            'operation_type' => $_GET['type'] ?? 'update'
        ];
    }

    http_response_code($httpCode);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'type' => $_GET['type'] ?? null,
            'booking_ref' => $_GET['ref'] ?? null,
            'has_request_body' => !empty($requestBody ?? ''),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
