<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Member.php';

$page_title = 'Members';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $m = new Member($conn);

    if ($action === 'add') {
        try {
            $m->create(
                trim($_POST['first_name']), trim($_POST['last_name']), trim($_POST['email']),
                trim($_POST['phone']), trim($_POST['occupation']), trim($_POST['why_join']),
                $_POST['status'] ?? 'pending', trim($_POST['notes'] ?? '')
            );
            log_activity('add_member', "Added: " . trim($_POST['first_name']) . " " . trim($_POST['last_name']));
            flash('success', 'Member added successfully.');
        } catch (mysqli_sql_exception $e) {
            flash('error', $e->getCode() == 1062 ? 'Email already exists.' : 'Could not add member.');
        }
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        try {
            $m->update(
                $id,
                trim($_POST['first_name']), trim($_POST['last_name']), trim($_POST['email']),
                trim($_POST['phone']), trim($_POST['occupation']), trim($_POST['why_join']),
                $_POST['status'], trim($_POST['notes'] ?? '')
            );
            log_activity('edit_member', "Edited member ID $id: " . trim($_POST['first_name']) . " " . trim($_POST['last_name']));
            flash('success', 'Member updated.');
        } catch (mysqli_sql_exception $e) {
            flash('error', $e->getCode() == 1062 ? 'Email already exists.' : 'Could not update member.');
        }
    }

    if ($action === 'delete') {
        $del_name = $m->getFullName((int)$_POST['id']);
        $m->delete((int)$_POST['id']);
        log_activity('delete_member', "Deleted: $del_name (ID " . (int)$_POST['id'] . ")");
        flash('success', 'Member deleted.');
    }

    if ($action === 'status') {
        $status = $_POST['status'] ?? 'pending';
        if (in_array($status, ['pending','approved','rejected'])) {
            $mem_name = $m->getFullName((int)$_POST['id']);
            $m->updateStatus((int)$_POST['id'], $status);
            log_activity('update_member_status', "Set $mem_name → $status");
            flash('success', 'Status updated to ' . $status . '.');
        }
    }

    header('Location: ' . ADMIN_URL . '/members.php');
    exit;
}

$filter  = $_GET['status'] ?? '';
$members = (new Member($conn))->getAll(
    ($filter && in_array($filter, ['pending','approved','rejected'])) ? $filter : ''
);

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1">
      <form method="GET" style="display:flex;gap:8px;align-items:center">
        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="pending"  <?= $filter==='pending'  ? 'selected':'' ?>>Pending</option>
          <option value="approved" <?= $filter==='approved' ? 'selected':'' ?>>Approved</option>
          <option value="rejected" <?= $filter==='rejected' ? 'selected':'' ?>>Rejected</option>
        </select>
        <?php if ($filter): ?>
        <a href="<?= ADMIN_URL ?>/members.php" class="btn btn-sm btn-secondary">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <a href="<?= ADMIN_URL ?>/export_members.php?status=<?= urlencode($filter) ?>"
       class="btn btn-success">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
      Export CSV
    </a>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Member
    </button>
  </div>

  <div class="table-wrap">
    <table id="dt-members">
      <thead>
        <tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Occupation</th><th>Status</th><th>Applied</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($members): foreach ($members as $m): ?>
        <tr>
          <td class="text-muted"><?= $m['id'] ?></td>
          <td class="fw-bold"><?= h($m['first_name'] . ' ' . $m['last_name']) ?></td>
          <td><?= h($m['email']) ?></td>
          <td><?= h($m['phone'] ?? '—') ?></td>
          <td><?= h($m['occupation'] ?? '—') ?></td>
          <td><span class="badge badge-<?= h($m['status']) ?>"><?= h($m['status']) ?></span></td>
          <td class="text-muted"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-secondary"
                onclick="openViewModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)">View</button>
              <button class="btn btn-sm btn-info"
                onclick="openEditModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)">Edit</button>
              <button class="btn btn-sm btn-success"
                onclick="openStatusModal(<?= $m['id'] ?>, '<?= h($m['first_name'].' '.$m['last_name']) ?>', '<?= h($m['status']) ?>')">Status</button>
              <form id="del-m-<?= $m['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
              </form>
              <button class="btn btn-sm btn-danger" onclick="confirmDelete('del-m-<?= $m['id'] ?>')">Delete</button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function() {
  $('#dt-members').DataTable({
    pageLength: 25,
    columnDefs: [{ orderable: false, targets: 7 }]
  });
});
</script>

