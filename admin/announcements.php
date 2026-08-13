<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';
require_once dirname(__DIR__) . '/classes/Category.php';

$page_title = 'Announcements';

// Friendly labels for the categories seeded by migrate_categories.php; any
// category an admin adds later just falls back to a title-cased slug.
$cat_labels = [
  'news'         => 'News',
  'minutes'      => 'Meeting Minutes',
  'notice'       => 'Notice',
  'announcement' => 'Announcement',
];
$form_categories = (new Category($conn))->getActive('announcement');

// Must match the whitelist news.php uses when rendering — sanitizing at save
// time means every consumer of `content` gets an already-safe value.
const ANNOUNCEMENT_ALLOWED_TAGS = ['p', 'br', 'b', 'i', 'u', 's', 'strong', 'em', 'ul', 'ol', 'li', 'h2', 'h3', 'a', 'blockquote'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  require_role('editor');
  $action = $_POST['action'] ?? '';
  $ann = new Announcement($conn);

  if ($action === 'add') {
    $title     = trim($_POST['title']);
    $content   = sanitize_html_fragment(trim($_POST['content']), ANNOUNCEMENT_ALLOWED_TAGS);
    $slug      = slugify($title) . '-' . substr(uniqid(), -4);
    $img       = upload_image('image', 'announcements') ?: '';
    $published = isset($_POST['is_published']) ? 1 : 0;
    $ann->create($title, $slug, $content, $img, $_POST['category'] ?? 'news', $published);
    log_activity('add_announcement', "Added: $title");
    flash('success', 'Announcement published.');
    // Brand new post — no "was it already published" history to check,
    // just notify if it went live immediately.
    if ($published) notify_newsletter_subscribers_of_post($conn, ['title' => $title, 'content' => $content, 'slug' => $slug]);
  }

  if ($action === 'edit') {
    $id        = (int)$_POST['id'];
    $title     = trim($_POST['title']);
    $content   = sanitize_html_fragment(trim($_POST['content']), ANNOUNCEMENT_ALLOWED_TAGS);
    $before    = $ann->findById($id);
    $oldImg    = $before['image_path'] ?? '';
    // A fresh upload always wins over a same-request "remove" checkbox.
    if (!empty($_FILES['image']['name'])) {
      $img = upload_image('image', 'announcements') ?: $oldImg;
    } elseif (!empty($_POST['remove_image'])) {
      $img = '';
    } else {
      $img = $oldImg;
    }
    $published = isset($_POST['is_published']) ? 1 : 0;
    $ann->update($id, $title, $content, $img, $_POST['category'] ?? 'news', $published);
    if ($img !== $oldImg && $oldImg) delete_image($oldImg);
    log_activity('edit_announcement', "Edited: $title");
    flash('success', 'Announcement updated.');
    // Only notify on the draft→published transition, never on an edit
    // to an already-published post (that would re-spam every subscriber
    // on every typo fix).
    if ($published && $before && !(int)$before['is_published']) {
      notify_newsletter_subscribers_of_post($conn, ['title' => $title, 'content' => $content, 'slug' => $before['slug']]);
    }
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
    $id     = (int)$_POST['id'];
    $before = $ann->findById($id);
    $ann->togglePublished($id);
    flash('success', 'Visibility toggled.');
    if ($before && !(int)$before['is_published']) {
      notify_newsletter_subscribers_of_post($conn, ['title' => $before['title'], 'content' => $before['content'], 'slug' => $before['slug']]);
    }
  }

  if ($action === 'bulk_publish' || $action === 'bulk_unpublish') {
    $published = $action === 'bulk_publish' ? 1 : 0;
    $ids       = array_map('intval', $_POST['ids'] ?? []);
    $count     = 0;
    foreach ($ids as $pid) {
      $p = $ann->findById($pid);
      if (!$p) continue;
      $wasUnpublished = !(int)$p['is_published'];
      $ann->update($pid, $p['title'], $p['content'], $p['image_path'] ?? '', $p['category'], $published);
      $count++;
      if ($published && $wasUnpublished) {
        notify_newsletter_subscribers_of_post($conn, ['title' => $p['title'], 'content' => $p['content'], 'slug' => $p['slug']]);
      }
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

$filter = in_array($_GET['cat'] ?? '', array_column($form_categories, 'name'), true) ? $_GET['cat'] : '';
$posts  = (new Announcement($conn))->getAll($filter);

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <div class="flex align-center gap-2 flex-wrap flex-grow-1">
      <a href="?" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
      <?php foreach ($form_categories as $c): $cat = $c['name']; ?>
        <a href="?cat=<?= $cat ?>" class="btn btn-sm <?= $filter === $cat ? 'btn-primary' : 'btn-secondary' ?>"><?= h($cat_labels[$cat] ?? ucfirst($cat)) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (has_role('editor')): ?>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-15">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        New Post
      </button>
    <?php endif; ?>
  </div>

  <?php if (has_role('editor')): ?>
    <div class="card-header bulk-bar" id="bulk-bar">
      <span id="bulk-count" class="text-muted fs-13 fw-600"></span>
      <div class="flex-gap8-wrap">
        <button type="button" class="btn btn-sm btn-secondary" onclick="submitAnnBulk('bulk_publish')">Publish Selected</button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="submitAnnBulk('bulk_unpublish')">Unpublish Selected</button>
        <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeleteAnn()">Delete Selected</button>
      </div>
    </div>
    <form id="bulk-form" method="POST" class="hidden">
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
          <th>Title</th>
          <th>Category</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($posts): foreach ($posts as $p): ?>
            <tr>
              <?php if (has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $p['id'] ?>" onchange="updateAnnBulkBar()"></td><?php endif; ?>
              <td>
                <div class="fw-bold"><?= h($p['title']) ?></div>
                <div class="text-muted text-clamp-350"><?= h(strip_tags($p['content'])) ?></div>
              </td>
              <td><span class="badge badge-category"><?= h($p['category']) ?></span></td>
              <td><?= $p['is_published'] ? '<span class="badge badge-approved">Published</span>' : '<span class="badge badge-pending">Draft</span>' ?></td>
              <td class="text-muted"><?= $p['created_at'] ? date('d M Y', strtotime($p['created_at'])) : '—' ?></td>
              <td>
                <div class="table-actions">
                  <button class="btn btn-icon btn-sm btn-secondary" title="View" aria-label="View" onclick="openViewModal(<?= h(json_encode($p)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg></button>
                  <?php if (has_role('editor')): ?>
                    <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($p)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                      </svg></button>
                    <form id="tog-a-<?= $p['id'] ?>" method="POST" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= $p['id'] ?>">
                      <button type="submit" class="btn btn-icon btn-sm btn-secondary" title="<?= $p['is_published'] ? 'Unpublish' : 'Publish' ?>" aria-label="<?= $p['is_published'] ? 'Unpublish' : 'Publish' ?>"><?= $p['is_published']
                                                                                                                                                                                                              ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                                                                                                                                                                                                              : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' ?></button>
                    </form>
                    <form id="del-a-<?= $p['id'] ?>" method="POST" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    </form>
                    <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-a-<?= $p['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                        <path d="M10 11v6" />
                        <path d="M14 11v6" />
                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                      </svg></button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
        <?php endforeach;
        endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="add-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content modal-700">
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
              <?php foreach ($form_categories as $c): ?><option value="<?= h($c['name']) ?>"><?= h($cat_labels[$c['name']] ?? ucfirst($c['name'])) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-group-row pt-24px">
            <input type="checkbox" name="is_published" id="a_pub" class="w-auto" checked>
            <label for="a_pub" class="fw-normal">Publish immediately</label>
          </div>
        </div>
        <div class="form-group mb-2">
          <label>Content *</label>
          <div id="add-quill-editor" class="quill-editor"></div>
          <textarea name="content" id="add-content-hidden" class="hidden"></textarea>
        </div>
        <div class="form-group">
          <label>Cover Image (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="aa_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <p><strong>Upload cover</strong></p>
            </label>
            <input type="file" id="aa_image" name="image" accept="image/*" class="hidden" onchange="previewImage(this,'add-ann-prev')">
            <div class="thumb-col">
              <span class="thumb-col-label">Preview</span>
              <img id="add-ann-prev" src="" alt="Preview" class="image-thumb image-thumb--wide">
            </div>
          </div>
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
  <div class="modal-dialog modal-content modal-700">
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
              <?php foreach ($form_categories as $c): ?><option value="<?= h($c['name']) ?>"><?= h($cat_labels[$c['name']] ?? ucfirst($c['name'])) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-group-row pt-24px">
            <input type="checkbox" name="is_published" id="ea_pub" class="w-auto">
            <label for="ea_pub" class="fw-normal">Published</label>
          </div>
        </div>
        <div class="form-group mb-2">
          <label>Content *</label>
          <div id="edit-quill-editor" class="quill-editor"></div>
          <textarea name="content" id="ea_content" class="hidden"></textarea>
        </div>
        <div class="form-group">
          <label>Replace Image (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="ea_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <p><strong>Upload cover</strong></p>
            </label>
            <input type="file" id="ea_image" name="image" accept="image/*" class="hidden" onchange="previewImage(this,'edit-ann-prev')">
            <div id="ea_img_preview"></div>
            <div class="thumb-col">
              <span class="thumb-col-label">New</span>
              <img id="edit-ann-prev" src="" alt="Preview" class="image-thumb image-thumb--wide">
            </div>
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

<!-- View Modal -->
<div class="modal fade" id="view-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content modal-620">
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" integrity="sha384-VvSC4PGxeMkOaAmyuDGZECjY2dkqdO/IdBYBUK+BCYNc3WIvRxHLUzQ5OSgUaMA7" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js" integrity="sha384-hcxmSutM10NL6iGBAA0LStIhy+kWJxfrhqWVMRuABZH5Vqztexq2nBz/Xnfllly9" crossorigin="anonymous"></script>
<script>
  // ── Quill editors ─────────────────────────────────────────────────────────────
  const quillToolbar = [
    ['bold', 'italic', 'underline', 'strike'],
    [{
      'list': 'ordered'
    }, {
      'list': 'bullet'
    }],
    [{
      'header': [2, 3, false]
    }],
    ['link'],
    ['clean']
  ];

  const addQuill = new Quill('#add-quill-editor', {
    theme: 'snow',
    modules: {
      toolbar: quillToolbar
    }
  });
  const editQuill = new Quill('#edit-quill-editor', {
    theme: 'snow',
    modules: {
      toolbar: quillToolbar
    }
  });

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
    ${p.image_path ? `<img src="${esc(p.image_path)}" class="view-cover-img">` : ''}
    <div class="view-avatar-row">
      <div>
        <div class="view-name">${esc(p.title)}</div>
        <div class="tag-row">
          <span class="badge badge-category">${esc(p.category)}</span>
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
      order: [
        [<?= has_role('editor') ? 4 : 3 ?>, 'desc']
      ],
      columnDefs: [{
        orderable: false,
        targets: <?= has_role('editor') ? '[0, 5]' : '[4]' ?>
      }]
    });
  });

  function openEditModal(p) {
    document.getElementById('ea_id').value = p.id;
    document.getElementById('ea_title').value = p.title;
    document.getElementById('ea_category').value = p.category;
    document.getElementById('ea_content').value = p.content;
    document.getElementById('ea_pub').checked = p.is_published == 1;
    const prev = document.getElementById('ea_img_preview');
    prev.innerHTML = p.image_path ?
      '<div class="thumb-col"><span class="thumb-col-label">Current</span><img src="' + esc(p.image_path) + '" class="current-photo-thumb current-photo-thumb--wide">' +
      '<label class="remove-photo-label"><input type="checkbox" name="remove_image" value="1" class="w-auto"> Remove</label></div>' :
      '';
    // Load content into Quill (treat as HTML if it looks like HTML, else plain text)
    if (p.content && p.content.trim().startsWith('<')) {
      editQuill.root.innerHTML = p.content;
    } else {
      editQuill.setText(p.content || '');
    }
    openModal('edit-modal');
  }

  function getCheckedAnnIds() {
    return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) {
      return c.value;
    });
  }

  function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(function(c) {
      c.checked = cb.checked;
    });
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
      inp.type = 'hidden';
      inp.name = 'ids[]';
      inp.value = id;
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