<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/BirthdayManager.php';
require_once dirname(__DIR__) . '/classes/Member.php';
require_once dirname(__DIR__) . '/classes/Event.php';
require_once dirname(__DIR__) . '/classes/Gallery.php';
require_once dirname(__DIR__) . '/classes/Project.php';
require_once dirname(__DIR__) . '/classes/ContactMessage.php';
require_once dirname(__DIR__) . '/classes/MemberDues.php';
require_once dirname(__DIR__) . '/classes/EventRSVP.php';
require_once dirname(__DIR__) . '/classes/ActivityLog.php';

// Process Birthday Wish form submission directly on dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_birthday_email') {
    csrf_verify();
    $id          = (int)($_POST['id'] ?? 0);
    $source_type = trim($_POST['source_type'] ?? 'member');

    if ($id > 0) {
        $bm = new BirthdayManager($conn);
        try {
            $ok = $bm->sendBirthdayWish($id, $source_type);
            if ($ok) {
                flash('success', 'Happy Birthday email sent successfully!');
            } else {
                flash('error', 'Failed to send birthday email. Please check mail settings.');
            }
        } catch (Throwable $e) {
            flash('error', 'Error sending email: ' . $e->getMessage());
        }
    }
    header('Location: ' . ADMIN_URL . '/index.php');
    exit;
}

$page_title = 'Dashboard';

$_member = new Member($conn);
$_event  = new Event($conn);
$_cm     = new ContactMessage($conn);
$_dues   = new MemberDues($conn);
$_rsvp   = new EventRSVP($conn);

$current_year = (int) date('Y');
$dues_totals  = $_dues->getYearTotals($current_year);

$stats = [
  'members'    => $_member->count(),
  'pending'    => $_member->count('pending'),
  'approved'   => $_member->count('approved'),
  'events'     => $_event->countByStatus('upcoming'),
  'gallery'    => (new Gallery($conn))->countActive(),
  'projects'   => (new Project($conn))->count(),
  'messages'   => $_cm->count('unread'),
  'rsvps'      => $_rsvp->count(),
  'dues_paid'  => (float) $dues_totals['total_paid'],
  'dues_due'   => (float) $dues_totals['total_due'],
];
$dues_outstanding = max(0, $stats['dues_due'] - $stats['dues_paid']);

$oldest_pending_at = db_val($conn, "SELECT MIN(created_at) FROM members WHERE status='pending'");
$pending_days       = $oldest_pending_at ? (int) floor((time() - strtotime($oldest_pending_at)) / 86400) : 0;

$members_this_month = monthly_counts($conn, 'members', 1)[0]['value'] ?? 0;
$rsvps_this_month   = monthly_counts($conn, 'event_rsvps', 1)[0]['value'] ?? 0;

$recent_members  = $_member->getRecent(5);
$recent_messages = $_cm->getRecent(5);
$upcoming_events = $_event->getUpcoming(4);
$recent_activity = (new ActivityLog($conn))->getPage(7, 0);

include __DIR__ . '/includes/header.php';
?>

