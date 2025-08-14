-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Aug 10, 2025 at 07:03 PM
-- Server version: 8.0.40
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fieldops_test`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `google_place_id` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `email`, `phone`, `address_line1`, `address_line2`, `city`, `state`, `postal_code`, `country`, `latitude`, `longitude`, `google_place_id`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'Jane', 'Ezell', 'asd@sdf.com', '1221231234', '1201 West 38th Street', '', 'Austin', 'TX', '78705', NULL, 30.30555500, -97.74532000, NULL, '2025-08-05 13:00:48', '2025-08-06 11:11:44', 1),
(2, 'Eric', 'Freeman', 'dasd@asda.com', '1231231234', '6725 Circle South Road', '', 'Austin', 'TX', '78745', NULL, 30.18882190, -97.77538470, 'ChIJh6BeRk-zRIYRJITk8htbXR0', '2025-08-05 13:00:48', '2025-08-06 11:11:44', 1),
(3, 'Christine', 'Wallace', 'sdfs@asd.com', '1231231234', '2149 McCloskey St', '', 'Austin', 'TX', '78704', NULL, 30.24970680, -97.74169250, 'ChIJP2Hzff-0RIYRstHK3TaJPa8', '2025-08-05 13:00:48', '2025-08-06 11:11:44', 1),
(4, 'Nelly', 'Newby', 'asnn@gmail.com', '5121221234', '3233 Harmon Avenue', '', 'Austin', 'TX', '78705', NULL, 30.29084370, -97.72353160, NULL, '2025-08-05 13:37:00', '2025-08-06 11:11:44', 1),
(5, 'Naq', 'dasd', 'asda@asda.com', '1231231234', '2300 Nueces Street', '', 'Austin', 'TX', '78705', NULL, 30.28706070, -97.74397550, NULL, '2025-08-05 13:42:43', '2025-08-06 11:11:44', 1),
(6, 'ASD', 'ASD', 'asd@asd.com', '123123124', '213 West 4th Street', '', 'Austin', 'TX', '78701', NULL, 30.26653820, -97.74553220, 'ChIJZ04x5gi1RIYR8SqaJ1s6w_M', '2025-08-05 13:45:17', '2025-08-06 11:11:44', 1),
(7, 'betty ', 'hoover', 'sdfsdfsd@gm.com', '(123) 131-2313', '906 Adventure Lane', '', 'Austin', 'TX', '78704', '', 30.24970680, -97.74169250, 'ChIJP2Hzff-0RIYRstHK3TaJPa8', '2025-08-05 14:10:14', '2025-08-06 11:12:40', 1),
(8, 'aslkjd', 'Wilco', 'klasd@kldfjs.com', '1231313131', '3423 Bee Caves Rd', '', 'Austin', 'TX', '78724', NULL, 30.33515300, -97.60237810, 'ChIJCewUmtzHRIYRW15igHouQr0', '2025-08-05 15:05:19', '2025-08-06 11:12:56', 1),
(9, 'Jane', 'Doe', 'jane.doe@example.com', '+1-512-555-1234', '123 Main Street', 'Apt 202', 'Austin', 'TX', '78704', 'USA', 30.25690100, -97.76380100, 'ChIJLwPMoJZKRIYRDNf3xznj8fw', '2025-08-06 05:18:17', '2025-08-06 05:18:17', 1),
(10, 'Newby', 'Customer', 'john.d@gn.com', '(512) 123-1232', '215 Brazos Street', '', 'Lockhart', 'TX', '78644', 'US', 29.88343290, -97.66905070, 'EiYyMTUgQnJhem9zIFN0LCBMb2NraGFydCwgVFggNzg2NDQsIFVTQSIxEi8KFAoSCYE5JSXxVkOGEfl-bVGxaBh5ENcBKhQKEgljr12t8VZDhhHItpDtmDgosg', '2025-08-06 06:02:57', '2025-08-06 06:03:24', 1),
(11, 'Franl', 'Smith', 'gg@dsad.com', '(123) 123-1312', '215 Brazos Street', '', 'Austin', 'TX', '78701', '', 30.26457120, -97.74249110, 'ChIJq8RRCAi1RIYRN3G2ucfnpKA', '2025-08-06 11:14:59', '2025-08-06 11:14:59', 1),
(12, 'Christine', 'Tallace', NULL, '512-555-0001', '2149 McCloskey St', NULL, 'Austin', 'TX', '78704', NULL, NULL, NULL, NULL, '2025-08-07 09:32:29', '2025-08-07 09:32:29', 1),
(13, 'Jane', 'TEzell', NULL, '512-555-0002', '1201 W 38th St', NULL, 'Austin', 'TX', '78705', NULL, NULL, NULL, NULL, '2025-08-07 09:32:29', '2025-08-07 09:32:29', 1),
(14, 'Ninth', 'Monkey', 'asdas@asda.com', '1231231235', '1234 South Lamar Boulevard', NULL, 'Austin', 'Texas', '78704', NULL, 30.25395210, -97.76329570, 'ChIJh7TPhx-1RIYR1Rn8F6_vTVA', '2025-08-09 05:39:37', '2025-08-09 05:39:37', 1);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int NOT NULL,
  `person_id` int UNSIGNED NOT NULL,
  `hire_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `role_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `person_id`, `hire_date`, `is_active`, `role_id`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-08-03', 1, 2, '2025-08-03 14:22:31', '2025-08-05 14:41:12'),
(2, 2, '2022-09-09', 1, 1, '2025-08-03 15:44:55', '2025-08-05 15:49:19'),
(3, 3, '2000-09-09', 1, 3, '2025-08-03 15:44:55', '2025-08-05 15:50:16'),
(4, 4, '2023-09-09', 1, 1, '2025-08-03 15:44:55', '2025-08-05 15:50:52'),
(5, 5, '2013-09-09', 0, 2, '2025-08-03 21:35:20', '2025-08-05 17:09:18'),
(6, 6, '2013-09-09', 1, 2, '2025-08-03 21:37:32', '2025-08-05 15:49:47'),
(7, 7, '2025-08-01', 1, 1, '2025-08-03 21:44:52', '2025-08-05 15:49:04'),
(8, 8, '2003-09-09', 1, 1, '2025-08-03 21:50:52', '2025-08-05 15:48:31'),
(9, 9, '2024-09-09', 1, 2, '2025-08-04 20:56:31', '2025-08-05 15:51:24'),
(15, 15, '2000-09-09', 1, 1, '2025-08-05 14:26:24', '2025-08-05 14:26:24'),
(16, 16, '2000-09-09', 1, 3, '2025-08-05 14:39:28', '2025-08-05 14:39:28'),
(17, 17, '2000-01-09', 1, 1, '2025-08-05 14:40:28', '2025-08-05 14:40:28'),
(18, 18, '2000-08-08', 1, 1, '2025-08-05 14:53:22', '2025-08-05 14:53:22'),
(19, 19, '2000-09-09', 1, 1, '2025-08-05 15:15:45', '2025-08-05 15:15:45'),
(20, 20, '2020-09-23', 1, 3, '2025-08-05 15:27:20', '2025-08-05 17:23:26'),
(21, 21, '2000-09-09', 1, 1, '2025-08-05 16:25:03', '2025-08-05 16:36:08'),
(22, 22, '2023-08-08', 1, 1, '2025-08-05 16:37:23', '2025-08-05 16:41:25'),
(23, 23, '2025-01-01', 1, 1, '2025-08-05 17:24:24', '2025-08-05 17:24:50'),
(24, 24, '2023-09-09', 1, 3, '2025-08-05 20:02:37', '2025-08-05 20:02:37'),
(25, 25, '2023-09-09', 1, 3, '2025-08-06 08:50:02', '2025-08-06 08:50:02'),
(26, 26, '2025-08-07', 1, 1, '2025-08-07 14:38:52', '2025-08-07 14:40:33'),
(27, 27, '2025-08-07', 1, 1, '2025-08-07 14:38:52', '2025-08-07 14:40:43'),
(28, 28, '2025-08-07', 1, NULL, '2025-08-07 14:51:29', '2025-08-07 14:51:29');

-- --------------------------------------------------------

--
-- Table structure for table `employee_availability`
--

CREATE TABLE `employee_availability` (
  `id` int UNSIGNED NOT NULL,
  `employee_id` int UNSIGNED NOT NULL,
  `day_of_week` varchar(16) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employee_availability`
