<?php

/**
 * One-time bootstrap for the very first admin account. Self-disables the
 * instant an admin row exists — from then on, additional admins are created
 * from within the dashboard (Settings → Admins, super_admin only), the same
 * way any other authenticated action is gated. Not linked from anywhere;
 * meant to be visited directly once, right after deploying.
 */
require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/rate_limit.php';
require_once dirname(__DIR__) . '/classes/Admin.php';
require_once dirname(__DIR__) . '/classes/ActivityLog.php';

$db   = new Database();
$conn = $db->connect();

if (isset($_SESSION['admin_id'])) {
  header('Location: index.php');
  exit;
}

$admin_obj = new Admin($conn);
$closed    = $admin_obj->count() > 0;
$error     = '';

if (!$closed && $_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  if (!rate_limit_allow($conn, 'admin_register', 5, 900)) {
    $error = rate_limit_message();
  } else {
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($admin_obj->count() > 0) {
      // Someone else finished the bootstrap in the moment between our
      // GET and this POST — re-check right before creating, not just
      // on page load, so two simultaneous first-run attempts can't
      // both succeed.
      $closed = true;
    } elseif ($username === '' || $password === '') {
      $error = 'Username and password are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
      $error = 'Username may only contain letters, numbers, dots, dashes, and underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
      $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
      $error = 'Passwords do not match.';
    } else {
      try {
        $id = $admin_obj->create($username, $password, $full_name, 'super_admin', $email);
        if ($id) {
          session_regenerate_id(true);
          $_SESSION['admin_id']         = $id;
          $_SESSION['admin_username']   = $username;
          $_SESSION['admin_name']       = $full_name;
          $_SESSION['admin_role']       = 'super_admin';
          $_SESSION['admin_login_time'] = time();
          try {
            (new ActivityLog($conn))->log(
              $id,
              $username,
              'register',
              'Created the first admin account',
              substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)
            );
          } catch (Throwable $e) {
          }
          header('Location: index.php');
          exit;
        }
        $error = 'That username is already taken.';
      } catch (mysqli_sql_exception $e) {
        $error = 'Could not create the account. Please try again.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Admin Account | Rotaract Kwanza</title>
  <link rel="icon" type="image/png" href="../assets/img/logo1.jpg">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/admin.css">
</head>

<body class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div class="logo-badge">RK</div>
      <h1>Rotaract Kwanza</h1>
      <p>Admin Panel | <?= $closed ? 'Registration Closed' : 'Create First Admin Account' ?></p>
    </div>

    <?php if ($closed): ?>
      <div class="alert alert-error">An admin account already exists — registration is closed. Additional admins are created from within the dashboard.</div>
      <a href="login.php" class="btn btn-primary">Go to Login</a>
    <?php else: ?>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="full_name">Full Name</label>
          <input type="text" id="full_name" name="full_name" autofocus
            value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            autocomplete="username">
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            autocomplete="email">
          <span class="form-hint">Used for password reset notifications</span>
        </div>
        <div class="form-group">
          <label for="password">Password <span class="text-muted fw-normal">(min. 8 characters)</span></label>
          <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">
          Create Account
        </button>
      </form>
    <?php endif; ?>
  </div>
</body>

</html>