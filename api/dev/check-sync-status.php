<?php
require_once '../config/database.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    echo "<h1>Sync Status Check</h1>";
    echo "<pre>";

    // Check last sync
    $sql = "SELECT * FROM sync_status ORDER BY id DESC LIMIT 5";
    $stmt = $pdo->query($sql);
    $syncs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== LAST 5 SYNCS ===\n\n";
    foreach ($syncs as $sync) {
        echo "ID: " . $sync['id'] . "\n";
        echo "Type: " . $sync['sync_type'] . "\n";
        echo "Status: " . $sync['status'] . "\n";
        echo "Date From: " . $sync['date_from'] . "\n";
        echo "Date To: " . $sync['date_to'] . "\n";
        echo "Total Found: " . $sync['total_found'] . "\n";
        echo "Total New: " . $sync['total_new'] . "\n";
        echo "Total Updated: " . $sync['total_updated'] . "\n";
        echo "Started: " . $sync['started_at'] . "\n";
        echo "Completed: " . $sync['completed_at'] . "\n";
        echo "\n" . str_repeat("-", 60) . "\n\n";
    }

    // Check bookings count by date
    echo "=== BOOKINGS COUNT BY PICKUP DATE ===\n\n";
    $sql = "SELECT DATE(pickup_date) as date, COUNT(*) as count
            FROM bookings
            WHERE pickup_date >= CURDATE()
            GROUP BY DATE(pickup_date)
            ORDER BY DATE(pickup_date)
            LIMIT 15";
    $stmt = $pdo->query($sql);
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($counts as $row) {
        echo $row['date'] . ": " . $row['count'] . " bookings\n";
    }

    // Check if 27/10 exists
    echo "\n=== BOOKINGS ON 2025-10-27 ===\n\n";
    $sql = "SELECT booking_ref, ht_status, passenger_name, pickup_date, created_at
            FROM bookings
            WHERE DATE(pickup_date) = '2025-10-27'
            ORDER BY pickup_date
            LIMIT 10";
    $stmt = $pdo->query($sql);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total: " . count($bookings) . " bookings\n\n";

    foreach ($bookings as $booking) {
        echo "Ref: " . $booking['booking_ref'] . "\n";
        echo "  Status: " . $booking['ht_status'] . "\n";
        echo "  Passenger: " . $booking['passenger_name'] . "\n";
        echo "  Pickup: " . $booking['pickup_date'] . "\n";
        echo "  Created: " . $booking['created_at'] . "\n";
        echo "\n";
    }

    echo "</pre>";

} catch (Exception $e) {
    echo "<pre>Error: " . $e->getMessage() . "</pre>";
}
