<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Member.php';

$page_title = 'Members';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $m = new Member($conn);

    if ($action === 'add') {
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
        } else {
            try {
                $new_id = $m->create(
                    trim($_POST['first_name']), trim($_POST['last_name']), $email,
                    trim($_POST['phone']), trim($_POST['occupation']), trim($_POST['why_join']),
                    $_POST['status'] ?? 'pending', trim($_POST['notes'] ?? ''), '',
                    trim($_POST['bio'] ?? ''), trim($_POST['linkedin_url'] ?? ''), trim($_POST['instagram_url'] ?? '')
                );
                $photo = upload_image('photo', 'members');
                if ($photo) $m->updatePhoto($new_id, $photo);
                log_activity('add_member', "Added: " . trim($_POST['first_name']) . " " . trim($_POST['last_name']));
                flash('success', 'Member added successfully.');
            } catch (mysqli_sql_exception $e) {
                flash('error', $e->getCode() == 1062 ? 'Email already exists.' : 'Could not add member.');
            }
        }
    }

    if ($action === 'edit') {
        $id    = (int)$_POST['id'];
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
        } else {
            try {
                $m->update(
                    $id,
                    trim($_POST['first_name']), trim($_POST['last_name']), $email,
                    trim($_POST['phone']), trim($_POST['occupation']), trim($_POST['why_join']),
                    $_POST['status'], trim($_POST['notes'] ?? ''),
                    trim($_POST['bio'] ?? ''), trim($_POST['linkedin_url'] ?? ''), trim($_POST['instagram_url'] ?? '')
                );
                $new_photo = upload_image('photo', 'members');
                if ($new_photo) {
                    $old_photo = $m->getPhotoById($id);
                    if ($old_photo) delete_image($old_photo);
                    $m->updatePhoto($id, $new_photo);
                }
                log_activity('edit_member', "Edited member ID $id: " . trim($_POST['first_name']) . " " . trim($_POST['last_name']));
                flash('success', 'Member updated.');
            } catch (mysqli_sql_exception $e) {
                flash('error', $e->getCode() == 1062 ? 'Email already exists.' : 'Could not update member.');
            }
        }
    }

    if ($action === 'delete') {
        $del_id    = (int)$_POST['id'];
        $del_name  = $m->getFullName($del_id);
        $del_photo = $m->getPhotoById($del_id);
        if ($del_photo) delete_image($del_photo);
        $m->delete($del_id);
        log_activity('delete_member', "Deleted: $del_name (ID $del_id)");
        flash('success', 'Member deleted.');
    }

    if ($action === 'bulk_status') {
        $status = $_POST['bulk_status'] ?? '';
        $ids    = array_map('intval', $_POST['ids'] ?? []);
        if (in_array($status, ['pending', 'approved', 'rejected'], true) && $ids) {
            require_once dirname(__DIR__) . '/classes/Mailer.php';
            require_once dirname(__DIR__) . '/classes/SiteSettings.php';
            $count = 0;
            foreach ($ids as $mid) {
                $member = $m->findById($mid);
                if (!$member) continue;
                $m->updateStatus($mid, $status);
                $count++;
                if (in_array($status, ['approved', 'rejected'], true)) {
                    try {
                        $mailer   = Mailer::fromSettings($conn);
                        $fullName = $member['first_name'] . ' ' . $member['last_name'];
                        $club     = get_setting('site_name', 'Rotaract Kwanza');
                        $mailer->applicationStatusChange($member['email'], $fullName, $status, $club);
                    } catch (Throwable $e) {}
                }
            }
            log_activity('bulk_update_member_status', "Bulk set $count member(s) to $status");
            flash('success', "$count member(s) updated to $status.");
        }
    }

    if ($action === 'bulk_delete') {
        $ids   = array_map('intval', $_POST['ids'] ?? []);
        $count = 0;
        foreach ($ids as $mid) {
            $name = $m->getFullName($mid);
            if (!$name) continue;
            $photo = $m->getPhotoById($mid);
            if ($photo) delete_image($photo);
            $m->delete($mid);
            $count++;
        }
        log_activity('bulk_delete_member', "Bulk deleted $count member(s)");
        flash('success', "$count member(s) deleted.");
    }

    if ($action === 'status') {
        $status = $_POST['status'] ?? 'pending';
        if (in_array($status, ['pending','approved','rejected'])) {
            $mem_name = $m->getFullName((int)$_POST['id']);
            $m->updateStatus((int)$_POST['id'], $status);
            log_activity('update_member_status', "Set $mem_name → $status");
            flash('success', 'Status updated to ' . $status . '.');

            // Send email notification on approve/reject
            if (in_array($status, ['approved','rejected'])) {
                try {
                    require_once dirname(__DIR__) . '/classes/Mailer.php';
                    require_once dirname(__DIR__) . '/classes/SiteSettings.php';
                    $member = $m->findById((int)$_POST['id']);
                    if ($member) {
                        $mailer   = Mailer::fromSettings($conn);
                        $fullName = $member['first_name'] . ' ' . $member['last_name'];
                        $club     = get_setting('site_name', 'Rotaract Kwanza');
                        $mailer->applicationStatusChange($member['email'], $fullName, $status, $club);
                    }
                } catch (Throwable $e) {}
            }
        }
    }

    if ($action === 'toggle_directory') {
        $id  = (int)$_POST['id'];
        $cur = (int)db_val($conn, 'SELECT show_in_directory FROM members WHERE id=?', [$id]);
        db_exec($conn, 'UPDATE members SET show_in_directory=? WHERE id=?', [$cur ? 0 : 1, $id]);
        flash('success', 'Directory visibility toggled.');
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
        <a href="members.php" class="btn btn-sm btn-secondary">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <?php if (has_role('editor')): ?>
    <a href="import_members.php" class="btn btn-secondary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><polyline points="16 17 12 21 8 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
      Import CSV
    </a>
    <a href="export_members.php?status=<?= urlencode($filter) ?>"
       class="btn btn-success">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
      Export CSV
    </a>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Member
    </button>
    <?php endif; ?>
  </div>

  <?php if (has_role('editor')): ?>
  <div class="card-header" id="bulk-bar" style="display:none;background:#fef6f0">
    <span id="bulk-count" class="text-muted" style="font-size:13px;font-weight:600"></span>
    <div style="display:flex;gap:8px">
      <button type="button" class="btn btn-sm btn-success" onclick="bulkApprove()">Approve Selected</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="bulkReject()">Reject Selected</button>
      <button type="button" class="btn btn-sm btn-danger" onclick="bulkDelete()">Delete Selected</button>
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
    <table id="dt-members">
      <thead>
        <tr>
          <?php if (has_role('editor')): ?><th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th><?php endif; ?>
          <th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Occupation</th><th>Status</th><th>Directory</th><th>Applied</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($members): foreach ($members as $m): ?>
        <tr>
          <?php if (has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $m['id'] ?>" onchange="updateBulkBar()"></td><?php endif; ?>
          <td class="text-muted"><?= $m['id'] ?></td>
          <td class="fw-bold"><?= h($m['first_name'] . ' ' . $m['last_name']) ?></td>
          <td><?= h($m['email']) ?></td>
          <td><?= h($m['phone'] ?? '—') ?></td>
          <td><?= h($m['occupation'] ?? '—') ?></td>
          <td><span class="badge badge-<?= h($m['status']) ?>"><?= h($m['status']) ?></span></td>
          <td>
            <?php if (has_role('editor')): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="toggle_directory">
              <input type="hidden" name="id" value="<?= $m['id'] ?>">
              <button type="submit" class="btn btn-sm <?= ($m['show_in_directory'] ?? 0) ? 'btn-success' : 'btn-secondary' ?>"
                      title="Toggle directory listing" style="font-size:11px">
                <?= ($m['show_in_directory'] ?? 0) ? '✓ Listed' : 'Hidden' ?>
              </button>
            </form>
            <?php else: ?>
            <span class="badge <?= ($m['show_in_directory'] ?? 0) ? 'badge-approved' : 'badge-rejected' ?>" style="font-size:11px"><?= ($m['show_in_directory'] ?? 0) ? 'Listed' : 'Hidden' ?></span>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= $m['created_at'] ? date('d M Y', strtotime($m['created_at'])) : '—' ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-icon btn-sm btn-secondary" title="View" aria-label="View"
                onclick="openViewModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit"
                onclick="openEditModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <button class="btn btn-icon btn-sm btn-success" title="Change Status" aria-label="Change Status"
                onclick="openStatusModal(<?= $m['id'] ?>, '<?= h($m['first_name'].' '.$m['last_name']) ?>', '<?= h($m['status']) ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg></button>
              <form id="del-m-<?= $m['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-m-<?= $m['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
              <?php endif; ?>
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
    columnDefs: [
      { orderable: false, targets: -1 }<?= has_role('editor') ? ", { orderable: false, targets: 0 }" : '' ?>
    ]
  });
});

