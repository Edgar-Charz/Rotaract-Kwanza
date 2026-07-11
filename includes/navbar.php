<?php
require_once dirname(__DIR__) . '/classes/SiteSettings.php';
$_current = basename($_SERVER['PHP_SELF']);
function nav_active(string $page): string
{
  global $_current;
  return $_current === $page ? ' class="active"' : '';
}
$_site_name  = isset($conn) ? (new SiteSettings($conn))->get('site_name', 'Rotaract Club of Kwanza') : 'Rotaract Club of Kwanza';
$_motto_text = isset($conn) ? (new SiteSettings($conn))->get('motto_text', 'Service Above Self') : 'Service Above Self';
?>
<div id="progress-bar"></div>

<nav id="navbar">
  <a href="index.php#home" class="nav-brand">
    <div class="nav-logo">RK</div>
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
    <li><a href="admin/login.php" class="nav-admin">&#9881; Admin</a></li>
  </ul>
  <div class="hamburger" id="hamburger" onclick="toggleMenu()" aria-label="Menu" role="button">
    <span></span><span></span><span></span>
  </div>
</nav>