--

INSERT INTO `employee_availability` (`id`, `employee_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(32, 1, 'Monday', '09:00:00', '12:00:00'),
(33, 1, 'Monday', '14:00:00', '15:00:00'),
(12, 2, 'Sunday', '09:00:00', '17:00:00'),
(15, 3, 'Monday', '09:00:00', '17:00:00'),
(1, 3, 'Sunday', '09:00:00', '15:00:00'),
(3, 3, 'Thursday', '09:00:00', '15:00:00'),
(2, 3, 'Tuesday', '09:00:00', '15:00:00'),
(10, 4, 'Sunday', '09:00:00', '14:00:00'),
(6, 6, 'Friday', '12:00:00', '15:00:00'),
(4, 6, 'Sunday', '12:00:00', '15:00:00'),
(5, 6, 'Tuesday', '12:00:00', '15:00:00'),
(11, 9, 'Monday', '14:30:00', '18:00:00'),
(17, 9, 'Thursday', '08:00:00', '17:00:00'),
(13, 20, 'Sunday', '12:00:00', '17:00:00'),
(14, 20, 'Wednesday', '12:00:00', '17:00:00'),
(7, 23, 'Sunday', '10:00:00', '15:00:00'),
(9, 23, 'Thursday', '10:00:00', '15:00:00'),
(8, 23, 'Tuesday', '10:00:00', '15:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `employee_skills`
--

CREATE TABLE `employee_skills` (
  `employee_id` int NOT NULL,
  `job_type_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employee_skills`
--

INSERT INTO `employee_skills` (`employee_id`, `job_type_id`) VALUES
(5, 1),
(6, 1),
(8, 1),
(9, 1),
(17, 1),
(22, 1),
(1, 2),
(9, 2),
(18, 2),
(20, 2),
(21, 2),
(22, 2),
(24, 2),
(1, 3),
(2, 3),
(7, 3),
(9, 3),
(15, 3),
(16, 3),
(18, 3),
(19, 3),
(21, 3),
(4, 4),
(5, 4),
(6, 4),
(8, 4),
(9, 4),
(15, 4),
(16, 4),
(17, 4),
(18, 4),
(19, 4),
(20, 4),
(21, 4),
(22, 4),
(23, 4),
(24, 4);

-- --------------------------------------------------------

--
-- Stand-in structure for view `employee_skill_names`
-- (See below for the actual view)
--
CREATE TABLE `employee_skill_names` (
`employee_id` int
,`skills` text
);

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `description` text NOT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time NOT NULL,
  `status` enum('Unassigned','Assigned','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Unassigned',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status_updated_at` timestamp NULL DEFAULT NULL,
  `duration_minutes` int DEFAULT '60'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `customer_id`, `description`, `scheduled_date`, `scheduled_time`, `status`, `created_at`, `updated_at`, `status_updated_at`, `duration_minutes`) VALUES
