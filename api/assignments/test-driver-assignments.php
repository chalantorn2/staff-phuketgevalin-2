<?php
// api/assignments/test-driver-assignments.php
// Test script to debug missing assignments in message generation

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

try {
    // Get driver_id from query parameter
    $driverId = $_GET['driver_id'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;

    if (!$driverId) {
        echo json_encode(['error' => 'driver_id is required']);
        exit;
    }

    $db = new Database();
    $pdo = $db->getConnection();

    // Build WHERE clause
    $where = ["a.driver_id = :driver_id", "a.status != 'cancelled'"];
    $params = [':driver_id' => $driverId];

    if ($dateFrom) {
        $where[] = "DATE(COALESCE(b.pickup_date, b.arrival_date, b.departure_date)) = :date_from";
        $params[':date_from'] = $dateFrom;
    }

    $whereClause = "WHERE " . implode(' AND ', $where);

    // Get all assignments for this driver (including No Tracking ones)
    $sql = "SELECT
                a.id,
                a.booking_ref,
                a.status,
                a.has_tracking,
                a.tracking_token as assignment_tracking_token,
                t.token as tracking_table_token,
                t.status as tracking_status,
                t.expires_at as tracking_expires,
                COALESCE(t.token, a.tracking_token) as final_token,
                GREATEST(COALESCE(a.has_tracking, 0), IF(t.token IS NOT NULL, 1, 0)) as final_has_tracking,
                b.passenger_name,
                b.pickup_date,
                b.accommodation_name,
                b.airport,
                d.name as driver_name
            FROM driver_vehicle_assignments a
            LEFT JOIN drivers d ON a.driver_id = d.id
            LEFT JOIN driver_tracking_tokens t ON a.id = t.assignment_id
                AND t.expires_at > NOW()
                AND t.id = (
                    SELECT t2.id
                    FROM driver_tracking_tokens t2
                    WHERE t2.assignment_id = a.id
                    AND t2.expires_at > NOW()
                    ORDER BY t2.created_at DESC
                    LIMIT 1
                )
            LEFT JOIN bookings b ON a.booking_ref = b.booking_ref
            {$whereClause}
            ORDER BY COALESCE(b.pickup_date, b.arrival_date, b.departure_date) ASC";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Separate by tracking status
    $withTracking = [];
    $withoutTracking = [];

    foreach ($assignments as $assignment) {
        if ($assignment['final_has_tracking']) {
            $withTracking[] = $assignment;
        } else {
            $withoutTracking[] = $assignment;
        }
    }

    // Return detailed analysis
    echo json_encode([
        'success' => true,
        'driver_id' => $driverId,
        'date_filter' => $dateFrom,
        'summary' => [
            'total_assignments' => count($assignments),
            'with_tracking' => count($withTracking),
            'without_tracking' => count($withoutTracking)
        ],
        'assignments' => [
            'all' => $assignments,
            'with_tracking' => $withTracking,
            'without_tracking' => $withoutTracking
        ],
        'analysis' => [
            'message' => count($withoutTracking) > 0
                ? "⚠️ Found " . count($withoutTracking) . " assignment(s) WITHOUT tracking that might be missing from message!"
                : "✅ All assignments have tracking tokens"
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
