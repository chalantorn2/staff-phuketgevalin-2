-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 02, 2025 at 02:41 PM
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
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_ref` varchar(20) NOT NULL,
  `ht_status` enum('PCON','ACON','PAMM','AAMM','PCAN','ACAN') NOT NULL,
  `internal_status` enum('pending','confirmed','assigned','in_progress','completed','rejected') DEFAULT 'pending',
  `passenger_name` varchar(255) DEFAULT NULL,
  `passenger_email` varchar(255) DEFAULT NULL,
  `passenger_phone` varchar(50) DEFAULT NULL,
  `pax_total` int(11) DEFAULT 1,
  `adults` int(11) DEFAULT 1,
  `children` int(11) DEFAULT 0,
  `infants` int(11) DEFAULT 0,
  `booking_type` varchar(100) DEFAULT NULL,
  `vehicle_type` varchar(100) DEFAULT NULL,
  `service_type` varchar(100) DEFAULT NULL,
  `airport` varchar(100) DEFAULT NULL,
  `airport_code` varchar(10) DEFAULT NULL,
  `resort` varchar(255) DEFAULT NULL,
  `accommodation_name` varchar(255) DEFAULT NULL,
  `accommodation_address1` varchar(255) DEFAULT NULL,
  `accommodation_address2` varchar(255) DEFAULT NULL,
  `accommodation_tel` varchar(50) DEFAULT NULL,
  `pickup_address1` varchar(255) DEFAULT NULL COMMENT 'For Quote: pickup location name',
  `pickup_address2` varchar(255) DEFAULT NULL COMMENT 'For Quote: pickup city/area',
  `pickup_address3` varchar(100) DEFAULT NULL COMMENT 'For Quote: pickup province',
  `pickup_address4` varchar(20) DEFAULT NULL COMMENT 'For Quote: pickup postal code',
  `dropoff_address1` varchar(255) DEFAULT NULL COMMENT 'For Quote: dropoff location name',
  `dropoff_address2` varchar(255) DEFAULT NULL COMMENT 'For Quote: dropoff city/area',
  `dropoff_address3` varchar(100) DEFAULT NULL COMMENT 'For Quote: dropoff province',
  `dropoff_address4` varchar(20) DEFAULT NULL COMMENT 'For Quote: dropoff postal code',
  `transfer_date` datetime DEFAULT NULL COMMENT 'For Quote: transfer date/time',
  `arrival_date` datetime DEFAULT NULL,
  `departure_date` datetime DEFAULT NULL,
  `pickup_date` datetime DEFAULT NULL,
  `pickup_date_adjusted` datetime DEFAULT NULL COMMENT 'Adjusted pickup date/time when original time needs to be changed (e.g., flight delays)',
  `last_action_date` datetime NOT NULL,
  `date_booked` datetime DEFAULT NULL,
  `flight_no_arrival` varchar(20) DEFAULT NULL,
  `flight_no_departure` varchar(20) DEFAULT NULL,
  `from_airport` varchar(100) DEFAULT NULL,
  `to_airport` varchar(100) DEFAULT NULL,
  `raw_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_data`)),
  `notes` text DEFAULT NULL,
  `synced_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `province` varchar(100) DEFAULT NULL COMMENT 'Auto-detected or manually set province',
  `province_source` enum('airport','postal','manual','unknown') DEFAULT 'unknown' COMMENT 'How province was determined',
  `province_confidence` enum('high','medium','low') DEFAULT 'low' COMMENT 'Confidence level of detection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Bookings synced from Holiday Taxis API with province detection';

-- --------------------------------------------------------

--
-- Table structure for table `booking_actions`
--

CREATE TABLE `booking_actions` (
  `id` int(11) NOT NULL,
  `booking_ref` varchar(20) NOT NULL,
  `action_type` enum('sync','manual_update','confirmed','rejected','assigned','completed') NOT NULL,
  `action_by` int(11) DEFAULT NULL,
  `action_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_data`)),
  `api_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`api_response`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_notes`
--

