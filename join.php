<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Member.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/classes/MembershipPerk.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$submitted = isset($_GET['success']) && $_GET['success'] === '1';
$message   = $submitted ? 'Application submitted! Our team will review it and get back to you soon.' : '';
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $first_name  = trim($_POST['first_name']  ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    $email       = trim($_POST['email']       ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    $occupation  = trim($_POST['occupation']  ?? '');
    $linkedin    = trim($_POST['linkedin_url'] ?? '');
    $instagram   = trim($_POST['instagram_url'] ?? '');
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
                $new_id = $member->create($first_name, $last_name, $email, $phone, $occupation, $why_join, 'pending', '', '', '', $linkedin, $instagram);

                // Optional profile photo
                $photo = upload_member_photo('photo');
                if ($photo) $member->updatePhoto($new_id, $photo);

                // Confirmation email to applicant (non-fatal)
                try {
                    require_once __DIR__ . '/classes/Mailer.php';
                    $club = (new SiteSettings($conn))->get('site_name', 'Rotaract Kwanza');
                    Mailer::fromSettings($conn)->applicationReceived($email, "$first_name $last_name", $club);
                } catch (Throwable $e) {}

                // Redirect (PRG) so refreshing the confirmation page doesn't resubmit the form
                header('Location: join.php?success=1');
                exit;
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
$perks = (new MembershipPerk($conn))->getActive();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Join Us &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/kwanza.css">
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
            <div class="form-row">
              <div class="form-group">
                <label>LinkedIn <span style="color:var(--text-soft);font-weight:400">(optional)</span></label>
                <input type="text" name="linkedin_url" value="<?= e($_POST['linkedin_url'] ?? '') ?>" placeholder="https://linkedin.com/in/yourname">
              </div>
              <div class="form-group">
                <label>Instagram <span style="color:var(--text-soft);font-weight:400">(optional)</span></label>
                <input type="text" name="instagram_url" value="<?= e($_POST['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/yourname">
              </div>
            </div>
            <div class="form-group">
              <label>Why do you want to join? <span style="color:var(--pink-700)">*</span></label>
              <textarea name="why_join" placeholder="Tell us about yourself and what motivates you to become a Rotaractor..." style="min-height:130px" required><?= e($_POST['why_join'] ?? '') ?></textarea>
            </div>
            <div class="form-group" style="margin-top:4px">
              <label>Profile Photo <span style="color:var(--text-soft);font-weight:400">(optional)</span></label>
              <input type="file" name="photo" accept="image/*" id="join-photo-input"
                     style="padding:6px;border:1.5px solid var(--border);border-radius:8px;width:100%;font-family:inherit;font-size:13px"
                     onchange="joinPhotoPreview(this)">
              <div id="join-photo-preview" style="display:none;margin-top:10px;text-align:center">
                <img id="join-photo-img" src="" alt="Preview"
                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--pink-200)">
              </div>
              <p style="font-size:11.5px;color:var(--text-soft);margin-top:5px">JPG, PNG or WEBP · max 3 MB. Shown in the member directory if your profile is listed.</p>
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
<script>
function joinPhotoPreview(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('join-photo-img').src = e.target.result;
      document.getElementById('join-photo-preview').style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>
