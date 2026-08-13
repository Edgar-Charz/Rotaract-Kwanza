<?php
require_once dirname(__DIR__, 2) . '/classes/ContactMessage.php';
require_once dirname(__DIR__, 2) . '/classes/Pledge.php';

// Which nav group contains the current page — that group stays expanded by
// default regardless of the visitor's remembered collapse state, so you're
// never left wondering which section you're inside.
$__current_page = basename($_SERVER['PHP_SELF']);
$__nav_groups = [
  'overview'       => ['index.php', 'reports.php'],
  'membership'     => ['members.php', 'dues.php'],
  'events'         => ['events.php', 'rsvps.php'],
  'projects'       => ['projects.php', 'project_signups.php', 'pledges.php', 'payment_accounts.php'],
  'team'           => ['team.php', 'roles.php', 'team_photos.php', 'leadership_history.php', 'leadership_term.php', 'leadership_term_photos.php'],
  'content'        => ['gallery.php', 'categories.php', 'values.php', 'perks.php'],
  'communications' => ['messages.php', 'announcements.php', 'newsletter.php'],
  'system'         => ['settings.php', 'activity_log.php', 'backup.php'],
];
$__nav_group_labels = [
  'overview'       => 'Overview',
  'membership'     => 'Membership',
  'events'         => 'Events',
  'projects'       => 'Projects & Fundraising',
  'team'           => 'Team & Leadership',
  'content'        => 'Site Content',
  'communications' => 'Communications',
  'system'         => 'System',
];
$__active_group = null;
foreach ($__nav_groups as $__slug => $__pages) {
  if (in_array($__current_page, $__pages, true)) {
    $__active_group = $__slug;
    break;
  }
}
function nav_group_class($slug)
{
  global $__active_group;
  return $slug === $__active_group ? ' active-group' : '';
}

