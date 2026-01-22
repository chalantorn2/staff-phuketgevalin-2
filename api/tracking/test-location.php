<?php
// api/tracking/test-location.php - Send Location Update to TEST/STAGING Environment
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $token = $input['token'] ?? null;
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    $accuracy = $input['accuracy'] ?? null;
    $status = $input['status'] ?? 'BEFORE_PICKUP';

    if (!$token || !$latitude || !$longitude) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token, latitude, and longitude are required']);
        exit;
    }

    // Validate status
    $validStatuses = ['BEFORE_PICKUP', 'WAITING_FOR_CUSTOMER', 'AFTER_PICKUP', 'COMPLETED', 'NO_SHOW'];
    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }

    $db = new Database();
    $pdo = $db->getConnection();

    // Get tracking token info
    $sql = "SELECT t.*, b.pickup_date, b.arrival_date, b.departure_date
            FROM driver_tracking_tokens t
            LEFT JOIN bookings b ON t.booking_ref = b.booking_ref
            WHERE t.token = :token";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token]);
    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Token not found']);
        exit;
    }

    // Check if tracking is active
    if ($tokenData['status'] !== 'active') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tracking not active. Please start the job first.']);
        exit;
    }

    // Save location to database
    $insertSql = "INSERT INTO driver_location_logs
                  (token_id, booking_ref, latitude, longitude, accuracy, tracking_status, tracked_at)
                  VALUES (:token_id, :booking_ref, :lat, :lng, :accuracy, :status, NOW())";

    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        ':token_id' => $tokenData['id'],
        ':booking_ref' => $tokenData['booking_ref'],
        ':lat' => $latitude,
        ':lng' => $longitude,
        ':accuracy' => $accuracy,
        ':status' => $status
    ]);

    $locationId = $pdo->lastInsertId();

    // ===== IMPORTANT: Send to TEST/STAGING Environment =====
    $syncSuccess = false;
    $syncError = null;
    $httpCode = null;
    $curlError = null;
    $fullResponse = null;

    try {
        $locationData = [
            'timestamp' => gmdate('Y-m-d\TH:i:s') . '+00:00',
            'location' => [
                'lat' => (float)$latitude,
                'lng' => (float)$longitude
            ],
            'status' => $status
        ];

        // USE TEST/STAGING ENDPOINT
        $testApiUrl = HolidayTaxisConfig::TEST_API_ENDPOINT . "/bookings/{$tokenData['booking_ref']}/vehicles/{$tokenData['vehicle_identifier']}/location";
        $testHeaders = [
            "API_KEY: " . HolidayTaxisConfig::TEST_API_KEY,
            "Content-Type: application/json",
            "Accept: application/json",
            "VERSION: " . HolidayTaxisConfig::TEST_API_VERSION
        ];

        // Log request for debugging
        error_log("HT TEST Location API Request: " . json_encode([
            'url' => $testApiUrl,
            'data' => $locationData,
            'headers' => $testHeaders
        ]));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testApiUrl,
            CURLOPT_HTTPHEADER => $testHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($locationData),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        // Log response for debugging
        error_log("HT TEST Location API Response: HTTP {$httpCode}, cURL Error: {$curlError}, Response: {$response}");

        // Determine success/failure
        if ($curlError) {
            $syncSuccess = false;
            $syncError = "cURL Error: {$curlError}";
            $fullResponse = $syncError;
        } elseif ($httpCode === 0) {
            $syncSuccess = false;
            $syncError = "No HTTP response (connection failed)";
            $fullResponse = $syncError;
        } elseif (in_array($httpCode, [200, 201, 204])) {
            $syncSuccess = true;
            $syncError = null;
            $fullResponse = $response;
        } else {
            $syncSuccess = false;
            $syncError = "HTTP {$httpCode}: {$response}";
            $fullResponse = $response;
        }

        // Update location log
        $updateLogSql = "UPDATE driver_location_logs
                        SET synced_to_holidaytaxis = :synced,
                            sync_response = :response,
                            sync_http_code = :code
                        WHERE id = :id";
        $updateLogStmt = $pdo->prepare($updateLogSql);
        $updateLogStmt->execute([
            ':synced' => $syncSuccess ? 1 : 0,
            ':response' => $syncError ?? $response ?? 'No response',
            ':code' => $httpCode,
            ':id' => $locationId
        ]);
    } catch (Exception $syncException) {
        $syncError = "Exception: " . $syncException->getMessage();
        $fullResponse = $syncError;
        error_log("HT TEST Location API Exception: " . $syncException->getMessage());
    }

    // Update tracking token
    $updateSql = "UPDATE driver_tracking_tokens
                  SET last_location_at = NOW(),
                      total_locations_sent = total_locations_sent + 1
                  WHERE token = :token";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([':token' => $token]);

    // Return detailed response for testing
    echo json_encode([
        'success' => true,
        'data' => [
            'location_saved' => true,
            'synced_to_holidaytaxis' => $syncSuccess,
            'sync_error' => $syncError,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'total_locations_sent' => (int)$tokenData['total_locations_sent'] + 1,
            'message' => 'Location updated successfully (TEST MODE)',
            'debug_info' => [
                'booking_ref' => $tokenData['booking_ref'],
                'vehicle_identifier' => $tokenData['vehicle_identifier'],
                'environment' => 'TEST/STAGING',
                'endpoint' => HolidayTaxisConfig::TEST_API_ENDPOINT,
                'api_response' => $fullResponse ? json_decode($fullResponse, true) : null
            ]
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    error_log("TEST Location Update API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
