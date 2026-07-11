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
        $paths = upload_multi_images('images', 'event_photos');
        foreach ($paths as $p) {
            $photo_obj->create($event_id, $p);
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

    header('Location: ' . ADMIN_URL . '/event_photos.php?event=' . $event_id);
    exit;
}

$photos = $photo_obj->getByEvent($event_id);

include __DIR__ . '/includes/header.php';
?>

<div class="card-header" style="background:#fff;border-radius:10px;padding:14px 20px;margin-bottom:20px;box-shadow:var(--shadow);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
  <div>
    <a href="events.php" class="btn btn-sm btn-secondary" style="margin-bottom:6px;display:inline-block">&larr; Back to Events</a>
    <div class="card-title"><?= h($event['title']) ?> — <?= count($photos) ?> Photo<?= count($photos) !== 1 ? 's' : '' ?></div>
  </div>
  <?php if (has_role('editor')): ?>
  <button class="btn btn-primary" onclick="openModal('add-modal')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Upload Photos
  </button>
  <?php endif; ?>
</div>

<?php if ($photos): ?>
<div class="gallery-admin-grid">
  <?php foreach ($photos as $p): ?>
  <div class="gallery-item-admin">
    <img src="<?= h($p['image_path']) ?>" alt="Event photo">
    <div class="gallery-item-info">
      <?php if (has_role('editor')): ?>
      <div class="gallery-item-actions">
        <form id="del-ep-<?= $p['id'] ?>" method="POST" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
        </form>
        <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-ep-<?= $p['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
  <p>No photos yet for this event. Upload some for visitors to see on the public event page.</p>
</div>
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
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <p><strong>Click to choose photos</strong></p>
            <p style="font-size:11px;margin-top:4px">JPG, PNG, GIF, WEBP — max 5 MB each. You can select multiple files.</p>
          </label>
          <input type="file" id="ep_images" name="images[]" accept="image/*" multiple style="display:none" onchange="document.getElementById('ep_count').textContent = this.files.length + ' file(s) selected';">
          <p id="ep_count" class="text-muted" style="font-size:12px;margin-top:6px"></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Upload</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
