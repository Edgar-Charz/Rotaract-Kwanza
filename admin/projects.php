<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Project.php';

$page_title = 'Projects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $proj = new Project($conn);

    if ($action === 'add') {
        $proj->create(
            trim($_POST['title']), trim($_POST['description']), trim($_POST['impact_stat']),
            trim($_POST['impact_label']), trim($_POST['icon_type']) ?: 'default',
            $_POST['status'] ?? 'active', isset($_POST['is_featured']) ? 1 : 0
        );
        log_activity('add_project', "Added project: " . trim($_POST['title']));
        flash('success', 'Project added.');
    }

    if ($action === 'edit') {
        $proj->update(
            (int)$_POST['id'],
            trim($_POST['title']), trim($_POST['description']), trim($_POST['impact_stat']),
            trim($_POST['impact_label']), trim($_POST['icon_type']) ?: 'default',
            $_POST['status'], isset($_POST['is_featured']) ? 1 : 0
        );
        log_activity('edit_project', "Edited project ID " . (int)$_POST['id'] . ": " . trim($_POST['title']));
        flash('success', 'Project updated.');
    }

    if ($action === 'delete') {
        $proj_title = $proj->getTitleById((int)$_POST['id']);
        $proj->delete((int)$_POST['id']);
        log_activity('delete_project', "Deleted project: $proj_title");
        flash('success', 'Project deleted.');
    }

    header('Location: ' . ADMIN_URL . '/projects.php');
    exit;
}

$projects = (new Project($conn))->getAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($projects) ?> Project<?= count($projects) !== 1 ? 's':'' ?></span>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Project
    </button>
  </div>
  <div class="table-wrap">
    <table id="dt-projects">
      <thead>
        <tr><th>Title</th><th>Impact</th><th>Status</th><th>Featured</th><th>Created</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($projects): foreach ($projects as $p): ?>
        <tr>
          <td>
            <div class="fw-bold"><?= h($p['title']) ?></div>
            <div class="text-muted" style="font-size:12px;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($p['description'] ?? '') ?></div>
          </td>
          <td>
            <?php if ($p['impact_stat']): ?>
            <div class="fw-bold text-primary"><?= h($p['impact_stat']) ?></div>
            <div class="text-muted" style="font-size:12px"><?= h($p['impact_label'] ?? '') ?></div>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td><span class="badge badge-<?= h($p['status']) ?>"><?= h($p['status']) ?></span></td>
          <td><?= $p['is_featured'] ? '<span class="badge badge-featured">Featured</span>' : '<span class="text-muted">No</span>' ?></td>
          <td class="text-muted"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-secondary" onclick="openViewModal(<?= h(json_encode($p)) ?>)">View</button>
              <button class="btn btn-sm btn-info" onclick="openEditModal(<?= h(json_encode($p)) ?>)">Edit</button>
              <form id="del-p-<?= $p['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
              </form>
              <button class="btn btn-sm btn-danger" onclick="confirmDelete('del-p-<?= $p['id'] ?>')">Delete</button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add New Project</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" required></div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Impact Stat</label><input type="text" name="impact_stat" placeholder="e.g. 1,200+"></div>
          <div class="form-group"><label>Impact Label</label><input type="text" name="impact_label" placeholder="e.g. children reached"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Icon Type</label><input type="text" name="icon_type" value="default"></div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="featured">Featured</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;margin-top:8px">
          <input type="checkbox" name="is_featured" id="a_feat" style="width:auto">
          <label for="a_feat" style="font-weight:400">Show on homepage</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Project</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Project</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="ep_id">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" id="ep_title" required></div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description" id="ep_description"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Impact Stat</label><input type="text" name="impact_stat" id="ep_impact_stat"></div>
          <div class="form-group"><label>Impact Label</label><input type="text" name="impact_label" id="ep_impact_label"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Icon Type</label><input type="text" name="icon_type" id="ep_icon_type"></div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="ep_status">
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="featured">Featured</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;margin-top:8px">
          <input type="checkbox" name="is_featured" id="ep_feat" style="width:auto">
          <label for="ep_feat" style="font-weight:400">Show on homepage</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="view-modal">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <span class="modal-title">Project Details</span>
      <button class="modal-close" onclick="closeModal('view-modal')">&times;</button>
    </div>
    <div class="modal-body" id="view-body"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('view-modal')">Close</button>
    </div>
  </div>
</div>

<script>
function openViewModal(p) {
  document.getElementById('view-body').innerHTML = `
    <div class="view-avatar-row">
      <div>
        <div class="view-name">${esc(p.title)}</div>
        <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap">
          <span class="badge badge-${esc(p.status)}">${esc(p.status)}</span>
          ${p.is_featured == 1 ? '<span class="badge badge-featured">Featured</span>' : ''}
        </div>
      </div>
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Impact</div><div class="view-dd">${esc(p.impact_stat) || '—'}</div></div>
      <div><div class="view-dt">Impact Label</div><div class="view-dd">${esc(p.impact_label) || '—'}</div></div>
      <div><div class="view-dt">Status</div><div class="view-dd">${esc(p.status)}</div></div>
      <div><div class="view-dt">Created</div><div class="view-dd">${esc(p.created_at ? p.created_at.substring(0,10) : '')}</div></div>
    </div>
    <div class="view-full">
      <div class="view-dt">Description</div>
      <div class="view-dd">${esc(p.description) || '—'}</div>
    </div>`;
  openModal('view-modal');
}

$(document).ready(function() {
  $('#dt-projects').DataTable({
    pageLength: 25,
    columnDefs: [{ orderable: false, targets: 5 }]
  });
});
function openEditModal(p) {
  document.getElementById('ep_id').value           = p.id;
  document.getElementById('ep_title').value        = p.title;
  document.getElementById('ep_description').value  = p.description || '';
  document.getElementById('ep_impact_stat').value  = p.impact_stat || '';
  document.getElementById('ep_impact_label').value = p.impact_label || '';
  document.getElementById('ep_icon_type').value    = p.icon_type || 'default';
  document.getElementById('ep_status').value       = p.status;
  document.getElementById('ep_feat').checked       = p.is_featured == 1;
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
