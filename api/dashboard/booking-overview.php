<?php
// api/dashboard/booking-overview.php - Booking Overview API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get period from query parameter
    $period = isset($_GET['period']) ? $_GET['period'] : 'week';

    // Calculate date range based on period
    $today = date('Y-m-d');
    $startDate = '';

    switch ($period) {
        case 'week':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'month':
            $startDate = date('Y-m-01'); // First day of current month
            break;
        case '30days':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            break;
        default:
            $startDate = date('Y-m-d', strtotime('-7 days'));
    }

    $endDate = date('Y-m-d 23:59:59');

    // === 1. STATS ===
    $statsSql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN ht_status = 'ACON' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN ht_status IN ('PCON', 'PAMM', 'AAMM') THEN 1 ELSE 0 END) as inProgress,
                    SUM(CASE WHEN ht_status IN ('ACAN', 'PCAN') THEN 1 ELSE 0 END) as cancelled
                 FROM bookings
                 WHERE pickup_date BETWEEN :start AND :end";

    $stmt = $pdo->prepare($statsSql);
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Convert to int
    $stats = [
        'total' => (int)$stats['total'],
        'completed' => (int)$stats['completed'],
        'inProgress' => (int)$stats['inProgress'],
        'cancelled' => (int)$stats['cancelled']
    ];

    // === 2. CHART DATA (Daily Bookings) ===
    $chartSql = "SELECT
                    DATE(pickup_date) as date,
                    COUNT(*) as count
                 FROM bookings
                 WHERE pickup_date BETWEEN :start AND :end
                 GROUP BY DATE(pickup_date)
                 ORDER BY DATE(pickup_date) ASC";

    $stmt = $pdo->prepare($chartSql);
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert count to int
    $chartData = array_map(function($item) {
        return [
            'date' => $item['date'],
            'count' => (int)$item['count']
        ];
    }, $chartData);

    // === 3. STATUS DISTRIBUTION ===
    $statusSql = "SELECT
                    ht_status as status,
                    COUNT(*) as count
                  FROM bookings
                  WHERE pickup_date BETWEEN :start AND :end
                  GROUP BY ht_status
                  ORDER BY count DESC";

    $stmt = $pdo->prepare($statusSql);
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $statusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert count to int
    $statusDistribution = array_map(function($item) {
        return [
            'status' => $item['status'],
            'count' => (int)$item['count']
        ];
    }, $statusDistribution);

    // === 4. TOP 5 ROUTES ===
    // Use airport, accommodation_name, and province to build routes
    $routesSql = "SELECT
                    COALESCE(airport, '') as pickup_location,
                    COALESCE(accommodation_name, resort, '') as dropoff_location,
                    province,
                    COUNT(*) as count
                  FROM bookings
                  WHERE pickup_date BETWEEN :start AND :end
                  AND (airport IS NOT NULL OR accommodation_name IS NOT NULL OR resort IS NOT NULL)
                  GROUP BY pickup_location, dropoff_location, province
                  ORDER BY count DESC
                  LIMIT 5";

    $stmt = $pdo->prepare($routesSql);
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $topRoutes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert count to int and format location names
    $topRoutes = array_map(function($item) {
        $pickup = !empty($item['pickup_location']) ? $item['pickup_location'] : 'Unknown';
        $dropoff = !empty($item['dropoff_location']) ? $item['dropoff_location'] : 'Unknown';

        return [
            'pickup_location' => $pickup,
            'dropoff_location' => $dropoff,
            'province' => $item['province'],
            'count' => (int)$item['count']
        ];
    }, $topRoutes);

    // === 5. RECENT BOOKINGS (Last 10) ===
    $recentSql = "SELECT
                    booking_ref,
                    pickup_date,
                    COALESCE(airport, '') as pickup_location,
                    COALESCE(accommodation_name, resort, '') as dropoff_location,
                    passenger_name as lead_passenger,
                    ht_status
                  FROM bookings
                  WHERE pickup_date BETWEEN :start AND :end
                  ORDER BY created_at DESC
                  LIMIT 10";

    $stmt = $pdo->prepare($recentSql);
    $stmt->execute([':start' => $startDate, ':end' => $endDate]);
    $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return response
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'chartData' => $chartData,
            'statusDistribution' => $statusDistribution,
            'topRoutes' => $topRoutes,
            'recentBookings' => $recentBookings,
            'period' => $period,
            'dateRange' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
