<?php
// api/auth/login.php - Super Simple Version
header('Content-Type: application/json');

require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        exit;
    }

    $db = new Database();
    $pdo = $db->getConnection();

    $sql = "SELECT id, username, password, full_name, role, status FROM staff_users WHERE username = ? AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    // Update last login
    $pdo->prepare("UPDATE staff_users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    unset($user['password']);

    echo json_encode([
        'success' => true,
        'data' => [
            'user' => $user,
            'token' => base64_encode($user['id'] . ':' . time())
        ],
        'message' => 'Login successful'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
