<?php
// api/dev/debug-location-tracking.php - Debug Location Tracking Logs
header('Content-Type: text/plain; charset=utf-8');

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get booking references to check
    $testBookingRef = 'BAHOL-26777988';
    $realBookingRef = 'HBEDS-25077304';

    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "           LOCATION TRACKING DEBUG REPORT\n";
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

    // API Configuration
    echo "─── API CONFIGURATION ─────────────────────────────────────────────\n";
    echo "Environment: PRODUCTION\n";
    echo "API Endpoint: " . HolidayTaxisConfig::API_ENDPOINT . "\n";
    echo "API Version: " . HolidayTaxisConfig::API_VERSION . "\n";
    echo "API Key: " . substr(HolidayTaxisConfig::API_KEY, 0, 20) . "..." . substr(HolidayTaxisConfig::API_KEY, -10) . "\n";
    echo "\n";

    // Function to display booking details
    function displayBookingDetails($pdo, $bookingRef, $label) {
        echo "═══════════════════════════════════════════════════════════════════\n";
        echo "  $label: $bookingRef\n";
        echo "═══════════════════════════════════════════════════════════════════\n\n";

        // Get tracking token info
        $sql = "SELECT * FROM driver_tracking_tokens WHERE booking_ref = :ref";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':ref' => $bookingRef]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$token) {
            echo "⚠️ No tracking token found for this booking\n\n";
            return;
        }

        echo "─── TRACKING TOKEN INFO ───────────────────────────────────────────\n";
        echo "Token ID: " . $token['id'] . "\n";
        echo "Booking Ref: " . $token['booking_ref'] . "\n";
        echo "Vehicle Identifier: " . $token['vehicle_identifier'] . "\n";
        echo "Status: " . $token['status'] . "\n";
        echo "Created At: " . $token['created_at'] . "\n";
        echo "Started At: " . ($token['started_at'] ?? 'Not started') . "\n";
        echo "Completed At: " . ($token['completed_at'] ?? 'Not completed') . "\n";
        echo "Total Locations Sent: " . $token['total_locations_sent'] . "\n";
        echo "Last Location At: " . ($token['last_location_at'] ?? 'Never') . "\n";
        echo "\n";

        // Get latest location logs
        $logSql = "SELECT * FROM driver_location_logs
                   WHERE booking_ref = :ref
                   ORDER BY tracked_at DESC
                   LIMIT 5";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([':ref' => $bookingRef]);
        $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($logs)) {
            echo "─── LOCATION LOGS ─────────────────────────────────────────────────\n";
            echo "No location logs found\n\n";
            return;
        }

        echo "─── LATEST LOCATION LOGS (Last 5) ────────────────────────────────\n";
        foreach ($logs as $index => $log) {
            echo "\n┌─ Log #" . ($index + 1) . " ─────────────────────────────────────────────────────\n";
            echo "│ ID: " . $log['id'] . "\n";
            echo "│ Tracked At: " . $log['tracked_at'] . "\n";
            echo "│ Location: " . $log['latitude'] . ", " . $log['longitude'] . "\n";
            echo "│ Accuracy: " . ($log['accuracy'] ?? 'N/A') . " meters\n";
            echo "│ Status: " . $log['tracking_status'] . "\n";
            echo "│\n";
            echo "│ ── SYNC STATUS ──────────────────────────────────────────────\n";
            echo "│ Synced to HT: " . ($log['synced_to_holidaytaxis'] ? '✅ YES' : '❌ NO') . "\n";
            echo "│ HTTP Code: " . ($log['sync_http_code'] ?? 'N/A') . "\n";
            echo "│ Response: " . ($log['sync_response'] ?? 'N/A') . "\n";
            echo "│\n";

            // Show what request would look like
            if (!empty($token['vehicle_identifier'])) {
                $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$bookingRef}/vehicles/{$token['vehicle_identifier']}/location";

                echo "│ ── REQUEST DETAILS ──────────────────────────────────────────\n";
                echo "│ Method: POST\n";
                echo "│ URL: " . $apiUrl . "\n";
                echo "│\n";
                echo "│ Headers:\n";
                echo "│   API_KEY: " . substr(HolidayTaxisConfig::API_KEY, 0, 20) . "...\n";
                echo "│   Content-Type: application/json\n";
                echo "│   Accept: application/json\n";
                echo "│   VERSION: " . HolidayTaxisConfig::API_VERSION . "\n";
                echo "│\n";
                echo "│ Request Body:\n";

                $requestBody = [
                    'timestamp' => gmdate('Y-m-d\TH:i:s', strtotime($log['tracked_at'])) . '+00:00',
                    'location' => [
                        'lat' => (float)$log['latitude'],
                        'lng' => (float)$log['longitude']
                    ],
                    'status' => $log['tracking_status']
                ];

                $jsonBody = json_encode($requestBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $lines = explode("\n", $jsonBody);
                foreach ($lines as $line) {
                    echo "│   " . $line . "\n";
                }
            }

            echo "└─────────────────────────────────────────────────────────────────\n";
        }
        echo "\n";
    }

    // Display both bookings
    displayBookingDetails($pdo, $testBookingRef, "TEST BOOKING");
    displayBookingDetails($pdo, $realBookingRef, "REAL BOOKING");

    // Summary comparison
    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "  COMPARISON SUMMARY\n";
    echo "═══════════════════════════════════════════════════════════════════\n\n";

    $testTokenSql = "SELECT COUNT(*) as count,
                     SUM(synced_to_holidaytaxis) as synced,
                     MAX(tracked_at) as last_tracked
                     FROM driver_location_logs
                     WHERE booking_ref = :ref";

    $stmt = $pdo->prepare($testTokenSql);
    $stmt->execute([':ref' => $testBookingRef]);
    $testStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->execute([':ref' => $realBookingRef]);
    $realStats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Test Booking ($testBookingRef):\n";
    echo "  Total Locations: " . $testStats['count'] . "\n";
    echo "  Successfully Synced: " . ($testStats['synced'] ?? 0) . "\n";
    echo "  Failed Syncs: " . ($testStats['count'] - ($testStats['synced'] ?? 0)) . "\n";
    echo "  Last Tracked: " . ($testStats['last_tracked'] ?? 'Never') . "\n";
    echo "\n";

    echo "Real Booking ($realBookingRef):\n";
    echo "  Total Locations: " . $realStats['count'] . "\n";
    echo "  Successfully Synced: " . ($realStats['synced'] ?? 0) . "\n";
    echo "  Failed Syncs: " . ($realStats['count'] - ($realStats['synced'] ?? 0)) . "\n";
    echo "  Last Tracked: " . ($realStats['last_tracked'] ?? 'Never') . "\n";
    echo "\n";

    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "  END OF REPORT\n";
    echo "═══════════════════════════════════════════════════════════════════\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
