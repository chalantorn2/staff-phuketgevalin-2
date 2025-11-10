<?php
// api/bookings/booking-detail-db.php - Get booking detail from database
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $bookingRef = $_GET['ref'] ?? null;

    if (!$bookingRef) {
        throw new Exception('Booking reference is required');
    }

    // Get booking from database
    $sql = "SELECT *, raw_data FROM bookings WHERE booking_ref = :ref";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ref' => $bookingRef]);
    $booking = $stmt->fetch();

    if (!$booking) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Booking not found in database'
        ]);
        exit;
    }

    // Get booking notes from database
    $notesSql = "SELECT * FROM booking_notes WHERE booking_ref = :ref ORDER BY created_at DESC LIMIT 1";
    $notesStmt = $pdo->prepare($notesSql);
    $notesStmt->execute([':ref' => $bookingRef]);
    $notes = $notesStmt->fetch();

    // Format response similar to Holiday Taxis API structure
    $response = [
        'success' => true,
        'data' => [
            'booking' => [
                'general' => [
                    'ref' => $booking['booking_ref'],
                    'status' => $booking['ht_status'],
                    'bookingtype' => $booking['booking_type'],
                    'vehicle' => $booking['vehicle_type'],
                    'passengername' => $booking['passenger_name'],
                    'passengertelno' => $booking['passenger_phone'],
                    'passengeremail' => $booking['passenger_email'],
                    'adults' => (int)($booking['adults'] ?? 1),
                    'children' => (int)($booking['children'] ?? 0),
                    'infants' => (int)($booking['infants'] ?? 0),
                    'pax' => (int)($booking['pax_total'] ?? 1),
                    'airport' => $booking['airport'],
                    'airportcode' => $booking['airport_code'],
                    'resort' => $booking['resort'],
                    'bookingdate' => $booking['date_booked']
                ],
                'arrival' => [
                    'arrivaldate' => $booking['arrival_date'],
                    'accommodationname' => $booking['accommodation_name'],
                    'accommodationaddress1' => $booking['accommodation_address1'],
                    'accommodationaddress2' => $booking['accommodation_address2'],
                    'accommodationtel' => $booking['accommodation_tel'],
                    'flightno' => $booking['flight_no_arrival'],
                    'fromairport' => $booking['from_airport']
                ],
                'departure' => [
                    'departuredate' => $booking['departure_date'],
                    'pickupdate' => $booking['pickup_date'],
                    'accommodationname' => $booking['accommodation_name'],
                    'flightno' => $booking['flight_no_departure'],
                    'toairport' => $booking['to_airport']
                ],
                'quote' => [
                    'transferdate' => $booking['transfer_date'],
                    'pickupaddress1' => $booking['pickup_address1'],
                    'pickupaddress2' => $booking['pickup_address2'],
                    'pickupaddress3' => $booking['pickup_address3'],
                    'pickupaddress4' => $booking['pickup_address4'],
                    'dropoffaddress1' => $booking['dropoff_address1'],
                    'dropoffaddress2' => $booking['dropoff_address2'],
                    'dropoffaddress3' => $booking['dropoff_address3'],
                    'dropoffaddress4' => $booking['dropoff_address4']
                ],
                // Province information
                'province' => $booking['province'],
                'province_source' => $booking['province_source'],
                'province_confidence' => $booking['province_confidence'],
                // Adjusted pickup date (if time was changed)
                'pickup_date_adjusted' => $booking['pickup_date_adjusted'],
                // Raw data
                'raw_data' => $booking['raw_data'] ? json_decode($booking['raw_data'], true) : null
            ],
            'notes' => $notes ? [
                'content' => $notes['note_content'],
                'flight_no_query' => (bool)$notes['flight_no_query'],
                'wrong_resort' => (bool)$notes['wrong_resort'],
                'mandatory_child_seat' => (bool)$notes['mandatory_child_seat'],
                'missing_accommodation' => (bool)$notes['missing_accommodation'],
                'missing_cruise_ship_name' => (bool)$notes['missing_cruise_ship_name'],
                'shuttle_to_private_address' => (bool)$notes['shuttle_to_private_address'],
                'no_show_arrival' => (bool)$notes['no_show_arrival'],
                'no_show_departure' => (bool)$notes['no_show_departure'],
                'created_at' => $notes['created_at']
            ] : null,
            'meta' => [
                'synced_at' => $booking['synced_at'],
                'last_updated' => $booking['updated_at'],
                'internal_status' => $booking['internal_status'],
                'source' => 'database'
            ]
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'booking_ref' => $bookingRef ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
