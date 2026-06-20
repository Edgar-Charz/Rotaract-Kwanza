<?php
require_once dirname(__DIR__, 2) . '/config/Database.php';
require_once dirname(__DIR__, 2) . '/classes/ActivityLog.php';
require_once dirname(__DIR__, 2) . '/classes/SiteSettings.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

// ── Constants ─────────────────────────────────────────────────────────────────

define('ADMIN_URL',   '/Rotaract_Kwanza/admin');
define('UPLOAD_DIR',  dirname(__DIR__, 2) . '/admin/uploads/');
define('UPLOAD_URL',  '/Rotaract_Kwanza/admin/uploads/');
define('SITE_ROOT',   dirname(__DIR__, 2));

// ── Database connection ───────────────────────────────────────────────────────

$_db_inst = new Database();
$conn = $_db_inst->connect();

// ── MySQLi helpers ────────────────────────────────────────────────────────────

function db_query(mysqli $db, string $sql, array $params = []): mysqli_stmt {
    $stmt = $db->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p))       $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else                  $types .= 's';
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function db_rows(mysqli $db, string $sql, array $params = []): array {
    $stmt = db_query($db, $sql, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function db_row(mysqli $db, string $sql, array $params = []): ?array {
    $stmt = db_query($db, $sql, $params);
    $row  = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function db_val(mysqli $db, string $sql, array $params = []): mixed {
    $stmt = db_query($db, $sql, $params);
    $row  = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $row ? $row[0] : null;
}

function db_exec(mysqli $db, string $sql, array $params = []): void {
    $stmt = db_query($db, $sql, $params);
    $stmt->close();
}

// ── Flash messages ────────────────────────────────────────────────────────────

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ── Output helpers ────────────────────────────────────────────────────────────

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ── File upload ───────────────────────────────────────────────────────────────

function upload_image(string $input_name, string $subdir): string|false {
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return false;
    }
    $file = $_FILES[$input_name];
    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, $allowed)) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false;

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = uniqid('img_', true) . '.' . $ext;
    $dir  = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $dest = $dir . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return UPLOAD_URL . $subdir . '/' . $name;
}

function delete_image(string $path): void {
    if (!$path) return;
    $file = SITE_ROOT . parse_url($path, PHP_URL_PATH);
    if (file_exists($file)) unlink($file);
}

// ── Pagination ────────────────────────────────────────────────────────────────

function paginate(int $total, int $per_page, int $current): array {
    $pages = (int) ceil($total / $per_page);
    return [
        'total'    => $total,
        'pages'    => $pages,
        'current'  => $current,
        'per_page' => $per_page,
        'offset'   => ($current - 1) * $per_page,
    ];
}

// ── Misc ──────────────────────────────────────────────────────────────────────

function active_nav(string $page): string {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}

function get_setting(string $key, string $default = ''): string {
    global $conn;
    return (new SiteSettings($conn))->get($key, $default);
}

function log_activity(string $action, string $description): void {
    global $conn;
    try {
        (new ActivityLog($conn))->log(
            (int)($_SESSION['admin_id'] ?? 0),
            $_SESSION['admin_username'] ?? 'unknown',
            $action,
            $description,
            substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)
        );
    } catch (Throwable $e) {}
}

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-') ?: 'post-' . time();
}
