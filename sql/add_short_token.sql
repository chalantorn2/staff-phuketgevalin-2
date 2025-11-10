-- Add short_token column to driver_tracking_tokens table
-- This allows short URLs like: /TCS-25581676/a1b2c3

ALTER TABLE `driver_tracking_tokens`
ADD COLUMN `short_token` VARCHAR(10) DEFAULT NULL AFTER `token`,
ADD UNIQUE KEY `idx_short_token` (`short_token`);

-- Update existing tokens to have short tokens
UPDATE `driver_tracking_tokens`
SET `short_token` = SUBSTRING(MD5(CONCAT(token, id)), 1, 8)
WHERE `short_token` IS NULL;
