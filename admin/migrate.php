<?php
/**
 * Migration v2 — adds: event_rsvps, member_dues, announcements, activity_log
 * Run once: /Rotaract_Kwanza/admin/migrate.php?key=migrate_v2_2024
 * Delete this file afterwards.
 */

define('MIGRATE_KEY', 'migrate_v2_2024');

if (!isset($_GET['key']) || $_GET['key'] !== MIGRATE_KEY) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;color:#c0392b">Access Denied</h2>');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli('localhost', 'root', '', 'rotaract_kwanza');
    $db->set_charset('utf8mb4');

    $tables = [
        "CREATE TABLE IF NOT EXISTS `event_rsvps` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `event_id`   INT NOT NULL,
            `name`       VARCHAR(100) NOT NULL,
            `email`      VARCHAR(100) NOT NULL,
            `phone`      VARCHAR(30),
            `guests`     INT DEFAULT 1,
            `notes`      TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS `member_dues` (
            `id`           INT AUTO_INCREMENT PRIMARY KEY,
            `member_id`    INT NOT NULL,
            `year`         YEAR NOT NULL,
            `amount_due`   DECIMAL(10,2) DEFAULT 0.00,
            `amount_paid`  DECIMAL(10,2) DEFAULT 0.00,
            `payment_date` DATE,
            `notes`        TEXT,
            `status`       ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
            `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_member_year` (`member_id`,`year`),
            FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS `announcements` (
            `id`           INT AUTO_INCREMENT PRIMARY KEY,
            `title`        VARCHAR(200) NOT NULL,
            `slug`         VARCHAR(220) UNIQUE,
            `content`      TEXT NOT NULL,
            `image_path`   VARCHAR(255),
            `category`     ENUM('news','minutes','notice','announcement') DEFAULT 'news',
            `is_published` TINYINT(1) DEFAULT 0,
            `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS `activity_log` (
            `id`             INT AUTO_INCREMENT PRIMARY KEY,
            `admin_id`       INT DEFAULT 0,
            `admin_username` VARCHAR(50),
            `action`         VARCHAR(100) NOT NULL,
            `description`    TEXT,
            `ip_address`     VARCHAR(45),
            `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
    ];

    foreach ($tables as $sql) {
        $db->query($sql);
    }

    $db->close();

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>body{font-family:sans-serif;max-width:500px;margin:60px auto;padding:20px}
    h2{color:#27ae60}.warn{color:#c0392b;font-weight:bold}a{color:#C0396B}</style></head><body>';
    echo '<h2>&#10003; Migration v2 Complete!</h2>';
    echo '<p>Created tables: <code>event_rsvps</code>, <code>member_dues</code>, <code>announcements</code>, <code>activity_log</code></p>';
    echo '<p><a href="/Rotaract_Kwanza/admin/">&rarr; Back to Dashboard</a></p>';
    echo '<p class="warn">&#9888; Delete this file (admin/migrate.php) now!</p>';
    echo '</body></html>';

} catch (mysqli_sql_exception $e) {
    echo '<h2 style="color:#c0392b;font-family:sans-serif">Migration Failed</h2>';
    echo '<pre style="background:#fee;padding:16px;border-radius:8px">' . htmlspecialchars($e->getMessage()) . '</pre>';
}
