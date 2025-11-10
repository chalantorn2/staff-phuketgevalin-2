<?php
// api/dev/test-sync-arrivals.php - Test Sync using Arrivals API
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
    $input = json_decode(file_get_contents('php://input'), true);

    $dateFrom = $input['dateFrom'] ?? null;
    $dateTo = $input['dateTo'] ?? null;
    $page = $input['page'] ?? 1;

    if (!$dateFrom || !$dateTo) {
        throw new Exception('dateFrom and dateTo are required');
    }

    $db = new Database();
    $pdo = $db->getConnection();

    // Use TEST API
    $useTest = true;
    $searchUrl = HolidayTaxisConfig::getApiEndpoint($useTest) . "/bookings/search/arrivals/since/{$dateFrom}/until/{$dateTo}/page/{$page}";

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::getApiKey($useTest),
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::getApiVersion($useTest)
    ];

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

    // Accept both 200 and 204
    if ($httpCode === 204) {
        // No content - no bookings found
        echo json_encode([
            'success' => true,
            'data' => [
                'totalFound' => 0,
                'totalNew' => 0,
                'totalUpdated' => 0,
                'totalDetailed' => 0,
                'dateRange' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]
        ]);
        exit;
    }

    if ($httpCode !== 200) {
        throw new Exception("Holiday Taxis API error: HTTP $httpCode");
    }

    $searchData = json_decode($response, true);
    if (!$searchData || !isset($searchData['bookings'])) {
        throw new Exception('Invalid API response format');
    }

    // Convert bookings to array
    $bookingsData = $searchData['bookings'];
    if (is_object($bookingsData) || (is_array($bookingsData) && isset($bookingsData['booking_0']))) {
        $bookings = array_values((array)$bookingsData);
    } else {
        $bookings = $bookingsData;
    }

    error_log("Test Sync - Arrivals Page 1: " . count($bookings) . " bookings");

    // Pagination: Keep fetching until no more bookings
    $page = 2;
    $maxPages = 50; // Safety limit

    while ($page <= $maxPages) {
        $pageUrl = HolidayTaxisConfig::getApiEndpoint($useTest) . "/bookings/search/arrivals/since/{$dateFrom}/until/{$dateTo}/page/{$page}";

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

                error_log("Test Sync - Arrivals Page $page: " . $pageCount . " bookings");
                $bookings = array_merge($bookings, $pageBookings);
            } else {
                break;
            }
        } else {
            break;
        }

        $page++;
        usleep(100000); // 0.1 second delay
    }

    error_log("Test Sync - Arrivals Total: " . count($bookings) . " bookings across " . ($page - 1) . " pages");

    // === FETCH DEPARTURES ===
    $departuresUrl = HolidayTaxisConfig::getApiEndpoint($useTest) . "/bookings/search/departures/since/{$dateFrom}/until/{$dateTo}/page/1";

    $chDep = curl_init();
    curl_setopt_array($chDep, [
        CURLOPT_URL => $departuresUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $responseDep = curl_exec($chDep);
    $httpCodeDep = curl_getinfo($chDep, CURLINFO_HTTP_CODE);
    curl_close($chDep);

    $departureBookings = [];

    if ($httpCodeDep === 200) {
        $searchDataDep = json_decode($responseDep, true);
        if ($searchDataDep && isset($searchDataDep['bookings'])) {
            $bookingsDataDep = $searchDataDep['bookings'];
            if (is_object($bookingsDataDep) || (is_array($bookingsDataDep) && isset($bookingsDataDep['booking_0']))) {
                $departureBookings = array_values((array)$bookingsDataDep);
            } else {
                $departureBookings = $bookingsDataDep;
            }

            error_log("Test Sync - Departures Page 1: " . count($departureBookings) . " bookings");

            // Pagination for departures
            $page = 2;
            while ($page <= $maxPages) {
                $pageUrlDep = HolidayTaxisConfig::getApiEndpoint($useTest) . "/bookings/search/departures/since/{$dateFrom}/until/{$dateTo}/page/{$page}";

                $chPageDep = curl_init();
                curl_setopt_array($chPageDep, [
                    CURLOPT_URL => $pageUrlDep,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30
                ]);

                $pageResponseDep = curl_exec($chPageDep);
                $pageHttpCodeDep = curl_getinfo($chPageDep, CURLINFO_HTTP_CODE);
                curl_close($chPageDep);

                if ($pageHttpCodeDep === 204) {
                    break;
                }

                if ($pageHttpCodeDep === 200) {
                    $pageDataDep = json_decode($pageResponseDep, true);
                    if ($pageDataDep && isset($pageDataDep['bookings'])) {
                        $pageBookingsDataDep = $pageDataDep['bookings'];
                        if (is_object($pageBookingsDataDep) || (is_array($pageBookingsDataDep) && isset($pageBookingsDataDep['booking_0']))) {
                            $pageBookingsDep = array_values((array)$pageBookingsDataDep);
                        } else {
                            $pageBookingsDep = $pageBookingsDataDep;
                        }

                        $pageCountDep = count($pageBookingsDep);
                        if ($pageCountDep === 0) {
                            break;
                        }

                        error_log("Test Sync - Departures Page $page: " . $pageCountDep . " bookings");
                        $departureBookings = array_merge($departureBookings, $pageBookingsDep);
                    } else {
                        break;
                    }
                } else {
                    break;
                }

                $page++;
                usleep(100000);
            }

            error_log("Test Sync - Departures Total: " . count($departureBookings) . " bookings");
        }
    } elseif ($httpCodeDep === 204) {
        error_log("Test Sync - Departures: No bookings found (204)");
    }

    // Merge arrivals and departures
    $allBookings = array_merge($bookings, $departureBookings);

    // Remove duplicates
    $uniqueBookings = [];
    $seenRefs = [];
    foreach ($allBookings as $booking) {
        $ref = $booking['ref'] ?? '';
        if ($ref && !isset($seenRefs[$ref])) {
            $uniqueBookings[] = $booking;
            $seenRefs[$ref] = true;
        }
    }

    error_log("Test Sync - Combined: Arrivals=" . count($bookings) . ", Departures=" . count($departureBookings) . ", Total=" . count($allBookings) . ", Unique=" . count($uniqueBookings));

    $totalFound = count($uniqueBookings);
    $totalNew = 0;
    $totalUpdated = 0;
    $totalDetailed = 0;

    foreach ($uniqueBookings as $booking) {
        // Add "Test-" prefix to booking ref to distinguish from production bookings
        $originalRef = $booking['ref'];
        $testRef = 'Test-' . $originalRef;

        // Check if exists
        $checkSql = "SELECT id FROM bookings WHERE booking_ref = :ref";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':ref' => $testRef]);
        $exists = $checkStmt->fetch();

        // Get detail (use original ref for API call)
        $detailData = null;
        $detailUrl = HolidayTaxisConfig::getApiEndpoint($useTest) . "/bookings/{$originalRef}";

        $detailCh = curl_init();
        curl_setopt_array($detailCh, [
            CURLOPT_URL => $detailUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20
        ]);

        $detailResponse = curl_exec($detailCh);
        $detailHttpCode = curl_getinfo($detailCh, CURLINFO_HTTP_CODE);
        curl_close($detailCh);

        if ($detailHttpCode === 200) {
            $detailData = json_decode($detailResponse, true);
            $totalDetailed++;
        }

        // Process dates
        $arrivalDate = $booking['arrivaldate'] ?? null;
        $departureDate = $booking['departuredate'] ?? null;
        $pickupDate = null;

        if ($detailData && isset($detailData['booking'])) {
            $bookingDetail = $detailData['booking'];
            if (isset($bookingDetail['arrival']['arrivaldate'])) {
                $arrivalDate = $bookingDetail['arrival']['arrivaldate'];
            }
            if (isset($bookingDetail['departure']['departuredate'])) {
                $departureDate = $bookingDetail['departure']['departuredate'];
            }
            if (isset($bookingDetail['departure']['pickupdate'])) {
                $pickupDate = $bookingDetail['departure']['pickupdate'];
            }
        }

        if (!$pickupDate) {
            $pickupDate = $departureDate ?: $arrivalDate;
        }

        // Extract additional data
        $bookingType = null;
        $airport = null;
        $airportCode = null;
        $resort = null;
        $accommodationName = null;
        $accommodationAddress1 = null;
        $accommodationAddress2 = null;
        $accommodationTel = null;
        $flightNoArrival = null;
        $flightNoDeparture = null;

        if ($detailData && isset($detailData['booking']['general'])) {
            $general = $detailData['booking']['general'];
            $bookingType = $general['bookingtype'] ?? null;
            $airport = $general['airport'] ?? null;
            $airportCode = $general['airportcode'] ?? null;
            $resort = $general['resort'] ?? null;
        }

        if ($detailData && isset($detailData['booking']['arrival'])) {
            $arrival = $detailData['booking']['arrival'];
            $accommodationName = $arrival['accommodationname'] ?? null;
            $accommodationAddress1 = $arrival['accommodationaddress1'] ?? null;
            $accommodationAddress2 = $arrival['accommodationaddress2'] ?? null;
            $accommodationTel = $arrival['accommodationtel'] ?? null;
            $flightNoArrival = $arrival['flightno'] ?? null;
        }

        if ($detailData && isset($detailData['booking']['departure'])) {
            $departure = $detailData['booking']['departure'];
            $flightNoDeparture = $departure['flightno'] ?? null;
            if (!$accommodationName) {
                $accommodationName = $departure['accommodationname'] ?? null;
                $accommodationAddress1 = $departure['accommodationaddress1'] ?? null;
                $accommodationAddress2 = $departure['accommodationaddress2'] ?? null;
                $accommodationTel = $departure['accommodationtel'] ?? null;
            }
        }

        // Detect province
        $provinceData = ProvinceMapping::detectProvince([
            'airport' => $airport,
            'airport_code' => $airportCode,
            'accommodation_address1' => $accommodationAddress1,
            'accommodation_address2' => $accommodationAddress2
        ]);

        $combinedRawData = [
            'search_data' => $booking,
            'detail_data' => $detailData,
            'sync_timestamp' => date('Y-m-d H:i:s'),
            'is_test' => true,
            'original_ref' => $originalRef
        ];

        if ($exists) {
            // Update - ไม่อัพเดท province เพราะอาจถูกแก้ไข manual แล้ว
            $updateSql = "UPDATE bookings SET
                            ht_status = :status,
                            passenger_name = :passenger_name,
                            passenger_phone = :passenger_phone,
                            pax_total = :pax_total,
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
                            flight_no_arrival = :flight_no_arrival,
                            flight_no_departure = :flight_no_departure,
                            last_action_date = :last_action_date,
                            raw_data = :raw_data,
                            synced_at = NOW(),
                            updated_at = NOW()
                          WHERE booking_ref = :ref";

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':ref' => $testRef,
                ':status' => $booking['status'],
                ':passenger_name' => $booking['passengername'] ?? null,
                ':passenger_phone' => $booking['passengertelno'] ?? null,
                ':pax_total' => 1,
                ':booking_type' => $bookingType,
                ':vehicle_type' => $booking['vehicle'] ?? null,
                ':airport' => $airport,
                ':airport_code' => $airportCode,
                ':resort' => $resort,
                ':accommodation_name' => $accommodationName,
                ':accommodation_address1' => $accommodationAddress1,
                ':accommodation_address2' => $accommodationAddress2,
                ':accommodation_tel' => $accommodationTel,
                ':arrival_date' => $arrivalDate,
                ':departure_date' => $departureDate,
                ':pickup_date' => $pickupDate,
                ':flight_no_arrival' => $flightNoArrival,
                ':flight_no_departure' => $flightNoDeparture,
                ':last_action_date' => $booking['lastactiondate'] ?? date('Y-m-d H:i:s'),
                ':raw_data' => json_encode($combinedRawData)
            ]);

            $totalUpdated++;
        } else {
            // Insert - มีการตรวจจับ province ตั้งแต่ตอนเพิ่ม booking ใหม่
            $insertSql = "INSERT INTO bookings (
                            booking_ref, ht_status, passenger_name, passenger_phone,
                            pax_total, booking_type, vehicle_type,
                            airport, airport_code, resort,
                            accommodation_name, accommodation_address1, accommodation_address2, accommodation_tel,
                            arrival_date, departure_date, pickup_date,
                            flight_no_arrival, flight_no_departure,
                            province, province_source, province_confidence,
                            last_action_date, raw_data, synced_at
                          ) VALUES (
                            :ref, :status, :passenger_name, :passenger_phone,
                            :pax_total, :booking_type, :vehicle_type,
                            :airport, :airport_code, :resort,
                            :accommodation_name, :accommodation_address1, :accommodation_address2, :accommodation_tel,
                            :arrival_date, :departure_date, :pickup_date,
                            :flight_no_arrival, :flight_no_departure,
                            :province, :province_source, :province_confidence,
                            :last_action_date, :raw_data, NOW()
                          )";

            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ':ref' => $testRef,
                ':status' => $booking['status'],
                ':passenger_name' => $booking['passengername'] ?? null,
                ':passenger_phone' => $booking['passengertelno'] ?? null,
                ':pax_total' => 1,
                ':booking_type' => $bookingType,
                ':vehicle_type' => $booking['vehicle'] ?? null,
                ':airport' => $airport,
                ':airport_code' => $airportCode,
                ':resort' => $resort,
                ':accommodation_name' => $accommodationName,
                ':accommodation_address1' => $accommodationAddress1,
                ':accommodation_address2' => $accommodationAddress2,
                ':accommodation_tel' => $accommodationTel,
                ':arrival_date' => $arrivalDate,
                ':departure_date' => $departureDate,
                ':pickup_date' => $pickupDate,
                ':flight_no_arrival' => $flightNoArrival,
                ':flight_no_departure' => $flightNoDeparture,
                ':province' => $provinceData['province'],
                ':province_source' => $provinceData['source'],
                ':province_confidence' => $provinceData['confidence'],
                ':last_action_date' => $booking['lastactiondate'] ?? date('Y-m-d H:i:s'),
                ':raw_data' => json_encode($combinedRawData)
            ]);

            $totalNew++;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'totalFound' => $totalFound,
            'totalNew' => $totalNew,
            'totalUpdated' => $totalUpdated,
            'totalDetailed' => $totalDetailed,
            'dateRange' => [
                'from' => $dateFrom,
                'to' => $dateTo
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
