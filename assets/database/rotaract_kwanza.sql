-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 12, 2026 at 04:44 PM
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
-- Database: `rotaract_kwanza`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT 0,
  `admin_username` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `admin_id`, `admin_username`, `action`, `description`, `ip_address`, `created_at`) VALUES
(5, 1, 'admin', 'add_project', 'Added project: Damu salama', '::1', '2026-06-15 07:47:50'),
(6, 1, 'admin', 'edit_project', 'Edited project ID 1: Damu salama', '::1', '2026-06-15 07:48:01'),
(7, 1, 'admin', 'add_event', 'Created event: BaeCation on 2026-06-16', '::1', '2026-06-15 07:58:43'),
(8, 1, 'admin', 'add_gallery', 'Uploaded photo: Club Members', '::1', '2026-06-15 10:36:52'),
(9, 1, 'admin', 'edit_gallery', 'Edited photo ID 9: Club Members', '::1', '2026-06-15 10:37:37'),
(10, 1, 'admin', 'edit_gallery', 'Edited photo ID 5: 1231', '::1', '2026-06-15 10:37:48'),
(11, 1, 'admin', 'edit_gallery', 'Edited photo ID 4: ads', '::1', '2026-06-15 10:37:59'),
(12, 1, 'admin', 'edit_gallery', 'Edited photo ID 8: kjhgkj', '::1', '2026-06-15 10:38:12'),
(13, 1, 'admin', 'edit_gallery', 'Edited photo ID 2: Fav', '::1', '2026-06-15 10:38:18'),
(14, 1, 'admin', 'update_settings', 'Updated site settings', '::1', '2026-07-05 20:05:55'),
(15, 1, 'admin', 'update_role', 'Set admin ID 3 role to super_admin', '::1', '2026-07-05 20:06:24'),
(16, 1, 'admin', 'update_role', 'Set admin ID 3 role to viewer', '::1', '2026-07-05 20:06:30'),
(17, 3, 'kwanza_admin', 'update_settings', 'Updated site settings', '::1', '2026-07-05 20:07:29'),
(18, 3, 'kwanza_admin', 'update_settings', 'Updated site settings', '::1', '2026-07-05 20:08:22'),
(19, 1, 'admin', 'edit_event', 'Edited event ID 1: BaeCation', '::1', '2026-07-05 20:09:35'),
(20, 1, 'admin', 'update_settings', 'Updated site settings', '::1', '2026-07-05 20:10:23'),
(21, 3, 'kwanza_admin', 'update_settings', 'Updated site settings', '::1', '2026-07-05 20:15:22'),
(22, 3, 'kwanza_admin', 'update_settings', 'Updated site settings', '::1', '2026-07-05 20:23:55'),
(23, 3, 'kwanza_admin', 'update_member_status', 'Set Rebecca Joseph → approved', '::1', '2026-07-05 20:33:39'),
(24, 3, 'kwanza_admin', 'edit_member', 'Edited member ID 1: edgar charles', '::1', '2026-07-05 20:34:15'),
(25, 1, 'admin', 'edit_event', 'Edited event ID 1: BaeCation', '::1', '2026-07-06 17:06:26'),
(26, 1, 'admin', 'add_announcement', 'Added: Board Meetin', '::1', '2026-07-06 17:08:50'),
(27, 1, 'admin', 'edit_announcement', 'Edited: Board Meetin', '::1', '2026-07-06 17:09:21'),
(28, 1, 'admin', 'add_event_photos', '5 photo(s) added to event: BaeCation', '::1', '2026-07-06 17:23:57'),
(29, 1, 'admin', 'add_event', 'Created event: Installation on 2026-07-07', '::1', '2026-07-06 17:29:02'),
(30, 1, 'admin', 'edit_event', 'Edited event ID 2: Installation', '::1', '2026-07-06 17:29:14'),
(31, 1, 'admin', 'edit_member', 'Edited member ID 1: edgar charles', '::1', '2026-07-06 17:39:50'),
(32, 1, 'admin', 'export_members', 'Exported members list to CSV (2 records)', '::1', '2026-07-06 17:44:25'),
(33, 1, 'admin', 'add_event_photos', '5 photo(s) added to event: Installation', '::1', '2026-07-06 17:49:25'),
(34, 1, 'admin', 'add_project_photos', '3 photo(s) added to project: Damu salama', '::1', '2026-07-06 18:06:42'),
(35, 1, 'admin', 'edit_project', 'Edited project ID 1: Damu salama', '::1', '2026-07-06 18:06:52'),
(36, 1, 'admin', 'update_dues', 'Updated dues for Rebecca Joseph — 2026 — paid', '::1', '2026-07-11 13:38:34'),
(37, 1, 'admin', 'update_dues', 'Updated dues for Rebecca Joseph — 2026 — paid', '::1', '2026-07-11 13:38:43'),
(38, 1, 'admin', 'update_dues', 'Updated dues for edgar charles — 2026 — paid', '::1', '2026-07-11 13:39:04'),
(39, 1, 'admin', 'update_dues', 'Updated dues for edgar charles — 2026 — partial', '::1', '2026-07-11 13:39:24'),
(40, 1, 'admin', 'edit_announcement', 'Edited: Board Meeting', '::1', '2026-07-11 13:40:33'),
(41, 1, 'admin', 'update_role', 'Set admin ID 3 role to editor', '::1', '2026-07-11 13:41:06'),
(42, 1, 'admin', 'update_role', 'Set admin ID 3 role to viewer', '::1', '2026-07-11 13:41:12'),
(43, 1, 'admin', 'edit_member', 'Edited member ID 1: edgar charles', '::1', '2026-07-11 14:10:02'),
(44, 1, 'admin', 'edit_member', 'Edited member ID 1: edgar charles', '::1', '2026-07-11 14:25:50'),
(45, 1, 'admin', 'export_report', 'Exported reports & analytics summary to CSV', '::1', '2026-07-11 14:42:18'),
(46, 1, 'admin', 'edit_team', 'Edited team member ID 3: Becky Jay', '::1', '2026-07-11 15:31:07'),
(47, 1, 'admin', 'update_settings', 'Updated site settings', '::1', '2026-07-11 15:52:10'),
(48, 1, 'admin', 'update_settings', 'Updated site settings', '::1', '2026-07-11 15:52:53'),
(49, 1, 'admin', 'update_settings', 'Updated site settings', '::1', '2026-07-11 15:54:23'),
(50, 1, 'admin', 'update_settings', 'Updated site settings', '::1', '2026-07-11 16:16:45'),
(51, 1, 'admin', 'export_messages', 'Exported messages list to CSV (0 records)', '::1', '2026-07-11 18:39:48'),
(52, 1, 'admin', 'update_settings', 'Updated site settings', '::1', '2026-07-11 19:46:04'),
(53, 1, 'admin', 'update_settings', 'Updated site settings', '::1', '2026-07-11 19:46:18'),
(54, 1, 'admin', 'update_settings', 'Updated site settings', '::1', '2026-07-11 19:46:55'),
(55, 1, 'admin', 'update_settings', 'Updated site settings', '::1', '2026-07-11 19:47:55'),
(56, 1, 'admin', 'edit_team', 'Edited team member ID 3: Becky Jay', '::1', '2026-07-11 19:50:45'),
(57, 1, 'admin', 'update_role', 'Set admin ID 3 role to viewer', '::1', '2026-07-11 19:53:09');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('super_admin','editor','viewer') NOT NULL DEFAULT 'super_admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `email`, `full_name`, `created_at`, `role`) VALUES
(1, 'admin', '$2y$10$5VXc38eWD2Ye4rtcCzGgPOnlgIlD6wbJXjwS0dFbfHfPf7QYD5fj.', 'admin@rotaractkwanza.org', 'Site Administrator', '2026-06-02 15:54:09', 'super_admin'),
(3, 'kwanza_admin', '$2y$10$5VXc38eWD2Ye4rtcCzGgPOnlgIlD6wbJXjwS0dFbfHfPf7QYD5fj.', 'admin2@rotaractkwanza.org', 'Site Administrator 2', '2026-07-05 20:00:30', 'viewer');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(220) DEFAULT NULL,
  `content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `category` enum('news','minutes','notice','announcement') DEFAULT 'news',
  `is_published` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `slug`, `content`, `image_path`, `category`, `is_published`, `created_at`, `updated_at`) VALUES
(1, 'Board Meeting', 'board-meetin-843b', '<p><strong><em>We will have a board meeting this friday</em></strong></p>', '/Rotaract_Kwanza/admin/uploads/announcements/img_6a4be12229bed5.03871946.jpg', 'minutes', 1, '2026-07-06 17:08:50', '2026-07-11 13:40:33');

-- --------------------------------------------------------

--
-- Table structure for table `club_values`
--

CREATE TABLE `club_values` (
  `id` int(11) NOT NULL,
  `icon_key` varchar(30) NOT NULL DEFAULT 'heart',
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `club_values`
--

INSERT INTO `club_values` (`id`, `icon_key`, `title`, `description`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'heart', 'Fellowship & Community', 'Building meaningful friendships and networks among young leaders across all walks of life.', 1, 1, '2026-07-11 13:49:18', '2026-07-11 13:49:18'),
(2, 'star', 'Professional Development', 'Empowering members with skills, mentorship, and opportunities to grow as future leaders.', 2, 1, '2026-07-11 13:49:18', '2026-07-11 13:49:18'),
(3, 'clock', 'Service Above Self', 'Dedicating our time and talent to uplifting lives through impactful community service projects.', 3, 1, '2026-07-11 13:49:18', '2026-07-11 13:49:18');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` varchar(50) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `tiktok_url` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'General',
  `status` enum('upcoming','past','cancelled') DEFAULT 'upcoming',
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `event_date`, `event_time`, `location`, `description`, `image_path`, `instagram_url`, `tiktok_url`, `category`, `status`, `is_featured`, `created_at`, `updated_at`) VALUES
(1, 'BaeCation', '2026-06-16', '9:00', 'Masaki', 'Baecation for enjoying everybody with their lovers.', '/Rotaract_Kwanza/admin/uploads/events/img_6a4ab9ff64dc79.76034171.jpg', NULL, NULL, 'General', 'past', 1, '2026-06-15 07:58:43', '2026-07-06 17:06:26'),
(2, 'Installation', '2026-07-07', '9:00', 'Masaki', 'We will install a new president of Rotaract Club of Kwanza', '/Rotaract_Kwanza/admin/uploads/events/img_6a4be5dead98e4.72574804.png', NULL, NULL, 'General', 'upcoming', 1, '2026-07-06 17:29:02', '2026-07-06 17:29:14');

