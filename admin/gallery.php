<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Gallery.php';
require_once dirname(__DIR__) . '/classes/Category.php';

$page_title = 'Gallery';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  require_role('editor');
  $action = $_POST['action'] ?? '';
  $gal = new Gallery($conn);

  if ($action === 'add') {
    $img = upload_image('image', 'gallery');
    if (!$img) {
      flash('error', 'Invalid or missing image. Allowed: JPG, PNG, GIF, WEBP (max 5MB).');
    } else {
      $gal->create(
        trim($_POST['title']),
        trim($_POST['description']),
        $img,
        trim($_POST['category']),
        (int)($_POST['display_order'] ?? 0)
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
      trim($_POST['title']),
      trim($_POST['description']),
      $img,
      trim($_POST['category']),
      (int)($_POST['display_order'] ?? 0),
      isset($_POST['is_active']) ? 1 : 0
    );
    if ($img !== $oldImg && $oldImg) delete_image($oldImg);
    log_activity('edit_gallery', "Edited photo ID $id: " . trim($_POST['title']));
    if ($img === $oldImg && !empty($_FILES['image']['name'])) {
      flash('error', 'Photo updated, but the new image could not be uploaded (invalid file type or too large).');
    } else {
      flash('success', 'Photo updated.');
    }
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
    $id = (int)$_POST['id'];
    $gal->toggleVisibility($id);
    log_activity('toggle_gallery_visibility', "Toggled visibility for gallery photo ID $id");
    flash('success', 'Visibility updated.');
  }

  if ($action === 'bulk_hide' || $action === 'bulk_show') {
    $ids    = array_map('intval', $_POST['ids'] ?? []);
    $active = $action === 'bulk_show' ? 1 : 0;
    $count  = $gal->updateVisibilityBatch($ids, $active);
    log_activity('bulk_update_gallery', "Bulk set $count photo(s) " . ($active ? 'visible' : 'hidden'));
    flash('success', "$count photo(s) updated.");
  }

  if ($action === 'bulk_delete') {
    $ids   = array_map('intval', $_POST['ids'] ?? []);
    $paths = $gal->getImagePathsByIds($ids);
    foreach ($paths as $path) {
      if ($path) delete_image($path);
    }
    $count = $gal->deleteBatch($ids);
    log_activity('bulk_delete_gallery', "Bulk deleted $count photo(s)");
    flash('success', "$count photo(s) deleted.");
  }

  if ($action === 'bulk_category') {
    $new_category = trim($_POST['bulk_category'] ?? '');
    $ids          = array_map('intval', $_POST['ids'] ?? []);
    $count        = $gal->updateCategoryBatch($ids, $new_category);
    log_activity('bulk_update_gallery_category', "Bulk set $count photo(s) category to '" . ($new_category ?: '(none)') . "'");
    flash('success', "$count photo(s) updated.");
  }

  header('Location: ' . ADMIN_URL . '/gallery.php');
  exit;
}

