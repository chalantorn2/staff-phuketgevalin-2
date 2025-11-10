-- Migration: Add Quote Booking Fields to bookings table
-- Purpose: Support Quote bookings (Point-to-Point transfers)
-- Date: 2025-10-29

USE tptravel;

-- Add fields for Quote bookings
ALTER TABLE `bookings`
ADD COLUMN `pickup_address1` VARCHAR(255) NULL COMMENT 'For Quote: pickup location name' AFTER `accommodation_tel`,
ADD COLUMN `pickup_address2` VARCHAR(255) NULL COMMENT 'For Quote: pickup city/area' AFTER `pickup_address1`,
ADD COLUMN `pickup_address3` VARCHAR(100) NULL COMMENT 'For Quote: pickup province' AFTER `pickup_address2`,
ADD COLUMN `pickup_address4` VARCHAR(20) NULL COMMENT 'For Quote: pickup postal code' AFTER `pickup_address3`,
ADD COLUMN `dropoff_address1` VARCHAR(255) NULL COMMENT 'For Quote: dropoff location name' AFTER `pickup_address4`,
ADD COLUMN `dropoff_address2` VARCHAR(255) NULL COMMENT 'For Quote: dropoff city/area' AFTER `dropoff_address1`,
ADD COLUMN `dropoff_address3` VARCHAR(100) NULL COMMENT 'For Quote: dropoff province' AFTER `dropoff_address2`,
ADD COLUMN `dropoff_address4` VARCHAR(20) NULL COMMENT 'For Quote: dropoff postal code' AFTER `dropoff_address3`,
ADD COLUMN `transfer_date` DATETIME NULL COMMENT 'For Quote: transfer date/time' AFTER `dropoff_address4`;

-- Add index for better query performance
CREATE INDEX `idx_transfer_date` ON `bookings`(`transfer_date`);
CREATE INDEX `idx_booking_type` ON `bookings`(`booking_type`);

-- Update existing Quote bookings to set transfer_date from pickup_date
UPDATE `bookings`
SET `transfer_date` = `pickup_date`
WHERE `booking_type` = 'Quote' AND `transfer_date` IS NULL AND `pickup_date` IS NOT NULL;

SELECT 'Migration completed: Quote booking fields added successfully!' as Result;
