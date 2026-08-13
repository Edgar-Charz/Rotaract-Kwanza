<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Search';

$q = trim($_GET['q'] ?? '');
$results = strlen($q) >= 2 ? admin_search($conn, $q, 10) : ['sources' => [], 'total' => 0];
$total = $results['total'];

include __DIR__ . '/includes/header.php';
?>

<div class="search-page-row">
  <div class="search-page-field">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
      class="search-page-icon">
      <circle cx="11" cy="11" r="8" />
      <line x1="21" y1="21" x2="16.65" y2="16.65" />
    </svg>
    <input type="text" id="search-page-input" name="q" value="<?= h($q) ?>"
      placeholder="Search anything in the admin panel…" autofocus
      class="search-page-input">
  </div>
</div>
<div id="search-status" class="text-muted search-page-status">
  <?php if ($q !== '' && strlen($q) < 2): ?>
    Enter at least 2 characters.
  <?php elseif ($q !== '' && $total === 0): ?>
    No results found for <strong><?= h($q) ?></strong>.
  <?php elseif ($q !== ''): ?>
    <?= $total ?> result<?= $total !== 1 ? 's' : '' ?> for <strong><?= h($q) ?></strong>
  <?php endif; ?>
</div>

<div id="search-results">
  <?php foreach ($results['sources'] as $src): ?>
    <?php $hasBadges = (bool) array_filter($src['items'], fn($i) => $i['badge'] !== null); ?>
    <div class="card mb-2">
      <div class="card-header">
        <span class="card-title"><?= h($src['label']) ?> (<?= count($src['items']) ?>)</span>
        <a href="<?= h($src['all_href']) ?>" class="btn btn-sm btn-secondary">View All</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Detail</th><?php if ($hasBadges): ?><th>Status</th><?php endif; ?><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($src['items'] as $item): ?>
              <tr>
                <td class="fw-bold"><?= h($item['title'] ?? '') ?></td>
                <td class="text-muted"><?= h($item['subtitle'] ?? '') ?></td>
                <?php if ($hasBadges): ?>
                  <td><?php if ($item['badge'] !== null): ?><span class="badge badge-<?= h(strtolower($item['badge_class'])) ?>"><?= h($item['badge']) ?></span><?php endif; ?></td>
                <?php endif; ?>
                <td><a href="<?= h($item['href']) ?>" class="btn btn-sm btn-secondary">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
</div><!-- #search-results -->

<?php include __DIR__ . '/includes/footer.php'; ?>