$gal_obj    = new Gallery($conn);
$category   = $_GET['category'] ?? '';
$search     = trim($_GET['q'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 24;
$total      = $gal_obj->count($category, $search);
$pg         = paginate($total, $per_page, $page);
$photos     = $gal_obj->getPage($per_page, $pg['offset'], $category, $search);
$categories = $gal_obj->getCategories();
$form_categories = (new Category($conn))->getActive('gallery');

$qs = fn(int $p) => '?' . http_build_query(array_filter(['category' => $category, 'q' => $search, 'page' => $p]));

include __DIR__ . '/includes/header.php';
?>

<div class="card-header card-header--standalone">
  <div class="flex align-center gap-2 flex-wrap">
    <span class="card-title"><?= $total ?> Photo<?= $total !== 1 ? 's' : '' ?></span>
    <a href="?<?= http_build_query(array_filter(['q' => $search])) ?>" class="btn btn-sm <?= !$category ? 'btn-primary' : 'btn-secondary' ?>">All</a>
    <?php foreach ($categories as $cat): ?>
      <a href="?<?= http_build_query(array_filter(['category' => $cat, 'q' => $search])) ?>" class="btn btn-sm <?= $category === $cat ? 'btn-primary' : 'btn-secondary' ?>"><?= h($cat) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="flex align-center gap-2 flex-wrap">
    <form method="GET" class="flex gap-8px">
      <?php if ($category): ?><input type="hidden" name="category" value="<?= h($category) ?>"><?php endif; ?>
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search title or description…" class="search-input-sm">
      <button type="submit" class="btn btn-sm btn-secondary">Search</button>
      <?php if ($search): ?><a href="?<?= http_build_query(array_filter(['category' => $category])) ?>" class="btn btn-sm btn-secondary">Clear</a><?php endif; ?>
    </form>
    <?php if (has_role('editor')): ?>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-15">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Upload Photo
      </button>
    <?php endif; ?>
  </div>
</div>

<?php if (has_role('editor')): ?>
  <div class="card-header bulk-bar bulk-bar--standalone" id="bulk-bar">
    <span id="bulk-count" class="text-muted fs-13 fw-600"></span>
    <div class="flex-gap8-wrap">
      <button type="button" class="btn btn-sm btn-secondary" onclick="submitGalleryBulk('bulk_show')">Show Selected</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="submitGalleryBulk('bulk_hide')">Hide Selected</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="openModal('bulk-category-modal')">Set Category&hellip;</button>
      <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeletePhotos()">Delete Selected</button>
    </div>
  </div>
  <form id="bulk-form" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" id="bulk-action-field" value="">
    <input type="hidden" name="bulk_category" id="bulk-category-field" value="">
    <div id="bulk-ids-container"></div>
  </form>

  <!-- Bulk Set Category Modal -->
  <div class="modal fade" id="bulk-category-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-content modal-xs">
      <div class="modal-header">
        <span class="modal-title">Set Category for Selected</span>
        <button class="modal-close" onclick="closeModal('bulk-category-modal')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Category <span class="text-muted fw-normal">(leave blank to clear)</span></label>
          <select id="bulk-category-input">
            <option value="">None</option>
            <?php foreach ($form_categories as $c): ?><option value="<?= h($c['name']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('bulk-category-modal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="applyBulkCategory()">Apply</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($photos): ?>
  <div class="gallery-admin-grid">
    <?php foreach ($photos as $p): ?>
      <div class="gallery-item-admin">
        <?php if (has_role('editor')): ?>
          <input type="checkbox" class="row-check gallery-select-checkbox" value="<?= $p['id'] ?>" onchange="updateGalleryBulkBar()">
        <?php endif; ?>
        <?php if ($p['image_path']): ?>
          <img src="<?= h($p['image_path']) ?>" alt="<?= h($p['title']) ?>">
        <?php else: ?>
          <div class="gallery-svg-placeholder">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="3" y="3" width="18" height="18" rx="2" />
              <circle cx="8.5" cy="8.5" r="1.5" />
              <polyline points="21 15 16 10 5 21" />
            </svg>
          </div>
        <?php endif; ?>
        <?php if (!$p['is_active']): ?>
          <div class="gallery-hidden-badge">Hidden</div>
        <?php endif; ?>
        <div class="gallery-item-info">
          <div class="item-title"><?= h($p['title']) ?></div>
          <?php if ($p['category']): ?>
            <div class="text-muted fs-11 mb-6px"><?= h($p['category']) ?></div>
          <?php endif; ?>
          <div class="gallery-item-actions">
            <button class="btn btn-icon btn-sm btn-secondary" title="View" aria-label="View" onclick="openViewModal(<?= h(json_encode($p)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg></button>
            <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($p)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                </svg></button>
              <form id="tog-<?= $p['id'] ?>" method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-icon btn-sm btn-secondary" title="<?= $p['is_active'] ? 'Hide' : 'Show' ?>" aria-label="<?= $p['is_active'] ? 'Hide' : 'Show' ?>"><?= $p['is_active']
                                                                                                                                                                                      ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                                                                                                                                                                                      : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' ?></button>
              </form>
              <form id="del-g-<?= $p['id'] ?>" method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-g-<?= $p['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6" />
                  <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                  <path d="M10 11v6" />
                  <path d="M14 11v6" />
                  <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                </svg></button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pg['pages'] > 1): ?>
    <div class="pagination">
      <a href="<?= $qs(max(1, $page - 1)) ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">&#8249; Prev</a>
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a href="<?= $qs($i) ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <a href="<?= $qs(min($pg['pages'], $page + 1)) ?>" class="page-link <?= $page >= $pg['pages'] ? 'disabled' : '' ?>">Next &#8250;</a>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
      <rect x="3" y="3" width="18" height="18" rx="2" />
      <circle cx="8.5" cy="8.5" r="1.5" />
      <polyline points="21 15 16 10 5 21" />
    </svg>
    <?php if ($search || $category): ?>
      <p>No photos match your search<?= $search ? ' for "' . h($search) . '"' : '' ?>.</p>
    <?php else: ?>
      <p>No photos yet. Upload your first photo!</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal fade" id="add-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
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
          <div class="image-field">
            <label class="upload-area" for="a_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <p><strong>Click to upload</strong></p>
              <p class="fs-11 mt-4px">JPG, PNG, GIF, WEBP — max 5 MB</p>
            </label>
            <input type="file" id="a_image" name="image" accept="image/*" class="hidden" onchange="previewImage(this,'add-gal-prev')">
            <div class="thumb-col">
              <span class="thumb-col-label">Preview</span>
              <img id="add-gal-prev" src="" alt="Preview" class="image-thumb image-thumb--wide">
            </div>
          </div>
        </div>
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" required></div>
        <div class="form-row">
          <div class="form-group"><label>Category</label>
            <select name="category">
              <option value="">None</option>
              <?php foreach ($form_categories as $c): ?><option value="<?= h($c['name']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
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
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
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
          <div class="image-field">
            <label class="upload-area" for="eg_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <p><strong>Click to upload</strong></p>
            </label>
            <input type="file" id="eg_image" name="image" accept="image/*" class="hidden" onchange="previewImage(this,'edit-gal-prev')">
            <div id="eg_current_img"></div>
            <div class="thumb-col">
              <span class="thumb-col-label">New</span>
              <img id="edit-gal-prev" src="" alt="Preview" class="image-thumb image-thumb--wide">
            </div>
          </div>
        </div>
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" id="eg_title" required></div>
        <div class="form-row">
          <div class="form-group"><label>Category</label>
            <select name="category" id="eg_category">
              <option value="">None</option>
              <?php foreach ($form_categories as $c): ?><option value="<?= h($c['name']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="eg_display_order" min="0"></div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description" id="eg_description"></textarea></div>
        <div class="form-group form-group-row">
          <input type="checkbox" name="is_active" id="eg_active" class="w-auto">
          <label for="eg_active" class="fw-normal">Visible on public gallery</label>
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
<div class="modal fade" id="view-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content modal-md">
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
    ${p.image_path ? `<img src="${esc(p.image_path)}" class="view-cover-img-lg">` : ''}
    <div class="view-avatar-row">
      <div>
        <div class="view-name">${esc(p.title)}</div>
        <div class="tag-row">
          ${p.category ? `<span class="badge badge-category">${esc(p.category)}</span>` : ''}
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
    document.getElementById('eg_id').value = p.id;
    document.getElementById('eg_title').value = p.title;
    document.getElementById('eg_category').value = p.category || '';
    document.getElementById('eg_display_order').value = p.display_order || 0;
    document.getElementById('eg_description').value = p.description || '';
    document.getElementById('eg_active').checked = p.is_active == 1;
    const ci = document.getElementById('eg_current_img');
    ci.innerHTML = p.image_path ?
      '<div class="thumb-col"><span class="thumb-col-label">Current</span><img src="' + esc(p.image_path) + '" class="current-photo-thumb current-photo-thumb--wide"></div>' :
      '';
    openModal('edit-modal');
  }

  function getCheckedGalleryIds() {
    return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) {
      return c.value;
    });
  }

  function updateGalleryBulkBar() {
    var ids = getCheckedGalleryIds();
    document.getElementById('bulk-bar').style.display = ids.length ? 'flex' : 'none';
    document.getElementById('bulk-count').textContent = ids.length + ' selected';
  }

  function submitGalleryBulk(action) {
    var ids = getCheckedGalleryIds();
    if (!ids.length) return;
    document.getElementById('bulk-action-field').value = action;
    var container = document.getElementById('bulk-ids-container');
    container.innerHTML = '';
    ids.forEach(function(id) {
      var inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'ids[]';
      inp.value = id;
      container.appendChild(inp);
    });
    document.getElementById('bulk-form').submit();
  }

  function bulkDeletePhotos() {
    var ids = getCheckedGalleryIds();
    if (ids.length && confirm('Permanently delete ' + ids.length + ' selected photo(s)? This cannot be undone.')) {
      submitGalleryBulk('bulk_delete');
    }
  }

  function applyBulkCategory() {
    var ids = getCheckedGalleryIds();
    if (!ids.length) return;
    var category = document.getElementById('bulk-category-input').value;
    document.getElementById('bulk-category-field').value = category;
    closeModal('bulk-category-modal');
    submitGalleryBulk('bulk_category');
  }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>