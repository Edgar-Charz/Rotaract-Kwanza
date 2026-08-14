<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Event.php';
require_once __DIR__ . '/classes/Project.php';
require_once __DIR__ . '/classes/TeamMember.php';
require_once __DIR__ . '/classes/Gallery.php';
require_once __DIR__ . '/classes/Announcement.php';
require_once __DIR__ . '/classes/Member.php';
require_once __DIR__ . '/classes/SiteSettings.php';
require_once __DIR__ . '/classes/ClubValue.php';
require_once __DIR__ . '/classes/MembershipPerk.php';
require_once __DIR__ . '/classes/MonthlyRecognition.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();

// getFeaturedOrNextUpcoming() puts featured events first, then fills with
// the soonest upcoming ones — same reasoning as the projects fix below, so
// this row isn't sparse just because few events happen to be flagged featured.
$events = (new Event($conn))->getFeaturedOrNextUpcoming(3);
// getAllExcludingCancelled() orders featured projects first, then most
// recent — so the homepage grid always shows up to 3 cards even when fewer
// than 3 projects are actually marked "Featured", instead of looking
// sparse, while never surfacing a scrapped/cancelled project on the homepage.
$projects = (new Project($conn))->getAllExcludingCancelled(3);
$team = array_slice((new TeamMember($conn))->getActive(), 0, 3);
$gallery = array_slice((new Gallery($conn))->getActive(), 0, 6);
$announcements = (new Announcement($conn))->getPublished(3);
$dir_members   = array_slice((new Member($conn))->getApprovedForDirectory(), 0, 5);
$club_values   = (new ClubValue($conn))->getActive();
$perks         = (new MembershipPerk($conn))->getActive();
$published_recognitions = (new MonthlyRecognition($conn))->getPublished();
$published_recognitions = array_values(array_filter($published_recognitions, static fn(array $recognition): bool => !empty($recognition['members'])));
$monthly_recognition = $published_recognitions[0] ?? false;

$settings = new SiteSettings($conn);
$stat_members = $settings->get('hero_stats_members', '120+');
$stat_projects = $settings->get('hero_stats_projects', '45+');
$stat_lives = $settings->get('hero_stats_lives', '8K+');
$stat_years = $settings->get('hero_stats_years', '12');
$fb = $settings->get('facebook_url', '');
$ig = $settings->get('instagram_url', '');
$tw = $settings->get('twitter_url', '');
$tt = $settings->get('tiktok_url', '');
$li = $settings->get('linkedin_url', '');
$addr = $settings->get('contact_address', 'Kwanza Community Centre, Kwanza District, Angola');
$tel = $settings->get('contact_phone', '+244 900 000 000');
$mail = $settings->get('contact_email', 'info@rotaractkwanza.org');
$contact_intro = $settings->get('contact_intro', 'Whether you have a question, partnership opportunity, or just want to say hello — our doors are always open.');
$hero_image = $settings->get('hero_image', '');
$about_image = $settings->get('about_image', '');
$founding_year = $settings->get('founding_year', '2012');
$hero_badge_year  = $settings->get('hero_badge_year', '2025');
$hero_badge_label = $settings->get('hero_badge_label', 'Outstanding Club Award');
$hero_pill_text   = $settings->get('hero_pill_text', 'Service In Action');
$hero_pill_label  = mb_strtoupper($hero_pill_text, 'UTF-8');
$brand_initials          = $settings->get('brand_initials', 'RK');
$contact_hours           = $settings->get('contact_hours', 'Mon – Fri, 8:00 AM – 5:00 PM');
$hero_eyebrow            = $settings->get('hero_eyebrow', 'Rotaract International · Kwanza');
$hero_title              = $settings->get('hero_title', 'Serving Communities, Changing Lives');
$hero_subtitle           = $settings->get('hero_subtitle', 'Together we make a difference');
$hero_description        = $settings->get('hero_description', 'The Rotaract Club of Kwanza is a vibrant community of young leaders committed to fellowship, professional development, and meaningful service to our community and beyond.');
$home_about_highlight    = $settings->get('home_about_highlight', 'Over a decade of community service and fellowship in Kwanza');
$home_about_description  = $settings->get('home_about_description', 'The Rotaract Club of Kwanza is a Rotary International-sponsored organization bringing together young professionals and leaders aged 18–30 to create lasting change in our community.');
$home_projects_description = $settings->get('home_projects_description', 'Discover our projects');
$home_events_description = $settings->get('home_events_description', 'Discover our next service days, leadership forums, and fellowship celebrations. Join Rotaract Kwanza for meaningful impact.');
$home_team_description   = $settings->get('home_team_description', 'Passionate, driven young leaders who dedicate their time to making a difference in Kwanza.');
$home_join_description   = $settings->get('home_join_description', 'Join a community of passionate young leaders making real change in Kwanza. Membership is open to all aged 18–30.');
$home_news_description   = $settings->get('home_news_description', 'Club updates, meeting minutes, and announcements from Rotaract Club of Kwanza.');
$home_gallery_description = $settings->get('home_gallery_description', 'A glimpse into our community service, events, and fellowship moments.');

