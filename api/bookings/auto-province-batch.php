<?php
// api/bookings/auto-province-batch.php - Auto-detect Province for Multiple Bookings
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';
require_once '../config/province-mapping.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    $bookingRefs = $input['booking_refs'] ?? [];

    if (empty($bookingRefs) || !is_array($bookingRefs)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'booking_refs array is required'
        ]);
        exit;
    }

    // Limit to prevent overload
    if (count($bookingRefs) > 100) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Maximum 100 bookings per request'
        ]);
        exit;
    }

    $results = [];
    $successCount = 0;
    $failedCount = 0;
    $skippedCount = 0;

    foreach ($bookingRefs as $bookingRef) {
        try {
            // Get booking data
            $sql = "SELECT
                        booking_ref,
                        airport,
                        airport_code,
                        from_airport,
                        to_airport,
                        accommodation_address1,
                        accommodation_address2,
                        province,
                        province_source
                    FROM bookings
                    WHERE booking_ref = :ref";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ref' => $bookingRef]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                $results[] = [
                    'booking_ref' => $bookingRef,
                    'status' => 'failed',
                    'error' => 'Booking not found'
                ];
                $failedCount++;
                continue;
            }

            // Skip if already has province
            if (!empty($booking['province'])) {
                $results[] = [
                    'booking_ref' => $bookingRef,
                    'status' => 'skipped',
                    'message' => 'Already has province',
                    'current_province' => $booking['province']
                ];
                $skippedCount++;
                continue;
            }

            // Detect province
            $provinceData = ProvinceMapping::detectProvince([
                'airport' => $booking['airport'],
                'airport_code' => $booking['airport_code'],
                'from_airport' => $booking['from_airport'],
                'to_airport' => $booking['to_airport'],
                'accommodation_address1' => $booking['accommodation_address1'],
                'accommodation_address2' => $booking['accommodation_address2']
            ]);

            // Update booking with detected province
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

            $results[] = [
                'booking_ref' => $bookingRef,
                'status' => 'success',
                'province' => $provinceData['province'],
                'source' => $provinceData['source'],
                'confidence' => $provinceData['confidence']
            ];
            $successCount++;

        } catch (Exception $e) {
            $results[] = [
                'booking_ref' => $bookingRef,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
            $failedCount++;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'total' => count($bookingRefs),
            'success' => $successCount,
            'failed' => $failedCount,
            'skipped' => $skippedCount,
            'results' => $results
        ],
        'message' => "Processed {$successCount} bookings successfully"
    ]);

} catch (Exception $e) {
    error_log("Auto Province Batch Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
