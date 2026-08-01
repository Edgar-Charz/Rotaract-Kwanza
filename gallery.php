<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Gallery.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$gallery_obj = new Gallery($conn);
$categories  = $gallery_obj->getActiveCategories();
$cat = in_array($_GET['cat'] ?? '', $categories, true) ? $_GET['cat'] : '';

$limit  = 24;
$page   = max(1, (int) ($_GET['page'] ?? 1));
$total  = $gallery_obj->countActiveFiltered($cat);
$pages  = max(1, (int) ceil($total / $limit));
$page   = min($page, $pages);
$offset = ($page - 1) * $limit;
$photos = $gallery_obj->getActivePage($limit, $offset, $cat);

// Cycle: tall, reg, reg, wide, reg, reg
// With grid-auto-flow:dense this fills beautifully into a 4-col grid
$patterns = ['tall', '', '', 'wide', '', ''];

$gallery_intro = site_setting($conn, 'home_gallery_description', 'A glimpse into our community service, events, and fellowship moments.');

$page_title = site_title($conn, 'Gallery');
$page_description = $gallery_intro;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
  <style>
    .gal-filters {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin: 28px 0 8px;
    }

    .gal-filter-btn {
      display: inline-block;
      padding: 7px 18px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      border: 1.5px solid var(--border);
      background: #fff;
      color: var(--text);
      cursor: pointer;
      font-family: inherit;
      text-decoration: none;
      transition: all .2s;
    }

    .gal-filter-btn.active,
    .gal-filter-btn:hover {
      background: var(--pink-700);
      color: #fff;
      border-color: var(--pink-700);
    }
  </style>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <section id="gallery" style="padding-top:100px">
    <div class="container">
      <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;margin-bottom:0">
        <div>
          <div class="section-eyebrow reveal">Our Moments</div>
          <h2 class="section-title reveal reveal-delay-1">Photo <em>Gallery</em></h2>
          <p class="section-lead reveal reveal-delay-2"><?= e($gallery_intro) ?></p>
        </div>
        <span class="btn-secondary" style="opacity:.6"><?= $total ?> Photo<?= $total !== 1 ? 's' : '' ?></span>
      </div>

      <?php if ($categories): ?>
        <div class="gal-filters">
          <a href="gallery.php" class="gal-filter-btn <?= $cat === '' ? 'active' : '' ?>">All</a>
          <?php foreach ($categories as $catOption): ?>
            <a href="?cat=<?= urlencode($catOption) ?>" class="gal-filter-btn <?= $cat === $catOption ? 'active' : '' ?>"><?= e($catOption) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($photos): ?>
        <div class="gallery-grid">
          <?php foreach ($photos as $gi => $photo):
            $gc  = $patterns[$gi % count($patterns)];
            $cls = 'gallery-item reveal' . ($gc ? ' ' . $gc : '');
          ?>
            <div class="<?= $cls ?>"
              <?php if ($photo['image_path']): ?>
              data-src="<?= e(img_url($photo['image_path'])) ?>"
              data-title="<?= e($photo['title']) ?>"
              data-desc="<?= e($photo['description'] ?? '') ?>"
              tabindex="0"
              role="button"
              aria-label="View <?= e($photo['title']) ?>"
              <?php endif; ?>>
              <?php if ($photo['image_path']): ?>
                <div class="gallery-inner">
                  <img src="<?= e(img_url($photo['image_path'])) ?>" alt="<?= e($photo['title']) ?>"
                    style="width:100%;height:100%;object-fit:cover;display:block">
                </div>
              <?php else: ?>
                <div class="gallery-inner" style="background:linear-gradient(145deg,var(--pink-300),var(--pink-700));display:flex;align-items:center;justify-content:center">
                  <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                    <rect x="10" y="10" width="40" height="40" rx="4" fill="rgba(255,255,255,0.2)" />
                    <circle cx="22" cy="22" r="5" fill="rgba(255,255,255,0.4)" />
                    <path d="M10 40l12-12 8 8 6-6 14 10H10z" fill="rgba(255,255,255,0.3)" />
                  </svg>
                </div>
              <?php endif; ?>
              <div class="gallery-overlay">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;flex-shrink:0">
                  <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
                </svg>
                <?= e($photo['title']) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
          <div class="pagination-pub">
            <a href="?page=<?= $page - 1 ?>&cat=<?= urlencode($cat) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹ Prev</a>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
              <a href="?page=<?= $i ?>&cat=<?= urlencode($cat) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?page=<?= $page + 1 ?>&cat=<?= urlencode($cat) ?>" class="page-btn <?= $page >= $pages ? 'disabled' : '' ?>">Next ›</a>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div style="text-align:center;padding:80px 20px;color:var(--text-soft)">
          <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="opacity:.3;margin-bottom:16px">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <polyline points="21 15 16 10 5 21" />
          </svg>
          <p style="font-size:1.2rem;font-weight:600">Gallery is empty</p>
          <p style="margin-top:8px">Photos will appear here once uploaded through the admin dashboard.</p>
        </div>
      <?php endif; ?>
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

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/scripts.js"></script>
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
          title: el.dataset.title || '',
          desc: el.dataset.desc || ''
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
        cap.textContent = photos[i].desc ? photos[i].title + ' — ' + photos[i].desc : photos[i].title;
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
</body>

</html>