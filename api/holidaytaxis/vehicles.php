<?php
// api/holidaytaxis/vehicles.php - Set Driver & Vehicle, Deallocate Vehicle
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/holiday-taxis.php';
require_once '../config/database.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $bookingRef = $_GET['ref'] ?? null;
    $vehicleIdentifier = $_GET['vehicle'] ?? null;

    if (!$bookingRef) {
        throw new Exception('Booking reference (ref) is required');
    }

    if (!$vehicleIdentifier) {
        throw new Exception('Vehicle identifier is required');
    }

    // Validate booking reference format
    if (!preg_match('/^[a-zA-Z0-9]{2,5}-[0-9]{6,}$/', $bookingRef)) {
        throw new Exception('Invalid booking reference format');
    }

    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    if ($method === 'PUT') {
        // Set Driver and Vehicle
        $requestBody = file_get_contents('php://input');
        $data = json_decode($requestBody, true);

        if (!$data) {
            throw new Exception('Invalid JSON in request body');
        }

        // Validate required fields
        if (!isset($data['driver']) || !isset($data['vehicle'])) {
            throw new Exception('Both driver and vehicle data are required');
        }

        // Validate driver fields
        $requiredDriverFields = ['name', 'phoneNumber'];
        foreach ($requiredDriverFields as $field) {
            if (!isset($data['driver'][$field]) || empty($data['driver'][$field])) {
                throw new Exception("Driver field '{$field}' is required");
            }
        }

        // Validate vehicle fields
        $requiredVehicleFields = ['brand', 'model', 'registration'];
        foreach ($requiredVehicleFields as $field) {
            if (!isset($data['vehicle'][$field]) || empty($data['vehicle'][$field])) {
                throw new Exception("Vehicle field '{$field}' is required");
            }
        }

        // Build API URL
        $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$bookingRef}/vehicles/{$vehicleIdentifier}";

        // Make API call
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if ($curlError) {
            throw new Exception("cURL Error: " . $curlError);
        }

        // Handle HTTP errors
        if ($httpCode === 404) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Booking not found',
                'booking_ref' => $bookingRef
            ]);
            exit;
        }

        if ($httpCode === 400) {
            http_response_code(400);
            $errorData = json_decode($response, true);
            echo json_encode([
                'success' => false,
                'error' => 'Bad request',
                'details' => $errorData,
                'api_response' => $response
            ]);
            exit;
        }

        if (!in_array($httpCode, [200, 201, 204])) {
            throw new Exception("Holiday Taxis API error: HTTP $httpCode - " . $response);
        }

        // Parse response
        $responseData = json_decode($response, true);

        // Update local database - mark assignment as having vehicle data sent
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $updateSql = "UPDATE driver_vehicle_assignments
                         SET last_sync_at = NOW(),
                             booking_status = 'ACON'
                         WHERE booking_ref = :ref";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([':ref' => $bookingRef]);
        } catch (Exception $dbError) {
            // Log but don't fail - the Holiday Taxis update was successful
            error_log("Database update error: " . $dbError->getMessage());
        }

        // Format success response
        $result = [
            'success' => true,
            'data' => $responseData ?? ['message' => 'Vehicle data sent successfully'],
            'meta' => [
                'booking_ref' => $bookingRef,
                'vehicle_identifier' => $vehicleIdentifier,
                'api_url' => $apiUrl,
                'http_code' => $httpCode,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];

        http_response_code($httpCode);
        echo json_encode($result);

    } elseif ($method === 'DELETE') {
        // Deallocate Vehicle
        $apiUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$bookingRef}/vehicles/{$vehicleIdentifier}";

        // Make API call
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if ($curlError) {
            throw new Exception("cURL Error: " . $curlError);
        }

        // Handle HTTP errors
        if ($httpCode === 404) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Booking or vehicle not found',
                'booking_ref' => $bookingRef
            ]);
            exit;
        }

        if (!in_array($httpCode, [200, 204])) {
            throw new Exception("Holiday Taxis API error: HTTP $httpCode - " . $response);
        }

        // Update local database
        try {
            $db = new Database();
            $pdo = $db->getConnection();

            $updateSql = "UPDATE driver_vehicle_assignments
                         SET last_sync_at = NOW()
                         WHERE booking_ref = :ref";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([':ref' => $bookingRef]);
        } catch (Exception $dbError) {
            error_log("Database update error: " . $dbError->getMessage());
        }

        // Format success response
        $result = [
            'success' => true,
            'data' => ['message' => 'Vehicle deallocated successfully'],
            'meta' => [
                'booking_ref' => $bookingRef,
                'vehicle_identifier' => $vehicleIdentifier,
                'api_url' => $apiUrl,
                'http_code' => $httpCode,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];

        http_response_code(200);
        echo json_encode($result);

    } else {
        throw new Exception('Only PUT and DELETE methods are allowed');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'booking_ref' => $bookingRef ?? null,
            'vehicle_identifier' => $vehicleIdentifier ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
