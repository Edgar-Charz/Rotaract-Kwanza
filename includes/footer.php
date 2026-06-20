<?php
require_once dirname(__DIR__) . '/classes/SiteSettings.php';
$_fb   = isset($conn) ? (new SiteSettings($conn))->get('facebook_url',  '#') : '#';
$_ig   = isset($conn) ? (new SiteSettings($conn))->get('instagram_url', '#') : '#';
$_tw   = isset($conn) ? (new SiteSettings($conn))->get('twitter_url',   '#') : '#';
?>
<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <a href="/Rotaract_Kwanza/index.php#home" class="logo">
        <div class="logo-circle">RK</div>
        <div class="logo-text">Rotaract Club of Kwanza<span>Service Above Self</span></div>
      </a>
      <p>A vibrant community of young leaders united by the spirit of service, fellowship, and positive change in Kwanza and beyond.</p>
      <div class="socials" style="margin-top:20px">
        <a href="<?= htmlspecialchars($_fb, ENT_QUOTES, 'UTF-8') ?>" class="social-btn" style="background:rgba(255,255,255,0.08)" aria-label="Facebook">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2" stroke-linecap="round"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
        </a>
        <a href="<?= htmlspecialchars($_ig, ENT_QUOTES, 'UTF-8') ?>" class="social-btn" style="background:rgba(255,255,255,0.08)" aria-label="Instagram">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2" stroke-linecap="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
        </a>
        <a href="<?= htmlspecialchars($_tw, ENT_QUOTES, 'UTF-8') ?>" class="social-btn" style="background:rgba(255,255,255,0.08)" aria-label="Twitter">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2" stroke-linecap="round"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>
        </a>
      </div>
    </div>
    <div class="footer-col">
      <h5>Quick Links</h5>
      <ul>
        <li><a href="/Rotaract_Kwanza/index.php#home">Home</a></li>
        <li><a href="/Rotaract_Kwanza/about.php">About Us</a></li>
        <li><a href="/Rotaract_Kwanza/events.php">Events</a></li>
        <li><a href="/Rotaract_Kwanza/projects.php">Projects</a></li>
        <li><a href="/Rotaract_Kwanza/gallery.php">Gallery</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h5>Get Involved</h5>
      <ul>
        <li><a href="/Rotaract_Kwanza/join.php">Join Rotaract</a></li>
        <li><a href="/Rotaract_Kwanza/team.php">Our Team</a></li>
        <li><a href="/Rotaract_Kwanza/contact.php">Contact Us</a></li>
        <li><a href="#">Sponsor a Project</a></li>
        <li><a href="#">Volunteer</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h5>Rotary Family</h5>
      <ul>
        <li><a href="https://www.rotary.org" target="_blank">Rotary International</a></li>
        <li><a href="#">Rotary Club of Kwanza</a></li>
        <li><a href="#">Rotaract D9400</a></li>
        <li><a href="#">Our Charter</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> Rotaract Club of Kwanza. All rights reserved.</p>
    <p>Made with <span class="heart">&#9829;</span> for community &amp; service</p>
  </div>
</footer>
