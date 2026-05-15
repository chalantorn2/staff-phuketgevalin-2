-- Fix vehicle_identifier for ALL pending/active tokens
-- Background:
--   * staff app previously stored Thai registration (e.g. "กค-5678") -> HT rejects with "Invalid Vehicle Identifier"
--   * driver mobile app previously stored booking_ref as vehicle_identifier -> HT rejects
-- Solution: use deterministic "vehicle_{id}" format (ASCII only) for all rows where vehicle_id is set.
-- Safe to re-run: only updates rows whose value is not already in the correct format.

UPDATE driver_tracking_tokens
SET vehicle_identifier = CONCAT('vehicle_', vehicle_id)
WHERE vehicle_id IS NOT NULL
  AND vehicle_identifier <> CONCAT('vehicle_', vehicle_id);

-- Verify
-- SELECT id, booking_ref, vehicle_id, vehicle_identifier, status, created_at
-- FROM driver_tracking_tokens
-- ORDER BY created_at DESC
-- LIMIT 50;
