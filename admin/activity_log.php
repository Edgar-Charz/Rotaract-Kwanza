<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/ActivityLog.php';

$page_title = 'Activity Log';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  // Purging is restricted to super_admin (not just editor) since this is the
  // one page an admin covering their tracks would target, and it can't be
  // undone once the rows are gone.
  require_role('super_admin');
  if (($_POST['action'] ?? '') === 'clear') {
    $days    = (int)($_POST['days'] ?? 30);
    $deleted = (new ActivityLog($conn))->deleteOlderThanDays($days);
    log_activity('clear_activity_log', "Cleared $deleted log entr" . ($deleted === 1 ? 'y' : 'ies') . " older than $days day(s)");
    flash('success', 'Old log entries cleared.');
    header('Location: ' . ADMIN_URL . '/activity_log.php');
    exit;
  }
}

$admin_f  = trim($_GET['admin'] ?? '');
$action_f = trim($_GET['action_filter'] ?? '');
$alog     = new ActivityLog($conn);
$display_cap  = 10000;
$total_count  = $alog->count($admin_f, $action_f);
$logs         = $alog->getPage($display_cap, 0, $admin_f, $action_f);
$truncated    = $total_count > count($logs);
$admins   = $alog->getDistinctAdmins();
$actions  = $alog->getDistinctActions();

$action_colors = [
  'add_'          => 'badge-approved',
  'edit_'         => 'badge-upcoming',
  'delete_'       => 'badge-rejected',
  'export_'       => 'badge-featured',
  'update_'       => 'badge-upcoming',
  'login_failed'  => 'badge-rejected',
  'login'         => 'badge-approved',
  'logout'        => 'badge-pending',
];

function action_badge(string $action): string
{
  global $action_colors;
  foreach ($action_colors as $prefix => $class) {
    if (str_starts_with($action, $prefix)) return $class;
  }
  return 'badge-pending';
}

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header gap-10px">
    <form method="GET" class="search-bar flex-grow-1 flex-wrap">
      <select name="admin" class="filter-select" onchange="this.form.submit()">
        <option value="">All Admins</option>
        <?php foreach ($admins as $a): ?>
          <option value="<?= h($a) ?>" <?= $admin_f === $a ? 'selected' : '' ?>><?= h($a) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="action_filter" class="filter-select" onchange="this.form.submit()">
        <option value="">All Actions</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= h($a) ?>" <?= $action_f === $a ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $a)) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($admin_f || $action_f): ?><a href="?" class="btn btn-sm btn-secondary">Clear Filters</a><?php endif; ?>
    </form>

    <div class="flex gap-8px">
      <a href="export_activity_log.php?admin=<?= urlencode($admin_f) ?>&action_filter=<?= urlencode($action_f) ?>" class="btn btn-sm btn-secondary">Export CSV</a>
      <?php if (has_role('super_admin')): ?>
        <button class="btn btn-danger btn-sm" onclick="openModal('clear-modal')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-14">
            <polyline points="3 6 5 6 21 6" />
            <path d="M19 6l-1 14H6L5 6" />
            <path d="M10 11v6M14 11v6" />
            <path d="M9 6V4h6v2" />
          </svg>
          Clear Old Logs
        </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($truncated): ?>
    <div class="alert alert-error alert-tight">
      Showing the most recent <?= number_format(count($logs)) ?> of <?= number_format($total_count) ?> matching entries.
      The CSV export is not affected by this limit and will include all matching rows.
    </div>
  <?php endif; ?>

  <div class="table-wrap">
    <table id="dt-activity">
      <thead>
        <tr>
          <th>#</th>
          <th>Admin</th>
          <th>Action</th>
          <th>Description</th>
          <th>IP</th>
          <th>Date &amp; Time</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($logs): foreach ($logs as $log): ?>
            <tr>
              <td class="text-muted"><?= $log['id'] ?></td>
              <td class="fw-bold"><?= h($log['admin_username'] ?? '—') ?></td>
              <td><span class="badge <?= action_badge($log['action']) ?>"><?= h(str_replace('_', ' ', $log['action'])) ?></span></td>
              <td><?= h($log['description'] ?? '—') ?></td>
              <td class="text-muted fs-12"><?= h($log['ip_address'] ?? '—') ?></td>
              <td class="text-muted"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></td>
            </tr>
        <?php endforeach;
        endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  $(document).ready(function() {
    $('#dt-activity').DataTable({
      pageLength: 50,
      order: [
        [5, 'desc']
      ],
      columnDefs: [{
        orderable: false,
        targets: 0
      }]
    });
  });
</script>

<?php if (has_role('super_admin')): ?>
  <!-- Clear Modal -->
  <div class="modal fade" id="clear-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-content modal-xs">
      <div class="modal-header">
        <span class="modal-title">Clear Old Log Entries</span>
        <button class="modal-close" onclick="closeModal('clear-modal')">&times;</button>
      </div>
      <form method="POST" id="clear-log-form">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="clear">
          <div class="form-group">
            <label>Delete entries older than</label>
            <select name="days" id="clear-days" onchange="document.getElementById('export-first-btn').dataset.exported='';document.getElementById('clear-submit-btn').disabled=true;">
              <option value="30">30 days</option>
              <option value="60">60 days</option>
              <option value="90">90 days</option>
              <option value="180">6 months</option>
              <option value="365">1 year</option>
            </select>
          </div>
          <p class="text-muted mt-1 fs-12">This cannot be undone. Export a copy of the entries you're about to delete first.</p>
          <button type="button" id="export-first-btn" class="btn btn-secondary btn-sm btn-block-mt6" onclick="exportBeforeClear()">Export These Entries First</button>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('clear-modal')">Cancel</button>
          <button type="submit" id="clear-submit-btn" class="btn btn-danger" disabled>Clear Entries</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    function exportBeforeClear() {
      var days = document.getElementById('clear-days').value;
      window.open('export_activity_log.php?days=' + encodeURIComponent(days), '_blank');
      document.getElementById('export-first-btn').dataset.exported = '1';
      document.getElementById('clear-submit-btn').disabled = false;
    }
  </script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>