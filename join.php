<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Member.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/classes/MembershipPerk.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$submitted = isset($_GET['success']) && $_GET['success'] === '1';
$message   = $submitted ? 'Application submitted! Our team will review it and get back to you soon.' : '';
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  if (!rate_limit_allow($conn, 'join', 3, 3600)) {
    $error = rate_limit_message(3600);
  } else {
    $first_name    = trim($_POST['first_name']    ?? '');
    $last_name     = trim($_POST['last_name']     ?? '');
    $email         = trim($_POST['email']         ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $occupation    = trim($_POST['occupation']    ?? '');
    $year_of_study = trim($_POST['year_of_study'] ?? '');
    $birthday      = trim($_POST['birthday']      ?? '');
    $why_join      = trim($_POST['why_join']      ?? '');
    // LinkedIn and Instagram aren't collected on the application — an officer
    // adds them later if the member wants a public directory/team profile.
    $linkedin  = '';
    $instagram = '';

    $birthday_date = $birthday !== '' ? DateTime::createFromFormat('Y-m-d', $birthday) : false;

    if (!$first_name || !$last_name || !$email || !$why_join || !$birthday) {
      $error = 'First name, last name, email, birthday, and reason for joining are required.';
    } elseif (!$birthday_date || $birthday_date->format('Y-m-d') !== $birthday || $birthday_date > new DateTime()) {
      $error = 'Please enter a valid birthday.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Invalid email address.';
    } else {
      $member = new Member($conn);
      if ($member->emailExists($email)) {
        $error = 'This email address is already registered. Please use a different email.';
      } else {
        try {
          $member->create($first_name, $last_name, $email, $phone, $occupation, $why_join, 'pending', '', '', '', $linkedin, $instagram, $year_of_study, $birthday);

          // Confirmation email to applicant (non-fatal)
          try {
            require_once __DIR__ . '/classes/Mailer.php';
            $club = (new SiteSettings($conn))->get('site_name', 'Rotaract Kwanza');
            Mailer::fromSettings($conn)->applicationReceived($email, "$first_name $last_name", $club);
          } catch (Throwable $e) {
          }

          // Redirect (PRG) so refreshing the confirmation page doesn't resubmit the form
          header('Location: join.php?success=1');
          exit;
        } catch (mysqli_sql_exception $e) {
          $error = 'Server error. Please try again later.';
        }
      }
    }
  }

  // PRG on validation failure too, not just success — otherwise refreshing
  // the response page re-submits the form. Old input (minus the file, which
  // can't survive a redirect) rides along in the session so the form
  // doesn't come back empty.
  if ($error !== '') {
    $_SESSION['flash_error'] = $error;
    $_SESSION['flash_old_input'] = $_POST;
    header('Location: join.php');
    exit;
  }
}

$old = $_SESSION['flash_old_input'] ?? [];
unset($_SESSION['flash_old_input']);

$settings = new SiteSettings($conn);
$fb = $settings->get('facebook_url',  '');
$ig = $settings->get('instagram_url', '');
$tw = $settings->get('twitter_url',   '');
$tt = $settings->get('tiktok_url',    '');
$li = $settings->get('linkedin_url',  '');
$perks = (new MembershipPerk($conn))->getActive();

