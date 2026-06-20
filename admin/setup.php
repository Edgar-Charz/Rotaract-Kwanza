<?php
/**
 * One-time database setup. Delete this file after running.
 * Access: /Rotaract_Kwanza/admin/setup.php?key=rotaract_setup_2024
 */

define('SETUP_KEY', 'rotaract_setup_2024');

if (!isset($_GET['key']) || $_GET['key'] !== SETUP_KEY) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;color:#c0392b">Access Denied</h2><p>Add <code>?key=' . SETUP_KEY . '</code> to run setup.</p>');
}

$adminUser  = 'admin';
$adminPass  = 'Admin@Kwanza2024';
$adminEmail = 'admin@rotaractkwanza.org';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli('localhost', 'root', '');
    $db->set_charset('utf8mb4');

    $db->query("CREATE DATABASE IF NOT EXISTS `rotaract_kwanza` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->select_db('rotaract_kwanza');

    $tables = [
        "CREATE TABLE IF NOT EXISTS `admins` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) UNIQUE NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `email` VARCHAR(100),
            `full_name` VARCHAR(100),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS `members` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `first_name` VARCHAR(50) NOT NULL,
            `last_name` VARCHAR(50) NOT NULL,
            `email` VARCHAR(100) UNIQUE NOT NULL,
            `phone` VARCHAR(30),
            `occupation` VARCHAR(100),
            `why_join` TEXT,
            `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
            `notes` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS `events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(200) NOT NULL,
            `event_date` DATE NOT NULL,
            `event_time` VARCHAR(50),
            `location` VARCHAR(200),
            `description` TEXT,
            `image_path` VARCHAR(255),
            `category` VARCHAR(50) DEFAULT 'General',
            `status` ENUM('upcoming','past','cancelled') DEFAULT 'upcoming',
            `is_featured` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS `gallery` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT,
            `image_path` VARCHAR(255) NOT NULL,
            `category` VARCHAR(50),
            `display_order` INT DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS `projects` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT,
            `impact_stat` VARCHAR(100),
            `impact_label` VARCHAR(100),
            `icon_type` VARCHAR(50) DEFAULT 'default',
            `status` ENUM('active','completed','featured') DEFAULT 'active',
            `is_featured` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS `team_members` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `full_name` VARCHAR(100) NOT NULL,
            `role` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `image_path` VARCHAR(255),
            `email` VARCHAR(100),
            `display_order` INT DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS `contact_messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `subject` VARCHAR(200),
            `message` TEXT NOT NULL,
            `status` ENUM('unread','read','replied') DEFAULT 'unread',
            `admin_notes` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS `site_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) UNIQUE NOT NULL,
            `setting_value` TEXT,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
    ];

    foreach ($tables as $sql) {
        $db->query($sql);
    }

    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT IGNORE INTO admins (username, password_hash, email, full_name) VALUES (?,?,?,?)");
    $stmt->bind_param('ssss', $adminUser, $hash, $adminEmail, $name);
    $name = 'Site Administrator';
    $stmt->execute();
    $stmt->close();

    $settings = [
        ['site_name',           'Rotaract Club of Kwanza'],
        ['contact_email',       'info@rotaractkwanza.org'],
        ['contact_phone',       '+244 900 000 000'],
        ['contact_address',     'Kwanza Community Centre, Kwanza District, Angola'],
        ['facebook_url',        '#'],
        ['instagram_url',       '#'],
        ['twitter_url',         '#'],
        ['linkedin_url',        '#'],
        ['about_text',          'Rotaract Club of Kwanza is a youth-led community service organization established in 2012, dedicated to community service, fellowship, and professional development.'],
        ['hero_stats_members',  '120+'],
        ['hero_stats_projects', '45+'],
        ['hero_stats_lives',    '8K+'],
        ['hero_stats_years',    '12'],
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?,?)");
    foreach ($settings as [$k, $v]) {
        $stmt->bind_param('ss', $k, $v);
        $stmt->execute();
    }
    $stmt->close();
    $db->close();

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>body{font-family:sans-serif;max-width:500px;margin:60px auto;padding:20px}
    h2{color:#27ae60}.creds{background:#f0f9f4;padding:16px;border-radius:8px;margin:16px 0}
    .warn{color:#c0392b;font-weight:bold}a{color:#C0396B}</style></head><body>';
    echo '<h2>&#10003; Database Setup Complete!</h2>';
    echo '<div class="creds"><strong>Admin Login Credentials:</strong><br>';
    echo 'Username: <code>' . $adminUser . '</code><br>';
    echo 'Password: <code>' . $adminPass . '</code></div>';
    echo '<p><a href="/Rotaract_Kwanza/admin/login.php">&rarr; Go to Admin Login</a></p>';
    echo '<p class="warn">&#9888; IMPORTANT: Delete this file (admin/setup.php) immediately after setup!</p>';
    echo '</body></html>';

} catch (mysqli_sql_exception $e) {
    echo '<h2 style="color:#c0392b;font-family:sans-serif">Setup Failed</h2>';
    echo '<pre style="background:#fee;padding:16px;border-radius:8px">' . htmlspecialchars($e->getMessage()) . '</pre>';
}
