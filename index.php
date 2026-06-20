<?php
session_start();
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Event.php';
require_once __DIR__ . '/classes/Project.php';
require_once __DIR__ . '/classes/TeamMember.php';
require_once __DIR__ . '/classes/Gallery.php';
require_once __DIR__ . '/classes/Announcement.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();

$events = (new Event($conn))->getFeaturedUpcoming(3);
$projects = (new Project($conn))->getFeatured(4);
$team = (new TeamMember($conn))->getActive();
$gallery = array_slice((new Gallery($conn))->getActive(), 0, 6);
$announcements = (new Announcement($conn))->getPublished(3);

$settings = new SiteSettings($conn);
$stat_members = $settings->get('hero_stats_members', '120+');
$stat_projects = $settings->get('hero_stats_projects', '45+');
$stat_lives = $settings->get('hero_stats_lives', '8K+');
$stat_years = $settings->get('hero_stats_years', '12');
$fb = $settings->get('facebook_url', '#');
$ig = $settings->get('instagram_url', '#');
$tw = $settings->get('twitter_url', '#');
$li = $settings->get('linkedin_url', '#');
$addr = $settings->get('contact_address', 'Kwanza Community Centre, Kwanza District, Angola');
$tel = $settings->get('contact_phone', '+244 900 000 000');
$mail = $settings->get('contact_email', 'info@rotaractkwanza.org');

