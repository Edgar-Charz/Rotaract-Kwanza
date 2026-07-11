<div class="main-wrapper">
  <header class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="6" x2="21" y2="6" />
        <line x1="3" y1="12" x2="21" y2="12" />
        <line x1="3" y1="18" x2="21" y2="18" />
      </svg>
    </button>
    <h1 class="topbar-title"><?= h($page_title ?? 'Dashboard') ?></h1>

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
      <span class="topbar-date"><?= date('D, d M Y') ?></span>
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