$page_title = site_title($conn, 'Join Us');
$page_description = 'Join a global network of young leaders committed to service, fellowship, and professional development with Rotaract Club of Kwanza.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>
  <?php require_once __DIR__ . '/includes/flash_toast.php'; ?>

  <section id="join" style="padding-top:100px">
    <div class="container">
      <div class="join-grid">

        <div>
          <div class="section-eyebrow reveal">Become a Member</div>
          <h2 class="section-title reveal reveal-delay-1">Make a <em>Difference</em> in Your Community</h2>
          <p class="section-lead reveal reveal-delay-2">Join a global network of young leaders committed to creating positive change through service, fellowship, and professional development.</p>

          <?php if ($perks): ?>
            <div class="perks reveal reveal-delay-3">
              <?php foreach ($perks as $perk): ?>
                <div class="perk-item">
                  <div class="perk-icon" style="color:var(--pink-700)"><?= icon_svg($perk['icon_key'], 'var(--pink-700)') ?></div>
                  <div>
                    <h4><?= e($perk['title']) ?></h4>
                    <?php if ($perk['description']): ?><p><?= e($perk['description']) ?></p><?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="socials reveal">
            <?php if ($fb): ?>
            <a href="<?= e($fb) ?>" class="social-btn" aria-label="Facebook" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round">
                <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
              </svg></a>
            <?php endif; ?>
            <?php if ($ig): ?>
            <a href="<?= e($ig) ?>" class="social-btn" aria-label="Instagram" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
              </svg></a>
            <?php endif; ?>
            <?php if ($tw): ?>
            <a href="<?= e($tw) ?>" class="social-btn" aria-label="X" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z" />
              </svg></a>
            <?php endif; ?>
            <?php if ($tt): ?>
            <a href="<?= e($tt) ?>" class="social-btn" aria-label="TikTok" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0115.54 3h-3.09v12.4a2.592 2.592 0 01-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 004.3 1.38V7.3s-1.88.09-3.24-1.48z" />
              </svg></a>
            <?php endif; ?>
            <?php if ($li): ?>
            <a href="<?= e($li) ?>" class="social-btn" aria-label="LinkedIn" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round">
                <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z" />
                <rect x="2" y="9" width="4" height="12" />
                <circle cx="4" cy="4" r="2" />
              </svg></a>
            <?php endif; ?>
          </div>
        </div>

        <div class="join-form reveal reveal-delay-2">
          <?php if ($submitted): ?>
            <div class="join-success">
              <div class="join-success-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2.5" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              </div>
              <h3>Application Submitted!</h3>
              <p><?= e($message) ?></p>
              <a href="index.php" class="btn-submit" style="display:inline-block;margin-top:1rem; text-decoration: none;">Back to Home &rarr;</a>
            </div>
          <?php else: ?>
            <h3>Membership Application</h3>
            <p>Fill out the form below and our team will review your application.</p>

            <form action="" method="POST" enctype="multipart/form-data">
              <?= csrf_field() ?>
              <div class="form-row">
                <div class="form-group">
                  <label>First Name</label>
                  <input type="text" name="first_name" value="<?= e($old['first_name'] ?? '') ?>" placeholder="Your first name" required>
                </div>
                <div class="form-group">
                  <label>Last Name</label>
                  <input type="text" name="last_name" value="<?= e($old['last_name'] ?? '') ?>" placeholder="Your last name" required>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Email</label>
                  <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" placeholder="your@email.com" required>
                </div>
                <div class="form-group">
                  <label>Phone</label>
                  <input type="tel" name="phone" value="<?= e($old['phone'] ?? '') ?>" placeholder="+244 900 000 000">
                </div>
              </div>
              <div class="form-group">
                <label>Field of Study / Programme</label>
                <input type="text" name="occupation" value="<?= e($old['occupation'] ?? '') ?>" placeholder="e.g. Computer Science, Medicine">
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Year of Study</label>
                  <select name="year_of_study">
                    <option value="">Select year</option>
                    <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Postgraduate'] as $yr): ?>
                      <option value="<?= e($yr) ?>" <?= ($old['year_of_study'] ?? '') === $yr ? 'selected' : '' ?>><?= e($yr) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Birthday</label>
                  <input type="date" name="birthday" value="<?= e($old['birthday'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
              </div>
              <div class="form-group">
                <label>Why do you want to join? <span style="color:var(--pink-700)">*</span></label>
                <textarea name="why_join" placeholder="Tell us about yourself and what motivates you to become a Rotaractor..." style="min-height:130px" required><?= e($old['why_join'] ?? '') ?></textarea>
              </div>
              <button type="submit" name="submitJoinBTN" class="btn-submit">Submit Application &rarr;</button>
            </form>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/scripts.js"></script>
</body>

</html>