-- --------------------------------------------------------

--
-- Table structure for table `event_photos`
--

CREATE TABLE `event_photos` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_photos`
--

INSERT INTO `event_photos` (`id`, `event_id`, `image_path`, `display_order`, `created_at`) VALUES
(1, 1, '/Rotaract_Kwanza/admin/uploads/event_photos/img_6a4be4ade28e54.57525758.jpg', 0, '2026-07-06 17:23:57'),
(2, 1, '/Rotaract_Kwanza/admin/uploads/event_photos/img_6a4be4ade397a2.50128309.png', 0, '2026-07-06 17:23:57'),
(3, 1, '/Rotaract_Kwanza/admin/uploads/event_photos/img_6a4be4ade41485.17825513.png', 0, '2026-07-06 17:23:57'),
(4, 1, '/Rotaract_Kwanza/admin/uploads/event_photos/img_6a4be4ade47791.31137881.png', 0, '2026-07-06 17:23:57'),
(5, 1, '/Rotaract_Kwanza/admin/uploads/event_photos/img_6a4be4ade4f583.84952797.png', 0, '2026-07-06 17:23:57'),
(6, 2, '/Rotaract_Kwanza/admin/uploads/event_photos/img_6a4beaa5a10c31.17736365.jpg', 0, '2026-07-06 17:49:25'),
(7, 2, '/Rotaract_Kwanza/admin/uploads/event_photos/img_6a4beaa5a216a1.80118840.jpg', 0, '2026-07-06 17:49:25'),
(8, 2, '/Rotaract_Kwanza/admin/uploads/event_photos/img_6a4beaa5a2d811.99064830.jpg', 0, '2026-07-06 17:49:25'),
(9, 2, '/Rotaract_Kwanza/admin/uploads/event_photos/img_6a4beaa5a39324.93409344.jpg', 0, '2026-07-06 17:49:25'),
(10, 2, '/Rotaract_Kwanza/admin/uploads/event_photos/img_6a4beaa5a43051.26507072.jpg', 0, '2026-07-06 17:49:25');

-- --------------------------------------------------------

--
-- Table structure for table `event_rsvps`
--

CREATE TABLE `event_rsvps` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `guests` int(11) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attended` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_rsvps`
--

INSERT INTO `event_rsvps` (`id`, `event_id`, `name`, `email`, `phone`, `guests`, `notes`, `created_at`, `attended`) VALUES
(1, 1, 'Edgar Charles', 'edgarcharles360@gmail.com', '+255716789012', 2, '', '2026-06-15 15:24:14', 1),
(2, 2, 'Edgar Charles', 'edgarcharles360@gmail.com', '+255679799406', 1, '', '2026-07-06 17:29:55', 1);

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gallery`
--

