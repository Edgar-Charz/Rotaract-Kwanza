<?php
require_once dirname(__DIR__, 2) . '/includes/session_init.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Pulled in early (every other admin page requires this again right after
// auth.php — require_once makes that a no-op) so $conn/get_setting() are
// available for the admin-configurable timeout thresholds below.
require_once __DIR__ . '/functions.php';

// Idle + absolute session timeout — an admin session left open on a shared
// machine (or simply forgotten) shouldn't stay valid indefinitely. Both
// thresholds are admin-configurable via Settings → Login & Session Security.
$idleTimeoutSecs     = max(60, (int) get_setting('session_idle_minutes', '30') * 60);
$absoluteTimeoutSecs = max(60, (int) get_setting('session_absolute_hours', '12') * 3600);

$now             = time();
$idleExpired     = isset($_SESSION['admin_last_activity']) && ($now - $_SESSION['admin_last_activity']) > $idleTimeoutSecs;
$absoluteExpired = isset($_SESSION['admin_login_time']) && ($now - $_SESSION['admin_login_time']) > $absoluteTimeoutSecs;

if ($idleExpired || $absoluteExpired) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}

$_SESSION['admin_last_activity'] = $now;

// A super_admin-initiated password reset flags the account so it can't reach
// anything else until a new password is set — prevents the temporary
// password (which someone other than the account owner may have seen) from
// remaining valid for normal use.
if (!empty($_SESSION['must_change_password']) && basename($_SERVER['SCRIPT_NAME']) !== 'force_password_change.php') {
    header('Location: ' . ADMIN_URL . '/force_password_change.php');
    exit;
}

/**
 * Single source of truth for the role hierarchy: super_admin > editor > viewer.
 * Both require_role() and has_role() resolve levels through this.
 */
function role_level(string $role): int
{
    static $hierarchy = ['viewer' => 1, 'editor' => 2, 'super_admin' => 3];
    return $hierarchy[$role] ?? 1;
}

/**
 * Gate a page to a minimum role level.
 * Call require_role('editor') to block viewers.
 */
function require_role(string $min_role): void
{
    $current  = $_SESSION['admin_role'] ?? 'viewer';
    $level    = role_level($current);
    $required = role_level($min_role);

    if ($level < $required) {
        http_response_code(403);
        // Include enough to render a proper error inside the admin shell
        $page_title = 'Access Denied';
        if (function_exists('h')) {
            include __DIR__ . '/header.php';
            echo '<div class="card access-denied">
                    <h2>Access Denied</h2>
                    <p class="text-muted">Your role (<strong>' . htmlspecialchars($current) . '</strong>) does not have permission for this action.</p>
                    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                  </div>';
            include __DIR__ . '/footer.php';
        } else {
            echo '<h2>Access Denied</h2><p>Insufficient permissions.</p>';
        }
        exit;
    }
}

/**
 * Return true if the current admin has at least the given role.
 */
function has_role(string $min_role): bool
{
    $current = $_SESSION['admin_role'] ?? 'viewer';
    return role_level($current) >= role_level($min_role);
}
