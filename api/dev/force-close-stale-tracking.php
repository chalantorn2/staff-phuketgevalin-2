<?php
// api/dev/force-close-stale-tracking.php
// Force-close driver_tracking_tokens that have been 'active' for >= N hours without completion.
// This is a LOCAL cleanup only - no Holiday Taxis API call is made.
// HT does not track "open" sessions; sending nothing simply means HT receives no further events.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

try {
    $minHours = isset($_GET['min_hours']) ? (int)$_GET['min_hours'] : 4;
    if ($minHours < 1) {
        $minHours = 1;
    }
    $dryRun = $_SERVER['REQUEST_METHOD'] !== 'POST';

    $db = new Database();
    $pdo = $db->getConnection();

    $selectSql = "SELECT t.id, t.booking_ref, t.assignment_id, t.started_at,
                         TIMESTAMPDIFF(MINUTE, t.started_at, NOW()) AS minutes_tracking,
                         d.name AS driver_name,
                         d.phone_number AS driver_phone,
                         v.registration AS vehicle_registration,
                         v.brand AS vehicle_brand,
                         v.model AS vehicle_model,
                         b.passenger_name,
                         b.passenger_phone,
                         b.pickup_date,
                         b.booking_type,
                         b.airport,
                         b.resort,
                         b.accommodation_name,
                         b.pickup_address1,
                         b.pickup_address2,
                         b.dropoff_address1,
                         b.dropoff_address2
                  FROM driver_tracking_tokens t
                  LEFT JOIN drivers d ON t.driver_id = d.id
                  LEFT JOIN vehicles v ON t.vehicle_id = v.id
                  LEFT JOIN bookings b ON t.booking_ref = b.booking_ref
                  WHERE t.status = 'active'
                    AND t.started_at IS NOT NULL
                    AND TIMESTAMPDIFF(HOUR, t.started_at, NOW()) >= :min_hours
                  ORDER BY t.started_at ASC";
    $stmt = $pdo->prepare($selectSql);
    $stmt->execute([':min_hours' => $minHours]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $affected = [
        'tokens' => 0,
        'assignments' => 0,
        'bookings' => 0
    ];

    if (!$dryRun && count($rows) > 0) {
        $pdo->beginTransaction();
        try {
            $tokenIds = array_column($rows, 'id');
            $assignmentIds = array_values(array_unique(array_column($rows, 'assignment_id')));
            $bookingRefs = array_values(array_unique(array_column($rows, 'booking_ref')));

            $tokenPlaceholders = implode(',', array_fill(0, count($tokenIds), '?'));
            $tokenUpdateSql = "UPDATE driver_tracking_tokens
                               SET status = 'completed',
                                   completed_at = NOW(),
                                   completion_type = 'FORCE_CLOSED'
                               WHERE id IN ($tokenPlaceholders)";
            $tokenStmt = $pdo->prepare($tokenUpdateSql);
            $tokenStmt->execute($tokenIds);
            $affected['tokens'] = $tokenStmt->rowCount();

            if (count($assignmentIds) > 0) {
                $aPlaceholders = implode(',', array_fill(0, count($assignmentIds), '?'));
                $aSql = "UPDATE driver_vehicle_assignments
                         SET status = 'completed',
                             completed_at = COALESCE(completed_at, NOW()),
                             completion_type = COALESCE(completion_type, 'FORCE_CLOSED')
                         WHERE id IN ($aPlaceholders)
                           AND status NOT IN ('completed', 'cancelled')";
                $aStmt = $pdo->prepare($aSql);
                $aStmt->execute($assignmentIds);
                $affected['assignments'] = $aStmt->rowCount();
            }

            if (count($bookingRefs) > 0) {
                $bPlaceholders = implode(',', array_fill(0, count($bookingRefs), '?'));
                $bSql = "UPDATE bookings
                         SET internal_status = 'completed',
                             completion_type = COALESCE(completion_type, 'FORCE_CLOSED')
                         WHERE booking_ref IN ($bPlaceholders)
                           AND (internal_status IS NULL OR internal_status <> 'completed')";
                $bStmt = $pdo->prepare($bSql);
                $bStmt->execute($bookingRefs);
                $affected['bookings'] = $bStmt->rowCount();
            }

            $pdo->commit();
        } catch (Exception $txError) {
            $pdo->rollBack();
            throw $txError;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'dry_run' => $dryRun,
            'min_hours' => $minHours,
            'candidates_count' => count($rows),
            'candidates' => array_map(function ($r) {
                $m = (int)$r['minutes_tracking'];
                $pickupParts = array_filter([
                    $r['pickup_address1'] ?? null,
                    $r['pickup_address2'] ?? null,
                    $r['airport'] ?? null,
                    $r['resort'] ?? null,
                    $r['accommodation_name'] ?? null
                ]);
                $dropoffParts = array_filter([
                    $r['dropoff_address1'] ?? null,
                    $r['dropoff_address2'] ?? null
                ]);
                return [
                    'token_id' => (int)$r['id'],
                    'booking_ref' => $r['booking_ref'],
                    'booking_type' => $r['booking_type'] ?? '',
                    'started_at' => $r['started_at'],
                    'minutes_tracking' => $m,
                    'duration_text' => floor($m / 60) . ' ชม. ' . ($m % 60) . ' นาที',
                    'driver_name' => $r['driver_name'] ?? '',
                    'driver_phone' => $r['driver_phone'] ?? '',
                    'vehicle' => trim(($r['vehicle_registration'] ?? '') . ' ' . ($r['vehicle_brand'] ?? '') . ' ' . ($r['vehicle_model'] ?? '')),
                    'passenger_name' => $r['passenger_name'] ?? '',
                    'passenger_phone' => $r['passenger_phone'] ?? '',
                    'pickup_date' => $r['pickup_date'],
                    'pickup_location' => implode(', ', $pickupParts),
                    'dropoff_location' => implode(', ', $dropoffParts),
                    'resort' => $r['resort'] ?? '',
                    'airport' => $r['airport'] ?? ''
                ];
            }, $rows),
            'affected' => $affected
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