$event_colors = ['', 'gold', 'rose'];
$gallery_classes = [
  'tall reveal',
  'reveal reveal-delay-1',
  'reveal reveal-delay-2',
  'wide reveal',
  'reveal reveal-delay-1',
  'reveal reveal-delay-2',
];

$page_title = site_title($conn);
$page_description = $hero_description;
if ($hero_image) $page_image = img_url($hero_image);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
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
      <a class="hero-recognition-callout hero-recognition-callout--desktop" href="recognitions.php"><span class="hero-recognition-callout-icon" aria-hidden="true">★</span><span><small>Member spotlight</small>Rotaractor of the Month</span><b aria-hidden="true">&rarr;</b></a>
      <div class="hero-content">
        <div class="hero-eyebrow-row"><div class="hero-eyebrow"><span class="dot"></span><?= e($hero_eyebrow) ?></div><div class="floating-pill hero-pill-desktop"><span class="pill-dot"></span><?= e($hero_pill_label) ?></div></div>
        <div class="hero-pill-mobile"><span class="pill-dot"></span><?= e($hero_pill_label) ?></div>
        <a class="hero-recognition-callout hero-recognition-callout--mobile" href="recognitions.php"><span class="hero-recognition-callout-icon" aria-hidden="true">★</span><span><small>Member spotlight</small>Rotaractor of the Month</span><b aria-hidden="true">&rarr;</b></a>
        <h1 class="hero-title"><?= e($hero_title) ?></h1>
        <p class="hero-subtitle"><?= e($hero_subtitle) ?></p>
        <p class="hero-desc"><?= e($hero_description) ?></p>
        <div class="hero-actions">
          <a href="join.php" class="btn-primary">
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
        <div class="hero-card-main">
          <?php if ($hero_image): ?>
            <img src="<?= e(img_url($hero_image)) ?>" alt="Rotaract Club of Kwanza" class="hero-card-img">
          <?php else: ?>
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
          <?php endif; ?>
        </div>
        <?php if ($hero_badge_label): ?>
          <div class="hero-badge"><strong><?= e($hero_badge_year) ?></strong><?= e($hero_badge_label) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="wave-divider wave-divider--white">
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
            <?php if ($about_image): ?>
              <img src="<?= e(img_url($about_image)) ?>" alt="Rotaract Club of Kwanza members" class="about-img">
            <?php else: ?>
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
            <?php endif; ?>
          </div>
          <div class="about-card-float">
            <div class="about-card-float-title">Est. <?= e($founding_year) ?></div>
            <div class="about-card-float-sub"><?= e($home_about_highlight) ?></div>
          </div>
        </div>
        <div class="about-content">
          <div class="section-eyebrow reveal">About Us</div>
          <h2 class="section-title reveal reveal-delay-1">Who We <em>Are</em></h2>
          <p class="section-lead reveal reveal-delay-2"><?= e($home_about_description) ?></p>
          <?php if ($club_values): ?>
            <div class="about-values reveal reveal-delay-3">
              <?php foreach ($club_values as $val): ?>
                <div class="value-item">
                  <div class="value-icon value-icon--pink"><?= icon_svg($val['icon_key'], 'var(--pink-700)') ?></div>
                  <div>
                    <h4><?= e($val['title']) ?></h4>
                    <?php if ($val['description']): ?><p><?= e($val['description']) ?></p><?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <a href="about.php" class="btn-secondary btn-secondary--inline-mt24 reveal reveal-delay-4">Learn
            More &rarr;</a>
        </div>
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
          <p class="section-lead reveal reveal-delay-2"><?= e($home_projects_description) ?></p>
        </div>
        <a href="projects.php" class="btn-secondary reveal btn-secondary--hero">All Projects</a>
      </div>
      <div class="projects-grid">
        <?php if ($projects):
          foreach ($projects as $i => $pj): ?>
            <a href="project.php?id=<?= $pj['id'] ?>" class="project-card reveal<?= $i > 0 ? ' reveal-delay-' . $i : '' ?>">
              <?php if ($pj['image_path'] ?? ''): ?>
                <div class="project-icon project-icon--photo">
                  <img src="<?= e(img_url($pj['image_path'])) ?>" alt="<?= e($pj['title']) ?>">
                </div>
              <?php else: ?>
                <div class="project-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="var(--gold-light)" stroke-width="1.8" stroke-linecap="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                  </svg>
                </div>
              <?php endif; ?>
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
            </a>
          <?php endforeach;
        else: ?>
          <div class="home-empty-state home-empty-state--grid-full">
            <p class="fs-110">Projects coming soon.</p>
          </div>
        <?php endif; ?>
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
          <p class="section-lead reveal reveal-delay-2"><?= e($home_events_description) ?></p>
        </div>
        <a href="events.php" class="btn-secondary reveal">View All Events</a>
      </div>
      <div class="events-grid">
        <?php if ($events):
          foreach ($events as $i => $ev): ?>
            <div class="event-card reveal<?= $i > 0 ? ' reveal-delay-' . $i : '' ?> event-card--clickable" data-href="event.php?id=<?= $ev['id'] ?>" tabindex="0" role="link" aria-label="View details for <?= e($ev['title']) ?>">
              <?php if ($ev['image_path'] ?? ''): ?>
                <div class="event-card-img event-card-img--flush">
                  <img src="<?= e(img_url($ev['image_path'])) ?>" alt="<?= e($ev['title']) ?>">
                  <div class="event-date-badge">
                    <div class="day"><?= date('d', strtotime($ev['event_date'])) ?></div>
                    <div class="month"><?= date('M', strtotime($ev['event_date'])) ?></div>
                  </div>
                </div>
              <?php else: ?>
                <div class="event-card-img <?= $event_colors[$i % 3] ?>">
                  <div class="event-icon-badge"><?= icon_svg(event_category_icon($ev['category'] ?? ''), '#fff') ?></div>
                  <div class="event-date-badge">
                    <div class="day"><?= date('d', strtotime($ev['event_date'])) ?></div>
                    <div class="month"><?= date('M', strtotime($ev['event_date'])) ?></div>
                  </div>
                </div>
              <?php endif; ?>
              <div class="event-card-body">
                <span class="event-tag"><?= e($ev['category'] ?? 'General') ?></span>
                <h3><a href="event.php?id=<?= $ev['id'] ?>" class="title-link"><?= e($ev['title']) ?></a></h3>
                <?php if ($ev['description']): ?>
                  <p><?= e($ev['description']) ?></p><?php endif; ?>
                <div class="event-meta">
                  <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-soft)" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                    <circle cx="12" cy="10" r="3" />
                  </svg>
                  <?= e($ev['location'] ?? '') ?> <?= ($ev['location'] && $ev['event_time']) ? ', ' : '' ?>
                  <?= e($ev['event_time'] ?? '') ?>
                </div>
                <div class="event-card-actions">
                  <a href="rsvp.php?id=<?= $ev['id'] ?>"
                    class="event-rsvp-btn"
                    onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">RSVP &rarr;</a>
                  <a href="event.php?id=<?= $ev['id'] ?>"
                    class="event-details-btn"
                    onmouseover="this.style.background='var(--pink-100)'" onmouseout="this.style.background='transparent'">Details</a>
                </div>
              </div>
            </div>
          <?php endforeach;
        else: ?>
          <div class="home-empty-state home-empty-state--grid-full">
            <p class="fs-110">No upcoming events at the moment. Check back soon!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- TEAM -->
  <section id="team">
    <div class="container">
      <div class="section-intro-centered section-intro-centered--sm">
        <div class="section-eyebrow reveal section-eyebrow--center">Our Leadership</div>
        <h2 class="section-title reveal reveal-delay-1">Meet the <em>Team</em></h2>
        <p class="section-lead reveal reveal-delay-2 mx-auto"><?= e($home_team_description) ?></p>
      </div>
      <div class="team-grid">
        <?php if ($team):
          foreach ($team as $i => $tm):
            $pal = avatar_palette($i);
            $initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_filter(explode(' ', $tm['full_name'])))));
            $initials = substr($initials, 0, 2);
        ?>
            <div class="team-card reveal<?= $i > 0 && $i < 4 ? ' reveal-delay-' . $i % 4 : '' ?>" role="link" tabindex="0" data-profile-url="member.php?source=team&id=<?= (int) $tm['id'] ?>">
              <div class="team-avatar" style="background:<?= $pal['bg'] ?>">
                <?php if ($tm['image_path']): ?>
                  <div class="team-avatar-circle team-avatar-circle--photo">
                    <img src="<?= e(img_url($tm['image_path'])) ?>" alt="<?= e($tm['full_name']) ?>">
                  </div>
                <?php else: ?>
                  <div class="team-avatar-circle" style="background:<?= $pal['circle'] ?>"><?= $initials ?></div>
                <?php endif; ?>
              </div>
              <div class="team-card-body">
                <div class="role"><?= e($tm['role']) ?></div>
                <h4><a class="member-card-profile-link" href="member.php?source=team&id=<?= (int) $tm['id'] ?>"><?= e($tm['full_name']) ?></a></h4>
                <?php if ($tm['description']): ?>
                  <p class="member-card-description"><?= e($tm['description']) ?></p><?php endif; ?>
              </div>
            </div>
          <?php endforeach;
        else: ?>
          <div class="home-empty-state home-empty-state--grid-full home-empty-state--tight">
            <p>Team information coming soon.</p>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($team): ?>
        <div class="text-center mt-40 flex flex-wrap gap-12 justify-center">
          <a href="team.php" class="btn-secondary reveal">Meet the Full Team</a>
          <a href="leadership_history.php" class="btn-secondary reveal">Leadership History</a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- GALLERY -->
  <section id="gallery">
    <div class="container">
      <div class="gallery-header-row">
        <div>
          <div class="section-eyebrow reveal">Our Moments</div>
          <h2 class="section-title reveal reveal-delay-1">Photo <em>Gallery</em></h2>
          <p class="section-lead reveal reveal-delay-2"><?= e($home_gallery_description) ?></p>
        </div>
        <a href="gallery.php" class="btn-secondary reveal">View All Photos</a>
      </div>
      <div class="gallery-grid">
        <?php if ($gallery):
          foreach ($gallery as $gi => $photo):
            $gc = $gallery_classes[$gi] ?? 'reveal'; 
        ?>
            <div class="gallery-item <?= $gc ?>">
              <?php if ($photo['image_path']): ?>
                <div class="gallery-inner gallery-inner--home"
                  style="height:<?= $gi === 0 ? '100%' : '200px' ?>">
                  <img src="<?= e(img_url($photo['image_path'])) ?>" alt="<?= e($photo['title']) ?>">
                </div>
              <?php else: ?>
                <div class="gallery-inner gallery-inner--home gallery-inner--placeholder"
                  style="height:<?= $gi === 0 ? '100%' : '200px' ?>">
                  <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                    <rect x="10" y="10" width="40" height="40" rx="4" fill="rgba(255,255,255,0.2)" />
                    <circle cx="22" cy="22" r="5" fill="rgba(255,255,255,0.4)" />
                    <path d="M10 40l12-12 8 8 6-6 14 10H10z" fill="rgba(255,255,255,0.3)" />
                  </svg>
                </div>
              <?php endif; ?>
              <div class="gallery-overlay"><?= e($photo['title']) ?></div>
            </div>
          <?php endforeach;
        else: ?>
          <div class="home-empty-state home-empty-state--grid-full">
            <p class="fs-110">Gallery photos coming soon.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- LATEST NEWS -->
  <?php if ($announcements): ?>
    <section id="news" class="home-news-section">
      <div class="container">
        <div
          class="home-news-header">
          <div>
            <div class="section-eyebrow reveal">Club Updates</div>
            <h2 class="section-title reveal reveal-delay-1">Latest <em>News</em></h2>
            <p class="section-lead reveal reveal-delay-2"><?= e($home_news_description) ?></p>
          </div>
          <a href="news.php" class="btn-secondary reveal">All Posts</a>
        </div>
        <div class="home-news-grid">
          <?php
          $cat_colors = ['news' => '#fce4ef', 'minutes' => '#d6eaff', 'notice' => '#fff3cd', 'announcement' => '#f0eafd'];
          $cat_text   = ['news' => '#c0396b', 'minutes' => '#1a5fb4', 'notice' => '#856404', 'announcement' => '#7b5ea7'];
          $cat_labels = ['news' => 'News', 'minutes' => 'Meeting Minutes', 'notice' => 'Notice', 'announcement' => 'Announcement'];
          foreach ($announcements as $ai => $ann): ?>
            <a href="news.php?slug=<?= e($ann['slug']) ?>"
              class="reveal<?= $ai > 0 ? ' reveal-delay-' . $ai : '' ?> home-news-card"
              onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 30px rgba(192,57,107,0.13)'"
              onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.07)'">
              <?php if ($ann['image_path']): ?>
                <div class="home-news-card-img"><img src="<?= e(img_url($ann['image_path'])) ?>"
                    alt="<?= e($ann['title']) ?>"></div>
              <?php else: ?>
                <div
                  class="home-news-card-placeholder">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--pink-500)" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                  </svg>
                </div>
              <?php endif; ?>
              <div class="home-news-card-body">
                <span
                  class="home-news-cat-tag" style="background:<?= $cat_colors[$ann['category']] ?? '#fce4ef' ?>;color:<?= $cat_text[$ann['category']] ?? '#c0396b' ?>"><?= $cat_labels[$ann['category']] ?? $ann['category'] ?></span>
                <h3
                  class="home-news-title">
                  <?= e($ann['title']) ?>
                </h3>
                <p class="home-news-excerpt">
                  <?= e(mb_strimwidth(strip_tags($ann['content']), 0, 120, '…')) ?>
                </p>
                <div class="home-news-date">
                  <?= date('d M Y', strtotime($ann['created_at'])) ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- MEMBER DIRECTORY PREVIEW -->
  <section id="directory" class="directory-preview">
    <div class="container">
      <div class="dir-preview-header">
        <div>
          <div class="section-eyebrow reveal">Our Members</div>
          <h2 class="section-title reveal reveal-delay-1">Member <em>Directory</em></h2>
          <p class="section-lead reveal reveal-delay-2">Meet the passionate young leaders who make up Rotaract Club of Kwanza.</p>
        </div>
        <a href="directory.php" class="btn-secondary reveal">Full Directory</a>
      </div>
      <?php if ($dir_members): ?>
        <div class="dir-grid">
          <?php foreach ($dir_members as $di => $dm):
            $initials = strtoupper(substr($dm['first_name'], 0, 1) . substr($dm['last_name'], 0, 1));
            $av_class = 'av-' . ($di % 7);
          ?>
            <div class="dir-card reveal<?= $di > 0 ? ' reveal-delay-' . ($di % 4) : '' ?>" role="link" tabindex="0" data-profile-url="member.php?source=directory&id=<?= (int) $dm['id'] ?>">
              <?php if ($dm['photo_path']): ?>
                <div class="dir-avatar dir-avatar-photo">
                  <img src="<?= e(img_url($dm['photo_path'])) ?>" alt="<?= e($dm['first_name'] . ' ' . $dm['last_name']) ?>">
                </div>
              <?php else: ?>
                <div class="dir-avatar <?= $av_class ?>"><?= e($initials) ?></div>
              <?php endif; ?>
              <div class="dir-name"><a class="member-card-profile-link" href="member.php?source=directory&id=<?= (int) $dm['id'] ?>"><?= e($dm['first_name'] . ' ' . $dm['last_name']) ?></a></div>
              <?php if ($dm['occupation']): ?>
                <div class="dir-role"><?= e($dm['occupation']) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="home-empty-state">
          <p class="fs-110">No members listed yet.</p>
          <a href="directory.php" class="dir-browse-link">Browse Directory</a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($monthly_recognition && $monthly_recognition['members']): ?>
    <!-- ROTARACTOR OF THE MONTH -->
    <section id="monthly-recognition" class="monthly-recognition-section">
      <div class="container">
        <div class="monthly-recognition-slider reveal" data-recognition-slider>
          <?php foreach ($published_recognitions as $recognition_index => $recognition_item): ?>
          <article class="monthly-recognition monthly-recognition-slide<?= $recognition_index === 0 ? ' is-active' : '' ?>" data-recognition-slide data-card-url="recognitions.php" aria-hidden="<?= $recognition_index === 0 ? 'false' : 'true' ?>" tabindex="0" role="link" aria-label="View all Rotaractors of the Month">
          <div class="monthly-recognition-intro">
            <div class="monthly-recognition-kicker">★ Rotaractor of the Month</div>
            <h2><?= e(date('F Y', strtotime($recognition_item['recognition_month']))) ?> <em>Spotlight</em></h2>
            <?php if ($recognition_item['citation']): ?><p><?= e($recognition_item['citation']) ?></p><?php endif; ?>
            <a class="monthly-recognition-archive-link" href="recognitions.php">View all Rotaractors of the Month <span aria-hidden="true">&rarr;</span></a>
          </div>
          <div class="monthly-recognition-members">
            <?php foreach ($recognition_item['members'] as $recipient):
              $initials = strtoupper(substr($recipient['first_name'], 0, 1) . substr($recipient['last_name'], 0, 1));
            ?>
              <a class="monthly-recipient" href="member.php?source=directory&id=<?= (int) $recipient['id'] ?>">
                <?php if ($recipient['photo_path']): ?>
                  <img src="<?= e(img_url($recipient['photo_path'])) ?>" alt="<?= e($recipient['first_name'] . ' ' . $recipient['last_name']) ?>">
                <?php else: ?>
                  <span class="monthly-recipient-initials"><?= e($initials) ?></span>
                <?php endif; ?>
                <span class="monthly-recipient-name"><?= e($recipient['first_name'] . ' ' . $recipient['last_name']) ?></span>
                <?php if ($recipient['occupation']): ?><span class="monthly-recipient-role"><?= e($recipient['occupation']) ?></span><?php endif; ?>
                <?php if ($recipient['citation']): ?><span class="monthly-recipient-citation"><?= e($recipient['citation']) ?></span><?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
          </article>
          <?php endforeach; ?>
          <?php if (count($published_recognitions) > 1): ?>
            <button type="button" class="monthly-recognition-slider-button monthly-recognition-slider-button--previous" data-recognition-prev aria-label="Show previous month">&larr;</button>
            <button type="button" class="monthly-recognition-slider-button monthly-recognition-slider-button--next" data-recognition-next aria-label="Show next month">&rarr;</button>
            <div class="monthly-recognition-slider-status" data-recognition-status aria-live="polite">1 of <?= count($published_recognitions) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- JOIN CTA -->
  <section id="join">
    <div class="container">
      <div class="join-grid">
        <div>
          <div class="section-eyebrow reveal">Membership</div>
          <h2 class="section-title reveal reveal-delay-1">Be Part of <em>Something</em> Greater</h2>
          <p class="section-lead reveal reveal-delay-2"><?= e($home_join_description) ?></p>
          <?php if ($perks): ?>
            <div class="join-perks reveal reveal-delay-3">
              <?php foreach ($perks as $perk): ?>
                <div class="perk">
                  <div class="perk-icon perk-icon--pink"><?= icon_svg($perk['icon_key'], 'var(--pink-700)') ?></div>
                  <span><?= e($perk['title']) ?><?= $perk['description'] ? ' — ' . e($perk['description']) : '' ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="join-form reveal reveal-delay-2">
          <h3>Ready to Join Us?</h3>
          <p>Fill out our membership application and our team will get back to you within 3 business days.</p>
          <div class="mt-24">
            <a href="join.php" class="btn-submit btn-submit--inline">Apply for Membership
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
          <p class="section-lead reveal reveal-delay-2"><?= e($contact_intro) ?></p>
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
                <p><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $tel)) ?>"><?= e($tel) ?></a><br><?= e($contact_hours) ?></p>
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
                <p><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></p>
              </div>
            </div>
          </div>
          <div class="socials reveal">
            <?php if ($fb): ?>
              <a href="<?= e($fb) ?>" class="social-btn" aria-label="Facebook" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none"
                  stroke-width="2" stroke-linecap="round">
                  <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                </svg></a>
            <?php endif; ?>
            <?php if ($ig): ?>
              <a href="<?= e($ig) ?>" class="social-btn" aria-label="Instagram" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none"
                  stroke-width="2" stroke-linecap="round">
                  <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                  <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
                  <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                </svg></a>
            <?php endif; ?>
            <?php if ($tw): ?>
              <a href="<?= e($tw) ?>" class="social-btn" aria-label="X" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="currentColor">
                  <path
                    d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z" />
                </svg></a>
            <?php endif; ?>
            <?php if ($tt): ?>
              <a href="<?= e($tt) ?>" class="social-btn" aria-label="TikTok" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="currentColor">
                  <path
                    d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0115.54 3h-3.09v12.4a2.592 2.592 0 01-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 004.3 1.38V7.3s-1.88.09-3.24-1.48z" />
                </svg></a>
            <?php endif; ?>
            <?php if ($li): ?>
              <a href="<?= e($li) ?>" class="social-btn" aria-label="LinkedIn" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none"
                  stroke-width="2" stroke-linecap="round">
                  <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z" />
                  <rect x="2" y="9" width="4" height="12" />
                  <circle cx="4" cy="4" r="2" />
                </svg></a>
            <?php endif; ?>
          </div>
        </div>
        <div class="contact-form reveal reveal-delay-2">
          <h3>Send Us a Message</h3>
          <p>Have something to say? We'd love to hear from you. Use our contact page to get in touch.</p>
          <div class="mt-24">
            <a href="contact.php" class="btn-submit btn-submit--inline">Go to Contact Page
              &rarr;</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/scripts.js"></script>
  <script>
    document.querySelectorAll('[data-recognition-slider]').forEach(function (slider) {
      var slides = Array.prototype.slice.call(slider.querySelectorAll('[data-recognition-slide]'));
      var previous = slider.querySelector('[data-recognition-prev]');
      var next = slider.querySelector('[data-recognition-next]');
      var status = slider.querySelector('[data-recognition-status]');
      var activeIndex = 0;
      function showSlide(index) {
        activeIndex = Math.max(0, Math.min(index, slides.length - 1));
        slides.forEach(function (slide, slideIndex) {
          var isActive = slideIndex === activeIndex;
          slide.classList.toggle('is-active', isActive);
          slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
        if (status) status.textContent = (activeIndex + 1) + ' of ' + slides.length;
        if (previous) previous.disabled = activeIndex === slides.length - 1;
        if (next) next.disabled = activeIndex === 0;
      }
      if (previous) previous.addEventListener('click', function () { showSlide(activeIndex + 1); });
      if (next) next.addEventListener('click', function () { showSlide(activeIndex - 1); });
      showSlide(activeIndex);
    });
  </script>
</body>

</html>
