<?php
require_once dirname(__DIR__, 2) . '/includes/session_init.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

/**
 * Gate a page to a minimum role level.
 * Hierarchy: super_admin > editor > viewer
 * Call require_role('editor') to block viewers.
 */
function require_role(string $min_role): void
{
    $hierarchy = ['viewer' => 1, 'editor' => 2, 'super_admin' => 3];
    $current   = $_SESSION['admin_role'] ?? 'viewer';
    $level     = $hierarchy[$current]  ?? 1;
    $required  = $hierarchy[$min_role] ?? 1;

    if ($level < $required) {
        http_response_code(403);
        // Include enough to render a proper error inside the admin shell
        $page_title = 'Access Denied';
        if (function_exists('h')) {
            include __DIR__ . '/header.php';
            echo '<div class="card" style="text-align:center;padding:40px">
                    <h2 style="color:var(--danger);margin-bottom:8px">Access Denied</h2>
                    <p class="text-muted">Your role (<strong>' . htmlspecialchars($current) . '</strong>) does not have permission for this action.</p>
                    <a href="index.php" class="btn btn-secondary" style="margin-top:16px">Back to Dashboard</a>
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
    $hierarchy = ['viewer' => 1, 'editor' => 2, 'super_admin' => 3];
    $current   = $_SESSION['admin_role'] ?? 'viewer';
    return ($hierarchy[$current] ?? 1) >= ($hierarchy[$min_role] ?? 1);
}
