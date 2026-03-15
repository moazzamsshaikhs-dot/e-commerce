-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 12, 2026 at 09:44 AM
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
-- Database: `ecommerce_db`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `get_bank_format` (`country_code` VARCHAR(2)) RETURNS LONGTEXT CHARSET utf8mb4 COLLATE utf8mb4_bin DETERMINISTIC READS SQL DATA BEGIN
    DECLARE format_json JSON;
    SELECT `bank_format` INTO format_json FROM `countries` WHERE `code` = country_code;
    RETURN format_json;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `account_balance_history`
--

CREATE TABLE `account_balance_history` (
  `id` int(11) NOT NULL,
  `admin_account_id` int(11) NOT NULL,
  `balance` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) DEFAULT 0.00,
  `change_type` enum('credit','debit','initial') DEFAULT 'initial',
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_accounts`
--

CREATE TABLE `admin_accounts` (
  `id` int(11) NOT NULL,
  `account_type` enum('paypal','stripe','easypaisa','jazzcash','visa','mastercard','bank') NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_email` varchar(255) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `account_holder` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `swift_code` varchar(50) DEFAULT NULL,
  `routing_number` varchar(50) DEFAULT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `cnic` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `total_credited` decimal(10,2) DEFAULT 0.00,
  `total_debited` decimal(10,2) DEFAULT 0.00,
  `last_transaction_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_accounts`
--

