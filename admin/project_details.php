<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Project.php';
require_once dirname(__DIR__) . '/classes/ProjectPhoto.php';
require_once dirname(__DIR__) . '/classes/Pledge.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$project_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  require_role('editor');
  $project_id = (int) ($_POST['id'] ?? 0);
  $editor = new Project($conn);
  $existing = $project_id ? $editor->findById($project_id) : null;
  $title = trim($_POST['title'] ?? '');
  if (!$existing || $title === '') {
    flash('error', 'Title is required.');
  } else {
    try {
      $old_image = $existing['image_path'] ?? '';
      if (!empty($_FILES['image']['name'])) {
        $image = upload_image('image', 'projects') ?: $old_image;
        if ($image === $old_image) flash('error', 'Project updated, but the new image could not be uploaded.');
      } elseif (!empty($_POST['remove_image'])) {
        $image = '';
      } else {
        $image = $old_image;
      }
      $start_date = trim($_POST['start_date'] ?? '') ?: null;
      $end_date   = trim($_POST['end_date'] ?? '') ?: null;
      $funding_goal = money_or_null($_POST['funding_goal'] ?? '');
      $editor->update(
        $project_id,
        $title,
        trim($_POST['description'] ?? ''),
        trim($_POST['impact_stat'] ?? ''),
        trim($_POST['impact_label'] ?? ''),
        trim($_POST['icon_type'] ?? '') ?: 'heart',
        $_POST['status'] ?? 'active',
        isset($_POST['is_featured']) ? 1 : 0,
        $image,
        clean_url($_POST['instagram_url'] ?? ''),
        clean_url($_POST['tiktok_url'] ?? ''),
        clean_url($_POST['x_url'] ?? ''),
        $start_date,
        $end_date,
        $funding_goal
      );
      if ($image !== $old_image && $old_image) delete_image($old_image);
      log_activity('edit_project', 'Edited project ID ' . $project_id . ': ' . $title);
      if (!isset($_SESSION['flash'])) flash('success', 'Project updated.');
    } catch (mysqli_sql_exception $e) {
      flash('error', 'Could not update project.');
    }
  }
  header('Location: ' . ADMIN_URL . '/project_details.php?id=' . $project_id);
  exit;
}

$project = $project_id ? (new Project($conn))->findById($project_id) : null;
if (!$project) {
  flash('error', 'Project not found.');
  header('Location: ' . ADMIN_URL . '/projects.php');
  exit;
}

$photos = (new ProjectPhoto($conn))->getByProject($project_id);
$icons = icon_options();

$signup_count = 0;
try {
  $stmt = $conn->prepare('SELECT COUNT(*) FROM project_signups WHERE project_id = ?');
  $stmt->bind_param('i', $project_id);
  $stmt->execute();
  $stmt->bind_result($signup_count);
  $stmt->fetch();
  $stmt->close();
} catch (Throwable $e) {}

$raised_amount = (new Pledge($conn))->sumConfirmedAmountForProject($project_id);
$page_title = $project['title'];
include __DIR__ . '/includes/header.php';
?>

