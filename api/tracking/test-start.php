<?php
// api/tracking/test-start.php - Start Tracking Job (TEST/STAGING Environment)
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
                'message' => 'Job already started (TEST MODE)',
                'environment' => 'TEST/STAGING'
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

    // ===== IMPORTANT: Send driver and vehicle info to TEST/STAGING Environment =====
    $vehicleSyncSuccess = false;
    $vehicleSyncError = null;
    $vehicleHttpCode = null;
    $vehicleResponse = null;

    try {
        // Build vehicle description with office contact
        $vehicleDescription = 'Office Contact: +66937376128 [TEST]';

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

        // USE TEST/STAGING ENDPOINT
        $testApiUrl = HolidayTaxisConfig::TEST_API_ENDPOINT . "/bookings/{$tokenData['booking_ref']}/vehicles/{$tokenData['vehicle_identifier']}";
        $testHeaders = [
            "API_KEY: " . HolidayTaxisConfig::TEST_API_KEY,
            "Content-Type: application/json",
            "Accept: application/json",
            "VERSION: " . HolidayTaxisConfig::TEST_API_VERSION
        ];

        // Log request for debugging
        error_log("HT TEST Vehicle Sync Request: " . json_encode([
            'url' => $testApiUrl,
            'data' => $driverData,
            'headers' => $testHeaders
        ]));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testApiUrl,
            CURLOPT_HTTPHEADER => $testHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($driverData),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $vehicleResponse = curl_exec($ch);
        $vehicleHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Log response for debugging
        error_log("HT TEST Vehicle Sync Response: HTTP {$vehicleHttpCode}, cURL Error: {$curlError}, Response: {$vehicleResponse}");

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
            $vehicleSyncError = "HTTP {$vehicleHttpCode}: {$vehicleResponse}";
        }
    } catch (Exception $syncError) {
        $vehicleSyncError = "Exception: " . $syncError->getMessage();
        error_log("Holiday Taxis TEST sync exception: " . $syncError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
            'tracking_interval' => (int)$tokenData['tracking_interval'],
            'message' => 'Tracking started successfully (TEST MODE)',
            'environment' => 'TEST/STAGING',
            'vehicle_sync' => [
                'success' => $vehicleSyncSuccess,
                'error' => $vehicleSyncError,
                'http_code' => $vehicleHttpCode,
                'response' => $vehicleResponse ? json_decode($vehicleResponse, true) : null
            ],
            'debug_info' => [
                'booking_ref' => $tokenData['booking_ref'],
                'vehicle_identifier' => $tokenData['vehicle_identifier'],
                'endpoint' => HolidayTaxisConfig::TEST_API_ENDPOINT,
                'driver_data_sent' => $driverData ?? null
            ]
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    error_log("TEST Start Tracking API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
