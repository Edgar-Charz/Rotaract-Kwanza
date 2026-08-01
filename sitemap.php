<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Project.php';
require_once __DIR__ . '/classes/Event.php';
require_once __DIR__ . '/classes/Announcement.php';

$db = new Database();
$conn = $db->connect();

// Same "detect project root from the request" trick used by img_url() in
// includes/helpers.php, so this works whether the site sits at the domain
// root or in a subdirectory, without hardcoding either.
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/sitemap.php');
$base = rtrim(dirname($script), '/');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base;

$urls = [];
$add = function (string $path, string $changefreq, string $priority, ?string $lastmod = null) use (&$urls, $origin) {
    $urls[] = [
        'loc' => $origin . '/' . ltrim($path, '/'),
        'changefreq' => $changefreq,
        'priority' => $priority,
        'lastmod' => $lastmod,
    ];
};

$add('index.php', 'weekly', '1.0');
$add('about.php', 'monthly', '0.7');
$add('projects.php', 'weekly', '0.8');
$add('events.php', 'weekly', '0.9');
$add('team.php', 'monthly', '0.6');
$add('leadership_history.php', 'monthly', '0.4');
$add('gallery.php', 'weekly', '0.6');
$add('news.php', 'daily', '0.7');
$add('directory.php', 'weekly', '0.5');
$add('join.php', 'monthly', '0.8');
$add('contact.php', 'monthly', '0.6');

foreach ((new Project($conn))->getAll() as $p) {
    $add('project.php?id=' . $p['id'], 'monthly', '0.6', $p['updated_at'] ?? $p['created_at'] ?? null);
}
foreach ((new Event($conn))->getAll() as $ev) {
    $add('event.php?id=' . $ev['id'], 'weekly', $ev['status'] === 'upcoming' ? '0.7' : '0.4', $ev['updated_at'] ?? $ev['created_at'] ?? null);
}
foreach ((new Announcement($conn))->getPublished() as $post) {
    $add('news.php?slug=' . rawurlencode($post['slug']), 'monthly', '0.5', $post['updated_at'] ?? $post['created_at'] ?? null);
}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php foreach ($urls as $u): ?>
        <url>
            <loc><?= htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></loc>
            <?php if ($u['lastmod']): ?>
                <lastmod><?= date('Y-m-d', strtotime($u['lastmod'])) ?></lastmod>
            <?php endif; ?>
            <changefreq><?= $u['changefreq'] ?></changefreq>
            <priority><?= $u['priority'] ?></priority>
        </url>
    <?php endforeach; ?>
</urlset>