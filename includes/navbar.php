<?php
require_once dirname(__DIR__) . '/classes/SiteSettings.php';
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
$_site_name      = isset($conn) ? (new SiteSettings($conn))->get('site_name', 'Rotaract Club of Kwanza') : 'Rotaract Club of Kwanza';
$_motto_text     = isset($conn) ? (new SiteSettings($conn))->get('motto_text', 'Service Above Self') : 'Service Above Self';
$_brand_initials = isset($conn) ? (new SiteSettings($conn))->get('brand_initials', 'RK') : 'RK';
?>
<div id="progress-bar"></div>

<nav id="navbar"<?= $_pinned_nav ? ' data-active-nav="' . htmlspecialchars($_pinned_nav, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
  <a href="index.php#home" class="nav-brand">
    <div class="nav-logo"><?= htmlspecialchars($_brand_initials, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="nav-name"><?= htmlspecialchars($_site_name, ENT_QUOTES, 'UTF-8') ?><span><?= htmlspecialchars($_motto_text, ENT_QUOTES, 'UTF-8') ?></span></div>
  </a>
  <ul class="nav-links" id="nav-links">
    <li><a href="index.php#home">Home</a></li>
    <li><a href="index.php#about">About</a></li>
    <li><a href="index.php#projects">Projects</a></li>
    <li><a href="index.php#events">Events</a></li>
    <li><a href="index.php#team">Team</a></li>
    <li><a href="index.php#gallery">Gallery</a></li>
    <li><a href="index.php#news">News</a></li>
    <li><a href="index.php#directory">Directory</a></li>
    <li><a href="index.php#join" class="nav-cta">Join Us</a></li>
    <li><a href="index.php#contact">Contact</a></li>
  </ul>
  <div class="hamburger" id="hamburger" onclick="toggleMenu()" aria-label="Menu" role="button">
    <span></span><span></span><span></span>
  </div>
</nav>