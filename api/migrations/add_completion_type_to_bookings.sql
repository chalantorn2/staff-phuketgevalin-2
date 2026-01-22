-- Add completion_type column to bookings table
-- This column stores the completion status: COMPLETED or NO_SHOW

ALTER TABLE bookings
ADD COLUMN completion_type VARCHAR(20) NULL DEFAULT NULL
COMMENT 'Completion type: COMPLETED or NO_SHOW'
AFTER internal_status;

-- Add index for better query performance
CREATE INDEX idx_bookings_completion_type ON bookings(completion_type);

-- Show the changes
SELECT 'Column completion_type added to bookings table successfully' AS message;
