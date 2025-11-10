<?php
// t.php - Simple Short URL Handler
// Usage: /t.php?t=a1b2c3d4
// Or with mod_rewrite: /t/a1b2c3d4

require_once 'api/config/database.php';

try {
    // Get short token from query parameter
    $shortToken = $_GET['t'] ?? null;

    // Method 2: Path info (/t.php/TOKEN)
    if (!$shortToken) {
        $pathInfo = $_SERVER['PATH_INFO'] ?? '';
        $segments = array_filter(explode('/', trim($pathInfo, '/')));
        $segments = array_values($segments);

        if (count($segments) >= 1) {
            $shortToken = $segments[0];
        }
    }

    // Validate
    if (empty($shortToken)) {
        http_response_code(400);
        die('Invalid tracking link. Missing token.');
    }

    // Look up full token from database using only short_token
    $db = new Database();
    $pdo = $db->getConnection();

    $sql = "SELECT token, expires_at, status
            FROM driver_tracking_tokens
            WHERE short_token = :short_token
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':short_token' => $shortToken
    ]);

    $result = $stmt->fetch();

    if (!$result) {
        http_response_code(404);
        die('Tracking link not found or expired. Please contact support.');
    }

    // Check if expired
    if (strtotime($result['expires_at']) < time()) {
        http_response_code(410);
        die('This tracking link has expired. Please contact support.');
    }

    // Build redirect URL
    $protocol = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $redirectUrl = $protocol . $host . '/track.html?token=' . $result['token'];

    // Redirect to tracking page
    header('Location: ' . $redirectUrl);
    exit;

} catch (Exception $e) {
    error_log("Short URL handler error: " . $e->getMessage());
    http_response_code(500);
    die('Server error. Please try again later.');
}