INSERT INTO `gallery` (`id`, `title`, `description`, `image_path`, `category`, `display_order`, `is_active`, `created_at`) VALUES
(2, 'Fav', '', '/Rotaract_Kwanza/admin/uploads/gallery/img_6a1f0111544ca6.65984670.jpg', '', 2, 1, '2026-06-02 16:13:05'),
(3, 'Pedri', '', '/Rotaract_Kwanza/admin/uploads/gallery/img_6a1f02fd040396.84669495.jpg', '', 2, 1, '2026-06-02 16:21:17'),
(4, 'ads', '', '/Rotaract_Kwanza/admin/uploads/gallery/img_6a1f031cae0931.39829091.jpg', '', 2, 1, '2026-06-02 16:21:48'),
(5, '1231', '', '/Rotaract_Kwanza/admin/uploads/gallery/img_6a1f03296a2ee6.85580963.jpg', '', 2, 1, '2026-06-02 16:22:01'),
(6, 'hghj', '', '/Rotaract_Kwanza/admin/uploads/gallery/img_6a1f0345110653.13394121.jpg', '', 2, 1, '2026-06-02 16:22:29'),
(8, 'kjhgkj', '', '/Rotaract_Kwanza/admin/uploads/gallery/img_6a1f049084f131.78335162.jpg', '', 2, 1, '2026-06-02 16:28:00'),
(9, 'Club Members', 'Meet my Club Members', '/Rotaract_Kwanza/admin/uploads/gallery/img_6a2fd5c4e52703.70571113.jpg', 'Events', 2, 1, '2026-06-15 10:36:52');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `username` varchar(50) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`username`, `attempts`, `locked_until`, `updated_at`) VALUES
('admin@udsm.ac.tz', 1, NULL, '2026-07-06 17:02:57');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `why_join` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `show_in_directory` tinyint(1) NOT NULL DEFAULT 0,
  `photo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `first_name`, `last_name`, `email`, `phone`, `occupation`, `bio`, `linkedin_url`, `instagram_url`, `why_join`, `status`, `notes`, `created_at`, `updated_at`, `show_in_directory`, `photo_path`) VALUES
(1, 'edgar', 'charles', 'edgarcharles360@gmail.com', '0679799406', 'Software Engineer', 'Hardworker', 'https://edgar', 'https://edgar', 'Excited', 'approved', 'I like this guy', '2026-06-02 16:09:06', '2026-07-11 14:25:50', 1, '/Rotaract_Kwanza/admin/uploads/members/img_6a4be8660625e6.26766596.png'),
(2, 'Rebecca', 'Joseph', 'becky@gmail.com', '+255696123478', 'Computer Science Student', NULL, NULL, NULL, 'I love to give back to the community', 'approved', '', '2026-06-15 10:31:56', '2026-07-11 14:46:20', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `membership_perks`
--

CREATE TABLE `membership_perks` (
  `id` int(11) NOT NULL,
  `icon_key` varchar(30) NOT NULL DEFAULT 'people',
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_perks`
--