$event_colors = ['', 'gold', 'rose'];
$gallery_classes = [
  'tall reveal" style="grid-row:span 2',
  'reveal reveal-delay-1',
  'reveal reveal-delay-2',
  'wide reveal',
  'reveal reveal-delay-1',
  'reveal reveal-delay-2',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rotaract Club of Kwanza</title>
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

  <!-- HERO -->
  <section id="home">
    <div class="hero-bg">
      <div class="hero-blob hero-blob-1"></div>
      <div class="hero-blob hero-blob-2"></div>
      <div class="hero-blob hero-blob-3"></div>
    </div>
    <div class="hero-grid container">
      <div class="hero-content">
        <div class="hero-eyebrow"><span class="dot"></span>Rotaract International &middot; Kwanza</div>
        <h1 class="hero-title">Serving <em>Communities,</em><br>Changing Lives</h1>
        <p class="hero-subtitle">Together we make a difference</p>
        <p class="hero-desc">The Rotaract Club of Kwanza is a vibrant community of young leaders committed to
          fellowship, professional development, and meaningful service to our community and beyond.</p>
        <div class="hero-actions">
          <a href="/Rotaract_Kwanza/join.php" class="btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 5v14M5 12h14" />
            </svg>
            Join Our Club
          </a>
          <a href="#about" class="btn-secondary">Learn More
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M12 5l7 7-7 7" />
            </svg>
          </a>
        </div>
        <div class="hero-stats">
          <div class="hero-stat">
            <div class="hero-stat-num"><?= e($stat_members) ?></div>
            <div class="hero-stat-label">Members</div>
          </div>
          <div class="hero-stat">
            <div class="hero-stat-num"><?= e($stat_projects) ?></div>
            <div class="hero-stat-label">Projects</div>
          </div>
          <div class="hero-stat">
            <div class="hero-stat-num"><?= e($stat_lives) ?></div>
            <div class="hero-stat-label">Lives Touched</div>
          </div>
          <div class="hero-stat">
            <div class="hero-stat-num"><?= e($stat_years) ?></div>
            <div class="hero-stat-label">Years Active</div>
          </div>
        </div>
      </div>
      <div class="hero-visual">
        <div class="floating-pill"><span class="pill-dot"></span>Service in Action</div>
        <div class="hero-card-main">
          <div class="hero-card-main-inner">
            <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="60" cy="60" r="52" stroke="rgba(255,255,255,0.3)" stroke-width="2" />
              <path d="M60 30 C60 30, 88 50, 88 68 C88 84 75 94 60 94 C45 94 32 84 32 68 C32 50 60 30 60 30Z"
                fill="rgba(255,255,255,0.2)" stroke="rgba(255,255,255,0.6)" stroke-width="1.5" />
              <circle cx="60" cy="58" r="14" fill="rgba(255,255,255,0.25)" stroke="rgba(255,255,255,0.8)"
                stroke-width="1.5" />
              <path d="M52 72 L60 64 L68 72" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="2"
                stroke-linecap="round" />
              <circle cx="40" cy="44" r="6" fill="rgba(255,255,255,0.3)" stroke="rgba(255,255,255,0.6)"
                stroke-width="1" />
              <circle cx="80" cy="44" r="6" fill="rgba(255,255,255,0.3)" stroke="rgba(255,255,255,0.6)"
                stroke-width="1" />
            </svg>
            <p>Empowering youth through service &amp; fellowship</p>
          </div>
        </div>
        <div class="hero-badge"><strong>2025</strong>Outstanding Club Award</div>
      </div>
    </div>
  </section>

  <div class="wave-divider" style="background:#fff;margin-top:-1px;">
    <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M0,40 C240,80 480,0 720,40 C960,80 1200,0 1440,40 L1440,0 L0,0 Z" fill="#FFF5F9" />
    </svg>
  </div>

  <!-- ABOUT -->
  <section id="about">
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
          <p class="section-lead reveal reveal-delay-2">The Rotaract Club of Kwanza is a Rotary International-sponsored
            organization bringing together young professionals and leaders aged 18&ndash;30 to create lasting change in
            our community.</p>
          <div class="about-values reveal reveal-delay-3">
            <div class="value-item">
              <div class="value-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2"
                  stroke-linecap="round">
                  <path
                    d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z" />
                </svg></div>
              <div>
                <h4>Fellowship &amp; Community</h4>
                <p>Building meaningful friendships and networks among young leaders across all walks of life.</p>
              </div>
            </div>
            <div class="value-item">
              <div class="value-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2"
                  stroke-linecap="round">
                  <path
                    d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                </svg></div>
              <div>
                <h4>Professional Development</h4>
                <p>Empowering members with skills, mentorship, and opportunities to grow as future leaders.</p>
              </div>
            </div>
            <div class="value-item">
              <div class="value-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2"
                  stroke-linecap="round">
                  <circle cx="12" cy="12" r="10" />
                  <path d="M12 8v4l3 3" />
                </svg></div>
              <div>
                <h4>Service Above Self</h4>
                <p>Dedicating our time and talent to uplifting lives through impactful community service projects.</p>
              </div>
            </div>
          </div>
          <a href="/Rotaract_Kwanza/about.php" class="btn-secondary" style="display:inline-block;margin-top:24px">Learn
            More &rarr;</a>
        </div>
      </div>
    </div>
  </section>

  <!-- EVENTS -->
  <section id="events">
    <div class="container">
      <div class="events-header">
        <div>
          <div class="section-eyebrow reveal">Events &amp; Activities</div>
          <h2 class="section-title reveal reveal-delay-1">Upcoming <em>Events</em></h2>
          <p class="section-lead reveal reveal-delay-2">Discover our next service days, leadership forums, and
            fellowship celebrations. Join Rotaract Kwanza for meaningful impact.</p>
        </div>
        <a href="/Rotaract_Kwanza/events.php" class="btn-secondary reveal">View All Events</a>
      </div>
      <div class="events-grid">
        <?php if ($events):
          foreach ($events as $i => $ev): ?>
            <div class="event-card reveal<?= $i > 0 ? ' reveal-delay-' . $i : '' ?>">
              <div class="event-card-img <?= $event_colors[$i % 3] ?>">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                  <rect x="16" y="20" width="28" height="22" rx="3" fill="rgba(255,255,255,0.25)"
                    stroke="rgba(255,255,255,0.8)" stroke-width="1.5" />
                  <path d="M22 20V17a2 2 0 014 0v3M34 20V17a2 2 0 014 0v3" stroke="rgba(255,255,255,0.8)" stroke-width="1.5"
                    stroke-linecap="round" />
                  <path d="M22 30h16M22 34h10" stroke="rgba(255,255,255,0.6)" stroke-width="1.5" stroke-linecap="round" />
                </svg>
                <div class="event-date-badge">
                  <div class="day"><?= date('d', strtotime($ev['event_date'])) ?></div>
                  <div class="month"><?= date('M', strtotime($ev['event_date'])) ?></div>
                </div>
              </div>
              <div class="event-card-body">
                <span class="event-tag"><?= e($ev['category'] ?? 'General') ?></span>
                <h3><?= e($ev['title']) ?></h3>
                <?php if ($ev['description']): ?>
                  <p><?= e($ev['description']) ?></p><?php endif; ?>
                <div class="event-meta">
                  <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-soft)" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                    <circle cx="12" cy="10" r="3" />
                  </svg>
                  <?= e($ev['location'] ?? '') ?>     <?= ($ev['location'] && $ev['event_time']) ? ', ' : '' ?>
                  <?= e($ev['event_time'] ?? '') ?>
                </div>
              </div>
            </div>
          <?php endforeach; else: ?>
          <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--text-soft)">
            <p style="font-size:1.1rem">No upcoming events at the moment. Check back soon!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- PROJECTS -->
  <section id="projects">
    <div class="container">
      <div class="projects-header">
        <div>
          <div class="section-eyebrow reveal">Our Impact</div>
          <h2 class="section-title reveal reveal-delay-1">Featured <em>Projects</em></h2>
        </div>
        <a href="/Rotaract_Kwanza/projects.php" class="btn-secondary reveal"
          style="color:#fff;border-color:rgba(255,255,255,0.3)">All Projects</a>
      </div>
      <div class="projects-grid">
        <?php if ($projects):
          foreach ($projects as $i => $pj): ?>
            <div class="project-card reveal<?= $i > 0 ? ' reveal-delay-' . $i : '' ?>">
              <div class="project-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--gold-light)" stroke-width="1.8" stroke-linecap="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                  <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
              </div>
              <h3><?= e($pj['title']) ?></h3>
              <?php if ($pj['description']): ?>
                <p><?= e($pj['description']) ?></p><?php endif; ?>
              <div class="project-impact">
                <?php if ($pj['impact_stat']): ?>
                  <div class="impact-stat">
                    <div class="impact-num"><?= e($pj['impact_stat']) ?></div>
                    <div class="impact-label"><?= e($pj['impact_label'] ?? '') ?></div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; else: ?>
          <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--text-soft)">
            <p style="font-size:1.1rem">Projects coming soon.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- LATEST NEWS -->
  <?php if ($announcements): ?>
    <section id="news" style="background:#f8f5ff;padding:80px 0">
      <div class="container">
        <div
          style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:32px;flex-wrap:wrap;gap:16px">
          <div>
            <div class="section-eyebrow reveal">Club Updates</div>
            <h2 class="section-title reveal reveal-delay-1">Latest <em>News</em></h2>
          </div>
          <a href="/Rotaract_Kwanza/news.php" class="btn-secondary reveal">All Posts</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:24px">
          <?php
          $cat_colors = ['news' => '#fce4ef', 'minutes' => '#d6eaff', 'notice' => '#fff3cd', 'announcement' => '#f0eafd'];
          $cat_text = ['news' => '#c0396b', 'minutes' => '#1a5fb4', 'notice' => '#856404', 'announcement' => '#7b5ea7'];
          $cat_labels = ['news' => 'News', 'minutes' => 'Meeting Minutes', 'notice' => 'Notice', 'announcement' => 'Announcement'];
          foreach ($announcements as $ai => $ann): ?>
            <a href="/Rotaract_Kwanza/news.php?slug=<?= e($ann['slug']) ?>"
              class="reveal<?= $ai > 0 ? ' reveal-delay-' . $ai : '' ?>"
              style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.07);text-decoration:none;color:inherit;display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s"
              onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 30px rgba(192,57,107,0.13)'"
              onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.07)'">
              <?php if ($ann['image_path']): ?>
                <div style="height:160px;overflow:hidden"><img src="<?= e($ann['image_path']) ?>"
                    alt="<?= e($ann['title']) ?>" style="width:100%;height:100%;object-fit:cover"></div>
              <?php else: ?>
                <div
                  style="height:160px;background:linear-gradient(135deg,var(--pink-100),var(--pink-200));display:flex;align-items:center;justify-content:center">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--pink-500)" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                  </svg>
                </div>
              <?php endif; ?>
              <div style="padding:20px;flex:1;display:flex;flex-direction:column">
                <span
                  style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:<?= $cat_colors[$ann['category']] ?? '#fce4ef' ?>;color:<?= $cat_text[$ann['category']] ?? '#c0396b' ?>;margin-bottom:10px"><?= $cat_labels[$ann['category']] ?? $ann['category'] ?></span>
                <h3
                  style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-weight:700;margin-bottom:8px;line-height:1.35">
                  <?= e($ann['title']) ?>
                </h3>
                <p style="font-size:13px;color:#636e72;line-height:1.6;flex:1">
                  <?= e(mb_strimwidth(strip_tags($ann['content']), 0, 120, '…')) ?>
                </p>
                <div style="font-size:12px;color:#b2bec3;margin-top:12px">
                  <?= date('d M Y', strtotime($ann['created_at'])) ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- TEAM -->
  <section id="team">
    <div class="container">
      <div style="text-align:center;max-width:600px;margin:0 auto 12px">
        <div class="section-eyebrow reveal" style="justify-content:center">Our Leadership</div>
        <h2 class="section-title reveal reveal-delay-1">Meet the <em>Team</em></h2>
        <p class="section-lead reveal reveal-delay-2" style="margin:0 auto">Passionate, driven young leaders who
          dedicate their time to making a difference in Kwanza.</p>
      </div>
      <div class="team-grid">
        <?php if ($team):
          foreach ($team as $i => $tm):
            $pal = avatar_palette($i);
            $initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_filter(explode(' ', $tm['full_name'])))));
            $initials = substr($initials, 0, 2);
            ?>
            <div class="team-card reveal<?= $i > 0 && $i < 4 ? ' reveal-delay-' . $i % 4 : '' ?>">
              <div class="team-avatar" style="background:<?= $pal['bg'] ?>">
                <?php if ($tm['image_path']): ?>
                  <div class="team-avatar-circle" style="overflow:hidden;padding:0">
                    <img src="<?= e($tm['image_path']) ?>" alt="<?= e($tm['full_name']) ?>"
                      style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">
                  </div>
                <?php else: ?>
                  <div class="team-avatar-circle" style="background:<?= $pal['circle'] ?>"><?= $initials ?></div>
                <?php endif; ?>
              </div>
              <div class="team-card-body">
                <h4><?= e($tm['full_name']) ?></h4>
                <div class="role"><?= e($tm['role']) ?></div>
                <?php if ($tm['description']): ?>
                  <p><?= e($tm['description']) ?></p><?php endif; ?>
              </div>
            </div>
          <?php endforeach; else: ?>
          <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-soft)">
            <p>Team information coming soon.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- GALLERY -->
  <section id="gallery">
    <div class="container">
      <div
        style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:0;flex-wrap:wrap;gap:20px">
        <div>
          <div class="section-eyebrow reveal">Our Moments</div>
          <h2 class="section-title reveal reveal-delay-1">Photo <em>Gallery</em></h2>
        </div>
        <a href="/Rotaract_Kwanza/gallery.php" class="btn-secondary reveal">View All Photos</a>
      </div>
      <div class="gallery-grid">
        <?php if ($gallery):
          foreach ($gallery as $gi => $photo):
            $gc = $gallery_classes[$gi] ?? 'reveal';
            ?>
            <div class="gallery-item <?= $gc ?>">
              <?php if ($photo['image_path']): ?>
                <div class="gallery-inner"
                  style="height:<?= $gi === 0 ? '100%' : '200px' ?>;border-radius:var(--radius-md);overflow:hidden;position:relative">
                  <img src="<?= e($photo['image_path']) ?>" alt="<?= e($photo['title']) ?>"
                    style="width:100%;height:100%;object-fit:cover;display:block">
                </div>
              <?php else: ?>
                <div class="gallery-inner"
                  style="background:linear-gradient(145deg,var(--pink-300),var(--pink-700));height:<?= $gi === 0 ? '100%' : '200px' ?>;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center">
                  <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                    <rect x="10" y="10" width="40" height="40" rx="4" fill="rgba(255,255,255,0.2)" />
                    <circle cx="22" cy="22" r="5" fill="rgba(255,255,255,0.4)" />
                    <path d="M10 40l12-12 8 8 6-6 14 10H10z" fill="rgba(255,255,255,0.3)" />
                  </svg>
                </div>
              <?php endif; ?>
              <div class="gallery-overlay"><?= e($photo['title']) ?></div>
            </div>
          <?php endforeach; else: ?>
          <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--text-soft)">
            <p style="font-size:1.1rem">Gallery photos coming soon.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- JOIN CTA -->
  <section id="join">
    <div class="container">
      <div class="join-grid">
        <div>
          <div class="section-eyebrow reveal">Membership</div>
          <h2 class="section-title reveal reveal-delay-1">Be Part of <em>Something</em> Greater</h2>
          <p class="section-lead reveal reveal-delay-2">Join a community of passionate young leaders making real change
            in Kwanza. Membership is open to all aged 18&ndash;30.</p>
          <div class="join-perks reveal reveal-delay-3">
            <div class="perk">
              <div class="perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2"
                  stroke-linecap="round">
                  <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
                </svg></div>
              <span>Access to a global network of Rotaractors</span>
            </div>
            <div class="perk">
              <div class="perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2"
                  stroke-linecap="round">
                  <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z" />
                  <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z" />
                </svg></div>
              <span>Leadership training &amp; skill-building workshops</span>
            </div>
            <div class="perk">
              <div class="perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2"
                  stroke-linecap="round">
                  <path
                    d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z" />
                </svg></div>
              <span>Meaningful community service &amp; social events</span>
            </div>
            <div class="perk">
              <div class="perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)" stroke-width="2"
                  stroke-linecap="round">
                  <circle cx="12" cy="8" r="6" />
                  <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
                </svg></div>
              <span>Recognition, awards &amp; international exposure</span>
            </div>
          </div>
        </div>
        <div class="join-form reveal reveal-delay-2">
          <h3>Ready to Join Us?</h3>
          <p>Fill out our membership application and our team will get back to you within 3 business days.</p>
          <div style="margin-top:24px">
            <a href="/Rotaract_Kwanza/join.php" class="btn-submit" style="display:inline-block; text-decoration: none;">Apply for Membership
              &rarr;</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CONTACT -->
  <section id="contact">
    <div class="container">
      <div class="contact-grid">
        <div>
          <div class="section-eyebrow reveal">Get In Touch</div>
          <h2 class="section-title reveal reveal-delay-1">We'd Love to <em>Hear</em> From You</h2>
          <p class="section-lead reveal reveal-delay-2">Whether you have a question, partnership opportunity, or just
            want to say hello &mdash; our doors are always open.</p>
          <div class="contact-info reveal reveal-delay-3">
            <div class="contact-item">
              <div class="contact-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)"
                  stroke-width="2" stroke-linecap="round">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                  <circle cx="12" cy="10" r="3" />
                </svg></div>
              <div>
                <h4>Visit Us</h4>
                <p><?= e($addr) ?></p>
              </div>
            </div>
            <div class="contact-item">
              <div class="contact-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)"
                  stroke-width="2" stroke-linecap="round">
                  <path
                    d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 8.63a19.79 19.79 0 01-3.07-8.67A2 2 0 012.18 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14.92z" />
                </svg></div>
              <div>
                <h4>Call Us</h4>
                <p><?= e($tel) ?><br>Mon &ndash; Fri, 8:00 AM &ndash; 5:00 PM</p>
              </div>
            </div>
            <div class="contact-item">
              <div class="contact-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pink-700)"
                  stroke-width="2" stroke-linecap="round">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                  <polyline points="22,6 12,13 2,6" />
                </svg></div>
              <div>
                <h4>Email Us</h4>
                <p><?= e($mail) ?></p>
              </div>
            </div>
          </div>
          <div class="socials reveal">
            <a href="<?= e($fb) ?>" class="social-btn" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="none"
                stroke-width="2" stroke-linecap="round">
                <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
              </svg></a>
            <a href="<?= e($ig) ?>" class="social-btn" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none"
                stroke-width="2" stroke-linecap="round">
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
              </svg></a>
            <a href="<?= e($tw) ?>" class="social-btn" aria-label="Twitter / X"><svg viewBox="0 0 24 24" fill="none"
                stroke-width="2" stroke-linecap="round">
                <path
                  d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z" />
              </svg></a>
            <a href="<?= e($li) ?>" class="social-btn" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="none"
                stroke-width="2" stroke-linecap="round">
                <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z" />
                <rect x="2" y="9" width="4" height="12" />
                <circle cx="4" cy="4" r="2" />
              </svg></a>
          </div>
        </div>
        <div class="contact-form reveal reveal-delay-2">
          <h3>Send Us a Message</h3>
          <p>Have something to say? We'd love to hear from you. Use our contact page to get in touch.</p>
          <div style="margin-top:24px">
            <a href="/Rotaract_Kwanza/contact.php" class="btn-submit" style="display:inline-block; text-decoration: none;">Go to Contact Page
              &rarr;</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="/Rotaract_Kwanza/assets/js/scripts.js"></script>
</body>

</html>