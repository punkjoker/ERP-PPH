-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2026 at 06:02 PM
-- Server version: 10.4.24-MariaDB
-- PHP Version: 8.1.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lynntech_manager`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Leave') NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `user_id`, `attendance_date`, `status`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 9, '2026-10-17', 'Absent', '', '2026-10-18 06:26:36', '2026-10-18 06:26:36'),
(2, 10, '2026-10-17', 'Leave', '', '2026-10-18 06:26:36', '2026-10-18 06:26:36'),
(3, 12, '2026-10-17', 'Present', '', '2026-10-18 06:26:36', '2026-10-18 06:26:36'),
(4, 9, '2026-10-19', 'Absent', '', '2026-10-19 05:00:10', '2026-10-19 05:00:10'),
(5, 10, '2026-10-19', 'Present', '', '2026-10-19 05:00:10', '2026-10-19 05:00:10'),
(6, 12, '2026-10-19', 'Leave', '', '2026-10-19 05:00:10', '2026-10-19 05:00:10'),
(7, 9, '2026-10-20', 'Present', '', '2026-10-20 07:27:40', '2026-10-20 07:27:40'),
(8, 10, '2026-10-20', 'Present', '', '2026-10-20 07:27:40', '2026-10-20 07:27:40'),
(9, 12, '2026-10-20', 'Present', '', '2026-10-20 07:27:40', '2026-10-20 07:27:40'),
(10, 14, '2026-10-20', 'Present', '', '2026-10-20 16:00:02', '2026-10-20 16:00:02');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `audit_id` int(11) NOT NULL,
  `auditor_name` varchar(150) NOT NULL,
  `auditor_organization` varchar(200) DEFAULT NULL,
  `auditor_email` varchar(150) DEFAULT NULL,
  `auditor_phone` varchar(30) DEFAULT NULL,
  `audit_type` varchar(150) NOT NULL,
  `department` varchar(150) DEFAULT NULL,
  `audit_date` date NOT NULL,
  `next_due_date` date DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `status` enum('Scheduled','Completed','Overdue') NOT NULL DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`audit_id`, `auditor_name`, `auditor_organization`, `auditor_email`, `auditor_phone`, `audit_type`, `department`, `audit_date`, `next_due_date`, `findings`, `recommendations`, `status`, `created_at`, `updated_at`) VALUES
(1, 'TECHY', 'Na', 'daviikyalo@gmail.com', '0748135605', 'Internal Audit', 'ICT', '2026-10-20', '2027-10-20', NULL, NULL, 'Scheduled', '2026-10-20 06:36:07', '2026-10-20 06:36:07');

-- --------------------------------------------------------

--
-- Table structure for table `bill_of_materials`
--

CREATE TABLE `bill_of_materials` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `bom_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `requested_by` varchar(100) DEFAULT NULL,
  `issued_by` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `bill_of_materials`
--

INSERT INTO `bill_of_materials` (`id`, `product_id`, `bom_date`, `expiry_date`, `batch_number`, `requested_by`, `issued_by`, `remarks`, `issue_date`, `description`, `status`, `created_at`) VALUES
(32, 10, '2025-10-16', NULL, NULL, 'MUTANIA KK', 'Stores', 'issued', '2025-10-16', 'saales', 'Approved', '2025-10-16 10:15:56'),
(33, 10, '2025-10-16', NULL, NULL, 'ACCTS', 'Mary', 'Issued', '2025-10-16', 'SALES', 'Approved', '2025-10-16 11:10:52'),
(34, 11, '2025-10-16', NULL, NULL, 'Augustine', 'Stores', 'Issued', '2025-10-16', 'Stocking', 'Approved', '2025-10-16 12:12:12'),
(35, 12, '2025-10-16', NULL, NULL, 'Production', 'Stores', 'issued', '2025-10-16', 'stock', 'Approved', '2025-10-16 12:43:09'),
(36, 12, '2025-10-21', NULL, 'BATCH-090-23', 'DOM', 'Stores', 'Issuedd', '2025-10-21', 'Stock', 'Approved', '2025-10-21 07:11:13'),
(37, 13, '2025-10-21', NULL, 'BATCH-090-290', 'DOM', 'Stores', 'ISSUED', '2025-10-21', 'STOCK', 'Approved', '2025-10-21 13:48:05'),
(38, 13, '2025-10-22', NULL, 'BATCH-090-234', 'DOMt', 'Stores', 'iSSUED', '2025-10-22', 'sales', 'Approved', '2025-10-22 08:25:35'),
(39, 13, '2025-10-22', NULL, 'BATCH-090-2345', 'DOMt', 'Stores', 'issued', '2025-10-22', 'sales', 'Approved', '2025-10-22 08:55:22'),
(40, 14, '2025-10-27', NULL, 'BATCH-090-23456', 'DAVID', 'MARY', 'Issued', '2025-10-27', 'For stock', 'Approved', '2025-10-27 05:08:50'),
(41, 14, '2025-10-27', NULL, 'BATCH-090-234567', 'DAVID', 'MARY', 'DONE', '2025-10-27', 'KWAL', 'Approved', '2025-10-27 06:37:14'),
(42, 14, '2025-10-27', NULL, 'BATCH-090-2345678', 'DAVID', 'MARY', 'iSSUED', '2025-10-27', 'SALES', 'Approved', '2025-10-27 06:40:45'),
(43, 14, '2025-10-27', NULL, 'BATCH-090-23456789', 'DAVID', 'S', 'XX', '2025-10-27', 'SS', 'Approved', '2025-10-27 06:56:27'),
(44, 15, '2025-10-28', NULL, 'BATCH-090-23D', 'DOM', 'Stores', 'iSSUED', '2025-10-28', 'FOR STOCK', 'Approved', '2025-10-28 10:39:39'),
(45, 16, '2025-10-28', NULL, 'BATCH-090-23F', 'Augustine', 'Mary', 'Issued', '2025-10-28', 'For stocking', 'Approved', '2025-10-28 11:56:15'),
(67, 16, '2025-11-04', NULL, 'BATCH-090-23-2-1', 'MUTANIA KK', 'Stores', 'Issued', '2025-11-04', 'SA', 'Approved', '2025-11-04 05:57:27'),
(68, 16, '2025-11-04', '2025-11-04', 'BATCH-090-23-2-2', 'MUTANIA KK', NULL, NULL, NULL, 'sale', 'Pending', '2025-11-04 06:34:18');

-- --------------------------------------------------------

--
-- Table structure for table `bill_of_material_items`
--

