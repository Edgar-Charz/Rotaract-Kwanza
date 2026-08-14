<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/MonthlyRecognition.php';
require_once dirname(__DIR__) . '/classes/RecognitionPhoto.php';

$recognitionId = (int) ($_GET['id'] ?? $_POST['recognition_id'] ?? 0);
$memberId = (int) ($_GET['member_id'] ?? $_POST['member_id'] ?? 0);
$recognition = (new MonthlyRecognition($conn))->findById($recognitionId);
if (!$recognition) {
  flash('error', 'Recognition record not found.');
  header('Location: ' . ADMIN_URL . '/recognitions.php');
  exit;
}
$recipient = null;
foreach ($recognition['members'] as $member) if ((int) $member['id'] === $memberId) $recipient = $member;
if (!$recipient) {
  flash('error', 'Recognition recipient not found.');
  header('Location: ' . ADMIN_URL . '/recognition_recipients.php?id=' . $recognitionId);
  exit;
}

$photoManager = new RecognitionPhoto($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  require_role('editor');
  if (($_POST['action'] ?? '') === 'upload') {
    $paths = upload_multi_images('images', 'recognition_photos');
    $start = count($photoManager->getByRecipient($recognitionId, $memberId));
    foreach ($paths as $index => $path) $photoManager->create($recognitionId, $memberId, $path, $start + $index);
    flash($paths ? 'success' : 'error', $paths ? count($paths) . ' photo(s) uploaded.' : 'No valid images were uploaded.');
  } elseif (($_POST['action'] ?? '') === 'delete') {
    $photo = $photoManager->findById((int) ($_POST['photo_id'] ?? 0));
    if ($photo && (int) $photo['recognition_id'] === $recognitionId && (int) $photo['member_id'] === $memberId) {
      delete_image($photo['image_path']);
      $photoManager->delete((int) $photo['id']);
      flash('success', 'Recipient photo deleted.');
    }
  }
  header('Location: ' . ADMIN_URL . '/recognition_photos.php?id=' . $recognitionId . '&member_id=' . $memberId);
  exit;
}

$photos = $photoManager->getByRecipient($recognitionId, $memberId);
$page_title = 'Recipient Recognition Photos';
include __DIR__ . '/includes/header.php';
?>
<div class="card-header card-header--standalone">
  <div><a href="recognition_recipients.php?id=<?= $recognitionId ?>" class="btn btn-sm btn-secondary back-link">&larr; Back to Recipients</a><div class="card-title"><?= h($recipient['first_name'] . ' ' . $recipient['last_name']) ?> — Recognition Photos</div></div>
  <?php if (has_role('editor')): ?><button class="btn btn-primary" onclick="openModal('recognition-photo-upload')">Upload Photos</button><?php endif; ?>
</div>
<p class="text-muted section-hint">These photos appear only for this recipient on the public recognition page. If no photos are uploaded, this member’s profile photo is used.</p>
<?php if ($photos): ?><div class="gallery-admin-grid"><?php foreach ($photos as $photo): ?><div class="gallery-item-admin"><img src="<?= h($photo['image_path']) ?>" alt="Recognition photo"><div class="gallery-item-info"><?php if (has_role('editor')): ?><form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="recognition_id" value="<?= $recognitionId ?>"><input type="hidden" name="member_id" value="<?= $memberId ?>"><input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>"><button class="btn btn-icon btn-sm btn-danger" title="Delete photo" aria-label="Delete photo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button></form><?php endif; ?></div></div><?php endforeach; ?></div><?php else: ?><div class="empty-state"><p>No recipient photos uploaded yet. The public page will use this member’s profile photo instead.</p></div><?php endif; ?>
<?php if (has_role('editor')): ?><div class="modal fade" id="recognition-photo-upload" tabindex="-1"><div class="modal-dialog modal-content"><div class="modal-header"><span class="modal-title">Upload Recipient Photos</span><button class="modal-close" onclick="closeModal('recognition-photo-upload')">&times;</button></div><form method="POST" enctype="multipart/form-data"><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="recognition_id" value="<?= $recognitionId ?>"><input type="hidden" name="member_id" value="<?= $memberId ?>"><div class="form-group"><label for="recognition-images">Photos</label><input id="recognition-images" type="file" name="images[]" accept="image/*" multiple required><p class="text-muted fs-12">You can select multiple JPG, PNG, GIF, or WEBP images (max 5 MB each).</p></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('recognition-photo-upload')">Cancel</button><button type="submit" class="btn btn-primary">Upload</button></div></form></div></div><?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
