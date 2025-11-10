-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 18, 2025 at 05:25 PM
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

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `phone_number`, `preferred_contact_method`, `contact_methods`, `license_number`, `username`, `password`, `status`, `created_at`, `updated_at`) VALUES
(1, 'สมชาย ใจดี', '+66812345678', 'VOICE', '[\"VOICE\",\"SMS\",\"WHATSAPP\"]', 'DL-123456', 'somchai', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-09-30 04:20:41', '2025-09-30 04:20:41'),
(2, 'สมศรี รักงาน', '+66823456789', 'WHATSAPP', '[\"WHATSAPP\",\"VOICE\"]', 'DL-234567', 'somsri', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-09-30 04:20:41', '2025-09-30 04:20:41'),
(3, 'นพดล เที่ยงตรง', '+66834567890', 'SMS', '[\"SMS\",\"VOICE\"]', 'DL-345678', 'nopadon', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-09-30 04:20:41', '2025-09-30 04:20:41'),
(4, 'ประยุทธ ขยัน', '+66845678901', 'VOICE', '[\"VOICE\",\"SMS\"]', 'DL-456789', 'prayut', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-09-30 04:20:41', '2025-09-30 04:20:41'),
(5, 'วิชัย มั่นคง', '+66856789012', 'WHATSAPP', '[\"WHATSAPP\",\"VOICE\",\"SMS\"]', 'DL-567890', 'vichai', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'inactive', '2025-09-30 04:20:41', '2025-09-30 04:20:41'),
(8, 'Test Driver', '+66999999999', 'SMS', '[\"SMS\",\"VOICE\"]', NULL, 'testdriver', '$2y$10$/2.XjCwLg8YDngSAjekDVexBBiJaofM25coOUWAI5qSMl4u52ZzF.', 'active', '2025-10-01 18:16:30', '2025-10-01 18:37:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_phone` (`phone_number`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
