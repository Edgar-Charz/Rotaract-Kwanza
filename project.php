<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Project.php';
require_once __DIR__ . '/classes/ProjectPhoto.php';
require_once __DIR__ . '/classes/Pledge.php';
require_once __DIR__ . '/classes/PaymentAccount.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$id      = (int) ($_GET['id'] ?? 0);
$project = $id ? (new Project($conn))->findById($id) : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pledge' && $project) {
  csrf_verify();

  if (!rate_limit_allow($conn, 'project_pledge', 5, 900)) {
    $_SESSION['flash_error'] = rate_limit_message();
  } else {
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $account_id = (int) ($_POST['payment_account_id'] ?? 0);
    $note       = mb_substr(trim($_POST['note'] ?? ''), 0, 500);

    $amount = null;
    // Commas are stripped here as a backstop — the money-input formatter
    // already strips them client-side, but this holds even with JS disabled.
    $amount_raw = str_replace(',', '', trim($_POST['amount'] ?? ''));
    if ($amount_raw !== '') {
      if (!is_numeric($amount_raw) || (float) $amount_raw < 0 || (float) $amount_raw > 999999999) {
        $_SESSION['flash_error'] = 'Please enter a valid pledge amount, or leave it blank.';
      } elseif ((float) $amount_raw > 0) {
        $amount = (float) $amount_raw; // exactly 0 is treated the same as blank
      }
    }

    $account = $account_id ? (new PaymentAccount($conn))->findById($account_id) : false;

    if (!isset($_SESSION['flash_error'])) {
      if (!$project['funding_goal']) {
        $_SESSION['flash_error'] = 'This project is not currently seeking sponsorship.';
      } elseif (!$name || !$email) {
        $_SESSION['flash_error'] = 'Name and email are required.';
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = 'Invalid email address.';
      } elseif (!$account || !$account['is_active']) {
        $_SESSION['flash_error'] = 'This payment method is no longer available — please refresh the page.';
      } else {
        try {
          $pledge = new Pledge($conn);
          $pledge_id = $pledge->create($name, $email, $phone, $account['id'], $account['type'], $account['label'], $amount, $note, $project['id'], $project['title']);

          try {
            require_once __DIR__ . '/classes/Mailer.php';
            $sent = Mailer::fromSettings($conn)->donationPledgeThanks($email, $name, $account['label'], $amount, $project['title']);
            $pledge->setEmailSent($pledge_id, $sent);
          } catch (Throwable $e) {
          }

          $_SESSION['flash_message'] = "Thank you, {$name}! We've noted your pledge towards {$project['title']} and an officer will follow up soon.";
        } catch (mysqli_sql_exception $e) {
          $_SESSION['flash_error'] = 'Could not save your pledge. Please try again later.';
        }
      }
    }
  }

  header('Location: project.php?id=' . $id);
  exit;
}

if (!$project) {
  http_response_code(404);
}

$photos = $project ? (new ProjectPhoto($conn))->getByProject($id) : [];

$bank_accounts   = $project ? (new PaymentAccount($conn))->getActiveByType('bank_transfer') : [];
$mobile_accounts = $project ? (new PaymentAccount($conn))->getActiveByType('mobile_money') : [];
$can_sponsor     = $project && !in_array($project['status'], ['completed', 'cancelled'], true) && $project['funding_goal'] && ($bank_accounts || $mobile_accounts);
$raised          = $can_sponsor ? (new Pledge($conn))->sumConfirmedAmountForProject($project['id']) : 0.0;

$page_title = $project ? site_title($conn, $project['title']) : site_title($conn, 'Project Not Found');
$page_description = $project
  ? mb_strimwidth(trim(strip_tags($project['description'] ?? '')), 0, 160, '…')
  : 'This project may have been removed or the link is incorrect.';
