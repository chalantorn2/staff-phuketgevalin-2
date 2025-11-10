<?php
// api/users/users.php - User Management API
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Get all users
            $sql = "SELECT id, username, full_name, role, status, last_login, created_at FROM staff_users ORDER BY created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $users
            ]);
            break;

        case 'POST':
            // Create new user
            $input = json_decode(file_get_contents('php://input'), true);
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            $full_name = $input['full_name'] ?? '';
            $role = $input['role'] ?? 'user';

            if (empty($username) || empty($password) || empty($full_name)) {
                throw new Exception('Username, password, and full name are required');
            }

            // Check if username exists
            $checkSql = "SELECT id FROM staff_users WHERE username = :username";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':username' => $username]);
            if ($checkStmt->fetch()) {
                throw new Exception('Username already exists');
            }

            // Insert new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertSql = "INSERT INTO staff_users (username, password, full_name, role) VALUES (:username, :password, :full_name, :role)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ':username' => $username,
                ':password' => $hashedPassword,
                ':full_name' => $full_name,
                ':role' => $role
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'User created successfully',
                'user_id' => $pdo->lastInsertId()
            ]);
            break;

        case 'PUT':
            // Update user
            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                throw new Exception('User ID is required');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $full_name = $input['full_name'] ?? '';
            $role = $input['role'] ?? '';
            $status = $input['status'] ?? '';

            if (empty($full_name)) {
                throw new Exception('Full name is required');
            }

            $updateSql = "UPDATE staff_users SET full_name = :full_name, role = :role, status = :status WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':id' => $userId,
                ':full_name' => $full_name,
                ':role' => $role,
                ':status' => $status
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully'
            ]);
            break;

        case 'DELETE':
            // Delete user
            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                throw new Exception('User ID is required');
            }

            // Don't allow deleting yourself
            if ($userId == $_SESSION['user_id']) {
                throw new Exception('Cannot delete your own account');
            }

            $deleteSql = "DELETE FROM staff_users WHERE id = :id";
            $deleteStmt = $pdo->prepare($deleteSql);
            $deleteStmt->execute([':id' => $userId]);

            echo json_encode([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
