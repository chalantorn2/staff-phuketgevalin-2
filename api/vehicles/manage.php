<?php
// api/vehicles/manage.php - Vehicle Management API
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Method');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

function sendResponse($success, $data = null, $message = '', $code = 200)
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    // Check for X-Method header override
    if (isset($_SERVER['HTTP_X_METHOD'])) {
        $method = $_SERVER['HTTP_X_METHOD'];
    }

    $db = new Database();
    $pdo = $db->getConnection();

    switch ($method) {
        case 'GET':
            handleGetVehicles($pdo);
            break;

        case 'POST':
            handleCreateVehicle($pdo, $input);
            break;

        case 'PUT':
            $vehicleId = $_GET['id'] ?? $input['id'] ?? null;
            handleUpdateVehicle($pdo, $vehicleId, $input);
            break;

        case 'DELETE':
            $vehicleId = $_GET['id'] ?? $input['id'] ?? null;
            handleDeleteVehicle($pdo, $vehicleId);
            break;

        default:
            sendResponse(false, null, 'Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Vehicle API error: " . $e->getMessage());
    sendResponse(false, null, 'Server error: ' . $e->getMessage(), 500);
}

function handleGetVehicles($pdo)
{
    $sql = "SELECT v.*, d.name as default_driver_name, d.phone_number as default_driver_phone
            FROM vehicles v 
            LEFT JOIN drivers d ON v.default_driver_id = d.id
            ORDER BY v.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(true, $vehicles, 'Vehicles retrieved successfully');
}

function handleCreateVehicle($pdo, $data)
{
    $registration = trim(strtoupper($data['registration'] ?? ''));
    $brand = trim($data['brand'] ?? '');
    $model = trim($data['model'] ?? '');
    $color = trim($data['color'] ?? '');
    $description = trim($data['description'] ?? '');
    $defaultDriverId = $data['default_driver_id'] ?? null;

    // Validate input
    if (empty($registration)) {
        sendResponse(false, null, 'Registration number is required', 400);
    }

    // Basic validation - just ensure it's not too long
    if (strlen($registration) > 20) {
        sendResponse(false, null, 'Registration number is too long (max 20 characters)', 400);
    }

    // Check if registration exists
    $checkSql = "SELECT id FROM vehicles WHERE registration = :registration";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':registration' => $registration]);

    if ($checkStmt->fetch()) {
        sendResponse(false, null, 'Registration number already exists', 400);
    }

    // Validate default driver if provided
    if ($defaultDriverId) {
        $driverCheckSql = "SELECT id FROM drivers WHERE id = :id AND status = 'active'";
        $driverCheckStmt = $pdo->prepare($driverCheckSql);
        $driverCheckStmt->execute([':id' => $defaultDriverId]);

        if (!$driverCheckStmt->fetch()) {
            sendResponse(false, null, 'Invalid or inactive driver selected', 400);
        }
    }

    // Insert vehicle
    $sql = "INSERT INTO vehicles (registration, brand, model, color, description, default_driver_id, status, created_at) 
            VALUES (:registration, :brand, :model, :color, :description, :default_driver_id, 'active', NOW())";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':registration' => $registration,
        ':brand' => $brand ?: null,
        ':model' => $model ?: null,
        ':color' => $color ?: null,
        ':description' => $description ?: null,
        ':default_driver_id' => $defaultDriverId ?: null
    ]);

    if ($result) {
        sendResponse(true, ['id' => $pdo->lastInsertId()], 'Vehicle created successfully');
    } else {
        sendResponse(false, null, 'Failed to create vehicle', 500);
    }
}

function handleUpdateVehicle($pdo, $vehicleId, $data)
{
    if (!$vehicleId) {
        sendResponse(false, null, 'Vehicle ID is required', 400);
    }

    $registration = trim(strtoupper($data['registration'] ?? ''));
    $brand = trim($data['brand'] ?? '');
    $model = trim($data['model'] ?? '');
    $color = trim($data['color'] ?? '');
    $description = trim($data['description'] ?? '');
    $defaultDriverId = $data['default_driver_id'] ?? null;
    $status = $data['status'] ?? 'active';

    if (empty($registration)) {
        sendResponse(false, null, 'Registration number is required', 400);
    }

    // Basic validation - just ensure it's not too long
    if (strlen($registration) > 20) {
        sendResponse(false, null, 'Registration number is too long (max 20 characters)', 400);
    }

    // Check if registration exists for other vehicles
    $checkSql = "SELECT id FROM vehicles WHERE registration = :registration AND id != :id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':registration' => $registration, ':id' => $vehicleId]);

    if ($checkStmt->fetch()) {
        sendResponse(false, null, 'Registration number already exists', 400);
    }

    // Validate default driver if provided
    if ($defaultDriverId) {
        $driverCheckSql = "SELECT id FROM drivers WHERE id = :id AND status = 'active'";
        $driverCheckStmt = $pdo->prepare($driverCheckSql);
        $driverCheckStmt->execute([':id' => $defaultDriverId]);

        if (!$driverCheckStmt->fetch()) {
            sendResponse(false, null, 'Invalid or inactive driver selected', 400);
        }
    }

    $sql = "UPDATE vehicles 
            SET registration = :registration, brand = :brand, model = :model, color = :color, 
                description = :description, default_driver_id = :default_driver_id, status = :status, updated_at = NOW() 
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':id' => $vehicleId,
        ':registration' => $registration,
        ':brand' => $brand ?: null,
        ':model' => $model ?: null,
        ':color' => $color ?: null,
        ':description' => $description ?: null,
        ':default_driver_id' => $defaultDriverId ?: null,
        ':status' => $status
    ]);

    if ($result) {
        sendResponse(true, null, 'Vehicle updated successfully');
    } else {
        sendResponse(false, null, 'Failed to update vehicle', 500);
    }
}

function handleDeleteVehicle($pdo, $vehicleId)
{
    if (!$vehicleId) {
        sendResponse(false, null, 'Vehicle ID is required', 400);
    }

    // Check if vehicle has active assignments
    $checkSql = "SELECT COUNT(*) as count FROM driver_vehicle_assignments WHERE vehicle_id = :id AND status IN ('assigned', 'in_progress')";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $vehicleId]);
    $activeAssignments = $checkStmt->fetch()['count'];

    if ($activeAssignments > 0) {
        sendResponse(false, null, 'Cannot delete vehicle with active assignments', 400);
    }

    $sql = "DELETE FROM vehicles WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([':id' => $vehicleId]);

    if ($result) {
        sendResponse(true, null, 'Vehicle deleted successfully');
    } else {
        sendResponse(false, null, 'Failed to delete vehicle', 500);
    }
}
