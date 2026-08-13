<div class="main-wrapper">
  <header class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="6" x2="21" y2="6" />
        <line x1="3" y1="12" x2="21" y2="12" />
        <line x1="3" y1="18" x2="21" y2="18" />
      </svg>
    </button>
    <div class="topbar-title-wrap">
      <?php if ($__active_group ?? null): ?>
        <div class="topbar-breadcrumb"><?= h($__nav_group_labels[$__active_group] ?? '') ?></div>
      <?php endif; ?>
      <h1 class="topbar-title"><?= h($page_title ?? 'Dashboard') ?></h1>
    </div>

    <form method="GET" action="search.php" class="topbar-search" id="topbar-search-form" role="search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="search-icon">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" name="q" id="topbar-search-input" placeholder="Search…"
             value="<?= isset($_GET['q']) && basename($_SERVER['PHP_SELF']) === 'search.php' ? h($_GET['q']) : '' ?>"
             autocomplete="off" aria-label="Search admin">
      <div id="search-dropdown" class="search-dropdown" role="listbox" aria-live="polite"></div>
    </form>

    <div class="topbar-right">
      <div class="notif-wrap">
        <button type="button" id="notif-bell" class="notif-bell" aria-label="Notifications" aria-expanded="false" title="Notifications">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <?php if (($__badge_total ?? 0) > 0): ?><span class="notif-badge"><?= $__badge_total > 99 ? '99+' : $__badge_total ?></span><?php endif; ?>
        </button>
        <div id="notif-dropdown" class="notif-dropdown" role="menu">
          <div class="notif-dropdown-title">Needs attention</div>
          <?php if (($__badge_total ?? 0) === 0): ?>
            <div class="notif-empty">You're all caught up.</div>
          <?php else: ?>
            <?php if ($__badge_messages > 0): ?>
              <a href="messages.php" class="notif-item">
                <span>Unread messages</span>
                <span class="notif-item-count"><?= $__badge_messages ?></span>
              </a>
            <?php endif; ?>
            <?php if ($__badge_signups > 0): ?>
              <a href="project_signups.php" class="notif-item">
                <span>Sign-ups awaiting contact</span>
                <span class="notif-item-count"><?= $__badge_signups ?></span>
              </a>
            <?php endif; ?>
            <?php if ($__badge_pledges > 0): ?>
              <a href="pledges.php" class="notif-item">
                <span>Pledges awaiting verification</span>
                <span class="notif-item-count"><?= $__badge_pledges ?></span>
              </a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <span class="topbar-date"><?= date('D, d M Y') ?></span>
      <a href="../" target="_blank" class="btn btn-sm btn-secondary" title="View public site">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        <span class="btn-label">View Site</span>
      </a>
      <a href="logout.php" class="btn btn-sm btn-danger" title="Log out">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span class="btn-label">Logout</span>
      </a>
    </div>
  </header>

  <main class="content">
    <?php $flash = get_flash();
    if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>" id="flash-msg">
        <?= h($flash['message']) ?>
        <button onclick="this.parentElement.remove()" class="alert-close">&times;</button>
      </div>
    <?php endif; ?>
