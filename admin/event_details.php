<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Event.php';
require_once dirname(__DIR__) . '/classes/EventPhoto.php';
require_once dirname(__DIR__) . '/classes/EventRSVP.php';
require_once dirname(__DIR__) . '/classes/Category.php';

$event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  require_role('editor');
  $event_id = (int) ($_POST['id'] ?? 0);
  $editor = new Event($conn);
  $existing = $event_id ? $editor->findById($event_id) : null;
  $title = trim($_POST['title'] ?? '');
  $date_ok = DateTime::createFromFormat('Y-m-d', $_POST['event_date'] ?? '') !== false;
  if (!$existing || $title === '' || !$date_ok) {
    flash('error', 'Enter an event title and a valid date.');
  } else {
    try {
      $old_image = $existing['image_path'] ?? '';
      if (!empty($_FILES['image']['name'])) {
        $image = upload_image('image', 'events') ?: $old_image;
        if ($image === $old_image) flash('error', 'Event updated, but the new image could not be uploaded.');
      } elseif (!empty($_POST['remove_image'])) {
        $image = '';
      } else {
        $image = $old_image;
      }
      $capacity = trim($_POST['capacity'] ?? '') !== '' ? max(0, (int) $_POST['capacity']) : null;
      $editor->update($event_id, $title, $_POST['event_date'], trim($_POST['event_time']), trim($_POST['location']), trim($_POST['description']), trim($_POST['category']) ?: 'General', $_POST['status'], isset($_POST['is_featured']) ? 1 : 0, $image, clean_url($_POST['instagram_url'] ?? ''), clean_url($_POST['tiktok_url'] ?? ''), clean_url($_POST['x_url'] ?? ''), $capacity);
      if ($image !== $old_image && $old_image) delete_image($old_image);
      log_activity('edit_event', 'Edited event ID ' . $event_id . ': ' . $title);
      if (!isset($_SESSION['flash'])) flash('success', 'Event updated.');
    } catch (mysqli_sql_exception $e) {
      flash('error', 'Could not update event.');
    }
  }
  header('Location: ' . ADMIN_URL . '/event_details.php?id=' . $event_id);
  exit;
}
$event = $event_id ? (new Event($conn))->findById($event_id) : null;
if (!$event) {
  flash('error', 'Event not found.');
  header('Location: ' . ADMIN_URL . '/events.php');
  exit;
}

$photos = (new EventPhoto($conn))->getByEvent($event_id);
$categories = (new Category($conn))->getActive('event');
$rsvp_count = (int) db_val($conn, 'SELECT COUNT(*) FROM event_rsvps WHERE event_id=?', [$event_id]);
$guest_count = (new EventRSVP($conn))->getGuestCount($event_id);
$page_title = $event['title'];
include __DIR__ . '/includes/header.php';
?>

