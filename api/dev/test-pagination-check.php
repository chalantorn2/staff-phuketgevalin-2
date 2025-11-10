<?php
// api/dev/test-pagination-check.php - Test if Holiday Taxis API has pagination for 27/10
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';

try {
    echo "<h1>Test Pagination for 2025-10-27</h1>";
    echo "<pre>";

    $targetDate = '2025-10-27';
    $dayFrom = '2025-10-27T00:00:00';
    $dayTo = '2025-10-27T23:59:59';

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    echo "=== TESTING PAGINATION FOR $targetDate ===\n\n";

    $allBookings = [];
    $page = 1;
    $maxPages = 10; // Test up to 10 pages

    while ($page <= $maxPages) {
        $searchUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/arrivals/since/{$dayFrom}/until/{$dayTo}/page/{$page}";

        echo "Testing Page $page:\n";
        echo "  URL: $searchUrl\n";

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

        echo "  HTTP Code: $httpCode\n";

        if ($httpCode === 200) {
            $data = json_decode($response, true);

            if ($data && isset($data['bookings'])) {
                $bookingsData = $data['bookings'];

                // Convert to array
                if (is_object($bookingsData) || (is_array($bookingsData) && isset($bookingsData['booking_0']))) {
                    $pageBookings = array_values((array)$bookingsData);
                } else {
                    $pageBookings = $bookingsData;
                }

                $count = count($pageBookings);
                echo "  Bookings found: $count\n";

                if ($count > 0) {
                    $allBookings = array_merge($allBookings, $pageBookings);

                    // Show first and last booking ref
                    echo "  First: " . ($pageBookings[0]['ref'] ?? 'N/A') . "\n";
                    echo "  Last: " . ($pageBookings[$count-1]['ref'] ?? 'N/A') . "\n";
                } else {
                    echo "  No more bookings - stopping\n";
                    break;
                }

                // Check for pagination info
                echo "  Response keys: " . implode(', ', array_keys($data)) . "\n";

                if (isset($data['meta'])) {
                    echo "  Meta: " . json_encode($data['meta']) . "\n";
                }
                if (isset($data['pagination'])) {
                    echo "  Pagination: " . json_encode($data['pagination']) . "\n";
                }
                if (isset($data['more'])) {
                    echo "  More: " . $data['more'] . "\n";
                }

            } else {
                echo "  No bookings in response\n";
                break;
            }
        } elseif ($httpCode === 204) {
            echo "  204 No Content - No more bookings\n";
            break;
        } else {
            echo "  ERROR Response: " . substr($response, 0, 200) . "\n";
            break;
        }

        echo "\n";
        $page++;
        usleep(200000); // 0.2 second delay
    }

    // Remove duplicates
    $uniqueRefs = [];
    $uniqueBookings = [];
    foreach ($allBookings as $booking) {
        $ref = $booking['ref'] ?? '';
        if ($ref && !isset($uniqueRefs[$ref])) {
            $uniqueBookings[] = $booking;
            $uniqueRefs[$ref] = true;
        }
    }

    echo "=== SUMMARY ===\n";
    echo "Total pages tested: " . ($page - 1) . "\n";
    echo "Total bookings found: " . count($allBookings) . "\n";
    echo "Unique bookings: " . count($uniqueBookings) . "\n";

    if (count($uniqueBookings) > 20) {
        echo "\n⚠️ FOUND MORE THAN 20 BOOKINGS!\n";
        echo "This confirms that pagination is needed.\n";
    } else {
        echo "\n✓ Only " . count($uniqueBookings) . " bookings on this date.\n";
        echo "Pagination may not be needed for this specific date.\n";
    }

    echo "\n=== ALL BOOKING REFS FOR $targetDate ===\n";
    foreach ($uniqueBookings as $booking) {
        echo $booking['ref'] . " - " . ($booking['passengername'] ?? 'N/A') . "\n";
    }

    echo "</pre>";

} catch (Exception $e) {
    echo "<pre>Error: " . $e->getMessage() . "</pre>";
}
