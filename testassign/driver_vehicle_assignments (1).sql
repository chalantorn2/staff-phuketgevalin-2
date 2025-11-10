-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 23, 2025 at 11:07 AM
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