// Items awaiting admin action — surfaced as badges on their nav-items and
// summed for the topbar notification bell (see topbar.php).
$__badge_messages = 0;
$__badge_signups  = 0;
$__badge_pledges  = 0;
try {
  $__badge_messages = (new ContactMessage($conn))->count('unread');
} catch (Throwable $e) {
}
try {
  $__stmt = $conn->prepare('SELECT COUNT(*) FROM project_signups WHERE contacted = 0');
  $__stmt->execute();
  $__stmt->bind_result($__badge_signups);
  $__stmt->fetch();
  $__stmt->close();
} catch (Throwable $e) {
}
try {
  $__badge_pledges = (new Pledge($conn))->count('unverified');
} catch (Throwable $e) {
}
$__badge_total = $__badge_messages + $__badge_signups + $__badge_pledges;
?>
<aside class="sidebar" id="sidebar">
  <script>
    // Restore the icon-rail preference before first paint (same reasoning as
    // the accordion-state script further down) so the sidebar doesn't render
    // full-width and then jump narrow a moment later.
    (function() {
      // Below the drawer breakpoint (must match sidebar.css/sidebar.js) rail
      // mode doesn't apply — the sidebar is already an off-canvas overlay,
      // so a rail preference remembered from an earlier desktop session
      // shouldn't collapse it to icons on a phone.
      if (window.innerWidth < 901) return;
      try {
        if (localStorage.getItem('sidebarRail') === '1') {
          document.getElementById('sidebar').classList.add('rail');
        }
      } catch (e) {}
    })();
  </script>

  <div class="sidebar-brand">
    <div class="brand-logo">RK</div>
    <div class="brand-text">
      <span class="brand-name">Rotaract Kwanza</span>
      <span class="brand-sub">Admin Panel</span>
    </div>
  </div>

  <button type="button" class="sidebar-rail-toggle" onclick="toggleSidebarRail()" aria-label="Collapse sidebar" title="Collapse sidebar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polyline points="15 18 9 12 15 6" />
    </svg>
  </button>

  <nav class="sidebar-nav">
    <a href="search.php" class="nav-item <?= active_nav('search.php') ?>" title="Search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8" />
        <line x1="21" y1="21" x2="16.65" y2="16.65" />
      </svg>
      Search
    </a>

    <div class="nav-section<?= nav_group_class('overview') ?>" data-group="overview">
      <button type="button" class="nav-section-toggle" onclick="toggleNavGroup(this)" aria-expanded="true" title="Overview">
        <svg class="nav-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7" />
          <rect x="14" y="3" width="7" height="7" />
          <rect x="3" y="14" width="7" height="7" />
          <rect x="14" y="14" width="7" height="7" />
        </svg>
        <span class="nav-section-label">Overview</span>
        <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
      <div class="nav-section-body">
        <a href="index.php" class="nav-item <?= active_nav('index.php') ?>" title="Dashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" />
            <rect x="14" y="3" width="7" height="7" />
            <rect x="3" y="14" width="7" height="7" />
            <rect x="14" y="14" width="7" height="7" />
          </svg>
          Dashboard
        </a>
        <a href="reports.php" class="nav-item <?= active_nav('reports.php') ?>" title="Reports & Analytics">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="20" x2="18" y2="10" />
            <line x1="12" y1="20" x2="12" y2="4" />
            <line x1="6" y1="20" x2="6" y2="14" />
            <line x1="2" y1="20" x2="22" y2="20" />
          </svg>
          Reports &amp; Analytics
        </a>
      </div>
    </div>

    <div class="nav-section<?= nav_group_class('membership') ?>" data-group="membership">
      <button type="button" class="nav-section-toggle" onclick="toggleNavGroup(this)" aria-expanded="true" title="Membership">
        <svg class="nav-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        <span class="nav-section-label">Membership</span>
        <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
      <div class="nav-section-body">
        <a href="members.php" class="nav-item <?= active_nav('members.php') ?>" title="Members">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          Members
        </a>
        <a href="dues.php" class="nav-item <?= active_nav('dues.php') ?>" title="Dues">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="1" x2="12" y2="23" />
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
          </svg>
          Dues
        </a>
      </div>
    </div>

    <div class="nav-section<?= nav_group_class('events') ?>" data-group="events">
      <button type="button" class="nav-section-toggle" onclick="toggleNavGroup(this)" aria-expanded="true" title="Events">
        <svg class="nav-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        <span class="nav-section-label">Events</span>
        <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
      <div class="nav-section-body">
        <a href="events.php" class="nav-item <?= active_nav('events.php') ?>" title="Events">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
          Events
        </a>
        <a href="rsvps.php" class="nav-item <?= active_nav('rsvps.php') ?>" title="RSVPs">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <polyline points="16 11 18 13 22 9" />
          </svg>
          RSVPs
        </a>
      </div>
    </div>

    <div class="nav-section<?= nav_group_class('projects') ?>" data-group="projects">
      <button type="button" class="nav-section-toggle" onclick="toggleNavGroup(this)" aria-expanded="true" title="Projects & Fundraising">
        <svg class="nav-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
          <polyline points="22 4 12 14.01 9 11.01" />
        </svg>
        <span class="nav-section-label">Projects &amp; Fundraising</span>
        <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
      <div class="nav-section-body">
        <a href="projects.php" class="nav-item <?= active_nav('projects.php') ?>" title="Projects">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          Projects
        </a>
        <a href="project_signups.php" class="nav-item <?= active_nav('project_signups.php') ?>" title="Get Involved Sign-Ups">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          Get Involved Sign-Ups
          <?php if ($__badge_signups > 0): ?><span class="badge-count"><?= $__badge_signups ?></span><?php endif; ?>
        </a>
        <a href="pledges.php" class="nav-item <?= active_nav('pledges.php') ?>" title="Pledges">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0L12 5.34l-.77-.76a5.4 5.4 0 0 0-7.65 7.65L12 21l8.42-8.77a5.4 5.4 0 0 0 0-7.65z" />
          </svg>
          Pledges
          <?php if ($__badge_pledges > 0): ?><span class="badge-count"><?= $__badge_pledges ?></span><?php endif; ?>
        </a>
        <a href="payment_accounts.php" class="nav-item <?= active_nav('payment_accounts.php') ?>" title="Payment Accounts">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="7" width="20" height="14" rx="2" />
            <path d="M16 21V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v16" />
          </svg>
          Payment Accounts
        </a>
      </div>
    </div>

    <div class="nav-section<?= nav_group_class('team') ?>" data-group="team">
      <button type="button" class="nav-section-toggle" onclick="toggleNavGroup(this)" aria-expanded="true" title="Team & Leadership">
        <svg class="nav-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="8" r="4" />
          <path d="M6 20v-2a6 6 0 0 1 12 0v2" />
        </svg>
        <span class="nav-section-label">Team &amp; Leadership</span>
        <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
      <div class="nav-section-body">
        <a href="team.php" class="nav-item <?= active_nav('team.php') ?>" title="Team">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="8" r="4" />
            <path d="M6 20v-2a6 6 0 0 1 12 0v2" />
          </svg>
          Team
        </a>
        <a href="roles.php" class="nav-item <?= active_nav('roles.php') ?>" title="Team Roles">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
          </svg>
          Team Roles
        </a>
        <a href="team_photos.php" class="nav-item <?= active_nav('team_photos.php') ?>" title="Team Photos">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <polyline points="21 15 16 10 5 21" />
          </svg>
          Team Photos
        </a>
        <a href="leadership_history.php" class="nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['leadership_history.php', 'leadership_term.php', 'leadership_term_photos.php'], true) ? 'active' : '' ?>" title="Leadership History">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 3L2 9l10 6 10-6-10-6z" />
            <path d="M2 15l10 6 10-6" />
            <path d="M2 12l10 6 10-6" />
          </svg>
          Leadership History
        </a>
      </div>
    </div>

    <div class="nav-section<?= nav_group_class('content') ?>" data-group="content">
      <button type="button" class="nav-section-toggle" onclick="toggleNavGroup(this)" aria-expanded="true" title="Site Content">
        <svg class="nav-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="18" height="18" rx="2" />
          <circle cx="8.5" cy="8.5" r="1.5" />
          <polyline points="21 15 16 10 5 21" />
        </svg>
        <span class="nav-section-label">Site Content</span>
        <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
      <div class="nav-section-body">
        <a href="gallery.php" class="nav-item <?= active_nav('gallery.php') ?>" title="Gallery">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <polyline points="21 15 16 10 5 21" />
          </svg>
          Gallery
        </a>
        <a href="categories.php" class="nav-item <?= active_nav('categories.php') ?>" title="Categories">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3H4a1 1 0 0 0-1 1v5.59a2 2 0 0 0 .59 1.41L13.17 20.6a2 2 0 0 0 2.83 0l4.59-4.59a2 2 0 0 0 0-2.83z" />
            <circle cx="7.5" cy="7.5" r="1.5" />
          </svg>
          Categories
        </a>
        <a href="values.php" class="nav-item <?= active_nav('values.php') ?>" title="Club Values">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z" />
          </svg>
          Club Values
        </a>
        <a href="perks.php" class="nav-item <?= active_nav('perks.php') ?>" title="Membership Perks">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="8" r="6" />
            <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
          </svg>
          Membership Perks
        </a>
      </div>
    </div>

    <div class="nav-section<?= nav_group_class('communications') ?>" data-group="communications">
      <button type="button" class="nav-section-toggle" onclick="toggleNavGroup(this)" aria-expanded="true" title="Communications">
        <svg class="nav-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
        <span class="nav-section-label">Communications</span>
        <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
      <div class="nav-section-body">
        <a href="messages.php" class="nav-item <?= active_nav('messages.php') ?>" title="Messages">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
          Messages
          <?php if ($__badge_messages > 0): ?><span class="badge-count"><?= $__badge_messages ?></span><?php endif; ?>
        </a>
        <a href="announcements.php" class="nav-item <?= active_nav('announcements.php') ?>" title="Announcements">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
          </svg>
          Announcements
        </a>
        <a href="newsletter.php" class="nav-item <?= active_nav('newsletter.php') ?>" title="Newsletter Subscribers">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
            <polyline points="22,6 12,13 2,6" />
          </svg>
          Newsletter Subscribers
        </a>
      </div>
    </div>

    <div class="nav-section<?= nav_group_class('system') ?>" data-group="system">
      <button type="button" class="nav-section-toggle" onclick="toggleNavGroup(this)" aria-expanded="true" title="System">
        <svg class="nav-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="3" />
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
        </svg>
        <span class="nav-section-label">System</span>
        <svg class="nav-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
      <div class="nav-section-body">
        <a href="settings.php" class="nav-item <?= active_nav('settings.php') ?>" title="Settings">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
          </svg>
          Settings
        </a>
        <a href="activity_log.php" class="nav-item <?= active_nav('activity_log.php') ?>" title="Activity Log">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
          </svg>
          Activity Log
        </a>
        <?php if (has_role('super_admin')): ?>
          <a href="backup.php" class="nav-item <?= active_nav('backup.php') ?>" title="DB Backup">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="8 17 12 21 16 17" />
              <line x1="12" y1="12" x2="12" y2="21" />
              <path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29" />
            </svg>
            DB Backup
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="nav-divider"></div>
    <a href="../" target="_blank" class="nav-item" title="View Site">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
        <polyline points="15 3 21 3 21 9" />
        <line x1="10" y1="14" x2="21" y2="3" />
      </svg>
      View Site
    </a>
  </nav>

  <script>
    // Runs synchronously during initial parse (before admin.js loads at the
    // end of body) so groups collapse before first paint — no flash of an
    // all-expanded sidebar snapping shut a moment later. Only the group
    // holding the current page stays open; every other group starts
    // collapsed on every navigation, so moving to a different section's
    // page doesn't leave a stale group expanded behind you.
    (function() {
      document.querySelectorAll('#sidebar .nav-section[data-group]').forEach(function(section) {
        if (section.classList.contains('active-group')) return;
        section.classList.add('collapsed');
        var btn = section.querySelector('.nav-section-toggle');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      });
    })();
  </script>

  <div class="sidebar-footer">
    <a href="settings.php#admin-account" class="admin-info" title="View profile">
      <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?></div>
      <div class="admin-meta">
        <div class="admin-name"><?= h($_SESSION['admin_username'] ?? 'Admin') ?></div>
        <span class="admin-role-hint">View profile</span>
      </div>
    </a>
    <a href="logout.php" class="footer-logout" title="Logout">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
        <polyline points="16 17 21 12 16 7" />
        <line x1="21" y1="12" x2="9" y2="12" />
      </svg>
      <span class="footer-logout-label">Logout</span>
    </a>
  </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>