<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);

$page_title = 'Page Not Found';

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="text-align:center;padding:60px 40px">
  <div style="font-family:'Cormorant Garamond',serif;font-size:4rem;font-weight:700;color:var(--primary);line-height:1">404</div>
  <h2 style="margin-top:8px">Page Not Found</h2>
  <p class="text-muted" style="margin-top:8px">The admin page you're looking for may have been moved, renamed, or never existed.</p>
  <a href="index.php" class="btn btn-primary" style="margin-top:20px;display:inline-block">Back to Dashboard</a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
