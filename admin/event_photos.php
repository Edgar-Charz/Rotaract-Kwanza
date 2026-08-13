<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Event.php';
require_once dirname(__DIR__) . '/classes/EventPhoto.php';

$event_id = (int)($_GET['event'] ?? 0);
$event    = (new Event($conn))->findById($event_id);

if (!$event) {
  flash('error', 'Event not found.');
  header('Location: ' . ADMIN_URL . '/events.php');
  exit;
}

$page_title = 'Photos — ' . $event['title'];
$photo_obj  = new EventPhoto($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  require_role('editor');
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $paths    = upload_multi_images('images', 'event_photos');
    $captions = $_POST['captions'] ?? [];
    $next_order = $photo_obj->countByEvent($event_id);
    foreach ($paths as $i => $p) {
      $photo_obj->create($event_id, $p, $next_order + $i, trim($captions[$i] ?? ''));
    }
    if ($paths) {
      log_activity('add_event_photos', count($paths) . ' photo(s) added to event: ' . $event['title']);
      flash('success', count($paths) . ' photo(s) uploaded.');
    } else {
      flash('error', 'No valid images uploaded. Allowed: JPG, PNG, GIF, WEBP (max 5MB each).');
    }
  }

  if ($action === 'delete') {
    $id    = (int)$_POST['id'];
    $photo = $photo_obj->findById($id);
    if ($photo && (int)$photo['event_id'] === $event_id) {
      delete_image($photo['image_path']);
      $photo_obj->delete($id);
      flash('success', 'Photo deleted.');
    }
  }

  if ($action === 'update_caption') {
    $id      = (int) $_POST['id'];
    $caption = trim($_POST['caption'] ?? '');
    $photo   = $photo_obj->findById($id);
    if ($photo && (int) $photo['event_id'] === $event_id) {
      $photo_obj->updateCaption($id, $caption);
      flash('success', 'Caption updated.');
    }
  }

  if ($action === 'reorder') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    foreach ($ids as $i => $pid) {
      $photo = $photo_obj->findById($pid);
      if (!$photo || (int) $photo['event_id'] !== $event_id) continue;
      $photo_obj->updateOrder($pid, $i);
    }
    flash('success', 'Photo order updated.');
  }

  header('Location: ' . ADMIN_URL . '/event_photos.php?event=' . $event_id);
  exit;
}

$photos = $photo_obj->getByEvent($event_id);

include __DIR__ . '/includes/header.php';
?>

<div class="card-header card-header--standalone">
  <div>
    <a href="events.php" class="btn btn-sm btn-secondary back-link">&larr; Back to Events</a>
    <div class="card-title"><?= h($event['title']) ?> — <?= count($photos) ?> Photo<?= count($photos) !== 1 ? 's' : '' ?></div>
  </div>
  <?php if (has_role('editor')): ?>
    <div class="flex-gap8-wrap">
      <?php if (count($photos) > 1): ?>
        <button class="btn btn-secondary" onclick="openReorderModal()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-15">
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
          </svg>
          Reorder
        </button>
      <?php endif; ?>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-15">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Upload Photos
      </button>
    </div>
  <?php endif; ?>
</div>

