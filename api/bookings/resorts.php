<?php
// api/bookings/resorts.php - Get All Unique Resorts from Database with Filtered Counts
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $db = new Database();
    $pdo = $db->getConnection();

    // Get filter parameters (same as database-search.php)
    $source = $_GET['source'] ?? 'bookings'; // 'bookings' or 'assignments'
    $bookingStatuses = $_GET['booking_status'] ?? [];
    $assignmentStatuses = $_GET['assignment_status'] ?? [];
    $bookingTypes = $_GET['booking_type'] ?? [];
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $search = trim($_GET['search'] ?? '');
    $province = $_GET['province'] ?? 'all';

    // Build WHERE clause
    $whereClause = "WHERE 1=1";
    $params = [];

    // Apply 3-day filter UNLESS user specifically searches by date
    $apply3DayFilter = empty($dateFrom) && empty($dateTo) && empty($search);

    if ($apply3DayFilter) {
        $whereClause .= " AND (
            (b.pickup_date > DATE_SUB(CURDATE(), INTERVAL 3 DAY) AND b.pickup_date IS NOT NULL AND b.pickup_date != '0000-00-00 00:00:00')
            OR (b.arrival_date > DATE_SUB(CURDATE(), INTERVAL 3 DAY) AND b.arrival_date IS NOT NULL AND b.arrival_date != '0000-00-00 00:00:00')
            OR (b.departure_date > DATE_SUB(CURDATE(), INTERVAL 3 DAY) AND b.departure_date IS NOT NULL AND b.departure_date != '0000-00-00 00:00:00')
            OR (
                (b.pickup_date IS NULL OR b.pickup_date = '0000-00-00 00:00:00')
                AND (b.arrival_date IS NULL OR b.arrival_date = '0000-00-00 00:00:00')
                AND (b.departure_date IS NULL OR b.departure_date = '0000-00-00 00:00:00')
            )
        )";
    }

    // Booking Status filter
    if (!empty($bookingStatuses) && is_array($bookingStatuses)) {
        $placeholders = implode(',', array_fill(0, count($bookingStatuses), '?'));
        $whereClause .= " AND b.ht_status IN ($placeholders)";
        foreach ($bookingStatuses as $status) {
            $params[] = $status;
        }
    }

    // Assignment Status filter
    if (!empty($assignmentStatuses) && is_array($assignmentStatuses)) {
        $assignmentConditions = [];
        foreach ($assignmentStatuses as $status) {
            if ($status === 'assigned') {
                $assignmentConditions[] = "EXISTS (SELECT 1 FROM driver_vehicle_assignments dva WHERE dva.booking_ref = b.booking_ref)";
            } elseif ($status === 'not_assigned') {
                $assignmentConditions[] = "NOT EXISTS (SELECT 1 FROM driver_vehicle_assignments dva WHERE dva.booking_ref = b.booking_ref)";
            }
        }
        if (!empty($assignmentConditions)) {
            $whereClause .= " AND (" . implode(' OR ', $assignmentConditions) . ")";
        }
    }

    // Booking Type filter
    if (!empty($bookingTypes) && is_array($bookingTypes)) {
        $typeConditions = [];
        foreach ($bookingTypes as $type) {
            if ($type === 'arrival') {
                $typeConditions[] = "(b.arrival_date IS NOT NULL AND b.arrival_date != '0000-00-00 00:00:00' AND (b.departure_date IS NULL OR b.departure_date = '0000-00-00 00:00:00'))";
            } elseif ($type === 'departure') {
                $typeConditions[] = "(b.departure_date IS NOT NULL AND b.departure_date != '0000-00-00 00:00:00' AND (b.arrival_date IS NULL OR b.arrival_date = '0000-00-00 00:00:00'))";
            } elseif ($type === 'p2p') {
                $typeConditions[] = "((b.arrival_date IS NULL OR b.arrival_date = '0000-00-00 00:00:00') AND (b.departure_date IS NULL OR b.departure_date = '0000-00-00 00:00:00'))";
            }
        }
        if (!empty($typeConditions)) {
            $whereClause .= " AND (" . implode(' OR ', $typeConditions) . ")";
        }
    }

    // Date range filter
    if ($dateFrom || $dateTo) {
        if ($dateFrom && $dateTo) {
            $dateToEnd = date('Y-m-d', strtotime($dateTo . ' +1 day'));
            $whereClause .= " AND b.pickup_date >= ? AND b.pickup_date < ?";
            $params[] = $dateFrom . ' 00:00:00';
            $params[] = $dateToEnd . ' 00:00:00';
        } elseif ($dateFrom) {
            $dateFromEnd = date('Y-m-d', strtotime($dateFrom . ' +1 day'));
            $whereClause .= " AND b.pickup_date >= ? AND b.pickup_date < ?";
            $params[] = $dateFrom . ' 00:00:00';
            $params[] = $dateFromEnd . ' 00:00:00';
        } elseif ($dateTo) {
            $dateToEnd = date('Y-m-d', strtotime($dateTo . ' +1 day'));
            $whereClause .= " AND b.pickup_date >= ? AND b.pickup_date < ?";
            $params[] = $dateTo . ' 00:00:00';
            $params[] = $dateToEnd . ' 00:00:00';
        }
    }

    // Search filter
    if (!empty($search)) {
        $whereClause .= " AND (LOWER(b.booking_ref) LIKE LOWER(?) OR LOWER(b.passenger_name) LIKE LOWER(?))";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Province filter
    if ($province !== 'all') {
        if ($province === 'unknown' || $province === 'null') {
            $whereClause .= " AND b.province IS NULL";
        } else {
            $whereClause .= " AND b.province = ?";
            $params[] = $province;
        }
    }

    // Get unique resorts with booking counts (filtered)
    if ($source === 'assignments') {
        // For Job Assignments page - only get resorts from assigned bookings
        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(b.resort), ''), NULLIF(TRIM(b.accommodation_name), '')) as resort_name,
                COUNT(DISTINCT a.id) as booking_count
            FROM driver_vehicle_assignments a
            LEFT JOIN bookings b ON a.booking_ref = b.booking_ref
            $whereClause
                AND a.status != 'cancelled'
                AND COALESCE(NULLIF(TRIM(b.resort), ''), NULLIF(TRIM(b.accommodation_name), '')) IS NOT NULL
                AND COALESCE(NULLIF(TRIM(b.resort), ''), NULLIF(TRIM(b.accommodation_name), '')) != ''
                AND COALESCE(NULLIF(TRIM(b.resort), ''), NULLIF(TRIM(b.accommodation_name), '')) != 'N/A'
            GROUP BY resort_name
            ORDER BY resort_name ASC
        ";
    } else {
        // For Booking Management page - get all resorts
        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(b.resort), ''), NULLIF(TRIM(b.accommodation_name), '')) as resort_name,
                COUNT(*) as booking_count
            FROM bookings b
            $whereClause
                AND COALESCE(NULLIF(TRIM(b.resort), ''), NULLIF(TRIM(b.accommodation_name), '')) IS NOT NULL
                AND COALESCE(NULLIF(TRIM(b.resort), ''), NULLIF(TRIM(b.accommodation_name), '')) != ''
                AND COALESCE(NULLIF(TRIM(b.resort), ''), NULLIF(TRIM(b.accommodation_name), '')) != 'N/A'
            GROUP BY resort_name
            ORDER BY resort_name ASC
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter out any remaining empty or invalid entries and format data
    $resorts = [];
    foreach ($results as $row) {
        $resortName = trim($row['resort_name']);
        if (!empty($resortName) && $resortName !== 'N/A') {
            $resorts[] = [
                'name' => $resortName,
                'count' => (int)$row['booking_count']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $resorts,
        'total' => count($resorts)
    ]);
} catch (Exception $e) {
    error_log("Resorts API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
