<?php
// api/assignments/generate-link.php - Generate Tracking Link for Driver
error_reporting(E_ALL);
ini_set('display_errors', 1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
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

function generateSecureToken($length = 64)
{
    return bin2hex(random_bytes($length / 2));
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, 'Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendResponse(false, null, 'Invalid JSON', 400);
    }

    $bookingRef = $input['booking_ref'] ?? null;
    $assignmentId = $input['assignment_id'] ?? null;

    if (!$bookingRef || !$assignmentId) {
        sendResponse(false, null, 'booking_ref and assignment_id are required', 400);
    }

    $db = new Database();
    $pdo = $db->getConnection();

    // Get assignment details with booking pickup date
    $sql = "SELECT a.*, d.name as driver_name, d.phone_number as driver_phone,
                   v.id as vehicle_id, v.registration, v.brand, v.model,
                   b.pickup_date, b.arrival_date, b.departure_date,
                   b.passenger_name, b.passenger_phone
            FROM driver_vehicle_assignments a
            LEFT JOIN drivers d ON a.driver_id = d.id
            LEFT JOIN vehicles v ON a.vehicle_id = v.id
            LEFT JOIN bookings b ON a.booking_ref = b.booking_ref
            WHERE a.id = :id AND a.booking_ref = :ref";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $assignmentId, ':ref' => $bookingRef]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        sendResponse(false, null, 'Assignment not found', 404);
    }

    // Determine the pickup date (use pickup_date, or arrival_date as fallback)
    $pickupDate = $assignment['pickup_date'] ?? $assignment['arrival_date'] ?? $assignment['departure_date'];

    if (!$pickupDate) {
        sendResponse(false, null, 'Pickup date not found for this booking', 400);
    }

    // Check if token already exists and is still valid
    $checkSql = "SELECT token, expires_at, status
                 FROM driver_tracking_tokens
                 WHERE assignment_id = :id
                 AND expires_at > NOW()
                 ORDER BY created_at DESC
                 LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $assignmentId]);
    $existingToken = $checkStmt->fetch();

    if ($existingToken && $existingToken['status'] === 'pending') {
        // Return existing token if still valid and not started
        $trackingUrl = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https://' : 'http://';
        $trackingUrl .= $_SERVER['HTTP_HOST'] . '/track.html?token=' . $existingToken['token'];

        sendResponse(true, [
            'token' => $existingToken['token'],
            'tracking_url' => $trackingUrl,
            'expires_at' => $existingToken['expires_at'],
            'pickup_date' => $pickupDate,
            'interval' => 30,
            'driver_name' => $assignment['driver_name'],
            'passenger_name' => $assignment['passenger_name'] ?? '',
            'vehicle' => $assignment['registration'] . ' - ' . $assignment['brand'] . ' ' . $assignment['model'],
            'is_existing' => true
        ], 'Using existing valid tracking link');
    }

    // Generate new token
    $token = generateSecureToken(64);

    // Set expiration to 3 days after pickup date
    $expiresAt = date('Y-m-d H:i:s', strtotime($pickupDate . ' +3 days'));

    // Insert new tracking token
    $insertSql = "INSERT INTO driver_tracking_tokens
                  (token, booking_ref, assignment_id, driver_id, vehicle_id, vehicle_identifier, expires_at)
                  VALUES (:token, :booking_ref, :assignment_id, :driver_id, :vehicle_id, :vehicle_identifier, :expires_at)";

    $insertStmt = $pdo->prepare($insertSql);
    $result = $insertStmt->execute([
        ':token' => $token,
        ':booking_ref' => $bookingRef,
        ':assignment_id' => $assignmentId,
        ':driver_id' => $assignment['driver_id'],
        ':vehicle_id' => $assignment['vehicle_id'],
        ':vehicle_identifier' => $assignment['registration'],
        ':expires_at' => $expiresAt
    ]);

    if (!$result) {
        sendResponse(false, null, 'Failed to create tracking token', 500);
    }

    // Update assignment with tracking token
    $updateSql = "UPDATE driver_vehicle_assignments
                  SET has_tracking = TRUE, tracking_token = :token
                  WHERE id = :id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([':token' => $token, ':id' => $assignmentId]);

    // Build tracking URL
    $trackingUrl = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https://' : 'http://';
    $trackingUrl .= $_SERVER['HTTP_HOST'] . '/track.html?token=' . $token;

    sendResponse(true, [
        'token' => $token,
        'tracking_url' => $trackingUrl,
        'expires_at' => $expiresAt,
        'pickup_date' => $pickupDate,
        'interval' => 30,
        'driver_name' => $assignment['driver_name'],
        'passenger_name' => $assignment['passenger_name'] ?? '',
        'vehicle' => $assignment['registration'] . ' - ' . $assignment['brand'] . ' ' . $assignment['model']
    ], 'Tracking link generated successfully');

} catch (Exception $e) {
    error_log("Generate Link API error: " . $e->getMessage());
    sendResponse(false, null, 'Server error: ' . $e->getMessage(), 500);
}
