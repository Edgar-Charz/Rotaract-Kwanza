<?php
/**
 * Shared public <head> assets. Set $page_title before including.
 * Optional: $extra_css = ['assets/css/pages/news.css']
 * Optional: $page_description (meta description + OG/Twitter description)
 * Optional: $page_image (absolute or root-relative path to an OG/Twitter image)
 */
$__page_title = $page_title ?? (isset($conn) ? site_title($conn) : 'Rotaract Club of Kwanza');
$__extra_css  = $extra_css ?? [];
$__description = $page_description ?? (isset($conn) ? site_setting($conn, 'footer_description', 'A vibrant community of young leaders united by the spirit of service, fellowship, and positive change in Kwanza and beyond.') : '');

// Absolute self URL + default share image, computed the same way img_url()
// derives the project root, so this works whether the site sits at the
// domain root or in a subdirectory.
$__script  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$__base    = rtrim(dirname($__script), '/');
$__scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$__host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$__origin  = $__scheme . '://' . $__host . $__base;
$__self_url = $__scheme . '://' . $__host . $__script . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
$__image_path = $page_image ?? 'assets/img/logo1.jpg';
// img_url() sometimes returns a domain-root-absolute path (leading "/") and
// sometimes a path relative to the current page's directory. Only the
// relative case needs $__origin's base directory prepended — doing it for
// both would duplicate the subdirectory when the site isn't at the domain root.
$__image_url = match (true) {
    str_starts_with($__image_path, 'http') => $__image_path,
    str_starts_with($__image_path, '/')    => $__scheme . '://' . $__host . $__image_path,
    default                                 => $__origin . '/' . $__image_path,
};
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($__page_title) ?></title>
<?php if ($__description !== ''): ?>
<meta name="description" content="<?= e($__description) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= e($__self_url) ?>">
<link rel="alternate" type="application/rss+xml" title="<?= e($__page_title) ?> — News" href="<?= e($__origin . '/rss.php') ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($__page_title) ?>">
<?php if ($__description !== ''): ?>
<meta property="og:description" content="<?= e($__description) ?>">
<?php endif; ?>
<meta property="og:url" content="<?= e($__self_url) ?>">
<meta property="og:image" content="<?= e($__image_url) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($__page_title) ?>">
<?php if ($__description !== ''): ?>
<meta name="twitter:description" content="<?= e($__description) ?>">
<?php endif; ?>
<meta name="twitter:image" content="<?= e($__image_url) ?>">
<link rel="icon" type="image/png" href="assets/img/logo1.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/kwanza.css">
<?php foreach ($__extra_css as $__css): ?>
<link rel="stylesheet" href="<?= e($__css) ?>">
<?php endforeach; ?>
