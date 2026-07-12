<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/LeadershipTerm.php';
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
        $id    = (int) $_POST['id'];
        $label = $lt->getLabelById($id);
        $path  = $lt->getImagePathById($id);
        if ($path) delete_image($path);
        $lt->delete($id);
        log_activity('delete_leadership_term', "Deleted leadership term: $label");
        flash('success', 'Term and all its officers removed.');
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

  <div class="table-wrap">
    <table id="dt-terms">
      <thead>
        <tr>
          <th>Term</th><th>Years</th><th>Officers</th><th>Summary</th><th>Order</th><th>Visible</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($terms): foreach ($terms as $t): ?>
        <tr>
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
          <input type="file" name="image" accept="image/*" onchange="previewImage(this,'add-term-prev')" style="padding:6px">
          <img id="add-term-prev" src="" alt="Preview" style="display:none;max-width:100%;margin-top:8px;border-radius:8px">
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
          <input type="file" name="image" accept="image/*" onchange="previewImage(this,'edit-term-prev')" style="padding:6px">
          <img id="edit-term-prev" src="" alt="Preview" style="display:none;max-width:100%;margin-top:8px;border-radius:8px">
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

<script>
$(document).ready(function() {
  $('#dt-terms').DataTable({ pageLength: 25, order: [[0, 'desc']], columnDefs: [{ orderable: false, targets: [6] }] });
});

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
