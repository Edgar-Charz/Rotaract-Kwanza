<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Project.php';
require_once __DIR__ . '/classes/ProjectSignup.php';
require_once __DIR__ . '/classes/Member.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();

$id = (int) ($_GET['id'] ?? 0);
$project = $id ? (new Project($conn))->findById($id) : false;

// Completed projects aren't taking volunteers anymore — mirrors how rsvp.php
// only accepts RSVPs for events still in 'upcoming' status.
if ($project && $project['status'] === 'completed') {
  $project = false;
}

if (!$project) {
  http_response_code(404);
}

$signup_obj = new ProjectSignup($conn);

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project) {
  csrf_verify();

  if (!rate_limit_allow($conn, 'project_signup', 10, 900)) {
    $error = rate_limit_message();
  } else {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$name || !$email) {
      $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Invalid email address.';
    } else {
      try {
        if ($signup_obj->alreadySignedUp($id, $email)) {
          $error = "This email has already signed up to help with this project.";
        } else {
          // Silently match against the members table so existing members don't
          // have to be re-entered as strangers — no public search/browse of
          // other members is exposed, this only ever matches the visitor's own email.
          $member    = (new Member($conn))->findByEmail($email);
          $member_id = $member ? (int) $member['id'] : null;

          $new_signup_id = $signup_obj->create($id, $name, $email, $phone, $notes, $member_id);
          $cancel_token = $signup_obj->findById($new_signup_id)['cancel_token'] ?? '';
          $cancel_url = $cancel_token ? site_origin() . '/cancel_signup.php?token=' . urlencode($cancel_token) : '';

          // Send confirmation email (non-fatal)
          try {
            require_once __DIR__ . '/classes/Mailer.php';
            Mailer::fromSettings($conn)->projectSignupConfirmation($email, $name, $project, (bool) $member, $cancel_url);
          } catch (Throwable $e) {
          }

          // Carried across the redirect so the confirmation page can show a
          // members-only note, a "join the club" nudge, and a cancel link
          // as appropriate.
          $_SESSION['flash_is_member']    = (bool) $member;
          $_SESSION['flash_cancel_token'] = $cancel_token;

          // Redirect (PRG) so refreshing the confirmation page doesn't resubmit the form
          header('Location: project_signup.php?id=' . $id . '&success=1');
          exit;
        }
      } catch (mysqli_sql_exception $e) {
        $error = 'Could not save your sign-up. Please try again later.';
      }
    }
  }

  // PRG on validation failure too, not just success — otherwise refreshing
  // the response page re-submits the sign-up. Old input rides along in the
  // session so the form doesn't come back empty.
  if ($error !== '') {
    $_SESSION['flash_error'] = $error;
    $_SESSION['flash_old_input'] = $_POST;
    header('Location: project_signup.php?id=' . $id);
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

$page_title = $project ? site_title($conn, 'Get Involved — ' . $project['title']) : site_title($conn, 'Get Involved');
$page_description = $project ? 'Sign up to help with ' . $project['title'] . ' with Rotaract Club of Kwanza.' : 'This project may have been removed or the link is incorrect.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <div class="rsvp-wrap">
    <?php if (!$project): ?>
      <div class="rsvp-card" style="text-align:center">
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:2rem;margin-bottom:12px">Project Not Found</h2>
        <p style="color:#636e72;margin-bottom:24px">This project may have been removed or the link is incorrect.</p>
        <a href="projects.php" style="color:var(--pink-700);font-weight:600">&#8592; View All Projects</a>
      </div>
    <?php elseif ($success): ?>
      <div class="rsvp-card">
        <div class="success-box">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" fill="#d1f2e0" stroke="#27ae60" stroke-width="1.5" />
            <polyline points="8 12 11 15 16 9" stroke="#27ae60" stroke-width="2.5" stroke-linecap="round"
              stroke-linejoin="round" />
          </svg>
          <h3>You're signed up!</h3>
          <p style="color:#636e72;margin-bottom:8px">Thanks for offering to help with <strong><?= e($project['title']) ?></strong></p>
          <p style="color:#636e72;font-size:13px">An officer will reach out soon with details on how you can get involved.</p>
          <?php if ($cancel_token): ?>
            <div style="margin-top:16px">
              <a href="cancel_signup.php?token=<?= e($cancel_token) ?>"
                style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:20px;background:#fdf0f5;border:1.5px solid #f5d5e3;color:#9b2335;font-size:13px;font-weight:600;text-decoration:none">
                Withdraw Signup
              </a>
            </div>
          <?php endif; ?>
          <?php if (!$was_member): ?>
            <div style="background:#fdf0f5;border-radius:10px;padding:16px 20px;margin-top:20px;text-align:left">
              <p style="font-weight:700;color:#2d3436;margin-bottom:4px">Enjoyed the idea of helping out?</p>
              <p style="color:#636e72;font-size:13.5px;margin-bottom:10px">We'd love to have you join the club as a full member.</p>
              <a href="join.php" class="btn-rsvp" style="display:inline-block;width:auto;padding:9px 20px;font-size:13.5px;text-decoration:none">Join the Club &rarr;</a>
            </div>
          <?php endif; ?>
          <a href="projects.php"
            style="display:inline-block;margin-top:24px;color:var(--pink-700);font-weight:600; text-decoration: none;">&#8592;
            View More Projects</a>
        </div>
      </div>
    <?php else: ?>
      <div class="rsvp-card">
        <div class="rsvp-event-header">
          <div
            style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;opacity:.7;margin-bottom:6px">
            Get Involved</div>
          <h2><?= e($project['title']) ?></h2>
        </div>

        <?php if ($error): ?>
          <div class="alert-err"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="rsvp-form">
          <?= csrf_field() ?>
          <div class="form-row">
            <div class="form-group"><label>Full Name *</label><input type="text" name="name"
                value="<?= e($old['name'] ?? '') ?>" placeholder="Your name" required></div>
            <div class="form-group"><label>Email *</label><input type="email" name="email"
                value="<?= e($old['email'] ?? '') ?>" placeholder="your@email.com" required></div>
          </div>
          <div class="form-group"><label>Phone</label><input type="tel" name="phone"
              value="<?= e($old['phone'] ?? '') ?>" placeholder="+244 900 000 000"></div>
          <div class="form-group"><label>How would you like to help? <span style="font-weight:400;color:#636e72">(optional)</span></label>
            <textarea name="notes" placeholder="e.g. I can help on the day, donate supplies, spread the word..."
              rows="3"><?= e($old['notes'] ?? '') ?></textarea></div>
          <button type="submit" class="btn-rsvp">Sign Up to Help &#10003;</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <script src="assets/js/scripts.js"></script>
</body>

</html>
