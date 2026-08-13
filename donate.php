<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/classes/Pledge.php';
require_once __DIR__ . '/classes/PaymentAccount.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pledge') {
  csrf_verify();

  if (!rate_limit_allow($conn, 'donation_pledge', 5, 900)) {
    $_SESSION['flash_error'] = rate_limit_message();
  } else {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $account_id = (int) ($_POST['payment_account_id'] ?? 0);
    $note      = mb_substr(trim($_POST['note'] ?? ''), 0, 500);

    $amount = null;
    // Commas are stripped here as a backstop — the money-input formatter
    // already strips them client-side, but this holds even with JS disabled.
    $amount_raw = str_replace(',', '', trim($_POST['amount'] ?? ''));
    if ($amount_raw !== '') {
      if (!is_numeric($amount_raw) || (float) $amount_raw < 0 || (float) $amount_raw > 999999999) {
        $_SESSION['flash_error'] = 'Please enter a valid donation amount, or leave it blank.';
      } elseif ((float) $amount_raw > 0) {
        $amount = (float) $amount_raw; // exactly 0 is treated the same as blank
      }
    }

    $account = $account_id ? (new PaymentAccount($conn))->findById($account_id) : false;

    if (!isset($_SESSION['flash_error'])) {
      if (!$name || !$email) {
        $_SESSION['flash_error'] = 'Name and email are required.';
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = 'Invalid email address.';
      } elseif (!$account || !$account['is_active']) {
        $_SESSION['flash_error'] = 'This payment method is no longer available — please refresh the page.';
      } else {
        try {
          $donation = new Pledge($conn);
          $pledge_id = $donation->create($name, $email, $phone, $account['id'], $account['type'], $account['label'], $amount, $note);

          try {
            require_once __DIR__ . '/classes/Mailer.php';
            $sent = Mailer::fromSettings($conn)->donationPledgeThanks($email, $name, $account['label'], $amount);
            $donation->setEmailSent($pledge_id, $sent);
          } catch (Throwable $e) {
          }

          $_SESSION['flash_message'] = "Thank you, {$name}! We've noted your donation and an officer will follow up soon.";
        } catch (mysqli_sql_exception $e) {
          $_SESSION['flash_error'] = 'Could not save your donation. Please try again later.';
        }
      }
    }
  }

  header('Location: donate.php');
  exit;
}

$settings = new SiteSettings($conn);
$donate_intro        = $settings->get('donate_intro', 'Every contribution helps us fund community service projects, from clean water initiatives to educational scholarships. Your generosity directly changes lives in Kwanza.');
$donate_gratitude_message = $settings->get('donate_gratitude_message', 'Thank you for even considering a gift — every contribution, big or small, helps us keep serving Kwanza.');
$contact_email        = $settings->get('contact_email', 'info@rotaractkwanza.org');
$stat_projects        = $settings->get('hero_stats_projects', '45+');
$stat_lives           = $settings->get('hero_stats_lives', '8K+');

$account_obj    = new PaymentAccount($conn);
$bank_accounts   = $account_obj->getActiveByType('bank_transfer');
$mobile_accounts = $account_obj->getActiveByType('mobile_money');

$has_payment_details = $bank_accounts || $mobile_accounts;

