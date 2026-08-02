<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/ProjectSignup.php';
require_once dirname(__DIR__) . '/classes/Project.php';

$page_title = 'Project Sign-Ups';

$project_id = (int)($_GET['project'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $signup_obj = new ProjectSignup($conn);
    $action     = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id     = (int)$_POST['id'];
        $record = $signup_obj->findById($id);
        if (!$record || ($project_id && (int)$record['project_id'] !== $project_id)) {
            flash('error', 'Sign-up not found.');
        } else {
            $signup_obj->delete($id);
            log_activity('delete_project_signup', "Deleted project sign-up ID $id");
            flash('success', 'Sign-up removed.');
        }
    }

    if ($action === 'contacted') {
        $id     = (int)$_POST['id'];
        $record = $signup_obj->findById($id);
        if (!$record || ($project_id && (int)$record['project_id'] !== $project_id)) {
            flash('error', 'Sign-up not found.');
        } else {
            $contacted = (int)(($_POST['contacted'] ?? 0) == '1');
            $signup_obj->markContacted($id, $contacted);
            flash('success', $contacted ? 'Marked as contacted.' : 'Marked as not contacted.');
        }
    }

    if ($action === 'bulk_contacted') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        if ($project_id) {
            $ids = array_values(array_filter($ids, function ($sid) use ($signup_obj, $project_id) {
                $record = $signup_obj->findById($sid);
                return $record && (int)$record['project_id'] === $project_id;
            }));
        }
        $count = $ids ? $signup_obj->markContactedBatch($ids, 1) : 0;
        log_activity('bulk_contact_project_signups', "Bulk marked $count project sign-up(s) as contacted");
        flash('success', "$count sign-up(s) marked as contacted.");
    }

    if ($action === 'add') {
        $add_project_id = (int) ($_POST['project_id'] ?? 0);
        $name           = trim($_POST['name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $notes          = trim($_POST['notes'] ?? '');

        if (!$add_project_id || !$name || !$email) {
            flash('error', 'Project, name, and email are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email address.');
        } elseif ($signup_obj->alreadySignedUp($add_project_id, $email)) {
            flash('error', 'This email has already signed up for this project.');
        } else {
            $signup_obj->create($add_project_id, $name, $email, $phone, $notes);
            log_activity('add_project_signup', "Manually added sign-up for $name to project ID $add_project_id");
            flash('success', 'Sign-up added.');
        }
    }

    if ($action === 'edit') {
        $id    = (int) $_POST['id'];
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!$name || !$email) {
            flash('error', 'Name and email are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email address.');
        } else {
            $signup_obj->update($id, $name, $email, $phone, $notes);
            log_activity('edit_project_signup', "Edited project sign-up ID $id");
            flash('success', 'Sign-up updated.');
        }
    }

    header('Location: ' . ADMIN_URL . '/project_signups.php' . ($project_id ? '?project=' . $project_id : ''));
    exit;
}

$signup_obj = new ProjectSignup($conn);
$projects   = (new Project($conn))->getAll();
$signups    = $project_id ? $signup_obj->getByProject($project_id) : $signup_obj->getAll();

$counts = [];
foreach ($signup_obj->getSummaryByProject() as $row) {
    $counts[$row['project_id']] = $row['n'];
}

include __DIR__ . '/includes/header.php';
?>

