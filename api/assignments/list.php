    <?php
    // api/assignments/list.php - Get All Assignments
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    require_once '../config/database.php';

    function sendResponse($success, $data = null, $message = '', $code = 200)
    {
        http_response_code($code);
        echo json_encode([
            'success' => $success,
            'data' => $data,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendResponse(false, null, 'Method not allowed', 405);
        }

        // Get filters
        $limit = (int)($_GET['limit'] ?? 100);
        $offset = (int)($_GET['offset'] ?? 0);
        $search = $_GET['search'] ?? '';
        $province = $_GET['province'] ?? 'all';
        $bookingType = $_GET['booking_type'] ?? 'all'; // all, arrival, departure
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';

        $db = new Database();
        $pdo = $db->getConnection();

        // Check if this is a default query (no filters/search)
        $isDefaultQuery = empty($search) && empty($dateFrom) && empty($dateTo) && $province === 'all' && $bookingType === 'all';

        // Build WHERE clause
        $where = [];
        $params = [];

        // Exclude cancelled assignments by default
        $where[] = "a.status != 'cancelled'";

        // Default query: Hide assignments older than 3 days from pickup_date
        if ($isDefaultQuery) {
            $where[] = "(
                COALESCE(b.pickup_date_adjusted, b.pickup_date, b.arrival_date, b.departure_date) >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                OR COALESCE(b.pickup_date_adjusted, b.pickup_date, b.arrival_date, b.departure_date) IS NULL
            )";
        }

        if (!empty($search)) {
            $searchParam = "%{$search}%";
            $where[] = "(a.booking_ref LIKE :search1 OR d.name LIKE :search2 OR v.registration LIKE :search3 OR b.passenger_name LIKE :search4)";
            $params[':search1'] = $searchParam;
            $params[':search2'] = $searchParam;
            $params[':search3'] = $searchParam;
            $params[':search4'] = $searchParam;
        }

        if ($province !== 'all') {
            if ($province === 'unknown') {
                $where[] = "(b.province IS NULL OR b.province = '')";
            } else {
                $where[] = "b.province = :province";
                $params[':province'] = $province;
            }
        }

        if ($bookingType !== 'all') {
            if ($bookingType === 'arrival') {
                $where[] = "(b.booking_type LIKE '%arrival%' OR b.arrival_date IS NOT NULL)";
            } else if ($bookingType === 'departure') {
                $where[] = "(b.booking_type LIKE '%departure%' OR b.departure_date IS NOT NULL)";
            }
        }

        if (!empty($dateFrom) && !empty($dateTo)) {
            // Both dates: range filter
            $dateToEnd = date('Y-m-d', strtotime($dateTo . ' +1 day'));
            $where[] = "COALESCE(b.pickup_date_adjusted, b.pickup_date, b.arrival_date, b.departure_date) >= :date_from";
            $where[] = "COALESCE(b.pickup_date_adjusted, b.pickup_date, b.arrival_date, b.departure_date) < :date_to";
            $params[':date_from'] = $dateFrom . ' 00:00:00';
            $params[':date_to'] = $dateToEnd . ' 00:00:00';
        } elseif (!empty($dateFrom)) {
            // Only dateFrom: filter single day
            $dateFromEnd = date('Y-m-d', strtotime($dateFrom . ' +1 day'));
            $where[] = "COALESCE(b.pickup_date_adjusted, b.pickup_date, b.arrival_date, b.departure_date) >= :date_from";
            $where[] = "COALESCE(b.pickup_date_adjusted, b.pickup_date, b.arrival_date, b.departure_date) < :date_from_end";
            $params[':date_from'] = $dateFrom . ' 00:00:00';
            $params[':date_from_end'] = $dateFromEnd . ' 00:00:00';
        } elseif (!empty($dateTo)) {
            // Only dateTo: filter up to that day
            $dateToEnd = date('Y-m-d', strtotime($dateTo . ' +1 day'));
            $where[] = "COALESCE(b.pickup_date_adjusted, b.pickup_date, b.arrival_date, b.departure_date) < :date_to";
            $params[':date_to'] = $dateToEnd . ' 00:00:00';
        }

        $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';

        // Get assignments with all details
        // Join with latest tracking token (including expired ones for display)
        // Use COALESCE to get tracking info from either assignments table or tracking_tokens table
        $sql = "SELECT a.*,
                    d.name as driver_name, d.phone_number as driver_phone,
                    v.registration, v.brand, v.model, v.color,
                    s.full_name as assigned_by_name,
                    COALESCE(t.token, a.tracking_token) as tracking_token,
                    COALESCE(t.status, IF(a.has_tracking = 1, 'pending', NULL)) as tracking_status,
                    COALESCE(t.completion_type, a.completion_type) as completion_type,
                    t.started_at as tracking_started_at, t.completed_at as tracking_completed_at,
                    t.total_locations_sent,
                    t.expires_at as tracking_expires_at,
                    GREATEST(COALESCE(a.has_tracking, 0), IF(t.token IS NOT NULL, 1, 0)) as has_tracking,
                    b.passenger_name, b.passenger_phone, b.vehicle_type, b.pax_total, b.booking_type,
                    b.pickup_date, b.pickup_date_adjusted, b.accommodation_name, b.airport, b.resort, b.province,
                    b.from_airport, b.to_airport, b.arrival_date, b.departure_date,
                    b.flight_no_arrival, b.flight_no_departure,
                    b.pickup_address1, b.dropoff_address1
                FROM driver_vehicle_assignments a
                LEFT JOIN drivers d ON a.driver_id = d.id
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                LEFT JOIN staff_users s ON a.assigned_by = s.id
                LEFT JOIN driver_tracking_tokens t ON a.id = t.assignment_id
                    AND t.id = (
                        SELECT t2.id
                        FROM driver_tracking_tokens t2
                        WHERE t2.assignment_id = a.id
                        ORDER BY t2.created_at DESC
                        LIMIT 1
                    )
                LEFT JOIN bookings b ON a.booking_ref = b.booking_ref
                {$whereClause}
                ORDER BY COALESCE(b.pickup_date_adjusted, b.pickup_date, b.arrival_date, b.departure_date) ASC, a.assigned_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);

        // Bind params
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $assignments = $stmt->fetchAll();

        // Process and format assignments
        $formattedAssignments = array_map(function ($assignment) {
            // Determine pickup and dropoff locations based on booking type
            $pickupLocation = '-';
            $dropoffLocation = '-';

            $bookingType = strtolower($assignment['booking_type'] ?? '');
            $accommodation = $assignment['accommodation_name'] ?? $assignment['resort'] ?? '';
            $airport = $assignment['airport'] ?? $assignment['from_airport'] ?? $assignment['to_airport'] ?? '';

            if (strpos($bookingType, 'arrival') !== false || $assignment['arrival_date']) {
                // Arrival transfer: Airport -> Accommodation
                $pickupLocation = $airport ?: 'Airport';
                $dropoffLocation = $accommodation ?: 'Resort/Hotel';
            } else if (strpos($bookingType, 'departure') !== false || $assignment['departure_date']) {
                // Departure transfer: Accommodation -> Airport
                $pickupLocation = $accommodation ?: 'Resort/Hotel';
                $dropoffLocation = $airport ?: 'Airport';
            } else if (strpos($bookingType, 'quote') !== false) {
                // Quote transfer: Use pickup_address1 and dropoff_address1
                $pickupAddress = $assignment['pickup_address1'] ?? '';
                $dropoffAddress = $assignment['dropoff_address1'] ?? '';

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

            // Determine pickup dates for display and sorting
            // Display pickup date is the original time (before adjustment)
            $displayPickupDate = $assignment['pickup_date'] ?? $assignment['arrival_date'] ?? $assignment['departure_date'] ?? null;

            // Sorting/filtering uses adjusted time if available
            $sortPickupDate = $assignment['pickup_date_adjusted'] ?? $assignment['pickup_date'] ?? $assignment['arrival_date'] ?? $assignment['departure_date'] ?? null;

            // Check if booking is older than 3 days (use adjusted time for this check)
            $isOldBooking = false;
            if ($sortPickupDate && $sortPickupDate !== '0000-00-00 00:00:00') {
                $pickupTimestamp = strtotime($sortPickupDate);
                $threeDaysAgo = strtotime('-3 days');
                $isOldBooking = $pickupTimestamp < $threeDaysAgo;
            }

            // Check if tracking token is expired
            $isTokenExpired = false;
            $trackingExpiresAt = $assignment['tracking_expires_at'] ?? null;
            if ($trackingExpiresAt) {
                $isTokenExpired = strtotime($trackingExpiresAt) <= time();
            }

            return [
                'id' => (int)$assignment['id'],
                'booking_ref' => $assignment['booking_ref'],
                'status' => $assignment['status'],
                'completion_type' => $assignment['completion_type'],
                'booking_status' => $assignment['booking_status'],

                'driver' => [
                    'id' => (int)$assignment['driver_id'],
                    'name' => $assignment['driver_name'],
                    'phone' => $assignment['driver_phone']
                ],

                'vehicle' => [
                    'id' => (int)$assignment['vehicle_id'],
                    'registration' => $assignment['registration'],
                    'brand' => $assignment['brand'],
                    'model' => $assignment['model'],
                    'color' => $assignment['color']
                ],

                'booking' => [
                    'passenger_name' => $assignment['passenger_name'] ?? '-',
                    'passenger_phone' => $assignment['passenger_phone'] ?? '-',
                    'vehicle' => $assignment['vehicle_type'] ?? '-',
                    'pax' => (int)($assignment['pax_total'] ?? 0),
                    'booking_type' => $assignment['booking_type'] ?? '-',
                    'province' => $assignment['province'] ?? 'Unknown',
                    'pickup_date' => $displayPickupDate,
                    'pickup_date_adjusted' => $assignment['pickup_date_adjusted'] ?? null,
                    'pickup_location' => $pickupLocation,
                    'dropoff_location' => $dropoffLocation,
                    'pickup_address1' => $assignment['pickup_address1'] ?? null,
                    'dropoff_address1' => $assignment['dropoff_address1'] ?? null,
                    'flight_no_arrival' => $assignment['flight_no_arrival'] ?? null,
                    'flight_no_departure' => $assignment['flight_no_departure'] ?? null
                ],

                'tracking' => [
                    'has_tracking' => (bool)$assignment['has_tracking'],
                    'token' => $assignment['tracking_token'],
                    'status' => $assignment['tracking_status'],
                    'started_at' => $assignment['tracking_started_at'],
                    'completed_at' => $assignment['tracking_completed_at'],
                    'total_locations_sent' => (int)($assignment['total_locations_sent'] ?? 0),
                    'expires_at' => $trackingExpiresAt,
                    'is_expired' => $isTokenExpired
                ],

                'assignment_notes' => $assignment['assignment_notes'],
                'assigned_by_name' => $assignment['assigned_by_name'],
                'assigned_at' => $assignment['assigned_at'],
                'last_sync_at' => $assignment['last_sync_at'],
                'cancelled_at' => $assignment['cancelled_at'],
                'cancellation_reason' => $assignment['cancellation_reason'],
                'is_old_booking' => $isOldBooking
            ];
        }, $assignments);

        // Get total count (must include all JOINs used in WHERE clause)
        $countSql = "SELECT COUNT(*) as total
                    FROM driver_vehicle_assignments a
                    LEFT JOIN drivers d ON a.driver_id = d.id
                    LEFT JOIN vehicles v ON a.vehicle_id = v.id
                    LEFT JOIN bookings b ON a.booking_ref = b.booking_ref
                    {$whereClause}";

        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetch()['total'];

        sendResponse(true, [
            'assignments' => $formattedAssignments,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ], 'Assignments retrieved successfully');
    } catch (Exception $e) {
        error_log("Assignments List API error: " . $e->getMessage());
        sendResponse(false, null, 'Server error: ' . $e->getMessage(), 500);
    }
