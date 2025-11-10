<?php
// api/holidaytaxis/search.php - Holiday Taxis Search API (3 types in 1 file)
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
    $type = $_GET['type'] ?? 'last-action';  // last-action, arrivals, departures
    $dateFrom = $_GET['dateFrom'] ?? null;
    $dateTo = $_GET['dateTo'] ?? null;
    $page = $_GET['page'] ?? 1;

    // Validation
    if (!$dateFrom || !$dateTo) {
        throw new Exception('dateFrom and dateTo are required');
    }

    if (!in_array($type, ['last-action', 'arrivals', 'departures'])) {
        throw new Exception('Invalid type. Must be: last-action, arrivals, or departures');
    }

    // Build API URL based on type
    switch ($type) {
        case 'arrivals':
            $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/arrivals/since/{$dateFrom}/until/{$dateTo}/page/{$page}";
            break;
        case 'departures':
            $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/departures/since/{$dateFrom}/until/{$dateTo}/page/{$page}";
            break;
        default: // last-action
            $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/since/{$dateFrom}/until/{$dateTo}/page/{$page}";
            break;
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
    if ($httpCode !== 200) {
        throw new Exception("Holiday Taxis API error: HTTP $httpCode - " . $response);
    }

    // Parse response
    $data = json_decode($response, true);
    if (!$data) {
        throw new Exception('Invalid JSON response from Holiday Taxis API');
    }

    // Format response
    $result = [
        'success' => true,
        'data' => $data,
        'meta' => [
            'search_type' => $type,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'page' => (int)$page,
            'api_url' => $apiUrl,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'type' => $type ?? 'unknown',
            'dateFrom' => $dateFrom ?? null,
            'dateTo' => $dateTo ?? null,
            'page' => $page ?? 1
        ]
    ]);
}
