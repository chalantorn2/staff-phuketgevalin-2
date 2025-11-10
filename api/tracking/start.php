<?php
// api/tracking/start.php - Start Tracking Job
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

    if (!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token is required']);
        exit;
    }

    $db = new Database();
    $pdo = $db->getConnection();

    // Get tracking token info with booking details
    $sql = "SELECT t.*, d.name as driver_name, d.phone_number as driver_phone, d.license_number,
                   v.registration, v.brand, v.model, v.color,
                   b.pickup_date, b.arrival_date, b.departure_date
            FROM driver_tracking_tokens t
            LEFT JOIN drivers d ON t.driver_id = d.id
            LEFT JOIN vehicles v ON t.vehicle_id = v.id
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

    // Validate date range: Can start from 5 hours before pickup to +24 hours after
    $pickupDate = $tokenData['pickup_date'] ?? $tokenData['arrival_date'] ?? $tokenData['departure_date'];

    if ($pickupDate) {
        $pickupTimestamp = strtotime($pickupDate);
        $now = time();

        // Calculate allowed range: 5 hours before pickup to +24 hours after pickup
        $startAllowed = $pickupTimestamp - (5 * 60 * 60); // 5 hours before pickup
        $endAllowed = $pickupTimestamp + (24 * 60 * 60); // +24 hours from pickup time

        if ($now < $startAllowed) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Cannot start job yet. You can start from 5 hours before the pickup time.',
                'can_start_at' => date('Y-m-d H:i:s', $startAllowed),
                'pickup_time' => date('Y-m-d H:i:s', $pickupTimestamp)
            ]);
            exit;
        }

        if ($now > $endAllowed) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Job time window has passed (24 hours from pickup time). Please contact support if you need to start this job.',
                'pickup_time' => date('Y-m-d H:i:s', $pickupTimestamp),
                'window_end' => date('Y-m-d H:i:s', $endAllowed)
            ]);
            exit;
        }

        // Check if link itself has expired (pickup_date + 3 days)
        $linkExpiration = $pickupTimestamp + (3 * 24 * 60 * 60);
        if ($now > $linkExpiration) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Link has expired (more than 3 days after pickup date)',
                'pickup_date' => date('Y-m-d H:i:s', $pickupTimestamp),
                'expired_at' => date('Y-m-d H:i:s', $linkExpiration)
            ]);
            exit;
        }
    }

    // Check if already completed
    if ($tokenData['status'] === 'completed') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'This job has already been completed',
            'completed_at' => $tokenData['completed_at'],
            'status' => 'completed'
        ]);
        exit;
    }

    // Check if already started
    if ($tokenData['status'] === 'active') {
        echo json_encode([
            'success' => true,
            'data' => [
                'status' => 'active',
                'started_at' => $tokenData['started_at'],
                'tracking_interval' => (int)$tokenData['tracking_interval'],
                'message' => 'Job already started'
            ]
        ]);
        exit;
    }

    // Start tracking
    $updateSql = "UPDATE driver_tracking_tokens
                  SET status = 'active', started_at = NOW()
                  WHERE token = :token";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([':token' => $token]);

    // Update assignment status to in_progress
    $updateAssignmentSql = "UPDATE driver_vehicle_assignments
                           SET status = 'in_progress'
                           WHERE id = :assignment_id";
    $updateAssignmentStmt = $pdo->prepare($updateAssignmentSql);
    $updateAssignmentStmt->execute([':assignment_id' => $tokenData['assignment_id']]);

    // Update booking internal_status
    $updateBookingSql = "UPDATE bookings
                        SET internal_status = 'in_progress'
                        WHERE booking_ref = :booking_ref";
    $updateBookingStmt = $pdo->prepare($updateBookingSql);
    $updateBookingStmt->execute([':booking_ref' => $tokenData['booking_ref']]);

    // Send driver and vehicle info to Holiday Taxis API
    $vehicleSyncSuccess = false;
    $vehicleSyncError = null;
    $vehicleHttpCode = null;

    try {
        require_once '../config/holiday-taxis.php';

        // Build vehicle description with office contact
        $vehicleDescription = 'Office Contact: +66937376128';

        // Add existing description if available (though currently not stored in DB)
        // If you want to add more info, you can append here

        $driverData = [
            'driver' => [
                'name' => $tokenData['driver_name'],
                'phoneNumber' => $tokenData['driver_phone'],
                'preferredContactMethod' => 'VOICE',
                'contactMethods' => ['VOICE', 'SMS', 'WHATSAPP']
            ],
            'vehicle' => [
                'brand' => $tokenData['brand'],
                'model' => $tokenData['model'],
                'color' => $tokenData['color'] ?? 'Unknown',
                'registration' => $tokenData['registration'],
                'description' => $vehicleDescription
            ]
        ];

        // Add license number if available
        if (!empty($tokenData['license_number'])) {
            $driverData['driver']['licenseNumber'] = $tokenData['license_number'];
        }

        $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$tokenData['booking_ref']}/vehicles/{$tokenData['vehicle_identifier']}";
        $headers = [
            "API_KEY: " . HolidayTaxisConfig::API_KEY,
            "Content-Type: application/json",
            "Accept: application/json",
            "VERSION: " . HolidayTaxisConfig::API_VERSION
        ];

        // Log request for debugging
        error_log("HT Vehicle Sync Request: " . json_encode([
            'url' => $apiUrl,
            'data' => $driverData,
            'headers' => $headers
        ]));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($driverData),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $vehicleHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Log response for debugging
        error_log("HT Vehicle Sync Response: HTTP {$vehicleHttpCode}, cURL Error: {$curlError}, Response: {$response}");

        // Determine success/failure
        if ($curlError) {
            $vehicleSyncSuccess = false;
            $vehicleSyncError = "cURL Error: {$curlError}";
        } elseif ($vehicleHttpCode === 0) {
            $vehicleSyncSuccess = false;
            $vehicleSyncError = "No HTTP response (connection failed)";
        } elseif (in_array($vehicleHttpCode, [200, 201, 204])) {
            $vehicleSyncSuccess = true;
        } else {
            $vehicleSyncSuccess = false;
            $vehicleSyncError = "HTTP {$vehicleHttpCode}: {$response}";
        }
    } catch (Exception $syncError) {
        // Log but continue - local tracking still works
        $vehicleSyncError = "Exception: " . $syncError->getMessage();
        error_log("Holiday Taxis sync exception: " . $syncError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
            'tracking_interval' => (int)$tokenData['tracking_interval'],
            'message' => 'Tracking started successfully',
            'vehicle_sync' => [
                'success' => $vehicleSyncSuccess,
                'error' => $vehicleSyncError,
                'http_code' => $vehicleHttpCode
            ],
            'debug_info' => [
                'booking_ref' => $tokenData['booking_ref'],
                'vehicle_identifier' => $tokenData['vehicle_identifier']
            ]
        ]
    ]);
} catch (Exception $e) {
    error_log("Start Tracking API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
