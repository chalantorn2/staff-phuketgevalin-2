<?php
// api/users/manage.php - New User Management API
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

function validateToken($token)
{
    // Simple token validation - decode and check
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);

    if (count($parts) !== 2) return false;

    $userId = $parts[0];
    $timestamp = $parts[1];

    // Token expires after 24 hours
    if (time() - $timestamp > 86400) return false;

    return $userId;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    // Check for X-Method header override
    if (isset($_SERVER['HTTP_X_METHOD'])) {
        $method = $_SERVER['HTTP_X_METHOD'];
    }

    // Get auth token from header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    // Simple auth check (except for login)
    $currentUserId = $token ? validateToken($token) : null;

    $db = new Database();
    $pdo = $db->getConnection();

    switch ($method) {
        case 'GET':
            handleGetUsers($pdo);
            break;

        case 'POST':
            handleCreateUser($pdo, $input);
            break;

        case 'PUT':
            $userId = $_GET['id'] ?? $input['id'] ?? null;
            handleUpdateUser($pdo, $userId, $input);
            break;

        case 'DELETE':
            $userId = $_GET['id'] ?? $input['id'] ?? null;
            handleDeleteUser($pdo, $userId);
            break;

        default:
            sendResponse(false, null, 'Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("User API error: " . $e->getMessage());
    sendResponse(false, null, 'Server error: ' . $e->getMessage(), 500);
}

function handleGetUsers($pdo)
{
    $sql = "SELECT id, username, full_name, role, status, last_login, created_at 
            FROM staff_users 
            ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(true, $users, 'Users retrieved successfully');
}

function handleCreateUser($pdo, $data)
{
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $full_name = trim($data['full_name'] ?? '');
    $role = $data['role'] ?? 'user';

    // Validate input
    if (empty($username) || empty($password) || empty($full_name)) {
        sendResponse(false, null, 'Username, password, and full name are required', 400);
    }

    // Check if username exists
    $checkSql = "SELECT id FROM staff_users WHERE username = :username";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':username' => $username]);

    if ($checkStmt->fetch()) {
        sendResponse(false, null, 'Username already exists', 400);
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $sql = "INSERT INTO staff_users (username, password, full_name, role, status, created_at) 
            VALUES (:username, :password, :full_name, :role, 'active', NOW())";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':username' => $username,
        ':password' => $hashedPassword,
        ':full_name' => $full_name,
        ':role' => $role
    ]);

    if ($result) {
        sendResponse(true, ['id' => $pdo->lastInsertId()], 'User created successfully');
    } else {
        sendResponse(false, null, 'Failed to create user', 500);
    }
}

function handleUpdateUser($pdo, $userId, $data)
{
    if (!$userId) {
        sendResponse(false, null, 'User ID is required', 400);
    }

    $full_name = trim($data['full_name'] ?? '');
    $role = $data['role'] ?? 'user';
    $status = $data['status'] ?? 'active';

    if (empty($full_name)) {
        sendResponse(false, null, 'Full name is required', 400);
    }

    $sql = "UPDATE staff_users 
            SET full_name = :full_name, role = :role, status = :status, updated_at = NOW() 
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':id' => $userId,
        ':full_name' => $full_name,
        ':role' => $role,
        ':status' => $status
    ]);

    if ($result) {
        sendResponse(true, null, 'User updated successfully');
    } else {
        sendResponse(false, null, 'Failed to update user', 500);
    }
}

function handleDeleteUser($pdo, $userId)
{
    if (!$userId) {
        sendResponse(false, null, 'User ID is required', 400);
    }

    $sql = "DELETE FROM staff_users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([':id' => $userId]);

    if ($result) {
        sendResponse(true, null, 'User deleted successfully');
    } else {
        sendResponse(false, null, 'Failed to delete user', 500);
    }
}
