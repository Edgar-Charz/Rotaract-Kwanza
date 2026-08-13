<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/NewsletterSubscriber.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$subscribers = new NewsletterSubscriber($conn);
$token       = trim($_GET['token'] ?? $_POST['token'] ?? '');
$subscriber  = $token !== '' ? $subscribers->findByToken($token) : false;

$done = false;

if ($subscriber && $_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $subscribers->delete((int) $subscriber['id']);
  $done = true;
}

$page_title = site_title($conn, 'Unsubscribe');
$page_description = 'Unsubscribe from Rotaract Club of Kwanza email updates.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <div class="rsvp-wrap">
    <div class="rsvp-card text-center">
      <?php if (!$subscriber): ?>
        <h2 class="confirm-title">Link Not Found</h2>
        <p class="confirm-text mb-24">This unsubscribe link is invalid or you're already unsubscribed.</p>
        <a href="news.php" class="confirm-link">&#8592; Back to News</a>
      <?php elseif ($done): ?>
        <div class="success-box">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" fill="#fde8e8" stroke="#e74c3c" stroke-width="1.5" />
            <path d="M8 8l8 8M16 8l-8 8" stroke="#e74c3c" stroke-width="2.5" stroke-linecap="round" />
          </svg>
          <h3>Unsubscribed</h3>
          <p class="confirm-text"><?= e($subscriber['email']) ?> will no longer receive email updates from us.</p>
          <a href="news.php" class="confirm-link-block mt-20">&#8592; Back to News</a>
        </div>
      <?php else: ?>
        <h2 class="confirm-title">Unsubscribe?</h2>
        <p class="confirm-text mb-20">Stop sending news updates to <strong><?= e($subscriber['email']) ?></strong>?</p>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <button type="submit" class="btn-rsvp btn-danger">Yes, Unsubscribe</button>
        </form>
        <a href="news.php" class="confirm-link-block mt-14">No, keep me subscribed</a>
      <?php endif; ?>
    </div>
  </div>

  <script src="assets/js/scripts.js"></script>
</body>

</html>