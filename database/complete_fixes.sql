-- ============================================================
-- COMPLETE FIXES SQL - ShopEase Pro E-Commerce
-- Run this in phpMyAdmin after importing ecommerce_db.sql
-- ============================================================

-- Fix collation for all tables
ALTER DATABASE ecommerce_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---- Reviews table: Add title column ----
ALTER TABLE `reviews` ADD COLUMN IF NOT EXISTS `title` varchar(255) DEFAULT NULL AFTER `rating`;
ALTER TABLE `reviews` ADD COLUMN IF NOT EXISTS `vendor_id` int(11) DEFAULT NULL AFTER `user_id`;
ALTER TABLE `reviews` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---- Products table: Add missing columns ----
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `average_rating` decimal(3,2) DEFAULT 0.00 AFTER `stock`;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `review_count` int(11) DEFAULT 0 AFTER `average_rating`;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `five_star_count` int(11) DEFAULT 0;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `four_star_count` int(11) DEFAULT 0;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `three_star_count` int(11) DEFAULT 0;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `two_star_count` int(11) DEFAULT 0;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `one_star_count` int(11) DEFAULT 0;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `approved_status` enum('pending','approved','rejected') DEFAULT 'pending';
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `vendor_id` int(11) DEFAULT NULL;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `is_featured` tinyint(1) DEFAULT 0;

-- ---- Users table: Add missing columns ----
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `subscription_plan` varchar(50) DEFAULT 'free';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `subscription_expiry` datetime DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `login_count` int(11) DEFAULT 0;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_login` datetime DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `profile_pic` varchar(255) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `vendor_category` varchar(100) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `vendor_bio` text DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `vendor_status` enum('pending','approved','rejected','suspended') DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `phone` varchar(20) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `country` varchar(10) DEFAULT NULL;

-- ---- Login attempts table ----
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- User sessions table ----
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `logout_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- User activities table ----
CREATE TABLE IF NOT EXISTS `user_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `activity_type` (`activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- OTP verification table ----
CREATE TABLE IF NOT EXISTS `otp_verification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `otp_type` varchar(20) DEFAULT 'email',
  `expires_at` datetime NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Password resets table ----
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Subscription plans table ----
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `billing_period` varchar(20) DEFAULT 'monthly',
  `features` text DEFAULT NULL,
  `max_products` int(11) DEFAULT -1,
  `max_orders` int(11) DEFAULT -1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `subscription_plans` (`name`, `display_name`, `price`, `features`) VALUES
('free', 'Free Plan', 0.00, 'Basic features'),
('premium', 'Premium Plan', 9.99, 'Advanced features'),
('business', 'Business Plan', 29.99, 'All features');

-- ---- Notifications table ----
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Cart items ----
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Wishlist ----
CREATE TABLE IF NOT EXISTS `wishlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Countries: Insert Pakistan and common countries ----
INSERT IGNORE INTO `countries` (`code`, `name`, `is_active`) VALUES
('PK','Pakistan',1),('US','United States',1),('GB','United Kingdom',1),
('CA','Canada',1),('AU','Australia',1),('IN','India',1),
('AE','United Arab Emirates',1),('SA','Saudi Arabia',1),
('BD','Bangladesh',1),('NG','Nigeria',1),('DE','Germany',1),
('FR','France',1),('IT','Italy',1),('ES','Spain',1),('BR','Brazil',1);

-- ---- Default settings ----
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('site_name', 'ShopEase Pro', 'general'),
('site_email', 'admin@shopeasepro.com', 'general'),
('site_phone', '+92-300-0000000', 'general'),
('currency', 'USD', 'general'),
('currency_symbol', '$', 'general'),
('tax_rate', '0', 'general'),
('shipping_cost', '0', 'shipping');

-- ============================================================
-- END OF FIXES
-- ============================================================
