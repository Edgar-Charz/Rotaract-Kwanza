<?php
require_once dirname(__DIR__) . '/classes/SiteSettings.php';
require_once __DIR__ . '/helpers.php';
$_fb   = isset($conn) ? (new SiteSettings($conn))->get('facebook_url',  '') : '';
$_ig   = isset($conn) ? (new SiteSettings($conn))->get('instagram_url', '') : '';
$_tw   = isset($conn) ? (new SiteSettings($conn))->get('twitter_url',   '') : '';
$_tt   = isset($conn) ? (new SiteSettings($conn))->get('tiktok_url',    '') : '';
$_li   = isset($conn) ? (new SiteSettings($conn))->get('linkedin_url',  '') : '';
$_site_name          = isset($conn) ? (new SiteSettings($conn))->get('site_name', 'Rotaract Club of Kwanza') : 'Rotaract Club of Kwanza';
$_motto_text         = isset($conn) ? (new SiteSettings($conn))->get('motto_text', 'Service Above Self') : 'Service Above Self';
$_sponsor_club       = isset($conn) ? (new SiteSettings($conn))->get('sponsor_club', 'Rotary Club of Kwanza') : 'Rotary Club of Kwanza';
$_sponsor_url        = isset($conn) ? (new SiteSettings($conn))->get('sponsor_club_url', '') : '';
$_brand_initials     = isset($conn) ? (new SiteSettings($conn))->get('brand_initials', 'RK') : 'RK';
$_site_logo          = isset($conn) ? (new SiteSettings($conn))->get('site_logo', '') : '';
$_footer_show_logo   = $_site_logo && (isset($conn) ? (new SiteSettings($conn))->get('footer_logo_display', 'logo') : 'logo') === 'logo';
$_footer_description = isset($conn) ? (new SiteSettings($conn))->get('footer_description', 'A vibrant community of young leaders united by the spirit of service, fellowship, and positive change in Kwanza and beyond.') : 'A vibrant community of young leaders united by the spirit of service, fellowship, and positive change in Kwanza and beyond.';
$_footer_tagline     = isset($conn) ? (new SiteSettings($conn))->get('footer_tagline', 'Made with ♥ for community & service') : 'Made with ♥ for community & service';
?>
<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <a href="index.php#home" class="logo">
        <div class="logo-circle">
          <?php if ($_footer_show_logo): ?>
            <img src="<?= htmlspecialchars(img_url($_site_logo), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($_site_name, ENT_QUOTES, 'UTF-8') ?> logo" class="logo-img">
          <?php else: ?>
            <?= htmlspecialchars($_brand_initials, ENT_QUOTES, 'UTF-8') ?>
          <?php endif; ?>
        </div>
        <div class="logo-text"><?= htmlspecialchars($_site_name, ENT_QUOTES, 'UTF-8') ?><span><?= htmlspecialchars($_motto_text, ENT_QUOTES, 'UTF-8') ?></span></div>
      </a>
      <p><?= htmlspecialchars($_footer_description, ENT_QUOTES, 'UTF-8') ?></p>
      <div class="socials mt-20">
        <?php if ($_fb): ?>
          <a href="<?= htmlspecialchars($_fb, ENT_QUOTES, 'UTF-8') ?>" class="social-btn social-btn--dark" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2" stroke-linecap="round">
              <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
            </svg>
          </a>
        <?php endif; ?>
        <?php if ($_ig): ?>
          <a href="<?= htmlspecialchars($_ig, ENT_QUOTES, 'UTF-8') ?>" class="social-btn social-btn--dark" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2" stroke-linecap="round">
              <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
              <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
              <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
            </svg>
          </a>
        <?php endif; ?>
        <?php if ($_tw): ?>
          <a href="<?= htmlspecialchars($_tw, ENT_QUOTES, 'UTF-8') ?>" class="social-btn social-btn--dark" aria-label="X" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="rgba(255,255,255,0.6)">
              <path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z" />
            </svg>
          </a>
        <?php endif; ?>
        <?php if ($_tt): ?>
          <a href="<?= htmlspecialchars($_tt, ENT_QUOTES, 'UTF-8') ?>" class="social-btn social-btn--dark" aria-label="TikTok" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="rgba(255,255,255,0.6)">
              <path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0115.54 3h-3.09v12.4a2.592 2.592 0 01-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 004.3 1.38V7.3s-1.88.09-3.24-1.48z" />
            </svg>
          </a>
        <?php endif; ?>
        <?php if ($_li): ?>
          <a href="<?= htmlspecialchars($_li, ENT_QUOTES, 'UTF-8') ?>" class="social-btn social-btn--dark" aria-label="LinkedIn" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2" stroke-linecap="round">
              <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z" />
              <rect x="2" y="9" width="4" height="12" />
              <circle cx="4" cy="4" r="2" />
            </svg>
          </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="footer-col">
      <h5>Quick Links</h5>
      <ul>
        <li><a href="index.php#home">Home</a></li>
        <li><a href="about.php">About Us</a></li>
        <li><a href="events.php">Events</a></li>
        <li><a href="projects.php">Projects</a></li>
        <li><a href="gallery.php">Gallery</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h5>Get Involved</h5>
      <ul>
        <li><a href="join.php">Join Rotaract</a></li>
        <li><a href="team.php">Our Team</a></li>
        <li><a href="contact.php">Contact Us</a></li>
        <li><a href="donate.php">Donate</a></li>
        <li><a href="news.php#subscribe">Get Email Updates</a></li>
        <li><a href="projects.php">Sponsor a Project</a></li>
        <li><a href="projects.php">Volunteer</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h5>Rotary Family</h5>
      <ul>
        <li><a href="https://www.rotary.org" target="_blank" rel="noopener noreferrer">Rotary International</a></li>
        <?php if ($_sponsor_url): ?>
          <li><a href="<?= htmlspecialchars($_sponsor_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($_sponsor_club, ENT_QUOTES, 'UTF-8') ?></a></li>
        <?php else: ?>
          <li><span><?= htmlspecialchars($_sponsor_club, ENT_QUOTES, 'UTF-8') ?></span></li>
        <?php endif; ?>
        <li><a href="about.php#about">Our Meetings</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($_site_name, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</p>
    <p><?= htmlspecialchars($_footer_tagline, ENT_QUOTES, 'UTF-8') ?> &middot; <a href="admin/login.php" class="footer-admin-link">Admin</a></p>
  </div>
</footer>

<button type="button" id="back-to-top" aria-label="Back to top">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
    <line x1="12" y1="19" x2="12" y2="5" />
    <polyline points="5 12 12 5 19 12" />
  </svg>
</button>