<style>
  .event-detail-card { overflow: hidden; border: 0; box-shadow: 0 14px 35px rgba(24, 23, 49, .09); }
  .event-detail-card > .card-header { padding: 25px 32px; }
  .event-detail-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .event-detail-hero { min-height: 250px; padding: 28px 32px; display: flex; align-items: center; gap: 28px; color: #fff; background: linear-gradient(120deg, #c0396b, #862555); }
  .event-detail-cover { width: 250px; height: 185px; border: 3px solid rgba(255,255,255,.75); border-radius: 14px; object-fit: cover; box-shadow: 0 10px 26px rgba(0,0,0,.24); }
  .event-detail-cover-button { padding: 0; border: 0; background: transparent; cursor: zoom-in; }
  .event-detail-hero h2 { margin: 0 0 10px; color: #fff; font-size: 28px; }
  .event-detail-hero .badge { background: #fff; color: #8d2856; margin-right: 6px; }
  .event-detail-content { padding: 30px 32px 34px; }
  .event-detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
  .event-detail-field { padding: 14px 15px; border: 1px solid var(--border); border-radius: 10px; background: #fff; min-height: 74px; }
  .event-detail-description { margin-top: 22px; }
  .event-detail-description .view-dd { padding: 15px 16px; min-height: 90px; line-height: 1.65; white-space: pre-wrap; background: #f8f9fc; border: 1px solid var(--border); border-radius: 10px; }
  .event-detail-gallery { margin-top: 30px; padding-top: 26px; border-top: 1px solid var(--border); }
  .event-detail-gallery-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 16px; }
  .event-gallery-trigger { width: 100%; padding: 0; overflow: hidden; border: 0; background: transparent; cursor: zoom-in; }
  .event-gallery-trigger img { display: block; width: 100%; height: 100%; }
  .event-photo-lightbox { display: none; position: fixed; inset: 0; z-index: 11000; align-items: center; justify-content: center; padding: 28px 88px; background: rgba(9, 7, 16, .94); }
  .event-photo-lightbox.is-open { display: flex; }
  .event-photo-lightbox img { max-width: 100%; max-height: 84vh; object-fit: contain; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.55); }
  .event-photo-lightbox .lightbox-close, .event-photo-lightbox .lightbox-nav { position: fixed; border: 0; color: #fff; background: rgba(255,255,255,.16); cursor: pointer; }
  .event-photo-lightbox .lightbox-close { top: 20px; right: 24px; width: 46px; height: 46px; border-radius: 50%; font-size: 28px; }
  .event-photo-lightbox .lightbox-nav { top: 50%; width: 50px; height: 62px; border-radius: 10px; font-size: 35px; transform: translateY(-50%); }
  .event-photo-lightbox .lightbox-prev { left: 24px; } .event-photo-lightbox .lightbox-next { right: 24px; }
  .event-photo-lightbox .lightbox-caption { position: fixed; bottom: 22px; left: 20px; right: 20px; color: #fff; text-align: center; }
  @media (max-width: 800px) { .event-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .event-detail-hero { align-items: flex-start; } }
  @media (max-width: 600px) { .event-detail-card > .card-header, .event-detail-hero { padding: 22px; } .event-detail-card > .card-header { flex-direction: column; align-items: flex-start; } .event-detail-content { padding: 22px; } .event-detail-hero { min-height: 0; flex-direction: column; } .event-detail-cover { width: 100%; height: 210px; } .event-detail-grid { grid-template-columns: 1fr; } }
</style>

<div class="card event-detail-card">
  <div class="card-header">
    <div><div class="text-muted fs-13">Events</div><h2 class="m-0">Event Details</h2></div>
    <div class="event-detail-actions">
      <?php if (has_role('editor')): ?><button type="button" class="btn btn-primary" onclick="openModal('edit-event-modal')">Edit Event</button><?php endif; ?>
      <a href="events.php" class="btn btn-secondary">&larr; Back to Events</a>
    </div>
  </div>
  <div class="event-detail-hero">
    <?php if (!empty($event['image_path'])): ?><button type="button" class="event-detail-cover-button" id="event-cover-open" aria-label="Open event cover image"><img src="<?= h($event['image_path']) ?>" alt="<?= h($event['title']) ?>" class="event-detail-cover"></button><?php endif; ?>
    <div><h2><?= h($event['title']) ?></h2><span class="badge badge-<?= h($event['status']) ?>"><?= h($event['status']) ?></span><?php if ($event['is_featured']): ?><span class="badge badge-featured">Featured</span><?php endif; ?></div>
  </div>
  <div class="event-detail-content">
    <div class="event-detail-grid">
      <div class="event-detail-field"><div class="view-dt">Date</div><div class="view-dd"><?= $event['event_date'] ? h(date('d M Y', strtotime($event['event_date']))) : '—' ?></div></div>
      <div class="event-detail-field"><div class="view-dt">Time</div><div class="view-dd"><?= h($event['event_time'] ?: '—') ?></div></div>
      <div class="event-detail-field"><div class="view-dt">Location</div><div class="view-dd"><?= h($event['location'] ?: '—') ?></div></div>
      <div class="event-detail-field"><div class="view-dt">Category</div><div class="view-dd"><?= h($event['category'] ?: '—') ?></div></div>
      <div class="event-detail-field"><div class="view-dt">RSVPs</div><div class="view-dd"><a href="rsvps.php?event=<?= $event_id ?>"><?= $rsvp_count ?> RSVP<?= $rsvp_count === 1 ? '' : 's' ?></a></div></div>
      <div class="event-detail-field"><div class="view-dt">Capacity</div><div class="view-dd"><?php if ($event['capacity'] !== null): ?><?= $guest_count ?> / <?= (int) $event['capacity'] ?><?php else: ?>Unlimited<?php endif; ?></div></div>
      <div class="event-detail-field"><div class="view-dt">Created</div><div class="view-dd"><?= !empty($event['created_at']) ? h(date('d M Y', strtotime($event['created_at']))) : '—' ?></div></div>
      <div class="event-detail-field"><div class="view-dt">Instagram</div><div class="view-dd"><?php if ($event['instagram_url']): ?><a href="<?= h($event['instagram_url']) ?>" target="_blank" rel="noopener">Open post</a><?php else: ?>—<?php endif; ?></div></div>
      <div class="event-detail-field"><div class="view-dt">TikTok / X</div><div class="view-dd"><?php if ($event['tiktok_url']): ?><a href="<?= h($event['tiktok_url']) ?>" target="_blank" rel="noopener">TikTok</a><?php endif; ?><?php if ($event['tiktok_url'] && $event['x_url']): ?> · <?php endif; ?><?php if ($event['x_url']): ?><a href="<?= h($event['x_url']) ?>" target="_blank" rel="noopener">X</a><?php endif; ?><?php if (!$event['tiktok_url'] && !$event['x_url']): ?>—<?php endif; ?></div></div>
    </div>
    <div class="event-detail-description"><div class="view-dt">Description</div><div class="view-dd"><?= nl2br(h($event['description'] ?: '—')) ?></div></div>
    <section class="event-detail-gallery">
      <div class="event-detail-gallery-head"><div><div class="view-dt">Event Photos</div><h3 class="m-0"><?= count($photos) ?> Photo<?= count($photos) === 1 ? '' : 's' ?></h3></div><?php if (has_role('editor')): ?><a href="event_photos.php?event=<?= $event_id ?>" class="btn btn-primary">Manage Photos</a><?php endif; ?></div>
      <?php if ($photos): ?><div class="gallery-admin-grid"><?php foreach ($photos as $index => $photo): ?><div class="gallery-item-admin"><button type="button" class="event-gallery-trigger" data-photo-index="<?= $index ?>" aria-label="Open event photo <?= $index + 1 ?>"><img src="<?= h($photo['image_path']) ?>" alt="<?= h($photo['caption'] ?: 'Event photo') ?>"></button><?php if ($photo['caption']): ?><div class="gallery-item-info"><div class="item-title fs-12"><?= h($photo['caption']) ?></div></div><?php endif; ?></div><?php endforeach; ?></div><?php else: ?><p class="text-muted">No event photos have been added yet.</p><?php endif; ?>
    </section>
  </div>
</div>

<?php if (!empty($event['image_path'])): ?>
<div class="event-photo-lightbox" id="event-cover-lightbox" role="dialog" aria-modal="true" aria-label="Event cover image"><img src="<?= h($event['image_path']) ?>" alt="<?= h($event['title']) ?>"><button type="button" class="lightbox-close" aria-label="Close image">&times;</button></div>
<?php endif; ?>

<?php if ($photos): ?>
<div class="event-photo-lightbox" id="event-gallery-lightbox" role="dialog" aria-modal="true" aria-label="Event photo viewer"><button type="button" class="lightbox-close" aria-label="Close photos">&times;</button><button type="button" class="lightbox-nav lightbox-prev" aria-label="Previous photo">&#8249;</button><img id="event-lightbox-image" src="" alt=""><button type="button" class="lightbox-nav lightbox-next" aria-label="Next photo">&#8250;</button><div class="lightbox-caption" id="event-lightbox-caption"></div></div>
<?php endif; ?>

<?php if (has_role('editor')): ?>
<div class="modal fade" id="edit-event-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content modal-xl">
    <div class="modal-header"><span class="modal-title">Edit <?= h($event['title']) ?></span><button type="button" class="modal-close" onclick="closeModal('edit-event-modal')">&times;</button></div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $event_id ?>">
        <div class="form-group"><label>Title *</label><input type="text" name="title" value="<?= h($event['title']) ?>" required maxlength="200"></div>
        <div class="form-row"><div class="form-group"><label>Date *</label><input type="date" name="event_date" value="<?= h($event['event_date']) ?>" required></div><div class="form-group"><label>Time</label><input type="text" name="event_time" value="<?= h($event['event_time'] ?? '') ?>"></div></div>
        <div class="form-row"><div class="form-group"><label>Location</label><input type="text" name="location" value="<?= h($event['location'] ?? '') ?>"></div><div class="form-group"><label>Category</label><select name="category"><?php foreach ($categories as $category): ?><option value="<?= h($category['name']) ?>" <?= $event['category'] === $category['name'] ? 'selected' : '' ?>><?= h($category['name']) ?></option><?php endforeach; ?><?php if (!$categories): ?><option value="<?= h($event['category']) ?>"><?= h($event['category'] ?: 'General') ?></option><?php endif; ?></select></div></div>
        <div class="form-group"><label>Capacity <span class="text-muted fw-normal">(leave blank for unlimited)</span></label><input type="number" name="capacity" min="0" value="<?= $event['capacity'] !== null ? (int) $event['capacity'] : '' ?>"></div>
        <div class="form-group"><label>Description</label><textarea name="description"><?= h($event['description'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Replace Cover Image</label><input type="file" name="image" accept="image/*"><?php if (!empty($event['image_path'])): ?><label class="remove-photo-label"><input type="checkbox" name="remove_image" value="1" class="w-auto"> Remove current image</label><?php endif; ?></div>
        <div class="form-row"><div class="form-group"><label>Instagram URL</label><input type="text" name="instagram_url" value="<?= h($event['instagram_url'] ?? '') ?>"></div><div class="form-group"><label>TikTok URL</label><input type="text" name="tiktok_url" value="<?= h($event['tiktok_url'] ?? '') ?>"></div></div>
        <div class="form-row"><div class="form-group"><label>X URL</label><input type="text" name="x_url" value="<?= h($event['x_url'] ?? '') ?>"></div><div class="form-group"><label>Status</label><select name="status"><?php foreach (Event::STATUSES as $status => $label): ?><option value="<?= $status ?>" <?= $event['status'] === $status ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="form-group form-group-row"><input type="checkbox" name="is_featured" id="detail-featured" class="w-auto" <?= $event['is_featured'] ? 'checked' : '' ?>><label for="detail-featured" class="fw-normal">Featured on homepage</label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('edit-event-modal')">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
  (function () {
    function closeLightbox(lightbox) { lightbox.classList.remove('is-open'); }
    <?php if (!empty($event['image_path'])): ?>
      var coverLightbox = document.getElementById('event-cover-lightbox');
      document.getElementById('event-cover-open').addEventListener('click', function () { coverLightbox.classList.add('is-open'); });
      coverLightbox.querySelector('.lightbox-close').addEventListener('click', function () { closeLightbox(coverLightbox); });
      coverLightbox.addEventListener('click', function (event) { if (event.target === coverLightbox) closeLightbox(coverLightbox); });
    <?php endif; ?>
    <?php if ($photos): ?>
      var photos = <?= json_encode(array_map(fn($photo) => ['src' => $photo['image_path'], 'caption' => $photo['caption'] ?? ''], $photos), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
      var galleryLightbox = document.getElementById('event-gallery-lightbox'); var currentIndex = 0;
      function showPhoto(index) { currentIndex = (index + photos.length) % photos.length; var photo = photos[currentIndex]; document.getElementById('event-lightbox-image').src = photo.src; document.getElementById('event-lightbox-image').alt = photo.caption || 'Event photo ' + (currentIndex + 1); document.getElementById('event-lightbox-caption').textContent = photo.caption || ('Photo ' + (currentIndex + 1) + ' of ' + photos.length); }
      document.querySelectorAll('.event-gallery-trigger').forEach(function (button) { button.addEventListener('click', function () { showPhoto(Number(button.dataset.photoIndex)); galleryLightbox.classList.add('is-open'); }); });
      galleryLightbox.querySelector('.lightbox-close').addEventListener('click', function () { closeLightbox(galleryLightbox); });
      galleryLightbox.querySelector('.lightbox-prev').addEventListener('click', function () { showPhoto(currentIndex - 1); }); galleryLightbox.querySelector('.lightbox-next').addEventListener('click', function () { showPhoto(currentIndex + 1); });
      galleryLightbox.addEventListener('click', function (event) { if (event.target === galleryLightbox) closeLightbox(galleryLightbox); });
    <?php endif; ?>
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') document.querySelectorAll('.event-photo-lightbox.is-open').forEach(closeLightbox); <?php if ($photos): ?>if (galleryLightbox.classList.contains('is-open') && event.key === 'ArrowLeft') showPhoto(currentIndex - 1); if (galleryLightbox.classList.contains('is-open') && event.key === 'ArrowRight') showPhoto(currentIndex + 1);<?php endif; ?> });
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
