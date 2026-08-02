<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Announcement.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$settings    = new SiteSettings($conn);
$site_name   = $settings->get('site_name', 'Rotaract Club of Kwanza');
$description = $settings->get('home_news_description', 'Club updates, meeting minutes, and announcements from Rotaract Club of Kwanza.');

$origin = site_origin();
$posts  = (new Announcement($conn))->getPublished(30);

// Real feed readers ask for application/rss+xml / application/atom+xml /
// application/xml and typically omit text/html entirely. Every browser
// navigating to a URL — Chrome, Firefox, Edge, embedded webviews — puts
// text/html in its Accept header. That split is a far more reliable signal
// than an xml-stylesheet processing instruction, which Chromium-based
// browsers no longer apply at all (XSLT support was removed browser-side
// in 2024/2025) — so a person clicking the link gets a readable page,
// while pasting the same URL into a reader still gets standards-compliant RSS.
$accept    = $_SERVER['HTTP_ACCEPT'] ?? '';
$isBrowser = str_contains($accept, 'text/html');

$esc = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

if ($isBrowser) {
  header('Content-Type: text/html; charset=utf-8');
?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $esc($site_name) ?> — News RSS Feed</title>
    <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
    <link rel="alternate" type="application/rss+xml" title="<?= $esc($site_name) ?> — News" href="<?= $esc($origin . '/rss.php') ?>">
    <style>
      body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: #fff5f9;
        color: #2d3436;
        margin: 0
      }

      .hdr {
        background: linear-gradient(135deg, #8a2450, #C0396B);
        color: #fff;
        padding: 48px 24px
      }

      .hdr .badge {
        display: inline-block;
        background: rgba(255, 255, 255, .15);
        border: 1px solid rgba(255, 255, 255, .35);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        margin-bottom: 16px
      }

      .hdr h1 {
        margin: 0 0 10px;
        font-family: Georgia, 'Cormorant Garamond', serif;
        font-size: 2rem
      }

      .hdr p {
        margin: 0;
        opacity: .85;
        max-width: 640px;
        line-height: 1.6
      }

      .wrap {
        max-width: 720px;
        margin: 0 auto;
        padding: 28px 24px 80px
      }

      .note {
        font-size: 12.5px;
        color: #9a8f95;
        margin: 0 0 24px;
        line-height: 1.6;
        background: #fff;
        border: 1px solid #f2e3ea;
        border-radius: 10px;
        padding: 14px 16px
      }

      .note code {
        background: #fdf3f7;
        border: 1px solid #eee0e5;
        border-radius: 5px;
        padding: 1px 6px;
        word-break: break-all
      }

      .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #8a2450;
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
        margin-bottom: 20px
      }

      .back-link:hover {
        gap: 8px
      }

      .item {
        background: #fff;
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 18px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .06)
      }

      .item h2 {
        margin: 0 0 8px;
        font-size: 1.2rem;
        font-family: Georgia, 'Cormorant Garamond', serif
      }

      .item h2 a {
        color: #8a2450;
        text-decoration: none
      }

      .item h2 a:hover {
        text-decoration: underline
      }

      .meta {
        font-size: 12px;
        color: #a19499;
        margin-bottom: 12px
      }

      .cat {
        display: inline-block;
        background: #fbe9f0;
        color: #8a2450;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
        margin-right: 8px
      }

      .desc {
        font-size: 14px;
        line-height: 1.75;
        color: #555;
        white-space: pre-line
      }
    </style>
  </head>

  <body>
    <div class="hdr">
      <div class="badge">RSS Feed</div>
      <h1><?= $esc($site_name) ?> — News</h1>
      <p><?= $esc($description) ?></p>
    </div>
    <div class="wrap">
      <a href="news.php" class="back-link">&#8592; Back to News</a>
      <?php foreach ($posts as $post): ?>
        <div class="item">
          <h2><a href="news.php?slug=<?= $esc($post['slug']) ?>"><?= $esc($post['title']) ?></a></h2>
          <div class="meta">
            <span class="cat"><?= $esc($post['category']) ?></span>
            <?= date('d M Y', strtotime($post['created_at'])) ?>
          </div>
          <div class="desc"><?= $esc(mb_strimwidth(trim(strip_tags($post['content'])), 0, 300, '…')) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </body>

  </html>
<?php
  exit;
}

// ── Actual RSS XML, served to feed readers / non-browser clients ──────────
$xmlEsc = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
  <channel>
    <title><?= $xmlEsc($site_name) ?> — News</title>
    <link><?= $xmlEsc($origin . '/news.php') ?></link>
    <description><?= $xmlEsc($description) ?></description>
    <language>en</language>
    <lastBuildDate><?= date(DATE_RSS) ?></lastBuildDate>
    <?php foreach ($posts as $post): ?>
      <item>
        <title><?= $xmlEsc($post['title']) ?></title>
        <link><?= $xmlEsc($origin . '/news.php?slug=' . rawurlencode($post['slug'])) ?></link>
        <guid isPermaLink="false"><?= $xmlEsc('rotaract-kwanza-announcement-' . $post['id']) ?></guid>
        <pubDate><?= date(DATE_RSS, strtotime($post['created_at'])) ?></pubDate>
        <category><?= $xmlEsc($post['category']) ?></category>
        <description><?= $xmlEsc(mb_strimwidth(trim(strip_tags($post['content'])), 0, 300, '…')) ?></description>
      </item>
    <?php endforeach; ?>
  </channel>
</rss>