<style>
  .project-detail-card { overflow: hidden; border: 0; box-shadow: 0 14px 35px rgba(24, 23, 49, .09); }
  .project-detail-card > .card-header { padding: 25px 32px; }
  .project-detail-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .project-detail-hero { min-height: 250px; padding: 28px 32px; display: flex; align-items: center; gap: 28px; color: #fff; background: linear-gradient(120deg, #c0396b, #862555); }
  .project-detail-cover { width: 250px; height: 185px; border: 3px solid rgba(255,255,255,.75); border-radius: 14px; object-fit: cover; box-shadow: 0 10px 26px rgba(0,0,0,.24); }
  .project-detail-cover-icon { width: 180px; height: 180px; border: 3px solid rgba(255,255,255,.75); border-radius: 14px; background: rgba(255,255,255,.15); box-shadow: 0 10px 26px rgba(0,0,0,.24); color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .project-detail-cover-icon svg { width: 80px; height: 80px; }
  .project-detail-cover-button { padding: 0; border: 0; background: transparent; cursor: zoom-in; }
  .project-detail-hero h2 { margin: 0 0 10px; color: #fff; font-size: 28px; }
  .project-detail-hero .badge { background: #fff; color: #8d2856; margin-right: 6px; }
  .project-detail-content { padding: 30px 32px 34px; }
  .project-detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
  .project-detail-field { padding: 14px 15px; border: 1px solid var(--border); border-radius: 10px; background: #fff; min-height: 74px; }
  .project-detail-description { margin-top: 22px; }
  .project-detail-description .view-dd { padding: 15px 16px; min-height: 90px; line-height: 1.65; white-space: pre-wrap; background: #f8f9fc; border: 1px solid var(--border); border-radius: 10px; }
  .project-detail-gallery { margin-top: 30px; padding-top: 26px; border-top: 1px solid var(--border); }
  .project-detail-gallery-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 16px; }
  .project-gallery-trigger { width: 100%; padding: 0; overflow: hidden; border: 0; background: transparent; cursor: zoom-in; }
  .project-gallery-trigger img { display: block; width: 100%; height: 100%; }
  .project-photo-lightbox { display: none; position: fixed; inset: 0; z-index: 11000; align-items: center; justify-content: center; padding: 28px 88px; background: rgba(9, 7, 16, .94); }
  .project-photo-lightbox.is-open { display: flex; }
  .project-photo-lightbox img { max-width: 100%; max-height: 84vh; object-fit: contain; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.55); }
  .project-photo-lightbox .lightbox-close, .project-photo-lightbox .lightbox-nav { position: fixed; border: 0; color: #fff; background: rgba(255,255,255,.16); cursor: pointer; }
  .project-photo-lightbox .lightbox-close { top: 20px; right: 24px; width: 46px; height: 46px; border-radius: 50%; font-size: 28px; }
  .project-photo-lightbox .lightbox-nav { top: 50%; width: 50px; height: 62px; border-radius: 10px; font-size: 35px; transform: translateY(-50%); }
  .project-photo-lightbox .lightbox-prev { left: 24px; } .project-photo-lightbox .lightbox-next { right: 24px; }
  .project-photo-lightbox .lightbox-caption { position: fixed; bottom: 22px; left: 20px; right: 20px; color: #fff; text-align: center; }
  @media (max-width: 800px) { .project-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .project-detail-hero { align-items: flex-start; } }
  @media (max-width: 600px) { .project-detail-card > .card-header, .project-detail-hero { padding: 22px; } .project-detail-card > .card-header { flex-direction: column; align-items: flex-start; } .project-detail-content { padding: 22px; } .project-detail-hero { min-height: 0; flex-direction: column; } .project-detail-cover { width: 100%; height: 210px; } .project-detail-grid { grid-template-columns: 1fr; } }
</style>

<div class="card project-detail-card">
  <div class="card-header">
    <div><div class="text-muted fs-13">Projects</div><h2 class="m-0">Project Details</h2></div>
    <div class="project-detail-actions">
      <?php if (has_role('editor')): ?><button type="button" class="btn btn-primary" onclick="openModal('edit-project-modal')">Edit Project</button><?php endif; ?>
      <a href="projects.php" class="btn btn-secondary">&larr; Back to Projects</a>
    </div>
  </div>
  <div class="project-detail-hero">
    <?php if (!empty($project['image_path'])): ?>
      <button type="button" class="project-detail-cover-button" id="project-cover-open" aria-label="Open project cover image"><img src="<?= h($project['image_path']) ?>" alt="<?= h($project['title']) ?>" class="project-detail-cover"></button>
    <?php else: ?>
      <div class="project-detail-cover-icon" aria-label="Project icon"><?= icon_svg($project['icon_type'] ?: 'heart') ?></div>
    <?php endif; ?>
    <div><h2><?= h($project['title']) ?></h2><span class="badge badge-<?= h($project['status']) ?>"><?= h($project['status']) ?></span><?php if ($project['is_featured']): ?><span class="badge badge-featured">Featured</span><?php endif; ?></div>
  </div>
  <div class="project-detail-content">
    <div class="project-detail-grid">
      <div class="project-detail-field"><div class="view-dt">Timeline</div><div class="view-dd"><?php if ($project['start_date'] || $project['end_date']): ?><?= $project['start_date'] ? h(date('d M Y', strtotime($project['start_date']))) : '?' ?> &ndash; <?= $project['end_date'] ? h(date('d M Y', strtotime($project['end_date']))) : 'Ongoing' ?><?php else: ?>—<?php endif; ?></div></div>
      <div class="project-detail-field"><div class="view-dt">Impact</div><div class="view-dd"><?= h($project['impact_stat'] ?: '—') ?><?= $project['impact_label'] ? ' (' . h($project['impact_label']) . ')' : '' ?></div></div>
      <div class="project-detail-field"><div class="view-dt">Funding</div><div class="view-dd"><?php if ($project['funding_goal']): ?><span class="fw-bold"><?= number_format($raised_amount, 2) ?></span> / <?= number_format((float)$project['funding_goal'], 2) ?><?php else: ?>Not seeking sponsorship<?php endif; ?></div></div>
      <div class="project-detail-field"><div class="view-dt">Signups</div><div class="view-dd"><a href="project_signups.php?project=<?= $project_id ?>"><?= $signup_count ?> Signup<?= $signup_count === 1 ? '' : 's' ?></a></div></div>
      <div class="project-detail-field"><div class="view-dt">Icon</div><div class="view-dd"><?= h($icons[$project['icon_type']] ?? $project['icon_type'] ?: 'Heart') ?></div></div>
      <div class="project-detail-field"><div class="view-dt">Status</div><div class="view-dd"><span class="badge badge-<?= h($project['status']) ?>"><?= h($project['status']) ?></span></div></div>
      <div class="project-detail-field"><div class="view-dt">Created</div><div class="view-dd"><?= !empty($project['created_at']) ? h(date('d M Y', strtotime($project['created_at']))) : '—' ?></div></div>
      <div class="project-detail-field"><div class="view-dt">Instagram</div><div class="view-dd"><?php if ($project['instagram_url']): ?><a href="<?= h($project['instagram_url']) ?>" target="_blank" rel="noopener">Open post</a><?php else: ?>—<?php endif; ?></div></div>
      <div class="project-detail-field"><div class="view-dt">TikTok / X</div><div class="view-dd"><?php if ($project['tiktok_url']): ?><a href="<?= h($project['tiktok_url']) ?>" target="_blank" rel="noopener">TikTok</a><?php endif; ?><?php if ($project['tiktok_url'] && $project['x_url']): ?> · <?php endif; ?><?php if ($project['x_url']): ?><a href="<?= h($project['x_url']) ?>" target="_blank" rel="noopener">X</a><?php endif; ?><?php if (!$project['tiktok_url'] && !$project['x_url']): ?>—<?php endif; ?></div></div>
    </div>
    <div class="project-detail-description"><div class="view-dt">Description</div><div class="view-dd"><?= nl2br(h($project['description'] ?: '—')) ?></div></div>
    <section class="project-detail-gallery">
      <div class="project-detail-gallery-head"><div><div class="view-dt">Project Photos</div><h3 class="m-0"><?= count($photos) ?> Photo<?= count($photos) === 1 ? '' : 's' ?></h3></div><?php if (has_role('editor')): ?><a href="project_photos.php?project=<?= $project_id ?>" class="btn btn-primary">Manage Photos</a><?php endif; ?></div>
      <?php if ($photos): ?><div class="gallery-admin-grid"><?php foreach ($photos as $index => $photo): ?><div class="gallery-item-admin"><button type="button" class="project-gallery-trigger" data-photo-index="<?= $index ?>" aria-label="Open project photo <?= $index + 1 ?>"><img src="<?= h($photo['image_path']) ?>" alt="<?= h($photo['caption'] ?: 'Project photo') ?>"></button><?php if ($photo['caption']): ?><div class="gallery-item-info"><div class="item-title fs-12"><?= h($photo['caption']) ?></div></div><?php endif; ?></div><?php endforeach; ?></div><?php else: ?><p class="text-muted">No project photos have been added yet.</p><?php endif; ?>
    </section>
  </div>
</div>

<?php if (!empty($project['image_path'])): ?>
<div class="project-photo-lightbox" id="project-cover-lightbox" role="dialog" aria-modal="true" aria-label="Project cover image"><img src="<?= h($project['image_path']) ?>" alt="<?= h($project['title']) ?>"><button type="button" class="lightbox-close" aria-label="Close image">&times;</button></div>
<?php endif; ?>

<?php if ($photos): ?>
<div class="project-photo-lightbox" id="project-gallery-lightbox" role="dialog" aria-modal="true" aria-label="Project photo viewer"><button type="button" class="lightbox-close" aria-label="Close photos">&times;</button><button type="button" class="lightbox-nav lightbox-prev" aria-label="Previous photo">&#8249;</button><img id="project-lightbox-image" src="" alt=""><button type="button" class="lightbox-nav lightbox-next" aria-label="Next photo">&#8250;</button><div class="lightbox-caption" id="project-lightbox-caption"></div></div>
<?php endif; ?>

<?php if (has_role('editor')): ?>
<div class="modal fade" id="edit-project-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content modal-xl">
    <div class="modal-header"><span class="modal-title">Edit <?= h($project['title']) ?></span><button type="button" class="modal-close" onclick="closeModal('edit-project-modal')">&times;</button></div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $project_id ?>">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" value="<?= h($project['title']) ?>" required></div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description"><?= h($project['description'] ?? '') ?></textarea></div>
        <div class="form-group mb-2">
          <label>Replace Cover Image (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="ep_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <p><strong>Upload cover</strong></p>
            </label>
            <input type="file" id="ep_image" name="image" accept="image/*" class="hidden" onchange="previewImage(this,'edit-proj-prev')">
            <?php if (!empty($project['image_path'])): ?>
              <div class="thumb-col">
                <span class="thumb-col-label">Current</span>
                <img src="<?= h($project['image_path']) ?>" class="current-photo-thumb current-photo-thumb--wide" alt="">
                <label class="remove-photo-label"><input type="checkbox" name="remove_image" value="1" class="w-auto"> Remove current image</label>
              </div>
            <?php endif; ?>
            <div class="thumb-col">
              <span class="thumb-col-label">New</span>
              <img id="edit-proj-prev" src="" alt="" class="image-thumb image-thumb--wide">
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Impact Stat</label><input type="text" name="impact_stat" value="<?= h($project['impact_stat'] ?? '') ?>" placeholder="e.g. 1,200+"></div>
          <div class="form-group"><label>Impact Label</label><input type="text" name="impact_label" value="<?= h($project['impact_label'] ?? '') ?>" placeholder="e.g. children reached"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Start Date <span class="text-muted fw-normal">(optional)</span></label><input type="date" name="start_date" value="<?= h($project['start_date'] ?? '') ?>"></div>
          <div class="form-group"><label>End Date <span class="text-muted fw-normal">(optional, blank = ongoing)</span></label><input type="date" name="end_date" value="<?= h($project['end_date'] ?? '') ?>"></div>
        </div>
        <div class="form-group mb-2">
          <label>Funding Goal <span class="text-muted fw-normal">(optional)</span></label>
          <input type="text" inputmode="decimal" class="money-input" name="funding_goal" value="<?= $project['funding_goal'] !== null ? h(number_format((float)$project['funding_goal'], 2, '.', '')) : '' ?>" placeholder="Leave blank if not seeking sponsorship">
        </div>
        <div class="form-row">
          <div class="form-group"><label>Icon <span class="text-muted fw-normal">(shown when there's no cover image)</span></label>
            <select name="icon_type">
              <?php foreach ($icons as $k => $label): ?>
                <option value="<?= h($k) ?>" <?= ($project['icon_type'] ?? 'heart') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <?php foreach (Project::STATUSES as $st => $label): ?>
                <option value="<?= $st ?>" <?= $project['status'] === $st ? 'selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Instagram URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="instagram_url" value="<?= h($project['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/p/..."></div>
          <div class="form-group"><label>TikTok URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="tiktok_url" value="<?= h($project['tiktok_url'] ?? '') ?>" placeholder="https://tiktok.com/@.../video/..."></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>X URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="x_url" value="<?= h($project['x_url'] ?? '') ?>" placeholder="https://x.com/.../status/..."></div>
        </div>
        <div class="form-group form-group-row mt-1">
          <input type="checkbox" name="is_featured" id="detail-featured" class="w-auto" <?= $project['is_featured'] ? 'checked' : '' ?>>
          <label for="detail-featured" class="fw-normal">Show on homepage</label>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('edit-project-modal')">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
  (function () {
    function closeLightbox(lightbox) { if (lightbox) lightbox.classList.remove('is-open'); }
    <?php if (!empty($project['image_path'])): ?>
      var coverLightbox = document.getElementById('project-cover-lightbox');
      var coverOpenBtn = document.getElementById('project-cover-open');
      if (coverOpenBtn && coverLightbox) {
        coverOpenBtn.addEventListener('click', function () { coverLightbox.classList.add('is-open'); });
        coverLightbox.querySelector('.lightbox-close').addEventListener('click', function () { closeLightbox(coverLightbox); });
        coverLightbox.addEventListener('click', function (event) { if (event.target === coverLightbox) closeLightbox(coverLightbox); });
      }
    <?php endif; ?>
    <?php if ($photos): ?>
      var photos = <?= json_encode(array_map(fn($photo) => ['src' => $photo['image_path'], 'caption' => $photo['caption'] ?? ''], $photos), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
      var galleryLightbox = document.getElementById('project-gallery-lightbox'); var currentIndex = 0;
      function showPhoto(index) { currentIndex = (index + photos.length) % photos.length; var photo = photos[currentIndex]; document.getElementById('project-lightbox-image').src = photo.src; document.getElementById('project-lightbox-image').alt = photo.caption || 'Project photo ' + (currentIndex + 1); document.getElementById('project-lightbox-caption').textContent = photo.caption || ('Photo ' + (currentIndex + 1) + ' of ' + photos.length); }
      document.querySelectorAll('.project-gallery-trigger').forEach(function (button) { button.addEventListener('click', function () { showPhoto(Number(button.dataset.photoIndex)); galleryLightbox.classList.add('is-open'); }); });
      if (galleryLightbox) {
        galleryLightbox.querySelector('.lightbox-close').addEventListener('click', function () { closeLightbox(galleryLightbox); });
        galleryLightbox.querySelector('.lightbox-prev').addEventListener('click', function () { showPhoto(currentIndex - 1); });
        galleryLightbox.querySelector('.lightbox-next').addEventListener('click', function () { showPhoto(currentIndex + 1); });
        galleryLightbox.addEventListener('click', function (event) { if (event.target === galleryLightbox) closeLightbox(galleryLightbox); });
      }
    <?php endif; ?>
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') document.querySelectorAll('.project-photo-lightbox.is-open').forEach(closeLightbox);
      <?php if ($photos): ?>
        if (galleryLightbox && galleryLightbox.classList.contains('is-open') && event.key === 'ArrowLeft') showPhoto(currentIndex - 1);
        if (galleryLightbox && galleryLightbox.classList.contains('is-open') && event.key === 'ArrowRight') showPhoto(currentIndex + 1);
      <?php endif; ?>
    });
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