(1, 13, 'Window Cleaning - South Side 0807', '2025-08-08', '12:00:00', 'Unassigned', '2025-08-03 11:17:29', '2025-08-07 18:28:37', NULL, 120),
(2, 2, 'Pressure Wash Driveway & Patio', '2025-08-06', '13:30:00', 'Assigned', '2025-08-03 11:17:29', '2025-08-08 13:37:40', '2025-08-08 13:37:40', 60),
(3, 3, 'Post-Construction Cleanup', '2025-08-07', '09:00:00', 'Assigned', '2025-08-03 11:17:29', '2025-08-07 16:12:22', NULL, 60),
(5, 2, 'Pressure UPDATED', '2025-09-09', '10:00:00', 'Unassigned', '2025-08-03 12:28:45', '2025-08-07 18:28:37', NULL, 60),
(6, 3, 'JD', '2024-08-07', '09:00:00', 'Unassigned', '2025-08-03 13:05:08', '2025-08-08 21:10:42', NULL, 120),
(7, 1, 'Window', '2024-08-22', '09:00:00', 'Unassigned', '2025-08-03 13:56:12', '2025-08-07 18:28:37', NULL, 60),
(8, 3, 'Christmas', '2025-09-09', '09:00:00', 'Unassigned', '2025-08-03 14:08:20', '2025-08-07 18:28:37', NULL, 60),
(9, 2, 'EF', '2025-09-09', '09:00:00', 'Unassigned', '2025-08-03 14:10:45', '2025-08-08 18:16:56', NULL, 120),
(10, 1, 'Christmas', '2025-09-09', '09:00:00', 'Unassigned', '2025-08-03 17:35:13', '2025-08-07 18:28:37', NULL, 60),
(11, 2, 'Two', '2025-09-09', '09:00:00', 'Unassigned', '2025-08-03 17:36:12', '2025-08-07 18:28:37', NULL, 60),
(12, 3, 'job type 1', '2025-09-09', '09:00:00', 'Unassigned', '2025-08-03 17:40:46', '2025-08-07 18:28:37', NULL, 60),
(13, 3, 'dasdd', '2025-09-09', '09:09:00', 'Unassigned', '2025-08-03 17:47:50', '2025-08-07 18:28:37', NULL, 60),
(14, 3, 'sda', '2025-08-24', '09:00:00', 'Assigned', '2025-08-03 18:01:04', '2025-08-08 13:53:11', '2025-08-08 13:53:11', 60),
(15, 1, '0806 856', '2025-08-09', '08:00:00', 'Assigned', '2025-08-03 18:02:34', '2025-08-08 10:31:38', '2025-08-08 10:31:38', 60),
(16, 2, 'Cook Patio', '2025-09-01', '09:00:00', 'Assigned', '2025-08-04 19:36:41', '2025-08-08 10:19:30', '2025-08-08 10:19:30', 60),
(18, 2, 'Eric', '2025-08-05', '09:00:00', 'Assigned', '2025-08-04 20:19:58', '2025-08-08 14:23:16', '2025-08-08 14:23:16', 120),
(19, 4, 'Clean up window', '2025-08-10', '11:00:00', 'Assigned', '2025-08-05 20:43:35', '2025-08-08 10:38:36', '2025-08-08 10:38:36', 60),
(20, 1, 'two story updated', '2025-08-07', '11:30:00', 'Assigned', '2025-08-05 21:03:50', '2025-08-08 13:32:13', '2025-08-08 13:32:13', 60),
(21, 1, 'Window washing in \r\n\r\n', '2025-08-07', '09:00:00', 'Unassigned', '2025-08-05 21:12:18', '2025-08-07 18:28:37', NULL, 60),
(22, 2, 'ASDAS', '2025-09-01', '10:00:00', 'Unassigned', '2025-08-05 22:49:39', '2025-08-07 18:28:37', NULL, 60),
(23, 1, 'MAPSSSS', '2025-08-06', '10:00:00', 'Unassigned', '2025-08-05 23:03:04', '2025-08-08 19:25:54', NULL, 120),
(24, 1, '640 update', '2025-08-06', '10:00:00', 'Unassigned', '2025-08-05 23:39:49', '2025-08-07 18:28:37', NULL, 60),
(25, 10, 'aug 6 0855\r\n', '2025-08-08', '10:00:00', 'Unassigned', '2025-08-06 13:54:25', '2025-08-07 18:28:37', NULL, 60),
(26, 10, '0914', '2025-08-07', '10:00:00', 'Assigned', '2025-08-06 14:14:59', '2025-08-08 13:37:03', '2025-08-08 13:37:03', 60),
(27, 1, 'Jane Edied.', '2025-08-08', '10:00:00', 'Unassigned', '2025-08-06 17:46:37', '2025-08-07 18:28:37', NULL, 60),
(28, 10, 'askdjasdk', '2025-09-01', '10:00:00', 'Unassigned', '2025-08-06 18:11:24', '2025-08-07 18:28:37', NULL, 60),
(29, 1, 'Chage date to 8 10 - time to 12 duration 120 added gutter to chrsst', '2025-08-10', '12:00:00', 'Assigned', '2025-08-06 23:38:25', '2025-08-07 17:29:41', NULL, 120),
(30, 2, 'christmas', '2025-11-01', '10:00:00', 'Assigned', '2025-08-07 00:52:13', '2025-08-08 10:02:58', '2025-08-08 10:02:58', 60),
(35, 10, '0807 0512', '2025-08-07', '09:00:00', 'Unassigned', '2025-08-07 10:13:52', '2025-08-07 18:28:37', NULL, 60),
(36, 12, 'Post-Construction Cleanup', '2025-08-08', '09:00:00', 'Unassigned', '2025-08-07 15:07:00', '2025-08-07 18:28:37', NULL, 90),
(37, 1, '0808 Jane 1100. 120min gutter clean', '2025-08-08', '11:00:00', 'Unassigned', '2025-08-07 18:54:05', '2025-08-07 18:54:56', NULL, 90),
(38, 10, '0808', '2025-08-08', '08:00:00', 'Assigned', '2025-08-07 23:43:52', '2025-08-08 11:03:29', '2025-08-08 11:03:29', 60),
(41, 3, '0808 1700 HOXD', '2025-08-09', '10:00:00', 'Unassigned', '2025-08-07 23:53:07', '2025-08-08 00:03:57', NULL, 60),
(42, 10, 'CONFLICT', '2025-08-09', '10:00:00', 'Unassigned', '2025-08-08 00:30:01', '2025-08-08 00:30:01', NULL, 60),
(43, 11, 'CONFLICT', '2025-08-09', '10:00:00', 'Unassigned', '2025-08-08 00:30:34', '2025-08-08 00:30:34', NULL, 60),
(44, 7, 'Betty window', '2025-08-09', '09:00:00', 'Unassigned', '2025-08-08 21:05:54', '2025-08-08 21:05:54', NULL, 90);

