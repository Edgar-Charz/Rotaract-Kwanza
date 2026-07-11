<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Project.php';
require_once __DIR__ . '/classes/ProjectPhoto.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$id      = (int) ($_GET['id'] ?? 0);
$project = $id ? (new Project($conn))->findById($id) : false;

if (!$project) {
    http_response_code(404);
}

$photos = $project ? (new ProjectPhoto($conn))->getByProject($id) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $project ? e($project['title']) : 'Project Not Found' ?> &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/kwanza.css">
  <style>
    .pj-hero-img { width:100%; max-height:380px; object-fit:cover; border-radius:16px; margin-bottom:28px; }
    .pj-icon-lg { width:64px; height:64px; border-radius:16px; background:rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center; margin-bottom:24px; }
    .pj-icon-lg svg { width:32px; height:32px; }
    .pj-badges { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
    .pj-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
    .pj-badge.active    { background:rgba(39,174,96,0.2); color:#6fe3a3; }
    .pj-badge.completed { background:rgba(255,255,255,0.15); color:#fff; }
    .pj-badge.featured  { background:rgba(212,136,42,0.25); color:var(--gold-light); border:1px solid rgba(212,136,42,0.4); }
    .pj-desc { font-size:15px; line-height:1.8; color:rgba(255,255,255,.8); white-space:pre-line; margin:18px 0 32px; max-width:720px; }
    .pj-back { display:inline-flex; align-items:center; gap:6px; color:rgba(255,255,255,.85); font-weight:600; font-size:14px; margin-bottom:24px; text-decoration:none; }
    .pj-cta { display:inline-block; margin-top:8px; padding:12px 28px; background:linear-gradient(135deg,var(--gold),#b06a1e); color:#fff; border-radius:10px; font-size:14px; font-weight:700; text-decoration:none; }
    .pj-socials { display:flex; gap:10px; margin-top:20px; }
    .pj-social-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:20px; background:rgba(255,255,255,.1); border:1.5px solid rgba(255,255,255,.2); color:#fff; font-size:13px; font-weight:600; text-decoration:none; transition:background .2s; }
    .pj-social-btn:hover { background:rgba(255,255,255,.2); }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

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

      <a href="join.php" class="pj-cta">Get Involved &rarr;</a>

      <?php if ($project['instagram_url'] || $project['tiktok_url']): ?>
        <div class="pj-socials">
          <?php if ($project['instagram_url']): ?>
            <a href="<?= e($project['instagram_url']) ?>" target="_blank" rel="noopener" class="pj-social-btn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
              Instagram
            </a>
          <?php endif; ?>
          <?php if ($project['tiktok_url']): ?>
            <a href="<?= e($project['tiktok_url']) ?>" target="_blank" rel="noopener" class="pj-social-btn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0115.54 3h-3.09v12.4a2.592 2.592 0 01-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 004.3 1.38V7.3s-1.88.09-3.24-1.48z"/></svg>
              TikTok
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
      <?php foreach ($photos as $p): ?>
      <div class="gallery-item reveal" data-src="<?= e(img_url($p['image_path'])) ?>" data-title="<?= e($project['title']) ?>" tabindex="0" role="button" aria-label="View photo">
        <div class="gallery-inner">
          <img src="<?= e(img_url($p['image_path'])) ?>" alt="<?= e($project['title']) ?>" style="width:100%;height:100%;object-fit:cover;display:block">
        </div>
        <div class="gallery-overlay">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;flex-shrink:0"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
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
