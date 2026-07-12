<?php
require_once dirname(__DIR__) . '/classes/SiteSettings.php';
$_current = basename($_SERVER['PHP_SELF']);
function nav_active(string $page): string
{
  global $_current;
  return $_current === $page ? ' class="active"' : '';
}
function nav_team_active(): string
{
  global $_current;
  return in_array($_current, ['team.php', 'leadership_history.php'], true) ? ' class="active"' : '';
}
$_team_nav_pinned = in_array($_current, ['team.php', 'leadership_history.php'], true);
$_site_name  = isset($conn) ? (new SiteSettings($conn))->get('site_name', 'Rotaract Club of Kwanza') : 'Rotaract Club of Kwanza';
$_motto_text = isset($conn) ? (new SiteSettings($conn))->get('motto_text', 'Service Above Self') : 'Service Above Self';
?>
<div id="progress-bar"></div>

<nav id="navbar"<?= $_team_nav_pinned ? ' data-active-nav="team"' : '' ?>>
  <a href="index.php#home" class="nav-brand">
    <div class="nav-logo">RK</div>
    <div class="nav-name"><?= htmlspecialchars($_site_name, ENT_QUOTES, 'UTF-8') ?><span><?= htmlspecialchars($_motto_text, ENT_QUOTES, 'UTF-8') ?></span></div>
  </a>
  <ul class="nav-links" id="nav-links">
    <li><a href="index.php#home">Home</a></li>
    <li><a href="index.php#about">About</a></li>
    <li><a href="index.php#projects">Projects</a></li>
    <li><a href="index.php#events">Events</a></li>
    <li><a href="team.php"<?= nav_team_active() ?>>Team</a></li>
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