<?php require_once dirname(__DIR__, 2) . '/classes/ContactMessage.php'; ?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">RK</div>
    <div class="brand-text">
      <span class="brand-name">Rotaract Kwanza</span>
      <span class="brand-sub">Admin Panel</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="<?= ADMIN_URL ?>/" class="nav-item <?= active_nav('index.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="<?= ADMIN_URL ?>/members.php" class="nav-item <?= active_nav('members.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Members
    </a>
    <a href="<?= ADMIN_URL ?>/events.php" class="nav-item <?= active_nav('events.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Events
    </a>
    <a href="<?= ADMIN_URL ?>/rsvps.php" class="nav-item <?= active_nav('rsvps.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
      RSVPs
    </a>
    <a href="<?= ADMIN_URL ?>/gallery.php" class="nav-item <?= active_nav('gallery.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      Gallery
    </a>
    <a href="<?= ADMIN_URL ?>/projects.php" class="nav-item <?= active_nav('projects.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      Projects
    </a>
    <a href="<?= ADMIN_URL ?>/team.php" class="nav-item <?= active_nav('team.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/></svg>
      Team
    </a>
    <a href="<?= ADMIN_URL ?>/messages.php" class="nav-item <?= active_nav('messages.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Messages
      <?php
        try {
            $u = (new ContactMessage($conn))->count('unread');
            if ($u > 0) echo '<span class="badge-count">' . $u . '</span>';
        } catch (Throwable $e) {}
      ?>
    </a>
    <a href="<?= ADMIN_URL ?>/settings.php" class="nav-item <?= active_nav('settings.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      Settings
    </a>
    <a href="<?= ADMIN_URL ?>/announcements.php" class="nav-item <?= active_nav('announcements.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Announcements
    </a>
    <a href="<?= ADMIN_URL ?>/dues.php" class="nav-item <?= active_nav('dues.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      Dues
    </a>
    <div class="nav-divider"></div>
    <a href="<?= ADMIN_URL ?>/activity_log.php" class="nav-item <?= active_nav('activity_log.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Activity Log
    </a>
    <a href="<?= ADMIN_URL ?>/backup.php" class="nav-item <?= active_nav('backup.php') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
      DB Backup
    </a>
    <div class="nav-divider"></div>
    <a href="/Rotaract_Kwanza/" target="_blank" class="nav-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      View Site
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="admin-info">
      <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?></div>
      <div>
        <div class="admin-name"><?= h($_SESSION['admin_username'] ?? 'Admin') ?></div>
        <a href="<?= ADMIN_URL ?>/logout.php" class="logout-link">Logout</a>
      </div>
    </div>
  </div>
</aside>
