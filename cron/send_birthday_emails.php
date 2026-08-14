<?php
/**
 * Emails approved members, team officers, and past leadership a birthday greeting on their birthday.
 * Can be scheduled to run once daily via cPanel Cron or Windows Task Scheduler:
 *   cPanel cron : php /home/username/public_html/cron/send_birthday_emails.php
 */
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/BirthdayManager.php';

function run_birthday_emails(mysqli $conn): void
{
    $bm = new BirthdayManager($conn);
    $res = $bm->checkAndAutoSendBirthdays();
    $count = count($res['today']);
    echo "Checked today's birthdays ($count celebrant(s) found).\n";
}

if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    run_birthday_emails((new Database())->connect());
}
