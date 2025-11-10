-- Fix vehicle_identifier to use English format instead of Thai registration
-- Problem: Holiday Taxis API doesn't accept Thai characters in vehicleIdentifier
-- Solution: Change from Thai registration (e.g., "กค-5678") to "vehicle_{id}" format

-- Update existing driver_tracking_tokens to use vehicle_id format
UPDATE driver_tracking_tokens
SET vehicle_identifier = CONCAT('vehicle_', vehicle_id)
WHERE vehicle_id IS NOT NULL;

-- Verify the changes
-- SELECT id, booking_ref, vehicle_id, vehicle_identifier, created_at
-- FROM driver_tracking_tokens
-- ORDER BY created_at DESC
-- LIMIT 20;
