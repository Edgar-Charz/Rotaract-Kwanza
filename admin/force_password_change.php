<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Admin.php';

// Nothing to do here if the account isn't actually flagged — send them on
// to the dashboard instead of showing a pointless form.
if (empty($_SESSION['must_change_password'])) {
  header('Location: ' . ADMIN_URL . '/index.php');
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $new     = $_POST['new_password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  if (strlen($new) < 8) {
    $error = 'New password must be at least 8 characters.';
  } elseif ($new !== $confirm) {
    $error = 'Passwords do not match.';
  } else {
    (new Admin($conn))->updatePassword((int) $_SESSION['admin_id'], $new);
    unset($_SESSION['must_change_password']);
    log_activity('force_password_change', 'Completed a required password reset');
    flash('success', 'Password updated. You can now use the dashboard as normal.');
    header('Location: ' . ADMIN_URL . '/index.php');
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Set New Password | Rotaract Kwanza</title>
  <link rel="icon" type="image/png" href="../assets/img/logo1.jpg">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/admin.css">
</head>

<body class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div class="logo-badge">RK</div>
      <h1>Rotaract Kwanza</h1>
      <p>A password reset was issued for your account &mdash; set a new password to continue.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" autofocus>
        <span class="form-hint">At least 8 characters</span>
      </div>
      <div class="form-group">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary">
        Set Password &amp; Continue
      </button>
    </form>

    <p class="text-center mt-2">
      <a href="logout.php" class="footer-link">Log out instead</a>
    </p>
  </div>
</body>

</html>