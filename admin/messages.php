<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/ContactMessage.php';

$page_title = 'Messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $cm     = new ContactMessage($conn);

    if ($action === 'delete') {
        $cm->delete($id);
        log_activity('delete_message', "Deleted contact message ID $id");
        flash('success', 'Message deleted.');
    }

    if ($action === 'status') {
        $status = $_POST['status'] ?? 'read';
        if (in_array($status, ['unread','read','replied'])) {
            $cm->updateStatus($id, $status);
        }
    }

    if ($action === 'notes') {
        $cm->markReplied($id, trim($_POST['admin_notes']));
        flash('success', 'Notes saved and message marked as replied.');
    }

    $view_param = isset($_GET['view']) ? '?view=' . (int)$_GET['view'] : '';
    header("Location: " . ADMIN_URL . "/messages.php$view_param");
    exit;
}

$cm = new ContactMessage($conn);

$filter   = $_GET['status'] ?? '';
$messages = $cm->getAll(
    ($filter && in_array($filter, ['unread','read','replied'])) ? $filter : ''
);

$view_msg = null;
if (isset($_GET['view'])) {
    $view_id  = (int)$_GET['view'];
    $view_msg = $cm->findById($view_id) ?: null;
    if ($view_msg && $view_msg['status'] === 'unread') {
        $cm->updateStatus($view_id, 'read');
        $view_msg['status'] = 'read';
    }
}

include __DIR__ . '/includes/header.php';
?>

<div style="display:grid;grid-template-columns:<?= $view_msg ? '1fr 1.2fr' : '1fr' ?>;gap:20px;align-items:start">

  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:8px">
      <span class="card-title">Inbox</span>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="?" class="btn btn-sm <?= !$filter ? 'btn-primary':'btn-secondary' ?>">All</a>
        <a href="?status=unread"  class="btn btn-sm <?= $filter==='unread'  ? 'btn-primary':'btn-secondary' ?>">Unread</a>
        <a href="?status=read"    class="btn btn-sm <?= $filter==='read'    ? 'btn-primary':'btn-secondary' ?>">Read</a>
        <a href="?status=replied" class="btn btn-sm <?= $filter==='replied' ? 'btn-primary':'btn-secondary' ?>">Replied</a>
      </div>
    </div>
    <div class="table-wrap">
      <table id="dt-messages">
        <thead><tr><th>From</th><th>Subject</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php if ($messages): foreach ($messages as $msg): ?>
          <tr style="<?= ($view_msg && $view_msg['id'] == $msg['id']) ? 'background:#f0f2ff' : '' ?>">
            <td>
              <div class="fw-bold" style="white-space:nowrap"><?= h($msg['full_name']) ?></div>
              <div class="text-muted" style="font-size:11.5px"><?= h($msg['email']) ?></div>
            </td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= h($msg['subject'] ?? '(no subject)') ?>
            </td>
            <td><span class="badge badge-<?= h($msg['status']) ?>"><?= h($msg['status']) ?></span></td>
            <td class="text-muted" style="white-space:nowrap"><?= date('d M Y', strtotime($msg['created_at'])) ?></td>
            <td>
              <div class="table-actions">
                <a href="?view=<?= $msg['id'] ?>" class="btn btn-sm btn-info">View</a>
                <form id="del-msg-<?= $msg['id'] ?>" method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                </form>
                <button class="btn btn-sm btn-danger" onclick="confirmDelete('del-msg-<?= $msg['id'] ?>')">Del</button>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($view_msg): ?>
  <div class="card" style="position:sticky;top:80px">
    <div class="card-header">
      <span class="card-title">Message Detail</span>
      <span class="badge badge-<?= h($view_msg['status']) ?>"><?= h($view_msg['status']) ?></span>
    </div>
    <div class="card-body">
      <div style="margin-bottom:16px">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px">FROM</div>
        <div class="fw-bold"><?= h($view_msg['full_name']) ?></div>
        <div><?= h($view_msg['email']) ?></div>
      </div>
      <div style="margin-bottom:16px">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px">SUBJECT</div>
        <div class="fw-bold"><?= h($view_msg['subject'] ?? '(no subject)') ?></div>
      </div>
      <div style="margin-bottom:16px">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px">DATE</div>
        <div><?= date('d M Y, H:i', strtotime($view_msg['created_at'])) ?></div>
      </div>
      <div style="margin-bottom:20px">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px">MESSAGE</div>
        <div class="message-full"><?= h($view_msg['message']) ?></div>
      </div>

      <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
        <a href="mailto:<?= h($view_msg['email']) ?>?subject=Re: <?= urlencode($view_msg['subject'] ?? '') ?>" class="btn btn-primary btn-sm">Reply via Email</a>
        <form method="POST" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="id" value="<?= $view_msg['id'] ?>">
          <input type="hidden" name="status" value="<?= $view_msg['status'] === 'unread' ? 'read' : 'unread' ?>">
          <button type="submit" class="btn btn-secondary btn-sm">Mark <?= $view_msg['status'] === 'unread' ? 'Read' : 'Unread' ?></button>
        </form>
      </div>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="notes">
        <input type="hidden" name="id" value="<?= $view_msg['id'] ?>">
        <div class="form-group">
          <label>Admin Notes (marks as replied)</label>
          <textarea name="admin_notes" style="min-height:80px"><?= h($view_msg['admin_notes'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-success btn-sm">Save Notes</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
$(document).ready(function() {
  $('#dt-messages').DataTable({
    pageLength: 25,
    order: [[3, 'desc']],
    columnDefs: [{ orderable: false, targets: 4 }]
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
