<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Category.php';

$page_title = 'Categories';

$valid_types = ['gallery', 'event', 'announcement'];
$type_labels = ['gallery' => 'Gallery', 'event' => 'Events', 'announcement' => 'Announcements'];
$type = in_array($_GET['type'] ?? '', $valid_types, true) ? $_GET['type'] : 'gallery';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $cat = new Category($conn);
    $post_type = in_array($_POST['type'] ?? '', $valid_types, true) ? $_POST['type'] : 'gallery';

    if ($action === 'add') {
        try {
            $cat->create(
                $post_type, trim($_POST['name']),
                (int)($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0
            );
            log_activity('add_category', "Added $post_type category: " . trim($_POST['name']));
            flash('success', 'Category added.');
        } catch (mysqli_sql_exception $e) {
            flash('error', $e->getCode() == 1062 ? 'A category with that name already exists.' : 'Could not add category.');
        }
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        try {
            $cat->update(
                $id, trim($_POST['name']),
                (int)($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0
            );
            log_activity('edit_category', "Edited category ID $id: " . trim($_POST['name']));
            flash('success', 'Category updated.');
        } catch (mysqli_sql_exception $e) {
            flash('error', $e->getCode() == 1062 ? 'A category with that name already exists.' : 'Could not update category.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $name = $cat->getNameById($id);
        $cat->delete($id);
        log_activity('delete_category', "Removed category: $name");
        flash('success', 'Category removed. Existing photos/events/posts already tagged with it keep that value until re-saved.');
    }

    header('Location: ' . ADMIN_URL . '/categories.php?type=' . $post_type);
    exit;
}

$categories = (new Category($conn))->getAll($type);

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <div class="flex align-center gap-2 flex-wrap" style="flex:1">
      <?php foreach ($valid_types as $t): ?>
      <a href="?type=<?= $t ?>" class="btn btn-sm <?= $type === $t ? 'btn-primary' : 'btn-secondary' ?>"><?= $type_labels[$t] ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (has_role('editor')): ?>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Category
    </button>
    <?php endif; ?>
  </div>
  <p class="text-muted" style="font-size:12.5px;padding:0 16px 12px">
    Manages the categories offered when tagging <?= strtolower($type_labels[$type]) ?>. Deleting a category only removes it from this list &mdash; items already tagged with it keep that value.
  </p>
  <div class="table-wrap">
    <table id="dt-categories">
      <thead>
        <tr><th>Name</th><th>Order</th><th>Visible</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($categories): foreach ($categories as $c): ?>
        <tr>
          <td class="fw-bold"><?= h($c['name']) ?></td>
          <td><?= $c['display_order'] ?></td>
          <td><?= $c['is_active'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($c)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <form id="del-c-<?= $c['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="type" value="<?= $type ?>">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-c-<?= $c['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="4" class="text-muted" style="text-align:center;padding:24px">No <?= strtolower($type_labels[$type]) ?> categories yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="add-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Add <?= $type_labels[$type] ?> Category</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="type" value="<?= $type ?>">
        <div class="form-group mb-2"><label>Name *</label><input type="text" name="name" placeholder="e.g. Community Service" required></div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0" min="0"></div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_active" id="ac_active" checked style="width:auto">
            <label for="ac_active" style="font-weight:400">Available when tagging</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Category</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Edit Category</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="type" value="<?= $type ?>">
        <input type="hidden" name="id" id="ec_id">
        <div class="form-group mb-2"><label>Name *</label><input type="text" name="name" id="ec_name" required></div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="ec_order" min="0"></div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_active" id="ec_active" style="width:auto">
            <label for="ec_active" style="font-weight:400">Available when tagging</label>
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
  $('#dt-categories').DataTable({ pageLength: 25, order: [[1, 'asc']], columnDefs: [{ orderable: false, targets: 3 }] });
});
function openEditModal(c) {
  document.getElementById('ec_id').value    = c.id;
  document.getElementById('ec_name').value  = c.name;
  document.getElementById('ec_order').value = c.display_order || 0;
  document.getElementById('ec_active').checked = c.is_active == 1;
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
