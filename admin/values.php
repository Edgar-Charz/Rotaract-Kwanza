<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/ClubValue.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$page_title = 'Club Values';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $cv = new ClubValue($conn);

    if ($action === 'add') {
        $cv->create(
            trim($_POST['icon_key']) ?: 'heart', trim($_POST['title']), trim($_POST['description']),
            (int)($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0
        );
        log_activity('add_value', "Added club value: " . trim($_POST['title']));
        flash('success', 'Value added.');
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $cv->update(
            $id,
            trim($_POST['icon_key']) ?: 'heart', trim($_POST['title']), trim($_POST['description']),
            (int)($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0
        );
        log_activity('edit_value', "Edited club value ID $id: " . trim($_POST['title']));
        flash('success', 'Value updated.');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $name = $cv->getTitleById($id);
        $cv->delete($id);
        log_activity('delete_value', "Removed club value: $name");
        flash('success', 'Value removed.');
    }

    header('Location: ' . ADMIN_URL . '/values.php');
    exit;
}

$values = (new ClubValue($conn))->getAll();
$icons  = icon_options();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($values) ?> Club Value<?= count($values) !== 1 ? 's' : '' ?></span>
    <?php if (has_role('editor')): ?>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Value
    </button>
    <?php endif; ?>
  </div>
  <p class="text-muted" style="font-size:12.5px;padding:0 16px 12px">
    Powers the "Our Values" cards shown on both the homepage and the About page.
  </p>
  <div class="table-wrap">
    <table id="dt-values">
      <thead>
        <tr><th>Icon</th><th>Title</th><th>Description</th><th>Order</th><th>Visible</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($values): foreach ($values as $v): ?>
        <tr>
          <td><div style="width:32px;height:32px;color:var(--primary)"><?= icon_svg($v['icon_key']) ?></div></td>
          <td class="fw-bold"><?= h($v['title']) ?></td>
          <td class="text-muted" style="font-size:12px;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($v['description'] ?? '') ?></td>
          <td><?= $v['display_order'] ?></td>
          <td><?= $v['is_active'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($v)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <form id="del-v-<?= $v['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $v['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-v-<?= $v['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
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
      <span class="modal-title">Add Club Value</span>
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
            <input type="checkbox" name="is_active" id="av_active" checked style="width:auto">
            <label for="av_active" style="font-weight:400">Visible on site</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Value</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Edit Club Value</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="ev_id">
        <div class="form-row">
          <div class="form-group"><label>Title *</label><input type="text" name="title" id="ev_title" required></div>
          <div class="form-group"><label>Icon</label>
            <select name="icon_key" id="ev_icon">
              <?php foreach ($icons as $k => $label): ?><option value="<?= h($k) ?>"><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description" id="ev_desc"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="ev_order" min="0"></div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_active" id="ev_active" style="width:auto">
            <label for="ev_active" style="font-weight:400">Visible on site</label>
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
  $('#dt-values').DataTable({ pageLength: 25, order: [[3, 'asc']], columnDefs: [{ orderable: false, targets: [0, 5] }] });
});
function openEditModal(v) {
  document.getElementById('ev_id').value    = v.id;
  document.getElementById('ev_title').value = v.title;
  document.getElementById('ev_icon').value  = v.icon_key || 'heart';
  document.getElementById('ev_desc').value  = v.description || '';
  document.getElementById('ev_order').value = v.display_order || 0;
  document.getElementById('ev_active').checked = v.is_active == 1;
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
