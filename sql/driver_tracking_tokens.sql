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
-- Table structure for table `driver_tracking_tokens`
--

CREATE TABLE `driver_tracking_tokens` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `booking_ref` varchar(50) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `vehicle_identifier` varchar(50) NOT NULL,
  `status` enum('pending','active','completed','cancelled') DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `tracking_interval` int(11) DEFAULT 30,
  `last_location_at` datetime DEFAULT NULL,
  `total_locations_sent` int(11) DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver_tracking_tokens`
--

INSERT INTO `driver_tracking_tokens` (`id`, `token`, `booking_ref`, `assignment_id`, `driver_id`, `vehicle_id`, `vehicle_identifier`, `status`, `started_at`, `completed_at`, `tracking_interval`, `last_location_at`, `total_locations_sent`, `expires_at`, `created_at`) VALUES
(3, '0a302a0fc5c82a642d5e6434894b745fec2de272b205612590d5e596fd0d4d61', 'TCS-25581676', 7, 1, 3, 'ABC-9012', 'completed', '2025-10-18 16:40:39', '2025-10-18 16:41:10', 30, '2025-10-18 16:40:50', 4, '2025-10-21 16:40:00', '2025-10-18 16:34:15'),
(4, 'dc13fb2e3131d767b55ba8451b6dd5028082f4d7ec3b81cf0b41dd3c0121ad82', 'HBEDS-26615051', 8, 8, 2, 'กค-5678', 'completed', '2025-10-18 17:20:42', '2025-10-18 17:21:09', 30, '2025-10-18 17:20:51', 2, '2025-10-21 17:20:00', '2025-10-18 17:09:13'),
(6, 'd798bd154cb53ee271f7386113e4cc2c7415ba03dc3af3751428fb997ea38392', 'HBEDS-26615051', 8, 8, 2, 'KK-5678', 'pending', NULL, NULL, 30, NULL, 0, '2025-10-21 17:20:00', '2025-10-18 17:43:22'),
(8, 'd6d0d0016ae5c69d40dc6cf9fc87bb98dda74b04c71ffc5301204725d17dcb1e', 'HBEDS-24297746', 11, 1, 2, 'KK-5678', 'completed', '2025-10-18 17:48:00', '2025-10-18 17:48:22', 30, '2025-10-18 17:48:12', 2, '2025-10-21 18:25:00', '2025-10-18 17:47:35'),
(9, 'effbbdcc665f50ef07b030385c641cf14bacd263fa1bf245fb6c6fca9c6831bf', 'HBEDS-24297746', 11, 1, 2, 'KK-5678', 'pending', NULL, NULL, 30, NULL, 0, '2025-10-21 18:25:00', '2025-10-18 18:22:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `driver_tracking_tokens`
--
ALTER TABLE `driver_tracking_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_booking_ref` (`booking_ref`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `driver_tracking_tokens`
--
ALTER TABLE `driver_tracking_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `driver_tracking_tokens`
--
ALTER TABLE `driver_tracking_tokens`
  ADD CONSTRAINT `driver_tracking_tokens_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `driver_vehicle_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `driver_tracking_tokens_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `driver_tracking_tokens_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
