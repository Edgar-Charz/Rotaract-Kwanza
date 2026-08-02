<?php
require_once dirname(__DIR__, 2) . '/config/Database.php';
require_once dirname(__DIR__, 2) . '/classes/ActivityLog.php';
require_once dirname(__DIR__, 2) . '/classes/SiteSettings.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/upload.php';

// ── Constants ─────────────────────────────────────────────────────────────────

// Compute site root URL from the current request — works on any server, no hardcoding
$_s   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
$_dir = dirname($_s);
if (basename($_dir) === 'includes') $_dir = dirname($_dir); // admin/includes → admin
if (basename($_dir) === 'admin')    $_dir = dirname($_dir); // admin → project root
$_base = rtrim($_dir, '/') === '/' ? '' : rtrim($_dir, '/');
define('ADMIN_URL',  $_base . '/admin');
define('UPLOAD_URL', $_base . '/admin/uploads/');
define('UPLOAD_DIR', dirname(__DIR__, 2) . '/admin/uploads/');
define('SITE_ROOT',  dirname(__DIR__, 2));
unset($_s, $_dir, $_base);

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

// Rejects any URL that isn't a well-formed http(s) link, so a stored value can
// never carry a javascript:/data:/vbscript: scheme through to a rendered <a href>.
function clean_url(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme === null || $scheme === false) return '';
    if (!in_array(strtolower($scheme), ['http', 'https'], true)) return '';
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

// ── Money input parsing ─────────────────────────────────────────────────────────
// Strips thousands separators from the money-input formatter's display value
// (assets/js/scripts.js / admin/assets/admin.js) — a backstop that holds even
// with JS disabled or a malformed paste.

/** For optional "goal"-style fields where 0 means the same thing as blank (not set). */
function money_or_null(?string $raw): ?float {
    $clean = str_replace(',', '', trim($raw ?? ''));
    if ($clean === '' || !is_numeric($clean)) return null;
    $val = (float) $clean;
    return $val > 0 ? $val : null;
}

/** For fields where 0 is a legitimate value in its own right (e.g. dues owed/paid). */
function money_or_zero(?string $raw): float {
    $clean = str_replace(',', '', trim($raw ?? ''));
    return is_numeric($clean) ? max(0.0, (float) $clean) : 0.0;
}

// ── File upload ───────────────────────────────────────────────────────────────

function upload_image(string $input_name, string $subdir): string|false {
    return save_uploaded_image_from_input($input_name, $subdir);
}

function upload_multi_images(string $input_name, string $subdir): array {
    if (empty($_FILES[$input_name]['name'][0])) return [];

    $files = $_FILES[$input_name];
    $saved = [];

    foreach ($files['name'] as $i => $name) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $file = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        $url = save_uploaded_image($file, $subdir);
        if ($url) $saved[$i] = $url;
    }
    return $saved;
}

/**
 * Duplicate an already-uploaded image into another uploads subdir (e.g. when
 * archiving a team member's photo into leadership history) so the two records
 * own independent files and deleting one never breaks the other.
 */
function copy_uploaded_image(string $sourcePath, string $subdir): string|false {
    if (!$sourcePath) return false;
    $urlPath = parse_url($sourcePath, PHP_URL_PATH) ?: '';
    $pos = strpos($urlPath, '/admin/uploads/');
    if ($pos === false) return false;
    $srcFile = SITE_ROOT . substr($urlPath, $pos);
    if (!is_file($srcFile)) return false;

    $ext     = strtolower(pathinfo($srcFile, PATHINFO_EXTENSION)) ?: 'jpg';
    $destDir = SITE_ROOT . '/admin/uploads/' . trim($subdir, '/') . '/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $name = uniqid('img_', true) . '.' . $ext;
    if (!copy($srcFile, $destDir . $name)) return false;

    // Reuse the same base URL prefix already on the source path (everything
    // before "/admin/uploads/") so the copy matches this install's convention
    // without depending on which page/script called us.
    $base = substr($urlPath, 0, $pos);
    return $base . '/admin/uploads/' . trim($subdir, '/') . '/' . $name;
}

function delete_image(string $path): void {
    if (!$path) return;
    $urlPath = parse_url($path, PHP_URL_PATH) ?: '';
    // Stored paths are prefixed with whatever base URL the app was installed under
    // (e.g. /Rotaract_Kwanza) at upload time — anchor on /admin/uploads/ instead of
    // assuming SITE_ROOT + the raw path line up, since that prefix isn't part of
    // the filesystem layout.
    $pos = strpos($urlPath, '/admin/uploads/');
    if ($pos === false) return;
    $file = SITE_ROOT . substr($urlPath, $pos);
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

// ── Rich text sanitization ───────────────────────────────────────────────────

/**
 * Sanitize a Quill-authored HTML fragment before storage: strips any tag not
 * in $allowedTags (unwrapping its contents rather than dropping them) and
 * strips every attribute except a safe `href` on `<a>` (http/https/mailto/
 * relative only — blocks `javascript:` etc). The client-side Quill toolbar
 * already limits what an editor can produce through the UI, but the POST
 * body itself is not a trusted boundary — this is what actually stops a
 * crafted request from storing a stored-XSS payload that gets rendered
 * unescaped to public visitors on pages like news.php.
 */
function sanitize_html_fragment(string $html, array $allowedTags): string
{
    if (trim($html) === '') return '';

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="utf-8" ?><div>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root) return '';

    sanitize_html_node($root, $allowedTags);

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

function sanitize_html_node(DOMNode $node, array $allowedTags): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child->nodeType === XML_COMMENT_NODE) {
            $node->removeChild($child);
            continue;
        }
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $tag = strtolower($child->nodeName);
        if (!in_array($tag, $allowedTags, true)) {
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }

        foreach (iterator_to_array($child->attributes) as $attr) {
            if ($tag === 'a' && strtolower($attr->nodeName) === 'href') {
                if (preg_match('~^(https?:|mailto:|/|#)~i', trim($attr->nodeValue))) {
                    continue;
                }
            }
            $child->removeAttribute($attr->nodeName);
        }
        if ($tag === 'a') {
            $child->setAttribute('rel', 'noopener noreferrer');
            $child->setAttribute('target', '_blank');
        }

        sanitize_html_node($child, $allowedTags);
    }
}

