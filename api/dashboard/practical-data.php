<?php
// api/dashboard/practical-data.php - Practical Dashboard Data API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // === 1. TODAY & TOMORROW OVERVIEW ===
    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');
    $tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));
    $tomorrowEnd = date('Y-m-d 23:59:59', strtotime('+1 day'));

    // Today's pickups (only confirmed and amended)
    $todayPickupsSql = "SELECT COUNT(*) as total FROM bookings
                        WHERE pickup_date BETWEEN :start AND :end
                        AND ht_status IN ('ACON', 'AAMM')";
    $stmt = $pdo->prepare($todayPickupsSql);
    $stmt->execute([':start' => $todayStart, ':end' => $todayEnd]);
    $todayPickups = (int)$stmt->fetch()['total'];

    // Tomorrow's pickups (only confirmed and amended)
    $stmt->execute([':start' => $tomorrowStart, ':end' => $tomorrowEnd]);
    $tomorrowPickups = (int)$stmt->fetch()['total'];

    // Unassigned jobs (today + tomorrow) - Check via driver_vehicle_assignments table
    $unassignedSql = "SELECT COUNT(*) as total FROM bookings b
                      WHERE b.pickup_date BETWEEN :today AND :tomorrow_end
                      AND b.ht_status NOT IN ('ACAN', 'PCAN')
                      AND NOT EXISTS (
                          SELECT 1 FROM driver_vehicle_assignments a
                          WHERE a.booking_ref = b.booking_ref
                          AND a.status != 'cancelled'
                      )";
    $stmt = $pdo->prepare($unassignedSql);
    $stmt->execute([
        ':today' => $todayStart,
        ':tomorrow_end' => $tomorrowEnd
    ]);
    $unassignedJobs = (int)$stmt->fetch()['total'];

    // Active tracking - Check via driver_tracking_tokens table
    $activeTrackingSql = "SELECT COUNT(DISTINCT a.id) as total
                          FROM driver_vehicle_assignments a
                          INNER JOIN driver_tracking_tokens t ON a.id = t.assignment_id
                          INNER JOIN bookings b ON a.booking_ref = b.booking_ref
                          WHERE t.status = 'active'
                          AND a.status != 'cancelled'
                          AND b.pickup_date >= CURDATE()";
    $stmt = $pdo->prepare($activeTrackingSql);
    $stmt->execute();
    $activeTracking = (int)$stmt->fetch()['total'];

    // Completed today - Check tracking completed today
    $completedTodaySql = "SELECT COUNT(DISTINCT a.id) as total
                          FROM driver_vehicle_assignments a
                          INNER JOIN driver_tracking_tokens t ON a.id = t.assignment_id
                          INNER JOIN bookings b ON a.booking_ref = b.booking_ref
                          WHERE t.status = 'completed'
                          AND DATE(t.completed_at) = CURDATE()
                          AND a.status != 'cancelled'";
    $stmt = $pdo->prepare($completedTodaySql);
    $stmt->execute();
    $completedToday = (int)$stmt->fetch()['total'];

    // === 2. CRITICAL JOBS (Urgent Assignments Needed) ===
    $now = date('Y-m-d H:i:s');
    $next3Hours = date('Y-m-d H:i:s', strtotime('+3 hours'));

    $criticalJobsSql = "SELECT
                            b.booking_ref,
                            b.ht_status,
                            b.passenger_name,
                            b.pickup_date,
                            b.province,
                            b.vehicle_type,
                            b.pax_total,
                            b.accommodation_name,
                            CASE
                                WHEN b.pickup_date <= :next3hours THEN 'urgent'
                                WHEN DATE(b.pickup_date) = CURDATE() THEN 'today'
                                ELSE 'normal'
                            END as urgency_level
                        FROM bookings b
                        WHERE b.pickup_date BETWEEN :now AND :today_end
                        AND b.ht_status NOT IN ('ACAN', 'PCAN')
                        AND NOT EXISTS (
                            SELECT 1 FROM driver_vehicle_assignments a
                            WHERE a.booking_ref = b.booking_ref
                            AND a.status != 'cancelled'
                        )
                        ORDER BY b.pickup_date ASC
                        LIMIT 20";

    $stmt = $pdo->prepare($criticalJobsSql);
    $stmt->execute([
        ':now' => $now,
        ':next3hours' => $next3Hours,
        ':today_end' => $todayEnd
    ]);
    $criticalJobs = $stmt->fetchAll();

    // === 3. RECENT UPCOMING BOOKINGS (Next 7 days) ===
    $next7Days = date('Y-m-d 23:59:59', strtotime('+7 days'));

    $recentBookingsSql = "SELECT
                            b.booking_ref,
                            b.ht_status,
                            b.passenger_name,
                            b.pickup_date,
                            b.province,
                            b.vehicle_type,
                            b.pax_total,
                            b.synced_at,
                            b.created_at
                        FROM bookings b
                        WHERE b.pickup_date BETWEEN :now AND :next7days
                        AND b.ht_status NOT IN ('ACAN', 'PCAN')
                        ORDER BY b.pickup_date ASC
                        LIMIT 15";

    $stmt = $pdo->prepare($recentBookingsSql);
    $stmt->execute([
        ':now' => $now,
        ':next7days' => $next7Days
    ]);
    $recentBookings = $stmt->fetchAll();

    // === 4. ISSUES COUNT ===
    // Missing province
    $missingProvinceSql = "SELECT COUNT(*) as total FROM bookings
                           WHERE pickup_date >= CURDATE()
                           AND ht_status NOT IN ('ACAN', 'PCAN')
                           AND (province IS NULL OR province = '')";
    $stmt = $pdo->prepare($missingProvinceSql);
    $stmt->execute();
    $missingProvince = (int)$stmt->fetch()['total'];

    // Missing flight info (for airport transfers)
    $missingFlightSql = "SELECT COUNT(*) as total FROM bookings
                         WHERE pickup_date >= CURDATE()
                         AND ht_status NOT IN ('ACAN', 'PCAN')
                         AND airport IS NOT NULL
                         AND airport != ''
                         AND (flight_no_arrival IS NULL OR flight_no_arrival = '')
                         AND (flight_no_departure IS NULL OR flight_no_departure = '')";
    $stmt = $pdo->prepare($missingFlightSql);
    $stmt->execute();
    $missingFlight = (int)$stmt->fetch()['total'];

    // Total issues
    $totalIssues = $missingProvince + $missingFlight;

    // === 5. LAST SYNC TIME ===
    $lastSyncSql = "SELECT MAX(completed_at) as last_sync FROM sync_status WHERE status = 'completed'";
    $stmt = $pdo->prepare($lastSyncSql);
    $stmt->execute();
    $lastSync = $stmt->fetch()['last_sync'];

    // === BUILD RESPONSE ===
    $response = [
        'success' => true,
        'data' => [
            'overview' => [
                'today_pickups' => $todayPickups,
                'tomorrow_pickups' => $tomorrowPickups,
                'unassigned_jobs' => $unassignedJobs,
                'active_tracking' => $activeTracking,
                'completed_today' => $completedToday
            ],
            'critical_jobs' => array_map(function ($job) {
                return [
                    'ref' => $job['booking_ref'],
                    'status' => $job['ht_status'],
                    'passenger' => $job['passenger_name'] ?? '-',
                    'pickup_date' => $job['pickup_date'],
                    'province' => $job['province'] ?? '-',
                    'vehicle' => $job['vehicle_type'],
                    'pax' => (int)$job['pax_total'],
                    'accommodation' => $job['accommodation_name'] ?? '-',
                    'urgency' => $job['urgency_level']
                ];
            }, $criticalJobs),
            'recent_bookings' => array_map(function ($booking) {
                return [
                    'ref' => $booking['booking_ref'],
                    'status' => $booking['ht_status'],
                    'passenger' => $booking['passenger_name'] ?? '-',
                    'pickup_date' => $booking['pickup_date'],
                    'province' => $booking['province'] ?? '-',
                    'vehicle' => $booking['vehicle_type'],
                    'pax' => (int)$booking['pax_total'],
                    'synced_at' => $booking['synced_at'],
                    'created_at' => $booking['created_at']
                ];
            }, $recentBookings),
            'issues' => [
                'total' => $totalIssues,
                'missing_province' => $missingProvince,
                'missing_flight' => $missingFlight
            ],
            'last_sync' => $lastSync,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
