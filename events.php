<?php
session_start();
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Event.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$event     = new Event($conn);
$upcoming  = $event->getUpcoming();
$past      = $event->getPast(6);

$event_colors = ['', 'gold', 'rose'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Events &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="/Rotaract_Kwanza/assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/Rotaract_Kwanza/assets/css/kwanza.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section id="events" style="padding-top:100px">
  <div class="container">
    <div class="events-header">
      <div>
        <div class="section-eyebrow reveal">Events &amp; Activities</div>
        <h2 class="section-title reveal reveal-delay-1">Upcoming <em>Events</em></h2>
        <p class="section-lead reveal reveal-delay-2">Discover our next service days, leadership forums, and fellowship celebrations. Join Rotaract Kwanza for meaningful impact and community connection.</p>
      </div>
    </div>

    <?php if ($upcoming): ?>
      <div class="events-grid">
        <?php foreach ($upcoming as $i => $ev): ?>
          <div class="event-card reveal<?= $i > 0 ? ' reveal-delay-' . $i % 3 : '' ?>">
            <div class="event-card-img <?= $event_colors[$i % 3] ?>">
              <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                <rect x="16" y="20" width="28" height="22" rx="3" fill="rgba(255,255,255,0.25)" stroke="rgba(255,255,255,0.8)" stroke-width="1.5"/>
                <path d="M22 20V17a2 2 0 014 0v3M34 20V17a2 2 0 014 0v3" stroke="rgba(255,255,255,0.8)" stroke-width="1.5" stroke-linecap="round"/>
                <path d="M22 30h16M22 34h10" stroke="rgba(255,255,255,0.6)" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              <div class="event-date-badge">
                <div class="day"><?= date('d', strtotime($ev['event_date'])) ?></div>
                <div class="month"><?= date('M', strtotime($ev['event_date'])) ?></div>
              </div>
            </div>
            <div class="event-card-body">
              <span class="event-tag"><?= e($ev['category'] ?? 'General') ?></span>
              <h3><?= e($ev['title']) ?></h3>
              <?php if ($ev['description']): ?><p><?= e($ev['description']) ?></p><?php endif; ?>
              <div class="event-meta">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-soft)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= e(trim(($ev['location'] ?? '') . ($ev['event_time'] ? ', ' . $ev['event_time'] : ''))) ?>
              </div>
              <a href="/Rotaract_Kwanza/rsvp.php?id=<?= $ev['id'] ?>"
                style="display:inline-block;margin-top:14px;padding:9px 20px;background:linear-gradient(135deg,var(--pink-600),var(--pink-800));color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;transition:opacity .2s"
                onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">RSVP &rarr;</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align:center;padding:80px 20px;color:var(--text-soft)">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="opacity:.3;margin-bottom:16px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <p style="font-size:1.2rem;font-weight:600">No upcoming events</p>
        <p style="margin-top:8px">Check back soon for new events from the admin dashboard.</p>
      </div>
    <?php endif; ?>

    <?php if ($past): ?>
      <div style="margin-top:60px">
        <h3 class="section-title" style="font-size:1.6rem;margin-bottom:24px">Past <em>Events</em></h3>
        <div class="events-grid">
          <?php foreach ($past as $i => $ev): ?>
            <div class="event-card reveal" style="opacity:.75">
              <div class="event-card-img" style="filter:grayscale(30%)">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                  <rect x="16" y="20" width="28" height="22" rx="3" fill="rgba(255,255,255,0.2)" stroke="rgba(255,255,255,0.6)" stroke-width="1.5"/>
                  <path d="M22 30h16M22 34h10" stroke="rgba(255,255,255,0.4)" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <div class="event-date-badge">
                  <div class="day"><?= date('d', strtotime($ev['event_date'])) ?></div>
                  <div class="month"><?= date('M', strtotime($ev['event_date'])) ?></div>
                </div>
              </div>
              <div class="event-card-body">
                <span class="event-tag"><?= e($ev['category'] ?? 'General') ?></span>
                <h3><?= e($ev['title']) ?></h3>
                <?php if ($ev['description']): ?><p><?= e($ev['description']) ?></p><?php endif; ?>
                <?php if ($ev['location']): ?>
                  <div class="event-meta">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-soft)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?= e($ev['location']) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="/Rotaract_Kwanza/assets/js/scripts.js"></script>
</body>
</html>
