<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Event.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();

$event = new Event($conn);
$search = trim($_GET['q'] ?? '');
$upcoming = $event->getUpcoming(0, $search);
$past = $search === '' ? $event->getPast(6) : [];

// Build calendar-friendly JSON for FullCalendar
$all_events = $event->getAll();
$cal_events = array_map(function ($ev) {
  return [
    'title' => $ev['title'],
    'start' => $ev['event_date'],
    'color' => match ($ev['status']) {
      'upcoming' => '#C0396B',
      'past' => '#b2bec3',
      'cancelled' => '#e74c3c',
      default => '#C0396B',
    },
    'url' => $ev['status'] === 'upcoming' ? 'rsvp.php?id=' . $ev['id'] : 'event.php?id=' . $ev['id'],
    'extendedProps' => [
      'location' => $ev['location'] ?? '',
      'status' => $ev['status'],
    ],
  ];
}, $all_events);

$event_colors = ['', 'gold', 'rose'];

$page_title = site_title($conn, 'Events');
$page_description = 'Upcoming service days, leadership forums, and fellowship celebrations from Rotaract Club of Kwanza.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <section id="events" class="pt-100">
    <div class="container">
      <div class="events-header">
        <div>
          <div class="section-eyebrow reveal">Events &amp; Activities</div>
          <h2 class="section-title reveal reveal-delay-1">Upcoming <em>Events</em></h2>
          <p class="section-lead reveal reveal-delay-2">Discover our next service days, leadership forums, and
            fellowship celebrations.</p>
        </div>
      </div>

      <form method="GET" class="events-search-form reveal reveal-delay-3">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search upcoming events…"
          class="events-search-input" autocomplete="off">
        <button type="submit" class="events-search-btn">Search</button>
      </form>

      <?php if ($search): ?>
        <p class="events-results-count reveal"><?= count($upcoming) ?> result<?= count($upcoming) !== 1 ? 's' : '' ?> for "<?= e($search) ?>" &middot; <a href="events.php" class="link-pink">Clear</a></p>
      <?php endif; ?>

      <!-- View toggle -->
      <div class="view-toggle reveal reveal-delay-4">
        <button class="view-btn active" id="btn-list" onclick="switchView('list')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            class="icon-va">
            <line x1="8" y1="6" x2="21" y2="6" />
            <line x1="8" y1="12" x2="21" y2="12" />
            <line x1="8" y1="18" x2="21" y2="18" />
            <line x1="3" y1="6" x2="3.01" y2="6" />
            <line x1="3" y1="12" x2="3.01" y2="12" />
            <line x1="3" y1="18" x2="3.01" y2="18" />
          </svg>
          List
        </button>
        <button class="view-btn" id="btn-calendar" onclick="switchView('calendar')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            class="icon-va">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
          Calendar
        </button>
      </div>

      <!-- Calendar view -->
      <div id="calendar-wrap" class="reveal">
        <div id="calendar"></div>
      </div>

      <!-- List view -->
      <div id="list-view">
        <?php if ($upcoming): ?>
          <div class="events-grid">
            <?php foreach ($upcoming as $i => $ev): ?>
              <div class="event-card reveal<?= ($i % 3 > 0) ? ' reveal-delay-' . ($i % 3) : '' ?> event-card--clickable" data-href="event.php?id=<?= $ev['id'] ?>" tabindex="0" role="link" aria-label="View details for <?= e($ev['title']) ?>">
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
                    <?= e(trim(($ev['location'] ?? '') . ($ev['event_time'] ? ', ' . $ev['event_time'] : ''))) ?: '—' ?>
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
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="gallery-empty reveal">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            <?php if ($search): ?>
              <p class="gallery-empty-title">No events found for "<?= e($search) ?>"</p>
              <p class="mt-8"><a href="events.php" class="confirm-link">Clear search</a></p>
            <?php else: ?>
              <p class="gallery-empty-title">No upcoming events</p>
              <p class="mt-8">Check back soon for new events.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($past): ?>
          <div class="mt-60">
            <h3 class="section-title section-title--gallery reveal">Past <em>Events</em></h3>
            <div class="events-grid">
              <?php foreach ($past as $i => $ev): ?>
                <div class="event-card reveal<?= ($i % 3 > 0) ? ' reveal-delay-' . ($i % 3) : '' ?> event-card--clickable event-card--past" data-href="event.php?id=<?= $ev['id'] ?>" tabindex="0" role="link" aria-label="View details for <?= e($ev['title']) ?>">
                  <?php if ($ev['image_path'] ?? ''): ?>
                    <div class="event-card-img event-card-img--flush event-card-img--gray">
                      <img src="<?= e(img_url($ev['image_path'])) ?>" alt="<?= e($ev['title']) ?>">
                      <div class="event-date-badge">
                        <div class="day"><?= date('d', strtotime($ev['event_date'])) ?></div>
                        <div class="month"><?= date('M', strtotime($ev['event_date'])) ?></div>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="event-card-img <?= $event_colors[$i % 3] ?> event-card-img--gray">
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
                    <?php if ($ev['location']): ?>
                      <div class="event-meta">
                        <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-soft)" stroke-width="2">
                          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                          <circle cx="12" cy="10" r="3" />
                        </svg>
                        <?= e($ev['location']) ?>
                      </div>
                    <?php endif; ?>
                    <a href="event.php?id=<?= $ev['id'] ?>" class="event-view-link">View Details &rarr;</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div><!-- /list-view -->

    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
  <script src="assets/js/scripts.js"></script>
  <script>
    var calEvents = <?= json_encode(array_values($cal_events)) ?>;
    var calInit = false;
    var calObj = null;

    function switchView(mode) {
      var isList = mode === 'list';
      var listView = document.getElementById('list-view');
      var calendarWrap = document.getElementById('calendar-wrap');
      listView.classList.toggle('hidden', !isList);
      calendarWrap.classList.toggle('active', !isList);
      document.getElementById('btn-list').classList.toggle('active', isList);
      document.getElementById('btn-calendar').classList.toggle('active', !isList);

      if (!isList) {
        calendarWrap.classList.add('visible');
        if (!calInit) {
          calInit = true;
          calObj = new FullCalendar.Calendar(document.getElementById('calendar'), {
            initialView: 'dayGridMonth',
            headerToolbar: {
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,listMonth'
            },
            events: calEvents,
            height: 'auto',
            eventClick: function(info) {
              if (info.event.url && info.event.url !== '#') {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
              }
            },
            eventDidMount: function(info) {
              var loc = info.event.extendedProps.location;
              if (loc) info.el.title = info.event.title + '\n📍 ' + loc;
            }
          });
          calObj.render();
        } else if (calObj) {
          calObj.updateSize();
        }
      }
    }
  </script>
</body>

</html>