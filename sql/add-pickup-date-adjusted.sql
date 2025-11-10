-- Add pickup_date_adjusted column to bookings table
-- This allows us to store adjusted pickup times when flights are delayed

ALTER TABLE bookings
ADD COLUMN pickup_date_adjusted DATETIME NULL
COMMENT 'Adjusted pickup date/time when original time needs to be changed (e.g., flight delays)'
AFTER pickup_date;

-- Check if column was added successfully
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'bookings'
  AND COLUMN_NAME = 'pickup_date_adjusted';
