<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/MonthlyRecognition.php';

$page_title = 'Recognition Recipients';
$recognitions = new MonthlyRecognition($conn);
$recognitionId = (int) ($_GET['id'] ?? $_POST['recognition_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  require_role('editor');
  $action = $_POST['action'] ?? '';
  if ($action === 'update_recipient') {
    $recognitions->updateRecipientCitation($recognitionId, (int) $_POST['member_id'], trim($_POST['citation'] ?? ''));
    flash('success', 'Recipient recognition updated.');
  } elseif ($action === 'remove_recipient') {
    $recognitions->removeRecipient($recognitionId, (int) $_POST['member_id']);
    flash('success', 'Recipient removed from this month.');
  } elseif ($action === 'update_recognition') {
    $current = $recognitions->findById($recognitionId);
    if ($current) {
      $notes = [];
      foreach ($current['members'] as $member) $notes[(int) $member['id']] = $member['citation'];
      $recognitions->updateDetails($recognitionId, trim($_POST['citation'] ?? ''), isset($_POST['is_published']), $notes);
      flash('success', 'Monthly recognition settings updated.');
    }
  }
  header('Location: ' . ADMIN_URL . '/recognition_recipients.php?id=' . $recognitionId);
  exit;
}

$recognition = $recognitions->findById($recognitionId);
if (!$recognition) {
  flash('error', 'Recognition record not found.');
  header('Location: ' . ADMIN_URL . '/recognitions.php');
  exit;
}

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= h(date('F Y', strtotime($recognition['recognition_month']))) ?> Recipients</span>
    <div class="table-actions">
      <a class="btn btn-sm btn-secondary" href="recognitions.php">Back to Archive</a>
      <a class="btn btn-sm btn-primary" href="members.php?status=approved&amp;recognition_month=<?= h(substr($recognition['recognition_month'], 0, 7)) ?>">Add / Manage Recipients</a>
      <?php if (has_role('editor')): ?><button class="btn btn-sm btn-info" onclick="openModal('recognition-settings')">Recognition Settings</button><?php endif; ?>
    </div>
  </div>
  <p class="text-muted section-hint">Review each recognized member and add a personal explanation of their contribution.</p>
  <div class="table-wrap">
    <table id="dt-recognition-recipients">
      <thead><tr><th>Member</th><th>Occupation</th><th>Recognition Note</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($recognition['members'] as $member): ?>
        <tr>
          <td class="fw-bold"><?= h($member['first_name'] . ' ' . $member['last_name']) ?></td>
          <td><?= h($member['occupation'] ?: '—') ?></td>
          <td class="text-muted text-clamp-320"><?= h($member['citation'] ?: 'No personal recognition note yet.') ?></td>
          <td><div class="table-actions">
            <button class="btn btn-icon btn-sm btn-secondary" title="View recipient details" aria-label="View recipient details" onclick="viewRecipient(<?= h(json_encode($member)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            <?php if (has_role('editor')): ?>
              <a class="btn btn-icon btn-sm btn-secondary" title="Manage recipient photos" aria-label="Manage recipient photos" href="recognition_photos.php?id=<?= $recognitionId ?>&amp;member_id=<?= (int) $member['id'] ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></a>
              <button class="btn btn-icon btn-sm btn-info" title="Edit note" aria-label="Edit note" onclick="editRecipient(<?= h(json_encode($member)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <form id="remove-recipient-<?= (int) $member['id'] ?>" method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="remove_recipient"><input type="hidden" name="recognition_id" value="<?= $recognitionId ?>"><input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Remove recipient" aria-label="Remove recipient" onclick="removeRecipient(<?= (int) $member['id'] ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="recipient-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-content">
  <div class="modal-header"><span class="modal-title" id="recipient-modal-title">Edit Recognition</span><button class="modal-close" onclick="closeModal('recipient-modal')">&times;</button></div>
  <form method="POST"><div class="modal-body">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="update_recipient"><input type="hidden" name="recognition_id" value="<?= $recognitionId ?>"><input type="hidden" name="member_id" id="recipient-member-id">
    <div class="form-group"><label for="recipient-citation">Personal Recognition Note</label><textarea id="recipient-citation" name="citation" rows="5" placeholder="Describe why this member is being recognized."></textarea></div>
  </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('recipient-modal')">Cancel</button><button type="submit" class="btn btn-primary">Save Note</button></div></form>
</div></div>

<div class="modal fade" id="recipient-view-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-content">
  <div class="modal-header"><span class="modal-title">Recipient Details</span><button class="modal-close" onclick="closeModal('recipient-view-modal')">&times;</button></div>
  <div class="modal-body">
    <div class="view-name" id="view-recipient-name"></div>
    <p class="text-muted mb-2" id="view-recipient-occupation"></p>
    <div class="form-group"><label>Email</label><div id="view-recipient-email" class="text-muted"></div></div>
    <div class="form-group"><label>Phone</label><div id="view-recipient-phone" class="text-muted"></div></div>
    <div class="form-group"><label>Year of Study</label><div id="view-recipient-year" class="text-muted"></div></div>
    <div class="form-group"><label>Member Bio</label><div id="view-recipient-bio" class="text-muted"></div></div>
    <div class="form-group mb-0"><label>Personal Recognition Note</label><div id="view-recipient-citation" class="text-muted"></div></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('recipient-view-modal')">Close</button></div>
</div></div>

<div class="modal fade" id="recognition-settings" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-content">
  <div class="modal-header"><span class="modal-title">Monthly Recognition Settings</span><button class="modal-close" onclick="closeModal('recognition-settings')">&times;</button></div>
  <form method="POST"><div class="modal-body">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="update_recognition"><input type="hidden" name="recognition_id" value="<?= $recognitionId ?>">
    <div class="form-group"><label for="monthly-citation">Shared Monthly Message</label><textarea id="monthly-citation" name="citation" rows="4"><?= h($recognition['citation'] ?? '') ?></textarea></div>
    <div class="form-group form-group-row"><input type="checkbox" name="is_published" id="is_published" class="w-auto" <?= $recognition['is_published'] ? 'checked' : '' ?>><label for="is_published" class="fw-normal">Publish on the homepage</label></div>
  </div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('recognition-settings')">Cancel</button><button type="submit" class="btn btn-primary">Save Settings</button></div></form>
</div></div>

<script>
  $(document).ready(function() { $('#dt-recognition-recipients').DataTable({ pageLength: 25, columnDefs: [{ orderable: false, targets: 3 }] }); });
  function editRecipient(member) {
    document.getElementById('recipient-modal-title').textContent = 'Recognition Note — ' + member.first_name + ' ' + member.last_name;
    document.getElementById('recipient-member-id').value = member.id;
    document.getElementById('recipient-citation').value = member.citation || '';
    openModal('recipient-modal');
  }
  function viewRecipient(member) {
    document.getElementById('view-recipient-name').textContent = member.first_name + ' ' + member.last_name;
    document.getElementById('view-recipient-occupation').textContent = member.occupation || 'No occupation listed';
    document.getElementById('view-recipient-email').textContent = member.email || 'Not provided';
    document.getElementById('view-recipient-phone').textContent = member.phone || 'Not provided';
    document.getElementById('view-recipient-year').textContent = member.year_of_study || 'Not provided';
    document.getElementById('view-recipient-bio').textContent = member.bio || 'No member bio yet.';
    document.getElementById('view-recipient-citation').textContent = member.citation || 'No personal recognition note yet.';
    openModal('recipient-view-modal');
  }
  function removeRecipient(memberId) {
    Swal.fire({ title: 'Remove recipient?', text: 'This member will no longer be recognized for this month.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#dc3545' }).then(function(result) {
      if (result.isConfirmed) document.getElementById('remove-recipient-' + memberId).submit();
    });
  }
  <?php if (($_GET['settings'] ?? '') === '1' && has_role('editor')): ?>$(document).ready(function() { openModal('recognition-settings'); });<?php endif; ?>
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
