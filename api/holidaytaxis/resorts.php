<?php
// api/holidaytaxis/resorts.php - Resorts Areas Metadata API
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
    $startAt = $_GET['startAt'] ?? 1;

    // Validation
    if (!is_numeric($startAt) || $startAt < 1) {
        throw new Exception('startAt must be a positive integer');
    }

    // Build API URL
    $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/products/resorts/areas?startAt=" . (int)$startAt;

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
        CURLOPT_TIMEOUT => 60, // Longer timeout for metadata
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

    // Process resorts data for easier frontend consumption
    $processedResorts = [];
    if (isset($data['resorts'])) {
        foreach ($data['resorts'] as $key => $resort) {
            if (isset($resort['resortid'])) {
                $processedResorts[] = [
                    'id' => (int)$resort['resortid'],
                    'areas_count' => is_array($resort['areas']) ? count($resort['areas']) : 0,
                    'areas' => $resort['areas'] ?? []
                ];
            }
        }
    }

    // Format response
    $result = [
        'success' => true,
        'data' => $data,
        'processed' => [
            'resorts' => $processedResorts,
            'total_resorts' => count($processedResorts),
            'start_at' => (int)$startAt,
            'has_next' => isset($data['metadata']['links']['next']) && !empty($data['metadata']['links']['next']),
            'has_previous' => isset($data['metadata']['links']['previous']) && !empty($data['metadata']['links']['previous']),
            'next_url' => $data['metadata']['links']['next'] ?? null,
            'previous_url' => $data['metadata']['links']['previous'] ?? null
        ],
        'meta' => [
            'api_url' => $apiUrl,
            'start_at' => (int)$startAt,
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
            'start_at' => $startAt ?? 1,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
