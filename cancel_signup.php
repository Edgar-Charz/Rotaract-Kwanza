<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/ProjectSignup.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$signup_obj = new ProjectSignup($conn);
$token      = trim($_GET['token'] ?? $_POST['token'] ?? '');
$signup     = $token !== '' ? $signup_obj->findByToken($token) : false;

$cancelled = false;

if ($signup && $_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $signup_obj->delete((int) $signup['id']);
  $cancelled = true;
}

$page_title = site_title($conn, 'Withdraw Signup');
$page_description = 'Withdraw your volunteer signup for a Rotaract Club of Kwanza project.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <div class="rsvp-wrap">
    <div class="rsvp-card" style="text-align:center">
      <?php if (!$signup): ?>
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:2rem;margin-bottom:12px">Link Not Found</h2>
        <p style="color:#636e72;margin-bottom:24px">This link is invalid or has already been used.</p>
        <a href="projects.php" style="color:var(--pink-700);font-weight:600">&#8592; View All Projects</a>
      <?php elseif ($cancelled): ?>
        <div class="success-box">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" fill="#fde8e8" stroke="#e74c3c" stroke-width="1.5" />
            <path d="M8 8l8 8M16 8l-8 8" stroke="#e74c3c" stroke-width="2.5" stroke-linecap="round" />
          </svg>
          <h3>Signup Withdrawn</h3>
          <p style="color:#636e72">You've been removed from the volunteer list for <strong><?= e($signup['project_title']) ?></strong>.</p>
          <a href="projects.php" style="display:inline-block;margin-top:20px;color:var(--pink-700);font-weight:600;text-decoration:none">&#8592; View All Projects</a>
        </div>
      <?php else: ?>
        <div class="rsvp-event-header" style="text-align:left">
          <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;opacity:.7;margin-bottom:6px">Withdraw Signup</div>
          <h2><?= e($signup['project_title']) ?></h2>
        </div>
        <p style="color:#636e72;margin-bottom:20px">Are you sure you want to withdraw <strong><?= e($signup['name']) ?></strong> from helping with this project?</p>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <button type="submit" class="btn-rsvp btn-danger">Yes, Withdraw My Signup</button>
        </form>
        <a href="project.php?id=<?= (int) $signup['project_id'] ?>" style="display:inline-block;margin-top:14px;color:var(--pink-700);font-weight:600;text-decoration:none">No, keep me signed up</a>
      <?php endif; ?>
    </div>
  </div>

  <script src="assets/js/scripts.js"></script>
</body>

</html>
