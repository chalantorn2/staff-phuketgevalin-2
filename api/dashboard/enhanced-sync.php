<?php
// api/dashboard/enhanced-sync.php - Enhanced Dashboard with Auto Sync
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';
require_once '../config/province-mapping.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Check for force sync parameter
    $input = json_decode(file_get_contents('php://input'), true);
    $forceSync = isset($input['force_sync']) && $input['force_sync'] === true;

    // Check if we need to sync (if database is empty or last sync > 1 hour ago)
    $lastSyncSql = "SELECT MAX(completed_at) as last_sync FROM sync_status WHERE status = 'completed'";
    $lastSyncStmt = $pdo->prepare($lastSyncSql);
    $lastSyncStmt->execute();
    $lastSync = $lastSyncStmt->fetch()['last_sync'];

    $bookingsCountSql = "SELECT COUNT(*) as total FROM bookings";
    $bookingsCountStmt = $pdo->prepare($bookingsCountSql);
    $bookingsCountStmt->execute();
    $bookingsCount = $bookingsCountStmt->fetch()['total'];

    $needsSync = false;
    if ($forceSync) {
        $needsSync = true;
        $syncReason = "Force sync requested";
    } elseif ($bookingsCount == 0) {
        $needsSync = true;
        $syncReason = "No bookings in database";
    } elseif (!$lastSync || strtotime($lastSync) < strtotime('-1 hour')) {
        $needsSync = true;
        $syncReason = "Background sync (silent)";

        // ทำ sync แบบ background - ไม่ block user (only if not force sync)
        if (!$forceSync && function_exists('fastcgi_finish_request')) {
            // Return response แต่ทำ sync ต่อ background
            echo json_encode([
                'success' => true,
                'data' => [
                    'sync_performed' => false,
                    'sync_reason' => 'Loading from database',
                    'stats' => getDashboardStats($pdo),
                    'recent_bookings' => getEnhancedRecentBookings($pdo),
                    'total_bookings' => (int)$bookingsCount,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            fastcgi_finish_request();

            // Sync background (user ไม่เห็น)
            performEnhancedSync($pdo);
            exit;
        }
    }

    $syncResult = null;
    if ($needsSync) {
        // Perform auto sync
        $syncResult = performEnhancedSync($pdo);
    }

    // Get dashboard stats
    $statsResult = getDashboardStats($pdo);

    // Get recent bookings
    $recentBookings = getEnhancedRecentBookings($pdo);

    $response = [
        'success' => true,
        'data' => [
            'sync_performed' => $needsSync,
            'sync_reason' => $syncReason ?? 'No sync needed',
            'sync_result' => $syncResult,
            'stats' => $statsResult,
            'recent_bookings' => $recentBookings,
            'last_sync' => $lastSync,
            'total_bookings' => (int)$bookingsCount,
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

function performEnhancedSync($pdo)
{
    // === DUAL QUERY STRATEGY (Today + Tomorrow Only) ===
    // Query 1: Last Action Date (Today + Tomorrow) - Catch new/updated bookings
    $dateFromLastAction = date('Y-m-d\T00:00:00');
    $dateToLastAction = date('Y-m-d\T23:59:59', strtotime('+1 day'));

    // Query 2: Pickup Date (Today + Tomorrow) - Catch upcoming bookings
    // Use 00:00:00 for start and 23:59:59 for end to cover full days
    $dateFromPickup = date('Y-m-d\T00:00:00');
    $dateToPickup = date('Y-m-d\T23:59:59', strtotime('+1 day'));

    // Log sync start
    $syncStartSql = "INSERT INTO sync_status (sync_type, date_from, date_to, status, started_at)
                     VALUES ('dual-query', :date_from, :date_to, 'running', NOW())";
    $syncStartStmt = $pdo->prepare($syncStartSql);
    $syncStartStmt->execute([
        ':date_from' => $dateFromLastAction,
        ':date_to' => $dateToPickup
    ]);
    $syncId = $pdo->lastInsertId();

    try {
        $headers = [
            "API_KEY: " . HolidayTaxisConfig::API_KEY,
            "Content-Type: application/json",
            "Accept: application/json",
            "VERSION: " . HolidayTaxisConfig::API_VERSION
        ];

        // === QUERY 1: Pickup Date (Arrivals) - DAY BY DAY ===
        // Holiday Taxis API limitation: wide range queries don't return all results
        // Solution: Query each day separately
        $bookings1 = [];

        // Use immutable dates to prevent issues
        $today = new DateTime();
        $endDay = new DateTime('+1 day'); // Today + Tomorrow only

        $totalDays = (int)$today->diff($endDay)->days + 1;
        error_log("Enhanced Sync - Query 1: Fetching arrivals day-by-day for $totalDays days from " . $today->format('Y-m-d') . " to " . $endDay->format('Y-m-d'));

        for ($i = 0; $i < $totalDays; $i++) {
            $currentDate = clone $today;
            $currentDate->modify("+{$i} days");

            $dayFrom = $currentDate->format('Y-m-d\T00:00:00');
            $dayTo = $currentDate->format('Y-m-d\T23:59:59');

            $searchUrl1 = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/arrivals/since/{$dayFrom}/until/{$dayTo}/page/1";

            $ch1 = curl_init();
            curl_setopt_array($ch1, [
                CURLOPT_URL => $searchUrl1,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);

            $response1 = curl_exec($ch1);
            $httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
            curl_close($ch1);

            if ($httpCode1 === 200) {
                $searchData1 = json_decode($response1, true);
                if ($searchData1 && isset($searchData1['bookings'])) {
                    $bookingsData1 = $searchData1['bookings'];
                    if (is_object($bookingsData1) || (is_array($bookingsData1) && isset($bookingsData1['booking_0']))) {
                        $dayBookings = array_values((array)$bookingsData1);
                    } else {
                        $dayBookings = $bookingsData1;
                    }

                    $dayTotal = count($dayBookings);
                    if ($dayTotal > 0) {
                        error_log("Enhanced Sync - " . $currentDate->format('Y-m-d') . " Page 1: " . $dayTotal . " bookings");
                        $bookings1 = array_merge($bookings1, $dayBookings);
                    }

                    // Pagination: Keep fetching until no more bookings
                    // Holiday Taxis API may not return total_pages, so we loop until empty
                    $page = 2;
                    $maxPages = 50; // Safety limit

                    while ($page <= $maxPages) {
                        $pageUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/arrivals/since/{$dayFrom}/until/{$dayTo}/page/{$page}";

                        $chPage = curl_init();
                        curl_setopt_array($chPage, [
                            CURLOPT_URL => $pageUrl,
                            CURLOPT_HTTPHEADER => $headers,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 30
                        ]);

                        $pageResponse = curl_exec($chPage);
                        $pageHttpCode = curl_getinfo($chPage, CURLINFO_HTTP_CODE);
                        curl_close($chPage);

                        // Stop if no content or error
                        if ($pageHttpCode === 204) {
                            break;
                        }

                        if ($pageHttpCode === 200) {
                            $pageData = json_decode($pageResponse, true);
                            if ($pageData && isset($pageData['bookings'])) {
                                $pageBookingsData = $pageData['bookings'];
                                if (is_object($pageBookingsData) || (is_array($pageBookingsData) && isset($pageBookingsData['booking_0']))) {
                                    $pageBookings = array_values((array)$pageBookingsData);
                                } else {
                                    $pageBookings = $pageBookingsData;
                                }

                                $pageCount = count($pageBookings);
                                if ($pageCount === 0) {
                                    // No more bookings, stop
                                    break;
                                }

                                error_log("Enhanced Sync - " . $currentDate->format('Y-m-d') . " Page $page: " . $pageCount . " bookings");
                                $bookings1 = array_merge($bookings1, $pageBookings);
                                $dayTotal += $pageCount;
                            } else {
                                // No bookings in response, stop
                                break;
                            }
                        } else {
                            // Error or no more pages
                            break;
                        }

                        $page++;
                        usleep(100000); // 0.1 second delay between pages
                    }

                    if ($dayTotal > 0) {
                        error_log("Enhanced Sync - " . $currentDate->format('Y-m-d') . " Total: " . $dayTotal . " bookings across " . ($page - 1) . " pages");
                    }
                }
            }

            // Add delay between days to prevent rate limiting
            usleep(100000); // 0.1 second delay between days
        }

        // === QUERY 2: Pickup Date (Departures) - DAY BY DAY ===
        // Some bookings only appear in departures endpoint if they have departure date = target date
        $bookings2 = [];

        error_log("Enhanced Sync - Query 2: Fetching departures day-by-day for $totalDays days from " . $today->format('Y-m-d') . " to " . $endDay->format('Y-m-d'));

        for ($i = 0; $i < $totalDays; $i++) {
            $currentDate = clone $today;
            $currentDate->modify("+{$i} days");

            $dayFrom = $currentDate->format('Y-m-d\T00:00:00');
            $dayTo = $currentDate->format('Y-m-d\T23:59:59');

            $searchUrl2 = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/departures/since/{$dayFrom}/until/{$dayTo}/page/1";

            $ch2 = curl_init();
            curl_setopt_array($ch2, [
                CURLOPT_URL => $searchUrl2,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);

            $response2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            if ($httpCode2 === 200) {
                $searchData2 = json_decode($response2, true);
                if ($searchData2 && isset($searchData2['bookings'])) {
                    $bookingsData2 = $searchData2['bookings'];
                    if (is_object($bookingsData2) || (is_array($bookingsData2) && isset($bookingsData2['booking_0']))) {
                        $dayBookings = array_values((array)$bookingsData2);
                    } else {
                        $dayBookings = $bookingsData2;
                    }

                    $dayTotal = count($dayBookings);
                    if ($dayTotal > 0) {
                        error_log("Enhanced Sync - Departures " . $currentDate->format('Y-m-d') . " Page 1: " . $dayTotal . " bookings");
                        $bookings2 = array_merge($bookings2, $dayBookings);
                    }

                    // Pagination: Keep fetching until no more bookings
                    $page = 2;
                    $maxPages = 50;

                    while ($page <= $maxPages) {
                        $pageUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/departures/since/{$dayFrom}/until/{$dayTo}/page/{$page}";

                        $chPage = curl_init();
                        curl_setopt_array($chPage, [
                            CURLOPT_URL => $pageUrl,
                            CURLOPT_HTTPHEADER => $headers,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 30
                        ]);

                        $pageResponse = curl_exec($chPage);
                        $pageHttpCode = curl_getinfo($chPage, CURLINFO_HTTP_CODE);
                        curl_close($chPage);

                        if ($pageHttpCode === 204) {
                            break;
                        }

                        if ($pageHttpCode === 200) {
                            $pageData = json_decode($pageResponse, true);
                            if ($pageData && isset($pageData['bookings'])) {
                                $pageBookingsData = $pageData['bookings'];
                                if (is_object($pageBookingsData) || (is_array($pageBookingsData) && isset($pageBookingsData['booking_0']))) {
                                    $pageBookings = array_values((array)$pageBookingsData);
                                } else {
                                    $pageBookings = $pageBookingsData;
                                }

                                $pageCount = count($pageBookings);
                                if ($pageCount === 0) {
                                    break;
                                }

                                error_log("Enhanced Sync - Departures " . $currentDate->format('Y-m-d') . " Page $page: " . $pageCount . " bookings");
                                $bookings2 = array_merge($bookings2, $pageBookings);
                                $dayTotal += $pageCount;
                            } else {
                                break;
                            }
                        } else {
                            break;
                        }

                        $page++;
                        usleep(100000);
                    }

                    if ($dayTotal > 0) {
                        error_log("Enhanced Sync - Departures " . $currentDate->format('Y-m-d') . " Total: " . $dayTotal . " bookings across " . ($page - 1) . " pages");
                    }
                }
            }

            usleep(100000);
        }

        // === QUERY 3: Last Action Date (Today + Tomorrow) ===
        $searchUrl3 = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/since/{$dateFromLastAction}/until/{$dateToLastAction}/page/1";

        $ch3 = curl_init();
        curl_setopt_array($ch3, [
            CURLOPT_URL => $searchUrl3,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response3 = curl_exec($ch3);
        $httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
        curl_close($ch3);

        $bookings3 = [];
        if ($httpCode3 === 200) {
            $searchData3 = json_decode($response3, true);
            if ($searchData3 && isset($searchData3['bookings'])) {
                $bookingsData3 = $searchData3['bookings'];
                if (is_object($bookingsData3) || (is_array($bookingsData3) && isset($bookingsData3['booking_0']))) {
                    $bookings3 = array_values((array)$bookingsData3);
                } else {
                    $bookings3 = $bookingsData3;
                }
            }
        }

        error_log("Enhanced Sync - Query 3 (Last Action): " . count($bookings3) . " bookings");

        // Merge all 3 results (avoid duplicates)
        $allBookings = array_merge($bookings1, $bookings2, $bookings3);
        $uniqueBookings = [];
        $processedRefs = [];

        foreach ($allBookings as $booking) {
            $ref = $booking['ref'];
            if (!in_array($ref, $processedRefs)) {
                $uniqueBookings[] = $booking;
                $processedRefs[] = $ref;
            }
        }

        $bookings = $uniqueBookings;
        $totalFound = count($allBookings);
        $uniqueCount = count($uniqueBookings);
        $totalNew = 0;
        $totalUpdated = 0;
        $totalDetailed = 0;
        $errors = [];

        error_log("Enhanced Sync - Query 1 (Arrivals): " . count($bookings1) . " bookings");
        error_log("Enhanced Sync - Query 2 (Departures): " . count($bookings2) . " bookings");
        error_log("Enhanced Sync - Query 3 (Last Action): " . count($bookings3) . " bookings");
        error_log("Enhanced Sync - Total found: $totalFound, Unique: $uniqueCount");

        foreach ($bookings as $index => $booking) {
            try {
                // เพิ่ม delay ระหว่างการประมวลผลแต่ละ booking
                if ($index > 0) {
                    usleep(200000); // หน่วงเวลา 0.2 วินาที (200,000 microseconds)
                }

                // เช็คว่า booking มีอยู่หรือไม่
                $checkSql = "SELECT id FROM bookings WHERE booking_ref = :ref";
                $checkStmt = $pdo->prepare($checkSql);
                $checkStmt->execute([':ref' => $booking['ref']]);
                $exists = $checkStmt->fetch();

                // ดึงข้อมูล booking detail
                $detailData = getBookingDetail($booking['ref'], $headers);
                if ($detailData) {
                    $totalDetailed++;
                }

                // หน่วงเวลาก่อนเรียก Notes API
                usleep(100000); // หน่วงเวลา 0.1 วินาที

                // ดึงข้อมูล Notes (เพิ่มส่วนนี้!!!)
                $notesText = getBookingNotesFromAPI($booking['ref'], $headers);
                error_log("DEBUG: Got notes for " . $booking['ref'] . ": " . ($notesText ? substr($notesText, 0, 50) . '...' : 'NULL'));

                // ประมวลผลข้อมูล booking (ส่ง notes ไปด้วย)
                $processedData = processBookingData($booking, $detailData, $notesText);

                error_log("DEBUG: ProcessedData notes for " . $booking['ref'] . ": " . ($processedData['notes'] ? substr($processedData['notes'], 0, 50) . '...' : 'NULL'));

                if ($exists) {
                    updateExistingBooking($pdo, $booking['ref'], $processedData);
                    $totalUpdated++;
                } else {
                    insertNewBooking($pdo, $booking['ref'], $processedData);
                    $totalNew++;
                }
            } catch (Exception $e) {
                error_log("DEBUG: Error processing " . $booking['ref'] . ": " . $e->getMessage());
                $errors[] = "Booking {$booking['ref']}: " . $e->getMessage();
                continue;
            }
        }

        // Update sync status - success
        $updateSyncSql = "UPDATE sync_status SET
                          total_found = :total_found,
                          total_new = :total_new,
                          total_updated = :total_updated,
                          status = 'completed',
                          completed_at = NOW()
                          WHERE id = :sync_id";
        $updateSyncStmt = $pdo->prepare($updateSyncSql);
        $updateSyncStmt->execute([
            ':sync_id' => $syncId,
            ':total_found' => $uniqueCount,
            ':total_new' => $totalNew,
            ':total_updated' => $totalUpdated
        ]);

        return [
            'success' => true,
            'strategy' => 'triple-query',
            'query1' => [
                'type' => 'arrivals',
                'from' => $dateFromPickup,
                'to' => $dateToPickup,
                'found' => count($bookings1)
            ],
            'query2' => [
                'type' => 'departures',
                'from' => $dateFromPickup,
                'to' => $dateToPickup,
                'found' => count($bookings2)
            ],
            'query3' => [
                'type' => 'last-action',
                'from' => $dateFromLastAction,
                'to' => $dateToLastAction,
                'found' => count($bookings3)
            ],
            'total_found' => $totalFound,
            'unique_bookings' => $uniqueCount,
            'total_new' => $totalNew,
            'total_updated' => $totalUpdated,
            'total_detailed' => $totalDetailed,
            'errors' => $errors,
            'sync_id' => $syncId
        ];
    } catch (Exception $e) {
        // Update sync status - failed
        $updateSyncSql = "UPDATE sync_status SET 
                          status = 'failed',
                          error_message = :error,
                          completed_at = NOW()
                          WHERE id = :sync_id";
        $updateSyncStmt = $pdo->prepare($updateSyncSql);
        $updateSyncStmt->execute([
            ':sync_id' => $syncId,
            ':error' => $e->getMessage()
        ]);

        throw $e;
    }
}
function getBookingDetail($bookingRef, $headers, $maxRetries = 3)
{
    $detailUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$bookingRef}";

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $detailUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Success
        if ($httpCode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            if ($data !== null) {
                if ($attempt > 1) {
                    error_log("✓ Detail API SUCCESS for $bookingRef on attempt $attempt/$maxRetries");
                }
                return $data;
            }
        }

        // Log failure
        $errorMsg = "HTTP $httpCode";
        if (!empty($curlError)) {
            $errorMsg .= " | cURL Error: $curlError";
        }
        error_log("✗ Detail API FAILED for $bookingRef (attempt $attempt/$maxRetries) - $errorMsg");

        // Retry with exponential backoff (except on last attempt)
        if ($attempt < $maxRetries) {
            $delay = $attempt * 500000; // 0.5s, 1s, 1.5s
            usleep($delay);
        }
    }

    // Final failure after all retries
    error_log("✗✗✗ Detail API FINAL FAILURE for $bookingRef after $maxRetries attempts - accommodation data will be missing");
    return null;
}

function getBookingNotesFromAPI($bookingRef, $headers, $maxRetries = 2)
{
    $notesUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/notes/{$bookingRef}";

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $notesUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Success
        if ($httpCode === 200 && !empty($response)) {
            $notesData = json_decode($response, true);

            // แปลงข้อมูล Notes เป็น text
            if (isset($notesData['notes']) && isset($notesData['notes']['note_0'])) {
                $note = $notesData['notes']['note_0'];

                $noteText = $note['note'] ?? '';
                $noteDate = $note['notedate'] ?? '';
                $noteUser = $note['user'] ?? '';

                // เพิ่มข้อมูลเพิ่มเติม
                $flags = [];
                if (!empty($note['flightnoquery'])) $flags[] = 'Flight Query';
                if (!empty($note['wrongresort'])) $flags[] = 'Wrong Resort';

                $formattedNote = $noteText;
                if (!empty($noteDate)) $formattedNote .= "\n\nDate: " . $noteDate;
                if (!empty($noteUser)) $formattedNote .= "\nUser: " . $noteUser;
                if (!empty($flags)) $formattedNote .= "\nFlags: " . implode(', ', $flags);

                if ($attempt > 1) {
                    error_log("✓ Notes API SUCCESS for $bookingRef on attempt $attempt/$maxRetries");
                }

                return $formattedNote;
            } else {
                // No notes available (not an error)
                return null;
            }
        }

        // Log failure
        $errorMsg = "HTTP $httpCode";
        if (!empty($curlError)) {
            $errorMsg .= " | cURL Error: $curlError";
        }
        error_log("✗ Notes API FAILED for $bookingRef (attempt $attempt/$maxRetries) - $errorMsg");

        // Retry with backoff
        if ($attempt < $maxRetries) {
            usleep(300000); // 0.3s delay
        }
    }

    error_log("✗✗ Notes API FINAL FAILURE for $bookingRef after $maxRetries attempts");
    return null;
}

function processBookingData($searchBooking, $detailData, $notesText = null)
{
    error_log("DEBUG: ProcessBookingData for " . $searchBooking['ref'] . " - Notes: " . ($notesText ?: 'NULL'));

    $data = [
        // From search data
        'status' => $searchBooking['status'],
        'passenger_name' => $searchBooking['passengername'] ?? null,
        'passenger_phone' => $searchBooking['passengertelno'] ?? null,
        'vehicle_type' => $searchBooking['vehicle'] ?? null,
        'last_action_date' => $searchBooking['lastactiondate'] ?? date('Y-m-d H:i:s'),

        'notes' => $notesText,  // ← สำคัญ!!! ต้องมีบรรทัดนี้

        // Default values
        'pax_total' => 1,
        'adults' => 1,
        'children' => 0,
        'infants' => 0,
        'arrival_date' => $searchBooking['arrivaldate'] ?? null,
        'departure_date' => $searchBooking['departuredate'] ?? null,
        'pickup_date' => null,

        // Additional fields
        'booking_type' => null,
        'passenger_email' => null,
        'airport' => null,
        'airport_code' => null,
        'resort' => null,
        'accommodation_name' => null,
        'accommodation_address1' => null,
        'accommodation_address2' => null,
        'accommodation_tel' => null,
        'flight_no_arrival' => null,
        'flight_no_departure' => null,
        'from_airport' => null,
        'to_airport' => null,

        // Province fields (will be populated later)
        'province' => null,
        'province_source' => null,
        'province_confidence' => null
    ];

    // Process detail data if available
    if ($detailData && isset($detailData['booking'])) {
        $booking = $detailData['booking'];

        // General info
        if (isset($booking['general'])) {
            $general = $booking['general'];
            $data['booking_type'] = $general['bookingtype'] ?? null;
            $data['airport'] = $general['airport'] ?? null;
            $data['airport_code'] = $general['airportcode'] ?? null;
            $data['resort'] = $general['resort'] ?? null;
            $data['pax_total'] = (int)($general['pax'] ?? $data['pax_total']);
            $data['adults'] = (int)($general['adults'] ?? $data['adults']);
            $data['children'] = (int)($general['children'] ?? $data['children']);
            $data['infants'] = (int)($general['infants'] ?? $data['infants']);

            // Override with more accurate data from detail
            if (isset($general['passengername'])) $data['passenger_name'] = $general['passengername'];
            if (isset($general['passengertelno'])) $data['passenger_phone'] = $general['passengertelno'];
            if (isset($general['vehicle'])) $data['vehicle_type'] = $general['vehicle'];
        }

        // Arrival info
        if (isset($booking['arrival'])) {
            $arrival = $booking['arrival'];
            $data['arrival_date'] = $arrival['arrivaldate'] ?? $data['arrival_date'];
            $data['accommodation_name'] = $arrival['accommodationname'] ?? null;
            $data['accommodation_address1'] = $arrival['accommodationaddress1'] ?? null;
            $data['accommodation_address2'] = $arrival['accommodationaddress2'] ?? null;
            $data['accommodation_tel'] = $arrival['accommodationtel'] ?? null;
            $data['flight_no_arrival'] = $arrival['flightno'] ?? null;
            $data['from_airport'] = $arrival['fromairport'] ?? null;
        }

        // Departure info
        if (isset($booking['departure'])) {
            $departure = $booking['departure'];
            $data['departure_date'] = $departure['departuredate'] ?? $data['departure_date'];
            $data['pickup_date'] = $departure['pickupdate'] ?? null;
            $data['flight_no_departure'] = $departure['flightno'] ?? null;
            $data['to_airport'] = $departure['toairport'] ?? null;

            // Use departure accommodation if arrival not available
            if (!$data['accommodation_name']) {
                $data['accommodation_name'] = $departure['accommodationname'] ?? null;
                $data['accommodation_address1'] = $departure['accommodationaddress1'] ?? null;
                $data['accommodation_address2'] = $departure['accommodationaddress2'] ?? null;
                $data['accommodation_tel'] = $departure['accommodationtel'] ?? null;
            }
        }
    }

    // Determine pickup date if not set
    if (!$data['pickup_date']) {
        $data['pickup_date'] = $data['departure_date'] ?: $data['arrival_date'];
    }

    // Detect province using ProvinceMapping
    $provinceData = ProvinceMapping::detectProvince([
        'airport' => $data['airport'],
        'airport_code' => $data['airport_code'],
        'from_airport' => $data['from_airport'],
        'to_airport' => $data['to_airport'],
        'accommodation_address1' => $data['accommodation_address1'],
        'accommodation_address2' => $data['accommodation_address2']
    ]);

    $data['province'] = $provinceData['province'];
    $data['province_source'] = $provinceData['source'];
    $data['province_confidence'] = $provinceData['confidence'];

    // Prepare raw data
    $data['raw_data'] = json_encode([
        'search_data' => $searchBooking,
        'detail_data' => $detailData,
        'processed_at' => date('Y-m-d H:i:s')
    ]);

    error_log("DEBUG: Final processedData notes for " . $searchBooking['ref'] . ": " . ($data['notes'] ?: 'NULL'));
    error_log("DEBUG: Detected province for " . $searchBooking['ref'] . ": " . ($data['province'] ?: 'NULL') . " (source: " . ($data['province_source'] ?: 'N/A') . ")");

    return $data;
}

function updateExistingBooking($pdo, $bookingRef, $data)
{
    error_log("DEBUG: Updating booking $bookingRef - Notes: " . ($data['notes'] ?: 'NULL'));

    // *** เพิ่ม Protection สำหรับ Notes ***
    // ถ้า notes ใหม่เป็น null/empty แต่ใน DB มี notes อยู่แล้ว ให้คงไว้
    if (empty($data['notes'])) {
        // เช็คว่าใน DB มี notes อยู่หรือไม่
        $checkNotesSql = "SELECT notes FROM bookings WHERE booking_ref = :ref AND notes IS NOT NULL AND notes != ''";
        $checkNotesStmt = $pdo->prepare($checkNotesSql);
        $checkNotesStmt->execute([':ref' => $bookingRef]);
        $existingNotes = $checkNotesStmt->fetchColumn();

        if ($existingNotes) {
            // ถ้ามี notes อยู่แล้ว ไม่ต้อง update notes
            unset($data['notes']);
            error_log("DEBUG: Protecting existing notes for $bookingRef");
        }
    }

    // *** เพิ่ม Protection สำหรับ Province ***
    // ถ้า province ใหม่เป็น null/empty แต่ใน DB มี province อยู่แล้ว ให้คงไว้
    if (empty($data['province'])) {
        // เช็คว่าใน DB มี province อยู่หรือไม่
        $checkProvinceSql = "SELECT province FROM bookings WHERE booking_ref = :ref AND province IS NOT NULL AND province != ''";
        $checkProvinceStmt = $pdo->prepare($checkProvinceSql);
        $checkProvinceStmt->execute([':ref' => $bookingRef]);
        $existingProvince = $checkProvinceStmt->fetchColumn();

        if ($existingProvince) {
            // ถ้ามี province อยู่แล้ว ไม่ต้อง update province
            unset($data['province']);
            unset($data['province_source']);
            unset($data['province_confidence']);
            error_log("DEBUG: Protecting existing province for $bookingRef");
        }
    }

    // สร้าง SQL query แบบ dynamic ตาม data ที่เหลือ
    $updateFields = [];
    $params = [':ref' => $bookingRef];

    $fieldMapping = [
        'status' => 'ht_status',
        'passenger_name' => 'passenger_name',
        'passenger_email' => 'passenger_email',
        'passenger_phone' => 'passenger_phone',
        'pax_total' => 'pax_total',
        'adults' => 'adults',
        'children' => 'children',
        'infants' => 'infants',
        'booking_type' => 'booking_type',
        'vehicle_type' => 'vehicle_type',
        'airport' => 'airport',
        'airport_code' => 'airport_code',
        'resort' => 'resort',
        'accommodation_name' => 'accommodation_name',
        'accommodation_address1' => 'accommodation_address1',
        'accommodation_address2' => 'accommodation_address2',
        'accommodation_tel' => 'accommodation_tel',
        'arrival_date' => 'arrival_date',
        'departure_date' => 'departure_date',
        'pickup_date' => 'pickup_date',
        'flight_no_arrival' => 'flight_no_arrival',
        'flight_no_departure' => 'flight_no_departure',
        'from_airport' => 'from_airport',
        'to_airport' => 'to_airport',
        'last_action_date' => 'last_action_date',
        'raw_data' => 'raw_data',
        'notes' => 'notes',
        'province' => 'province',
        'province_source' => 'province_source',
        'province_confidence' => 'province_confidence'
    ];

    foreach ($fieldMapping as $dataKey => $dbColumn) {
        if (isset($data[$dataKey])) {
            $updateFields[] = "$dbColumn = :$dataKey";
            $params[":$dataKey"] = $data[$dataKey];
        }
    }

    // เพิ่ม timestamp fields
    $updateFields[] = "synced_at = NOW()";
    $updateFields[] = "updated_at = NOW()";

    $sql = "UPDATE bookings SET " . implode(', ', $updateFields) . " WHERE booking_ref = :ref";

    error_log("DEBUG: SQL for $bookingRef: " . $sql);
    error_log("DEBUG: Will update notes? " . (isset($data['notes']) ? 'YES' : 'NO'));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function insertNewBooking($pdo, $bookingRef, $data)
{
    error_log("DEBUG: Inserting new booking $bookingRef - Notes: " . ($data['notes'] ?: 'NULL'));
    error_log("DEBUG: Inserting new booking $bookingRef - Province: " . ($data['province'] ?: 'NULL'));

    // สำหรับ booking ใหม่ ให้ insert ตามปกติ (รวม notes และ province ถ้ามี)
    $sql = "INSERT INTO bookings (
                booking_ref, ht_status, passenger_name, passenger_email, passenger_phone,
                pax_total, adults, children, infants,
                booking_type, vehicle_type,
                airport, airport_code, resort,
                accommodation_name, accommodation_address1, accommodation_address2, accommodation_tel,
                arrival_date, departure_date, pickup_date,
                flight_no_arrival, flight_no_departure, from_airport, to_airport,
                province, province_source, province_confidence,
                last_action_date, raw_data, notes, synced_at
            ) VALUES (
                :ref, :status, :passenger_name, :passenger_email, :passenger_phone,
                :pax_total, :adults, :children, :infants,
                :booking_type, :vehicle_type,
                :airport, :airport_code, :resort,
                :accommodation_name, :accommodation_address1, :accommodation_address2, :accommodation_tel,
                :arrival_date, :departure_date, :pickup_date,
                :flight_no_arrival, :flight_no_departure, :from_airport, :to_airport,
                :province, :province_source, :province_confidence,
                :last_action_date, :raw_data, :notes, NOW()
            )";

    $stmt = $pdo->prepare($sql);
    $params = $data;
    $params[':ref'] = $bookingRef;

    error_log("DEBUG: About to execute INSERT for $bookingRef with notes: " . ($params['notes'] ?: 'NULL'));

    $stmt->execute($params);
}

function getDashboardStats($pdo)
{
    $dateFrom = date('Y-m-d H:i:s', strtotime('-7 days'));
    $dateTo = date('Y-m-d H:i:s');

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

    $stats = [
        'newBookings' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'amendments' => 0
    ];

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
    $todaySql = "SELECT COUNT(*) as today_total 
                 FROM bookings 
                 WHERE DATE(created_at) = CURDATE()";
    $todayStmt = $pdo->prepare($todaySql);
    $todayStmt->execute();
    $todayResult = $todayStmt->fetch();

    return [
        'stats' => $stats,
        'totalToday' => (int)$todayResult['today_total'],
        'dateRange' => ['from' => $dateFrom, 'to' => $dateTo]
    ];
}

function getEnhancedRecentBookings($pdo)
{
    // Show only upcoming pickups (today to +14 days)
    $sql = "SELECT
                booking_ref,
                ht_status,
                passenger_name,
                passenger_phone,
                pax_total,
                booking_type,
                vehicle_type,
                airport,
                resort,
                accommodation_name,
                arrival_date,
                departure_date,
                pickup_date,
                last_action_date,
                created_at,
                province,
                province_source,
                province_confidence
            FROM bookings
            WHERE pickup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
            ORDER BY pickup_date ASC, created_at DESC
            LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $bookings = $stmt->fetchAll();

    return array_map(function ($booking) {
        return [
            'ref' => $booking['booking_ref'],
            'status' => $booking['ht_status'],
            'passenger' => [
                'name' => $booking['passenger_name'] ?? '-',
                'phone' => $booking['passenger_phone']
            ],
            'pax' => (int)$booking['pax_total'],
            'bookingType' => $booking['booking_type'],
            'vehicle' => $booking['vehicle_type'],
            'airport' => $booking['airport'],
            'resort' => $booking['resort'],
            'accommodation' => [
                'name' => $booking['accommodation_name']
            ],
            'arrivalDate' => $booking['arrival_date'],
            'departureDate' => $booking['departure_date'],
            'pickupDate' => $booking['pickup_date'],
            'lastActionDate' => $booking['last_action_date'],
            'createdAt' => $booking['created_at'],
            'province' => $booking['province'],
            'province_source' => $booking['province_source'],
            'province_confidence' => $booking['province_confidence']
        ];
    }, $bookings);
}
