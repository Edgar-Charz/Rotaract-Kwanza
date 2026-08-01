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
  <style>
    /* ── View toggle ─────────────────────────── */
    .view-toggle {
      display: flex;
      gap: 10px;
      margin-bottom: 28px;
    }

    .view-btn {
      padding: 8px 22px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      border: 1.5px solid var(--border);
      background: #fff;
      cursor: pointer;
      color: var(--text);
      font-family: inherit;
      transition: all .2s;
    }

    .view-btn.active,
    .view-btn:hover {
      background: var(--pink-700);
      color: #fff;
      border-color: var(--pink-700);
    }

    /* ── Calendar wrapper ────────────────────── */
    #calendar-wrap {
      background: #fff;
      border-radius: 14px;
      padding: 20px;
      box-shadow: 0 2px 14px rgba(0, 0, 0, .07);
      margin-bottom: 40px;
      display: none;
    }

    #calendar-wrap.active {
      display: block;
    }

    /* FullCalendar overrides */
    .fc .fc-toolbar-title {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.4rem;
    }

    .fc .fc-button-primary {
      background: var(--pink-700);
      border-color: var(--pink-700);
    }

    .fc .fc-button-primary:hover {
      background: var(--pink-800);
      border-color: var(--pink-800);
    }

    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
      background: var(--pink-900);
      border-color: var(--pink-900);
    }

    .fc-event {
      cursor: pointer;
    }

    /* ── List view ───────────────────────────── */
    #list-view {
      display: block;
    }

    #list-view.hidden {
      display: none;
    }
  </style>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <section id="events" style="padding-top:100px">
    <div class="container">
      <div class="events-header">
        <div>
          <div class="section-eyebrow reveal">Events &amp; Activities</div>
          <h2 class="section-title reveal reveal-delay-1">Upcoming <em>Events</em></h2>
          <p class="section-lead reveal reveal-delay-2">Discover our next service days, leadership forums, and
            fellowship celebrations.</p>
        </div>
      </div>

      <form method="GET" style="display:flex;gap:10px;max-width:420px;margin:0 0 24px">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search upcoming events…"
          style="flex:1;padding:11px 16px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;font-family:inherit;outline:none">
        <button type="submit" style="padding:11px 22px;background:var(--pink-700);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit">Search</button>
      </form>

      <?php if ($search): ?>
        <p style="color:var(--text-soft);font-size:13px;margin:-14px 0 24px"><?= count($upcoming) ?> result<?= count($upcoming) !== 1 ? 's' : '' ?> for "<?= e($search) ?>" &middot; <a href="events.php" style="color:var(--pink-700)">Clear</a></p>
      <?php endif; ?>

      <!-- View toggle -->
      <div class="view-toggle">
        <button class="view-btn active" id="btn-list" onclick="switchView('list')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            style="vertical-align:-2px;margin-right:5px">
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
            style="vertical-align:-2px;margin-right:5px">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
          Calendar
        </button>
      </div>

      <!-- Calendar view -->
      <div id="calendar-wrap">
        <div id="calendar"></div>
      </div>

      <!-- List view -->
      <div id="list-view">
        <?php if ($upcoming): ?>
          <div class="events-grid">
            <?php foreach ($upcoming as $i => $ev): ?>
              <div class="event-card reveal<?= $i > 0 ? ' reveal-delay-' . $i % 3 : '' ?>" data-href="event.php?id=<?= $ev['id'] ?>" tabindex="0" role="link" aria-label="View details for <?= e($ev['title']) ?>" style="cursor:pointer">
                <?php if ($ev['image_path'] ?? ''): ?>
                  <div class="event-card-img" style="padding:0;overflow:hidden">
                    <img src="<?= e(img_url($ev['image_path'])) ?>" alt="<?= e($ev['title']) ?>"
                      style="width:100%;height:100%;object-fit:cover">
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
                  <h3><a href="event.php?id=<?= $ev['id'] ?>" style="color:inherit;text-decoration:none"><?= e($ev['title']) ?></a></h3>
                  <?php if ($ev['description']): ?>
                    <p><?= e($ev['description']) ?></p><?php endif; ?>
                  <div class="event-meta">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-soft)" stroke-width="2">
                      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                      <circle cx="12" cy="10" r="3" />
                    </svg>
                    <?= e(trim(($ev['location'] ?? '') . ($ev['event_time'] ? ', ' . $ev['event_time'] : ''))) ?: '—' ?>
                  </div>
                  <div style="display:flex;gap:10px;align-items:center;margin-top:14px">
                    <a href="rsvp.php?id=<?= $ev['id'] ?>"
                      style="display:inline-block;padding:9px 20px;background:linear-gradient(135deg,var(--pink-600),var(--pink-800));color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;transition:opacity .2s"
                      onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">RSVP &rarr;</a>
                    <a href="event.php?id=<?= $ev['id'] ?>" style="font-size:13px;font-weight:700;color:var(--pink-800);text-decoration:none">Details</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="text-align:center;padding:80px 20px;color:var(--text-soft)">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"
              style="opacity:.3;margin-bottom:16px">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            <?php if ($search): ?>
              <p style="font-size:1.2rem;font-weight:600">No events found for "<?= e($search) ?>"</p>
              <p style="margin-top:8px"><a href="events.php" style="color:var(--pink-700);font-weight:600">Clear search</a></p>
            <?php else: ?>
              <p style="font-size:1.2rem;font-weight:600">No upcoming events</p>
              <p style="margin-top:8px">Check back soon for new events.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($past): ?>
          <div style="margin-top:60px">
            <h3 class="section-title" style="font-size:1.6rem;margin-bottom:24px">Past <em>Events</em></h3>
            <div class="events-grid">
              <?php foreach ($past as $i => $ev): ?>
                <div class="event-card reveal" data-href="event.php?id=<?= $ev['id'] ?>" tabindex="0" role="link" aria-label="View details for <?= e($ev['title']) ?>" style="opacity:.75;cursor:pointer">
                  <?php if ($ev['image_path'] ?? ''): ?>
                    <div class="event-card-img" style="padding:0;overflow:hidden;filter:grayscale(30%)">
                      <img src="<?= e(img_url($ev['image_path'])) ?>" alt="<?= e($ev['title']) ?>"
                        style="width:100%;height:100%;object-fit:cover">
                      <div class="event-date-badge">
                        <div class="day"><?= date('d', strtotime($ev['event_date'])) ?></div>
                        <div class="month"><?= date('M', strtotime($ev['event_date'])) ?></div>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="event-card-img <?= $event_colors[$i % 3] ?>" style="filter:grayscale(30%)">
                      <div class="event-icon-badge"><?= icon_svg(event_category_icon($ev['category'] ?? ''), '#fff') ?></div>
                      <div class="event-date-badge">
                        <div class="day"><?= date('d', strtotime($ev['event_date'])) ?></div>
                        <div class="month"><?= date('M', strtotime($ev['event_date'])) ?></div>
                      </div>
                    </div>
                  <?php endif; ?>
                  <div class="event-card-body">
                    <span class="event-tag"><?= e($ev['category'] ?? 'General') ?></span>
                    <h3><a href="event.php?id=<?= $ev['id'] ?>" style="color:inherit;text-decoration:none"><?= e($ev['title']) ?></a></h3>
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
                    <a href="event.php?id=<?= $ev['id'] ?>" style="display:inline-block;margin-top:14px;font-size:13px;font-weight:700;color:var(--pink-800);text-decoration:none">View Details &rarr;</a>
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
      document.getElementById('list-view').classList.toggle('hidden', !isList);
      document.getElementById('calendar-wrap').classList.toggle('active', !isList);
      document.getElementById('btn-list').classList.toggle('active', isList);
      document.getElementById('btn-calendar').classList.toggle('active', !isList);

      if (!isList && !calInit) {
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
      }
    }

    // Whole card is clickable through to the event detail page, but clicks on
    // an actual link inside it (RSVP, Details, title) keep going to their own
    // destination instead of being overridden by the card-wide handler.
    document.querySelectorAll('.event-card[data-href]').forEach(function (card) {
      card.addEventListener('click', function (e) {
        if (e.target.closest('a')) return;
        window.location.href = card.dataset.href;
      });
      card.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.target.closest('a')) window.location.href = card.dataset.href;
      });
    });
  </script>
</body>

</html>