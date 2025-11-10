<?php
// api/assignments/assign.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Method');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

function sendResponse($success, $data = null, $message = '', $code = 200)
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    if (isset($_SERVER['HTTP_X_METHOD'])) {
        $method = $_SERVER['HTTP_X_METHOD'];
    }

    $db = new Database();
    $pdo = $db->getConnection();

    switch ($method) {
        case 'GET':
            // ดึงข้อมูล assignment
            $bookingRef = $_GET['booking_ref'] ?? null;
            if (!$bookingRef) {
                sendResponse(false, null, 'Booking reference required', 400);
            }

            $sql = "SELECT a.*, a.completion_type,
                           d.name as driver_name, d.phone_number as driver_phone,
                           v.registration, v.brand, v.model,
                           s.full_name as assigned_by_name
                    FROM driver_vehicle_assignments a
                    LEFT JOIN drivers d ON a.driver_id = d.id
                    LEFT JOIN vehicles v ON a.vehicle_id = v.id
                    LEFT JOIN staff_users s ON a.assigned_by = s.id
                    WHERE a.booking_ref = :ref
                    ORDER BY a.assigned_at DESC
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ref' => $bookingRef]);
            $assignment = $stmt->fetch();

            sendResponse(true, $assignment, 'Assignment retrieved');
            break;

        case 'POST':
            // มอบหมายงาน
            $bookingRef = $input['booking_ref'] ?? null;
            $driverId = $input['driver_id'] ?? null;
            $vehicleId = $input['vehicle_id'] ?? null;
            $notes = $input['notes'] ?? '';
            $assignedBy = $input['assigned_by'] ?? null; // TODO: ใช้จาก session

            if (!$bookingRef || !$driverId || !$vehicleId) {
                sendResponse(false, null, 'Booking ref, driver and vehicle required', 400);
            }

            // ตรวจสอบ booking status - ต้องเป็น ACON (Confirmed) เท่านั้น
            $bookingSql = "SELECT ht_status FROM bookings WHERE booking_ref = :ref";
            $bookingStmt = $pdo->prepare($bookingSql);
            $bookingStmt->execute([':ref' => $bookingRef]);
            $booking = $bookingStmt->fetch();

            if (!$booking) {
                sendResponse(false, null, 'Booking not found', 404);
            }

            // อนุญาตให้ ACON (Confirmed) และ AAMM (Amendment Approved) assign job ได้
            $allowedStatuses = ['ACON', 'AAMM'];
            if (!in_array($booking['ht_status'], $allowedStatuses)) {
                $statusMap = [
                    'PCON' => 'Pending Confirmation',
                    'ACAN' => 'Cancelled',
                    'PCAN' => 'Pending Cancellation',
                    'PAMM' => 'Pending Amendment',
                    'AAMM' => 'Amendment Approved'
                ];
                $statusName = $statusMap[$booking['ht_status']] ?? $booking['ht_status'];
                sendResponse(false, null, "Cannot assign job. Booking status is '{$statusName}'. Only confirmed bookings (ACON) or amendment approved bookings (AAMM) can be assigned.", 400);
            }

            // ตรวจสอบว่ามี assignment อยู่แล้วหรือไม่
            $checkSql = "SELECT id FROM driver_vehicle_assignments WHERE booking_ref = :ref";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':ref' => $bookingRef]);

            if ($checkStmt->fetch()) {
                sendResponse(false, null, 'Job already assigned. Use reassign instead.', 400);
            }

            // ดึง vehicle identifier
            $vehicleSql = "SELECT registration FROM vehicles WHERE id = :id";
            $vehicleStmt = $pdo->prepare($vehicleSql);
            $vehicleStmt->execute([':id' => $vehicleId]);
            $vehicle = $vehicleStmt->fetch();

            if (!$vehicle) {
                sendResponse(false, null, 'Vehicle not found', 404);
            }

            // Insert assignment
            $sql = "INSERT INTO driver_vehicle_assignments 
                    (booking_ref, driver_id, vehicle_id, vehicle_identifier, 
                     assignment_notes, assigned_by, status, assigned_at)
                    VALUES (:ref, :driver_id, :vehicle_id, :vehicle_identifier, 
                            :notes, :assigned_by, 'assigned', NOW())";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':ref' => $bookingRef,
                ':driver_id' => $driverId,
                ':vehicle_id' => $vehicleId,
                ':vehicle_identifier' => $vehicle['registration'],
                ':notes' => $notes,
                ':assigned_by' => $assignedBy
            ]);

            if ($result) {
                sendResponse(true, ['id' => $pdo->lastInsertId()], 'Job assigned successfully');
            } else {
                sendResponse(false, null, 'Failed to assign job', 500);
            }
            break;

        case 'PUT':
            // Reassign
            $assignmentId = $input['assignment_id'] ?? null;
            $driverId = $input['driver_id'] ?? null;
            $vehicleId = $input['vehicle_id'] ?? null;
            $notes = $input['notes'] ?? '';

            if (!$assignmentId || !$driverId || !$vehicleId) {
                sendResponse(false, null, 'Assignment ID, driver and vehicle required', 400);
            }

            // ดึงข้อมูล assignment เดิมเพื่อตรวจสอบ status
            $oldAssignmentSql = "SELECT status, booking_ref, vehicle_identifier, driver_id, vehicle_id
                                 FROM driver_vehicle_assignments
                                 WHERE id = :id";
            $oldAssignmentStmt = $pdo->prepare($oldAssignmentSql);
            $oldAssignmentStmt->execute([':id' => $assignmentId]);
            $oldAssignment = $oldAssignmentStmt->fetch();

            if (!$oldAssignment) {
                sendResponse(false, null, 'Assignment not found', 404);
            }

            // ตรวจสอบว่าคนขับหรือรถเปลี่ยนไปหรือไม่
            $isDriverChanged = ($oldAssignment['driver_id'] != $driverId);
            $isVehicleChanged = ($oldAssignment['vehicle_id'] != $vehicleId);

            // ถ้าคนขับเดิมเริ่มงานแล้ว (status = 'in_progress') และมีการเปลี่ยนคนขับหรือรถ
            // ต้องส่ง DELETE request ไปที่ Holiday Taxis ก่อน
            if ($oldAssignment['status'] === 'in_progress' && ($isDriverChanged || $isVehicleChanged)) {
                try {
                    require_once '../config/holiday-taxis.php';

                    $deleteUrl = HolidayTaxisConfig::API_ENDPOINT .
                        "/bookings/{$oldAssignment['booking_ref']}/vehicles/{$oldAssignment['vehicle_identifier']}";

                    $headers = [
                        "API_KEY: " . HolidayTaxisConfig::API_KEY,
                        "VERSION: " . HolidayTaxisConfig::API_VERSION
                    ];

                    // Log request for debugging
                    error_log("HT Vehicle DELETE Request: " . json_encode([
                        'url' => $deleteUrl,
                        'reason' => 'Driver/Vehicle reassignment during in_progress status',
                        'booking_ref' => $oldAssignment['booking_ref'],
                        'old_vehicle_identifier' => $oldAssignment['vehicle_identifier']
                    ]));

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $deleteUrl,
                        CURLOPT_HTTPHEADER => $headers,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 15,
                        CURLOPT_CUSTOMREQUEST => 'DELETE',
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    // Log response
                    error_log("HT Vehicle DELETE Response: HTTP {$httpCode}, cURL Error: {$curlError}, Response: {$response}");

                    // หาก DELETE ไม่สำเร็จ ให้เตือนแต่ยังดำเนินการต่อ
                    if ($curlError || !in_array($httpCode, [200, 204, 404])) {
                        error_log("Warning: Failed to delete old vehicle from Holiday Taxis. HTTP {$httpCode}: {$response}");
                        // 404 = vehicle ไม่เคยถูกส่งไป หรือถูกลบไปแล้ว (ไม่เป็นไร)
                        // Continue with reassignment anyway
                    }
                } catch (Exception $deleteError) {
                    // Log error แต่ยังทำงานต่อ เพราะ local assignment ยังต้องทำ
                    error_log("Holiday Taxis DELETE exception: " . $deleteError->getMessage());
                }
            }

            // ดึงข้อมูลรถใหม่
            $vehicleSql = "SELECT registration FROM vehicles WHERE id = :id";
            $vehicleStmt = $pdo->prepare($vehicleSql);
            $vehicleStmt->execute([':id' => $vehicleId]);
            $vehicle = $vehicleStmt->fetch();

            if (!$vehicle) {
                sendResponse(false, null, 'Vehicle not found', 404);
            }

            // UPDATE assignment ในฐานข้อมูล
            $sql = "UPDATE driver_vehicle_assignments
                    SET driver_id = :driver_id,
                        vehicle_id = :vehicle_id,
                        vehicle_identifier = :vehicle_identifier,
                        assignment_notes = :notes,
                        assigned_at = NOW()
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':id' => $assignmentId,
                ':driver_id' => $driverId,
                ':vehicle_id' => $vehicleId,
                ':vehicle_identifier' => $vehicle['registration'],
                ':notes' => $notes
            ]);

            if ($result) {
                $message = 'Job reassigned successfully';
                if ($oldAssignment['status'] === 'in_progress' && ($isDriverChanged || $isVehicleChanged)) {
                    $message .= ' (Old driver/vehicle deallocated from Holiday Taxis)';
                }
                sendResponse(true, null, $message);
            } else {
                sendResponse(false, null, 'Failed to reassign job', 500);
            }
            break;

        case 'DELETE':
            // Unassign
            $assignmentId = $_GET['id'] ?? $input['id'] ?? null;

            if (!$assignmentId) {
                sendResponse(false, null, 'Assignment ID required', 400);
            }

            $sql = "DELETE FROM driver_vehicle_assignments WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([':id' => $assignmentId]);

            if ($result) {
                sendResponse(true, null, 'Assignment removed successfully');
            } else {
                sendResponse(false, null, 'Failed to remove assignment', 500);
            }
            break;

        default:
            sendResponse(false, null, 'Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Assignment API error: " . $e->getMessage());
    sendResponse(false, null, 'Server error: ' . $e->getMessage(), 500);
}
