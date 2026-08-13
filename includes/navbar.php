<?php
require_once dirname(__DIR__) . '/classes/SiteSettings.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
$_current = basename($_SERVER['PHP_SELF']);
// Standalone pages don't have every nav-target section on them, so the
// scroll-spy in scripts.js can't detect which nav item to highlight by
// scroll position alone. Pin the right nav item explicitly per page instead.
$_page_nav_map = [
  'about.php'              => 'about',
  'projects.php'            => 'projects',
  'project.php'             => 'projects',
  'project_signup.php'      => 'projects',
  'events.php'              => 'events',
  'event.php'               => 'events',
  'rsvp.php'                => 'events',
  'team.php'                => 'team',
  'leadership_history.php'  => 'team',
  'gallery.php'             => 'gallery',
  'news.php'                => 'news',
  'directory.php'           => 'directory',
  'join.php'                => 'join',
  'contact.php'             => 'contact',
];
$_pinned_nav = $_page_nav_map[$_current] ?? null;
$_is_home    = ($_current === 'index.php' || $_current === '');
$_site_name      = isset($conn) ? (new SiteSettings($conn))->get('site_name', 'Rotaract Club of Kwanza') : 'Rotaract Club of Kwanza';
$_motto_text     = isset($conn) ? (new SiteSettings($conn))->get('motto_text', 'Service Above Self') : 'Service Above Self';
$_brand_initials = isset($conn) ? (new SiteSettings($conn))->get('brand_initials', 'RK') : 'RK';
$_site_logo      = isset($conn) ? (new SiteSettings($conn))->get('site_logo', '') : '';
$_navbar_show_logo = $_site_logo && (isset($conn) ? (new SiteSettings($conn))->get('navbar_logo_display', 'logo') : 'logo') === 'logo';
?>
<div id="progress-bar"></div>

<nav id="navbar" <?= $_pinned_nav ? ' data-active-nav="' . htmlspecialchars($_pinned_nav, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
  <a href="index.php#home" class="nav-brand">
    <div class="nav-logo">
      <?php if ($_navbar_show_logo): ?>
        <img src="<?= htmlspecialchars(img_url($_site_logo), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($_site_name, ENT_QUOTES, 'UTF-8') ?> logo" class="nav-logo-img">
      <?php else: ?>
        <?= htmlspecialchars($_brand_initials, ENT_QUOTES, 'UTF-8') ?>
      <?php endif; ?>
    </div>
    <div class="nav-name"><?= htmlspecialchars($_site_name, ENT_QUOTES, 'UTF-8') ?><span><?= htmlspecialchars($_motto_text, ENT_QUOTES, 'UTF-8') ?></span></div>
  </a>
  <ul class="nav-links" id="nav-links">
    <li><a href="index.php#home" data-nav="home">Home</a></li>
    <li><a href="<?= $_is_home ? 'index.php#about' : 'about.php' ?>" data-nav="about">About</a></li>
    <li><a href="<?= $_is_home ? 'index.php#projects' : 'projects.php' ?>" data-nav="projects">Projects</a></li>
    <li><a href="<?= $_is_home ? 'index.php#events' : 'events.php' ?>" data-nav="events">Events</a></li>
    <li><a href="<?= $_is_home ? 'index.php#team' : 'team.php' ?>" data-nav="team">Team</a></li>
    <li><a href="<?= $_is_home ? 'index.php#gallery' : 'gallery.php' ?>" data-nav="gallery">Gallery</a></li>
    <li><a href="<?= $_is_home ? 'index.php#news' : 'news.php' ?>" data-nav="news">News</a></li>
    <li><a href="<?= $_is_home ? 'index.php#directory' : 'directory.php' ?>" data-nav="directory">Directory</a></li>
    <li><a href="<?= $_is_home ? 'index.php#join' : 'join.php' ?>" class="nav-cta" data-nav="join">Join Us</a></li>
    <li><a href="<?= $_is_home ? 'index.php#contact' : 'contact.php' ?>" data-nav="contact">Contact</a></li>
    <li>
      <a href="admin/login.php" class="nav-admin" aria-label="Admin Login" title="Admin Login">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="3" />
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
        </svg>
      </a>
    </li>
  </ul>
  <div class="hamburger" id="hamburger" onclick="toggleMenu()" aria-label="Menu" role="button">
    <span></span><span></span><span></span>
  </div>
</nav>