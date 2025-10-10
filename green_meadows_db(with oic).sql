-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2025 at 06:42 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `green_meadows_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_lockouts`
--

CREATE TABLE `account_lockouts` (
  `lockout_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `locked_until` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unlocked_at` timestamp NULL DEFAULT NULL,
  `unlocked_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `Log_ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Activity_Type` varchar(255) NOT NULL,
  `Activity_Details` text NOT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`Log_ID`, `User_ID`, `Activity_Type`, `Activity_Details`, `Timestamp`) VALUES
(1, 1, 'User Creation', 'Created new user: Jemmanuel Que (Role: 1)', '2025-05-17 12:32:38'),
(2, 1, 'User Creation', 'Created new user: Jemmanuel Que (Role: 1)', '2025-05-17 12:53:45'),
(3, 1, 'User Archive', 'Archived user: Jefferson Que', '2025-05-18 10:42:38'),
(4, 1, 'User Creation', 'Created new user: Jemmanuel Que (Role: 2)', '2025-05-18 10:50:53'),
(5, 1, 'User Creation', 'Created new user: Jemmanuel Que (Role: 1)', '2025-05-18 11:00:56'),
(6, 1, 'User Creation', 'Created new user: Jemmanuel Que (Role: 1)', '2025-05-18 11:02:40'),
(7, 1, 'User Update', 'Updated user: Jemmanuel Que', '2025-05-18 11:15:41'),
(8, 1, 'User Archive', 'Archived user: Jemmanuel Que', '2025-05-18 11:47:29'),
(9, 1, 'User Archive', 'Archived user: Jemmanuel Que', '2025-05-18 11:47:34'),
(10, 1, 'User Archive', 'Archived user: Jemmanuel Que', '2025-05-18 11:56:13'),
(11, 1, 'User Recovery', 'Recovered user: Jemmanuel Que', '2025-05-18 15:00:06'),
(12, 1, 'User Archive', 'Archived user: Jemmanuel Que', '2025-05-18 15:00:22'),
(13, 1, 'User Recovery', 'Recovered user: Jemmanuel Que', '2025-05-18 15:05:08'),
(14, 8, 'User Update', 'Updated guard: Luhan Ming', '2025-05-27 10:12:14'),
(15, 8, 'User Update', 'Updated guard: Jasmine Claire Que', '2025-05-27 10:12:52'),
(16, 8, 'User Update', 'Updated guard: Joshua Beaver Que', '2025-05-27 10:13:01'),
(17, 8, 'User Update', 'Updated guard: Luhan Ming', '2025-05-27 11:01:39'),
(18, 8, 'User Update', 'Updated guard: Jasmine Claire Que', '2025-05-27 11:01:58'),
(19, 8, 'User Update', 'Updated guard: Joshua Beaver Que', '2025-05-27 11:02:12'),
(20, 8, 'Guard Recovery', 'Recovered guard: Julianna Laguna', '2025-05-27 11:03:18'),
(21, 8, 'Guard Recovery', 'Recovered guard: Dylan Wang', '2025-05-27 11:03:23'),
(22, 8, 'User Update', 'Updated guard: Dylan Wang', '2025-05-27 11:03:36'),
(23, 8, 'User Update', 'Updated guard: Julianna Laguna', '2025-05-31 07:57:01'),
(24, 8, 'User Update', 'Updated guard: Joshua Beaver Que', '2025-05-31 08:52:06'),
(25, 8, 'User Update', 'Updated guard: Joshua Beaver Que', '2025-05-31 08:52:34'),
(26, 8, 'User Archive', 'Archived guard: Joshua Beaver Que', '2025-05-31 08:52:46'),
(27, 8, 'Guard Recovery', 'Recovered guard: Joshua Beaver Que', '2025-05-31 08:53:50'),
(28, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Bulacan\' from ₱525.00 to ₱500.00', '2025-06-03 07:27:35'),
(29, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Bulacan\' from ₱500.00 to ₱525.00', '2025-06-03 07:27:43'),
(30, 8, 'User Update', 'Updated guard: Joshua Beaver Que', '2025-06-03 08:03:59'),
(31, 8, 'User Update', 'Updated guard: Luhan Ming', '2025-06-03 08:04:17'),
(32, 8, 'User Creation', 'Created new guard: James Obed Laguna (Location: Pampanga)', '2025-06-03 08:31:00'),
(33, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Naga\' from ₱0.00 to ₱415.00', '2025-06-03 08:39:17'),
(34, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Pampanga\' from ₱0.00 to ₱540.00', '2025-06-03 08:39:27'),
(35, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Pangasinan\' from ₱0.00 to ₱435.00', '2025-06-03 08:39:37'),
(36, 8, 'User Update', 'Updated guard: James Obed Laguna', '2025-06-03 08:40:27'),
(37, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Pampanga\' from ₱0.00 to ₱540.00', '2025-06-03 09:11:00'),
(38, 8, 'User Creation', 'Created new guard: Amelia de Sena (Location: Cavite)', '2025-06-03 09:24:44'),
(39, 8, 'Guard Update', 'Updated guard: Julianna Laguna', '2025-06-03 09:25:08'),
(40, 8, 'Guard Update', 'Updated guard: Jasmine Claire Que', '2025-06-03 09:25:22'),
(41, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Cavite\' from ₱0.00 to ₱540.00', '2025-06-03 10:55:48'),
(42, 8, 'Guard Update', 'Updated guard: Amelia de Sena', '2025-06-03 11:25:53'),
(43, 8, 'User Creation', 'Created new guard: Johnny Bravo (Location: Laguna)', '2025-06-03 11:33:01'),
(44, 8, 'Guard Update', 'Updated guard: Johnny Bravo', '2025-06-03 11:36:41'),
(45, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Laguna\' from ₱0.00 to ₱540.00', '2025-06-03 11:38:25'),
(46, 8, 'User Creation', 'Created new guard: Romeo Calamasa (Location: Biñan)', '2025-06-03 11:40:49'),
(47, 8, 'Guard Update', 'Updated guard: Romeo Calamasa', '2025-06-03 11:45:43'),
(48, 8, 'Guard Update', 'Updated guard: Romeo Calamasa', '2025-06-03 11:46:35'),
(49, 8, 'User Creation', 'Created new guard: Bernard de Sena (Location: Batangas)', '2025-06-03 11:47:19'),
(50, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Biñan\' from ₱0.00 to ₱540.00', '2025-06-03 11:48:30'),
(51, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Batangas\' from ₱0.00 to ₱540.00', '2025-06-03 11:48:40'),
(52, 8, 'Guard Update', 'Updated guard: Bernard de Sena', '2025-06-03 11:48:55'),
(53, 8, 'User Creation', 'Created new guard: Angelita Baldovino (Location: Batangas)', '2025-06-03 11:53:05'),
(54, 8, 'User Archive', 'Archived guard: Angelita Baldovino', '2025-06-03 12:01:10'),
(55, 8, 'Guard Deletion', 'Permanently deleted guard: Angelita Baldovino', '2025-06-03 12:06:16'),
(56, 8, 'User Creation', 'Created new guard: Angelita Baldovino (Location: Batangas)', '2025-06-04 09:45:16'),
(57, 8, 'Guard Update', 'Updated guard: Angelita Baldovino', '2025-06-04 09:45:46'),
(58, 8, 'User Archive', 'Archived guard: Angelita Baldovino', '2025-06-04 09:46:27'),
(59, 8, 'Guard Recovery', 'Recovered guard: Angelita Baldovino', '2025-06-04 09:46:35'),
(60, 8, 'Guard Update', 'Updated guard information for Bernard de Sena (ID: 52)', '2025-06-04 10:04:51'),
(61, 8, 'User Archive', 'Archived guard (ID: 52) with reason: basta', '2025-06-04 10:10:11'),
(62, 8, 'Guard Recovery', 'Recovered guard: Bernard de Sena', '2025-06-04 10:17:28'),
(63, 8, 'User Archive', 'Archived guard: Bernard de Sena with reason: test', '2025-06-04 10:17:49'),
(64, 8, 'Guard Recovery', 'Recovered guard: Bernard de Sena', '2025-06-04 10:18:12'),
(65, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Batangas\' from ₱0.00 to ₱540.00', '2025-06-04 10:31:15'),
(66, 8, 'User Creation', 'Created new guard: Test Test (Location: Batangas)', '2025-06-04 10:32:00'),
(67, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Batangas\' from ₱360.00 to ₱550.00', '2025-06-04 11:18:55'),
(68, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Batangas\' from ₱550.00 to ₱540.00', '2025-06-04 11:19:04'),
(69, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Batangas\' from ₱540.00 to ₱550.00', '2025-06-04 11:20:22'),
(70, 6, 'Rate Update', 'Jemmanuel Cyril Que updated daily rate for location \'Batangas\' from ₱550.00 to ₱540.00', '2025-06-04 11:20:47'),
(71, 6, 'Holiday Management', 'Added new holiday: Test (2025-06-20)', '2025-06-20 09:27:31'),
(72, 6, 'Holiday System', 'Auto-populated Philippine holidays for 2026', '2025-06-20 09:38:52'),
(73, 6, 'Holiday Management', 'Added new holiday: Test 2 (2025-06-21)', '2025-06-20 09:40:31'),
(74, 6, 'Holiday Management', 'Deleted holiday: Test 2 (2025-06-21)', '2025-06-20 09:54:17'),
(75, 6, 'Holiday Management', 'Deleted holiday: Test (2025-06-20)', '2025-06-20 09:54:21'),
(76, 6, 'Holiday Management', 'Added new holiday: Test (2025-06-20)', '2025-06-20 10:06:51'),
(77, 6, 'Holiday Management', 'Deleted holiday: Test', '2025-06-20 10:06:58'),
(78, 6, 'Holiday System', 'Auto-populated Philippine holidays for 2024', '2025-06-20 10:19:20'),
(79, 6, 'Holiday Management', 'Added new holiday: test (2025-12-26)', '2025-06-20 10:20:25'),
(80, 6, 'Holiday Management', 'Deleted holiday: test', '2025-06-20 10:20:31'),
(81, 6, 'Attendance Archive', 'Archived attendance record ID 40 for Dylan Wang - Reason: redundant', '2025-06-23 09:26:17'),
(82, 6, 'Attendance Archive', 'Archived attendance record ID 41 for Dylan Wang - Reason: redundant\n', '2025-06-23 09:26:38'),
(83, 6, 'Attendance Edit', 'Edited attendance record ID 44 - Reason: just some lapses', '2025-06-23 09:27:54'),
(84, 6, 'Attendance Delete Permanent', 'Permanently deleted attendance record ID 38 for Dylan Wang - Reason: Not needed', '2025-06-23 12:04:13'),
(85, 6, 'Holiday Management', 'Added new holiday: test (2025-07-04)', '2025-07-03 20:25:57'),
(86, 6, 'Holiday Management', 'Deleted holiday: test', '2025-07-03 20:26:01'),
(87, 1, 'Login', 'User logged in from IP: ::1', '2025-07-04 00:37:23'),
(88, 1, 'Login', 'User logged in from IP: ::1', '2025-07-04 00:43:51'),
(89, 8, 'Login', 'User logged in from IP: ::1', '2025-07-04 00:45:06'),
(90, 1, 'Login', 'User logged in from IP: ::1', '2025-07-04 00:45:46'),
(91, 1, 'Logout', 'User logged out from IP: ::1', '2025-07-04 00:45:48'),
(92, 1, 'Login', 'User logged in from IP: ::1', '2025-07-04 00:47:10'),
(93, 8, 'Login', 'User logged in from IP: ::1', '2025-07-04 00:47:22'),
(94, 8, 'Logout', 'User logged out from IP: ::1', '2025-07-04 01:05:30'),
(95, 1, 'Login', 'User logged in from IP: ::1', '2025-07-04 01:13:17'),
(96, 1, 'Logout', 'User logged out from IP: ::1', '2025-07-04 01:14:07'),
(97, 10, 'Login', 'User logged in from IP: ::1', '2025-07-08 09:45:26'),
(98, 10, 'Logout', 'User logged out from IP: ::1', '2025-07-08 09:45:38'),
(99, 2, 'Login', 'User logged in from IP: ::1', '2025-07-11 05:11:56'),
(100, 2, 'Logout', 'User logged out from IP: ::1', '2025-07-11 05:12:17'),
(101, 55, 'Password Reset', 'Password reset successfully via email verification', '2025-07-15 11:09:06'),
(102, 55, 'Password Reset', 'Password reset successfully via email verification', '2025-07-15 11:15:31'),
(103, 55, 'Login', 'User logged in from IP: ::1', '2025-07-15 11:24:55'),
(104, 55, 'Logout', 'User logged out from IP: ::1', '2025-07-15 11:26:39'),
(105, 2, 'Login', 'User logged in from IP: ::1', '2025-07-15 11:27:03'),
(106, 6, 'Login', 'User logged in from IP: ::1', '2025-07-15 11:29:11'),
(109, 38, 'Login', 'User logged in from IP: ::1', '2025-07-15 17:15:01'),
(110, 38, 'Logout', 'User logged out from IP: ::1', '2025-07-15 17:15:29'),
(111, 37, 'Login', 'User logged in from IP: ::1', '2025-07-15 17:15:34'),
(112, 37, 'Logout', 'User logged out from IP: ::1', '2025-07-15 17:16:07'),
(113, 3, 'Login', 'User logged in from IP: ::1', '2025-07-15 17:16:11'),
(114, 6, 'Holiday Management', 'Added new holiday: Regular Holiday (2025-07-16)', '2025-07-15 17:22:30'),
(115, 3, 'Logout', 'User logged out from IP: ::1', '2025-07-15 17:22:49'),
(116, 2, 'Login', 'User logged in from IP: ::1', '2025-07-15 17:22:53'),
(118, 2, 'Logout', 'User logged out from IP: ::1', '2025-07-15 18:54:43'),
(119, 8, 'Login', 'User logged in from IP: ::1', '2025-07-15 18:54:49'),
(120, 8, 'User Creation', 'Created new guard: Brian Lawrence Dumayas (Location: Manila)', '2025-07-15 18:59:42'),
(121, 8, 'Guard Update', 'Updated guard information for Brian Lawrence Dumayas (ID: 56)', '2025-07-15 19:00:12'),
(122, 8, 'Logout', 'User logged out from IP: ::1', '2025-07-15 19:00:40'),
(123, 56, 'Password Reset', 'Password reset successfully via email verification', '2025-07-15 19:01:54'),
(124, 56, 'Login', 'User logged in from IP: ::1', '2025-07-15 19:02:09'),
(126, 35, 'Login', 'User logged in from IP: ::1', '2025-07-15 19:23:19'),
(127, 35, 'Guard Update', 'Updated guard information for Angelita Baldovino (ID: 54)', '2025-07-15 19:23:44'),
(128, 35, 'Guard Update', 'Updated guard information for Bernard de Sena (ID: 52)', '2025-07-15 19:23:54'),
(129, 35, 'Guard Update', 'Updated guard information for Test Test (ID: 55)', '2025-07-15 19:24:01'),
(130, 35, 'Guard Update', 'Updated guard information for Romeo Calamasa (ID: 51)', '2025-07-15 19:24:09'),
(131, 6, 'Login', 'User logged in from IP: 192.168.1.4', '2025-07-15 20:11:43'),
(132, 6, 'Login', 'User logged in from IP: ::1', '2025-07-15 20:32:45'),
(133, 56, 'Login', 'User logged in from IP: ::1', '2025-07-15 20:38:05'),
(134, 56, 'Logout', 'User logged out from IP: ::1', '2025-07-15 20:38:49'),
(135, 1, 'Login', 'User logged in from IP: ::1', '2025-07-15 20:39:01'),
(136, 6, 'Attendance Archive', 'Archived attendance record ID 42 for Dylan Wang - Reason: redundant data\n', '2025-07-15 20:42:09'),
(137, 35, 'User Creation', 'Created new guard: Rafael Castro (Location: San Pedro Laguna)', '2025-07-15 21:15:02'),
(138, 6, 'Attendance Add', 'Jemmanuel Cyril Que added attendance record for Angelita Malabana Baldovino - Date: July 6, 2025 - Time: 6:00 AM to 6:00 PM - Reason: manual entry', '2025-07-15 21:23:58'),
(139, 6, 'Attendance Add', 'Jemmanuel Cyril Que added attendance record for Dylan de Sena Wang - Date: May 4, 2025 to May 5, 2025 - Time: 6:00 PM to 6:00 AM - Reason: manual entry, just trying the payroll computing', '2025-07-15 21:43:39'),
(140, 1, 'Logout', 'User logged out from IP: ::1', '2025-07-17 17:32:02'),
(141, 6, 'Login', 'User logged in from IP: ::1', '2025-07-17 17:32:09'),
(142, 6, 'Logout', 'User logged out from IP: ::1', '2025-07-17 20:55:41'),
(143, 6, 'Login', 'User logged in from IP: ::1', '2025-07-17 20:55:49'),
(144, 6, 'Logout', 'User logged out from IP: ::1', '2025-07-17 20:55:52'),
(145, 8, 'Login', 'User logged in from IP: ::1', '2025-07-17 20:56:16'),
(146, 8, 'Login', 'User logged in from IP: ::1', '2025-07-19 10:13:06'),
(147, 8, 'Login', 'User logged in from IP: ::1', '2025-07-22 18:47:39'),
(148, 8, 'Logout', 'User logged out from IP: ::1', '2025-07-22 19:34:35'),
(149, 6, 'Login', 'User logged in from IP: ::1', '2025-07-22 19:34:54'),
(150, 6, 'Rate Update', 'Maricel Valdez updated daily rate for location \'Manila\' from ₱645.00 to ₱695.00 - Reason: Rate in NCR got higher', '2025-07-22 20:15:41'),
(151, 6, 'Logout', 'User logged out from IP: ::1', '2025-07-22 20:19:48'),
(152, 2, 'Login', 'User logged in from IP: ::1', '2025-07-22 20:20:52'),
(153, 2, 'Logout', 'User logged out from IP: ::1', '2025-07-22 20:21:59'),
(154, 6, 'Login', 'User logged in from IP: ::1', '2025-07-22 20:25:09'),
(155, 6, 'Login', 'User logged in from IP: ::1', '2025-07-24 14:40:51'),
(156, 1, 'Login', 'User logged in from IP: ::1', '2025-07-29 17:33:44'),
(157, 1, 'User Update', 'Updated user: Jenny Dela Cruz', '2025-07-29 17:36:36'),
(158, 1, 'Logout', 'User logged out from IP: ::1', '2025-07-29 17:38:49'),
(159, 56, 'Login', 'User logged in from IP: ::1', '2025-07-29 17:39:00'),
(160, 56, 'Logout', 'User logged out from IP: ::1', '2025-07-29 17:41:50'),
(161, 8, 'Login', 'User logged in from IP: ::1', '2025-07-29 17:41:58'),
(162, 8, 'Logout', 'User logged out from IP: ::1', '2025-07-29 17:47:15'),
(163, 1, 'Login', 'User logged in from IP: ::1', '2025-07-29 17:49:00'),
(164, 6, 'Login', 'User logged in from IP: ::1', '2025-07-29 17:51:43'),
(165, 6, 'Logout', 'User logged out from IP: ::1', '2025-07-29 18:43:39'),
(166, 8, 'Login', 'User logged in from IP: ::1', '2025-07-29 18:43:49'),
(167, 1, 'Logout', 'User logged out from IP: ::1', '2025-07-29 18:53:45'),
(168, 6, 'Login', 'User logged in from IP: ::1', '2025-07-29 18:56:53'),
(169, 6, 'Holiday Management', 'Added new holiday: test (2025-07-30)', '2025-07-29 19:00:40'),
(170, 6, 'Holiday Management', 'Deleted holiday: test', '2025-07-29 19:01:09'),
(171, 6, 'Logout', 'User logged out from IP: ::1', '2025-07-29 19:29:36'),
(172, 6, 'Login', 'User logged in from IP: ::1', '2025-07-29 19:47:47'),
(173, 2, 'Login', 'User logged in from IP: 127.0.0.1', '2025-07-29 20:14:14'),
(174, 8, 'Guard Update', 'Updated guard information for Ricardo Natividad (ID: 56)', '2025-07-29 20:52:08'),
(175, 6, 'Login', 'User logged in from IP: ::1', '2025-08-02 14:12:04'),
(176, 6, 'Attendance Add', 'Maricel Valdez added attendance record for Allan Roque Basilio - Date: July 16, 2025 - Time: 6:00 AM to 6:05 PM - Reason: test', '2025-08-02 14:15:06'),
(177, 6, 'Holiday Management', 'Deleted holiday: Regular Holiday', '2025-08-02 14:16:36'),
(178, 6, 'Attendance Add', 'Maricel Valdez added attendance record for Allan Roque Basilio - Date: July 17, 2025 - Time: 5:45 AM to 6:45 PM - Reason: test', '2025-08-02 14:21:01'),
(179, 6, 'Attendance Add', 'Maricel Valdez added attendance record for Allan Roque Basilio - Date: July 18, 2025 - Time: 7:00 AM to 7:00 PM - Reason: test\n', '2025-08-02 14:26:05'),
(180, 6, 'Attendance Add', 'Maricel Valdez added attendance record for Allan Roque Basilio - Date: July 21, 2025 to July 22, 2025 - Time: 6:00 PM to 6:05 AM - Reason: test\n', '2025-08-02 14:38:51'),
(181, 6, 'Attendance Add', 'Maricel Valdez added attendance record for Allan Roque Basilio - Date: July 22, 2025 to July 23, 2025 - Time: 6:05 PM to 6:00 AM - Reason: test', '2025-08-02 14:40:13'),
(182, 6, 'Attendance Add', 'Maricel Valdez added attendance record for Allan Roque Basilio - Date: July 23, 2025 - Time: 7:00 AM to 6:00 PM - Reason: test\n', '2025-08-02 14:40:57'),
(183, 6, 'Attendance Add', 'Maricel Valdez added attendance record for Allan Roque Basilio - Date: July 24, 2025 to July 25, 2025 - Time: 6:00 PM to 6:00 AM - Reason: test', '2025-08-02 14:44:17'),
(184, 6, 'Holiday Management', 'Added new holiday: Regular holiday (2025-07-23)', '2025-08-02 15:02:47'),
(185, 6, 'Attendance Archive', 'Archived attendance record ID 63 for Allan Basilio - Reason: basta\n', '2025-08-02 16:34:25'),
(186, 6, 'Attendance Archive', 'Archived attendance record ID 64 for Allan Basilio - Reason: basta', '2025-08-02 16:34:30'),
(187, 6, 'Attendance Archive', 'Archived attendance record ID 65 for Allan Basilio - Reason: basta', '2025-08-02 16:34:36'),
(188, 6, 'Attendance Archive', 'Archived attendance record ID 66 for Allan Basilio - Reason: basta', '2025-08-02 16:34:40'),
(189, 6, 'Attendance Archive', 'Archived attendance record ID 67 for Allan Basilio - Reason: basta', '2025-08-02 16:34:44'),
(190, 6, 'Attendance Archive', 'Archived attendance record ID 62 for Allan Basilio - Reason: basta', '2025-08-02 16:37:06'),
(191, 6, 'Attendance Restore', 'Restored attendance record ID 62 for Allan Basilio - Reason: restore test', '2025-08-02 16:37:49'),
(192, 6, 'Attendance Restore', 'Restored attendance record ID 63 for Allan Basilio - Reason: yes', '2025-08-02 16:39:26'),
(193, 6, 'Attendance Restore', 'Restored attendance record ID 64 for Allan Basilio - Reason: yes', '2025-08-02 16:39:31'),
(194, 6, 'Attendance Restore', 'Restored attendance record ID 65 for Allan Basilio - Reason: yes', '2025-08-02 16:39:35'),
(195, 6, 'Attendance Restore', 'Restored attendance record ID 66 for Allan Basilio - Reason: yes', '2025-08-02 16:39:39'),
(196, 6, 'Attendance Restore', 'Restored attendance record ID 67 for Allan Basilio - Reason: yes', '2025-08-02 16:39:42'),
(197, 6, 'Attendance Archive', 'Archived attendance record ID 67 for Allan Basilio - Reason: yep', '2025-08-02 16:44:48'),
(198, 6, 'Attendance Archive', 'Archived attendance record ID 66 for Allan Basilio - Reason: yeet', '2025-08-02 16:44:52'),
(199, 6, 'Attendance Archive', 'Archived attendance record ID 65 for Allan Basilio - Reason: why not coconut', '2025-08-02 16:45:00'),
(200, 6, 'Attendance Archive', 'Archived attendance record ID 64 for Allan Basilio - Reason: k', '2025-08-02 16:45:05'),
(201, 6, 'Attendance Archive', 'Archived attendance record ID 63 for Allan Basilio - Reason: suii', '2025-08-02 16:45:12'),
(202, 6, 'Attendance Archive', 'Archived attendance record ID 62 for Allan Basilio - Reason: vasta', '2025-08-02 16:50:57'),
(203, 6, 'Attendance Restore', 'Restored attendance record ID 62 for Allan Basilio - Reason: yeet', '2025-08-02 16:51:08'),
(204, 6, 'Attendance Archive', 'Archived attendance record ID 68 for Allan Basilio - Reason: archive this', '2025-08-02 17:17:46'),
(205, 6, 'Attendance Restore', 'Restored attendance record ID 63 for Allan Basilio - Reason: yeah', '2025-08-02 17:39:24'),
(206, 6, 'Attendance Restore', 'Restored attendance record ID 64 for Allan Basilio - Reason: basta', '2025-08-02 18:26:03'),
(207, 6, 'Attendance Restore', 'Restored attendance record ID 65 for Allan Basilio - Reason: sure', '2025-08-02 18:27:22'),
(208, 6, 'Attendance Restore', 'Restored attendance record ID 66 for Allan Basilio - Reason: yes', '2025-08-02 19:21:44'),
(209, 2, 'Login', 'User logged in from IP: ::1', '2025-08-04 18:09:39'),
(210, 2, 'Logout', 'User logged out from IP: ::1', '2025-08-04 18:35:34'),
(211, 56, 'Login', 'User logged in from IP: ::1', '2025-08-04 18:35:42'),
(212, 56, 'Login', 'User logged in from IP: ::1', '2025-08-04 18:51:59'),
(213, 56, 'Logout', 'User logged out from IP: ::1', '2025-08-04 21:30:18'),
(214, 2, 'Login', 'User logged in from IP: ::1', '2025-08-04 21:30:43'),
(215, 2, 'Login', 'User logged in from IP: 192.168.1.4', '2025-08-04 21:52:15'),
(216, 2, 'Logout', 'User logged out from IP: ::1', '2025-08-04 22:19:16'),
(217, 56, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:19:24'),
(218, 56, 'Logout', 'User logged out from IP: ::1', '2025-08-04 22:21:57'),
(219, 2, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:22:02'),
(220, 2, 'Logout', 'User logged out from IP: ::1', '2025-08-04 22:28:30'),
(221, 2, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:28:37'),
(222, 2, 'Logout', 'User logged out from IP: ::1', '2025-08-04 22:28:50'),
(223, 56, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:28:55'),
(224, 56, 'Logout', 'User logged out from IP: ::1', '2025-08-04 22:30:48'),
(225, 37, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:30:53'),
(226, 37, 'Logout', 'User logged out from IP: ::1', '2025-08-04 22:32:27'),
(227, 3, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:33:23'),
(228, 37, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:37:13'),
(229, 37, 'Logout', 'User logged out from IP: ::1', '2025-08-04 22:37:51'),
(230, 3, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:37:55'),
(231, 3, 'Logout', 'User logged out from IP: ::1', '2025-08-04 22:38:15'),
(232, 56, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:38:28'),
(233, 51, 'Password Reset', 'Password reset successfully via email verification', '2025-08-04 22:42:23'),
(234, 51, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:42:35'),
(235, 51, 'Logout', 'User logged out from IP: ::1', '2025-08-04 22:43:42'),
(236, 51, 'Login', 'User logged in from IP: ::1', '2025-08-04 22:43:53'),
(237, 51, 'Logout', 'User logged out from IP: ::1', '2025-08-04 22:44:55'),
(238, 1, 'Login', 'User logged in from IP: ::1', '2025-08-11 11:50:31'),
(239, 6, 'Login', 'User logged in from IP: ::1', '2025-08-11 11:51:51'),
(240, 1, 'Logout', 'User logged out from IP: ::1', '2025-08-11 11:58:02'),
(241, 6, 'Login', 'User logged in from IP: ::1', '2025-08-11 11:58:14'),
(242, 6, 'Logout', 'User logged out from IP: ::1', '2025-08-11 12:03:09'),
(243, 2, 'Login', 'User logged in from IP: ::1', '2025-08-11 12:03:35'),
(244, 2, 'Logout', 'User logged out from IP: ::1', '2025-08-11 12:11:10'),
(245, 8, 'Login', 'User logged in from IP: ::1', '2025-08-11 12:11:17'),
(246, 8, 'User Archive', 'Archived guard: Leo Bautista with reason: test', '2025-08-11 12:15:49'),
(247, 8, 'Guard Recovery', 'Recovered guard: Leo Bautista', '2025-08-11 12:15:59'),
(248, 8, 'Logout', 'User logged out from IP: ::1', '2025-08-11 12:20:17'),
(249, 8, 'Login', 'User logged in from IP: ::1', '2025-08-11 12:25:16'),
(250, 8, 'Logout', 'User logged out from IP: ::1', '2025-08-11 12:25:47'),
(251, 1, 'Login', 'User logged in from IP: ::1', '2025-08-11 12:25:59'),
(252, 1, 'Logout', 'User logged out from IP: ::1', '2025-08-11 12:26:06'),
(253, 6, 'Login', 'User logged in from IP: ::1', '2025-08-11 13:07:13'),
(254, 6, 'Logout', 'User logged out from IP: ::1', '2025-08-11 13:08:26'),
(255, 1, 'Login', 'User logged in from IP: ::1', '2025-08-11 13:08:35'),
(256, 6, 'Login', 'User logged in from IP: ::1', '2025-08-11 13:08:59'),
(257, 1, 'Logout', 'User logged out from IP: ::1', '2025-08-13 14:38:11'),
(258, 8, 'Login', 'User logged in from IP: ::1', '2025-08-13 14:38:29'),
(259, 8, 'Auto Cleanup', 'Auto-deleted 3 applicant record(s) older than 1 week', '2025-08-16 16:19:13'),
(260, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:19:13'),
(261, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:19:35'),
(262, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:19:35'),
(263, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:19:53'),
(264, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:19:53'),
(265, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:19:56'),
(266, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:19:56'),
(267, 8, 'Manual Cleanup', 'Manually deleted 0 applicant record(s) older than 1 week', '2025-08-16 16:20:12'),
(268, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:30:20'),
(269, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:30:20'),
(270, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:30:30'),
(271, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:30:30'),
(272, 8, 'Leave Request Action', 'Accepted leave request for Dylan Wang from Jul 09, 2025 - Jul 10, 2025 with reason: approved by HR', '2025-08-16 16:42:47'),
(273, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:58:40'),
(274, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-16 16:58:40'),
(275, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-19 09:17:51'),
(276, 1, 'Employee Creation', 'Juan Dela Cruz created new employee: Test Employee (Role: Security Guard) (Location: Test Location)', '2025-08-19 15:45:38'),
(277, 8, 'Employee Creation', 'Erwin Mendoza created new employee: Test Employee (Role: Security Guard) (Location: Test Location)', '2025-08-19 15:54:03'),
(278, 8, 'Employee Creation', 'Erwin Mendoza created new employee: Test Employee (Role: Security Guard) (Location: Manila)', '2025-08-19 16:06:12'),
(279, 8, 'Employee Creation', 'Erwin Mendoza created new employee: Jasmine Aladdin (Role: Security Guard) (Location: Laguna)', '2025-08-19 16:56:49'),
(280, 8, 'Employee Creation', 'Erwin Mendoza created new employee: jasmine aladdin (Role: Security Guard) (Location: Laguna)', '2025-08-19 17:00:28'),
(281, 8, 'Employee Creation', 'Erwin Mendoza created new employee: Jasmine Aladdin (Role: Security Guard) (Location: Laguna)', '2025-08-19 17:06:20'),
(282, 63, 'Login', 'User logged in from IP: ::1', '2025-08-19 17:07:49'),
(283, 63, 'Password Reset', 'Password reset successfully via email verification', '2025-08-19 17:08:02'),
(284, 63, 'Logout', 'User logged out from IP: ::1', '2025-08-19 17:08:28'),
(285, 63, 'Login', 'User logged in from IP: ::1', '2025-08-19 17:08:34'),
(286, 63, 'Logout', 'User logged out from IP: ::1', '2025-08-19 17:08:39'),
(287, 63, 'Login', 'User logged in from IP: ::1', '2025-08-19 17:08:44'),
(288, 63, 'Logout', 'User logged out from IP: ::1', '2025-08-19 17:08:45'),
(289, 8, 'Employee Update', 'Erwin Mendoza updated employee: Biscuit Aladdin', '2025-08-19 17:49:08'),
(290, 8, 'Employee Archive', 'Erwin Mendoza archived employee: Biscuit Aladdin with reason: testing lang lods', '2025-08-19 17:54:55'),
(291, 8, 'Employee Archive', 'Erwin Mendoza archived employee: Maricel Valdez with reason: testing lang\r\n', '2025-08-19 17:59:21'),
(292, 8, 'User Recovery', 'Recovered user: Maricel Valdez', '2025-08-19 18:06:24'),
(293, 8, 'Guard Recovery', 'Recovered user: Biscuit Aladdin', '2025-08-19 18:09:05'),
(294, 6, 'Login', 'User logged in from IP: ::1', '2025-08-19 18:20:05'),
(295, 8, 'Employee Creation', 'Erwin Mendoza created new employee: Testing1 Testing1 (Role: Security Guard) (Location: Biñan)', '2025-08-19 18:32:06'),
(296, 64, 'Password Reset', 'Password reset successfully via email verification', '2025-08-19 18:34:14'),
(297, 64, 'Login', 'User logged in from IP: ::1', '2025-08-19 18:34:29'),
(298, 8, 'Employee Update', 'Erwin Mendoza updated employee: Testing1 Testing1', '2025-08-19 18:37:27'),
(299, 8, 'Employee Update', 'Erwin Mendoza updated employee: Biñan Laguna', '2025-08-19 18:38:08'),
(300, 8, 'Employee Update', 'Erwin Mendoza updated employee: Biñan Laguna', '2025-08-19 18:39:46'),
(301, 8, 'Employee Update', 'Erwin Mendoza updated employee: Biñan Laguna', '2025-08-19 18:40:13'),
(302, 8, 'Login', 'User logged in from IP: ::1', '2025-08-19 18:58:56'),
(303, 8, 'Attendance Add', 'Erwin Mendoza added attendance record for Biscuit Aladdin - Date: August 23, 2025 to August 24, 2025 - Time: 6:00 PM to 6:01 AM - Reason: testing lang\n', '2025-08-22 17:39:30'),
(304, 8, 'Attendance Add', 'Erwin Mendoza added attendance record for Biscuit Aladdin - Date: August 22, 2025 to August 23, 2025 - Time: 6:00 PM to 6:01 AM - Reason: testing lang\n', '2025-08-22 17:40:31'),
(305, 8, 'Attendance Archive', 'Erwin Mendoza archived attendance of Biscuit Aladdin - Date: August 23, 2025 to August 24, 2025 - Time: 6:00 PM to 6:01 AM - Reason: test', '2025-08-22 18:05:25'),
(306, 6, 'Login', 'User logged in from IP: ::1', '2025-08-22 18:08:45'),
(307, 8, 'Login', 'User logged in from IP: ::1', '2025-08-22 18:16:11'),
(308, 8, 'Attendance Recovery', 'Erwin Mendoza restored attendance of Biscuit Aladdin - Date: August 23, 2025 to August 24, 2025 - Time: 6:00 PM to 6:01 AM', '2025-08-22 18:18:03'),
(309, 8, 'Attendance Delete Permanent', 'Permanently deleted archived attendance record ID 67 for Allan Basilio', '2025-08-22 18:18:13'),
(310, 8, 'Attendance Delete Permanent', 'Erwin Mendoza permanently deleted Allan Basilio\'s attendance record on July 17, 2025', '2025-08-22 19:14:39'),
(311, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-22 19:25:14'),
(312, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-22 19:25:14'),
(313, 6, 'Logout', 'User logged out from IP: ::1', '2025-08-22 20:45:28'),
(314, 2, 'Login', 'User logged in from IP: ::1', '2025-08-22 20:45:41'),
(315, 8, 'Performance Evaluation', 'Completed performance evaluation for User ID: 37 (Grace Villanueva Samson) with overall rating: 87.35%', '2025-08-22 21:08:06'),
(316, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-26 10:39:34'),
(317, 8, 'Login', 'User logged in from IP: ::1', '2025-08-26 10:40:34'),
(318, 8, 'Employee Creation', 'Erwin Mendoza created new employee: Belle Mariano (Role: Security Guard) (Location: Laguna)', '2025-08-26 11:58:29'),
(319, 65, 'Login', 'User logged in from IP: ::1', '2025-08-26 12:03:10'),
(320, 65, 'Logout', 'User logged out from IP: ::1', '2025-08-26 12:05:04'),
(321, 65, 'Password Reset', 'Password reset successfully via email verification', '2025-08-26 12:06:02'),
(322, 8, 'Employee Creation', 'Erwin Mendoza created new employee: Suyou Johnson (Role: Security Guard) (Location: Laguna)', '2025-08-26 12:13:17'),
(323, 8, 'Employee Update', 'Erwin Mendoza updated employee: Suyou Johnson', '2025-08-26 12:16:00'),
(324, 8, 'Employee Update', 'Erwin Mendoza updated employee: Suyou Johnson', '2025-08-26 12:22:24'),
(325, 8, 'Employee Creation', 'Erwin Mendoza created new employee: Cici Aurora (Role: Security Guard) (Location: Pampanga)', '2025-08-26 12:25:44'),
(326, 67, 'Login', 'User logged in from IP: ::1', '2025-08-26 12:26:25'),
(327, 67, 'Password Reset', 'Password reset successfully via email verification', '2025-08-26 12:27:24'),
(328, 67, 'Logout', 'User logged out from IP: ::1', '2025-08-26 12:32:18'),
(329, 8, 'Logout', 'User logged out from IP: ::1', '2025-08-26 15:53:41'),
(330, 1, 'Login', 'User logged in from IP: ::1', '2025-08-26 15:54:06'),
(331, 8, 'Login', 'User logged in from IP: ::1', '2025-08-29 12:27:28'),
(332, 8, 'Employee Update', 'Erwin Mendoza updated employee: Biscuit Aladdin', '2025-08-29 13:06:47'),
(333, 8, 'Employee Update', 'Erwin Mendoza updated employee: Biscuit Aladdin', '2025-08-29 13:07:07'),
(334, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-08-29 13:08:07'),
(335, 8, 'Employee Update', 'Erwin Mendoza updated employee: Biscuit Aladdin', '2025-08-29 13:13:29'),
(336, 8, 'Employee Update', 'Erwin Mendoza updated employee: Biscuit Aladdin', '2025-08-29 13:13:37'),
(337, 8, 'Password Management', 'Password changed via profile page', '2025-08-29 13:48:18'),
(338, 8, 'Logout', 'User logged out from IP: ::1', '2025-08-29 13:48:28'),
(339, 8, 'Login', 'User logged in from IP: ::1', '2025-08-29 13:48:38'),
(340, 8, 'Password Management', 'Password changed via profile page', '2025-08-29 13:57:51'),
(341, 8, 'Password Management', 'Password changed via profile page', '2025-08-29 14:06:28'),
(342, 8, 'Logout', 'User logged out from IP: ::1', '2025-08-29 14:06:36'),
(343, 8, 'Login', 'User logged in from IP: ::1', '2025-08-29 14:06:42'),
(344, 8, 'Password Management', 'Password changed via profile page', '2025-08-29 14:09:44'),
(345, 1, 'Login', 'User logged in from IP: ::1', '2025-08-29 14:12:03'),
(346, 8, 'Employee Update', 'Erwin Mendoza updated employee: Biscuit Aladdin', '2025-08-29 18:13:02'),
(347, 1, 'Employee Update', 'Juan Dela Cruz updated employee: Maricel Valdez', '2025-08-29 18:34:51'),
(348, 1, 'Login', 'User logged in from IP: ::1', '2025-09-02 14:02:58'),
(349, 8, 'Login', 'User logged in from IP: ::1', '2025-09-02 14:18:47'),
(350, 1, 'Login', 'User logged in from IP: ::1', '2025-09-03 14:28:55'),
(351, 1, 'Auto Cleanup', 'Auto-deleted 1 applicant record(s) older than 1 week', '2025-09-03 14:29:27'),
(352, 1, 'Attendance Recovery', 'Juan Dela Cruz restored attendance of Dylan Wang - Date: May 5, 2025 - Time: 2:22 PM to no time out', '2025-09-03 14:40:44'),
(353, 8, 'Login', 'User logged in from IP: ::1', '2025-09-03 14:41:32'),
(354, 8, 'Attendance Archive', 'Erwin Mendoza archived attendance of Dylan Wang - Date: May 5, 2025 - Time: 2:22 PM to no time out - Reason: not needed\n', '2025-09-03 14:41:49'),
(355, 1, 'Attendance Delete Permanent', 'Juan Dela Cruz permanently deleted Dylan Wang\'s attendance record on May 5, 2025', '2025-09-03 14:41:57'),
(356, 1, 'Login', 'User logged in from IP: ::1', '2025-09-03 14:43:32'),
(357, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-03 16:01:36'),
(358, 1, 'Login', 'User logged in from IP: ::1', '2025-09-03 16:08:42'),
(359, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-03 16:08:59'),
(360, 1, 'Login', 'User logged in from IP: ::1', '2025-09-03 16:10:17'),
(361, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-03 16:10:31'),
(362, 1, 'Login', 'User logged in from IP: ::1', '2025-09-03 16:25:25'),
(363, 1, 'Login', 'User logged in from IP: 192.168.68.107', '2025-09-03 16:26:27'),
(364, 1, 'Login', 'User logged in from IP: ::1', '2025-09-03 16:27:55'),
(365, 1, 'Login', 'User logged in from IP: 192.168.68.107', '2025-09-03 16:34:51'),
(366, 56, 'Login', 'User logged in from IP: ::1', '2025-09-03 17:21:00'),
(367, 56, 'Logout', 'User logged out from IP: ::1', '2025-09-03 17:21:41'),
(368, 2, 'Login', 'User logged in from IP: ::1', '2025-09-03 17:21:47'),
(369, 2, 'Logout', 'User logged out from IP: ::1', '2025-09-03 18:33:42'),
(370, 37, 'Login', 'User logged in from IP: ::1', '2025-09-03 18:33:48'),
(371, 8, 'Login', 'User logged in from IP: ::1', '2025-09-04 15:40:44'),
(372, 8, 'Employee Update', 'Erwin Mendoza updated employee: Maricel Valdez', '2025-09-04 15:40:57'),
(373, 8, 'Logout', 'User logged out from IP: ::1', '2025-09-04 15:41:00'),
(374, 6, 'Login', 'User logged in from IP: ::1', '2025-09-04 15:41:05'),
(375, 6, 'Login', 'User logged in from IP: ::1', '2025-09-05 11:46:32'),
(376, 2, 'Login', 'User logged in from IP: ::1', '2025-09-05 12:16:38'),
(377, 2, 'Logout', 'User logged out from IP: ::1', '2025-09-05 13:04:58'),
(378, 65, 'Login', 'User logged in from IP: ::1', '2025-09-05 13:05:07'),
(379, 65, 'Logout', 'User logged out from IP: ::1', '2025-09-05 13:09:38'),
(380, 1, 'Login', 'User logged in from IP: ::1', '2025-09-05 13:09:53'),
(381, 1, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-09-05 13:30:38'),
(382, 1, 'Login', 'User logged in from IP: ::1', '2025-09-06 16:57:52'),
(383, 1, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-09-06 16:58:47'),
(384, 2, 'Login', 'User logged in from IP: ::1', '2025-09-06 17:20:22'),
(385, 1, 'Login', 'User logged in from IP: ::1', '2025-09-06 18:42:47'),
(386, 2, 'Logout', 'User logged out from IP: ::1', '2025-09-06 18:58:43'),
(387, 8, 'Login', 'User logged in from IP: ::1', '2025-09-06 18:58:58'),
(388, 8, 'Logout', 'User logged out from IP: ::1', '2025-09-06 19:41:20'),
(389, 10, 'Login', 'User logged in from IP: ::1', '2025-09-06 19:41:30'),
(390, 10, 'Login', 'User logged in from IP: ::1', '2025-09-06 19:42:13'),
(391, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-06 19:46:08'),
(392, 10, 'Login', 'User logged in from IP: ::1', '2025-09-06 19:46:15'),
(393, 1, 'Login', 'User logged in from IP: ::1', '2025-09-06 19:47:04'),
(394, 8, 'Login', 'User logged in from IP: ::1', '2025-09-09 06:40:26'),
(395, 8, 'Logout', 'User logged out from IP: ::1', '2025-09-09 06:40:38'),
(396, 1, 'Login', 'User logged in from IP: ::1', '2025-09-09 06:40:44'),
(397, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-09 06:41:01'),
(398, 1, 'Login', 'User logged in from IP: ::1', '2025-09-09 06:46:19'),
(399, 1, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-09-09 08:14:20'),
(400, 8, 'Login', 'User logged in from IP: ::1', '2025-09-09 08:25:32'),
(401, 65, 'Password Reset', 'Password reset successfully via email verification', '2025-09-09 08:49:03'),
(402, 65, 'Login', 'User logged in from IP: ::1', '2025-09-09 08:49:31'),
(403, 65, 'Logout', 'User logged out from IP: ::1', '2025-09-09 08:49:37'),
(404, 2, 'Login', 'User logged in from IP: ::1', '2025-09-09 08:59:39'),
(405, 1, 'User Creation', 'Juan Dela Cruz created new user: test test (Role: Super Admin)', '2025-09-09 14:43:08'),
(406, 1, 'Employee Update', 'Juan Dela Cruz updated employee: test test', '2025-09-09 14:45:03'),
(407, 8, 'Logout', 'User logged out from IP: ::1', '2025-09-09 14:45:49'),
(408, 68, 'Login', 'User logged in from IP: ::1', '2025-09-09 14:46:03'),
(409, 68, 'Logout', 'User logged out from IP: ::1', '2025-09-09 14:46:06'),
(410, 68, 'Login', 'User logged in from IP: ::1', '2025-09-09 14:46:25'),
(411, 68, 'Password Management', 'Password changed via profile page', '2025-09-09 14:47:31'),
(412, 68, 'Logout', 'User logged out from IP: ::1', '2025-09-09 14:47:37'),
(413, 68, 'Login', 'User logged in from IP: ::1', '2025-09-09 14:47:41'),
(414, 1, 'Login', 'User logged in from IP: ::1', '2025-09-12 00:35:54'),
(415, 1, 'User Creation', 'Juan Dela Cruz created new user: asdas asdas (Role: Admin)', '2025-09-12 00:39:09'),
(416, 69, 'Login', 'User logged in from IP: ::1', '2025-09-12 00:39:52'),
(417, 1, 'Employee Update', 'Juan Dela Cruz updated employee: Dio Brando', '2025-09-12 00:54:25'),
(418, 69, 'Logout', 'User logged out from IP: ::1', '2025-09-12 00:59:00'),
(419, 1, 'Login', 'User logged in from IP: ::1', '2025-09-12 01:20:04'),
(420, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-12 01:20:08'),
(421, 10, 'Login', 'User logged in from IP: ::1', '2025-09-12 01:20:15'),
(422, 10, 'Logout', 'User logged out from IP: ::1', '2025-09-12 01:20:23'),
(423, 1, 'Login', 'User logged in from IP: ::1', '2025-09-12 01:20:47'),
(424, 69, 'Password Reset', 'Password reset successfully via email verification', '2025-09-12 01:21:33'),
(425, 69, 'Login', 'User logged in from IP: ::1', '2025-09-12 01:22:17'),
(426, 1, 'Employee Update', 'Juan Dela Cruz updated employee: Dio Brando', '2025-09-12 01:22:57'),
(427, 69, 'Logout', 'User logged out from IP: ::1', '2025-09-12 01:23:35'),
(428, 2, 'Login', 'User logged in from IP: ::1', '2025-09-12 01:23:47'),
(429, 2, 'Logout', 'User logged out from IP: ::1', '2025-09-12 01:24:09'),
(430, 56, 'Login', 'User logged in from IP: ::1', '2025-09-12 01:24:12'),
(431, 56, 'Logout', 'User logged out from IP: ::1', '2025-09-12 01:24:16'),
(432, 65, 'Login', 'User logged in from IP: ::1', '2025-09-12 01:24:21'),
(433, 65, 'Logout', 'User logged out from IP: ::1', '2025-09-12 01:24:27'),
(434, 63, 'Login', 'User logged in from IP: ::1', '2025-09-12 01:24:34'),
(435, 63, 'Logout', 'User logged out from IP: ::1', '2025-09-12 01:43:14'),
(436, 10, 'Login', 'User logged in from IP: ::1', '2025-09-12 01:43:21'),
(437, 10, 'Auto Cleanup', 'Auto-deleted 1 applicant record(s) older than 1 week', '2025-09-12 01:43:27'),
(438, 10, 'Employee Update', 'Liza Domingo updated employee: Dio Brando', '2025-09-12 05:22:24'),
(439, 10, 'Employee Update', 'Liza Domingo updated employee: Dio Brando', '2025-09-12 05:22:36'),
(440, 10, 'Employee Update', 'Liza Domingo updated employee: Connor McGregor', '2025-09-12 05:25:17'),
(441, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-12 06:04:59'),
(442, 1, 'Login', 'User logged in from IP: ::1', '2025-09-12 06:27:28'),
(443, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-12 06:54:09'),
(444, 1, 'Login', 'User logged in from IP: ::1', '2025-09-12 07:25:04'),
(445, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-12 08:10:21'),
(446, 1, 'Login', 'User logged in from IP: ::1', '2025-09-12 08:10:35'),
(447, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-12 08:10:38'),
(448, 2, 'Login', 'User logged in from IP: ::1', '2025-09-12 08:10:55'),
(449, 2, 'Logout', 'User logged out from IP: ::1', '2025-09-12 08:12:20'),
(450, 8, 'Login', 'User logged in from IP: ::1', '2025-09-12 08:13:37'),
(451, 8, 'Logout', 'User logged out from IP: ::1', '2025-09-12 08:13:53'),
(452, 2, 'Login', 'User logged in from IP: ::1', '2025-09-13 00:55:31'),
(453, 10, 'Logout', 'User logged out from IP: ::1', '2025-09-13 00:58:03'),
(454, 65, 'Login', 'User logged in from IP: ::1', '2025-09-13 00:58:16'),
(455, 1, 'Login', 'User logged in from IP: ::1', '2025-09-13 02:57:11'),
(456, 2, 'Login', 'User logged in from IP: ::1', '2025-09-13 02:57:54'),
(457, 65, 'Logout', 'User logged out from IP: ::1', '2025-09-13 03:01:02'),
(458, 8, 'Login', 'User logged in from IP: ::1', '2025-09-13 03:01:30'),
(459, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-09-13 03:01:34'),
(460, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-13 03:03:58'),
(461, 2, 'Logout', 'User logged out from IP: ::1', '2025-09-13 03:14:35'),
(462, 6, 'Login', 'User logged in from IP: ::1', '2025-09-13 03:14:44'),
(463, 6, 'Logout', 'User logged out from IP: ::1', '2025-09-13 03:15:36'),
(464, 2, 'Login', 'User logged in from IP: ::1', '2025-09-13 03:15:45'),
(465, 1, 'Login', 'User logged in from IP: ::1', '2025-09-13 03:18:08'),
(466, 6, 'Login', 'User logged in from IP: ::1', '2025-09-13 03:52:37'),
(467, 1, 'Login', 'User logged in from IP: ::1', '2025-09-14 09:13:55'),
(468, 1, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-09-14 09:21:19'),
(469, 2, 'Login', 'User logged in from IP: ::1', '2025-09-14 10:06:12'),
(470, 2, 'Logout', 'User logged out from IP: ::1', '2025-09-14 10:27:18'),
(471, 6, 'Login', 'User logged in from IP: ::1', '2025-09-14 10:27:26'),
(472, 6, 'Logout', 'User logged out from IP: ::1', '2025-09-14 10:50:57'),
(473, 8, 'Login', 'User logged in from IP: ::1', '2025-09-14 10:51:10'),
(474, 8, 'Login', 'User logged in from IP: ::1', '2025-09-16 05:11:48'),
(475, 8, 'Employee Creation', 'Erwin Mendoza created new employee: test02 test02 (Role: Human Resource)', '2025-09-16 07:34:51'),
(476, 8, 'Employee Update', 'Erwin Mendoza updated employee: test02 test02', '2025-09-16 07:46:28'),
(477, 1, 'Login', 'User logged in from IP: ::1', '2025-09-17 06:54:33'),
(478, 1, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-09-17 06:54:49'),
(479, 8, 'Login', 'User logged in from IP: ::1', '2025-09-17 06:58:08'),
(480, 2, 'Login', 'User logged in from IP: ::1', '2025-09-17 07:02:21'),
(481, 8, 'Performance Evaluation', 'Completed performance evaluation for User ID: 64 (Biñan Testing Laguna) with overall rating: 90.00%', '2025-09-17 07:26:19'),
(482, 6, 'Login', 'User logged in from IP: ::1', '2025-09-17 07:41:39'),
(483, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-18 00:14:44'),
(484, 2, 'Login', 'User logged in from IP: ::1', '2025-09-18 00:14:56'),
(485, 8, 'Login', 'User logged in from IP: ::1', '2025-09-18 02:18:32'),
(486, 8, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-09-18 02:18:34'),
(487, 8, 'Employee Creation', 'Erwin Mendoza created new employee: test08 test08 (Role: Human Resource)', '2025-09-18 02:28:39'),
(488, 8, 'Employee Update', 'Erwin Mendoza updated employee: test08 test08', '2025-09-18 02:29:34'),
(489, 2, 'Logout', 'User logged out from IP: ::1', '2025-09-18 03:36:50'),
(490, 6, 'Login', 'User logged in from IP: ::1', '2025-09-18 08:02:39'),
(491, 1, 'Login', 'User logged in from IP: ::1', '2025-09-19 15:00:28'),
(492, 1, 'Auto Cleanup', 'Auto-deleted 1 applicant record(s) older than 1 week', '2025-09-19 15:00:34'),
(493, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-19 15:11:08'),
(494, 6, 'Login', 'User logged in from IP: 127.0.0.1', '2025-09-19 15:11:22'),
(495, 6, 'Rate Update', 'Maricel Valdez updated daily rate for location \'Batangas\' from ₱540.00 to ₱590.00 - Reason: wala lang trip ko lang', '2025-09-19 15:28:26'),
(496, 6, 'Rate Update', 'Maricel Valdez updated daily rate for location \'Batangas\' from ₱590.00 to ₱540.00 - Reason: nag kamali ako', '2025-09-19 15:28:41'),
(497, 1, 'Login', 'User logged in from IP: ::1', '2025-09-22 18:21:46'),
(498, 1, 'Logout', 'User logged out from IP: ::1', '2025-09-23 08:44:15'),
(499, 2, 'Login', 'User logged in from IP: ::1', '2025-09-23 11:57:08'),
(500, 2, 'Logout', 'User logged out from IP: ::1', '2025-09-23 12:02:53'),
(501, 8, 'Login', 'User logged in from IP: ::1', '2025-09-23 12:03:15'),
(502, 6, 'Login', 'User logged in from IP: ::1', '2025-09-24 16:34:59'),
(503, 6, 'Password Management', 'Password changed via profile page', '2025-09-24 17:19:16'),
(504, 6, 'Logout', 'User logged out from IP: ::1', '2025-09-24 17:22:59'),
(505, 6, 'Login', 'User logged in from IP: ::1', '2025-09-24 17:23:19'),
(506, 6, 'Password Management', 'Password changed via profile page', '2025-09-24 17:23:35'),
(507, 6, 'Attendance Delete Permanent', 'Permanently deleted attendance record ID 40 for Dylan Wang - Reason: just because', '2025-09-24 17:47:07'),
(508, 6, 'Logout', 'User logged out from IP: ::1', '2025-09-24 17:49:30'),
(509, 1, 'Login', 'User logged in from IP: ::1', '2025-09-24 17:49:38'),
(510, 1, 'Auto Cleanup', 'Auto-deleted 3 applicant record(s) older than 1 week', '2025-09-24 18:16:25'),
(511, 1, 'Login', 'User logged in from IP: ::1', '2025-10-06 06:00:30'),
(512, 1, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-10-06 06:15:10'),
(513, 1, 'Logout', 'User logged out from IP: ::1', '2025-10-06 06:29:35'),
(514, 2, 'Login', 'User logged in from IP: ::1', '2025-10-06 06:29:48'),
(515, 1, 'Login', 'User logged in from IP: ::1', '2025-10-07 05:07:39'),
(516, 1, 'Auto Cleanup', 'Applicant cleanup executed - no old records found to delete', '2025-10-07 05:07:54'),
(517, 8, 'Login', 'User logged in from IP: ::1', '2025-10-07 05:08:11'),
(518, 6, 'Login', 'User logged in from IP: ::1', '2025-10-07 05:09:05'),
(519, 2, 'Login', 'User logged in from IP: ::1', '2025-10-07 05:12:50'),
(520, 8, 'Attendance Edit', 'Erwin Mendoza edited attendance for Dylan Wang - Old: September 7, 2025 - 2:35 AM to 2:30 PM - New: October 6, 2025 - 6:00 PM to 6:00 AM - Reason: fix lapses.', '2025-10-07 05:15:48'),
(521, 8, 'Attendance Edit', 'Erwin Mendoza edited attendance for Dylan Wang - Old: October 6, 2025 - 6:00 PM to 6:00 AM - New: October 5, 2025 - 6:00 PM to 6:00 AM - Reason: lapses\n', '2025-10-07 05:19:15'),
(522, 51, 'Login', 'User logged in from IP: ::1', '2025-10-07 08:41:58'),
(523, 1, 'System Update', 'Added new role: OIC (Officer in Charge) - Role_ID: 8', '2025-10-09 03:48:55'),
(524, 1, 'System Update', 'Created oic_locations table for OIC location management', '2025-10-09 03:48:55'),
(525, 1, 'OIC Creation', 'Created new OIC: Maria Santos assigned to Manila', '2025-10-09 03:56:32'),
(526, 1, 'OIC Creation', 'Created new OIC: Carlos Rivera assigned to Bulacan', '2025-10-09 03:56:32'),
(527, 1, 'OIC Creation', 'Created new OIC: Ana Garcia assigned to Batangas', '2025-10-09 03:56:32'),
(528, 1, 'OIC Creation', 'Created new OIC: John Torres assigned to Laguna', '2025-10-09 03:56:32'),
(529, 1, 'OIC Creation', 'Created new OIC: Elena Cruz assigned to Cavite', '2025-10-09 03:56:32'),
(530, 1, 'OIC Assignment', 'Assigned Carlos Rivera to additional location: Pampanga', '2025-10-09 03:56:32'),
(531, 1, 'System Update', 'Added new role: OIC (Officer in Charge) - Role_ID: 8', '2025-10-09 03:56:32'),
(532, 1, 'System Update', 'Created oic_locations table for OIC location management', '2025-10-09 03:56:32'),
(533, 1, 'System Update', 'Inserted 5 OIC dummy users with location assignments', '2025-10-09 03:56:32'),
(534, 77, 'Login', 'User logged in from IP: ::1', '2025-10-09 03:58:56');

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `Applicant_ID` int(11) NOT NULL,
  `First_Name` varchar(50) NOT NULL,
  `Middle_Name` varchar(50) DEFAULT NULL,
  `Last_Name` varchar(50) NOT NULL,
  `Name_Extension` varchar(10) DEFAULT NULL,
  `Email` varchar(100) NOT NULL,
  `Phone_Number` varchar(20) NOT NULL,
  `Position` varchar(50) NOT NULL,
  `Preferred_Location` varchar(50) DEFAULT NULL,
  `Resume_Path` varchar(255) NOT NULL,
  `Additional_Info` text DEFAULT NULL,
  `Application_Date` timestamp NOT NULL DEFAULT current_timestamp(),
  `Status` enum('New','Contacted','Interview Scheduled','Hired','Rejected') NOT NULL DEFAULT 'New',
  `Reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `Last_Modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`Applicant_ID`, `First_Name`, `Middle_Name`, `Last_Name`, `Name_Extension`, `Email`, `Phone_Number`, `Position`, `Preferred_Location`, `Resume_Path`, `Additional_Info`, `Application_Date`, `Status`, `Reviewed`, `Last_Modified`) VALUES
(10, 'testing01', 'testing01', 'testing01', 'Jr.', 'testing@gmail.com', '09278881111', 'Security Guard', 'Laguna', 'uploads/resumes/1759825911_testing01_testing01_resume.pdf', '', '2025-10-07 08:31:51', 'New', 1, '2025-10-07 08:32:09'),
(11, 'test', 'test', 'test', 'Jr.', 'test@gmail.com', '09279991990', 'Lady Guard', 'Pangasinan', 'uploads/resumes/1759826035_test_test_resume.pdf', '', '2025-10-07 08:33:55', 'New', 1, '2025-10-07 08:34:02');

-- --------------------------------------------------------

--
-- Table structure for table `archived_guards`
--

CREATE TABLE `archived_guards` (
  `archive_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(13) DEFAULT NULL,
  `location_name` varchar(100) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_by` int(11) DEFAULT NULL,
  `archive_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archived_guards`
--

INSERT INTO `archived_guards` (`archive_id`, `user_id`, `first_name`, `middle_name`, `last_name`, `email`, `phone_number`, `location_name`, `archived_at`, `archived_by`, `archive_reason`) VALUES
(23, 39, 'Joshua Beaver', 'Abellera', 'Que', 'jc.saxophonist0629@gmail.com', '09565299476', 'Naga', '2025-05-31 08:52:46', 8, 'no particular reason.'),
(25, 54, 'Angelita', 'Malabana', 'Baldovino', 'guard11@gmail.com', '09564299473', 'Batangas', '2025-06-04 09:46:27', 8, 'basta'),
(29, 52, 'Bernard', 'Malabana', 'de Sena', 'guard10@gmail.com', '09565261234', 'Batangas', '2025-06-04 10:10:11', 8, 'basta'),
(30, 52, 'Bernard', 'Malabana', 'de Sena', 'guard10@gmail.com', '09565261234', 'Batangas', '2025-06-04 10:17:49', 8, 'test'),
(31, 55, 'Leo', 'Angeles', 'Bautista', 'batangas3@gmail.com', '09666666666', 'Batangas', '2025-08-11 12:15:49', 8, 'test');

-- --------------------------------------------------------

--
-- Table structure for table `archive_dtr_data`
--

CREATE TABLE `archive_dtr_data` (
  `ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `time_in` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `time_out` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archive_dtr_data`
--

INSERT INTO `archive_dtr_data` (`ID`, `User_ID`, `first_name`, `last_name`, `time_in`, `time_out`) VALUES
(41, 2, 'Dylan', 'Wang', '2025-04-22 20:01:36', '2025-04-22 20:46:45'),
(42, 2, 'Dylan', 'Wang', '2025-04-22 20:46:59', '2025-04-23 05:10:53');

-- --------------------------------------------------------

--
-- Table structure for table `archive_leave_requests`
--

CREATE TABLE `archive_leave_requests` (
  `ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `Leave_Type` varchar(255) NOT NULL,
  `Leave_Reason` text NOT NULL,
  `Start_Date` date NOT NULL,
  `End_Date` date NOT NULL,
  `Request_Date` timestamp NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Pending','Approved','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archive_leave_requests`
--

INSERT INTO `archive_leave_requests` (`ID`, `User_ID`, `first_name`, `last_name`, `Leave_Type`, `Leave_Reason`, `Start_Date`, `End_Date`, `Request_Date`, `Status`) VALUES
(1, 2, 'Dylan ', 'Wang', 'vacation', 'just give me a reason', '2025-03-19', '2025-03-21', '2025-03-19 11:00:42', 'Pending'),
(3, 9, 'Christian', 'Rosario', 'emergency', 'my daughter got sick.', '2025-04-22', '2025-04-24', '2025-04-21 16:50:42', 'Pending'),
(5, 2, 'Dylan', 'Wang', 'vacation', 'I\'m going to Puerto Galera sir.', '2025-04-25', '2025-04-30', '2025-04-24 13:59:32', 'Pending'),
(6, 2, 'Dylan', 'Wang', 'emergency', 'my daughter got sick', '2025-04-28', '2025-05-01', '2025-04-28 04:15:37', 'Pending'),
(7, 2, 'Dylan', 'Wang', 'sick', 'i caught a flu sir.', '2025-05-04', '2025-05-06', '2025-05-04 15:40:24', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Guard_Email` varchar(255) DEFAULT NULL,
  `IP_Address` varchar(255) DEFAULT NULL,
  `Time_In` timestamp NOT NULL DEFAULT current_timestamp(),
  `Time_Out` timestamp NULL DEFAULT NULL,
  `Hours_Worked` decimal(5,2) DEFAULT 0.00,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `Latitude` decimal(9,6) DEFAULT NULL,
  `Longitude` decimal(9,6) DEFAULT NULL,
  `face_verified` tinyint(1) DEFAULT 0,
  `verification_image_path` varchar(255) DEFAULT NULL,
  `designated_location_id` int(11) DEFAULT NULL,
  `distance_from_location` decimal(10,2) DEFAULT NULL,
  `location_verified` tinyint(1) DEFAULT 0,
  `Time_Out_Latitude` decimal(10,8) DEFAULT NULL,
  `Time_Out_Longitude` decimal(11,8) DEFAULT NULL,
  `Time_Out_IP` varchar(45) DEFAULT NULL,
  `Time_Out_Image` varchar(255) DEFAULT NULL,
  `Updated_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`ID`, `User_ID`, `Guard_Email`, `IP_Address`, `Time_In`, `Time_Out`, `Hours_Worked`, `Created_At`, `Latitude`, `Longitude`, `face_verified`, `verification_image_path`, `designated_location_id`, `distance_from_location`, `location_verified`, `Time_Out_Latitude`, `Time_Out_Longitude`, `Time_Out_IP`, `Time_Out_Image`, `Updated_At`) VALUES
(29, 2, NULL, NULL, '2025-04-17 22:00:00', '2025-04-18 14:00:00', 0.00, '2025-04-19 12:22:59', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(30, 2, NULL, NULL, '2025-04-18 23:00:00', '2025-04-19 14:00:00', 0.00, '2025-04-19 12:33:28', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-04-19 20:33:56'),
(31, 2, NULL, NULL, '2025-04-20 10:00:00', '2025-04-19 22:00:00', 0.00, '2025-04-19 12:38:06', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-04-20 23:59:41'),
(32, 3, NULL, NULL, '2025-04-20 22:00:00', '2025-04-21 14:00:00', 0.00, '2025-04-20 18:35:49', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-04-21 03:02:06'),
(34, 3, NULL, NULL, '2025-04-22 10:00:00', '2025-04-22 22:00:00', 0.00, '2025-04-20 19:15:23', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(43, 2, NULL, NULL, '2025-03-22 20:27:58', '2025-03-23 17:10:17', 0.00, '2025-04-23 05:19:31', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(44, 2, NULL, '110.54.197.192', '2025-04-23 10:00:00', '2025-04-23 22:00:00', 0.00, '2025-04-23 06:03:41', 14.211621, 121.167781, 1, '../uploads/attendance/1745388221_2.jpg', NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-06-23 05:27:54'),
(47, 3, NULL, NULL, '2025-04-23 06:56:06', NULL, 0.00, '2025-04-28 02:33:34', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(49, 2, NULL, '115.146.194.18', '2025-04-28 10:00:00', '2025-04-28 22:00:00', 999.99, '2025-04-28 06:25:06', 14.219862, 121.164585, 1, '../uploads/attendance/1745821506_2.jpg', NULL, NULL, 0, 14.13611520, 120.59279360, '180.195.202.3', '../uploads/attendance/1751354185_2.jpg', '2025-07-15 19:34:23'),
(50, 3, NULL, NULL, '2025-04-25 23:45:00', '2025-04-26 10:00:00', 0.00, '2025-04-30 03:49:10', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(56, 2, NULL, '180.195.202.3', '2025-06-30 22:00:00', '2025-07-01 10:00:00', 1.00, '2025-07-01 07:31:52', 14.136115, 120.592794, 1, '../uploads/attendance/1751355112_2.jpg', NULL, NULL, 1, 14.13611520, 120.59279360, '180.195.202.3', '../uploads/attendance/1751360504_2.jpg', '2025-07-15 19:35:40'),
(57, 2, NULL, '180.195.202.3', '2025-07-16 10:00:00', '2025-07-16 22:00:00', 0.00, '2025-07-15 17:23:09', 14.208205, 121.117082, 1, '../uploads/attendance/1752600189_2.jpg', NULL, NULL, 1, NULL, NULL, NULL, NULL, '2025-07-16 01:28:15'),
(58, 56, NULL, '180.195.202.3', '2025-07-15 22:00:00', '2025-07-16 10:00:00', 0.00, '2025-07-15 19:05:55', 14.208205, 121.117082, 1, '../uploads/attendance/1752606355_56.jpg', NULL, NULL, 1, NULL, NULL, NULL, NULL, '2025-07-16 03:07:11'),
(59, 54, NULL, '::1', '2025-07-05 22:00:00', '2025-07-06 10:00:00', 0.00, '2025-07-15 21:23:58', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(60, 2, NULL, '::1', '2025-05-04 10:00:00', '2025-05-04 22:00:00', 0.00, '2025-07-15 21:43:39', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(61, 49, NULL, '::1', '2025-07-15 22:00:00', '2025-07-16 10:05:00', 0.00, '2025-08-02 14:15:06', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(62, 49, NULL, NULL, '2025-07-16 21:45:00', '2025-07-17 10:45:00', 0.00, '2025-08-02 16:51:08', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-08-03 00:51:08'),
(63, 49, NULL, NULL, '2025-07-17 23:00:00', '2025-07-18 11:00:00', 0.00, '2025-08-02 17:39:24', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-08-03 01:39:24'),
(64, 49, NULL, NULL, '2025-07-21 10:00:00', '2025-07-21 22:05:00', 0.00, '2025-08-02 18:26:03', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-08-03 02:26:03'),
(65, 49, NULL, NULL, '2025-07-22 10:05:00', '2025-07-22 22:00:00', 0.00, '2025-08-02 18:27:22', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-08-03 02:27:22'),
(66, 49, NULL, NULL, '2025-07-23 11:00:00', '2025-07-23 22:01:00', 0.00, '2025-08-02 19:21:44', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-08-03 03:21:44'),
(70, 63, NULL, '::1', '2025-08-23 10:00:00', '2025-08-23 22:01:00', 0.00, '2025-08-22 18:18:03', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(71, 63, NULL, '::1', '2025-08-22 10:00:00', '2025-08-22 22:01:00', 0.00, '2025-08-22 17:40:31', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(77, 2, NULL, '::1', '2025-10-05 10:00:00', '2025-10-05 22:00:00', 707.00, '2025-09-06 18:35:07', 14.208801, 121.126065, 1, '../uploads/attendance/1757183707_2.jpg', NULL, NULL, 1, 14.22289030, 121.16172090, '115.146.194.18', '../uploads/attendance/1759732222_2.jpg', '2025-10-06 14:30:22'),
(78, 51, NULL, '110.54.167.222', '2025-10-07 08:50:29', NULL, 0.00, '2025-10-07 08:50:29', 14.211646, 121.167690, 1, '../uploads/attendance/1759827029_51.jpg', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `Log_ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Action` varchar(255) NOT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `IP_Address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`Log_ID`, `User_ID`, `Action`, `Timestamp`, `IP_Address`) VALUES
(1, 8, 'Archived guard: Christian Rosario', '2025-04-25 08:56:07', '::1'),
(2, 8, 'Archived guard: Christian Rosario', '2025-04-25 08:57:40', '::1'),
(3, 8, 'Archived guard: Christian Rosario', '2025-04-25 08:59:11', '::1'),
(4, 8, 'Archived guard: Dylan Wang', '2025-04-25 09:08:06', '::1'),
(5, 8, 'Archived guard: Dylan Wang', '2025-04-25 09:17:30', '::1'),
(6, 8, 'Archived guard: James Obed Laguna', '2025-04-25 09:21:41', '::1'),
(7, 8, 'Recovered guard: Dylan Wang', '2025-04-25 09:40:50', '::1'),
(8, 8, 'Archived guard: Dylan Wang', '2025-04-25 09:46:31', '::1'),
(9, 8, 'Archived guard: James Obed Laguna', '2025-04-25 11:06:00', '::1'),
(10, 8, 'Recovered guard: James Obed Laguna', '2025-04-25 11:06:09', '::1'),
(11, 8, 'Updated guard details for ID: 26', '2025-04-25 11:09:53', '::1'),
(12, 8, 'Archived guard: James Obed Laguna', '2025-04-25 11:09:58', '::1'),
(13, 8, 'Updated guard details for ID: 3', '2025-04-25 11:11:38', '::1'),
(14, 8, 'Updated guard details for ID: 27', '2025-04-25 11:11:51', '::1'),
(15, 8, 'Updated guard details for ID: 24', '2025-04-25 11:12:10', '::1'),
(16, 8, 'Archived guard: Jemmanuel Cyril Que', '2025-04-25 12:28:07', '::1'),
(17, 8, 'Recovered guard: Jemmanuel Cyril Que', '2025-04-25 18:13:57', '::1'),
(18, 8, 'Updated guard details for ID: 28', '2025-04-25 18:17:32', '::1'),
(19, 8, 'Archived guard: Jemmanuel Cyril Que', '2025-04-25 18:17:41', '::1'),
(20, 8, 'Recovered guard: Dylan Wang', '2025-04-25 18:22:22', '::1'),
(21, 8, 'Archived guard: Jemmanuel Cyril Que', '2025-04-25 18:22:28', '::1'),
(22, 8, 'Permanently deleted guard with ID: 29', '2025-04-25 18:22:35', '::1'),
(23, 8, 'Archived guard: Jemmanuel Cyril Que', '2025-04-25 18:23:35', '::1'),
(24, 8, 'Permanently deleted guard with ID: 30', '2025-04-25 18:23:39', '::1'),
(25, 8, 'Archived guard: Dylan Wang', '2025-04-27 02:47:15', '::1'),
(26, 8, 'Recovered guard: Dylan Wang', '2025-04-27 02:47:35', '::1'),
(27, 8, 'Updated guard details for ID: 2', '2025-04-27 02:47:48', '::1'),
(28, 8, 'Updated guard details for ID: 2', '2025-04-27 02:48:01', '::1'),
(29, 8, 'Updated guard details for ID: 37', '2025-04-28 04:37:23', '::1'),
(30, 8, 'Updated guard details for ID: 37', '2025-04-28 04:37:29', '::1'),
(31, 8, 'Archived guard: Julianna Laguna', '2025-04-28 04:37:32', '::1'),
(32, 8, 'Recovered guard: Julianna Laguna', '2025-04-28 04:37:38', '::1'),
(33, 8, 'Updated guard details for ID: 37', '2025-04-28 06:16:32', '::1'),
(34, 8, 'Updated guard details for ID: 37', '2025-04-28 06:17:03', '::1'),
(35, 8, 'Updated guard details for ID: 38', '2025-05-27 10:12:14', '::1'),
(36, 8, 'Updated guard details for ID: 3', '2025-05-27 10:12:52', '::1'),
(37, 8, 'Updated guard details for ID: 39', '2025-05-27 10:13:01', '::1'),
(38, 8, 'Updated guard details for ID: 38', '2025-05-27 11:01:39', '::1'),
(39, 8, 'Updated guard details for ID: 3', '2025-05-27 11:01:58', '::1'),
(40, 8, 'Updated guard details for ID: 39', '2025-05-27 11:02:12', '::1'),
(41, 8, 'Recovered guard: Julianna Laguna', '2025-05-27 11:03:18', '::1'),
(42, 8, 'Recovered guard: Dylan Wang', '2025-05-27 11:03:23', '::1'),
(43, 8, 'Updated guard details for ID: 2', '2025-05-27 11:03:36', '::1'),
(44, 8, 'Updated guard details for ID: 37', '2025-05-31 07:57:01', '::1'),
(45, 8, 'Updated guard details for ID: 39', '2025-05-31 08:52:06', '::1'),
(46, 8, 'Updated guard details for ID: 39', '2025-05-31 08:52:34', '::1'),
(47, 8, 'Archived guard: Joshua Beaver Que', '2025-05-31 08:52:46', '::1'),
(48, 8, 'Recovered guard: Joshua Beaver Que', '2025-05-31 08:53:50', '::1'),
(49, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Bulacan from ₱525.00 to ₱500.00', '2025-06-03 07:27:35', '::1'),
(50, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Bulacan from ₱500.00 to ₱525.00', '2025-06-03 07:27:43', '::1'),
(51, 8, 'Updated guard details for ID: 39', '2025-06-03 08:03:59', '::1'),
(52, 8, 'Updated guard details for ID: 38', '2025-06-03 08:04:17', '::1'),
(53, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Naga from ₱0.00 to ₱415.00', '2025-06-03 08:39:17', '::1'),
(54, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Pampanga from ₱0.00 to ₱540.00', '2025-06-03 08:39:27', '::1'),
(55, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Pangasinan from ₱0.00 to ₱435.00', '2025-06-03 08:39:37', '::1'),
(56, 8, 'Updated guard details for ID: 47', '2025-06-03 08:40:27', '::1'),
(57, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Pampanga from ₱0.00 to ₱540.00', '2025-06-03 09:11:00', '::1'),
(58, 8, 'Updated guard details for ID: 37', '2025-06-03 09:25:08', '::1'),
(59, 8, 'Updated guard details for ID: 3', '2025-06-03 09:25:22', '::1'),
(60, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Cavite from ₱0.00 to ₱540.00', '2025-06-03 10:55:48', '::1'),
(61, 8, 'Updated guard details for ID: 49', '2025-06-03 11:25:53', '::1'),
(62, 8, 'Updated guard details for ID: 50', '2025-06-03 11:36:41', '::1'),
(63, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Laguna from ₱0.00 to ₱540.00', '2025-06-03 11:38:25', '::1'),
(64, 8, 'Updated guard details for ID: 51', '2025-06-03 11:45:43', '::1'),
(65, 8, 'Updated guard details for ID: 51', '2025-06-03 11:46:35', '::1'),
(66, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Biñan from ₱0.00 to ₱540.00', '2025-06-03 11:48:30', '::1'),
(67, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Batangas from ₱0.00 to ₱540.00', '2025-06-03 11:48:40', '::1'),
(68, 8, 'Updated guard details for ID: 52', '2025-06-03 11:48:55', '::1'),
(69, 8, 'Archived guard: Angelita Baldovino', '2025-06-03 12:01:10', '::1'),
(70, 8, 'Permanently deleted guard with ID: 53', '2025-06-03 12:06:16', '::1'),
(71, 8, 'Updated guard details for ID: 54', '2025-06-04 09:45:46', '::1'),
(72, 8, 'Archived guard: Angelita Baldovino', '2025-06-04 09:46:27', '::1'),
(73, 8, 'Recovered guard: Angelita Baldovino', '2025-06-04 09:46:35', '::1'),
(74, 8, 'Updated guard details for ID: 52', '2025-06-04 10:04:51', '::1'),
(75, 8, 'Archived guard: Bernard de Sena', '2025-06-04 10:10:11', '::1'),
(76, 8, 'Recovered guard: Bernard de Sena', '2025-06-04 10:17:28', '::1'),
(77, 8, 'Archived guard: Bernard de Sena', '2025-06-04 10:17:49', '::1'),
(78, 8, 'Recovered guard: Bernard de Sena', '2025-06-04 10:18:12', '::1'),
(79, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Batangas from ₱0.00 to ₱540.00', '2025-06-04 10:31:15', '::1'),
(80, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Batangas from ₱360.00 to ₱550.00', '2025-06-04 11:18:55', '::1'),
(81, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Batangas from ₱550.00 to ₱540.00', '2025-06-04 11:19:04', '::1'),
(82, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Batangas from ₱540.00 to ₱550.00', '2025-06-04 11:20:22', '::1'),
(83, 6, 'Rate Change: Jemmanuel Cyril Que updated location rate for Batangas from ₱550.00 to ₱540.00', '2025-06-04 11:20:47', '::1'),
(84, 8, 'Updated guard details for ID: 56', '2025-07-15 19:00:12', '::1'),
(85, 35, 'Updated guard details for ID: 54', '2025-07-15 19:23:44', '::1'),
(86, 35, 'Updated guard details for ID: 52', '2025-07-15 19:23:54', '::1'),
(87, 35, 'Updated guard details for ID: 55', '2025-07-15 19:24:01', '::1'),
(88, 35, 'Updated guard details for ID: 51', '2025-07-15 19:24:09', '::1'),
(89, 6, 'RATE_UPDATE: Maricel Valdez changed location rate for \'NCR\' from ₱645.00 to ₱695.00 | Reason: Rate in NCR got higher | Affected Guards: All guards in NCR', '2025-07-22 20:15:41', '::1'),
(90, 8, 'Updated guard details for ID: 56', '2025-07-29 20:52:08', '::1'),
(91, 8, 'Archived guard: Leo Bautista', '2025-08-11 12:15:49', '::1'),
(92, 8, 'Recovered guard: Leo Bautista', '2025-08-11 12:15:59', '::1'),
(93, 8, 'Recovered user ID: 6', '2025-08-19 18:06:24', '::1'),
(94, 8, 'Recovered user ID: 63', '2025-08-19 18:09:05', '::1'),
(95, 6, 'RATE_UPDATE: Maricel Valdez changed location rate for \'Batangas\' from ₱540.00 to ₱590.00 | Reason: wala lang trip ko lang | Affected Guards: All guards in Batangas', '2025-09-19 15:28:26', '::1'),
(96, 6, 'RATE_UPDATE: Maricel Valdez changed location rate for \'Batangas\' from ₱590.00 to ₱540.00 | Reason: nag kamali ako | Affected Guards: All guards in Batangas', '2025-09-19 15:28:41', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `edit_attendance_logs`
--

CREATE TABLE `edit_attendance_logs` (
  `Log_ID` int(11) NOT NULL,
  `Attendance_ID` int(11) NOT NULL,
  `Editor_User_ID` int(11) NOT NULL,
  `Editor_Name` varchar(255) NOT NULL,
  `Old_Time_In` datetime NOT NULL,
  `New_Time_In` datetime NOT NULL,
  `Old_Time_Out` datetime DEFAULT NULL,
  `New_Time_Out` datetime DEFAULT NULL,
  `Edit_Timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `IP_Address` varchar(45) DEFAULT NULL,
  `Action_Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `edit_attendance_logs`
--

INSERT INTO `edit_attendance_logs` (`Log_ID`, `Attendance_ID`, `Editor_User_ID`, `Editor_Name`, `Old_Time_In`, `New_Time_In`, `Old_Time_Out`, `New_Time_Out`, `Edit_Timestamp`, `IP_Address`, `Action_Description`) VALUES
(5, 30, 6, 'Jemmanuel Cyril Que', '2025-04-19 06:00:00', '2025-04-19 07:00:00', '2025-04-19 22:00:00', '2025-04-19 22:00:00', '2025-04-19 12:33:56', '::1', 'Edited attendance record ID 30'),
(6, 31, 6, 'Jemmanuel Cyril Que', '2025-04-20 22:00:00', '2025-04-20 18:00:00', '2025-04-20 10:00:00', '2025-04-20 06:00:00', '2025-04-20 15:59:41', '::1', 'Edited attendance record ID 31'),
(7, 32, 6, 'Jemmanuel Cyril Que', '2025-04-21 06:00:00', '2025-04-21 06:00:00', '2025-04-21 18:00:00', '2025-04-21 22:00:00', '2025-04-20 19:02:06', '::1', 'Edited attendance record ID 32'),
(8, 44, 6, 'Jemmanuel Cyril Que', '2025-04-23 02:03:41', '2025-04-23 06:00:00', NULL, '2025-04-23 18:00:00', '2025-06-23 09:27:54', '::1', 'Edited attendance record ID 44 - Reason: just some lapses'),
(9, 49, 6, 'Jemmanuel Cyril Que', '2025-04-28 14:25:06', '2025-04-28 18:00:00', '2025-07-01 15:16:25', '2025-04-29 06:00:00', '2025-07-15 11:34:23', '::1', 'Edited attendance record ID 49 - Reason: lapses.'),
(10, 56, 6, 'Jemmanuel Cyril Que', '2025-07-01 15:31:52', '2025-07-01 06:00:00', '2025-07-01 17:01:44', '2025-07-01 18:00:00', '2025-07-15 11:35:40', '::1', 'Edited attendance record ID 56 - Reason: trip ko lang\n'),
(11, 57, 6, 'Jemmanuel Cyril Que', '2025-07-16 01:23:09', '2025-07-16 18:00:00', NULL, '2025-07-17 06:00:00', '2025-07-15 17:28:15', '::1', 'Edited attendance record ID 57 - Reason: for payroll calculation testing \n'),
(12, 58, 6, 'Jemmanuel Cyril Que', '2025-07-16 03:05:55', '2025-07-16 06:00:00', NULL, '2025-07-16 18:00:00', '2025-07-15 19:07:11', '::1', 'Edited attendance record ID 58 - Reason: day shift, payroll calculation checking.'),
(13, 59, 6, 'Jemmanuel Cyril Que', '1970-01-01 00:00:00', '2025-07-06 06:00:00', '1970-01-01 00:00:00', '2025-07-06 18:00:00', '2025-07-15 21:23:58', '::1', 'Added new attendance record - Reason: manual entry'),
(14, 60, 6, 'Jemmanuel Cyril Que', '1970-01-01 00:00:00', '2025-05-04 18:00:00', '1970-01-01 00:00:00', '2025-05-05 06:00:00', '2025-07-15 21:43:39', '::1', 'Added new attendance record - Reason: manual entry, just trying the payroll computing'),
(15, 61, 6, 'Maricel Valdez', '1970-01-01 00:00:00', '2025-07-16 06:00:00', '1970-01-01 00:00:00', '2025-07-16 18:05:00', '2025-08-02 14:15:06', '::1', 'Added new attendance record - Reason: test'),
(25, 71, 8, 'Erwin Mendoza', '1970-01-01 00:00:00', '2025-08-22 18:00:00', '1970-01-01 00:00:00', '2025-08-23 06:01:00', '2025-08-22 17:40:31', '::1', 'Added new attendance record - Reason: testing lang\n'),
(26, 77, 8, 'Erwin Mendoza', '2025-09-07 02:35:07', '2025-10-06 18:00:00', '2025-10-06 14:30:22', '2025-10-07 06:00:00', '2025-10-07 05:15:48', '::1', 'Edited attendance - Reason: fix lapses.'),
(27, 77, 8, 'Erwin Mendoza', '2025-10-06 18:00:00', '2025-10-05 18:00:00', '2025-10-07 06:00:00', '2025-10-06 06:00:00', '2025-10-07 05:19:15', '::1', 'Edited attendance - Reason: lapses\n');

-- --------------------------------------------------------

--
-- Table structure for table `employee_rates`
--

CREATE TABLE `employee_rates` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `regular_hours` decimal(5,2) DEFAULT NULL,
  `overtime_hours` decimal(5,2) DEFAULT NULL,
  `night_diff_hours` decimal(5,2) DEFAULT NULL,
  `gross_pay` decimal(10,2) DEFAULT NULL,
  `total_deductions` decimal(10,2) DEFAULT NULL,
  `net_pay` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_ratings`
--

CREATE TABLE `evaluation_ratings` (
  `rating_id` int(11) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `criterion_name` varchar(255) NOT NULL,
  `rating_score` int(11) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluation_ratings`
--

INSERT INTO `evaluation_ratings` (`rating_id`, `evaluation_id`, `criterion_name`, `rating_score`, `comments`, `created_at`) VALUES
(1, 3, 'Technical Skills - Job Knowledge', 90, 'Knowledgeable', '2025-08-22 21:08:06'),
(2, 3, 'Technical Skills - Tool Competency', 85, 'Competent with tools', '2025-08-22 21:08:06'),
(3, 3, 'Technical Skills - Safety Procedure', 85, 'Follows safety procedure and wear PPE.', '2025-08-22 21:08:06'),
(4, 3, 'Quality - Accuracy', 90, 'Accurate.', '2025-08-22 21:08:06'),
(5, 3, 'Quality - Completeness/Orderliness', 90, 'Complete and orderly.', '2025-08-22 21:08:06'),
(6, 3, 'Quality - Reliability', 90, 'Reliable at all times.', '2025-08-22 21:08:06'),
(7, 3, 'Productivity - Time Management', 90, 'Good.', '2025-08-22 21:08:06'),
(8, 3, 'Productivity - Resource Utilization', 85, 'Very Efficient at utilizing resources.', '2025-08-22 21:08:06'),
(9, 3, 'Productivity - Priority Setting', 85, 'Not bad.', '2025-08-22 21:08:06'),
(10, 3, 'Diligence - Follows Instructions', 90, 'Follows instructions at all times.', '2025-08-22 21:08:06'),
(11, 3, 'Diligence - Flexibility/Adaptable', 85, 'Flexible and adaptable.', '2025-08-22 21:08:06'),
(12, 3, 'Diligence - Customer Focus', 85, 'Good customer service.', '2025-08-22 21:08:06'),
(13, 3, 'Diligence - Attendance', 85, 'Punctual.', '2025-08-22 21:08:06'),
(14, 3, 'Diligence - Compliance', 85, 'Is complying at rules at all times.', '2025-08-22 21:08:06'),
(15, 3, 'Work Attitude - Team Cooperation', 90, 'Teamplayer.', '2025-08-22 21:08:06'),
(16, 3, 'Work Attitude - Respect', 85, 'Respectable at all ages and genders.', '2025-08-22 21:08:06'),
(17, 3, 'Work Attitude - Conduct and Behavior', 90, 'A good guard indeed.', '2025-08-22 21:08:06'),
(18, 4, 'Technical Skills - Job Knowledge', 90, '', '2025-09-17 07:26:19'),
(19, 4, 'Technical Skills - Tool Competency', 90, '', '2025-09-17 07:26:19'),
(20, 4, 'Technical Skills - Safety Procedure', 90, '', '2025-09-17 07:26:19'),
(21, 4, 'Quality - Accuracy', 90, '', '2025-09-17 07:26:19'),
(22, 4, 'Quality - Completeness/Orderliness', 90, '', '2025-09-17 07:26:19'),
(23, 4, 'Quality - Reliability', 90, '', '2025-09-17 07:26:19'),
(24, 4, 'Productivity - Time Management', 90, '', '2025-09-17 07:26:19'),
(25, 4, 'Productivity - Resource Utilization', 90, '', '2025-09-17 07:26:19'),
(26, 4, 'Productivity - Priority Setting', 90, '', '2025-09-17 07:26:19'),
(27, 4, 'Diligence - Follows Instructions', 90, '', '2025-09-17 07:26:19'),
(28, 4, 'Diligence - Flexibility/Adaptable', 90, '', '2025-09-17 07:26:19'),
(29, 4, 'Diligence - Customer Focus', 90, '', '2025-09-17 07:26:19'),
(30, 4, 'Diligence - Attendance', 90, '', '2025-09-17 07:26:19'),
(31, 4, 'Diligence - Compliance', 90, '', '2025-09-17 07:26:19'),
(32, 4, 'Work Attitude - Team Cooperation', 90, '', '2025-09-17 07:26:19'),
(33, 4, 'Work Attitude - Respect', 90, '', '2025-09-17 07:26:19'),
(34, 4, 'Work Attitude - Conduct and Behavior', 90, '', '2025-09-17 07:26:19');

-- --------------------------------------------------------

--
-- Table structure for table `face_recognition_data`
--

CREATE TABLE `face_recognition_data` (
  `face_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `face_encoding` longblob NOT NULL,
  `profile_image_path` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `face_recognition_logs`
--

CREATE TABLE `face_recognition_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attempt_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `verification_status` tinyint(1) NOT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `face_recognition_logs`
--

INSERT INTO `face_recognition_logs` (`log_id`, `user_id`, `attempt_timestamp`, `verification_status`, `confidence_score`, `image_path`, `ip_address`, `device_info`) VALUES
(1, 2, '2025-03-22 18:21:34', 1, 0.88, 'verification_images/2_1742667694.jpg', '49.144.35.222', NULL),
(2, 2, '2025-03-22 20:16:27', 1, 0.88, 'verification_images/2_1742674587.jpg', '49.144.35.222', NULL),
(3, 2, '2025-03-22 20:25:44', 1, 0.88, 'verification_images/2_1742675144.jpg', '49.144.35.222', NULL),
(4, 2, '2025-03-22 20:25:48', 1, 0.86, 'verification_images/2_1742675148.jpg', '49.144.35.222', NULL),
(5, 2, '2025-03-22 20:27:58', 1, 0.87, 'verification_images/2_1742675278.jpg', '49.144.35.222', NULL),
(6, 2, '2025-03-23 17:10:15', 1, 0.87, 'verification_images/2_1742749815.jpg', '49.144.35.222', NULL),
(7, 2, '2025-03-23 17:10:17', 1, 0.87, 'verification_images/2_1742749817.jpg', '49.144.35.222', NULL),
(8, 2, '2025-03-23 17:14:24', 1, 0.89, 'verification_images/2_1742750064.jpg', '49.144.35.222', NULL),
(9, 2, '2025-03-23 18:51:16', 1, 0.87, 'verification_images/2_1742755876.jpg', '49.144.35.222', NULL),
(10, 2, '2025-03-23 18:51:18', 1, 0.88, 'verification_images/2_1742755878.jpg', '49.144.35.222', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `face_recognition_settings`
--

CREATE TABLE `face_recognition_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `face_recognition_settings`
--

INSERT INTO `face_recognition_settings` (`setting_id`, `setting_name`, `setting_value`, `description`, `updated_at`, `updated_by`) VALUES
(1, 'min_confidence_threshold', '0.6', 'Minimum confidence score required for face verification (0.0 to 1.0)', '2025-03-22 17:17:35', NULL),
(2, 'max_face_distance', '0.6', 'Maximum allowed distance between face encodings', '2025-03-22 17:17:35', NULL),
(3, 'required_face_size', '150', 'Minimum required face size in pixels', '2025-03-22 17:17:35', NULL),
(4, 'store_verification_images', 'true', 'Whether to store verification attempt images', '2025-03-22 17:17:35', NULL),
(5, 'verification_image_retention_days', '30', 'Number of days to retain verification images', '2025-03-22 17:17:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `govt_details`
--

CREATE TABLE `govt_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sss_number` varchar(10) DEFAULT NULL COMMENT 'SSS Number - 10 digits (format: ##-#######-#)',
  `tin_number` varchar(12) DEFAULT NULL COMMENT 'TIN Number - 9 or 12 digits (format: ###-###-### or ###-###-###-###)',
  `philhealth_number` varchar(12) DEFAULT NULL COMMENT 'PhilHealth Number - 12 digits (format: ##-#########-#)',
  `pagibig_number` varchar(12) DEFAULT NULL COMMENT 'Pag-IBIG MID Number - 12 digits (format: ####-####-####)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `govt_details`
--

INSERT INTO `govt_details` (`id`, `user_id`, `sss_number`, `tin_number`, `philhealth_number`, `pagibig_number`, `created_at`, `updated_at`) VALUES
(1, 1, '75-5235451', '981-984-619-', '85-130412691', '5187-5619-98', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(2, 2, '27-1670236', '449-515-155-', '95-766285166', '8844-1905-81', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(3, 3, '58-9706797', '560-556-822-', '90-131447580', '1131-2130-36', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(4, 6, '2517428215', '900462561', '909307614645', '603083525856', '2025-08-19 11:09:39', '2025-09-04 15:40:57'),
(5, 8, '36-6333449', '908-683-594-', '27-181839041', '5021-2573-60', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(6, 10, '53-5525388', '792-775-211-', '53-225516868', '1064-2956-62', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(7, 22, '37-6556237', '490-923-461-', '90-411421108', '9791-4642-23', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(8, 23, '49-1488087', '329-998-208-', '87-223575766', '6119-9936-43', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(9, 35, '89-6760507', '238-456-170-', '64-573923855', '5827-6170-96', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(10, 37, '62-6405823', '656-849-153-', '58-815138889', '1976-3199-90', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(11, 38, '49-8328424', '622-551-867-', '82-158439587', '7968-4040-32', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(12, 39, '93-8496308', '139-380-978-', '35-662361002', '2071-5676-17', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(13, 46, '41-2092962', '432-396-231-', '13-927768988', '2397-2750-10', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(14, 47, '87-9748673', '610-916-456-', '85-458148053', '9076-4421-87', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(15, 49, '33-2002975', '190-584-665-', '82-414160565', '1139-7251-17', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(16, 50, '19-5895491', '963-839-575-', '14-349069822', '9463-5045-11', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(17, 51, '83-3074776', '838-612-922-', '55-113704625', '5401-3513-62', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(18, 52, '70-3349703', '227-388-919-', '96-972351687', '2757-9621-75', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(19, 54, '70-3489256', '737-763-967-', '62-417744025', '4675-1715-46', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(20, 55, '41-9450219', '503-403-420-', '12-682552646', '4970-3167-74', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(21, 56, '51-8718805', '539-699-131-', '83-949096432', '8443-6303-24', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(22, 57, '16-3961991', '679-930-901-', '32-672726060', '1582-7834-84', '2025-08-19 11:09:39', '2025-08-19 11:09:39'),
(28, 63, '3456515612', '123213213213', '123213213213', '123213212132', '2025-08-19 17:06:16', '2025-08-29 18:13:02'),
(29, 64, '3545623132', '445132131654', '774546116194', '957498462162', '2025-08-19 18:32:01', '2025-08-19 18:40:13'),
(30, 65, '4654564513', '899879451313', '897612132185', '131321245456', '2025-08-26 11:58:25', '2025-08-26 11:58:25'),
(31, 66, '5456121321', '564498446213', '165156112313', '489465132132', '2025-08-26 12:13:12', '2025-08-26 12:22:24'),
(32, 67, '3548421321', '321564665456', '789541621213', '877465121347', '2025-08-26 12:25:39', '2025-08-26 12:25:39'),
(33, 68, '5465456445', '184915315889', '116565154561', '781651651651', '2025-09-09 14:43:03', '2025-09-12 05:25:17'),
(34, 69, '6789012345', '890123456', '789012345678', '123456789012', '2025-09-12 00:39:05', '2025-09-12 05:22:36'),
(38, 73, '0000000000', '000000000000', '000000000000', '000000000000', '2025-09-16 07:34:47', '2025-09-16 07:46:28'),
(39, 74, '1111111111', '000000000000', '000000000000', '000000000000', '2025-09-18 02:28:35', '2025-09-18 02:29:34');

-- --------------------------------------------------------

--
-- Table structure for table `guard_faces`
--

CREATE TABLE `guard_faces` (
  `id` int(11) NOT NULL,
  `guard_id` int(11) NOT NULL,
  `face_descriptor` text NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guard_faces`
--

INSERT INTO `guard_faces` (`id`, `guard_id`, `face_descriptor`, `profile_image`, `created_at`) VALUES
(1, 2, '[-0.10485482215881348,0.07141751796007156,0.014067431911826134,-0.04222659766674042,-0.03208447992801666,-0.11330995708703995,-0.0471605509519577,-0.17564710974693298,0.06738220900297165,-0.04918129742145538,0.3352760076522827,-0.12169769406318665,-0.1564052402973175,-0.12771852314472198,-0.0734044536948204,0.1809317022562027,-0.203342467546463,-0.09425954520702362,-0.07584238052368164,-0.025455746799707413,0.09092779457569122,-0.02962992712855339,-0.0023825003299862146,0.09393575042486191,-0.0556538924574852,-0.2702133059501648,-0.10622873157262802,-0.1195850744843483,0.013805779628455639,-0.049237534403800964,-0.0487818717956543,0.039433788508176804,-0.21229144930839539,-0.07828523963689804,0.02318704128265381,0.016766179352998734,-0.04569201543927193,-0.053072236478328705,0.16623635590076447,-0.008465816266834736,-0.14151565730571747,0.017884597182273865,0.04727283865213394,0.23359498381614685,0.17839780449867249,0.0790528655052185,-0.012000028043985367,-0.07953815162181854,0.07238748669624329,-0.1860937923192978,-0.024495285004377365,0.1363821029663086,0.13885515928268433,0.05391274765133858,0.009896008297801018,-0.11997223645448685,0.018813321366906166,0.049242861568927765,-0.12725034356117249,0.00888497568666935,0.0555352047085762,-0.09127312153577805,-0.009390263818204403,-0.12594418227672577,0.2588065266609192,0.08259838074445724,-0.145393967628479,-0.08486485481262207,0.12495400011539459,-0.0719936266541481,-0.06595107913017273,0.0637834444642067,-0.14893417060375214,-0.18924519419670105,-0.2967539131641388,0.02245156280696392,0.4356582462787628,0.060535091906785965,-0.17355191707611084,0.0010129599831998348,-0.09560444951057434,-0.0033439695835113525,0.10234351456165314,0.03401903063058853,-0.018727988004684448,-0.031011389568448067,-0.10328749567270279,-0.009275275282561779,0.19904407858848572,-0.10101055353879929,-0.0323391892015934,0.17643709480762482,-0.09616518020629883,0.07096364349126816,-0.02298959344625473,-0.001557988696731627,-0.011940188705921173,0.06715782731771469,-0.0472133494913578,0.08098182082176208,-0.022242102771997452,-0.0537390261888504,-0.008904585614800453,0.06018822267651558,-0.08943402767181396,0.01856767013669014,0.037997279316186905,0.03293556347489357,-0.038898419588804245,0.01873811148107052,-0.1669185906648636,-0.0541628822684288,0.09308222681283951,-0.2563892900943756,0.25006669759750366,0.16675272583961487,0.052792586386203766,0.08259537816047668,0.07682106643915176,0.0646570473909378,-0.046133968979120255,-0.03339207172393799,-0.17671649158000946,-0.049506645649671555,0.12195847183465958,-0.0036425800062716007,0.06414271146059036,-0.015984026715159416]', 'face_profiles/2_1742671321.jpg', '2025-03-22 18:16:49'),
(2, 3, '[-0.13777972757816315,0.09598755091428757,0.033233948051929474,-0.03432667627930641,-0.06914833188056946,-0.050469886511564255,-0.006663312669843435,-0.12093514949083328,0.13857145607471466,-0.017646973952651024,0.3118540346622467,-0.09069893509149551,-0.18190181255340576,-0.147128164768219,-0.00489212479442358,0.17386873066425323,-0.25183436274528503,-0.14764301478862762,-0.09291239082813263,-0.050079748034477234,0.038000866770744324,-0.004378968849778175,0.029356777667999268,0.03937738761305809,-0.17801080644130707,-0.35904112458229065,-0.044414132833480835,-0.07416529953479767,0.0530545674264431,-0.02310965768992901,-0.02165956236422062,0.07813643664121628,-0.29502201080322266,-0.1315760314464569,0.05423358827829361,0.10687444359064102,0.03436713293194771,-0.00828336738049984,0.19193920493125916,-0.03424558788537979,-0.19258978962898254,-0.011907101608812809,0.107168048620224,0.26899653673171997,0.12490235269069672,0.06753004342317581,-0.026012413203716278,-0.07758599519729614,0.08516740053892136,-0.1886604279279709,0.02754451520740986,0.14758582413196564,0.08428694307804108,0.0681285634636879,-0.05470045655965805,-0.1391720026731491,0.019350742921233177,0.11197680979967117,-0.1677985042333603,-0.0337916798889637,0.06463884562253952,-0.07973165810108185,-0.06567375361919403,-0.09472133964300156,0.25183549523353577,0.0821763426065445,-0.10965652018785477,-0.14781048893928528,0.048133280128240585,-0.062038954347372055,-0.033943142741918564,0.07932904362678528,-0.14259372651576996,-0.1498345136642456,-0.39324697852134705,0.035804010927677155,0.42508062720298767,0.012970758602023125,-0.21653370559215546,-0.006770802196115255,-0.04693962261080742,0.011962336488068104,0.1341957151889801,0.0853850245475769,-0.054332539439201355,0.07689592242240906,-0.11913467943668365,0.06945404410362244,0.18384678661823273,-0.050900042057037354,-0.03818963095545769,0.17499268054962158,-0.006961014121770859,0.07350754737854004,0.025785084813833237,0.07149279862642288,-0.05636964365839958,0.023466864600777626,-0.15193577110767365,0.022312352433800697,0.09189727157354355,0.057218287140131,0.008988434448838234,0.10093997418880463,-0.12542946636676788,0.0699659064412117,0.05717448145151138,-0.0036630535032600164,0.01112693827599287,-0.02490049973130226,-0.052109941840171814,-0.13348117470741272,0.09114385396242142,-0.2360432893037796,0.18189983069896698,0.1810983568429947,-0.02687298133969307,0.15366989374160767,0.08208709955215454,0.05882274731993675,-0.0673801451921463,0.04298737645149231,-0.15006589889526367,-0.014797558076679707,0.12594696879386902,-0.030278820544481277,0.12156166136264801,-0.029506392776966095]', 'face_profiles/guard_3.jpg', '2025-03-23 16:17:42'),
(3, 37, '[-0.14056318998336792,0.11344368755817413,0.10038354247808456,0.003317062044516206,-0.04618147015571594,-0.10819223523139954,0.0020680935122072697,-0.12297313660383224,0.06127319484949112,-0.025154516100883484,0.3061407804489136,-0.1361839920282364,-0.17343822121620178,-0.1167704313993454,-0.0654451921582222,0.16142168641090393,-0.19890913367271423,-0.0847950354218483,-0.007960253395140171,0.02002350240945816,0.1244579628109932,-0.02430245466530323,-0.032497383654117584,0.005475921090692282,-0.0003212928422726691,-0.3048587441444397,-0.09000057727098465,-0.11485044658184052,0.11038543283939362,-0.01460068579763174,-0.020108956843614578,0.006957724690437317,-0.23767763376235962,-0.06201865151524544,-0.014926095493137836,0.000720309151802212,-0.01546004880219698,-0.02631547302007675,0.16912034153938293,-0.05060094594955444,-0.18534821271896362,-0.007108327932655811,0.04678897559642792,0.23777110874652863,0.2015886753797531,0.07292108237743378,-0.01589231751859188,-0.07392829656600952,0.05619532987475395,-0.1678067147731781,-0.002760064322501421,0.1388343721628189,0.14740240573883057,0.066065713763237,0.010977067984640598,-0.13029780983924866,0.011141598224639893,0.03853417932987213,-0.16196531057357788,0.04104291647672653,0.08929241448640823,-0.06952762603759766,0.013126282021403313,-0.10310583561658859,0.264193058013916,0.030124418437480927,-0.10408168286085129,-0.15314461290836334,0.1489407867193222,-0.14477011561393738,-0.06020626425743103,0.08121654391288757,-0.1363060623407364,-0.1333518922328949,-0.33696794509887695,0.019337698817253113,0.3791496753692627,0.08396980166435242,-0.15997344255447388,0.04617151618003845,-0.09585560858249664,-0.0008433855837211013,0.14152377843856812,0.08775563538074493,0.01742749661207199,-0.0007575012859888375,-0.10792022198438644,-0.055793777108192444,0.18487335741519928,-0.1065804585814476,-0.03261907398700714,0.1785362958908081,-0.08981075137853622,0.038574960082769394,-0.06328418105840683,-0.048847831785678864,0.003856871509924531,0.04178749769926071,-0.058625124394893646,0.010832349769771099,-0.03578612580895424,-0.0484536737203598,0.0004705842293333262,0.12412869930267334,-0.14610064029693604,0.022257454693317413,0.011955141089856625,0.08547943830490112,-0.02489638701081276,-0.015161392278969288,-0.0978085920214653,-0.02278667315840721,0.09475284069776535,-0.24186556041240692,0.28359028697013855,0.15278314054012299,0.013995382003486156,0.05577262490987778,0.07689745724201202,0.062177084386348724,-0.02265118435025215,-0.03648832440376282,-0.18663328886032104,-0.025071673095226288,0.07222127914428711,-0.03471880778670311,0.061995454132556915,0.014487776905298233]', 'face_profiles/guard_37.jpg', '2025-04-28 04:39:18'),
(4, 38, '[-0.128128781914711,0.07268238812685013,0.10462052375078201,-0.01779523305594921,-0.0853804424405098,-0.12005873024463654,-0.007629510015249252,-0.1303025335073471,0.04600043594837189,-0.03975675627589226,0.27663135528564453,-0.12056301534175873,-0.15322832763195038,-0.10825285315513611,-0.0521276630461216,0.16882599890232086,-0.1781223714351654,-0.06939545273780823,-0.0558452233672142,0.014676757156848907,0.08642406016588211,-0.017876368016004562,0.028483722358942032,0.030792202800512314,-0.013898991048336029,-0.2802848517894745,-0.1236993819475174,-0.09842889755964279,0.03524026274681091,-0.0633922666311264,-0.02277272194623947,0.02633582428097725,-0.20042744278907776,-0.08915743976831436,-0.001331557403318584,0.05335206165909767,-0.02747335657477379,-0.07373765110969543,0.15354330837726593,-0.0779268890619278,-0.16427567601203918,0.006523688789457083,0.04207673296332359,0.21279698610305786,0.18850694596767426,0.044520530849695206,-0.02235051989555359,-0.07098649442195892,0.03717091679573059,-0.14301234483718872,0.020168906077742577,0.11149497330188751,0.12341756373643875,0.04023369401693344,0.04589281976222992,-0.0752050057053566,0.04609062522649765,0.08747493475675583,-0.1463337242603302,0.02007748745381832,0.09704570472240448,-0.0732211098074913,0.037563882768154144,-0.10721098631620407,0.26462140679359436,0.068648561835289,-0.10766492784023285,-0.1298338919878006,0.13870498538017273,-0.09687281399965286,-0.06112942099571228,0.05159858241677284,-0.1437997967004776,-0.14600564539432526,-0.277327299118042,0.02563423290848732,0.4048785865306854,0.09864655137062073,-0.20424190163612366,0.025289589539170265,-0.11154737323522568,-0.02072622999548912,0.10730811953544617,0.08406449854373932,0.0023754590656608343,0.011131005361676216,-0.12895946204662323,-0.02765534445643425,0.15834513306617737,-0.08178597688674927,-0.036629244685173035,0.20040249824523926,-0.06625580042600632,0.014967852272093296,0.005692770704627037,-0.07048920542001724,-0.019528677687048912,0.05828205496072769,-0.07933329790830612,0.006340572610497475,0.00944818276911974,-0.08750081807374954,-0.006088752765208483,0.1180429756641388,-0.0795900896191597,0.02918950654566288,-0.004863782320171595,0.07187087088823318,-0.013532876968383789,0.001514481264166534,-0.13607211410999298,-0.05570943281054497,0.12077163904905319,-0.2253546118736267,0.2318873256444931,0.11866297572851181,-0.0065855043940246105,0.06343255192041397,0.0886925458908081,0.1375768482685089,-0.024551812559366226,-0.014519359916448593,-0.15726099908351898,-0.0029212050139904022,0.10748211294412613,-0.015848509967327118,0.11255813390016556,0.04112761840224266]', 'face_profiles/guard_38.jpg', '2025-04-28 08:49:57'),
(5, 56, '[-0.06834924966096878,0.060460105538368225,0.0034771314822137356,-0.08914816379547119,-0.06460975110530853,0.012529004365205765,-0.0830722451210022,-0.14325685799121857,0.1830139458179474,-0.1704127937555313,0.24667206406593323,-0.09034456312656403,-0.21271798014640808,-0.0451815165579319,-0.06540323048830032,0.21963603794574738,-0.20749396085739136,-0.12481853365898132,-0.03409465402364731,0.03409280627965927,0.059427693486213684,-0.031978704035282135,-0.00805218517780304,0.09464391320943832,-0.04079486057162285,-0.33964720368385315,-0.15255454182624817,-0.08626005053520203,-0.0362824872136116,-0.06196720153093338,-0.043420691043138504,0.021504124626517296,-0.17996780574321747,-0.008152782917022705,-0.025951571762561798,0.0535571314394474,-0.04253120347857475,-0.1393224000930786,0.19621241092681885,0.018205907195806503,-0.28624314069747925,0.04296555742621422,0.0386396087706089,0.2078353762626648,0.17580746114253998,0.017980067059397697,8.634291589260101e-5,-0.13308513164520264,0.1282094269990921,-0.1457262486219406,0.024937044829130173,0.16153760254383087,0.1214926615357399,0.028652716428041458,-0.02271394059062004,-0.11720140278339386,-0.015797888860106468,0.09851409494876862,-0.13208000361919403,-0.007879209704697132,0.09779282659292221,-0.0674324482679367,-0.009158950299024582,-0.15110522508621216,0.23862288892269135,0.16942672431468964,-0.15975263714790344,-0.2220563143491745,0.14842207729816437,-0.16620926558971405,-0.07576780021190643,0.09904660284519196,-0.18807201087474823,-0.2055819183588028,-0.30135926604270935,-0.005404103547334671,0.330656498670578,0.07224320620298386,-0.13320426642894745,0.009220628067851067,-0.06889905780553818,-0.006560042966157198,0.06595198810100555,0.2256549745798111,0.034018829464912415,0.018068905919790268,-0.03786585107445717,-0.05312233418226242,0.2504390478134155,-0.07077105343341827,-0.04440338537096977,0.23580166697502136,-0.07390490919351578,0.02026280015707016,-0.034234121441841125,0.032174669206142426,-0.04146815091371536,0.015288508497178555,-0.08871379494667053,0.02135687880218029,-0.03565675765275955,-0.02854219824075699,-0.04108724743127823,0.1068907156586647,-0.14491215348243713,0.0782192125916481,-0.03204633295536041,0.04711894690990448,0.04244781285524368,0.00508489366620779,-0.12964074313640594,-0.08638168126344681,0.1691025197505951,-0.2363796830177307,0.1637418270111084,0.12767718732357025,0.07498989999294281,0.06519313156604767,0.12237659841775894,0.16051717102527618,-0.0502629317343235,-0.04461044818162918,-0.27012529969215393,-0.005745215341448784,0.09737571328878403,-0.008679618127644062,0.06748174875974655,-0.02267780900001526]', 'face_profiles/guard_56.jpg', '2025-07-15 19:05:37'),
(6, 51, '[-0.2004828304052353,0.10671041905879974,0.06918257474899292,-0.0285650547593832,-0.0668213739991188,-0.09912829846143723,-0.004192967899143696,-0.17684555053710938,0.12009075284004211,0.0036566411145031452,0.2830831706523895,-0.1267693042755127,-0.2228686660528183,-0.1438540369272232,-0.014565825462341309,0.21505333483219147,-0.23248305916786194,-0.11690225452184677,-0.06867093592882156,-0.040484920144081116,0.05626777186989784,-0.026508860290050507,0.05629510059952736,0.06341882795095444,-0.1354389637708664,-0.36125391721725464,-0.08119131624698639,-0.0967337116599083,0.04004010185599327,-0.021825652569532394,-0.052456799894571304,0.05499935895204544,-0.2342318296432495,-0.12185376137495041,0.03937787562608719,0.08882462233304977,0.0048302854411304,-0.040547456592321396,0.20528176426887512,-0.05124928429722786,-0.22645166516304016,-0.05482030287384987,0.10688979923725128,0.23330999910831451,0.15874716639518738,0.05040179193019867,0.00877828523516655,-0.09042820334434509,0.05288950353860855,-0.18740832805633545,0.0500057153403759,0.13362397253513336,0.07148667424917221,0.06753382831811905,-0.05920013412833214,-0.1383470743894577,-0.02143489569425583,0.11888137459754944,-0.18651171028614044,-0.02820848859846592,0.10112106055021286,-0.09918376058340073,-0.08748555183410645,-0.06445717811584473,0.26642686128616333,0.1421283334493637,-0.14239145815372467,-0.13008709251880646,0.08500832319259644,-0.0793101042509079,-0.006453271489590406,0.08765291422605515,-0.16306906938552856,-0.16275466978549957,-0.371944785118103,0.03230394050478935,0.45044100284576416,0.004096523392945528,-0.1756739765405655,-0.04222756251692772,-0.11930175125598907,0.022749783471226692,0.12320619076490402,0.0949101448059082,-0.03677074983716011,0.04852573573589325,-0.0883496031165123,0.04916268214583397,0.1536676287651062,-0.07689633220434189,-0.04288969188928604,0.199904203414917,0.003215857082977891,0.06331170350313187,-0.0025055441074073315,0.09468888491392136,-0.024496067315340042,-0.003445093287155032,-0.10590700805187225,-0.01174084097146988,0.09193278104066849,0.03653886541724205,0.04467576742172241,0.09884539991617203,-0.14062517881393433,0.054257262498140335,0.08420807123184204,0.02550201676785946,0.03747591748833656,-0.03175890073180199,-0.055342938750982285,-0.14397791028022766,0.09981196373701096,-0.23599597811698914,0.17674829065799713,0.18704326450824738,-0.03171579912304878,0.13416139781475067,0.055160827934741974,0.07800155133008957,-0.04133229702711105,-0.010406662710011005,-0.11055616289377213,0.036010026931762695,0.15449653565883636,0.008809994906187057,0.1134900450706482,-0.013509322889149189]', 'face_profiles/guard_51.jpg', '2025-10-07 08:48:17');

-- --------------------------------------------------------

--
-- Table structure for table `guard_locations`
--

CREATE TABLE `guard_locations` (
  `location_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `designated_latitude` decimal(10,8) NOT NULL,
  `designated_longitude` decimal(11,8) NOT NULL,
  `allowed_radius` int(11) NOT NULL DEFAULT 100,
  `is_active` tinyint(1) DEFAULT 1,
  `is_primary` tinyint(1) DEFAULT 1,
  `daily_rate` decimal(10,2) DEFAULT 0.00,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guard_locations`
--

INSERT INTO `guard_locations` (`location_id`, `user_id`, `location_name`, `designated_latitude`, `designated_longitude`, `allowed_radius`, `is_active`, `is_primary`, `daily_rate`, `assigned_by`, `assigned_at`, `created_at`, `updated_at`) VALUES
(21, 3, 'San Pedro Laguna', 14.36394350, 121.05830000, 100, 1, 1, 560.00, 8, '2025-04-25 11:11:38', '2025-04-25 11:11:38', '2025-05-27 11:01:58'),
(28, 2, 'NCR', 14.59044920, 120.98036210, 100, 1, 1, 695.00, 8, '2025-04-27 02:48:01', '2025-04-27 02:47:35', '2025-08-11 13:18:56'),
(30, 37, 'Bulacan', 15.00000000, 121.08333300, 100, 1, 1, 525.00, 8, '2025-04-28 06:17:03', '2025-04-28 04:37:38', '2025-06-03 07:27:43'),
(32, 38, 'Pangasinan', 15.91666700, 120.33333300, 100, 1, 1, 435.00, 8, '2025-04-28 08:46:38', '2025-04-28 08:46:38', '2025-06-03 08:39:37'),
(34, 39, 'Naga', 13.62401220, 123.18503180, 100, 1, 1, 415.00, 8, '2025-05-17 10:40:10', '2025-05-17 10:40:10', '2025-06-03 08:39:17'),
(35, 47, 'Pampanga', 15.05196350, 120.64453980, 100, 1, 1, 540.00, 8, '2025-06-03 08:31:00', '2025-06-03 08:31:00', '2025-06-03 09:11:00'),
(36, 49, 'Cavite', 14.25540730, 120.86715030, 100, 1, 1, 540.00, 8, '2025-06-03 09:24:44', '2025-06-03 09:24:44', '2025-06-03 11:25:53'),
(37, 50, 'Laguna', 14.16964760, 121.33365260, 100, 1, 1, 540.00, 8, '2025-06-03 11:33:01', '2025-06-03 11:33:01', '2025-06-03 11:38:25'),
(38, 51, 'Biñan', 14.33882590, 121.08418260, 100, 1, 1, 540.00, 8, '2025-06-03 11:40:49', '2025-06-03 11:40:49', '2025-07-15 19:38:45'),
(39, 52, 'Batangas', 13.91468260, 121.08675660, 100, 1, 1, 540.00, 8, '2025-06-03 11:47:19', '2025-06-03 11:47:19', '2025-09-19 15:28:41'),
(41, 54, 'Batangas', 13.91468260, 121.08675660, 100, 1, 1, 540.00, 8, '2025-06-04 09:45:16', '2025-06-04 09:45:16', '2025-09-19 15:28:41'),
(42, 55, 'Batangas', 13.91468260, 121.08675660, 100, 1, 1, 540.00, 8, '2025-06-04 10:32:00', '2025-06-04 10:32:00', '2025-09-19 15:28:41'),
(43, 56, 'NCR', 14.59044920, 120.98036210, 100, 1, 1, 695.00, 8, '2025-07-15 18:59:42', '2025-07-15 18:59:42', '2025-08-19 18:33:00'),
(44, 57, 'San Pedro Laguna', 14.36394350, 121.05830000, 100, 1, 1, 560.00, 35, '2025-07-15 21:15:02', '2025-07-15 21:15:02', '2025-08-19 18:32:55'),
(50, 63, 'Laguna', 14.16964760, 121.33365260, 100, 1, 1, 540.00, 8, '2025-08-19 17:06:16', '2025-08-19 17:06:16', '2025-08-19 18:17:28'),
(51, 64, 'Biñan', 14.33882590, 121.08418260, 100, 1, 1, 540.00, NULL, '2025-08-19 18:32:01', '2025-08-19 18:32:01', '2025-08-19 18:40:13'),
(52, 65, 'Laguna', 14.16964760, 121.33365260, 100, 1, 1, 540.00, NULL, '2025-08-26 11:58:25', '2025-08-26 11:58:25', '2025-08-26 11:58:25'),
(53, 66, 'Laguna', 14.16964760, 121.33365260, 100, 1, 1, 540.00, NULL, '2025-08-26 12:13:12', '2025-08-26 12:13:12', '2025-08-26 12:13:12'),
(54, 67, 'Pampanga', 15.05196350, 120.64553980, 100, 1, 1, 540.00, NULL, '2025-08-26 12:25:39', '2025-08-26 12:25:39', '2025-08-26 12:25:39');

-- --------------------------------------------------------

--
-- Table structure for table `guard_settings`
--

CREATE TABLE `guard_settings` (
  `setting_id` int(11) NOT NULL,
  `cash_bond_per_period` decimal(10,2) DEFAULT 100.00,
  `cash_bond_limit` decimal(10,2) DEFAULT 10000.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guard_settings`
--

INSERT INTO `guard_settings` (`setting_id`, `cash_bond_per_period`, `cash_bond_limit`, `updated_at`, `updated_by`) VALUES
(1, 100.00, 10000.00, '2025-06-20 11:27:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `holiday_id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(100) NOT NULL,
  `holiday_type` enum('Regular','Special Non-Working','Special Working') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_default` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`holiday_id`, `holiday_date`, `holiday_name`, `holiday_type`, `created_at`, `updated_at`, `is_default`) VALUES
(1, '2025-01-01', 'New Year\'s Day', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(2, '2025-04-01', 'Eidul-Fitar Holiday', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(3, '2025-04-09', 'The Day of Valor', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(4, '2025-04-17', 'Maundy Thursday', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(5, '2025-04-18', 'Good Friday', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(6, '2025-05-01', 'Labor Day', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(7, '2025-06-07', 'Eid al-Adha (Feast of the Sacrifice)', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(8, '2025-06-12', 'Independence Day', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(9, '2025-08-25', 'National Heroes Day', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(10, '2025-11-30', 'Bonifacio Day', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(11, '2025-12-25', 'Christmas Day', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(12, '2025-12-30', 'Rizal Day', 'Regular', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(13, '2025-01-29', 'Lunar New Year\'s Day', 'Special Non-Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(14, '2025-04-19', 'Black Saturday', 'Special Non-Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(15, '2025-08-21', 'Ninoy Aquino Day', 'Special Non-Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(16, '2025-10-31', 'Special Non-Working Day', 'Special Non-Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(17, '2025-11-01', 'All Saints\' Day', 'Special Non-Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(18, '2025-12-08', 'Feast of the Immaculate Conception', 'Special Non-Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(19, '2025-12-24', 'Christmas Eve', 'Special Non-Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(20, '2025-12-31', 'New Year\'s Eve', 'Special Non-Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(21, '2025-01-23', 'First Philippine Republic Day', 'Special Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(22, '2025-09-03', 'Yamashita Surrender Day', 'Special Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(23, '2025-09-08', 'Feast of the Nativity of Mary', 'Special Working', '2025-04-21 19:08:04', '2025-06-20 09:53:12', 1),
(25, '2026-01-01', 'New Year\'s Day', 'Regular', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(26, '2026-04-09', 'Day of Valor (Araw ng Kagitingan)', 'Regular', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(27, '2026-05-01', 'Labor Day', 'Regular', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(28, '2026-06-12', 'Independence Day', 'Regular', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(29, '2026-11-30', 'Bonifacio Day', 'Regular', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(30, '2026-12-25', 'Christmas Day', 'Regular', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(31, '2026-12-30', 'Rizal Day', 'Regular', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(32, '2026-08-24', 'National Heroes Day', 'Regular', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(33, '2026-04-02', 'Maundy Thursday', 'Regular', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(34, '2026-04-03', 'Good Friday', 'Regular', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(35, '2026-02-25', 'EDSA People Power Revolution Anniversary', 'Special Non-Working', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(36, '2026-08-21', 'Ninoy Aquino Day', 'Special Non-Working', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(37, '2026-11-01', 'All Saints\' Day', 'Special Non-Working', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(38, '2026-12-08', 'Feast of the Immaculate Conception', 'Special Non-Working', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(39, '2026-12-31', 'Last Day of the Year', 'Special Non-Working', '2025-06-20 09:38:52', '2025-06-20 10:17:01', 1),
(42, '2024-01-01', 'New Year\'s Day', 'Regular', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(43, '2024-04-09', 'Day of Valor (Araw ng Kagitingan)', 'Regular', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(44, '2024-05-01', 'Labor Day', 'Regular', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(45, '2024-06-12', 'Independence Day', 'Regular', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(46, '2024-11-30', 'Bonifacio Day', 'Regular', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(47, '2024-12-25', 'Christmas Day', 'Regular', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(48, '2024-12-30', 'Rizal Day', 'Regular', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(49, '2024-08-26', 'National Heroes Day', 'Regular', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(50, '2024-03-28', 'Maundy Thursday', 'Regular', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(51, '2024-03-29', 'Good Friday', 'Regular', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(52, '2024-02-25', 'EDSA People Power Revolution Anniversary', 'Special Non-Working', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(53, '2024-08-21', 'Ninoy Aquino Day', 'Special Non-Working', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(54, '2024-11-01', 'All Saints\' Day', 'Special Non-Working', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(55, '2024-12-08', 'Feast of the Immaculate Conception', 'Special Non-Working', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(56, '2024-12-31', 'Last Day of the Year', 'Special Non-Working', '2025-06-20 10:19:20', '2025-06-20 10:19:20', 0),
(61, '2025-07-23', 'Regular holiday', 'Regular', '2025-08-02 15:02:47', '2025-08-02 15:02:47', 0);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Leave_Type` varchar(255) NOT NULL,
  `Leave_Reason` text NOT NULL,
  `Start_Date` date NOT NULL,
  `End_Date` date NOT NULL,
  `Request_Date` timestamp NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`ID`, `User_ID`, `Leave_Type`, `Leave_Reason`, `Start_Date`, `End_Date`, `Request_Date`, `Status`, `rejection_reason`) VALUES
(8, 2, 'sick', 'I have flu sir.', '2025-05-13', '2025-05-18', '2025-05-11 16:21:32', 'Pending', NULL),
(9, 2, 'emergency', 'testing lang to', '2025-07-09', '2025-07-10', '2025-07-01 10:39:33', 'Approved', NULL),
(11, 1, 'Vacation', 'Family vacation', '2025-08-20', '2025-08-22', '2025-08-16 16:43:04', 'Approved', NULL),
(12, 2, 'Sick', 'Medical appointment', '2025-08-18', '2025-08-18', '2025-08-16 16:43:04', 'Rejected', 'Insufficient notice provided. Please submit sick leave requests at least 24 hours in advance unless it is an emergency.'),
(13, 3, 'Emergency', 'Family emergency', '2025-08-19', '2025-08-20', '2025-08-16 16:43:04', 'Pending', NULL),
(14, 1, 'Vacation', 'Family vacation - approved test', '2025-08-19', '2025-08-21', '2025-08-16 16:47:32', 'Approved', NULL),
(15, 51, 'emergency', 'may sakit po anak ko', '2025-10-07', '2025-10-09', '2025-10-07 08:55:33', 'Pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`attempt_id`, `email`, `ip_address`, `attempt_time`, `success`, `user_agent`) VALUES
(1, 'admin@gmail.com', '::1', '2025-07-08 09:45:26', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36'),
(3, 'guard@gmail.com', '::1', '2025-07-11 05:11:56', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(4, 'jc.saxophonist0629@gmail.com', '::1', '2025-07-15 11:24:55', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(5, 'guard@gmail.com', '::1', '2025-07-15 11:27:03', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(6, 'accounting@gmail.com', '::1', '2025-07-15 11:29:11', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(7, 'guard5@gmail.com', '::1', '2025-07-15 17:15:01', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(8, 'guard3@gmail.com', '::1', '2025-07-15 17:15:34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(9, 'guard2@gmail.com', '::1', '2025-07-15 17:16:11', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(10, 'guard@gmail.com', '::1', '2025-07-15 17:22:53', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(11, 'hr@gmail.com', '::1', '2025-07-15 18:54:49', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(12, 'jc.saxophonist0629@gmail.com', '::1', '2025-07-15 19:02:09', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(13, 'hr2@gmail.com', '::1', '2025-07-15 19:23:19', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(14, 'accounting@gmail.com', '192.168.1.4', '2025-07-15 20:11:43', 1, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36'),
(15, 'accounting@gmail.com', '::1', '2025-07-15 20:32:45', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(16, 'manila2@gmail.com', '::1', '2025-07-15 20:38:05', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(17, 'superadmin@gmail.com', '::1', '2025-07-15 20:39:01', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(18, 'accounting@gmail.com', '::1', '2025-07-17 17:32:09', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(19, 'accounting@gmail.com', '::1', '2025-07-17 20:55:49', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(20, 'hr@gmail.com', '::1', '2025-07-17 20:56:16', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(21, 'hr@gmail.com', '::1', '2025-07-19 10:13:06', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(22, 'hr@gmail.com', '::1', '2025-07-22 18:47:39', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(24, 'accounting@gmail.com', '::1', '2025-07-22 19:34:54', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(25, 'guard@gmail.com', '::1', '2025-07-22 20:19:57', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(26, 'guard@gmail.com', '::1', '2025-07-22 20:20:06', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(27, 'guard1@gmail.com', '::1', '2025-07-22 20:20:26', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(28, 'manila1@gmail.com', '::1', '2025-07-22 20:20:52', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(29, 'accounting@gmail.com', '::1', '2025-07-22 20:25:09', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(30, 'accounting@gmail.com', '::1', '2025-07-24 14:40:51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(31, 'superadmin@gmail.com', '::1', '2025-07-29 17:33:44', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(32, 'manila2@gmail.com', '::1', '2025-07-29 17:39:00', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(33, 'hr@gmail.com', '::1', '2025-07-29 17:41:58', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(35, 'superadmin@gmail.com', '::1', '2025-07-29 17:49:00', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(36, 'accounting@gmail.com', '::1', '2025-07-29 17:51:43', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(37, 'hr@gmail.com', '::1', '2025-07-29 18:43:49', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(38, 'accounting@gmail.com', '::1', '2025-07-29 18:56:53', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(39, 'accounting@gmail.com', '::1', '2025-07-29 19:47:47', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(40, 'manila1@gmail.com', '127.0.0.1', '2025-07-29 20:14:14', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:141.0) Gecko/20100101 Firefox/141.0'),
(41, 'accounting@gmail.com', '::1', '2025-08-02 14:12:04', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(42, 'manila@gmail.com', '::1', '2025-08-04 18:09:30', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(43, 'manila1@gmail.com', '::1', '2025-08-04 18:09:39', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(44, 'manila2@gmail.com', '::1', '2025-08-04 18:35:42', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(45, 'manila2@gmail.com', '::1', '2025-08-04 18:51:59', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(46, 'manila1@gmail.com', '::1', '2025-08-04 21:30:43', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(47, 'manila1@gmail.com', '192.168.1.4', '2025-08-04 21:52:15', 1, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36'),
(48, 'manila2@gmail.com', '::1', '2025-08-04 22:19:24', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(49, 'manila1@gmail.com', '::1', '2025-08-04 22:22:02', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(50, 'manila1@gmail.com', '::1', '2025-08-04 22:28:37', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(51, 'manila2@gmail.com', '::1', '2025-08-04 22:28:55', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(52, 'guard3@gmail.com', '::1', '2025-08-04 22:30:53', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(53, 'guard2@gmail.com', '::1', '2025-08-04 22:33:23', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(54, 'guard3@gmail.com', '::1', '2025-08-04 22:37:13', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(55, 'guard2@gmail.com', '::1', '2025-08-04 22:37:55', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(56, 'manilar2@gmail.com', '::1', '2025-08-04 22:38:21', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(57, 'manila2@gmail.com', '::1', '2025-08-04 22:38:28', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(61, 'jc.saxophonist0629@gmail.com', '::1', '2025-08-04 22:42:35', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(62, 'binan@gmail.com', '::1', '2025-08-04 22:43:53', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(64, 'superadmin@gmail.com', '::1', '2025-08-11 11:50:31', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(65, 'accounting@gmail.com', '::1', '2025-08-11 11:51:51', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(66, 'accounting@gmail.com', '::1', '2025-08-11 11:58:14', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(67, 'guard@gmail.com', '::1', '2025-08-11 12:03:25', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(68, 'manila1@gmail.com', '::1', '2025-08-11 12:03:35', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(69, 'hr@gmail.com', '::1', '2025-08-11 12:11:17', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(71, 'hr@gmail.com', '::1', '2025-08-11 12:25:16', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(72, 'superadmin@gmail.com', '::1', '2025-08-11 12:25:59', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(74, 'accounting@gmail.com', '::1', '2025-08-11 13:07:13', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(75, 'superadmin@gmail.com', '::1', '2025-08-11 13:08:35', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(76, 'accounting@gmail.com', '::1', '2025-08-11 13:08:59', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(77, 'hr@gmail.com', '::1', '2025-08-13 14:38:29', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(78, 'jasmineclaireque916@gmail.com', '::1', '2025-08-19 17:07:49', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(79, 'jasmineclaireque916@gmail.com', '::1', '2025-08-19 17:08:34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(80, 'jasmineclaireque916@gmail.com', '::1', '2025-08-19 17:08:44', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(82, 'accounting@gmail.com', '::1', '2025-08-19 18:20:05', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(83, 'jasmineclaireque916@gmail.com', '::1', '2025-08-19 18:34:29', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(84, 'hr@gmail.com', '::1', '2025-08-19 18:58:56', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(85, 'accounting@gmail.com', '::1', '2025-08-22 18:08:45', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(86, 'hr@gmail.com', '::1', '2025-08-22 18:16:11', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(87, 'manila1@gmail.com', '::1', '2025-08-22 20:45:41', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(88, 'hr@gmail.com', '::1', '2025-08-26 10:40:34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(89, 'jasmineclaireque916@gmail.com', '::1', '2025-08-26 12:03:10', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(90, 'jc.saxophonist0629@gmail.com', '::1', '2025-08-26 12:26:25', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(92, 'superadmin@gmail.com', '::1', '2025-08-26 15:54:06', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(94, 'hr@gmail.com', '::1', '2025-08-29 12:27:28', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(95, 'hr@gmail.com', '::1', '2025-08-29 13:48:38', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(96, 'hr@gmail.com', '::1', '2025-08-29 14:06:42', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(97, 'superadmin@gmail.com', '::1', '2025-08-29 14:12:03', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(98, 'superadmin@gmail.com', '::1', '2025-09-02 14:02:58', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(99, 'hr@gmail.com', '::1', '2025-09-02 14:18:47', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(100, 'superadmin@gmail.com', '::1', '2025-09-03 14:28:55', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(101, 'hr@gmail.com', '::1', '2025-09-03 14:41:32', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(102, 'superadmin@gmail.com', '::1', '2025-09-03 14:43:32', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(103, 'superadmin@gmail.com', '::1', '2025-09-03 16:08:42', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(104, 'superadmin@gmail.com', '::1', '2025-09-03 16:10:17', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(105, 'superadmin@gmail.com', '::1', '2025-09-03 16:25:25', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(106, 'superadmin@gmail.com', '192.168.68.107', '2025-09-03 16:26:27', 1, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36'),
(107, 'superadmin@gmail.com', '::1', '2025-09-03 16:27:55', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(108, 'superadmin@gmail.com', '192.168.68.107', '2025-09-03 16:34:51', 1, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36'),
(109, 'manila2@gmail.com', '::1', '2025-09-03 17:21:00', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(110, 'manila1@gmail.com', '::1', '2025-09-03 17:21:47', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(111, 'guard3@gmail.com', '::1', '2025-09-03 18:33:48', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),
(112, 'hr@gmail.com', '::1', '2025-09-04 15:40:44', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(113, 'accounting@gmail.com', '::1', '2025-09-04 15:41:05', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(114, 'accounting@gmail.com', '::1', '2025-09-05 11:46:32', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(115, 'manila1@gmail.com', '::1', '2025-09-05 12:16:38', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(116, 'laguna2@gmail.com', '::1', '2025-09-05 13:05:07', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(117, 'superadmin@gmail.com', '::1', '2025-09-05 13:09:53', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(118, 'superadmin@gmail.com', '::1', '2025-09-06 16:57:52', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(119, 'manila1@gmail.com', '::1', '2025-09-06 17:20:22', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(120, 'superadmin@gmail.com', '::1', '2025-09-06 18:42:47', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(121, 'hr@gmail.com', '::1', '2025-09-06 18:58:58', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(122, 'admin@gmail.com', '::1', '2025-09-06 19:41:30', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(123, 'admin@gmail.com', '::1', '2025-09-06 19:42:13', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(124, 'admin@gmail.com', '::1', '2025-09-06 19:46:15', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(125, 'superadmin@gmail.com', '::1', '2025-09-06 19:47:04', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0'),
(126, 'hr@gmail.com', '::1', '2025-09-09 06:40:26', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(127, 'superadmin@gmail.com', '::1', '2025-09-09 06:40:44', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(128, 'superadmin@gmail.com', '::1', '2025-09-09 06:46:19', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(129, 'hr@gmail.com', '::1', '2025-09-09 08:25:32', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(130, 'jc.saxophonist0629@gmail.com', '::1', '2025-09-09 08:49:31', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(131, 'manila1@gmail.com', '::1', '2025-09-09 08:59:39', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(132, 'jc.saxophonist0629@gmail.com', '::1', '2025-09-09 14:46:03', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(133, 'jc.saxophonist0629@gmail.com', '::1', '2025-09-09 14:46:25', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(134, 'jc.saxophonist0629@gmail.com', '::1', '2025-09-09 14:47:41', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(135, 'superadmin@gmail.com', '::1', '2025-09-12 00:35:54', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(136, 'jc.saxophonist0629@gmail.com', '::1', '2025-09-12 00:39:52', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(137, 'superadmin@gmail.com', '::1', '2025-09-12 00:59:59', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(138, 'admin@gmail.com', '::1', '2025-09-12 01:13:49', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(139, 'admin@gmail.com', '::1', '2025-09-12 01:13:59', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(140, 'admin@gmail.com', '::1', '2025-09-12 01:15:32', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(141, 'superadmin@gmail.com', '::1', '2025-09-12 01:17:32', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(142, 'superadmin@gmail.com', '::1', '2025-09-12 01:20:04', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(143, 'admin@gmail.com', '::1', '2025-09-12 01:20:15', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(144, 'admin2@gmail.com', '::1', '2025-09-12 01:20:30', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(145, 'superadmin@gmail.com', '::1', '2025-09-12 01:20:47', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(146, 'jc.saxophonist0629@gmail.com', '::1', '2025-09-12 01:22:17', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(147, 'manila1@gmail.com', '::1', '2025-09-12 01:23:47', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(148, 'manila2@gmail.com', '::1', '2025-09-12 01:24:12', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(149, 'laguna2@gmail.com', '::1', '2025-09-12 01:24:21', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(150, 'laguna1@gmail.com', '::1', '2025-09-12 01:24:34', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(151, 'admin@gmail.com', '::1', '2025-09-12 01:43:21', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(152, 'superadmin@gmail.com', '::1', '2025-09-12 06:27:28', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(153, 'superadmin@gmail.com', '::1', '2025-09-12 07:25:04', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(154, 'superadmin@gmail.com', '::1', '2025-09-12 08:10:35', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(155, 'manila1@gmail.com', '::1', '2025-09-12 08:10:55', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(156, 'hr@gmail.com', '::1', '2025-09-12 08:13:37', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(157, 'guard1@gmail.com', '::1', '2025-09-13 00:55:14', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(158, 'manila1@gmail.com', '::1', '2025-09-13 00:55:31', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(159, 'laguna2@gmail.com', '::1', '2025-09-13 00:58:16', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(160, 'superadmin@gmail.com', '::1', '2025-09-13 02:57:11', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(161, 'manila1@gmail.com', '::1', '2025-09-13 02:57:54', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(162, 'hr@gmail.com', '::1', '2025-09-13 03:01:30', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(163, 'accounting@gmail.com', '::1', '2025-09-13 03:14:44', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(164, 'manila1@gmail.com', '::1', '2025-09-13 03:15:45', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(165, 'superadmin@gmail.com', '::1', '2025-09-13 03:18:08', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(166, 'accounting@gmail.com', '::1', '2025-09-13 03:52:37', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(167, 'superadmin@gmail.com', '::1', '2025-09-14 09:13:55', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(168, 'manila1@gmail.com', '::1', '2025-09-14 10:06:12', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(169, 'accounting@gmail.com', '::1', '2025-09-14 10:27:26', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(170, 'hr@gmail.com', '::1', '2025-09-14 10:51:10', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(171, 'hr@gmail.com', '::1', '2025-09-16 05:11:48', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(172, 'superadmin@gmail.com', '::1', '2025-09-17 06:54:33', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(173, 'hr@gmail.com', '::1', '2025-09-17 06:58:08', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(174, 'manila1@gmail.com', '::1', '2025-09-17 07:02:21', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(175, 'accounting@gmail.com', '::1', '2025-09-17 07:41:39', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(176, 'manila1@gmail.com', '::1', '2025-09-18 00:14:56', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(177, 'hr@gmail.com', '::1', '2025-09-18 02:18:32', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(178, 'accounting@gmail.com', '::1', '2025-09-18 08:02:39', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0'),
(179, 'superadmin@gmail.com', '::1', '2025-09-19 15:00:28', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(180, 'accounting@gmail.com', '127.0.0.1', '2025-09-19 15:11:22', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(181, 'superadmin@gmail.com', '::1', '2025-09-22 18:21:46', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(183, 'manila1@gmail.com', '::1', '2025-09-23 11:57:08', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(185, 'hr@gmail.com', '::1', '2025-09-23 12:03:15', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(186, 'accounting@gmail.com', '::1', '2025-09-24 16:34:59', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(188, 'accounting@gmail.com', '::1', '2025-09-24 17:23:19', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(189, 'superadmin@gmail.com', '::1', '2025-09-24 17:49:38', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(190, 'superadmin@gmail.com', '::1', '2025-10-06 06:00:30', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(191, 'guard@gmail.com', '::1', '2025-10-06 06:29:41', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(192, 'manila1@gmail.com', '::1', '2025-10-06 06:29:48', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(193, 'superadmin@gmail.com', '::1', '2025-10-07 05:07:39', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(194, 'hr@gmail.com', '::1', '2025-10-07 05:08:11', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(195, 'accounting@gmail.com', '::1', '2025-10-07 05:09:05', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(196, 'manila1@gmail.com', '::1', '2025-10-07 05:12:50', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(197, 'binan@gmail.com', '::1', '2025-10-07 08:41:58', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(198, 'oic-manila@gmail.com', '::1', '2025-10-09 03:58:39', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
(199, 'oic-manila@gmail.com', '::1', '2025-10-09 03:58:56', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `Notification_ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Message` text NOT NULL,
  `Status` enum('Unread','Read') DEFAULT 'Unread',
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `Sent_Via` enum('Email','SMS','In-App') DEFAULT 'In-App'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `oic_locations`
--

CREATE TABLE `oic_locations` (
  `oic_location_id` int(11) NOT NULL,
  `oic_user_id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `oic_locations`
--

INSERT INTO `oic_locations` (`oic_location_id`, `oic_user_id`, `location_name`, `assigned_by`, `assigned_at`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 77, 'Manila', 1, '2025-10-09 03:56:32', 1, '2025-10-09 03:56:32', '2025-10-09 03:56:32'),
(2, 78, 'Bulacan', 1, '2025-10-09 03:56:32', 1, '2025-10-09 03:56:32', '2025-10-09 03:56:32'),
(3, 79, 'Batangas', 1, '2025-10-09 03:56:32', 1, '2025-10-09 03:56:32', '2025-10-09 03:56:32'),
(4, 80, 'Laguna', 1, '2025-10-09 03:56:32', 1, '2025-10-09 03:56:32', '2025-10-09 03:56:32'),
(5, 81, 'Cavite', 1, '2025-10-09 03:56:32', 1, '2025-10-09 03:56:32', '2025-10-09 03:56:32'),
(6, 78, 'Pampanga', 1, '2025-10-09 03:56:32', 1, '2025-10-09 03:56:32', '2025-10-09 03:56:32');

-- --------------------------------------------------------

--
-- Table structure for table `otp_requests`
--

CREATE TABLE `otp_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_requests`
--

INSERT INTO `otp_requests` (`id`, `user_id`, `otp`, `expires_at`) VALUES
(18, 1, '330864', '2025-03-30 19:11:08'),
(19, 1, '169054', '2025-03-30 19:21:10'),
(20, 1, '363400', '2025-03-30 19:56:23');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `email`, `token`, `otp`, `expires_at`, `used`, `created_at`, `ip_address`) VALUES
(1, 'jc.saxophonist0629@gmail.com', '17896ddba05b2c13429660c32ed8a93542c24b550657892f9eaa40d3a7304107', '427995', '2025-07-15 12:21:43', 1, '2025-07-15 10:11:43', '::1'),
(2, 'jc.saxophonist0629@gmail.com', 'f78bc948334efa38c519d9704beff07ed971d63815fd5d6415241f8d8cd4db29', '200008', '2025-07-15 12:49:08', 0, '2025-07-15 10:39:08', '::1'),
(3, 'jc.saxophonist0629@gmail.com', '464c28d2994585f5ae4206e50b38fd3e5db6db93dc99bc47dbad70eb18e793d9', '702165', '2025-07-15 12:55:57', 0, '2025-07-15 10:45:57', '::1'),
(4, 'jc.saxophonist0629@gmail.com', '0e222d0987670450c59f113ef5c941d81d870fe190151c0fe2c156e0b98c0f9c', '693129', '2025-07-15 19:03:43', 0, '2025-07-15 10:53:43', '::1'),
(5, 'jc.saxophonist0629@gmail.com', '22a2f2c96f2da36ffa8d07d98d0e0b2ee1987a06a8cef6340661a42973904f00', '679189', '2025-07-15 19:04:08', 0, '2025-07-15 10:54:08', '::1'),
(6, 'jc.saxophonist0629@gmail.com', '6e666573a4b006722f46fe3237127726ab0bf37703a83f6aa86e068ddd9cc70c', '727968', '2025-07-15 19:04:31', 0, '2025-07-15 10:54:31', '::1'),
(7, 'jc.saxophonist0629@gmail.com', '8813aeed93c128e0800d261bb43e2b2efd282c6c3566a0048476a12e3b67f2c8', '118408', '2025-07-15 19:16:02', 1, '2025-07-15 11:06:02', '::1'),
(8, 'jc.saxophonist0629@gmail.com', '9c5b23d048444d1a372b102d9f6b4ecad8deca6031aa060c75755b71e1a8ab68', '706969', '2025-07-15 19:24:26', 1, '2025-07-15 11:14:26', '::1'),
(9, 'jc.saxophonist0629@gmail.com', '198c38175041147ec649a9969db42ca720a313b8ec02c5e194a5958832dbb7b9', '151402', '2025-07-16 03:11:21', 1, '2025-07-15 19:01:21', '::1'),
(10, 'jc.saxophonist0629@gmail.com', 'b069a2b5813bd77cbf68c1b5d86849cf0b6e8b3058e5c05999a1d2f06ced9764', '384418', '2025-08-05 06:51:16', 1, '2025-08-04 22:41:16', '::1'),
(11, 'jasmineclaireque916@gmail.com', 'db3cb9399de83a46783dbca0b7831ddd24e0798ebdb53c8bfbb5362db238fbe9', '836536', '2025-08-20 01:17:08', 1, '2025-08-19 17:07:08', '::1'),
(12, 'jasmineclaireque916@gmail.com', '068040ef824c9879628f5449e0d623448c2624f2b2224d2a790244526f7168a9', '266764', '2025-08-20 02:43:50', 1, '2025-08-19 18:33:50', '::1'),
(13, 'jasmineclaireque916@gmail.com', '2d767a289b60c756763848948ecc560b427e8dddb081b424a1e0328d511473c9', '256113', '2025-08-26 20:15:09', 1, '2025-08-26 12:05:09', '::1'),
(14, 'jc.saxophonist0629@gmail.com', '1a42f7f671fca1ab002cdc5e9bdccf2d5e5acbc5eff9d9c2be7f2e21660f042a', '644608', '2025-08-26 20:36:45', 1, '2025-08-26 12:26:45', '::1'),
(15, 'jc.saxophonist0629@gmail.com', '3b6404af6e2360e570bbee309fb668c61b260e32e62341ec82b5fdfd464d0496', '288287', '2025-09-09 15:58:25', 1, '2025-09-09 08:48:25', '::1'),
(16, 'jc.saxophonist0629@gmail.com', 'eeea665320cc4723d1f48804370d43da7be57b5b38a82ab225e1a5064bca7cc8', '631375', '2025-09-12 09:31:01', 1, '2025-09-12 01:21:01', '::1'),
(17, 'jemmanuelque629@gmail.com', '93797cea79880aa87dccf5b2c9cbc54e4e451594d1cfd09a86f135df0a082607', '679802', '2025-09-23 17:23:39', 0, '2025-09-23 09:13:39', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Period_Start` date NOT NULL,
  `Period_End` date NOT NULL,
  `Reg_Hours` int(11) NOT NULL,
  `Reg_Earnings` decimal(10,2) NOT NULL,
  `OT_Hours` int(11) DEFAULT 0,
  `OT_Earnings` decimal(10,2) DEFAULT 0.00,
  `Uniform_Allowance` decimal(10,2) DEFAULT 0.00,
  `Gross_Pay` decimal(10,2) NOT NULL,
  `SSS` decimal(10,2) DEFAULT 0.00,
  `PhilHealth` decimal(10,2) DEFAULT 0.00,
  `PagIbig` decimal(10,2) DEFAULT 0.00,
  `Tax` decimal(10,2) DEFAULT 0.00,
  `Total_Deductions` decimal(10,2) DEFAULT 0.00,
  `Net_Salary` decimal(10,2) NOT NULL,
  `SSS_Loan` decimal(10,2) DEFAULT 0.00,
  `PagIbig_Loan` decimal(10,2) DEFAULT 0.00,
  `Late_Undertime` decimal(10,2) DEFAULT 0.00,
  `Cash_Advances` decimal(10,2) DEFAULT 0.00,
  `Cash_Bond` decimal(10,2) DEFAULT 0.00,
  `Holiday_Earnings` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`ID`, `User_ID`, `Period_Start`, `Period_End`, `Reg_Hours`, `Reg_Earnings`, `OT_Hours`, `OT_Earnings`, `Uniform_Allowance`, `Gross_Pay`, `SSS`, `PhilHealth`, `PagIbig`, `Tax`, `Total_Deductions`, `Net_Salary`, `SSS_Loan`, `PagIbig_Loan`, `Late_Undertime`, `Cash_Advances`, `Cash_Bond`, `Holiday_Earnings`) VALUES
(1, 2, '2024-03-01', '2024-03-15', 88, 17600.00, 10, 2000.00, 500.00, 20100.00, 900.00, 500.00, 300.00, 1600.00, 3300.00, 16800.00, 600.00, 350.00, 200.00, 1000.00, 250.00, 1000.00),
(2, 2, '2025-04-16', '2025-04-30', 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 500.00, 0.00, 0.00),
(3, 3, '2025-04-16', '2025-04-30', 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 500.00, 0.00, 0.00),
(4, 37, '2025-05-01', '2025-05-15', 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(5, 38, '2025-05-01', '2025-05-15', 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1000.00, 0.00, 0.00),
(6, 3, '2025-04-01', '2025-04-15', 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(7, 2, '2025-07-01', '2025-07-15', 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(8, 2, '2025-05-01', '2025-05-15', 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(9, 49, '2025-07-16', '2025-07-31', 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(10, 2, '2025-09-01', '2025-09-15', 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 600.00, 0.00, 0.00),
(11, 63, '2025-07-16', '2025-07-31', 0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_employees`
--

CREATE TABLE `payroll_employees` (
  `payroll_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `hourly_rate` decimal(10,2) DEFAULT 0.00,
  `daily_rate` decimal(10,2) DEFAULT 0.00,
  `regular_hours` decimal(5,2) DEFAULT 0.00,
  `regular_pay` decimal(10,2) DEFAULT 0.00,
  `night_diff_hours` decimal(5,2) DEFAULT 0.00,
  `night_diff_pay` decimal(10,2) DEFAULT 0.00,
  `rest_day_hours` decimal(5,2) DEFAULT 0.00,
  `rest_day_pay` decimal(10,2) DEFAULT 0.00,
  `holiday_hours` decimal(5,2) DEFAULT 0.00,
  `holiday_pay` decimal(10,2) DEFAULT 0.00,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `overtime_pay` decimal(10,2) DEFAULT 0.00,
  `gross_pay` decimal(10,2) DEFAULT 0.00,
  `sss_deduction` decimal(10,2) DEFAULT 0.00,
  `philhealth_deduction` decimal(10,2) DEFAULT 0.00,
  `pagibig_deduction` decimal(10,2) DEFAULT 0.00,
  `tax_deduction` decimal(10,2) DEFAULT 0.00,
  `total_deductions` decimal(10,2) DEFAULT 0.00,
  `net_pay` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_monthly_settings`
--

CREATE TABLE `payroll_monthly_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `cutoff_period` enum('1-15','16-31','full') DEFAULT 'full',
  `sss_contribution` decimal(10,2) DEFAULT 0.00,
  `philhealth_contribution` decimal(10,2) DEFAULT 0.00,
  `pagibig_contribution` decimal(10,2) DEFAULT 0.00,
  `tax_withholding` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_monthly_settings`
--

INSERT INTO `payroll_monthly_settings` (`id`, `user_id`, `year`, `month`, `cutoff_period`, `sss_contribution`, `philhealth_contribution`, `pagibig_contribution`, `tax_withholding`, `created_at`, `updated_at`, `updated_by`) VALUES
(7, 49, 2025, 9, '1-15', 200.00, 0.00, 0.00, 0.00, '2025-09-18 07:32:57', '2025-09-18 07:32:57', 6);

-- --------------------------------------------------------

--
-- Table structure for table `performance_evaluations`
--

CREATE TABLE `performance_evaluations` (
  `evaluation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `evaluation_date` date NOT NULL,
  `overall_rating` decimal(5,2) DEFAULT NULL,
  `overall_performance` varchar(100) DEFAULT NULL,
  `recommendation` enum('renewal','termination','others') DEFAULT NULL,
  `contract_term` varchar(100) DEFAULT NULL,
  `other_recommendation` text DEFAULT NULL,
  `evaluated_by` varchar(255) DEFAULT NULL,
  `client_representative` varchar(255) DEFAULT NULL,
  `gmsai_representative` varchar(255) DEFAULT NULL,
  `evaluator_id` int(11) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `client_assignment` varchar(255) DEFAULT NULL,
  `position` varchar(100) DEFAULT 'Security Guard',
  `area_assigned` varchar(255) DEFAULT NULL,
  `evaluation_period` varchar(255) DEFAULT NULL,
  `status` enum('Draft','Completed','Archived') DEFAULT 'Draft',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `evaluated_by_oic` tinyint(1) DEFAULT 0 COMMENT 'Indicates if evaluation was conducted by OIC',
  `oic_location` varchar(100) DEFAULT NULL COMMENT 'Location of the OIC who conducted the evaluation'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `performance_evaluations`
--

INSERT INTO `performance_evaluations` (`evaluation_id`, `user_id`, `evaluation_date`, `overall_rating`, `overall_performance`, `recommendation`, `contract_term`, `other_recommendation`, `evaluated_by`, `client_representative`, `gmsai_representative`, `evaluator_id`, `employee_name`, `client_assignment`, `position`, `area_assigned`, `evaluation_period`, `status`, `remarks`, `created_at`, `updated_at`, `evaluated_by_oic`, `oic_location`) VALUES
(1, 2, '2025-06-15', 87.35, 'Good (G)', 'others', NULL, 'Keep up the good work!', 'Erwin Mendoza', 'Toua Tokuchi', 'Erwin Mendoza', 8, 'Dylan Castillo Wang', 'NCR', 'Security Guard', 'NCR', 'May 2025 - July 2025', 'Completed', NULL, '2025-07-19 10:10:53', '2025-08-22 21:17:43', 0, NULL),
(2, 37, '2025-04-28', 83.25, 'Fair (F)', NULL, NULL, NULL, 'Erwin Mendoza', 'Al Pacino', 'Erwin Mendoza', 8, 'Grace Villanueva Samson', 'Bulacan', 'Security Guard', 'Bulacan', 'January 2025 - April 2025', 'Completed', NULL, '2025-07-19 10:10:53', '2025-08-22 21:15:46', 0, NULL),
(3, 37, '2025-08-22', 87.35, 'Good (G)', '', '', '', 'Erwin Mendoza', 'Al Pacino', 'Erwin Mendoza', 8, 'Grace Villanueva Samson', 'Bulacan', 'Security Guard', 'Bulacan', 'June 2025 - August 2025', 'Completed', NULL, '2025-08-22 21:08:06', '2025-08-22 21:15:41', 0, NULL),
(4, 64, '2025-09-17', 90.00, 'Outstanding (O)', '', '', '', 'Erwin Mendoza', 'Boy Abunda', 'Erwin Mendoza', 8, 'Biñan Testing Laguna', 'Biñan', 'Security Guard', 'Biñan', 'January 2023 - December 2023', 'Completed', NULL, '2025-09-17 07:26:19', '2025-09-17 07:26:19', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `Role_ID` int(11) NOT NULL,
  `Role_Name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`Role_ID`, `Role_Name`) VALUES
(1, 'Super Admin'),
(2, 'Admin'),
(3, 'HR'),
(4, 'Accounting'),
(5, 'Security Guard'),
(6, 'Pending'),
(7, 'Archived'),
(8, 'OIC');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `User_ID` int(11) NOT NULL,
  `Username` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Password_Hash` varchar(255) NOT NULL,
  `Role_ID` int(11) DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `hired_date` date DEFAULT NULL,
  `Profile_Pic` varchar(255) DEFAULT NULL,
  `First_Name` varchar(255) DEFAULT NULL,
  `Last_Name` varchar(255) DEFAULT NULL,
  `name_extension` varchar(20) DEFAULT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `phone_number` varchar(13) NOT NULL,
  `birthday` date DEFAULT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `civil_status` enum('Single','Married','Widowed','Divorced','Separated') NOT NULL DEFAULT 'Single',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `archived_at` timestamp NULL DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Username`, `Email`, `Password_Hash`, `Role_ID`, `Created_At`, `hired_date`, `Profile_Pic`, `First_Name`, `Last_Name`, `name_extension`, `middle_name`, `phone_number`, `birthday`, `sex`, `civil_status`, `status`, `archived_at`, `archived_by`, `employee_id`, `address`) VALUES
(1, 'juan.dela cruz', 'superadmin@gmail.com', '$2y$10$4o8TTcg6LGwJhh26woR5SOJNcWNMbBCzf98lLRMOIDFmBf8LNm9xG', 1, '2025-03-19 10:37:50', '2025-02-27', '../uploads/68890629cd68f.jpg', 'Juan', 'Dela Cruz', NULL, 'Santos', '09565299471', '1975-01-24', 'Male', 'Single', 'Active', NULL, NULL, 'SUPERADMIN01', 'Quezon City, Metro Manila'),
(2, 'dylan.wang', 'manila1@gmail.com', '$2y$10$3MX5J5NDshhvpMSIXXvDsePKI09Y0tJYdIEay5jL.P9XLIkZHfQxe', 5, '2025-03-19 10:42:48', '2025-03-08', '../uploads/68066006ac668.jfif', 'Dylan', 'Wang', NULL, 'Castillo', '09054552061', '1976-08-24', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD01', 'Manila, Metro Manila'),
(3, 'ramon.santos', 'guard2@gmail.com', '$2y$10$xalHF/.FRioK/mJ6jGNKMeOAOOLyhQX63l4SKEl57M41g0MnGNwAi', 5, '2025-03-22 20:36:01', '2025-02-27', NULL, 'Ramon', 'Santos', NULL, 'Enriquez', '09765716997', '2005-09-16', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD02', 'Malolos, Bulacan'),
(6, 'maricel.valdez', 'accounting@gmail.com', '$2y$10$TMPq6ufUUjVNjHvwKwAiDu/SK4usvSZVyoK7jRqCptPB7OQIzCmeu', 4, '2025-04-06 03:48:25', '2025-03-10', '../uploads/68794634aac25.avif', 'Maricel', 'Valdez', '', 'Torres', '09272591950', '2004-06-29', 'Female', 'Single', 'Active', NULL, NULL, 'ACCTG01', 'Lipa City, Batangas'),
(8, 'erwin.mendoza', 'hr@gmail.com', '$2y$10$/6IQ902izeWoY5haI6MbU.cpMY8rN9Tw.bN5WiJsoWeT8TtotZC6G', 3, '2025-04-14 16:24:10', '2025-04-11', '../uploads/68add3afcae22.avif', 'Erwin', 'Mendoza', NULL, 'Mendez', '09948197932', '2004-08-20', 'Male', 'Single', 'Active', NULL, NULL, 'HR01', 'San Fernando, Pampanga'),
(10, 'liza.domingo', 'admin@gmail.com', '$2y$10$LdI/ewV4X1JoBDFg1Hjr8uZv79BNWd33rw8AJjep63q4U89jiJ9ma', 2, '2025-04-22 15:53:26', '2025-03-25', NULL, 'Liza', 'Domingo', NULL, 'Dela Peña', '09054552061', '2024-09-16', 'Female', 'Single', 'Active', NULL, NULL, 'ADMIN01', 'Calamba, Laguna'),
(22, 'noel.de luna', 'pending1@gmail.com', '$2y$10$/mzw2didndKV/mLl8bSZ.ebkUeA/RJodcfBuD2GwesAvkaNiJ58s6', 6, '2025-04-23 05:43:44', '2025-04-15', NULL, 'Noel', 'De Luna', NULL, 'Garcia', '09565299478', '2004-07-20', 'Male', 'Single', 'Active', NULL, NULL, 'PENDING01', 'Antipolo City, Rizal'),
(23, 'karen.aquino', 'pending2@gmail.com', '$2y$10$HHT6Gi9XU7/VuStke1M8w.rBP1Be3kS.PVuehENGpFDMUEfq8aW6C', 6, '2025-04-23 06:08:24', '2025-04-05', NULL, 'Karen', 'Aquino', NULL, 'Abad', '09087778912', '2004-12-14', 'Female', 'Single', 'Active', NULL, NULL, 'PENDING02', 'Tarlac City, Tarlac'),
(35, 'jose.pagulayan', 'hr2@gmail.com', '$2y$10$pCci8rSiPnwPD6/WXP/OJ.tzeB.u4rPV1XQtLiDVzG6ivnbj..9Oa', 3, '2025-04-27 05:52:19', '2025-04-19', NULL, 'Jose', 'Pagulayan', NULL, 'Rodriguez', '09565299473', NULL, 'Male', 'Single', 'Active', NULL, NULL, 'HR02', 'Dagupan City, Pangasinan'),
(37, 'grace.samson', 'guard3@gmail.com', '$2y$10$lr4W92.U/s4s21QQ6Lq8q.72w61NgRvRvXKiH6lej7X6w1xv2Q.oK', 5, '2025-04-27 05:57:22', '2025-04-10', NULL, 'Grace', 'Samson', NULL, 'Villanueva', '09565299475', NULL, 'Female', 'Single', 'Active', NULL, NULL, 'GUARD03', 'Imus, Cavite'),
(38, 'michael.javier', 'guard5@gmail.com', '$2y$10$aLDm9bJPOuoKhwulomM5EesUtllzk8W/3QLo5MJzsAtE7uH4FR4oy', 5, '2025-04-28 08:46:38', '2025-03-30', NULL, 'Michael', 'Javier', NULL, 'Tan', '09565299474', '2004-06-29', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD04', 'Naga City, Camarines Sur'),
(39, 'catherine.lim', 'guard4@gmail.com', '$2y$10$EL9hHRjATWTkesKdbUvh7eLQk7VFHNCukZ9wgedLUz9MFlcL8MOvu', 5, '2025-05-17 10:40:10', '2025-05-11', NULL, 'Catherine', 'Lim', NULL, 'Lopez', '09565299476', '1999-12-17', 'Female', 'Single', 'Active', NULL, NULL, 'GUARD05', 'Santa Rosa, Laguna'),
(46, 'jenny.dela cruz', 'superadmin2@gmail.com', '$2y$10$Auvg1ssf/cwg0Yn2Gy4GnuyPwKhRfD9YFLknCaPuyAoY/n02D6oTq', 1, '2025-05-18 11:02:35', '2025-05-13', NULL, 'Jenny', 'Dela Cruz', NULL, 'Uy', '09565299472', '2004-06-29', 'Male', 'Single', 'Active', NULL, NULL, 'SUPERADMIN02', 'Makati City, Metro Manila'),
(47, 'patrick.ignacio', 'guard6@gmail.com', '$2y$10$EGzeNFqCm93MR1xyi3/2ZOzT3s.lhjw0gwxFA1Lw9VCGSujmW7fey', 5, '2025-06-03 08:31:00', '2025-05-27', NULL, 'Patrick', 'Ignacio', 'Jr.', 'Salvador', '09565287470', '1996-05-28', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD06', 'Dasmariñas, Cavite'),
(49, 'allan.basilio', 'guard7@gmail.com', '$2y$10$0zzjow19UfyVNwv0AISus.P3C3mgPZC7goJFLUubP7RERVeF//LwC', 5, '2025-06-03 09:24:44', '2025-05-15', NULL, 'Allan', 'Basilio', NULL, 'Roque', '09565274567', '1975-08-28', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD07', 'San Jose del Monte, Bulacan'),
(50, 'dennis.magno', 'guard8@gmail.com', '$2y$10$1DjnWPadcsQdEiiNymYjs.aCfXL5h.1AK0wnPQmjgdK9JPCdlGtF6', 5, '2025-06-03 11:33:01', '2025-05-19', NULL, 'Dennis', 'Magno', NULL, 'Cruz', '09565219471', '1977-10-05', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD08', 'Batangas City, Batangas'),
(51, 'shaira.castillo', 'binan@gmail.com', '$2y$10$PZ0HKup2cSuwCtbAwTNTZOykxmeekSnV8mZNk1nvVmQBAjyjos7PK', 5, '2025-06-03 11:40:49', '2025-05-16', NULL, 'Shaira', 'Castillo', '', 'Fernandez', '09565271475', '2000-01-30', 'Female', 'Single', 'Active', NULL, NULL, 'GUARD09', 'Biñan, Laguna'),
(52, 'bryan.tan', 'batangas2@gmail.com', '$2y$10$doiYIEoUwd3//MQguYqgtOj2bmTmUkNHXx5Ly5mwQTDnUu1HblJKi', 5, '2025-06-03 11:47:19', '2025-05-19', NULL, 'Bryan', 'Tan', '', 'Tolentino', '09565261234', '1981-07-30', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD10', 'Tanauan, Batangas'),
(54, 'rica.mercado', 'batangas1@gmail.com', '$2y$10$fo8v6fFnDuKbDF2WNreYseoohm0r0fJ6yYJeJ.IkcH15k9P0N9wfW', 5, '2025-06-04 09:45:16', '2025-05-14', NULL, 'Rica', 'Mercado', '', 'Dizon', '09564299473', '1995-08-14', 'Female', 'Single', 'Active', NULL, NULL, 'GUARD11', 'Lemery, Batangas'),
(55, 'leo.bautista', 'batangas3@gmail.com', '$2y$10$s2weSbgAendk8jU3ErpjaeM7L.L7YYnw8KxkraBXj6RyF97U/vfJO', 5, '2025-06-04 10:32:00', '2025-06-04', NULL, 'Leo', 'Bautista', '', 'Angeles', '09666666666', '2025-06-04', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD12', 'Nasugbu, Batangas'),
(56, 'ricardo.navarro', 'manila2@gmail.com', '$2y$10$unvMGUvvf2QwxzkDAzEaEemNI4ekg0IZEJokgsu0xT9NJPk08Oy0m', 5, '2025-07-15 18:59:42', '2025-06-20', '../uploads/6889077add559.jpg', 'Ricardo', 'Natividad', '', 'De Guzman', '09227541850', '2004-02-24', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD13', 'Pasig City, Metro Manila'),
(57, 'joanna.ramos', 'guard9@gmail.com', '$2y$10$0CBoAiGBw0r.FuUgHF7Y3ON4KXeeh8kgGDkpkSARHIPtfV7tAujDq', 5, '2025-07-15 21:15:02', '2025-07-02', NULL, 'Joanna', 'Ramos', NULL, 'Cruzado', '09054542161', '2004-05-13', 'Female', 'Single', 'Active', NULL, NULL, 'GUARD14', 'Lucena City, Quezon'),
(63, 'biscuit.aladdin', 'laguna1@gmail.com', '$2y$10$SSWBAs7gbD4iCiQXCnsOeePufSnF2yROvpUBISF7jZGvu50KvD3KW', 5, '2025-08-19 17:06:16', '2023-08-20', NULL, 'Biscuit', 'Aladdin', '', '', '09213213213', '2005-09-16', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD15', 'Los Baños, Laguna'),
(64, 'testing1.testing1', 'binan1@gmail.com', '$2y$10$MDE8FUa/JawbfhEDXTm5C.dx7xcc1pt1H2WcwrKtrbjeOY3vs4p0W', 5, '2025-08-19 18:32:01', '2023-06-29', NULL, 'Biñan', 'Laguna', 'Jr.', 'Testing', '09321231546', '2005-09-16', 'Male', 'Single', 'Active', NULL, NULL, 'GUARD16', 'Biñan City, Laguna'),
(65, 'belle.mariano', 'laguna2@gmail.com', '$2y$10$mfxvFuZhlgjvYhOspI17kuPPCI6.cfVetEK7CDsJSKyl7CSBksK9K', 5, '2025-08-26 11:58:25', '2025-08-14', NULL, 'Belle', 'Mariano', '', 'Palomina', '09565299479', '2005-09-13', 'Female', 'Married', 'Active', NULL, NULL, 'GUARD17', 'San Pablo City, Laguna'),
(66, 'suyou.johnson', 'laguna3@gmail.com', '$2y$10$9/QDGpd1oUQi9lHLiyIHO.hLJZ/gXkpEh97ZAtH0keAiOuUm/lH2G', 5, '2025-08-26 12:13:12', '2011-08-26', NULL, 'Suyou', 'Johnson', '', 'Hayabusa', '09565299480', '1976-01-24', 'Male', 'Separated', 'Active', NULL, NULL, 'GUARD18', 'Sta. Cruz, Laguna'),
(67, 'cici.aurora', 'pampanga@gmail.com', '$2y$10$cWoGodfNIFzTx3D7SjWUrOORHn7gPBe0nghpVPgBCOajN13AcMx2e', 5, '2025-08-26 12:25:39', '2015-06-29', NULL, 'Cici', 'Aurora', '', '', '09565299481', '1976-01-24', 'Female', 'Separated', 'Active', NULL, NULL, 'GUARD19', 'Angeles City, Pampanga'),
(68, 'test.test', 'superadmin3@gmail.com', '$2y$10$SPCAJtY8H7JYby6IjdSZk.O3KHC6a0Fnv3U.qC2ZutUct6zsfROO2', 1, '2025-09-09 14:43:03', '2025-07-09', '../uploads/68c03df71529b.jpg', 'Connor', 'McGregor', '', 'Detroit', '09885554444', '2004-06-15', 'Male', 'Single', 'Active', NULL, NULL, 'SUPER_ADMIN_03', 'Calamba City, Laguna'),
(69, 'asdas.asdas', 'admin2@gmail.com', '$2y$10$Pzhu90/XqqlNWNkygGemAeDqXPcxra39SFTZkDxABfzv8gYdww7/S', 2, '2025-09-12 00:39:05', '2023-08-21', '../uploads/68c36e5f7be61.jpg', 'Dio', 'Brando', 'Jr.', 'Joestar', '09565887421', '2004-06-29', 'Male', 'Single', 'Active', NULL, NULL, 'Admin-09-12', 'RHSU 4-A, Camp Vicente Lim, Mayapa, Calamba City, Laguna'),
(73, 'test02.test02', 'hr4@gmail.com', '$2y$10$Jz//tjhI3S57U58xX8wUu.WkoArKv2Iy7Iq95KDEpwma7Pg6PIaWu', 4, '2025-09-16 07:34:47', '2006-06-16', NULL, 'test02', 'test02', '', 'test02', '09272581765', '2004-06-29', 'Male', 'Married', 'Active', NULL, NULL, 'HR_0916', NULL),
(74, 'test08.test08', 'jemmanuelque629@gmail.com', '$2y$10$Z5IQA5ooxH.bkSWy6XdBru/64use32oLym08r11twBy83KQdjhzCm', 4, '2025-09-18 02:28:35', '2020-06-25', NULL, 'test08', 'test08', 'IV', 'test08', '09565776669', '2001-08-21', 'Female', 'Separated', 'Active', NULL, NULL, 'HR_0918', NULL),
(77, 'maria.santos', 'oic-manila@gmail.com', '$2y$10$qGeLSH2KCvbhTxqpZ55KP.DTzgjgR0j9RxxY.f4jxsigNA22ZAmtG', 8, '2025-10-09 03:56:32', '2024-01-15', NULL, 'Maria', 'Santos', NULL, 'Dela Cruz', '09171234567', '1985-03-20', 'Female', 'Married', 'Active', NULL, NULL, 'OIC001', NULL),
(78, 'carlos.rivera', 'oic-bulacan@gmail.com', '$2y$10$qGeLSH2KCvbhTxqpZ55KP.DTzgjgR0j9RxxY.f4jxsigNA22ZAmtG', 8, '2025-10-09 03:56:32', '2024-02-01', NULL, 'Carlos', 'Rivera', NULL, 'Mendoza', '09187654321', '1980-07-15', 'Male', 'Single', 'Active', NULL, NULL, 'OIC002', NULL),
(79, 'ana.garcia', 'oic-batangas@gmail.com', '$2y$10$qGeLSH2KCvbhTxqpZ55KP.DTzgjgR0j9RxxY.f4jxsigNA22ZAmtG', 8, '2025-10-09 03:56:32', '2024-03-10', NULL, 'Ana', 'Garcia', NULL, 'Lopez', '09199876543', '1988-11-08', 'Female', 'Single', 'Active', NULL, NULL, 'OIC003', NULL),
(80, 'john.torres', 'oic-laguna@gmail.com', '$2y$10$qGeLSH2KCvbhTxqpZ55KP.DTzgjgR0j9RxxY.f4jxsigNA22ZAmtG', 8, '2025-10-09 03:56:32', '2024-04-05', NULL, 'John', 'Torres', NULL, 'Villanueva', '09166789012', '1982-05-25', 'Male', 'Married', 'Active', NULL, NULL, 'OIC004', NULL),
(81, 'elena.cruz', 'oic-cavite@gmail.com', '$2y$10$qGeLSH2KCvbhTxqpZ55KP.DTzgjgR0j9RxxY.f4jxsigNA22ZAmtG', 8, '2025-10-09 03:56:32', '2024-05-20', NULL, 'Elena', 'Cruz', NULL, 'Reyes', '09123456789', '1987-09-12', 'Female', 'Married', 'Active', NULL, NULL, 'OIC005', NULL);

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `validate_phone_number` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.phone_number NOT REGEXP '^09[0-9]{9}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Phone number must start with 09 followed by 9 digits.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `remember_token` varchar(128) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`session_id`, `user_id`, `remember_token`, `ip_address`, `user_agent`, `created_at`, `expires_at`, `last_activity`, `is_active`) VALUES
('00750e7f0609dd39fe879a113c86f76f9f202962edd16c3b08962d9222a64ab5', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:23:47', '2025-09-13 09:23:47', '2025-09-12 01:24:09', 0),
('01e46ba40211db224e4abe3242d785a3de17e21ea29bf3d1ca1731083a4a0c9f', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 03:52:37', '2025-09-14 11:52:37', '2025-09-14 10:27:26', 0),
('027f90a33b18c1d03f8306d76b8b25a3bd8f7e8b97f52bab61a022b6fd251b01', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 19:47:47', '2025-07-30 21:47:47', '2025-07-30 16:22:03', 0),
('028993ffe1ed17053409df9a78c0975142f9da8784405ecb587f44f523c3150a', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-05 12:16:38', '2025-09-06 20:16:38', '2025-09-05 13:04:58', 0),
('084c638cc430b5b1d170b821ddc184f3f462cff582bb8402bb4a506417b703b8', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 12:11:17', '2025-08-12 14:11:17', '2025-08-11 12:20:17', 0),
('08e4db42d303b1558962e2bc8eda6fbc4ae4574a07f2f6281ad48d2e86f10d77', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-06 16:57:52', '2025-09-08 00:57:52', '2025-09-06 18:42:47', 0),
('09bc155a09a9452900f418f46a82fbe94d2869d2003a4594c4e05ea734b5dac5', 37, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:30:53', '2025-08-06 00:30:53', '2025-08-04 22:32:27', 0),
('0b8451810d0376d93f7adfa9afe391edd9f85957e2368b2f5f4f7770fbb77d7c', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 06:54:33', '2025-09-18 14:54:33', '2025-09-18 00:14:44', 0),
('0fb8fdc7ac9e34c777ce9e045e04ab425e26d13e7a09fa49f1b78035151cfa4e', 56, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:39:00', '2025-07-30 19:39:00', '2025-07-29 17:41:50', 0),
('0ff263d9136a4452377c75ce2807ce66f023459ccd3a6606e9720201dfd6064a', 51, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:42:35', '2025-08-06 00:42:35', '2025-08-04 22:43:42', 0),
('1007e8f281c67f9de3a1cb547baf096c08ff0be9630e331eb33d01e8fac75378', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-07 05:08:11', '2025-10-08 13:08:11', '2025-10-07 09:05:01', 1),
('151ed9b2595d51ac49f5317fcb3181dfe68b73e51c4ccb6f194f9b75effe2f20', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:02:21', '2025-09-18 15:02:21', '2025-09-18 00:14:56', 0),
('1567bb21349e905c30752cb1732dc5120e27e90c85934df29126bde0f1999fac', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-04 01:13:17', '2025-07-05 03:13:17', '2025-07-04 01:14:07', 0),
('165d4c63ca4b3108ff01b8be6dde455dd8541a7daa7aed68ff2029388e999c64', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-14 10:06:12', '2025-09-15 18:06:12', '2025-09-14 10:27:18', 0),
('17d39f72c9b905a943505b84281b3d763796d22752c16a3a3f1e4b430841de8b', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 16:10:17', '2025-09-04 18:10:17', '2025-09-03 16:10:31', 0),
('17de98141683c2e2d80bf7f0818404f208951c7b6569ac9beb265357ead6a193', 65, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 12:03:10', '2025-08-27 14:03:10', '2025-08-26 12:05:04', 0),
('18710ff2354759ebc95aa92740196445d40d54051192a97b5dd53123a54100bc', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-19 10:13:06', '2025-07-20 12:13:06', '2025-07-22 17:54:08', 0),
('19307fa61bd8cab81f041a45a5ab6550fb1387f1e5171d622b724cc79786203c', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 06:40:26', '2025-09-10 08:40:26', '2025-09-09 06:40:38', 0),
('1c9c392901aa65f15df7f52b56b624b1ed4d36536ac5b2a74d21043ff1a6c23f', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 12:25:59', '2025-08-12 14:25:59', '2025-08-11 12:26:06', 0),
('1deba5fe830fb2150197c921cbe3640a5063db143860021050866a3938980565', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 00:59:59', '2025-09-13 08:59:59', '2025-09-12 01:17:32', 0),
('1e3b0e30ec7908e2c056d2eaef09c6788596276feae2eee68c2d84326e116f05', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 17:21:47', '2025-09-04 19:21:47', '2025-09-03 18:33:42', 0),
('1faf0bb9c62b5532b07d19d5eb7915ebc8e35981c83bbc6015870b2d438f01f4', 68, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 14:46:25', '2025-09-10 16:46:25', '2025-09-09 14:47:37', 0),
('208bedfaf7b10bd0aee40e2ed32e00e2395d81cee9c47ce23db5cbb8accb6631', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-06 19:47:04', '2025-09-08 03:47:04', '2025-09-09 06:40:44', 0),
('2297335c008427effeb844168c945d77dd045dd3c5d6716037a5dd54d7e6d9eb', 69, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:22:17', '2025-09-13 09:22:17', '2025-09-12 01:23:35', 0),
('2313de0e58d83bfcc4cec83c60e53f2d06abded8af2358d0c531719f025c256a', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 13:08:35', '2025-08-12 15:08:35', '2025-08-13 14:38:11', 0),
('243d7c0191d019cfb345bd232d8ca68c8cefae34f62984e14842cfb2635ed46e', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-04 15:40:44', '2025-09-05 17:40:44', '2025-09-04 15:41:00', 0),
('26a138eba4b1b10841cfa8750f39c2e94fc393afb3d8fa7fd4600da635bf03eb', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 20:20:52', '2025-07-23 22:20:52', '2025-07-22 20:21:59', 0),
('2751437641ef4f04efd632b8531c3c2f0a1e085a19c87045b6be909a3dca4d31', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-07 05:09:05', '2025-10-08 13:09:05', '2025-10-07 09:12:13', 1),
('2a611603a733cea74c870a94dbf7d726dab4190451c23a661ef0a5a27b68635c', 6, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-19 15:11:22', '2025-09-20 23:11:22', '2025-09-22 18:21:39', 0),
('2b581a0cabc530a4abb2829725e1e476c63732979a2100bdcd2c3d9d49c258e3', 3, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:33:23', '2025-08-06 00:33:23', '2025-08-04 22:37:55', 0),
('2b6282e49e5a9d8dc3579ebb57f7a11d81867a207e0eba3da39501fdf528c9bd', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 13:07:13', '2025-08-12 15:07:13', '2025-08-11 13:08:26', 0),
('2e7698d8c449055190956f3c26b875f6f9f109d270b00ced6f4b16222da0928b', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:22:02', '2025-08-06 00:22:02', '2025-08-04 22:28:30', 0),
('2f08494bac7e694a7a0fe638c523a01d40c8aca1905bfc09e93a172b469d4c3d', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 03:18:08', '2025-09-14 11:18:08', '2025-09-14 09:08:09', 0),
('2f744af3703cfec22e061593bed9a2577bb39835568aaa52edb45cab3b7ea3dd', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-04 00:37:23', '2025-07-05 02:37:23', '2025-07-04 00:43:51', 0),
('3012e0589c085574c03b9147852c180c5892bdd8d75daf9af9fee0bd3a569005', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:20:47', '2025-09-13 09:20:47', '2025-09-12 06:04:59', 0),
('332d9f4f48a643ac92983c5f74c37176574b5d8af610ad006008d1e0f3a9ed1c', 56, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 18:35:42', '2025-08-05 20:35:42', '2025-08-04 18:51:59', 0),
('33970a1b8a56f820bb79fc2768dd3caf37ef305f35f253de0e951e0e871bc3c9', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-22 20:45:41', '2025-08-23 22:45:41', '2025-09-03 17:21:47', 0),
('362894307cf887986ce4257ea73c64d06bbb6f9fdae7d8dc8c95da3ae3139303', 65, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 00:58:16', '2025-09-14 08:58:16', '2025-09-13 03:01:02', 0),
('39388b427b5eb64b6ce4a47bf2bac102d3eb8020f3bf352c827ee4b2e11f2992', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-06 18:58:58', '2025-09-08 02:58:58', '2025-09-06 19:41:20', 0),
('39689b088e889977fe0284ce4fa315a48131d45177fbaa91ec1aaf00c2c95881', 56, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 17:21:00', '2025-09-04 19:21:00', '2025-09-03 17:21:41', 0),
('3c088a3b4ff9f4050b534b0809b99b59feb18b8ced2a0406e4981ac8d36858b0', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 11:58:14', '2025-08-12 13:58:14', '2025-08-11 12:03:09', 0),
('3c37a055783fecbc5072edacb08afb06090ede17d26d75fb399a7ee42032cc56', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:20:04', '2025-09-13 09:20:04', '2025-09-12 01:20:08', 0),
('3d5b34c15f973afb8dec5fef6289a16b52a7427aa48f9a39def9e362ee789c50', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 06:46:19', '2025-09-10 08:46:19', '2025-09-12 00:35:54', 0),
('3e47b5816ad697a54693187a12cf30aa4580d63386793fcc9bf85a04d00d69d1', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-14 10:27:26', '2025-09-15 18:27:26', '2025-09-14 10:50:57', 0),
('407b8dd8e7c620d3562c8946657a64467372b57fde275c6a92eaf49e54f8563e', 69, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 00:39:52', '2025-09-13 08:39:52', '2025-09-12 00:59:00', 0),
('4537246aee1f0cb88f94826f056d149e1baa394c697605e3b36aff6d9dcaa597', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 11:27:03', '2025-07-16 13:27:03', '2025-07-15 17:22:53', 0),
('491a34885f072384851b4b06e100db57eefcad6f4313a3ba64a386024c7e0102', 2, NULL, '192.168.1.4', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', '2025-08-04 21:52:15', '2025-08-05 23:52:15', '2025-08-04 22:22:02', 0),
('4e589c57d4620cabc6d0888dbb22607989ea84d1712e6bcdafa559e212bf07a9', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-19 18:20:05', '2025-08-20 20:20:05', '2025-08-22 18:08:45', 0),
('4eee89a9864d0e8a0cad3ffa38c431ebfe76d924c44737775f74c5757d41cad7', 56, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:38:28', '2025-08-06 00:38:28', '2025-08-11 11:24:58', 0),
('54b2cac6fdd8b836118322af14e48d80f21542af3530f8b20bac278d97abf99b', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 18:21:46', '2025-09-24 02:21:46', '2025-09-23 08:44:15', 0),
('551a4791654d72624e10f647f94992989b0306192def21ef73adb358363b42c4', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-04 00:43:51', '2025-07-05 02:43:51', '2025-07-04 00:45:46', 0),
('55222279cd7418e0862110583fd3ac1b3c1dbcd72656e93fb1bfc6d06cac52d6', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 14:06:42', '2025-08-30 16:06:42', '2025-09-02 14:18:47', 0),
('554db780178f3c7619d83e1e502268f8c9c9196331f0087c3b31e2e1663c560e', 2, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:141.0) Gecko/20100101 Firefox/141.0', '2025-07-29 20:14:14', '2025-07-30 22:14:14', '2025-08-04 18:09:39', 0),
('55c250d6735656796ebc7b13ef4ea0d3d35b087041040deb7f5dc6a779af13f8', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 09:13:55', '2025-09-15 17:13:55', '2025-09-17 06:54:19', 0),
('564c9193b457ccd5456db6cb262e49edd8000d4b8eb1cc942735a08ba9d545bd', 10, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:20:15', '2025-09-13 09:20:15', '2025-09-12 01:20:23', 0),
('57f16913ae623491a87f8b28b8a75b231dd8f1ca1e6278b4b53f0baf068a0f9b', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-07 05:07:39', '2025-10-08 13:07:39', '2025-10-07 09:17:59', 1),
('5a2edb66fe409a4288fd9ad0156c43436e6b8c9a3f63e5675ad904985fc96eee', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-04 00:47:10', '2025-07-05 02:47:10', '2025-07-04 01:13:17', 0),
('5a60787b319f3b6fbbc1992b3da3312e4bcf18246d7603b848a659de6609ddd5', 56, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 18:51:59', '2025-08-05 20:51:59', '2025-08-04 21:30:18', 0),
('5a828d546e41a8b6bfa7468b9c4beab62a5259f563a36f664974fe0988ed5a98', 10, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-12 01:15:32', '2025-09-13 09:15:32', '2025-09-12 01:20:15', 0),
('5a98c3efc5dc64e7d6a1490b546e7d70f7f9b673e7f86edc297ff2e87061d200', 38, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 17:15:01', '2025-07-16 19:15:01', '2025-07-15 17:15:29', 0),
('5ba5a1e2894601dcf72d9cfd27c1c54e007ee39cb7fc08ea4cce4a1f22ab3c2a', 56, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:19:24', '2025-08-06 00:19:24', '2025-08-04 22:21:57', 0),
('5d21b21536dd0c2e1bc8333b2d83acd11cb3b07db8c7a20a59373f4b1c0c5999', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 12:03:15', '2025-09-24 20:03:15', '2025-09-24 16:34:34', 0),
('5e64b4d6bd09f8428f59c9c402651e9f7474d93b03d8bcdb1e89a6c13804ed30', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 11:57:08', '2025-09-24 19:57:08', '2025-09-23 12:02:53', 0),
('5f07755fd7c71d4082728dbcd410a4812df7a18475e27ac189750c8240c3a0b2', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-06 17:20:22', '2025-09-08 01:20:22', '2025-09-06 18:58:43', 0),
('5f7ca280034c72679f97e8595368410b7c9cef71dc57d3fb0ca517bb4b20706e', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-04 00:45:06', '2025-07-05 02:45:06', '2025-07-04 00:47:22', 0),
('60b3ee51da606932f0344a38dd7de0c8fd50e16adcc75597a2d6c2ca27cd0971', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 18:56:53', '2025-07-30 20:56:53', '2025-07-29 19:29:36', 0),
('6381a72774f741a4d9af9697cbd3b7a086901530018124b17762c7790d0f229d', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 18:47:39', '2025-07-23 20:47:39', '2025-07-22 19:34:35', 0),
('63c11f5c738de0a8bf6421f5ba66fbf4da747b63251cd26d81fda83ca07ad005', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 06:40:44', '2025-09-10 08:40:44', '2025-09-09 06:41:01', 0),
('6491fe929873910685a7de3b6543516d585cd884943e53d5667efee1a8c303a8', 63, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-19 17:08:34', '2025-08-20 19:08:34', '2025-08-19 17:08:39', 0),
('64df051b0f11709ac167f2da8c66e9c7f549f9c37345b12276df0320e4aaa2c6', 10, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-06 19:42:13', '2025-09-08 03:42:13', '2025-09-06 19:46:15', 0),
('654faa8ba5e284aaf79483e2b4e8fd78b5d429cc346de566c3ac500840f8bc92', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:49:00', '2025-07-30 19:49:00', '2025-07-29 18:53:45', 0),
('65905353adeaef7ce5aa084d1486b354775b89dfc3eaba6d6a31f6b2ef068b8f', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 12:03:35', '2025-08-12 14:03:35', '2025-08-11 12:11:10', 0),
('67153e19058412ca85cd985bbc283086293befe0bb7b48321e76b07b8affc26d', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-18 08:02:39', '2025-09-19 16:02:39', '2025-09-19 15:11:22', 0),
('67293ac8a9996c7bd4d15fb3d481a17d27a757e241aa3de59f3d9fc53bfd2656', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-17 20:55:49', '2025-07-18 22:55:49', '2025-07-17 20:55:52', 0),
('67b785682d45394d29a94ab4714bcca407646f1ab52faf6cd6901427f0cceb56', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 12:27:28', '2025-08-30 14:27:28', '2025-08-29 13:48:28', 0),
('6c080f0dc6248e1421a40a5804113a64f66fa84c6f29a224371a31ef544aae4e', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-18 00:14:56', '2025-09-19 08:14:56', '2025-09-18 03:36:50', 0),
('6d2f63aac17e59894135e7cfc7bb19d947f695bd9c51797779fcf9adfa3a7852', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 06:58:08', '2025-09-18 14:58:08', '2025-09-18 02:18:32', 0),
('6eaac2c775aa59d649ff27b77693e7ac4e9b0d728127b90ae51c9c023c3e939e', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-13 00:55:31', '2025-09-14 08:55:31', '2025-09-13 02:57:54', 0),
('6f503428e1d1c801cef5c2bc01d2a23ea83ff805fd9930da54d965f81ab957f7', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 13:08:59', '2025-08-12 15:08:59', '2025-08-19 18:20:05', 0),
('736a3f61ce0c953499ca9cfdc86b25baa7c44e0f2b11afa027e74c62b38953bf', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-05 11:46:32', '2025-09-06 13:46:32', '2025-09-06 16:57:39', 0),
('74bc75fc278b728e954b2c6b5b0142f2e45df7c3629e7e4fb27575f5636afe99', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 18:09:39', '2025-08-05 20:09:39', '2025-08-04 18:35:34', 0),
('7618a5518326c709f2ed99623360bbb79010b5e03c6cd1736eb5835bf778f035', 55, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 11:24:55', '2025-07-16 13:24:55', '2025-07-15 11:26:39', 0),
('7667728c2a299cceb0c161ed1d41d16007275844ace0729e5736629c1ccd634c', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-13 14:38:29', '2025-08-14 16:38:29', '2025-08-19 18:57:34', 0),
('76c42e76627bf725ff5ea752e30230cf87d12f17b728f984e5b17a607a0431ba', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:28:37', '2025-08-06 00:28:37', '2025-08-04 22:28:50', 0),
('7703f59919b486adff2353f92f046818e5ad97c3b06f01b86d698648d5e9f581', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-04 00:45:46', '2025-07-05 02:45:46', '2025-07-04 00:45:48', 0),
('7a2ef5d24fd1b1dfb4d5e193e4183b503ed22dc6a8678c58274fb055c880366e', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 08:10:55', '2025-09-13 16:10:55', '2025-09-12 08:12:20', 0),
('7bb53336dcd7ce9dc1616624c46756631e0af21c323470f499e3014889ab43c7', 56, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 19:02:09', '2025-07-16 21:02:09', '2025-07-15 20:38:05', 0),
('7ce59998413788b93c9721b7ba6f06ce2c411362063b5c7992041660b0685b01', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 14:12:04', '2025-08-03 16:12:04', '2025-08-04 22:42:28', 0),
('7e4da6638c8a5909ac5fbf0e17b8b6958447e0d0bee8a97b95117490a5996429', 56, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 20:38:05', '2025-07-16 22:38:05', '2025-07-15 20:38:49', 0),
('801c1c3dbcc223d9a9f31f920292e17a92b014062b46c004b3ff5af381dfddeb', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-18 02:18:32', '2025-09-19 10:18:32', '2025-09-23 12:03:15', 0),
('80c38ce5cda9fca7c343287581c96aaee5169e3a4afa5641f4c4f23edfeed09e', 1, NULL, '192.168.68.107', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '2025-09-03 16:34:51', '2025-09-04 18:34:51', '2025-09-05 13:09:53', 0),
('81a0ab93eb75a7986e6d07a39c8cb8709c1ccb27b37eb8e32e81acb3766d1557', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-19 15:00:28', '2025-09-20 23:00:28', '2025-09-19 15:11:08', 0),
('84cf0b3eedc711ffe8ec112ed411cc64cc5a2d0fa7377311b11ff5c96e335813', 6, 'd9ef882180ff81bfc9fa6af44a961b72ff8a45e2c9583b7b747bcd59df3446ed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 11:29:11', '2025-08-14 13:29:11', '2025-07-15 20:11:43', 0),
('859433439e8b2a5720eebc9840d6fd86fb9dc7272f2b12ccd795ea1abd6ab317', 56, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:24:12', '2025-09-13 09:24:12', '2025-09-12 01:24:16', 0),
('86051e360952bbfa77016080742ec775f6b319dd6eb7ef1d90d4a0bade821ed1', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-13 03:14:44', '2025-09-14 11:14:44', '2025-09-13 03:15:36', 0),
('870dee95327d06ffb83503d2169cafe34e9e2d24a0aa5a363acb046773879a24', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 18:54:49', '2025-07-16 20:54:49', '2025-07-15 19:00:40', 0),
('87470eada43618a4c3a768e5129c01dffadb3971272b2df4653069b4bc4123f8', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 07:25:04', '2025-09-13 15:25:04', '2025-09-12 08:10:21', 0),
('8b97266f2c1d5dbb19495d1cb129393f90f86d79cd1924fd66062940b8727ec6', 51, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:43:53', '2025-08-06 00:43:53', '2025-08-04 22:44:55', 0),
('8c047bfc49adadc3c9467a01da5db981f8290340522a958a52c533f61653ca21', 65, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:24:21', '2025-09-13 09:24:21', '2025-09-12 01:24:27', 0),
('8d4ee54a2f3d61dc0d1a59be8c85d5acafe8b9baef86fd9ba2e4427823e40f71', 3, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 17:16:11', '2025-07-16 19:16:11', '2025-07-15 17:22:49', 0),
('8e54aa4bc24c34f35a3dcc1e7ccc756cbe8d2ad003b60efc30066687d1e67584', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-17 17:32:09', '2025-07-18 19:32:09', '2025-07-17 20:55:41', 0),
('9566ac0c64178a9ccc1e4b400f2558d8c86ef101dcac65281f0a54ce3f00c6d8', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-13 02:57:54', '2025-09-14 10:57:54', '2025-09-13 03:14:35', 0),
('9684fce6a7e73a2b362446669fbd89a9d5f9b465e2071f69ed3326e0b4335051', 10, 'd0713e8c42bc310b7bdd65389a68f509c3235e5704d6c8b883da0dcc7b2c145e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-08 09:45:26', '2025-08-07 11:45:26', '2025-07-08 09:45:38', 0),
('9ae2176aadc5b783ca4f0a1290bdfe4fb4c87f2537d8ba330e01bc50d7f51c1d', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 15:54:06', '2025-08-27 17:54:06', '2025-08-29 14:11:56', 0),
('9c001b46a338e9af2bac8e60e753c80384d17c5ba7ec1d6f3fb3eaecd46b6d4c', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-17 20:56:16', '2025-07-18 22:56:16', '2025-07-19 10:12:51', 0),
('9cc1270fee78146d7b6ab81784ae2105a40f5b769b5394dae83af828c495afd7', 6, NULL, '192.168.1.4', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', '2025-07-15 20:11:43', '2025-07-16 22:11:43', '2025-07-15 20:32:45', 0),
('9f593cfd349801e99562f012b72de459feebfbf9d1f20a0465d8422c0b5ee868', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 08:25:32', '2025-09-10 10:25:32', '2025-09-09 14:45:49', 0),
('a0610baf763d66ce98d6fe5c890e8ea5aa702141d588abd19e9d2a0eee52e0cc', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:40:51', '2025-07-25 16:40:51', '2025-07-24 20:37:49', 0),
('a0b51ffdad8533974db69b2922b0d641deac9a2fec78c78252189ddf1bc67ac4', 10, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-06 19:46:15', '2025-09-08 03:46:15', '2025-09-12 01:13:59', 0),
('a4096c11ef89e0b7782d7d34c575cc130a630f608d86d97e942a537085595fcf', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-11 05:11:56', '2025-07-12 07:11:56', '2025-07-11 05:12:17', 0),
('a4210796d1191e4e85394b850d7072f5ea6b7390587e53e205d6cd82a3e35114', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 14:12:03', '2025-08-30 16:12:03', '2025-09-02 14:02:58', 0),
('a4277af5ad89264739f73b8684061877321913894b39bb2c480f49f3a69c3fa0', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 16:27:55', '2025-09-04 18:27:55', '2025-09-03 16:34:51', 0),
('a4d05c673693f943ea73f8b512f962c2c2a0753c1536fde218e69667d902d3ee', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-06 06:00:30', '2025-10-07 14:00:30', '2025-10-06 06:29:35', 0),
('ab0893be618d58806ed3e28dc0b12cd0ead23b7b9818a1a9da59e5691a290aef', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 08:59:39', '2025-09-10 10:59:39', '2025-09-12 01:23:47', 0),
('ab276c76a15117dfa6f0c2a92ab97a26ba0097e0f3db4fe7658b77152e777766', 68, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 14:46:03', '2025-09-10 16:46:03', '2025-09-09 14:46:06', 0),
('ab794a8903fbc9829533440a953042bf7a533eee77951d2ed13f3c8cffed35d0', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-13 03:15:45', '2025-09-14 11:15:45', '2025-09-14 09:08:17', 0),
('ad21e76e87b030bcb74ba3c5fc16d8bfa4fd8c40d14b0bb0efae0acd32511113', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 06:27:28', '2025-09-13 14:27:28', '2025-09-12 06:54:09', 0),
('addbb2f918da1dece4df4ca2f9bb02021e7580da233e8e0ca3413618250b2ba9', 37, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:37:13', '2025-08-06 00:37:13', '2025-08-04 22:37:51', 0),
('aee3d0420e4074fb7105dbfe37d383fa98d9d9cf8dd27921089ed7c57f65473d', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 08:13:37', '2025-09-13 16:13:37', '2025-09-12 08:13:53', 0),
('af75c79157ead974d8be8955e08f3e59e65be83b382fcde0973ca0ffde72d4c1', 51, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-07 08:41:58', '2025-10-08 16:41:58', '2025-10-07 08:56:15', 1),
('b52c019f14391c5ee731cc1a1cde618c1e6d71f841d1c29bf14ce1b88e217ee0', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:51:43', '2025-07-30 19:51:43', '2025-07-29 18:43:39', 0),
('b634ead75cba2088400c17a337ba3ed93c0b2f39182f11f33b30572c87b06fa3', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-04 15:41:05', '2025-09-05 17:41:05', '2025-09-05 11:46:27', 0),
('b657238ae23713e2d3f3cff362adc566310108e94c3b0d12b30453f1d45c2883', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 14:28:55', '2025-09-04 16:28:55', '2025-09-03 14:43:32', 0),
('b66852726023cf13e92197fed500d13eee85abc568f35b057dd74a91c11bb8b3', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 17:23:19', '2025-09-26 01:23:19', '2025-09-24 17:49:30', 0),
('b69aa0ca9a64a9656f1c9cb9e6f045852c168f9a8457b7c029601a20576998d4', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-16 05:11:48', '2025-09-17 13:11:48', '2025-09-17 06:58:08', 0),
('b6b8a6e4a5b6999233c1a60f44c9b0f7571228cf57418ed425b501e5cf389742', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 21:30:43', '2025-08-05 23:30:43', '2025-08-04 21:52:15', 0),
('b838745eb9c0a47035f640e122d534d3b90e57819dd951015dee42d1fdcefc1a', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 03:01:30', '2025-09-14 11:01:30', '2025-09-14 10:51:10', 0),
('b97807b30416128df06c4801ad7fd70975ea90309a376f132591d15bbf837950', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 16:08:42', '2025-09-04 18:08:42', '2025-09-03 16:08:59', 0),
('bb2de4ec9684826b060af51e3d7ee7451e976b49d3aace99191f5fef54c72100', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 14:43:32', '2025-09-04 16:43:32', '2025-09-03 16:01:36', 0),
('be2489144fab7afe36f7ddf999deabb2173f0f46cc1600e326f64b32213c94f2', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-04 00:47:22', '2025-07-05 02:47:22', '2025-07-04 01:05:30', 0),
('be27e3b2c4a580c332382995bfb307f4433dc669d1d67054d01cfe3a2e7c42c4', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 13:48:38', '2025-08-30 15:48:38', '2025-08-29 14:06:36', 0),
('be97002c11d176910f29f0586a0b6c547f1ae853b54556ee37682aa5c8b058d6', 63, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-19 17:08:44', '2025-08-20 19:08:44', '2025-08-19 17:08:45', 0),
('bf22bbf18e036639266603b30906071aabe19e07d8ebe63b6bdd64b01328ab42', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 16:34:59', '2025-09-26 00:34:59', '2025-09-24 17:22:59', 0),
('c2a51e6c559f1a4eb6ee97d37957215d61348e7081ea3691bccd8a605d40964b', 10, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-06 19:41:30', '2025-09-08 03:41:30', '2025-09-06 19:42:13', 0),
('c3a7e0151d5aa7bfc82de9076526ba703d98287ce4d4cdbb6605eed27b233e91', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 20:25:09', '2025-07-23 22:25:09', '2025-07-24 14:16:49', 0),
('c42c01c1c375fa8eb0f967efbd652d4adc46eeefbdfb4d1677d169e31b642ce1', 37, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 18:33:48', '2025-09-04 20:33:48', '2025-09-03 19:00:56', 1),
('c540f4a6d4ddec4d5342c970cc1a9c8def3588f02fc3331364e4b233e3735b56', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-19 18:58:56', '2025-08-20 20:58:56', '2025-08-22 18:08:20', 0),
('c5be1391de7964fb70614b4456ebfeaaf48869396770b6084d29ebbb7d998c20', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-07 05:12:50', '2025-10-08 13:12:50', '2025-10-07 08:35:27', 1),
('c5cd436c27425faf9090098a88b792d37b4685b1bb56f7d2144085bbb90ad50e', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 14:18:47', '2025-09-03 16:18:47', '2025-09-03 14:41:32', 0),
('c70fa859523ca361dbc073bf0c563c6e379802286aafc1be1c180a879ebe7859', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 14:02:58', '2025-09-03 16:02:58', '2025-09-03 14:28:32', 0),
('ca736a3ba1e9b7603c808b8f5f5fdbe2bc3d5d89601791b38ce24a153a76db3b', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 19:34:54', '2025-07-23 21:34:54', '2025-07-22 20:19:48', 0),
('cb84cb3ec8d0fea9209d4315f3b312ed1c3d84653b65f210cfb536dd2dcbcaff', 63, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-19 17:07:49', '2025-08-20 19:07:49', '2025-08-19 17:08:28', 0),
('cf480c9269f31a2fd292277f0e9efa6da3a2087e26fe97f4d2f1ea7dd7053a17', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 02:57:11', '2025-09-14 10:57:11', '2025-09-13 03:03:58', 0),
('d25c77e5bc1b3fe8db825e23def613b7eaf23b0893a636dc3679d3862b652995', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 00:35:54', '2025-09-13 08:35:54', '2025-09-12 00:59:59', 0),
('d3a0691543b88b24a954cb6ef6cce118c34bbcaace25b7a7991b5fea5f57b198', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 08:10:35', '2025-09-13 16:10:35', '2025-09-12 08:10:38', 0),
('d58ffefc53b330ab1ef9801827169d81af7ac21f3bc6a71779c952dec1b1ccf0', 64, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-19 18:34:29', '2025-08-20 20:34:29', '2025-08-19 18:34:40', 1),
('d5d78f3f4ffb0c6761a8f07297a049e8e99fc432916b5b0c11f7f5f3daf32ca0', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 10:40:34', '2025-08-27 12:40:34', '2025-08-26 15:53:41', 0),
('d883e78dc39e7dccbff5a48fe365d7831db1a6fe35552efaca5d5324d72794f4', 1, NULL, '192.168.68.107', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '2025-09-03 16:26:27', '2025-09-04 18:26:27', '2025-09-03 16:27:55', 0),
('dbc6c938059f5f8b6c5d2cf9de0d93e2763140c5e64fd8857d30645371aba24b', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:33:44', '2025-07-30 19:33:44', '2025-07-29 17:38:49', 0),
('dc1cf0fb06762d2f854a5a180e4c4dd01535468d4f91f7e38e1346f881f347cc', 77, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 03:58:56', '2025-10-10 11:58:56', '2025-10-09 04:41:27', 1),
('de69f39654ba41360418ad0cd706efd537f7d83adcd2930b8cd54978121da939', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 11:51:51', '2025-08-12 13:51:51', '2025-08-11 11:58:14', 0),
('de8039d4871619277227aee404c6d951fc96258a3a69c57c118efed6cf68ec9b', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-22 18:16:11', '2025-08-23 20:16:11', '2025-08-26 10:40:16', 0),
('de856f743e4ef5252fd16618debb82e5372be3dbe17fbf3ab5c08088a52f9ce2', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 12:25:16', '2025-08-12 14:25:16', '2025-08-11 12:25:47', 0),
('dee8b9b8d648e591506cf051936f5cd47a6d9df6a6b5e3a6bbc0d38e12bb1e83', 56, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:28:55', '2025-08-06 00:28:55', '2025-08-04 22:30:48', 0),
('df1debb4a00cd1e23bb125b5c0ca4ed35091f2f342fd3a40bd51e7af7980faae', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 20:32:45', '2025-07-16 22:32:45', '2025-07-17 17:32:09', 0),
('dfd4a75bc309014d4e9a349d662cb9d240ed1db03e84a8acd9e9c48e18755a43', 37, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 17:15:34', '2025-07-16 19:15:34', '2025-07-15 17:16:07', 0),
('e12fe19e69d0c562ba072b05b00921952bc2d044a7d842fe64b51857547e07af', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-22 18:08:45', '2025-08-23 20:08:45', '2025-08-22 20:45:28', 0),
('e1c892944a3f06441fcd28bba8de52159f7426d3cd2d12a7ae6389e55b7c0870', 68, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 14:47:41', '2025-09-10 16:47:41', '2025-09-09 14:48:07', 1),
('e2089a731808a6c9d4ff729de4b274a74888934b4ef8244b545f832d240a4176', 63, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:24:34', '2025-09-13 09:24:34', '2025-09-12 01:43:14', 0),
('e2e15223bead9690bd6e54ea1e140162af07d8dba4abf45998666846af60b05a', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:17:32', '2025-09-13 09:17:32', '2025-09-12 01:20:04', 0),
('e31b5867c3aafd689e9ca164683991694571f65e85f9728993b72bdde8e319b2', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 11:50:31', '2025-08-12 13:50:31', '2025-08-11 11:58:02', 0),
('e3e5e4a2a7eb9583abe5a9ee0aa17235f596c8dc310fdc2f542e2a62d07177fe', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-06 06:29:48', '2025-10-07 14:29:48', '2025-10-07 05:12:50', 0),
('e65f70a934cfe3e8a78b8e24b177c8c7be1840f470504b77cd50f591880212b8', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-14 10:51:10', '2025-09-15 18:51:10', '2025-09-16 05:11:48', 0),
('e783588d36080753136f6c24591cdd90053bbed58bf249e8cce6d5151f82c9a3', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 18:43:49', '2025-07-30 20:43:49', '2025-08-11 12:11:17', 0),
('e97a86126647e338717eb88e3afdf01a3b6d4ce6569e8cce43703fc3321e9bc2', 65, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 08:49:31', '2025-09-10 10:49:31', '2025-09-09 08:49:37', 0),
('eb6025daaa1eba5ccda39648aaae79362f117b11947693f530bf7c213b2dd490', 65, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-05 13:05:07', '2025-09-06 21:05:07', '2025-09-05 13:09:38', 0),
('ebefa04aac4b805cdca6159c7c45836449a9696f51de595d06af55bfea80fee2', 10, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:13:59', '2025-09-13 09:13:59', '2025-09-12 01:15:32', 0),
('ed79f9f31e2a3e5a186d6adeeaccbe0b8493c5ad36388b82a6f2d91893ba0b56', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-06 18:42:47', '2025-09-08 02:42:47', '2025-09-06 19:46:08', 0),
('edd67aed30ffb5c8789776ab2a1ecce77564ce083bb5eda820bc3c00a3877c25', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 17:49:38', '2025-09-26 01:49:38', '2025-10-06 06:00:30', 0),
('eefb2b4a19b60239e781fa3c559e328746563163b907a1f60b18c18c131f29af', 35, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 19:23:19', '2025-07-16 21:23:19', '2025-07-15 19:23:19', 1),
('f13e203341e694ea1b621a2058a16ad43d7cd6d52348a4624b4fd5ac321ed790', 3, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-04 22:37:55', '2025-08-06 00:37:55', '2025-08-04 22:38:15', 0),
('f2b049ecdd1fc03f0b5b23c62689b32ff1957887f64fc154a5c49233c823e1db', 10, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-12 01:43:21', '2025-09-13 09:43:21', '2025-09-13 00:58:03', 0),
('f34100b05d177dc6064ffac4f034247abeb5c3c6d10c8301e863f3f3c7dbf949', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 16:25:25', '2025-09-04 18:25:25', '2025-09-03 16:26:27', 0),
('f37c7513e877005691eab3fa146a4577770aa31143f191da98adf62e7222480f', 6, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-17 07:41:39', '2025-09-18 15:41:39', '2025-09-18 08:02:31', 0),
('f63941503a8ee80458d312401b701109989e7cfbad6026d89de145b31c665d74', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 20:39:01', '2025-07-16 22:39:01', '2025-07-17 17:32:02', 0),
('f63f9cc5c3b3167978b720f7c96071147825b7832b871b073011a91d0681e4da', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 14:41:32', '2025-09-04 16:41:32', '2025-09-04 15:40:44', 0),
('f655ba8ba9dcaa74c4525790b8c3eda48eee0c5d5f8f9796cb8d81782dc3955b', 8, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:41:58', '2025-07-30 19:41:58', '2025-07-29 17:47:15', 0),
('f6df33475c1df074b12de5b6891049773b9cc79336a70b0f3dd153d4567c1a52', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-15 17:22:53', '2025-07-16 19:22:53', '2025-07-15 18:54:43', 0),
('fb559ad1ff3059f98a28fd94a380fe8c65e1799753ca854e63f7c5914f4dd3e7', 67, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 12:26:25', '2025-08-27 14:26:25', '2025-08-26 12:32:18', 0),
('fb6e053f4e99e3102b9d500306a080e1227bb78ac089a60dffcae1aefdda3924', 1, 'cdd7f869f4211d47b9c85d88622da040c98f732bab834861e48ebed0a619e65f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-09-05 13:09:53', '2025-10-05 21:09:53', '2025-09-06 16:57:52', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_lockouts`
--
ALTER TABLE `account_lockouts`
  ADD PRIMARY KEY (`lockout_id`),
  ADD UNIQUE KEY `idx_email_active` (`email`),
  ADD KEY `idx_locked_until` (`locked_until`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`Log_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`Applicant_ID`);

--
-- Indexes for table `archived_guards`
--
ALTER TABLE `archived_guards`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `archived_by` (`archived_by`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `archive_dtr_data`
--
ALTER TABLE `archive_dtr_data`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `archive_leave_requests`
--
ALTER TABLE `archive_leave_requests`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `designated_location_id` (`designated_location_id`),
  ADD KEY `idx_attendance_face_verified` (`face_verified`),
  ADD KEY `idx_attendance_location_verified` (`location_verified`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`Log_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `edit_attendance_logs`
--
ALTER TABLE `edit_attendance_logs`
  ADD PRIMARY KEY (`Log_ID`),
  ADD KEY `Attendance_ID` (`Attendance_ID`),
  ADD KEY `Editor_User_ID` (`Editor_User_ID`);

--
-- Indexes for table `employee_rates`
--
ALTER TABLE `employee_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `evaluation_ratings`
--
ALTER TABLE `evaluation_ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD KEY `evaluation_id` (`evaluation_id`),
  ADD KEY `criterion_name` (`criterion_name`);

--
-- Indexes for table `face_recognition_data`
--
ALTER TABLE `face_recognition_data`
  ADD PRIMARY KEY (`face_id`),
  ADD KEY `idx_face_recognition_user` (`user_id`);

--
-- Indexes for table `face_recognition_logs`
--
ALTER TABLE `face_recognition_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_face_recognition_logs_user` (`user_id`);

--
-- Indexes for table `face_recognition_settings`
--
ALTER TABLE `face_recognition_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `govt_details`
--
ALTER TABLE `govt_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `guard_faces`
--
ALTER TABLE `guard_faces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guard_id` (`guard_id`);

--
-- Indexes for table `guard_locations`
--
ALTER TABLE `guard_locations`
  ADD PRIMARY KEY (`location_id`),
  ADD UNIQUE KEY `uc_guard_primary_location` (`user_id`,`is_primary`),
  ADD KEY `idx_guard_locations_user` (`user_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `guard_settings`
--
ALTER TABLE `guard_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`holiday_id`),
  ADD KEY `idx_holiday_date` (`holiday_date`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_email_time` (`email`,`attempt_time`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempt_time`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`Notification_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `oic_locations`
--
ALTER TABLE `oic_locations`
  ADD PRIMARY KEY (`oic_location_id`),
  ADD KEY `idx_oic_user` (`oic_user_id`),
  ADD KEY `idx_assigned_by` (`assigned_by`),
  ADD KEY `idx_location_name` (`location_name`),
  ADD KEY `idx_oic_active` (`oic_user_id`,`is_active`),
  ADD KEY `idx_location_active` (`location_name`,`is_active`);

--
-- Indexes for table `otp_requests`
--
ALTER TABLE `otp_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `payroll_employees`
--
ALTER TABLE `payroll_employees`
  ADD PRIMARY KEY (`payroll_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payroll_monthly_settings`
--
ALTER TABLE `payroll_monthly_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_month_cutoff` (`user_id`,`year`,`month`,`cutoff_period`),
  ADD KEY `fk_user_settings` (`user_id`),
  ADD KEY `fk_updated_by` (`updated_by`),
  ADD KEY `idx_user_date_cutoff` (`user_id`,`year`,`month`,`cutoff_period`);

--
-- Indexes for table `performance_evaluations`
--
ALTER TABLE `performance_evaluations`
  ADD PRIMARY KEY (`evaluation_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `evaluator_id` (`evaluator_id`),
  ADD KEY `evaluation_date` (`evaluation_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_perf_user` (`user_id`),
  ADD KEY `idx_perf_evaluator` (`evaluator_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`Role_ID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD UNIQUE KEY `unique_employee_id` (`employee_id`),
  ADD KEY `fk_role_id` (`Role_ID`),
  ADD KEY `archived_by` (`archived_by`),
  ADD KEY `idx_user_status` (`status`),
  ADD KEY `idx_user_archived_at` (`archived_at`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `remember_token` (`remember_token`),
  ADD KEY `expires_at` (`expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_lockouts`
--
ALTER TABLE `account_lockouts`
  MODIFY `lockout_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `Log_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=535;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `Applicant_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `archived_guards`
--
ALTER TABLE `archived_guards`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `archive_dtr_data`
--
ALTER TABLE `archive_dtr_data`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `archive_leave_requests`
--
ALTER TABLE `archive_leave_requests`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `Log_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `edit_attendance_logs`
--
ALTER TABLE `edit_attendance_logs`
  MODIFY `Log_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `employee_rates`
--
ALTER TABLE `employee_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evaluation_ratings`
--
ALTER TABLE `evaluation_ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `face_recognition_data`
--
ALTER TABLE `face_recognition_data`
  MODIFY `face_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `face_recognition_logs`
--
ALTER TABLE `face_recognition_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `face_recognition_settings`
--
ALTER TABLE `face_recognition_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `govt_details`
--
ALTER TABLE `govt_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `guard_faces`
--
ALTER TABLE `guard_faces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `guard_locations`
--
ALTER TABLE `guard_locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `guard_settings`
--
ALTER TABLE `guard_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `Notification_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `oic_locations`
--
ALTER TABLE `oic_locations`
  MODIFY `oic_location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `otp_requests`
--
ALTER TABLE `otp_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `payroll_employees`
--
ALTER TABLE `payroll_employees`
  MODIFY `payroll_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_monthly_settings`
--
ALTER TABLE `payroll_monthly_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `performance_evaluations`
--
ALTER TABLE `performance_evaluations`
  MODIFY `evaluation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `Role_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`) ON DELETE SET NULL;

--
-- Constraints for table `archived_guards`
--
ALTER TABLE `archived_guards`
  ADD CONSTRAINT `archived_guards_ibfk_1` FOREIGN KEY (`archived_by`) REFERENCES `users` (`User_ID`),
  ADD CONSTRAINT `archived_guards_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`designated_location_id`) REFERENCES `guard_locations` (`location_id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`) ON DELETE SET NULL;

--
-- Constraints for table `edit_attendance_logs`
--
ALTER TABLE `edit_attendance_logs`
  ADD CONSTRAINT `edit_attendance_logs_ibfk_1` FOREIGN KEY (`Attendance_ID`) REFERENCES `attendance` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `edit_attendance_logs_ibfk_2` FOREIGN KEY (`Editor_User_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `employee_rates`
--
ALTER TABLE `employee_rates`
  ADD CONSTRAINT `employee_rates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `evaluation_ratings`
--
ALTER TABLE `evaluation_ratings`
  ADD CONSTRAINT `fk_ratings_evaluation` FOREIGN KEY (`evaluation_id`) REFERENCES `performance_evaluations` (`evaluation_id`) ON DELETE CASCADE;

--
-- Constraints for table `face_recognition_data`
--
ALTER TABLE `face_recognition_data`
  ADD CONSTRAINT `face_recognition_data_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `face_recognition_logs`
--
ALTER TABLE `face_recognition_logs`
  ADD CONSTRAINT `face_recognition_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `face_recognition_settings`
--
ALTER TABLE `face_recognition_settings`
  ADD CONSTRAINT `face_recognition_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `govt_details`
--
ALTER TABLE `govt_details`
  ADD CONSTRAINT `fk_govt_details_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `guard_faces`
--
ALTER TABLE `guard_faces`
  ADD CONSTRAINT `guard_faces_ibfk_1` FOREIGN KEY (`guard_id`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `guard_locations`
--
ALTER TABLE `guard_locations`
  ADD CONSTRAINT `guard_locations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `guard_locations_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `guard_settings`
--
ALTER TABLE `guard_settings`
  ADD CONSTRAINT `fk_guard_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `oic_locations`
--
ALTER TABLE `oic_locations`
  ADD CONSTRAINT `oic_locations_ibfk_1` FOREIGN KEY (`oic_user_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `oic_locations_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`User_ID`) ON DELETE SET NULL;

--
-- Constraints for table `otp_requests`
--
ALTER TABLE `otp_requests`
  ADD CONSTRAINT `otp_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_employees`
--
ALTER TABLE `payroll_employees`
  ADD CONSTRAINT `payroll_employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `payroll_monthly_settings`
--
ALTER TABLE `payroll_monthly_settings`
  ADD CONSTRAINT `fk_payroll_settings_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`User_ID`),
  ADD CONSTRAINT `fk_payroll_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `performance_evaluations`
--
ALTER TABLE `performance_evaluations`
  ADD CONSTRAINT `fk_evaluations_evaluator` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evaluations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_role_id` FOREIGN KEY (`Role_ID`) REFERENCES `roles` (`Role_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`Role_ID`) REFERENCES `roles` (`Role_ID`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`archived_by`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