<div class="split-layout" style="display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start">

  <div class="card">
    <div class="card-header"><span class="card-title">Projects</span></div>
    <div style="padding:8px 0">
      <a href="?" style="display:block;padding:10px 16px;font-size:13px;color:var(--text-muted);text-decoration:none;border-radius:6px;margin:0 8px;<?= !$project_id?'background:var(--content-bg);font-weight:700':'' ?>">All Sign-Ups</a>
      <?php foreach ($projects as $p): $c = $counts[$p['id']] ?? null; ?>
      <a href="?project=<?= $p['id'] ?>"
         style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;font-size:13px;color:var(--text);text-decoration:none;border-radius:6px;margin:0 8px;<?= $project_id===$p['id']?'background:var(--content-bg);font-weight:700':'' ?>">
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($p['title']) ?></span>
        <?php if ($c): ?>
        <span style="background:var(--primary);color:#fff;border-radius:10px;font-size:10px;font-weight:700;padding:1px 7px;flex-shrink:0;margin-left:6px"><?= $c ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-header">
        <span class="card-title">
          <?php if ($project_id): ?>
            <?php $sel = (new Project($conn))->findById($project_id); ?>
            <?= h($sel['title'] ?? 'Project') ?>
          <?php else: ?>
            All Sign-Ups
          <?php endif; ?>
        </span>
        <div class="flex align-center gap-2">
          <span class="text-muted" style="font-size:13px"><?= count($signups) ?> sign-up<?= count($signups) !== 1 ? 's' : '' ?></span>
          <a href="export_project_signups.php?project=<?= $project_id ?>" class="btn btn-sm btn-secondary">Export CSV</a>
          <?php if ($project_id && has_role('editor')): ?>
          <button class="btn btn-sm btn-primary" onclick="openModal('add-signup-modal')">+ Add Sign-Up</button>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($project_id && has_role('editor')): ?>
      <div class="card-header" id="bulk-bar" style="display:none;background:#fef6f0">
        <span id="bulk-count" class="text-muted" style="font-size:13px;font-weight:600"></span>
        <div style="display:flex;gap:8px">
          <button type="button" class="btn btn-sm btn-success" onclick="bulkMarkContacted()">Mark Selected as Contacted</button>
        </div>
      </div>
      <form id="bulk-form" method="POST" style="display:none">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="bulk_contacted">
        <div id="bulk-ids-container"></div>
      </form>
      <?php endif; ?>

      <?php
        // Checkbox and Project are mutually exclusive (opposite $project_id
        // conditions) so the real column count is never the naive "all
        // columns" total — it varies by role and whether a project is
        // selected. Used below for the "no results" row's colspan, which a
        // hardcoded number can't track without risking DataTables' "Incorrect
        // column count" warning whenever the header shrinks.
        $colcount = 7
          + ($project_id && has_role('editor') ? 1 : 0)
          + (!$project_id ? 1 : 0)
          + ($project_id ? 1 : 0);
      ?>
      <div class="table-wrap">
        <table id="dt-signups">
          <thead>
            <tr>
              <?php if ($project_id && has_role('editor')): ?><th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th><?php endif; ?>
              <?php if (!$project_id): ?><th>Project</th><?php endif; ?>
              <th>Name</th><th>Email</th><th>Phone</th><th>Notes</th><th>Member</th>
              <?php if ($project_id): ?><th>Contacted</th><?php endif; ?>
              <th>Signed Up</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($signups): foreach ($signups as $s): ?>
            <tr>
              <?php if ($project_id && has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $s['id'] ?>" onchange="updateBulkBar()"></td><?php endif; ?>
              <?php if (!$project_id): ?><td class="text-muted" style="font-size:12px"><?= h($s['project_title']) ?></td><?php endif; ?>
              <td class="fw-bold"><?= h($s['name']) ?></td>
              <td><?= h($s['email']) ?></td>
              <td class="text-muted"><?= h($s['phone'] ?? '—') ?></td>
              <td class="text-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($s['notes'] ?? '—') ?></td>
              <td><?= $s['member_id'] ? '<span class="badge badge-approved">Member</span>' : '<span class="text-muted">—</span>' ?></td>
              <?php if ($project_id): ?>
              <td>
                <?php if (has_role('editor')): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="contacted">
                  <input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <input type="hidden" name="contacted" value="<?= ($s['contacted'] ?? 0) ? 0 : 1 ?>">
                  <button type="submit" class="btn btn-sm <?= ($s['contacted'] ?? 0) ? 'btn-success' : 'btn-secondary' ?>" title="Toggle contacted">
                    <?= ($s['contacted'] ?? 0) ? '✓ Contacted' : 'Mark Contacted' ?>
                  </button>
                </form>
                <?php else: ?>
                <span class="badge <?= ($s['contacted'] ?? 0) ? 'badge-approved' : 'badge-pending' ?>"><?= ($s['contacted'] ?? 0) ? 'Contacted' : 'Not yet' ?></span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
              <td class="text-muted"><?= $s['created_at'] ? date('d M Y H:i', strtotime($s['created_at'])) : '—' ?></td>
              <td>
                <div class="table-actions">
                  <?php if (has_role('editor')): ?>
                  <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditSignupModal(<?= h(json_encode($s)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
                  <form id="del-s-<?= $s['id'] ?>" method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                  </form>
                  <button class="btn btn-icon btn-sm btn-danger" title="Remove" aria-label="Remove" onclick="confirmDelete('del-s-<?= $s['id'] ?>')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="<?= $colcount ?>" class="text-muted" style="text-align:center;padding:30px">No sign-ups found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php if ($project_id && has_role('editor')): ?>
<!-- Add Sign-Up Modal -->
<div class="modal fade" id="add-signup-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:460px">
    <div class="modal-header">
      <span class="modal-title">Add Sign-Up</span>
      <button class="modal-close" onclick="closeModal('add-signup-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="name" required></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
        </div>
        <div class="form-group"><label>Phone</label><input type="tel" name="phone"></div>
        <div class="form-group"><label>Notes</label><textarea name="notes" style="min-height:70px"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-signup-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Sign-Up</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Sign-Up Modal -->
<div class="modal fade" id="edit-signup-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:460px">
    <div class="modal-header">
      <span class="modal-title">Edit Sign-Up</span>
      <button class="modal-close" onclick="closeModal('edit-signup-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="es_id">
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="name" id="es_name" required></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" id="es_email" required></div>
        </div>
        <div class="form-group"><label>Phone</label><input type="tel" name="phone" id="es_phone"></div>
        <div class="form-group"><label>Notes</label><textarea name="notes" id="es_notes" style="min-height:70px"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-signup-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
  var lastCol = $('#dt-signups thead th').length - 1;
  var hasCheckbox = <?= ($project_id && has_role('editor')) ? 'true' : 'false' ?>;
  $('#dt-signups').DataTable({
    pageLength: 25,
    order: [[lastCol - 1, 'desc']],
    columnDefs: hasCheckbox
      ? [{ orderable: false, targets: [0, lastCol] }]
      : [{ orderable: false, targets: lastCol }]
  });
});

function getCheckedSignupIds() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) { return c.value; });
}
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(function(c) { c.checked = cb.checked; });
  updateBulkBar();
}
function updateBulkBar() {
  var ids = getCheckedSignupIds();
  var bar = document.getElementById('bulk-bar');
  if (!bar) return;
  bar.style.display = ids.length ? 'flex' : 'none';
  document.getElementById('bulk-count').textContent = ids.length + ' selected';
}
function bulkMarkContacted() {
  var ids = getCheckedSignupIds();
  if (!ids.length) return;
  if (!confirm('Mark ' + ids.length + ' selected sign-up(s) as contacted?')) return;
  var container = document.getElementById('bulk-ids-container');
  container.innerHTML = '';
  ids.forEach(function(id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('bulk-form').submit();
}
function openEditSignupModal(s) {
  document.getElementById('es_id').value    = s.id;
  document.getElementById('es_name').value  = s.name;
  document.getElementById('es_email').value = s.email;
  document.getElementById('es_phone').value = s.phone || '';
  document.getElementById('es_notes').value = s.notes || '';
  openModal('edit-signup-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