INSERT INTO `membership_perks` (`id`, `icon_key`, `title`, `description`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'people', 'Global Network', 'Connect with over 220,000 Rotaractors in 9,600+ clubs worldwide.', 1, 1, '2026-07-11 13:49:18', '2026-07-11 13:49:18'),
(2, 'book', 'Leadership Growth', 'Develop practical leadership and professional skills through hands-on experience.', 2, 1, '2026-07-11 13:49:18', '2026-07-11 13:49:18'),
(3, 'shield', 'Community Impact', 'Lead and participate in meaningful service projects that transform lives.', 3, 1, '2026-07-11 13:49:18', '2026-07-11 13:49:18'),
(4, 'calendar', 'Exciting Events', 'Attend social gatherings, workshops, conferences, and cultural events.', 4, 1, '2026-07-11 13:49:18', '2026-07-11 13:49:18');

-- --------------------------------------------------------

--
-- Table structure for table `member_dues`
--

CREATE TABLE `member_dues` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `amount_due` decimal(10,2) DEFAULT 0.00,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `member_dues`
--

INSERT INTO `member_dues` (`id`, `member_id`, `year`, `amount_due`, `amount_paid`, `payment_date`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '2026', 0.00, 10000.00, '2026-06-02', 'full paid', 'partial', '2026-06-02 16:56:11', '2026-07-11 13:39:24'),
(2, 2, '2026', 5000.00, 5000.00, '2026-07-11', 'Monthly payment', 'paid', '2026-07-11 13:38:34', '2026-07-11 13:38:43');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `impact_stat` varchar(100) DEFAULT NULL,
  `impact_label` varchar(100) DEFAULT NULL,
  `icon_type` varchar(50) DEFAULT 'default',
  `status` enum('active','completed','featured') DEFAULT 'active',
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `tiktok_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `description`, `impact_stat`, `impact_label`, `icon_type`, `status`, `is_featured`, `created_at`, `updated_at`, `image_path`, `instagram_url`, `tiktok_url`) VALUES
(1, 'Damu salama', 'Tulienda kuchangia damu katika hospitali ya taifa Muhimbili, ili kuwezesha ama kurahisisha upatikanaji wa damu kwa wahitaji.', '120', 'Patients reached', 'default', 'completed', 1, '2026-06-15 07:47:50', '2026-07-06 18:06:52', '/Rotaract_Kwanza/admin/uploads/projects/img_6a4beebc55dea3.59047373.png', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_photos`
--

