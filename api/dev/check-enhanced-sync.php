<?php
// api/dev/check-enhanced-sync.php - Check Enhanced Sync Code
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Check Enhanced Sync Implementation</h1>";
echo "<pre>";

echo "=== FILE CHECK ===\n";
$file = '../dashboard/enhanced-sync.php';
if (file_exists($file)) {
    echo "File exists: YES\n";
    echo "File size: " . filesize($file) . " bytes\n";
    echo "Last modified: " . date('Y-m-d H:i:s', filemtime($file)) . "\n\n";
} else {
    echo "File exists: NO\n";
    die("File not found!");
}

echo "=== CODE ANALYSIS ===\n";
$content = file_get_contents($file);

// Check for key implementations
$checks = [
    'Force Sync Check' => 'force_sync',
    'Day-by-Day Loop' => 'for ($i = 0; $i < $totalDays; $i++)',
    'Province Detection' => 'ProvinceMapping::detectProvince',
    'DateTime Clone' => 'clone $today',
    'Current Date Variable' => '$currentDate->format',
    'Dual Query Type' => "'dual-query'",
    '+10 days' => "'+10 days'",
];

foreach ($checks as $name => $search) {
    if (strpos($content, $search) !== false) {
        echo "$name: ✓ FOUND\n";
    } else {
        echo "$name: ✗ NOT FOUND\n";
    }
}

echo "\n=== PROBLEMATIC CODE CHECK ===\n";
$problems = [
    'Old $startDate variable' => '$startDate->format',
    'Old $startDate modify' => '$startDate->modify',
    'Old +14 days' => "'+14 days'",
];

$hasProblems = false;
foreach ($problems as $name => $search) {
    if (strpos($content, $search) !== false) {
        echo "$name: ⚠️ STILL EXISTS (should be removed)\n";
        $hasProblems = true;
    } else {
        echo "$name: ✓ REMOVED\n";
    }
}

if ($hasProblems) {
    echo "\n⚠️ WARNING: Old code still present!\n";
} else {
    echo "\n✓ All old code removed!\n";
}

// Check specific line numbers
echo "\n=== KEY CODE SECTIONS ===\n";

// Extract line around force_sync
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    $lineNum = $i + 1;

    if (strpos($line, 'force_sync') !== false) {
        echo "Line $lineNum: " . trim($line) . "\n";
    }

    if (strpos($line, 'dual-query') !== false) {
        echo "Line $lineNum: " . trim($line) . "\n";
    }

    if (strpos($line, '$totalDays') !== false && strpos($line, 'for') !== false) {
        echo "Line $lineNum: " . trim($line) . "\n";
    }

    if (strpos($line, '$currentDate') !== false && strpos($line, 'clone') !== false) {
        echo "Line $lineNum: " . trim($line) . "\n";
    }
}

echo "\n=== FUNCTION CHECK ===\n";
if (strpos($content, 'function performEnhancedSync') !== false) {
    echo "performEnhancedSync function: ✓ FOUND\n";

    // Count lines in function
    preg_match('/function performEnhancedSync.*?\n}/s', $content, $matches);
    if (isset($matches[0])) {
        $funcLines = substr_count($matches[0], "\n");
        echo "Function length: ~$funcLines lines\n";
    }
} else {
    echo "performEnhancedSync function: ✗ NOT FOUND\n";
}

echo "\n=== TESTING ACTUAL EXECUTION ===\n";
echo "Attempting to call enhanced-sync.php with force_sync...\n\n";

$url = "https://staff.phuketgevalin.com/api/dashboard/enhanced-sync.php";
$postData = json_encode(['force_sync' => true]);

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
    CURLOPT_TIMEOUT => 120,
    CURLOPT_SSL_VERIFYPEER => false
]);

$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Time taken: " . round($endTime - $startTime, 2) . " seconds\n";

if ($curlError) {
    echo "cURL Error: $curlError\n";
}

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data) {
        echo "\nResponse (summary):\n";
        echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
        if (isset($data['data']['sync_performed'])) {
            echo "Sync Performed: " . ($data['data']['sync_performed'] ? 'Yes' : 'No') . "\n";
            echo "Sync Reason: " . ($data['data']['sync_reason'] ?? 'N/A') . "\n";
        }
        if (isset($data['error'])) {
            echo "Error: " . $data['error'] . "\n";
        }
    } else {
        echo "\nFailed to parse JSON response\n";
        echo "Raw response (first 500 chars):\n";
        echo substr($response, 0, 500) . "\n";
    }
} else {
    echo "\nHTTP Error $httpCode\n";
    echo "Response (first 500 chars):\n";
    echo substr($response, 0, 500) . "\n";
}

echo "\n=== DATABASE SYNC STATUS CHECK ===\n";

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $sql = "SELECT * FROM sync_status ORDER BY id DESC LIMIT 3";
    $stmt = $pdo->query($sql);
    $syncs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Last 3 syncs:\n\n";
    foreach ($syncs as $sync) {
        echo "ID: " . $sync['id'] . "\n";
        echo "  Type: [" . ($sync['sync_type'] ?? 'EMPTY') . "]\n";
        echo "  Status: " . $sync['status'] . "\n";
        echo "  Found: " . $sync['total_found'] . " | New: " . $sync['total_new'] . " | Updated: " . $sync['total_updated'] . "\n";
        echo "  Started: " . $sync['started_at'] . " | Completed: " . ($sync['completed_at'] ?? 'NULL') . "\n";
        echo "\n";
    }

    $bookingSql = "SELECT COUNT(*) as count FROM bookings WHERE DATE(pickup_date) = '2025-10-27'";
    $bookingStmt = $pdo->query($bookingSql);
    $bookingCount = $bookingStmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo "Bookings on 2025-10-27: $bookingCount\n";
} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
