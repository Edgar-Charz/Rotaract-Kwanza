<?php
/**
 * Emails approved members a birthday greeting on their birthday.
 * Not triggered by the app itself — schedule it to run once daily:
 *   Windows Task Scheduler : php.exe C:\xampp\htdocs\Rotaract_Kwanza\cron\send_birthday_emails.php
 *   Linux/cPanel cron      : php /path/to/Rotaract_Kwanza/cron/send_birthday_emails.php
 */
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Member.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/SiteSettings.php';

function run_birthday_emails(mysqli $conn): void
{
    $club   = (new SiteSettings($conn))->get('site_name', 'Rotaract Kwanza');
    $mailer = Mailer::fromSettings($conn);
    $member = new Member($conn);
    $today  = date('Y-m-d');

    $birthdays = $member->getTodaysBirthdays();
    if (!$birthdays) {
        echo "No birthdays today.\n";
        return;
    }

    foreach ($birthdays as $row) {
        $name = trim($row['first_name'] . ' ' . $row['last_name']);
        $sent = $mailer->birthdayGreeting($row['email'], $name, $club);
        if ($sent) {
            $member->markBirthdayEmailSent((int) $row['id'], $today);
        }
        echo ($sent ? 'Sent' : 'FAILED') . " birthday email to $name <{$row['email']}>\n";
    }
}

if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    run_birthday_emails((new Database())->connect());
}
