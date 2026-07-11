<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/TeamMember.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$page_title = 'Team Members';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $tm = new TeamMember($conn);

    if ($action === 'add') {
        $img = upload_image('image', 'team') ?: '';
        $tm->create(
            trim($_POST['full_name']), trim($_POST['role']), trim($_POST['description']),
            $img, trim($_POST['email']), (int)($_POST['display_order'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0,
            trim($_POST['term'] ?? ''), trim($_POST['linkedin_url'] ?? ''),
            (int)($_POST['tier'] ?? 3), trim($_POST['instagram_url'] ?? '')
        );
        log_activity('add_team', "Added team member: " . trim($_POST['full_name']) . " — " . trim($_POST['role']));
        flash('success', 'Team member added.');
    }

    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $oldImg = $tm->getImagePathById($id);
        $img    = upload_image('image', 'team') ?: $oldImg;
        $tm->update(
            $id,
            trim($_POST['full_name']), trim($_POST['role']), trim($_POST['description']),
            $img, trim($_POST['email']), (int)($_POST['display_order'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0,
            trim($_POST['term'] ?? ''), trim($_POST['linkedin_url'] ?? ''),
            (int)($_POST['tier'] ?? 3), trim($_POST['instagram_url'] ?? '')
        );
        if ($img && $img !== $oldImg && $oldImg) delete_image($oldImg);
        log_activity('edit_team', "Edited team member ID $id: " . trim($_POST['full_name']));
        flash('success', 'Team member updated.');
    }

    if ($action === 'delete') {
        $id      = (int)$_POST['id'];
        $tm_name = $tm->getFullNameById($id);
        $path    = $tm->getImagePathById($id);
        if ($path) delete_image($path);
        $tm->delete($id);
        log_activity('delete_team', "Removed team member: $tm_name");
        flash('success', 'Team member removed.');
    }

    header('Location: ' . ADMIN_URL . '/team.php');
    exit;
}

$team  = (new TeamMember($conn))->getAll();
$tiers = team_tiers();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($team) ?> Team Member<?= count($team) !== 1 ? 's':'' ?></span>
    <?php if (has_role('editor')): ?>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Member
    </button>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table id="dt-team">
      <thead>
        <tr><th>Photo</th><th>Name</th><th>Role</th><th>Tier</th><th>Term</th><th>Email</th><th>Order</th><th>Visible</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($team): foreach ($team as $t): ?>
        <tr>
          <td>
            <?php if ($t['image_path']): ?>
            <img src="<?= h($t['image_path']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
            <?php else: ?>
            <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#C0396B,#D4882A);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px">
              <?= strtoupper(substr($t['full_name'], 0, 1)) ?>
            </div>
            <?php endif; ?>
          </td>
          <td class="fw-bold"><?= h($t['full_name']) ?></td>
          <td><?= h($t['role']) ?></td>
          <td class="text-muted" style="font-size:12px"><?= h($tiers[(int)($t['tier'] ?? 3)] ?? '') ?></td>
          <td class="text-muted"><?= h($t['term'] ?? '') ?: '—' ?></td>
          <td class="text-muted"><?= h($t['email'] ?? '—') ?></td>
          <td><?= $t['display_order'] ?></td>
          <td><?= $t['is_active'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-icon btn-sm btn-secondary" title="View" aria-label="View" onclick="openViewModal(<?= h(json_encode($t)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($t)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <form id="del-t-<?= $t['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-t-<?= $t['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
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
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Add Team Member</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group mb-2">
          <label>Photo (optional)</label>
          <label class="upload-area" for="at_image" style="padding:16px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:24px;height:24px;margin-bottom:4px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <p><strong>Upload photo</strong></p>
          </label>
          <input type="file" id="at_image" name="image" accept="image/*" style="display:none" onchange="previewImage(this,'add-team-prev')">
          <img id="add-team-prev" src="" alt="Preview">
        </div>
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required></div>
          <div class="form-group"><label>Role / Position *</label><input type="text" name="role" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Hierarchy Tier</label>
            <select name="tier">
              <?php foreach ($tiers as $tk => $tlabel): ?><option value="<?= $tk ?>" <?= $tk === 3 ? 'selected' : '' ?>><?= h($tlabel) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Term <span class="text-muted" style="font-weight:400">(e.g. 2025&ndash;2026)</span></label><input type="text" name="term" placeholder="2025–2026"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0" min="0"></div>
          <div class="form-group"><label>Email</label><input type="email" name="email"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>LinkedIn URL</label><input type="text" name="linkedin_url" placeholder="https://linkedin.com/in/..."></div>
          <div class="form-group"><label>Instagram URL</label><input type="text" name="instagram_url" placeholder="https://instagram.com/..."></div>
        </div>
        <div class="form-group mb-2"><label>Description / Bio</label><textarea name="description"></textarea></div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" id="at_active" checked style="width:auto">
          <label for="at_active" style="font-weight:400">Show on public team page</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Member</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Edit Team Member</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="et_id">
        <div class="form-group mb-2">
          <label>Replace Photo (optional)</label>
          <div id="et_current_photo" style="margin-bottom:8px"></div>
          <input type="file" name="image" accept="image/*" onchange="previewImage(this,'edit-team-prev')" style="padding:6px">
          <img id="edit-team-prev" src="" alt="Preview">
        </div>
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" id="et_name" required></div>
          <div class="form-group"><label>Role *</label><input type="text" name="role" id="et_role" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Hierarchy Tier</label>
            <select name="tier" id="et_tier">
              <?php foreach ($tiers as $tk => $tlabel): ?><option value="<?= $tk ?>"><?= h($tlabel) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Term <span class="text-muted" style="font-weight:400">(e.g. 2025&ndash;2026)</span></label><input type="text" name="term" id="et_term" placeholder="2025–2026"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="et_order" min="0"></div>
          <div class="form-group"><label>Email</label><input type="email" name="email" id="et_email"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>LinkedIn URL</label><input type="text" name="linkedin_url" id="et_linkedin" placeholder="https://linkedin.com/in/..."></div>
          <div class="form-group"><label>Instagram URL</label><input type="text" name="instagram_url" id="et_instagram" placeholder="https://instagram.com/..."></div>
        </div>
        <div class="form-group mb-2"><label>Description / Bio</label><textarea name="description" id="et_desc"></textarea></div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" id="et_active" style="width:auto">
          <label for="et_active" style="font-weight:400">Show on public team page</label>
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
  <div class="modal-dialog modal-content" style="max-width:500px">
    <div class="modal-header">
      <span class="modal-title">Team Member Details</span>
      <button class="modal-close" onclick="closeModal('view-modal')">&times;</button>
    </div>
    <div class="modal-body" id="view-body"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('view-modal')">Close</button>
    </div>
  </div>
</div>

<script>
var TEAM_TIERS = <?= json_encode($tiers, JSON_FORCE_OBJECT) ?>;
function tierLabel(tier) {
  return TEAM_TIERS[tier] || TEAM_TIERS[3] || 'Team Members';
}
function openViewModal(t) {
  const photoHtml = t.image_path
    ? `<img src="${esc(t.image_path)}" class="view-avatar">`
    : `<div class="view-avatar-init">${esc(t.full_name ? t.full_name[0].toUpperCase() : '?')}</div>`;
  document.getElementById('view-body').innerHTML = `
    <div class="view-avatar-row">
      ${photoHtml}
      <div>
        <div class="view-name">${esc(t.full_name)}</div>
        <div style="color:var(--text-muted);font-size:13px">${esc(t.role)}</div>
        <span class="badge ${t.is_active == 1 ? 'badge-approved' : 'badge-rejected'}" style="margin-top:4px">
          ${t.is_active == 1 ? 'Active' : 'Hidden'}
        </span>
      </div>
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Tier</div><div class="view-dd">${esc(tierLabel(t.tier))}</div></div>
      <div><div class="view-dt">Term</div><div class="view-dd">${esc(t.term) || '—'}</div></div>
      <div><div class="view-dt">Email</div><div class="view-dd">${t.email ? `<a href="mailto:${esc(t.email)}">${esc(t.email)}</a>` : '—'}</div></div>
      <div><div class="view-dt">LinkedIn</div><div class="view-dd">${t.linkedin_url ? `<a href="${esc(t.linkedin_url)}" target="_blank" rel="noopener">${esc(t.linkedin_url)}</a>` : '—'}</div></div>
      <div><div class="view-dt">Instagram</div><div class="view-dd">${t.instagram_url ? `<a href="${esc(t.instagram_url)}" target="_blank" rel="noopener">${esc(t.instagram_url)}</a>` : '—'}</div></div>
      <div><div class="view-dt">Display Order</div><div class="view-dd">${esc(t.display_order)}</div></div>
    </div>
    <div class="view-full">
      <div class="view-dt">Bio / Description</div>
      <div class="view-dd">${esc(t.description) || '—'}</div>
    </div>`;
  openModal('view-modal');
}

$(document).ready(function() {
  $('#dt-team').DataTable({
    pageLength: 25,
    order: [[3, 'asc'], [6, 'asc']],
    columnDefs: [{ orderable: false, targets: [0, 7] }]
  });
});
function openEditModal(t) {
  document.getElementById('et_id').value    = t.id;
  document.getElementById('et_name').value  = t.full_name;
  document.getElementById('et_role').value  = t.role;
  document.getElementById('et_email').value = t.email || '';
  document.getElementById('et_term').value  = t.term || '';
  document.getElementById('et_tier').value  = t.tier || 3;
  document.getElementById('et_linkedin').value  = t.linkedin_url || '';
  document.getElementById('et_instagram').value = t.instagram_url || '';
  document.getElementById('et_order').value = t.display_order || 0;
  document.getElementById('et_desc').value  = t.description || '';
  document.getElementById('et_active').checked = t.is_active == 1;
  const ph = document.getElementById('et_current_photo');
  ph.innerHTML = t.image_path ? '<img src="' + t.image_path + '" style="width:50px;height:50px;border-radius:50%;object-fit:cover">' : '';
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
