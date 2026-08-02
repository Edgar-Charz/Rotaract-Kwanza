<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/LeadershipTerm.php';
require_once dirname(__DIR__) . '/classes/LeadershipMember.php';
require_once dirname(__DIR__) . '/classes/TeamRole.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$term_id = (int) ($_GET['id'] ?? 0);
$lt = new LeadershipTerm($conn);
$term = $term_id > 0 ? $lt->findById($term_id) : false;

if (!$term) {
    flash('error', 'Leadership term not found.');
    header('Location: ' . ADMIN_URL . '/leadership_history.php');
    exit;
}

$page_title = 'Officers — ' . $term['term_label'];
$redirect = ADMIN_URL . '/leadership_term.php?id=' . $term_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $lm = new LeadershipMember($conn);

    if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $role_id   = (int) ($_POST['role_id'] ?? 0);
        if ($full_name === '') {
            flash('error', 'Full name is required.');
        } elseif ($role_id <= 0 || !(new TeamRole($conn))->getNameById($role_id)) {
            flash('error', 'Please select a valid role.');
        } else {
            try {
                $img = upload_image('image', 'leadership') ?: '';
                if (!$img && !empty($_FILES['image']['name'])) {
                    flash('error', 'Officer added, but the photo could not be uploaded (invalid file type or too large).');
                }
                $lm->create(
                    $term_id, $full_name, '',
                    trim($_POST['description'] ?? ''),
                    $img,
                    (int) ($_POST['display_order'] ?? 0),
                    isset($_POST['is_active']) ? 1 : 0,
                    clean_url($_POST['linkedin_url'] ?? ''),
                    clean_url($_POST['instagram_url'] ?? ''),
                    $role_id
                );
                log_activity('add_leadership_member', "Added officer $full_name to term {$term['term_label']}");
                if (!isset($_SESSION['flash'])) flash('success', 'Officer added.');
            } catch (mysqli_sql_exception $e) {
                flash('error', 'Could not add officer.');
            }
        }
    }

    if ($action === 'edit') {
        $id        = (int) $_POST['id'];
        $full_name = trim($_POST['full_name'] ?? '');
        $role_id   = (int) ($_POST['role_id'] ?? 0);
        if ($full_name === '') {
            flash('error', 'Full name is required.');
        } elseif ($role_id <= 0 || !(new TeamRole($conn))->getNameById($role_id)) {
            flash('error', 'Please select a valid role.');
        } else {
            try {
                $record = $lm->findById($id);
                if (!$record || (int) $record['term_id'] !== $term_id) {
                    flash('error', 'Officer not found.');
                } else {
                    $oldImg = $record['photo_path'] ?? '';
                    // A fresh upload always wins over a same-request "remove" checkbox.
                    if (!empty($_FILES['image']['name'])) {
                        $img = upload_image('image', 'leadership') ?: $oldImg;
                        if ($img === $oldImg) {
                            flash('error', 'Officer updated, but the new photo could not be uploaded (invalid file type or too large).');
                        }
                    } elseif (!empty($_POST['remove_image'])) {
                        $img = '';
                    } else {
                        $img = $oldImg;
                    }
                    $lm->update(
                        $id, $full_name, '',
                        trim($_POST['description'] ?? ''),
                        $img,
                        (int) ($_POST['display_order'] ?? 0),
                        isset($_POST['is_active']) ? 1 : 0,
                        clean_url($_POST['linkedin_url'] ?? ''),
                        clean_url($_POST['instagram_url'] ?? ''),
                        $role_id
                    );
                    if ($img !== $oldImg && $oldImg) delete_image($oldImg);
                    log_activity('edit_leadership_member', "Edited officer ID $id: $full_name");
                    if (!isset($_SESSION['flash'])) flash('success', 'Officer updated.');
                }
            } catch (mysqli_sql_exception $e) {
                flash('error', 'Could not update officer.');
            }
        }
    }

    if ($action === 'delete') {
        $id     = (int) $_POST['id'];
        $record = $lm->findById($id);
        if ($record && (int) $record['term_id'] === $term_id) {
            if ($record['photo_path']) delete_image($record['photo_path']);
            $lm->delete($id);
            log_activity('delete_leadership_member', "Removed officer: {$record['full_name']}");
            flash('success', 'Officer removed.');
        }
    }

    if ($action === 'bulk_active') {
        $active = $_POST['bulk_active'] === '1' ? 1 : 0;
        $ids    = array_map('intval', $_POST['ids'] ?? []);
        $count  = 0;
        foreach ($ids as $mid) {
            $record = $lm->findById($mid);
            if (!$record || (int) $record['term_id'] !== $term_id) continue;
            $lm->update(
                $mid, $record['full_name'], $record['role'],
                $record['description'] ?? '', $record['photo_path'] ?? '',
                (int) $record['display_order'], $active,
                $record['linkedin_url'] ?? '', $record['instagram_url'] ?? '',
                (int) ($record['role_id'] ?? 0)
            );
            $count++;
        }
        log_activity('bulk_update_leadership_members', "Bulk set $count officer(s) " . ($active ? 'visible' : 'hidden'));
        flash('success', "$count officer(s) updated.");
    }

    if ($action === 'bulk_delete') {
        $ids   = array_map('intval', $_POST['ids'] ?? []);
        $count = 0;
        foreach ($ids as $mid) {
            $record = $lm->findById($mid);
            if (!$record || (int) $record['term_id'] !== $term_id) continue;
            if ($record['photo_path']) delete_image($record['photo_path']);
            $lm->delete($mid);
            $count++;
        }
        log_activity('bulk_delete_leadership_members', "Bulk deleted $count officer(s)");
        flash('success', "$count officer(s) removed.");
    }

    header('Location: ' . $redirect);
    exit;
}