-- --------------------------------------------------------

--
-- Table structure for table `job_employee`
--

CREATE TABLE `job_employee` (
  `job_id` int NOT NULL,
  `employee_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `job_employee`
--

INSERT INTO `job_employee` (`job_id`, `employee_id`) VALUES
(1, 1),
(3, 1),
(11, 1),
(2, 3),
(9, 3),
(12, 3),
(14, 3),
(6, 4),
(8, 4),
(16, 7),
(5, 8),
(7, 8),
(10, 8),
(13, 8),
(16, 8);

-- --------------------------------------------------------

--
-- Table structure for table `job_employee_assignment`
--

CREATE TABLE `job_employee_assignment` (
  `id` int UNSIGNED NOT NULL,
  `job_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `job_employee_assignment`
--

INSERT INTO `job_employee_assignment` (`id`, `job_id`, `employee_id`, `assigned_at`) VALUES
(1, 1, 1, '2025-08-09 12:57:50'),
(2, 2, 9, '2025-08-09 12:57:50'),
(3, 3, 8, '2025-08-09 12:57:50'),
(4, 5, 4, '2025-08-09 12:57:50'),
(5, 9, 3, '2025-08-09 12:57:50'),
(6, 12, 28, '2025-08-09 12:57:50'),
(7, 14, 9, '2025-08-09 12:57:50'),
(8, 15, 22, '2025-08-09 12:57:50'),
(9, 16, 22, '2025-08-09 12:57:50'),
(10, 18, 9, '2025-08-09 12:57:50'),
(11, 19, 8, '2025-08-09 12:57:50'),
(12, 20, 8, '2025-08-09 12:57:50'),
(13, 26, 21, '2025-08-09 12:57:50'),
(14, 29, 3, '2025-08-09 12:57:50'),
(15, 30, 22, '2025-08-09 12:57:50'),
(16, 36, 28, '2025-08-09 12:57:50'),
(17, 38, 22, '2025-08-09 12:57:50');

-- --------------------------------------------------------

--
-- Table structure for table `job_job_types`
--

CREATE TABLE `job_job_types` (
  `job_id` int NOT NULL,
  `job_type_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `job_job_types`
--

INSERT INTO `job_job_types` (`job_id`, `job_type_id`) VALUES
(1, 1),
(3, 1),
(5, 1),
(6, 1),
(15, 1),
(16, 1),
(18, 1),
(19, 1),
(20, 1),
(21, 1),
(35, 1),
(42, 1),
(43, 1),
(44, 1),
(2, 2),
(5, 2),
(14, 2),
(18, 2),
(26, 2),
(36, 2),
(1, 3),
(7, 3),
(9, 3),
(13, 3),
(18, 3),
(25, 3),
(26, 3),
(27, 3),
(29, 3),
(37, 3),
(41, 3),
(7, 4),
(9, 4),
(12, 4),
(22, 4),
(23, 4),
(24, 4),
(27, 4),
(28, 4),
(29, 4),
(30, 4),
(38, 4);

-- --------------------------------------------------------

--
-- Table structure for table `job_types`
--

CREATE TABLE `job_types` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `job_types`
--

INSERT INTO `job_types` (`id`, `name`) VALUES
(1, 'Window Washing'),
(2, 'Pressure Washing'),
(3, 'Gutter Cleaning'),
(4, 'Christmas Lights');

-- --------------------------------------------------------

--
-- Table structure for table `people`
--

CREATE TABLE `people` (
  `id` int UNSIGNED NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `google_place_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `people`
--

INSERT INTO `people` (`id`, `first_name`, `last_name`, `email`, `phone`, `created_at`, `updated_at`, `address_line1`, `address_line2`, `city`, `state`, `postal_code`, `country`, `latitude`, `longitude`, `google_place_id`) VALUES
(1, 'Jane', 'Doe', 'jane.doe@example.com', '512-555-1234', '2025-08-03 14:22:31', '2025-08-05 14:41:12', '1234 1/2 Alameda Drive', '', 'Austin', 'TX', '78704', 'US', 30.2497068, -97.7416925, NULL),
(2, 'Alice', 'Johnson', 'alice@example.com', '(555) 111-1', '2025-08-03 15:44:55', '2025-08-05 15:49:19', '4423 Hank Ave', '', 'Austin', 'TX', '78745', 'US', 30.2233618, -97.7803959, 'ChIJW6CJF7S0RIYR94faqNzhNfA'),
(3, 'Bob', 'Martinez', 'bob@example.com', '(555) 222-2', '2025-08-03 15:44:55', '2025-08-05 15:50:16', '124 W 8th St', '', 'Austin', 'TX', '78701', 'US', 30.2704723, -97.7428279, 'ChIJa2fsGwq1RIYRjSHRJYacbj4'),
(4, 'Carla', 'Nguyen', 'carla@example.com', '(555) 333-3', '2025-08-03 15:44:55', '2025-08-05 15:50:52', '1234 1/2 Alameda Dr', '', 'Austin', 'TX', '78704', 'US', 30.2497068, -97.7416925, 'ChIJP2Hzff-0RIYRstHK3TaJPa8'),
(5, 'Mark', 'Inactive', 'ms@asqqwed.com', '(412) 123-1234', '2025-08-03 21:35:20', '2025-08-05 17:09:18', '1250 S Capital of Texas Hwy', '', 'Austin', 'TX', '78746', 'US', 30.2806438, -97.8237930, 'ChIJ5YOw04hKW4YR16Fm06y1MVI'),
(6, 'Mark', 'Kaderli', 'ms@asd.com', '(412) 123-1234', '2025-08-03 21:37:32', '2025-08-05 15:49:47', '1234 1/2 Alameda Dr', '', 'Austin', 'TX', '78704', 'US', 30.2497068, -97.7416925, 'ChIJP2Hzff-0RIYRstHK3TaJPa8'),
(7, 'Feddy', 'Fan', 'ff@gm.com', '(123) 123-1234', '2025-08-03 21:44:52', '2025-08-05 15:49:04', '324 Hillside Ct', '', 'Austin', 'TX', '78746', 'US', 30.2829161, -97.8167368, 'ChIJP28CCpBKW4YRhTraE3ZoGow'),
(8, 'Doug', 'Double', 'dd@gmail.com', '(123) 123-1231', '2025-08-03 21:50:52', '2025-08-05 15:48:31', '12319 N Mopac Expy', '', 'Austin', 'TX', '78758', 'US', 30.4159542, -97.7047386, 'ChIJ4T_oDyLMRIYRRjurAoubcUo'),
(9, 'John', 'Stokes', 'js@qwe.cm', '(412) 123-5432', '2025-08-04 20:56:31', '2025-08-05 15:51:24', '422 Guadalupe St', '', 'Austin', 'TX', '78701', 'US', 30.2676422, -97.7470401, 'ChIJq6qqzQ61RIYRe4CHUfaPVlM'),
(15, 'Waren', 'Dev', 'wc@gm.com', '', '2025-08-05 14:26:24', '2025-08-05 14:26:24', '2300 Nueces Street', '', 'Austin', 'TX', '78705', 'US', 30.2870607, -97.7439755, NULL),
(16, 'John', 'Dadey', 'John.Dadey@conversamos.com', '5126591117', '2025-08-05 14:39:28', '2025-08-05 14:39:28', '1234 South Lamar Boulevard', '', 'Austin', 'TX', '78704', 'US', 30.2539521, -97.7632957, NULL),
(17, 'Reggie', 'Jackson', 'rj@fm.com', '', '2025-08-05 14:40:28', '2025-08-05 14:40:28', '5601 Brodie Lane', '', 'Sunset Valley', 'TX', '78745', 'US', 30.2264733, -97.8208084, NULL),
(18, 'Betty', 'Boop', 'asdas@12312.com', '5123219876', '2025-08-05 14:53:22', '2025-08-05 14:53:49', '12312 Sugarleaf Place', '', 'Austin', 'TX', '78748', 'US', 30.1502957, -97.8554195, NULL),
(19, 'John', 'Dader', 'asdaas@gma.com', '(123) 121-2312', '2025-08-05 15:15:45', '2025-08-05 17:28:11', '2145 Guadalupe St', '', 'Austin', 'TX', '78705', 'US', 30.2843339, -97.7416558, 'ChIJhQmDgJ21RIYRl8DyDBDyocs'),
(20, 'Don', 'Jones', 'asdas@123.com', '(412) 123-2111', '2025-08-05 15:27:20', '2025-08-05 17:23:26', '906 Adventure Ln', '', 'Cedar Park', 'TX', '78613', 'US', 30.5102235, -97.7852774, 'ChIJg4T2MjMtW4YRVrdGfu3_0zQ'),
(21, 'Betty', 'Bone', 'asdas@sdfd.com', '(312) 312-3123', '2025-08-05 16:25:03', '2025-08-05 17:28:11', '3423 Bee Caves Rd', '', 'West Lake Hills', 'TX', '78746', 'US', 30.2753624, -97.8049572, 'ChIJQ0IcVb5KW4YRBrIp7h2A-FQ'),
(22, 'Kim', 'Barnes', 'dnsd@sdda.com', '(123) 123-1231', '2025-08-05 16:37:23', '2025-08-05 17:28:11', '509 W 11th St', '', 'Austin', 'TX', '78701', 'US', 30.2741006, -97.7464174, 'ChIJvWspRtK1RIYR5_e0S9azVFY'),
(23, 'Fran', 'Muelman', 'fm@sdfs.com', '(123) 123-1231', '2025-08-05 17:24:24', '2025-08-05 17:28:11', '2131 1/2 William Barton Dr', '', 'Austin', 'TX', '78746', 'US', 30.2645462, -97.7703285, 'ChIJe-yEFDu1RIYRc2UyhRO1DKk'),
(24, 'QWPEQKOPE', 'keep', 'qweqweq@gm.com', '(213) 123-1231', '2025-08-05 20:02:37', '2025-08-05 20:03:09', '2047 South Center Street, Austin, TX, USA', '', 'Austin', 'TX', '78751', NULL, 30.3133915, -97.7108458, 'ChIJU83SPhDKRIYRd08ICRN8tro'),
(25, 'John', 'Dadey', 'john.dadey@convdsfersamos.com', '(512) 659-1117', '2025-08-06 08:50:02', '2025-08-06 10:47:15', '1250 South Capital of Texas Highway', '', 'Austin', 'TX', '78746', NULL, 30.2806438, -97.8237930, 'ChIJ5YOw04hKW4YR16Fm06y1MVI'),
(26, 'Doug', 'Trouble', NULL, NULL, '2025-08-07 14:35:24', '2025-08-07 14:35:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 'Carla', 'Juyen', NULL, NULL, '2025-08-07 14:35:24', '2025-08-07 14:35:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 'Alice', 'Andrews', NULL, NULL, '2025-08-07 14:49:31', '2025-08-07 14:49:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'Bob', 'Benson', NULL, NULL, '2025-08-07 14:49:31', '2025-08-07 14:49:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'Technician'),
(2, 'Dispatcher'),
(3, 'Admin');

-- --------------------------------------------------------

--
-- Structure for view `employee_skill_names`
--
DROP TABLE IF EXISTS `employee_skill_names`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `employee_skill_names`  AS SELECT `es`.`employee_id` AS `employee_id`, group_concat(distinct `jt`.`name` order by `jt`.`name` ASC separator ', ') AS `skills` FROM (`employee_skills` `es` join `job_types` `jt` on((`jt`.`id` = `es`.`job_type_id`))) GROUP BY `es`.`employee_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique` (`email`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_employees_person` (`person_id`),
  ADD KEY `fk_employee_role` (`role_id`),
  ADD KEY `idx_employees_person` (`person_id`);

--
-- Indexes for table `employee_availability`
--
ALTER TABLE `employee_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_emp_day_start_end` (`employee_id`,`day_of_week`,`start_time`,`end_time`),
  ADD KEY `idx_emp_avail_emp_day_start` (`employee_id`,`day_of_week`,`start_time`,`end_time`);

--
-- Indexes for table `employee_skills`
--
ALTER TABLE `employee_skills`
  ADD PRIMARY KEY (`employee_id`,`job_type_id`),
  ADD KEY `job_type_id` (`job_type_id`),
  ADD KEY `idx_es_employee` (`employee_id`),
  ADD KEY `idx_es_jobtype` (`job_type_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_jobs_customer` (`customer_id`);

--
-- Indexes for table `job_employee`
--
ALTER TABLE `job_employee`
  ADD PRIMARY KEY (`job_id`,`employee_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `job_employee_assignment`
--
ALTER TABLE `job_employee_assignment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_job_employee` (`job_id`,`employee_id`),
  ADD KEY `fk_jea_employee` (`employee_id`),
  ADD KEY `idx_jea_job` (`job_id`),
  ADD KEY `idx_jea_emp` (`employee_id`);

--
-- Indexes for table `job_job_types`
--
ALTER TABLE `job_job_types`
  ADD PRIMARY KEY (`job_id`,`job_type_id`),
  ADD KEY `job_type_id` (`job_type_id`),
  ADD KEY `idx_jjt_job` (`job_id`),
  ADD KEY `idx_jjt_type` (`job_type_id`);

--
-- Indexes for table `job_types`
--
ALTER TABLE `job_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `people`
--
ALTER TABLE `people`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `employee_availability`
--
ALTER TABLE `employee_availability`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `job_employee_assignment`
--
ALTER TABLE `job_employee_assignment`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `job_types`
--
ALTER TABLE `job_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `people`
--
ALTER TABLE `people`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employees_person` FOREIGN KEY (`person_id`) REFERENCES `people` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `employee_skills`
--
ALTER TABLE `employee_skills`
  ADD CONSTRAINT `fk_es_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_es_jobtype` FOREIGN KEY (`job_type_id`) REFERENCES `job_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `fk_jobs_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `job_employee_assignment`
--
ALTER TABLE `job_employee_assignment`
  ADD CONSTRAINT `fk_jea_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jea_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_job_types`
--
ALTER TABLE `job_job_types`
  ADD CONSTRAINT `fk_jjt_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jjt_type` FOREIGN KEY (`job_type_id`) REFERENCES `job_types` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
