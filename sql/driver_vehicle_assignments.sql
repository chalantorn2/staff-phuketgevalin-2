-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 19, 2025 at 07:56 PM
-- Server version: 10.6.19-MariaDB-log
-- PHP Version: 8.3.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tptravel_staff`
--

-- --------------------------------------------------------

--
-- Table structure for table `driver_vehicle_assignments`
--

CREATE TABLE `driver_vehicle_assignments` (
  `id` int(11) NOT NULL,
  `booking_ref` varchar(20) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `vehicle_identifier` varchar(40) NOT NULL,
  `assignment_notes` text DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('assigned','in_progress','completed','cancelled') DEFAULT 'assigned',
  `booking_status` varchar(10) DEFAULT 'ACON',
  `last_sync_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `has_tracking` tinyint(1) DEFAULT 0,
  `tracking_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `driver_vehicle_assignments`
--

INSERT INTO `driver_vehicle_assignments` (`id`, `booking_ref`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `assignment_notes`, `assigned_by`, `assigned_at`, `status`, `booking_status`, `last_sync_at`, `cancelled_at`, `cancellation_reason`, `has_tracking`, `tracking_token`) VALUES
(7, 'TCS-25581676', 1, 3, 'ABC-9012', 'Test', NULL, '2025-10-18 09:34:13', 'completed', 'ACON', NULL, NULL, NULL, 1, '0a302a0fc5c82a642d5e6434894b745fec2de272b205612590d5e596fd0d4d61'),
(8, 'HBEDS-26615051', 8, 2, 'กค-5678', 'Test', NULL, '2025-10-18 10:09:11', 'completed', 'ACON', NULL, NULL, NULL, 1, 'd798bd154cb53ee271f7386113e4cc2c7415ba03dc3af3751428fb997ea38392'),
(11, 'HBEDS-24297746', 1, 2, 'KK-5678', 'Test', NULL, '2025-10-18 10:47:33', 'completed', 'ACON', NULL, NULL, NULL, 1, 'effbbdcc665f50ef07b030385c641cf14bacd263fa1bf245fb6c6fca9c6831bf');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `driver_vehicle_assignments`
--
ALTER TABLE `driver_vehicle_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_ref` (`booking_ref`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `fk_assigned_by` (`assigned_by`),
  ADD KEY `idx_booking_status` (`booking_status`),
  ADD KEY `idx_tracking_token` (`tracking_token`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `driver_vehicle_assignments`
--
ALTER TABLE `driver_vehicle_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `driver_vehicle_assignments`
--
ALTER TABLE `driver_vehicle_assignments`
  ADD CONSTRAINT `driver_vehicle_assignments_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `driver_vehicle_assignments_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
