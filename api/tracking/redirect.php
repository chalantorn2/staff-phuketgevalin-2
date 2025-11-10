<?php
// api/tracking/redirect.php - Short URL Redirect Handler
// Handles URLs like: /{booking_ref}/{short_token}
// Redirects to: /track.html?token={long_token}

require_once '../config/database.php';

try {
    // Get booking_ref and short_token from URL
    // Expected format: /TCS-25581676/a1b2c3d4
    $requestUri = $_SERVER['REQUEST_URI'];

    // Remove query string if exists
    $path = parse_url($requestUri, PHP_URL_PATH);

    // Remove leading slash and split by slash
    $segments = array_filter(explode('/', trim($path, '/')));
    $segments = array_values($segments); // Re-index

    // Should have exactly 2 segments: booking_ref and short_token
    if (count($segments) !== 2) {
        http_response_code(404);
        die('Invalid tracking link format. Expected: /{booking_ref}/{short_token}');
    }

    $bookingRef = $segments[0];
    $shortToken = $segments[1];

    // Validate format
    if (empty($bookingRef) || empty($shortToken)) {
        http_response_code(400);
        die('Missing booking reference or token');
    }

    // Look up full token from database
    $db = new Database();
    $pdo = $db->getConnection();

    $sql = "SELECT token, expires_at, status
            FROM driver_tracking_tokens
            WHERE booking_ref = :booking_ref
            AND short_token = :short_token
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':booking_ref' => $bookingRef,
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
    error_log("Redirect API error: " . $e->getMessage());
    http_response_code(500);
    die('Server error. Please try again later.');
}
