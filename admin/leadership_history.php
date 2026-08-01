<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/LeadershipTerm.php';
require_once dirname(__DIR__) . '/classes/LeadershipMember.php';
require_once dirname(__DIR__) . '/classes/LeadershipTermPhoto.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$page_title = 'Leadership History';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $lt = new LeadershipTerm($conn);

    if ($action === 'add') {
        $label = trim($_POST['term_label'] ?? '');
        if ($label === '') {
            flash('error', 'Term label is required.');
        } else {
            try {
                $img = upload_image('image', 'leadership') ?: '';
                $ys  = (int) ($_POST['year_start'] ?? 0) ?: null;
                $ye  = (int) ($_POST['year_end'] ?? 0) ?: null;
                $lt->create(
                    $label, $ys, $ye,
                    trim($_POST['summary'] ?? ''),
                    $img,
                    (int) ($_POST['display_order'] ?? 0),
                    isset($_POST['is_active']) ? 1 : 0
                );
                log_activity('add_leadership_term', "Added leadership term: $label");
                flash('success', 'Term added. You can now add officers to this term.');
            } catch (mysqli_sql_exception $e) {
                flash('error', 'Could not add term.');
            }
        }
    }

    if ($action === 'edit') {
        $id    = (int) $_POST['id'];
        $label = trim($_POST['term_label'] ?? '');
        if ($label === '') {
            flash('error', 'Term label is required.');
        } else {
            try {
                $oldImg = $lt->getImagePathById($id);
                $img    = upload_image('image', 'leadership') ?: $oldImg;
                $ys     = (int) ($_POST['year_start'] ?? 0) ?: null;
                $ye     = (int) ($_POST['year_end'] ?? 0) ?: null;
                $lt->update(
                    $id, $label, $ys, $ye,
                    trim($_POST['summary'] ?? ''),
                    $img,
                    (int) ($_POST['display_order'] ?? 0),
                    isset($_POST['is_active']) ? 1 : 0
                );
                if ($img && $img !== $oldImg && $oldImg) delete_image($oldImg);
                log_activity('edit_leadership_term', "Edited leadership term ID $id: $label");
                flash('success', 'Term updated.');
            } catch (mysqli_sql_exception $e) {
                flash('error', 'Could not update term.');
            }
        }
    }

    if ($action === 'delete') {
        $id     = (int) $_POST['id'];
        $label  = $lt->getLabelById($id);
        $path   = $lt->getImagePathById($id);
        $lm     = new LeadershipMember($conn);
        $ltp    = new LeadershipTermPhoto($conn);
        $photos = $ltp->getByTerm($id);
        foreach ($lm->getByTermId($id) as $officer) {
            if ($officer['photo_path']) delete_image($officer['photo_path']);
        }
        if ($path) delete_image($path);
        foreach ($photos as $p) delete_image($p['image_path']);
        $lt->delete($id); // cascades leadership_members and leadership_term_photos rows via FK
        log_activity('delete_leadership_term', "Deleted leadership term: $label");
        flash('success', 'Term and all its officers removed.');
    }

    if ($action === 'bulk_active') {
        $active = $_POST['bulk_active'] === '1' ? 1 : 0;
        $ids    = array_map('intval', $_POST['ids'] ?? []);
        $count  = 0;
        foreach ($ids as $tid) {
            $t = $lt->findById($tid);
            if (!$t) continue;
            $lt->update(
                $tid, $t['term_label'], $t['year_start'] ?: null, $t['year_end'] ?: null,
                $t['summary'] ?? '', $t['image_path'] ?? '', (int) $t['display_order'], $active
            );
            $count++;
        }
        log_activity('bulk_update_leadership_terms', "Bulk set $count term(s) " . ($active ? 'visible' : 'hidden'));
        flash('success', "$count term(s) updated.");
    }

    if ($action === 'bulk_delete') {
        $ids   = array_map('intval', $_POST['ids'] ?? []);
        $count = 0;
        $lm    = new LeadershipMember($conn);
        $ltp   = new LeadershipTermPhoto($conn);
        foreach ($ids as $tid) {
            $label = $lt->getLabelById($tid);
            if (!$label) continue;
            foreach ($lm->getByTermId($tid) as $officer) {
                if ($officer['photo_path']) delete_image($officer['photo_path']);
            }
            foreach ($ltp->getByTerm($tid) as $p) {
                delete_image($p['image_path']);
            }
            $path = $lt->getImagePathById($tid);
            if ($path) delete_image($path);
            $lt->delete($tid);
            $count++;
        }
        log_activity('bulk_delete_leadership_terms', "Bulk deleted $count term(s)");
        flash('success', "$count term(s) and their officers removed.");
    }

    if ($action === 'duplicate') {
        $source_id = (int) ($_POST['source_id'] ?? 0);
        $new_label = trim($_POST['new_term_label'] ?? '');
        $source    = $source_id ? $lt->findById($source_id) : false;

        if (!$source) {
            flash('error', 'Source term not found.');
        } elseif ($new_label === '') {
            flash('error', 'New term label is required.');
        } else {
            $ys    = (int) ($_POST['new_term_year_start'] ?? 0) ?: null;
            $ye    = (int) ($_POST['new_term_year_end'] ?? 0) ?: null;
            $photo = $source['image_path'] ? (copy_uploaded_image($source['image_path'], 'leadership') ?: '') : '';

            $new_term_id = $lt->create($new_label, $ys, $ye, $source['summary'] ?? '', $photo, 0, 1);

            $lm     = new LeadershipMember($conn);
            $copied = 0;
            foreach ($lm->getByTermId($source_id) as $o) {
                $officer_photo = $o['photo_path'] ? (copy_uploaded_image($o['photo_path'], 'leadership') ?: '') : '';
                $lm->create(
                    $new_term_id, $o['full_name'], $o['role'], $o['description'] ?? '', $officer_photo,
                    (int) $o['display_order'], 1,
                    $o['linkedin_url'] ?? '', $o['instagram_url'] ?? '',
                    (int) ($o['role_id'] ?? 0)
                );
                $copied++;
            }

            log_activity('duplicate_leadership_term', "Duplicated term '{$source['term_label']}' into '$new_label' with $copied officer(s)");
            flash('success', "Term duplicated with $copied officer(s). Edit the new term's details as needed.");
        }
    }

    header('Location: ' . ADMIN_URL . '/leadership_history.php');
    exit;
}

