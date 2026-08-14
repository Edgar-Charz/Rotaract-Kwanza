<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Member.php';

$member_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  require_role('editor');
  $member_id = (int) ($_POST['id'] ?? 0);
  $email = trim($_POST['email'] ?? '');
  $editable_member = $member_id ? (new Member($conn))->findById($member_id) : null;

  if (!$editable_member || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Please enter a valid email address.');
  } else {
    try {
      $editor = new Member($conn);
      $editor->update($member_id, trim($_POST['first_name']), trim($_POST['last_name']), $email, trim($_POST['phone']), trim($_POST['occupation']), trim($_POST['why_join']), $_POST['status'], trim($_POST['notes'] ?? ''), trim($_POST['bio'] ?? ''), clean_url($_POST['linkedin_url'] ?? ''), clean_url($_POST['instagram_url'] ?? ''), trim($_POST['year_of_study'] ?? ''), trim($_POST['birthday'] ?? '') ?: null);
      if (!empty($_FILES['photo']['name'])) {
        $new_photo = upload_image('photo', 'members');
        if ($new_photo) {
          if ($editable_member['photo_path']) delete_image($editable_member['photo_path']);
          $editor->updatePhoto($member_id, $new_photo);
        } else {
          flash('error', 'Member updated, but the new photo could not be uploaded.');
        }
      } elseif (!empty($_POST['remove_photo']) && $editable_member['photo_path']) {
        delete_image($editable_member['photo_path']);
        $editor->updatePhoto($member_id, '');
      }
      log_activity('edit_member', 'Edited member ID ' . $member_id . ' from member details.');
      if (!isset($_SESSION['flash'])) flash('success', 'Member updated.');
    } catch (mysqli_sql_exception $e) {
      flash('error', $e->getCode() == 1062 ? 'Email already exists.' : 'Could not update member.');
    }
  }
  header('Location: ' . ADMIN_URL . '/member_details.php?id=' . $member_id);
  exit;
}

$member = $member_id ? (new Member($conn))->findById($member_id) : null;
if (!$member) {
  flash('error', 'Member not found.');
  header('Location: ' . ADMIN_URL . '/members.php');
  exit;
}

$dues_year = (int) date('Y');
$dues_status = db_val($conn, 'SELECT status FROM member_dues WHERE member_id=? AND year=?', [$member_id, $dues_year]);
$page_title = trim($member['first_name'] . ' ' . $member['last_name']);
include __DIR__ . '/includes/header.php';
?>

