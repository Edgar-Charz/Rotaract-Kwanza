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
function img_url(string $path): string
{
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
    // 3 MB cap matches the limit shown to applicants on join.php.
    $saved = save_uploaded_image_from_input($input_name, 'members', 'mp_', 3 * 1024 * 1024);
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

// Maps an event's free-text category to one of the curated icon_svg() keys,
// so event cards show a relevant glyph instead of one fixed icon for every card.
function event_category_icon(?string $category): string
{
    $category = strtolower(trim($category ?? ''));
    $map = [
        'community service' => 'heart',
        'service'            => 'heart',
        'environment'        => 'heart',
        'professional dev'   => 'book',
        'professional dev.'  => 'book',
        'professional development' => 'book',
        'workshop'           => 'book',
        'training'           => 'book',
        'fellowship'         => 'people',
        'social'             => 'people',
        'networking'         => 'people',
        'meeting'            => 'calendar',
        'installation'       => 'award',
        'fundraiser'         => 'chart',
        'fundraising'        => 'chart',
    ];
    foreach ($map as $needle => $icon) {
        if ($category !== '' && str_contains($category, $needle)) return $icon;
    }
    return 'calendar';
}

// Renders a self-contained group-photo slider (prev/next + dots) from a flat
// list of image paths. Falls back to a single plain image for one photo, and
// renders nothing for an empty list. Shared by team.php and leadership_history.php,
// each of which may place more than one slider on the page (JS auto-inits all of them).
function render_photo_slider(array $image_paths, string $alt): string
{
    $image_paths = array_values(array_filter($image_paths));
    if (!$image_paths) return '';

    if (count($image_paths) === 1) {
        return '<div class="photo-slider"><img src="' . e(img_url($image_paths[0])) . '" alt="' . e($alt) . '"></div>';
    }

    $slides = '';
    $dots   = '';
    foreach ($image_paths as $i => $path) {
        $slides .= '<div class="photo-slider-slide"><img src="' . e(img_url($path)) . '" alt="' . e($alt) . '"></div>';
        $dots   .= '<button type="button" class="photo-slider-dot' . ($i === 0 ? ' active' : '') . '" aria-label="Go to photo ' . ($i + 1) . '"></button>';
    }

    return '<div class="photo-slider">'
        . '<div class="photo-slider-track">' . $slides . '</div>'
        . '<button type="button" class="photo-slider-btn prev" aria-label="Previous photo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>'
        . '<button type="button" class="photo-slider-btn next" aria-label="Next photo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></button>'
        . '<div class="photo-slider-dots">' . $dots . '</div>'
        . '</div>';
}

// Renders admin-authored rich text (announcements) safely: keeps a small
// allowlist of formatting tags, but — unlike a bare strip_tags() call —
// also strips every attribute except a validated <a href>, so a stored
// onclick=/onmouseover=/javascript: URL can't ride along on an otherwise
// "safe" allowed tag.
function sanitize_rich_text(string $html): string
{
    $allowed = '<p><br><b><i><u><s><strong><em><ul><ol><li><h2><h3><a><blockquote>';
    $html = strip_tags($html, $allowed);
    if ($html === '') return '';

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $body = $doc->getElementsByTagName('div')->item(0);
    if (!$body) return e(strip_tags($html));

    $walk = function (DOMNode $node) use (&$walk) {
        if ($node->hasChildNodes()) {
            foreach (iterator_to_array($node->childNodes) as $child) {
                $walk($child);
            }
        }
        if (!($node instanceof DOMElement)) return;

        // Capture href before stripping attributes below wipes it out too.
        $isLink = strtolower($node->tagName) === 'a';
        $href   = $isLink ? $node->getAttribute('href') : null;

        foreach (iterator_to_array($node->attributes ?? []) as $attr) {
            $node->removeAttribute($attr->name);
        }

        if ($isLink) {
            $safe = preg_match('~^(https?://|mailto:|/|#)~i', $href ?? '') === 1;
            $node->setAttribute('href', $safe ? $href : '#');
            $node->setAttribute('rel', 'noopener noreferrer');
            $node->setAttribute('target', '_blank');
        }
    };
    foreach (iterator_to_array($body->childNodes) as $child) {
        $walk($child);
    }

    $out = '';
    foreach (iterator_to_array($body->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

/**
 * Absolute origin + project-root path for the current request, e.g.
 * "https://host/Rotaract_Kwanza" — for building absolute links (emails,
 * RSS, .ics files) where a page-relative href like "rsvp.php" won't
 * resolve outside a browser tab actually on this site. Same
 * detect-the-root-from-the-request trick as img_url() and sitemap.php.
 * Also callable from admin/ scripts (e.g. to link back to a public page
 * from a notification email) — steps up out of admin/ the same way
 * img_url() does, so the /admin segment never leaks into the result.
 */
function site_origin(): string
{
    static $origin = null;
    if ($origin === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir    = dirname($script);
        if (basename($dir) === 'admin')    $dir = dirname($dir);
        if (basename($dir) === 'includes') $dir = dirname($dir);
        $base   = rtrim($dir, '/');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base;
    }
    return $origin;
}

/** Builds a downloadable data: URI for a single-event .ics calendar file (1-hour default duration, floating local time — no stored timezone to convert from). */
function build_ics_data_uri(string $title, string $description, string $location, string $event_date, string $event_time = ''): string
{
    $startTs = strtotime($event_date . ' ' . ($event_time ?: '09:00')) ?: strtotime($event_date) ?: time();
    $endTs   = $startTs + 3600;

    $esc = fn(string $s): string => addcslashes(str_replace(["\r\n", "\n"], '\\n', $s), ",;");

    $ics = "BEGIN:VCALENDAR\r\n"
        . "VERSION:2.0\r\n"
        . "PRODID:-//Rotaract Kwanza//Events//EN\r\n"
        . "BEGIN:VEVENT\r\n"
        . "UID:" . md5($title . $event_date . $location) . "@rotaractkwanza\r\n"
        . "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n"
        . "DTSTART:" . date('Ymd\THis', $startTs) . "\r\n"
        . "DTEND:" . date('Ymd\THis', $endTs) . "\r\n"
        . "SUMMARY:" . $esc($title) . "\r\n"
        . ($description !== '' ? "DESCRIPTION:" . $esc($description) . "\r\n" : '')
        . ($location !== '' ? "LOCATION:" . $esc($location) . "\r\n" : '')
        . "END:VEVENT\r\n"
        . "END:VCALENDAR\r\n";

    return 'data:text/calendar;charset=utf-8;base64,' . base64_encode($ics);
}

/** Absolute URL of the current request, reconstructed from the request itself (no hardcoded host). */
function current_page_url(): string
{
    return site_origin() . '/' . basename($_SERVER['SCRIPT_NAME'] ?? '')
        . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
}

/**
 * Renders a Facebook/X/WhatsApp/Instagram/TikTok/copy-link share row for a
 * news post or event page.
 *
 * Instagram and TikTok have no web "share this URL" intent the way
 * Facebook/X/WhatsApp do — neither platform accepts an arbitrary link to
 * pre-fill a post/story with on the web. The standard workaround (what
 * their own in-app share sheets effectively do) is: copy the link, then
 * open the platform so the visitor can paste it into a Story/bio/DM
 * themselves. See the .share-copy-open-btn handler in scripts.js.
 */
function render_share_buttons(string $url, string $title): string
{
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    return '<div class="share-row">'
        . '<span class="share-label">Share</span>'
        . '<a href="https://www.facebook.com/sharer/sharer.php?u=' . $u . '" target="_blank" rel="noopener" class="share-btn">Facebook</a>'
        . '<a href="https://twitter.com/intent/tweet?url=' . $u . '&text=' . $t . '" target="_blank" rel="noopener" class="share-btn">X</a>'
        . '<a href="https://wa.me/?text=' . $t . '%20' . $u . '" target="_blank" rel="noopener" class="share-btn">WhatsApp</a>'
        . '<button type="button" class="share-btn share-copy-open-btn" data-share-url="' . e($url) . '" data-open-url="https://www.instagram.com/">Instagram</button>'
        . '<button type="button" class="share-btn share-copy-open-btn" data-share-url="' . e($url) . '" data-open-url="https://www.tiktok.com/">TikTok</button>'
        . '<button type="button" class="share-btn share-copy-btn" data-share-url="' . e($url) . '">Copy Link</button>'
        . '</div>';
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
