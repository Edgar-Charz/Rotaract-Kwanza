<?php
// Served in place of a static robots.txt (see .htaccess rewrite) so the
// Sitemap directive can be an absolute URL, which is what most crawlers
// expect, without hardcoding a domain — same origin-detection trick used
// by sitemap.php and includes/helpers.php's site_origin().
header('Content-Type: text/plain; charset=utf-8');

$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/robots.php');
$base   = rtrim(dirname($script), '/');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base;
?>
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /assets/database/
Disallow: /config/
Disallow: /classes/
Disallow: /includes/

Sitemap: <?= $origin ?>/sitemap.php