<?php if ($photos): ?>
  <div class="gallery-admin-grid">
    <?php foreach ($photos as $p): ?>
      <div class="gallery-item-admin">
        <img src="<?= h($p['image_path']) ?>" alt="<?= h($p['caption'] ?: 'Event photo') ?>">
        <div class="gallery-item-info">
          <?php if ($p['caption']): ?><div class="item-title fs-12"><?= h($p['caption']) ?></div><?php endif; ?>
          <?php if (has_role('editor')): ?>
            <div class="gallery-item-actions">
              <button class="btn btn-icon btn-sm btn-info" title="Edit Caption" aria-label="Edit Caption" onclick="openCaptionModal(<?= (int) $p['id'] ?>, <?= h(json_encode($p['caption'] ?? '')) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                </svg></button>
              <form id="del-ep-<?= $p['id'] ?>" method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-ep-<?= $p['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6" />
                  <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                  <path d="M10 11v6" />
                  <path d="M14 11v6" />
                  <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                </svg></button>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
      <rect x="3" y="3" width="18" height="18" rx="2" />
      <circle cx="8.5" cy="8.5" r="1.5" />
      <polyline points="21 15 16 10 5 21" />
    </svg>
    <p>No photos yet for this event. Upload some for visitors to see on the public event page.</p>
  </div>
<?php endif; ?>

<?php if (has_role('editor')): ?>
  <!-- Edit Caption Modal -->
  <div class="modal fade" id="caption-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-content modal-400">
      <div class="modal-header">
        <span class="modal-title">Edit Caption</span>
        <button class="modal-close" onclick="closeModal('caption-modal')">&times;</button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="update_caption">
          <input type="hidden" name="id" id="cap_id">
          <div class="form-group">
            <label>Caption <span class="text-muted fw-normal">(shown in the public photo lightbox)</span></label>
            <input type="text" name="caption" id="cap_caption" maxlength="255">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('caption-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Caption</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    function openCaptionModal(id, caption) {
      document.getElementById('cap_id').value = id;
      document.getElementById('cap_caption').value = caption || '';
      openModal('caption-modal');
    }
  </script>

  <!-- Reorder Modal -->
  <div class="modal fade" id="reorder-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-content modal-480">
      <div class="modal-header">
        <span class="modal-title">Reorder Photos</span>
        <button class="modal-close" onclick="closeModal('reorder-modal')">&times;</button>
      </div>
      <div class="modal-body">
        <p class="text-muted fs-12-5 mb-12px">Drag to reorder. This sets the order photos appear in on the public event page.</p>
        <div id="reorder-list"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('reorder-modal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveReorder()">Save Order</button>
      </div>
    </div>
  </div>

  <form id="reorder-form" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="reorder">
    <div id="reorder-ids-container"></div>
  </form>
<?php endif; ?>

<!-- Upload Modal -->
<div class="modal fade" id="add-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Upload Photos</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group mb-2">
          <label>Photos *</label>
          <label class="upload-area" for="ep_images">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="17 8 12 3 7 8" />
              <line x1="12" y1="3" x2="12" y2="15" />
            </svg>
            <p><strong>Click to choose photos</strong></p>
            <p class="fs-11 mt-4px">JPG, PNG, GIF, WEBP — max 5 MB each. You can select multiple files.</p>
          </label>
          <input type="file" id="ep_images" name="images[]" accept="image/*" multiple class="hidden" onchange="renderCaptionInputs(this)">
          <p id="ep_count" class="text-muted fs-12 mt-6px"></p>
          <div id="ep_caption_list" class="mt-6px"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Upload</button>
      </div>
    </form>
  </div>
</div>

<script>
  function renderCaptionInputs(input) {
    document.getElementById('ep_count').textContent = input.files.length ?
      input.files.length + ' file(s) selected' :
      '';
    const list = document.getElementById('ep_caption_list');
    list.innerHTML = '';
    if (!input.files || !input.files.length) return;

    Array.from(input.files).forEach(function(file) {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;gap:8px;margin-top:8px';

      const label = document.createElement('span');
      label.style.cssText = 'font-size:11.5px;color:var(--text-muted);width:110px;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
      label.title = file.name;
      label.textContent = file.name;

      const captionInput = document.createElement('input');
      captionInput.type = 'text';
      captionInput.name = 'captions[]';
      captionInput.placeholder = 'Caption (optional)';
      captionInput.style.cssText = 'flex:1;padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:12.5px;font-family:inherit';

      row.appendChild(label);
      row.appendChild(captionInput);
      list.appendChild(row);
    });
  }

  var allEventPhotos = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'image_path' => $p['image_path'], 'caption' => $p['caption'] ?? ''], $photos)) ?>;

  function openReorderModal() {
    const list = document.getElementById('reorder-list');
    list.innerHTML = '';
    allEventPhotos.forEach(function(p) {
      const row = document.createElement('div');
      row.className = 'reorder-row';
      row.draggable = true;
      row.dataset.id = p.id;
      row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;margin-bottom:6px;background:#fff;cursor:grab';

      const thumb = document.createElement('img');
      thumb.src = p.image_path;
      thumb.style.cssText = 'width:44px;height:32px;border-radius:5px;object-fit:cover;flex-shrink:0';

      const label = document.createElement('div');
      label.style.cssText = 'flex:1;font-size:12.5px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
      label.textContent = p.caption || '(no caption)';

      const handle = document.createElement('div');
      handle.innerHTML = '&#8942;&#8942;';
      handle.style.cssText = 'color:var(--text-muted);letter-spacing:-3px;flex-shrink:0';

      row.appendChild(thumb);
      row.appendChild(label);
      row.appendChild(handle);
      list.appendChild(row);
    });
    attachReorderDragHandlers(list);
    openModal('reorder-modal');
  }

  function attachReorderDragHandlers(list) {
    let dragEl = null;
    list.querySelectorAll('.reorder-row').forEach(function(row) {
      row.addEventListener('dragstart', function() {
        dragEl = row;
        row.style.opacity = '0.4';
      });
      row.addEventListener('dragend', function() {
        row.style.opacity = '1';
      });
      row.addEventListener('dragover', function(e) {
        e.preventDefault();
        if (!dragEl || dragEl === row) return;
        const bounding = row.getBoundingClientRect();
        const offset = e.clientY - bounding.top;
        if (offset > bounding.height / 2) {
          row.after(dragEl);
        } else {
          row.before(dragEl);
        }
      });
    });
  }

  function saveReorder() {
    const rows = document.querySelectorAll('#reorder-list .reorder-row');
    const container = document.getElementById('reorder-ids-container');
    container.innerHTML = '';
    rows.forEach(function(row) {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'ids[]';
      inp.value = row.dataset.id;
      container.appendChild(inp);
    });
    document.getElementById('reorder-form').submit();
  }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>