INSERT INTO `admin_accounts` (`id`, `account_type`, `account_name`, `account_email`, `account_number`, `account_holder`, `bank_name`, `swift_code`, `routing_number`, `iban`, `phone_number`, `cnic`, `is_active`, `is_default`, `current_balance`, `total_credited`, `total_debited`, `last_transaction_at`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'paypal', 'Primary PayPal', 'paypal@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 13:08:26', '2026-03-02 13:08:26'),
(2, 'stripe', 'Main Stripe', 'stripe@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 13:08:26', '2026-03-02 13:08:26'),
(3, 'easypaisa', 'Easypaisa Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 13:08:26', '2026-03-02 13:08:26'),
(4, 'jazzcash', 'JazzCash Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 13:08:26', '2026-03-02 13:08:26'),
(5, 'bank', 'Business Bank Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 13:08:26', '2026-03-02 13:08:26'),
(6, 'paypal', 'Primary PayPal', 'paypal@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:10:19', '2026-03-02 14:10:19'),
(7, 'stripe', 'Main Stripe', 'stripe@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:10:19', '2026-03-02 14:10:19'),
(8, 'easypaisa', 'Easypaisa Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:10:19', '2026-03-02 14:10:19'),
(9, 'jazzcash', 'JazzCash Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:10:19', '2026-03-02 14:10:19'),
(10, 'bank', 'Business Bank Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:10:19', '2026-03-02 14:10:19'),
(11, 'paypal', 'Primary PayPal', 'paypal@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:12:07', '2026-03-02 14:12:07'),
(12, 'stripe', 'Main Stripe', 'stripe@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:12:07', '2026-03-02 14:12:07'),
(13, 'easypaisa', 'Easypaisa Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:12:07', '2026-03-02 14:12:07'),
(14, 'jazzcash', 'JazzCash Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:12:07', '2026-03-02 14:12:07'),
(15, 'bank', 'Business Bank Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:12:07', '2026-03-02 14:12:07'),
(16, 'paypal', 'Primary PayPal', 'paypal@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:24', '2026-03-02 14:13:24'),
(17, 'stripe', 'Main Stripe', 'stripe@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:24', '2026-03-02 14:13:24'),
(18, 'easypaisa', 'Easypaisa Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:24', '2026-03-02 14:13:24'),
(19, 'jazzcash', 'JazzCash Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:24', '2026-03-02 14:13:24'),
(20, 'bank', 'Business Bank Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:24', '2026-03-02 14:13:24'),
(21, 'paypal', 'Primary PayPal', 'paypal@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:39', '2026-03-02 14:13:39'),
(22, 'stripe', 'Main Stripe', 'stripe@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:39', '2026-03-02 14:13:39'),
(23, 'easypaisa', 'Easypaisa Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:39', '2026-03-02 14:13:39'),
(24, 'jazzcash', 'JazzCash Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:39', '2026-03-02 14:13:39'),
(25, 'bank', 'Business Bank Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:39', '2026-03-02 14:13:39'),
(26, 'paypal', 'Primary PayPal', 'paypal@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:58', '2026-03-02 14:13:58'),
(27, 'stripe', 'Main Stripe', 'stripe@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:58', '2026-03-02 14:13:58'),
(28, 'easypaisa', 'Easypaisa Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:58', '2026-03-02 14:13:58'),
(29, 'jazzcash', 'JazzCash Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:58', '2026-03-02 14:13:58'),
(30, 'bank', 'Business Bank Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:13:58', '2026-03-02 14:13:58'),
(31, 'paypal', 'Primary PayPal', 'paypal@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:14:10', '2026-03-02 14:14:10'),
(32, 'stripe', 'Main Stripe', 'stripe@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:14:10', '2026-03-02 14:14:10'),
(33, 'easypaisa', 'Easypaisa Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:14:10', '2026-03-02 14:14:10'),
(34, 'jazzcash', 'JazzCash Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:14:10', '2026-03-02 14:14:10'),
(35, 'bank', 'Business Bank Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:14:10', '2026-03-02 14:14:10'),
(36, 'paypal', 'Primary PayPal', 'paypal@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:14:22', '2026-03-02 14:14:22'),
(37, 'stripe', 'Main Stripe', 'stripe@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:14:22', '2026-03-02 14:14:22'),
(38, 'easypaisa', 'Easypaisa Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:14:22', '2026-03-02 14:14:22'),
(39, 'jazzcash', 'JazzCash Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:14:22', '2026-03-02 14:14:22'),
(40, 'bank', 'Business Bank Account', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-03-02 14:14:22', '2026-03-02 14:14:22');

-- --------------------------------------------------------

--
-- Table structure for table `admin_commission_collections`
--

CREATE TABLE `admin_commission_collections` (
  `id` int(11) NOT NULL,
  `vendor_commission_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `commission_amount` decimal(10,2) NOT NULL,
  `collected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('collected','pending','transferred') DEFAULT 'collected',
  `admin_account_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_payment_accounts`
--

CREATE TABLE `admin_payment_accounts` (
  `id` int(11) NOT NULL,
  `gateway_id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_details` longtext DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_system_access`
--

CREATE TABLE `admin_system_access` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `access_level` enum('full','limited','view_only') DEFAULT 'limited',
  `can_manage_accounts` tinyint(1) DEFAULT 0,
  `can_manage_withdrawals` tinyint(1) DEFAULT 0,
  `can_view_commissions` tinyint(1) DEFAULT 1,
  `can_process_payments` tinyint(1) DEFAULT 0,
  `can_manage_admins` tinyint(1) DEFAULT 0,
  `is_super_admin` tinyint(1) DEFAULT 0,
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `last_accessed` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_system_access`
--

INSERT INTO `admin_system_access` (`id`, `admin_id`, `access_level`, `can_manage_accounts`, `can_manage_withdrawals`, `can_view_commissions`, `can_process_payments`, `can_manage_admins`, `is_super_admin`, `granted_by`, `granted_at`, `expires_at`, `last_accessed`) VALUES
(1, 2, 'full', 1, 1, 1, 1, 1, 1, NULL, '2026-03-02 14:10:19', NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `admin_vendors_management`
-- (See below for the actual view)
--
CREATE TABLE `admin_vendors_management` (
`id` int(11)
,`username` varchar(50)
,`email` varchar(100)
,`phone` varchar(20)
,`full_name` varchar(100)
,`user_type` enum('admin','user','vendor')
,`vendor_status` enum('pending','approved','rejected','suspended')
,`vendor_verified` tinyint(1)
,`vendor_since` date
,`vendor_rating` decimal(3,2)
,`total_products` int(11)
,`total_sales` int(11)
,`account_status` enum('active','suspended','deactivated')
,`created_at` timestamp
,`last_login` datetime
,`active_products` bigint(21)
,`pending_products` bigint(21)
,`total_vendor_orders` bigint(21)
,`total_vendor_sales` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `analytics`
--

CREATE TABLE `analytics` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `page_views` int(11) DEFAULT 0,
  `unique_visitors` int(11) DEFAULT 0,
  `bounce_rate` float DEFAULT 0,
  `avg_session_duration` int(11) DEFAULT 0,
  `conversions` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_pages`
--

CREATE TABLE `analytics_pages` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `page_url` varchar(255) NOT NULL,
  `page_title` varchar(255) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `unique_visitors` int(11) DEFAULT 0,
  `avg_time_on_page` int(11) DEFAULT 0,
  `bounce_rate` float DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_sources`
--

CREATE TABLE `analytics_sources` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `source_type` enum('direct','organic','referral','social','email','paid') DEFAULT 'direct',
  `source_name` varchar(255) DEFAULT NULL,
  `visitors` int(11) DEFAULT 0,
  `conversions` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_schedules`
--

CREATE TABLE `backup_schedules` (
  `id` int(11) NOT NULL,
  `schedule_type` enum('daily','weekly','monthly') DEFAULT 'daily',
  `backup_type` enum('full','database','files') DEFAULT 'database',
  `time` time DEFAULT '02:00:00',
  `day_of_week` int(11) DEFAULT 0,
  `day_of_month` int(11) DEFAULT 1,
  `keep_backups` int(11) DEFAULT 30,
  `last_run` datetime DEFAULT NULL,
  `next_run` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`id`, `user_id`, `product_id`, `quantity`, `added_at`) VALUES
(2, 5, 2, 1, '2026-03-06 13:19:11'),
(3, 5, 1, 1, '2026-03-06 15:06:09');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'approved',
  `vendor_id` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `parent_id`, `image`, `is_active`, `approval_status`, `vendor_id`, `rejection_reason`, `submitted_by`, `approved_by`, `approved_at`, `commission_rate`, `meta_title`, `meta_description`, `created_at`) VALUES
(1, 'Electronics', 'electronics', 'Latest gadgets, smartphones, laptops, and electronic accessories', NULL, 'electronics.jpg', 1, 'approved', NULL, NULL, NULL, NULL, NULL, 10.00, NULL, NULL, '2026-02-21 12:21:07'),
(2, 'Fashion', 'fashion', 'Trendy clothing, shoes, accessories for men and women', NULL, 'fashion.jpg', 1, 'approved', NULL, NULL, NULL, NULL, NULL, 8.00, NULL, NULL, '2026-02-21 12:21:07'),
(3, 'Home & Living', 'home-living', 'Furniture, home decor, kitchen appliances, and household items', NULL, 'home.jpg', 1, 'approved', NULL, NULL, NULL, NULL, NULL, 7.00, NULL, NULL, '2026-02-21 12:21:07'),
(4, 'Books', 'books', 'Fiction, non-fiction, educational, and bestseller books', NULL, 'books.jpg', 1, 'approved', NULL, NULL, NULL, NULL, NULL, 5.00, NULL, NULL, '2026-02-21 12:21:07'),
(5, 'Sports & Outdoors', 'sports', 'Sports equipment, fitness gear, camping, and outdoor recreation', NULL, 'sports.jpg', 1, 'approved', NULL, NULL, NULL, NULL, NULL, 6.00, NULL, NULL, '2026-02-21 12:21:07'),
(6, 'Toys & Games', 'toys', 'Toys for kids, board games, puzzles, and educational games', NULL, 'toys.jpg', 1, 'approved', NULL, NULL, NULL, NULL, NULL, 8.00, NULL, NULL, '2026-02-21 12:21:07'),
(7, 'Health & Beauty', 'health-beauty', 'Skincare, makeup, personal care, and wellness products', NULL, 'health.jpg', 1, 'approved', NULL, NULL, NULL, NULL, NULL, 9.00, NULL, NULL, '2026-02-21 12:21:07'),
(8, 'Automotive', 'automotive', 'Car accessories, tools, and automotive parts', NULL, 'automotive.jpg', 1, 'approved', NULL, NULL, NULL, NULL, NULL, 7.00, NULL, NULL, '2026-02-21 12:21:07'),
(9, 'Groceries', 'groceries', 'Food items, beverages, and household supplies', NULL, 'groceries.jpg', 1, 'approved', NULL, NULL, NULL, NULL, NULL, 5.00, NULL, NULL, '2026-02-21 12:21:07'),
(10, 'Baby Products', 'baby', 'Baby care, diapers, strollers, and nursery items', NULL, 'baby.jpg', 1, 'approved', NULL, NULL, NULL, NULL, NULL, 6.00, NULL, NULL, '2026-02-21 12:21:07');

-- --------------------------------------------------------

--
-- Table structure for table `category_change_requests`
--

CREATE TABLE `category_change_requests` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `request_type` enum('use_category','change_commission') NOT NULL,
  `old_commission_rate` decimal(5,2) DEFAULT NULL,
  `new_commission_rate` decimal(5,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `code` varchar(2) NOT NULL,
  `name` varchar(100) NOT NULL,
  `currency_code` varchar(3) DEFAULT 'USD',
  `currency_symbol` varchar(10) DEFAULT '$',
  `phone_code` varchar(10) DEFAULT NULL,
  `bank_format` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `code`, `name`, `currency_code`, `currency_symbol`, `phone_code`, `bank_format`, `is_active`, `created_at`) VALUES
(1, 'US', 'United States', 'USD', '$', '+1', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Routing Number (ABA)\",\r\n    \"routing_format\": \"\\\\d{9}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 17,\r\n    \"account_number_format\": \"\\\\d{9,17}\"\r\n}', 1, '2026-02-21 12:28:49'),
(2, 'GB', 'United Kingdom', 'GBP', '£', '+44', '{\r\n    \"routing_required\": false,\r\n    \"routing_label\": \"Sort Code\",\r\n    \"routing_format\": \"\\\\d{6}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Sort Code\",\r\n    \"branch_code_format\": \"\\\\d{6}\",\r\n    \"account_number_length\": 14,\r\n    \"account_number_format\": \"\\\\d{8,14}\"\r\n}', 1, '2026-02-21 12:28:49'),
(3, 'CA', 'Canada', 'CAD', 'C$', '+1', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Transit Number\",\r\n    \"routing_format\": \"\\\\d{5}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Institution Number\",\r\n    \"branch_code_format\": \"\\\\d{3}\",\r\n    \"account_number_length\": 12,\r\n    \"account_number_format\": \"\\\\d{7,12}\"\r\n}', 1, '2026-02-21 12:28:49'),
(4, 'AU', 'Australia', 'AUD', 'A$', '+61', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"BSB Code\",\r\n    \"routing_format\": \"\\\\d{6}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 9,\r\n    \"account_number_format\": \"\\\\d{6,9}\"\r\n}', 1, '2026-02-21 12:28:49'),
(5, 'IN', 'India', 'INR', '₹', '+91', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": true,\r\n    \"ifsc_format\": \"^[A-Z]{4}0[A-Z0-9]{6}$\",\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 18,\r\n    \"account_number_format\": \"\\\\d{9,18}\"\r\n}', 1, '2026-02-21 12:28:49'),
(6, 'PK', 'Pakistan', 'PKR', '₨', '+92', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Bank Code\",\r\n    \"branch_code_format\": \"\\\\d{4}\",\r\n    \"account_number_length\": 24,\r\n    \"account_number_format\": \"\\\\d{10,24}\"\r\n}', 1, '2026-02-21 12:28:49'),
(7, 'BD', 'Bangladesh', 'BDT', '৳', '+880', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Bank Code\",\r\n    \"branch_code_format\": \"\\\\d{2,4}\",\r\n    \"account_number_length\": 13,\r\n    \"account_number_format\": \"\\\\d{10,13}\"\r\n}', 1, '2026-02-21 12:28:49'),
(8, 'AE', 'United Arab Emirates', 'AED', 'د.إ', '+971', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^AE\\\\d{21}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 23,\r\n    \"account_number_format\": \"\\\\d{8,23}\"\r\n}', 1, '2026-02-21 12:28:49'),
(9, 'SA', 'Saudi Arabia', 'SAR', '﷼', '+966', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^SA\\\\d{22}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 24,\r\n    \"account_number_format\": \"\\\\d{8,24}\"\r\n}', 1, '2026-02-21 12:28:49'),
(10, 'MY', 'Malaysia', 'MYR', 'RM', '+60', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Branch Code\",\r\n    \"branch_code_format\": \"\\\\d{3}\",\r\n    \"account_number_length\": 12,\r\n    \"account_number_format\": \"\\\\d{9,12}\"\r\n}', 1, '2026-02-21 12:28:49'),
(11, 'SG', 'Singapore', 'SGD', 'S$', '+65', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Branch Code\",\r\n    \"branch_code_format\": \"\\\\d{3}\",\r\n    \"account_number_length\": 12,\r\n    \"account_number_format\": \"\\\\d{4,12}\"\r\n}', 1, '2026-02-21 12:28:49'),
(12, 'ID', 'Indonesia', 'IDR', 'Rp', '+62', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Branch Code\",\r\n    \"branch_code_format\": \"\\\\d{3}\",\r\n    \"account_number_length\": 16,\r\n    \"account_number_format\": \"\\\\d{8,16}\"\r\n}', 1, '2026-02-21 12:28:49'),
(13, 'AF', 'Afghanistan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(14, 'AL', 'Albania', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(15, 'DZ', 'Algeria', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(16, 'AS', 'American Samoa', 'USD', '$', '+1', NULL, 1, '2026-02-21 12:29:11'),
(17, 'AD', 'Andorra', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(18, 'AO', 'Angola', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(19, 'AI', 'Anguilla', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(20, 'AQ', 'Antarctica', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(21, 'AG', 'Antigua and Barbuda', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(22, 'AR', 'Argentina', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(23, 'AM', 'Armenia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(24, 'AW', 'Aruba', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(25, 'AU', 'Australia', 'AUD', 'A$', '+61', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"BSB Code\",\r\n    \"routing_format\": \"\\\\d{6}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 9,\r\n    \"account_number_format\": \"\\\\d{6,9}\"\r\n}', 1, '2026-02-21 12:29:11'),
(26, 'AT', 'Austria', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(27, 'AZ', 'Azerbaijan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(28, 'BS', 'Bahamas', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(29, 'BH', 'Bahrain', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(30, 'BD', 'Bangladesh', 'BDT', '৳', '+880', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Bank Code\",\r\n    \"branch_code_format\": \"\\\\d{2,4}\",\r\n    \"account_number_length\": 13,\r\n    \"account_number_format\": \"\\\\d{10,13}\"\r\n}', 1, '2026-02-21 12:29:11'),
(31, 'BB', 'Barbados', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(32, 'BY', 'Belarus', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(33, 'BE', 'Belgium', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(34, 'BZ', 'Belize', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(35, 'BJ', 'Benin', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(36, 'BM', 'Bermuda', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(37, 'BT', 'Bhutan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(38, 'BO', 'Bolivia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(39, 'BQ', 'Bonaire, Sint Eustatius and Saba', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(40, 'BA', 'Bosnia and Herzegovina', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(41, 'BW', 'Botswana', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(42, 'BV', 'Bouvet Island', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(43, 'BR', 'Brazil', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(44, 'IO', 'British Indian Ocean Territory', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(45, 'BN', 'Brunei Darussalam', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(46, 'BG', 'Bulgaria', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(47, 'BF', 'Burkina Faso', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(48, 'BI', 'Burundi', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(49, 'CV', 'Cabo Verde', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(50, 'KH', 'Cambodia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(51, 'CM', 'Cameroon', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(52, 'CA', 'Canada', 'CAD', 'C$', '+1', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Transit Number\",\r\n    \"routing_format\": \"\\\\d{5}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Institution Number\",\r\n    \"branch_code_format\": \"\\\\d{3}\",\r\n    \"account_number_length\": 12,\r\n    \"account_number_format\": \"\\\\d{7,12}\"\r\n}', 1, '2026-02-21 12:29:11'),
(53, 'KY', 'Cayman Islands', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(54, 'CF', 'Central African Republic', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(55, 'TD', 'Chad', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(56, 'CL', 'Chile', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(57, 'CN', 'China', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(58, 'CX', 'Christmas Island', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(59, 'CC', 'Cocos (Keeling) Islands', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(60, 'CO', 'Colombia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(61, 'KM', 'Comoros', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(62, 'CG', 'Congo', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(63, 'CD', 'Congo, Democratic Republic of the', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(64, 'CK', 'Cook Islands', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(65, 'CR', 'Costa Rica', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(66, 'HR', 'Croatia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(67, 'CU', 'Cuba', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(68, 'CW', 'Curaçao', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(69, 'CY', 'Cyprus', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(70, 'CZ', 'Czechia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(71, 'DK', 'Denmark', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(72, 'DJ', 'Djibouti', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(73, 'DM', 'Dominica', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(74, 'DO', 'Dominican Republic', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(75, 'EC', 'Ecuador', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(76, 'EG', 'Egypt', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(77, 'SV', 'El Salvador', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(78, 'GQ', 'Equatorial Guinea', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(79, 'ER', 'Eritrea', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(80, 'EE', 'Estonia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(81, 'SZ', 'Eswatini', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(82, 'ET', 'Ethiopia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(83, 'FK', 'Falkland Islands (Malvinas)', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(84, 'FO', 'Faroe Islands', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(85, 'FJ', 'Fiji', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(86, 'FI', 'Finland', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(87, 'FR', 'France', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(88, 'GF', 'French Guiana', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(89, 'PF', 'French Polynesia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(90, 'TF', 'French Southern Territories', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(91, 'GA', 'Gabon', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(92, 'GM', 'Gambia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(93, 'GE', 'Georgia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(94, 'DE', 'Germany', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(95, 'GH', 'Ghana', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(96, 'GI', 'Gibraltar', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(97, 'GR', 'Greece', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(98, 'GL', 'Greenland', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(99, 'GD', 'Grenada', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(100, 'GP', 'Guadeloupe', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(101, 'GU', 'Guam', 'USD', '$', '+1', NULL, 1, '2026-02-21 12:29:11'),
(102, 'GT', 'Guatemala', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(103, 'GG', 'Guernsey', 'GBP', '£', '+44', NULL, 1, '2026-02-21 12:29:11'),
(104, 'GN', 'Guinea', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(105, 'GW', 'Guinea-Bissau', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(106, 'GY', 'Guyana', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(107, 'HT', 'Haiti', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(108, 'HM', 'Heard Island and McDonald Islands', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(109, 'VA', 'Holy See', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(110, 'HN', 'Honduras', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(111, 'HK', 'Hong Kong', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(112, 'HU', 'Hungary', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(113, 'IS', 'Iceland', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(114, 'IN', 'India', 'INR', '₹', '+91', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": true,\r\n    \"ifsc_format\": \"^[A-Z]{4}0[A-Z0-9]{6}$\",\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 18,\r\n    \"account_number_format\": \"\\\\d{9,18}\"\r\n}', 1, '2026-02-21 12:29:11'),
(115, 'ID', 'Indonesia', 'IDR', 'Rp', '+62', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Branch Code\",\r\n    \"branch_code_format\": \"\\\\d{3}\",\r\n    \"account_number_length\": 16,\r\n    \"account_number_format\": \"\\\\d{8,16}\"\r\n}', 1, '2026-02-21 12:29:11'),
(116, 'IR', 'Iran', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(117, 'IQ', 'Iraq', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(118, 'IE', 'Ireland', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(119, 'IM', 'Isle of Man', 'GBP', '£', '+44', NULL, 1, '2026-02-21 12:29:11'),
(120, 'IL', 'Israel', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(121, 'IT', 'Italy', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(122, 'JM', 'Jamaica', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(123, 'JP', 'Japan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(124, 'JE', 'Jersey', 'GBP', '£', '+44', NULL, 1, '2026-02-21 12:29:11'),
(125, 'JO', 'Jordan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(126, 'KZ', 'Kazakhstan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(127, 'KE', 'Kenya', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(128, 'KI', 'Kiribati', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(129, 'KP', 'Korea, Democratic People\'s Republic of', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(130, 'KR', 'Korea, Republic of', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(131, 'KW', 'Kuwait', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(132, 'KG', 'Kyrgyzstan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(133, 'LA', 'Lao People\'s Democratic Republic', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(134, 'LV', 'Latvia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(135, 'LB', 'Lebanon', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(136, 'LS', 'Lesotho', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(137, 'LR', 'Liberia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(138, 'LY', 'Libya', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(139, 'LI', 'Liechtenstein', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(140, 'LT', 'Lithuania', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(141, 'LU', 'Luxembourg', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(142, 'MO', 'Macao', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(143, 'MG', 'Madagascar', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(144, 'MW', 'Malawi', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(145, 'MY', 'Malaysia', 'MYR', 'RM', '+60', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Branch Code\",\r\n    \"branch_code_format\": \"\\\\d{3}\",\r\n    \"account_number_length\": 12,\r\n    \"account_number_format\": \"\\\\d{9,12}\"\r\n}', 1, '2026-02-21 12:29:11'),
(146, 'MV', 'Maldives', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(147, 'ML', 'Mali', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(148, 'MT', 'Malta', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(149, 'MH', 'Marshall Islands', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(150, 'MQ', 'Martinique', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(151, 'MR', 'Mauritania', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(152, 'MU', 'Mauritius', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(153, 'YT', 'Mayotte', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(154, 'MX', 'Mexico', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(155, 'FM', 'Micronesia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(156, 'MD', 'Moldova', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(157, 'MC', 'Monaco', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(158, 'MN', 'Mongolia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(159, 'ME', 'Montenegro', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(160, 'MS', 'Montserrat', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(161, 'MA', 'Morocco', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(162, 'MZ', 'Mozambique', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(163, 'MM', 'Myanmar', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(164, 'NA', 'Namibia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(165, 'NR', 'Nauru', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(166, 'NP', 'Nepal', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(167, 'NL', 'Netherlands', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(168, 'NC', 'New Caledonia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(169, 'NZ', 'New Zealand', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(170, 'NI', 'Nicaragua', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(171, 'NE', 'Niger', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(172, 'NG', 'Nigeria', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(173, 'NU', 'Niue', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(174, 'NF', 'Norfolk Island', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(175, 'MP', 'Northern Mariana Islands', 'USD', '$', '+1', NULL, 1, '2026-02-21 12:29:11'),
(176, 'NO', 'Norway', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(177, 'OM', 'Oman', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(178, 'PK', 'Pakistan', 'PKR', '₨', '+92', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Bank Code\",\r\n    \"branch_code_format\": \"\\\\d{4}\",\r\n    \"account_number_length\": 24,\r\n    \"account_number_format\": \"\\\\d{10,24}\"\r\n}', 1, '2026-02-21 12:29:11'),
(179, 'PW', 'Palau', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(180, 'PS', 'Palestine, State of', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(181, 'PA', 'Panama', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(182, 'PG', 'Papua New Guinea', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(183, 'PY', 'Paraguay', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(184, 'PE', 'Peru', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(185, 'PH', 'Philippines', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(186, 'PN', 'Pitcairn', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(187, 'PL', 'Poland', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(188, 'PT', 'Portugal', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(189, 'PR', 'Puerto Rico', 'USD', '$', '+1', NULL, 1, '2026-02-21 12:29:11'),
(190, 'QA', 'Qatar', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(191, 'RE', 'Réunion', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(192, 'RO', 'Romania', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(193, 'RU', 'Russian Federation', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(194, 'RW', 'Rwanda', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(195, 'BL', 'Saint Barthélemy', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(196, 'SH', 'Saint Helena, Ascension and Tristan da Cunha', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(197, 'KN', 'Saint Kitts and Nevis', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(198, 'LC', 'Saint Lucia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(199, 'MF', 'Saint Martin (French part)', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(200, 'PM', 'Saint Pierre and Miquelon', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(201, 'VC', 'Saint Vincent and the Grenadines', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(202, 'WS', 'Samoa', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(203, 'SM', 'San Marino', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(204, 'ST', 'Sao Tome and Principe', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(205, 'SA', 'Saudi Arabia', 'SAR', '﷼', '+966', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^SA\\\\d{22}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 24,\r\n    \"account_number_format\": \"\\\\d{8,24}\"\r\n}', 1, '2026-02-21 12:29:11'),
(206, 'SN', 'Senegal', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(207, 'RS', 'Serbia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(208, 'SC', 'Seychelles', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(209, 'SL', 'Sierra Leone', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(210, 'SG', 'Singapore', 'SGD', 'S$', '+65', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Branch Code\",\r\n    \"branch_code_format\": \"\\\\d{3}\",\r\n    \"account_number_length\": 12,\r\n    \"account_number_format\": \"\\\\d{4,12}\"\r\n}', 1, '2026-02-21 12:29:11'),
(211, 'SX', 'Sint Maarten (Dutch part)', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(212, 'SK', 'Slovakia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(213, 'SI', 'Slovenia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(214, 'SB', 'Solomon Islands', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(215, 'SO', 'Somalia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(216, 'ZA', 'South Africa', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(217, 'GS', 'South Georgia and the South Sandwich Islands', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(218, 'SS', 'South Sudan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(219, 'ES', 'Spain', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(220, 'LK', 'Sri Lanka', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(221, 'SD', 'Sudan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(222, 'SR', 'Suriname', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(223, 'SJ', 'Svalbard and Jan Mayen', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(224, 'SE', 'Sweden', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(225, 'CH', 'Switzerland', 'USD', '$', NULL, '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"[A-Z]{2}\\\\d{2}[A-Z0-9]{11,30}\"\r\n}', 1, '2026-02-21 12:29:11'),
(226, 'SY', 'Syrian Arab Republic', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(227, 'TW', 'Taiwan, Province of China', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(228, 'TJ', 'Tajikistan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(229, 'TZ', 'Tanzania, United Republic of', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(230, 'TH', 'Thailand', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(231, 'TL', 'Timor-Leste', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(232, 'TG', 'Togo', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(233, 'TK', 'Tokelau', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(234, 'TO', 'Tonga', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(235, 'TT', 'Trinidad and Tobago', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(236, 'TN', 'Tunisia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(237, 'TR', 'Turkey', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(238, 'TM', 'Turkmenistan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(239, 'TC', 'Turks and Caicos Islands', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(240, 'TV', 'Tuvalu', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(241, 'UG', 'Uganda', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(242, 'UA', 'Ukraine', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(243, 'AE', 'United Arab Emirates', 'AED', 'د.إ', '+971', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^AE\\\\d{21}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 23,\r\n    \"account_number_format\": \"\\\\d{8,23}\"\r\n}', 1, '2026-02-21 12:29:11'),
(244, 'GB', 'United Kingdom', 'GBP', '£', '+44', '{\r\n    \"routing_required\": false,\r\n    \"routing_label\": \"Sort Code\",\r\n    \"routing_format\": \"\\\\d{6}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Sort Code\",\r\n    \"branch_code_format\": \"\\\\d{6}\",\r\n    \"account_number_length\": 14,\r\n    \"account_number_format\": \"\\\\d{8,14}\"\r\n}', 1, '2026-02-21 12:29:11'),
(245, 'US', 'United States', 'USD', '$', '+1', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Routing Number (ABA)\",\r\n    \"routing_format\": \"\\\\d{9}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 17,\r\n    \"account_number_format\": \"\\\\d{9,17}\"\r\n}', 1, '2026-02-21 12:29:11'),
(246, 'UM', 'United States Minor Outlying Islands', 'USD', '$', '+1', NULL, 1, '2026-02-21 12:29:11'),
(247, 'UY', 'Uruguay', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(248, 'UZ', 'Uzbekistan', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(249, 'VU', 'Vanuatu', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(250, 'VE', 'Venezuela', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(251, 'VN', 'Viet Nam', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(252, 'VG', 'Virgin Islands, British', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(253, 'VI', 'Virgin Islands, U.S.', 'USD', '$', '+1', NULL, 1, '2026-02-21 12:29:11'),
(254, 'WF', 'Wallis and Futuna', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(255, 'EH', 'Western Sahara', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(256, 'YE', 'Yemen', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(257, 'ZM', 'Zambia', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(258, 'ZW', 'Zimbabwe', 'USD', '$', NULL, NULL, 1, '2026-02-21 12:29:11'),
(259, 'US', 'United States', 'USD', '$', '+1', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Routing Number (ABA)\",\r\n    \"routing_format\": \"\\\\d{9}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 17,\r\n    \"account_number_format\": \"\\\\d{9,17}\"\r\n}', 1, '2026-02-23 15:35:20'),
(260, 'GB', 'United Kingdom', 'GBP', '£', '+44', '{\r\n    \"routing_required\": false,\r\n    \"routing_label\": \"Sort Code\",\r\n    \"routing_format\": \"\\\\d{6}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Sort Code\",\r\n    \"branch_code_format\": \"\\\\d{6}\",\r\n    \"account_number_length\": 14,\r\n    \"account_number_format\": \"\\\\d{8,14}\"\r\n}', 1, '2026-02-23 15:35:20'),
(261, 'PK', 'Pakistan', 'PKR', '₨', '+92', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Bank Code\",\r\n    \"branch_code_format\": \"\\\\d{4}\",\r\n    \"account_number_length\": 24,\r\n    \"account_number_format\": \"\\\\d{10,24}\"\r\n}', 1, '2026-02-23 15:35:20'),
(262, 'IN', 'India', 'INR', '₹', '+91', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": true,\r\n    \"ifsc_format\": \"^[A-Z]{4}0[A-Z0-9]{6}$\",\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 18,\r\n    \"account_number_format\": \"\\\\d{9,18}\"\r\n}', 1, '2026-02-23 15:35:20'),
(263, 'AE', 'UAE', 'AED', 'د.إ', '+971', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^AE\\\\d{21}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 23,\r\n    \"account_number_format\": \"\\\\d{8,23}\"\r\n}', 1, '2026-02-23 15:35:20'),
(264, 'SA', 'Saudi Arabia', 'SAR', '﷼', '+966', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^SA\\\\d{22}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 24,\r\n    \"account_number_format\": \"\\\\d{8,24}\"\r\n}', 1, '2026-02-23 15:35:20'),
(265, 'CA', 'Canada', 'CAD', 'C$', '+1', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Transit Number\",\r\n    \"routing_format\": \"\\\\d{5}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Institution Number\",\r\n    \"branch_code_format\": \"\\\\d{3}\",\r\n    \"account_number_length\": 12,\r\n    \"account_number_format\": \"\\\\d{7,12}\"\r\n}', 1, '2026-02-23 15:35:20'),
(266, 'AU', 'Australia', 'AUD', 'A$', '+61', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"BSB Code\",\r\n    \"routing_format\": \"\\\\d{6}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 9,\r\n    \"account_number_format\": \"\\\\d{6,9}\"\r\n}', 1, '2026-02-23 15:35:20'),
(267, 'DE', 'Germany', 'EUR', '€', '+49', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^DE\\\\d{20}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Bankleitzahl\",\r\n    \"branch_code_format\": \"\\\\d{8}\",\r\n    \"account_number_length\": 22,\r\n    \"account_number_format\": \"\\\\d{10}\",\r\n    \"notes\": \"German bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59'),
(268, 'FR', 'France', 'EUR', '€', '+33', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^FR\\\\d{22}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Code Banque\",\r\n    \"branch_code_format\": \"\\\\d{5}\",\r\n    \"account_number_length\": 27,\r\n    \"account_number_format\": \"\\\\d{11}\",\r\n    \"notes\": \"French bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59'),
(269, 'IT', 'Italy', 'EUR', '€', '+39', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^IT\\\\d{23}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"CIN\",\r\n    \"branch_code_format\": \"[A-Z0-9]{1}\",\r\n    \"account_number_length\": 27,\r\n    \"account_number_format\": \"\\\\d{12}\",\r\n    \"notes\": \"Italian bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59'),
(270, 'ES', 'Spain', 'EUR', '€', '+34', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^ES\\\\d{22}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Bank Code\",\r\n    \"branch_code_format\": \"\\\\d{4}\",\r\n    \"account_number_length\": 24,\r\n    \"account_number_format\": \"\\\\d{10}\",\r\n    \"notes\": \"Spanish bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59'),
(271, 'NL', 'Netherlands', 'EUR', '€', '+31', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^NL\\\\d{18}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 18,\r\n    \"account_number_format\": \"[A-Z]{4}\\\\d{10}\",\r\n    \"notes\": \"Dutch bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59'),
(272, 'BE', 'Belgium', 'EUR', '€', '+32', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^BE\\\\d{14}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 16,\r\n    \"account_number_format\": \"\\\\d{12}\",\r\n    \"notes\": \"Belgian bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59'),
(273, 'CH', 'Switzerland', 'CHF', 'Fr', '+41', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^CH\\\\d{19}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Bank Code\",\r\n    \"branch_code_format\": \"\\\\d{5}\",\r\n    \"account_number_length\": 21,\r\n    \"account_number_format\": \"\\\\d{12}\",\r\n    \"notes\": \"Swiss bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59'),
(274, 'SE', 'Sweden', 'SEK', 'kr', '+46', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^SE\\\\d{22}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Clearing Number\",\r\n    \"branch_code_format\": \"\\\\d{4,5}\",\r\n    \"account_number_length\": 24,\r\n    \"account_number_format\": \"\\\\d{7,14}\",\r\n    \"notes\": \"Swedish bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59'),
(275, 'NO', 'Norway', 'NOK', 'kr', '+47', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^NO\\\\d{13}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 15,\r\n    \"account_number_format\": \"\\\\d{11}\",\r\n    \"notes\": \"Norwegian bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59'),
(276, 'DK', 'Denmark', 'DKK', 'kr', '+45', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^DK\\\\d{16}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Reg. No.\",\r\n    \"branch_code_format\": \"\\\\d{4}\",\r\n    \"account_number_length\": 18,\r\n    \"account_number_format\": \"\\\\d{10}\",\r\n    \"notes\": \"Danish bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59'),
(277, 'JP', 'Japan', 'JPY', '¥', '+81', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Bank Code\",\r\n    \"routing_format\": \"\\\\d{4}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Branch Code\",\r\n    \"branch_code_format\": \"\\\\d{3}\",\r\n    \"account_number_length\": 7,\r\n    \"account_number_format\": \"\\\\d{7}\",\r\n    \"notes\": \"Japanese bank accounts use bank code + branch code + account number\"\r\n}', 1, '2026-02-23 15:36:59'),
(278, 'CN', 'China', 'CNY', '¥', '+86', '{\r\n    \"routing_required\": false,\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Bank Code\",\r\n    \"branch_code_format\": \"\\\\d{12}\",\r\n    \"account_number_length\": 19,\r\n    \"account_number_format\": \"\\\\d{16,19}\",\r\n    \"notes\": \"Chinese bank accounts use CNAPS code\"\r\n}', 1, '2026-02-23 15:36:59'),
(279, 'BR', 'Brazil', 'BRL', 'R$', '+55', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Bank Code\",\r\n    \"routing_format\": \"\\\\d{3}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^BR\\\\d{25}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": true,\r\n    \"branch_code_label\": \"Branch Code\",\r\n    \"branch_code_format\": \"\\\\d{4}\",\r\n    \"account_number_length\": 23,\r\n    \"account_number_format\": \"\\\\d{5,12}\",\r\n    \"notes\": \"Brazilian bank accounts use bank code + branch + account number\"\r\n}', 1, '2026-02-23 15:36:59'),
(280, 'ZA', 'South Africa', 'ZAR', 'R', '+27', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Branch Code\",\r\n    \"routing_format\": \"\\\\d{6}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 9,\r\n    \"account_number_format\": \"\\\\d{6,9}\",\r\n    \"notes\": \"South African bank accounts use branch code + account number\"\r\n}', 1, '2026-02-23 15:36:59'),
(281, 'NG', 'Nigeria', 'NGN', '₦', '+234', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Bank Code\",\r\n    \"routing_format\": \"\\\\d{3}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 10,\r\n    \"account_number_format\": \"\\\\d{10}\",\r\n    \"notes\": \"Nigerian bank accounts use NUBAN format (10 digits)\"\r\n}', 1, '2026-02-23 15:36:59'),
(282, 'RU', 'Russia', 'RUB', '₽', '+7', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"BIK Code\",\r\n    \"routing_format\": \"\\\\d{9}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": false,\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 20,\r\n    \"account_number_format\": \"\\\\d{20}\",\r\n    \"notes\": \"Russian bank accounts use BIK + account number\"\r\n}', 1, '2026-02-23 15:36:59'),
(283, 'TR', 'Turkey', 'TRY', '₺', '+90', '{\r\n    \"routing_required\": true,\r\n    \"routing_label\": \"Bank Code\",\r\n    \"routing_format\": \"\\\\d{5}\",\r\n    \"swift_required\": true,\r\n    \"iban_required\": true,\r\n    \"iban_format\": \"^TR\\\\d{24}$\",\r\n    \"ifsc_required\": false,\r\n    \"branch_code_required\": false,\r\n    \"account_number_length\": 26,\r\n    \"account_number_format\": \"\\\\d{16}\",\r\n    \"notes\": \"Turkish bank accounts use IBAN format\"\r\n}', 1, '2026-02-23 15:36:59');

-- --------------------------------------------------------

--
-- Table structure for table `country_bank_formats`
--

CREATE TABLE `country_bank_formats` (
  `id` int(11) NOT NULL,
  `country_code` varchar(2) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `routing_required` tinyint(1) DEFAULT 0,
  `routing_label` varchar(100) DEFAULT 'Routing Number',
  `routing_format` varchar(100) DEFAULT NULL,
  `swift_required` tinyint(1) DEFAULT 0,
  `iban_required` tinyint(1) DEFAULT 0,
  `iban_format` varchar(100) DEFAULT NULL,
  `ifsc_required` tinyint(1) DEFAULT 0,
  `branch_code_required` tinyint(1) DEFAULT 0,
  `account_number_length` int(11) DEFAULT NULL,
  `account_number_format` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `country_bank_formats`
--

INSERT INTO `country_bank_formats` (`id`, `country_code`, `bank_name`, `routing_required`, `routing_label`, `routing_format`, `swift_required`, `iban_required`, `iban_format`, `ifsc_required`, `branch_code_required`, `account_number_length`, `account_number_format`, `created_at`) VALUES
(1, 'US', 'US Bank', 1, 'Routing Number (ABA)', NULL, 1, 0, NULL, 0, 0, 17, NULL, '2026-02-23 15:35:21'),
(2, 'GB', 'UK Bank', 0, 'Sort Code', NULL, 1, 1, NULL, 0, 1, 14, NULL, '2026-02-23 15:35:21'),
(3, 'PK', 'Pakistan Bank', 0, 'Bank Code', NULL, 1, 0, NULL, 0, 1, 24, NULL, '2026-02-23 15:35:21'),
(4, 'IN', 'Indian Bank', 0, 'IFSC Code', NULL, 0, 0, NULL, 1, 1, 18, NULL, '2026-02-23 15:35:21'),
(5, 'AE', 'UAE Bank', 0, 'IBAN', NULL, 1, 1, NULL, 0, 0, 23, NULL, '2026-02-23 15:35:21'),
(6, 'SA', 'Saudi Bank', 0, 'IBAN', NULL, 1, 1, NULL, 0, 0, 24, NULL, '2026-02-23 15:35:21'),
(7, 'CA', 'Canadian Bank', 1, 'Transit Number', NULL, 1, 0, NULL, 0, 1, 12, NULL, '2026-02-23 15:35:21'),
(8, 'AU', 'Australian Bank', 1, 'BSB Code', NULL, 1, 0, NULL, 0, 1, 9, NULL, '2026-02-23 15:35:21');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `valid_from` datetime DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `usage_limit` int(11) DEFAULT 1,
  `times_used` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_fields`
--

CREATE TABLE `custom_fields` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `field_key` varchar(100) NOT NULL,
  `field_type` varchar(50) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `default_value` text DEFAULT NULL,
  `options` text DEFAULT NULL,
  `validation_rules` varchar(255) DEFAULT NULL,
  `help_text` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_required` tinyint(1) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `css_class` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deleted_products`
--

CREATE TABLE `deleted_products` (
  `id` int(11) NOT NULL,
  `original_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `sales_count` int(11) DEFAULT 0,
  `approved_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `low_stock` tinyint(1) DEFAULT 0,
  `out_of_stock` tinyint(1) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `review_count` int(11) DEFAULT 0,
  `five_star_count` int(11) DEFAULT 0,
  `four_star_count` int(11) DEFAULT 0,
  `three_star_count` int(11) DEFAULT 0,
  `two_star_count` int(11) DEFAULT 0,
  `one_star_count` int(11) DEFAULT 0,
  `delete_reason` varchar(255) DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT current_timestamp(),
  `original_created_at` timestamp NULL DEFAULT NULL,
  `original_updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deleted_reviews`
--

CREATE TABLE `deleted_reviews` (
  `id` int(11) NOT NULL,
  `original_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `delete_reason` varchar(255) DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `template_key` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `variables` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `helpful_links` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `import_export_logs`
--

CREATE TABLE `import_export_logs` (
  `id` int(11) NOT NULL,
  `type` enum('import','export') NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `settings_count` int(11) DEFAULT NULL,
  `import_mode` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 10.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `balance_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid','overdue','refunded') DEFAULT 'unpaid',
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('draft','sent','viewed','approved','rejected','cancelled') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `viewed_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_payments`
--

CREATE TABLE `invoice_payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_settings`
--

CREATE TABLE `invoice_settings` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `terms` text DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `bank_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_templates`
--

CREATE TABLE `invoice_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `template_key` varchar(50) NOT NULL,
  `html_content` text NOT NULL,
  `css_styles` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `attempted_at` datetime DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip_address`, `username`, `attempted_at`, `success`) VALUES
(1, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-21 19:00:28', 1),
(2, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-21 19:18:37', 1),
(3, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-22 12:41:43', 1),
(4, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-22 14:03:02', 1),
(5, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-22 16:45:15', 1),
(6, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-22 18:03:01', 1),
(7, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-22 19:47:40', 1),
(8, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-22 20:07:10', 1),
(9, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-22 20:24:11', 1),
(10, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-23 14:13:00', 1),
(11, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-23 14:43:23', 1),
(12, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-23 16:46:01', 1),
(13, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-23 17:24:29', 1),
(14, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-23 17:28:10', 1),
(15, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-23 19:26:09', 1),
(16, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-23 20:15:39', 1),
(17, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-24 13:51:16', 1),
(18, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-24 19:12:43', 1),
(19, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 12:18:34', 1),
(20, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 13:50:36', 1),
(21, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 14:07:24', 0),
(22, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 14:07:29', 0),
(23, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 14:07:37', 0),
(24, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 14:07:46', 0),
(25, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 14:10:31', 0),
(26, '::1', '', '2026-02-25 14:10:36', 0),
(27, '::1', '', '2026-02-25 14:13:01', 0),
(28, '::1', '', '2026-02-25 14:13:10', 0),
(29, '::1', '', '2026-02-25 14:13:33', 0),
(30, '::1', '', '2026-02-25 14:13:50', 0),
(31, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 14:38:10', 0),
(32, '::1', 'moazzams030@gmail.com', '2026-02-25 14:38:25', 1),
(33, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 14:38:51', 0),
(34, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 14:39:03', 0),
(35, '::1', 'moazzams030@gmail.com', '2026-02-25 14:41:39', 1),
(36, '::1', 'moazzams030@gmail.com', '2026-02-25 14:41:56', 1),
(37, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 14:42:52', 0),
(38, '::1', 'moazzams030@gmail.com', '2026-02-25 14:43:02', 1),
(39, '::1', 'moazzams030@gmail.com', '2026-02-25 15:49:37', 1),
(40, '::1', 'moazzams030@gmail.com', '2026-02-25 16:44:00', 1),
(41, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 16:45:38', 0),
(42, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 16:45:46', 0),
(43, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 16:46:12', 0),
(44, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 16:46:17', 1),
(45, '::1', 'moazzams030@gmail.com', '2026-02-25 17:17:44', 1),
(46, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 17:56:42', 1),
(47, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 19:12:51', 1),
(48, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-25 20:13:47', 1),
(49, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-26 12:37:19', 1),
(50, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-26 14:05:00', 1),
(51, '::1', 'moazzams030@gmail.com', '2026-02-26 14:30:06', 1),
(52, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-26 15:10:49', 1),
(53, '::1', 'moazzams030@gmail.com', '2026-02-26 15:41:18', 1),
(54, '::1', 'moazzams030@gmail.com', '2026-02-26 17:39:19', 1),
(55, '::1', 'moazzams030@gmail.com', '2026-02-26 19:07:38', 1),
(56, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-26 19:08:21', 1),
(57, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-27 11:13:27', 1),
(58, '::1', 'moazzams030@gmail.com', '2026-02-27 11:13:40', 1),
(59, '::1', 'moazzamsshaikhs@gmail.com', '2026-02-27 12:10:07', 1),
(60, '::1', 'moazzams030@gmail.com', '2026-02-27 12:10:42', 1),
(61, '::1', 'moazzamsshaikhs@gmail.com', '2026-03-02 18:13:53', 1),
(62, '::1', 'moazzams030@gmail.com', '2026-03-02 18:14:53', 1),
(63, '::1', 'moazzams030@gmail.com', '2026-03-05 17:48:40', 1),
(64, '::1', 'moazzams030@gmail.com', '2026-03-05 20:41:00', 1),
(65, '::1', 'moazzams030@gmail.com', '2026-03-06 14:54:40', 1),
(66, '::1', 'Shikhs@gmail.com', '2026-03-06 17:56:25', 1),
(67, '::1', 'moazzams030@gmail.com', '2026-03-06 18:19:42', 1),
(68, '::1', 'Shikhs@gmail.com', '2026-03-06 19:29:08', 1),
(69, '::1', 'moazzams030@gmail.com', '2026-03-06 19:33:45', 1),
(70, '::1', 'moazzams030@gmail.com', '2026-03-06 20:13:55', 1),
(71, '::1', 'moazzams030@gmail.com', '2026-03-07 17:07:50', 1),
(72, '::1', 'Shikhs@gmail.com', '2026-03-07 17:08:31', 1),
(73, '::1', 'Shikhs@gmail.com', '2026-03-07 17:30:03', 1),
(74, '::1', 'Shikhs@gmail.com', '2026-03-07 19:18:02', 1),
(75, '::1', 'Shikhs@gmail.com', '2026-03-08 12:27:17', 1),
(76, '::1', 'Shikhs@gmail.com', '2026-03-08 14:45:00', 1),
(77, '::1', 'Shikhs@gmail.com', '2026-03-08 17:00:01', 1),
(78, '::1', 'Shikhs@gmail.com', '2026-03-08 19:17:14', 1),
(79, '::1', 'Shikhs@gmail.com', '2026-03-12 13:37:25', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'Payment Method Verified', 'Your Paypal account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:47:02'),
(2, 1, 'Payment Method Verified', 'Your Paypal account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:47:35'),
(3, 1, 'Payment Method Verified', 'Your Paypal account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:48:28'),
(4, 1, 'Payment Method Verified', 'Your Paypal account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:48:56'),
(5, 1, 'Payment Method Verified', 'Your Paypal account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:49:02'),
(6, 1, 'Payment Method Verified', 'Your Paypal account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:49:10'),
(7, 1, 'Payment Method Verified', 'Your Bank account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:49:20'),
(8, 1, 'Payment Method Verified', 'Your Bank account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:49:28'),
(9, 1, 'Payment Method Verified', 'Your Bank account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:50:28'),
(10, 1, 'Payment Method Verified', 'Your Bank account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:51:28'),
(11, 1, 'Payment Method Verified', 'Your Bank account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:52:28'),
(12, 1, 'Payment Method Verified', 'Your Bank account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:53:28'),
(13, 1, 'Payment Method Verified', 'Your Bank account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:54:03'),
(14, 1, 'Payment Method Verified', 'Your Bank account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:54:42'),
(15, 1, 'Payment Method Verified', 'Your Stripe account has been verified. You can now withdraw funds.', 'success', 0, '2026-02-26 09:55:00'),
(16, 1, 'Document Approved', '✅ Your <strong>Id proof</strong> has been approved and verified.', 'success', 0, '2026-03-06 09:56:48'),
(17, 1, 'Document Approved', '✅ Your <strong>Id proof</strong> has been approved and verified.', 'success', 0, '2026-03-06 09:58:18'),
(18, 2, 'New Vendor Registration', 'New vendor moa has registered and is waiting for approval.', 'info', 0, '2026-03-09 10:51:45');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `payment_method` enum('cod','card','paypal','bank_transfer','easypaisa','jazzcash') DEFAULT 'cod',
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `shipping_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `shipping_method` varchar(100) DEFAULT NULL,
  `shipping_carrier_id` int(11) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivered_date` datetime DEFAULT NULL,
  `cancelled_date` datetime DEFAULT NULL,
  `estimated_delivery` date DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `is_gift` tinyint(1) DEFAULT 0,
  `gift_message` text DEFAULT NULL,
  `customer_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `after_order_status_update` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    -- When order status changes to 'delivered'
    IF NEW.status = 'delivered' AND OLD.status != 'delivered' THEN
        -- Process payment to vendor through admin system
        -- This will be handled by application logic, not directly in SQL
        -- But we can log that order is ready for payment
        
        INSERT INTO order_status_history 
        (order_id, status, notes, created_at)
        VALUES (NEW.id, 'ready_for_payment', 'Order delivered - ready for vendor payment', NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `order_items`
--
DELIMITER $$
CREATE TRIGGER `after_order_item_insert` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    DECLARE v_commission_rate DECIMAL(5,2);
    DECLARE v_vendor_id INT;
    DECLARE v_commission_amount DECIMAL(10,2);
    DECLARE v_admin_account_id INT;
    DECLARE v_category_id INT;
    
    -- Get vendor_id and category_id
    SELECT p.vendor_id, p.category_id INTO v_vendor_id, v_category_id
    FROM products p
    WHERE p.id = NEW.product_id;
    
    -- Get commission rate - first check vendor_category_commissions, then categories default
    SELECT COALESCE(
        (SELECT commission_rate FROM vendor_category_commissions 
         WHERE vendor_id = v_vendor_id AND category_id = v_category_id),
        (SELECT commission_rate FROM categories WHERE id = v_category_id),
        10.00
    ) INTO v_commission_rate;
    
    -- Calculate commission
    SET v_commission_amount = (NEW.unit_price * NEW.quantity * v_commission_rate / 100);
    
    -- Insert into vendor_commissions
    INSERT INTO vendor_commissions 
    (vendor_id, product_id, order_id, commission_rate, product_price, commission_amount, status)
    VALUES (v_vendor_id, NEW.product_id, NEW.order_id, v_commission_rate, NEW.unit_price, v_commission_amount, 'pending');
    
    -- Get last insert ID
    SET @last_commission_id = LAST_INSERT_ID();
    
    -- Get default admin account for this payment type
    SELECT id INTO v_admin_account_id FROM admin_accounts WHERE is_default = 1 AND is_active = 1 LIMIT 1;
    
    -- Insert into admin_commission_collections
    INSERT INTO admin_commission_collections 
    (vendor_commission_id, vendor_id, order_id, product_id, commission_amount, admin_account_id)
    VALUES (@last_commission_id, v_vendor_id, NEW.order_id, NEW.product_id, v_commission_amount, v_admin_account_id);
    
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_notes`
--

CREATE TABLE `order_notes` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `note_type` enum('internal','customer') DEFAULT 'internal',
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled','refunded') NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verification`
--

CREATE TABLE `otp_verification` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `otp_code` varchar(10) NOT NULL,
  `otp_type` enum('email','phone','reset') NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_verification`
--

INSERT INTO `otp_verification` (`id`, `user_id`, `otp_code`, `otp_type`, `expires_at`, `verified`, `created_at`) VALUES
(1, 1, '250963', 'email', '2026-02-21 17:40:54', 1, '2026-02-21 12:30:54'),
(2, 0, '993066', 'email', '2026-02-25 14:22:20', 1, '2026-02-25 09:12:20'),
(3, 5, '736568', 'email', '2026-03-06 16:19:46', 1, '2026-03-06 11:09:47'),
(4, 6, '700492', 'email', '2026-03-09 16:01:42', 1, '2026-03-09 10:51:42');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_audit`
--

CREATE TABLE `payment_audit` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateways`
--

CREATE TABLE `payment_gateways` (
  `id` int(11) NOT NULL,
  `gateway_code` varchar(50) NOT NULL,
  `gateway_name` varchar(100) NOT NULL,
  `gateway_icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `test_mode` tinyint(1) DEFAULT 1,
  `api_key` text DEFAULT NULL,
  `api_secret` text DEFAULT NULL,
  `api_url` varchar(255) DEFAULT NULL,
  `merchant_id` varchar(100) DEFAULT NULL,
  `account_email` varchar(255) DEFAULT NULL,
  `additional_settings` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_gateways`
--

INSERT INTO `payment_gateways` (`id`, `gateway_code`, `gateway_name`, `gateway_icon`, `is_active`, `is_default`, `sort_order`, `test_mode`, `api_key`, `api_secret`, `api_url`, `merchant_id`, `account_email`, `additional_settings`, `created_at`, `updated_at`) VALUES
(1, 'bank', 'Bank Transfer', 'fas fa-university', 1, 0, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-08 07:28:46', '2026-03-08 07:28:46'),
(2, 'paypal', 'PayPal', 'fab fa-paypal', 1, 0, 2, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-08 07:28:46', '2026-03-08 07:28:46'),
(3, 'stripe', 'Stripe', 'fab fa-stripe', 1, 0, 3, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-08 07:28:46', '2026-03-08 07:28:46'),
(4, 'easypaisa', 'Easypaisa', 'fas fa-mobile-alt', 1, 0, 4, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-08 07:28:46', '2026-03-08 07:28:46'),
(5, 'jazzcash', 'JazzCash', 'fas fa-mobile-alt', 1, 0, 5, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-08 07:28:46', '2026-03-08 07:28:46'),
(6, 'cod', 'Cash on Delivery', 'fas fa-money-bill-wave', 1, 0, 6, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-08 07:28:46', '2026-03-08 07:28:46');

-- --------------------------------------------------------

--
-- Table structure for table `payment_intents`
--

CREATE TABLE `payment_intents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `intent_id` varchar(255) NOT NULL,
  `gateway` enum('stripe','paypal','bank_transfer','easypaisa','jazzcash') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `plan_id` int(11) DEFAULT NULL,
  `plan_slug` varchar(100) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
  `metadata` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_proofs`
--

CREATE TABLE `payment_proofs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `account_holder` varchar(255) NOT NULL,
  `transfer_date` date NOT NULL,
  `transfer_amount` decimal(10,2) NOT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `slip_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `transaction_type` enum('subscription','one_time','refund','credit') NOT NULL,
  `gateway` varchar(50) NOT NULL,
  `gateway_transaction_id` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `status` enum('pending','completed','failed','refunded','cancelled') DEFAULT 'pending',
  `description` text DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `approved_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `is_featured` tinyint(1) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `sales_count` int(11) DEFAULT 0,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `images` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `low_stock` tinyint(1) DEFAULT 0,
  `out_of_stock` tinyint(1) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `review_count` int(11) DEFAULT 0,
  `five_star_count` int(11) DEFAULT 0,
  `four_star_count` int(11) DEFAULT 0,
  `three_star_count` int(11) DEFAULT 0,
  `two_star_count` int(11) DEFAULT 0,
  `one_star_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `vendor_id`, `approved_status`, `is_featured`, `views`, `sales_count`, `name`, `description`, `price`, `old_price`, `image`, `images`, `category`, `category_id`, `stock`, `featured`, `created_at`, `updated_at`, `low_stock`, `out_of_stock`, `average_rating`, `review_count`, `five_star_count`, `four_star_count`, `three_star_count`, `two_star_count`, `one_star_count`) VALUES
(1, 1, 'approved', 0, 38, 0, 'jhasfja  yuap', 'smdmdvfk jajgua gu  ausviaviava jvaj haaa gavu afa', 12.00, 15.00, 'product_69a05896b9e104.54893707_1772116118.png', NULL, 'electronics', NULL, 11, 1, '2026-02-21 14:01:23', '2026-03-06 15:22:12', 0, 0, 0.00, 0, 0, 0, 0, 0, 0),
(2, 1, 'approved', 0, 6, 0, 'Test Out of Stock Product', 'aalblag agsauuagpiuagg;uaug', 150.00, 180.00, 'product_1772114944_f0d2260e.jpg', NULL, 'baby', NULL, 10, 1, '2026-02-26 14:09:04', '2026-03-07 15:29:25', 0, 0, 0.00, 0, 0, 0, 0, 0, 0),
(4, 1, 'approved', 0, 34, 0, 'Test Out of Stock Product', 'hjagal;najip iaboi', 15.00, 20.00, 'product_1772114944_f0d2260e.jpg', NULL, 'books', NULL, 12, 1, '2026-02-26 14:20:31', '2026-03-08 14:36:51', 0, 0, 0.00, 0, 0, 0, 0, 0, 0);

--
-- Triggers `products`
--
DELIMITER $$
CREATE TRIGGER `update_stock_flags_on_insert` BEFORE INSERT ON `products` FOR EACH ROW BEGIN
    SET NEW.low_stock = (NEW.stock > 0 AND NEW.stock < 10);
    SET NEW.out_of_stock = (NEW.stock = 0);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_stock_flags_on_update` BEFORE UPDATE ON `products` FOR EACH ROW BEGIN
    SET NEW.low_stock = (NEW.stock > 0 AND NEW.stock < 10);
    SET NEW.out_of_stock = (NEW.stock = 0);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_vendor_products_delete` AFTER DELETE ON `products` FOR EACH ROW BEGIN
    UPDATE users 
    SET total_products = (
        SELECT COUNT(*) FROM products WHERE vendor_id = OLD.vendor_id
    )
    WHERE id = OLD.vendor_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_vendor_products_insert` AFTER INSERT ON `products` FOR EACH ROW BEGIN
    UPDATE users 
    SET total_products = (
        SELECT COUNT(*) FROM products WHERE vendor_id = NEW.vendor_id
    )
    WHERE id = NEW.vendor_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `review_text` text DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `review_likes`
--

CREATE TABLE `review_likes` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `review_reports`
--

CREATE TABLE `review_reports` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `additional_info` text DEFAULT NULL,
  `status` enum('pending','reviewed','resolved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `group` varchar(50) DEFAULT 'general',
  `sort_order` int(11) DEFAULT 0,
  `options` text DEFAULT NULL,
  `validation_rules` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `is_required` tinyint(1) DEFAULT 0,
  `help_text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `created_at`, `updated_at`, `group`, `sort_order`, `options`, `validation_rules`, `is_public`, `is_required`, `help_text`) VALUES
(6, 'smtp_enabled', '0', 'boolean', 'email', 'Enable SMTP for sending emails', '2026-03-05 14:46:39', '2026-03-05 14:46:39', 'general', 0, NULL, NULL, 0, 0, NULL),
(7, 'smtp_host', 'smtp.gmail.com', 'text', 'email', 'SMTP server hostname', '2026-03-05 14:46:39', '2026-03-05 14:46:39', 'general', 0, NULL, NULL, 0, 0, NULL),
(8, 'smtp_port', '587', 'number', 'email', 'SMTP server port', '2026-03-05 14:46:39', '2026-03-05 14:46:39', 'general', 0, NULL, NULL, 0, 0, NULL),
(9, 'smtp_username', '', 'text', 'email', 'SMTP username', '2026-03-05 14:46:39', '2026-03-05 14:46:39', 'general', 0, NULL, NULL, 0, 0, NULL),
(10, 'smtp_password', '', 'text', 'email', 'SMTP password', '2026-03-05 14:46:39', '2026-03-05 14:46:39', 'general', 0, NULL, NULL, 0, 0, NULL),
(11, 'smtp_secure', 'tls', 'text', 'email', 'SMTP encryption (tls/ssl)', '2026-03-05 14:46:39', '2026-03-05 14:46:39', 'general', 0, NULL, NULL, 0, 0, NULL),
(12, 'from_email', 'noreply@ecommerce.com', '', 'email', 'From email address', '2026-03-05 14:46:39', '2026-03-05 14:46:39', 'general', 0, NULL, NULL, 0, 0, NULL),
(13, 'from_name', 'E-Commerce Store', 'text', 'email', 'From name', '2026-03-05 14:46:39', '2026-03-05 14:46:39', 'general', 0, NULL, NULL, 0, 0, NULL),
(14, 'admin_email', 'admin@ecommerce.com', '', 'email', 'Admin email address', '2026-03-05 14:46:39', '2026-03-05 14:46:39', 'general', 0, NULL, NULL, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings_groups`
--

CREATE TABLE `settings_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings_history`
--

CREATE TABLE `settings_history` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipping_carriers`
--

CREATE TABLE `shipping_carriers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `tracking_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `is_recurring` tinyint(1) DEFAULT 1,
  `stripe_price_id` varchar(255) DEFAULT NULL,
  `paypal_plan_id` varchar(255) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `is_popular` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `category` enum('account','billing','orders','products','technical','other') DEFAULT 'other',
  `message` text NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `assigned_to` int(11) DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_updates`
--

CREATE TABLE `system_updates` (
  `id` int(11) NOT NULL,
  `version` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `release_date` date NOT NULL,
  `changelog` text DEFAULT NULL,
  `is_applied` tinyint(1) DEFAULT 0,
  `applied_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_form_requests`
--

CREATE TABLE `tax_form_requests` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `tax_year` int(11) NOT NULL,
  `form_type` varchar(50) NOT NULL,
  `delivery_method` enum('email','mail') DEFAULT 'email',
  `status` enum('pending','processing','completed','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tax_form_requests`
--

INSERT INTO `tax_form_requests` (`id`, `vendor_id`, `tax_year`, `form_type`, `delivery_method`, `status`, `notes`, `processed_by`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 1, 2026, '1099nec', 'mail', 'pending', NULL, NULL, NULL, '2026-02-22 09:14:24', '2026-02-22 09:14:24'),
(2, 1, 2026, '1099nec', 'email', 'pending', NULL, NULL, NULL, '2026-02-22 09:21:32', '2026-02-22 09:21:32');

-- --------------------------------------------------------

--
-- Table structure for table `upgrade_requests`
--

CREATE TABLE `upgrade_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `plan_slug` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `transaction_id` varchar(255) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT 'default.png',
  `user_type` enum('admin','user','vendor') DEFAULT 'user',
  `vendor_status` enum('pending','approved','rejected','suspended') DEFAULT 'pending',
  `vendor_verified` tinyint(1) DEFAULT 0,
  `vendor_since` date DEFAULT NULL,
  `vendor_bio` text DEFAULT NULL,
  `vendor_category` int(11) DEFAULT NULL,
  `vendor_rating` decimal(3,2) DEFAULT 0.00,
  `total_products` int(11) DEFAULT 0,
  `total_sales` int(11) DEFAULT 0,
  `email_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0,
  `subscription_plan` enum('free','premium','business') DEFAULT 'free',
  `subscription_expiry` date DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT 'other',
  `date_of_birth` date DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `social_facebook` varchar(255) DEFAULT NULL,
  `social_twitter` varchar(255) DEFAULT NULL,
  `social_instagram` varchar(255) DEFAULT NULL,
  `social_linkedin` varchar(255) DEFAULT NULL,
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `login_count` int(11) DEFAULT 0,
  `account_status` enum('active','suspended','deactivated') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `store_banner` varchar(255) DEFAULT NULL,
  `store_logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `phone`, `full_name`, `address`, `billing_address`, `tax_id`, `profile_pic`, `user_type`, `vendor_status`, `vendor_verified`, `vendor_since`, `vendor_bio`, `vendor_category`, `vendor_rating`, `total_products`, `total_sales`, `email_verified`, `phone_verified`, `subscription_plan`, `subscription_expiry`, `bio`, `gender`, `date_of_birth`, `country`, `city`, `postal_code`, `social_facebook`, `social_twitter`, `social_instagram`, `social_linkedin`, `otp_code`, `otp_expiry`, `last_login`, `login_count`, `account_status`, `created_at`, `updated_at`, `created_by`, `store_banner`, `store_logo`) VALUES
(1, 'Moazzam', 'moazzamsshaikhs@gmail.com', '$2y$10$6G5VJ8CkyyysiBLXVRVHPOlM/cZJa/Rk0FLU5ztmlsXuJRRH5tPa6', '03132842740', 'Moazzam shaikh', 'new anjam colony baldia karachi pakistan no.4', NULL, '123-45-6789', 'vendor_1_1771684353.png', 'vendor', 'approved', 1, NULL, 'jalhfoabfoafbo;qbb', NULL, 2.00, 3, 0, 1, 1, 'free', NULL, '', 'male', NULL, 'PK', 'KARACHI', '75400', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-02 18:13:53', 31, 'active', '2026-02-21 12:30:54', '2026-03-02 13:25:03', NULL, NULL, NULL),
(2, 'system administrator', 'moazzams030@gmail.com', '$2y$10$NevC0vTk3j9O/Qi3oOR3weJhFcygdm7YTzDpacDdIK3vZsvWYiR.S', '03132842741', 'Moazzam shaikhs', NULL, NULL, NULL, 'default.png', 'admin', 'approved', 1, NULL, 'lajsshfoa', 0, 0.00, 0, 0, 1, 0, 'business', NULL, NULL, 'other', NULL, 'PK', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-07 17:07:50', 21, 'active', '2026-02-25 09:12:20', '2026-03-07 12:07:50', NULL, NULL, NULL),
(5, 'Shaikh', 'Shikhs@gmail.com', '$2y$10$zSjiVgPLopwxrscPBgAAS.aei0I6PkNFbQa4Tue6Uj/iCwiDEiXSy', '03132842742', 'Shaikhs', NULL, NULL, NULL, 'default.png', 'user', NULL, 0, NULL, NULL, NULL, 0.00, 0, 0, 1, 0, 'free', NULL, NULL, 'other', NULL, 'PK', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-12 13:37:25', 10, 'active', '2026-03-06 11:09:47', '2026-03-12 08:37:25', NULL, NULL, NULL),
(6, 'moa', 'moa@gmail.com', '$2y$10$qRXBYkZiZWHEvfyfQnEROemHXO5p/qaWTqtVvY7ON4kD.88eaaKa6', '03132842743', 'jfgu ahg8a', NULL, NULL, NULL, 'default.png', 'vendor', 'pending', 0, NULL, 'aua ahb8an uabb dav uab', 0, 0.00, 0, 0, 1, 0, 'free', NULL, NULL, 'other', NULL, 'AE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'active', '2026-03-09 10:51:42', '2026-03-09 10:52:15', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_activities`
--

CREATE TABLE `user_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_activities`
--

INSERT INTO `user_activities` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'registration', 'User registered as vendor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 12:30:54'),
(2, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 12:31:25'),
(3, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 12:32:20'),
(4, 1, 'profile_update', 'Updated vendor profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 12:32:45'),
(5, 1, 'profile_update', 'Updated vendor profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 12:33:03'),
(6, 1, 'profile_update', 'Updated vendor profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 12:33:12'),
(7, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:00:28'),
(8, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:00:28'),
(9, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:00:34'),
(10, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:00:36'),
(11, 1, 'product_add', 'Added new product: jhasfja  yuap (ID: 1)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:01:23'),
(12, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:02:18'),
(13, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:08:30'),
(14, 1, 'profile_update', 'Updated vendor profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:08:59'),
(15, 1, 'avatar_update', 'Updated profile picture', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:17:29'),
(16, 1, 'avatar_update', 'Updated profile picture', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:17:46'),
(17, 1, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:18:30'),
(18, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:18:37'),
(19, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:18:37'),
(20, 1, 'avatar_update', 'Updated profile picture', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:32:33'),
(21, 1, 'document_upload', 'Uploaded business_registration document', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 14:33:12'),
(22, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 15:05:35'),
(23, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 15:06:27'),
(24, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 15:06:44'),
(25, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 15:06:48'),
(26, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 07:41:43'),
(27, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 07:41:44'),
(28, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 09:03:02'),
(29, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 09:03:03'),
(30, 1, 'form_request', 'Requested 1099nec for 2026', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 09:14:24'),
(31, 1, 'tax_update', 'Updated tax information', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 09:20:28'),
(32, 1, 'document_upload', 'Uploaded tax document', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 09:21:06'),
(33, 1, 'form_request', 'Requested 1099nec for 2026', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 09:21:32'),
(34, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 09:41:26'),
(35, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 11:45:15'),
(36, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 11:45:15'),
(37, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 12:27:13'),
(38, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 12:27:27'),
(39, 1, 'performance_report_view', 'Viewed performance reports', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 12:28:32'),
(40, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 13:03:01'),
(41, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 13:03:01'),
(42, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 13:07:40'),
(43, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 13:15:16'),
(44, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 14:47:40'),
(45, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 14:47:40'),
(46, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 15:07:10'),
(47, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 15:07:10'),
(48, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 15:24:11'),
(49, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 15:24:11'),
(50, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:13:00'),
(51, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:13:01'),
(52, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:43:23'),
(53, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:43:23'),
(54, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:53:39'),
(55, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:58:47'),
(56, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 10:02:01'),
(57, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 11:46:01'),
(58, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 11:46:04'),
(59, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 11:48:57'),
(60, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 12:24:29'),
(61, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 12:24:29'),
(62, 1, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 12:24:39'),
(63, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 12:28:10'),
(64, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 12:28:10'),
(65, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:26:09'),
(66, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:26:10'),
(67, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:41:14'),
(68, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:41:46'),
(69, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:41:53'),
(70, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:42:01'),
(71, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:42:26'),
(72, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:59:21'),
(73, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-23 15:01:40'),
(74, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:04:59'),
(75, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:10:59'),
(76, 1, 'update_account', 'Updated savings account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:11:07'),
(77, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:11:20'),
(78, 1, 'update_account', 'Updated savings account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:11:29'),
(79, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:15:39'),
(80, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:15:40'),
(81, 1, 'view_account', 'Viewed bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:23:47'),
(82, 1, 'profile_update', 'Updated vendor profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:40:43'),
(83, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 08:51:16'),
(84, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 08:51:18'),
(85, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 08:55:29'),
(86, 1, 'update_bank', 'Updated bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 09:41:29'),
(87, 1, 'update_bank', 'Updated bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 09:42:34'),
(88, 1, 'update_bank', 'Updated bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 09:44:13'),
(89, 1, 'update_bank', 'Updated bank account #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 09:44:49'),
(90, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:12:43'),
(91, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:12:44'),
(92, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:12:50'),
(93, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:12:53'),
(94, 1, 'set_default', 'Set bank account #1 as default', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:22:15'),
(95, 1, 'profile_update', 'Updated vendor profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:32:51'),
(96, 1, 'profile_update', 'Updated vendor profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 14:57:04'),
(97, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 07:18:34'),
(98, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 07:18:35'),
(99, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 07:18:46'),
(100, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 08:50:36'),
(101, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 08:50:37'),
(102, 1, 'update_settings', 'Updated general store settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 08:59:17'),
(103, 1, 'update_settings', 'Updated general store settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 08:59:44'),
(104, 1, 'update_store', 'Updated store details', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:00:31'),
(105, 1, 'update_policies', 'Updated store policies', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:02:06'),
(106, 1, 'update_policies', 'Updated store policies', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:02:37'),
(107, 1, 'update_social', 'Updated social media links', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:06:57'),
(108, 1, 'deactivate_store', 'Deactivated store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:07:11'),
(109, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:07:24'),
(110, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:07:29'),
(111, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:07:37'),
(112, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:07:46'),
(113, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:10:31'),
(114, 0, 'registration', 'User registered as vendor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:12:20'),
(115, 0, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:13:01'),
(116, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:38:10'),
(117, 0, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:38:25'),
(118, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:38:51'),
(119, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:39:03'),
(120, 0, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:41:39'),
(121, 0, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:41:49'),
(122, 0, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:41:56'),
(123, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:42:52'),
(124, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:43:02'),
(125, 2, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:43:03'),
(126, 2, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:43:50'),
(127, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 10:49:37'),
(128, 2, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 10:49:38'),
(129, 2, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 10:54:02'),
(130, 2, 'update_settings', 'Updated general store settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:14:54'),
(131, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:44:00'),
(132, 2, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:44:00'),
(133, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:45:38'),
(134, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:45:46'),
(135, 0, 'failed_login', 'Failed login attempt for username: moazzamsshaikhs@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:46:12'),
(136, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:46:17'),
(137, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 11:46:17'),
(138, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:17:44'),
(139, 2, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:17:44'),
(140, 2, 'upload_logo', 'Uploaded new store logo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:21:47'),
(141, 2, 'upload_banner', 'Uploaded new store banner', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:22:22'),
(142, 2, 'delete_logo', 'Deleted store logo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:24:21'),
(143, 2, 'upload_logo', 'Uploaded new store logo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:24:39'),
(144, 2, 'delete_logo', 'Deleted store logo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:26:01'),
(145, 2, 'upload_logo', 'Uploaded new store logo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:26:18'),
(146, 2, 'delete_logo', 'Deleted store logo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:27:01'),
(147, 2, 'upload_logo', 'Uploaded new store logo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:27:16'),
(148, 2, 'delete_logo', 'Deleted store logo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:29:15'),
(149, 2, 'upload_logo', 'Uploaded new store logo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:29:30'),
(150, 2, 'upload_banner', 'Uploaded new store banner', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:30:46'),
(151, 2, 'upload_banner', 'Uploaded new store banner', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:31:11'),
(152, 2, 'upload_banner', 'Uploaded new store banner', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:32:16'),
(153, 2, 'update_store', 'Updated store details', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:33:00'),
(154, 2, 'export_data', 'Exported products data', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:33:26'),
(155, 2, 'export_data', 'Exported analytics data', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:34:57'),
(156, 2, 'regenerate_api', 'Regenerated API key', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:35:12'),
(157, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:56:42'),
(158, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:56:42'),
(159, 1, 'update_store', 'Updated store details', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 13:15:53'),
(160, 1, 'upload_logo', 'Uploaded new store logo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 13:31:51'),
(161, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:12:51'),
(162, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:12:51'),
(163, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:47:15'),
(164, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:50:02'),
(165, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:50:30'),
(166, 1, 'update_bank', 'Updated bank account #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:51:42'),
(167, 1, 'set_default', 'Set bank account #1 as default', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:55:51'),
(168, 1, 'set_default', 'Set bank account #2 as default', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:55:58'),
(169, 1, 'set_default', 'Set bank account #1 as default', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:56:12'),
(170, 1, 'delete', 'Deleted bank account #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:56:18'),
(171, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:56:51'),
(172, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 15:13:47'),
(173, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 15:13:47'),
(174, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 15:13:52'),
(175, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 15:13:55'),
(176, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 07:37:19'),
(177, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 07:37:20'),
(178, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 07:47:35'),
(179, 1, 'add_bank', 'Added bank account: Example Bank ending in 5665', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 07:53:01'),
(180, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 09:05:00'),
(181, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 09:05:00'),
(182, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 09:30:06'),
(183, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 10:10:49'),
(184, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 10:10:52'),
(185, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 10:41:18'),
(186, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 10:47:31'),
(187, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 11:10:57'),
(188, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 12:39:19'),
(189, 2, 'product_edit', 'Edited product: jhasfja  yuap (ID: 1)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 13:21:57'),
(190, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:07:38'),
(191, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:08:21'),
(192, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:08:22'),
(193, 1, 'product_add', 'Added new product: Test Out of Stock Product (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:09:04'),
(194, 2, 'product_approved', 'Approved product: Test Out of Stock Product (ID: 2)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:13:14'),
(195, 2, 'bulk_upload', 'Bulk product upload completed: 1 successful, 3 failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:14:12'),
(196, 1, 'product_add', 'Added new product: Test Out of Stock Product (ID: 4)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:20:31'),
(197, 2, 'product_approved', 'Approved product: Test Out of Stock Product (ID: 4)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:20:56'),
(198, 2, 'product_delete', 'Deleted product: iPhone 14 Pro (ID: 3)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:25:12'),
(199, 2, 'product_edit', 'Edited product: jhasfja  yuap (ID: 1)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:28:38'),
(200, 1, 'profile_update', 'Updated vendor profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 15:07:45'),
(201, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 06:13:27'),
(202, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 06:13:27'),
(203, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 06:13:40'),
(204, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 06:18:37'),
(205, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 06:20:46'),
(206, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 06:21:51'),
(207, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 06:22:22'),
(208, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 06:22:39'),
(209, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 06:30:49'),
(210, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 07:10:07'),
(211, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 07:10:07'),
(212, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 07:10:42'),
(213, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 13:13:53'),
(214, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 13:13:53'),
(215, 1, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 13:14:30'),
(216, 1, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 13:14:47'),
(217, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 13:14:53'),
(218, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 12:48:40'),
(219, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 15:41:00'),
(220, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 09:54:40'),
(221, 2, 'document_approved', 'Approved document ID: 2 for vendor: Moazzam shaikh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 09:56:48'),
(222, 2, 'document_approved', 'Approved document ID: 2 for vendor: Moazzam shaikh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 09:58:18'),
(223, 5, 'registration', 'User registered as user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:09:47'),
(224, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:10:10'),
(225, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:10:17'),
(226, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:11:37'),
(227, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:13:37'),
(228, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:13:47'),
(229, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:13:55'),
(230, 5, 'wishlist_view', 'Viewed wishlist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:13:57'),
(231, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:14:01'),
(232, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:14:04'),
(233, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:14:09'),
(234, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:14:54'),
(235, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:15:08'),
(236, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:17:02'),
(237, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:18:59'),
(238, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 11:19:39'),
(239, 5, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 12:56:25'),
(240, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 12:56:25'),
(241, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 12:56:34'),
(242, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:17:40'),
(243, 5, 'cart_add', 'Added product to cart: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:17:52'),
(244, 5, 'wishlist_view', 'Viewed wishlist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:18:02'),
(245, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:18:04'),
(246, 5, 'wishlist_view', 'Viewed wishlist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:18:14'),
(247, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:18:19'),
(248, 5, 'wishlist_add', 'Added product to wishlist ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:18:23'),
(249, 5, 'wishlist_view', 'Viewed wishlist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:18:27');
INSERT INTO `user_activities` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(250, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:18:32'),
(251, 5, 'wishlist_view', 'Viewed wishlist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:18:51'),
(252, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:18:54'),
(253, 5, 'cart_update', 'Updated cart quantity for product ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:19:07'),
(254, 5, 'cart_remove', 'Removed product from cart ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:19:08'),
(255, 5, 'cart_add', 'Added product to cart: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:19:11'),
(256, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:19:17'),
(257, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:19:42'),
(258, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:21:38'),
(259, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:21:59'),
(260, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:22:11'),
(261, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:22:14'),
(262, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:22:17'),
(263, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:22:25'),
(264, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:28:45'),
(265, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:29:01'),
(266, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 13:30:26'),
(267, 5, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:29:08'),
(268, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:29:08'),
(269, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:29:26'),
(270, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:29:32'),
(271, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:33:05'),
(272, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:33:27'),
(273, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:33:45'),
(274, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:37:25'),
(275, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:37:36'),
(276, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:37:38'),
(277, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:41:02'),
(278, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:41:09'),
(279, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:41:11'),
(280, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:41:16'),
(281, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:41:40'),
(282, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:43:06'),
(283, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:43:23'),
(284, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:44:53'),
(285, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:45:07'),
(286, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:46:32'),
(287, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:46:40'),
(288, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:46:49'),
(289, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:46:51'),
(290, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:47:12'),
(291, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:52:33'),
(292, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:53:38'),
(293, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:02:08'),
(294, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:02:48'),
(295, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:02:55'),
(296, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:04:47'),
(297, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:04:58'),
(298, 5, 'cart_add', 'Added product to cart: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:06:09'),
(299, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:06:15'),
(300, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:06:25'),
(301, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:06:36'),
(302, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:06:40'),
(303, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:08:46'),
(304, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:09:00'),
(305, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:11:02'),
(306, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:13:45'),
(307, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:13:55'),
(308, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:17:47'),
(309, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:17:58'),
(310, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:19:06'),
(311, 5, 'contact_vendor', 'Contacted vendor about product ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:19:06'),
(312, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:19:15'),
(313, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:21:24'),
(314, 5, 'product_view', 'Viewed product: jhasfja  yuap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:22:12'),
(315, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:22:39'),
(316, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:22:52'),
(317, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:23:00'),
(318, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:23:07'),
(319, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 15:24:33'),
(320, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:07:50'),
(321, 5, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:08:31'),
(322, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:08:31'),
(323, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:08:46'),
(324, 5, 'profile_access', 'Accessed profile page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:08:49'),
(325, 5, 'orders_view', 'Viewed orders list', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:08:52'),
(326, 5, 'wishlist_view', 'Viewed wishlist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:08:55'),
(327, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:09:00'),
(328, 5, 'settings_view', 'Viewed settings: general', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:09:11'),
(329, 5, 'settings_view', 'Viewed settings: security', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:09:13'),
(330, 5, 'settings_view', 'Viewed settings: notifications', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:09:15'),
(331, 5, 'settings_view', 'Viewed settings: billing', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:09:16'),
(332, 5, 'settings_view', 'Viewed settings: notifications', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:09:31'),
(333, 5, 'settings_view', 'Viewed settings: notifications', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:09:37'),
(334, 5, 'settings_view', 'Viewed settings: general', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:09:45'),
(335, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:10:01'),
(336, 5, 'profile_access', 'Accessed profile page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:10:03'),
(337, 5, 'orders_view', 'Viewed orders list', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:10:04'),
(338, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:10:14'),
(339, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 12:10:28'),
(340, 5, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:18:02'),
(341, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:18:02'),
(342, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:19:01'),
(343, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:19:08'),
(344, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:19:30'),
(345, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:20:06'),
(346, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:27:27'),
(347, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:27:49'),
(348, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:31:17'),
(349, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:32:23'),
(350, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:40:01'),
(351, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:40:11'),
(352, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:40:17'),
(353, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:48:53'),
(354, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:49:18'),
(355, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 15:07:52'),
(356, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 15:28:14'),
(357, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 15:28:33'),
(358, 5, 'wishlist_view', 'Viewed wishlist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 15:29:05'),
(359, 5, 'wishlist_view', 'Viewed wishlist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 15:29:15'),
(360, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 15:29:25'),
(361, 5, 'vendor_view', 'Viewed vendor store: Test Store', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 15:29:34'),
(362, 5, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 07:27:17'),
(363, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 07:27:17'),
(364, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 07:27:24'),
(365, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 07:27:30'),
(366, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 07:55:56'),
(367, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 07:56:38'),
(368, 5, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 09:45:00'),
(369, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 09:45:00'),
(370, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 09:45:34'),
(371, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 09:45:43'),
(372, 5, 'profile_access', 'Accessed profile page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 09:45:44'),
(373, 5, 'orders_view', 'Viewed orders list', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 09:45:46'),
(374, 5, 'wishlist_view', 'Viewed wishlist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 09:45:48'),
(375, 5, 'settings_view', 'Viewed settings: general', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 09:45:50'),
(376, 5, 'activity_view', 'Viewed activity log', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 09:45:54'),
(377, 5, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:00:01'),
(378, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:00:01'),
(379, 5, 'orders_view', 'Viewed orders list', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:00:07'),
(380, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:00:09'),
(381, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:00:14'),
(382, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:00:45'),
(383, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:00:54'),
(384, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:04:31'),
(385, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:04:32'),
(386, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:04:53'),
(387, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:05:01'),
(388, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:05:08'),
(389, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:08:03'),
(390, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:08:43'),
(391, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:20:22'),
(392, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:32:42'),
(393, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:32:48'),
(394, 5, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:17:13'),
(395, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:17:14'),
(396, 5, 'orders_view', 'Viewed orders list', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:17:21'),
(397, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:17:22'),
(398, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:17:29'),
(399, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:18:33'),
(400, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:18:59'),
(401, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:20:24'),
(402, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:20:31'),
(403, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:20:47'),
(404, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:24:06'),
(405, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:27:13'),
(406, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:27:19'),
(407, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:29:09'),
(408, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:33:10'),
(409, 5, 'product_view', 'Viewed product: Test Out of Stock Product', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:36:51'),
(410, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 15:17:33'),
(411, 6, 'registration', 'User registered as vendor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 10:51:45'),
(412, 6, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 10:52:15'),
(413, 6, 'dashboard_access', 'Accessed vendor dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 10:53:01'),
(414, 2, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 08:37:17'),
(415, 5, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 08:37:25'),
(416, 5, 'dashboard_access', 'Accessed user dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 08:37:25'),
(417, 5, 'shop_access', 'Accessed shop page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 08:37:38'),
(418, 5, 'wishlist_view', 'Viewed wishlist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 08:37:52'),
(419, 5, 'cart_view', 'Viewed shopping cart', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 08:37:53');

-- --------------------------------------------------------

--
-- Table structure for table `user_payment_methods`
--

CREATE TABLE `user_payment_methods` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `stripe_payment_method_id` varchar(255) DEFAULT NULL,
  `paypal_email` varchar(255) DEFAULT NULL,
  `bank_account_details` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `login_time`, `logout_time`, `is_active`) VALUES
(1, 1, 'c70ece22e088803565db50d6846ad17843a59de7afe5fc53f9be0a648415216e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 19:00:28', '2026-02-21 19:18:30', 0),
(2, 1, 'b6a1cf61396e65b8e90b59cf16a9f48a760a99b3bfbe0a07e093b4a423ccf2b7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 19:18:37', NULL, 1),
(3, 1, '0cef945b2cd0ef81e2bbb83b2641df4631beb399f4cf692270547d7c476a0e30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 12:41:43', NULL, 1),
(4, 1, '6d28fd40973c25fb14ccaea1a60d46df5c81684ce1cd6cf23c56a9333f320e75', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 14:03:02', NULL, 1),
(5, 1, 'c41fad0b56e8b8c18f47049a9c131ebbe4e94f268f6ad2638cbccd968a5667c2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 16:45:15', NULL, 1),
(6, 1, 'c3249179fb3002a33cd24b7ce6a5ae39add8d5b2d5dce56735a7135f4c4addbb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 18:03:01', NULL, 1),
(7, 1, '6e4dbd174e3bfa3569f4eab557cc8a2d98ca1ed8d1e8093e6dd77f532897ebff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 19:47:40', NULL, 1),
(8, 1, '91d464ce0d790a9821de93c8b8ae2a55e1a5add7e63d6b72d2d9fe5f0ea37328', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 20:07:10', NULL, 1),
(9, 1, '3f8a2d2bc80bcbdac60f1461fcbcd570926a1ea13312610f1bb0c3dd2a30834e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-22 20:24:11', NULL, 1),
(10, 1, '844914743b107ae1e33bea7d1ca228eb5c566086d3e0db7574ab65d2076d2c1e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:13:00', NULL, 1),
(11, 1, 'fddec6cb9250bc8dd9423bc35ea330a36c2157d7e7474466002ee7ecfd7bac99', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:43:23', NULL, 1),
(12, 1, '009e55c520edb5eb0caada5580937fef1c57a6285b64663aa1d9420fc707ef1b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 16:46:01', NULL, 1),
(13, 1, '928e4c0f513dda0734c87d39090888d2dcef512e9ee393fc5ce2c2dfd246d7e5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 17:24:29', '2026-02-23 17:24:39', 0),
(14, 1, '1b1aa6f9fe26ab20e436b02d2f8ae4a2be1f8ae3477e4efe55c961fccbbf2d1d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 17:28:10', NULL, 1),
(15, 1, '7e7eadd1157dcb1e55254e93c77a060048280a00f2fef444a096a2ae268c30c0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 19:26:09', NULL, 1),
(16, 1, '092773d6293ddeca3a7ff902d8b166391fae937fc8a2c7fde9c8ddc21afd6259', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 20:15:39', NULL, 1),
(17, 1, '917747c3c9de70e847ecd965cd04c4d39b3bac045773d32f4678046d26603627', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 13:51:16', NULL, 1),
(18, 1, '840fcd90996259550afc69e22ff006f7d89b5a07349f419c80dc0d74fed1106d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 19:12:43', NULL, 1),
(19, 1, '6edf6aad577375b79535ee98c6a9cdca233faed3bc78b5b4dbc37ad2a652cb19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:18:34', NULL, 1),
(20, 1, '4771e54a35495042f016ac35ca088ecf8620b3051a99ccbb1838bf78214cd186', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 13:50:36', NULL, 1),
(21, 0, 'e8bae04f71ec5965c0974bea3a56db696ac8da61543c995fd88a0c9e736f16b2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:38:25', NULL, 1),
(22, 0, '3f3d38cbbc2e16fdd80448b660f0ca944c62f1884b35b6b17ce9622202c95481', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:41:39', '2026-02-25 14:41:49', 0),
(23, 0, '26458acc06e66ec4734c967f4901e06ac5bc675a184390f0e4de087e9bd1f520', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:41:56', NULL, 1),
(24, 2, 'ecbe3487bef9a7ee4f698760f7dcb63ab5d00e987cde77155024094e795db420', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:43:02', NULL, 1),
(25, 2, '19a56b50da5e54f1777376d9feb241e7d2efcdd325860979702d24ffa443bde9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 15:49:37', NULL, 1),
(26, 2, 'ca094f93dafafb77af64f85c6701a760d62cdfb66cde2ce8cd78f3b8c7db4cff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 16:44:00', NULL, 1),
(27, 1, '2f27f9b35044d076533c0b53fe04722d0fdb7c513cb67fc39291694f12b47bc8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 16:46:17', NULL, 1),
(28, 2, '16864264dfc7af9c595df293e090ee0d8247aafee6bef6bdcd1478f3fcf59c68', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 17:17:44', NULL, 1),
(29, 1, '03a9e15f301cb16151f0bc1811073a4457311df72eea7c9f56aeda02f527c771', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 17:56:42', NULL, 1),
(30, 1, 'ad03cd64b147bd2ecb20bec3937ee1e40742828cba8b7e5dc3fd76da424a5c47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 19:12:51', NULL, 1),
(31, 1, '6d4012dff686c0a23cff9b3a75c5ce14149061aabb4761f24e7a2256f018df49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 20:13:47', NULL, 1),
(32, 1, '1878b8be43fba0c1234614b0332561fb619635e5ed9b6a0ccafe689708dd2d12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 12:37:19', NULL, 1),
(33, 1, '22a52bdd8fa87ede6f50a173319b503443185a4aa2077552341f539a6ec4c30c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:05:00', NULL, 1),
(34, 2, 'f401f1c5a6c1d3653dd016075e93cf1500629235d75cec5e51b5623edcd54245', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:30:06', NULL, 1),
(35, 1, '3e78199ee8012110ee943c493e0ed64c8658ddaa6ef5f16926f04804b0e08474', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 15:10:49', NULL, 1),
(36, 2, '31526f67d06a4b9417469660a96393c58cbd19e232c8188c2741556b39484e81', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 15:41:18', NULL, 1),
(37, 2, '9d656156c5ce4649e28d79ec5f1040bbb009069dd957086faf9537194ad7cb52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 17:39:19', NULL, 1),
(38, 2, 'c3a6b7a15bc186a11db22b714a85aac055bd0913ef48d185bf1eacb46a3d84f5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:07:38', NULL, 1),
(39, 1, 'f0560d561109507e6b3c9bea286fbed0d39b16f335dd59dc8a2a4551d2270361', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:08:21', NULL, 1),
(40, 1, 'd6fe705a6f8c960284e4634d6eb59a36b6ec8a07bf8b8d06d06a6ad4d4976bc5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 11:13:27', NULL, 1),
(41, 2, 'a75f3deb89a461fc7a6cd3d5ad831f02035ff166335beb01dade0e0a4ff25c37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 11:13:40', NULL, 1),
(42, 1, 'd2864ebd70e6782307ed80710b5439729c2fee20049b69efb35ea9fbe27f413f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 12:10:07', NULL, 1),
(43, 2, '5b1b2b632957d715249e86c08a8547fcbd15d3050dc8013dabbd7c1482914d99', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-27 12:10:42', NULL, 1),
(44, 1, '6fc7260bcaff730ef23e98f2295b07303271c8183d6a06b6fecb63ca2c65ffc1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 18:13:53', '2026-03-02 18:14:47', 0),
(45, 2, 'aeb3c8739ee6ec71e60794adad520073f91259bdc4d4bd1f6fcf769e84c2972d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 18:14:53', NULL, 1),
(46, 2, '0aa074c1286cac2905443db343826f4ae45e5e700e551d41069340e788c5dd3f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 17:48:40', NULL, 1),
(47, 2, 'da8de4801c825489a5d6efcbc1bc4fde672ebad3fc2e6b54f554da9cff07a7a5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 20:41:00', NULL, 1),
(48, 2, 'c811ddc15f60d12fdec5cbab58da0cc988bfb9b3c7e8012be374259d8930145e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 14:54:40', NULL, 1),
(49, 5, '5e2d3d389df1f133a96e83e6f0a7337af3004b26dfe1603f82841706296dd237', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 17:56:25', NULL, 1),
(50, 2, '4eb6b767ace0a03af6189a5a85c6b962cc88f2abe36d26de230b76cf8e77e891', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 18:19:42', NULL, 1),
(51, 5, 'a96e2ba4eacb7dd84752680b2d3e6d21d6d90f377bc95735e975c66706bc40ca', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 19:29:08', NULL, 1),
(52, 2, '4f138e6b8dab34c5586d9a53ed96ffe471909f7171523cd043700cc3676b7a5f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 19:33:45', NULL, 1),
(53, 2, 'e7d5c3bf48334ea00a52d5d90e14ed8f0a3ae4b144872b372b02a409feb8392e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 20:13:55', NULL, 1),
(54, 2, 'df9987b3e1b767c403c1b94a18ecf2c33ae9f91056492581a5b6b5055a0dcd63', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 17:07:50', '2026-03-07 17:29:54', 0),
(55, 5, 'a369513bb63dd893e8851f9bfb4cc7277829c94a3978cd0d3b23d21c197e9c88', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 17:08:31', NULL, 1),
(56, 5, 'cdfdf6cf33c2fa1d6498e3197fea2532ec65e3d0f3c02faceec0febedcfcecdf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 17:30:03', NULL, 1),
(57, 5, '7bfa77b3c91a3c75ac45515a758daa01b8aeafad96de685ca7f9f3e64ae04383', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 19:18:02', NULL, 1),
(58, 5, 'd0dff910740daae0c9c6dff5b99931b2c3e7c6c606ff719fa34f51926dd6ea3d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 12:27:17', NULL, 1),
(59, 5, '63bb2a15fd5da74da559c27430f8d8a3cdd68d7f8dd6ea8464964e5786186fbb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:45:00', NULL, 1),
(60, 5, '6c736015300b6e0f7f92367e267995b0419121022b9eb52924e85531990e52c3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 17:00:01', NULL, 1),
(61, 5, '8e7cebff6387e189a1b5b2e4d32d48a1ea3c3f7f66bddeabb2dd88fc896aca36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 19:17:13', NULL, 1),
(62, 5, 'f4ccb0bf900db0256ceed1a36a4c58cc96d47b6c39df125b1b2fc85ce2eaa9a4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 13:37:25', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `vendors_payment_methods`
--

CREATE TABLE `vendors_payment_methods` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `method_type` enum('bank','paypal','stripe','easypaisa','jazzcash','visa','mastercard','amex') NOT NULL,
  `account_title` varchar(255) NOT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `account_holder_name` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `ifsc_code` varchar(50) DEFAULT NULL,
  `swift_code` varchar(50) DEFAULT NULL,
  `routing_number` varchar(50) DEFAULT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `cnic_number` varchar(20) DEFAULT NULL,
  `stripe_account_id` varchar(255) DEFAULT NULL,
  `stripe_publishable_key` varchar(255) DEFAULT NULL,
  `paypal_account_id` varchar(255) DEFAULT NULL,
  `card_type` varchar(50) DEFAULT NULL,
  `card_last_four` varchar(4) DEFAULT NULL,
  `card_expiry_month` varchar(2) DEFAULT NULL,
  `card_expiry_year` varchar(4) DEFAULT NULL,
  `card_encrypted_data` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_bank_accounts`
--

CREATE TABLE `vendor_bank_accounts` (
  `id` int(11) NOT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `routing_number` varchar(50) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `ifsc_code` varchar(50) DEFAULT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `account_type` enum('savings','current') DEFAULT 'savings',
  `mobile_wallet_type` enum('easypaisa','jazzcash') DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `cnic_number` varchar(20) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_bank_accounts`
--

INSERT INTO `vendor_bank_accounts` (`id`, `payment_method_id`, `vendor_id`, `account_holder_name`, `bank_name`, `account_number`, `routing_number`, `swift_code`, `iban`, `ifsc_code`, `branch_name`, `branch_code`, `account_type`, `mobile_wallet_type`, `mobile_number`, `cnic_number`, `is_default`, `is_verified`, `verified_by`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 'Moazzam', 'Meezan', '13746515646', '15161', '1656', '126', '12548JHGU', 'JHAFUBCAJB', '165465', 'current', NULL, '03132842740', '42401-7061995-5', 0, 1, NULL, NULL, '2026-02-22 08:04:52', '2026-02-26 07:53:01'),
(3, 1, 1, 'Moazzam shaikh', 'Example Bank', '13746515665', NULL, '121', NULL, 'EXMP0001234', 'Main Branch', NULL, 'savings', NULL, NULL, NULL, 1, 1, 2, '2026-02-26 14:54:42', '2026-02-26 07:53:01', '2026-02-26 09:54:42');

--
-- Triggers `vendor_bank_accounts`
--
DELIMITER $$
CREATE TRIGGER `vendor_bank_accounts_before_update` BEFORE UPDATE ON `vendor_bank_accounts` FOR EACH ROW BEGIN
    SET NEW.updated_at = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_cards`
--

CREATE TABLE `vendor_cards` (
  `id` int(11) NOT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL,
  `card_type` enum('visa','mastercard','amex') DEFAULT 'visa',
  `card_holder_name` varchar(255) NOT NULL,
  `card_number_encrypted` text NOT NULL,
  `card_last_four` varchar(4) NOT NULL,
  `expiry_month` varchar(2) NOT NULL,
  `expiry_year` varchar(4) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_categories`
--

CREATE TABLE `vendor_categories` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_categories_selected`
--

CREATE TABLE `vendor_categories_selected` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `category_slug` varchar(100) DEFAULT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `category_data` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_category`
--

CREATE TABLE `vendor_category` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `category_name` varchar(255) DEFAULT NULL,
  `category_slug` varchar(255) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `status` enum('active','inactive','rejected') DEFAULT 'active',
  `rejection_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_category_commissions`
--

CREATE TABLE `vendor_category_commissions` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `vendor_category_commissions`
--
DELIMITER $$
CREATE TRIGGER `before_insert_vendor_category_commissions` BEFORE INSERT ON `vendor_category_commissions` FOR EACH ROW BEGIN
    SET NEW.created_at = NOW();
    SET NEW.updated_at = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_update_vendor_category_commissions` BEFORE UPDATE ON `vendor_category_commissions` FOR EACH ROW BEGIN
    SET NEW.updated_at = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_commissions`
--

CREATE TABLE `vendor_commissions` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `product_price` decimal(10,2) NOT NULL,
  `commission_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `vendor_commissions`
--
DELIMITER $$
CREATE TRIGGER `before_insert_vendor_commissions` BEFORE INSERT ON `vendor_commissions` FOR EACH ROW BEGIN
    SET NEW.created_at = NOW();
    IF NEW.commission_amount IS NULL THEN
        SET NEW.commission_amount = (NEW.product_price * NEW.commission_rate / 100);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_update_vendor_commissions` BEFORE UPDATE ON `vendor_commissions` FOR EACH ROW BEGIN
    SET NEW.commission_amount = (NEW.product_price * NEW.commission_rate / 100);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vendor_dashboard_summary`
-- (See below for the actual view)
--
CREATE TABLE `vendor_dashboard_summary` (
`vendor_id` int(11)
,`username` varchar(50)
,`full_name` varchar(100)
,`total_products` bigint(21)
,`approved_products` bigint(21)
,`pending_products` bigint(21)
,`featured_products` bigint(21)
,`total_orders` bigint(21)
,`total_earnings` decimal(32,2)
,`paid_earnings` decimal(32,2)
,`pending_earnings` decimal(32,2)
,`total_reviews` bigint(21)
,`average_rating` decimal(14,4)
,`low_stock_items` bigint(21)
,`out_of_stock_items` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_documents`
--

CREATE TABLE `vendor_documents` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `document_type` enum('id_proof','address_proof','business_registration','tax_certificate') NOT NULL,
  `document_number` varchar(100) DEFAULT NULL,
  `document_file` varchar(255) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_documents`
--

INSERT INTO `vendor_documents` (`id`, `vendor_id`, `document_type`, `document_number`, `document_file`, `verified`, `verified_by`, `verified_at`, `expiry_date`, `created_at`) VALUES
(1, 1, 'business_registration', 'addasfs', 'doc_1_1771684392.jpg', 1, NULL, NULL, '2027-02-12', '2026-02-21 14:33:12'),
(2, 1, 'id_proof', 'addasfs', 'tax_1_1771752066.jpg', 1, 2, '2026-03-06 14:58:18', '2027-02-12', '2026-02-22 09:21:06');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_earnings`
--

CREATE TABLE `vendor_earnings` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `product_price` decimal(10,2) NOT NULL,
  `commission` decimal(5,2) DEFAULT 0.00,
  `commission_amount` decimal(10,2) NOT NULL,
  `vendor_amount` decimal(10,2) NOT NULL,
  `status` varchar(20) DEFAULT NULL,
  `paid_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vendor_earnings_summary`
-- (See below for the actual view)
--
CREATE TABLE `vendor_earnings_summary` (
`vendor_id` int(11)
,`username` varchar(50)
,`full_name` varchar(100)
,`total_orders` bigint(21)
,`total_earnings` decimal(32,2)
,`paid_amount` decimal(32,2)
,`pending_amount` decimal(32,2)
,`total_products` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_messages`
--

CREATE TABLE `vendor_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied','archived') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_messages`
--

INSERT INTO `vendor_messages` (`id`, `user_id`, `vendor_id`, `product_id`, `subject`, `message`, `status`, `created_at`, `read_at`, `replied_at`) VALUES
(1, 5, 1, 1, 'moazzam shaikhs', 'jhflvudkllu yudcuv', 'unread', '2026-03-06 15:19:06', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_metrics`
--

CREATE TABLE `vendor_metrics` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `month_year` varchar(7) DEFAULT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_sales` decimal(10,2) DEFAULT 0.00,
  `total_earnings` decimal(10,2) DEFAULT 0.00,
  `total_withdrawals` decimal(10,2) DEFAULT 0.00,
  `product_views` int(11) DEFAULT 0,
  `conversion_rate` decimal(5,2) DEFAULT 0.00,
  `avg_rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_mobile_accounts`
--

CREATE TABLE `vendor_mobile_accounts` (
  `id` int(11) NOT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL,
  `account_type` enum('easypaisa','jazzcash') NOT NULL,
  `mobile_number` varchar(20) NOT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `cnic_number` varchar(20) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_mobile_accounts`
--

INSERT INTO `vendor_mobile_accounts` (`id`, `payment_method_id`, `vendor_id`, `account_type`, `mobile_number`, `account_holder_name`, `cnic_number`, `is_default`, `is_verified`, `verified_by`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, 7, 1, 'easypaisa', '03132842740', 'Moazzam shaikh', NULL, 1, 0, NULL, NULL, '2026-02-26 09:55:45', '2026-02-26 09:55:45'),
(2, 8, 1, 'jazzcash', '03132842740', 'Moazzam shaikh', NULL, 1, 0, NULL, NULL, '2026-02-26 09:56:15', '2026-02-26 09:56:15');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vendor_monthly_performance`
-- (See below for the actual view)
--
CREATE TABLE `vendor_monthly_performance` (
`vendor_id` int(11)
,`username` varchar(50)
,`month_year` varchar(7)
,`monthly_orders` bigint(21)
,`monthly_earnings` decimal(32,2)
,`products_sold` bigint(21)
,`monthly_paid` decimal(32,2)
,`monthly_pending` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_payment_methods`
--

CREATE TABLE `vendor_payment_methods` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `method_type` enum('bank','paypal','stripe','easypaisa','jazzcash','visa','mastercard','amex') NOT NULL,
  `country_code` varchar(2) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_payment_methods`
--

INSERT INTO `vendor_payment_methods` (`id`, `vendor_id`, `method_type`, `country_code`, `is_default`, `is_verified`, `verified_by`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'bank', 'PK', 1, 0, NULL, NULL, '2026-02-26 07:53:01', '2026-02-26 07:53:01'),
(2, 1, 'paypal', '', 1, 0, NULL, NULL, '2026-02-26 08:30:24', '2026-02-26 08:30:24'),
(3, 1, 'stripe', '', 1, 0, NULL, NULL, '2026-02-26 09:06:40', '2026-02-26 09:06:40'),
(7, 1, 'easypaisa', '', 0, 0, NULL, NULL, '2026-02-26 09:55:45', '2026-02-26 09:56:15'),
(8, 1, 'jazzcash', '', 1, 0, NULL, NULL, '2026-02-26 09:56:15', '2026-02-26 09:56:15');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_paypal_accounts`
--

CREATE TABLE `vendor_paypal_accounts` (
  `id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `paypal_email` varchar(255) NOT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `paypal_account_id` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_paypal_accounts`
--

INSERT INTO `vendor_paypal_accounts` (`id`, `payment_method_id`, `vendor_id`, `paypal_email`, `account_holder_name`, `paypal_account_id`, `is_default`, `is_verified`, `verified_by`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'moazzams030@gmail.com', 'Moazzam shaikh', NULL, 0, 1, 2, '2026-02-26 14:49:10', '2026-02-26 08:30:24', '2026-02-26 09:49:10');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vendor_products_detailed`
-- (See below for the actual view)
--
CREATE TABLE `vendor_products_detailed` (
`product_id` int(11)
,`product_name` varchar(255)
,`description` text
,`price` decimal(10,2)
,`old_price` decimal(10,2)
,`image` varchar(255)
,`category` varchar(100)
,`stock` int(11)
,`featured` tinyint(1)
,`approved_status` enum('pending','approved','rejected')
,`views` int(11)
,`sales_count` int(11)
,`product_created` timestamp
,`vendor_id` int(11)
,`vendor_username` varchar(50)
,`vendor_name` varchar(100)
,`vendor_rating` decimal(3,2)
,`vendor_status` enum('pending','approved','rejected','suspended')
,`category_name` varchar(100)
,`average_rating` decimal(14,4)
,`review_count` bigint(21)
,`total_sold` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vendor_products_view`
-- (See below for the actual view)
--
CREATE TABLE `vendor_products_view` (
`id` int(11)
,`vendor_id` int(11)
,`approved_status` enum('pending','approved','rejected')
,`is_featured` tinyint(1)
,`views` int(11)
,`sales_count` int(11)
,`name` varchar(255)
,`description` text
,`price` decimal(10,2)
,`old_price` decimal(10,2)
,`image` varchar(255)
,`category` varchar(100)
,`stock` int(11)
,`featured` tinyint(1)
,`created_at` timestamp
,`updated_at` timestamp
,`low_stock` tinyint(1)
,`out_of_stock` tinyint(1)
,`vendor_username` varchar(50)
,`vendor_name` varchar(100)
,`vendor_rating` decimal(3,2)
,`category_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `vendor_responses`
--

CREATE TABLE `vendor_responses` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `response_text` text NOT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `is_edited` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_reviews`
--

CREATE TABLE `vendor_reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text NOT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_settings`
--

CREATE TABLE `vendor_settings` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `store_description` text DEFAULT NULL,
  `vendor_category` varchar(100) DEFAULT NULL,
  `store_logo` varchar(255) DEFAULT NULL,
  `store_banner` varchar(255) DEFAULT NULL,
  `store_address` text DEFAULT NULL,
  `store_phone` varchar(20) DEFAULT NULL,
  `store_email` varchar(100) DEFAULT NULL,
  `store_website` varchar(255) DEFAULT NULL,
  `store_social_facebook` varchar(255) DEFAULT NULL,
  `store_social_instagram` varchar(255) DEFAULT NULL,
  `store_social_twitter` varchar(255) DEFAULT NULL,
  `store_social_linkedin` varchar(255) DEFAULT NULL,
  `store_social_youtube` varchar(255) DEFAULT NULL,
  `store_social_pinterest` varchar(255) DEFAULT NULL,
  `store_policy` text DEFAULT NULL,
  `return_policy` text DEFAULT NULL,
  `shipping_policy` text DEFAULT NULL,
  `payment_methods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_methods`)),
  `store_currency` varchar(10) DEFAULT 'USD',
  `store_timezone` varchar(50) DEFAULT 'UTC',
  `store_language` varchar(10) DEFAULT 'en',
  `business_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`business_hours`)),
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `free_shipping_threshold` decimal(10,2) DEFAULT 0.00,
  `low_stock_notify` tinyint(1) DEFAULT 1,
  `auto_hide_out_of_stock` tinyint(1) DEFAULT 1,
  `allow_backorders` tinyint(1) DEFAULT 0,
  `api_key` varchar(255) DEFAULT NULL,
  `webhook_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_settings`
--

INSERT INTO `vendor_settings` (`id`, `vendor_id`, `store_name`, `store_description`, `vendor_category`, `store_logo`, `store_banner`, `store_address`, `store_phone`, `store_email`, `store_website`, `store_social_facebook`, `store_social_instagram`, `store_social_twitter`, `store_social_linkedin`, `store_social_youtube`, `store_social_pinterest`, `store_policy`, `return_policy`, `shipping_policy`, `payment_methods`, `store_currency`, `store_timezone`, `store_language`, `business_hours`, `min_order_amount`, `free_shipping_threshold`, `low_stock_notify`, `auto_hide_out_of_stock`, `allow_backorders`, `api_key`, `webhook_url`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 1, 'Test Store', 'skjfisbisb', 'vendor-food', 'vendor_1_logo_1772026311.jpg', NULL, '', '03132842740', 'moazzamsshaikhs@gmail.com', 'http://localhost/e-commerce/admin/vendors/settings/settings.php', 'moazzam.sheikh.581408/', '', '', '', '', '', 'a,m,dna;l', 'jlsf;osno;nososisbsius', 'jshgso;s sisons', '[\"paypal\",\"stripe\",\"easypaisa\"]', 'PKR', 'America/New_York', 'en', '{\"monday\":{\"open\":\"07:00\",\"close\":\"07:07\",\"enabled\":true},\"tuesday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false},\"wednesday\":{\"open\":\"08:12\",\"close\":\"08:12\",\"enabled\":true},\"thursday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false},\"friday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false},\"saturday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false},\"sunday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false}}', 0.00, 0.00, 1, 1, 0, NULL, NULL, 1, '2026-02-25 13:59:17', '2026-02-25 18:31:51'),
(3, 2, 'jyeeafdiua', 'njdfaukj', 'vendor-home', 'vendor_2_logo_1772022570.jpg', 'vendor_2_banner_1772022736.png', '', '01654+96265', 'moazzams030@gmail.com', 'http://localhost/e-commerce/admin/vendors/settings/settings.php', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'USD', 'UTC', 'en', '{\"monday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false},\"tuesday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false},\"wednesday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false},\"thursday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false},\"friday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false},\"saturday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false},\"sunday\":{\"open\":\"\",\"close\":\"\",\"enabled\":false}}', 0.00, 0.00, 1, 1, 1, 'sk_live_01c68b045785df09cce6598a40626036', NULL, 1, '2026-02-25 16:14:54', '2026-02-25 17:35:12');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_shipping_methods`
--

CREATE TABLE `vendor_shipping_methods` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `method_name` varchar(100) NOT NULL,
  `method_data` text DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_shipping_settings`
--

CREATE TABLE `vendor_shipping_settings` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `processing_time` int(11) DEFAULT 1,
  `handling_fee` decimal(10,2) DEFAULT 0.00,
  `package_dimensions` text DEFAULT NULL,
  `packaging_instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_shipping_zones`
--

CREATE TABLE `vendor_shipping_zones` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `zone_name` varchar(100) NOT NULL,
  `zone_data` text DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_stripe_accounts`
--

CREATE TABLE `vendor_stripe_accounts` (
  `id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `stripe_account_id` varchar(255) NOT NULL,
  `stripe_publishable_key` varchar(255) DEFAULT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `account_email` varchar(255) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendor_stripe_accounts`
--

INSERT INTO `vendor_stripe_accounts` (`id`, `payment_method_id`, `vendor_id`, `stripe_account_id`, `stripe_publishable_key`, `account_holder_name`, `account_email`, `is_default`, `is_verified`, `verified_by`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 'acct_1654', NULL, 'Moazzam', 'moazzamsshaikhs@gmail.com', 0, 1, 2, '2026-02-26 14:55:00', '2026-02-26 09:06:40', '2026-02-26 09:55:00');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_tax_classes`
--

CREATE TABLE `vendor_tax_classes` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `class_description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_tax_exemptions`
--

CREATE TABLE `vendor_tax_exemptions` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `country` varchar(2) DEFAULT NULL,
  `exemption_type` enum('wholesale','nonprofit','government','other') DEFAULT 'wholesale',
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_tax_rates`
--

CREATE TABLE `vendor_tax_rates` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `tax_class_id` int(11) DEFAULT NULL,
  `country` varchar(2) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `rate` decimal(5,2) NOT NULL,
  `rate_name` varchar(100) NOT NULL,
  `priority` int(11) DEFAULT 1,
  `compound` tinyint(1) DEFAULT 0,
  `shipping` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_tax_settings`
--

CREATE TABLE `vendor_tax_settings` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `tax_enabled` tinyint(1) DEFAULT 1,
  `prices_include_tax` tinyint(1) DEFAULT 0,
  `tax_based_on` enum('billing','shipping','base') DEFAULT 'billing',
  `shipping_taxable` tinyint(1) DEFAULT 1,
  `tax_rounding` enum('round','per_item','none') DEFAULT 'round',
  `tax_number` varchar(100) DEFAULT NULL,
  `tax_display` enum('excl','incl','both') DEFAULT 'excl',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_withdrawals`
--

CREATE TABLE `vendor_withdrawals` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `withdrawal_method` enum('bank','paypal','stripe','visa','easypaisa','jazzcash','cash') DEFAULT 'bank',
  `payment_method_id` int(11) DEFAULT NULL,
  `withdrawal_amount` decimal(10,2) NOT NULL,
  `fee_amount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','processing','completed','rejected','cancelled') DEFAULT 'pending',
  `account_details` text DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `card_last_four` varchar(4) DEFAULT NULL,
  `cnic_number` varchar(20) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_withdrawal_requests`
--

CREATE TABLE `vendor_withdrawal_requests` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `request_amount` decimal(10,2) NOT NULL,
  `available_balance` decimal(10,2) NOT NULL,
  `withdrawal_method` enum('bank','paypal','stripe','easypaisa','jazzcash','visa','mastercard') NOT NULL,
  `account_details` text NOT NULL,
  `status` enum('pending','approved','processing','completed','rejected','cancelled') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_zone_methods`
--

CREATE TABLE `vendor_zone_methods` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `method_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `added_at`) VALUES
(1, 5, 2, '2026-03-06 13:18:23');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_methods`
--

CREATE TABLE `withdrawal_methods` (
  `id` int(11) NOT NULL,
  `method_code` varchar(50) NOT NULL,
  `method_name` varchar(100) NOT NULL,
  `method_icon` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `min_amount` decimal(10,2) DEFAULT 10.00,
  `max_amount` decimal(10,2) DEFAULT NULL,
  `fee_percentage` decimal(5,2) DEFAULT 0.00,
  `fee_fixed` decimal(10,2) DEFAULT 0.00,
  `processing_days` int(11) DEFAULT 3,
  `requires_account` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `withdrawal_methods`
--

INSERT INTO `withdrawal_methods` (`id`, `method_code`, `method_name`, `method_icon`, `description`, `min_amount`, `max_amount`, `fee_percentage`, `fee_fixed`, `processing_days`, `requires_account`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'bank', 'Bank Transfer', 'university', 'Direct bank transfer to your account. Standard processing time 3-5 business days.', 10.00, 10000.00, 0.00, 0.00, 3, 1, 1, 1, '2026-02-22 15:52:49'),
(2, 'paypal', 'PayPal', 'paypal', 'Withdraw to your PayPal account. Instant transfer to your PayPal balance.', 10.00, 5000.00, 2.50, 0.00, 1, 1, 1, 2, '2026-02-22 15:52:49'),
(3, 'stripe', 'Stripe', 'credit-card', 'Withdraw to your Stripe account. Fast processing to your connected bank account.', 10.00, 5000.00, 2.00, 0.00, 2, 1, 1, 3, '2026-02-22 15:52:49'),
(4, 'visa', 'Visa Card', 'credit-card', 'Withdraw directly to your Visa debit/credit card. Instant transfer to your card.', 20.00, 2000.00, 3.50, 0.50, 1, 1, 1, 4, '2026-02-22 15:52:49'),
(5, 'easypaisa', 'Easypaisa', 'mobile-alt', 'Withdraw to your Easypaisa mobile account. Popular in Pakistan.', 5.00, 500.00, 2.00, 0.25, 1, 1, 1, 5, '2026-02-22 15:52:49'),
(6, 'jazzcash', 'JazzCash', 'mobile-alt', 'Withdraw to your JazzCash mobile account. Fast and secure.', 5.00, 500.00, 2.00, 0.25, 1, 1, 1, 6, '2026-02-22 15:52:49'),
(7, 'cash', 'Cash Pickup', 'money-bill', 'Pick up cash at partner locations nationwide. Valid ID required.', 20.00, 1000.00, 5.00, 0.00, 1, 0, 1, 7, '2026-02-22 15:52:49'),
(8, 'mastercard', 'Mastercard', NULL, NULL, 20.00, NULL, 3.50, 0.50, 1, 1, 1, 5, '2026-02-26 07:50:30');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_transactions`
--

CREATE TABLE `withdrawal_transactions` (
  `id` int(11) NOT NULL,
  `withdrawal_request_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `admin_account_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fee` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'completed',
  `transaction_data` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `withdrawal_transactions`
--
DELIMITER $$
CREATE TRIGGER `after_withdrawal_transaction_insert` AFTER INSERT ON `withdrawal_transactions` FOR EACH ROW BEGIN
    DECLARE old_balance DECIMAL(10,2);
    
    -- Get current balance
    SELECT current_balance INTO old_balance FROM admin_accounts WHERE id = NEW.admin_account_id;
    
    -- Update admin_accounts balance
    UPDATE admin_accounts SET 
        current_balance = current_balance - NEW.net_amount,
        total_debited = total_debited + NEW.net_amount,
        last_transaction_at = NOW()
    WHERE id = NEW.admin_account_id;
    
    -- Insert into balance history
    INSERT INTO account_balance_history 
    (admin_account_id, balance, change_amount, change_type, reference_id, reference_type, notes)
    VALUES (NEW.admin_account_id, old_balance - NEW.net_amount, NEW.net_amount, 'debit', NEW.id, 'withdrawal', CONCAT('Withdrawal to vendor #', NEW.vendor_id));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure for view `admin_vendors_management`
--
DROP TABLE IF EXISTS `admin_vendors_management`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `admin_vendors_management`  AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`phone` AS `phone`, `u`.`full_name` AS `full_name`, `u`.`user_type` AS `user_type`, `u`.`vendor_status` AS `vendor_status`, `u`.`vendor_verified` AS `vendor_verified`, `u`.`vendor_since` AS `vendor_since`, `u`.`vendor_rating` AS `vendor_rating`, `u`.`total_products` AS `total_products`, `u`.`total_sales` AS `total_sales`, `u`.`account_status` AS `account_status`, `u`.`created_at` AS `created_at`, `u`.`last_login` AS `last_login`, (select count(0) from `products` `p` where `p`.`vendor_id` = `u`.`id` and `p`.`approved_status` = 'approved') AS `active_products`, (select count(0) from `products` `p` where `p`.`vendor_id` = `u`.`id` and `p`.`approved_status` = 'pending') AS `pending_products`, (select count(distinct `o`.`id`) from ((`orders` `o` join `order_items` `oi` on(`o`.`id` = `oi`.`order_id`)) join `products` `p` on(`oi`.`product_id` = `p`.`id`)) where `p`.`vendor_id` = `u`.`id`) AS `total_vendor_orders`, coalesce((select sum(`o`.`total_amount`) from ((`orders` `o` join `order_items` `oi` on(`o`.`id` = `oi`.`order_id`)) join `products` `p` on(`oi`.`product_id` = `p`.`id`)) where `p`.`vendor_id` = `u`.`id`),0) AS `total_vendor_sales` FROM `users` AS `u` WHERE `u`.`user_type` = 'vendor' ;

-- --------------------------------------------------------

--
-- Structure for view `vendor_dashboard_summary`
--
DROP TABLE IF EXISTS `vendor_dashboard_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vendor_dashboard_summary`  AS SELECT `u`.`id` AS `vendor_id`, `u`.`username` AS `username`, `u`.`full_name` AS `full_name`, (select count(0) from `products` `p` where `p`.`vendor_id` = `u`.`id`) AS `total_products`, (select count(0) from `products` `p` where `p`.`vendor_id` = `u`.`id` and `p`.`approved_status` = 'approved') AS `approved_products`, (select count(0) from `products` `p` where `p`.`vendor_id` = `u`.`id` and `p`.`approved_status` = 'pending') AS `pending_products`, (select count(0) from `products` `p` where `p`.`vendor_id` = `u`.`id` and `p`.`featured` = 1) AS `featured_products`, (select count(distinct `oi`.`order_id`) from (`order_items` `oi` join `products` `p` on(`oi`.`product_id` = `p`.`id`)) where `p`.`vendor_id` = `u`.`id`) AS `total_orders`, coalesce((select sum(`ve`.`vendor_amount`) from `vendor_earnings` `ve` where `ve`.`vendor_id` = `u`.`id`),0) AS `total_earnings`, coalesce((select sum(`ve`.`vendor_amount`) from `vendor_earnings` `ve` where `ve`.`vendor_id` = `u`.`id` and `ve`.`status` = 'paid'),0) AS `paid_earnings`, coalesce((select sum(`ve`.`vendor_amount`) from `vendor_earnings` `ve` where `ve`.`vendor_id` = `u`.`id` and `ve`.`status` in ('pending','processing')),0) AS `pending_earnings`, (select count(0) from (`reviews` `r` join `products` `p` on(`r`.`product_id` = `p`.`id`)) where `p`.`vendor_id` = `u`.`id`) AS `total_reviews`, (select coalesce(avg(`r`.`rating`),0) from (`reviews` `r` join `products` `p` on(`r`.`product_id` = `p`.`id`)) where `p`.`vendor_id` = `u`.`id`) AS `average_rating`, (select count(0) from `products` `p` where `p`.`vendor_id` = `u`.`id` and `p`.`stock` > 0 and `p`.`stock` < 10) AS `low_stock_items`, (select count(0) from `products` `p` where `p`.`vendor_id` = `u`.`id` and `p`.`stock` = 0) AS `out_of_stock_items` FROM `users` AS `u` WHERE `u`.`user_type` = 'vendor' ;

-- --------------------------------------------------------

--
-- Structure for view `vendor_earnings_summary`
--
DROP TABLE IF EXISTS `vendor_earnings_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vendor_earnings_summary`  AS SELECT `u`.`id` AS `vendor_id`, `u`.`username` AS `username`, `u`.`full_name` AS `full_name`, count(distinct `ve`.`order_id`) AS `total_orders`, sum(`ve`.`vendor_amount`) AS `total_earnings`, sum(case when `ve`.`status` = 'paid' then `ve`.`vendor_amount` else 0 end) AS `paid_amount`, sum(case when `ve`.`status` in ('pending','processing') then `ve`.`vendor_amount` else 0 end) AS `pending_amount`, count(distinct `p`.`id`) AS `total_products` FROM ((`users` `u` left join `vendor_earnings` `ve` on(`u`.`id` = `ve`.`vendor_id`)) left join `products` `p` on(`u`.`id` = `p`.`vendor_id`)) WHERE `u`.`user_type` = 'vendor' GROUP BY `u`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `vendor_monthly_performance`
--
DROP TABLE IF EXISTS `vendor_monthly_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vendor_monthly_performance`  AS SELECT `u`.`id` AS `vendor_id`, `u`.`username` AS `username`, date_format(`ve`.`created_at`,'%Y-%m') AS `month_year`, count(distinct `ve`.`order_id`) AS `monthly_orders`, coalesce(sum(`ve`.`vendor_amount`),0) AS `monthly_earnings`, count(distinct `p`.`id`) AS `products_sold`, coalesce(sum(case when `ve`.`status` = 'paid' then `ve`.`vendor_amount` else 0 end),0) AS `monthly_paid`, coalesce(sum(case when `ve`.`status` in ('pending','processing') then `ve`.`vendor_amount` else 0 end),0) AS `monthly_pending` FROM ((`users` `u` left join `vendor_earnings` `ve` on(`u`.`id` = `ve`.`vendor_id`)) left join `products` `p` on(`u`.`id` = `p`.`vendor_id`)) WHERE `u`.`user_type` = 'vendor' GROUP BY `u`.`id`, date_format(`ve`.`created_at`,'%Y-%m') ;

-- --------------------------------------------------------

--
-- Structure for view `vendor_products_detailed`
--
DROP TABLE IF EXISTS `vendor_products_detailed`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vendor_products_detailed`  AS SELECT `p`.`id` AS `product_id`, `p`.`name` AS `product_name`, `p`.`description` AS `description`, `p`.`price` AS `price`, `p`.`old_price` AS `old_price`, `p`.`image` AS `image`, `p`.`category` AS `category`, `p`.`stock` AS `stock`, `p`.`featured` AS `featured`, `p`.`approved_status` AS `approved_status`, `p`.`views` AS `views`, `p`.`sales_count` AS `sales_count`, `p`.`created_at` AS `product_created`, `u`.`id` AS `vendor_id`, `u`.`username` AS `vendor_username`, `u`.`full_name` AS `vendor_name`, `u`.`vendor_rating` AS `vendor_rating`, `u`.`vendor_status` AS `vendor_status`, `c`.`name` AS `category_name`, coalesce(avg(`r`.`rating`),0) AS `average_rating`, count(`r`.`id`) AS `review_count`, (select count(0) from `order_items` `oi` where `oi`.`product_id` = `p`.`id`) AS `total_sold` FROM (((`products` `p` join `users` `u` on(`p`.`vendor_id` = `u`.`id` and `u`.`user_type` = 'vendor')) left join `categories` `c` on(`p`.`category` = `c`.`slug`)) left join `reviews` `r` on(`p`.`id` = `r`.`product_id`)) GROUP BY `p`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `vendor_products_view`
--
DROP TABLE IF EXISTS `vendor_products_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vendor_products_view`  AS SELECT `p`.`id` AS `id`, `p`.`vendor_id` AS `vendor_id`, `p`.`approved_status` AS `approved_status`, `p`.`is_featured` AS `is_featured`, `p`.`views` AS `views`, `p`.`sales_count` AS `sales_count`, `p`.`name` AS `name`, `p`.`description` AS `description`, `p`.`price` AS `price`, `p`.`old_price` AS `old_price`, `p`.`image` AS `image`, `p`.`category` AS `category`, `p`.`stock` AS `stock`, `p`.`featured` AS `featured`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, `p`.`low_stock` AS `low_stock`, `p`.`out_of_stock` AS `out_of_stock`, `u`.`username` AS `vendor_username`, `u`.`full_name` AS `vendor_name`, `u`.`vendor_rating` AS `vendor_rating`, `c`.`name` AS `category_name` FROM ((`products` `p` join `users` `u` on(`p`.`vendor_id` = `u`.`id`)) left join `categories` `c` on(`p`.`category` = `c`.`slug`)) WHERE `u`.`user_type` = 'vendor' AND `u`.`vendor_status` = 'approved' ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_balance_history`
--
ALTER TABLE `account_balance_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_account_id` (`admin_account_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_type` (`account_type`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `is_default` (`is_default`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `admin_commission_collections`
--
ALTER TABLE `admin_commission_collections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_commission_id` (`vendor_commission_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `admin_account_id` (`admin_account_id`);

--
-- Indexes for table `admin_payment_accounts`
--
ALTER TABLE `admin_payment_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gateway_id` (`gateway_id`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `admin_system_access`
--
ALTER TABLE `admin_system_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin` (`admin_id`),
  ADD KEY `is_super_admin` (`is_super_admin`),
  ADD KEY `granted_by` (`granted_by`);

--
-- Indexes for table `analytics`
--
ALTER TABLE `analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`date`);

--
-- Indexes for table `analytics_pages`
--
ALTER TABLE `analytics_pages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `analytics_sources`
--
ALTER TABLE `analytics_sources`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `backup_schedules`
--
ALTER TABLE `backup_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `category_change_requests`
--
ALTER TABLE `category_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_currency` (`currency_code`);

--
-- Indexes for table `country_bank_formats`
--
ALTER TABLE `country_bank_formats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `country_code` (`country_code`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `custom_fields`
--
ALTER TABLE `custom_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `field_key` (`field_key`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `deleted_products`
--
ALTER TABLE `deleted_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `original_id` (`original_id`),
  ADD KEY `deleted_by` (`deleted_by`),
  ADD KEY `deleted_at` (`deleted_at`);

--
-- Indexes for table `deleted_reviews`
--
ALTER TABLE `deleted_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `deleted_by` (`deleted_by`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_key` (`template_key`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category` (`category`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `sort_order` (`sort_order`);

--
-- Indexes for table `import_export_logs`
--
ALTER TABLE `import_export_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `invoice_settings`
--
ALTER TABLE `invoice_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoice_templates`
--
ALTER TABLE `invoice_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_key` (`template_key`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `shipping_carrier_id` (`shipping_carrier_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_notes`
--
ALTER TABLE `order_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `otp_verification`
--
ALTER TABLE `otp_verification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `payment_audit`
--
ALTER TABLE `payment_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gateway_code` (`gateway_code`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `payment_intents`
--
ALTER TABLE `payment_intents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `intent_id` (`intent_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `gateway` (`gateway`);

--
-- Indexes for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gateway_transaction_id` (`gateway_transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `transaction_type` (`transaction_type`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_category_status` (`category_id`,`approved_status`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `review_likes`
--
ALTER TABLE `review_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`review_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `review_reports`
--
ALTER TABLE `review_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `review_id` (`review_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_settings_group` (`group`),
  ADD KEY `idx_settings_sort` (`sort_order`);

--
-- Indexes for table `settings_groups`
--
ALTER TABLE `settings_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `settings_history`
--
ALTER TABLE `settings_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `shipping_carriers`
--
ALTER TABLE `shipping_carriers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `priority` (`priority`),
  ADD KEY `category` (`category`);

--
-- Indexes for table `system_updates`
--
ALTER TABLE `system_updates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tax_form_requests`
--
ALTER TABLE `tax_form_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `status` (`status`),
  ADD KEY `tax_year` (`tax_year`);

--
-- Indexes for table `upgrade_requests`
--
ALTER TABLE `upgrade_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `payment_method` (`payment_method`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_user_activity` (`user_id`,`created_at`),
  ADD KEY `idx_activity_type` (`activity_type`);

--
-- Indexes for table `user_payment_methods`
--
ALTER TABLE `user_payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `stripe_customer_id` (`stripe_customer_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `vendors_payment_methods`
--
ALTER TABLE `vendors_payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `method_type` (`method_type`),
  ADD KEY `is_verified` (`is_verified`),
  ADD KEY `is_default` (`is_default`);

--
-- Indexes for table `vendor_bank_accounts`
--
ALTER TABLE `vendor_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `idx_vendor_default` (`vendor_id`,`is_default`),
  ADD KEY `idx_account_verification` (`vendor_id`,`is_verified`),
  ADD KEY `idx_payment_method` (`payment_method_id`),
  ADD KEY `idx_vendor_verified` (`vendor_id`,`is_verified`);

--
-- Indexes for table `vendor_cards`
--
ALTER TABLE `vendor_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `card_last_four` (`card_last_four`),
  ADD KEY `vendor_cards_ibfk_2` (`verified_by`),
  ADD KEY `idx_vendor_verified` (`vendor_id`,`is_verified`);

--
-- Indexes for table `vendor_categories`
--
ALTER TABLE `vendor_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vendor_category` (`vendor_id`,`category_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `vendor_categories_selected`
--
ALTER TABLE `vendor_categories_selected`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vendor_category` (`vendor_id`,`category_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_vendor_status` (`vendor_id`,`status`),
  ADD KEY `idx_category_slug` (`category_slug`);

--
-- Indexes for table `vendor_category`
--
ALTER TABLE `vendor_category`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vendor_category` (`vendor_id`,`category_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `vendor_category_commissions`
--
ALTER TABLE `vendor_category_commissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vendor_category` (`vendor_id`,`category_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `vendor_commissions`
--
ALTER TABLE `vendor_commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `vendor_documents`
--
ALTER TABLE `vendor_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `vendor_earnings`
--
ALTER TABLE `vendor_earnings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_item_id` (`order_item_id`);

--
-- Indexes for table `vendor_messages`
--
ALTER TABLE `vendor_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `vendor_metrics`
--
ALTER TABLE `vendor_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vendor_month` (`vendor_id`,`month_year`);

--
-- Indexes for table `vendor_mobile_accounts`
--
ALTER TABLE `vendor_mobile_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `account_type` (`account_type`),
  ADD KEY `mobile_number` (`mobile_number`),
  ADD KEY `vendor_mobile_accounts_ibfk_2` (`verified_by`),
  ADD KEY `idx_vendor_verified` (`vendor_id`,`is_verified`);

--
-- Indexes for table `vendor_payment_methods`
--
ALTER TABLE `vendor_payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `method_type` (`method_type`),
  ADD KEY `country_code` (`country_code`);

--
-- Indexes for table `vendor_paypal_accounts`
--
ALTER TABLE `vendor_paypal_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `idx_vendor_verified` (`vendor_id`,`is_verified`);

--
-- Indexes for table `vendor_responses`
--
ALTER TABLE `vendor_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `review_id` (`review_id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `vendor_reviews`
--
ALTER TABLE `vendor_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vendor_user` (`user_id`,`vendor_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `rating` (`rating`),
  ADD KEY `is_approved` (`is_approved`);

--
-- Indexes for table `vendor_settings`
--
ALTER TABLE `vendor_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `vendor_shipping_methods`
--
ALTER TABLE `vendor_shipping_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_enabled` (`is_enabled`);

--
-- Indexes for table `vendor_shipping_settings`
--
ALTER TABLE `vendor_shipping_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `vendor_shipping_zones`
--
ALTER TABLE `vendor_shipping_zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_enabled` (`is_enabled`);

--
-- Indexes for table `vendor_stripe_accounts`
--
ALTER TABLE `vendor_stripe_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `idx_vendor_verified` (`vendor_id`,`is_verified`);

--
-- Indexes for table `vendor_tax_classes`
--
ALTER TABLE `vendor_tax_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vendor_class` (`vendor_id`,`class_name`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_default` (`is_default`);

--
-- Indexes for table `vendor_tax_exemptions`
--
ALTER TABLE `vendor_tax_exemptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_customer_email` (`customer_email`),
  ADD KEY `idx_validity` (`valid_from`,`valid_to`);

--
-- Indexes for table `vendor_tax_rates`
--
ALTER TABLE `vendor_tax_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_location` (`country`,`state`,`city`,`postcode`),
  ADD KEY `idx_tax_class` (`tax_class_id`);

--
-- Indexes for table `vendor_tax_settings`
--
ALTER TABLE `vendor_tax_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `vendor_withdrawals`
--
ALTER TABLE `vendor_withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_payment_method` (`payment_method_id`),
  ADD KEY `idx_processed_by` (`processed_by`),
  ADD KEY `idx_status_created` (`status`,`created_at`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `vendor_withdrawal_requests`
--
ALTER TABLE `vendor_withdrawal_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `status` (`status`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `vendor_zone_methods`
--
ALTER TABLE `vendor_zone_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_zone_method` (`zone_id`,`method_id`),
  ADD KEY `idx_zone_id` (`zone_id`),
  ADD KEY `idx_method_id` (`method_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `withdrawal_methods`
--
ALTER TABLE `withdrawal_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `method_code` (`method_code`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `sort_order` (`sort_order`);

--
-- Indexes for table `withdrawal_transactions`
--
ALTER TABLE `withdrawal_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `withdrawal_request_id` (`withdrawal_request_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `admin_account_id` (`admin_account_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_balance_history`
--
ALTER TABLE `account_balance_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `admin_commission_collections`
--
ALTER TABLE `admin_commission_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_payment_accounts`
--
ALTER TABLE `admin_payment_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_system_access`
--
ALTER TABLE `admin_system_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `analytics`
--
ALTER TABLE `analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_pages`
--
ALTER TABLE `analytics_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_sources`
--
ALTER TABLE `analytics_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_schedules`
--
ALTER TABLE `backup_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `category_change_requests`
--
ALTER TABLE `category_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=284;

--
-- AUTO_INCREMENT for table `country_bank_formats`
--
ALTER TABLE `country_bank_formats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `custom_fields`
--
ALTER TABLE `custom_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deleted_products`
--
ALTER TABLE `deleted_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deleted_reviews`
--
ALTER TABLE `deleted_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `import_export_logs`
--
ALTER TABLE `import_export_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_settings`
--
ALTER TABLE `invoice_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_templates`
--
ALTER TABLE `invoice_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_notes`
--
ALTER TABLE `order_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_verification`
--
ALTER TABLE `otp_verification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_audit`
--
ALTER TABLE `payment_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payment_intents`
--
ALTER TABLE `payment_intents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_likes`
--
ALTER TABLE `review_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_reports`
--
ALTER TABLE `review_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `settings_groups`
--
ALTER TABLE `settings_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings_history`
--
ALTER TABLE `settings_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shipping_carriers`
--
ALTER TABLE `shipping_carriers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_updates`
--
ALTER TABLE `system_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_form_requests`
--
ALTER TABLE `tax_form_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `upgrade_requests`
--
ALTER TABLE `upgrade_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_activities`
--
ALTER TABLE `user_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=420;

--
-- AUTO_INCREMENT for table `user_payment_methods`
--
ALTER TABLE `user_payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `vendors_payment_methods`
--
ALTER TABLE `vendors_payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_bank_accounts`
--
ALTER TABLE `vendor_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vendor_cards`
--
ALTER TABLE `vendor_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_categories`
--
ALTER TABLE `vendor_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_categories_selected`
--
ALTER TABLE `vendor_categories_selected`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_category`
--
ALTER TABLE `vendor_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_category_commissions`
--
ALTER TABLE `vendor_category_commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_commissions`
--
ALTER TABLE `vendor_commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vendor_documents`
--
ALTER TABLE `vendor_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vendor_earnings`
--
ALTER TABLE `vendor_earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_messages`
--
ALTER TABLE `vendor_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendor_metrics`
--
ALTER TABLE `vendor_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_mobile_accounts`
--
ALTER TABLE `vendor_mobile_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vendor_payment_methods`
--
ALTER TABLE `vendor_payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `vendor_paypal_accounts`
--
ALTER TABLE `vendor_paypal_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendor_responses`
--
ALTER TABLE `vendor_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_reviews`
--
ALTER TABLE `vendor_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_settings`
--
ALTER TABLE `vendor_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `vendor_shipping_methods`
--
ALTER TABLE `vendor_shipping_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_shipping_settings`
--
ALTER TABLE `vendor_shipping_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_shipping_zones`
--
ALTER TABLE `vendor_shipping_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_stripe_accounts`
--
ALTER TABLE `vendor_stripe_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendor_tax_classes`
--
ALTER TABLE `vendor_tax_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_tax_exemptions`
--
ALTER TABLE `vendor_tax_exemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_tax_rates`
--
ALTER TABLE `vendor_tax_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_tax_settings`
--
ALTER TABLE `vendor_tax_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_withdrawals`
--
ALTER TABLE `vendor_withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_withdrawal_requests`
--
ALTER TABLE `vendor_withdrawal_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_zone_methods`
--
ALTER TABLE `vendor_zone_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `withdrawal_methods`
--
ALTER TABLE `withdrawal_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `withdrawal_transactions`
--
ALTER TABLE `withdrawal_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_balance_history`
--
ALTER TABLE `account_balance_history`
  ADD CONSTRAINT `account_balance_history_ibfk_1` FOREIGN KEY (`admin_account_id`) REFERENCES `admin_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_accounts`
--
ALTER TABLE `admin_accounts`
  ADD CONSTRAINT `admin_accounts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_commission_collections`
--
ALTER TABLE `admin_commission_collections`
  ADD CONSTRAINT `admin_commission_collections_ibfk_1` FOREIGN KEY (`vendor_commission_id`) REFERENCES `vendor_commissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_commission_collections_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_commission_collections_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_commission_collections_ibfk_4` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_commission_collections_ibfk_5` FOREIGN KEY (`admin_account_id`) REFERENCES `admin_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_system_access`
--
ALTER TABLE `admin_system_access`
  ADD CONSTRAINT `admin_system_access_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_system_access_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `categories_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `category_change_requests`
--
ALTER TABLE `category_change_requests`
  ADD CONSTRAINT `category_change_requests_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_change_requests_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_change_requests_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tax_form_requests`
--
ALTER TABLE `tax_form_requests`
  ADD CONSTRAINT `tax_form_requests_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendors_payment_methods`
--
ALTER TABLE `vendors_payment_methods`
  ADD CONSTRAINT `vendors_payment_methods_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_bank_accounts`
--
ALTER TABLE `vendor_bank_accounts`
  ADD CONSTRAINT `vendor_bank_accounts_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_cards`
--
ALTER TABLE `vendor_cards`
  ADD CONSTRAINT `vendor_cards_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_cards_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendor_categories`
--
ALTER TABLE `vendor_categories`
  ADD CONSTRAINT `vendor_categories_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_categories_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendor_categories_selected`
--
ALTER TABLE `vendor_categories_selected`
  ADD CONSTRAINT `vendor_categories_selected_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_categories_selected_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_categories_selected_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendor_category`
--
ALTER TABLE `vendor_category`
  ADD CONSTRAINT `vendor_category_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_category_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_category_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendor_category_commissions`
--
ALTER TABLE `vendor_category_commissions`
  ADD CONSTRAINT `vendor_category_commissions_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_category_commissions_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_category_commissions_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendor_commissions`
--
ALTER TABLE `vendor_commissions`
  ADD CONSTRAINT `vendor_commissions_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_commissions_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_commissions_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_documents`
--
ALTER TABLE `vendor_documents`
  ADD CONSTRAINT `vendor_documents_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_earnings`
--
ALTER TABLE `vendor_earnings`
  ADD CONSTRAINT `vendor_earnings_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_earnings_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_earnings_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_mobile_accounts`
--
ALTER TABLE `vendor_mobile_accounts`
  ADD CONSTRAINT `vendor_mobile_accounts_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_mobile_accounts_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendor_settings`
--
ALTER TABLE `vendor_settings`
  ADD CONSTRAINT `vendor_settings_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_shipping_methods`
--
ALTER TABLE `vendor_shipping_methods`
  ADD CONSTRAINT `vendor_shipping_methods_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_shipping_zones`
--
ALTER TABLE `vendor_shipping_zones`
  ADD CONSTRAINT `vendor_shipping_zones_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_withdrawal_requests`
--
ALTER TABLE `vendor_withdrawal_requests`
  ADD CONSTRAINT `vendor_withdrawal_requests_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_withdrawal_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendor_zone_methods`
--
ALTER TABLE `vendor_zone_methods`
  ADD CONSTRAINT `vendor_zone_methods_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `vendor_shipping_zones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_zone_methods_ibfk_2` FOREIGN KEY (`method_id`) REFERENCES `vendor_shipping_methods` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawal_transactions`
--
ALTER TABLE `withdrawal_transactions`
  ADD CONSTRAINT `withdrawal_transactions_ibfk_1` FOREIGN KEY (`withdrawal_request_id`) REFERENCES `vendor_withdrawal_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawal_transactions_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawal_transactions_ibfk_3` FOREIGN KEY (`admin_account_id`) REFERENCES `admin_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawal_transactions_ibfk_4` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
