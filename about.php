<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/classes/ClubValue.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();

$settings = new SiteSettings($conn);
$values   = (new ClubValue($conn))->getActive();

$about_image     = $settings->get('about_image', '');
$about_text      = $settings->get('about_text', "The Rotaract Club of Kwanza was founded by a group of young professionals who believed their community deserved more than good intentions — it deserved consistent, hands-on action. What began as a handful of members meeting informally has grown into one of the region's most active Rotaract clubs, running service projects, professional development workshops, and fellowship events throughout the year.");
$founding_year   = $settings->get('founding_year', '2012');
$motto_text      = $settings->get('motto_text', 'Service Above Self');
$mission_text    = $settings->get('mission_text', 'To provide young leaders with a platform to develop professional and leadership skills while addressing the physical and social needs of our community through impactful, hands-on service.');
$sponsor_club    = $settings->get('sponsor_club', 'Rotary Club of Kwanza');
$meeting_day     = $settings->get('meeting_day', 'Every Thursday');
$meeting_time    = $settings->get('meeting_time', '6:00 PM');
$meeting_location = $settings->get('meeting_location', '') ?: $settings->get('contact_address', 'Kwanza Community Centre, Kwanza District, Angola');

$stat_members  = $settings->get('hero_stats_members', '120+');
$stat_projects = $settings->get('hero_stats_projects', '45+');
$stat_lives    = $settings->get('hero_stats_lives', '8K+');
$stat_years    = $settings->get('hero_stats_years', '12');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="assets/css/kwanza.css">
  <style>
    .ab-card { background:#fff; border-radius:16px; padding:32px; box-shadow:0 2px 14px rgba(0,0,0,.06); }
    .ab-mission-grid { display:grid; grid-template-columns:1.3fr 1fr; gap:24px; margin-top:28px; }
    .ab-motto-card { background:linear-gradient(135deg,var(--pink-700),var(--pink-900)); color:#fff; display:flex; flex-direction:column; justify-content:center; text-align:center; }
    .ab-motto-card .ab-motto-label { font-size:12px; font-weight:700; letter-spacing:1px; text-transform:uppercase; opacity:.75; margin-bottom:10px; }
    .ab-motto-card .ab-motto-text { font-family:'Cormorant Garamond',serif; font-size:1.9rem; font-style:italic; line-height:1.3; }
    .ab-mission-card h3 { font-family:'Cormorant Garamond',serif; font-size:1.4rem; margin-bottom:12px; }
    .ab-mission-card p { color:var(--text-soft); line-height:1.75; font-size:15px; }
    .ab-meeting-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; margin-top:28px; }
    .ab-meeting-item { display:flex; gap:14px; align-items:flex-start; }
    .ab-meeting-icon { width:44px; height:44px; border-radius:12px; background:var(--pink-100); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .ab-meeting-icon svg { width:20px; height:20px; }
    .ab-meeting-item h4 { font-size:14px; margin-bottom:2px; }
    .ab-meeting-item p { font-size:13.5px; color:var(--text-soft); }
    @media (max-width:760px) { .ab-mission-grid { grid-template-columns:1fr; } }
  </style>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <section id="about" style="padding-top:100px">
    <div class="container">
      <div class="about-grid">
        <div class="about-visual reveal">
          <div class="about-img-wrap">
            <?php if ($about_image): ?>
            <img src="<?= e(img_url($about_image)) ?>" alt="Rotaract Club of Kwanza members" class="about-img">
            <?php else: ?>
            <svg viewBox="0 0 300 300" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="150" cy="150" r="120" fill="rgba(212,52,122,0.15)" />
              <circle cx="150" cy="150" r="80" fill="rgba(212,52,122,0.2)" />
              <circle cx="150" cy="100" r="28" fill="var(--pink-500)" opacity="0.7" />
              <path d="M90 200 C90 165 110 148 150 148 C190 148 210 165 210 200" stroke="var(--pink-600)"
                stroke-width="3" fill="none" stroke-linecap="round" />
              <circle cx="100" cy="90" r="18" fill="var(--pink-400)" opacity="0.6" />
              <circle cx="200" cy="90" r="18" fill="var(--pink-400)" opacity="0.6" />
              <path d="M70 185 C70 160 82 150 100 150 C118 150 130 160 130 185" stroke="var(--pink-500)"
                stroke-width="2" fill="none" stroke-linecap="round" opacity="0.7" />
              <path d="M170 185 C170 160 182 150 200 150 C218 150 230 160 230 185" stroke="var(--pink-500)"
                stroke-width="2" fill="none" stroke-linecap="round" opacity="0.7" />
            </svg>
            <?php endif; ?>
          </div>
          <div class="about-card-float">
            <div class="about-card-float-title">Est. <?= e($founding_year) ?></div>
            <div class="about-card-float-sub">Chartered under <?= e($sponsor_club) ?></div>
          </div>
        </div>

        <div class="about-content">
          <div class="section-eyebrow reveal">Our Story</div>
          <h2 class="section-title reveal reveal-delay-1">Who We <em>Are</em></h2>
          <p class="section-lead reveal reveal-delay-2"><?= nl2br(e($about_text)) ?></p>
        </div>
      </div>

      <div style="margin-top:80px">
        <div class="section-eyebrow reveal">Mission &amp; Motto</div>
        <h2 class="section-title reveal reveal-delay-1">What Drives <em>Us</em></h2>
        <div class="ab-mission-grid reveal reveal-delay-2">
          <div class="ab-card ab-mission-card">
            <h3>Our Mission</h3>
            <p><?= nl2br(e($mission_text)) ?></p>
          </div>
          <div class="ab-card ab-motto-card">
            <div class="ab-motto-label">Our Motto</div>
            <div class="ab-motto-text">&ldquo;<?= e($motto_text) ?>&rdquo;</div>
          </div>
        </div>
      </div>

      <?php if ($values): ?>
      <div style="margin-top:60px">
        <div class="about-values reveal reveal-delay-3">
          <?php foreach ($values as $val): ?>
            <div class="value-item">
              <div class="value-icon" style="color:var(--pink-700)"><?= icon_svg($val['icon_key'], 'var(--pink-700)') ?></div>
              <div>
                <h4><?= e($val['title']) ?></h4>
                <?php if ($val['description']): ?><p><?= e($val['description']) ?></p><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div style="margin-top:80px">
        <div class="section-eyebrow reveal">Get Involved</div>
        <h2 class="section-title reveal reveal-delay-1">Meetings &amp; <em>Affiliation</em></h2>
        <div class="ab-card reveal reveal-delay-2">
          <div class="ab-meeting-grid">
            <div class="ab-meeting-item">
              <div class="ab-meeting-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
              <div><h4>Meeting Day</h4><p><?= e($meeting_day) ?></p></div>
            </div>
            <div class="ab-meeting-item">
              <div class="ab-meeting-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
              <div><h4>Meeting Time</h4><p><?= e($meeting_time) ?></p></div>
            </div>
            <div class="ab-meeting-item">
              <div class="ab-meeting-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
              <div><h4>Location</h4><p><?= e($meeting_location) ?></p></div>
            </div>
            <div class="ab-meeting-item">
              <div class="ab-meeting-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div>
              <div><h4>Sponsoring Club</h4><p><?= e($sponsor_club) ?></p></div>
            </div>
          </div>
        </div>
      </div>

      <div style="margin-top:80px">
        <div class="section-eyebrow reveal">By the Numbers</div>
        <h2 class="section-title reveal reveal-delay-1">Our <em>Impact</em></h2>
        <div class="stats-grid reveal reveal-delay-2">
          <div class="stat-card">
            <div class="stat-number"><?= e($stat_years) ?></div>
            <div class="stat-label">Years of Service</div>
          </div>
          <div class="stat-card">
            <div class="stat-number"><?= e($stat_members) ?></div>
            <div class="stat-label">Active Members</div>
          </div>
          <div class="stat-card">
            <div class="stat-number"><?= e($stat_projects) ?></div>
            <div class="stat-label">Projects Completed</div>
          </div>
          <div class="stat-card">
            <div class="stat-number"><?= e($stat_lives) ?></div>
            <div class="stat-label">Lives Impacted</div>
          </div>
        </div>
      </div>

      <div style="margin-top:80px;text-align:center">
        <div class="section-eyebrow reveal">Get Involved</div>
        <h2 class="section-title reveal reveal-delay-1">Ready to Make a <em>Difference</em>?</h2>
        <p class="section-lead reveal reveal-delay-2" style="max-width:560px;margin:0 auto 2rem">Join a global movement
          of young leaders dedicated to service, fellowship, and building a better world.</p>
        <div class="reveal reveal-delay-3">
          <a href="join.php" class="btn-submit" style="display:inline-block; margin-right:1rem; text-decoration: none;">Join Us
            &rarr;</a>
          <a href="contact.php" class="btn-submit"
            style="display:inline-block;background:transparent;border:2px solid var(--pink-700);color:var(--pink-700); text-decoration: none;">Contact
            Us</a>
        </div>
      </div>
    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/scripts.js"></script>
</body>

</html>