CREATE TABLE `project_photos` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_photos`
--

INSERT INTO `project_photos` (`id`, `project_id`, `image_path`, `display_order`, `created_at`) VALUES
(1, 1, '/Rotaract_Kwanza/admin/uploads/project_photos/img_6a4beeb26b9502.78767967.png', 0, '2026-07-06 18:06:42'),
(2, 1, '/Rotaract_Kwanza/admin/uploads/project_photos/img_6a4beeb26c67b1.07454854.png', 0, '2026-07-06 18:06:42'),
(3, 1, '/Rotaract_Kwanza/admin/uploads/project_photos/img_6a4beeb26d0ce5.06826196.png', 0, '2026-07-06 18:06:42');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'site_name', 'Rotaract Club of Kwanza - UDSM', '2026-07-11 19:47:55'),
(2, 'contact_email', 'info@rotaractkwanza.org', '2026-06-02 15:54:09'),
(3, 'contact_phone', '+244 900 000 000', '2026-06-02 15:54:09'),
(4, 'contact_address', 'Kwanza Community Centre, Kwanza District, Dar Es Salaam, Tanzania', '2026-06-02 16:16:48'),
(5, 'facebook_url', '#', '2026-06-02 15:54:09'),
(6, 'instagram_url', '#', '2026-06-02 15:54:09'),
(7, 'twitter_url', '#', '2026-06-02 15:54:09'),
(8, 'linkedin_url', '#', '2026-06-02 15:54:09'),
(9, 'about_text', 'Rotaract Club of Kwanza is a youth-led community service organization established in 8th August 2001, dedicated to community service, fellowship, and professional development.', '2026-07-11 19:46:55'),
(10, 'hero_stats_members', '60+', '2026-06-02 16:17:05'),
(11, 'hero_stats_projects', '45+', '2026-06-02 15:54:09'),
(12, 'hero_stats_lives', '8K+', '2026-06-02 15:54:09'),
(13, 'hero_stats_years', '25', '2026-07-11 16:16:45'),
(92, 'mail_from_name', '', '2026-07-05 20:05:55'),
(93, 'mail_from_email', '', '2026-07-05 20:05:55'),
(95, 'about_image', '/Rotaract_Kwanza/admin/uploads/site/img_6a4ab923a8dfe9.55506171.jpg', '2026-07-05 20:05:55'),
(145, 'hero_image', '/Rotaract_Kwanza/admin/uploads/site/img_6a4abd5b1da241.01310404.jpg', '2026-07-05 20:23:55'),
(159, 'founding_year', '2001', '2026-07-11 15:52:10'),
(160, 'motto_text', 'Service Above Self', '2026-07-11 15:54:23'),
(161, 'mission_text', 'To provide young leaders with a platform to develop professional and leadership skills while addressing the physical and social needs of our community through impactful, hands-on service.', '2026-07-11 15:54:23'),
(162, 'sponsor_club', 'Rotary Club of Bahari', '2026-07-11 15:52:10'),
(163, 'sponsor_club_url', '', '2026-07-11 15:52:10'),
(164, 'meeting_day', 'Every Friday', '2026-07-11 15:52:10'),
(165, 'meeting_time', '6:00 PM', '2026-07-11 15:52:10'),
(166, 'meeting_location', 'Royal Oven, Survey', '2026-07-11 15:52:53'),
(167, 'hero_badge_year', '', '2026-07-11 15:52:10'),
(168, 'hero_badge_label', '', '2026-07-11 15:52:10'),
(271, 'brand_initials', 'RK', '2026-07-11 19:46:04'),
(272, 'footer_description', 'A vibrant community of young leaders united by the spirit of service, fellowship, and positive change in Kwanza and beyond.', '2026-07-11 19:46:04'),
(273, 'footer_tagline', 'Made with ♥ for community & service', '2026-07-11 19:46:04'),
(274, 'contact_hours', 'Mon – Fri, 8:00 AM – 5:00 PM', '2026-07-11 19:46:04'),
(275, 'hero_eyebrow', 'Rotary International · Kwanza', '2026-07-11 19:46:55'),
(276, 'hero_title', 'Serving Communities, Changing Lives', '2026-07-11 19:46:04'),
(277, 'hero_subtitle', 'Together we make a difference', '2026-07-11 19:46:04'),
(278, 'hero_description', 'The Rotaract Club of Kwanza is a vibrant community of young leaders committed to fellowship, professional development, and meaningful service to our community and beyond.', '2026-07-11 19:46:04'),
(279, 'home_about_highlight', 'Over a decade of community service and fellowship in Kwanza', '2026-07-11 19:46:04'),
(280, 'home_about_description', 'The Rotaract Club of Kwanza is a Rotary International-sponsored organization bringing together young professionals and leaders aged 18–30 to create lasting change in our community.', '2026-07-11 19:46:04'),
(281, 'home_events_description', 'Discover our next service days, leadership forums, and fellowship celebrations. Join Rotaract Kwanza for meaningful impact.', '2026-07-11 19:46:04'),
(282, 'home_team_description', 'Passionate, driven young leaders who dedicate their time to making a difference in Kwanza.', '2026-07-11 19:46:04'),
(283, 'home_join_description', 'Join a community of passionate young leaders making real change in Kwanza. Membership is open to all aged 18–30.', '2026-07-11 19:46:04'),
(284, 'contact_intro', 'Whether you have a question, partnership opportunity, or just want to say hello — our doors are always open.', '2026-07-11 19:46:04');

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` varchar(100) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `term` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `full_name`, `role`, `role_id`, `term`, `description`, `image_path`, `email`, `linkedin_url`, `instagram_url`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 'Frenkie De Jong', 'Midfilder', NULL, NULL, 'Creativity and vison at work. Discipline is the key...', '/Rotaract_Kwanza/admin/uploads/team/img_6a1f03fba8e569.70139408.jpg', 'dejomg@gmail.com', NULL, NULL, 2, 1, '2026-06-02 16:25:31', '2026-07-11 15:09:51'),
(3, 'Becky Jay', 'President', 1, '', 'Committed to success', '/Rotaract_Kwanza/admin/uploads/team/img_6a529e95b271d2.81255588.jpg', 'becky@gmail.com', '', '', 1, 1, '2026-06-03 18:24:45', '2026-07-11 19:50:45');

