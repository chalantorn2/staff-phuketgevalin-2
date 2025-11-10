<?php
// api/dev/debug-sync.php - Debug Sync Process
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    echo "<h1>Debug Sync Process</h1>";
    echo "<pre>";

    // Test Date Formats
    echo "=== DATE FORMATS ===\n";
    $today = date('Y-m-d');
    $todayTime = date('Y-m-d\TH:i:s');
    $target = '2025-10-27';
    $targetTime = date('Y-m-d\TH:i:s', strtotime('2025-10-27'));
    $dateFrom = date('Y-m-d\T00:00:00');
    $dateTo = date('Y-m-d\T23:59:59', strtotime('+14 days'));

    echo "Today (date): $today\n";
    echo "Today (datetime): $todayTime\n";
    echo "Target date: $target\n";
    echo "Target datetime: $targetTime\n";
    echo "Query From: $dateFrom\n";
    echo "Query To: $dateTo\n";
    echo "\n";

    // Test API Call
    echo "=== API CALL TEST ===\n";
    $searchUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/arrivals/since/{$dateFrom}/until/{$dateTo}/page/1";
    echo "URL: $searchUrl\n\n";

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
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
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    if ($curlError) {
        echo "cURL Error: $curlError\n";
    }
    echo "\n";

    if ($httpCode === 200) {
        $data = json_decode($response, true);

        if (isset($data['bookings'])) {
            $bookingsData = $data['bookings'];

            // Convert to array
            if (is_object($bookingsData) || (is_array($bookingsData) && isset($bookingsData['booking_0']))) {
                $bookings = array_values((array)$bookingsData);
            } else {
                $bookings = $bookingsData;
            }

            echo "Total bookings found (page 1): " . count($bookings) . "\n";

            // Check if there are more pages
            $totalPages = 1;
            if (isset($data['meta']['total_pages'])) {
                $totalPages = $data['meta']['total_pages'];
            } elseif (isset($data['pagination']['total_pages'])) {
                $totalPages = $data['pagination']['total_pages'];
            }

            echo "Total pages available: $totalPages\n";

            // Fetch all pages (max 5 pages for testing)
            if ($totalPages > 1) {
                $maxPages = min($totalPages, 5);
                echo "Fetching pages 2-$maxPages...\n";

                for ($page = 2; $page <= $maxPages; $page++) {
                    $pageUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/arrivals/since/{$dateFrom}/until/{$dateTo}/page/{$page}";

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $pageUrl,
                        CURLOPT_HTTPHEADER => $headers,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 30
                    ]);

                    $pageResponse = curl_exec($ch);
                    $pageHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($pageHttpCode === 200) {
                        $pageData = json_decode($pageResponse, true);
                        if (isset($pageData['bookings'])) {
                            $pageBookingsData = $pageData['bookings'];
                            if (is_object($pageBookingsData) || (is_array($pageBookingsData) && isset($pageBookingsData['booking_0']))) {
                                $pageBookings = array_values((array)$pageBookingsData);
                            } else {
                                $pageBookings = $pageBookingsData;
                            }
                            $bookings = array_merge($bookings, $pageBookings);
                            echo "  Page $page: " . count($pageBookings) . " bookings\n";
                        }
                    }

                    usleep(200000); // 0.2 second delay
                }
            }

            echo "\nTotal bookings (all pages): " . count($bookings) . "\n\n";

            // Show first 5 bookings
            echo "=== FIRST 5 BOOKINGS ===\n";
            foreach (array_slice($bookings, 0, 5) as $booking) {
                echo "Ref: " . ($booking['ref'] ?? 'N/A') . "\n";
                echo "  Status: " . ($booking['status'] ?? 'N/A') . "\n";
                echo "  Arrival Date: " . ($booking['arrivaldate'] ?? 'N/A') . "\n";
                echo "  Departure Date: " . ($booking['departuredate'] ?? 'N/A') . "\n";
                echo "  Last Action: " . ($booking['lastactiondate'] ?? 'N/A') . "\n";
                echo "\n";
            }

            // Check for specific date
            echo "=== BOOKINGS ON 2025-10-27 ===\n";
            $targetBookings = array_filter($bookings, function ($b) {
                $arrivalDate = $b['arrivaldate'] ?? '';
                $departureDate = $b['departuredate'] ?? '';
                return strpos($arrivalDate, '2025-10-27') !== false ||
                    strpos($departureDate, '2025-10-27') !== false;
            });

            echo "Found " . count($targetBookings) . " bookings on 2025-10-27\n\n";

            foreach ($targetBookings as $booking) {
                echo "Ref: " . ($booking['ref'] ?? 'N/A') . "\n";
                echo "  Passenger: " . ($booking['passengername'] ?? 'N/A') . "\n";
                echo "  Arrival Date: " . ($booking['arrivaldate'] ?? 'N/A') . "\n";
                echo "  Departure Date: " . ($booking['departuredate'] ?? 'N/A') . "\n";
                echo "\n";
            }
        } else {
            echo "No bookings in response\n";
            echo "Response structure: " . print_r(array_keys($data), true) . "\n";
        }
    } elseif ($httpCode === 204) {
        echo "204 No Content - No bookings found\n";
    } else {
        echo "Error Response:\n";
        echo $response . "\n";
    }

    echo "\n";

    // Check Database
    echo "=== DATABASE CHECK ===\n";
    $dbSql = "SELECT booking_ref, pickup_date, arrival_date, departure_date, ht_status, created_at
              FROM bookings
              WHERE (pickup_date LIKE '2025-10-27%'
                     OR arrival_date LIKE '2025-10-27%'
                     OR departure_date LIKE '2025-10-27%')
              ORDER BY created_at DESC";

    $stmt = $pdo->prepare($dbSql);
    $stmt->execute();
    $dbBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Bookings in database for 2025-10-27: " . count($dbBookings) . "\n\n";

    foreach ($dbBookings as $booking) {
        echo "Ref: " . $booking['booking_ref'] . "\n";
        echo "  Status: " . $booking['ht_status'] . "\n";
        echo "  Arrival: " . ($booking['arrival_date'] ?? 'N/A') . "\n";
        echo "  Departure: " . ($booking['departure_date'] ?? 'N/A') . "\n";
        echo "  Pickup: " . ($booking['pickup_date'] ?? 'N/A') . "\n";
        echo "  Created: " . $booking['created_at'] . "\n";
        echo "\n";
    }

    // Check Dashboard Query
    echo "=== DASHBOARD QUERY CHECK ===\n";
    $dashboardSql = "SELECT booking_ref, pickup_date, arrival_date, departure_date
                     FROM bookings
                     WHERE pickup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
                     ORDER BY pickup_date ASC
                     LIMIT 10";

    $stmt = $pdo->prepare($dashboardSql);
    $stmt->execute();
    $dashboardBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Bookings in Dashboard query: " . count($dashboardBookings) . "\n\n";

    foreach ($dashboardBookings as $booking) {
        echo "Ref: " . $booking['booking_ref'] . " | Pickup: " . ($booking['pickup_date'] ?? 'N/A') . "\n";
    }

    // === TEST SPECIFIC DATE RANGE ===
    echo "\n=== TEST SPECIFIC DATE (2025-10-27 only) ===\n";
    $specificFrom = '2025-10-27T00:00:00';
    $specificTo = '2025-10-27T23:59:59';
    $specificUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/arrivals/since/{$specificFrom}/until/{$specificTo}/page/1";

    echo "Testing date range: $specificFrom to $specificTo\n";
    echo "URL: $specificUrl\n\n";

    $chSpecific = curl_init();
    curl_setopt_array($chSpecific, [
        CURLOPT_URL => $specificUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $specificResponse = curl_exec($chSpecific);
    $specificHttpCode = curl_getinfo($chSpecific, CURLINFO_HTTP_CODE);
    curl_close($chSpecific);

    echo "HTTP Code: $specificHttpCode\n";

    if ($specificHttpCode === 200) {
        $specificData = json_decode($specificResponse, true);
        if (isset($specificData['bookings'])) {
            $specificBookingsData = $specificData['bookings'];
            if (is_object($specificBookingsData) || (is_array($specificBookingsData) && isset($specificBookingsData['booking_0']))) {
                $specificBookings = array_values((array)$specificBookingsData);
            } else {
                $specificBookings = $specificBookingsData;
            }

            echo "Bookings found for 2025-10-27: " . count($specificBookings) . "\n\n";

            foreach (array_slice($specificBookings, 0, 10) as $booking) {
                echo "Ref: " . ($booking['ref'] ?? 'N/A') . "\n";
                echo "  Passenger: " . ($booking['passengername'] ?? 'N/A') . "\n";
                echo "  Arrival Date: " . ($booking['arrivaldate'] ?? 'N/A') . "\n";
                echo "  Status: " . ($booking['status'] ?? 'N/A') . "\n";
                echo "\n";
            }
        } else {
            echo "No bookings found\n";
        }
    } elseif ($specificHttpCode === 204) {
        echo "204 No Content - No bookings on this date\n";
    } else {
        echo "Error: $specificResponse\n";
    }

    echo "</pre>";
} catch (Exception $e) {
    echo "<pre>Error: " . $e->getMessage() . "</pre>";
}
