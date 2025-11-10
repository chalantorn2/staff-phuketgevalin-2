<?php
// api/sync/backfill-provinces.php - Backfill provinces for existing bookings
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Allow both GET and POST for easy testing
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';
require_once '../config/province-mapping.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get all bookings that have NULL province or province_source = 'unknown'
    $sql = "SELECT
                id,
                booking_ref,
                airport,
                airport_code,
                accommodation_address1,
                accommodation_address2,
                province,
                province_source
            FROM bookings
            WHERE province IS NULL
               OR province_source = 'unknown'
               OR province_source IS NULL
            ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $bookings = $stmt->fetchAll();

    $totalBookings = count($bookings);
    $totalUpdated = 0;
    $totalSkipped = 0;
    $updateDetails = [];

    foreach ($bookings as $booking) {
        // Detect province
        $provinceData = ProvinceMapping::detectProvince([
            'airport' => $booking['airport'],
            'airport_code' => $booking['airport_code'],
            'accommodation_address1' => $booking['accommodation_address1'],
            'accommodation_address2' => $booking['accommodation_address2']
        ]);

        // Only update if we found a province (not unknown)
        if ($provinceData['province'] !== null) {
            $updateSql = "UPDATE bookings
                         SET province = :province,
                             province_source = :province_source,
                             province_confidence = :province_confidence,
                             updated_at = NOW()
                         WHERE id = :id";

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':id' => $booking['id'],
                ':province' => $provinceData['province'],
                ':province_source' => $provinceData['source'],
                ':province_confidence' => $provinceData['confidence']
            ]);

            $totalUpdated++;

            $updateDetails[] = [
                'booking_ref' => $booking['booking_ref'],
                'old_province' => $booking['province'],
                'new_province' => $provinceData['province'],
                'source' => $provinceData['source'],
                'confidence' => $provinceData['confidence']
            ];
        } else {
            $totalSkipped++;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'totalBookings' => $totalBookings,
            'totalUpdated' => $totalUpdated,
            'totalSkipped' => $totalSkipped,
            'sampleUpdates' => array_slice($updateDetails, 0, 10), // Show first 10
            'completedAt' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
