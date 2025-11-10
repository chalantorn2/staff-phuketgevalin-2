<?php
// api/drivers/list.php - Get All Drivers List
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Get all active drivers
    $sql = "SELECT id, name, phone_number, license_number, status
            FROM drivers
            WHERE status = 'active'
            ORDER BY name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $drivers = $stmt->fetchAll();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $drivers
    ]);
} catch (Exception $e) {
    error_log("Drivers List API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