-- --------------------------------------------------------

--
-- Table structure for table `team_roles`
--

CREATE TABLE `team_roles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `tier_label` varchar(100) NOT NULL DEFAULT 'Team Members',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_roles`
--

INSERT INTO `team_roles` (`id`, `name`, `tier_label`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'President', 'Leadership', 10, 1, '2026-07-11 16:55:18', '2026-07-11 16:55:18'),
(2, 'Vice President', 'Executive Committee', 20, 1, '2026-07-11 16:55:18', '2026-07-11 16:55:18'),
(3, 'Secretary', 'Executive Committee', 21, 1, '2026-07-11 16:55:18', '2026-07-11 16:55:18'),
(4, 'Treasurer', 'Executive Committee', 22, 1, '2026-07-11 16:55:18', '2026-07-11 16:55:18'),
(5, 'Service Director', 'Directors & Coordinators', 30, 1, '2026-07-11 16:55:18', '2026-07-11 16:55:18'),
(6, 'Membership Director', 'Directors & Coordinators', 31, 1, '2026-07-11 16:55:18', '2026-07-11 16:55:18'),
(7, 'Committee Chair', 'Directors & Coordinators', 32, 1, '2026-07-11 16:55:18', '2026-07-11 16:55:18'),
(8, 'Team Member', 'Team Members', 40, 1, '2026-07-11 16:55:18', '2026-07-11 16:55:18');

