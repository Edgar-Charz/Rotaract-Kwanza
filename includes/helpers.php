<?php
require_once __DIR__ . '/upload.php';

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Retrieve a public site setting while keeping a single settings instance per request. */
function site_setting(mysqli $conn, string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        require_once dirname(__DIR__) . '/classes/SiteSettings.php';
        $settings = new SiteSettings($conn);
    }
    return $settings->get($key, $default);
}

/** Build a consistent, database-backed browser title for public pages. */
function site_title(mysqli $conn, string $page = ''): string
{
    $siteName = site_setting($conn, 'site_name', 'Rotaract Club of Kwanza');
    return $page === '' ? $siteName : $page . ' — ' . $siteName;
}

// Returns the URL to an uploaded image, regardless of which server the site runs on.
// Detects the project root from the current request so no path is ever hardcoded.
function img_url(string $path): string {
    static $root = null;
    if ($root === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir    = dirname($script);
        // Step up from admin/ or admin/includes/ to the project root
        if (basename($dir) === 'admin')    $dir = dirname($dir);
        if (basename($dir) === 'includes') $dir = dirname($dir);
        $root = rtrim($dir, '/') === '' ? '' : rtrim($dir, '/');
    }
    // Extract the relative portion after /admin/uploads/ and rebuild with the correct root
    $pos = strpos($path, '/admin/uploads/');
    if ($pos !== false) {
        return $root . '/admin/uploads/' . substr($path, $pos + 15);
    }
    return $path;
}

function upload_member_photo(string $input_name): string
{
    $saved = save_uploaded_image_from_input($input_name, 'members', 'mp_');
    if (!$saved) return '';
    $pos = strpos($saved, '/admin/uploads/');
    return $pos !== false ? 'admin/uploads/' . substr($saved, $pos + 15) : ltrim($saved, '/');
}

function delete_member_photo(string $path): void
{
    if (!$path) return;
    $abs = dirname(__DIR__) . '/' . ltrim($path, '/');
    if (is_file($abs)) unlink($abs);
}

// Curated, XSS-safe icon library for admin-selectable icons (club values, membership perks).
// Only these fixed keys are ever rendered — never render arbitrary/user-supplied SVG markup.
function icon_options(): array
{
    return [
        'heart'    => 'Heart',
        'star'     => 'Star',
        'clock'    => 'Clock',
        'people'   => 'People / Network',
        'book'     => 'Book / Growth',
        'shield'   => 'Shield / Impact',
        'calendar' => 'Calendar',
        'award'    => 'Award / Ribbon',
        'chart'    => 'Chart / Growth',
    ];
}

function icon_svg(string $key, string $stroke = 'currentColor'): string
{
    $paths = [
        'heart'    => '<path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/>',
        'star'     => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/>',
        'people'   => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
        'book'     => '<path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>',
        'shield'   => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'award'    => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>',
        'chart'    => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
    ];
    $inner = $paths[$key] ?? $paths['heart'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="' . e($stroke) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
}

function avatar_palette(int $i): array
{
    $p = [
        ['bg' => 'linear-gradient(135deg,var(--pink-100),var(--pink-200))', 'circle' => 'linear-gradient(135deg,var(--pink-600),var(--pink-800))'],
        ['bg' => 'linear-gradient(135deg,#FFF0E6,#FFD4AA)', 'circle' => 'linear-gradient(135deg,var(--gold),#C26B0A)'],
        ['bg' => 'linear-gradient(135deg,#EDF7F0,#C8EDD5)', 'circle' => 'linear-gradient(135deg,#27AE60,#1A6B3B)'],
        ['bg' => 'linear-gradient(135deg,#EEF4FF,#CCDAFF)', 'circle' => 'linear-gradient(135deg,#4A7FE8,#2252B8)'],
        ['bg' => 'linear-gradient(135deg,#FFF5E6,#FFDDAA)', 'circle' => 'linear-gradient(135deg,#E07B20,#A45310)'],
        ['bg' => 'linear-gradient(135deg,#F0EDFF,#D5CCFF)', 'circle' => 'linear-gradient(135deg,#7B5EA7,#4A2E88)'],
        ['bg' => 'linear-gradient(135deg,var(--pink-50),var(--pink-100))', 'circle' => 'linear-gradient(135deg,var(--pink-500),var(--pink-700))'],
    ];
    return $p[$i % count($p)];
}