<!-- Add Modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add New Member</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
          <div class="form-group"><label>First Name *</label><input type="text" name="first_name" required></div>
          <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
          <div class="form-group"><label>Phone</label><input type="tel" name="phone"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Occupation</label><input type="text" name="occupation"></div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Why do they want to join?</label><textarea name="why_join"></textarea></div>
        <div class="form-group"><label>Admin Notes</label><textarea name="notes" style="min-height:60px"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Member</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Member</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="e_id">
        <div class="form-row">
          <div class="form-group"><label>First Name *</label><input type="text" name="first_name" id="e_first_name" required></div>
          <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" id="e_last_name" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Email *</label><input type="email" name="email" id="e_email" required></div>
          <div class="form-group"><label>Phone</label><input type="tel" name="phone" id="e_phone"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Occupation</label><input type="text" name="occupation" id="e_occupation"></div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="e_status">
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Why join?</label><textarea name="why_join" id="e_why_join"></textarea></div>
        <div class="form-group"><label>Admin Notes</label><textarea name="notes" id="e_notes" style="min-height:60px"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Status Modal -->
<div class="modal-overlay" id="status-modal">
  <div class="modal" style="max-width:380px">
    <div class="modal-header">
      <span class="modal-title">Update Status</span>
      <button class="modal-close" onclick="closeModal('status-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="id" id="s_id">
        <p class="mb-2">Member: <strong id="s_name"></strong></p>
        <div class="form-group">
          <label>New Status</label>
          <select name="status" id="s_status">
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('status-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="view-modal">
  <div class="modal" style="max-width:540px">
    <div class="modal-header">
      <span class="modal-title">Member Details</span>
      <button class="modal-close" onclick="closeModal('view-modal')">&times;</button>
    </div>
    <div class="modal-body" id="view-body"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('view-modal')">Close</button>
    </div>
  </div>
</div>

<script>
function openViewModal(m) {
  document.getElementById('view-body').innerHTML = `
    <div class="view-avatar-row">
      <div class="view-avatar-init">${esc(m.first_name ? m.first_name[0].toUpperCase() : '?')}</div>
      <div>
        <div class="view-name">${esc(m.first_name)} ${esc(m.last_name)}</div>
        <span class="badge badge-${esc(m.status)}">${esc(m.status)}</span>
      </div>
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Email</div><div class="view-dd"><a href="mailto:${esc(m.email)}">${esc(m.email)}</a></div></div>
      <div><div class="view-dt">Phone</div><div class="view-dd">${esc(m.phone) || '—'}</div></div>
      <div><div class="view-dt">Occupation</div><div class="view-dd">${esc(m.occupation) || '—'}</div></div>
      <div><div class="view-dt">Applied</div><div class="view-dd">${esc(m.created_at ? m.created_at.substring(0,10) : '')}</div></div>
    </div>
    <div class="view-full">
      <div class="view-dt">Why they want to join</div>
      <div class="view-dd">${esc(m.why_join) || '—'}</div>
    </div>
    <div class="view-full">
      <div class="view-dt">Admin Notes</div>
      <div class="view-dd">${esc(m.notes) || '—'}</div>
    </div>`;
  openModal('view-modal');
}

function openEditModal(m) {
  document.getElementById('e_id').value         = m.id;
  document.getElementById('e_first_name').value = m.first_name;
  document.getElementById('e_last_name').value  = m.last_name;
  document.getElementById('e_email').value      = m.email;
  document.getElementById('e_phone').value      = m.phone || '';
  document.getElementById('e_occupation').value = m.occupation || '';
  document.getElementById('e_status').value     = m.status;
  document.getElementById('e_why_join').value   = m.why_join || '';
  document.getElementById('e_notes').value      = m.notes || '';
  openModal('edit-modal');
}
function openStatusModal(id, name, status) {
  document.getElementById('s_id').value            = id;
  document.getElementById('s_name').textContent    = name;
  document.getElementById('s_status').value        = status;
  openModal('status-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
