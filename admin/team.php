<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/TeamMember.php';
require_once dirname(__DIR__) . '/classes/TeamRole.php';
require_once dirname(__DIR__) . '/classes/LeadershipTerm.php';
require_once dirname(__DIR__) . '/classes/LeadershipMember.php';

$page_title = 'Team Members';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $tm = new TeamMember($conn);

    if ($action === 'add') {
        $full_name = trim($_POST['full_name']);
        $role_id   = (int)($_POST['role_id'] ?? 0);
        if ($full_name === '') {
            flash('error', 'Full name is required.');
        } elseif ($role_id <= 0 || !(new TeamRole($conn))->getNameById($role_id)) {
            flash('error', 'Please select a valid role.');
        } else {
            try {
                $img = upload_image('image', 'team') ?: '';
                if (!$img && !empty($_FILES['image']['name'])) {
                    flash('error', 'Team member added, but the photo could not be uploaded (invalid file type or too large).');
                }
                $tm->create(
                    $full_name, $role_id, trim($_POST['description']),
                    $img, trim($_POST['email']), (int)($_POST['display_order'] ?? 0),
                    isset($_POST['is_active']) ? 1 : 0,
                    trim($_POST['term'] ?? ''), clean_url($_POST['linkedin_url'] ?? ''), clean_url($_POST['instagram_url'] ?? '')
                );
                log_activity('add_team', "Added team member: $full_name");
                if (!isset($_SESSION['flash'])) flash('success', 'Team member added.');
            } catch (mysqli_sql_exception $e) {
                flash('error', 'Could not add team member.');
            }
        }
    }

    if ($action === 'edit') {
        $id        = (int)$_POST['id'];
        $full_name = trim($_POST['full_name']);
        $role_id   = (int)($_POST['role_id'] ?? 0);
        if ($full_name === '') {
            flash('error', 'Full name is required.');
        } elseif ($role_id <= 0 || !(new TeamRole($conn))->getNameById($role_id)) {
            flash('error', 'Please select a valid role.');
        } else {
            try {
                $oldImg = $tm->getImagePathById($id);
                $img    = upload_image('image', 'team') ?: $oldImg;
                if ($img === $oldImg && !empty($_FILES['image']['name'])) {
                    flash('error', 'Team member updated, but the new photo could not be uploaded (invalid file type or too large).');
                }
                $tm->update(
                    $id,
                    $full_name, $role_id, trim($_POST['description']),
                    $img, trim($_POST['email']), (int)($_POST['display_order'] ?? 0),
                    isset($_POST['is_active']) ? 1 : 0,
                    trim($_POST['term'] ?? ''), clean_url($_POST['linkedin_url'] ?? ''), clean_url($_POST['instagram_url'] ?? '')
                );
                if ($img && $img !== $oldImg && $oldImg) delete_image($oldImg);
                log_activity('edit_team', "Edited team member ID $id: $full_name");
                if (!isset($_SESSION['flash'])) flash('success', 'Team member updated.');
            } catch (mysqli_sql_exception $e) {
                flash('error', 'Could not update team member.');
            }
        }
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

    if ($action === 'bulk_active') {
        $active = $_POST['bulk_active'] === '1' ? 1 : 0;
        $ids    = array_map('intval', $_POST['ids'] ?? []);
        $count  = 0;
        foreach ($ids as $tid) {
            $t = $tm->findById($tid);
            if (!$t) continue;
            $tm->update(
                $tid, $t['full_name'], (int)$t['role_id'], $t['description'] ?? '', $t['image_path'] ?? '',
                $t['email'] ?? '', (int)$t['display_order'], $active,
                $t['term'] ?? '', $t['linkedin_url'] ?? '', $t['instagram_url'] ?? ''
            );
            $count++;
        }
        log_activity('bulk_update_team', "Bulk set $count team member(s) " . ($active ? 'visible' : 'hidden'));
        flash('success', "$count team member(s) updated.");
    }

    if ($action === 'bulk_delete') {
        $ids   = array_map('intval', $_POST['ids'] ?? []);
        $count = 0;
        foreach ($ids as $tid) {
            $name = $tm->getFullNameById($tid);
            if (!$name) continue;
            $path = $tm->getImagePathById($tid);
            if ($path) delete_image($path);
            $tm->delete($tid);
            $count++;
        }
        log_activity('bulk_delete_team', "Bulk removed $count team member(s)");
        flash('success', "$count team member(s) removed.");
    }

    if ($action === 'bulk_reassign_role') {
        $new_role_id = (int) ($_POST['bulk_role_id'] ?? 0);
        $ids         = array_map('intval', $_POST['ids'] ?? []);
        $tr          = new TeamRole($conn);
        if ($new_role_id <= 0 || !$tr->getNameById($new_role_id)) {
            flash('error', 'Please select a valid role.');
        } else {
            $count = 0;
            foreach ($ids as $tid) {
                $t = $tm->findById($tid);
                if (!$t) continue;
                $tm->update(
                    $tid, $t['full_name'], $new_role_id, $t['description'] ?? '', $t['image_path'] ?? '',
                    $t['email'] ?? '', (int) $t['display_order'], (int) $t['is_active'],
                    $t['term'] ?? '', $t['linkedin_url'] ?? '', $t['instagram_url'] ?? ''
                );
                $count++;
            }
            log_activity('bulk_reassign_team_role', "Bulk reassigned $count team member(s) to role ID $new_role_id");
            flash('success', "$count team member(s) reassigned.");
        }
    }

    if ($action === 'reorder') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        foreach ($ids as $i => $tid) {
            $t = $tm->findById($tid);
            if (!$t) continue;
            $tm->update(
                $tid, $t['full_name'], (int) $t['role_id'], $t['description'] ?? '', $t['image_path'] ?? '',
                $t['email'] ?? '', $i, (int) $t['is_active'],
                $t['term'] ?? '', $t['linkedin_url'] ?? '', $t['instagram_url'] ?? ''
            );
        }
        log_activity('reorder_team', 'Reordered team member display order');
        flash('success', 'Team order updated.');
    }

    if ($action === 'bulk_archive') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        if (!$ids) {
            flash('error', 'No team members selected.');
        } else {
            $term_id = (int) ($_POST['term_id'] ?? 0);
            if ($term_id === -1) {
                $new_label = trim($_POST['new_term_label'] ?? '');
                if ($new_label === '') {
                    flash('error', 'New term label is required.');
                    header('Location: ' . ADMIN_URL . '/team.php');
                    exit;
                }
                $ys = (int) ($_POST['new_term_year_start'] ?? 0) ?: null;
                $ye = (int) ($_POST['new_term_year_end'] ?? 0) ?: null;
                try {
                    $term_id = (new LeadershipTerm($conn))->create($new_label, $ys, $ye, '', '', 0, 1);
                } catch (mysqli_sql_exception $e) {
                    $term_id = 0;
                    flash('error', 'Could not create the new term.');
                }
            }
            if ($term_id > 0) {
                $lm    = new LeadershipMember($conn);
                $count = 0;
                foreach ($ids as $mid) {
                    $member = $tm->findById($mid);
                    if (!$member) continue;
                    $photo = $member['image_path'] ? (copy_uploaded_image($member['image_path'], 'leadership') ?: '') : '';
                    $lm->create(
                        $term_id, $member['full_name'], $member['role'], $member['description'] ?? '', $photo,
                        (int) $member['display_order'], 1,
                        $member['linkedin_url'] ?? '', $member['instagram_url'] ?? '',
                        (int) ($member['role_id'] ?? 0)
                    );
                    $count++;
                }
                log_activity('bulk_archive_team', "Bulk archived $count team member(s) to leadership history (term ID $term_id)");
                flash('success', "$count team member(s) archived to Leadership History.");
            } elseif (!isset($_SESSION['flash'])) {
                flash('error', 'Please select or create a term.');
            }
        }
    }

    if ($action === 'archive') {
        $member_id = (int) ($_POST['member_id'] ?? 0);
        $member    = $tm->findById($member_id);
        if (!$member) {
            flash('error', 'Team member not found.');
        } else {
            $term_id = (int) ($_POST['term_id'] ?? 0);
            if ($term_id === -1) {
                $new_label = trim($_POST['new_term_label'] ?? '');
                if ($new_label === '') {
                    flash('error', 'New term label is required.');
                    header('Location: ' . ADMIN_URL . '/team.php');
                    exit;
                }
                $ys = (int) ($_POST['new_term_year_start'] ?? 0) ?: null;
                $ye = (int) ($_POST['new_term_year_end'] ?? 0) ?: null;
                try {
                    $term_id = (new LeadershipTerm($conn))->create($new_label, $ys, $ye, '', '', 0, 1);
                } catch (mysqli_sql_exception $e) {
                    $term_id = 0;
                    flash('error', 'Could not create the new term.');
                }
            }
            if ($term_id > 0) {
                $role        = trim($_POST['role'] ?? '') ?: $member['role'];
                $description = trim($_POST['description'] ?? '') ?: ($member['description'] ?? '');
                $photo       = $member['image_path'] ? (copy_uploaded_image($member['image_path'], 'leadership') ?: '') : '';
                // Only carry the role_id FK across when the admin left the role text
                // unchanged — if they typed something else, it's a deliberate free-text
                // override and shouldn't silently keep pointing at the old team_roles row.
                $role_id = ($role === $member['role']) ? (int) ($member['role_id'] ?? 0) : 0;
                (new LeadershipMember($conn))->create(
                    $term_id, $member['full_name'], $role, $description, $photo,
                    (int) $member['display_order'], 1,
                    $member['linkedin_url'] ?? '', $member['instagram_url'] ?? '',
                    $role_id
                );
                log_activity('archive_team_member', "Archived {$member['full_name']} to leadership history (term ID $term_id)");
                flash('success', "{$member['full_name']} archived to Leadership History.");
            } elseif (!isset($_SESSION['flash'])) {
                flash('error', 'Please select or create a term.');
            }
        }
    }

    header('Location: ' . ADMIN_URL . '/team.php');
    exit;
}