function getCheckedMemberIds() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) { return c.value; });
}
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(function(c) { c.checked = cb.checked; });
  updateBulkBar();
}
function updateBulkBar() {
  var ids = getCheckedMemberIds();
  document.getElementById('bulk-bar').style.display = ids.length ? 'flex' : 'none';
  document.getElementById('bulk-count').textContent = ids.length + ' selected';
}
function submitBulk(action, status) {
  var ids = getCheckedMemberIds();
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
function bulkApprove() {
  var ids = getCheckedMemberIds();
  if (ids.length && confirm('Approve ' + ids.length + ' selected member(s)?')) submitBulk('bulk_status', 'approved');
}
function bulkReject() {
  var ids = getCheckedMemberIds();
  if (ids.length && confirm('Reject ' + ids.length + ' selected member(s)?')) submitBulk('bulk_status', 'rejected');
}
function bulkDelete() {
  var ids = getCheckedMemberIds();
  if (ids.length && confirm('Permanently delete ' + ids.length + ' selected member(s)? This cannot be undone.')) submitBulk('bulk_delete');
}
</script>

<!-- Add Modal -->
<div class="modal fade" id="add-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Add New Member</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
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
        <div class="form-group">
          <label>Public Bio <span class="text-muted" style="font-weight:400">(shown on the member directory, optional)</span></label>
          <textarea name="bio" style="min-height:60px" placeholder="Short intro shown publicly, e.g. interests, what they do in the club..."></textarea>
        </div>
        <div class="form-row">
          <div class="form-group"><label>LinkedIn URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="linkedin_url" placeholder="https://linkedin.com/in/..."></div>
          <div class="form-group"><label>Instagram URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="instagram_url" placeholder="https://instagram.com/..."></div>
        </div>
        <div class="form-group"><label>Admin Notes</label><textarea name="notes" style="min-height:60px"></textarea></div>
        <div class="form-group">
          <label>Profile Photo <span class="text-muted" style="font-weight:400">(optional)</span></label>
          <input type="file" name="photo" accept="image/*" style="padding:6px" onchange="previewImage(this,'add-m-photo-prev')">
          <img id="add-m-photo-prev" src="" alt="Preview" style="display:none;width:72px;height:72px;border-radius:50%;object-fit:cover;margin-top:8px;border:2px solid var(--border)">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Member</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Edit Member</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
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
        <div class="form-group">
          <label>Public Bio <span class="text-muted" style="font-weight:400">(shown on the member directory, optional)</span></label>
          <textarea name="bio" id="e_bio" style="min-height:60px" placeholder="Short intro shown publicly, e.g. interests, what they do in the club..."></textarea>
        </div>
        <div class="form-row">
          <div class="form-group"><label>LinkedIn URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="linkedin_url" id="e_linkedin" placeholder="https://linkedin.com/in/..."></div>
          <div class="form-group"><label>Instagram URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="instagram_url" id="e_instagram" placeholder="https://instagram.com/..."></div>
        </div>
        <div class="form-group"><label>Admin Notes</label><textarea name="notes" id="e_notes" style="min-height:60px"></textarea></div>
        <div class="form-group">
          <label>Profile Photo</label>
          <div id="e_photo_current" style="margin-bottom:8px"></div>
          <input type="file" name="photo" accept="image/*" style="padding:6px" onchange="previewImage(this,'edit-m-photo-prev')">
          <img id="edit-m-photo-prev" src="" alt="New preview" style="display:none;width:72px;height:72px;border-radius:50%;object-fit:cover;margin-top:8px;border:2px solid var(--border)">
          <p class="text-muted" style="font-size:11.5px;margin-top:4px">Leave blank to keep the current photo.</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="status-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:380px">
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
<div class="modal fade" id="view-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:540px">
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
function memberAvatar(m, size) {
  size = size || 56;
  if (m.photo_path) {
    // photo_path is like admin/uploads/members/xxx.jpg — resolve to a URL
    var base = window.location.pathname.replace(/\/admin\/.*$/, '');
    var src  = base + '/' + m.photo_path;
    return '<img src="' + src + '" alt="" style="width:' + size + 'px;height:' + size + 'px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">';
  }
  var initials = (m.first_name ? m.first_name[0] : '?').toUpperCase();
  return '<div class="view-avatar-init" style="width:' + size + 'px;height:' + size + 'px;font-size:' + Math.round(size * 0.4) + 'px">' + esc(initials) + '</div>';
}

function openViewModal(m) {
  document.getElementById('view-body').innerHTML = `
    <div class="view-avatar-row">
      ${memberAvatar(m, 56)}
      <div>
        <div class="view-name">${esc(m.first_name)} ${esc(m.last_name)}</div>
        <span class="badge badge-${esc(m.status)}">${esc(m.status)}</span>
      </div>
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Email</div><div class="view-dd"><a href="mailto:${esc(m.email)}">${esc(m.email)}</a></div></div>
      <div><div class="view-dt">Phone</div><div class="view-dd">${esc(m.phone) || '—'}</div></div>
      <div><div class="view-dt">Occupation</div><div class="view-dd">${esc(m.occupation) || '—'}</div></div>
      <div><div class="view-dt">LinkedIn</div><div class="view-dd">${m.linkedin_url ? `<a href="${esc(m.linkedin_url)}" target="_blank" rel="noopener">${esc(m.linkedin_url)}</a>` : '—'}</div></div>
      <div><div class="view-dt">Instagram</div><div class="view-dd">${m.instagram_url ? `<a href="${esc(m.instagram_url)}" target="_blank" rel="noopener">${esc(m.instagram_url)}</a>` : '—'}</div></div>
      <div><div class="view-dt">Applied</div><div class="view-dd">${esc(m.created_at ? m.created_at.substring(0,10) : '')}</div></div>
    </div>
    <div class="view-full">
      <div class="view-dt">Public Bio</div>
      <div class="view-dd">${esc(m.bio) || '—'}</div>
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
  document.getElementById('e_bio').value        = m.bio || '';
  document.getElementById('e_linkedin').value   = m.linkedin_url || '';
  document.getElementById('e_instagram').value  = m.instagram_url || '';
  document.getElementById('e_notes').value      = m.notes || '';

  // Show existing photo or placeholder
  var cur = document.getElementById('e_photo_current');
  var prev = document.getElementById('edit-m-photo-prev');
  prev.style.display = 'none'; prev.src = '';
  cur.innerHTML = memberAvatar(m, 72);

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
