<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/NewsletterSubscriber.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/helpers.php';

// POST-only handler for the "get notified of new posts" form embedded on
// news.php and in the site footer — no standalone page of its own, it just
// redirects back to wherever the form was submitted from.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: news.php');
    exit;
}

$db   = new Database();
$conn = $db->connect();

csrf_verify();

// Only a bare "name.php" is ever accepted — no slashes, no scheme, no host —
// so this can never be turned into an open redirect via a crafted "redirect" value.
$redirect = $_POST['redirect'] ?? 'news.php';
if (!preg_match('/^[a-zA-Z0-9_-]+\.php$/', $redirect) || !is_file(__DIR__ . '/' . $redirect)) {
    $redirect = 'news.php';
}

if (!rate_limit_allow($conn, 'subscribe', 5, 900)) {
    $_SESSION['flash_error'] = rate_limit_message();
    header('Location: ' . $redirect);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Please enter a valid email address.';
    header('Location: ' . $redirect);
    exit;
}

$subscribers = new NewsletterSubscriber($conn);
$alreadyIn   = (bool) $subscribers->findByEmail($email);
$subscribers->create($email);

if (!$alreadyIn) {
    $subscriber  = $subscribers->findByEmail($email);
    $club        = (new SiteSettings($conn))->get('site_name', 'Rotaract Kwanza');
    $unsubscribe = site_origin() . '/unsubscribe.php?token=' . urlencode($subscriber['unsubscribe_token'] ?? '');
    try {
        require_once __DIR__ . '/classes/Mailer.php';
        Mailer::fromSettings($conn)->newsletterConfirmation($email, $unsubscribe, $club);
    } catch (Throwable $e) {
    }
}

$_SESSION['flash_message'] = $alreadyIn
    ? "You're already subscribed with that email."
    : 'Subscribed! Check your inbox for a confirmation.';
header('Location: ' . $redirect);
exit;
