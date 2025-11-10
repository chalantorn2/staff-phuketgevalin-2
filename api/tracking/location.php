<?php
// api/tracking/location.php - Send Location Update
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

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

    // Get tracking token info with booking details
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

    // Check token expiration based on pickup_date + 3 days
    $pickupDate = $tokenData['pickup_date'] ?? $tokenData['arrival_date'] ?? $tokenData['departure_date'];
    if ($pickupDate) {
        $expirationTime = strtotime($pickupDate . ' +3 days');
        if (time() > $expirationTime) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Token has expired (more than 3 days after pickup date)']);
            exit;
        }
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

    // Send to Holiday Taxis API
    $syncSuccess = false;
    $syncError = null;
    $httpCode = null;
    $curlError = null;

    try {
        require_once '../config/holiday-taxis.php';

        $locationData = [
            'timestamp' => gmdate('Y-m-d\TH:i:s') . '+00:00',
            'location' => [
                'lat' => (float)$latitude,
                'lng' => (float)$longitude
            ],
            'status' => $status
        ];

        $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$tokenData['booking_ref']}/vehicles/{$tokenData['vehicle_identifier']}/location";
        $headers = [
            "API_KEY: " . HolidayTaxisConfig::API_KEY,
            "Content-Type: application/json",
            "Accept: application/json",
            "VERSION: " . HolidayTaxisConfig::API_VERSION
        ];

        // Log request for debugging
        error_log("HT Location API Request: " . json_encode([
            'url' => $apiUrl,
            'data' => $locationData,
            'headers' => $headers
        ]));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_HTTPHEADER => $headers,
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
        error_log("HT Location API Response: HTTP {$httpCode}, cURL Error: {$curlError}, Response: {$response}");

        // Determine success/failure
        if ($curlError) {
            // cURL error (network, DNS, SSL, timeout, etc.)
            $syncSuccess = false;
            $syncError = "cURL Error: {$curlError}";
        } elseif ($httpCode === 0) {
            // No HTTP response (connection failed)
            $syncSuccess = false;
            $syncError = "No HTTP response (connection failed)";
        } elseif (in_array($httpCode, [200, 201, 204])) {
            // Success
            $syncSuccess = true;
            $syncError = null;
        } else {
            // HTTP error (4xx, 5xx)
            $syncSuccess = false;
            $syncError = "HTTP {$httpCode}: {$response}";
        }

        // Update location log with detailed info (always update, not just on success)
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
        error_log("HT Location API Exception: " . $syncException->getMessage());
    }

    // Update tracking token
    $updateSql = "UPDATE driver_tracking_tokens
                  SET last_location_at = NOW(),
                      total_locations_sent = total_locations_sent + 1
                  WHERE token = :token";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([':token' => $token]);

    echo json_encode([
        'success' => true,
        'data' => [
            'location_saved' => true,
            'synced_to_holidaytaxis' => $syncSuccess,
            'sync_error' => $syncError,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'total_locations_sent' => (int)$tokenData['total_locations_sent'] + 1,
            'message' => 'Location updated successfully',
            'debug_info' => [
                'booking_ref' => $tokenData['booking_ref'],
                'vehicle_identifier' => $tokenData['vehicle_identifier']
            ]
        ]
    ]);
} catch (Exception $e) {
    error_log("Location Update API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
