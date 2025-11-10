<?php
// api/bookings/export-data.php - Export Full Booking Data (Aligned with database-search.php)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get query parameters (with separate assignment_status)
    // Support comma-separated values for status and province (checkbox filters)
    $statusParam = isset($_GET['status']) ? $_GET['status'] : 'all';
    $status = ($statusParam === 'all' || empty($statusParam)) ? [] : explode(',', $statusParam);

    $assignmentStatus = isset($_GET['assignment_status']) ? $_GET['assignment_status'] : 'all';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $provinceParam = isset($_GET['province']) ? $_GET['province'] : 'all';
    $province = ($provinceParam === 'all' || empty($provinceParam)) ? [] : explode(',', $provinceParam);

    $format = isset($_GET['format']) ? $_GET['format'] : 'json';

    // Build query with LEFT JOIN for assignments
    $sql = "SELECT
                b.booking_ref,
                b.ht_status,
                b.booking_type,
                b.pickup_date,
                b.accommodation_name,
                b.resort,
                b.airport,
                b.from_airport,
                b.to_airport,
                b.arrival_date,
                b.departure_date,
                b.pickup_address1,
                b.dropoff_address1,
                b.passenger_name as lead_passenger,
                b.pax_total,
                COALESCE(b.flight_no_arrival, b.flight_no_departure) as flight_number,
                b.province,
                b.vehicle_type,
                d.name as driver_name,
                v.registration as vehicle_number
            FROM bookings b
            LEFT JOIN driver_vehicle_assignments dva ON b.booking_ref = dva.booking_ref
            LEFT JOIN drivers d ON dva.driver_id = d.id
            LEFT JOIN vehicles v ON dva.vehicle_id = v.id
            WHERE 1=1";

    $params = [];

    // Booking status filter (support multiple statuses)
    if (!empty($status) && count($status) > 0) {
        $statusPlaceholders = [];
        foreach ($status as $idx => $stat) {
            $placeholder = ":status_$idx";
            $statusPlaceholders[] = $placeholder;
            $params[$placeholder] = $stat;
        }
        $sql .= " AND b.ht_status IN (" . implode(',', $statusPlaceholders) . ")";
    }

    // Assignment status filter (separate from booking status)
    if (!empty($assignmentStatus) && $assignmentStatus !== 'all') {
        if ($assignmentStatus === 'pending') {
            // Not assigned (no assignment record)
            $sql .= " AND dva.id IS NULL";
        } elseif ($assignmentStatus === 'assigned') {
            // Has assignment (any status)
            $sql .= " AND dva.id IS NOT NULL";
        } else {
            // Has assignment with specific status (for backward compatibility)
            $sql .= " AND dva.status = :assignment_status_param";
            $params[':assignment_status_param'] = $assignmentStatus;
        }
    }

    // Province filter (support multiple provinces)
    if (!empty($province) && count($province) > 0) {
        $provinceConditions = [];
        foreach ($province as $idx => $prov) {
            if ($prov === 'unknown') {
                $provinceConditions[] = "(b.province IS NULL OR b.province = '' OR b.province = 'Unknown')";
            } else {
                $placeholder = ":province_$idx";
                $provinceConditions[] = "b.province = " . $placeholder;
                $params[$placeholder] = $prov;
            }
        }
        if (!empty($provinceConditions)) {
            $sql .= " AND (" . implode(' OR ', $provinceConditions) . ")";
        }
    }

    // Date filter (same logic as database-search.php)
    if (!empty($dateFrom) || !empty($dateTo)) {
        if (!empty($dateFrom) && !empty($dateTo)) {
            // Both From and To specified - date range
            $dateToEnd = date('Y-m-d', strtotime($dateTo . ' +1 day'));
            $sql .= " AND (
                (b.arrival_date >= :dateFrom1 AND b.arrival_date < :dateTo1)
                OR (b.departure_date >= :dateFrom2 AND b.departure_date < :dateTo2)
                OR (b.pickup_date >= :dateFrom3 AND b.pickup_date < :dateTo3)
            )";
            $params[':dateFrom1'] = $dateFrom . ' 00:00:00';
            $params[':dateTo1'] = $dateToEnd . ' 00:00:00';
            $params[':dateFrom2'] = $dateFrom . ' 00:00:00';
            $params[':dateTo2'] = $dateToEnd . ' 00:00:00';
            $params[':dateFrom3'] = $dateFrom . ' 00:00:00';
            $params[':dateTo3'] = $dateToEnd . ' 00:00:00';
        } elseif (!empty($dateFrom)) {
            // Only From specified - show that single day
            $dateFromEnd = date('Y-m-d', strtotime($dateFrom . ' +1 day'));
            $sql .= " AND (
                (b.arrival_date >= :dateFrom1 AND b.arrival_date < :dateFromEnd1)
                OR (b.departure_date >= :dateFrom2 AND b.departure_date < :dateFromEnd2)
                OR (b.pickup_date >= :dateFrom3 AND b.pickup_date < :dateFromEnd3)
            )";
            $params[':dateFrom1'] = $dateFrom . ' 00:00:00';
            $params[':dateFromEnd1'] = $dateFromEnd . ' 00:00:00';
            $params[':dateFrom2'] = $dateFrom . ' 00:00:00';
            $params[':dateFromEnd2'] = $dateFromEnd . ' 00:00:00';
            $params[':dateFrom3'] = $dateFrom . ' 00:00:00';
            $params[':dateFromEnd3'] = $dateFromEnd . ' 00:00:00';
        } else {
            // Only To specified - show that single day
            $dateToEnd = date('Y-m-d', strtotime($dateTo . ' +1 day'));
            $sql .= " AND (
                (b.arrival_date >= :dateTo1 AND b.arrival_date < :dateToEnd1)
                OR (b.departure_date >= :dateTo2 AND b.departure_date < :dateToEnd2)
                OR (b.pickup_date >= :dateTo3 AND b.pickup_date < :dateToEnd3)
            )";
            $params[':dateTo1'] = $dateTo . ' 00:00:00';
            $params[':dateToEnd1'] = $dateToEnd . ' 00:00:00';
            $params[':dateTo2'] = $dateTo . ' 00:00:00';
            $params[':dateToEnd2'] = $dateToEnd . ' 00:00:00';
            $params[':dateTo3'] = $dateTo . ' 00:00:00';
            $params[':dateToEnd3'] = $dateToEnd . ' 00:00:00';
        }
    }

    // Search filter
    if (!empty($search)) {
        $searchValue = "%$search%";
        $sql .= " AND (
            b.booking_ref LIKE :search1 OR
            b.passenger_name LIKE :search2 OR
            b.accommodation_name LIKE :search3 OR
            b.resort LIKE :search4 OR
            b.airport LIKE :search5 OR
            b.flight_no_arrival LIKE :search6 OR
            b.flight_no_departure LIKE :search7
        )";
        $params[':search1'] = $searchValue;
        $params[':search2'] = $searchValue;
        $params[':search3'] = $searchValue;
        $params[':search4'] = $searchValue;
        $params[':search5'] = $searchValue;
        $params[':search6'] = $searchValue;
        $params[':search7'] = $searchValue;
    }

    $sql .= " ORDER BY b.pickup_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process bookings to calculate pickup_location and dropoff_location
    // using the same logic as Job Assignments
    foreach ($bookings as &$booking) {
        $pickupLocation = '-';
        $dropoffLocation = '-';

        $bookingType = strtolower($booking['booking_type'] ?? '');
        $accommodation = $booking['accommodation_name'] ?? $booking['resort'] ?? '';
        $airport = $booking['airport'] ?? $booking['from_airport'] ?? $booking['to_airport'] ?? '';

        if (strpos($bookingType, 'arrival') !== false || !empty($booking['arrival_date'])) {
            // Arrival transfer: Airport -> Accommodation
            $pickupLocation = $airport ?: 'Airport';
            $dropoffLocation = $accommodation ?: 'Resort/Hotel';
        } else if (strpos($bookingType, 'departure') !== false || !empty($booking['departure_date'])) {
            // Departure transfer: Accommodation -> Airport
            $pickupLocation = $accommodation ?: 'Resort/Hotel';
            $dropoffLocation = $airport ?: 'Airport';
        } else if (strpos($bookingType, 'quote') !== false) {
            // Quote transfer: Use pickup_address1 and dropoff_address1
            $pickupAddress = $booking['pickup_address1'] ?? '';
            $dropoffAddress = $booking['dropoff_address1'] ?? '';

            if (!empty($pickupAddress) && !empty($dropoffAddress)) {
                $pickupLocation = $pickupAddress;
                $dropoffLocation = $dropoffAddress;
            } elseif (!empty($pickupAddress)) {
                $pickupLocation = $pickupAddress;
                $dropoffLocation = 'Destination';
            } elseif (!empty($dropoffAddress)) {
                $pickupLocation = 'Origin';
                $dropoffLocation = $dropoffAddress;
            } else {
                // Fallback to accommodation/airport if addresses not available
                if (!empty($accommodation) && !empty($airport)) {
                    $pickupLocation = $accommodation;
                    $dropoffLocation = $airport;
                } elseif (!empty($accommodation)) {
                    $pickupLocation = $accommodation;
                    $dropoffLocation = 'Destination';
                } elseif (!empty($airport)) {
                    $pickupLocation = $airport;
                    $dropoffLocation = 'Destination';
                } else {
                    $pickupLocation = '-';
                    $dropoffLocation = '-';
                }
            }
        } else {
            // Default: Use available location data
            if (!empty($accommodation) && !empty($airport)) {
                // If both exist, default to departure direction
                $pickupLocation = $accommodation;
                $dropoffLocation = $airport;
            } elseif (!empty($accommodation)) {
                $pickupLocation = $accommodation;
                $dropoffLocation = 'Destination';
            } elseif (!empty($airport)) {
                $pickupLocation = $airport;
                $dropoffLocation = 'Destination';
            } else {
                $pickupLocation = '-';
                $dropoffLocation = '-';
            }
        }

        // Add computed fields to booking
        $booking['pickup_location'] = $pickupLocation;
        $booking['dropoff_location'] = $dropoffLocation;
    }
    unset($booking); // Break reference

    // Get summary statistics (apply same filters)
    $summarySql = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN b.ht_status = 'ACON' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN b.ht_status = 'PCON' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN b.ht_status LIKE '%CAN' THEN 1 ELSE 0 END) as cancelled,
            SUM(b.pax_total) as total_passengers
        FROM bookings b
        LEFT JOIN driver_vehicle_assignments dva ON b.booking_ref = dva.booking_ref
        WHERE 1=1";

    $summaryParams = [];

    // Apply same filters
    // Booking status filter (support multiple statuses)
    if (!empty($status) && count($status) > 0) {
        $statusPlaceholders = [];
        foreach ($status as $idx => $stat) {
            $placeholder = ":summary_status_$idx";
            $statusPlaceholders[] = $placeholder;
            $summaryParams[$placeholder] = $stat;
        }
        $summarySql .= " AND b.ht_status IN (" . implode(',', $statusPlaceholders) . ")";
    }

    // Assignment status filter
    if (!empty($assignmentStatus) && $assignmentStatus !== 'all') {
        if ($assignmentStatus === 'pending') {
            // Not assigned (no assignment record)
            $summarySql .= " AND dva.id IS NULL";
        } elseif ($assignmentStatus === 'assigned') {
            // Has assignment (any status)
            $summarySql .= " AND dva.id IS NOT NULL";
        } else {
            // Has assignment with specific status
            $summarySql .= " AND dva.status = :summary_assignment_status_param";
            $summaryParams[':summary_assignment_status_param'] = $assignmentStatus;
        }
    }

    // Province filter (support multiple provinces)
    if (!empty($province) && count($province) > 0) {
        $provinceConditions = [];
        foreach ($province as $idx => $prov) {
            if ($prov === 'unknown') {
                $provinceConditions[] = "(b.province IS NULL OR b.province = '' OR b.province = 'Unknown')";
            } else {
                $placeholder = ":summary_province_$idx";
                $provinceConditions[] = "b.province = " . $placeholder;
                $summaryParams[$placeholder] = $prov;
            }
        }
        if (!empty($provinceConditions)) {
            $summarySql .= " AND (" . implode(' OR ', $provinceConditions) . ")";
        }
    }

    if (!empty($dateFrom) || !empty($dateTo)) {
        if (!empty($dateFrom) && !empty($dateTo)) {
            // Both From and To specified - date range
            $dateToEnd = date('Y-m-d', strtotime($dateTo . ' +1 day'));
            $summarySql .= " AND (
                (b.arrival_date >= :summary_dateFrom1 AND b.arrival_date < :summary_dateTo1)
                OR (b.departure_date >= :summary_dateFrom2 AND b.departure_date < :summary_dateTo2)
                OR (b.pickup_date >= :summary_dateFrom3 AND b.pickup_date < :summary_dateTo3)
            )";
            $summaryParams[':summary_dateFrom1'] = $dateFrom . ' 00:00:00';
            $summaryParams[':summary_dateTo1'] = $dateToEnd . ' 00:00:00';
            $summaryParams[':summary_dateFrom2'] = $dateFrom . ' 00:00:00';
            $summaryParams[':summary_dateTo2'] = $dateToEnd . ' 00:00:00';
            $summaryParams[':summary_dateFrom3'] = $dateFrom . ' 00:00:00';
            $summaryParams[':summary_dateTo3'] = $dateToEnd . ' 00:00:00';
        } elseif (!empty($dateFrom)) {
            // Only From specified - show that single day
            $dateFromEnd = date('Y-m-d', strtotime($dateFrom . ' +1 day'));
            $summarySql .= " AND (
                (b.arrival_date >= :summary_dateFrom1 AND b.arrival_date < :summary_dateFromEnd1)
                OR (b.departure_date >= :summary_dateFrom2 AND b.departure_date < :summary_dateFromEnd2)
                OR (b.pickup_date >= :summary_dateFrom3 AND b.pickup_date < :summary_dateFromEnd3)
            )";
            $summaryParams[':summary_dateFrom1'] = $dateFrom . ' 00:00:00';
            $summaryParams[':summary_dateFromEnd1'] = $dateFromEnd . ' 00:00:00';
            $summaryParams[':summary_dateFrom2'] = $dateFrom . ' 00:00:00';
            $summaryParams[':summary_dateFromEnd2'] = $dateFromEnd . ' 00:00:00';
            $summaryParams[':summary_dateFrom3'] = $dateFrom . ' 00:00:00';
            $summaryParams[':summary_dateFromEnd3'] = $dateFromEnd . ' 00:00:00';
        } else {
            // Only To specified - show that single day
            $dateToEnd = date('Y-m-d', strtotime($dateTo . ' +1 day'));
            $summarySql .= " AND (
                (b.arrival_date >= :summary_dateTo1 AND b.arrival_date < :summary_dateToEnd1)
                OR (b.departure_date >= :summary_dateTo2 AND b.departure_date < :summary_dateToEnd2)
                OR (b.pickup_date >= :summary_dateTo3 AND b.pickup_date < :summary_dateToEnd3)
            )";
            $summaryParams[':summary_dateTo1'] = $dateTo . ' 00:00:00';
            $summaryParams[':summary_dateToEnd1'] = $dateToEnd . ' 00:00:00';
            $summaryParams[':summary_dateTo2'] = $dateTo . ' 00:00:00';
            $summaryParams[':summary_dateToEnd2'] = $dateToEnd . ' 00:00:00';
            $summaryParams[':summary_dateTo3'] = $dateTo . ' 00:00:00';
            $summaryParams[':summary_dateToEnd3'] = $dateToEnd . ' 00:00:00';
        }
    }

    if (!empty($search)) {
        $searchValue = "%$search%";
        $summarySql .= " AND (
            b.booking_ref LIKE :summary_search1 OR
            b.passenger_name LIKE :summary_search2 OR
            b.accommodation_name LIKE :summary_search3 OR
            b.resort LIKE :summary_search4 OR
            b.airport LIKE :summary_search5 OR
            b.flight_no_arrival LIKE :summary_search6 OR
            b.flight_no_departure LIKE :summary_search7
        )";
        $summaryParams[':summary_search1'] = $searchValue;
        $summaryParams[':summary_search2'] = $searchValue;
        $summaryParams[':summary_search3'] = $searchValue;
        $summaryParams[':summary_search4'] = $searchValue;
        $summaryParams[':summary_search5'] = $searchValue;
        $summaryParams[':summary_search6'] = $searchValue;
        $summaryParams[':summary_search7'] = $searchValue;
    }

    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($summaryParams);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    // Get province list for filter
    $provincesStmt = $pdo->query("
        SELECT DISTINCT province
        FROM bookings
        WHERE province IS NOT NULL AND province != ''
        ORDER BY province
    ");
    $provinces = $provincesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Format response based on requested format
    $response = [
        'success' => true,
        'data' => [
            'bookings' => $bookings,
            'summary' => $summary,
            'provinces' => $provinces,
            'filters' => [
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
                'province' => $province
            ],
            'total' => count($bookings),
            'generated_at' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
