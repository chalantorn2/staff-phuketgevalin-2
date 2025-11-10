<?php
// api/tracking/test-ht-connection.php - Test Holiday Taxis API Connection
//
// Usage: Navigate to this file in browser or run via CLI
// This script tests the connection to Holiday Taxis API without affecting real data

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once '../config/holiday-taxis.php';

$results = [];

// Test 1: Check if cURL is available
$results['curl_available'] = function_exists('curl_init');
if ($results['curl_available']) {
    $curlVersion = curl_version();
    $results['curl_version'] = $curlVersion['version'];
    $results['curl_ssl_version'] = $curlVersion['ssl_version'] ?? 'Unknown';
} else {
    $results['error'] = 'cURL is not available. Please install php-curl extension.';
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}

// Test 2: Check API Configuration
$results['config'] = [
    'api_endpoint' => HolidayTaxisConfig::API_ENDPOINT,
    'api_version' => HolidayTaxisConfig::API_VERSION,
    'api_key_length' => strlen(HolidayTaxisConfig::API_KEY),
    'api_key_prefix' => substr(HolidayTaxisConfig::API_KEY, 0, 10) . '...'
];

// Test 3: Try to connect to Holiday Taxis (using a test booking ref)
$testBookingRef = 'HTA-00000000'; // Test booking that won't exist
$testVehicleId = 'test-vehicle-123';

$apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$testBookingRef}/vehicles/{$testVehicleId}/location";

$testLocationData = [
    'timestamp' => gmdate('Y-m-d\TH:i:s') . '+00:00',
    'location' => [
        'lat' => 7.8804,
        'lng' => 98.3923
    ],
    'status' => 'BEFORE_PICKUP'
];

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
    CURLOPT_TIMEOUT => 10,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testLocationData),
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_VERBOSE => false
]);

$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlInfo = curl_getinfo($ch);
curl_close($ch);

$results['connection_test'] = [
    'url' => $apiUrl,
    'http_code' => $httpCode,
    'curl_error' => $curlError ?: null,
    'response_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
    'response_body' => $response,
    'response_size' => strlen($response) . ' bytes',
    'connection_details' => [
        'total_time' => $curlInfo['total_time'] ?? 0,
        'namelookup_time' => $curlInfo['namelookup_time'] ?? 0,
        'connect_time' => $curlInfo['connect_time'] ?? 0,
        'pretransfer_time' => $curlInfo['pretransfer_time'] ?? 0,
        'starttransfer_time' => $curlInfo['starttransfer_time'] ?? 0,
        'primary_ip' => $curlInfo['primary_ip'] ?? 'Unknown'
    ]
];

// Interpret results
if ($curlError) {
    $results['status'] = 'FAILED';
    $results['verdict'] = '❌ cURL Error: ' . $curlError;
    $results['recommendation'] = 'Check network connectivity, DNS resolution, or firewall settings.';
} elseif ($httpCode === 0) {
    $results['status'] = 'FAILED';
    $results['verdict'] = '❌ No HTTP response received';
    $results['recommendation'] = 'Server cannot reach Holiday Taxis API. Check firewall or network settings.';
} elseif ($httpCode === 404) {
    $results['status'] = 'SUCCESS';
    $results['verdict'] = '✅ Connection successful! (404 is expected for test booking)';
    $results['recommendation'] = 'API connection is working. 404 means the test booking doesn\'t exist, which is expected.';
} elseif ($httpCode === 401 || $httpCode === 403) {
    $results['status'] = 'AUTH_FAILED';
    $results['verdict'] = '⚠️ Authentication failed';
    $results['recommendation'] = 'Check API_KEY is correct. Response: ' . $response;
} elseif (in_array($httpCode, [200, 201, 204])) {
    $results['status'] = 'SUCCESS';
    $results['verdict'] = '✅ Connection fully successful!';
    $results['recommendation'] = 'API is working perfectly.';
} else {
    $results['status'] = 'PARTIAL';
    $results['verdict'] = "⚠️ Unexpected HTTP code: {$httpCode}";
    $results['recommendation'] = 'Check response body for details.';
}

// Test 4: Test DNS resolution
$parsedUrl = parse_url(HolidayTaxisConfig::API_ENDPOINT);
$hostname = $parsedUrl['host'] ?? '';
if ($hostname) {
    $dnsRecord = gethostbyname($hostname);
    $results['dns_test'] = [
        'hostname' => $hostname,
        'resolved_ip' => $dnsRecord,
        'dns_working' => ($dnsRecord !== $hostname)
    ];
}

// Output results
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