if ($project && $project['image_path']) $page_image = img_url($project['image_path']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
  <style>
    .pj-hero-img {
      width: 100%;
      max-height: 380px;
      object-fit: cover;
      border-radius: 16px;
      margin-bottom: 28px;
    }

    .pj-icon-lg {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 24px;
    }

    .pj-icon-lg svg {
      width: 32px;
      height: 32px;
    }

    .pj-badges {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }

    .pj-badge {
      display: inline-block;
      padding: 4px 14px;
      border-radius: 20px;
      font-size: 11.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
    }

    .pj-badge.active {
      background: rgba(39, 174, 96, 0.2);
      color: #6fe3a3;
    }

    .pj-badge.completed {
      background: rgba(255, 255, 255, 0.15);
      color: #fff;
    }

    .pj-badge.planning {
      background: rgba(212, 136, 42, 0.2);
      color: var(--gold-light);
    }

    .pj-badge.cancelled {
      background: rgba(231, 76, 60, 0.2);
      color: #ff8a7a;
    }

    .pj-badge.featured {
      background: rgba(212, 136, 42, 0.25);
      color: var(--gold-light);
      border: 1px solid rgba(212, 136, 42, 0.4);
    }

    .pj-desc {
      font-size: 15px;
      line-height: 1.8;
      color: rgba(255, 255, 255, .8);
      white-space: pre-line;
      margin: 18px 0 32px;
      max-width: 720px;
    }

    .pj-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: rgba(255, 255, 255, .85);
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 24px;
      text-decoration: none;
    }

    .pj-cta {
      display: inline-block;
      margin-top: 8px;
      padding: 12px 28px;
      background: linear-gradient(135deg, var(--gold), #b06a1e);
      color: #fff;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
    }

    .pj-socials {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }

    .pj-social-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 20px;
      background: rgba(255, 255, 255, .1);
      border: 1.5px solid rgba(255, 255, 255, .2);
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      transition: background .2s;
    }

    .pj-social-btn:hover {
      background: rgba(255, 255, 255, .2);
    }

    .pj-funding {
      background: rgba(255, 255, 255, .06);
      border: 1px solid rgba(255, 255, 255, .12);
      border-radius: 14px;
      padding: 22px 26px;
      margin: 24px 0 8px;
      max-width: 480px;
    }

    .pj-funding-figures {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      margin-bottom: 10px;
    }

    .pj-funding-raised {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.6rem;
      font-weight: 700;
      color: #fff;
    }

    .pj-funding-goal {
      font-size: 12.5px;
      color: rgba(255, 255, 255, .65);
    }

    .pj-progress-track {
      background: rgba(255, 255, 255, .12);
      border-radius: 20px;
      height: 10px;
      overflow: hidden;
    }

    .pj-progress-fill {
      background: linear-gradient(90deg, var(--gold), var(--gold-light));
      height: 100%;
      border-radius: 20px;
    }

    .pj-sponsor-btn {
      display: inline-block;
      margin-top: 16px;
      padding: 11px 24px;
      background: linear-gradient(135deg, var(--gold), #b06a1e);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 13.5px;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
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
      color: #2d3436;
    }

    .pledge-box h3 {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.5rem;
      margin-bottom: 4px;
    }

    .pledge-box p.pledge-sub {
      color: var(--text-soft, #636e72);
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

    .pledge-box select,
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

  <section id="projects" style="padding-top:100px;min-height:60vh">
    <div class="container">
      <?php if (!$project): ?>
        <div style="text-align:center;padding:80px 20px;color:rgba(255,255,255,0.6)">
          <p style="font-size:1.2rem;font-weight:600">Project not found</p>
          <p style="margin-top:8px">This project may have been removed or the link is incorrect.</p>
          <a href="projects.php" class="pj-back" style="margin-top:20px;justify-content:center">&#8592; Back to All Projects</a>
        </div>
      <?php else: ?>

        <a href="projects.php" class="pj-back">&#8592; Back to All Projects</a>

        <?php if ($project['image_path']): ?>
          <img class="pj-hero-img" src="<?= e(img_url($project['image_path'])) ?>" alt="<?= e($project['title']) ?>">
        <?php else: ?>
          <div class="pj-icon-lg"><?= icon_svg($project['icon_type'] ?: 'heart', 'var(--gold-light)') ?></div>
        <?php endif; ?>

        <div class="pj-badges">
          <span class="pj-badge <?= e($project['status']) ?>"><?= e(ucfirst($project['status'])) ?></span>
          <?php if ($project['is_featured']): ?><span class="pj-badge featured">Featured</span><?php endif; ?>
        </div>

        <h1 class="section-title" style="margin-bottom:0"><?= e($project['title']) ?></h1>

        <?php if ($project['start_date'] || $project['end_date']): ?>
          <p style="color:rgba(255,255,255,.7);font-size:13.5px;margin-top:8px">
            <?= $project['start_date'] ? date('M Y', strtotime($project['start_date'])) : '' ?>
            &ndash;
            <?= $project['end_date'] ? date('M Y', strtotime($project['end_date'])) : 'Ongoing' ?>
          </p>
        <?php endif; ?>

        <?php if ($project['impact_stat']): ?>
          <div class="project-impact" style="margin-top:20px">
            <div class="impact-stat">
              <div class="impact-num" style="font-size:2rem"><?= e($project['impact_stat']) ?></div>
              <div class="impact-label"><?= e($project['impact_label'] ?? '') ?></div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($project['description']): ?>
          <p class="pj-desc"><?= e($project['description']) ?></p>
        <?php endif; ?>

        <?php if ($project['status'] === 'cancelled'): ?>
          <p style="color:rgba(255,255,255,.75);font-size:13.5px;background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.35);border-radius:8px;padding:12px 16px;display:inline-block">This project has been cancelled.</p>
        <?php elseif ($project['status'] !== 'completed'): ?>
          <a href="project_signup.php?id=<?= (int) $project['id'] ?>" class="pj-cta">Get Involved &rarr;</a>
        <?php endif; ?>

        <?php if ($project['funding_goal']): ?>
          <div class="pj-funding reveal">
            <div class="pj-funding-figures">
              <span class="pj-funding-raised"><?= e(number_format($raised, 2)) ?></span>
              <span class="pj-funding-goal">raised of <?= e(number_format((float) $project['funding_goal'], 2)) ?> goal</span>
            </div>
            <div class="pj-progress-track">
              <div class="pj-progress-fill" style="width:<?= min(100, $project['funding_goal'] > 0 ? ($raised / $project['funding_goal']) * 100 : 0) ?>%"></div>
            </div>
            <?php if ($can_sponsor): ?>
              <button type="button" class="pj-sponsor-btn" onclick="openPledgeModal()">Sponsor This Project &rarr;</button>
            <?php elseif (!in_array($project['status'], ['completed', 'cancelled'], true)): ?>
              <p style="color:rgba(255,255,255,.6);font-size:12.5px;margin-top:14px">Sponsorship isn't open right now — <a href="contact.php" style="color:var(--gold-light)">contact us</a> if you'd like to help fund this project.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($project['instagram_url'] || $project['tiktok_url'] || ($project['x_url'] ?? '')): ?>
          <div class="pj-socials">
            <?php if ($project['instagram_url']): ?>
              <a href="<?= e($project['instagram_url']) ?>" target="_blank" rel="noopener" class="pj-social-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                  <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
                  <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                </svg>
                Instagram
              </a>
            <?php endif; ?>
            <?php if ($project['tiktok_url']): ?>
              <a href="<?= e($project['tiktok_url']) ?>" target="_blank" rel="noopener" class="pj-social-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0115.54 3h-3.09v12.4a2.592 2.592 0 01-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 004.3 1.38V7.3s-1.88.09-3.24-1.48z" />
                </svg>
                TikTok
              </a>
            <?php endif; ?>
            <?php if ($project['x_url'] ?? ''): ?>
              <a href="<?= e($project['x_url']) ?>" target="_blank" rel="noopener" class="pj-social-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z" />
                </svg>
                X
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </section>

  <?php if ($photos): ?>
    <section style="background:#fff;padding:60px 0">
      <div class="container">
        <h3 class="section-title" style="font-size:1.6rem;margin-bottom:24px">Project <em>Gallery</em></h3>
        <div class="gallery-grid">
          <?php foreach ($photos as $p): $pcaption = $p['caption'] ?: $project['title']; ?>
            <div class="gallery-item reveal" data-src="<?= e(img_url($p['image_path'])) ?>" data-title="<?= e($pcaption) ?>" tabindex="0" role="button" aria-label="View photo">
              <div class="gallery-inner">
                <img src="<?= e(img_url($p['image_path'])) ?>" alt="<?= e($pcaption) ?>" style="width:100%;height:100%;object-fit:cover;display:block">
              </div>
              <div class="gallery-overlay">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;flex-shrink:0">
                  <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
                </svg>
                View
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Lightbox -->
    <div class="lb-overlay" id="lb-overlay" role="dialog" aria-modal="true" aria-label="Image lightbox">
      <button class="lb-close" id="lb-close" aria-label="Close lightbox">&times;</button>
      <button class="lb-nav lb-prev" id="lb-prev" aria-label="Previous image">&#8249;</button>
      <div class="lb-img-wrap">
        <img id="lb-img" src="" alt="" loading="eager">
        <p class="lb-caption" id="lb-caption"></p>
        <p class="lb-counter" id="lb-counter"></p>
      </div>
      <button class="lb-nav lb-next" id="lb-next" aria-label="Next image">&#8250;</button>
    </div>
  <?php endif; ?>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <?php if ($can_sponsor): ?>
  <div class="pledge-overlay" id="pledge-overlay">
    <div class="pledge-box">
      <h3>Sponsor This Project</h3>
      <p class="pledge-sub">Let us know so an officer can follow up and confirm receipt.</p>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="pledge">
        <div class="form-group">
          <label>Pay Into *</label>
          <select name="payment_account_id" id="pledge-account-select" required onchange="updatePledgeAccountDetails()">
            <option value="">Select a payment account&hellip;</option>
            <?php if ($bank_accounts): ?>
              <optgroup label="Bank Transfer">
                <?php foreach ($bank_accounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>" data-details="<?= e($acc['details']) ?>"><?= e($acc['label']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
            <?php if ($mobile_accounts): ?>
              <optgroup label="Mobile Money">
                <?php foreach ($mobile_accounts as $acc): ?>
                  <option value="<?= $acc['id'] ?>" data-details="<?= e($acc['details']) ?>"><?= e($acc['label']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          </select>
          <p id="pledge-account-details" style="font-size:12.5px;color:var(--text-soft,#636e72);white-space:pre-line;margin-top:6px"></p>
        </div>
        <div class="form-group"><label>Full Name *</label><input type="text" name="name" required></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
        <div class="form-group"><label>Phone</label><input type="tel" name="phone"></div>
        <div class="form-group"><label>Amount <span style="font-weight:400;color:var(--text-soft,#636e72)">(optional)</span></label><input type="text" inputmode="decimal" class="money-input" name="amount" placeholder="Leave blank if you'd rather not say"></div>
        <div class="form-group"><label>Note <span style="font-weight:400;color:var(--text-soft,#636e72)">(optional)</span></label><textarea name="note" rows="2" maxlength="500"></textarea></div>
        <div class="pledge-box-actions">
          <button type="button" class="btn-submit" style="background:transparent;border:2px solid var(--pink-700,#9b2335);color:var(--pink-700,#9b2335)" onclick="closePledgeModal()">Cancel</button>
          <button type="submit" class="btn-submit">Send</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    function openPledgeModal() {
      document.getElementById('pledge-overlay').classList.add('open');
    }
    function closePledgeModal() {
      document.getElementById('pledge-overlay').classList.remove('open');
    }
    function updatePledgeAccountDetails() {
      var sel = document.getElementById('pledge-account-select');
      var opt = sel.options[sel.selectedIndex];
      document.getElementById('pledge-account-details').textContent = opt ? (opt.dataset.details || '') : '';
    }
    document.getElementById('pledge-overlay').addEventListener('click', function(e) {
      if (e.target === this) closePledgeModal();
    });
  </script>
  <?php endif; ?>

  <script src="assets/js/scripts.js"></script>
  <?php if ($photos): ?>
    <script>
      (function() {
        var photos = [];
        var current = 0;
        var overlay = document.getElementById('lb-overlay');
        var img = document.getElementById('lb-img');
        var cap = document.getElementById('lb-caption');
        var counter = document.getElementById('lb-counter');

        document.querySelectorAll('.gallery-item[data-src]').forEach(function(el, i) {
          photos.push({
            src: el.dataset.src,
            title: el.dataset.title || ''
          });
          el.addEventListener('click', function() {
            openLb(i);
          });
          el.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              openLb(i);
            }
          });
        });

        function openLb(i) {
          current = i;
          img.src = photos[i].src;
          img.alt = photos[i].title;
          cap.textContent = photos[i].title;
          counter.textContent = (i + 1) + ' / ' + photos.length;
          overlay.classList.add('open');
          document.body.style.overflow = 'hidden';
          document.getElementById('lb-close').focus();
        }

        function closeLb() {
          overlay.classList.remove('open');
          document.body.style.overflow = '';
          setTimeout(function() {
            img.src = '';
          }, 250);
        }

        function prevImg() {
          openLb((current - 1 + photos.length) % photos.length);
        }

        function nextImg() {
          openLb((current + 1) % photos.length);
        }

        document.getElementById('lb-close').addEventListener('click', closeLb);
        document.getElementById('lb-prev').addEventListener('click', function(e) {
          e.stopPropagation();
          prevImg();
        });
        document.getElementById('lb-next').addEventListener('click', function(e) {
          e.stopPropagation();
          nextImg();
        });

        overlay.addEventListener('click', function(e) {
          if (e.target === overlay) closeLb();
        });

        document.addEventListener('keydown', function(e) {
          if (!overlay.classList.contains('open')) return;
          if (e.key === 'Escape') closeLb();
          if (e.key === 'ArrowLeft') prevImg();
          if (e.key === 'ArrowRight') nextImg();
        });
      })();
    </script>
  <?php endif; ?>
</body>

</html>