<style>
  .member-detail-card { overflow: hidden; border: 0; box-shadow: 0 14px 35px rgba(24, 23, 49, .09); }
  .member-detail-card > .card-header { padding: 25px 32px; background: #fff; }
  .member-detail-card .modal-body { padding: 0 32px 34px; }
  .member-detail-card .view-avatar-row { min-height: 245px; margin: 0 -32px 28px; padding: 26px 32px; color: #fff; border: 0; background: linear-gradient(120deg, #c0396b, #862555); }
  .member-detail-card .view-avatar-row .view-name { color: #fff; font-size: 26px; font-weight: 700; margin-bottom: 8px; }
  .member-detail-card .view-avatar-row .badge { background: #fff; color: #8d2856; }
  .member-photo-button { width: 182px; height: 194px; padding: 0; overflow: hidden; border: 3px solid rgba(255,255,255,.78); border-radius: 14px; background: transparent; cursor: zoom-in; box-shadow: 0 10px 26px rgba(0,0,0,.24); }
  .member-photo-button img { display: block; width: 100%; height: 100%; object-fit: cover; }
  .member-detail-card .view-avatar-row .view-avatar-init { width: 182px !important; height: 194px !important; border: 3px solid rgba(255,255,255,.75); border-radius: 14px; background: rgba(255,255,255,.2); font-size: 52px !important; box-shadow: 0 10px 26px rgba(0,0,0,.24); }
  .member-detail-card .view-dl { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
  .member-detail-card .view-dl > div { border: 1px solid var(--border); border-radius: 10px; padding: 14px 15px; min-height: 74px; background: #fff; }
  .member-detail-card .view-dd { overflow-wrap: anywhere; }
  .member-detail-card .view-full { margin-top: 20px; }
  .member-detail-card .view-full .view-dd { min-height: 88px; padding: 14px 16px; border: 1px solid var(--border); background: #f8f9fc; border-radius: 10px; }
  .member-detail-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .member-photo-preview { display: none; position: fixed; inset: 0; z-index: 11000; align-items: center; justify-content: center; padding: 28px; background: rgba(9, 7, 16, .92); }
  .member-photo-preview.is-open { display: flex; }
  .member-photo-preview img { max-width: min(100%, 900px); max-height: 88vh; object-fit: contain; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
  .member-photo-preview button { position: fixed; top: 20px; right: 24px; color: #fff; background: rgba(255,255,255,.16); border: 0; border-radius: 50%; width: 46px; height: 46px; font-size: 28px; cursor: pointer; }
  .member-edit-dialog { max-width: 960px !important; }
  @media (max-width: 900px) { .member-detail-card .view-dl { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 620px) { .member-detail-card > .card-header { padding: 22px; align-items: flex-start; flex-direction: column; } .member-detail-card .modal-body { padding: 0 22px 24px; } .member-detail-card .view-avatar-row { min-height: 0; margin: 0 -22px 24px; padding: 24px 22px; } .member-photo-button, .member-detail-card .view-avatar-row .view-avatar-init { width: 116px !important; height: 134px !important; } .member-detail-card .view-avatar-row .view-name { font-size: 22px; } .member-detail-card .view-dl { grid-template-columns: 1fr; } }
</style>

<div class="card member-detail-card">
  <div class="card-header">
    <div><div class="text-muted fs-13">Membership</div><h2 class="m-0">Member Details</h2></div>
    <div class="member-detail-actions">
      <?php if (has_role('editor')): ?><button type="button" class="btn btn-primary" onclick="openModal('edit-member-modal')">Edit Member</button><?php endif; ?>
      <a href="members.php" class="btn btn-secondary">&larr; Back to Members</a>
    </div>
  </div>
  <div class="modal-body">
    <div class="view-avatar-row">
      <?php if (!empty($member['photo_path'])): ?>
        <button type="button" class="member-photo-button" id="member-photo-open" aria-label="Open <?= h($page_title) ?>'s photo"><img src="<?= h('../' . $member['photo_path']) ?>" alt="<?= h($page_title) ?>"></button>
      <?php else: ?>
        <div class="view-avatar-init" style="width:72px;height:72px;font-size:29px"><?= h(strtoupper(substr($member['first_name'], 0, 1))) ?></div>
      <?php endif; ?>
      <div><div class="view-name"><?= h($page_title) ?></div><span class="badge badge-<?= h($member['status']) ?>"><?= h($member['status']) ?></span></div>
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Email</div><div class="view-dd"><a href="mailto:<?= h($member['email']) ?>"><?= h($member['email']) ?></a></div></div>
      <div><div class="view-dt">Phone</div><div class="view-dd"><?= h($member['phone'] ?: '—') ?></div></div>
      <div><div class="view-dt">Occupation / Field of Study</div><div class="view-dd"><?= h($member['occupation'] ?: '—') ?></div></div>
      <div><div class="view-dt">Year of Study</div><div class="view-dd"><?= h($member['year_of_study'] ?: '—') ?></div></div>
      <div><div class="view-dt">Birthday</div><div class="view-dd"><?= !empty($member['birthday']) ? h(date('d M Y', strtotime($member['birthday']))) : '—' ?></div></div>
      <div><div class="view-dt">Applied</div><div class="view-dd"><?= !empty($member['created_at']) ? h(date('d M Y', strtotime($member['created_at']))) : '—' ?></div></div>
      <div><div class="view-dt">Directory</div><div class="view-dd"><span class="badge <?= !empty($member['show_in_directory']) ? 'badge-approved' : 'badge-rejected' ?>"><?= !empty($member['show_in_directory']) ? 'Listed' : 'Hidden' ?></span></div></div>
      <div><div class="view-dt">Dues (<?= $dues_year ?>)</div><div class="view-dd"><?php if ($dues_status): ?><a href="dues.php?year=<?= $dues_year ?>#member-<?= $member_id ?>" class="badge badge-<?= h($dues_status) ?> no-underline"><?= h($dues_status) ?></a><?php else: ?><a href="dues.php?year=<?= $dues_year ?>#member-<?= $member_id ?>">No record</a><?php endif; ?></div></div>
      <div><div class="view-dt">LinkedIn</div><div class="view-dd"><?php if (!empty($member['linkedin_url'])): ?><a href="<?= h($member['linkedin_url']) ?>" target="_blank" rel="noopener">Open profile</a><?php else: ?>—<?php endif; ?></div></div>
      <div><div class="view-dt">Instagram</div><div class="view-dd"><?php if (!empty($member['instagram_url'])): ?><a href="<?= h($member['instagram_url']) ?>" target="_blank" rel="noopener">Open profile</a><?php else: ?>—<?php endif; ?></div></div>
    </div>
    <div class="view-full"><div class="view-dt">Public Bio</div><div class="view-dd"><?= nl2br(h($member['bio'] ?: '—')) ?></div></div>
    <div class="view-full"><div class="view-dt">Why they want to join</div><div class="view-dd"><?= nl2br(h($member['why_join'] ?: '—')) ?></div></div>
    <div class="view-full"><div class="view-dt">Admin Notes</div><div class="view-dd"><?= nl2br(h($member['notes'] ?: '—')) ?></div></div>
  </div>
</div>

<?php if (!empty($member['photo_path'])): ?>
  <div class="member-photo-preview" id="member-photo-preview" role="dialog" aria-modal="true" aria-label="<?= h($page_title) ?> photo">
    <img src="<?= h('../' . $member['photo_path']) ?>" alt="<?= h($page_title) ?>">
    <button type="button" id="member-photo-close" aria-label="Close photo">&times;</button>
  </div>
<?php endif; ?>

<?php if (has_role('editor')): ?>
<div class="modal fade" id="edit-member-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content member-edit-dialog">
    <div class="modal-header"><span class="modal-title">Edit <?= h($page_title) ?></span><button type="button" class="modal-close" onclick="closeModal('edit-member-modal')">&times;</button></div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= $member_id ?>">
        <div class="form-row">
          <div class="form-group"><label>First Name *</label><input type="text" name="first_name" value="<?= h($member['first_name']) ?>" required></div>
          <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" value="<?= h($member['last_name']) ?>" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= h($member['email']) ?>" required></div>
          <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= h($member['phone'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Occupation / Field of Study</label><input type="text" name="occupation" value="<?= h($member['occupation'] ?? '') ?>"></div>
          <div class="form-group"><label>Year of Study</label><select name="year_of_study"><option value="">Select year</option><?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Postgraduate'] as $year): ?><option value="<?= $year ?>" <?= ($member['year_of_study'] ?? '') === $year ? 'selected' : '' ?>><?= $year ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Birthday</label><input type="date" name="birthday" value="<?= h(!empty($member['birthday']) ? substr($member['birthday'], 0, 10) : '') ?>" max="<?= date('Y-m-d') ?>"></div>
          <div class="form-group"><label>Status</label><select name="status"><?php foreach (['pending', 'approved', 'rejected'] as $status): ?><option value="<?= $status ?>" <?= $member['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-group"><label>Why do they want to join?</label><textarea name="why_join"><?= h($member['why_join'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Public Bio</label><textarea name="bio" class="mh-60"><?= h($member['bio'] ?? '') ?></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>LinkedIn URL</label><input type="text" name="linkedin_url" value="<?= h($member['linkedin_url'] ?? '') ?>"></div>
          <div class="form-group"><label>Instagram URL</label><input type="text" name="instagram_url" value="<?= h($member['instagram_url'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label>Admin Notes</label><textarea name="notes" class="mh-60"><?= h($member['notes'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Profile Photo</label><input type="file" name="photo" accept="image/*"><?php if (!empty($member['photo_path'])): ?><label class="remove-photo-label"><input type="checkbox" name="remove_photo" value="1" class="w-auto"> Remove current photo</label><?php endif; ?></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('edit-member-modal')">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($member['photo_path'])): ?>
<script>
  (function () {
    var preview = document.getElementById('member-photo-preview');
    document.getElementById('member-photo-open').addEventListener('click', function () { preview.classList.add('is-open'); });
    document.getElementById('member-photo-close').addEventListener('click', function () { preview.classList.remove('is-open'); });
    preview.addEventListener('click', function (event) { if (event.target === preview) preview.classList.remove('is-open'); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') preview.classList.remove('is-open'); });
  })();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
