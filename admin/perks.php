<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/MembershipPerk.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$page_title = 'Membership Perks';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $mp = new MembershipPerk($conn);

    if ($action === 'add') {
        $mp->create(
            trim($_POST['icon_key']) ?: 'people', trim($_POST['title']), trim($_POST['description']),
            (int)($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0
        );
        log_activity('add_perk', "Added membership perk: " . trim($_POST['title']));
        flash('success', 'Perk added.');
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $mp->update(
            $id,
            trim($_POST['icon_key']) ?: 'people', trim($_POST['title']), trim($_POST['description']),
            (int)($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0
        );
        log_activity('edit_perk', "Edited membership perk ID $id: " . trim($_POST['title']));
        flash('success', 'Perk updated.');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $name = $mp->getTitleById($id);
        $mp->delete($id);
        log_activity('delete_perk', "Removed membership perk: $name");
        flash('success', 'Perk removed.');
    }

    header('Location: ' . ADMIN_URL . '/perks.php');
    exit;
}

$perks = (new MembershipPerk($conn))->getAll();
$icons = icon_options();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($perks) ?> Membership Perk<?= count($perks) !== 1 ? 's' : '' ?></span>
    <?php if (has_role('editor')): ?>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Perk
    </button>
    <?php endif; ?>
  </div>
  <p class="text-muted" style="font-size:12.5px;padding:0 16px 12px">
    Powers the "Why Join" perks shown on the Join page and the homepage Join CTA section.
  </p>
  <div class="table-wrap">
    <table id="dt-perks">
      <thead>
        <tr><th>Icon</th><th>Title</th><th>Description</th><th>Order</th><th>Visible</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($perks): foreach ($perks as $p): ?>
        <tr>
          <td><div style="width:32px;height:32px;color:var(--primary)"><?= icon_svg($p['icon_key']) ?></div></td>
          <td class="fw-bold"><?= h($p['title']) ?></td>
          <td class="text-muted" style="font-size:12px;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($p['description'] ?? '') ?></td>
          <td><?= $p['display_order'] ?></td>
          <td><?= $p['is_active'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($p)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <form id="del-p-<?= $p['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-p-<?= $p['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
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
      <span class="modal-title">Add Membership Perk</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
          <div class="form-group"><label>Title *</label><input type="text" name="title" required></div>
          <div class="form-group"><label>Icon</label>
            <select name="icon_key">
              <?php foreach ($icons as $k => $label): ?><option value="<?= h($k) ?>"><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0" min="0"></div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_active" id="ap_active" checked style="width:auto">
            <label for="ap_active" style="font-weight:400">Visible on site</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Perk</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Edit Membership Perk</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="ep_id">
        <div class="form-row">
          <div class="form-group"><label>Title *</label><input type="text" name="title" id="ep_title" required></div>
          <div class="form-group"><label>Icon</label>
            <select name="icon_key" id="ep_icon">
              <?php foreach ($icons as $k => $label): ?><option value="<?= h($k) ?>"><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description" id="ep_desc"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="ep_order" min="0"></div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_active" id="ep_active" style="width:auto">
            <label for="ep_active" style="font-weight:400">Visible on site</label>
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
  $('#dt-perks').DataTable({ pageLength: 25, order: [[3, 'asc']], columnDefs: [{ orderable: false, targets: [0, 5] }] });
});
function openEditModal(p) {
  document.getElementById('ep_id').value    = p.id;
  document.getElementById('ep_title').value = p.title;
  document.getElementById('ep_icon').value  = p.icon_key || 'people';
  document.getElementById('ep_desc').value  = p.description || '';
  document.getElementById('ep_order').value = p.display_order || 0;
  document.getElementById('ep_active').checked = p.is_active == 1;
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