$members = (new LeadershipMember($conn))->getByTermId($term_id);
$roles   = (new TeamRole($conn))->getActive();

// Group roles by tier_label (in display_order) so the <select> can render <optgroup>s.
$roles_by_tier = [];
foreach ($roles as $r) {
    $roles_by_tier[$r['tier_label']][] = $r;
}

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom:16px">
  <a href="leadership_history.php" class="btn btn-secondary btn-sm">&larr; All Terms</a>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div>
      <span class="card-title"><?= h($term['term_label']) ?></span>
      <?php if ($term['year_start']): ?>
      <div class="text-muted" style="font-size:12.5px;margin-top:4px">
        <?= (int) $term['year_start'] ?><?= $term['year_end'] ? '–' . (int) $term['year_end'] : '' ?>
        &middot; <?= count($members) ?> officer<?= count($members) !== 1 ? 's' : '' ?>
      </div>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px">
      <a href="leadership_term_photos.php?term=<?= $term_id ?>" class="btn btn-secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        Manage Photos
      </a>
      <?php if (has_role('editor')): ?>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Officer
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($term['summary']): ?>
  <p class="text-muted" style="font-size:13px;padding:0 16px 12px;margin:0"><?= h($term['summary']) ?></p>
  <?php endif; ?>
  <?php if ($term['image_path']): ?>
  <div style="padding:0 16px 12px">
    <img src="<?= h($term['image_path']) ?>" alt="Term group photo" style="max-width:280px;border-radius:12px;border:1px solid var(--border)">
    <p class="text-muted" style="font-size:11.5px;margin-top:6px">Cover photo — use "Manage Photos" to add more.</p>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Officers</span>
  </div>

  <?php if (has_role('editor')): ?>
  <div class="card-header" id="bulk-bar" style="display:none;background:#fef6f0">
    <span id="bulk-count" class="text-muted" style="font-size:13px;font-weight:600"></span>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="button" class="btn btn-sm btn-secondary" onclick="submitMemberBulk('bulk_active','1')">Show Selected</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="submitMemberBulk('bulk_active','0')">Hide Selected</button>
      <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeleteMembers()">Delete Selected</button>
    </div>
  </div>
  <form id="bulk-form" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" id="bulk-action-field" value="">
    <input type="hidden" name="bulk_active" id="bulk-active-field" value="">
    <div id="bulk-ids-container"></div>
  </form>
  <?php endif; ?>

  <?php
    // The checkbox column only exists for editors, so the real column count
    // is 6 for viewers and 7 for editors — used below for the "no officers"
    // row's colspan, which a hardcoded number can't track without risking
    // DataTables' "Incorrect column count" warning for viewer-role admins.
    $colcount = 6 + (has_role('editor') ? 1 : 0);
  ?>
  <div class="table-wrap">
    <table id="dt-members">
      <thead>
        <tr>
          <?php if (has_role('editor')): ?><th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th><?php endif; ?>
          <th>Photo</th><th>Name</th><th>Role</th><th>Order</th><th>Visible</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($members): foreach ($members as $m): ?>
        <tr>
          <?php if (has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $m['id'] ?>" onchange="updateMemberBulkBar()"></td><?php endif; ?>
          <td>
            <?php if ($m['photo_path']): ?>
            <img src="<?= h($m['photo_path']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
            <?php else: ?>
            <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#C0396B,#D4882A);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px">
              <?= strtoupper(substr($m['full_name'], 0, 1)) ?>
            </div>
            <?php endif; ?>
          </td>
          <td class="fw-bold"><?= h($m['full_name']) ?></td>
          <td><?= h($m['role']) ?></td>
          <td><?= (int) $m['display_order'] ?></td>
          <td><?= $m['is_active'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-icon btn-sm btn-secondary" title="View" aria-label="View" onclick="openViewMemberModal(<?= h(json_encode($m)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditMemberModal(<?= h(json_encode($m)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <form id="del-m-<?= $m['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-m-<?= $m['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="<?= $colcount ?>" class="text-muted" style="text-align:center;padding:24px">No officers yet. Add the leadership team for this term.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Officer Modal -->
<div class="modal fade" id="add-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Add Officer — <?= h($term['term_label']) ?></span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required></div>
          <div class="form-group"><label>Role *</label>
            <select name="role_id" required>
              <option value="">Select a role&hellip;</option>
              <?php foreach ($roles_by_tier as $tier_label => $tier_roles): ?>
                <optgroup label="<?= h($tier_label) ?>">
                  <?php foreach ($tier_roles as $r): ?><option value="<?= $r['id'] ?>"><?= h($r['name']) ?></option><?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0" min="0"></div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>LinkedIn URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="linkedin_url" placeholder="https://linkedin.com/in/..."></div>
          <div class="form-group"><label>Instagram URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="instagram_url" placeholder="https://instagram.com/..."></div>
        </div>
        <div class="form-group mb-2">
          <label>Photo (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="am_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p><strong>Upload photo</strong></p>
            </label>
            <input type="file" id="am_image" name="image" accept="image/*" style="display:none" onchange="previewImage(this,'add-member-prev')">
            <div class="thumb-col">
              <span class="thumb-col-label">Preview</span>
              <img id="add-member-prev" src="" alt="Preview" class="image-thumb image-thumb--avatar">
            </div>
          </div>
        </div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" id="am_active" checked style="width:auto">
          <label for="am_active" style="font-weight:400">Visible on public page</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Officer</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Officer Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Edit Officer</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="em_id">
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" id="em_name" required></div>
          <div class="form-group"><label>Role *</label>
            <select name="role_id" id="em_role_id" required>
              <option value="">Select a role&hellip;</option>
              <?php foreach ($roles_by_tier as $tier_label => $tier_roles): ?>
                <optgroup label="<?= h($tier_label) ?>">
                  <?php foreach ($tier_roles as $r): ?><option value="<?= $r['id'] ?>"><?= h($r['name']) ?></option><?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="em_order" min="0"></div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description" id="em_desc"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>LinkedIn URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="linkedin_url" id="em_linkedin" placeholder="https://linkedin.com/in/..."></div>
          <div class="form-group"><label>Instagram URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="instagram_url" id="em_instagram" placeholder="https://instagram.com/..."></div>
        </div>
        <div class="form-group mb-2">
          <label>Photo (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="em_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p><strong>Replace photo</strong></p>
            </label>
            <input type="file" id="em_image" name="image" accept="image/*" style="display:none" onchange="previewImage(this,'edit-member-prev')">
            <div class="thumb-col">
              <span class="thumb-col-label">Photo</span>
              <img id="edit-member-prev" src="" alt="Preview" class="image-thumb image-thumb--avatar">
              <label id="em_remove_wrap" style="display:none;align-items:center;gap:5px;font-size:11.5px;font-weight:400;margin-top:6px">
                <input type="checkbox" name="remove_image" value="1" style="width:auto"> Remove
              </label>
            </div>
          </div>
        </div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" id="em_active" style="width:auto">
          <label for="em_active" style="font-weight:400">Visible on public page</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- View Officer Modal -->
<div class="modal fade" id="view-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:500px">
    <div class="modal-header">
      <span class="modal-title">Officer Details</span>
      <button class="modal-close" onclick="closeModal('view-modal')">&times;</button>
    </div>
    <div class="modal-body" id="view-body"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('view-modal')">Close</button>
    </div>
  </div>
</div>

<script>
function openViewMemberModal(m) {
  const photoHtml = m.photo_path
    ? `<img src="${esc(m.photo_path)}" class="view-avatar">`
    : `<div class="view-avatar-init">${esc(m.full_name ? m.full_name[0].toUpperCase() : '?')}</div>`;
  document.getElementById('view-body').innerHTML = `
    <div class="view-avatar-row">
      ${photoHtml}
      <div>
        <div class="view-name">${esc(m.full_name)}</div>
        <div style="color:var(--text-muted);font-size:13px">${esc(m.role)}</div>
        <span class="badge ${m.is_active == 1 ? 'badge-approved' : 'badge-rejected'}" style="margin-top:4px">
          ${m.is_active == 1 ? 'Visible' : 'Hidden'}
        </span>
      </div>
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Term</div><div class="view-dd"><?= h($term['term_label']) ?></div></div>
      <div><div class="view-dt">Display Order</div><div class="view-dd">${esc(m.display_order)}</div></div>
      <div><div class="view-dt">LinkedIn</div><div class="view-dd">${m.linkedin_url ? `<a href="${esc(m.linkedin_url)}" target="_blank" rel="noopener">${esc(m.linkedin_url)}</a>` : '—'}</div></div>
      <div><div class="view-dt">Instagram</div><div class="view-dd">${m.instagram_url ? `<a href="${esc(m.instagram_url)}" target="_blank" rel="noopener">${esc(m.instagram_url)}</a>` : '—'}</div></div>
    </div>
    <div class="view-full">
      <div class="view-dt">Description</div>
      <div class="view-dd">${esc(m.description) || '—'}</div>
    </div>`;
  openModal('view-modal');
}
</script>

<script>
$(document).ready(function() {
  $('#dt-members').DataTable({
    pageLength: 25,
    order: [[<?= has_role('editor') ? 4 : 3 ?>, 'asc']],
    columnDefs: [{ orderable: false, targets: <?= has_role('editor') ? '[0, 6]' : '[5]' ?> }]
  });
});

function openEditMemberModal(m) {
  document.getElementById('em_id').value = m.id;
  document.getElementById('em_name').value = m.full_name || '';
  document.getElementById('em_role_id').value = m.role_id || '';
  document.getElementById('em_order').value = m.display_order || 0;
  document.getElementById('em_desc').value = m.description || '';
  document.getElementById('em_linkedin').value = m.linkedin_url || '';
  document.getElementById('em_instagram').value = m.instagram_url || '';
  document.getElementById('em_active').checked = m.is_active == 1;
  const prev = document.getElementById('edit-member-prev');
  const removeWrap = document.getElementById('em_remove_wrap');
  if (removeWrap) {
    removeWrap.style.display = m.photo_path ? 'flex' : 'none';
    removeWrap.querySelector('input[type="checkbox"]').checked = false;
  }
  if (prev) {
    if (m.photo_path) { prev.src = m.photo_path; prev.style.display = 'block'; }
    else prev.style.display = 'none';
  }
  openModal('edit-modal');
}

function updateMemberBulkBar() {
  const checks = document.querySelectorAll('#dt-members tbody .row-check:checked');
  const bar = document.getElementById('bulk-bar');
  const count = document.getElementById('bulk-count');
  const container = document.getElementById('bulk-ids-container');
  if (!bar || !count || !container) return;
  const ids = Array.from(checks).map((input) => input.value);
  bar.style.display = ids.length ? 'flex' : 'none';
  count.textContent = ids.length + ' selected';
  container.innerHTML = ids.map((id) => '<input type="hidden" name="ids[]" value="' + id + '">').join('');
}

function submitMemberBulk(action, active) {
  document.getElementById('bulk-action-field').value = action;
  document.getElementById('bulk-active-field').value = active;
  document.getElementById('bulk-form').submit();
}

function bulkDeleteMembers() {
  if (confirm('Delete the selected officers? This cannot be undone.')) {
    document.getElementById('bulk-action-field').value = 'bulk_delete';
    document.getElementById('bulk-form').submit();
  }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