CREATE TABLE `booking_notes` (
  `id` int(11) NOT NULL,
  `booking_ref` varchar(20) NOT NULL,
  `note_content` text DEFAULT NULL,
  `flight_no_query` tinyint(1) DEFAULT 0,
  `wrong_resort` tinyint(1) DEFAULT 0,
  `mandatory_child_seat` tinyint(1) DEFAULT 0,
  `missing_accommodation` tinyint(1) DEFAULT 0,
  `missing_cruise_ship_name` tinyint(1) DEFAULT 0,
  `shuttle_to_private_address` tinyint(1) DEFAULT 0,
  `no_show_arrival` tinyint(1) DEFAULT 0,
  `no_show_departure` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `preferred_contact_method` enum('VOICE','SMS','VIBER','WHATSAPP') NOT NULL,
  `contact_methods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`contact_methods`)),
  `license_number` varchar(50) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_location_logs`
--

CREATE TABLE `driver_location_logs` (
  `id` int(11) NOT NULL,
  `token_id` int(11) NOT NULL,
  `booking_ref` varchar(50) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `accuracy` decimal(10,2) DEFAULT NULL,
  `tracking_status` enum('BEFORE_PICKUP','WAITING_FOR_CUSTOMER','AFTER_PICKUP','COMPLETED','NO_SHOW') NOT NULL,
  `synced_to_holidaytaxis` tinyint(1) DEFAULT 0,
  `sync_response` text DEFAULT NULL,
  `sync_http_code` int(11) DEFAULT NULL,
  `sync_error` text DEFAULT NULL,
  `tracked_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_tracking_tokens`
--

CREATE TABLE `driver_tracking_tokens` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `short_token` varchar(10) DEFAULT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `reassignment_history`
--

CREATE TABLE `reassignment_history` (
  `id` int(11) NOT NULL,
  `booking_ref` varchar(50) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `old_driver_id` int(11) DEFAULT NULL,
  `old_vehicle_id` int(11) DEFAULT NULL,
  `old_vehicle_identifier` varchar(50) DEFAULT NULL,
  `new_driver_id` int(11) DEFAULT NULL,
  `new_vehicle_id` int(11) DEFAULT NULL,
  `new_vehicle_identifier` varchar(50) DEFAULT NULL,
  `reason` enum('vehicle_breakdown','driver_unavailable','customer_request','other') DEFAULT 'other',
  `reason_notes` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `was_in_progress` tinyint(1) DEFAULT 0,
  `had_location_tracking` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_users`
--

CREATE TABLE `staff_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','staff','viewer') DEFAULT 'staff',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sync_status`
--

CREATE TABLE `sync_status` (
  `id` int(11) NOT NULL,
  `sync_type` enum('manual','auto') DEFAULT 'auto',
  `date_from` datetime NOT NULL,
  `date_to` datetime NOT NULL,
  `total_found` int(11) DEFAULT 0,
  `total_new` int(11) DEFAULT 0,
  `total_updated` int(11) DEFAULT 0,
  `status` enum('running','completed','failed') DEFAULT 'running',
  `error_message` text DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `registration` varchar(20) NOT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `default_driver_id` int(11) DEFAULT NULL,
  `status` enum('active','maintenance','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_booking_ref` (`booking_ref`),
  ADD KEY `idx_ht_status` (`ht_status`),
  ADD KEY `idx_last_action` (`last_action_date`),
  ADD KEY `idx_pickup_date` (`pickup_date`),
  ADD KEY `idx_synced_at` (`synced_at`),
  ADD KEY `idx_notes` (`notes`(100)),
  ADD KEY `idx_province` (`province`),
  ADD KEY `idx_transfer_date` (`transfer_date`),
  ADD KEY `idx_booking_type` (`booking_type`);

--
-- Indexes for table `booking_actions`
--
ALTER TABLE `booking_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_ref` (`booking_ref`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `booking_notes`
--
ALTER TABLE `booking_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_ref` (`booking_ref`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_phone` (`phone_number`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `driver_location_logs`
--
ALTER TABLE `driver_location_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token_id` (`token_id`),
  ADD KEY `idx_booking_ref` (`booking_ref`),
  ADD KEY `idx_tracked_at` (`tracked_at`),
  ADD KEY `idx_synced` (`synced_to_holidaytaxis`);

--
-- Indexes for table `driver_tracking_tokens`
--
ALTER TABLE `driver_tracking_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD UNIQUE KEY `idx_short_token` (`short_token`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_booking_ref` (`booking_ref`),
  ADD KEY `idx_status` (`status`);

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
-- Indexes for table `reassignment_history`
--
ALTER TABLE `reassignment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `old_driver_id` (`old_driver_id`),
  ADD KEY `new_driver_id` (`new_driver_id`),
  ADD KEY `old_vehicle_id` (`old_vehicle_id`),
  ADD KEY `new_vehicle_id` (`new_vehicle_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_booking_ref` (`booking_ref`),
  ADD KEY `idx_assignment_id` (`assignment_id`);

--
-- Indexes for table `staff_users`
--
ALTER TABLE `staff_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `sync_status`
--
ALTER TABLE `sync_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_started_at` (`started_at`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`registration`),
  ADD KEY `idx_registration` (`registration`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `default_driver_id` (`default_driver_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_actions`
--
ALTER TABLE `booking_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_notes`
--
ALTER TABLE `booking_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `driver_location_logs`
--
ALTER TABLE `driver_location_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `driver_tracking_tokens`
--
ALTER TABLE `driver_tracking_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `driver_vehicle_assignments`
--
ALTER TABLE `driver_vehicle_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reassignment_history`
--
ALTER TABLE `reassignment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_users`
--
ALTER TABLE `staff_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sync_status`
--
ALTER TABLE `sync_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_actions`
--
ALTER TABLE `booking_actions`
  ADD CONSTRAINT `booking_actions_ibfk_1` FOREIGN KEY (`action_by`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_notes`
--
ALTER TABLE `booking_notes`
  ADD CONSTRAINT `booking_notes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `driver_location_logs`
--
ALTER TABLE `driver_location_logs`
  ADD CONSTRAINT `driver_location_logs_ibfk_1` FOREIGN KEY (`token_id`) REFERENCES `driver_tracking_tokens` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `driver_tracking_tokens`
--
ALTER TABLE `driver_tracking_tokens`
  ADD CONSTRAINT `driver_tracking_tokens_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `driver_vehicle_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `driver_tracking_tokens_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `driver_tracking_tokens_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `driver_vehicle_assignments`
--
ALTER TABLE `driver_vehicle_assignments`
  ADD CONSTRAINT `driver_vehicle_assignments_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `driver_vehicle_assignments_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reassignment_history`
--
ALTER TABLE `reassignment_history`
  ADD CONSTRAINT `reassignment_history_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `driver_vehicle_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reassignment_history_ibfk_2` FOREIGN KEY (`old_driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reassignment_history_ibfk_3` FOREIGN KEY (`new_driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reassignment_history_ibfk_4` FOREIGN KEY (`old_vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reassignment_history_ibfk_5` FOREIGN KEY (`new_vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reassignment_history_ibfk_6` FOREIGN KEY (`changed_by`) REFERENCES `staff_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`default_driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
