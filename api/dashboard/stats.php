<?php
// api/dashboard/stats.php - Fixed Dashboard Stats API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Calculate date range (last 7 days)
    $dateFrom = date('Y-m-d H:i:s', strtotime('-7 days'));
    $dateTo = date('Y-m-d H:i:s');

    // Get booking stats
    $sql = "SELECT 
                ht_status,
                COUNT(*) as count
            FROM bookings 
            WHERE last_action_date BETWEEN :date_from AND :date_to
            GROUP BY ht_status";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':date_from' => $dateFrom,
        ':date_to' => $dateTo
    ]);

    $results = $stmt->fetchAll();

    // Initialize stats with actual Holiday Taxis statuses
    $stats = [
        'newBookings' => 0,    // PCON
        'confirmed' => 0,      // ACON  
        'cancelled' => 0,      // ACAN
        'amendments' => 0      // PAMM + AAMM
    ];

    // Map results to stats
    foreach ($results as $row) {
        switch ($row['ht_status']) {
            case 'PCON':
                $stats['newBookings'] = (int)$row['count'];
                break;
            case 'ACON':
                $stats['confirmed'] = (int)$row['count'];
                break;
            case 'ACAN':
                $stats['cancelled'] = (int)$row['count'];
                break;
            case 'PAMM':
            case 'AAMM':
                $stats['amendments'] += (int)$row['count'];
                break;
        }
    }

    // Get total bookings today
    $sqlToday = "SELECT COUNT(*) as today_total 
                FROM bookings 
                WHERE DATE(created_at) = CURDATE()";

    $stmtToday = $pdo->prepare($sqlToday);
    $stmtToday->execute();
    $todayResult = $stmtToday->fetch();

    $response = [
        'success' => true,
        'data' => [
            'stats' => $stats,
            'totalToday' => (int)$todayResult['today_total'],
            'dateRange' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'lastUpdate' => date('Y-m-d H:i:s')
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
