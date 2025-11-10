<?php
// api/bookings/adjust-time.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);

    $bookingRef = $input['booking_ref'] ?? null;
    $newPickupDate = $input['new_pickup_date'] ?? null;

    // Validation
    if (!$bookingRef) {
        throw new Exception('Booking reference is required');
    }

    if (!$newPickupDate) {
        throw new Exception('New pickup date is required');
    }

    // Validate date format
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $newPickupDate);
    if (!$dateTime) {
        throw new Exception('Invalid date format. Expected: YYYY-MM-DD HH:MM:SS');
    }

    // Check if booking exists
    $stmt = $pdo->prepare("SELECT id, pickup_date FROM bookings WHERE booking_ref = ?");
    $stmt->execute([$bookingRef]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Booking not found');
    }

    $originalPickupDate = $booking['pickup_date'];

    // Update pickup_date_adjusted
    $stmt = $pdo->prepare("
        UPDATE bookings
        SET pickup_date_adjusted = ?
        WHERE booking_ref = ?
    ");

    if (!$stmt->execute([$newPickupDate, $bookingRef])) {
        throw new Exception('Failed to update pickup time');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Pickup time adjusted successfully',
        'data' => [
            'booking_ref' => $bookingRef,
            'original_pickup_date' => $originalPickupDate,
            'adjusted_pickup_date' => $newPickupDate
        ]
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
