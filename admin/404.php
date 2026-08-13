<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);

$page_title = 'Page Not Found';

include __DIR__ . '/includes/header.php';
?>

<div class="card error-404-card">
  <div class="error-404-code">404</div>
  <h2 class="mt-1">Page Not Found</h2>
  <p class="text-muted mt-1">The admin page you're looking for may have been moved, renamed, or never existed.</p>
  <a href="index.php" class="btn btn-primary error-404-btn">Back to Dashboard</a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>