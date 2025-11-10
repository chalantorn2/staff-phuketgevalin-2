<?php
// Check booking types distribution in database

$conn = new mysqli('localhost', 'root', 'root', 'tptravel');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Check booking types
$result = $conn->query('SELECT booking_type, COUNT(*) as count FROM bookings GROUP BY booking_type ORDER BY count DESC');

echo "=== Booking Types Distribution ===\n";
while($row = $result->fetch_assoc()) {
    echo $row['booking_type'] . ': ' . $row['count'] . "\n";
}

// Check sample bookings with arrival/departure data
$result = $conn->query("
    SELECT
        booking_ref,
        booking_type,
        CASE WHEN arrival_date IS NOT NULL THEN 'YES' ELSE 'NO' END as has_arrival,
        CASE WHEN departure_date IS NOT NULL THEN 'YES' ELSE 'NO' END as has_departure,
        CASE WHEN transfer_date IS NOT NULL THEN 'YES' ELSE 'NO' END as has_transfer
    FROM bookings
    ORDER BY created_at DESC
    LIMIT 30
");

echo "\n=== Sample Bookings (Latest 30) ===\n";
echo str_pad("Booking Ref", 20) . "\t" . str_pad("Type", 25) . "\tArrival\tDeparture\tTransfer\n";
echo str_repeat("-", 90) . "\n";
while($row = $result->fetch_assoc()) {
    echo str_pad($row['booking_ref'], 20) . "\t" .
         str_pad($row['booking_type'], 25) . "\t" .
         $row['has_arrival'] . "\t" .
         $row['has_departure'] . "\t\t" .
         $row['has_transfer'] . "\n";
}

// Check specific examples
echo "\n=== Detailed Examples ===\n";

$examples = [
    'HTXA-25983970', // Quote
    'HBEDS-26883571',
    'HBEDS-26909924'
];

foreach ($examples as $ref) {
    $result = $conn->query("SELECT booking_ref, booking_type, arrival_date, departure_date, transfer_date FROM bookings WHERE booking_ref = '$ref'");
    if ($row = $result->fetch_assoc()) {
        echo "\n$ref ({$row['booking_type']}):\n";
        echo "  - Arrival Date: " . ($row['arrival_date'] ?: 'NULL') . "\n";
        echo "  - Departure Date: " . ($row['departure_date'] ?: 'NULL') . "\n";
        echo "  - Transfer Date: " . ($row['transfer_date'] ?: 'NULL') . "\n";
    }
}

$conn->close();
