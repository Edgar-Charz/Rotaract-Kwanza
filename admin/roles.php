<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/TeamRole.php';

$page_title = 'Team Roles';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $tr = new TeamRole($conn);

    if ($action === 'add') {
        $tr->create(
            trim($_POST['name']), trim($_POST['tier_label']) ?: 'Team Members',
            (int)($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0
        );
        log_activity('add_role', "Added team role: " . trim($_POST['name']));
        flash('success', 'Role added.');
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $tr->update(
            $id,
            trim($_POST['name']), trim($_POST['tier_label']) ?: 'Team Members',
            (int)($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0
        );
        log_activity('edit_role', "Edited team role ID $id: " . trim($_POST['name']));
        flash('success', 'Role updated.');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $name = $tr->getNameById($id);
        $tr->delete($id);
        log_activity('delete_role', "Removed team role: $name");
        flash('success', 'Role removed. Members previously assigned this role keep their name/tier until re-saved.');
    }

    header('Location: ' . ADMIN_URL . '/roles.php');
    exit;
}

$roles = (new TeamRole($conn))->getAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($roles) ?> Team Role<?= count($roles) !== 1 ? 's' : '' ?></span>
    <?php if (has_role('editor')): ?>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Role
    </button>
    <?php endif; ?>
  </div>
  <p class="text-muted" style="font-size:12.5px;padding:0 16px 12px">
    Defines the positions admins can assign to team members (Team &rarr; Add/Edit Member). The <strong>Tier</strong> text groups roles into the sections shown on the public Team page &mdash; use the same tier text for every role that belongs in the same section, and order roles top-to-bottom the way you want the hierarchy to read (Display Order).
  </p>
  <div class="table-wrap">
    <table id="dt-roles">
      <thead>
        <tr><th>Name</th><th>Tier</th><th>Order</th><th>Visible</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($roles): foreach ($roles as $r): ?>
        <tr>
          <td class="fw-bold"><?= h($r['name']) ?></td>
          <td class="text-muted"><?= h($r['tier_label']) ?></td>
          <td><?= $r['display_order'] ?></td>
          <td><?= $r['is_active'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($r)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <form id="del-r-<?= $r['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-r-<?= $r['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="add-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Add Team Role</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group mb-2"><label>Role Name *</label><input type="text" name="name" placeholder="e.g. Vice President" required></div>
        <div class="form-group mb-2">
          <label>Tier <span class="text-muted" style="font-weight:400">(section heading on the public Team page)</span></label>
          <input type="text" name="tier_label" placeholder="e.g. Leadership" list="tier-suggestions" required>
          <datalist id="tier-suggestions">
            <?php foreach (array_unique(array_column($roles, 'tier_label')) as $tl): ?><option value="<?= h($tl) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0" min="0"></div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_active" id="ar_active" checked style="width:auto">
            <label for="ar_active" style="font-weight:400">Available when adding members</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Role</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Edit Team Role</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="er_id">
        <div class="form-group mb-2"><label>Role Name *</label><input type="text" name="name" id="er_name" required></div>
        <div class="form-group mb-2">
          <label>Tier <span class="text-muted" style="font-weight:400">(section heading on the public Team page)</span></label>
          <input type="text" name="tier_label" id="er_tier" list="tier-suggestions" required>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="er_order" min="0"></div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_active" id="er_active" style="width:auto">
            <label for="er_active" style="font-weight:400">Available when adding members</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
$(document).ready(function() {
  $('#dt-roles').DataTable({ pageLength: 25, order: [[2, 'asc']], columnDefs: [{ orderable: false, targets: 4 }] });
});
function openEditModal(r) {
  document.getElementById('er_id').value    = r.id;
  document.getElementById('er_name').value  = r.name;
  document.getElementById('er_tier').value  = r.tier_label;
  document.getElementById('er_order').value = r.display_order || 0;
  document.getElementById('er_active').checked = r.is_active == 1;
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
