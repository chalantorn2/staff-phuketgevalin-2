<?php
// api/sync/manual-sync-arrivals.php - Manual Sync with Custom Date Range
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';
require_once '../config/province-mapping.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get input parameters
    $input = json_decode(file_get_contents('php://input'), true);

    $dateFrom = $input['date_from'] ?? null;
    $dateTo = $input['date_to'] ?? null;

    if (!$dateFrom || !$dateTo) {
        throw new Exception('date_from and date_to are required');
    }

    // Validate date format
    $fromDate = new DateTime($dateFrom);
    $toDate = new DateTime($dateTo);

    if ($fromDate > $toDate) {
        throw new Exception('date_from must be before or equal to date_to');
    }

    // Calculate days
    $totalDays = (int)$fromDate->diff($toDate)->days + 1;

    // Limit to prevent overload
    if ($totalDays > 30) {
        throw new Exception('Maximum 30 days per sync. Please reduce date range.');
    }

    error_log("Manual Sync - Fetching arrivals for $totalDays days from " . $fromDate->format('Y-m-d') . " to " . $toDate->format('Y-m-d'));

    // Log sync start
    $syncStartSql = "INSERT INTO sync_status (sync_type, date_from, date_to, status, started_at)
                     VALUES ('manual-sync', :date_from, :date_to, 'running', NOW())";
    $syncStartStmt = $pdo->prepare($syncStartSql);
    $syncStartStmt->execute([
        ':date_from' => $dateFrom,
        ':date_to' => $dateTo
    ]);
    $syncId = $pdo->lastInsertId();

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    $allArrivals = [];
    $allDepartures = [];

    // Query each day separately - ARRIVALS
    error_log("Manual Sync - Fetching ARRIVALS for $totalDays days");
    for ($i = 0; $i < $totalDays; $i++) {
        $currentDate = clone $fromDate;
        $currentDate->modify("+{$i} days");

        $dayFrom = $currentDate->format('Y-m-d\T00:00:00');
        $dayTo = $currentDate->format('Y-m-d\T23:59:59');

        $searchUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/arrivals/since/{$dayFrom}/until/{$dayTo}/page/1";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $searchUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['bookings'])) {
                $bookingsData = $data['bookings'];
                if (is_object($bookingsData) || (is_array($bookingsData) && isset($bookingsData['booking_0']))) {
                    $dayBookings = array_values((array)$bookingsData);
                } else {
                    $dayBookings = $bookingsData;
                }

                $dayTotal = count($dayBookings);
                if ($dayTotal > 0) {
                    error_log("Manual Sync - Arrivals " . $currentDate->format('Y-m-d') . " Page 1: " . $dayTotal . " bookings");
                    $allArrivals = array_merge($allArrivals, $dayBookings);
                }

                // Pagination: Keep fetching until no more bookings
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

                            error_log("Manual Sync - Arrivals " . $currentDate->format('Y-m-d') . " Page $page: " . $pageCount . " bookings");
                            $allArrivals = array_merge($allArrivals, $pageBookings);
                            $dayTotal += $pageCount;
                        } else {
                            break;
                        }
                    } else {
                        break;
                    }

                    $page++;
                    usleep(100000); // 0.1 second delay
                }

                if ($dayTotal > 0) {
                    error_log("Manual Sync - Arrivals " . $currentDate->format('Y-m-d') . " Total: " . $dayTotal . " bookings across " . ($page - 1) . " pages");
                }
            }
        }

        usleep(100000); // 0.1 second delay between days
    }

    // Query each day separately - DEPARTURES
    error_log("Manual Sync - Fetching DEPARTURES for $totalDays days");
    for ($i = 0; $i < $totalDays; $i++) {
        $currentDate = clone $fromDate;
        $currentDate->modify("+{$i} days");

        $dayFrom = $currentDate->format('Y-m-d\T00:00:00');
        $dayTo = $currentDate->format('Y-m-d\T23:59:59');

        $searchUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/departures/since/{$dayFrom}/until/{$dayTo}/page/1";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $searchUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['bookings'])) {
                $bookingsData = $data['bookings'];
                if (is_object($bookingsData) || (is_array($bookingsData) && isset($bookingsData['booking_0']))) {
                    $dayBookings = array_values((array)$bookingsData);
                } else {
                    $dayBookings = $bookingsData;
                }

                $dayTotal = count($dayBookings);
                if ($dayTotal > 0) {
                    error_log("Manual Sync - Departures " . $currentDate->format('Y-m-d') . " Page 1: " . $dayTotal . " bookings");
                    $allDepartures = array_merge($allDepartures, $dayBookings);
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

                            error_log("Manual Sync - Departures " . $currentDate->format('Y-m-d') . " Page $page: " . $pageCount . " bookings");
                            $allDepartures = array_merge($allDepartures, $pageBookings);
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
                    error_log("Manual Sync - Departures " . $currentDate->format('Y-m-d') . " Total: " . $dayTotal . " bookings across " . ($page - 1) . " pages");
                }
            }
        }

        usleep(100000);
    }

    // Merge arrivals and departures, then remove duplicates
    $allBookings = array_merge($allArrivals, $allDepartures);
    $uniqueBookings = [];
    $seenRefs = [];
    foreach ($allBookings as $booking) {
        $ref = $booking['ref'] ?? '';
        if ($ref && !isset($seenRefs[$ref])) {
            $uniqueBookings[] = $booking;
            $seenRefs[$ref] = true;
        }
    }

    error_log("Manual Sync - Arrivals: " . count($allArrivals) . ", Departures: " . count($allDepartures) . ", Total: " . count($allBookings) . ", Unique: " . count($uniqueBookings));

    $totalFound = count($uniqueBookings);
    $totalNew = 0;
    $totalUpdated = 0;
    $totalFailed = 0;
    $failedRefs = [];

    // Process bookings
    foreach ($uniqueBookings as $booking) {
        try {
            $ref = $booking['ref'] ?? '';
            if (!$ref) continue;

            // Check if exists
            $checkSql = "SELECT id FROM bookings WHERE booking_ref = :ref";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':ref' => $ref]);
            $exists = $checkStmt->fetch();

            // Get full booking details (with retry)
            $detailData = null;
            $maxRetries = 3;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $detailUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$ref}";
                $chDetail = curl_init();
                curl_setopt_array($chDetail, [
                    CURLOPT_URL => $detailUrl,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);

                $detailResponse = curl_exec($chDetail);
                $detailHttpCode = curl_getinfo($chDetail, CURLINFO_HTTP_CODE);
                $curlError = curl_error($chDetail);
                curl_close($chDetail);

                // Success
                if ($detailHttpCode === 200 && !empty($detailResponse)) {
                    $data = json_decode($detailResponse, true);
                    if ($data !== null && isset($data['booking'])) {
                        $detailData = $data;
                        if ($attempt > 1) {
                            error_log("✓ Manual Sync - Detail API SUCCESS for $ref on attempt $attempt/$maxRetries");
                        }
                        break;
                    }
                }

                // Log failure
                $errorMsg = "HTTP $detailHttpCode";
                if (!empty($curlError)) {
                    $errorMsg .= " | cURL Error: $curlError";
                }
                error_log("✗ Manual Sync - Detail API FAILED for $ref (attempt $attempt/$maxRetries) - $errorMsg");

                // Retry with exponential backoff
                if ($attempt < $maxRetries) {
                    $delay = $attempt * 500000; // 0.5s, 1s, 1.5s
                    usleep($delay);
                }
            }

            // Skip if failed after all retries
            if ($detailData === null) {
                error_log("✗✗✗ Manual Sync - Detail API FINAL FAILURE for $ref after $maxRetries attempts - skipping");
                $totalFailed++;
                $failedRefs[] = $ref;
                continue;
            }

            if (!isset($detailData['booking'])) continue;

            $fullBooking = $detailData['booking'];

            // Process booking data
            $processedData = processBookingData($fullBooking, $booking);

            if ($exists) {
                // Update
                $updateSql = "UPDATE bookings SET
                    ht_status = :ht_status,
                    passenger_name = :passenger_name,
                    passenger_email = :passenger_email,
                    passenger_phone = :passenger_phone,
                    pax_total = :pax_total,
                    adults = :adults,
                    children = :children,
                    infants = :infants,
                    booking_type = :booking_type,
                    vehicle_type = :vehicle_type,
                    airport = :airport,
                    airport_code = :airport_code,
                    resort = :resort,
                    accommodation_name = :accommodation_name,
                    accommodation_address1 = :accommodation_address1,
                    accommodation_address2 = :accommodation_address2,
                    accommodation_tel = :accommodation_tel,
                    arrival_date = :arrival_date,
                    departure_date = :departure_date,
                    pickup_date = :pickup_date,
                    last_action_date = :last_action_date,
                    date_booked = :date_booked,
                    flight_no_arrival = :flight_no_arrival,
                    flight_no_departure = :flight_no_departure,
                    from_airport = :from_airport,
                    to_airport = :to_airport,
                    raw_data = :raw_data,
                    notes = :notes,
                    province = :province,
                    province_source = :province_source,
                    province_confidence = :province_confidence,
                    synced_at = NOW(),
                    updated_at = NOW()
                WHERE booking_ref = :booking_ref";

                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute($processedData);
                $totalUpdated++;
            } else {
                // Insert
                $insertSql = "INSERT INTO bookings (
                    booking_ref, ht_status, passenger_name, passenger_email, passenger_phone,
                    pax_total, adults, children, infants, booking_type, vehicle_type,
                    airport, airport_code, resort, accommodation_name,
                    accommodation_address1, accommodation_address2, accommodation_tel,
                    arrival_date, departure_date, pickup_date, last_action_date, date_booked,
                    flight_no_arrival, flight_no_departure, from_airport, to_airport,
                    raw_data, notes, province, province_source, province_confidence,
                    synced_at, created_at
                ) VALUES (
                    :booking_ref, :ht_status, :passenger_name, :passenger_email, :passenger_phone,
                    :pax_total, :adults, :children, :infants, :booking_type, :vehicle_type,
                    :airport, :airport_code, :resort, :accommodation_name,
                    :accommodation_address1, :accommodation_address2, :accommodation_tel,
                    :arrival_date, :departure_date, :pickup_date, :last_action_date, :date_booked,
                    :flight_no_arrival, :flight_no_departure, :from_airport, :to_airport,
                    :raw_data, :notes, :province, :province_source, :province_confidence,
                    NOW(), NOW()
                )";

                $insertStmt = $pdo->prepare($insertSql);
                $insertStmt->execute($processedData);
                $totalNew++;
            }

            usleep(50000); // 0.05 second delay between detail queries
        } catch (Exception $e) {
            error_log("Manual Sync - Error processing booking {$ref}: " . $e->getMessage());
            $totalFailed++;
            $failedRefs[] = $ref;
        }
    }

    // Update sync status
    $syncEndSql = "UPDATE sync_status SET
        status = 'completed',
        total_found = :total_found,
        total_new = :total_new,
        total_updated = :total_updated,
        completed_at = NOW()
    WHERE id = :sync_id";

    $syncEndStmt = $pdo->prepare($syncEndSql);
    $syncEndStmt->execute([
        ':total_found' => $totalFound,
        ':total_new' => $totalNew,
        ':total_updated' => $totalUpdated,
        ':sync_id' => $syncId
    ]);

    // Build success message
    $message = "Manual sync completed: {$totalNew} new, {$totalUpdated} updated out of {$totalFound} found";
    if ($totalFailed > 0) {
        $message .= " ({$totalFailed} failed - will be auto-fixed by Backfill Job)";
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'sync_id' => $syncId,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'total_days' => $totalDays,
            'total_found' => $totalFound,
            'total_new' => $totalNew,
            'total_updated' => $totalUpdated,
            'total_failed' => $totalFailed,
            'failed_refs' => $failedRefs,
            'backfill_suggestion' => $totalFailed > 0 ? 'Some bookings failed. They will be auto-fixed by Backfill Job within 15-30 minutes.' : null
        ],
        'message' => $message
    ]);
} catch (Exception $e) {
    error_log("Manual Sync Error: " . $e->getMessage());

    if (isset($syncId)) {
        $errorSql = "UPDATE sync_status SET status = 'failed', completed_at = NOW() WHERE id = :sync_id";
        $errorStmt = $pdo->prepare($errorSql);
        $errorStmt->execute([':sync_id' => $syncId]);
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function processBookingData($fullBooking, $searchBooking)
{
    $general = $fullBooking['general'] ?? [];
    $arrival = $fullBooking['arrival'] ?? [];
    $departure = $fullBooking['departure'] ?? [];

    // Extract accommodation data (try arrival first, then departure)
    $accommodationName = $arrival['accommodationname'] ?? null;
    $accommodationAddress1 = $arrival['accommodationaddress1'] ?? null;
    $accommodationAddress2 = $arrival['accommodationaddress2'] ?? null;
    $accommodationTel = $arrival['accommodationtel'] ?? null;

    // Fallback to departure if arrival doesn't have accommodation
    if (empty($accommodationName) && !empty($departure)) {
        $accommodationName = $departure['accommodationname'] ?? null;
        $accommodationAddress1 = $departure['accommodationaddress1'] ?? null;
        $accommodationAddress2 = $departure['accommodationaddress2'] ?? null;
        $accommodationTel = $departure['accommodationtel'] ?? null;
    }

    // Detect province (use accommodation data from whichever source we got it)
    $provinceData = ProvinceMapping::detectProvince([
        'airport' => $general['airport'] ?? null,
        'airport_code' => $general['airportcode'] ?? null,
        'from_airport' => $arrival['fromairport'] ?? null,
        'to_airport' => $departure['toairport'] ?? null,
        'accommodation_address1' => $accommodationAddress1,
        'accommodation_address2' => $accommodationAddress2
    ]);

    $pickupDate = null;
    if (!empty($arrival['arrivaldate'])) {
        $pickupDate = $arrival['arrivaldate'];
    } elseif (!empty($departure['pickupdate'])) {
        $pickupDate = $departure['pickupdate'];
    }

    $arrivalDate = $arrival['arrivaldate'] ?? null;
    $departureDate = $departure['departuredate'] ?? null;
    $lastActionDate = $searchBooking['lastactiondate'] ?? $general['datebooked'] ?? null;

    // Combine notes
    $notesArray = [];
    if (isset($fullBooking['notes']) && is_array($fullBooking['notes'])) {
        foreach ($fullBooking['notes'] as $note) {
            if (is_array($note) && isset($note['notecontent'])) {
                $notesArray[] = $note['notecontent'];
            } elseif (is_string($note)) {
                $notesArray[] = $note;
            }
        }
    }
    $notes = implode("\n\n", $notesArray);

    $rawData = [
        'search_data' => $searchBooking,
        'detail_data' => $fullBooking,
        'processed_at' => date('Y-m-d H:i:s')
    ];

    return [
        ':booking_ref' => $general['ref'] ?? '',
        ':ht_status' => $general['status'] ?? 'PCON',
        ':passenger_name' => $general['passengername'] ?? null,
        ':passenger_email' => $general['passengeremail'] ?? null,
        ':passenger_phone' => $general['passengertelno'] ?? null,
        ':pax_total' => $general['pax'] ?? 1,
        ':adults' => $general['adults'] ?? 1,
        ':children' => $general['children'] ?? 0,
        ':infants' => $general['infants'] ?? 0,
        ':booking_type' => $general['bookingtype'] ?? null,
        ':vehicle_type' => $general['vehicle'] ?? null,
        ':airport' => $general['airport'] ?? null,
        ':airport_code' => $general['airportcode'] ?? null,
        ':resort' => $general['resort'] ?? null,
        ':accommodation_name' => $accommodationName,
        ':accommodation_address1' => $accommodationAddress1,
        ':accommodation_address2' => $accommodationAddress2,
        ':accommodation_tel' => $accommodationTel,
        ':arrival_date' => $arrivalDate,
        ':departure_date' => $departureDate,
        ':pickup_date' => $pickupDate,
        ':last_action_date' => $lastActionDate,
        ':date_booked' => $general['datebooked'] ?? null,
        ':flight_no_arrival' => $arrival['flightno'] ?? null,
        ':flight_no_departure' => $departure['flightno'] ?? null,
        ':from_airport' => $arrival['fromairport'] ?? null,
        ':to_airport' => $departure['toairport'] ?? null,
        ':raw_data' => json_encode($rawData),
        ':notes' => $notes,
        ':province' => $provinceData['province'],
        ':province_source' => $provinceData['source'],
        ':province_confidence' => $provinceData['confidence']
    ];
}