$terms = (new LeadershipTerm($conn))->getAllWithMemberCounts();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($terms) ?> Leadership Term<?= count($terms) !== 1 ? 's' : '' ?></span>
    <?php if (has_role('editor')): ?>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Term
    </button>
    <?php endif; ?>
  </div>
  <p class="text-muted" style="font-size:12.5px;padding:0 16px 12px">
    Manage past leadership by term. Create a term first, then open it to add officers for that period.
  </p>

  <?php if (has_role('editor')): ?>
  <div class="card-header" id="bulk-bar" style="display:none;background:#fef6f0">
    <span id="bulk-count" class="text-muted" style="font-size:13px;font-weight:600"></span>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="button" class="btn btn-sm btn-secondary" onclick="submitTermBulk('bulk_active','1')">Show Selected</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="submitTermBulk('bulk_active','0')">Hide Selected</button>
      <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeleteTerms()">Delete Selected</button>
    </div>
  </div>
  <form id="bulk-form" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" id="bulk-action-field" value="">
    <input type="hidden" name="bulk_active" id="bulk-active-field" value="">
    <div id="bulk-ids-container"></div>
  </form>
  <?php endif; ?>

  <div class="table-wrap">
    <table id="dt-terms">
      <thead>
        <tr>
          <?php if (has_role('editor')): ?><th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th><?php endif; ?>
          <th>Term</th><th>Years</th><th>Officers</th><th>Summary</th><th>Order</th><th>Visible</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($terms): foreach ($terms as $t): ?>
        <tr>
          <?php if (has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $t['id'] ?>" onchange="updateBulkBar()"></td><?php endif; ?>
          <td class="fw-bold"><?= h($t['term_label']) ?></td>
          <td class="text-muted">
            <?php if ($t['year_start']): ?>
              <?= (int) $t['year_start'] ?><?= $t['year_end'] && $t['year_end'] != $t['year_start'] ? '–' . (int) $t['year_end'] : '' ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><span class="badge badge-approved"><?= (int) $t['member_count'] ?></span></td>
          <td class="text-muted" style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($t['summary'] ?? '') ?: '—' ?></td>
          <td><?= (int) $t['display_order'] ?></td>
          <td><?= $t['is_active'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <a href="leadership_term.php?id=<?= (int) $t['id'] ?>" class="btn btn-icon btn-sm btn-secondary" title="Manage Officers" aria-label="Manage Officers">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </a>
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit Term" aria-label="Edit Term" onclick="openEditTermModal(<?= h(json_encode($t)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <button class="btn btn-icon btn-sm btn-secondary" title="Duplicate Term" aria-label="Duplicate Term" onclick="openDuplicateModal(<?= (int) $t['id'] ?>, <?= h(json_encode($t['term_label'])) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
              <form id="del-term-<?= $t['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete Term" aria-label="Delete Term" onclick="if(confirm('Delete this term and all its officers? This cannot be undone.')) document.getElementById('del-term-<?= $t['id'] ?>').submit()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Term Modal -->
<div class="modal fade" id="add-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Add Leadership Term</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group mb-2"><label>Term Label *</label><input type="text" name="term_label" required placeholder="2024–2025"></div>
        <div class="form-row">
          <div class="form-group"><label>Year Start</label><input type="number" name="year_start" min="1990" max="2100" placeholder="2024"></div>
          <div class="form-group"><label>Year End</label><input type="number" name="year_end" min="1990" max="2100" placeholder="2025"></div>
        </div>
        <div class="form-group mb-2"><label>Term Summary</label><textarea name="summary" placeholder="Brief overview of this leadership period…"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0" min="0"></div>
        </div>
        <div class="form-group mb-2">
          <label>Group Photo (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="at_term_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p><strong>Upload photo</strong></p>
            </label>
            <input type="file" id="at_term_image" name="image" accept="image/*" style="display:none" onchange="previewImage(this,'add-term-prev')">
            <div class="thumb-col">
              <span class="thumb-col-label">Preview</span>
              <img id="add-term-prev" src="" alt="Preview" class="image-thumb image-thumb--wide">
            </div>
          </div>
        </div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" id="at_active" checked style="width:auto">
          <label for="at_active" style="font-weight:400">Visible on public page</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Term</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Term Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Edit Leadership Term</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="et_id">
        <div class="form-group mb-2"><label>Term Label *</label><input type="text" name="term_label" id="et_label" required></div>
        <div class="form-row">
          <div class="form-group"><label>Year Start</label><input type="number" name="year_start" id="et_ys" min="1990" max="2100"></div>
          <div class="form-group"><label>Year End</label><input type="number" name="year_end" id="et_ye" min="1990" max="2100"></div>
        </div>
        <div class="form-group mb-2"><label>Term Summary</label><textarea name="summary" id="et_summary"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="et_order" min="0"></div>
        </div>
        <div class="form-group mb-2">
          <label>Group Photo (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="et_term_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p><strong>Replace photo</strong></p>
            </label>
            <input type="file" id="et_term_image" name="image" accept="image/*" style="display:none" onchange="previewImage(this,'edit-term-prev')">
            <div class="thumb-col">
              <span class="thumb-col-label">Photo</span>
              <img id="edit-term-prev" src="" alt="Preview" class="image-thumb image-thumb--wide">
            </div>
          </div>
        </div>
        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" id="et_active" style="width:auto">
          <label for="et_active" style="font-weight:400">Visible on public page</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Duplicate Term Modal -->
<div class="modal fade" id="duplicate-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Duplicate Term</span>
      <button class="modal-close" onclick="closeModal('duplicate-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="duplicate">
        <input type="hidden" name="source_id" id="dup_source_id">
        <p class="text-muted" style="font-size:12.5px;margin-bottom:14px">
          Copies <strong id="dup_source_label"></strong> and all of its officers (name, role, description, photo, socials) into a new term.
        </p>
        <div class="form-group mb-2"><label>New Term Label *</label><input type="text" name="new_term_label" id="dup_new_label" required placeholder="2026–2027"></div>
        <div class="form-row">
          <div class="form-group"><label>Year Start</label><input type="number" name="new_term_year_start" min="1990" max="2100"></div>
          <div class="form-group"><label>Year End</label><input type="number" name="new_term_year_end" min="1990" max="2100"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('duplicate-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Duplicate</button>
      </div>
    </form>
  </div>
</div>

<script>
$(document).ready(function() {
  $('#dt-terms').DataTable({
    pageLength: 25,
    order: [[<?= has_role('editor') ? 1 : 0 ?>, 'desc']],
    columnDefs: [
      { orderable: false, targets: -1 }<?= has_role('editor') ? ", { orderable: false, targets: 0 }" : '' ?>
    ]
  });
});

function openDuplicateModal(id, label) {
  document.getElementById('dup_source_id').value = id;
  document.getElementById('dup_source_label').textContent = label;
  document.getElementById('dup_new_label').value = '';
  openModal('duplicate-modal');
}

function getCheckedTermIds() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(function (c) { return c.value; });
}
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(function (c) { c.checked = cb.checked; });
  updateBulkBar();
}
function updateBulkBar() {
  var ids = getCheckedTermIds();
  document.getElementById('bulk-bar').style.display = ids.length ? 'flex' : 'none';
  document.getElementById('bulk-count').textContent = ids.length + ' selected';
}
function submitTermBulk(action, activeVal) {
  var ids = getCheckedTermIds();
  if (!ids.length) return;
  document.getElementById('bulk-action-field').value = action;
  document.getElementById('bulk-active-field').value = activeVal || '';
  var container = document.getElementById('bulk-ids-container');
  container.innerHTML = '';
  ids.forEach(function (id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('bulk-form').submit();
}
function bulkDeleteTerms() {
  var ids = getCheckedTermIds();
  if (ids.length && confirm('Permanently delete ' + ids.length + ' selected term(s) and all their officers? This cannot be undone.')) {
    submitTermBulk('bulk_delete');
  }
}

function openEditTermModal(t) {
  document.getElementById('et_id').value = t.id;
  document.getElementById('et_label').value = t.term_label || '';
  document.getElementById('et_ys').value = t.year_start || '';
  document.getElementById('et_ye').value = t.year_end || '';
  document.getElementById('et_summary').value = t.summary || '';
  document.getElementById('et_order').value = t.display_order || 0;
  document.getElementById('et_active').checked = t.is_active == 1;
  const prev = document.getElementById('edit-term-prev');
  if (prev) {
    if (t.image_path) { prev.src = t.image_path; prev.style.display = 'block'; }
    else prev.style.display = 'none';
  }
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
