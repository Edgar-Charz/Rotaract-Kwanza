<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Event.php';
require_once __DIR__ . '/classes/EventRSVP.php';
require_once __DIR__ . '/classes/Member.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();

$id = (int) ($_GET['id'] ?? 0);
$event = $id ? (new Event($conn))->findUpcomingById($id) : false;

if (!$event) {
  http_response_code(404);
}

$rsvp_obj = new EventRSVP($conn);
$guests_used = ($event && $event['capacity'] !== null) ? $rsvp_obj->getGuestCount($id) : 0;
$spots_left = ($event && $event['capacity'] !== null) ? max(0, (int) $event['capacity'] - $guests_used) : null;
$is_full = $spots_left !== null && $spots_left <= 0;

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $event) {
  csrf_verify();

  if (!rate_limit_allow($conn, 'rsvp', 10, 900)) {
    $error = rate_limit_message();
  } else {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $guests = max(1, (int) ($_POST['guests'] ?? 1));
    $guest_names = trim($_POST['guest_names'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$name || !$email) {
      $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Invalid email address.';
    } elseif ($event['capacity'] !== null && ($guests_used + $guests) > (int) $event['capacity']) {
      $error = $spots_left > 0
        ? "Only $spots_left spot" . ($spots_left === 1 ? '' : 's') . " left for this event — please reduce your guest count."
        : 'Sorry, this event has reached full capacity.';
    } else {
      try {
        if ($rsvp_obj->alreadyRegistered($id, $email)) {
          $error = "This email has already RSVP'd to this event.";
        } else {
          // Silently match against the members table so existing members don't
          // have to be re-entered as strangers — no public search/browse of
          // other members is exposed, this only ever matches the visitor's own email.
          $member = (new Member($conn))->findByEmail($email);
          $member_id = $member ? (int) $member['id'] : null;

          $new_rsvp_id = $rsvp_obj->create($id, $name, $email, $phone, $guests, $notes, $member_id, $guest_names);
          $cancel_token = $rsvp_obj->findById($new_rsvp_id)['cancel_token'] ?? '';
          $cancel_url = $cancel_token ? site_origin() . '/cancel_rsvp.php?token=' . urlencode($cancel_token) : '';

          // Send confirmation email (non-fatal)
          try {
            require_once __DIR__ . '/classes/Mailer.php';
            Mailer::fromSettings($conn)->rsvpConfirmation($email, $name, $event, (bool) $member, $cancel_url);
          } catch (Throwable $e) {
          }

          // Carried across the redirect so the confirmation page can show a
          // members-only note, a "join the club" nudge, and cancel/calendar
          // links as appropriate.
          $_SESSION['flash_is_member']   = (bool) $member;
          $_SESSION['flash_cancel_token'] = $cancel_token;

          // Redirect (PRG) so refreshing the confirmation page doesn't resubmit the form
          header('Location: rsvp.php?id=' . $id . '&success=1');
          exit;
        }
      } catch (mysqli_sql_exception $e) {
        $error = 'Could not save your RSVP. Please try again later.';
      }
    }
  }

  // PRG on validation failure too, not just success — otherwise refreshing
  // the response page re-submits the RSVP. Old input rides along in the
  // session so the form doesn't come back empty.
  if ($error !== '') {
    $_SESSION['flash_error'] = $error;
    $_SESSION['flash_old_input'] = $_POST;
    header('Location: rsvp.php?id=' . $id);
    exit;
  }
}

$old = $_SESSION['flash_old_input'] ?? [];
unset($_SESSION['flash_old_input']);
if (isset($_SESSION['flash_error'])) {
  $error = $_SESSION['flash_error'];
  unset($_SESSION['flash_error']);
}
$was_member = $_SESSION['flash_is_member'] ?? false;
unset($_SESSION['flash_is_member']);
$cancel_token = $_SESSION['flash_cancel_token'] ?? '';
unset($_SESSION['flash_cancel_token']);
$ics_href = ($success && $event) ? build_ics_data_uri($event['title'], $event['description'] ?? '', $event['location'] ?? '', $event['event_date'], $event['event_time'] ?? '') : '';

$page_title = $event ? site_title($conn, 'RSVP — ' . $event['title']) : site_title($conn, 'RSVP');
$page_description = $event ? 'RSVP for ' . $event['title'] . ' with Rotaract Club of Kwanza.' : 'This event may have ended or the link is incorrect.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <div class="rsvp-wrap">
    <?php if (!$event): ?>
      <div class="rsvp-card text-center">
        <h2 class="confirm-title">Event Not Found</h2>
        <p class="confirm-text mb-24">This event may have ended or the link is incorrect.</p>
        <a href="events.php" class="confirm-link">&#8592; View All Events</a>
      </div>
    <?php elseif ($success): ?>
      <div class="rsvp-card">
        <div class="success-box">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" fill="#d1f2e0" stroke="#27ae60" stroke-width="1.5" />
            <polyline points="8 12 11 15 16 9" stroke="#27ae60" stroke-width="2.5" stroke-linecap="round"
              stroke-linejoin="round" />
          </svg>
          <h3>You're registered!</h3>
          <p class="confirm-text mb-8">See you at <strong><?= e($event['title']) ?></strong></p>
          <p class="confirm-text fs-13">
            <?= date('l, d F Y', strtotime($event['event_date'])) ?>
            <?= $event['event_time'] ? ' &mdash; ' . e($event['event_time']) : '' ?>
          </p>
          <?php if ($event['location']): ?>
            <p class="confirm-text fs-13 mt-4">&#128205; <?= e($event['location']) ?></p>
          <?php endif; ?>
          <div class="rsvp-action-row">
            <?php if ($ics_href): ?>
              <a href="<?= e($ics_href) ?>" download="<?= e(preg_replace('/[^a-z0-9]+/i', '-', $event['title'])) ?>.ics"
                class="calendar-pill">
                &#128197; Add to Calendar
              </a>
            <?php endif; ?>
            <?php if ($cancel_token): ?>
              <a href="cancel_rsvp.php?token=<?= e($cancel_token) ?>"
                class="withdraw-pill">
                Cancel RSVP
              </a>
            <?php endif; ?>
          </div>
          <?php if (!$was_member): ?>
            <div class="join-nudge-box">
              <p class="join-nudge-title">Enjoyed being here?</p>
              <p class="join-nudge-text">We'd love to have you join the club as a full
                member.</p>
              <a href="join.php" class="btn-rsvp btn-rsvp--pill-link">Join the Club
                &rarr;</a>
            </div>
          <?php endif; ?>
          <a href="events.php"
            class="confirm-link-block mt-24">&#8592;
            View More Events</a>
        </div>
      </div>
    <?php else: ?>
      <div class="rsvp-card">
        <div class="rsvp-event-header">
          <div
            class="confirm-event-label">
            <?= e($event['category'] ?? 'Event') ?>
          </div>
          <h2><?= e($event['title']) ?></h2>
          <div class="rsvp-meta">
            <span>&#128197; <?= date('D, d M Y', strtotime($event['event_date'])) ?></span>
            <?php if ($event['event_time']): ?><span>&#128336; <?= e($event['event_time']) ?></span><?php endif; ?>
            <?php if ($event['location']): ?><span>&#128205; <?= e($event['location']) ?></span><?php endif; ?>
          </div>
          <?php if ($spots_left !== null): ?>
            <div class="rsvp-meta mt-6">
              <span
                class="fw-700"><?= $is_full ? 'Fully booked' : $spots_left . ' spot' . ($spots_left === 1 ? '' : 's') . ' left' ?></span>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($error): ?>
          <div class="alert-err"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($is_full): ?>
          <div class="rsvp-full-notice">
            <p class="rsvp-full-title">This event is fully booked</p>
            <p class="confirm-text fs-13-5">All spots have been claimed. Check back in case a spot opens up, or
              explore our other events.</p>
            <a href="events.php" class="rsvp-full-back">&#8592;
              View All Events</a>
          </div>
        <?php else: ?>
          <form method="POST" class="rsvp-form">
            <?= csrf_field() ?>
            <div class="form-row">
              <div class="form-group"><label for="rsvp_name">Full Name *</label><input type="text" id="rsvp_name" name="name"
                  value="<?= e($old['name'] ?? '') ?>" placeholder="Your name" required></div>
              <div class="form-group"><label for="rsvp_email">Email *</label><input type="email" id="rsvp_email" name="email"
                  value="<?= e($old['email'] ?? '') ?>" placeholder="your@email.com" required></div>
            </div>
            <div class="form-row">
              <div class="form-group"><label for="rsvp_phone">Phone</label><input type="tel" id="rsvp_phone" name="phone"
                  value="<?= e($old['phone'] ?? '') ?>" placeholder="+244 900 000 000"></div>
              <div class="form-group">
                <label for="rsvp_guests">Number of Guests</label>
                <select id="rsvp_guests" name="guests">
                  <?php $max_guests = $spots_left !== null ? max(1, min(10, $spots_left)) : 10; ?>
                  <?php for ($g = 1; $g <= $max_guests; $g++): ?>
                    <option value="<?= $g ?>" <?= ($g == (int) ($old['guests'] ?? 1)) ? 'selected' : '' ?>><?= $g ?></option>
                  <?php endfor; ?>
                </select>
              </div>
            </div>
            <div class="form-group"><label for="rsvp_guest_names">Names of people you're bringing <span
                  class="optional-hint-gray">(optional)</span></label>
              <input type="text" id="rsvp_guest_names" name="guest_names" value="<?= e($old['guest_names'] ?? '') ?>"
                placeholder="e.g. Jane Doe, Mike Smith">
            </div>
            <div class="form-group"><label for="rsvp_notes">Notes / Dietary requirements</label><textarea id="rsvp_notes" name="notes" placeholder="Optional"
                rows="3"><?= e($old['notes'] ?? '') ?></textarea></div>
            <button type="submit" class="btn-rsvp">Confirm RSVP &#10003;</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <script src="assets/js/scripts.js"></script>
</body>

</html>