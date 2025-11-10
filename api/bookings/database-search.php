<?php
error_log("DEBUG: New database-search.php loaded - " . date('Y-m-d H:i:s'));
// api/bookings/database-search.php - Advanced Booking Search API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Pagination parameters
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    $offset = ($page - 1) * $limit;

    // Filter parameters
    $status = $_GET['status'] ?? 'all';
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $dateType = $_GET['date_type'] ?? 'pickup';
    $search = trim($_GET['search'] ?? '');
    $province = $_GET['province'] ?? 'all';
    $sortBy = $_GET['sort_by'] ?? 'pickup';
    $sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC');

    error_log("Search API Debug - Search term: '$search'");

    // Base WHERE clause
    $whereClause = "WHERE 1=1";
    $params = [];

    // Always apply 3-day filter UNLESS user specifically searches by date
    // This ensures we only show recent bookings by default
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
        error_log("3-day filter applied: YES");
    } else {
        error_log("3-day filter applied: NO (user has date filter or search)");
    }

    // Check if this is still default query (for limiting max results)
    $isDefaultQuery = empty($search) && empty($dateFrom) && empty($dateTo) &&
        ($status === 'all' || $status === 'ACON') &&
        $province === 'all';

    error_log("isDefaultQuery: " . ($isDefaultQuery ? 'true' : 'false') . " | search: '$search' | dateFrom: '$dateFrom' | dateTo: '$dateTo' | status: '$status' | province: '$province'");

    // Status filter (supports both booking status and assignment status)
    if ($status !== 'all') {
        if (strpos($status, 'assignment:') === 0) {
            // Assignment status filter
            $assignmentStatus = substr($status, 11); // Remove "assignment:" prefix
            if ($assignmentStatus === 'pending') {
                // Not assigned (no assignment record)
                $whereClause .= " AND dva.id IS NULL";
            } else {
                // Has assignment with specific status
                $whereClause .= " AND dva.status = ?";
                $params[] = $assignmentStatus;
            }
        } else {
            // Booking status filter
            $whereClause .= " AND b.ht_status = ?";
            $params[] = $status;
        }
    }

    // Date range filter
    if ($dateFrom || $dateTo) {
        if ($dateFrom && $dateTo) {
            if ($dateType === 'sync') {
                $whereClause .= " AND DATE(b.last_action_date) BETWEEN ? AND ?";
                $params[] = $dateFrom;
                $params[] = $dateTo;
            } else {
                // Check arrival_date, departure_date, or pickup_date
                // Use >= and < for accurate date filtering (includes full day)
                $dateToEnd = date('Y-m-d', strtotime($dateTo . ' +1 day'));
                $whereClause .= " AND (
                    (b.arrival_date >= ? AND b.arrival_date < ?)
                    OR (b.departure_date >= ? AND b.departure_date < ?)
                    OR (b.pickup_date >= ? AND b.pickup_date < ?)
                )";
                $params[] = $dateFrom . ' 00:00:00';
                $params[] = $dateToEnd . ' 00:00:00';
                $params[] = $dateFrom . ' 00:00:00';
                $params[] = $dateToEnd . ' 00:00:00';
                $params[] = $dateFrom . ' 00:00:00';
                $params[] = $dateToEnd . ' 00:00:00';
            }
        } elseif ($dateFrom) {
            if ($dateType === 'sync') {
                $whereClause .= " AND DATE(b.last_action_date) = ?";
                $params[] = $dateFrom;
            } else {
                // Use >= for single date (includes full day from 00:00:00)
                $dateFromEnd = date('Y-m-d', strtotime($dateFrom . ' +1 day'));
                $whereClause .= " AND (
                    (b.arrival_date >= ? AND b.arrival_date < ?)
                    OR (b.departure_date >= ? AND b.departure_date < ?)
                    OR (b.pickup_date >= ? AND b.pickup_date < ?)
                )";
                $params[] = $dateFrom . ' 00:00:00';
                $params[] = $dateFromEnd . ' 00:00:00';
                $params[] = $dateFrom . ' 00:00:00';
                $params[] = $dateFromEnd . ' 00:00:00';
                $params[] = $dateFrom . ' 00:00:00';
                $params[] = $dateFromEnd . ' 00:00:00';
            }
        } elseif ($dateTo) {
            if ($dateType === 'sync') {
                $whereClause .= " AND DATE(b.last_action_date) = ?";
                $params[] = $dateTo;
            } else {
                // Use < for single date (includes full day until 23:59:59)
                $dateToEnd = date('Y-m-d', strtotime($dateTo . ' +1 day'));
                $whereClause .= " AND (
                    (b.arrival_date >= ? AND b.arrival_date < ?)
                    OR (b.departure_date >= ? AND b.departure_date < ?)
                    OR (b.pickup_date >= ? AND b.pickup_date < ?)
                )";
                $params[] = $dateTo . ' 00:00:00';
                $params[] = $dateToEnd . ' 00:00:00';
                $params[] = $dateTo . ' 00:00:00';
                $params[] = $dateToEnd . ' 00:00:00';
                $params[] = $dateTo . ' 00:00:00';
                $params[] = $dateToEnd . ' 00:00:00';
            }
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

    $maxDefaultResults = 100;

    // Validate and sanitize sort parameters
    $validSortBy = ['pickup'];
    $validSortOrder = ['ASC', 'DESC'];

    if (!in_array($sortBy, $validSortBy)) {
        $sortBy = 'pickup';
    }
    if (!in_array($sortOrder, $validSortOrder)) {
        $sortOrder = 'ASC';
    }

    // Build ORDER BY clause
    $orderByClause = "ORDER BY b.pickup_date IS NULL, b.pickup_date $sortOrder, b.created_at DESC";

    // Get total count
    if ($isDefaultQuery) {
        $countSql = "SELECT COUNT(*) as total FROM (
            SELECT b.id FROM bookings b
            LEFT JOIN driver_vehicle_assignments dva ON b.booking_ref = dva.booking_ref
            $whereClause
            $orderByClause
            LIMIT $maxDefaultResults
        ) as limited_bookings";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = min($countStmt->fetch()['total'], $maxDefaultResults);
    } else {
        $countSql = "SELECT COUNT(*) as total FROM bookings b
                     LEFT JOIN driver_vehicle_assignments dva ON b.booking_ref = dva.booking_ref
                     $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch()['total'];
    }

    // Get paginated bookings
    if ($isDefaultQuery) {
        $sql = "SELECT * FROM (
            SELECT
                b.booking_ref,
                b.ht_status,
                b.passenger_name,
                b.passenger_phone,
                b.pax_total,
                b.adults,
                b.children,
                b.infants,
                b.province,
                b.province_source,
                b.province_confidence,
                b.booking_type,
                b.vehicle_type,
                b.airport,
                b.resort,
                b.accommodation_name,
                b.arrival_date,
                b.departure_date,
                b.pickup_date,
                b.flight_no_arrival,
                b.flight_no_departure,
                b.last_action_date,
                b.created_at,
                b.raw_data,
                CASE WHEN dva.id IS NOT NULL THEN 1 ELSE 0 END as is_assigned,
                dva.status as assignment_status
            FROM bookings b
            LEFT JOIN driver_vehicle_assignments dva ON b.booking_ref = dva.booking_ref
            $whereClause
            $orderByClause
            LIMIT $maxDefaultResults
        ) as limited_bookings
        ORDER BY pickup_date IS NULL, pickup_date $sortOrder, created_at DESC
        LIMIT ? OFFSET ?";
    } else {
        $sql = "SELECT
            b.booking_ref,
            b.ht_status,
            b.passenger_name,
            b.passenger_phone,
            b.pax_total,
            b.adults,
            b.children,
            b.infants,
            b.province,
            b.province_source,
            b.province_confidence,
            b.booking_type,
            b.vehicle_type,
            b.airport,
            b.resort,
            b.accommodation_name,
            b.arrival_date,
            b.departure_date,
            b.pickup_date,
            b.flight_no_arrival,
            b.flight_no_departure,
            b.last_action_date,
            b.created_at,
            b.raw_data,
            CASE WHEN dva.id IS NOT NULL THEN 1 ELSE 0 END as is_assigned,
            dva.status as assignment_status
        FROM bookings b
        LEFT JOIN driver_vehicle_assignments dva ON b.booking_ref = dva.booking_ref
        $whereClause
        $orderByClause
        LIMIT ? OFFSET ?";
    }

    $allParams = $params;
    $allParams[] = (int)$limit;
    $allParams[] = (int)$offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($allParams);
    $bookings = $stmt->fetchAll();

    // Format response
    $formattedBookings = array_map(function ($booking) {
        $vehicle = '-';
        $passengerPhone = $booking['passenger_phone'];

        if (!empty($booking['raw_data'])) {
            $rawData = json_decode($booking['raw_data'], true);
            if ($rawData && isset($rawData['vehicle'])) {
                $vehicle = $rawData['vehicle'];
            }
            if (empty($passengerPhone) && isset($rawData['passengertelno'])) {
                $passengerPhone = $rawData['passengertelno'];
            }
        }

        // Check if booking is older than 3 days
        $isOldBooking = false;
        if ($booking['pickup_date'] && $booking['pickup_date'] !== '0000-00-00 00:00:00') {
            $pickupDate = strtotime($booking['pickup_date']);
            $threeDaysAgo = strtotime('-3 days');
            $isOldBooking = $pickupDate < $threeDaysAgo;
        }

        return [
            'ref' => $booking['booking_ref'],
            'status' => $booking['ht_status'],
            'passenger' => [
                'name' => $booking['passenger_name'] ?? '-',
                'phone' => $passengerPhone
            ],
            'pax' => (int)$booking['pax_total'] ?? 1,
            'paxDetails' => [
                'adults' => (int)($booking['adults'] ?? 1),
                'children' => (int)($booking['children'] ?? 0),
                'infants' => (int)($booking['infants'] ?? 0)
            ],
            'bookingType' => $booking['booking_type'] ?? 'N/A',
            'airport' => $booking['airport'] ?? 'N/A',
            'province' => $booking['province'] ?? null,
            'province_source' => $booking['province_source'] ?? 'unknown',
            'province_confidence' => $booking['province_confidence'] ?? 'low',
            'arrivalDate' => ($booking['arrival_date'] && $booking['arrival_date'] !== '0000-00-00 00:00:00') ? $booking['arrival_date'] : null,
            'departureDate' => ($booking['departure_date'] && $booking['departure_date'] !== '0000-00-00 00:00:00') ? $booking['departure_date'] : null,
            'pickupDate' => ($booking['pickup_date'] && $booking['pickup_date'] !== '0000-00-00 00:00:00') ? $booking['pickup_date'] : null,
            'flightNoArrival' => $booking['flight_no_arrival'] ?? null,
            'flightNoDeparture' => $booking['flight_no_departure'] ?? null,
            'vehicle' => $booking['vehicle_type'] ?? $vehicle,
            'resort' => $booking['resort'] ?? 'N/A',
            'accommodation' => [
                'name' => $booking['accommodation_name']
            ],
            'lastActionDate' => $booking['last_action_date'],
            'createdAt' => $booking['created_at'],
            'is_assigned' => (int)$booking['is_assigned'],
            'assignment_status' => $booking['assignment_status'] ?? null,
            'is_old_booking' => $isOldBooking
        ];
    }, $bookings);

    $totalPages = ceil($totalRecords / $limit);

    $response = [
        'success' => true,
        'data' => [
            'bookings' => $formattedBookings,
            'pagination' => [
                'current_page' => (int)$page,
                'total_pages' => $totalPages,
                'total_records' => (int)$totalRecords,
                'per_page' => (int)$limit,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
                'is_default_query' => $isDefaultQuery,
                'max_default_results' => $isDefaultQuery ? $maxDefaultResults : null
            ],
            'filters' => [
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'date_type' => $dateType,
                'search' => $search,
                'province' => $province,
                'limit' => (int)$limit
            ],
            'lastUpdate' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
