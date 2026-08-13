<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/helpers.php';

http_response_code(404);

$db = new Database();
$conn = $db->connect();
$page_title = site_title($conn, 'Page Not Found');
$page_description = 'The page you were looking for could not be found.';

// .htaccess routes any unmatched URL to this file via an internal rewrite,
// so the browser's address bar still shows the original (bogus, possibly
// deeply nested) path. Every other page's relative asset/link hrefs resolve
// fine because the user actually navigated to that page's real URL — this
// one has no such guarantee, so a <base> tag pins all relative resolution
// back to the site root (same root-detection trick as img_url()).
$__script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/404.php');
$__base_href = rtrim(dirname($__script), '/') . '/';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <base href="<?= e($__base_href) ?>">
  <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <section class="error-404-section">
    <div class="container text-center">
      <div class="error-404-code">
        404</div>
      <h1 class="section-title mt-8">Page Not <em>Found</em></h1>
      <p class="section-lead error-404-lead">The page you're looking for may have been
        moved, renamed, or never existed.</p>
      <a href="index.php" class="btn-submit error-404-btn">Back to Home &rarr;</a>
    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/scripts.js"></script>
</body>

</html>