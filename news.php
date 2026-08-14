<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Announcement.php';
require_once __DIR__ . '/includes/csrf.php';
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

$news_intro = site_setting($conn, 'home_news_description', 'Club updates, meeting minutes, and announcements from Rotaract Club of Kwanza.');

$page_title = $slug && $post ? site_title($conn, $post['title']) : site_title($conn, 'News');
$page_description = $slug && $post
  ? mb_strimwidth(trim(strip_tags($post['content'])), 0, 160, '…')
  : 'Club updates, meeting minutes, and announcements from Rotaract Club of Kwanza.';
$page_image = ($slug && $post && $post['image_path']) ? img_url($post['image_path']) : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>
  <?php require_once __DIR__ . '/includes/flash_toast.php'; ?>

  <section id="news" class="pt-100">
    <div class="container">
      <div class="projects-header">
        <div>
      <div class="section-eyebrow section-eyebrow--hero-dark reveal">Club Updates</div>
          <h2 class="section-title reveal reveal-delay-1">News &amp; <em>Announcements</em></h2>
          <p class="section-lead reveal reveal-delay-2 section-lead--white"><?= e($news_intro) ?></p>
        </div>
      </div>

    <?php if ($slug): ?>
      <?php if (!$post): ?>
        <p class="news-post-not-found reveal">Post not found. <a href="news.php">Back to News</a></p>
      <?php else: ?>
        <a href="news.php<?= $cat ? '?cat=' . $cat : '' ?>" class="back-link reveal">&#8592; Back to News</a>
        <div class="single-post reveal">
          <?php if ($post['image_path']): ?>
            <div class="single-post-img"><img src="<?= e(img_url($post['image_path'])) ?>" alt="<?= e($post['title']) ?>"></div>
          <?php endif; ?>
          <div class="single-post-body">
            <span class="news-cat-tag <?= e($post['category']) ?>"><?= e($cat_labels[$post['category']] ?? $post['category']) ?></span>
            <h1><?= e($post['title']) ?></h1>
            <p class="news-post-date"><?= date('l, d F Y', strtotime($post['created_at'])) ?></p>
            <div class="post-content"><?= sanitize_rich_text($post['content']) ?></div>
            <?= render_share_buttons(current_page_url(), $post['title']) ?>
          </div>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="cat-filters reveal">
        <a href="news.php" class="cat-btn <?= !$cat ? 'active' : '' ?>">All</a>
        <?php foreach ($cat_labels as $k => $label): ?>
          <a href="news.php?cat=<?= $k ?>" class="cat-btn <?= $cat === $k ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <a href="rss.php" class="cat-btn cat-btn--rss" title="Subscribe via RSS">&#128225; RSS</a>
      </div>

      <div id="subscribe" class="subscribe-box reveal reveal-delay-1">
        <div>
          <p class="subscribe-title">Prefer email over RSS?</p>
          <p class="subscribe-text">Get notified whenever we post news, minutes, or announcements.</p>
        </div>
        <form action="subscribe.php" method="POST" class="subscribe-form">
          <?= csrf_field() ?>
          <input type="hidden" name="redirect" value="news.php">
          <input type="email" name="email" required placeholder="you@email.com"
            class="subscribe-input">
          <button type="submit" class="subscribe-btn">Get Notified</button>
        </form>
      </div>

      <?php if ($posts): ?>
        <div class="news-grid">
          <?php foreach ($posts as $item): ?>
            <a href="news.php?slug=<?= e($item['slug']) ?>" class="news-card reveal">
              <div class="news-card-img">
                <?php if ($item['image_path']): ?>
                  <img src="<?= e(img_url($item['image_path'])) ?>" alt="<?= e($item['title']) ?>">
                <?php else: ?>
                  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                  </svg>
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
          <div class="pagination-pub reveal">
            <a href="?page=<?= $page - 1 ?>&cat=<?= $cat ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹ Prev</a>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
              <a href="?page=<?= $i ?>&cat=<?= $cat ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?page=<?= $page + 1 ?>&cat=<?= $cat ?>" class="page-btn <?= $page >= $pages ? 'disabled' : '' ?>">Next ›</a>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="gallery-empty reveal">
          <p class="gallery-empty-title">No posts yet</p>
          <p class="mt-8">Check back soon for club news and announcements.</p>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/scripts.js"></script>
</body>

</html>
