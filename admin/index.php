<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Member.php';
require_once dirname(__DIR__) . '/classes/Event.php';
require_once dirname(__DIR__) . '/classes/Gallery.php';
require_once dirname(__DIR__) . '/classes/Project.php';
require_once dirname(__DIR__) . '/classes/ContactMessage.php';
require_once dirname(__DIR__) . '/classes/MemberDues.php';
require_once dirname(__DIR__) . '/classes/EventRSVP.php';

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
];

$recent_members  = $_member->getRecent(5);
$recent_messages = $_cm->getRecent(5);
$upcoming_events = $_event->getUpcoming(4);

include __DIR__ . '/includes/header.php';
?>

<div class="card mb-2">
  <div class="card-body dash-welcome">
    <div class="dash-welcome-text">
      <div class="card-title" style="margin-bottom:2px">Welcome back, <?= h($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin') ?></div>
      <div class="text-muted" style="font-size:13px">Role: <?= h($_SESSION['admin_role'] ?? 'viewer') ?></div>
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

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon pink" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <div>
      <div class="stat-label">Total Members</div>
      <div class="stat-value"><?= $stats['members'] ?></div>
      <div class="stat-sub"><?= $stats['pending'] ?> pending · <?= $stats['approved'] ?> approved</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon gold" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    </div>
    <div>
      <div class="stat-label">Upcoming Events</div>
      <div class="stat-value"><?= $stats['events'] ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon blue" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    </div>
    <div>
      <div class="stat-label">Gallery Photos</div>
      <div class="stat-value"><?= $stats['gallery'] ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon green" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    </div>
    <div>
      <div class="stat-label">Projects</div>
      <div class="stat-value"><?= $stats['projects'] ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon red" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    </div>
    <div>
      <div class="stat-label">Unread Messages</div>
      <div class="stat-value"><?= $stats['messages'] ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon gold" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    </div>
    <div>
      <div class="stat-label">Dues Collected (<?= $current_year ?>)</div>
      <div class="stat-value">Tsh <?= number_format($stats['dues_paid'], 0) ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon blue" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
    </div>
    <div>
      <div class="stat-label">Total RSVPs</div>
      <div class="stat-value"><?= $stats['rsvps'] ?></div>
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
        <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php if ($recent_members): foreach ($recent_members as $m): ?>
          <tr>
            <td class="fw-bold"><?= h($m['first_name'] . ' ' . $m['last_name']) ?></td>
            <td class="text-muted"><?= h($m['email']) ?></td>
            <td><span class="badge badge-<?= h($m['status']) ?>"><?= h($m['status']) ?></span></td>
            <td class="text-muted"><?= $m['created_at'] ? date('d M Y', strtotime($m['created_at'])) : '—' ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="4" class="text-muted" style="text-align:center;padding:20px">No applications yet.</td></tr>
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
        <thead><tr><th>Event</th><th>Date</th><th>Location</th></tr></thead>
        <tbody>
          <?php if ($upcoming_events): foreach ($upcoming_events as $ev): ?>
          <tr>
            <td class="fw-bold"><?= h($ev['title']) ?></td>
            <td class="text-muted"><?= $ev['event_date'] ? date('d M Y', strtotime($ev['event_date'])) : '—' ?></td>
            <td class="text-muted"><?= h($ev['location'] ?? '—') ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="3" class="text-muted" style="text-align:center;padding:20px">No upcoming events.</td></tr>
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
      <thead><tr><th>From</th><th>Subject</th><th>Preview</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php if ($recent_messages): foreach ($recent_messages as $msg): ?>
        <tr>
          <td class="fw-bold"><?= h($msg['full_name']) ?></td>
          <td><?= h($msg['subject'] ?? '—') ?></td>
          <td class="text-muted message-preview"><?= h(mb_strimwidth($msg['message'], 0, 80, '…')) ?></td>
          <td><span class="badge badge-<?= h($msg['status']) ?>"><?= h($msg['status']) ?></span></td>
          <td class="text-muted"><?= $msg['created_at'] ? date('d M Y', strtotime($msg['created_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" class="text-muted" style="text-align:center;padding:20px">No messages yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
