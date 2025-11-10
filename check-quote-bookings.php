<?php
require_once 'api/config/database.php';

$db = new Database();
$pdo = $db->getConnection();

$sql = "SELECT booking_ref, booking_type,
        airport, from_airport, to_airport,
        accommodation_name, resort,
        pickup_address1, dropoff_address1,
        arrival_date, departure_date, pickup_date
        FROM bookings
        WHERE booking_type LIKE '%Quote%'
        LIMIT 5";

$stmt = $pdo->query($sql);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Quote Bookings Data:\n";
echo "==================\n\n";

foreach ($bookings as $booking) {
    echo "Booking Ref: " . $booking['booking_ref'] . "\n";
    echo "Type: " . $booking['booking_type'] . "\n";
    echo "Airport: " . ($booking['airport'] ?? 'NULL') . "\n";
    echo "From Airport: " . ($booking['from_airport'] ?? 'NULL') . "\n";
    echo "To Airport: " . ($booking['to_airport'] ?? 'NULL') . "\n";
    echo "Accommodation: " . ($booking['accommodation_name'] ?? 'NULL') . "\n";
    echo "Resort: " . ($booking['resort'] ?? 'NULL') . "\n";
    echo "Pickup Address: " . ($booking['pickup_address1'] ?? 'NULL') . "\n";
    echo "Dropoff Address: " . ($booking['dropoff_address1'] ?? 'NULL') . "\n";
    echo "Arrival Date: " . ($booking['arrival_date'] ?? 'NULL') . "\n";
    echo "Departure Date: " . ($booking['departure_date'] ?? 'NULL') . "\n";
    echo "Pickup Date: " . ($booking['pickup_date'] ?? 'NULL') . "\n";
    echo "\n---\n\n";
}