// ── Reporting ─────────────────────────────────────────────────────────────────

/**
 * Monthly counts for the trailing $months months, one grouped query instead of
 * one query per month. $table/$date_col are always literals passed by callers,
 * never user input.
 */
function monthly_counts(mysqli $db, string $table, int $months, string $date_col = 'created_at'): array {
    $rows = db_rows(
        $db,
        "SELECT DATE_FORMAT($date_col, '%Y-%m') AS ym, COUNT(*) AS n
         FROM $table
         WHERE $date_col >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL ? MONTH)
         GROUP BY ym",
        [$months - 1]
    );
    $counts = array_column($rows, 'n', 'ym');

    $series = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-$i months"));
        $series[] = ['label' => date('M Y', strtotime("-$i months")), 'value' => (int) ($counts[$ym] ?? 0)];
    }
    return $series;
}

/**
 * Monthly series (row count, or SUM of $sum_col when given) for every calendar
 * month between $from and $to inclusive (both 'Y-m-d'). $table/$date_col/$sum_col
 * are always literals passed by callers, never user input.
 */
function monthly_series_range(mysqli $db, string $table, string $from, string $to, string $date_col = 'created_at', ?string $sum_col = null): array {
    $agg = $sum_col ? "COALESCE(SUM($sum_col),0)" : "COUNT(*)";
    $rows = db_rows(
        $db,
        "SELECT DATE_FORMAT($date_col, '%Y-%m') AS ym, $agg AS n
         FROM $table
         WHERE $date_col >= ? AND $date_col <= ?
         GROUP BY ym",
        [$from, $to]
    );
    $counts = array_column($rows, 'n', 'ym');

    $series  = [];
    $cursor  = new DateTime(date('Y-m-01', strtotime($from)));
    $end     = new DateTime(date('Y-m-01', strtotime($to)));
    while ($cursor <= $end) {
        $ym = $cursor->format('Y-m');
        $series[] = ['label' => $cursor->format('M Y'), 'value' => (float) ($counts[$ym] ?? 0)];
        $cursor->modify('+1 month');
    }
    return $series;
}

// ── Misc ──────────────────────────────────────────────────────────────────────

function active_nav(string $page): string {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}

// Cached per request: without this, a page like settings.php (~50 keys) or
// auth.php's timeout checks would issue one query per key on every load.
function get_setting(string $key, string $default = ''): string {
    global $conn;
    static $cache = null;
    if ($cache === null) {
        $cache = (new SiteSettings($conn))->getAll();
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
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

/**
 * Emails every newsletter subscriber that a post just went live. Call this
 * only on the draft→published transition (not on every edit of an already-
 * published post, and not on unpublish) — callers are responsible for that
 * check, since only they know the before/after state.
 */
function notify_newsletter_subscribers_of_post(mysqli $conn, array $post): void {
    require_once dirname(__DIR__, 2) . '/classes/NewsletterSubscriber.php';
    require_once dirname(__DIR__, 2) . '/classes/Mailer.php';
    require_once dirname(__DIR__, 2) . '/includes/helpers.php'; // site_origin()

    $subscribers = (new NewsletterSubscriber($conn))->getAll();
    if (!$subscribers) return;

    $club   = get_setting('site_name', 'Rotaract Kwanza');
    $mailer = Mailer::fromSettings($conn);
    $url    = site_origin() . '/news.php?slug=' . rawurlencode($post['slug']);
    $sent   = 0;

    foreach ($subscribers as $sub) {
        $unsubscribe = site_origin() . '/unsubscribe.php?token=' . urlencode($sub['unsubscribe_token']);
        try {
            $ok = $mailer->announcementNotification(
                $sub['email'],
                ['title' => $post['title'], 'content' => $post['content'], 'url' => $url],
                $unsubscribe,
                $club
            );
            if ($ok) $sent++;
        } catch (Throwable $e) {
        }
    }

    log_activity('newsletter_notify', "Notified $sent subscriber(s) of new post: {$post['title']}");
}

// ── CSV export ────────────────────────────────────────────────────────────────

/**
 * Prefix values that a spreadsheet app would interpret as a formula
 * (=, +, -, @) with a single quote so exported CSVs can't be used for
 * formula-injection against whoever opens them in Excel/Sheets.
 */
function csv_safe($value): string {
    $value = (string) $value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }
    return $value;
}

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-') ?: 'post-' . time();
}
