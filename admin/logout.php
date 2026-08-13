<?php
require_once dirname(__DIR__) . '/includes/session_init.php';
if (isset($_SESSION['admin_id'])) {
    require_once dirname(__DIR__) . '/config/Database.php';
    require_once dirname(__DIR__) . '/classes/ActivityLog.php';
    try {
        $conn = (new Database())->connect();
        (new ActivityLog($conn))->log(
            (int)$_SESSION['admin_id'],
            $_SESSION['admin_username'] ?? 'unknown',
            'logout',
            'Logged out',
            substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)
        );
    } catch (Throwable $e) {
    }
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
