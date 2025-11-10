-- ====================================================================
-- SQL สำหรับมอบหมายงาน (Job Assignments)
-- เลือกเฉพาะ Booking ที่มีสถานะ ACON (Agent Confirmed) เท่านั้น
-- ====================================================================

-- Assignment 1: Test-HBEDS-24409466 (Chiang Rai - ACON)
-- Arrival: 2025-08-01 08:30:00
-- Driver: Somchai Jaidee (ID: 1), Vehicle: Toyota Commuter (ID: 1, KK-1234)
INSERT INTO `driver_vehicle_assignments`
(`booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `status`, `booking_status`, `has_tracking`)
VALUES
('Test-HBEDS-24409466', 1, 1, 'KK-1234', 'Assigned for testing - Chiang Rai Airport pickup', 'assigned', 'ACON', 0);

-- Assignment 2: Test-AMDES-26112095 (Chiang Mai - ACON)
-- Arrival: 2025-08-01 10:15:00
-- Driver: Somsri Rakngan (ID: 2), Vehicle: Honda Civic (ID: 2, KK-5678)
INSERT INTO `driver_vehicle_assignments`
(`booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `status`, `booking_status`, `has_tracking`)
VALUES
('Test-AMDES-26112095', 2, 2, 'KK-5678', 'Assigned for testing - Chiang Mai Airport pickup', 'assigned', 'ACON', 0);

-- Assignment 3: Test-HBEDS-25821343 (Koh Samui - ACON)
-- Arrival: 2025-08-01 11:05:00
-- Driver: Noppadon Thiengtrong (ID: 3), Vehicle: Isuzu D-Max (ID: 3, ABC-9012)
INSERT INTO `driver_vehicle_assignments`
(`booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `status`, `booking_status`, `has_tracking`)
VALUES
('Test-HBEDS-25821343', 3, 3, 'ABC-9012', 'Assigned for testing - Koh Samui Airport pickup', 'assigned', 'ACON', 0);

-- Assignment 4: Test-HBEDS-24703414 (Koh Samui - ACON)
-- Arrival: 2025-08-01 11:05:00 (Same time as above, different driver)
-- Driver: Prayut Khayan (ID: 4), Vehicle: Mazda CX-5 (ID: 4, KG-3456)
INSERT INTO `driver_vehicle_assignments`
(`booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `status`, `booking_status`, `has_tracking`)
VALUES
('Test-HBEDS-24703414', 4, 4, 'KG-3456', 'Assigned for testing - Koh Samui Airport pickup (Minibus)', 'assigned', 'ACON', 0);

-- Assignment 5: Test-HBEDS-24021988 (Chiang Mai - ACON)
-- Arrival: 2025-08-01 11:40:00
-- Driver: Test Driver (ID: 8), Vehicle: Toyota Vios (ID: 5, XYZ-7890)
INSERT INTO `driver_vehicle_assignments`
(`booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `status`, `booking_status`, `has_tracking`)
VALUES
('Test-HBEDS-24021988', 8, 5, 'XYZ-7890', 'Assigned for testing - Chiang Mai Airport to city center', 'assigned', 'ACON', 0);

-- Assignment 6: Test-HBEDS-24310307 (Koh Samui - ACON)
-- Arrival: 2025-08-01 13:20:00
-- Driver: Somchai Jaidee (ID: 1), Vehicle: Toyota Commuter (ID: 1, KK-1234)
INSERT INTO `driver_vehicle_assignments`
(`booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `status`, `booking_status`, `has_tracking`)
VALUES
('Test-HBEDS-24310307', 1, 1, 'KK-1234', 'Assigned for testing - Second job for Somchai', 'assigned', 'ACON', 0);

-- Assignment 7: Test-TCS-24606124 (Chiang Mai - ACON)
-- Arrival: 2025-08-01 13:40:00
-- Driver: Somsri Rakngan (ID: 2), Vehicle: Honda Civic (ID: 2, KK-5678)
INSERT INTO `driver_vehicle_assignments`
(`booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `status`, `booking_status`, `has_tracking`)
VALUES
('Test-TCS-24606124', 2, 2, 'KK-5678', 'Assigned for testing - Chiang Mai city center', 'assigned', 'ACON', 0);

-- Assignment 8: Test-HBEDS-25634740 (Chiang Rai - ACON)
-- Arrival: 2025-08-01 17:20:00
-- Driver: Noppadon Thiengtrong (ID: 3), Vehicle: Isuzu D-Max (ID: 3, ABC-9012)
INSERT INTO `driver_vehicle_assignments`
(`booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `status`, `booking_status`, `has_tracking`)
VALUES
('Test-HBEDS-25634740', 3, 3, 'ABC-9012', 'Assigned for testing - Chiang Rai evening pickup', 'assigned', 'ACON', 0);

-- Assignment 9: Test-HBEDS-26117430 (Koh Samui - ACON)
-- Arrival: 2025-08-01 17:45:00
-- Driver: Prayut Khayan (ID: 4), Vehicle: Mazda CX-5 (ID: 4, KG-3456)
INSERT INTO `driver_vehicle_assignments`
(`booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `status`, `booking_status`, `has_tracking`)
VALUES
('Test-HBEDS-26117430', 4, 4, 'KG-3456', 'Assigned for testing - Koh Samui evening arrival', 'assigned', 'ACON', 0);

-- Assignment 10: Test-HBEDS-26278041 (Koh Samui - ACON)
-- Arrival: 2025-08-01 18:00:00
-- Driver: Test Driver (ID: 8), Vehicle: Toyota Vios (ID: 5, XYZ-7890)
INSERT INTO `driver_vehicle_assignments`
(`booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `status`, `booking_status`, `has_tracking`)
VALUES
('Test-HBEDS-26278041', 8, 5, 'XYZ-7890', 'Assigned for testing - Koh Samui late evening', 'assigned', 'ACON', 0);

-- ====================================================================
-- สรุป: สร้าง 10 Assignments สำหรับทดสอบ
-- - เฉพาะ Booking ที่มีสถานะ ACON เท่านั้น
-- - กระจายงานให้ไดรเวอร์และรถทั้ง 5 คัน
-- - ครอบคลุมพื้นที่: Chiang Rai, Chiang Mai, Koh Samui
-- - เวลา Pickup: 08:30 - 18:00
-- ====================================================================

COMMIT;
