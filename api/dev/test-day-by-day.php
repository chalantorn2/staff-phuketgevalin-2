<?php
// api/dev/test-day-by-day.php - Test Day-by-Day Query Strategy
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';

try {
    echo "<h1>Test Day-by-Day Query Strategy</h1>";
    echo "<pre>";

    // === TEST CONFIGURATION ===
    $today = new DateTime();
    $endDay = new DateTime('+10 days');

    echo "=== CONFIGURATION ===\n";
    echo "Start Date: " . $today->format('Y-m-d') . "\n";
    echo "End Date: " . $endDay->format('Y-m-d') . "\n";
    echo "\n";

    // === DAY-BY-DAY QUERY ===
    $totalDays = (int)$today->diff($endDay)->days + 1;
    echo "=== QUERYING $totalDays DAYS ===\n\n";

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    $allBookings = [];
    $daySummary = [];

    for ($i = 0; $i < $totalDays; $i++) {
        $currentDate = clone $today;
        $currentDate->modify("+{$i} days");

        $dayFrom = $currentDate->format('Y-m-d\T00:00:00');
        $dayTo = $currentDate->format('Y-m-d\T23:59:59');

        $searchUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/search/arrivals/since/{$dayFrom}/until/{$dayTo}/page/1";

        echo "Day " . ($i + 1) . ": " . $currentDate->format('Y-m-d') . "\n";
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
                if (is_object($bookingsData) || (is_array($bookingsData) && isset($bookingsData['booking_0']))) {
                    $dayBookings = array_values((array)$bookingsData);
                } else {
                    $dayBookings = $bookingsData;
                }

                $count = count($dayBookings);
                echo "  Bookings found: $count\n";

                $daySummary[$currentDate->format('Y-m-d')] = $count;

                if ($count > 0) {
                    $allBookings = array_merge($allBookings, $dayBookings);

                    // Show first booking as sample
                    $sample = $dayBookings[0];
                    echo "  Sample: " . ($sample['ref'] ?? 'N/A') . " - " . ($sample['passengername'] ?? 'N/A') . "\n";
                }
            }
        } elseif ($httpCode === 204) {
            echo "  No bookings (204 No Content)\n";
            $daySummary[$currentDate->format('Y-m-d')] = 0;
        } else {
            echo "  ERROR: $response\n";
        }

        echo "\n";
        usleep(100000); // 0.1 second delay
    }

    // === SUMMARY ===
    echo "=== SUMMARY ===\n";
    echo "Total days queried: $totalDays\n";
    echo "Total unique bookings found: " . count($allBookings) . "\n\n";

    echo "Daily breakdown:\n";
    foreach ($daySummary as $date => $count) {
        echo "  $date: $count bookings\n";
    }

    // === CHECK FOR 2025-10-27 ===
    echo "\n=== SPECIFIC CHECK: 2025-10-27 ===\n";
    if (isset($daySummary['2025-10-27'])) {
        echo "Found " . $daySummary['2025-10-27'] . " bookings for 2025-10-27\n";

        // Show all 27/10 bookings
        $oct27Bookings = array_filter($allBookings, function($b) {
            $arrivalDate = $b['arrivaldate'] ?? '';
            return strpos($arrivalDate, '2025-10-27') !== false;
        });

        if (count($oct27Bookings) > 0) {
            echo "\nBookings on 2025-10-27:\n";
            foreach (array_slice($oct27Bookings, 0, 5) as $booking) {
                echo "  Ref: " . ($booking['ref'] ?? 'N/A') . "\n";
                echo "    Passenger: " . ($booking['passengername'] ?? 'N/A') . "\n";
                echo "    Arrival: " . ($booking['arrivaldate'] ?? 'N/A') . "\n\n";
            }
        }
    } else {
        echo "2025-10-27 is not in the query range\n";
    }

    echo "</pre>";

} catch (Exception $e) {
    echo "<pre>Error: " . $e->getMessage() . "</pre>";
}
