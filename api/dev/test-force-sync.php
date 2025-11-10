<?php
// api/dev/test-force-sync.php - Test Force Sync
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test Force Sync</h1>";
echo "<pre>";

echo "=== SENDING FORCE SYNC REQUEST ===\n\n";

$url = "http://localhost/api/dashboard/enhanced-sync.php";
// If running on live server, use:
// $url = "https://staff.phuketgevalin.com/api/dashboard/enhanced-sync.php";

$postData = json_encode(['force_sync' => true]);

echo "URL: $url\n";
echo "Payload: $postData\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ],
    CURLOPT_TIMEOUT => 120 // 2 minutes for sync to complete
]);

$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "=== RESPONSE ===\n";
echo "HTTP Code: $httpCode\n";
echo "Time taken: " . round($endTime - $startTime, 2) . " seconds\n\n";

if ($curlError) {
    echo "cURL Error: $curlError\n";
} else {
    $data = json_decode($response, true);

    if ($data) {
        echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n\n";

        if (isset($data['data'])) {
            $responseData = $data['data'];

            echo "Sync Performed: " . ($responseData['sync_performed'] ? 'Yes' : 'No') . "\n";
            echo "Sync Reason: " . ($responseData['sync_reason'] ?? 'N/A') . "\n";
            echo "Last Sync: " . ($responseData['last_sync'] ?? 'N/A') . "\n";
            echo "Total Bookings: " . ($responseData['total_bookings'] ?? 0) . "\n\n";

            if (isset($responseData['sync_result'])) {
                $syncResult = $responseData['sync_result'];
                echo "=== SYNC RESULT ===\n";
                echo "Total Found: " . ($syncResult['total_found'] ?? 0) . "\n";
                echo "Total New: " . ($syncResult['total_new'] ?? 0) . "\n";
                echo "Total Updated: " . ($syncResult['total_updated'] ?? 0) . "\n";
                echo "Query 1 (Last Action): " . ($syncResult['query1_count'] ?? 0) . " bookings\n";
                echo "Query 2 (Arrivals): " . ($syncResult['query2_count'] ?? 0) . " bookings\n\n";
            }

            if (isset($responseData['stats'])) {
                $stats = $responseData['stats'];
                echo "=== STATS ===\n";
                echo json_encode($stats, JSON_PRETTY_PRINT) . "\n\n";
            }

            if (isset($responseData['recent_bookings'])) {
                $bookings = $responseData['recent_bookings'];
                echo "=== RECENT BOOKINGS ===\n";
                echo "Count: " . count($bookings) . "\n\n";

                // Check for 2025-10-27
                $oct27 = array_filter($bookings, function ($b) {
                    return strpos($b['pickup_date'] ?? '', '2025-10-27') !== false;
                });

                echo "Bookings on 2025-10-27: " . count($oct27) . "\n";

                if (count($oct27) > 0) {
                    echo "\nSample bookings for 2025-10-27:\n";
                    foreach (array_slice($oct27, 0, 5) as $booking) {
                        echo "  " . ($booking['booking_ref'] ?? 'N/A') . " - " .
                            ($booking['passenger_name'] ?? 'N/A') . " - " .
                            ($booking['pickup_date'] ?? 'N/A') . "\n";
                    }
                }
            }
        }

        if (isset($data['error'])) {
            echo "Error: " . $data['error'] . "\n";
        }
    } else {
        echo "Raw Response:\n";
        echo $response . "\n";
    }
}

echo "\n=== DATABASE CHECK ===\n";

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Check bookings on 2025-10-27
    $sql = "SELECT COUNT(*) as count FROM bookings
            WHERE DATE(pickup_date) = '2025-10-27'";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Bookings in DB for 2025-10-27: " . $result['count'] . "\n\n";

    // Check last sync
    $syncSql = "SELECT * FROM sync_status ORDER BY id DESC LIMIT 1";
    $syncStmt = $pdo->query($syncSql);
    $lastSync = $syncStmt->fetch(PDO::FETCH_ASSOC);

    if ($lastSync) {
        echo "Last Sync:\n";
        echo "  ID: " . $lastSync['id'] . "\n";
        echo "  Type: " . $lastSync['sync_type'] . "\n";
        echo "  Status: " . $lastSync['status'] . "\n";
        echo "  Total Found: " . $lastSync['total_found'] . "\n";
        echo "  Total New: " . $lastSync['total_new'] . "\n";
        echo "  Total Updated: " . $lastSync['total_updated'] . "\n";
        echo "  Started: " . $lastSync['started_at'] . "\n";
        echo "  Completed: " . $lastSync['completed_at'] . "\n";
    }
} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
