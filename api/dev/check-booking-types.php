<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check booking types distribution in database

$conn = new mysqli('localhost', 'root', 'root', 'tptravel');
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

$response = [
    'success' => true,
    'data' => []
];

// Check booking types
$result = $conn->query('SELECT booking_type, COUNT(*) as count FROM bookings GROUP BY booking_type ORDER BY count DESC');

$response['data']['distribution'] = [];
while($row = $result->fetch_assoc()) {
    $response['data']['distribution'][] = [
        'type' => $row['booking_type'],
        'count' => (int)$row['count']
    ];
}

// Check sample bookings with arrival/departure data
$result = $conn->query("
    SELECT
        booking_ref,
        booking_type,
        arrival_date,
        departure_date,
        transfer_date
    FROM bookings
    ORDER BY created_at DESC
    LIMIT 30
");

$response['data']['samples'] = [];
while($row = $result->fetch_assoc()) {
    $response['data']['samples'][] = [
        'ref' => $row['booking_ref'],
        'type' => $row['booking_type'],
        'has_arrival' => !empty($row['arrival_date']),
        'has_departure' => !empty($row['departure_date']),
        'has_transfer' => !empty($row['transfer_date']),
        'arrival_date' => $row['arrival_date'],
        'departure_date' => $row['departure_date'],
        'transfer_date' => $row['transfer_date']
    ];
}

// Check specific examples
$examples = [
    'HTXA-25983970', // Quote
    'HBEDS-26883571',
    'HBEDS-26909924'
];

$response['data']['examples'] = [];
foreach ($examples as $ref) {
    $result = $conn->query("SELECT booking_ref, booking_type, arrival_date, departure_date, transfer_date FROM bookings WHERE booking_ref = '$ref'");
    if ($row = $result->fetch_assoc()) {
        $response['data']['examples'][] = [
            'ref' => $row['booking_ref'],
            'type' => $row['booking_type'],
            'arrival_date' => $row['arrival_date'],
            'departure_date' => $row['departure_date'],
            'transfer_date' => $row['transfer_date']
        ];
    }
}

$conn->close();

echo json_encode($response, JSON_PRETTY_PRINT);
