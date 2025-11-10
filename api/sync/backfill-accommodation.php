<?php
// api/sync/backfill-accommodation.php - Backfill Missing Accommodation Data
// Purpose: Re-fetch accommodation data for bookings that are missing it
// Run: Every 15-30 minutes via cron OR manually via HTTP POST

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Allow both POST and GET (for cron and manual testing)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../config/holiday-taxis.php';
require_once '../config/province-mapping.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Parameters
    $input = json_decode(file_get_contents('php://input'), true);
    $batchSize = $input['batch_size'] ?? 10; // Process 10 bookings at a time
    $daysAhead = $input['days_ahead'] ?? 14; // Look ahead 14 days

    error_log("=== BACKFILL JOB STARTED ===");
    error_log("Batch size: $batchSize | Days ahead: $daysAhead");

    // Step 1: Find bookings missing accommodation data
    // Criteria:
    // - accommodation_name is NULL or empty
    // - pickup_date is in the future (today to +14 days)
    // - NOT cancelled (ht_status != 'ACAN')
    $sql = "SELECT
                booking_ref,
                pickup_date,
                arrival_date,
                departure_date,
                ht_status,
                synced_at
            FROM bookings
            WHERE (accommodation_name IS NULL OR accommodation_name = '')
            AND ht_status != 'ACAN'
            AND (
                (pickup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days_ahead DAY))
                OR (arrival_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days_ahead DAY))
                OR (departure_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days_ahead DAY))
            )
            ORDER BY pickup_date ASC, arrival_date ASC
            LIMIT :batch_size";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':days_ahead', (int)$daysAhead, PDO::PARAM_INT);
    $stmt->bindValue(':batch_size', (int)$batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $bookingsToFix = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalFound = count($bookingsToFix);
    error_log("Found $totalFound bookings missing accommodation data");

    if ($totalFound === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'No bookings need backfill',
            'data' => [
                'found' => 0,
                'fixed' => 0,
                'failed' => 0
            ]
        ]);
        exit;
    }

    // Step 2: Prepare API headers
    $headers = [
        "API_KEY: " . HolidayTaxisConfig::API_KEY,
        "Content-Type: application/json",
        "Accept: application/json",
        "VERSION: " . HolidayTaxisConfig::API_VERSION
    ];

    $totalFixed = 0;
    $totalFailed = 0;
    $errors = [];

    // Step 3: Process each booking
    foreach ($bookingsToFix as $index => $booking) {
        $bookingRef = $booking['booking_ref'];
        error_log("[$index/$totalFound] Processing $bookingRef...");

        try {
            // Rate limiting: delay between bookings
            if ($index > 0) {
                usleep(500000); // 0.5 second delay between bookings
            }

            // Fetch booking detail with retry
            $detailData = getBookingDetailWithRetry($bookingRef, $headers);

            if ($detailData === null) {
                error_log("✗ Failed to get detail for $bookingRef - skipping");
                $totalFailed++;
                $errors[] = $bookingRef . ": Detail API failed";
                continue;
            }

            // Extract accommodation data
            $accommodationName = null;
            $accommodationAddress1 = null;
            $accommodationAddress2 = null;
            $accommodationTel = null;

            if (isset($detailData['booking']['arrival'])) {
                $arrival = $detailData['booking']['arrival'];
                $accommodationName = $arrival['accommodationname'] ?? null;
                $accommodationAddress1 = $arrival['accommodationaddress1'] ?? null;
                $accommodationAddress2 = $arrival['accommodationaddress2'] ?? null;
                $accommodationTel = $arrival['accommodationtel'] ?? null;
            }

            // Try departure if arrival doesn't have data
            if (!$accommodationName && isset($detailData['booking']['departure'])) {
                $departure = $detailData['booking']['departure'];
                $accommodationName = $departure['accommodationname'] ?? null;
                $accommodationAddress1 = $departure['accommodationaddress1'] ?? null;
                $accommodationAddress2 = $departure['accommodationaddress2'] ?? null;
                $accommodationTel = $departure['accommodationtel'] ?? null;
            }

            // Check if we got accommodation data
            if ($accommodationName) {
                // Update booking with accommodation data
                $updateSql = "UPDATE bookings SET
                                accommodation_name = :accommodation_name,
                                accommodation_address1 = :accommodation_address1,
                                accommodation_address2 = :accommodation_address2,
                                accommodation_tel = :accommodation_tel,
                                updated_at = NOW()
                              WHERE booking_ref = :ref";

                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([
                    ':ref' => $bookingRef,
                    ':accommodation_name' => $accommodationName,
                    ':accommodation_address1' => $accommodationAddress1,
                    ':accommodation_address2' => $accommodationAddress2,
                    ':accommodation_tel' => $accommodationTel
                ]);

                error_log("✓ Fixed $bookingRef - accommodation: $accommodationName");
                $totalFixed++;
            } else {
                error_log("⚠ $bookingRef has no accommodation data in API response");
                $totalFailed++;
                $errors[] = $bookingRef . ": No accommodation in API";
            }

        } catch (Exception $e) {
            error_log("✗ Error processing $bookingRef: " . $e->getMessage());
            $totalFailed++;
            $errors[] = $bookingRef . ": " . $e->getMessage();
        }
    }

    // Step 4: Log results
    error_log("=== BACKFILL JOB COMPLETED ===");
    error_log("Found: $totalFound | Fixed: $totalFixed | Failed: $totalFailed");

    $response = [
        'success' => true,
        'message' => "Backfill completed: $totalFixed/$totalFound bookings fixed",
        'data' => [
            'found' => $totalFound,
            'fixed' => $totalFixed,
            'failed' => $totalFailed,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("BACKFILL JOB ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Fetch booking detail with retry mechanism
 */
function getBookingDetailWithRetry($bookingRef, $headers, $maxRetries = 3)
{
    $detailUrl = HolidayTaxisConfig::API_ENDPOINT . "/bookings/{$bookingRef}";

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $detailUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Success
        if ($httpCode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            if ($data !== null) {
                if ($attempt > 1) {
                    error_log("  ✓ Retry success on attempt $attempt");
                }
                return $data;
            }
        }

        // Failed - log and retry
        error_log("  ✗ Attempt $attempt/$maxRetries failed (HTTP $httpCode)");

        if ($attempt < $maxRetries) {
            $delay = $attempt * 500000; // 0.5s, 1s, 1.5s
            usleep($delay);
        }
    }

    return null;
}
