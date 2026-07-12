<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

$page_title = 'Announcements';

// Must match the whitelist news.php uses when rendering — sanitizing at save
// time means every consumer of `content` gets an already-safe value.
const ANNOUNCEMENT_ALLOWED_TAGS = ['p','br','b','i','u','s','strong','em','ul','ol','li','h2','h3','a','blockquote'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $ann = new Announcement($conn);

    if ($action === 'add') {
        $title   = trim($_POST['title']);
        $content = sanitize_html_fragment(trim($_POST['content']), ANNOUNCEMENT_ALLOWED_TAGS);
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
        $id      = (int)$_POST['id'];
        $title   = trim($_POST['title']);
        $content = sanitize_html_fragment(trim($_POST['content']), ANNOUNCEMENT_ALLOWED_TAGS);
        $oldImg  = $ann->getImagePathById($id);
        $img     = upload_image('image', 'announcements') ?: $oldImg;
        $ann->update($id, $title, $content, $img,
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

    if ($action === 'bulk_publish' || $action === 'bulk_unpublish') {
        $published = $action === 'bulk_publish' ? 1 : 0;
        $ids       = array_map('intval', $_POST['ids'] ?? []);
        $count     = 0;
        foreach ($ids as $pid) {
            $p = $ann->findById($pid);
            if (!$p) continue;
            $ann->update($pid, $p['title'], $p['content'], $p['image_path'] ?? '', $p['category'], $published);
            $count++;
        }
        log_activity('bulk_update_announcement', "Bulk set $count post(s) " . ($published ? 'published' : 'unpublished'));
        flash('success', "$count post(s) updated.");
    }

    if ($action === 'bulk_delete') {
        $ids   = array_map('intval', $_POST['ids'] ?? []);
        $count = 0;
        foreach ($ids as $pid) {
            $p = $ann->findById($pid);
            if (!$p) continue;
            if ($p['image_path']) delete_image($p['image_path']);
            $ann->delete($pid);
            $count++;
        }
        log_activity('bulk_delete_announcement', "Bulk deleted $count post(s)");
        flash('success', "$count post(s) deleted.");
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
    <?php if (has_role('editor')): ?>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Post
    </button>
    <?php endif; ?>
  </div>

  <?php if (has_role('editor')): ?>
  <div class="card-header" id="bulk-bar" style="display:none;background:#fef6f0">
    <span id="bulk-count" class="text-muted" style="font-size:13px;font-weight:600"></span>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="button" class="btn btn-sm btn-secondary" onclick="submitAnnBulk('bulk_publish')">Publish Selected</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="submitAnnBulk('bulk_unpublish')">Unpublish Selected</button>
      <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeleteAnn()">Delete Selected</button>
    </div>
  </div>
  <form id="bulk-form" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" id="bulk-action-field" value="">
    <div id="bulk-ids-container"></div>
  </form>
  <?php endif; ?>

  <div class="table-wrap">
    <table id="dt-announcements">
      <thead>
        <tr>
          <?php if (has_role('editor')): ?><th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th><?php endif; ?>
          <th>Title</th><th>Category</th><th>Status</th><th>Date</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($posts): foreach ($posts as $p): ?>
        <tr>
          <?php if (has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $p['id'] ?>" onchange="updateAnnBulkBar()"></td><?php endif; ?>
          <td>
            <div class="fw-bold"><?= h($p['title']) ?></div>
            <div class="text-muted" style="font-size:12px;max-width:350px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h(strip_tags($p['content'])) ?></div>
          </td>
          <td><span class="badge badge-info" style="background:#d6eaff;color:#1a5fb4"><?= h($p['category']) ?></span></td>
          <td><?= $p['is_published'] ? '<span class="badge badge-approved">Published</span>' : '<span class="badge badge-pending">Draft</span>' ?></td>
          <td class="text-muted"><?= $p['created_at'] ? date('d M Y', strtotime($p['created_at'])) : '—' ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-icon btn-sm btn-secondary" title="View" aria-label="View" onclick="openViewModal(<?= h(json_encode($p)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($p)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <form id="tog-a-<?= $p['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-icon btn-sm btn-secondary" title="<?= $p['is_published']?'Unpublish':'Publish' ?>" aria-label="<?= $p['is_published']?'Unpublish':'Publish' ?>"><?= $p['is_published']
                  ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                  : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' ?></button>
              </form>
              <form id="del-a-<?= $p['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-a-<?= $p['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
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
  <div class="modal-dialog modal-content" style="max-width:700px">
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
        <div class="form-group mb-2">
          <label>Content *</label>
          <div id="add-quill-editor" style="min-height:160px;border:1.5px solid var(--border);border-radius:8px;background:#fff"></div>
          <textarea name="content" id="add-content-hidden" style="display:none"></textarea>
        </div>
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
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:700px">
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
        <div class="form-group mb-2">
          <label>Content *</label>
          <div id="edit-quill-editor" style="min-height:160px;border:1.5px solid var(--border);border-radius:8px;background:#fff"></div>
          <textarea name="content" id="ea_content" style="display:none"></textarea>
        </div>
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
<div class="modal fade" id="view-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:620px">
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
// ── Quill editors ─────────────────────────────────────────────────────────────
const quillToolbar = [
  ['bold','italic','underline','strike'],
  [{'list':'ordered'},{'list':'bullet'}],
  [{'header':[2,3,false]}],
  ['link'],
  ['clean']
];

const addQuill = new Quill('#add-quill-editor', { theme:'snow', modules:{ toolbar: quillToolbar } });
const editQuill = new Quill('#edit-quill-editor', { theme:'snow', modules:{ toolbar: quillToolbar } });

function quillIsEmpty(q) {
  return q.getText().trim().length === 0;
}
function showQuillError(editorId, msg) {
  const el = document.getElementById(editorId);
  el.style.borderColor = '#e74c3c';
  let err = el.nextElementSibling;
  if (!err || !err.classList.contains('quill-err')) {
    err = document.createElement('div');
    err.className = 'quill-err';
    err.style.cssText = 'color:#e74c3c;font-size:12px;margin-top:4px';
    el.insertAdjacentElement('afterend', err);
  }
  err.textContent = msg;
}
function clearQuillError(editorId) {
  const el = document.getElementById(editorId);
  el.style.borderColor = '';
  const err = el.nextElementSibling;
  if (err && err.classList.contains('quill-err')) err.remove();
}

// Sync + validate on submit
document.querySelector('#add-modal form').addEventListener('submit', function(e) {
  clearQuillError('add-quill-editor');
  if (quillIsEmpty(addQuill)) {
    e.preventDefault();
    showQuillError('add-quill-editor', 'Content is required.');
    return;
  }
  document.getElementById('add-content-hidden').value = addQuill.root.innerHTML;
});
document.querySelector('#edit-modal form').addEventListener('submit', function(e) {
  clearQuillError('edit-quill-editor');
  if (quillIsEmpty(editQuill)) {
    e.preventDefault();
    showQuillError('edit-quill-editor', 'Content is required.');
    return;
  }
  document.getElementById('ea_content').value = editQuill.root.innerHTML;
});

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
      <div class="view-dd">${p.content || ''}</div>
    </div>`;
  openModal('view-modal');
}

$(document).ready(function() {
  $('#dt-announcements').DataTable({
    pageLength: 25,
    order: [[<?= has_role('editor') ? 4 : 3 ?>, 'desc']],
    columnDefs: [{ orderable: false, targets: <?= has_role('editor') ? '[0, 5]' : '[4]' ?> }]
  });
});
function openEditModal(p) {
  document.getElementById('ea_id').value       = p.id;
  document.getElementById('ea_title').value    = p.title;
  document.getElementById('ea_category').value = p.category;
  document.getElementById('ea_content').value  = p.content;
  document.getElementById('ea_pub').checked    = p.is_published == 1;
  const prev = document.getElementById('ea_img_preview');
  prev.innerHTML = p.image_path ? '<img src="'+esc(p.image_path)+'" style="max-height:80px;border-radius:6px">' : '';
  // Load content into Quill (treat as HTML if it looks like HTML, else plain text)
  if (p.content && p.content.trim().startsWith('<')) {
    editQuill.root.innerHTML = p.content;
  } else {
    editQuill.setText(p.content || '');
  }
  openModal('edit-modal');
}

function getCheckedAnnIds() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) { return c.value; });
}
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(function(c) { c.checked = cb.checked; });
  updateAnnBulkBar();
}
function updateAnnBulkBar() {
  var ids = getCheckedAnnIds();
  document.getElementById('bulk-bar').style.display = ids.length ? 'flex' : 'none';
  document.getElementById('bulk-count').textContent = ids.length + ' selected';
}
function submitAnnBulk(action) {
  var ids = getCheckedAnnIds();
  if (!ids.length) return;
  document.getElementById('bulk-action-field').value = action;
  var container = document.getElementById('bulk-ids-container');
  container.innerHTML = '';
  ids.forEach(function(id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('bulk-form').submit();
}
function bulkDeleteAnn() {
  var ids = getCheckedAnnIds();
  if (ids.length && confirm('Permanently delete ' + ids.length + ' selected post(s)? This cannot be undone.')) {
    submitAnnBulk('bulk_delete');
  }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
