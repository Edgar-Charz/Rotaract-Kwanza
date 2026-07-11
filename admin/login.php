<?php
require_once dirname(__DIR__) . '/includes/session_init.php';

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/classes/Admin.php';

$db   = new Database();
$conn = $db->connect();

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// ── Rate limiting (DB-backed per username: 5 attempts per 15 minutes) ────────
// Persisted in the login_attempts table rather than $_SESSION, so an attacker
// can't reset their attempt count just by dropping the session cookie.
$MAX_ATTEMPTS = 5;
$LOCKOUT_SECS = 15 * 60;

$posted_username = trim($_POST['username'] ?? '');
$error   = '';
$locked  = false;
$lockout = 0;

if ($posted_username !== '') {
    $stmt = $conn->prepare('SELECT UNIX_TIMESTAMP(locked_until) AS locked_until FROM login_attempts WHERE username = ?');
    $stmt->bind_param('s', $posted_username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && $row['locked_until'] && time() < $row['locked_until']) {
        $locked  = true;
        $lockout = (int)$row['locked_until'];
    }
}

if ($locked) {
    $remaining = ceil(($lockout - time()) / 60);
    $error = "Too many failed attempts. Try again in {$remaining} minute(s).";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    $username = $posted_username;
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $admin = (new Admin($conn))->login($username, $password);
            if ($admin) {
                // Success - clear this user's attempt record and start session
                $del = $conn->prepare('DELETE FROM login_attempts WHERE username = ?');
                $del->bind_param('s', $username);
                $del->execute();
                $del->close();

                session_regenerate_id(true);
                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name']     = $admin['full_name'];
                $_SESSION['admin_role']     = $admin['role'] ?? 'super_admin';
                header('Location: index.php');
                exit;
            }
        } catch (mysqli_sql_exception $e) {}

        // Failed attempt - recorded server-side, keyed by username
        $stmt = $conn->prepare(
            'INSERT INTO login_attempts (username, attempts, locked_until) VALUES (?, 1, NULL)
             ON DUPLICATE KEY UPDATE
               attempts = attempts + 1,
               locked_until = IF(attempts + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NULL)'
        );
        $stmt->bind_param('sii', $username, $MAX_ATTEMPTS, $LOCKOUT_SECS);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('SELECT attempts, UNIX_TIMESTAMP(locked_until) AS locked_until FROM login_attempts WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $attempts = (int)($row['attempts'] ?? 1);
        if ($row && $row['locked_until'] && time() < $row['locked_until']) {
            $locked  = true;
            $lockout = (int)$row['locked_until'];
            $error   = "Too many failed attempts. Try again in 15 minutes.";
        } else {
            $remaining_attempts = max(0, $MAX_ATTEMPTS - $attempts);
            $error = "Invalid username or password. {$remaining_attempts} attempt(s) remaining.";
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Rotaract Kwanza</title>
  <link rel="icon" type="image/png" href="../assets/img/logo1.jpg">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div class="logo-badge">RK</div>
      <h1>Rotaract Kwanza</h1>
      <p>Admin Panel | Sign In</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required <?= $locked ? 'disabled' : 'autofocus' ?>
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               autocomplete="username">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required <?= $locked ? 'disabled' : '' ?>
               autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;"
              <?= $locked ? 'disabled' : '' ?>>
        Sign In
      </button>
    </form>

    <?php if ($locked): ?>
    <script>
      // Countdown on lockout
      (function() {
        var end = <?= $lockout ?> * 1000;
        function tick() {
          var left = Math.max(0, Math.ceil((end - Date.now()) / 1000));
          if (left <= 0) { location.reload(); return; }
          var m = Math.floor(left / 60), s = left % 60;
          document.querySelector('.alert-error').textContent =
            'Account locked. Try again in ' + m + ':' + (s < 10 ? '0' : '') + s + '.';
          setTimeout(tick, 1000);
        }
        tick();
      })();
    </script>
    <?php endif; ?>
  </div>
</body>
</html>
