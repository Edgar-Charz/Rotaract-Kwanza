<?php
session_start();
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Event.php';
require_once __DIR__ . '/classes/EventRSVP.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$id    = (int) ($_GET['id'] ?? 0);
$event = $id ? (new Event($conn))->findUpcomingById($id) : false;

if (!$event) {
    http_response_code(404);
}

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $event) {
    csrf_verify();

    $name   = trim($_POST['name']   ?? '');
    $email  = trim($_POST['email']  ?? '');
    $phone  = trim($_POST['phone']  ?? '');
    $guests = max(1, (int) ($_POST['guests'] ?? 1));
    $notes  = trim($_POST['notes']  ?? '');

    if (!$name || !$email) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        try {
            $rsvp = new EventRSVP($conn);
            if ($rsvp->alreadyRegistered($id, $email)) {
                $error = "This email has already RSVP'd to this event.";
            } else {
                $rsvp->create($id, $name, $email, $phone, $guests, $notes);
                $success = true;
            }
        } catch (mysqli_sql_exception $e) {
            $error = 'Could not save your RSVP. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RSVP &mdash; <?= $event ? e($event['title']) : 'Event Not Found' ?></title>
  <link rel="icon" type="image/png" href="/Rotaract_Kwanza/assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/Rotaract_Kwanza/assets/css/kwanza.css">
  <style>
    .rsvp-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:80px 20px 40px; background:var(--surface-bg,#fff5f9); }
    .rsvp-card { background:#fff; border-radius:20px; padding:40px; max-width:520px; width:100%; box-shadow:0 8px 40px rgba(192,57,107,0.12); }
    .rsvp-event-header { background:linear-gradient(135deg,var(--pink-600),var(--pink-900)); border-radius:12px; padding:24px; color:#fff; margin-bottom:28px; }
    .rsvp-event-header h2 { font-family:'Cormorant Garamond',serif; font-size:1.5rem; font-weight:700; margin:0 0 8px; }
    .rsvp-meta { display:flex; gap:16px; flex-wrap:wrap; font-size:13px; opacity:.85; margin-top:10px; }
    .rsvp-meta span { display:flex; align-items:center; gap:5px; }
    .rsvp-form .form-group { margin-bottom:16px; }
    .rsvp-form label { display:block; font-weight:600; font-size:13px; margin-bottom:5px; color:#2d3436; }
    .rsvp-form input, .rsvp-form select, .rsvp-form textarea { width:100%; padding:10px 13px; border:1.5px solid #e0e4ef; border-radius:8px; font-size:13.5px; font-family:inherit; outline:none; transition:border-color .2s; }
    .rsvp-form input:focus, .rsvp-form select:focus, .rsvp-form textarea:focus { border-color:var(--pink-600); }
    .rsvp-form .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .btn-rsvp { width:100%; padding:13px; border:none; border-radius:10px; background:linear-gradient(135deg,var(--pink-600),var(--pink-800)); color:#fff; font-size:15px; font-weight:700; cursor:pointer; font-family:inherit; margin-top:4px; transition:opacity .2s; }
    .btn-rsvp:hover { opacity:.9; }
    .success-box { text-align:center; padding:20px 0; }
    .success-box svg { margin-bottom:14px; }
    .success-box h3 { font-family:'Cormorant Garamond',serif; font-size:1.8rem; margin-bottom:8px; }
    .alert-err { background:#fde8e8; border:1px solid #f5b8be; color:#9b2335; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13.5px; }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="rsvp-wrap">
  <?php if (!$event): ?>
    <div class="rsvp-card" style="text-align:center">
      <h2 style="font-family:'Cormorant Garamond',serif;font-size:2rem;margin-bottom:12px">Event Not Found</h2>
      <p style="color:#636e72;margin-bottom:24px">This event may have ended or the link is incorrect.</p>
      <a href="/Rotaract_Kwanza/events.php" style="color:var(--pink-700);font-weight:600">&#8592; View All Events</a>
    </div>
  <?php elseif ($success): ?>
    <div class="rsvp-card">
      <div class="success-box">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" fill="#d1f2e0" stroke="#27ae60" stroke-width="1.5"/>
          <polyline points="8 12 11 15 16 9" stroke="#27ae60" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h3>You're registered!</h3>
        <p style="color:#636e72;margin-bottom:8px">See you at <strong><?= e($event['title']) ?></strong></p>
        <p style="color:#636e72;font-size:13px">
          <?= date('l, d F Y', strtotime($event['event_date'])) ?><?= $event['event_time'] ? ' &mdash; ' . e($event['event_time']) : '' ?>
        </p>
        <?php if ($event['location']): ?>
          <p style="color:#636e72;font-size:13px;margin-top:4px">&#128205; <?= e($event['location']) ?></p>
        <?php endif; ?>
        <a href="/Rotaract_Kwanza/events.php" style="display:inline-block;margin-top:24px;color:var(--pink-700);font-weight:600">&#8592; View More Events</a>
      </div>
    </div>
  <?php else: ?>
    <div class="rsvp-card">
      <div class="rsvp-event-header">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;opacity:.7;margin-bottom:6px"><?= e($event['category'] ?? 'Event') ?></div>
        <h2><?= e($event['title']) ?></h2>
        <div class="rsvp-meta">
          <span>&#128197; <?= date('D, d M Y', strtotime($event['event_date'])) ?></span>
          <?php if ($event['event_time']): ?><span>&#128336; <?= e($event['event_time']) ?></span><?php endif; ?>
          <?php if ($event['location']): ?><span>&#128205; <?= e($event['location']) ?></span><?php endif; ?>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="alert-err"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="rsvp-form">
        <?= csrf_field() ?>
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="Your name" required></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="your@email.com" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+244 900 000 000"></div>
          <div class="form-group">
            <label>Number of Guests</label>
            <select name="guests">
              <?php for ($g = 1; $g <= 10; $g++): ?>
                <option value="<?= $g ?>" <?= ($g == (int) ($_POST['guests'] ?? 1)) ? 'selected' : '' ?>><?= $g ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Notes / Dietary requirements</label><textarea name="notes" placeholder="Optional" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea></div>
        <button type="submit" class="btn-rsvp">Confirm RSVP &#10003;</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<script src="/Rotaract_Kwanza/assets/js/scripts.js"></script>
</body>
</html>
