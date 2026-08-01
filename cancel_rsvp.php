<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/EventRSVP.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$rsvp_obj = new EventRSVP($conn);
$token    = trim($_GET['token'] ?? $_POST['token'] ?? '');
$rsvp     = $token !== '' ? $rsvp_obj->findByToken($token) : false;

$cancelled = false;

if ($rsvp && $_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $rsvp_obj->delete((int) $rsvp['id']);
  $cancelled = true;
}

$page_title = site_title($conn, 'Cancel RSVP');
$page_description = 'Cancel your RSVP to a Rotaract Club of Kwanza event.';
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
      <?php if (!$rsvp): ?>
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:2rem;margin-bottom:12px">Link Not Found</h2>
        <p style="color:#636e72;margin-bottom:24px">This cancellation link is invalid or has already been used.</p>
        <a href="events.php" style="color:var(--pink-700);font-weight:600">&#8592; View All Events</a>
      <?php elseif ($cancelled): ?>
        <div class="success-box">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" fill="#fde8e8" stroke="#e74c3c" stroke-width="1.5" />
            <path d="M8 8l8 8M16 8l-8 8" stroke="#e74c3c" stroke-width="2.5" stroke-linecap="round" />
          </svg>
          <h3>RSVP Cancelled</h3>
          <p style="color:#636e72">You're no longer registered for <strong><?= e($rsvp['event_title']) ?></strong>.</p>
          <a href="events.php" style="display:inline-block;margin-top:20px;color:var(--pink-700);font-weight:600;text-decoration:none">&#8592; View All Events</a>
        </div>
      <?php else: ?>
        <div class="rsvp-event-header" style="text-align:left">
          <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;opacity:.7;margin-bottom:6px">Cancel RSVP</div>
          <h2><?= e($rsvp['event_title']) ?></h2>
          <div class="rsvp-meta">
            <span>&#128197; <?= date('l, d F Y', strtotime($rsvp['event_date'])) ?></span>
            <?php if ($rsvp['event_time']): ?><span>&#128336; <?= e($rsvp['event_time']) ?></span><?php endif; ?>
          </div>
        </div>
        <p style="color:#636e72;margin-bottom:20px">Are you sure you want to cancel your RSVP for <strong><?= e($rsvp['name']) ?></strong>
          <?= $rsvp['guests'] > 1 ? '(+' . ((int) $rsvp['guests'] - 1) . ' guest' . ($rsvp['guests'] > 2 ? 's' : '') . ')' : '' ?>?</p>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <button type="submit" class="btn-rsvp btn-danger">Yes, Cancel My RSVP</button>
        </form>
        <a href="event.php?id=<?= (int) $rsvp['event_id'] ?>" style="display:inline-block;margin-top:14px;color:var(--pink-700);font-weight:600;text-decoration:none">No, keep my RSVP</a>
      <?php endif; ?>
    </div>
  </div>

  <script src="assets/js/scripts.js"></script>
</body>

</html>
