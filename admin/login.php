<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/classes/Admin.php';

$db   = new Database();
$conn = $db->connect();

if (isset($_SESSION['admin_id'])) {
    header('Location: /Rotaract_Kwanza/admin/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $admin = (new Admin($conn))->login($username, $password);
            if ($admin) {
                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name']     = $admin['full_name'];
                header('Location: /Rotaract_Kwanza/admin/');
                exit;
            }
        } catch (mysqli_sql_exception $e) {}
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — Rotaract Kwanza</title>
  <link rel="stylesheet" href="/Rotaract_Kwanza/admin/assets/admin.css">
</head>
<body class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div class="logo-badge">RK</div>
      <h1>Rotaract Kwanza</h1>
      <p>Admin Panel — Sign In</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">Sign In</button>
    </form>
  </div>
</body>
</html>