CREATE TABLE `bill_of_material_items` (
  `id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `chemical_id` int(11) NOT NULL,
  `chemical_code` varchar(50) DEFAULT NULL,
  `chemical_name` varchar(100) DEFAULT NULL,
  `rm_lot_no` varchar(50) DEFAULT NULL,
  `quantity_requested` decimal(10,2) DEFAULT NULL,
  `unit` varchar(10) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `po_number` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `bill_of_material_items`
--

INSERT INTO `bill_of_material_items` (`id`, `bom_id`, `chemical_id`, `chemical_code`, `chemical_name`, `rm_lot_no`, `quantity_requested`, `unit`, `unit_price`, `total_cost`, `po_number`) VALUES
(43, 32, 0, 'OK001', 'Oklin Facoal', '141025', 50.00, 'kgs', 1000.00, 50000.00, '40021'),
(44, 32, 0, 'OK003', 'ProClean HT4', '141025', 50.00, 'kgs', 1000.00, 50000.00, '40021'),
(45, 33, 0, 'OK001', 'Oklin Facoal', '141025', 25.00, 'kgs', 1000.00, 25000.00, '40021'),
(46, 33, 0, 'OK003', 'ProClean HT4', '141025', 25.00, 'kgs', 1000.00, 25000.00, '40021'),
(47, 34, 0, 'CHM008', 'HYDROXIDE', '161025', 90.00, 'kgs', 250.00, 22500.00, '40022'),
(48, 34, 0, 'CHM008', 'SODA PEARLS', '161025', 210.00, 'kg', 400.00, 60000.00, '40022'),
(49, 35, 0, 'CHM00002', 'CALCIUM', '161025-1', 450.00, 'kgs', 50.00, 22500.00, '40024'),
(50, 36, 0, 'CHM00002', 'CALCIUM', '161025-2', 500.00, 'kgs', 30.00, 11500.00, '40025'),
(51, 37, 0, 'AG009', 'Ag', 'DOO09', 250.00, 'kgs', 30.00, 7500.00, '40026'),
(52, 38, 0, 'AG009', 'Ag', 'DOO09', 50.00, 'kgs', 30.00, 1500.00, '40026'),
(53, 39, 0, 'AG009', 'Ag', 'DOO09', 50.00, 'kgs', 30.00, 1500.00, '40026'),
(54, 40, 0, 'FLOU90', 'Flour', 'RM27102025-2', 350.00, 'kg', 140.00, 49000.00, '40030'),
(55, 40, 0, 'OILOU90', 'Oil', 'RM27102025-1', 175.00, 'ltr', 150.00, 26250.00, '40030'),
(56, 40, 0, 'SAILOU90', 'Salt', 'RM27102025-3', 175.00, 'kg', 10.00, 1750.00, '40030'),
(57, 41, 0, 'FLOU90', 'Flour', 'RM27102025-2', 150.00, 'kg', 140.00, 21000.00, '40030'),
(58, 41, 0, 'OILOU90', 'Oil', 'RM27102025-1', 75.00, 'ltr', 150.00, 11250.00, '40030'),
(59, 41, 0, 'SAILOU90', 'Salt', 'RM27102025-3', 75.00, 'kg', 10.00, 750.00, '40030'),
(60, 42, 0, 'FLOU90', 'Flour', 'RM27102025-2', 125.00, 'kg', 140.00, 17500.00, '40030'),
(61, 42, 0, 'OILOU90', 'Oil', 'RM27102025-1', 62.50, 'ltr', 150.00, 9375.00, '40030'),
(62, 42, 0, 'SAILOU90', 'Salt', 'RM27102025-3', 62.50, 'kg', 10.00, 625.00, '40030'),
(63, 43, 0, 'FLOU90', 'Flour', 'RM27102025-2', 125.00, 'kg', 140.00, 17500.00, '40030'),
(64, 43, 0, 'OILOU90', 'Oil', 'RM27102025-1', 62.50, 'ltr', 150.00, 9375.00, '40030'),
(65, 43, 0, 'SAILOU90', 'Salt', 'RM27102025-3', 62.50, 'kg', 10.00, 625.00, '40030'),
(66, 44, 0, 'AV080', 'Avacado Oil', '08102025KL', 185.29, 'kg', 50.00, 9264.50, '40032'),
(67, 44, 0, 'PO-080', 'Potash', '08102025KLS', 164.71, 'kg', 50.00, 8235.50, '40032'),
(68, 45, 0, 'PAAS-09', 'PAAS', '10282025-1', 45.00, 'kg', 100.00, 4500.00, '40033'),
(69, 45, 0, 'PT-0970', 'POTASIUM HYDROXIDE', '08102025-2', 30.00, 'kg', 200.00, 6000.00, '40033'),
(70, 45, 0, 'PURI-0700', 'Purified Water', '131025-3', 225.00, 'kg', 150.00, 33750.00, '40033'),
(74, 67, 0, 'PAAS-09', 'PAAS', '10282025-1', 150.00, 'kg', 100.00, 15000.00, '40033'),
(75, 67, 0, 'PT-0970', 'POTASIUM HYDROXIDE', '08102025-2', 100.00, 'kg', 200.00, 20000.00, '40033'),
(76, 67, 0, 'PURI-0700', 'Purified Water', '131025-3', 750.00, 'kg', 150.00, 112500.00, '40033'),
(77, 68, 0, 'PAAS-09', 'PAAS', '', 15.00, 'kg', 0.00, 0.00, NULL),
(78, 68, 0, 'PT-0970', 'POTASIUM HYDROXIDE', '', 10.00, 'kg', 0.00, 0.00, NULL),
(79, 68, 0, 'PURI-0700', 'Purified Water', '', 75.00, 'kg', 0.00, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bom`
--

CREATE TABLE `bom` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `std_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(20) NOT NULL DEFAULT 'kg',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `bom`
--

INSERT INTO `bom` (`id`, `product_id`, `std_quantity`, `unit`, `created_at`) VALUES
(12, 10, 200.00, 'kg', '2025-10-16 10:15:05'),
(13, 11, 1000.00, 'kg', '2025-10-16 12:10:54'),
(14, 12, 500.00, 'kg', '2025-10-16 12:42:43'),
(15, 13, 300.00, 'kg', '2025-10-21 13:47:39'),
(16, 14, 1000.00, 'kg', '2025-10-27 05:07:57'),
(17, 15, 850.00, 'kg', '2025-10-28 10:39:05'),
(18, 16, 100.00, 'kg', '2025-10-28 11:53:23');

-- --------------------------------------------------------

--
-- Table structure for table `bom_materials`
--

CREATE TABLE `bom_materials` (
  `id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `chemical_id` int(11) NOT NULL,
  `std_quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `bom_materials`
--

INSERT INTO `bom_materials` (`id`, `bom_id`, `chemical_id`, `std_quantity`, `unit`) VALUES
(18, 12, 5, 100.00, 'kgs'),
(19, 12, 6, 100.00, 'kgs'),
(20, 13, 7, 300.00, 'kgs'),
(21, 13, 8, 700.00, 'kg'),
(22, 14, 9, 500.00, 'kgs'),
(23, 15, 10, 300.00, 'kgs'),
(24, 16, 12, 500.00, 'kg'),
(25, 16, 13, 250.00, 'ltr'),
(26, 16, 14, 250.00, 'kg'),
(27, 17, 15, 450.00, 'kg'),
(28, 17, 16, 400.00, 'kg'),
(29, 18, 19, 75.00, 'kg'),
(30, 18, 17, 15.00, 'kg'),
(31, 18, 18, 10.00, 'kg');

-- --------------------------------------------------------

--
-- Table structure for table `breakfast_expense`
--

CREATE TABLE `breakfast_expense` (
  `breakfast_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `total_amount` decimal(10,2) NOT NULL,
  `items_bought_by` varchar(100) DEFAULT NULL,
  `transport_cost` decimal(10,2) DEFAULT NULL,
  `petty_cash_no` varchar(100) DEFAULT NULL,
  `approved_by` varchar(150) DEFAULT NULL,
  `payment_status` enum('Pending','Paid') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `breakfast_expense`
--

INSERT INTO `breakfast_expense` (`breakfast_id`, `expense_date`, `items`, `total_amount`, `items_bought_by`, `transport_cost`, `petty_cash_no`, `approved_by`, `payment_status`, `created_at`) VALUES
(1, '2025-10-07', '[{\"item\":\"Bread-7\",\"cost\":490}]', 490.00, '', 0.00, NULL, NULL, 'Pending', '2025-10-07 07:50:00'),
(2, '2025-10-07', '[{\"item\":\"Bread-7\",\"cost\":490}]', 490.00, '', 0.00, NULL, NULL, 'Pending', '2025-10-07 07:53:52'),
(3, '2025-10-08', '[{\"item\":\"Bread-7\",\"cost\":490},{\"item\":\"Blueband\",\"cost\":200}]', 790.00, 'Nicho', 100.00, 'DBV009y', 'HR', 'Paid', '2025-10-07 08:01:29');

-- --------------------------------------------------------

--
-- Table structure for table `chemicals_in`
--

CREATE TABLE `chemicals_in` (
  `id` int(11) NOT NULL,
  `chemical_id` int(11) DEFAULT NULL,
  `chemical_name` varchar(150) NOT NULL,
  `batch_no` varchar(100) DEFAULT NULL,
  `rm_lot_no` varchar(100) NOT NULL,
  `std_quantity` decimal(10,2) NOT NULL,
  `original_quantity` decimal(10,2) NOT NULL,
  `remaining_quantity` decimal(10,2) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `date_added` date NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `chemical_code` varchar(50) DEFAULT NULL,
  `po_number` varchar(50) DEFAULT NULL,
  `action_type` enum('New','Restock') DEFAULT 'New'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `chemicals_in`
--

INSERT INTO `chemicals_in` (`id`, `chemical_id`, `chemical_name`, `batch_no`, `rm_lot_no`, `std_quantity`, `original_quantity`, `remaining_quantity`, `total_cost`, `unit_price`, `date_added`, `status`, `created_at`, `updated_at`, `chemical_code`, `po_number`, `action_type`) VALUES
(10, NULL, 'SUGAR', '25-001', '161025', 100.00, 0.00, 100.00, 100000.00, 1000.00, '2025-10-16', 'Approved', '2025-10-16 09:52:58', '2025-10-16 09:54:10', 'CH009', '40019', 'New'),
(11, NULL, 'Hotherm B', '25-001', '151025', 140.00, 0.00, 140.00, 7000.00, 50.00, '2025-10-16', 'Approved', '2025-10-16 09:53:18', '2025-10-16 09:54:23', '2023', '40017', 'New'),
(12, NULL, 'RLT 20F', '25-001', '141025', 50.00, 0.00, 50.00, 2000.00, 40.00, '2025-10-16', 'Approved', '2025-10-16 09:53:41', '2025-10-16 09:54:40', '4011', '40016', 'New'),
(13, NULL, 'ProClean HT4', '25-001', '141025', 250.00, 0.00, 175.00, 250000.00, 1000.00, '2025-10-16', 'Approved', '2025-10-16 10:05:40', '2025-10-16 11:11:20', 'OK003', '40021', 'New'),
(14, NULL, 'Oklin Facoal', '25-001', '141025', 200.00, 0.00, 125.00, 200000.00, 1000.00, '2025-10-16', 'Approved', '2025-10-16 10:05:54', '2025-10-16 11:11:20', 'OK001', '40021', 'New'),
(15, NULL, 'SODA PEARLS', '25-001', '161025', 250.00, 0.00, 0.00, 62500.00, 250.00, '2025-10-16', 'Approved', '2025-10-16 12:01:12', '2025-10-16 12:13:18', 'CHM008', '40022', 'New'),
(16, NULL, 'HYDROXIDE', '25-001', '161025', 300.00, 0.00, 250.00, 120000.00, 400.00, '2025-10-16', 'Approved', '2025-10-16 12:01:31', '2025-10-16 12:13:18', 'CHM008', '40022', 'New'),
(17, NULL, 'SODA PEARLS', '25-001', '161025', 200.00, 0.00, 200.00, 60000.00, 300.00, '2025-10-16', 'Approved', '2025-10-16 12:33:50', '2025-10-16 12:35:15', 'CHM008', '40023', 'New'),
(18, NULL, 'SODA PEARLS', '25-001', '161025', 250.00, 0.00, 250.00, 62500.00, 250.00, '2025-10-16', 'Approved', '2025-10-16 12:34:35', '2025-10-16 12:35:33', 'CHM008', '40022', 'New'),
(19, NULL, 'CALCIUM', '25-0009', '161025-1', 500.00, 0.00, 0.00, 25000.00, 50.00, '2025-10-16', 'Pending', '2025-10-16 12:40:49', '2025-10-21 07:28:21', 'CHM00002', '40024', 'New'),
(20, NULL, 'CALCIUM', '25-0009', '161025-2', 300.00, 0.00, 0.00, 9000.00, 30.00, '2025-10-16', 'Pending', '2025-10-16 12:41:04', '2025-10-21 07:28:21', 'CHM00002', '40025', 'New'),
(21, NULL, 'Ag', 'FRESH', 'DOO09', 1000.00, 0.00, 271.00, 30000.00, 30.00, '2025-10-21', 'Approved', '2025-10-21 13:46:23', '2025-10-24 07:44:18', 'AG009', '40026', 'New'),
(22, NULL, 'Oil', 'BA 25-005', 'RM27102025-1', 1000.00, 0.00, 625.00, 150000.00, 150.00, '2025-10-27', 'Approved', '2025-10-27 05:04:30', '2025-10-27 06:56:46', 'OILOU90', '40030', 'New'),
(23, NULL, 'Flour', 'BA 25-006', 'RM27102025-2', 1000.00, 0.00, 250.00, 140000.00, 140.00, '2025-10-27', 'Approved', '2025-10-27 05:04:48', '2025-10-27 06:56:46', 'FLOU90', '40030', 'New'),
(24, NULL, 'Salt', 'BA 25-007', 'RM27102025-3', 1000.00, 0.00, 0.00, 10000.00, 10.00, '2025-10-27', 'Approved', '2025-10-27 05:05:13', '2025-10-27 07:45:46', 'SAILOU90', '40030', 'New'),
(25, NULL, 'Salt', '25-0046', '081020256', 500.00, 0.00, 425.00, 5000.00, 10.00, '2025-10-27', 'Approved', '2025-10-27 07:29:16', '2025-10-27 07:45:46', 'SAILOU90', '40031', 'New'),
(26, NULL, 'Avacado Oil', '25-001DM', '08102025KL', 1200.00, 0.00, 1014.71, 60000.00, 50.00, '2025-10-28', 'Approved', '2025-10-28 10:34:56', '2025-10-28 10:41:08', 'AV080', '40032', 'New'),
(27, NULL, 'Potash', '25-001DMS', '08102025KLS', 1000.00, 0.00, 835.29, 50000.00, 50.00, '2025-10-28', 'Approved', '2025-10-28 10:36:11', '2025-10-28 10:41:08', 'PO-080', '40032', 'New'),
(28, NULL, 'PAAS', 'BAT-0801', '10282025-1', 1000.00, 0.00, 675.00, 100000.00, 100.00, '2025-10-28', 'Approved', '2025-10-28 11:43:05', '2025-11-04 06:01:19', 'PAAS-09', '40033', 'New'),
(29, NULL, 'POTASIUM HYDROXIDE', 'BAT-08011', '08102025-2', 1500.00, 0.00, 1350.00, 300000.00, 200.00, '2025-10-28', 'Approved', '2025-10-28 11:43:51', '2025-11-04 06:01:19', 'PT-0970', '40033', 'New'),
(30, NULL, 'Purified Water', 'BAT-080112', '131025-3', 1200.00, 0.00, 75.00, 180000.00, 150.00, '2025-10-28', 'Approved', '2025-10-28 11:44:14', '2025-11-04 06:01:19', 'PURI-0700', '40033', 'New'),
(31, NULL, 'PAAS', '2025-11', '11-3-25', 5000.00, 0.00, 5000.00, 150000.00, 30.00, '2025-11-03', '', '2025-11-03 13:26:06', '2025-11-03 13:50:16', 'PAAS-09', '40036', 'New'),
(33, NULL, 'POTASIUM HYDROXIDE', '2025-13', '11-3-25-B', 4000.00, 0.00, 4000.00, 160000.00, 40.00, '2025-11-03', 'Approved', '2025-11-03 13:27:04', '2025-11-03 13:28:04', 'PT-0970', '40036', 'New');

-- --------------------------------------------------------

--
-- Table structure for table `chemical_names`
--

CREATE TABLE `chemical_names` (
  `id` int(11) NOT NULL,
  `chemical_name` varchar(150) NOT NULL,
  `main_category` varchar(100) DEFAULT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `group_code` varchar(100) DEFAULT NULL,
  `chemical_code` varchar(100) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `chemical_names`
--

INSERT INTO `chemical_names` (`id`, `chemical_name`, `main_category`, `group_name`, `group_code`, `chemical_code`, `category`, `description`, `created_at`) VALUES
(1, 'RLT 20F', 'Chemicals', NULL, NULL, '4011', 'TREATEMENT', 'Condensate Treatment', '2025-10-14 05:11:44'),
(2, 'Hotherm B', 'Chemicals', NULL, NULL, '2023', 'Fuel Additives', 'Fuel Additives', '2025-10-14 05:50:09'),
(3, 'SUGAR', 'Chemicals', 'ACID', '100001', 'CH009', 'Solid', 'Sugar', '2025-10-16 07:43:00'),
(4, 'GRUNDOFFS PUMB', 'Engineering Products', 'Engineering product', '2001', 'ST0009', 'Pumb', 'Pumb', '2025-10-16 08:01:12'),
(5, 'Oklin Facoal', 'Chemicals', 'ACID', '100001', 'OK001', 'ACID', 'Oklin Facoal', '2025-10-16 10:03:42'),
(6, 'ProClean HT4', 'Chemicals', 'ACID', '100001', 'OK003', 'ACID', 'ProClean HT4', '2025-10-16 10:04:16'),
(7, 'HYDROXIDE', 'Chemicals', 'Acids', 'GRP002', 'CHM008', 'Acid', 'Descalant', '2025-10-16 11:56:04'),
(8, 'SODA PEARLS', 'Chemicals', 'Alkaline', 'GRP002', 'CHM008', 'Acid', 'Alkaline', '2025-10-16 11:57:11'),
(9, 'CALCIUM', 'Chemicals', 'ACID', 'GRP003', 'CHM00002', 'Solid', 'Alkaline', '2025-10-16 12:38:19'),
(10, 'Ag', 'Chemicals', 'ACID', 'GR090', 'AG009', 'Solid', 'Ag', '2025-10-21 13:40:40'),
(11, 'DRUMS', 'Packaging Materials', 'DRUMS', 'DR004', 'DROD', 'DRUM', 'DRUM', '2025-10-24 09:19:40'),
(12, 'Flour', 'Chemicals', 'additive', 'FLO009', 'FLOU90', 'Solid', 'additives', '2025-10-27 05:00:29'),
(13, 'Oil', 'Chemicals', 'additive', 'OILLO009', 'OILOU90', 'Solid', 'Additive', '2025-10-27 05:01:21'),
(14, 'Salt', 'Chemicals', 'additive', 'SALLO009', 'SAILOU90', 'Solid', 'Salt', '2025-10-27 05:02:00'),
(15, 'Avacado Oil', 'Chemicals', 'additive', 'SP191', 'AV080', 'Liquid', 'Liquid', '2025-10-28 10:32:07'),
(16, 'Potash', 'Chemicals', 'additive', 'OP191', 'PO-080', 'Liquid', 'Additive', '2025-10-28 10:32:58'),
(17, 'PAAS', 'Chemicals', 'ACID', 'GRU80', 'PAAS-09', 'Acid', 'ACID', '2025-10-28 11:37:40'),
(18, 'POTASIUM HYDROXIDE', 'Chemicals', 'ACID', 'GRPO901', 'PT-0970', 'Alkaline', 'Alkaline', '2025-10-28 11:38:41'),
(19, 'Purified Water', 'Chemicals', 'Acids', 'GP-1001', 'PURI-0700', 'Acid', 'ACID', '2025-10-28 11:39:31'),
(20, '20L Jerrycan', 'Packaging Materials', 'Packaging', 'PK-08', 'PCK-09', 'Packaging', 'Packaging', '2025-10-31 12:25:14');

-- --------------------------------------------------------

--
-- Table structure for table `daily_reports`
--

CREATE TABLE `daily_reports` (
  `report_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `day_name` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `daily_reports`
--

INSERT INTO `daily_reports` (`report_id`, `user_id`, `report_date`, `day_name`, `created_at`) VALUES
(1, 10, '2025-10-30', 'Thursday', '2025-10-30 06:20:53'),
(2, 10, '2025-10-31', 'Friday', '2025-10-30 08:11:41'),
(3, 10, '2025-10-28', 'Tuesday', '2025-10-30 08:12:09'),
(4, 14, '2026-10-21', 'Wednesday', '2026-10-20 10:21:07');

-- --------------------------------------------------------

--
-- Table structure for table `daily_tasks`
--

CREATE TABLE `daily_tasks` (
  `task_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `time_taken` varchar(50) DEFAULT NULL,
  `overseen_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `daily_tasks`
--

INSERT INTO `daily_tasks` (`task_id`, `report_id`, `task_name`, `time_taken`, `overseen_by`, `created_at`) VALUES
(1, 1, 'CODING', '4HOURS', 'QC', '2025-10-30 06:20:53'),
(2, 1, 'SALE', '2HOURS', 'QC', '2025-10-30 06:20:53'),
(3, 2, 'CODING', '4HOURS', 'QC', '2025-10-30 08:11:41'),
(4, 3, 'CODING', '4HOURS', 'QC', '2025-10-30 08:12:09'),
(5, 3, 'SALE', '2HOURS', 'QC', '2025-10-30 08:12:09'),
(6, 3, 'CODING', '4HOURS', 'QC', '2025-10-30 08:12:09'),
(7, 1, 'CODING', '4HOURS', 'QC', '2025-10-30 08:13:44'),
(8, 4, 'CODING', '4HOURS', 'QC', '2026-10-20 10:21:07'),
(9, 4, 'CODING', '4HOURS', 'QC', '2026-10-20 10:21:07');

-- --------------------------------------------------------

--
-- Table structure for table `deductions`
--

CREATE TABLE `deductions` (
  `deduction_id` int(11) NOT NULL,
  `deduction_name` varchar(100) NOT NULL,
  `rate` decimal(5,2) NOT NULL COMMENT 'Percentage rate e.g. 10.00 = 10%',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `deductions`
--

INSERT INTO `deductions` (`deduction_id`, `deduction_name`, `rate`, `description`, `created_at`) VALUES
(1, 'HOUSING LEVY', 15.00, 'LEVY', '2025-10-27 09:53:45'),
(2, 'KRA', 15.00, 'KRA', '2025-10-27 10:02:37'),
(3, 'SHA', 18.00, 'SHA', '2025-10-27 10:02:51');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `delivery_contact` varchar(100) DEFAULT NULL,
  `expected_delivery` date DEFAULT NULL,
  `delivered_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`id`, `po_id`, `company_name`, `delivery_contact`, `expected_delivery`, `delivered_date`, `status`, `updated_at`) VALUES
(1, 3, NULL, NULL, NULL, NULL, 0, '2025-10-04 08:29:26'),
(2, 4, NULL, NULL, NULL, NULL, 0, '2025-10-04 08:31:31'),
(3, 1, NULL, NULL, NULL, NULL, 0, '2025-10-04 08:31:43'),
(4, 5, 'JUMIA', '0700000000', '2025-10-07', '2025-10-07', 1, '2025-10-07 07:12:17'),
(5, 9, 'JUMIA', '0700000000', '2025-10-13', '2025-10-06', 1, '2025-10-13 11:48:49');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_details`
--

CREATE TABLE `delivery_details` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `delivery_details`
--

INSERT INTO `delivery_details` (`id`, `company_name`, `address`, `contact`, `email`, `created_at`) VALUES
(1, 'KWAL', 'P.O BOX 76 NAIROBI', '0748135605', 'daviikyalo@gmail.com', '2025-10-22 11:47:24');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_items`
--

CREATE TABLE `delivery_items` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_orders`
--

CREATE TABLE `delivery_orders` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `delivery_number` varchar(100) DEFAULT NULL,
  `original_status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `delivery_orders`
--

INSERT INTO `delivery_orders` (`id`, `delivery_id`, `remarks`, `invoice_number`, `delivery_number`, `original_status`, `created_at`) VALUES
(1, 1, '', NULL, NULL, 'Pending', '2025-10-22 12:40:37'),
(2, 1, 'Delivery', 'DB00981', 'ISJ187', 'Assigned', '2025-10-23 05:04:54'),
(3, 1, 'Sale', 'cvt5', 'vtt', 'Pending', '2025-10-23 05:55:05'),
(4, 1, 'vvv', 'DB00981v', 'ISJ187v', 'Assigned', '2025-10-23 05:58:26'),
(6, 1, 'sal', 'DB00981vh', 'ISJ187vk', 'Assigned', '2025-10-23 06:35:07'),
(7, 1, 'sales', 'DB00981vhx', 'ISJ187vc', 'Pending', '2025-10-23 06:49:48'),
(9, 1, 'Deliver', 'DB009813', 'ISJ187vkd', 'Pending', '2025-10-27 06:59:52'),
(10, 1, 'done', 'DB00981vs', 'ISJ187vs', 'Pending', '2025-10-27 07:01:12'),
(11, 1, 'issued', 'DB00981vhn', 'ISJ187vkn', 'Pending', '2025-10-27 07:08:58'),
(12, 1, 'issued', 'DB009813f', 'ISJ187vkdf', 'Pending', '2025-10-27 07:27:06'),
(13, 1, 'issued', 'DB00981vhd', 'ISJ187vdd', 'Pending', '2025-10-27 07:30:56'),
(14, 1, 'sales', 'DB00981vhs', 'ISJ187vcs', 'Pending', '2025-10-27 07:45:46'),
(15, 1, 'SALE', 'DB0098132', 'ISJ187vkd4', 'Assigned', '2025-10-28 10:45:36'),
(16, 1, 'SAALES', 'DB00981323', 'ISJ187vkd3', 'Assigned', '2025-10-28 12:18:11'),
(17, 1, 'sale', '', 'A-0909', 'Pending', '2025-10-29 11:30:26'),
(18, 1, 'issued', 'DB00981vhssss', 'ISJ187vcssss', 'Pending', '2025-10-29 11:57:46'),
(19, 1, '', 'DB00981vhssssd', 'ISJ187vcssssd', 'Pending', '2025-10-29 12:01:42'),
(20, 1, 'issued', 'DB00981vhss', 'ISJ187vsg', 'Pending', '2025-10-29 12:05:37'),
(22, 1, '', 'DB00981s', 'ISJ187vs', 'Pending', '2025-10-29 12:08:10'),
(23, 1, 'issued', 'DB00981v34', 'ISJ187vk67', 'Assigned', '2025-10-29 12:19:55'),
(24, 1, 'sale', 'DB00981v70', 'ISJ187456', 'Assigned', '2025-10-29 12:24:27'),
(25, 1, 'sale', 'ss', 'ss', 'Pending', '2025-10-29 12:32:46'),
(29, 1, '', '', '', 'Pending', '2025-10-29 12:53:18');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_orders_store_b`
--

CREATE TABLE `delivery_orders_store_b` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `remarks` text DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `delivery_number` varchar(100) DEFAULT NULL,
  `original_status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `delivery_orders_store_b`
--

INSERT INTO `delivery_orders_store_b` (`id`, `company_name`, `remarks`, `invoice_number`, `delivery_number`, `original_status`, `created_at`) VALUES
(1, 'KICC', 'SALE', 'DB00981vhZ', 'ISJ187vZ', '', '2025-10-31 09:53:27');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_order_items`
--

CREATE TABLE `delivery_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `material_name` varchar(255) DEFAULT NULL,
  `pack_size` decimal(10,2) DEFAULT 0.00,
  `source_table` enum('stock_in','chemicals_in','finished_products') NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity_removed` decimal(10,2) NOT NULL,
  `remaining_quantity` decimal(10,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `delivery_order_items`
--

INSERT INTO `delivery_order_items` (`id`, `order_id`, `item_name`, `material_name`, `pack_size`, `source_table`, `item_id`, `quantity_removed`, `remaining_quantity`, `unit`, `created_at`) VALUES
(1, 1, 'GOLD (Batch: BATCH-090-234)', NULL, 0.00, 'finished_products', 1, 3.00, 5.00, '', '2025-10-22 12:40:37'),
(2, 1, 'GOLD (Batch: BATCH-090-2345)', NULL, 0.00, 'finished_products', 2, 2.00, 5.00, 'kg', '2025-10-22 12:40:37'),
(3, 1, 'Ag (Lot: DOO09)', NULL, 0.00, 'chemicals_in', 21, 300.00, 650.00, '', '2025-10-22 12:40:37'),
(4, 2, 'GOLD (Batch: BATCH-090-2345)', NULL, 0.00, 'finished_products', 2, 1.00, 3.00, 'kg', '2025-10-23 05:04:54'),
(5, 2, 'Ag (Lot: DOO09)', NULL, 0.00, 'chemicals_in', 21, 30.00, 350.00, '', '2025-10-23 05:04:54'),
(6, 3, 'GRUNDOFFS PUMB (Batch: BTS001)', NULL, 0.00, 'stock_in', 5, 50.00, 100.00, 'pieces', '2025-10-23 05:55:05'),
(7, 4, 'GRUNDOFFS PUMB (Batch: BTS001)', NULL, 0.00, 'stock_in', 5, 50.00, 100.00, 'pieces', '2025-10-23 05:58:26'),
(8, 6, 'GOLD (Batch: BATCH-090-234)', NULL, 0.00, 'finished_products', 1, 1.00, 2.00, '', '2025-10-23 06:35:07'),
(9, 6, 'GRUNDOFFS PUMB (Batch: BTS001)', NULL, 0.00, 'stock_in', 5, 50.00, 100.00, 'pieces', '2025-10-23 06:35:07'),
(10, 6, 'Ag (Batch: DOO09)', NULL, 0.00, 'chemicals_in', 21, 49.00, 320.00, 'kg', '2025-10-23 06:35:07'),
(11, 7, 'GRUNDOFFS PUMB (Batch: BTS001)', NULL, 0.00, 'stock_in', 5, 50.00, 50.00, 'pieces', '2025-10-23 06:49:48'),
(12, 9, 'WHEAT (Batch: BATCH-090-23456)', NULL, 0.00, 'finished_products', 3, 15.00, 20.00, 'Kg', '2025-10-27 06:59:52'),
(13, 10, 'WHEAT (Batch: BATCH-090-23456)', NULL, 0.00, 'finished_products', 3, 15.00, 5.00, 'Kg', '2025-10-27 07:01:12'),
(14, 11, 'WHEAT (Batch: BATCH-090-234567)', NULL, 0.00, 'finished_products', 4, 30.00, 25.00, 'kg', '2025-10-27 07:08:58'),
(15, 12, 'HYDROXIDE (Lot: 161025)', NULL, 0.00, 'chemicals_in', 16, 300.00, 250.00, 'kg', '2025-10-27 07:27:06'),
(16, 13, 'Salt (Lot: RM27102025-3)', NULL, 0.00, 'chemicals_in', 24, 700.00, 625.00, 'kg', '2025-10-27 07:30:56'),
(17, 14, 'Salt (Lot: RM27102025-3)', NULL, 0.00, 'chemicals_in', 24, 700.00, 625.00, 'kg', '2025-10-27 07:45:46'),
(18, 14, 'WHEAT (Batch: BATCH-090-2345678)', NULL, 0.00, 'finished_products', 5, 5.00, 20.00, 'kg', '2025-10-27 07:45:46'),
(19, 15, 'SOAP (Batch: BATCH-090-23D)', NULL, 0.00, 'finished_products', 7, 5.00, 7.00, 'Kg', '2025-10-28 10:45:36'),
(20, 16, 'Y400 (Batch: BATCH-090-23F)', NULL, 0.00, 'finished_products', 8, 7.00, 15.00, 'Drum', '2025-10-28 12:18:11'),
(21, 16, 'PAAS (Lot: 10282025-1)', NULL, 0.00, 'chemicals_in', 28, 100.00, 955.00, 'kg', '2025-10-28 12:18:11'),
(22, 17, 'Y400 (Batch: BATCH-090-23F)', NULL, 0.00, 'finished_products', 8, 8.00, 8.00, 'Drum', '2025-10-29 11:30:26'),
(23, 18, 'Y400 (Batch: BATCH-090-23f)', NULL, 20.00, '', 13, 4.00, 5.00, 'Kg', '2025-10-29 11:57:46'),
(24, 19, 'GRUNDOFFS PUMB (Batch: 25-00134)', NULL, 0.00, '', 6, 50.00, 900.00, 'kg', '2025-10-29 12:01:42'),
(25, 22, 'GRUNDOFFS PUMB (Batch: 25-00134)', NULL, 0.00, 'stock_in', 6, 100.00, 850.00, 'kg', '2025-10-29 12:08:10'),
(26, 23, 'Y400 (Batch: BATCH-090-23f)', '', 25.00, 'finished_products', 14, 3.00, 4.00, 'kg', '2025-10-29 12:19:55'),
(27, 24, 'Y400 (Batch: BATCH-090-23f)', 'DRUMS', 25.00, 'finished_products', 14, 1.00, 2.00, 'kg', '2025-10-29 12:24:27'),
(28, 25, 'Y400 (Batch: BATCH-090-23f)', 'DRUMS', 25.00, 'finished_products', 14, 1.00, 1.00, 'kg', '2025-10-29 12:32:46');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_order_items_store_b`
--

CREATE TABLE `delivery_order_items_store_b` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `material_name` varchar(255) DEFAULT NULL,
  `pack_size` decimal(10,2) DEFAULT 0.00,
  `source_table` varchar(100) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity_removed` decimal(10,2) DEFAULT 0.00,
  `remaining_quantity` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `delivery_order_items_store_b`
--

INSERT INTO `delivery_order_items_store_b` (`id`, `order_id`, `item_name`, `material_name`, `pack_size`, `source_table`, `item_id`, `quantity_removed`, `remaining_quantity`, `unit`, `created_at`) VALUES
(1, 1, 'Y400', '', 20.00, 'store_b_finished_products_in', 1, 500.00, 2000.00, 'kg', '2025-10-31 09:53:27'),
(2, 1, 'PAAS', '', 50.00, 'store_b_chemicals_in', 1, 500.00, 2000.00, 'kg', '2025-10-31 09:53:27');

-- --------------------------------------------------------

--
-- Table structure for table `department_requests`
--

CREATE TABLE `department_requests` (
  `id` int(11) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `item_used_by` varchar(100) DEFAULT NULL,
  `requested_qty` int(11) DEFAULT NULL,
  `requested_by` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('Pending Approval','Approved','Rejected') DEFAULT 'Pending Approval',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_qty` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `department_requests`
--

INSERT INTO `department_requests` (`id`, `item_name`, `item_used_by`, `requested_qty`, `requested_by`, `department`, `status`, `created_at`, `approved_qty`) VALUES
(1, 'Shoes', 'David', 1, 'MUTANIA KK', 'ICT', 'Approved', '2025-10-07 12:04:23', 2);

-- --------------------------------------------------------

--
-- Table structure for table `disciplinary_cases`
--

CREATE TABLE `disciplinary_cases` (
  `case_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `case_date` date NOT NULL,
  `allegation` varchar(255) NOT NULL,
  `case_details` text NOT NULL,
  `committee_comments` text DEFAULT NULL,
  `outcome` enum('Pending','No Action','Verbal Warning','Written Warning','Final Warning','Other') NOT NULL DEFAULT 'Pending',
  `action_taken` text DEFAULT NULL,
  `recorded_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `disciplinary_cases`
--

INSERT INTO `disciplinary_cases` (`case_id`, `user_id`, `case_date`, `allegation`, `case_details`, `committee_comments`, `outcome`, `action_taken`, `recorded_by`, `created_at`, `updated_at`) VALUES
(1, 9, '2026-10-19', 'LATE', 'LATE', 'Warning issued', 'Written Warning', 'yes', 'hr', '2026-10-19 05:41:26', '2026-10-19 05:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `disposables`
--

CREATE TABLE `disposables` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `asset_id` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `disposal_method` varchar(100) DEFAULT NULL,
  `disposal_date` varchar(100) DEFAULT NULL,
  `disposal_location` varchar(255) DEFAULT NULL,
  `quantity` varchar(50) DEFAULT NULL,
  `condition_before` varchar(100) DEFAULT NULL,
  `authorized_by` varchar(255) DEFAULT NULL,
  `handled_by` varchar(255) DEFAULT NULL,
  `regulatory_ref` varchar(255) DEFAULT NULL,
  `certificate_ref` varchar(255) DEFAULT NULL,
  `reason_for_disposal` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `disposables`
--

INSERT INTO `disposables` (`id`, `item_name`, `asset_id`, `category`, `disposal_method`, `disposal_date`, `disposal_location`, `quantity`, `condition_before`, `authorized_by`, `handled_by`, `regulatory_ref`, `certificate_ref`, `reason_for_disposal`, `remarks`) VALUES
(1, 'Computer', 'ssdjo2', 'electronic', 'Sold', '2025-10-09', 'Nairobi', '1', 'Old', 'QC', 'QC', 'NME123', 'MNS', 'New one', 'None'),
(2, 'Computer', 'ssdjo2', 'electronic', 'Sold', '2025-10-09', 'Nairobi', '1', 'Old', 'QC', 'QC', 'NME123', 'MNS', 'New one', 'None');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `kra_pin` varchar(20) DEFAULT NULL,
  `nssf_number` varchar(30) DEFAULT NULL,
  `nhif_number` varchar(30) DEFAULT NULL,
  `passport_path` varchar(255) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `date_of_hire` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `employment_type` varchar(20) DEFAULT 'Permanent',
  `contract_start` date DEFAULT NULL,
  `contract_end` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `first_name`, `last_name`, `national_id`, `kra_pin`, `nssf_number`, `nhif_number`, `passport_path`, `email`, `phone`, `department`, `position`, `date_of_hire`, `status`, `created_at`, `updated_at`, `employment_type`, `contract_start`, `contract_end`) VALUES
(11, 'JOHN', 'DOE', '8890900', 'KRAFGVSsXf', 'JSJSKHDL', 'eeemsd', NULL, 'Johndoe@gmail.com', '0748135605', 'ICT', 'tech', '2026-10-20', 'Active', '2026-10-20 07:55:38', '2026-10-20 07:55:38', 'Permanent', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee_allowances`
--

CREATE TABLE `employee_allowances` (
  `allowance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `allowance_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employee_allowances`
--

INSERT INTO `employee_allowances` (`allowance_id`, `user_id`, `allowance_name`, `amount`, `created_at`) VALUES
(5, 9, 'House', 8000.00, '2025-10-27 12:06:08'),
(8, 10, 'House', 8000.00, '2025-10-30 12:43:17'),
(9, 10, 'Entertainment', 5000.00, '2025-10-30 12:43:17'),
(10, 11, 'House', 6000.00, '2026-10-20 08:14:06');

-- --------------------------------------------------------

--
-- Table structure for table `employee_appraisal`
--

CREATE TABLE `employee_appraisal` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `evaluator_name` varchar(150) NOT NULL,
  `evaluator_department` varchar(150) DEFAULT NULL,
  `eval_date` date NOT NULL,
  `quality_of_work` int(11) DEFAULT NULL,
  `work_consistency` int(11) DEFAULT NULL,
  `communication` int(11) DEFAULT NULL,
  `independent_work` int(11) DEFAULT NULL,
  `takes_initiative` int(11) DEFAULT NULL,
  `exercises_teamwork` int(11) DEFAULT NULL,
  `productivity` int(11) DEFAULT NULL,
  `creativity` int(11) DEFAULT NULL,
  `honesty` int(11) DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employee_appraisal`
--

INSERT INTO `employee_appraisal` (`id`, `user_id`, `evaluator_name`, `evaluator_department`, `eval_date`, `quality_of_work`, `work_consistency`, `communication`, `independent_work`, `takes_initiative`, `exercises_teamwork`, `productivity`, `creativity`, `honesty`, `total_score`, `created_at`) VALUES
(1, 10, 'DAVE KINDLE', 'TECH', '2025-10-30', 2, 4, 4, 5, 1, 3, 5, 3, 5, 71.11, '2025-10-30 11:54:20');

-- --------------------------------------------------------

--
-- Table structure for table `employee_deductions`
--

CREATE TABLE `employee_deductions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `deduction_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employee_deductions`
--

INSERT INTO `employee_deductions` (`id`, `user_id`, `deduction_id`) VALUES
(5, 9, 3),
(6, 9, 1),
(10, 10, 1),
(11, 10, 2),
(12, 11, 2),
(13, 11, 1),
(14, 11, 3),
(15, 14, 1),
(16, 14, 2);

-- --------------------------------------------------------

--
-- Table structure for table `employee_information`
--

CREATE TABLE `employee_information` (
  `info_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `skills` text DEFAULT NULL,
  `certifications` text DEFAULT NULL,
  `expected_salary` decimal(10,2) DEFAULT 0.00,
  `approved_salary` decimal(10,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_information_items`
--

CREATE TABLE `employee_information_items` (
  `item_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `category` enum('education','experience') NOT NULL,
  `field1` varchar(255) DEFAULT NULL,
  `field2` varchar(255) DEFAULT NULL,
  `field3` varchar(255) DEFAULT NULL,
  `field4` varchar(255) DEFAULT NULL,
  `field5` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_salary`
--

CREATE TABLE `employee_salary` (
  `salary_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `base_salary` decimal(10,2) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `employee_salary`
--

INSERT INTO `employee_salary` (`salary_id`, `user_id`, `base_salary`, `bank_name`, `bank_account`, `created_at`) VALUES
(1, 10, 45000.00, 'Equity bank', '90ks0288', '2025-10-27 10:10:50'),
(2, 9, 30000.00, 'Equity bank', '90ks02884', '2025-10-27 12:06:08'),
(3, 11, 80000.00, 'Equity bank', '90ks028846', '2026-10-20 08:14:06'),
(4, 14, 90000.00, 'Equity bank', '90ks028846d', '2026-10-20 09:32:32');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `expense_name` varchar(100) NOT NULL,
  `expense_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('Paid','Not Paid') DEFAULT 'Not Paid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `finished_products`
--

CREATE TABLE `finished_products` (
  `id` int(11) NOT NULL,
  `production_run_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `obtained_yield` decimal(10,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `pack_size` decimal(10,2) DEFAULT NULL,
  `remaining_size` decimal(10,2) DEFAULT NULL,
  `packaged_quantity` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `finished_products`
--

INSERT INTO `finished_products` (`id`, `production_run_id`, `product_id`, `material_id`, `product_name`, `batch_number`, `obtained_yield`, `unit`, `pack_size`, `remaining_size`, `packaged_quantity`, `created_at`) VALUES
(1, 111, 13, NULL, 'GOLD', 'BATCH-090-234', 50.00, '', 5.00, 1.00, 0.00, '2025-10-22 08:35:50'),
(2, 113, 13, NULL, 'GOLD', 'BATCH-090-2345', 50.00, 'kg', 5.00, 2.00, 0.00, '2025-10-22 09:42:21'),
(3, 115, 14, NULL, 'WHEAT', 'BATCH-090-23456', 700.00, 'Kg', 20.00, 0.00, 0.00, '2025-10-27 05:18:07'),
(4, 117, 14, NULL, 'WHEAT', 'BATCH-090-234567', 300.00, 'kg', 25.00, 0.00, 0.00, '2025-10-27 06:39:02'),
(5, 119, 14, NULL, 'WHEAT', 'BATCH-090-2345678', 250.00, 'kg', 25.00, 15.00, 0.00, '2025-10-27 06:42:53'),
(6, 121, 14, NULL, 'WHEAT', 'BATCH-090-23456789', 250.00, 'kg', 25.00, 10.00, 0.00, '2025-10-27 06:57:59'),
(7, 123, 15, NULL, 'SOAP', 'BATCH-090-23D', 350.00, 'Drums', 50.00, 2.00, 0.00, '2025-10-28 10:43:30'),
(8, 125, 16, NULL, 'Y400', 'BATCH-090-23F', 300.00, 'Drum', 20.00, 0.00, 0.00, '2025-10-28 12:03:41'),
(13, 128, 16, 7, 'Y400', 'BATCH-090-23f', 200.00, 'Kg', 20.00, 0.00, 100.00, '2025-10-29 08:35:25'),
(14, 128, 16, 7, 'Y400', 'BATCH-090-23f', 200.00, 'kg', 25.00, 0.00, 100.00, '2025-10-29 08:35:25'),
(15, 130, 16, 8, 'Y400', 'BATCH-090-23-2-1', 1000.00, 'kg', 20.00, 25.00, 500.00, '2025-11-04 06:05:23'),
(16, 130, 16, 9, 'Y400', 'BATCH-090-23-2-1', 1000.00, 'Kg', 10.00, 40.00, 400.00, '2025-11-04 06:05:23'),
(17, 130, 16, 9, 'Y400', 'BATCH-090-23-2-1', 1000.00, '', 25.00, 4.00, 100.00, '2025-11-04 06:05:23');

-- --------------------------------------------------------

--
-- Table structure for table `fuel_cost`
--

CREATE TABLE `fuel_cost` (
  `id` int(11) NOT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `amount_refueled` decimal(10,2) DEFAULT NULL,
  `refueled_by` varchar(100) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `fuel_cost`
--

INSERT INTO `fuel_cost` (`id`, `vehicle_number`, `amount_refueled`, `refueled_by`, `approved_by`, `receipt_path`, `created_at`) VALUES
(1, 'KNM 1190', 11000.00, 'MUTANIA', 'HR', 'uploads/fuel_receipts/1759815120_pic.jpg', '2025-10-07 05:32:00'),
(2, 'KFV 009A', 3000.00, 'JOE', 'JOES', 'uploads/fuel_receipts/1759822098_Screenshot 2024-06-19 085433.jpg', '2025-10-07 07:28:18');

-- --------------------------------------------------------

--
-- Table structure for table `goals`
--

CREATE TABLE `goals` (
  `goal_id` int(11) NOT NULL,
  `goal_title` varchar(255) NOT NULL,
  `goal_description` text NOT NULL,
  `department` varchar(150) NOT NULL,
  `tasks_done` text DEFAULT NULL,
  `overseen_by` varchar(150) NOT NULL,
  `status` enum('Pending','Completed') NOT NULL DEFAULT 'Pending',
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `goals`
--

INSERT INTO `goals` (`goal_id`, `goal_title`, `goal_description`, `department`, `tasks_done`, `overseen_by`, `status`, `comments`, `created_at`, `updated_at`) VALUES
(1, 'Monthly stock', 'count', 'ICT', 'Counting', 'QC', 'Completed', '', '2026-10-20 06:10:07', '2026-10-20 06:12:17');

-- --------------------------------------------------------

--
-- Table structure for table `goal_employees`
--

CREATE TABLE `goal_employees` (
  `id` int(11) NOT NULL,
  `goal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `goal_employees`
--

INSERT INTO `goal_employees` (`id`, `goal_id`, `user_id`) VALUES
(3, 1, 9),
(4, 1, 10);

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `group_id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `groups`
--

INSERT INTO `groups` (`group_id`, `group_name`, `description`) VALUES
(1, 'SuperAdmin', 'Full access to all modules'),
(2, 'HR', 'Human Resources Department'),
(3, 'Stores', 'Inventory and stores management'),
(4, 'Production', 'Production Department'),
(5, 'QualityControl', 'Quality control department'),
(6, 'Procurement', 'Handles supplier relations, purchases, and procurement reports'),
(7, 'Drivers', 'Handles deliveries, vehicles, and transport management'),
(8, 'Reports', 'Access to overall system reports and analytics'),
(9, 'Staff', 'Staff account'),
(10, 'Accounts', 'Account Login'),
(11, 'Sales', 'Sales Login'),
(12, 'litein', 'litein store');

-- --------------------------------------------------------

--
-- Table structure for table `inspected_chemicals_in`
--

CREATE TABLE `inspected_chemicals_in` (
  `id` int(11) NOT NULL,
  `chemical_id` int(11) NOT NULL,
  `rm_lot_no` varchar(100) DEFAULT NULL,
  `approved_quantity` decimal(10,2) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `tests` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tests`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `inspected_chemicals_in`
--

INSERT INTO `inspected_chemicals_in` (`id`, `chemical_id`, `rm_lot_no`, `approved_quantity`, `approved_by`, `approved_date`, `tests`, `created_at`) VALUES
(7, 10, '161025', 100.00, 'Selinah', '2025-10-16', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"3\"}]', '2025-10-16 09:54:10'),
(8, 11, '151025', 140.00, 'Selinah', '2025-10-16', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"3\"}]', '2025-10-16 09:54:23'),
(9, 12, '141025', 50.00, 'Selinah', '2025-10-16', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"3\"}]', '2025-10-16 09:54:40'),
(10, 13, '141025', 50.00, 'Selinah', '2025-10-16', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"3\"}]', '2025-10-16 10:06:09'),
(11, 14, '141025', 50.00, 'Selinah', '2025-10-16', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"3\"}]', '2025-10-16 10:06:17'),
(12, 15, '161025', 250.00, 'Selinah', '2025-10-16', '[{\"type\":\"PH\",\"specification\":\"2-4\",\"result\":\"3\"}]', '2025-10-16 12:03:17'),
(13, 16, '161025', 250.00, 'Selinah', '2025-10-16', '[{\"type\":\"PH\",\"specification\":\"2-4\",\"result\":\"3\"}]', '2025-10-16 12:03:32'),
(14, 17, '161025', 250.00, 'Selinah', '2025-10-16', '[{\"type\":\"PH\",\"specification\":\"2-4\",\"result\":\"3\"}]', '2025-10-16 12:35:15'),
(15, 18, '161025', 250.00, 'Selinah', '2025-10-16', '[{\"type\":\"PH\",\"specification\":\"2-4\",\"result\":\"3\"}]', '2025-10-16 12:35:33'),
(16, 21, 'DOO09', 1000.00, 'Selinah', '2025-10-24', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"ok\"}]', '2025-10-24 07:44:18'),
(17, 24, 'RM27102025-3', 1000.00, 'Selinah', '2025-10-27', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"3\"},{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"4-8\"}]', '2025-10-27 05:06:07'),
(18, 23, 'RM27102025-2', 1000.00, 'Selinah', '2025-10-27', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"3\"}]', '2025-10-27 05:06:24'),
(19, 22, 'RM27102025-1', 1000.00, 'Selinah', '2025-10-27', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"3\"},{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"4-8\"}]', '2025-10-27 05:06:34'),
(20, 25, '081020256', 500.00, 'QC', '2025-10-27', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"4-8\"}]', '2025-10-27 07:29:48'),
(21, 26, '08102025KL', 1200.00, 'Selinah', '2025-10-28', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"4-8\"}]', '2025-10-28 10:37:41'),
(22, 27, '08102025KLS', 100.00, 'Selinah', '2025-10-28', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"4-8\"}]', '2025-10-28 10:37:53'),
(23, 28, '10282025-1', 1000.00, 'QC', '2025-10-28', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"ok\"}]', '2025-10-28 11:50:40'),
(24, 29, '08102025-2', 1500.00, 'QC', '2025-10-28', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"ok\"}]', '2025-10-28 11:50:58'),
(25, 30, '131025-3', 1200.00, 'QC', '2025-10-28', '[{\"type\":\"PH\",\"specification\":\"Measure ph\",\"result\":\"ok\"}]', '2025-10-28 11:51:19');

-- --------------------------------------------------------

--
-- Table structure for table `issued_items`
--

CREATE TABLE `issued_items` (
  `issue_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `department` varchar(150) DEFAULT NULL,
  `issued_to_person` varchar(150) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('Issued','Returned') NOT NULL DEFAULT 'Issued',
  `issue_comments` text DEFAULT NULL,
  `return_comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `issued_items`
--

INSERT INTO `issued_items` (`issue_id`, `item_name`, `item_code`, `quantity`, `department`, `issued_to_person`, `issue_date`, `due_date`, `return_date`, `status`, `issue_comments`, `return_comments`, `created_at`, `updated_at`) VALUES
(1, 'Shoes', NULL, 1, 'ICT', 'kk', '2026-10-20', NULL, NULL, 'Issued', NULL, NULL, '2026-10-20 06:45:59', '2026-10-20 06:45:59');

-- --------------------------------------------------------

--
-- Table structure for table `job_adverts`
--

CREATE TABLE `job_adverts` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `employment_type` enum('Full-time','Part-time','Contract','Internship','Temporary') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Full-time',
  `description` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `responsibilities` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualifications` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `application_deadline` date NOT NULL,
  `status` enum('Draft','Published','Closed','Revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `link_token_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `job_advert_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cover_letter` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Received','Under Review','Shortlisted','Rejected','Hired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Received',
  `privacy_consent` tinyint(1) NOT NULL DEFAULT 0,
  `consent_at` datetime DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_application_files`
--

CREATE TABLE `job_application_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` int(10) UNSIGNED NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_descriptions`
--

CREATE TABLE `job_descriptions` (
  `job_id` int(11) NOT NULL,
  `department` varchar(150) NOT NULL,
  `job_role` varchar(150) NOT NULL,
  `position_title` varchar(200) NOT NULL,
  `requirements` text NOT NULL,
  `skills_required` text NOT NULL,
  `responsibilities` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `job_descriptions`
--

INSERT INTO `job_descriptions` (`job_id`, `department`, `job_role`, `position_title`, `requirements`, `skills_required`, `responsibilities`, `created_at`, `updated_at`) VALUES
(1, 'ICT', 'ICT OFFICER', 'IT OFFICER', 'Bachelors', 'Cisco', 'Network\r\ncctv\r\nuser support', '2026-10-20 05:57:13', '2026-10-20 05:57:13');

-- --------------------------------------------------------

--
-- Table structure for table `label_reconciliation`
--

CREATE TABLE `label_reconciliation` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `material_name` varchar(255) NOT NULL,
  `quantity_removed` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remaining_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `issued_to` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `used` decimal(10,2) DEFAULT 0.00,
  `wasted` decimal(10,2) DEFAULT 0.00,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `reconciled_by` varchar(150) DEFAULT NULL,
  `reconciled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `label_reconciliation`
--

INSERT INTO `label_reconciliation` (`id`, `material_id`, `material_name`, `quantity_removed`, `remaining_quantity`, `issued_to`, `description`, `batch_number`, `used`, `wasted`, `unit_cost`, `total_cost`, `reconciled_by`, `reconciled_at`, `remarks`) VALUES
(1, 5, 'Hotherm B labels', 20.00, 8.00, 'Production', 'Issued', 'BATCH-090-23F', 14.00, 6.00, 10.00, 200.00, NULL, '2025-10-30 08:56:28', NULL),
(2, 4, 'Hotherm B labels', 10.00, 14.00, 'Production', 'ff', '', 5.00, 5.00, 13.00, 130.00, NULL, '2025-11-03 06:20:09', NULL),
(3, 6, 'Hotherm B labels', 50.00, 40.00, 'Production', 'issued', 'BATCH-090-23-2-1', 40.00, 10.00, 10.00, 500.00, NULL, '2025-11-04 06:19:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `leave_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` enum('Annual','Sick','Paternity','Maternity') NOT NULL,
  `description` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) GENERATED ALWAYS AS (to_days(`end_date`) - to_days(`start_date`) + 1) STORED,
  `status` enum('Pending','Approved','Denied') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `leaves`
--

INSERT INTO `leaves` (`leave_id`, `user_id`, `leave_type`, `description`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 9, 'Sick', 'SICK', '2025-10-24', '2025-10-27', 'Approved', '2025-10-24 13:09:35', '2025-10-24 13:21:37'),
(2, 10, 'Sick', 'sick', '2025-10-27', '2025-10-31', 'Approved', '2025-10-27 12:37:00', '2025-10-27 12:38:43'),
(3, 3, 'Sick', 'SICK', '2025-10-27', '2025-11-01', 'Pending', '2025-10-27 13:42:48', '2025-10-27 13:42:48'),
(4, 10, 'Annual', 'SICK', '2025-10-27', '2025-11-01', 'Approved', '2025-10-28 05:38:39', '2025-10-28 05:38:58'),
(5, 10, 'Maternity', 'Maternity', '2025-10-28', '2025-10-31', 'Approved', '2025-10-28 09:51:56', '2025-10-28 09:52:23'),
(6, 10, 'Annual', 'SICK', '2025-11-02', '2025-11-06', 'Approved', '2025-10-28 12:56:38', '2025-10-28 12:56:56');

-- --------------------------------------------------------

--
-- Table structure for table `lunch_expense`
--

CREATE TABLE `lunch_expense` (
  `lunch_id` int(11) NOT NULL,
  `week_no` int(2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `items` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `petty_cash_no` varchar(100) DEFAULT NULL,
  `approved_by` varchar(150) DEFAULT NULL,
  `payment_status` enum('Pending','Paid') DEFAULT 'Pending',
  `items_bought_by` varchar(100) DEFAULT NULL,
  `transport_cost` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `lunch_expense`
--

INSERT INTO `lunch_expense` (`lunch_id`, `week_no`, `start_date`, `end_date`, `items`, `total_amount`, `petty_cash_no`, `approved_by`, `payment_status`, `items_bought_by`, `transport_cost`, `created_at`) VALUES
(1, 39, '2025-09-28', '2025-10-04', '[{\"item\":\"Onions1kg\",\"cost\":500},{\"item\":\"Tomatos\",\"cost\":1000},{\"item\":\"Flour\",\"cost\":500}]', 2100.00, NULL, NULL, 'Pending', 'Nicho', 100.00, '2025-10-03 07:02:00'),
(2, 42, '2025-10-19', '2025-10-25', '[{\"item\":\"RICE-2KG\",\"cost\":260},{\"item\":\"Onions1kg\",\"cost\":100}]', 460.00, 'DBV009', 'Accounts', 'Paid', 'Nicho', 100.00, '2025-10-23 07:48:41');

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `material_name` varchar(100) NOT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `material_code` varchar(50) DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`id`, `material_name`, `po_number`, `unit`, `material_code`, `cost`, `quantity`, `created_at`) VALUES
(1, 'Labels', NULL, NULL, NULL, 1000.00, 5, '2025-09-29 13:19:03'),
(2, 'Jerry Can 20L', NULL, NULL, NULL, 500.00, 0, '2025-10-01 06:40:51'),
(3, '5litre jerrycan', NULL, NULL, NULL, 20.00, 0, '2025-10-13 11:10:10'),
(4, 'Hotherm B labels', NULL, NULL, NULL, 13.00, 14, '2025-10-15 08:58:54'),
(5, 'Hotherm B labels', '40028', 'pieces', NULL, 10.00, 8, '2025-10-24 09:01:58'),
(6, 'Hotherm B labels', '40028', 'pieces', NULL, 10.00, 40, '2025-10-24 09:06:09'),
(7, 'DRUMS', '40029', 'pieces', NULL, 10.00, 411, '2025-10-24 09:20:42'),
(8, 'DRUMS', '2011', 'pieces', NULL, 100.00, -15, '2025-10-31 12:17:27'),
(9, '20L Jerrycan', '40035', 'units', 'PCK-09', 50.00, 86, '2025-10-31 13:09:38');

-- --------------------------------------------------------

--
-- Table structure for table `material_out_history`
--

CREATE TABLE `material_out_history` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `material_name` varchar(100) NOT NULL,
  `quantity_removed` int(11) NOT NULL,
  `remaining_quantity` int(11) NOT NULL,
  `issued_to` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `used` decimal(10,2) DEFAULT 0.00,
  `wasted` decimal(10,2) DEFAULT 0.00,
  `removed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `material_out_history`
--

INSERT INTO `material_out_history` (`id`, `material_id`, `material_name`, `quantity_removed`, `remaining_quantity`, `issued_to`, `description`, `batch_number`, `used`, `wasted`, `removed_at`, `unit_cost`, `total_cost`) VALUES
(1, 1, 'Labels', 5, 10, 'Production', 'For lynnmax', NULL, 0.00, 0.00, '2025-09-30 10:13:45', 0.00, 0.00),
(2, 1, 'Labels', 5, 5, 'Production', 'For lynnmax', NULL, 0.00, 0.00, '2025-09-30 10:19:36', 0.00, 0.00),
(3, 3, '5litre jerrycan', 20, 80, 'Production', 'Packaging', NULL, 0.00, 0.00, '2025-10-13 11:11:08', 0.00, 0.00),
(4, 3, '5litre jerrycan', 2, 74, 'ENVIRONHIB 100BR', 'Used for approved packaging of production run #71', NULL, 0.00, 0.00, '2025-10-15 13:40:33', 0.00, 0.00),
(5, 4, 'Hotherm B labels', 2, 98, 'ENVIRONHIB 100BR', 'Used for approved packaging of production run #71', NULL, 0.00, 0.00, '2025-10-15 13:40:33', 0.00, 0.00),
(6, 3, '5litre jerrycan', 5, 69, 'Disperse 5F-0500', 'Used for approved packaging of production run #73', NULL, 0.00, 0.00, '2025-10-16 09:38:44', 0.00, 0.00),
(7, 4, 'Hotherm B labels', 5, 93, 'Disperse 5F-0500', 'Used for approved packaging of production run #73', NULL, 0.00, 0.00, '2025-10-16 09:38:44', 0.00, 0.00),
(8, 3, '5litre jerrycan', 5, 64, 'POLYHIB C127', 'Used for approved packaging of production run #75', NULL, 0.00, 0.00, '2025-10-16 10:47:44', 0.00, 0.00),
(9, 4, 'Hotherm B labels', 5, 88, 'POLYHIB C127', 'Used for approved packaging of production run #75', NULL, 0.00, 0.00, '2025-10-16 10:47:44', 0.00, 0.00),
(10, 3, '5litre jerrycan', 5, 59, 'POLYHIB C127', 'Used for approved packaging of production run #75', NULL, 0.00, 0.00, '2025-10-16 10:47:44', 0.00, 0.00),
(11, 4, 'Hotherm B labels', 5, 83, 'POLYHIB C127', 'Used for approved packaging of production run #75', NULL, 0.00, 0.00, '2025-10-16 10:47:44', 0.00, 0.00),
(12, 3, '5litre jerrycan', 5, 54, 'POLYHIB C127', 'Used for approved packaging of production run #75', NULL, 0.00, 0.00, '2025-10-16 10:47:44', 0.00, 0.00),
(13, 3, '5litre jerrycan', 5, 49, 'POLYHIB C127', 'Used for approved packaging of production run #77', NULL, 0.00, 0.00, '2025-10-16 11:16:46', 0.00, 0.00),
(14, 4, 'Hotherm B labels', 5, 78, 'POLYHIB C127', 'Used for approved packaging of production run #77', NULL, 0.00, 0.00, '2025-10-16 11:16:46', 0.00, 0.00),
(15, 3, '5litre jerrycan', 12, 37, 'OXYCLEAN PLUS', 'Used for approved packaging of production run #79', NULL, 0.00, 0.00, '2025-10-16 12:18:12', 0.00, 0.00),
(16, 4, 'Hotherm B labels', 12, 66, 'OXYCLEAN PLUS', 'Used for approved packaging of production run #79', NULL, 0.00, 0.00, '2025-10-16 12:18:12', 0.00, 0.00),
(17, 3, '5litre jerrycan', 25, 12, 'sunflowercafe', 'Used for approved packaging of production run #81', NULL, 0.00, 0.00, '2025-10-22 06:03:45', 0.00, 0.00),
(18, 3, '5litre jerrycan', 12, 0, 'GOLD', 'Used for approved packaging of production run #109', NULL, 0.00, 0.00, '2025-10-22 06:07:36', 0.00, 0.00),
(19, 4, 'Hotherm B labels', 10, 56, 'GOLD', 'Used for approved packaging of production run #111', NULL, 0.00, 0.00, '2025-10-22 08:52:08', 0.00, 0.00),
(20, 4, 'Hotherm B labels', 10, 46, 'GOLD', 'Used for approved packaging of production run #113', NULL, 0.00, 0.00, '2025-10-22 09:42:39', 0.00, 0.00),
(21, 7, 'DRUMS', 35, 465, 'WHEAT', 'Used for approved packaging of production run #115', NULL, 0.00, 0.00, '2025-10-27 05:18:45', 0.00, 0.00),
(22, 7, 'DRUMS', 12, 453, 'WHEAT', 'Used for approved packaging of production run #117', NULL, 0.00, 0.00, '2025-10-27 06:39:31', 0.00, 0.00),
(23, 5, 'Hotherm B labels', 12, 88, 'WHEAT', 'Used for approved packaging of production run #117', NULL, 0.00, 0.00, '2025-10-27 06:39:31', 0.00, 0.00),
(24, 7, 'DRUMS', 10, 443, 'WHEAT', 'Used for approved packaging of production run #119', NULL, 0.00, 0.00, '2025-10-27 06:43:08', 0.00, 0.00),
(25, 6, 'Hotherm B labels', 10, 90, 'WHEAT', 'Used for approved packaging of production run #119', NULL, 0.00, 0.00, '2025-10-27 06:43:08', 0.00, 0.00),
(26, 7, 'DRUMS', 7, 436, 'SOAP', 'Used for approved packaging of production run #123', NULL, 0.00, 0.00, '2025-10-28 10:44:06', 0.00, 0.00),
(27, 4, 'Hotherm B labels', 7, 39, 'SOAP', 'Used for approved packaging of production run #123', NULL, 0.00, 0.00, '2025-10-28 10:44:06', 0.00, 0.00),
(28, 7, 'DRUMS', 15, 421, 'Y400', 'Used for approved packaging of production run #125', NULL, 0.00, 0.00, '2025-10-28 12:04:23', 0.00, 0.00),
(29, 4, 'Hotherm B labels', 15, 24, 'Y400', 'Used for approved packaging of production run #125', NULL, 0.00, 0.00, '2025-10-28 12:04:23', 0.00, 0.00),
(30, 7, 'DRUMS', 10, 411, 'Y400', 'Used for approved packaging of production run #128', NULL, 0.00, 0.00, '2025-10-29 08:06:30', 0.00, 0.00),
(31, 2, 'Jerry Can 20L', 10, 0, 'Y400', 'Used for approved packaging of production run #128', NULL, 0.00, 0.00, '2025-10-29 08:06:30', 0.00, 0.00),
(35, 5, 'Hotherm B labels', 20, 8, 'Production', 'Issued', 'BATCH-090-23F', 14.00, 6.00, '2025-10-30 08:56:28', 10.00, 200.00),
(36, 4, 'Hotherm B labels', 10, 14, 'Production', 'ff', '', 5.00, 5.00, '2025-11-03 06:20:09', 13.00, 130.00),
(37, 8, 'DRUMS', 25, -15, 'Y400', 'Used for approved packaging of production run #130', NULL, 0.00, 0.00, '2025-11-04 06:05:45', 0.00, 0.00),
(38, 9, '20L Jerrycan', 40, 90, 'Y400', 'Used for approved packaging of production run #130', NULL, 0.00, 0.00, '2025-11-04 06:05:45', 0.00, 0.00),
(39, 9, '20L Jerrycan', 4, 86, 'Y400', 'Used for approved packaging of production run #130', NULL, 0.00, 0.00, '2025-11-04 06:05:45', 0.00, 0.00),
(40, 6, 'Hotherm B labels', 50, 40, 'Production', 'issued', 'BATCH-090-23-2-1', 40.00, 10.00, '2025-11-04 06:19:05', 10.00, 500.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_deliveries`
--

CREATE TABLE `order_deliveries` (
  `id` int(11) NOT NULL,
  `delivery_day` varchar(100) DEFAULT NULL,
  `delivery_date` date NOT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_deliveries`
--

INSERT INTO `order_deliveries` (`id`, `delivery_day`, `delivery_date`, `status`, `created_at`) VALUES
(1, 'Monday dispatch', '2025-10-08', 'Completed', '2025-10-08 13:43:16'),
(2, 'KWAL XYZ Delivery', '2025-10-13', 'Completed', '2025-10-13 12:03:11'),
(3, 'Monday dispatch', '2025-10-23', 'Completed', '2025-10-23 05:05:23'),
(4, 'Monday dispatch', '2025-10-23', 'Pending', '2025-10-23 06:44:57'),
(5, 'TUESDAY-28/7', '2025-10-28', 'Pending', '2025-10-28 10:46:32'),
(6, 'TUE DISPATCH-28', '2025-10-28', 'Completed', '2025-10-28 12:21:11'),
(7, 'WENO DISPATCH', '2025-10-29', 'Pending', '2025-10-29 13:27:13'),
(8, 'WENO DISPATCH', '2025-10-29', 'Pending', '2025-10-29 13:49:10');

-- --------------------------------------------------------

--
-- Table structure for table `order_deliveries_store_b`
--

CREATE TABLE `order_deliveries_store_b` (
  `id` int(11) NOT NULL,
  `delivery_day` varchar(100) NOT NULL,
  `delivery_date` date NOT NULL,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_deliveries_store_b`
--

INSERT INTO `order_deliveries_store_b` (`id`, `delivery_day`, `delivery_date`, `status`, `created_at`) VALUES
(1, 'Monday dispatch', '2025-10-31', '', '2025-10-31 11:47:30');

-- --------------------------------------------------------

--
-- Table structure for table `order_delivery_items`
--

CREATE TABLE `order_delivery_items` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `delivery_order_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_delivery_items`
--

INSERT INTO `order_delivery_items` (`id`, `delivery_id`, `destination`, `delivery_order_id`, `product_name`, `quantity`) VALUES
(1, 1, 'RUIRU', 0, 'LYNNCARE', '100KG'),
(2, 1, 'RUIRU', 0, 'PRO', '10KG'),
(3, 2, 'RUIRU', 0, 'XYZ', '60KG'),
(4, 3, 'RUIRU', 2, '', ''),
(5, 4, 'RUIRUU', 6, '', ''),
(6, 4, 'RUIRU', 4, '', ''),
(7, 5, 'RUIRU', 15, '', ''),
(8, 6, 'RUIRU', 16, '', ''),
(9, 7, 'RUIRU', 23, '', ''),
(10, 8, 'RUIRU', 24, '', '');

-- --------------------------------------------------------

--
-- Table structure for table `order_delivery_items_store_b`
--

CREATE TABLE `order_delivery_items_store_b` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `delivery_order_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_delivery_items_store_b`
--

INSERT INTO `order_delivery_items_store_b` (`id`, `delivery_id`, `destination`, `delivery_order_id`, `created_at`) VALUES
(1, 1, 'RTHIKA', 1, '2025-10-31 11:47:30');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `manual_name` varchar(255) DEFAULT NULL,
  `chemical_code` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `po_id`, `product_id`, `manual_name`, `chemical_code`, `quantity`, `unit`, `unit_price`) VALUES
(1, 6, NULL, NULL, NULL, 100.00, 'kg', 1000.00),
(2, 6, NULL, NULL, NULL, 299.00, 'kg', 50.00),
(3, 7, NULL, 'ALKACLEAN', NULL, 200.00, 'Ltrs', 1000.00),
(4, 7, NULL, 'SALT', NULL, 20.00, 'kg', 20.00),
(5, 8, NULL, 'PRO CLEAN', NULL, 20.00, 'Ltrs', 1000.00),
(6, 8, NULL, 'BOILER CHEM', NULL, 20.00, 'Kg', 500.00),
(7, 9, NULL, 'UFACID', NULL, 215.00, 'kg', 400.00),
(8, 10, NULL, 'RLT 20F', '', 130.00, 'Kg', 330.00),
(9, 10, NULL, 'Hotherm B', NULL, 300.00, 'kg', 150.00),
(10, 10, NULL, 'LABELS', NULL, 200.00, 'Pieces', 3.00),
(11, 11, NULL, 'Hotherm B', '', 90.00, 'Kg', 300.00),
(12, 11, NULL, 'LABLES-LYNNCARE', '2023', 100.00, 'Pieces', 15.00),
(13, 11, NULL, 'RLT 20F', '', 91.00, 'KG', 100.00),
(17, 12, NULL, 'Hotherm B', '', 130.00, 'Kg', 330.00),
(18, 12, NULL, 'RLT 20F', NULL, 20.00, 'kg', 150.00),
(19, 12, NULL, 'LABEL', NULL, 30.00, 'Pieces', 3.00),
(23, 13, NULL, 'Hotherm B', '', 40.00, 'kg', 40.00),
(24, 13, NULL, 'JERRYCAN', NULL, 40.00, 'Pieces', 15.00),
(25, 13, NULL, 'RLT 20F', NULL, 40.00, 'Kg', 45.00),
(29, 14, NULL, 'Hotherm B', NULL, 10.00, 'litres', 1400.00),
(30, 14, NULL, 'LABEL', NULL, 500.00, 'Pieces', 10.00),
(31, 14, NULL, 'RLT 20F', NULL, 40.00, 'Kg', 200.00),
(38, 15, NULL, 'Hotherm B', NULL, 200.00, 'Kg', 100.00),
(39, 15, NULL, 'Labels', NULL, 15.00, 'kg', 300.00),
(40, 15, NULL, 'RLT 20F', NULL, 20.00, 'Ltrs', 400.00),
(41, 16, NULL, 'Hotherm B', '2023', 300.00, 'Kg', 4.00),
(45, 17, NULL, 'Hotherm B', '2023', 300.00, 'kg', 50.00),
(46, 17, NULL, 'Label', '', 400.00, 'kg', 60.00),
(47, 17, NULL, 'RLT 20F', '4011', 50.00, 'KG', 40.00),
(48, 18, NULL, 'Hotherm B', '2023', 140.00, 'Kg', 50.00),
(49, 19, NULL, 'RLT 20F', '4011', 1000.00, 'kg', 200.00),
(50, 20, NULL, 'SUGAR', 'CH009', 100.00, 'kg', 1000.00),
(51, 22, NULL, 'GRUNDOFFS PUMB', 'ST0009', 100.00, 'pieces', 20000.00),
(52, 23, NULL, 'Oklin Facoal', 'OK001', 200.00, 'kg', 1000.00),
(53, 23, NULL, 'ProClean HT4', 'OK003', 250.00, 'kg', 1000.00),
(54, 24, NULL, 'HYDROXIDE', 'CHM008', 300.00, 'kg', 400.00),
(55, 24, NULL, 'SODA PEARLS', 'CHM008', 250.00, 'kg', 250.00),
(56, 25, NULL, 'SODA PEARLS', 'CHM008', 200.00, 'kg', 300.00),
(57, 26, NULL, 'CALCIUM', 'CHM00002', 500.00, 'kg', 50.00),
(58, 27, NULL, 'CALCIUM', 'CHM00002', 300.00, 'kg', 30.00),
(59, 28, NULL, 'Ag', 'AG009', 1000.00, 'kg', 30.00),
(60, 29, NULL, 'GRUNDOFFS PUMB', 'ST0009', 900.00, 'kg', 100.00),
(61, 29, NULL, 'SALT', '', 20.00, 'Kg', 15.00),
(62, 30, NULL, 'Hotherm B labels', '', 100.00, 'pieces', 10.00),
(63, 31, NULL, 'DRUMS', 'DROD', 500.00, 'pieces', 10.00),
(64, 32, NULL, 'Oil', 'OILOU90', 1000.00, 'litres', 150.00),
(65, 32, NULL, 'Salt', 'SAILOU90', 1000.00, 'Kg', 10.00),
(66, 32, NULL, 'Flour', 'FLOU90', 1000.00, 'Kg', 140.00),
(67, 33, NULL, 'Salt', 'SAILOU90', 500.00, 'Kg', 10.00),
(68, 34, NULL, 'Potash', 'PO-080', 1000.00, 'kg', 50.00),
(69, 34, NULL, 'Avacado Oil', 'AV080', 1200.00, 'kg', 50.00),
(73, 35, NULL, 'POTASIUM HYDROXIDE', 'PT-0970', 1500.00, 'kg', 200.00),
(74, 35, NULL, 'Purified Water', 'PURI-0700', 1200.00, 'kg', 150.00),
(75, 35, NULL, 'PAAS', 'PAAS-09', 1000.00, 'kg', 100.00),
(76, 36, NULL, '20L Jerrycan', 'PCK-09', 100.00, 'Units', 50.00),
(77, 37, NULL, '20L Jerrycan', 'PCK-09', 30.00, 'Units', 50.00),
(78, 38, NULL, 'PAAS', 'PAAS-09', 5000.00, 'kg', 30.00),
(79, 38, NULL, 'POTASIUM HYDROXIDE', 'PT-0970', 4000.00, 'kg', 40.00),
(80, 38, NULL, 'Purified Water', 'PURI-0700', 3500.00, 'litres', 1000.00);

-- --------------------------------------------------------

--
-- Table structure for table `organization_hierarchy`
--

CREATE TABLE `organization_hierarchy` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `reports_to_employee_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `packaging`
--

CREATE TABLE `packaging` (
  `id` int(11) NOT NULL,
  `production_run_id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `packaging_date` date DEFAULT curdate(),
  `pack_size` decimal(10,2) DEFAULT 0.00,
  `packaged_quantity` varchar(100) DEFAULT NULL,
  `unpackaged_qty` decimal(10,2) DEFAULT 0.00,
  `quantity_used` int(11) DEFAULT 0,
  `units` varchar(50) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) GENERATED ALWAYS AS (`quantity_used` * `cost_per_unit`) STORED,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `issued_by` varchar(100) DEFAULT NULL,
  `issued_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `packaging`
--

INSERT INTO `packaging` (`id`, `production_run_id`, `material_id`, `item_name`, `packaging_date`, `pack_size`, `packaged_quantity`, `unpackaged_qty`, `quantity_used`, `units`, `cost_per_unit`, `status`, `remarks`, `created_at`, `issued_by`, `issued_date`) VALUES
(13, 71, 3, 'ENVIRONHIB 100BR', '2025-10-15', 20.00, NULL, 10.00, 2, 'kg', 20.00, 'Approved', '', '2025-10-15 13:11:53', 'HR', '2025-10-15 15:40:00'),
(14, 71, 4, 'ENVIRONHIB 100BR', '2025-10-15', 20.00, NULL, 10.00, 2, 'pcs', 13.00, 'Approved', '', '2025-10-15 13:11:53', 'HR', '2025-10-15 15:40:00'),
(15, 73, 3, 'Disperse 5F-0500', '2025-10-16', 20.00, NULL, 0.00, 5, 'kg', 20.00, 'Approved', '', '2025-10-16 09:38:08', 'HR', '2025-10-16 11:38:00'),
(16, 73, 4, 'Disperse 5F-0500', '2025-10-16', 20.00, NULL, 0.00, 5, 'kg', 13.00, 'Approved', '', '2025-10-16 09:38:08', 'HR', '2025-10-16 11:38:00'),
(17, 75, 3, 'POLYHIB C127', '2025-10-16', 20.00, NULL, 0.00, 5, 'litres', 20.00, 'Approved', '', '2025-10-16 10:19:21', 'HR', '2025-10-16 12:47:00'),
(18, 75, 4, 'POLYHIB C127', '2025-10-16', 20.00, NULL, 0.00, 5, 'pieces', 13.00, 'Approved', '', '2025-10-16 10:19:21', 'HR', '2025-10-16 12:47:00'),
(19, 75, 3, 'POLYHIB C127', '2025-10-16', 20.00, NULL, 0.00, 5, 'litres', 20.00, 'Approved', '', '2025-10-16 10:32:28', 'HR', '2025-10-16 12:47:00'),
(20, 75, 4, 'POLYHIB C127', '2025-10-16', 20.00, NULL, 0.00, 5, 'pieces', 13.00, 'Approved', '', '2025-10-16 10:32:28', 'HR', '2025-10-16 12:47:00'),
(21, 75, 3, 'POLYHIB C127', '2025-10-16', 20.00, NULL, 0.00, 5, '', 20.00, 'Approved', '', '2025-10-16 10:35:21', 'HR', '2025-10-16 12:47:00'),
(22, 77, 3, 'POLYHIB C127', '2025-10-16', 10.00, NULL, 0.00, 5, 'litres', 20.00, 'Approved', '', '2025-10-16 11:14:54', 'HR', '2025-10-16 13:16:00'),
(23, 77, 4, 'POLYHIB C127', '2025-10-16', 10.00, NULL, 0.00, 5, '', 13.00, 'Approved', '', '2025-10-16 11:14:54', 'HR', '2025-10-16 13:16:00'),
(24, 79, 3, 'OXYCLEAN PLUS', '2025-10-16', 25.00, NULL, 0.00, 12, 'litres', 20.00, 'Approved', '', '2025-10-16 12:16:45', 'HR', '2025-10-16 14:17:00'),
(25, 79, 4, 'OXYCLEAN PLUS', '2025-10-16', 25.00, NULL, 0.00, 12, 'pieces', 13.00, 'Approved', '', '2025-10-16 12:16:45', 'HR', '2025-10-16 14:17:00'),
(26, 81, 3, 'sunflowercafe', '2025-10-21', 20.00, NULL, 0.00, 25, '', 20.00, 'Approved', '', '2025-10-21 13:27:25', 'HR', '2025-10-22 08:03:00'),
(27, 109, 3, 'GOLD', '2025-10-21', 20.00, NULL, 10.00, 12, 'kg', 20.00, 'Approved', '', '2025-10-21 13:51:12', 'HR', '2025-10-22 08:07:00'),
(28, 111, 4, 'GOLD', '2025-10-22', 5.00, NULL, 0.00, 10, '', 13.00, 'Approved', '', '2025-10-22 08:35:50', 'HR', '2025-10-22 10:52:00'),
(29, 113, 4, 'GOLD', '2025-10-22', 5.00, NULL, 0.00, 10, 'kg', 13.00, 'Approved', '', '2025-10-22 09:42:21', 'HR', '2025-10-22 11:42:00'),
(30, 115, 7, 'WHEAT', '2025-10-27', 20.00, NULL, 0.00, 35, 'Kg', 10.00, 'Approved', '', '2025-10-27 05:18:07', 'HR', '2025-10-27 06:18:00'),
(31, 117, 7, 'WHEAT', '2025-10-27', 25.00, NULL, 0.00, 12, 'kg', 10.00, 'Approved', '', '2025-10-27 06:39:02', 'HR', '2025-10-27 07:39:00'),
(32, 117, 5, 'WHEAT', '2025-10-27', 25.00, NULL, 0.00, 12, 'pieces', 10.00, 'Approved', '', '2025-10-27 06:39:02', 'HR', '2025-10-27 07:39:00'),
(33, 119, 7, 'WHEAT', '2025-10-27', 25.00, NULL, 0.00, 10, 'kg', 10.00, 'Approved', '', '2025-10-27 06:42:53', 'HR', '2025-10-27 07:43:00'),
(34, 119, 6, 'WHEAT', '2025-10-27', 25.00, NULL, 0.00, 10, 'pieces', 10.00, 'Approved', '', '2025-10-27 06:42:53', 'HR', '2025-10-27 07:43:00'),
(35, 121, 7, 'WHEAT', '2025-10-27', 25.00, NULL, 0.00, 10, 'kg', 10.00, 'Pending', '', '2025-10-27 06:57:59', NULL, '2025-10-27 09:57:59'),
(36, 121, 4, 'WHEAT', '2025-10-27', 25.00, NULL, 0.00, 10, 'pieces', 13.00, 'Pending', '', '2025-10-27 06:57:59', NULL, '2025-10-27 09:57:59'),
(37, 123, 7, 'SOAP', '2025-10-28', 50.00, NULL, 0.00, 7, 'Kg', 10.00, 'Approved', '', '2025-10-28 10:43:30', 'STORES', '2025-10-28 11:43:00'),
(38, 123, 4, 'SOAP', '2025-10-28', 50.00, NULL, 0.00, 7, 'Kg', 13.00, 'Approved', '', '2025-10-28 10:43:30', 'STORES', '2025-10-28 11:43:00'),
(39, 125, 7, 'Y400', '2025-10-28', 20.00, NULL, 0.00, 15, 'Drum', 10.00, 'Approved', '', '2025-10-28 12:03:41', 'MARY', '2025-10-28 13:04:00'),
(40, 125, 4, 'Y400', '2025-10-28', 20.00, NULL, 0.00, 15, 'pieces', 13.00, 'Approved', '', '2025-10-28 12:03:41', 'MARY', '2025-10-28 13:04:00'),
(41, 121, 7, 'WHEAT', '2025-10-29', 10.00, NULL, 0.00, 15, '', 10.00, 'Pending', '', '2025-10-29 07:49:28', NULL, '2025-10-29 10:49:28'),
(42, 121, 7, 'WHEAT', '2025-10-29', 10.00, NULL, 0.00, 15, 'Ltrs', 10.00, 'Pending', '', '2025-10-29 07:50:39', NULL, '2025-10-29 10:50:39'),
(47, 128, 7, 'Y400', '2025-10-29', 20.00, '100', 0.00, 5, 'Kg', 10.00, 'Pending', '', '2025-10-29 08:35:25', NULL, '2025-10-29 11:35:25'),
(48, 128, 7, 'Y400', '2025-10-29', 25.00, '100', 0.00, 4, 'kg', 10.00, 'Pending', '', '2025-10-29 08:35:25', NULL, '2025-10-29 11:35:25'),
(49, 130, 8, 'Y400', '2025-11-04', 20.00, '500', 0.00, 25, 'kg', 100.00, 'Approved', '', '2025-11-04 06:05:23', 'STORES', '2025-11-04 07:05:00'),
(50, 130, 9, 'Y400', '2025-11-04', 10.00, '400', 0.00, 40, 'Kg', 50.00, 'Approved', '', '2025-11-04 06:05:23', 'STORES', '2025-11-04 07:05:00'),
(51, 130, 9, 'Y400', '2025-11-04', 25.00, '100', 0.00, 4, '', 50.00, 'Approved', '', '2025-11-04 06:05:23', 'STORES', '2025-11-04 07:05:00');

-- --------------------------------------------------------

--
-- Table structure for table `packaging_reconciliation`
--

CREATE TABLE `packaging_reconciliation` (
  `id` int(11) NOT NULL,
  `qc_inspection_id` int(11) NOT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `issued` decimal(10,2) DEFAULT NULL,
  `used` decimal(10,2) DEFAULT NULL,
  `wasted` decimal(10,2) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT NULL,
  `quantity_achieved` decimal(10,2) DEFAULT NULL,
  `yield_percent` decimal(5,2) DEFAULT NULL,
  `units` varchar(50) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `packaging_reconciliation`
--

INSERT INTO `packaging_reconciliation` (`id`, `qc_inspection_id`, `item_name`, `issued`, `used`, `wasted`, `balance`, `quantity_achieved`, `yield_percent`, `units`, `cost_per_unit`, `total_cost`) VALUES
(1, 4, 'Label', 10.00, 5.00, 2.00, 3.00, 200.00, 90.00, '20', 5.00, 100.00),
(3, 10, 'Label', 10.00, 5.00, 2.00, 3.00, 200.00, 90.00, '20', 5.00, 100.00),
(4, 13, 'Label', 10.00, 5.00, 2.00, 3.00, 2.00, 90.00, '50', 5.00, 250.00),
(5, 13, 'Jerrycan', 1.00, 1.00, 0.00, 0.00, 1.00, 90.00, '1', 20.00, 20.00),
(6, 15, 'Label', 10.00, 5.00, 2.00, 3.00, 2.00, 90.00, '50', 5.00, 250.00);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_records`
--

CREATE TABLE `payroll_records` (
  `payroll_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `base_salary` decimal(10,2) DEFAULT 0.00,
  `total_allowances` decimal(10,2) DEFAULT 0.00,
  `total_deductions` decimal(10,2) DEFAULT 0.00,
  `net_pay` decimal(10,2) DEFAULT 0.00,
  `month` varchar(20) NOT NULL,
  `year` int(11) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payroll_records`
--

INSERT INTO `payroll_records` (`payroll_id`, `user_id`, `full_name`, `base_salary`, `total_allowances`, `total_deductions`, `net_pay`, `month`, `year`, `details`, `created_at`) VALUES
(1, 10, 'Lynn tech', 45000.00, 23000.00, 21400.00, 46600.00, 'October', 2025, '{\"allowances\":[{\"name\":\"House\",\"amount\":8000},{\"name\":\"Entertainment\",\"amount\":5000},{\"name\":\"Extra hours\",\"amount\":10000}],\"deductions\":[{\"name\":\"HOUSING LEVY\",\"amount\":\"15.00%\"},{\"name\":\"KRA\",\"amount\":\"15.00%\"},{\"name\":\"Missing days\",\"amount\":4000}]}', '2025-10-27 11:37:08'),
(2, 9, 'KYALO DAVID', 30000.00, 8000.00, 12540.00, 25460.00, 'October', 2025, '{\"allowances\":[{\"name\":\"House\",\"amount\":8000}],\"deductions\":[{\"name\":\"HOUSING LEVY\",\"amount\":\"15.00%\"},{\"name\":\"SHA\",\"amount\":\"18.00%\"}]}', '2025-10-27 12:07:05'),
(3, 10, 'Lynn tech', 45000.00, 18000.00, 17400.00, 45600.00, 'November', 2025, '{\"allowances\":[{\"name\":\"House\",\"amount\":8000},{\"name\":\"Entertainment\",\"amount\":5000},{\"name\":\"Exra shift\",\"amount\":5000}],\"deductions\":[{\"name\":\"HOUSING LEVY\",\"amount\":\"15.00%\"},{\"name\":\"KRA\",\"amount\":\"15.00%\"}]}', '2025-10-28 05:54:17'),
(4, 10, 'Lynn tech', 45000.00, 13000.00, 17400.00, 40600.00, 'October', 2026, '{\"allowances\":[{\"name\":\"House\",\"amount\":8000},{\"name\":\"Entertainment\",\"amount\":5000}],\"deductions\":[{\"name\":\"HOUSING LEVY\",\"amount\":\"15.00%\"},{\"name\":\"KRA\",\"amount\":\"15.00%\"}]}', '2026-10-20 07:38:15'),
(5, 11, 'Accounts', 80000.00, 6000.00, 41280.00, 44720.00, 'October', 2026, '{\"allowances\":[{\"name\":\"House\",\"amount\":6000}],\"deductions\":[{\"name\":\"KRA\",\"amount\":\"15.00%\"},{\"name\":\"HOUSING LEVY\",\"amount\":\"15.00%\"},{\"name\":\"SHA\",\"amount\":\"18.00%\"}]}', '2026-10-20 08:14:38'),
(6, 14, 'JOHN DOE', 90000.00, 0.00, 27000.00, 63000.00, 'October', 2026, '{\"allowances\":[],\"deductions\":[{\"name\":\"HOUSING LEVY\",\"amount\":\"15.00%\"},{\"name\":\"KRA\",\"amount\":\"15.00%\"}]}', '2026-10-20 09:32:44');

-- --------------------------------------------------------

--
-- Table structure for table `performance_behaviours`
--

CREATE TABLE `performance_behaviours` (
  `behaviour_id` int(11) NOT NULL,
  `eval_id` int(11) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `performance_evaluations`
--

CREATE TABLE `performance_evaluations` (
  `eval_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `evaluator_name` varchar(255) DEFAULT NULL,
  `position_department` varchar(255) DEFAULT NULL,
  `academic_qualification` varchar(255) DEFAULT NULL,
  `academic_grade` varchar(50) DEFAULT NULL,
  `professional_qualification` varchar(255) DEFAULT NULL,
  `professional_grade` varchar(50) DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `key_activities` text DEFAULT NULL,
  `accomplishments` text DEFAULT NULL,
  `challenges` text DEFAULT NULL,
  `improvement_plan` text DEFAULT NULL,
  `previous_goals` text DEFAULT NULL,
  `future_goals` text DEFAULT NULL,
  `manager_support` text DEFAULT NULL,
  `employee_concerns` text DEFAULT NULL,
  `overall_comments_appraisee` text DEFAULT NULL,
  `overall_comments_appraiser` text DEFAULT NULL,
  `employee_signature_date` date DEFAULT NULL,
  `evaluator_signature_date` date DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `approved_signature` varchar(255) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `authorized_by` varchar(255) DEFAULT NULL,
  `authorized_signature` varchar(255) DEFAULT NULL,
  `authorized_date` date DEFAULT NULL,
  `eval_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `performance_objectives`
--

CREATE TABLE `performance_objectives` (
  `objective_id` int(11) NOT NULL,
  `eval_id` int(11) NOT NULL,
  `department_objective` text DEFAULT NULL,
  `target` text DEFAULT NULL,
  `actual_performance` text DEFAULT NULL,
  `rating_appraisee` int(11) DEFAULT NULL,
  `rating_appraiser` int(11) DEFAULT NULL,
  `rating_consensus` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `po_list`
--

CREATE TABLE `po_list` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `po_no` varchar(50) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` tinyint(4) DEFAULT 0 COMMENT '0=Pending,1=Approved,2=Denied',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `po_list`
--

INSERT INTO `po_list` (`id`, `supplier_id`, `po_no`, `discount_percentage`, `discount_amount`, `tax_percentage`, `tax_amount`, `notes`, `status`, `created_at`) VALUES
(1, 6, '10001', 0.00, 0.00, 16.00, 800.00, '', 0, '2025-10-03 13:44:46'),
(3, 6, '40001', 0.00, 0.00, 16.00, 160.00, '', 1, '2025-10-03 13:47:59'),
(4, 1, '40004', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-03 13:58:22'),
(5, 2, '40005', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-07 07:03:17'),
(6, 1, '40006', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-09 05:13:02'),
(7, 1, '40007', 0.00, 0.00, 16.00, 32064.00, '', 0, '2025-10-09 05:43:29'),
(8, 6, '40008', 0.00, 0.00, 16.00, 4800.00, '', 0, '2025-10-09 05:47:48'),
(9, 6, '40009', 0.00, 0.00, 16.00, 13760.00, '', 0, '2025-10-13 11:46:47'),
(10, 1, '40010', 0.00, 0.00, 16.00, 14160.00, '', 0, '2025-10-14 05:52:54'),
(11, 1, '40011', 0.00, 0.00, 16.00, 6016.00, '', 1, '2025-10-14 06:16:34'),
(12, 6, '40012', 0.00, 0.00, 16.00, 7358.40, '', 1, '2025-10-14 06:33:19'),
(13, 9, '40013', 0.00, 0.00, 16.00, 640.00, '', 1, '2025-10-14 06:42:26'),
(14, 9, '40014', 0.00, 0.00, 16.00, 4320.00, '', 1, '2025-10-14 06:55:41'),
(15, 9, '40015', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-14 07:31:49'),
(16, 9, '20001', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-14 07:46:13'),
(17, 9, '40016', 0.00, 0.00, 16.00, 6560.00, '', 1, '2025-10-14 07:56:48'),
(18, 9, '40017', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-14 08:38:00'),
(19, 9, '40018', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-14 12:04:37'),
(20, 9, '40019', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-16 07:47:47'),
(22, 9, '40020', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-16 08:02:49'),
(23, 9, '40021', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-16 10:05:16'),
(24, 9, '40022', 0.00, 0.00, 16.00, 29200.00, '', 1, '2025-10-16 12:00:19'),
(25, 9, '40023', 0.00, 0.00, 16.00, 9600.00, '', 1, '2025-10-16 12:33:04'),
(26, 9, '40024', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-16 12:39:37'),
(27, 9, '40025', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-16 12:40:11'),
(28, 9, '40026', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-21 13:45:43'),
(29, 9, '40027', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-23 05:34:43'),
(30, 9, '40028', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-24 08:56:10'),
(31, 9, '40029', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-24 09:20:22'),
(32, 9, '40030', 0.00, 0.00, 16.00, 48000.00, '', 1, '2025-10-27 05:03:34'),
(33, 9, '40031', 0.00, 0.00, 16.00, 800.00, '', 1, '2025-10-27 07:28:28'),
(34, 9, '40032', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-28 10:33:49'),
(35, 9, '40033', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-28 11:41:09'),
(36, 9, '40034', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-31 12:34:01'),
(37, 9, '40035', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-10-31 13:10:17'),
(38, 15, '40036', 0.00, 0.00, 0.00, 0.00, '', 1, '2025-11-03 13:23:51');

-- --------------------------------------------------------

--
-- Table structure for table `procurement_products`
--

CREATE TABLE `procurement_products` (
  `id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `requested_by` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `procurement_products`
--

INSERT INTO `procurement_products` (`id`, `product_name`, `department`, `requested_by`, `description`, `created_at`) VALUES
(1, 'OCTANE', 'Stores', 'MUTANIA KK', 'OCTANE', '2025-10-03 09:46:05');

-- --------------------------------------------------------

--
-- Table structure for table `production_procedures`
--

CREATE TABLE `production_procedures` (
  `id` int(11) NOT NULL,
  `production_run_id` int(11) NOT NULL,
  `procedure_name` varchar(255) DEFAULT NULL,
  `done_by` varchar(100) DEFAULT NULL,
  `checked_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `production_procedures`
--

INSERT INTO `production_procedures` (`id`, `production_run_id`, `procedure_name`, `done_by`, `checked_by`, `created_at`) VALUES
(17, 53, 'Dosing', 'Production', 'QC', '2025-10-08 06:27:09'),
(18, 53, 'Filing', 'Production', 'QC', '2025-10-08 06:27:09'),
(19, 56, 'Dosing', 'Production', 'QC', '2025-10-08 06:42:06'),
(20, 56, 'Filing', 'Production', 'QC', '2025-10-08 06:42:06'),
(21, 60, 'dispensing', 'augustine', 'victor', '2025-10-09 11:48:43'),
(22, 60, 'manufacturing', 'augustine', 'victor', '2025-10-09 11:48:43'),
(23, 62, 'Dosing', 'Production', 'Augustine', '2025-10-13 11:27:19'),
(24, 64, 'Dosing', 'Production', 'QC', '2025-10-15 07:43:08'),
(25, 64, 'Filing', 'Production', 'QC', '2025-10-15 07:43:08'),
(26, 65, 'Dosing', 'Production', 'QC', '2025-10-15 07:49:57'),
(27, 65, 'Filing', 'Production', 'QC', '2025-10-15 07:49:57'),
(28, 71, 'Dosing', 'Production', 'QC', '2025-10-15 12:03:48'),
(29, 71, 'Filing', 'Production', 'QC', '2025-10-15 12:03:48'),
(30, 73, 'Dosing', 'Production', 'QC', '2025-10-16 09:35:06'),
(31, 75, 'Dosing', 'Production', 'QC', '2025-10-16 10:17:44'),
(32, 77, 'Dosing', 'Production', 'QC', '2025-10-16 11:12:47'),
(33, 79, 'Dosing', 'Production', 'QC', '2025-10-16 12:14:46'),
(34, 81, 'Dosing', 'Production', 'QC', '2025-10-21 08:34:59'),
(35, 109, 'Dosing', 'Production', 'QC', '2025-10-21 13:50:11'),
(36, 111, 'Dosing', 'Production', 'QC', '2025-10-22 08:30:15'),
(37, 113, 'Dosing', 'Production', 'QC', '2025-10-22 09:41:40'),
(38, 115, 'Dosing', 'Production', 'Augustine', '2025-10-27 05:12:25'),
(39, 115, 'Filing', 'Production', 'QC', '2025-10-27 05:12:25'),
(40, 115, 'Mixing', 'Production', 'Augustine', '2025-10-27 05:12:25'),
(41, 117, 'Dosing', 'Production', 'Augustine', '2025-10-27 06:37:56'),
(42, 119, 'Dosing', 'Production', 'Augustine', '2025-10-27 06:41:26'),
(43, 121, 'Dosing', 'Production', 'Augustine', '2025-10-27 06:57:03'),
(44, 123, 'Dosing', 'Production', 'Augustine', '2025-10-28 10:42:22'),
(45, 125, 'Mixing', 'Production team', 'Augustine', '2025-10-28 11:59:35'),
(46, 125, 'Filing', 'Production', 'QC', '2025-10-28 11:59:35'),
(47, 128, 'Dosing', 'Production', 'QC', '2025-10-29 07:54:06'),
(48, 130, 'Dosing', 'Production', 'QC', '2025-11-04 06:02:28'),
(49, 130, 'Filing', 'Production', 'QC', '2025-11-04 06:02:28');

-- --------------------------------------------------------

--
-- Table structure for table `production_runs`
--

CREATE TABLE `production_runs` (
  `id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_code` varchar(50) DEFAULT '209024',
  `requested_by` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `expected_yield` varchar(50) DEFAULT NULL,
  `obtained_yield` varchar(50) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `status` enum('In production','Completed') DEFAULT 'In production',
  `qc_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `start_date` datetime DEFAULT current_timestamp(),
  `completed_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `production_runs`
--

INSERT INTO `production_runs` (`id`, `request_id`, `product_name`, `product_code`, `requested_by`, `description`, `expected_yield`, `obtained_yield`, `completed_at`, `status`, `qc_status`, `start_date`, `completed_date`, `created_at`, `updated_at`) VALUES
(53, 2, 'LYNNCARE', '209024', 'Stores', 'Issued', '1100kg', '919kg', NULL, 'Completed', 'Pending', '2025-10-08 09:27:09', NULL, '2025-10-08 06:27:09', '2025-10-08 06:27:09'),
(56, 3, 'LYNNCARE', '209024', 'MUTANIA KK', 'DETERGENTS', '1100kg', '919kg', '2025-10-07 09:41:00', 'Completed', 'Pending', '2025-10-08 09:41:38', NULL, '2025-10-08 06:41:38', '2025-10-08 06:42:06'),
(60, 6, 'MULTITREAT 1F', '209024', 'Augustine', 'stocking', '200kgs', '200kgs', '2025-10-09 14:46:00', 'Completed', 'Pending', '2025-10-09 14:46:33', NULL, '2025-10-09 11:46:33', '2025-10-09 11:48:43'),
(62, 7, 'XYZ', '209024', 'Augustine', 'Stocking', '105kg', '105kg', '2025-10-13 14:26:00', 'Completed', 'Pending', '2025-10-13 14:26:12', NULL, '2025-10-13 11:26:12', '2025-10-13 11:27:19'),
(64, 26, 'ENVIRONHIB 100BR', '209024', 'JOE', 'sales', '500', '500', '2025-10-15 10:42:00', 'Completed', 'Pending', '2025-10-15 10:11:17', NULL, '2025-10-15 07:11:17', '2025-10-15 07:43:08'),
(65, 28, 'ENVIRONHIB 100BR', '209024', 'Production', 'for sales', '50', '50', '2025-10-15 10:49:00', 'Completed', 'Pending', '2025-10-15 10:11:25', NULL, '2025-10-15 07:11:25', '2025-10-15 07:49:57'),
(71, 30, 'ENVIRONHIB 100BR', '209024', 'Selinah', 'stock', '50', '50', '2025-10-15 15:03:00', 'Completed', 'Pending', '2025-10-15 15:02:41', NULL, '2025-10-15 12:02:41', '2025-10-15 12:03:48'),
(73, 31, 'Disperse 5F-0500', '209024', 'JOE day', 'sales', '100', '100', '2025-10-16 12:34:00', 'Completed', 'Pending', '2025-10-16 12:34:50', NULL, '2025-10-16 09:34:50', '2025-10-16 09:35:06'),
(75, 32, 'POLYHIB C127', '209024', 'MUTANIA KK', 'saales', '100', '100', '2025-10-16 13:17:00', 'Completed', 'Pending', '2025-10-16 13:17:28', NULL, '2025-10-16 10:17:28', '2025-10-16 10:17:44'),
(77, 33, 'POLYHIB C127', '209024', 'ACCTS', 'SALES', '50', '50', '2025-10-16 14:12:00', 'Completed', 'Pending', '2025-10-16 14:12:30', NULL, '2025-10-16 11:12:30', '2025-10-16 11:12:47'),
(79, 34, 'OXYCLEAN PLUS', '209024', 'Augustine', 'Stocking', '300', '300', '2025-10-16 15:14:00', 'Completed', 'Pending', '2025-10-16 15:14:03', NULL, '2025-10-16 12:14:03', '2025-10-16 12:14:46'),
(81, 36, 'sunflowercafe', '209024', 'DOM', 'Stock', '500', '', '2025-10-21 11:34:00', 'Completed', 'Pending', '2025-10-21 10:29:35', NULL, '2025-10-21 07:29:35', '2025-10-21 08:34:59'),
(109, 37, 'GOLD', '209024', 'DOM', 'STOCK', '250', '250', '2025-10-21 16:50:00', 'Completed', 'Pending', '2025-10-21 16:49:56', NULL, '2025-10-21 13:49:56', '2025-10-21 13:50:11'),
(111, 38, 'GOLD', '209024', 'DOMt', 'sales', '50', '50', '2025-10-22 11:30:00', 'Completed', 'Pending', '2025-10-22 11:30:01', NULL, '2025-10-22 08:30:01', '2025-10-22 08:30:15'),
(113, 39, 'GOLD', '209024', 'DOMt', 'sales', '50', '50', '2025-10-22 12:41:00', 'Completed', 'Pending', '2025-10-22 12:41:23', NULL, '2025-10-22 09:41:23', '2025-10-22 09:41:40'),
(115, 40, 'WHEAT', '209024', 'DAVID', 'For stock', '700', '700', '2025-10-27 08:11:00', 'Completed', 'Pending', '2025-10-27 08:11:31', NULL, '2025-10-27 05:11:31', '2025-10-27 05:12:25'),
(117, 41, 'WHEAT', '209024', 'DAVID', 'KWAL', '300', '300', '2025-10-27 09:37:00', 'Completed', 'Pending', '2025-10-27 09:37:42', NULL, '2025-10-27 06:37:42', '2025-10-27 06:37:56'),
(119, 42, 'WHEAT', '209024', 'DAVID', 'SALES', '250', '250', '2025-10-27 09:41:00', 'Completed', 'Pending', '2025-10-27 09:41:12', NULL, '2025-10-27 06:41:12', '2025-10-27 06:41:26'),
(121, 43, 'WHEAT', '209024', 'DAVID', 'SS', '250', '250', '2025-10-27 09:56:00', 'Completed', 'Pending', '2025-10-27 09:56:53', NULL, '2025-10-27 06:56:53', '2025-10-27 06:57:03'),
(123, 44, 'SOAP', '209024', 'DOM', 'FOR STOCK', '350', '350', '2025-10-28 13:42:00', 'Completed', 'Pending', '2025-10-28 13:41:49', NULL, '2025-10-28 10:41:49', '2025-10-28 10:42:22'),
(125, 45, 'Y400', '209024', 'Augustine', 'For stocking', '300', '300', '2025-10-28 14:58:00', 'Completed', 'Pending', '2025-10-28 14:58:19', NULL, '2025-10-28 11:58:19', '2025-10-28 11:59:35'),
(128, 46, 'Y400', '209024', 'MUTANIA KK', 'ales', '200', '200', '2025-10-29 10:53:00', 'Completed', 'Pending', '2025-10-29 10:53:45', NULL, '2025-10-29 07:53:45', '2025-10-29 07:54:06'),
(130, 67, 'Y400', '209024', 'MUTANIA KK', 'SA', '1000', '1000', '2025-11-04 09:02:00', 'Completed', 'Pending', '2025-11-04 09:02:04', NULL, '2025-11-04 06:02:04', '2025-11-04 06:02:28');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `category` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `original_quantity` decimal(10,2) DEFAULT 0.00,
  `remaining_quantity` decimal(10,2) DEFAULT 0.00,
  `obtained_quantity` decimal(10,2) DEFAULT 0.00,
  `pack_size` varchar(50) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `product_code`, `category`, `description`, `original_quantity`, `remaining_quantity`, `obtained_quantity`, `pack_size`, `unit`, `created_at`) VALUES
(10, 'POLYHIB C127', NULL, 'Open cooling', 'POLYHIB C127', 0.00, 15.00, 15.00, '10', 'litres', '2025-10-16 10:06:50'),
(11, 'OXYCLEAN PLUS', NULL, 'CIP', 'Chemical', 0.00, 24.00, 24.00, '25', 'litres', '2025-10-16 12:09:59'),
(12, 'sunflowercafe', NULL, 'DETERGENT', 'detergent', 0.00, 25.00, 25.00, NULL, NULL, '2025-10-16 12:42:11'),
(13, 'GOLD', 'GD09KSK', 'Solid', 'gold', 0.00, 32.00, 32.00, NULL, NULL, '2025-10-21 13:39:44'),
(14, 'WHEAT', 'WT001', 'Flour', 'Flour', 0.00, 79.00, 79.00, NULL, NULL, '2025-10-27 04:57:42'),
(15, 'SOAP', 'SP00090', 'DETERGENT', 'Solid', 0.00, 0.00, 0.00, NULL, NULL, '2025-10-28 10:29:39'),
(16, 'Y400', 'Y-004', 'DETERGENT', 'Detergent', 0.00, 0.00, 0.00, NULL, NULL, '2025-10-28 11:52:12');

-- --------------------------------------------------------

--
-- Table structure for table `qc_inspections`
--

CREATE TABLE `qc_inspections` (
  `id` int(11) NOT NULL,
  `production_run_id` int(11) NOT NULL,
  `test_name` varchar(100) DEFAULT NULL,
  `specification` text DEFAULT NULL,
  `procedure_done` text DEFAULT NULL,
  `qc_status` enum('Approved Product','Not Approved') DEFAULT 'Not Approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `qc_inspections`
--

INSERT INTO `qc_inspections` (`id`, `production_run_id`, `test_name`, `specification`, `procedure_done`, `qc_status`, `created_at`) VALUES
(4, 56, NULL, NULL, NULL, 'Approved Product', '2025-10-08 09:12:27'),
(5, 56, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', 'Approved Product', '2025-10-08 09:12:27'),
(6, 56, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', 'Approved Product', '2025-10-08 09:12:27'),
(10, 53, NULL, NULL, NULL, 'Approved Product', '2025-10-08 09:44:21'),
(11, 53, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', 'Approved Product', '2025-10-08 09:44:21'),
(12, 53, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', 'Approved Product', '2025-10-08 09:44:21'),
(13, 60, NULL, NULL, NULL, 'Approved Product', '2025-10-13 08:47:05'),
(14, 60, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', 'Approved Product', '2025-10-13 08:47:05'),
(15, 62, NULL, NULL, NULL, 'Approved Product', '2025-10-13 11:29:03'),
(16, 62, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', 'Approved Product', '2025-10-13 11:29:03'),
(17, 64, NULL, NULL, NULL, 'Approved Product', '2025-10-15 08:16:57'),
(22, 65, NULL, NULL, NULL, 'Approved Product', '2025-10-15 08:40:50'),
(23, 71, NULL, NULL, NULL, 'Approved Product', '2025-10-15 12:04:24'),
(24, 73, NULL, NULL, NULL, 'Approved Product', '2025-10-16 09:35:47'),
(25, 75, NULL, NULL, NULL, 'Approved Product', '2025-10-16 10:18:18'),
(26, 77, NULL, NULL, NULL, 'Approved Product', '2025-10-16 11:14:03'),
(27, 79, NULL, NULL, NULL, 'Approved Product', '2025-10-16 12:15:28'),
(28, 81, NULL, NULL, NULL, 'Approved Product', '2025-10-21 09:56:19'),
(29, 109, NULL, NULL, NULL, 'Approved Product', '2025-10-21 13:50:32'),
(30, 111, NULL, NULL, NULL, 'Approved Product', '2025-10-22 08:30:42'),
(31, 113, NULL, NULL, NULL, 'Approved Product', '2025-10-22 09:41:53'),
(32, 115, NULL, NULL, NULL, 'Approved Product', '2025-10-27 05:17:14'),
(33, 117, NULL, NULL, NULL, 'Approved Product', '2025-10-27 06:38:16'),
(34, 119, NULL, NULL, NULL, 'Approved Product', '2025-10-27 06:42:04'),
(35, 121, NULL, NULL, NULL, 'Approved Product', '2025-10-27 06:57:29'),
(36, 123, NULL, NULL, NULL, 'Approved Product', '2025-10-28 10:42:49'),
(37, 125, NULL, NULL, NULL, 'Approved Product', '2025-10-28 12:00:28'),
(38, 128, NULL, NULL, NULL, 'Approved Product', '2025-10-29 07:54:31'),
(39, 130, NULL, NULL, NULL, 'Approved Product', '2025-11-04 06:02:59');

-- --------------------------------------------------------

--
-- Table structure for table `qc_tests`
--

CREATE TABLE `qc_tests` (
  `id` int(11) NOT NULL,
  `qc_inspection_id` int(11) NOT NULL,
  `test_name` varchar(255) DEFAULT NULL,
  `specification` varchar(255) DEFAULT NULL,
  `procedure_done` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `qc_tests`
--

INSERT INTO `qc_tests` (`id`, `qc_inspection_id`, `test_name`, `specification`, `procedure_done`, `created_at`) VALUES
(7, 25, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-16 10:18:18'),
(8, 26, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-16 11:14:03'),
(9, 27, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-16 12:15:28'),
(10, 28, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-21 09:56:19'),
(11, 29, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-21 13:50:32'),
(12, 30, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-22 08:30:42'),
(13, 32, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-27 05:17:14'),
(14, 32, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-27 05:17:14'),
(15, 33, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-27 06:38:16'),
(16, 34, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-27 06:42:04'),
(17, 35, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-27 06:57:29'),
(18, 36, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-28 10:42:49'),
(19, 37, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-28 12:00:28'),
(20, 38, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-10-29 07:54:31'),
(21, 39, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-11-04 06:02:59'),
(22, 39, 'Appearance', 'Viscous clear liquid', 'Viscous clear liquid', '2025-11-04 06:02:59');

-- --------------------------------------------------------

--
-- Table structure for table `quality_manager_review`
--

CREATE TABLE `quality_manager_review` (
  `id` int(11) NOT NULL,
  `production_run_id` int(11) NOT NULL,
  `checklist_no` int(11) DEFAULT NULL,
  `checklist_item` text DEFAULT NULL,
  `response` enum('Yes','No') DEFAULT 'No',
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `quality_manager_review`
--

INSERT INTO `quality_manager_review` (`id`, `production_run_id`, `checklist_no`, `checklist_item`, `response`, `status`, `created_at`) VALUES
(1, 81, 0, 'All production processes have been fully followed and complied', 'Yes', 'Approved', '2025-10-22 06:26:39'),
(2, 81, 1, 'Quality Control Processes have been fully followed', 'Yes', 'Approved', '2025-10-22 06:26:39'),
(3, 81, 2, 'All QC reports are duly filled, recorded, and signed', 'Yes', 'Approved', '2025-10-22 06:26:39'),
(4, 81, 3, 'Final product complies with standard specifications', 'Yes', 'Approved', '2025-10-22 06:26:39'),
(5, 81, 4, 'Final product complies with packaging specifications', 'Yes', 'Approved', '2025-10-22 06:26:39'),
(6, 81, 5, 'Retain sample collected and stored', 'Yes', 'Approved', '2025-10-22 06:26:39'),
(7, 81, 6, 'All blank spaces have been fully filled', 'Yes', 'Approved', '2025-10-22 06:26:39'),
(8, 81, 7, 'Certificate of Analysis complies with test results', 'Yes', 'Approved', '2025-10-22 06:26:39'),
(9, 81, 8, 'Product released for sale', 'Yes', 'Approved', '2025-10-22 06:26:39'),
(19, 109, 0, 'All production processes have been fully followed and complied', 'Yes', 'Approved', '2025-10-22 07:09:58'),
(20, 109, 1, 'Quality Control Processes have been fully followed', 'Yes', 'Approved', '2025-10-22 07:09:58'),
(21, 109, 2, 'All QC reports are duly filled, recorded, and signed', 'No', 'Pending', '2025-10-22 07:09:58'),
(22, 109, 3, 'Final product complies with standard specifications', 'Yes', 'Approved', '2025-10-22 07:09:58'),
(23, 109, 4, 'Final product complies with packaging specifications', 'No', 'Pending', '2025-10-22 07:09:58'),
(24, 109, 5, 'Retain sample collected and stored', 'Yes', 'Approved', '2025-10-22 07:09:58'),
(25, 109, 6, 'All blank spaces have been fully filled', 'Yes', 'Approved', '2025-10-22 07:09:58'),
(26, 109, 7, 'Certificate of Analysis complies with test results', 'No', 'Pending', '2025-10-22 07:09:58'),
(27, 109, 8, 'Product released for sale', 'No', 'Pending', '2025-10-22 07:09:58'),
(28, 111, 0, 'All production processes have been fully followed and complied', 'Yes', 'Approved', '2025-10-24 07:46:07'),
(29, 111, 1, 'Quality Control Processes have been fully followed', 'Yes', 'Approved', '2025-10-24 07:46:07'),
(30, 111, 2, 'All QC reports are duly filled, recorded, and signed', 'Yes', 'Approved', '2025-10-24 07:46:07'),
(31, 111, 3, 'Final product complies with standard specifications', 'Yes', 'Approved', '2025-10-24 07:46:07'),
(32, 111, 4, 'Final product complies with packaging specifications', 'Yes', 'Approved', '2025-10-24 07:46:07'),
(33, 111, 5, 'Retain sample collected and stored', 'Yes', 'Approved', '2025-10-24 07:46:07'),
(34, 111, 6, 'All blank spaces have been fully filled', 'Yes', 'Approved', '2025-10-24 07:46:07'),
(35, 111, 7, 'Certificate of Analysis complies with test results', 'Yes', 'Approved', '2025-10-24 07:46:07'),
(36, 111, 8, 'Product released for sale', 'Yes', 'Approved', '2025-10-24 07:46:07'),
(37, 115, 0, 'All production processes have been fully followed and complied', 'Yes', 'Approved', '2025-10-27 05:19:20'),
(38, 115, 1, 'Quality Control Processes have been fully followed', 'Yes', 'Approved', '2025-10-27 05:19:20'),
(39, 115, 2, 'All QC reports are duly filled, recorded, and signed', 'Yes', 'Approved', '2025-10-27 05:19:20'),
(40, 115, 3, 'Final product complies with standard specifications', 'Yes', 'Approved', '2025-10-27 05:19:20'),
(41, 115, 4, 'Final product complies with packaging specifications', 'Yes', 'Approved', '2025-10-27 05:19:20'),
(42, 115, 5, 'Retain sample collected and stored', 'Yes', 'Approved', '2025-10-27 05:19:20'),
(43, 115, 6, 'All blank spaces have been fully filled', 'Yes', 'Approved', '2025-10-27 05:19:20'),
(44, 115, 7, 'Certificate of Analysis complies with test results', 'Yes', 'Approved', '2025-10-27 05:19:20'),
(45, 115, 8, 'Product released for sale', 'Yes', 'Approved', '2025-10-27 05:19:20'),
(46, 123, 0, 'All production processes have been fully followed and complied', 'Yes', 'Approved', '2025-10-28 10:44:32'),
(47, 123, 1, 'Quality Control Processes have been fully followed', 'Yes', 'Approved', '2025-10-28 10:44:32'),
(48, 123, 2, 'All QC reports are duly filled, recorded, and signed', 'Yes', 'Approved', '2025-10-28 10:44:32'),
(49, 123, 3, 'Final product complies with standard specifications', 'Yes', 'Approved', '2025-10-28 10:44:32'),
(50, 123, 4, 'Final product complies with packaging specifications', 'Yes', 'Approved', '2025-10-28 10:44:32'),
(51, 123, 5, 'Retain sample collected and stored', 'Yes', 'Approved', '2025-10-28 10:44:32'),
(52, 123, 6, 'All blank spaces have been fully filled', 'Yes', 'Approved', '2025-10-28 10:44:32'),
(53, 123, 7, 'Certificate of Analysis complies with test results', 'Yes', 'Approved', '2025-10-28 10:44:32'),
(54, 123, 8, 'Product released for sale', 'Yes', 'Approved', '2025-10-28 10:44:32'),
(55, 125, 0, 'All production processes have been fully followed and complied', 'Yes', 'Approved', '2025-10-28 12:05:04'),
(56, 125, 1, 'Quality Control Processes have been fully followed', 'Yes', 'Approved', '2025-10-28 12:05:04'),
(57, 125, 2, 'All QC reports are duly filled, recorded, and signed', 'Yes', 'Approved', '2025-10-28 12:05:04'),
(58, 125, 3, 'Final product complies with standard specifications', 'Yes', 'Approved', '2025-10-28 12:05:04'),
(59, 125, 4, 'Final product complies with packaging specifications', 'Yes', 'Approved', '2025-10-28 12:05:04'),
(60, 125, 5, 'Retain sample collected and stored', 'Yes', 'Approved', '2025-10-28 12:05:04'),
(61, 125, 6, 'All blank spaces have been fully filled', 'Yes', 'Approved', '2025-10-28 12:05:04'),
(62, 125, 7, 'Certificate of Analysis complies with test results', 'Yes', 'Approved', '2025-10-28 12:05:04'),
(63, 125, 8, 'Product released for sale', 'Yes', 'Approved', '2025-10-28 12:05:04');

-- --------------------------------------------------------

--
-- Table structure for table `rejected_chemicals_in`
--

CREATE TABLE `rejected_chemicals_in` (
  `id` int(11) NOT NULL,
  `chemical_id` int(11) DEFAULT NULL,
  `chemical_name` varchar(150) NOT NULL,
  `batch_no` varchar(100) DEFAULT NULL,
  `rm_lot_no` varchar(100) NOT NULL,
  `std_quantity` decimal(10,2) NOT NULL,
  `original_quantity` decimal(10,2) NOT NULL,
  `remaining_quantity` decimal(10,2) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `date_added` date NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `chemical_code` varchar(50) DEFAULT NULL,
  `po_number` varchar(50) DEFAULT NULL,
  `action_type` enum('New','Restock') DEFAULT 'New'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `rejected_chemicals_in`
--

INSERT INTO `rejected_chemicals_in` (`id`, `chemical_id`, `chemical_name`, `batch_no`, `rm_lot_no`, `std_quantity`, `original_quantity`, `remaining_quantity`, `total_cost`, `unit_price`, `date_added`, `status`, `created_at`, `updated_at`, `chemical_code`, `po_number`, `action_type`) VALUES
(32, NULL, 'Purified Water', '2025-12', '11-3-25-A', 3500.00, 0.00, 3500.00, 3500000.00, 1000.00, '2025-11-03', '', '2025-11-03 13:26:39', '2025-11-03 13:28:17', 'PURI-0700', '40036', 'New');

-- --------------------------------------------------------

--
-- Table structure for table `shortlisted_applicants`
--

CREATE TABLE `shortlisted_applicants` (
  `applicant_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `position_applied` varchar(150) NOT NULL,
  `interview_date` date DEFAULT NULL,
  `interview_time` time DEFAULT NULL,
  `interview_venue` varchar(200) DEFAULT NULL,
  `interviewer` varchar(150) DEFAULT NULL,
  `interview_status` enum('Pending','Attended','Absent','Passed','Failed') NOT NULL DEFAULT 'Pending',
  `qualifications` text DEFAULT NULL,
  `technical_skills` text DEFAULT NULL,
  `communication_skills` text DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `weaknesses` text DEFAULT NULL,
  `interview_notes` text DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `next_steps` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `stock_in`
--

CREATE TABLE `stock_in` (
  `id` int(11) NOT NULL,
  `stock_name` varchar(255) DEFAULT NULL,
  `stock_code` varchar(100) DEFAULT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `batch_no` varchar(100) DEFAULT NULL,
  `quantity` varchar(100) DEFAULT NULL,
  `original_quantity` int(11) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `stock_in`
--

INSERT INTO `stock_in` (`id`, `stock_name`, `stock_code`, `po_number`, `batch_no`, `quantity`, `original_quantity`, `unit`, `total_cost`, `unit_cost`, `created_at`) VALUES
(1, 'CHLORINE', '1001', NULL, NULL, '1', NULL, '1Kg', 1000.00, 100.00, '2025-09-29 13:34:55'),
(4, 'UNGAROL', '20009', NULL, NULL, '350', 500, 'Kg', 100000.00, 200.00, '2025-10-11 07:04:59'),
(5, 'GRUNDOFFS PUMB', 'ST0009', '40020', 'BTS001', '0', 100, 'pieces', 2000000.00, 20000.00, '2025-10-15 21:00:00'),
(6, 'GRUNDOFFS PUMB', 'ST0009', '40027', '25-00134', '637', 900, 'kg', 90000.00, 100.00, '2025-10-22 21:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `stock_names`
--

CREATE TABLE `stock_names` (
  `id` int(11) NOT NULL,
  `stock_name` varchar(100) NOT NULL,
  `stock_code` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `stock_out_history`
--

CREATE TABLE `stock_out_history` (
  `id` int(11) NOT NULL,
  `stock_code` varchar(50) DEFAULT NULL,
  `stock_name` varchar(100) DEFAULT NULL,
  `quantity_removed` decimal(10,2) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `remaining_quantity` decimal(10,2) DEFAULT NULL,
  `stock_date` date DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `requested_by` varchar(255) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `removed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `stock_out_history`
--

INSERT INTO `stock_out_history` (`id`, `stock_code`, `stock_name`, `quantity_removed`, `unit_cost`, `remaining_quantity`, `stock_date`, `reason`, `requested_by`, `approved_by`, `removed_at`) VALUES
(1, '1001', 'CHLORINE', 5.00, 100.00, 5.00, '2025-09-30', NULL, NULL, NULL, '2025-09-30 05:33:05'),
(2, '1001', 'CHLORINE', 2.00, 100.00, 3.00, '2025-09-30', NULL, NULL, NULL, '2025-09-30 05:53:15'),
(3, '1001', 'CHLORINE', 2.00, 100.00, 1.00, '2025-09-30', NULL, NULL, NULL, '2025-09-30 05:54:24'),
(4, '20009', 'UNGAROL', 100.00, 200.00, 400.00, '2025-10-11', NULL, NULL, NULL, '2025-10-11 07:06:36'),
(5, '20009', 'UNGAROL', 50.00, 200.00, 350.00, '2025-10-11', 'Requested by KWAL', 'MUTANIA KK', 'Stores', '2025-10-11 07:20:12');

-- --------------------------------------------------------

--
-- Table structure for table `store_b_chemicals_in`
--

CREATE TABLE `store_b_chemicals_in` (
  `id` int(11) NOT NULL,
  `chemical_id` int(11) NOT NULL,
  `chemical_name` varchar(150) DEFAULT NULL,
  `main_category` varchar(100) DEFAULT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `group_code` varchar(100) DEFAULT NULL,
  `chemical_code` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `delivery_number` varchar(100) DEFAULT NULL,
  `quantity_received` varchar(50) DEFAULT NULL,
  `remaining_quantity` varchar(50) DEFAULT NULL,
  `units` varchar(50) DEFAULT NULL,
  `pack_size` varchar(50) DEFAULT NULL,
  `unit_cost` varchar(50) DEFAULT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `receiving_date` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `store_b_chemicals_in`
--

INSERT INTO `store_b_chemicals_in` (`id`, `chemical_id`, `chemical_name`, `main_category`, `group_name`, `group_code`, `chemical_code`, `category`, `delivery_number`, `quantity_received`, `remaining_quantity`, `units`, `pack_size`, `unit_cost`, `po_number`, `received_by`, `receiving_date`, `created_at`) VALUES
(1, 17, 'PAAS', 'Chemicals', 'ACID', 'GRU80', 'PAAS-09', 'Acid', 'ISJ187vs', '1000', '500', 'kg', '50', '100', '40030', 'Store b', '2025-10-31', '2025-10-31 05:42:22'),
(2, 17, 'PAAS', 'Chemicals', 'ACID', 'GRU80', 'PAAS-09', 'Acid', 'ISJ187vs', '1000', '1000', 'kg', '50kg', '100', '40030', 'Store b', '2025-10-31', '2025-10-31 05:52:04'),
(3, 15, 'Avacado Oil', 'Chemicals', 'additive', 'SP191', 'AV080', 'Liquid', 'ISJ187vs', '500', '500', 'kg', '50kg', '100', '40030', 'Store b USER', '2025-10-31', '2025-10-31 09:00:41'),
(4, 19, 'Purified Water', 'Chemicals', 'Acids', 'GP-1001', 'PURI-0700', 'Acid', 'ISJ187vs', '1000', '1000', 'kg', '50kg', '100', '40033', 'Store b', '2025-11-05', '2025-11-05 06:59:24');

-- --------------------------------------------------------

--
-- Table structure for table `store_b_engineering_products_in`
--

CREATE TABLE `store_b_engineering_products_in` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) DEFAULT NULL,
  `main_category` varchar(100) DEFAULT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `group_code` varchar(100) DEFAULT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `delivery_number` varchar(100) DEFAULT NULL,
  `quantity_received` decimal(10,2) DEFAULT 0.00,
  `remaining_quantity` decimal(10,2) DEFAULT 0.00,
  `units` varchar(50) DEFAULT NULL,
  `pack_size` varchar(50) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `po_number` varchar(100) DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `receiving_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `store_b_engineering_products_in`
--

INSERT INTO `store_b_engineering_products_in` (`id`, `product_id`, `product_name`, `main_category`, `group_name`, `group_code`, `product_code`, `category`, `delivery_number`, `quantity_received`, `remaining_quantity`, `units`, `pack_size`, `unit_cost`, `po_number`, `received_by`, `receiving_date`, `created_at`) VALUES
(1, 4, 'GRUNDOFFS PUMB', 'Engineering Products', 'Engineering product', '2001', 'ST0009', 'Pumb', 'ISJ187vsg', 10.00, 10.00, 'pumbs', '1', 1000.00, '40031', 'Store b engineer', '2025-10-31', '2025-10-31 06:43:17');

-- --------------------------------------------------------

--
-- Table structure for table `store_b_finished_products_in`
--

CREATE TABLE `store_b_finished_products_in` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `delivery_number` varchar(100) DEFAULT NULL,
  `quantity_received` decimal(10,2) DEFAULT 0.00,
  `remaining_quantity` decimal(10,2) DEFAULT 0.00,
  `units` varchar(50) DEFAULT NULL,
  `pack_size` varchar(50) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `po_number` varchar(100) DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `receiving_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `store_b_finished_products_in`
--

INSERT INTO `store_b_finished_products_in` (`id`, `product_id`, `product_name`, `product_code`, `category`, `description`, `delivery_number`, `quantity_received`, `remaining_quantity`, `units`, `pack_size`, `unit_cost`, `po_number`, `received_by`, `receiving_date`, `created_at`) VALUES
(1, 16, 'Y400', 'Y-004', 'DETERGENT', 'Detergent', 'ISJ187vs', 1000.00, 500.00, 'kg', '20kg', 1000.00, '40029', 'Store b engineer', '0000-00-00', '2025-10-31 07:12:57'),
(2, 16, 'Y400', 'Y-004', 'DETERGENT', 'Detergent', 'ISJ187vsK', 1000.00, 1000.00, 'kg', '20kg', 1000.00, '40029', 'Store b engineer', '2025-10-31', '2025-10-31 07:13:34'),
(3, 16, 'Y400', 'Y-004', 'DETERGENT', 'Detergent', 'ISJ187vs', 1000.00, 1000.00, 'kg', '20kg', 100.00, '40032', 'Store b engineer', '2025-11-05', '2025-11-05 08:00:39'),
(4, 14, 'WHEAT', 'WT001', 'Flour', 'Flour', 'ISJ187vs', 1000.00, 1000.00, 'kg', '20kg', 100.00, '40032', 'Store b engineer', '2025-11-05', '2025-11-05 08:21:49');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `chemical_id` int(11) DEFAULT NULL,
  `manual_product` varchar(255) DEFAULT NULL,
  `type_model_brand` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `user_of_product` varchar(255) DEFAULT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `supplier_contact` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `status` enum('available','unavailable','initial') DEFAULT 'initial',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `product_id`, `chemical_id`, `manual_product`, `type_model_brand`, `quantity`, `user_of_product`, `supplier_name`, `supplier_contact`, `price`, `payment_terms`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, '', 'Chemical', 1000, 'Stores', 'JUPED', '070000000', 1000.00, 'After purchase', 'available', '2025-10-03 12:03:48', '2025-10-03 12:03:48'),
(2, 1, NULL, '', 'Chemical', 1000, 'Stores', 'JUPED', '070000000', 1000.00, 'After purchase', 'available', '2025-10-03 12:04:20', '2025-10-03 12:04:20'),
(6, NULL, NULL, 'PROTANE', 'Chemical', 1000, 'Stores', 'JUPE', '070000000', NULL, 'After purchase', 'available', '2025-10-03 12:18:30', '2025-10-03 12:18:30'),
(7, NULL, NULL, NULL, NULL, NULL, NULL, 'JUPED', '070000000', 1000.00, 'After purchase', 'unavailable', '2025-10-03 12:57:55', '2025-10-03 12:57:55'),
(8, NULL, NULL, NULL, 'Chemical', 1000, 'Stores', 'JUPED', '070000000', 1000.00, 'After purchase', 'unavailable', '2025-10-03 13:03:04', '2025-10-03 13:03:04'),
(9, NULL, NULL, 'HOTHERM B', 'Chemical', 100, 'Production', 'KWETU', '0711111111', 4000.00, 'After purchase', 'available', '2025-10-14 06:40:37', '2025-10-14 06:40:37'),
(10, NULL, NULL, NULL, 'Chemical', 1000, 'Production', 'YTECH', '0790000000', 50000.00, 'After purchase', 'available', '2025-11-03 06:42:30', '2025-11-03 06:42:30'),
(11, NULL, NULL, NULL, 'Chemical', 1000, 'Production', 'YTECH', '0790000000', 50000.00, 'After purchase', 'available', '2025-11-03 06:47:16', '2025-11-03 06:47:16'),
(12, NULL, NULL, '', 'Chemical', 1000, 'Production', 'YTECH', '0790000000', 50000.00, 'After purchase', 'available', '2025-11-03 06:50:30', '2025-11-03 06:50:30'),
(13, NULL, NULL, '', 'Chemical', 1000, 'Production', 'YTECH', '0790000000', 50000.00, 'After purchase', 'available', '2025-11-03 06:54:31', '2025-11-03 06:54:31'),
(14, NULL, 16, 'Potash', 'Chemical', 1000, 'Production', 'YTECH', '0790000000', 50000.00, 'After purchase', 'available', '2025-11-03 06:56:07', '2025-11-03 06:56:07'),
(15, NULL, 17, 'PAAS', 'Chemical', 1000, 'Production', 'YTECH', '0790000000', 50000.00, 'After purchase', 'available', '2025-11-03 07:06:00', '2025-11-03 07:06:00');

-- --------------------------------------------------------

--
-- Table structure for table `trainings`
--

CREATE TABLE `trainings` (
  `training_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `training_name` varchar(200) NOT NULL,
  `training_date` date NOT NULL,
  `status` enum('Pending','Done') DEFAULT 'Pending',
  `done_by` varchar(100) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `trainings_request`
--

CREATE TABLE `trainings_request` (
  `training_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `training_title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Pending','Approved','Denied') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `trainings_request`
--

INSERT INTO `trainings_request` (`training_id`, `user_id`, `training_title`, `description`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 9, 'SALES', 'ABOUT SALES', '2025-10-24', '2025-10-24', 'Pending', '2025-10-24 13:32:49'),
(2, 10, 'SALES', 'about sales', '2025-10-28', '2025-10-28', 'Approved', '2025-10-28 05:35:38'),
(3, 10, 'SALES', 'about sales', '2025-10-29', '2025-10-29', 'Approved', '2025-10-28 09:58:27');

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `vehicle_id` varchar(100) DEFAULT NULL,
  `destination_from` varchar(100) DEFAULT NULL,
  `destination_to` varchar(100) DEFAULT NULL,
  `distance_km` decimal(10,2) DEFAULT NULL,
  `delivery_name` varchar(100) DEFAULT NULL,
  `delivery_date` datetime DEFAULT NULL,
  `route_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `trips`
--

INSERT INTO `trips` (`id`, `driver_name`, `vehicle_id`, `destination_from`, `destination_to`, `distance_km`, `delivery_name`, `delivery_date`, `route_name`, `created_at`) VALUES
(1, 'MUTANIA KK', 'KNM 1190', 'KITENGELA', 'INDUSTRIAL', 40.00, 'KWAL Delivery', '2025-10-07 09:35:00', 'MSA ROAD', '2025-10-07 06:55:34'),
(2, 'MUTANIA KK', 'KNM 1190', 'INDUSTRIAL', 'KITENGELA', 40.00, 'KWAL', '2025-10-07 09:36:00', 'MSA ROAD ', '2025-10-07 06:55:34'),
(3, 'MUTANIA KK', 'KNM 1190', 'KITENGELA', 'INDUSTRIAL', 40.00, 'KWAL Delivery', '2025-10-07 09:35:00', 'MSA ROAD', '2025-10-07 06:56:49'),
(4, 'MUTANIA KK', 'KNM 1190', 'INDUSTRIAL', 'KITENGELA', 40.00, 'KWAL', '2025-10-07 09:36:00', 'MSA ROAD ', '2025-10-07 06:56:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `group_id` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `employee_id`, `full_name`, `email`, `national_id`, `password`, `group_id`, `status`, `created_at`) VALUES
(1, NULL, 'Lynn tech', 'lynntechsuperadmin@gmail.com', NULL, '$2y$10$6oQdPSZHY86qfKDX/DbBUeTlev7CTS.EavoOLhidzahRpDL1nMJSi', 1, 'active', '2025-09-29 11:48:47'),
(2, NULL, 'stores', 'stores@gmail.com', NULL, '$2y$10$mfzO6Ge1qCEgDqQqmn8B4.ORUAy./gP.UJIXYeXhvoeaAE/LHkJQ2', 3, 'active', '2025-10-01 06:17:27'),
(3, NULL, 'hr', 'hr@gmail.com', NULL, '$2y$10$jg89H.SoI8E0KTy7wVgG0OQCBGlqYxVh5rTQaX6Ce0rZPrwvjhpvu', 2, 'active', '2025-10-09 06:10:30'),
(4, NULL, 'procurement', 'procurement@gmail.com', NULL, '$2y$10$bWB0IVTPwCBpDiz7TfATZ.LA6ALcVgz5wfovE2xKTu0UkvJpnyfHK', 6, 'active', '2025-10-09 06:10:57'),
(5, NULL, 'QC', 'qc@gmail.com', NULL, '$2y$10$EiW6uol70gUnMfxGUkMcF.dsTjEdrAA9WZ2QYNu.E.O7Q06gZS0d6', 5, 'active', '2025-10-09 06:11:20'),
(6, NULL, 'driver', 'driver@gmail.com', NULL, '$2y$10$Q.oPTUMJL1765Qr/Zez9IOMMsKRTzp9GtHjlwlyQS5kehAlDYDwoK', 7, 'active', '2025-10-09 06:11:50'),
(7, NULL, 'report', 'report@gmail.com', NULL, '$2y$10$V3siQpYl.nXYQ9pm6GhiFuH5o5OBQf4EVGcPYX13XwZckEzgcXAA2', 8, 'active', '2025-10-09 06:12:20'),
(8, NULL, 'production', 'production@gmail.com', NULL, '$2y$10$5H7WTJQDa.i2kaiH4IhOj.K0b3neCa9nPagRuO2ilNrIdskqzUsI.', 4, 'active', '2025-10-09 06:21:15'),
(11, NULL, 'Accounts', 'accounts@lynntech.co.ke', '', '$2y$10$mvABsEHHZe/Se2hdjJkGoeG6.ExM1U.U22X/I99a/HRCzg1WGtOoa', 10, 'active', '2025-10-28 06:38:00'),
(13, NULL, 'litein', 'litein@gmail.com', '', '$2y$10$yXWthK/WCAMOwXI4rpcj0OVwysPSvTzzMFAcAEzmdgRHG2bmsvqZS', 12, 'active', '2025-10-31 13:44:27'),
(14, 11, 'JOHN DOE', 'Johndoe@gmail.com', '8890900', '$2y$10$bzjrsD77Cb3qkEMqgitSauFBStJQY8BjEFgFSvOYUVZuZCKmWPGdu', 9, 'active', '2026-10-20 08:13:12');

-- --------------------------------------------------------

--
-- Table structure for table `user_performance_behaviours`
--

CREATE TABLE `user_performance_behaviours` (
  `id` int(11) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `category` varchar(150) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `user_performance_behaviours`
--

INSERT INTO `user_performance_behaviours` (`id`, `evaluation_id`, `category`, `rating`) VALUES
(1, 1, 'Integrity', 5),
(2, 1, 'Coworker Relations', 4),
(3, 1, 'Client Relations', 4),
(4, 1, 'Technical Skills', 4),
(5, 1, 'Dependability', 4),
(6, 1, 'Punctuality', 4),
(7, 1, 'Attendance', 4),
(8, 2, 'Integrity', 5),
(9, 2, 'Coworker Relations', 4),
(10, 2, 'Client Relations', 4),
(11, 2, 'Technical Skills', 4),
(12, 2, 'Dependability', 4),
(13, 2, 'Punctuality', 4),
(14, 2, 'Attendance', 4);

-- --------------------------------------------------------

--
-- Table structure for table `user_performance_evaluation`
--

CREATE TABLE `user_performance_evaluation` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `evaluator_name` varchar(150) DEFAULT NULL,
  `position_department` varchar(150) DEFAULT NULL,
  `academic_qualification` varchar(150) DEFAULT NULL,
  `academic_grade` varchar(50) DEFAULT NULL,
  `professional_qualification` varchar(150) DEFAULT NULL,
  `professional_grade` varchar(50) DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `key_activities` text DEFAULT NULL,
  `accomplishments` text DEFAULT NULL,
  `challenges` text DEFAULT NULL,
  `improvement_plan` text DEFAULT NULL,
  `previous_goals` text DEFAULT NULL,
  `future_goals` text DEFAULT NULL,
  `manager_support` text DEFAULT NULL,
  `employee_concerns` text DEFAULT NULL,
  `overall_comments_appraisee` text DEFAULT NULL,
  `overall_comments_appraiser` text DEFAULT NULL,
  `approved_by` varchar(150) DEFAULT NULL,
  `approved_signature` varchar(150) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `authorized_by` varchar(150) DEFAULT NULL,
  `authorized_signature` varchar(150) DEFAULT NULL,
  `authorized_date` date DEFAULT NULL,
  `eval_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `user_performance_evaluation`
--

INSERT INTO `user_performance_evaluation` (`id`, `user_id`, `evaluator_name`, `position_department`, `academic_qualification`, `academic_grade`, `professional_qualification`, `professional_grade`, `strengths`, `key_activities`, `accomplishments`, `challenges`, `improvement_plan`, `previous_goals`, `future_goals`, `manager_support`, `employee_concerns`, `overall_comments_appraisee`, `overall_comments_appraiser`, `approved_by`, `approved_signature`, `approved_date`, `authorized_by`, `authorized_signature`, `authorized_date`, `eval_date`, `created_at`) VALUES
(1, 10, 'DAVE', 'ICT', 'Bachelors', 'A', 'ICT', 'A', 'A', 'B', 'ALL', 'NON', 'YES', '', 'YES', 'YES', 'YES', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-30', '2025-10-30 10:06:06'),
(2, 12, 'DAVE', 'ICT', 'Bachelors', 'A', 'ICT', 'A', 'YES', 'YES', 'YES', 'YES', 'YES', 'YES', 'YES', 'YES', 'YES', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-30', '2025-10-30 11:05:33');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `vehicle_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `driver_name`, `vehicle_number`, `model`, `license_expiry`, `vehicle_name`) VALUES
(1, 'MUTANIA KK', 'KNM 1190', 'ISUZU', '2025-10-07', 'Lorry'),
(2, 'KAMULU', 'KFV 009A', 'ISUZU', '2025-10-22', 'Pickup');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_maintenance`
--

CREATE TABLE `vehicle_maintenance` (
  `id` int(11) NOT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `maintenance_name` varchar(100) DEFAULT NULL,
  `maintenance_company` varchar(100) DEFAULT NULL,
  `maintenance_cost` decimal(10,2) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `vehicle_maintenance`
--

INSERT INTO `vehicle_maintenance` (`id`, `driver_name`, `vehicle_number`, `maintenance_name`, `maintenance_company`, `maintenance_cost`, `approved_by`, `receipt_path`, `created_at`) VALUES
(1, 'MUTANIA KK', 'KNM 1190', 'Battery', 'ETC', 9000.00, 'mutania kyalo', 'uploads/receipts/1759814076_pic.jpg', '2025-10-07 05:14:36'),
(2, 'KAMULU', 'KFV 009A', 'Tire', 'ETC', 10000.00, 'mutania kyalo', 'uploads/receipts/1759822150_trouble.jpg', '2025-10-07 07:29:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_staff_date` (`user_id`,`attendance_date`),
  ADD KEY `idx_attendance_date` (`attendance_date`),
  ADD KEY `idx_attendance_user_date` (`user_id`,`attendance_date`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_audit_date` (`audit_date`),
  ADD KEY `idx_next_due_date` (`next_due_date`),
  ADD KEY `idx_audit_status` (`status`);

--
-- Indexes for table `bill_of_materials`
--
ALTER TABLE `bill_of_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `bill_of_material_items`
--
ALTER TABLE `bill_of_material_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bom_id` (`bom_id`),
  ADD KEY `idx_chemical_id` (`chemical_id`);

--
-- Indexes for table `bom`
--
ALTER TABLE `bom`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `bom_materials`
--
ALTER TABLE `bom_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bom_id` (`bom_id`),
  ADD KEY `fk_bom_materials_chemicalnames` (`chemical_id`);

--
-- Indexes for table `breakfast_expense`
--
ALTER TABLE `breakfast_expense`
  ADD PRIMARY KEY (`breakfast_id`);

--
-- Indexes for table `chemicals_in`
--
ALTER TABLE `chemicals_in`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_chemical_in_name` (`chemical_id`);

--
-- Indexes for table `chemical_names`
--
ALTER TABLE `chemical_names`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `daily_reports`
--
ALTER TABLE `daily_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `daily_tasks`
--
ALTER TABLE `daily_tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `deductions`
--
ALTER TABLE `deductions`
  ADD PRIMARY KEY (`deduction_id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_po` (`po_id`);

--
-- Indexes for table `delivery_details`
--
ALTER TABLE `delivery_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_id` (`delivery_id`);

--
-- Indexes for table `delivery_orders`
--
ALTER TABLE `delivery_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_id` (`delivery_id`);

--
-- Indexes for table `delivery_orders_store_b`
--
ALTER TABLE `delivery_orders_store_b`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `delivery_order_items`
--
ALTER TABLE `delivery_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `delivery_order_items_store_b`
--
ALTER TABLE `delivery_order_items_store_b`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `department_requests`
--
ALTER TABLE `department_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `disciplinary_cases`
--
ALTER TABLE `disciplinary_cases`
  ADD PRIMARY KEY (`case_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_case_date` (`case_date`),
  ADD KEY `idx_outcome` (`outcome`);

--
-- Indexes for table `disposables`
--
ALTER TABLE `disposables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `national_id` (`national_id`),
  ADD UNIQUE KEY `kra_pin` (`kra_pin`),
  ADD UNIQUE KEY `nssf_number` (`nssf_number`),
  ADD UNIQUE KEY `nhif_number` (`nhif_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `employee_allowances`
--
ALTER TABLE `employee_allowances`
  ADD PRIMARY KEY (`allowance_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `employee_appraisal`
--
ALTER TABLE `employee_appraisal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `employee_deductions`
--
ALTER TABLE `employee_deductions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `deduction_id` (`deduction_id`);

--
-- Indexes for table `employee_information`
--
ALTER TABLE `employee_information`
  ADD PRIMARY KEY (`info_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employee_information_items`
--
ALTER TABLE `employee_information_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employee_salary`
--
ALTER TABLE `employee_salary`
  ADD PRIMARY KEY (`salary_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `finished_products`
--
ALTER TABLE `finished_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_run_id` (`production_run_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `fuel_cost`
--
ALTER TABLE `fuel_cost`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `goals`
--
ALTER TABLE `goals`
  ADD PRIMARY KEY (`goal_id`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `goal_employees`
--
ALTER TABLE `goal_employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_goal_employee` (`goal_id`,`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`group_id`),
  ADD UNIQUE KEY `group_name` (`group_name`);

--
-- Indexes for table `inspected_chemicals_in`
--
ALTER TABLE `inspected_chemicals_in`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chemical_id` (`chemical_id`);

--
-- Indexes for table `issued_items`
--
ALTER TABLE `issued_items`
  ADD PRIMARY KEY (`issue_id`),
  ADD KEY `idx_issue_status` (`status`),
  ADD KEY `idx_issue_date` (`issue_date`),
  ADD KEY `idx_issue_item` (`item_name`);

--
-- Indexes for table `job_adverts`
--
ALTER TABLE `job_adverts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_job_advert_token` (`link_token_hash`),
  ADD KEY `idx_job_advert_status_deadline` (`status`,`application_deadline`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_application_email_per_job` (`job_advert_id`,`email`),
  ADD KEY `idx_application_status` (`status`),
  ADD KEY `idx_application_submitted` (`submitted_at`);

--
-- Indexes for table `job_application_files`
--
ALTER TABLE `job_application_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stored_filename` (`stored_name`),
  ADD KEY `idx_application_file` (`application_id`);

--
-- Indexes for table `job_descriptions`
--
ALTER TABLE `job_descriptions`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_job_role` (`job_role`);

--
-- Indexes for table `label_reconciliation`
--
ALTER TABLE `label_reconciliation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `lunch_expense`
--
ALTER TABLE `lunch_expense`
  ADD PRIMARY KEY (`lunch_id`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `material_out_history`
--
ALTER TABLE `material_out_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `order_deliveries`
--
ALTER TABLE `order_deliveries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_deliveries_store_b`
--
ALTER TABLE `order_deliveries_store_b`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_delivery_items`
--
ALTER TABLE `order_delivery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_id` (`delivery_id`);

--
-- Indexes for table `order_delivery_items_store_b`
--
ALTER TABLE `order_delivery_items_store_b`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_id` (`delivery_id`),
  ADD KEY `delivery_order_id` (`delivery_order_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `organization_hierarchy`
--
ALTER TABLE `organization_hierarchy`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `idx_reports_to` (`reports_to_employee_id`);

--
-- Indexes for table `packaging`
--
ALTER TABLE `packaging`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_run_id` (`production_run_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `packaging_reconciliation`
--
ALTER TABLE `packaging_reconciliation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `qc_inspection_id` (`qc_inspection_id`);

--
-- Indexes for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD PRIMARY KEY (`payroll_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `performance_behaviours`
--
ALTER TABLE `performance_behaviours`
  ADD PRIMARY KEY (`behaviour_id`),
  ADD KEY `eval_id` (`eval_id`);

--
-- Indexes for table `performance_evaluations`
--
ALTER TABLE `performance_evaluations`
  ADD PRIMARY KEY (`eval_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `performance_objectives`
--
ALTER TABLE `performance_objectives`
  ADD PRIMARY KEY (`objective_id`),
  ADD KEY `eval_id` (`eval_id`);

--
-- Indexes for table `po_list`
--
ALTER TABLE `po_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_no` (`po_no`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `procurement_products`
--
ALTER TABLE `procurement_products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `production_procedures`
--
ALTER TABLE `production_procedures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_run_id` (`production_run_id`);

--
-- Indexes for table `production_runs`
--
ALTER TABLE `production_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_id` (`request_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `qc_inspections`
--
ALTER TABLE `qc_inspections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_run_id` (`production_run_id`);

--
-- Indexes for table `qc_tests`
--
ALTER TABLE `qc_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `qc_inspection_id` (`qc_inspection_id`);

--
-- Indexes for table `quality_manager_review`
--
ALTER TABLE `quality_manager_review`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_run_id` (`production_run_id`);

--
-- Indexes for table `rejected_chemicals_in`
--
ALTER TABLE `rejected_chemicals_in`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_chemical_in_name` (`chemical_id`);

--
-- Indexes for table `shortlisted_applicants`
--
ALTER TABLE `shortlisted_applicants`
  ADD PRIMARY KEY (`applicant_id`),
  ADD KEY `idx_interview_date` (`interview_date`),
  ADD KEY `idx_interview_status` (`interview_status`),
  ADD KEY `idx_position` (`position_applied`);

--
-- Indexes for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_names`
--
ALTER TABLE `stock_names`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_out_history`
--
ALTER TABLE `stock_out_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `store_b_chemicals_in`
--
ALTER TABLE `store_b_chemicals_in`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chemical_id` (`chemical_id`);

--
-- Indexes for table `store_b_engineering_products_in`
--
ALTER TABLE `store_b_engineering_products_in`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `store_b_finished_products_in`
--
ALTER TABLE `store_b_finished_products_in`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_supplier_product` (`product_id`);

--
-- Indexes for table `trainings`
--
ALTER TABLE `trainings`
  ADD PRIMARY KEY (`training_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `trainings_request`
--
ALTER TABLE `trainings_request`
  ADD PRIMARY KEY (`training_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `user_performance_behaviours`
--
ALTER TABLE `user_performance_behaviours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evaluation_id` (`evaluation_id`);

--
-- Indexes for table `user_performance_evaluation`
--
ALTER TABLE `user_performance_evaluation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicle_maintenance`
--
ALTER TABLE `vehicle_maintenance`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bill_of_materials`
--
ALTER TABLE `bill_of_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `bill_of_material_items`
--
ALTER TABLE `bill_of_material_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `bom`
--
ALTER TABLE `bom`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `bom_materials`
--
ALTER TABLE `bom_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `breakfast_expense`
--
ALTER TABLE `breakfast_expense`
  MODIFY `breakfast_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chemicals_in`
--
ALTER TABLE `chemicals_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `chemical_names`
--
ALTER TABLE `chemical_names`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `daily_reports`
--
ALTER TABLE `daily_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `daily_tasks`
--
ALTER TABLE `daily_tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `deductions`
--
ALTER TABLE `deductions`
  MODIFY `deduction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `delivery_details`
--
ALTER TABLE `delivery_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `delivery_items`
--
ALTER TABLE `delivery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_orders`
--
ALTER TABLE `delivery_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `delivery_orders_store_b`
--
ALTER TABLE `delivery_orders_store_b`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `delivery_order_items`
--
ALTER TABLE `delivery_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `delivery_order_items_store_b`
--
ALTER TABLE `delivery_order_items_store_b`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `department_requests`
--
ALTER TABLE `department_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `disciplinary_cases`
--
ALTER TABLE `disciplinary_cases`
  MODIFY `case_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `disposables`
--
ALTER TABLE `disposables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `employee_allowances`
--
ALTER TABLE `employee_allowances`
  MODIFY `allowance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employee_appraisal`
--
ALTER TABLE `employee_appraisal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employee_deductions`
--
ALTER TABLE `employee_deductions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `employee_information`
--
ALTER TABLE `employee_information`
  MODIFY `info_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employee_information_items`
--
ALTER TABLE `employee_information_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employee_salary`
--
ALTER TABLE `employee_salary`
  MODIFY `salary_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `finished_products`
--
ALTER TABLE `finished_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `fuel_cost`
--
ALTER TABLE `fuel_cost`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `goals`
--
ALTER TABLE `goals`
  MODIFY `goal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `goal_employees`
--
ALTER TABLE `goal_employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `inspected_chemicals_in`
--
ALTER TABLE `inspected_chemicals_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `issued_items`
--
ALTER TABLE `issued_items`
  MODIFY `issue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `job_adverts`
--
ALTER TABLE `job_adverts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_application_files`
--
ALTER TABLE `job_application_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_descriptions`
--
ALTER TABLE `job_descriptions`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `label_reconciliation`
--
ALTER TABLE `label_reconciliation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `lunch_expense`
--
ALTER TABLE `lunch_expense`
  MODIFY `lunch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `material_out_history`
--
ALTER TABLE `material_out_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `order_deliveries`
--
ALTER TABLE `order_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `order_deliveries_store_b`
--
ALTER TABLE `order_deliveries_store_b`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_delivery_items`
--
ALTER TABLE `order_delivery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_delivery_items_store_b`
--
ALTER TABLE `order_delivery_items_store_b`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `organization_hierarchy`
--
ALTER TABLE `organization_hierarchy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packaging`
--
ALTER TABLE `packaging`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `packaging_reconciliation`
--
ALTER TABLE `packaging_reconciliation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payroll_records`
--
ALTER TABLE `payroll_records`
  MODIFY `payroll_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `performance_behaviours`
--
ALTER TABLE `performance_behaviours`
  MODIFY `behaviour_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `performance_evaluations`
--
ALTER TABLE `performance_evaluations`
  MODIFY `eval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `performance_objectives`
--
ALTER TABLE `performance_objectives`
  MODIFY `objective_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `po_list`
--
ALTER TABLE `po_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `procurement_products`
--
ALTER TABLE `procurement_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `production_procedures`
--
ALTER TABLE `production_procedures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `production_runs`
--
ALTER TABLE `production_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `qc_inspections`
--
ALTER TABLE `qc_inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `qc_tests`
--
ALTER TABLE `qc_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `quality_manager_review`
--
ALTER TABLE `quality_manager_review`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `rejected_chemicals_in`
--
ALTER TABLE `rejected_chemicals_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `shortlisted_applicants`
--
ALTER TABLE `shortlisted_applicants`
  MODIFY `applicant_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_in`
--
ALTER TABLE `stock_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_names`
--
ALTER TABLE `stock_names`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_out_history`
--
ALTER TABLE `stock_out_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `store_b_chemicals_in`
--
ALTER TABLE `store_b_chemicals_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `store_b_engineering_products_in`
--
ALTER TABLE `store_b_engineering_products_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `store_b_finished_products_in`
--
ALTER TABLE `store_b_finished_products_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `trainings`
--
ALTER TABLE `trainings`
  MODIFY `training_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `trainings_request`
--
ALTER TABLE `trainings_request`
  MODIFY `training_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_performance_behaviours`
--
ALTER TABLE `user_performance_behaviours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_performance_evaluation`
--
ALTER TABLE `user_performance_evaluation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vehicle_maintenance`
--
ALTER TABLE `vehicle_maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bill_of_materials`
--
ALTER TABLE `bill_of_materials`
  ADD CONSTRAINT `bill_of_materials_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bill_of_material_items`
--
ALTER TABLE `bill_of_material_items`
  ADD CONSTRAINT `bill_of_material_items_ibfk_1` FOREIGN KEY (`bom_id`) REFERENCES `bill_of_materials` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bom`
--
ALTER TABLE `bom`
  ADD CONSTRAINT `bom_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bom_materials`
--
ALTER TABLE `bom_materials`
  ADD CONSTRAINT `bom_materials_ibfk_1` FOREIGN KEY (`bom_id`) REFERENCES `bom` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bom_materials_chemicalnames` FOREIGN KEY (`chemical_id`) REFERENCES `chemical_names` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chemicals_in`
--
ALTER TABLE `chemicals_in`
  ADD CONSTRAINT `fk_chemical_in_name` FOREIGN KEY (`chemical_id`) REFERENCES `chemical_names` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_reports`
--
ALTER TABLE `daily_reports`
  ADD CONSTRAINT `daily_reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_tasks`
--
ALTER TABLE `daily_tasks`
  ADD CONSTRAINT `daily_tasks_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `daily_reports` (`report_id`) ON DELETE CASCADE;

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `fk_po` FOREIGN KEY (`po_id`) REFERENCES `po_list` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD CONSTRAINT `delivery_items_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_orders`
--
ALTER TABLE `delivery_orders`
  ADD CONSTRAINT `delivery_orders_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `delivery_details` (`id`);

--
-- Constraints for table `delivery_order_items`
--
ALTER TABLE `delivery_order_items`
  ADD CONSTRAINT `delivery_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `delivery_orders` (`id`);

--
-- Constraints for table `delivery_order_items_store_b`
--
ALTER TABLE `delivery_order_items_store_b`
  ADD CONSTRAINT `delivery_order_items_store_b_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `delivery_orders_store_b` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_allowances`
--
ALTER TABLE `employee_allowances`
  ADD CONSTRAINT `employee_allowances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `employee_appraisal`
--
ALTER TABLE `employee_appraisal`
  ADD CONSTRAINT `employee_appraisal_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_deductions`
--
ALTER TABLE `employee_deductions`
  ADD CONSTRAINT `employee_deductions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_deductions_ibfk_2` FOREIGN KEY (`deduction_id`) REFERENCES `deductions` (`deduction_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_information`
--
ALTER TABLE `employee_information`
  ADD CONSTRAINT `employee_information_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_information_items`
--
ALTER TABLE `employee_information_items`
  ADD CONSTRAINT `employee_information_items_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_salary`
--
ALTER TABLE `employee_salary`
  ADD CONSTRAINT `employee_salary_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `finished_products`
--
ALTER TABLE `finished_products`
  ADD CONSTRAINT `finished_products_ibfk_1` FOREIGN KEY (`production_run_id`) REFERENCES `production_runs` (`id`),
  ADD CONSTRAINT `finished_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `goal_employees`
--
ALTER TABLE `goal_employees`
  ADD CONSTRAINT `fk_goal_employee_goal` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`goal_id`) ON DELETE CASCADE;

--
-- Constraints for table `inspected_chemicals_in`
--
ALTER TABLE `inspected_chemicals_in`
  ADD CONSTRAINT `inspected_chemicals_in_ibfk_1` FOREIGN KEY (`chemical_id`) REFERENCES `chemicals_in` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD CONSTRAINT `fk_application_advert` FOREIGN KEY (`job_advert_id`) REFERENCES `job_adverts` (`id`);

--
-- Constraints for table `job_application_files`
--
ALTER TABLE `job_application_files`
  ADD CONSTRAINT `fk_application_file` FOREIGN KEY (`application_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `leaves_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `material_out_history`
--
ALTER TABLE `material_out_history`
  ADD CONSTRAINT `material_out_history_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`);

--
-- Constraints for table `order_delivery_items`
--
ALTER TABLE `order_delivery_items`
  ADD CONSTRAINT `order_delivery_items_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `order_deliveries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_delivery_items_store_b`
--
ALTER TABLE `order_delivery_items_store_b`
  ADD CONSTRAINT `order_delivery_items_store_b_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `order_deliveries_store_b` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_delivery_items_store_b_ibfk_2` FOREIGN KEY (`delivery_order_id`) REFERENCES `delivery_orders_store_b` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `po_list` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `procurement_products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `organization_hierarchy`
--
ALTER TABLE `organization_hierarchy`
  ADD CONSTRAINT `fk_hierarchy_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hierarchy_manager` FOREIGN KEY (`reports_to_employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL;

--
-- Constraints for table `packaging`
--
ALTER TABLE `packaging`
  ADD CONSTRAINT `packaging_ibfk_1` FOREIGN KEY (`production_run_id`) REFERENCES `production_runs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `packaging_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `packaging_reconciliation`
--
ALTER TABLE `packaging_reconciliation`
  ADD CONSTRAINT `packaging_reconciliation_ibfk_1` FOREIGN KEY (`qc_inspection_id`) REFERENCES `qc_inspections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD CONSTRAINT `payroll_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `performance_behaviours`
--
ALTER TABLE `performance_behaviours`
  ADD CONSTRAINT `performance_behaviours_ibfk_1` FOREIGN KEY (`eval_id`) REFERENCES `performance_evaluations` (`eval_id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_evaluations`
--
ALTER TABLE `performance_evaluations`
  ADD CONSTRAINT `performance_evaluations_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_objectives`
--
ALTER TABLE `performance_objectives`
  ADD CONSTRAINT `performance_objectives_ibfk_1` FOREIGN KEY (`eval_id`) REFERENCES `performance_evaluations` (`eval_id`) ON DELETE CASCADE;

--
-- Constraints for table `po_list`
--
ALTER TABLE `po_list`
  ADD CONSTRAINT `po_list_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `production_procedures`
--
ALTER TABLE `production_procedures`
  ADD CONSTRAINT `production_procedures_ibfk_1` FOREIGN KEY (`production_run_id`) REFERENCES `production_runs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qc_inspections`
--
ALTER TABLE `qc_inspections`
  ADD CONSTRAINT `qc_inspections_ibfk_1` FOREIGN KEY (`production_run_id`) REFERENCES `production_runs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qc_tests`
--
ALTER TABLE `qc_tests`
  ADD CONSTRAINT `qc_tests_ibfk_1` FOREIGN KEY (`qc_inspection_id`) REFERENCES `qc_inspections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quality_manager_review`
--
ALTER TABLE `quality_manager_review`
  ADD CONSTRAINT `quality_manager_review_ibfk_1` FOREIGN KEY (`production_run_id`) REFERENCES `production_runs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_b_chemicals_in`
--
ALTER TABLE `store_b_chemicals_in`
  ADD CONSTRAINT `store_b_chemicals_in_ibfk_1` FOREIGN KEY (`chemical_id`) REFERENCES `chemical_names` (`id`);

--
-- Constraints for table `store_b_engineering_products_in`
--
ALTER TABLE `store_b_engineering_products_in`
  ADD CONSTRAINT `store_b_engineering_products_in_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `chemical_names` (`id`);

--
-- Constraints for table `store_b_finished_products_in`
--
ALTER TABLE `store_b_finished_products_in`
  ADD CONSTRAINT `store_b_finished_products_in_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `fk_supplier_product` FOREIGN KEY (`product_id`) REFERENCES `procurement_products` (`id`);

--
-- Constraints for table `trainings`
--
ALTER TABLE `trainings`
  ADD CONSTRAINT `trainings_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `trainings_request`
--
ALTER TABLE `trainings_request`
  ADD CONSTRAINT `trainings_request_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_performance_behaviours`
--
ALTER TABLE `user_performance_behaviours`
  ADD CONSTRAINT `user_performance_behaviours_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `user_performance_evaluation` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
