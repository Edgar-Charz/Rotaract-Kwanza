<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

$page_title = 'Announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $ann = new Announcement($conn);

    if ($action === 'add') {
        $title   = trim($_POST['title']);
        $content = trim($_POST['content']);
        $slug    = slugify($title) . '-' . substr(uniqid(), -4);
        $img     = upload_image('image', 'announcements') ?: '';
        $ann->create($title, $slug, $content, $img,
            $_POST['category'] ?? 'news',
            isset($_POST['is_published']) ? 1 : 0
        );
        log_activity('add_announcement', "Added: $title");
        flash('success', 'Announcement published.');
    }

    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $title  = trim($_POST['title']);
        $oldImg = $ann->getImagePathById($id);
        $img    = upload_image('image', 'announcements') ?: $oldImg;
        $ann->update($id, $title, trim($_POST['content']), $img,
            $_POST['category'] ?? 'news',
            isset($_POST['is_published']) ? 1 : 0
        );
        if ($img !== $oldImg && $oldImg) delete_image($oldImg);
        log_activity('edit_announcement', "Edited: $title");
        flash('success', 'Announcement updated.');
    }

    if ($action === 'delete') {
        $id   = (int)$_POST['id'];
        $path = $ann->getImagePathById($id);
        if ($path) delete_image($path);
        $ann->delete($id);
        log_activity('delete_announcement', "Deleted announcement ID $id");
        flash('success', 'Announcement deleted.');
    }

    if ($action === 'toggle') {
        $ann->togglePublished((int)$_POST['id']);
        flash('success', 'Visibility toggled.');
    }

    header('Location: ' . ADMIN_URL . '/announcements.php');
    exit;
}

$filter = in_array($_GET['cat'] ?? '', ['news','minutes','notice','announcement']) ? $_GET['cat'] : '';
$posts  = (new Announcement($conn))->getAll($filter);

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <div class="flex align-center gap-2 flex-wrap" style="flex:1">
      <a href="?" class="btn btn-sm <?= !$filter?'btn-primary':'btn-secondary' ?>">All</a>
      <?php foreach(['news','minutes','notice','announcement'] as $cat): ?>
      <a href="?cat=<?= $cat ?>" class="btn btn-sm <?= $filter===$cat?'btn-primary':'btn-secondary' ?>"><?= ucfirst($cat) ?></a>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Post
    </button>
  </div>
  <div class="table-wrap">
    <table id="dt-announcements">
      <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if ($posts): foreach ($posts as $p): ?>
        <tr>
          <td>
            <div class="fw-bold"><?= h($p['title']) ?></div>
            <div class="text-muted" style="font-size:12px;max-width:350px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h(strip_tags($p['content'])) ?></div>
          </td>
          <td><span class="badge badge-info" style="background:#d6eaff;color:#1a5fb4"><?= h($p['category']) ?></span></td>
          <td><?= $p['is_published'] ? '<span class="badge badge-approved">Published</span>' : '<span class="badge badge-pending">Draft</span>' ?></td>
          <td class="text-muted"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-secondary" onclick="openViewModal(<?= h(json_encode($p)) ?>)">View</button>
              <button class="btn btn-sm btn-info" onclick="openEditModal(<?= h(json_encode($p)) ?>)">Edit</button>
              <form id="tog-a-<?= $p['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary"><?= $p['is_published']?'Unpublish':'Publish' ?></button>
              </form>
              <form id="del-a-<?= $p['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
              </form>
              <button class="btn btn-sm btn-danger" onclick="confirmDelete('del-a-<?= $p['id'] ?>')">Delete</button>
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
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <span class="modal-title">New Announcement</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" required></div>
        <div class="form-row">
          <div class="form-group">
            <label>Category</label>
            <select name="category">
              <option value="news">News</option>
              <option value="minutes">Meeting Minutes</option>
              <option value="notice">Notice</option>
              <option value="announcement">Announcement</option>
            </select>
          </div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:24px">
            <input type="checkbox" name="is_published" id="a_pub" style="width:auto" checked>
            <label for="a_pub" style="font-weight:400">Publish immediately</label>
          </div>
        </div>
        <div class="form-group mb-2"><label>Content *</label><textarea name="content" style="min-height:160px" required></textarea></div>
        <div class="form-group">
          <label>Cover Image (optional)</label>
          <input type="file" name="image" accept="image/*" onchange="previewImage(this,'add-ann-prev')" style="padding:6px">
          <img id="add-ann-prev" src="" alt="Preview">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Post</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <span class="modal-title">Edit Announcement</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="ea_id">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" id="ea_title" required></div>
        <div class="form-row">
          <div class="form-group">
            <label>Category</label>
            <select name="category" id="ea_category">
              <option value="news">News</option>
              <option value="minutes">Meeting Minutes</option>
              <option value="notice">Notice</option>
              <option value="announcement">Announcement</option>
            </select>
          </div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:24px">
            <input type="checkbox" name="is_published" id="ea_pub" style="width:auto">
            <label for="ea_pub" style="font-weight:400">Published</label>
          </div>
        </div>
        <div class="form-group mb-2"><label>Content *</label><textarea name="content" id="ea_content" style="min-height:160px" required></textarea></div>
        <div class="form-group">
          <label>Replace Image (optional)</label>
          <div id="ea_img_preview" style="margin-bottom:8px"></div>
          <input type="file" name="image" accept="image/*" onchange="previewImage(this,'edit-ann-prev')" style="padding:6px">
          <img id="edit-ann-prev" src="" alt="Preview">
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
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <span class="modal-title">Announcement Details</span>
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
    ${p.image_path ? `<img src="${esc(p.image_path)}" style="width:100%;max-height:180px;object-fit:cover;border-radius:8px;margin-bottom:14px">` : ''}
    <div class="view-avatar-row">
      <div>
        <div class="view-name">${esc(p.title)}</div>
        <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap">
          <span class="badge" style="background:#d6eaff;color:#1a5fb4">${esc(p.category)}</span>
          ${p.is_published == 1 ? '<span class="badge badge-approved">Published</span>' : '<span class="badge badge-pending">Draft</span>'}
        </div>
      </div>
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Category</div><div class="view-dd">${esc(p.category)}</div></div>
      <div><div class="view-dt">Date</div><div class="view-dd">${esc(p.created_at ? p.created_at.substring(0,10) : '')}</div></div>
    </div>
    <div class="view-full">
      <div class="view-dt">Content</div>
      <div class="view-dd">${esc(p.content)}</div>
    </div>`;
  openModal('view-modal');
}

$(document).ready(function() {
  $('#dt-announcements').DataTable({
    pageLength: 25,
    order: [[3, 'desc']],
    columnDefs: [{ orderable: false, targets: 4 }]
  });
});
function openEditModal(p) {
  document.getElementById('ea_id').value       = p.id;
  document.getElementById('ea_title').value    = p.title;
  document.getElementById('ea_category').value = p.category;
  document.getElementById('ea_content').value  = p.content;
  document.getElementById('ea_pub').checked    = p.is_published == 1;
  const prev = document.getElementById('ea_img_preview');
  prev.innerHTML = p.image_path ? '<img src="'+p.image_path+'" style="max-height:80px;border-radius:6px">' : '';
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