<div class="card mb-2">
  <div class="card-body dash-welcome">
    <div class="dash-welcome-text">
      <div class="card-title mb-2px">Welcome back, <?= h($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin') ?></div>
      <div class="text-muted fs-13">Role: <?= h($_SESSION['admin_role'] ?? 'viewer') ?></div>
    </div>
    <?php if (has_role('editor')): ?>
      <div class="dash-welcome-actions">
        <a href="members.php?new=1" class="btn btn-sm btn-primary">+ Add Member</a>
        <a href="events.php?new=1" class="btn btn-sm btn-secondary">+ Create Event</a>
        <a href="announcements.php?new=1" class="btn btn-sm btn-secondary">+ Post Announcement</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
$birthdayData = check_and_auto_send_birthdays($conn);
$todaysBirthdays   = $birthdayData['today'];
$upcomingBirthdays = $birthdayData['upcoming'];
?>

<?php if (!empty($todaysBirthdays) || !empty($upcomingBirthdays)): ?>
<div class="card mb-2 border-gold" style="background: linear-gradient(135deg, rgba(212,175,55,0.08) 0%, rgba(227,24,55,0.05) 100%); border-left: 4px solid var(--gold, #d4af37);">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <span style="font-size:1.4rem;">🎂</span>
      <div>
        <span class="card-title fw-bold">Birthday &amp; Celebration Center</span>
        <div class="text-muted fs-12">Automated greetings &amp; upcoming birthday alerts for members &amp; leaders</div>
      </div>
    </div>
    <span class="badge badge-approved fs-11">Auto-Check Active</span>
  </div>
  <div class="card-body pt-0">
    <?php if (!empty($todaysBirthdays)): ?>
      <div class="mb-3">
        <div class="fw-bold text-danger mb-2 d-flex align-items-center gap-1">
          <span>🎉</span> <span>Celebrating Today!</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($todaysBirthdays as $b): ?>
            <?php 
              $bName = h(trim($b['first_name'] . ' ' . ($b['last_name'] ?? '')));
              $isSent = !empty($b['last_birthday_email_sent']) && $b['last_birthday_email_sent'] === date('Y-m-d');
            ?>
            <div class="d-flex align-items-center justify-content-between p-2 rounded bg-white border flex-grow-1" style="min-width: 280px; max-width: 450px;">
              <div>
                <div class="fw-bold fs-13"><?= $bName ?></div>
                <div class="text-muted fs-11"><?= h($b['email'] ?: 'No email on file') ?> &middot; <span class="text-capitalize"><?= str_replace('_', ' ', $b['source_type']) ?></span></div>
              </div>
              <div>
                <?php if ($isSent): ?>
                  <span class="badge badge-approved fs-11">Email Sent ✅</span>
                <?php else: ?>
                  <form method="POST" action="index.php" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send_birthday_email">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <input type="hidden" name="source_type" value="<?= h($b['source_type']) ?>">
                    <button type="submit" class="btn btn-sm btn-primary py-1 px-2 fs-11">🎁 Send Wish</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($upcomingBirthdays)): ?>
      <div>
        <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-1">
          <span>⏳</span> <span>Upcoming Birthdays (Next 24–48 Hours)</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($upcomingBirthdays as $ub): ?>
            <?php 
              $ubName = h(trim($ub['first_name'] . ' ' . ($ub['last_name'] ?? '')));
              $bDateFormatted = date('d M', strtotime($ub['birthday']));
              $isUbSent = !empty($ub['last_birthday_email_sent']) && $ub['last_birthday_email_sent'] === date('Y-m-d');
            ?>
            <div class="d-flex align-items-center justify-content-between p-2 rounded bg-white border flex-grow-1" style="min-width: 280px; max-width: 450px;">
              <div>
                <div class="fw-bold fs-13"><?= $ubName ?></div>
                <div class="text-muted fs-11">Birthday: <strong class="text-primary"><?= $bDateFormatted ?></strong> &middot; <?= h($ub['email']) ?></div>
              </div>
              <div>
                <?php if ($isUbSent): ?>
                  <span class="badge badge-approved fs-11">Email Sent ✅</span>
                <?php else: ?>
                  <form method="POST" action="index.php" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send_birthday_email">
                    <input type="hidden" name="id" value="<?= $ub['id'] ?>">
                    <input type="hidden" name="source_type" value="<?= h($ub['source_type']) ?>">
                    <button type="submit" class="btn btn-sm btn-secondary py-1 px-2 fs-11">Send Early Wish</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon pink" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
      </svg>
    </div>
    <div>
      <div class="stat-label">Total Members</div>
      <div class="stat-value"><?= $stats['members'] ?></div>
      <div class="stat-sub">
        <?php if ($stats['pending'] > 0 && $pending_days >= 7): ?>
          <span class="text-danger fw-bold"><?= $stats['pending'] ?> pending</span> (oldest <?= $pending_days ?>d) · <?= $stats['approved'] ?> approved
        <?php else: ?>
          <?= $stats['pending'] ?> pending · <?= $stats['approved'] ?> approved
        <?php endif; ?>
        <?php if ($members_this_month > 0): ?> · <span class="text-success">+<?= $members_this_month ?> this month</span><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon gold" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" />
        <line x1="16" y1="2" x2="16" y2="6" />
        <line x1="8" y1="2" x2="8" y2="6" />
        <line x1="3" y1="10" x2="21" y2="10" />
      </svg>
    </div>
    <div>
      <div class="stat-label">Upcoming Events</div>
      <div class="stat-value"><?= $stats['events'] ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon blue" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="18" height="18" rx="2" />
        <circle cx="8.5" cy="8.5" r="1.5" />
        <polyline points="21 15 16 10 5 21" />
      </svg>
    </div>
    <div>
      <div class="stat-label">Gallery Photos</div>
      <div class="stat-value"><?= $stats['gallery'] ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon green" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
      </svg>
    </div>
    <div>
      <div class="stat-label">Projects</div>
      <div class="stat-value"><?= $stats['projects'] ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon red" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
      </svg>
    </div>
    <div>
      <div class="stat-label">Unread Messages</div>
      <div class="stat-value"><?= $stats['messages'] ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon gold" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="1" x2="12" y2="23" />
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
      </svg>
    </div>
    <div>
      <div class="stat-label">Dues Collected (<?= $current_year ?>)</div>
      <div class="stat-value">Tsh <?= number_format($stats['dues_paid'], 0) ?></div>
      <?php if ($dues_outstanding > 0): ?>
        <div class="stat-sub text-danger">Tsh <?= number_format($dues_outstanding, 0) ?> outstanding</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon blue" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <polyline points="16 11 18 13 22 9" />
      </svg>
    </div>
    <div>
      <div class="stat-label">Total RSVPs</div>
      <div class="stat-value"><?= $stats['rsvps'] ?></div>
      <?php if ($rsvps_this_month > 0): ?>
        <div class="stat-sub"><span class="text-success">+<?= $rsvps_this_month ?> this month</span></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="dash-grid">
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Applications</span>
      <a href="members.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent_members): foreach ($recent_members as $m): ?>
              <tr>
                <td class="fw-bold"><?= h($m['first_name'] . ' ' . $m['last_name']) ?></td>
                <td class="text-muted"><?= h($m['email']) ?></td>
                <td><span class="badge badge-<?= h($m['status']) ?>"><?= h($m['status']) ?></span></td>
                <td class="text-muted"><?= $m['created_at'] ? date('d M Y', strtotime($m['created_at'])) : '—' ?></td>
              </tr>
            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="4" class="text-muted table-empty">No applications yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Upcoming Events</span>
      <a href="events.php" class="btn btn-sm btn-secondary">Manage</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th>Location</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($upcoming_events): foreach ($upcoming_events as $ev): ?>
              <tr>
                <td class="fw-bold"><?= h($ev['title']) ?></td>
                <td class="text-muted"><?= $ev['event_date'] ? date('d M Y', strtotime($ev['event_date'])) : '—' ?></td>
                <td class="text-muted"><?= h($ev['location'] ?? '—') ?></td>
              </tr>
            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="3" class="text-muted table-empty">No upcoming events.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mt-2">
  <div class="card-header">
    <span class="card-title">Recent Messages</span>
    <a href="messages.php" class="btn btn-sm btn-secondary">View All</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>From</th>
          <th>Subject</th>
          <th>Preview</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recent_messages): foreach ($recent_messages as $msg): ?>
            <tr>
              <td class="fw-bold"><?= h($msg['full_name']) ?></td>
              <td><?= h($msg['subject'] ?? '—') ?></td>
              <td class="text-muted message-preview"><?= h(mb_strimwidth($msg['message'], 0, 80, '…')) ?></td>
              <td><span class="badge badge-<?= h($msg['status']) ?>"><?= h($msg['status']) ?></span></td>
              <td class="text-muted"><?= $msg['created_at'] ? date('d M Y', strtotime($msg['created_at'])) : '—' ?></td>
            </tr>
          <?php endforeach;
        else: ?>
          <tr>
            <td colspan="5" class="text-muted table-empty">No messages yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-2">
  <div class="card-header">
    <span class="card-title">Recent Activity</span>
    <a href="activity_log.php" class="btn btn-sm btn-secondary">View All</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Admin</th>
          <th>Action</th>
          <th>Description</th>
          <th>Date &amp; Time</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recent_activity): foreach ($recent_activity as $log): ?>
            <tr>
              <td class="fw-bold"><?= h($log['admin_username'] ?? '—') ?></td>
              <td><span class="badge badge-upcoming"><?= h(str_replace('_', ' ', $log['action'])) ?></span></td>
              <td class="text-muted"><?= h($log['description'] ?? '—') ?></td>
              <td class="text-muted"><?= $log['created_at'] ? date('d M Y H:i', strtotime($log['created_at'])) : '—' ?></td>
            </tr>
          <?php endforeach;
        else: ?>
          <tr>
            <td colspan="4" class="text-muted table-empty">No activity recorded yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>