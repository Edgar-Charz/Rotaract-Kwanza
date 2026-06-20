<?php
session_start();
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Member.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$message = $error = '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $first_name  = trim($_POST['first_name']  ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    $email       = trim($_POST['email']       ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    $occupation  = trim($_POST['occupation']  ?? '');
    $why_join    = trim($_POST['why_join']    ?? '');

    if (!$first_name || !$last_name || !$email || !$why_join) {
        $error = 'First name, last name, email, and reason for joining are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $member = new Member($conn);
        if ($member->emailExists($email)) {
            $error = 'This email address is already registered. Please use a different email.';
        } else {
            try {
                $member->create($first_name, $last_name, $email, $phone, $occupation, $why_join);
                $message   = 'Application submitted! Our team will review it and get back to you soon.';
                $submitted = true;
            } catch (mysqli_sql_exception $e) {
                $error = 'Server error. Please try again later.';
            }
        }
    }
}

$settings = new SiteSettings($conn);
$fb = $settings->get('facebook_url',  '#');
$ig = $settings->get('instagram_url', '#');
$tw = $settings->get('twitter_url',   '#');
$li = $settings->get('linkedin_url',  '#');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Join Us &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="/Rotaract_Kwanza/assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/Rotaract_Kwanza/assets/css/kwanza.css">
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

        <div class="perks reveal reveal-delay-3">
          <div class="perk-item">
            <div class="perk-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <div>
              <h4>Global Network</h4>
              <p>Connect with over 220,000 Rotaractors in 9,600+ clubs worldwide.</p>
            </div>
          </div>
          <div class="perk-item">
            <div class="perk-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div>
              <h4>Leadership Growth</h4>
              <p>Develop practical leadership and professional skills through hands-on experience.</p>
            </div>
          </div>
          <div class="perk-item">
            <div class="perk-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div>
              <h4>Community Impact</h4>
              <p>Lead and participate in meaningful service projects that transform lives.</p>
            </div>
          </div>
          <div class="perk-item">
            <div class="perk-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
              <h4>Exciting Events</h4>
              <p>Attend social gatherings, workshops, conferences, and cultural events.</p>
            </div>
          </div>
        </div>

        <div class="socials reveal">
          <a href="<?= e($fb) ?>" class="social-btn" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>
          <a href="<?= e($ig) ?>" class="social-btn" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>
          <a href="<?= e($tw) ?>" class="social-btn" aria-label="Twitter / X"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg></a>
          <a href="<?= e($li) ?>" class="social-btn" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></a>
        </div>
      </div>

      <div class="join-form reveal reveal-delay-2">
        <?php if ($submitted): ?>
          <div class="join-success">
            <div class="join-success-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3>Application Submitted!</h3>
            <p><?= e($message) ?></p>
            <a href="/Rotaract_Kwanza/" class="btn-submit" style="display:inline-block;margin-top:1rem; text-decoration: none;">Back to Home &rarr;</a>
          </div>
        <?php else: ?>
          <h3>Membership Application</h3>
          <p>Fill out the form below and our team will review your application.</p>

          <form action="" method="POST">
            <?= csrf_field() ?>
            <div class="form-row">
              <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" placeholder="Your first name" required>
              </div>
              <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" placeholder="Your last name" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="your@email.com" required>
              </div>
              <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+244 900 000 000">
              </div>
            </div>
            <div class="form-group">
              <label>Occupation / Field of Study</label>
              <input type="text" name="occupation" value="<?= e($_POST['occupation'] ?? '') ?>" placeholder="e.g. Software Engineer, Medical Student">
            </div>
            <div class="form-group">
              <label>Why do you want to join? <span style="color:var(--pink-700)">*</span></label>
              <textarea name="why_join" placeholder="Tell us about yourself and what motivates you to become a Rotaractor..." style="min-height:130px" required><?= e($_POST['why_join'] ?? '') ?></textarea>
            </div>
            <button type="submit" name="submitJoinBTN" class="btn-submit">Submit Application &rarr;</button>
          </form>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="/Rotaract_Kwanza/assets/js/scripts.js"></script>
</body>
</html>