--
-- Table structure for table `leadership_terms`
--

CREATE TABLE `leadership_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_label` varchar(50) NOT NULL,
  `year_start` smallint(6) DEFAULT NULL,
  `year_end` smallint(6) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `leadership_members`
--

CREATE TABLE `leadership_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `club_values`
--
ALTER TABLE `club_values`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_photos`
--
ALTER TABLE `event_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `event_rsvps`
--
ALTER TABLE `event_rsvps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leadership_terms`
--
ALTER TABLE `leadership_terms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leadership_members`
--
ALTER TABLE `leadership_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `term_id` (`term_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `membership_perks`
--
ALTER TABLE `membership_perks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `member_dues`
--
ALTER TABLE `member_dues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member_year` (`member_id`,`year`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_photos`
--
ALTER TABLE `project_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_team_members_role` (`role_id`);

--
-- Indexes for table `team_roles`
--
ALTER TABLE `team_roles`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `club_values`
--
ALTER TABLE `club_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `event_photos`
--
ALTER TABLE `event_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `event_rsvps`
--
ALTER TABLE `event_rsvps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `leadership_terms`
--
ALTER TABLE `leadership_terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leadership_members`
--
ALTER TABLE `leadership_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `membership_perks`
--
ALTER TABLE `membership_perks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `member_dues`
--
ALTER TABLE `member_dues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project_photos`
--
ALTER TABLE `project_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=402;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `team_roles`
--
ALTER TABLE `team_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `event_photos`
--
ALTER TABLE `event_photos`
  ADD CONSTRAINT `event_photos_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_rsvps`
--
ALTER TABLE `event_rsvps`
  ADD CONSTRAINT `event_rsvps_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leadership_members`
--
ALTER TABLE `leadership_members`
  ADD CONSTRAINT `leadership_members_ibfk_1` FOREIGN KEY (`term_id`) REFERENCES `leadership_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `member_dues`
--
ALTER TABLE `member_dues`
  ADD CONSTRAINT `member_dues_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_photos`
--
ALTER TABLE `project_photos`
  ADD CONSTRAINT `project_photos_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `fk_team_members_role` FOREIGN KEY (`role_id`) REFERENCES `team_roles` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