$page_title = site_title($conn, 'Donate');
$page_description = mb_strimwidth(trim(strip_tags($donate_intro)), 0, 160, '…');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
  <style>
    .donate-hero {
      background: linear-gradient(135deg, var(--pink-800), var(--pink-900));
      padding: 60px 0 40px;
      margin-top: 60px;
      color: #fff;
    }

    .donate-hero h1 {
      font-family: 'Cormorant Garamond', serif;
      font-size: 2.4rem;
      font-weight: 700;
      margin: 8px 0 0;
    }

    .donate-hero p {
      max-width: 620px;
      margin: 12px 0 0;
      color: rgba(255, 255, 255, .8);
      font-size: 14.5px;
      line-height: 1.7;
    }

    .donate-methods {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
      margin-top: 40px;
    }

    .donate-card {
      background: #fff;
      border-radius: 16px;
      padding: 30px;
      box-shadow: 0 2px 14px rgba(0, 0, 0, .06);
    }

    .donate-card h3 {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.4rem;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .donate-card-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: var(--pink-100);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .donate-card-icon svg {
      width: 18px;
      height: 18px;
    }

    .donate-details {
      color: var(--text-soft);
      line-height: 1.8;
      font-size: 14px;
      white-space: pre-line;
    }

    .donate-gratitude {
      background: var(--pink-100);
      border-radius: 14px;
      padding: 18px 24px;
      margin-bottom: 8px;
      text-align: center;
      color: var(--pink-800);
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.15rem;
      font-style: italic;
    }

    .donate-card-actions {
      display: flex;
      gap: 10px;
      margin-top: 18px;
      flex-wrap: wrap;
    }

    .donate-btn-copy,
    .donate-btn-pledge {
      border: none;
      cursor: pointer;
      border-radius: 20px;
      font-size: 12.5px;
      font-weight: 600;
      padding: 8px 16px;
    }

    .donate-btn-copy {
      background: transparent;
      border: 1.5px solid var(--pink-700);
      color: var(--pink-700);
    }

    .donate-btn-pledge {
      background: var(--pink-700);
      color: #fff;
    }

    .pledge-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .pledge-overlay.open {
      display: flex;
    }

    .pledge-box {
      background: #fff;
      border-radius: 16px;
      padding: 30px;
      max-width: 420px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
    }

    .pledge-box h3 {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.5rem;
      margin-bottom: 4px;
    }

    .pledge-box p.pledge-sub {
      color: var(--text-soft);
      font-size: 13px;
      margin-bottom: 18px;
    }

    .pledge-box .form-group {
      margin-bottom: 14px;
    }

    .pledge-box label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 5px;
    }

    .pledge-box input,
    .pledge-box textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1.5px solid #e5e5ea;
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      box-sizing: border-box;
    }

    .pledge-box-actions {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }
  </style>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>
  <?php require_once __DIR__ . '/includes/flash_toast.php'; ?>

  <div class="donate-hero">
    <div class="container">
      <div class="section-eyebrow section-eyebrow--hero-dark">Support Our Mission</div>
      <h1>Donate to <em>Rotaract Kwanza</em></h1>
      <p><?= e($donate_intro) ?></p>
    </div>
  </div>

  <div class="container donate-container">

    <div class="stats-grid reveal mb-16">
      <div class="stat-card">
        <div class="stat-number"><?= e($stat_projects) ?></div>
        <div class="stat-label">Projects Funded</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= e($stat_lives) ?></div>
        <div class="stat-label">Lives Impacted</div>
      </div>
    </div>

    <?php if ($has_payment_details): ?>
      <div class="donate-gratitude reveal"><?= e($donate_gratitude_message) ?></div>
      <div class="donate-methods">
        <?php foreach ($bank_accounts as $acc): ?>
          <div class="donate-card reveal">
            <h3>
              <span class="donate-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="2" y="7" width="20" height="14" rx="2" />
                  <path d="M16 21V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16" />
                </svg></span>
              <?= e($acc['label']) ?>
            </h3>
            <p class="donate-details" id="donate-account-<?= $acc['id'] ?>"><?= e($acc['details']) ?></p>
            <div class="donate-card-actions">
              <button type="button" class="donate-btn-copy" data-copy-target="donate-account-<?= $acc['id'] ?>">Copy Details</button>
              <button type="button" class="donate-btn-pledge" onclick="openPledgeModal(<?= $acc['id'] ?>, '<?= e(addslashes($acc['label'])) ?>')">I've Donated &rarr;</button>
            </div>
          </div>
        <?php endforeach; ?>
        <?php foreach ($mobile_accounts as $acc): ?>
          <div class="donate-card reveal">
            <h3>
              <span class="donate-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="5" y="2" width="14" height="20" rx="2" />
                  <line x1="12" y1="18" x2="12.01" y2="18" />
                </svg></span>
              <?= e($acc['label']) ?>
            </h3>
            <p class="donate-details" id="donate-account-<?= $acc['id'] ?>"><?= e($acc['details']) ?></p>
            <div class="donate-card-actions">
              <button type="button" class="donate-btn-copy" data-copy-target="donate-account-<?= $acc['id'] ?>">Copy Details</button>
              <button type="button" class="donate-btn-pledge" onclick="openPledgeModal(<?= $acc['id'] ?>, '<?= e(addslashes($acc['label'])) ?>')">I've Donated &rarr;</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="donate-cta-empty">
        <p class="donate-cta-empty-title">Want to support us financially?</p>
        <p class="donate-cta-empty-text">Reach out and our team will share the best way to contribute.</p>
      </div>
    <?php endif; ?>

    <div class="text-center mt-56">
      <div class="section-eyebrow reveal section-eyebrow--center">Other Ways to Help</div>
      <h2 class="section-title reveal reveal-delay-1">Give Your <em>Time</em>, Too</h2>
      <p class="section-lead reveal reveal-delay-2 donate-help-lead">Money isn't the only way to make a difference — join us as a member or volunteer for a project.</p>
      <div class="reveal reveal-delay-3 donate-cta-buttons">
        <a href="join.php" class="btn-submit btn-submit--inline">Become a Member &rarr;</a>
        <a href="projects.php" class="btn-submit btn-submit--inline btn-submit--outline">Volunteer for a Project</a>
        <a href="mailto:<?= e($contact_email) ?>" class="btn-submit btn-submit--inline btn-submit--outline">Email Us</a>
      </div>
    </div>

  </div>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <?php if ($has_payment_details): ?>
  <div class="pledge-overlay" id="pledge-overlay">
    <div class="pledge-box">
      <h3>I've Donated &mdash; <span id="pledge-method-label"></span></h3>
      <p class="pledge-sub">Let us know so an officer can follow up and confirm receipt.</p>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="pledge">
        <input type="hidden" name="payment_account_id" id="pledge-method-input">
        <div class="form-group"><label for="pledge_name">Full Name *</label><input type="text" id="pledge_name" name="name" required></div>
        <div class="form-group"><label for="pledge_email">Email *</label><input type="email" id="pledge_email" name="email" required></div>
        <div class="form-group"><label for="pledge_phone">Phone</label><input type="tel" id="pledge_phone" name="phone"></div>
        <div class="form-group"><label for="pledge_amount">Amount <span class="optional-hint">(optional)</span></label><input type="text" inputmode="decimal" class="money-input" id="pledge_amount" name="amount" placeholder="Leave blank if you'd rather not say"></div>
        <div class="form-group"><label for="pledge_note">Note <span class="optional-hint">(optional)</span></label><textarea id="pledge_note" name="note" rows="2" maxlength="500"></textarea></div>
        <div class="pledge-box-actions">
          <button type="button" class="btn-submit btn-submit--outline" onclick="closePledgeModal()">Cancel</button>
          <button type="submit" class="btn-submit">Send</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    function openPledgeModal(accountId, label) {
      document.getElementById('pledge-method-input').value = accountId;
      document.getElementById('pledge-method-label').textContent = label;
      document.getElementById('pledge-overlay').classList.add('open');
    }
    function closePledgeModal() {
      document.getElementById('pledge-overlay').classList.remove('open');
    }
    document.getElementById('pledge-overlay').addEventListener('click', function(e) {
      if (e.target === this) closePledgeModal();
    });
  </script>
  <?php endif; ?>

  <script src="assets/js/scripts.js"></script>
</body>

</html>
