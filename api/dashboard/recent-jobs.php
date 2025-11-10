<?php
// api/dashboard/recent-jobs.php - With Pagination
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $limit = $_GET['limit'] ?? 10;
    $page = $_GET['page'] ?? 1;
    $status = $_GET['status'] ?? 'all';

    $offset = ($page - 1) * $limit;

    // Build WHERE clause for last 7 days
    $whereClause = "WHERE last_action_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $params = [];

    if ($status !== 'all') {
        $whereClause .= " AND ht_status = :status";
        $params[':status'] = $status;
    }

    // Get total count first
    $countSql = "SELECT COUNT(*) as total FROM bookings $whereClause";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch()['total'];

    // Get paginated jobs
    $sql = "SELECT 
                booking_ref,
                ht_status,
                passenger_name,
                passenger_phone,
                pax_total,
                pickup_date,
                resort,
                accommodation_name,
                last_action_date,
                created_at,
                raw_data
            FROM bookings 
            $whereClause
            ORDER BY last_action_date DESC 
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $jobs = $stmt->fetchAll();

    // Format response - อ่าน vehicle จาก raw_data
    $formattedJobs = array_map(function ($job) {
        $vehicle = '-';
        $passengerPhone = $job['passenger_phone'];

        if (!empty($job['raw_data'])) {
            $rawData = json_decode($job['raw_data'], true);
            if ($rawData && isset($rawData['vehicle'])) {
                $vehicle = $rawData['vehicle'];
            }
            if (empty($passengerPhone) && isset($rawData['passengertelno'])) {
                $passengerPhone = $rawData['passengertelno'];
            }
        }

        return [
            'ref' => $job['booking_ref'],
            'status' => $job['ht_status'],
            'passenger' => [
                'name' => $job['passenger_name'] ?? '-',
                'phone' => $passengerPhone
            ],
            'pax' => (int)$job['pax_total'] ?? 1,
            'pickupDate' => $job['pickup_date'],
            'vehicle' => $vehicle,
            'resort' => $job['resort'],
            'accommodation' => [
                'name' => $job['accommodation_name']
            ],
            'lastActionDate' => $job['last_action_date'],
            'createdAt' => $job['created_at']
        ];
    }, $jobs);

    $totalPages = ceil($totalRecords / $limit);

    $response = [
        'success' => true,
        'data' => [
            'bookings' => $formattedJobs,
            'pagination' => [
                'current_page' => (int)$page,
                'total_pages' => $totalPages,
                'total_records' => (int)$totalRecords,
                'per_page' => (int)$limit,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'filters' => [
                'status' => $status,
                'limit' => (int)$limit
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
