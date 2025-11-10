<?php
// api/dev/compare-apis.php - Compare different API endpoints for 27/10
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';

try {
    echo "<h1>Compare API Endpoints for 2025-10-27</h1>";
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

    $endpoints = [
        'Last Action Date' => "/bookings/search/since/{$dayFrom}/until/{$dayTo}",
        'Arrival Date' => "/bookings/search/arrivals/since/{$dayFrom}/until/{$dayTo}",
        'Departure Date' => "/bookings/search/departures/since/{$dayFrom}/until/{$dayTo}"
    ];

    $results = [];

    foreach ($endpoints as $name => $endpoint) {
        echo "=== ENDPOINT: $name ===\n";
        echo "URL: " . HolidayTaxisConfig::API_ENDPOINT . $endpoint . "/page/1\n\n";

        $allBookings = [];
        $page = 1;
        $maxPages = 10;

        while ($page <= $maxPages) {
            $url = HolidayTaxisConfig::API_ENDPOINT . $endpoint . "/page/{$page}";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 204) {
                echo "Page $page: 204 No Content\n";
                break;
            }

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if ($data && isset($data['bookings'])) {
                    $bookingsData = $data['bookings'];
                    if (is_object($bookingsData) || (is_array($bookingsData) && isset($bookingsData['booking_0']))) {
                        $pageBookings = array_values((array)$bookingsData);
                    } else {
                        $pageBookings = $bookingsData;
                    }

                    $count = count($pageBookings);
                    if ($count === 0) {
                        echo "Page $page: 0 bookings (stopping)\n";
                        break;
                    }

                    echo "Page $page: $count bookings\n";
                    $allBookings = array_merge($allBookings, $pageBookings);
                } else {
                    echo "Page $page: No bookings in response\n";
                    break;
                }
            } else {
                echo "Page $page: HTTP $httpCode\n";
                break;
            }

            $page++;
            usleep(200000);
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

        $results[$name] = [
            'total' => count($uniqueBookings),
            'refs' => array_column($uniqueBookings, 'ref')
        ];

        echo "Total unique: " . count($uniqueBookings) . " bookings\n\n";
    }

    // Compare results
    echo "=== COMPARISON ===\n\n";

    foreach ($results as $name => $data) {
        echo "$name: " . $data['total'] . " bookings\n";
    }

    echo "\n=== UNIQUE TO EACH ENDPOINT ===\n\n";

    $allRefs = [];
    foreach ($results as $name => $data) {
        foreach ($data['refs'] as $ref) {
            if (!isset($allRefs[$ref])) {
                $allRefs[$ref] = [];
            }
            $allRefs[$ref][] = $name;
        }
    }

    // Find refs unique to each endpoint
    foreach ($results as $name => $data) {
        echo "$name ONLY:\n";
        $uniqueToThis = [];
        foreach ($data['refs'] as $ref) {
            if (count($allRefs[$ref]) === 1) {
                $uniqueToThis[] = $ref;
            }
        }
        if (empty($uniqueToThis)) {
            echo "  (none)\n";
        } else {
            foreach ($uniqueToThis as $ref) {
                echo "  - $ref\n";
            }
        }
        echo "\n";
    }

    // Union of all refs
    echo "=== UNION OF ALL ENDPOINTS ===\n";
    echo "Total unique refs across all endpoints: " . count($allRefs) . "\n\n";

    // Show refs and which endpoints they appear in
    foreach ($allRefs as $ref => $endpoints) {
        echo "$ref - Found in: " . implode(", ", $endpoints) . "\n";
    }

    // Load expected refs from JSON
    echo "\n=== COMPARE WITH EXPECTED (bookings_from_overseas.json) ===\n";
    $jsonFile = '../../bookings_from_overseas.json';
    if (file_exists($jsonFile)) {
        $expected = json_decode(file_get_contents($jsonFile), true);
        $expectedRefs = array_column($expected, 'reference');

        echo "Expected: " . count($expectedRefs) . " bookings\n";
        echo "Found: " . count($allRefs) . " bookings\n";
        echo "Difference: " . abs(count($expectedRefs) - count($allRefs)) . "\n\n";

        $missing = array_diff($expectedRefs, array_keys($allRefs));
        if (!empty($missing)) {
            echo "Missing from API:\n";
            foreach ($missing as $ref) {
                $booking = array_filter($expected, fn($b) => $b['reference'] === $ref)[0] ?? null;
                if ($booking) {
                    echo "  - $ref (Status: {$booking['status']}, Booked: {$booking['date_booked']})\n";
                }
            }
        } else {
            echo "✓ All expected bookings found!\n";
        }

        echo "\n";
        $extra = array_diff(array_keys($allRefs), $expectedRefs);
        if (!empty($extra)) {
            echo "Extra in API (not in expected):\n";
            foreach ($extra as $ref) {
                echo "  - $ref\n";
            }
        }
    } else {
        echo "bookings_from_overseas.json not found\n";
    }

    echo "</pre>";

} catch (Exception $e) {
    echo "<pre>Error: " . $e->getMessage() . "</pre>";
}
