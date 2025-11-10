-- Add 'code' column to drivers table
-- This column will be used for driver login instead of ID

ALTER TABLE `drivers`
ADD COLUMN `code` VARCHAR(50) DEFAULT NULL AFTER `license_number`,
ADD UNIQUE KEY `idx_code` (`code`);

-- Optional: Update existing drivers with their ID as initial code
-- You can run this if you want to set initial codes
-- UPDATE `drivers` SET `code` = CONCAT('DRV', LPAD(id, 3, '0')) WHERE `code` IS NULL;
