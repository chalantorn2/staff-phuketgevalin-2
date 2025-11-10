-- Remove short_token column from driver_tracking_tokens table
-- This reverts the changes from add_short_token.sql

-- Drop the unique index first
ALTER TABLE `driver_tracking_tokens`
DROP INDEX IF EXISTS `idx_short_token`;

-- Drop the short_token column
ALTER TABLE `driver_tracking_tokens`
DROP COLUMN IF EXISTS `short_token`;
