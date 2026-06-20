<?php
session_start();
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="/Rotaract_Kwanza/assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="/Rotaract_Kwanza/assets/css/kwanza.css">
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <section id="about" style="padding-top:100px">
    <div class="container">
      <div class="about-grid">
        <div class="about-visual reveal">
          <div class="about-img-wrap">
            <svg viewBox="0 0 300 300" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="150" cy="150" r="120" fill="rgba(212,52,122,0.15)" />
              <circle cx="150" cy="150" r="80" fill="rgba(212,52,122,0.2)" />
              <circle cx="150" cy="100" r="28" fill="var(--pink-500)" opacity="0.7" />
              <path d="M90 200 C90 165 110 148 150 148 C190 148 210 165 210 200" stroke="var(--pink-600)"
                stroke-width="3" fill="none" stroke-linecap="round" />
              <circle cx="100" cy="90" r="18" fill="var(--pink-400)" opacity="0.6" />
              <circle cx="200" cy="90" r="18" fill="var(--pink-400)" opacity="0.6" />
              <path d="M70 185 C70 160 82 150 100 150 C118 150 130 160 130 185" stroke="var(--pink-500)"
                stroke-width="2" fill="none" stroke-linecap="round" opacity="0.7" />
              <path d="M170 185 C170 160 182 150 200 150 C218 150 230 160 230 185" stroke="var(--pink-500)"
                stroke-width="2" fill="none" stroke-linecap="round" opacity="0.7" />
            </svg>
          </div>
          <div class="about-card-float">
            <div class="about-card-float-title">Est. 2012</div>
            <div class="about-card-float-sub">Over a decade of community service and fellowship in Kwanza</div>
          </div>
        </div>

        <div class="about-content">
          <div class="section-eyebrow reveal">About Us</div>
          <h2 class="section-title reveal reveal-delay-1">Who We <em>Are</em></h2>
          <p class="section-lead reveal reveal-delay-2">
            The Rotaract Club of Kwanza is a Rotary International-sponsored organization bringing together young
            professionals and leaders aged 18&ndash;30 to create lasting change in our community.
          </p>
          <div class="about-values reveal reveal-delay-3">
            <div class="value-item">
              <div class="value-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round">
                  <path
                    d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z" />
                </svg>
              </div>
              <div>
                <h4>Fellowship &amp; Community</h4>
                <p>Building meaningful friendships and networks among young leaders across all walks of life.</p>
              </div>
            </div>
            <div class="value-item">
              <div class="value-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round">
                  <path
                    d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                </svg>
              </div>
              <div>
                <h4>Professional Development</h4>
                <p>Empowering members with skills, mentorship, and opportunities to grow as future leaders.</p>
              </div>
            </div>
            <div class="value-item">
              <div class="value-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2" stroke-linecap="round">
                  <circle cx="12" cy="12" r="10" />
                  <path d="M12 8v4l3 3" />
                </svg>
              </div>
              <div>
                <h4>Service Above Self</h4>
                <p>Dedicating our time and talent to uplifting lives through impactful community service projects.</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div style="margin-top:80px">
        <div class="section-eyebrow reveal">Our Mission</div>
        <h2 class="section-title reveal reveal-delay-1">What Drives <em>Us</em></h2>
        <div class="stats-grid reveal reveal-delay-2">
          <div class="stat-card">
            <div class="stat-number">12+</div>
            <div class="stat-label">Years of Service</div>
          </div>
          <div class="stat-card">
            <div class="stat-number">100+</div>
            <div class="stat-label">Active Members</div>
          </div>
          <div class="stat-card">
            <div class="stat-number">50+</div>
            <div class="stat-label">Projects Completed</div>
          </div>
          <div class="stat-card">
            <div class="stat-number">5k+</div>
            <div class="stat-label">Lives Impacted</div>
          </div>
        </div>
      </div>

      <div style="margin-top:80px;text-align:center">
        <div class="section-eyebrow reveal">Get Involved</div>
        <h2 class="section-title reveal reveal-delay-1">Ready to Make a <em>Difference</em>?</h2>
        <p class="section-lead reveal reveal-delay-2" style="max-width:560px;margin:0 auto 2rem">Join a global movement
          of young leaders dedicated to service, fellowship, and building a better world.</p>
        <div class="reveal reveal-delay-3">
          <a href="/Rotaract_Kwanza/join.php" class="btn-submit" style="display:inline-block;margin-right:1rem">Join Us
            &rarr;</a>
          <a href="/Rotaract_Kwanza/contact.php" class="btn-submit"
            style="display:inline-block;background:transparent;border:2px solid var(--pink-700);color:var(--pink-700)">Contact
            Us</a>
        </div>
      </div>
    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="/Rotaract_Kwanza/assets/js/scripts.js"></script>
</body>

</html>