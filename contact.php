<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/ContactMessage.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $subject   = trim($_POST['subject']   ?? '');
    $msg       = trim($_POST['message']   ?? '');

    if (!$full_name || !$email || !$msg) {
        $error = 'Name, email, and message are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        try {
            (new ContactMessage($conn))->create($full_name, $email, $subject, $msg);
            $_SESSION['flash_message'] = 'Message sent! We will get back to you soon.';
            header('Location: contact.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = 'Server error. Please try again later.';
        }
    }
}

$settings = new SiteSettings($conn);
$addr = $settings->get('contact_address', 'Kwanza Community Centre, Kwanza District, Angola');
$tel  = $settings->get('contact_phone',   '+244 900 000 000');
$mail = $settings->get('contact_email',   'info@rotaractkwanza.org');
$fb   = $settings->get('facebook_url',    '#');
$ig   = $settings->get('instagram_url',   '#');
$tw   = $settings->get('twitter_url',     '#');
$li   = $settings->get('linkedin_url',    '#');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/kwanza.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/flash_toast.php'; ?>

<section id="contact" style="padding-top:100px">
  <div class="container">
    <div class="contact-grid">
      <div>
        <div class="section-eyebrow reveal">Get In Touch</div>
        <h2 class="section-title reveal reveal-delay-1">We'd Love to <em>Hear</em> From You</h2>
        <p class="section-lead reveal reveal-delay-2">Whether you have a question, partnership opportunity, or just want to say hello &mdash; our doors are always open.</p>
        <div class="contact-info reveal reveal-delay-3">
          <div class="contact-item">
            <div class="contact-item-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <div><h4>Visit Us</h4><p><?= e($addr) ?></p></div>
          </div>
          <div class="contact-item">
            <div class="contact-item-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 8.63a19.79 19.79 0 01-3.07-8.67A2 2 0 012.18 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14.92z"/></svg>
            </div>
            <div><h4>Call Us</h4><p><?= e($tel) ?><br>Mon &ndash; Fri, 8:00 AM &ndash; 5:00 PM</p></div>
          </div>
          <div class="contact-item">
            <div class="contact-item-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <div><h4>Email Us</h4><p><?= e($mail) ?></p></div>
          </div>
        </div>
        <div class="socials reveal">
          <a href="<?= e($fb) ?>" class="social-btn" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>
          <a href="<?= e($ig) ?>" class="social-btn" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>
          <a href="<?= e($tw) ?>" class="social-btn" aria-label="Twitter / X"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg></a>
          <a href="<?= e($li) ?>" class="social-btn" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></a>
        </div>
      </div>

      <div class="contact-form reveal reveal-delay-2">
        <h3>Send Us a Message</h3>
        <p>Fill out the form below and we'll get back to you as soon as possible.</p>

        <form action="" method="POST">
          <?= csrf_field() ?>
          <div class="form-row">
            <div class="form-group">
              <label>Full Name</label>
              <input type="text" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="Your name" required>
            </div>
            <div class="form-group">
              <label>Email</label>
              <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="your@email.com" required>
            </div>
          </div>
          <div class="form-group">
            <label>Subject</label>
            <input type="text" name="subject" value="<?= e($_POST['subject'] ?? '') ?>" placeholder="How can we help?">
          </div>
          <div class="form-group">
            <label>Message</label>
            <textarea name="message" placeholder="Tell us more..." style="min-height:140px" required><?= e($_POST['message'] ?? '') ?></textarea>
          </div>
          <button type="submit" name="submitContactBTN" class="btn-submit">Send Message &rarr;</button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/scripts.js"></script>
</body>
</html>
