<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$settings = new SiteSettings($conn);
$donate_intro        = $settings->get('donate_intro', 'Every contribution helps us fund community service projects, from clean water initiatives to educational scholarships. Your generosity directly changes lives in Kwanza.');
$donate_bank_details  = $settings->get('donate_bank_details', '');
$donate_mobile_money  = $settings->get('donate_mobile_money', '');
$contact_email        = $settings->get('contact_email', 'info@rotaractkwanza.org');
$stat_projects        = $settings->get('hero_stats_projects', '45+');
$stat_lives           = $settings->get('hero_stats_lives', '8K+');

$has_payment_details = $donate_bank_details !== '' || $donate_mobile_money !== '';

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
  </style>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <div class="donate-hero">
    <div class="container">
      <div class="section-eyebrow" style="color:rgba(255,255,255,.7);justify-content:flex-start">Support Our Mission</div>
      <h1>Donate to <em>Rotaract Kwanza</em></h1>
      <p><?= e($donate_intro) ?></p>
    </div>
  </div>

  <div class="container" style="padding:48px 0 80px">

    <div class="stats-grid reveal" style="margin-bottom:16px">
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
      <div class="donate-methods">
        <?php if ($donate_bank_details): ?>
          <div class="donate-card reveal">
            <h3>
              <span class="donate-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="2" y="7" width="20" height="14" rx="2" />
                  <path d="M16 21V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16" />
                </svg></span>
              Bank Transfer
            </h3>
            <p class="donate-details"><?= e($donate_bank_details) ?></p>
          </div>
        <?php endif; ?>
        <?php if ($donate_mobile_money): ?>
          <div class="donate-card reveal">
            <h3>
              <span class="donate-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="5" y="2" width="14" height="20" rx="2" />
                  <line x1="12" y1="18" x2="12.01" y2="18" />
                </svg></span>
              Mobile Money
            </h3>
            <p class="donate-details"><?= e($donate_mobile_money) ?></p>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div style="text-align:center;padding:60px 20px;color:var(--text-soft)">
        <p style="font-size:1.1rem;font-weight:600">Want to support us financially?</p>
        <p style="margin-top:8px;max-width:480px;margin-left:auto;margin-right:auto">Reach out and our team will share the best way to contribute.</p>
      </div>
    <?php endif; ?>

    <div style="text-align:center;margin-top:56px">
      <div class="section-eyebrow reveal" style="justify-content:center">Other Ways to Help</div>
      <h2 class="section-title reveal reveal-delay-1">Give Your <em>Time</em>, Too</h2>
      <p class="section-lead reveal reveal-delay-2" style="max-width:520px;margin:0 auto 2rem">Money isn't the only way to make a difference — join us as a member or volunteer for a project.</p>
      <div class="reveal reveal-delay-3" style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
        <a href="join.php" class="btn-submit" style="display:inline-block;text-decoration:none">Become a Member &rarr;</a>
        <a href="projects.php" class="btn-submit" style="display:inline-block;background:transparent;border:2px solid var(--pink-700);color:var(--pink-700);text-decoration:none">Volunteer for a Project</a>
        <a href="mailto:<?= e($contact_email) ?>" class="btn-submit" style="display:inline-block;background:transparent;border:2px solid var(--pink-700);color:var(--pink-700);text-decoration:none">Email Us</a>
      </div>
    </div>

  </div>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/scripts.js"></script>
</body>

</html>
