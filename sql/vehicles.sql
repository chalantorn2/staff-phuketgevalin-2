-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 18, 2025 at 05:26 PM
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
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `registration`, `brand`, `model`, `color`, `description`, `default_driver_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'กข-1234', 'Toyota', 'Commuter', 'ขาว', 'รถตู้ 12 ที่นั่ง สภาพดี', 1, 'active', '2025-09-30 04:20:48', '2025-09-30 04:20:48'),
(2, 'กค-5678', 'Honda', 'Civic', 'เทา', 'รถเก๋ง 4 ที่นั่ง', 2, 'active', '2025-09-30 04:20:48', '2025-09-30 04:20:48'),
(3, 'ABC-9012', 'Isuzu', 'D-Max', 'ดำ', 'กระบะ 4 ประตู', 3, 'active', '2025-09-30 04:20:48', '2025-09-30 04:20:48'),
(4, 'กง-3456', 'Mazda', 'CX-5', 'แดง', 'SUV 7 ที่นั่ง', 4, 'maintenance', '2025-09-30 04:20:48', '2025-09-30 04:20:48'),
(5, 'XYZ-7890', 'Toyota', 'Vios', 'เงิน', 'รถเก๋งประหยัด', 8, 'active', '2025-09-30 04:20:48', '2025-10-01 18:37:19');

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`default_driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
