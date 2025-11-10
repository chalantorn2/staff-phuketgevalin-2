<?php
// api/tracking/complete.php - Complete Tracking Job
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
    $status = $input['status'] ?? 'COMPLETED';
    $notes = $input['notes'] ?? '';

    if (!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token is required']);
        exit;
    }

    // Validate status
    $validStatuses = ['COMPLETED', 'NO_SHOW'];
    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Status must be COMPLETED or NO_SHOW']);
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

    // Check if already completed
    if ($tokenData['status'] === 'completed') {
        echo json_encode([
            'success' => true,
            'data' => [
                'status' => 'completed',
                'completed_at' => $tokenData['completed_at'],
                'message' => 'Job already completed'
            ]
        ]);
        exit;
    }

    // Send final location to Holiday Taxis API (if we have location data)
    if (!empty($input['latitude']) && !empty($input['longitude'])) {
        try {
            require_once '../config/holiday-taxis.php';

            $locationData = [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'location' => [
                    'lat' => (float)$input['latitude'],
                    'lng' => (float)$input['longitude']
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

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($locationData),
                CURLOPT_FOLLOWLOCATION => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Save final location to database
            if (in_array($httpCode, [200, 201, 204])) {
                $insertSql = "INSERT INTO driver_location_logs
                             (token_id, booking_ref, latitude, longitude, tracking_status, tracked_at, synced_to_holidaytaxis, sync_http_code)
                             VALUES (:token_id, :booking_ref, :lat, :lng, :status, NOW(), TRUE, :code)";
                $insertStmt = $pdo->prepare($insertSql);
                $insertStmt->execute([
                    ':token_id' => $tokenData['id'],
                    ':booking_ref' => $tokenData['booking_ref'],
                    ':lat' => $input['latitude'],
                    ':lng' => $input['longitude'],
                    ':status' => $status,
                    ':code' => $httpCode
                ]);
            }
        } catch (Exception $syncError) {
            error_log("Holiday Taxis final sync error: " . $syncError->getMessage());
        }
    }

    // Calculate duration
    $startedAt = strtotime($tokenData['started_at']);
    $completedAt = time();
    $durationMinutes = round(($completedAt - $startedAt) / 60);

    // Update tracking token to completed with completion_type
    $updateSql = "UPDATE driver_tracking_tokens
                  SET status = 'completed',
                      completed_at = NOW(),
                      completion_type = :completion_type
                  WHERE token = :token";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([
        ':token' => $token,
        ':completion_type' => $status
    ]);

    // Update assignment status with completion_type
    $updateAssignmentSql = "UPDATE driver_vehicle_assignments
                           SET status = 'completed',
                               completed_at = NOW(),
                               completion_type = :completion_type
                           WHERE id = :id";
    $updateAssignmentStmt = $pdo->prepare($updateAssignmentSql);
    $updateAssignmentStmt->execute([
        ':id' => $tokenData['assignment_id'],
        ':completion_type' => $status
    ]);

    // Update bookings table - only set internal_status, NOT ht_status
    // ht_status is reserved for Holiday Taxis API responses only
    $updateBookingSql = "UPDATE bookings
                         SET internal_status = 'completed'
                         WHERE booking_ref = :booking_ref";

    error_log("Updating booking {$tokenData['booking_ref']} with completion_type: $status (internal_status=completed)");

    $updateBookingStmt = $pdo->prepare($updateBookingSql);
    $updateBookingStmt->execute([':booking_ref' => $tokenData['booking_ref']]);

    $rowsAffected = $updateBookingStmt->rowCount();
    error_log("Booking update affected $rowsAffected rows");

    echo json_encode([
        'success' => true,
        'data' => [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'total_duration_minutes' => $durationMinutes,
            'total_locations_sent' => (int)$tokenData['total_locations_sent'],
            'completion_type' => $status,
            'booking_ref' => $tokenData['booking_ref'],
            'rows_updated' => $rowsAffected,
            'message' => 'Job completed successfully'
        ]
    ]);
} catch (Exception $e) {
    error_log("Complete Tracking API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
