<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Event.php';
require_once __DIR__ . '/classes/EventPhoto.php';
require_once __DIR__ . '/classes/EventRSVP.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$id    = (int) ($_GET['id'] ?? 0);
$event = $id ? (new Event($conn))->findById($id) : false;

if (!$event) {
    http_response_code(404);
}

$photos     = $event ? (new EventPhoto($conn))->getByEvent($id) : [];
$going      = ($event && $event['status'] === 'upcoming') ? (new EventRSVP($conn))->getAttendanceSummary($id)['total'] : 0;
$event_colors = ['', 'gold', 'rose'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $event ? e($event['title']) : 'Event Not Found' ?> &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/kwanza.css">
  <style>
    .ev-hero { position:relative; border-radius:18px; overflow:hidden; margin-bottom:32px; min-height:280px; display:flex; align-items:flex-end; }
    .ev-hero-img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .ev-hero-fallback { position:absolute; inset:0; background:linear-gradient(135deg,var(--pink-600),var(--pink-900)); }
    .ev-hero-fallback.gold { background:linear-gradient(135deg,#D4882A,#8a5312); }
    .ev-hero-fallback.rose { background:linear-gradient(135deg,#e88ab5,var(--pink-800)); }
    .ev-hero-overlay { position:relative; z-index:1; width:100%; padding:32px; background:linear-gradient(0deg,rgba(0,0,0,.55),rgba(0,0,0,0) 70%); }
    .ev-hero-overlay h1 { color:#fff; font-family:'Cormorant Garamond',serif; font-size:2.2rem; margin:8px 0; }
    .ev-status { display:inline-block; padding:4px 14px; border-radius:20px; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
    .ev-status.upcoming  { background:#d1f2e0; color:#1a5c35; }
    .ev-status.past      { background:#e9ecef; color:#555; }
    .ev-status.cancelled { background:#fde8e8; color:#9b2335; }
    .ev-meta-row { display:flex; gap:20px; flex-wrap:wrap; color:rgba(255,255,255,.9); font-size:14px; margin-top:6px; }
    .ev-meta-row span { display:flex; align-items:center; gap:6px; }
    .ev-body { display:grid; grid-template-columns:2fr 1fr; gap:36px; align-items:start; }
    .ev-desc { font-size:15px; line-height:1.8; color:var(--text); white-space:pre-line; }
    .ev-cta-card { background:#fff; border-radius:14px; padding:26px; box-shadow:0 2px 14px rgba(0,0,0,.07); text-align:center; }
    .ev-cta-card p { color:var(--text-soft); font-size:13.5px; margin-bottom:16px; }
    .ev-back { display:inline-flex; align-items:center; gap:6px; color:var(--pink-800); font-weight:600; font-size:14px; margin-bottom:24px; text-decoration:none; }
    .ev-socials { display:flex; gap:10px; margin-top:20px; }
    .ev-social-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:20px; background:var(--pink-50); border:1.5px solid var(--pink-200); color:var(--pink-800); font-size:13px; font-weight:600; text-decoration:none; transition:background .2s; }
    .ev-social-btn:hover { background:var(--pink-100); }
    @media (max-width:820px) { .ev-body { grid-template-columns:1fr; } }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section id="event-detail" style="padding-top:100px">
  <div class="container">
    <?php if (!$event): ?>
      <div style="text-align:center;padding:80px 20px;color:var(--text-soft)">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="opacity:.3;margin-bottom:16px">
          <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <p style="font-size:1.2rem;font-weight:600">Event not found</p>
        <p style="margin-top:8px">This event may have been removed or the link is incorrect.</p>
        <a href="events.php" class="ev-back" style="margin-top:20px">&#8592; Back to All Events</a>
      </div>
    <?php else: ?>

      <a href="events.php" class="ev-back">&#8592; Back to All Events</a>

      <div class="ev-hero">
        <?php if ($event['image_path']): ?>
          <img class="ev-hero-img" src="<?= e(img_url($event['image_path'])) ?>" alt="<?= e($event['title']) ?>">
        <?php else: ?>
          <div class="ev-hero-fallback <?= $event_colors[$event['id'] % 3] ?>"></div>
        <?php endif; ?>
        <div class="ev-hero-overlay">
          <span class="ev-status <?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span>
          <h1><?= e($event['title']) ?></h1>
          <div class="ev-meta-row">
            <span>&#128197; <?= date('l, d F Y', strtotime($event['event_date'])) ?></span>
            <?php if ($event['event_time']): ?><span>&#128336; <?= e($event['event_time']) ?></span><?php endif; ?>
            <?php if ($event['location']): ?><span>&#128205; <?= e($event['location']) ?></span><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="ev-body">
        <div>
          <span class="event-tag"><?= e($event['category'] ?? 'General') ?></span>
          <?php if ($event['description']): ?>
            <p class="ev-desc" style="margin-top:14px"><?= e($event['description']) ?></p>
          <?php endif; ?>

          <?php if ($photos): ?>
            <h3 class="section-title" style="font-size:1.5rem;margin:40px 0 20px">Photo <em>Gallery</em></h3>
            <div class="gallery-grid">
              <?php foreach ($photos as $pi => $p): ?>
              <div class="gallery-item reveal" data-src="<?= e(img_url($p['image_path'])) ?>" data-title="<?= e($event['title']) ?>" tabindex="0" role="button" aria-label="View photo">
                <div class="gallery-inner">
                  <img src="<?= e(img_url($p['image_path'])) ?>" alt="<?= e($event['title']) ?>" style="width:100%;height:100%;object-fit:cover;display:block">
                </div>
                <div class="gallery-overlay">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;flex-shrink:0"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                  View
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="ev-cta-card">
          <?php if ($event['status'] === 'upcoming'): ?>
            <?php if ($going > 0): ?>
              <p><strong><?= $going ?></strong> <?= $going === 1 ? 'person is' : 'people are' ?> already going!</p>
            <?php else: ?>
              <p>Be the first to RSVP for this event.</p>
            <?php endif; ?>
            <a href="rsvp.php?id=<?= $event['id'] ?>"
               style="display:inline-block;padding:12px 28px;background:linear-gradient(135deg,var(--pink-600),var(--pink-800));color:#fff;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none">
              RSVP Now &rarr;
            </a>
          <?php elseif ($event['status'] === 'cancelled'): ?>
            <p>This event has been cancelled.</p>
          <?php else: ?>
            <p>This event has ended. Thank you to everyone who joined us!</p>
            <?php if ($photos): ?><p style="margin-top:8px">Check out the photo recap on the left.</p><?php endif; ?>
          <?php endif; ?>

          <?php if ($event['instagram_url'] || $event['tiktok_url']): ?>
            <div class="ev-socials" style="justify-content:center">
              <?php if ($event['instagram_url']): ?>
                <a href="<?= e($event['instagram_url']) ?>" target="_blank" rel="noopener" class="ev-social-btn">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                  Instagram
                </a>
              <?php endif; ?>
              <?php if ($event['tiktok_url']): ?>
                <a href="<?= e($event['tiktok_url']) ?>" target="_blank" rel="noopener" class="ev-social-btn">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0115.54 3h-3.09v12.4a2.592 2.592 0 01-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 004.3 1.38V7.3s-1.88.09-3.24-1.48z"/></svg>
                  TikTok
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    <?php endif; ?>
  </div>
</section>

<?php if ($photos): ?>
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

<script src="assets/js/scripts.js"></script>
<?php if ($photos): ?>
<script>
(function () {
  var photos  = [];
  var current = 0;
  var overlay = document.getElementById('lb-overlay');
  var img     = document.getElementById('lb-img');
  var cap     = document.getElementById('lb-caption');
  var counter = document.getElementById('lb-counter');

  document.querySelectorAll('.gallery-item[data-src]').forEach(function (el, i) {
    photos.push({ src: el.dataset.src, title: el.dataset.title || '' });
    el.addEventListener('click', function () { openLb(i); });
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openLb(i); }
    });
  });

  function openLb(i) {
    current   = i;
    img.src   = photos[i].src;
    img.alt   = photos[i].title;
    cap.textContent     = photos[i].title;
    counter.textContent = (i + 1) + ' / ' + photos.length;
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    document.getElementById('lb-close').focus();
  }

  function closeLb() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(function () { img.src = ''; }, 250);
  }

  function prevImg() { openLb((current - 1 + photos.length) % photos.length); }
  function nextImg() { openLb((current + 1) % photos.length); }

  document.getElementById('lb-close').addEventListener('click', closeLb);
  document.getElementById('lb-prev').addEventListener('click', function (e) { e.stopPropagation(); prevImg(); });
  document.getElementById('lb-next').addEventListener('click', function (e) { e.stopPropagation(); nextImg(); });

  overlay.addEventListener('click', function (e) { if (e.target === overlay) closeLb(); });

  document.addEventListener('keydown', function (e) {
    if (!overlay.classList.contains('open')) return;
    if (e.key === 'Escape')     closeLb();
    if (e.key === 'ArrowLeft')  prevImg();
    if (e.key === 'ArrowRight') nextImg();
  });
})();
</script>
<?php endif; ?>
</body>
</html>
