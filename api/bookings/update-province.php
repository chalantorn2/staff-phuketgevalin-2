<?php
// api/bookings/update-province.php - Update or Re-detect Province
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../config/province-mapping.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'POST' && $method !== 'PUT') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $bookingRef = $input['booking_ref'] ?? null;
    $action = $input['action'] ?? 'manual'; // 'manual' or 'redetect'
    $province = $input['province'] ?? null;

    if (!$bookingRef) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Booking reference is required']);
        exit;
    }

    $db = new Database();
    $pdo = $db->getConnection();

    // Get booking data
    $sql = "SELECT * FROM bookings WHERE booking_ref = :ref";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ref' => $bookingRef]);
    $booking = $stmt->fetch();

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }

    if ($action === 'redetect') {
        // Re-detect province automatically
        $provinceData = ProvinceMapping::detectProvince([
            'airport' => $booking['airport'],
            'airport_code' => $booking['airport_code'],
            'from_airport' => $booking['from_airport'] ?? null,
            'to_airport' => $booking['to_airport'] ?? null,
            'accommodation_address1' => $booking['accommodation_address1'],
            'accommodation_address2' => $booking['accommodation_address2']
        ]);

        $updateSql = "UPDATE bookings
                      SET province = :province,
                          province_source = :source,
                          province_confidence = :confidence,
                          updated_at = NOW()
                      WHERE booking_ref = :ref";

        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':province' => $provinceData['province'],
            ':source' => $provinceData['source'],
            ':confidence' => $provinceData['confidence'],
            ':ref' => $bookingRef
        ]);

        echo json_encode([
            'success' => true,
            'data' => [
                'booking_ref' => $bookingRef,
                'province' => $provinceData['province'],
                'source' => $provinceData['source'],
                'confidence' => $provinceData['confidence'],
                'action' => 'redetect'
            ],
            'message' => 'Province re-detected successfully'
        ]);

    } else {
        // Manual update
        if (!$province) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Province is required for manual update']);
            exit;
        }

        // Validate province
        $allProvinces = ProvinceMapping::getAllProvinces();
        if (!in_array($province, $allProvinces) && $province !== null) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid province',
                'valid_provinces' => $allProvinces
            ]);
            exit;
        }

        $updateSql = "UPDATE bookings
                      SET province = :province,
                          province_source = 'manual',
                          province_confidence = 'high',
                          updated_at = NOW()
                      WHERE booking_ref = :ref";

        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':province' => $province,
            ':ref' => $bookingRef
        ]);

        echo json_encode([
            'success' => true,
            'data' => [
                'booking_ref' => $bookingRef,
                'province' => $province,
                'source' => 'manual',
                'confidence' => 'high',
                'action' => 'manual'
            ],
            'message' => 'Province updated successfully'
        ]);
    }

} catch (Exception $e) {
    error_log("Update Province API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
