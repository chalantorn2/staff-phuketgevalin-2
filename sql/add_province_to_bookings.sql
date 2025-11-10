-- Add province columns to bookings table
-- This allows automatic province detection from airport or postal code

ALTER TABLE bookings
ADD COLUMN province VARCHAR(100) NULL COMMENT 'Auto-detected or manually set province',
ADD COLUMN province_source ENUM('airport', 'postal', 'manual', 'unknown') DEFAULT 'unknown' COMMENT 'How province was determined',
ADD COLUMN province_confidence ENUM('high', 'medium', 'low') DEFAULT 'low' COMMENT 'Confidence level of detection',
ADD INDEX idx_province (province);

-- Add comment to table
ALTER TABLE bookings COMMENT = 'Bookings synced from Holiday Taxis API with province detection';
