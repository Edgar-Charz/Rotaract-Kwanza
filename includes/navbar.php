<?php
$_current = basename($_SERVER['PHP_SELF']);
function nav_active(string $page): string
{
  global $_current;
  return $_current === $page ? ' class="active"' : '';
}
?>
<div id="progress-bar"></div>

<nav id="navbar">
  <a href="/Rotaract_Kwanza/index.php#home" class="nav-brand">
    <div class="nav-logo">RK</div>
    <div class="nav-name">Rotaract Club of Kwanza<span>Service Above Self</span></div>
  </a>
  <ul class="nav-links" id="nav-links">
    <li><a href="/Rotaract_Kwanza/index.php#home">Home</a></li>
    <li><a href="/Rotaract_Kwanza/about.php#about" <?= nav_active('about.php') ?>>About</a></li>
    <li><a href="/Rotaract_Kwanza/events.php#events" <?= nav_active('events.php') ?>>Events</a></li>
    <li><a href="/Rotaract_Kwanza/projects.php#projects" <?= nav_active('projects.php') ?>>Projects</a></li>
    <li><a href="/Rotaract_Kwanza/team.php#team" <?= nav_active('team.php') ?>>Team</a></li>
    <li><a href="/Rotaract_Kwanza/gallery.php#gallery" <?= nav_active('gallery.php') ?>>Gallery</a></li>
    <li><a href="/Rotaract_Kwanza/news.php#news" <?= nav_active('news.php') ?>>News</a></li>
    <li><a href="/Rotaract_Kwanza/join.php#join" class="nav-cta<?= $_current === 'join.php' ? ' active' : '' ?>">Join
        Us</a></li>
    <li><a href="/Rotaract_Kwanza/contact.php#contact" <?= nav_active('contact.php') ?>>Contact</a></li>
    <li>
      <a href="/Rotaract_Kwanza/admin/login.php" class="nav-admin">⚙ Admin</a>
    </li>
  </ul>
  <div class="hamburger" id="hamburger" onclick="toggleMenu()" aria-label="Menu" role="button">
    <span></span><span></span><span></span>
  </div>
</nav>