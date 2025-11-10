-- Fix pax_total calculation for all existing bookings
-- This script extracts adults, children, and infants from raw_data JSON
-- and updates pax_total = adults + children + infants

UPDATE bookings
SET
    adults = COALESCE(
        JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.detail_data.booking.general.adults')),
        JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.search_data.adults')),
        1
    ),
    children = COALESCE(
        JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.detail_data.booking.general.children')),
        JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.search_data.children')),
        0
    ),
    infants = COALESCE(
        JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.detail_data.booking.general.infants')),
        JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.search_data.infants')),
        0
    ),
    pax_total = (
        COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.detail_data.booking.general.adults')),
            JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.search_data.adults')),
            1
        ) +
        COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.detail_data.booking.general.children')),
            JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.search_data.children')),
            0
        ) +
        COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.detail_data.booking.general.infants')),
            JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.search_data.infants')),
            0
        )
    ),
    updated_at = NOW()
WHERE raw_data IS NOT NULL
AND JSON_VALID(raw_data) = 1;

-- Show results of the update
SELECT
    booking_ref,
    passenger_name,
    adults,
    children,
    infants,
    pax_total,
    CONCAT(adults, ' + ', children, ' + ', infants, ' = ', pax_total) as calculation
FROM bookings
ORDER BY updated_at DESC
LIMIT 20;
