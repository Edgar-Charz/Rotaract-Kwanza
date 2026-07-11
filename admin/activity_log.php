<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/ActivityLog.php';

$page_title = 'Activity Log';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    if (($_POST['action'] ?? '') === 'clear') {
        (new ActivityLog($conn))->deleteOlderThanDays((int)($_POST['days'] ?? 30));
        flash('success', 'Old log entries cleared.');
        header('Location: ' . ADMIN_URL . '/activity_log.php');
        exit;
    }
}

$admin_f  = trim($_GET['admin'] ?? '');
$alog     = new ActivityLog($conn);
$logs     = $alog->getPage(10000, 0, $admin_f);
$admins   = $alog->getDistinctAdmins();

$action_colors = [
    'add_'    => 'badge-approved',
    'edit_'   => 'badge-upcoming',
    'delete_' => 'badge-rejected',
    'export_' => 'badge-featured',
    'update_' => 'badge-upcoming',
    'login'   => 'badge-approved',
];

function action_badge(string $action): string {
    global $action_colors;
    foreach ($action_colors as $prefix => $class) {
        if (str_starts_with($action, $prefix)) return $class;
    }
    return 'badge-pending';
}

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header" style="flex-wrap:wrap;gap:10px">
    <form method="GET" class="search-bar" style="flex:1;gap:8px;flex-wrap:wrap">
      <select name="admin" class="filter-select" onchange="this.form.submit()">
        <option value="">All Admins</option>
        <?php foreach ($admins as $a): ?>
        <option value="<?= h($a) ?>" <?= $admin_f===$a?'selected':'' ?>><?= h($a) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($admin_f): ?><a href="?" class="btn btn-sm btn-secondary">Clear</a><?php endif; ?>
    </form>

    <?php if (has_role('editor')): ?>
    <button class="btn btn-danger btn-sm" onclick="openModal('clear-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
      Clear Old Logs
    </button>
    <?php endif; ?>
  </div>

  <div class="table-wrap">
    <table id="dt-activity">
      <thead><tr><th>#</th><th>Admin</th><th>Action</th><th>Description</th><th>IP</th><th>Date &amp; Time</th></tr></thead>
      <tbody>
        <?php if ($logs): foreach ($logs as $log): ?>
        <tr>
          <td class="text-muted"><?= $log['id'] ?></td>
          <td class="fw-bold"><?= h($log['admin_username'] ?? '—') ?></td>
          <td><span class="badge <?= action_badge($log['action']) ?>"><?= h(str_replace('_',' ',$log['action'])) ?></span></td>
          <td><?= h($log['description'] ?? '—') ?></td>
          <td class="text-muted" style="font-size:12px"><?= h($log['ip_address'] ?? '—') ?></td>
          <td class="text-muted"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function() {
  $('#dt-activity').DataTable({
    pageLength: 50,
    order: [[5, 'desc']],
    columnDefs: [{ orderable: false, targets: 0 }]
  });
});
</script>

<!-- Clear Modal -->
<div class="modal fade" id="clear-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:380px">
    <div class="modal-header">
      <span class="modal-title">Clear Old Log Entries</span>
      <button class="modal-close" onclick="closeModal('clear-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="clear">
        <div class="form-group">
          <label>Delete entries older than</label>
          <select name="days">
            <option value="30">30 days</option>
            <option value="60">60 days</option>
            <option value="90">90 days</option>
            <option value="180">6 months</option>
            <option value="365">1 year</option>
          </select>
        </div>
        <p class="text-muted mt-1" style="font-size:12px">This cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('clear-modal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Clear Entries</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
