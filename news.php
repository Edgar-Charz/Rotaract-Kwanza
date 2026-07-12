<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Announcement.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$announcement = new Announcement($conn);

$valid_cats = ['news', 'minutes', 'notice', 'announcement'];
$cat  = in_array($_GET['cat'] ?? '', $valid_cats) ? ($_GET['cat'] ?? '') : '';
$slug = $_GET['slug'] ?? '';

$post  = null;
$posts = [];
$page  = 1;
$pages = 1;

if ($slug) {
    $post = $announcement->findBySlug($slug) ?: null;
} else {
    $limit  = 9;
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $total  = $announcement->countPublished($cat);
    $pages  = (int) ceil($total / $limit);
    $offset = ($page - 1) * $limit;
    $posts  = $announcement->getPublished($limit, $cat, $offset);
}

$cat_labels = [
    'news'         => 'News',
    'minutes'      => 'Meeting Minutes',
    'notice'       => 'Notice',
    'announcement' => 'Announcement',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $slug && $post ? e($post['title']) . ' &mdash; ' : '' ?>News &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/kwanza.css">
  <style>
    .news-section { padding-top:100px; padding-bottom:60px; }
    .news-hero { background:linear-gradient(135deg,var(--pink-800),var(--pink-900)); padding:60px 0 40px; margin-top:60px; color:#fff; }
    .cat-filters { display:flex; gap:10px; flex-wrap:wrap; margin:24px 0; }
    .cat-btn { padding:7px 18px; border-radius:20px; font-size:13px; font-weight:600; text-decoration:none; border:1.5px solid var(--border); color:var(--text); transition:all .2s; }
    .cat-btn:hover, .cat-btn.active { background:var(--primary,#C0396B); color:#fff; border-color:var(--primary,#C0396B); }
    .news-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:24px; margin-top:24px; }
    .news-card { background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.07); transition:transform .2s,box-shadow .2s; text-decoration:none; color:inherit; display:flex; flex-direction:column; }
    .news-card:hover { transform:translateY(-3px); box-shadow:0 8px 30px rgba(192,57,107,0.13); }
    .news-card-img { height:180px; background:linear-gradient(135deg,var(--pink-200),var(--pink-500)); display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; }
    .news-card-img img { width:100%; height:100%; object-fit:cover; }
    .news-card-body { padding:20px; flex:1; display:flex; flex-direction:column; }
    .news-cat-tag { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; background:#fce4ef; color:var(--pink-700); margin-bottom:10px; }
    .news-cat-tag.minutes { background:#d6eaff; color:#1a5fb4; }
    .news-cat-tag.notice { background:#fff3cd; color:#856404; }
    .news-cat-tag.announcement { background:#f0eafd; color:#7b5ea7; }
    .news-card-body h3 { font-family:'Cormorant Garamond',serif; font-size:1.2rem; font-weight:700; margin-bottom:8px; line-height:1.35; }
    .news-card-body p { font-size:13px; color:#636e72; line-height:1.6; flex:1; }
    .news-date { font-size:12px; color:#b2bec3; margin-top:12px; }
    .single-post { max-width:760px; margin:0 auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08); }
    .single-post-img { height:320px; background:linear-gradient(135deg,var(--pink-300),var(--pink-700)); overflow:hidden; }
    .single-post-img img { width:100%; height:100%; object-fit:cover; }
    .single-post-body { padding:36px; }
    .single-post-body h1 { font-family:'Cormorant Garamond',serif; font-size:2.2rem; font-weight:700; line-height:1.25; margin:12px 0 16px; }
    .post-content { font-size:15px; line-height:1.85; color:#2d3436; white-space:pre-wrap; word-break:break-word; }
    .back-link { display:inline-flex; align-items:center; gap:6px; color:var(--pink-700); font-weight:600; font-size:14px; text-decoration:none; margin-bottom:24px; }
    .back-link:hover { gap:8px; }
    .pagination-pub { display:flex; gap:8px; justify-content:center; margin-top:40px; flex-wrap:wrap; }
    .page-btn { padding:8px 14px; border-radius:7px; border:1.5px solid var(--border); color:var(--text); text-decoration:none; font-size:13px; transition:all .2s; }
    .page-btn:hover { border-color:var(--primary); color:var(--primary); }
    .page-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
    .page-btn.disabled { opacity:.4; pointer-events:none; }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="news-hero">
  <div class="container">
    <div class="section-eyebrow" style="color:rgba(255,255,255,0.7);justify-content:flex-start">Club Updates</div>
    <h1 style="font-family:'Cormorant Garamond',serif;font-size:2.4rem;font-weight:700;margin:8px 0 0">News &amp; <em>Announcements</em></h1>
  </div>
</div>

<div class="container news-section" style="padding-top:32px">
  <?php if ($slug): ?>
    <?php if (!$post): ?>
      <p style="text-align:center;color:var(--text-soft);padding:60px 0">Post not found. <a href="news.php">Back to News</a></p>
    <?php else: ?>
      <a href="news.php<?= $cat ? '?cat=' . $cat : '' ?>" class="back-link">&#8592; Back to News</a>
      <div class="single-post">
        <?php if ($post['image_path']): ?>
          <div class="single-post-img"><img src="<?= e(img_url($post['image_path'])) ?>" alt="<?= e($post['title']) ?>"></div>
        <?php endif; ?>
        <div class="single-post-body">
          <span class="news-cat-tag <?= e($post['category']) ?>"><?= e($cat_labels[$post['category']] ?? $post['category']) ?></span>
          <h1><?= e($post['title']) ?></h1>
          <p style="font-size:13px;color:#b2bec3;margin-bottom:24px"><?= date('l, d F Y', strtotime($post['created_at'])) ?></p>
          <div class="post-content"><?= strip_tags($post['content'], '<p><br><b><i><u><s><strong><em><ul><ol><li><h2><h3><a><blockquote>') ?></div>
        </div>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <div class="cat-filters">
      <a href="news.php" class="cat-btn <?= !$cat ? 'active' : '' ?>">All</a>
      <?php foreach ($cat_labels as $k => $label): ?>
        <a href="news.php?cat=<?= $k ?>" class="cat-btn <?= $cat === $k ? 'active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($posts): ?>
      <div class="news-grid">
        <?php foreach ($posts as $item): ?>
          <a href="news.php?slug=<?= e($item['slug']) ?>" class="news-card">
            <div class="news-card-img">
              <?php if ($item['image_path']): ?>
                <img src="<?= e(img_url($item['image_path'])) ?>" alt="<?= e($item['title']) ?>">
              <?php else: ?>
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              <?php endif; ?>
            </div>
            <div class="news-card-body">
              <span class="news-cat-tag <?= e($item['category']) ?>"><?= e($cat_labels[$item['category']] ?? $item['category']) ?></span>
              <h3><?= e($item['title']) ?></h3>
              <p><?= e(mb_strimwidth(strip_tags($item['content']), 0, 140, '…')) ?></p>
              <div class="news-date"><?= date('d M Y', strtotime($item['created_at'])) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
        <div class="pagination-pub">
          <a href="?page=<?= $page - 1 ?>&cat=<?= $cat ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹ Prev</a>
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=<?= $i ?>&cat=<?= $cat ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <a href="?page=<?= $page + 1 ?>&cat=<?= $cat ?>" class="page-btn <?= $page >= $pages ? 'disabled' : '' ?>">Next ›</a>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div style="text-align:center;padding:80px 20px;color:var(--text-soft)">
        <p style="font-size:1.2rem;font-weight:600">No posts yet</p>
        <p style="margin-top:8px">Check back soon for club news and announcements.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/scripts.js"></script>
</body>
</html>
