<?php
// redirect-handler.php - Alternative URL Handler (if .htaccess doesn't work)
// Place this in root directory and configure web server to route requests here

require_once 'api/config/database.php';

try {
    // Get the request URI
    $requestUri = $_SERVER['REQUEST_URI'];

    // Remove query string if exists
    $path = parse_url($requestUri, PHP_URL_PATH);

    // Remove leading slash and split by slash
    $segments = array_filter(explode('/', trim($path, '/')));
    $segments = array_values($segments); // Re-index

    // Check if this is a tracking short URL format: /BOOKING-REF/SHORT_TOKEN
    if (count($segments) === 2 && preg_match('/^[A-Z0-9-]+$/', $segments[0]) && preg_match('/^[a-z0-9]+$/', $segments[1])) {
        $bookingRef = $segments[0];
        $shortToken = $segments[1];

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
    }

    // Not a tracking URL - show 404
    http_response_code(404);
    echo "404 - Page Not Found";

} catch (Exception $e) {
    error_log("Redirect handler error: " . $e->getMessage());
    http_response_code(500);
    die('Server error. Please try again later.');
}
