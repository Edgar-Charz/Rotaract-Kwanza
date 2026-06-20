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