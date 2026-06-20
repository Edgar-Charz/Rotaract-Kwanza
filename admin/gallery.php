<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Gallery.php';

$page_title = 'Gallery';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $gal = new Gallery($conn);

    if ($action === 'add') {
        $img = upload_image('image', 'gallery');
        if (!$img) {
            flash('error', 'Invalid or missing image. Allowed: JPG, PNG, GIF, WEBP (max 5MB).');
        } else {
            $gal->create(
                trim($_POST['title']), trim($_POST['description']), $img,
                trim($_POST['category']), (int)($_POST['display_order'] ?? 0)
            );
            log_activity('add_gallery', "Uploaded photo: " . trim($_POST['title']));
            flash('success', 'Photo added to gallery.');
        }
    }

    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $oldImg = $gal->getImagePathById($id);
        $img    = upload_image('image', 'gallery') ?: $oldImg;
        $gal->update(
            $id,
            trim($_POST['title']), trim($_POST['description']), $img,
            trim($_POST['category']), (int)($_POST['display_order'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0
        );
        if ($img !== $oldImg && $oldImg) delete_image($oldImg);
        log_activity('edit_gallery', "Edited photo ID $id: " . trim($_POST['title']));
        flash('success', 'Photo updated.');
    }

    if ($action === 'delete') {
        $id   = (int)$_POST['id'];
        $path = $gal->getImagePathById($id);
        if ($path) delete_image($path);
        $gal->delete($id);
        log_activity('delete_gallery', "Deleted gallery photo ID $id");
        flash('success', 'Photo deleted.');
    }

    if ($action === 'toggle') {
        $gal->toggleVisibility((int)$_POST['id']);
        flash('success', 'Visibility updated.');
    }

    header('Location: ' . ADMIN_URL . '/gallery.php');
    exit;
}

$photos = (new Gallery($conn))->getAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card-header" style="background:#fff;border-radius:10px;padding:14px 20px;margin-bottom:20px;box-shadow:var(--shadow);display:flex;justify-content:space-between;align-items:center">
  <span class="card-title"><?= count($photos) ?> Photo<?= count($photos) !== 1 ? 's':'' ?></span>
  <button class="btn btn-primary" onclick="openModal('add-modal')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Upload Photo
  </button>
</div>

<?php if ($photos): ?>
<div class="gallery-admin-grid">
  <?php foreach ($photos as $p): ?>
  <div class="gallery-item-admin">
    <?php if ($p['image_path']): ?>
    <img src="<?= h($p['image_path']) ?>" alt="<?= h($p['title']) ?>">
    <?php else: ?>
    <div class="gallery-svg-placeholder">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    </div>
    <?php endif; ?>
    <?php if (!$p['is_active']): ?>
    <div style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.6);color:#fff;font-size:10px;padding:2px 6px;border-radius:4px">Hidden</div>
    <?php endif; ?>
    <div class="gallery-item-info">
      <div class="item-title"><?= h($p['title']) ?></div>
      <?php if ($p['category']): ?>
      <div class="text-muted" style="font-size:11px;margin-bottom:6px"><?= h($p['category']) ?></div>
      <?php endif; ?>
      <div class="gallery-item-actions">
        <button class="btn btn-sm btn-secondary" onclick="openViewModal(<?= h(json_encode($p)) ?>)">View</button>
        <button class="btn btn-sm btn-info" onclick="openEditModal(<?= h(json_encode($p)) ?>)">Edit</button>
        <form id="tog-<?= $p['id'] ?>" method="POST" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn-sm btn-secondary"><?= $p['is_active'] ? 'Hide':'Show' ?></button>
        </form>
        <form id="del-g-<?= $p['id'] ?>" method="POST" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
        </form>
        <button class="btn btn-sm btn-danger" onclick="confirmDelete('del-g-<?= $p['id'] ?>')">Del</button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
  <p>No photos yet. Upload your first photo!</p>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Upload New Photo</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group mb-2">
          <label>Photo *</label>
          <label class="upload-area" for="a_image">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <p><strong>Click to upload</strong></p>
            <p style="font-size:11px;margin-top:4px">JPG, PNG, GIF, WEBP — max 5 MB</p>
          </label>
          <input type="file" id="a_image" name="image" accept="image/*" style="display:none" onchange="previewImage(this,'add-gal-prev')">
          <img id="add-gal-prev" src="" alt="Preview">
        </div>
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" required></div>
        <div class="form-row">
          <div class="form-group"><label>Category</label><input type="text" name="category" placeholder="e.g. Events, Service…"></div>
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0" min="0"></div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Upload Photo</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Photo</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="eg_id">
        <div class="form-group mb-2">
          <label>Replace Photo (optional)</label>
          <div id="eg_current_img" style="margin-bottom:8px"></div>
          <input type="file" name="image" accept="image/*" onchange="previewImage(this,'edit-gal-prev')" style="padding:6px">
          <img id="edit-gal-prev" src="" alt="Preview">
        </div>
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" id="eg_title" required></div>
        <div class="form-row">
          <div class="form-group"><label>Category</label><input type="text" name="category" id="eg_category"></div>
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="eg_display_order" min="0"></div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description" id="eg_description"></textarea></div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" id="eg_active" style="width:auto">
          <label for="eg_active" style="font-weight:400">Visible on public gallery</label>
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
  <div class="modal" style="max-width:540px">
    <div class="modal-header">
      <span class="modal-title">Photo Details</span>
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
    ${p.image_path ? `<img src="${esc(p.image_path)}" style="width:100%;max-height:260px;object-fit:cover;border-radius:8px;margin-bottom:14px">` : ''}
    <div class="view-avatar-row">
      <div>
        <div class="view-name">${esc(p.title)}</div>
        <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap">
          ${p.category ? `<span class="badge" style="background:#d6eaff;color:#1a5fb4">${esc(p.category)}</span>` : ''}
          <span class="badge ${p.is_active == 1 ? 'badge-approved' : 'badge-rejected'}">${p.is_active == 1 ? 'Visible' : 'Hidden'}</span>
        </div>
      </div>
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Category</div><div class="view-dd">${esc(p.category) || '—'}</div></div>
      <div><div class="view-dt">Display Order</div><div class="view-dd">${esc(p.display_order)}</div></div>
    </div>
    ${p.description ? `<div class="view-full"><div class="view-dt">Description</div><div class="view-dd">${esc(p.description)}</div></div>` : ''}`;
  openModal('view-modal');
}

function openEditModal(p) {
  document.getElementById('eg_id').value            = p.id;
  document.getElementById('eg_title').value         = p.title;
  document.getElementById('eg_category').value      = p.category || '';
  document.getElementById('eg_display_order').value = p.display_order || 0;
  document.getElementById('eg_description').value   = p.description || '';
  document.getElementById('eg_active').checked      = p.is_active == 1;
  const ci = document.getElementById('eg_current_img');
  ci.innerHTML = p.image_path ? '<img src="' + p.image_path + '" style="max-height:80px;border-radius:6px">' : '';
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
