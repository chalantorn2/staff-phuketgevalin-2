<?php
// api/assignments/force-complete.php
// Staff manually marks an assignment as COMPLETED or NO_SHOW.
// Local-only update. Does NOT call Holiday Taxis - HT has no concept of
// out-of-band session closure; absence of further POST /location is enough.

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
    $bookingRef = $input['booking_ref'] ?? null;
    $completionType = $input['completion_type'] ?? null;

    if (!$bookingRef) {
        throw new Exception('booking_ref is required');
    }
    if (!in_array($completionType, ['COMPLETED', 'NO_SHOW'], true)) {
        throw new Exception('completion_type must be COMPLETED or NO_SHOW');
    }

    $db = new Database();
    $pdo = $db->getConnection();

    $pdo->beginTransaction();

    $assignStmt = $pdo->prepare(
        "SELECT id, status FROM driver_vehicle_assignments
         WHERE booking_ref = :ref AND status <> 'cancelled'
         ORDER BY id DESC LIMIT 1"
    );
    $assignStmt->execute([':ref' => $bookingRef]);
    $assignment = $assignStmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        throw new Exception('No active assignment found for this booking');
    }

    $tokenSql = "UPDATE driver_tracking_tokens
                 SET status = 'completed',
                     completed_at = COALESCE(completed_at, NOW()),
                     completion_type = :ctype
                 WHERE assignment_id = :aid";
    $tokenStmt = $pdo->prepare($tokenSql);
    $tokenStmt->execute([
        ':ctype' => $completionType,
        ':aid' => $assignment['id']
    ]);
    $tokensUpdated = $tokenStmt->rowCount();

    $aSql = "UPDATE driver_vehicle_assignments
             SET status = 'completed',
                 completed_at = COALESCE(completed_at, NOW()),
                 completion_type = :ctype
             WHERE id = :id";
    $aStmt = $pdo->prepare($aSql);
    $aStmt->execute([
        ':ctype' => $completionType,
        ':id' => $assignment['id']
    ]);
    $assignmentUpdated = $aStmt->rowCount();

    $bSql = "UPDATE bookings
             SET internal_status = 'completed',
                 completion_type = :ctype
             WHERE booking_ref = :ref";
    $bStmt = $pdo->prepare($bSql);
    $bStmt->execute([
        ':ctype' => $completionType,
        ':ref' => $bookingRef
    ]);
    $bookingUpdated = $bStmt->rowCount();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $completionType === 'NO_SHOW'
            ? 'Marked as No Show'
            : 'Marked as Completed',
        'data' => [
            'booking_ref' => $bookingRef,
            'completion_type' => $completionType,
            'assignment_id' => (int)$assignment['id'],
            'rows_affected' => [
                'tokens' => $tokensUpdated,
                'assignments' => $assignmentUpdated,
                'bookings' => $bookingUpdated
            ]
        ]
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
