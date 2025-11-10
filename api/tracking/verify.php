<?php
// api/tracking/verify.php - Verify Tracking Data Sync Status
error_reporting(E_ALL);
ini_set('display_errors', 1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

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
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendResponse(false, null, 'Method not allowed', 405);
    }

    $bookingRef = $_GET['booking_ref'] ?? null;

    if (!$bookingRef) {
        sendResponse(false, null, 'booking_ref is required', 400);
    }

    $db = new Database();
    $pdo = $db->getConnection();

    // Get tracking token info
    $tokenSql = "SELECT t.*, d.name as driver_name, v.registration,
                        a.status as assignment_status, a.assigned_at
                 FROM driver_tracking_tokens t
                 LEFT JOIN drivers d ON t.driver_id = d.id
                 LEFT JOIN vehicles v ON t.vehicle_id = v.id
                 LEFT JOIN driver_vehicle_assignments a ON t.assignment_id = a.id
                 WHERE t.booking_ref = :ref
                 ORDER BY t.created_at DESC
                 LIMIT 1";

    $tokenStmt = $pdo->prepare($tokenSql);
    $tokenStmt->execute([':ref' => $bookingRef]);
    $token = $tokenStmt->fetch();

    if (!$token) {
        sendResponse(false, null, 'No tracking found for this booking', 404);
    }

    // Get location logs
    $locationsSql = "SELECT id, latitude, longitude, accuracy, tracking_status,
                            tracked_at, synced_to_holidaytaxis, sync_http_code, sync_response
                     FROM driver_location_logs
                     WHERE booking_ref = :ref
                     ORDER BY tracked_at ASC";

    $locationsStmt = $pdo->prepare($locationsSql);
    $locationsStmt->execute([':ref' => $bookingRef]);
    $locations = $locationsStmt->fetchAll();

    // Calculate sync statistics
    $totalLocations = count($locations);
    $syncedLocations = array_filter($locations, fn($loc) => $loc['synced_to_holidaytaxis'] == 1);
    $syncedCount = count($syncedLocations);
    $syncSuccessRate = $totalLocations > 0 ? round(($syncedCount / $totalLocations) * 100, 2) : 0;

    // Get first and last location
    $firstLocation = $locations[0] ?? null;
    $lastLocation = $locations[count($locations) - 1] ?? null;

    // Check driver/vehicle sync (from start.php)
    $driverVehicleSynced = $token['started_at'] ? true : false;

    // Format response
    $response = [
        'booking_ref' => $bookingRef,
        'assignment_status' => $token['assignment_status'],
        'tracking_status' => $token['status'],

        'tracking_info' => [
            'token_created_at' => $token['created_at'],
            'started_at' => $token['started_at'],
            'completed_at' => $token['completed_at'],
            'expires_at' => $token['expires_at'],
            'is_expired' => strtotime($token['expires_at']) < time()
        ],

        'driver_vehicle' => [
            'driver_name' => $token['driver_name'],
            'vehicle_registration' => $token['registration'],
            'vehicle_identifier' => $token['vehicle_identifier'],
            'synced_at_start' => $driverVehicleSynced
        ],

        'gps_tracking' => [
            'total_locations_sent' => $totalLocations,
            'locations_synced_to_ht' => $syncedCount,
            'sync_success_rate' => $syncSuccessRate . '%',
            'first_location_at' => $firstLocation ? $firstLocation['tracked_at'] : null,
            'last_location_at' => $lastLocation ? $lastLocation['tracked_at'] : null,
            'tracking_duration' => $firstLocation && $lastLocation ?
                gmdate('H:i:s', strtotime($lastLocation['tracked_at']) - strtotime($firstLocation['tracked_at'])) : null
        ],

        'sync_details' => [
            'all_synced' => $syncedCount === $totalLocations && $totalLocations > 0,
            'partial_sync' => $syncedCount > 0 && $syncedCount < $totalLocations,
            'no_sync' => $syncedCount === 0 && $totalLocations > 0,
            'failed_syncs' => $totalLocations - $syncedCount
        ],

        'recent_locations' => array_map(function($loc) {
            return [
                'tracked_at' => $loc['tracked_at'],
                'latitude' => (float)$loc['latitude'],
                'longitude' => (float)$loc['longitude'],
                'accuracy' => (float)$loc['accuracy'],
                'status' => $loc['tracking_status'],
                'synced_to_ht' => (bool)$loc['synced_to_holidaytaxis'],
                'http_code' => $loc['sync_http_code'],
                'response' => $loc['sync_response']
            ];
        }, array_slice($locations, -10)) // Last 10 locations
    ];

    // Overall verdict
    if ($totalLocations === 0) {
        $verdict = '⚠️ No GPS data sent yet';
    } elseif ($syncedCount === $totalLocations) {
        $verdict = '✅ All data synced to Holiday Taxis successfully';
    } elseif ($syncedCount > 0) {
        $verdict = '⚠️ Partial sync - some data failed to sync to Holiday Taxis';
    } else {
        $verdict = '❌ All sync attempts failed - Holiday Taxis may not have received data';
    }

    $response['verdict'] = $verdict;

    sendResponse(true, $response, 'Tracking verification completed');

} catch (Exception $e) {
    error_log("Tracking Verify API error: " . $e->getMessage());
    sendResponse(false, null, 'Server error: ' . $e->getMessage(), 500);
}