$team  = (new TeamMember($conn))->getAll();
$roles = (new TeamRole($conn))->getActive();

// Group roles by tier_label (in display_order) so the <select> can render <optgroup>s.
$roles_by_tier = [];
foreach ($roles as $r) {
    $roles_by_tier[$r['tier_label']][] = $r;
}

$leadership_terms = (new LeadershipTerm($conn))->getAllWithMemberCounts();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($team) ?> Team Member<?= count($team) !== 1 ? 's':'' ?></span>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="roles.php" class="btn btn-secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Manage Roles
      </a>
      <?php if (has_role('editor')): ?>
      <button class="btn btn-secondary" onclick="openReorderModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        Reorder
      </button>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Member
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!$roles): ?>
    <p class="text-muted" style="font-size:12.5px;padding:0 16px 12px">
      No roles have been created yet. <a href="roles.php">Add a role</a> before adding team members.
    </p>
  <?php endif; ?>

  <?php if (has_role('editor')): ?>
  <div class="card-header" id="bulk-bar" style="display:none;background:#fef6f0">
    <span id="bulk-count" class="text-muted" style="font-size:13px;font-weight:600"></span>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="button" class="btn btn-sm btn-secondary" onclick="submitTeamBulk('bulk_active','1')">Show Selected</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="submitTeamBulk('bulk_active','0')">Hide Selected</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="openModal('bulk-role-modal')">Reassign Role&hellip;</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="openBulkArchiveModal()">Archive to Leadership Term&hellip;</button>
      <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeleteTeam()">Delete Selected</button>
    </div>
  </div>
  <form id="bulk-form" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" id="bulk-action-field" value="">
    <input type="hidden" name="bulk_active" id="bulk-active-field" value="">
    <input type="hidden" name="bulk_role_id" id="bulk-role-field" value="">
    <input type="hidden" name="term_id" id="bulk-archive-term-field" value="">
    <input type="hidden" name="new_term_label" id="bulk-archive-new-label-field" value="">
    <input type="hidden" name="new_term_year_start" id="bulk-archive-new-ys-field" value="">
    <input type="hidden" name="new_term_year_end" id="bulk-archive-new-ye-field" value="">
    <div id="bulk-ids-container"></div>
  </form>

  <!-- Bulk Reassign Role Modal -->
  <div class="modal fade" id="bulk-role-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-content" style="max-width:380px">
      <div class="modal-header">
        <span class="modal-title">Reassign Role for Selected</span>
        <button class="modal-close" onclick="closeModal('bulk-role-modal')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>New Role</label>
          <select id="bulk-role-select">
            <option value="">Select a role&hellip;</option>
            <?php foreach ($roles_by_tier as $tier_label => $tier_roles): ?>
              <optgroup label="<?= h($tier_label) ?>">
                <?php foreach ($tier_roles as $r): ?><option value="<?= $r['id'] ?>"><?= h($r['name']) ?></option><?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('bulk-role-modal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="applyBulkRole()">Apply</button>
      </div>
    </div>
  </div>

  <!-- Bulk Archive to Leadership Term Modal -->
  <div class="modal fade" id="bulk-archive-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-content" style="max-width:420px">
      <div class="modal-header">
        <span class="modal-title">Archive Selected to Leadership Term</span>
        <button class="modal-close" onclick="closeModal('bulk-archive-modal')">&times;</button>
      </div>
      <div class="modal-body">
        <p class="text-muted" style="font-size:12.5px;margin-bottom:14px">
          Copies each selected member's name, role, description, and photo into the chosen Leadership Term. The team members themselves are unaffected.
        </p>
        <div class="form-group mb-2">
          <label>Term</label>
          <select id="bulk-archive-term-select" onchange="toggleBulkArchiveNewTerm()">
            <?php foreach ($leadership_terms as $lt): ?>
              <option value="<?= (int) $lt['id'] ?>"><?= h($lt['term_label']) ?></option>
            <?php endforeach; ?>
            <option value="-1">+ Create new term&hellip;</option>
          </select>
        </div>
        <div id="bulk-archive-new-term-fields" style="display:none">
          <div class="form-group mb-2"><label>New Term Label</label><input type="text" id="bulk-archive-new-label" placeholder="2025–2026"></div>
          <div class="form-row">
            <div class="form-group"><label>Year Start</label><input type="number" id="bulk-archive-new-ys" min="1990" max="2100"></div>
            <div class="form-group"><label>Year End</label><input type="number" id="bulk-archive-new-ye" min="1990" max="2100"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('bulk-archive-modal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="applyBulkArchive()">Archive</button>
      </div>
    </div>
  </div>

  <!-- Reorder Modal -->
  <div class="modal fade" id="reorder-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-content" style="max-width:480px">
      <div class="modal-header">
        <span class="modal-title">Reorder Team Members</span>
        <button class="modal-close" onclick="closeModal('reorder-modal')">&times;</button>
      </div>
      <div class="modal-body">
        <p class="text-muted" style="font-size:12.5px;margin-bottom:12px">Drag to reorder. This sets each member's display order (used as a tie-breaker within the same role/tier).</p>
        <div id="reorder-list"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('reorder-modal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveReorder()">Save Order</button>
      </div>
    </div>
  </div>

  <form id="reorder-form" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="reorder">
    <div id="reorder-ids-container"></div>
  </form>
  <?php endif; ?>

  <div class="table-wrap">
    <table id="dt-team">
      <thead>
        <tr>
          <?php if (has_role('editor')): ?><th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th><?php endif; ?>
          <th>Photo</th><th>Name</th><th>Role</th><th>Tier</th><th>Term</th><th>Email</th><th>Order</th><th>Visible</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($team): foreach ($team as $t): ?>
        <tr>
          <?php if (has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $t['id'] ?>" onchange="updateTeamBulkBar()"></td><?php endif; ?>
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
          <td class="text-muted" style="font-size:12px"><?= h($t['tier_label']) ?></td>
          <td class="text-muted"><?= h($t['term'] ?? '') ?: '—' ?></td>
          <td class="text-muted"><?= h($t['email'] ?? '—') ?></td>
          <td><?= $t['display_order'] ?></td>
          <td><?= $t['is_active'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-icon btn-sm btn-secondary" title="View" aria-label="View" onclick="openViewModal(<?= h(json_encode($t)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($t)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <button class="btn btn-icon btn-sm btn-secondary" title="Archive to Leadership History" aria-label="Archive to Leadership History" onclick="openArchiveModal(<?= h(json_encode($t)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><line x1="10" y1="12" x2="14" y2="12"/></svg></button>
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
          <div class="image-field">
            <label class="upload-area" for="at_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p><strong>Upload photo</strong></p>
            </label>
            <input type="file" id="at_image" name="image" accept="image/*" style="display:none" onchange="previewImage(this,'add-team-prev')">
            <div class="thumb-col">
              <span class="thumb-col-label">Preview</span>
              <img id="add-team-prev" src="" alt="Preview" class="image-thumb image-thumb--avatar">
            </div>
          </div>
        </div>
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
          <div class="form-group"><label>Term <span class="text-muted" style="font-weight:400">(e.g. 2025&ndash;2026)</span></label><input type="text" name="term" placeholder="2025–2026"></div>
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0" min="0"></div>
        </div>
        <div class="form-row">
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
          <div class="image-field">
            <label class="upload-area" for="et_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p><strong>Replace photo</strong></p>
            </label>
            <input type="file" id="et_image" name="image" accept="image/*" style="display:none" onchange="previewImage(this,'edit-team-prev')">
            <div id="et_current_photo"></div>
            <div class="thumb-col">
              <span class="thumb-col-label">New</span>
              <img id="edit-team-prev" src="" alt="Preview" class="image-thumb image-thumb--avatar">
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" id="et_name" required></div>
          <div class="form-group"><label>Role *</label>
            <select name="role_id" id="et_role" required>
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
          <div class="form-group"><label>Term <span class="text-muted" style="font-weight:400">(e.g. 2025&ndash;2026)</span></label><input type="text" name="term" id="et_term" placeholder="2025–2026"></div>
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="et_order" min="0"></div>
        </div>
        <div class="form-row">
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

<!-- Archive to Leadership History Modal -->
<div class="modal fade" id="archive-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:460px">
    <div class="modal-header">
      <span class="modal-title">Archive to Leadership History</span>
      <button class="modal-close" onclick="closeModal('archive-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="archive">
        <input type="hidden" name="member_id" id="ar_member_id">
        <p class="text-muted" style="font-size:12.5px;margin-bottom:14px">
          Copies <strong id="ar_member_name"></strong>'s name, role, description, and photo into a Leadership History term. The team member itself is unaffected.
        </p>
        <div class="form-group mb-2">
          <label>Term</label>
          <select name="term_id" id="ar_term_id" onchange="toggleArchiveNewTerm()">
            <?php foreach ($leadership_terms as $lt): ?>
              <option value="<?= (int) $lt['id'] ?>"><?= h($lt['term_label']) ?></option>
            <?php endforeach; ?>
            <option value="-1">+ Create new term&hellip;</option>
          </select>
        </div>
        <div id="ar_new_term_fields" style="display:none">
          <div class="form-group mb-2"><label>New Term Label</label><input type="text" name="new_term_label" id="ar_new_term_label" placeholder="2025–2026"></div>
          <div class="form-row">
            <div class="form-group"><label>Year Start</label><input type="number" name="new_term_year_start" min="1990" max="2100"></div>
            <div class="form-group"><label>Year End</label><input type="number" name="new_term_year_end" min="1990" max="2100"></div>
          </div>
        </div>
        <div class="form-group mb-2"><label>Role</label><input type="text" name="role" id="ar_role"></div>
        <div class="form-group"><label>Description</label><textarea name="description" id="ar_description" style="min-height:70px"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('archive-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Archive</button>
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
      <div><div class="view-dt">Tier</div><div class="view-dd">${esc(t.tier_label)}</div></div>
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
    order: [[<?= has_role('editor') ? 7 : 6 ?>, 'asc']],
    columnDefs: [{ orderable: false, targets: <?= has_role('editor') ? '[0, 1, 9]' : '[0, 8]' ?> }]
  });
});
function openEditModal(t) {
  document.getElementById('et_id').value    = t.id;
  document.getElementById('et_name').value  = t.full_name;
  document.getElementById('et_role').value  = t.role_id || '';
  document.getElementById('et_email').value = t.email || '';
  document.getElementById('et_term').value  = t.term || '';
  document.getElementById('et_linkedin').value  = t.linkedin_url || '';
  document.getElementById('et_instagram').value = t.instagram_url || '';
  document.getElementById('et_order').value = t.display_order || 0;
  document.getElementById('et_desc').value  = t.description || '';
  document.getElementById('et_active').checked = t.is_active == 1;
  const ph = document.getElementById('et_current_photo');
  ph.innerHTML = t.image_path
    ? '<div class="thumb-col"><span class="thumb-col-label">Current</span><img src="' + esc(t.image_path) + '" class="current-photo-thumb current-photo-thumb--avatar"></div>'
    : '';
  openModal('edit-modal');
}

function getCheckedTeamIds() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) { return c.value; });
}
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(function(c) { c.checked = cb.checked; });
  updateTeamBulkBar();
}
function updateTeamBulkBar() {
  var ids = getCheckedTeamIds();
  document.getElementById('bulk-bar').style.display = ids.length ? 'flex' : 'none';
  document.getElementById('bulk-count').textContent = ids.length + ' selected';
}
function submitTeamBulk(action, activeVal) {
  var ids = getCheckedTeamIds();
  if (!ids.length) return;
  document.getElementById('bulk-action-field').value = action;
  document.getElementById('bulk-active-field').value = activeVal || '';
  var container = document.getElementById('bulk-ids-container');
  container.innerHTML = '';
  ids.forEach(function(id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('bulk-form').submit();
}
function bulkDeleteTeam() {
  var ids = getCheckedTeamIds();
  if (ids.length && confirm('Permanently remove ' + ids.length + ' selected team member(s)? This cannot be undone.')) {
    submitTeamBulk('bulk_delete');
  }
}

function applyBulkRole() {
  var ids = getCheckedTeamIds();
  if (!ids.length) return;
  var roleId = document.getElementById('bulk-role-select').value;
  if (!roleId) { alert('Please select a role.'); return; }
  document.getElementById('bulk-action-field').value = 'bulk_reassign_role';
  document.getElementById('bulk-role-field').value = roleId;
  closeModal('bulk-role-modal');
  var container = document.getElementById('bulk-ids-container');
  container.innerHTML = '';
  ids.forEach(function (id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('bulk-form').submit();
}

function openBulkArchiveModal() {
  var ids = getCheckedTeamIds();
  if (!ids.length) return;
  document.getElementById('bulk-archive-term-select').value = '';
  document.getElementById('bulk-archive-new-label').value = '';
  document.getElementById('bulk-archive-new-ys').value = '';
  document.getElementById('bulk-archive-new-ye').value = '';
  toggleBulkArchiveNewTerm();
  openModal('bulk-archive-modal');
}
function toggleBulkArchiveNewTerm() {
  var sel = document.getElementById('bulk-archive-term-select');
  document.getElementById('bulk-archive-new-term-fields').style.display = sel.value === '-1' ? 'block' : 'none';
}
function applyBulkArchive() {
  var ids = getCheckedTeamIds();
  if (!ids.length) return;
  var termId = document.getElementById('bulk-archive-term-select').value;
  if (!termId) { alert('Please select a term.'); return; }
  if (termId === '-1' && !document.getElementById('bulk-archive-new-label').value.trim()) {
    alert('Please enter a label for the new term.');
    return;
  }
  document.getElementById('bulk-action-field').value = 'bulk_archive';
  document.getElementById('bulk-archive-term-field').value = termId;
  document.getElementById('bulk-archive-new-label-field').value = document.getElementById('bulk-archive-new-label').value;
  document.getElementById('bulk-archive-new-ys-field').value = document.getElementById('bulk-archive-new-ys').value;
  document.getElementById('bulk-archive-new-ye-field').value = document.getElementById('bulk-archive-new-ye').value;
  closeModal('bulk-archive-modal');
  var container = document.getElementById('bulk-ids-container');
  container.innerHTML = '';
  ids.forEach(function (id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('bulk-form').submit();
}

function openArchiveModal(t) {
  document.getElementById('ar_member_id').value = t.id;
  document.getElementById('ar_member_name').textContent = t.full_name;
  document.getElementById('ar_role').value = t.role || '';
  document.getElementById('ar_description').value = t.description || '';
  toggleArchiveNewTerm();
  openModal('archive-modal');
}
function toggleArchiveNewTerm() {
  const sel = document.getElementById('ar_term_id');
  document.getElementById('ar_new_term_fields').style.display = sel.value === '-1' ? 'block' : 'none';
}

var allTeamMembers = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'full_name' => $t['full_name'], 'role' => $t['role'], 'image_path' => $t['image_path']], $team)) ?>;

function openReorderModal() {
  const list = document.getElementById('reorder-list');
  list.innerHTML = '';
  allTeamMembers.forEach(function (m) {
    const row = document.createElement('div');
    row.className = 'reorder-row';
    row.draggable = true;
    row.dataset.id = m.id;
    row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;margin-bottom:6px;background:#fff;cursor:grab';

    const avatar = document.createElement('div');
    avatar.style.cssText = 'width:32px;height:32px;border-radius:50%;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#C0396B,#D4882A);color:#fff;font-size:13px;font-weight:700';
    if (m.image_path) {
      const img = document.createElement('img');
      img.src = m.image_path;
      img.style.cssText = 'width:100%;height:100%;object-fit:cover';
      avatar.appendChild(img);
    } else {
      avatar.textContent = m.full_name ? m.full_name[0].toUpperCase() : '?';
    }

    const label = document.createElement('div');
    label.style.cssText = 'flex:1';
    const nameEl = document.createElement('div');
    nameEl.style.cssText = 'font-weight:700;font-size:13px';
    nameEl.textContent = m.full_name;
    const roleEl = document.createElement('div');
    roleEl.style.cssText = 'font-size:11.5px;color:var(--text-muted)';
    roleEl.textContent = m.role;
    label.appendChild(nameEl);
    label.appendChild(roleEl);

    const handle = document.createElement('div');
    handle.innerHTML = '&#8942;&#8942;';
    handle.style.cssText = 'color:var(--text-muted);letter-spacing:-3px;flex-shrink:0';

    row.appendChild(avatar);
    row.appendChild(label);
    row.appendChild(handle);
    list.appendChild(row);
  });
  attachReorderDragHandlers(list);
  openModal('reorder-modal');
}

function attachReorderDragHandlers(list) {
  let dragEl = null;
  list.querySelectorAll('.reorder-row').forEach(function (row) {
    row.addEventListener('dragstart', function () {
      dragEl = row;
      row.style.opacity = '0.4';
    });
    row.addEventListener('dragend', function () {
      row.style.opacity = '1';
    });
    row.addEventListener('dragover', function (e) {
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
  rows.forEach(function (row) {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = row.dataset.id;
    container.appendChild(inp);
  });
  document.getElementById('reorder-form').submit();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
