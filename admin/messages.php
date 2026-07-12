<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/ContactMessage.php';

$page_title = 'Messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
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
            flash('success', 'Marked as ' . $status . '.');
        }
    }

    if ($action === 'notes') {
        $cm->markReplied($id, trim($_POST['admin_notes']));
        flash('success', 'Notes saved and message marked as replied.');
    }

    if ($action === 'bulk_status') {
        $status = $_POST['bulk_status'] ?? '';
        $ids    = array_map('intval', $_POST['ids'] ?? []);
        if (in_array($status, ['unread', 'read', 'replied'], true) && $ids) {
            $count = 0;
            foreach ($ids as $mid) {
                $cm->updateStatus($mid, $status);
                $count++;
            }
            log_activity('bulk_update_message_status', "Bulk set $count message(s) to $status");
            flash('success', "$count message(s) marked as $status.");
        }
    }

    if ($action === 'bulk_delete') {
        $ids   = array_map('intval', $_POST['ids'] ?? []);
        $count = 0;
        foreach ($ids as $mid) {
            $cm->delete($mid);
            $count++;
        }
        log_activity('bulk_delete_message', "Bulk deleted $count message(s)");
        flash('success', "$count message(s) deleted.");
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

<div class="split-layout" style="display:grid;grid-template-columns:<?= $view_msg ? '1fr 1.2fr' : '1fr' ?>;gap:20px;align-items:start">

  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:8px">
      <span class="card-title">Inbox</span>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="?" class="btn btn-sm <?= !$filter ? 'btn-primary':'btn-secondary' ?>">All</a>
        <a href="?status=unread"  class="btn btn-sm <?= $filter==='unread'  ? 'btn-primary':'btn-secondary' ?>">Unread</a>
        <a href="?status=read"    class="btn btn-sm <?= $filter==='read'    ? 'btn-primary':'btn-secondary' ?>">Read</a>
        <a href="?status=replied" class="btn btn-sm <?= $filter==='replied' ? 'btn-primary':'btn-secondary' ?>">Replied</a>
        <a href="export_messages.php?status=<?= urlencode($filter) ?>" class="btn btn-sm btn-secondary">Export CSV</a>
      </div>
    </div>

    <?php if (has_role('editor')): ?>
    <div class="card-header" id="bulk-bar" style="display:none;background:#fef6f0">
      <span id="bulk-count" class="text-muted" style="font-size:13px;font-weight:600"></span>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn btn-sm btn-secondary" onclick="submitMsgBulk('bulk_status','read')">Mark Read</button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="submitMsgBulk('bulk_status','unread')">Mark Unread</button>
        <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeleteMessages()">Delete Selected</button>
      </div>
    </div>
    <form id="bulk-form" method="POST" style="display:none">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" id="bulk-action-field" value="">
      <input type="hidden" name="bulk_status" id="bulk-status-field" value="">
      <div id="bulk-ids-container"></div>
    </form>
    <?php endif; ?>

    <div class="table-wrap">
      <table id="dt-messages">
        <thead>
          <tr>
            <?php if (has_role('editor')): ?><th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th><?php endif; ?>
            <th>From</th><th>Subject</th><th>Status</th><th>Date</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($messages): foreach ($messages as $msg): ?>
          <tr style="<?= ($view_msg && $view_msg['id'] == $msg['id']) ? 'background:#f0f2ff' : '' ?>">
            <?php if (has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $msg['id'] ?>" onchange="updateMsgBulkBar()"></td><?php endif; ?>
            <td>
              <div class="fw-bold" style="white-space:nowrap"><?= h($msg['full_name']) ?></div>
              <div class="text-muted" style="font-size:11.5px"><?= h($msg['email']) ?></div>
            </td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= h($msg['subject'] ?? '(no subject)') ?>
            </td>
            <td><span class="badge badge-<?= h($msg['status']) ?>"><?= h($msg['status']) ?></span></td>
            <td class="text-muted" style="white-space:nowrap"><?= $msg['created_at'] ? date('d M Y', strtotime($msg['created_at'])) : '—' ?></td>
            <td>
              <div class="table-actions">
                <a href="?view=<?= $msg['id'] ?>" class="btn btn-icon btn-sm btn-info" title="View" aria-label="View"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                <?php if (has_role('editor')): ?>
                <form id="del-msg-<?= $msg['id'] ?>" method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                </form>
                <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-msg-<?= $msg['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
                <?php endif; ?>
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
        <div><?= $view_msg['created_at'] ? date('d M Y, H:i', strtotime($view_msg['created_at'])) : '—' ?></div>
      </div>
      <div style="margin-bottom:20px">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px">MESSAGE</div>
        <div class="message-full"><?= h($view_msg['message']) ?></div>
      </div>

      <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
        <a href="mailto:<?= h($view_msg['email']) ?>?subject=Re: <?= urlencode($view_msg['subject'] ?? '') ?>" class="btn btn-primary btn-sm">Reply via Email</a>
        <?php if (has_role('editor')): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="id" value="<?= $view_msg['id'] ?>">
          <input type="hidden" name="status" value="<?= $view_msg['status'] === 'unread' ? 'read' : 'unread' ?>">
          <button type="submit" class="btn btn-secondary btn-sm">Mark <?= $view_msg['status'] === 'unread' ? 'Read' : 'Unread' ?></button>
        </form>
        <?php endif; ?>
      </div>

      <?php if (has_role('editor')): ?>
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
      <?php elseif ($view_msg['admin_notes'] ?? ''): ?>
      <div class="form-group">
        <label>Admin Notes</label>
        <div class="text-muted"><?= nl2br(h($view_msg['admin_notes'])) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
$(document).ready(function() {
  $('#dt-messages').DataTable({
    pageLength: 25,
    order: [[<?= has_role('editor') ? 4 : 3 ?>, 'desc']],
    columnDefs: [{ orderable: false, targets: <?= has_role('editor') ? '[0, 5]' : '[4]' ?> }]
  });
});

function getCheckedMsgIds() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) { return c.value; });
}
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(function(c) { c.checked = cb.checked; });
  updateMsgBulkBar();
}
function updateMsgBulkBar() {
  var ids = getCheckedMsgIds();
  document.getElementById('bulk-bar').style.display = ids.length ? 'flex' : 'none';
  document.getElementById('bulk-count').textContent = ids.length + ' selected';
}
function submitMsgBulk(action, status) {
  var ids = getCheckedMsgIds();
  if (!ids.length) return;
  document.getElementById('bulk-action-field').value = action;
  document.getElementById('bulk-status-field').value = status || '';
  var container = document.getElementById('bulk-ids-container');
  container.innerHTML = '';
  ids.forEach(function(id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('bulk-form').submit();
}
function bulkDeleteMessages() {
  var ids = getCheckedMsgIds();
  if (ids.length && confirm('Permanently delete ' + ids.length + ' selected message(s)? This cannot be undone.')) {
    submitMsgBulk('bulk_delete');
  }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
