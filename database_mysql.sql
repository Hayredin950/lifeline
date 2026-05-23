-- LifeLine Blood Network - MySQL Schema

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Table structure for table `users`
--
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'donor',
  `is_active` tinyint(1) DEFAULT '1',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_key` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `achievements`
--
CREATE TABLE IF NOT EXISTS `achievements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text,
  `earned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `achievements_donor_id_type_key` (`donor_id`,`type`),
  CONSTRAINT `achievements_donor_id_fkey` FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `audit_logs`
--
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` text,
  `new_values` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `audit_logs_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `blood_banks`
--
CREATE TABLE IF NOT EXISTS `blood_banks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `working_hours` varchar(100) DEFAULT NULL,
  `has_24h_service` tinyint(1) DEFAULT '0',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `blood_requests`
--
CREATE TABLE IF NOT EXISTS `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT NULL,
  `patient_blood_type` varchar(5) DEFAULT NULL,
  `units_needed` int(11) DEFAULT '1',
  `urgency` varchar(20) DEFAULT 'normal',
  `status` varchar(20) DEFAULT 'open',
  `required_date` date DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `hospital_address` text,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `blood_requests_hospital_id_fkey` FOREIGN KEY (`hospital_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `donation_history`
--
CREATE TABLE IF NOT EXISTS `donation_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) DEFAULT NULL,
  `request_id` int(11) DEFAULT NULL,
  `hospital_id` int(11) DEFAULT NULL,
  `donation_date` date NOT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `units` int(11) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `donation_history_donor_id_fkey` FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `donation_history_hospital_id_fkey` FOREIGN KEY (`hospital_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `donation_history_request_id_fkey` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `donor_matches`
--
CREATE TABLE IF NOT EXISTS `donor_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) DEFAULT NULL,
  `donor_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_matches_request_id_donor_id_key` (`request_id`,`donor_id`),
  CONSTRAINT `donor_matches_donor_id_fkey` FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `donor_matches_request_id_fkey` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `donor_profiles`
--
CREATE TABLE IF NOT EXISTS `donor_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `address` text,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT '1',
  `last_donation_date` date DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `total_donations` int(11) DEFAULT '0',
  `tier` varchar(20) DEFAULT 'bronze',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `donor_profiles_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `hospital_profiles`
--
CREATE TABLE IF NOT EXISTS `hospital_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `hospital_name` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `license_number` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `hospital_profiles_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `password_resets`
--
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `password_resets_email_key` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `testimonials`
--
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `story` text NOT NULL,
  `rating` int(11) DEFAULT '5',
  `is_approved` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `testimonials_donor_id_fkey` FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Seed data for `blood_banks`
--
INSERT INTO `blood_banks` (`id`, `name`, `address`, `city`, `state`, `phone`, `email`, `license_number`, `working_hours`, `has_24h_service`, `latitude`, `longitude`, `created_at`) VALUES
(1, 'AIIMS Blood Bank', 'Ansari Nagar East, New Delhi', 'New Delhi', 'Delhi', '011-26588500', NULL, NULL, '24 Hours', 1, NULL, NULL, CURRENT_TIMESTAMP),
(2, 'Rotary Blood Bank', 'Connaught Place', 'New Delhi', 'Delhi', '011-23366243', NULL, NULL, '8am-8pm', 0, NULL, NULL, CURRENT_TIMESTAMP),
(3, 'KEM Hospital Blood Bank', 'Acharya Donde Marg, Parel', 'Mumbai', 'Maharashtra', '022-24107000', NULL, NULL, '24 Hours', 1, NULL, NULL, CURRENT_TIMESTAMP),
(4, 'Lilavati Hospital Blood Bank', 'Bandra Reclamation', 'Mumbai', 'Maharashtra', '022-26751000', NULL, NULL, '24 Hours', 1, NULL, NULL, CURRENT_TIMESTAMP),
(5, 'Apollo Hospital Blood Bank', 'Greams Road', 'Chennai', 'Tamil Nadu', '044-28293333', NULL, NULL, '24 Hours', 1, NULL, NULL, CURRENT_TIMESTAMP),
(6, 'Nimhans Blood Bank', 'Hosur Road, Bangalore', 'Bangalore', 'Karnataka', '080-46110007', NULL, NULL, '8am-6pm', 0, NULL, NULL, CURRENT_TIMESTAMP),
(7, 'PGI Blood Bank', 'Sector 12, Chandigarh', 'Chandigarh', 'Punjab', '0172-2756565', NULL, NULL, '24 Hours', 1, NULL, NULL, CURRENT_TIMESTAMP),
(8, 'SGPGI Blood Bank', 'Raebareli Road', 'Lucknow', 'Uttar Pradesh', '0522-2494404', NULL, NULL, '24 Hours', 1, NULL, NULL, CURRENT_TIMESTAMP);

--
-- Seed data for `testimonials`
--
INSERT INTO `testimonials` (`id`, `donor_id`, `recipient_name`, `story`, `rating`, `is_approved`, `created_at`) VALUES
(1, NULL, 'Ravi Kumar\'s Family', 'My father needed O- blood urgently after his accident. Within 2 hours, LifeLine matched us with a donor in our city. He survived because of this platform. Forever grateful.', 5, 1, CURRENT_TIMESTAMP),
(2, NULL, 'Dr. Priya Sharma', 'As a hospital administrator, LifeLine has transformed how we handle emergency blood needs. The matching system is incredibly fast and reliable.', 5, 1, CURRENT_TIMESTAMP),
(3, NULL, 'Meera Singh', 'I donated blood for the first time through LifeLine. The process was so simple and knowing I helped save a life is the best feeling in the world.', 5, 1, CURRENT_TIMESTAMP);
