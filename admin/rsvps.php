<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/EventRSVP.php';
require_once dirname(__DIR__) . '/classes/Event.php';

$page_title = 'Event RSVPs';

$event_id = (int)($_GET['event'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $rsvp_obj = new EventRSVP($conn);
    $action   = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $rsvp_obj->delete((int)$_POST['id']);
        log_activity('delete_rsvp', "Deleted RSVP ID " . (int)$_POST['id']);
        flash('success', 'RSVP removed.');
    }

    if ($action === 'attend') {
        $attended = (int)(($_POST['attended'] ?? 0) == '1');
        $rsvp_obj->markAttended((int)$_POST['id'], $attended);
        flash('success', $attended ? 'Marked as present.' : 'Marked as not present.');
    }

    if ($action === 'bulk_attend') {
        $ids   = array_map('intval', $_POST['ids'] ?? []);
        $count = 0;
        foreach ($ids as $rid) {
            $rsvp_obj->markAttended($rid, 1);
            $count++;
        }
        log_activity('bulk_mark_attended', "Bulk marked $count RSVP(s) as attended");
        flash('success', "$count RSVP(s) marked as attended.");
    }

    if ($action === 'add') {
        $add_event_id = (int) ($_POST['event_id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $guests       = max(1, (int) ($_POST['guests'] ?? 1));
        $notes        = trim($_POST['notes'] ?? '');

        if (!$add_event_id || !$name || !$email) {
            flash('error', 'Event, name, and email are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email address.');
        } else {
            $ev          = (new Event($conn))->findById($add_event_id);
            $guests_used = ($ev && $ev['capacity'] !== null) ? $rsvp_obj->getGuestCount($add_event_id) : 0;
            if ($ev && $ev['capacity'] !== null && ($guests_used + $guests) > (int) $ev['capacity']) {
                flash('error', 'Not enough spots left for this event.');
            } elseif ($rsvp_obj->alreadyRegistered($add_event_id, $email)) {
                flash('error', "This email has already RSVP'd to this event.");
            } else {
                $rsvp_obj->create($add_event_id, $name, $email, $phone, $guests, $notes);
                log_activity('add_rsvp', "Manually added RSVP for $name to event ID $add_event_id");
                flash('success', 'RSVP added.');
            }
        }
    }

    if ($action === 'edit') {
        $id     = (int) $_POST['id'];
        $name   = trim($_POST['name'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $guests = max(1, (int) ($_POST['guests'] ?? 1));
        $notes  = trim($_POST['notes'] ?? '');

        if (!$name || !$email) {
            flash('error', 'Name and email are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email address.');
        } else {
            $rsvp_obj->update($id, $name, $email, $phone, $guests, $notes);
            log_activity('edit_rsvp', "Edited RSVP ID $id");
            flash('success', 'RSVP updated.');
        }
    }

    header('Location: ' . ADMIN_URL . '/rsvps.php' . ($event_id ? '?event=' . $event_id : ''));
    exit;
}

$rsvp_obj = new EventRSVP($conn);
$events = (new Event($conn))->getAllTitles();
$rsvps  = $event_id ? $rsvp_obj->getByEvent($event_id) : $rsvp_obj->getAll();

$attendance     = $event_id ? $rsvp_obj->getAttendanceSummary($event_id) : null;
$selected_event = $event_id ? (new Event($conn))->findById($event_id) : null;
$guests_used    = ($selected_event && $selected_event['capacity'] !== null) ? $rsvp_obj->getGuestCount($event_id) : 0;
$spots_left     = ($selected_event && $selected_event['capacity'] !== null) ? max(0, (int) $selected_event['capacity'] - $guests_used) : null;

$counts = [];
foreach ($rsvp_obj->getSummaryByEvent() as $row) {
    $counts[$row['event_id']] = $row;
}

include __DIR__ . '/includes/header.php';
?>

<div class="split-layout" style="display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start">

  <div class="card">
    <div class="card-header"><span class="card-title">Events</span></div>
    <div style="padding:8px 0">
      <a href="?" style="display:block;padding:10px 16px;font-size:13px;color:var(--text-muted);text-decoration:none;border-radius:6px;margin:0 8px;<?= !$event_id?'background:var(--content-bg);font-weight:700':'' ?>">All RSVPs</a>
      <?php foreach ($events as $ev): $c = $counts[$ev['id']] ?? null; ?>
      <a href="?event=<?= $ev['id'] ?>"
         style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;font-size:13px;color:var(--text);text-decoration:none;border-radius:6px;margin:0 8px;<?= $event_id===$ev['id']?'background:var(--content-bg);font-weight:700':'' ?>">
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($ev['title']) ?></span>
        <?php if ($c): ?>
        <span style="background:var(--primary);color:#fff;border-radius:10px;font-size:10px;font-weight:700;padding:1px 7px;flex-shrink:0;margin-left:6px"><?= $c['n'] ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <?php if ($event_id && $attendance): ?>
    <div class="stats-grid" style="margin-bottom:16px;grid-template-columns:repeat(<?= $spots_left !== null ? 4 : 3 ?>,1fr)">
      <div class="stat-card">
        <div class="stat-icon pink"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
        <div><div class="stat-label">Registrations</div><div class="stat-value"><?= $attendance['total'] ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div><div class="stat-label">Attended</div><div class="stat-value"><?= (int)$attendance['attended'] ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
        <div>
          <div class="stat-label">Attendance Rate</div>
          <div class="stat-value">
            <?= $attendance['total'] > 0 ? round(($attendance['attended'] / $attendance['total']) * 100) : 0 ?>%
          </div>
        </div>
      </div>
      <?php if ($spots_left !== null): ?>
      <div class="stat-card">
        <div class="stat-icon <?= $spots_left <= 0 ? 'red' : 'blue' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div>
          <div class="stat-label">Spots Left</div>
          <div class="stat-value"><?= $spots_left ?></div>
          <div class="stat-sub"><?= $guests_used ?> of <?= (int) $selected_event['capacity'] ?> reserved</div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <span class="card-title">
          <?php if ($event_id): ?>
            <?= h((new Event($conn))->getTitleById($event_id) ?: 'Event') ?>
          <?php else: ?>
            All RSVPs
          <?php endif; ?>
        </span>
        <div class="flex align-center gap-2">
          <span class="text-muted" style="font-size:13px">
            <?= count($rsvps) ?> registrations &mdash; <?= array_sum(array_column($rsvps,'guests')) ?> guests total
          </span>
          <a href="export_rsvps.php?event=<?= $event_id ?>" class="btn btn-sm btn-secondary">Export CSV</a>
          <?php if ($event_id && has_role('editor')): ?>
          <button class="btn btn-sm btn-primary" onclick="openModal('add-rsvp-modal')">+ Add RSVP</button>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($event_id && has_role('editor')): ?>
      <div class="card-header" id="bulk-bar" style="display:none;background:#fef6f0">
        <span id="bulk-count" class="text-muted" style="font-size:13px;font-weight:600"></span>
        <div style="display:flex;gap:8px">
          <button type="button" class="btn btn-sm btn-success" onclick="bulkMarkAttended()">Mark Selected as Attended</button>
        </div>
      </div>
      <form id="bulk-form" method="POST" style="display:none">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="bulk_attend">
        <div id="bulk-ids-container"></div>
      </form>
      <?php endif; ?>

      <div class="table-wrap">
        <table id="dt-rsvps">
          <thead>
            <tr>
              <?php if ($event_id && has_role('editor')): ?><th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th><?php endif; ?>
              <?php if (!$event_id): ?><th>Event</th><?php endif; ?>
              <th>Name</th><th>Email</th><th>Phone</th><th>Guests</th><th>Notes</th>
              <?php if ($event_id): ?><th>Attended</th><?php endif; ?>
              <th>Registered</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rsvps): foreach ($rsvps as $r): ?>
            <tr>
              <?php if ($event_id && has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $r['id'] ?>" onchange="updateBulkBar()"></td><?php endif; ?>
              <?php if (!$event_id): ?><td class="text-muted" style="font-size:12px"><?= h($r['event_title']) ?></td><?php endif; ?>
              <td class="fw-bold"><?= h($r['name']) ?></td>
              <td><?= h($r['email']) ?></td>
              <td class="text-muted"><?= h($r['phone'] ?? '—') ?></td>
              <td><?= $r['guests'] ?></td>
              <td class="text-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($r['notes'] ?? '—') ?></td>
              <?php if ($event_id): ?>
              <td>
                <?php if (has_role('editor')): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="attend">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <input type="hidden" name="attended" value="<?= ($r['attended'] ?? 0) ? 0 : 1 ?>">
                  <button type="submit" class="btn btn-sm <?= ($r['attended'] ?? 0) ? 'btn-success' : 'btn-secondary' ?>" title="Toggle attendance">
                    <?= ($r['attended'] ?? 0) ? '✓ Present' : 'Mark Present' ?>
                  </button>
                </form>
                <?php else: ?>
                <span class="badge <?= ($r['attended'] ?? 0) ? 'badge-approved' : 'badge-pending' ?>"><?= ($r['attended'] ?? 0) ? 'Present' : 'Not marked' ?></span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
              <td class="text-muted"><?= $r['created_at'] ? date('d M Y H:i', strtotime($r['created_at'])) : '—' ?></td>
              <td>
                <div class="table-actions">
                  <?php if (has_role('editor')): ?>
                  <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditRsvpModal(<?= h(json_encode($r)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
                  <form id="del-r-<?= $r['id'] ?>" method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  </form>
                  <button class="btn btn-icon btn-sm btn-danger" title="Remove" aria-label="Remove" onclick="confirmDelete('del-r-<?= $r['id'] ?>')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="10" class="text-muted" style="text-align:center;padding:30px">No RSVPs found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php if ($event_id && has_role('editor')): ?>
<!-- Add RSVP Modal -->
<div class="modal fade" id="add-rsvp-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:460px">
    <div class="modal-header">
      <span class="modal-title">Add RSVP</span>
      <button class="modal-close" onclick="closeModal('add-rsvp-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="event_id" value="<?= $event_id ?>">
        <?php if ($spots_left !== null): ?>
          <p class="text-muted" style="font-size:12.5px;margin-bottom:12px"><?= $spots_left ?> spot<?= $spots_left === 1 ? '' : 's' ?> left for this event.</p>
        <?php endif; ?>
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="name" required></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Phone</label><input type="tel" name="phone"></div>
          <div class="form-group"><label>Guests</label><input type="number" name="guests" value="1" min="1" max="10"></div>
        </div>
        <div class="form-group"><label>Notes</label><textarea name="notes" style="min-height:70px"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-rsvp-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add RSVP</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit RSVP Modal -->
<div class="modal fade" id="edit-rsvp-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:460px">
    <div class="modal-header">
      <span class="modal-title">Edit RSVP</span>
      <button class="modal-close" onclick="closeModal('edit-rsvp-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="er_id">
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input type="text" name="name" id="er_name" required></div>
          <div class="form-group"><label>Email *</label><input type="email" name="email" id="er_email" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Phone</label><input type="tel" name="phone" id="er_phone"></div>
          <div class="form-group"><label>Guests</label><input type="number" name="guests" id="er_guests" min="1" max="10"></div>
        </div>
        <div class="form-group"><label>Notes</label><textarea name="notes" id="er_notes" style="min-height:70px"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-rsvp-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
  var lastCol = $('#dt-rsvps thead th').length - 1;
  var hasCheckbox = <?= ($event_id && has_role('editor')) ? 'true' : 'false' ?>;
  $('#dt-rsvps').DataTable({
    pageLength: 25,
    order: [[lastCol - 1, 'desc']],
    columnDefs: hasCheckbox
      ? [{ orderable: false, targets: [0, lastCol] }]
      : [{ orderable: false, targets: lastCol }]
  });
});

function getCheckedRsvpIds() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) { return c.value; });
}
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(function(c) { c.checked = cb.checked; });
  updateBulkBar();
}
function updateBulkBar() {
  var ids = getCheckedRsvpIds();
  var bar = document.getElementById('bulk-bar');
  if (!bar) return;
  bar.style.display = ids.length ? 'flex' : 'none';
  document.getElementById('bulk-count').textContent = ids.length + ' selected';
}
function bulkMarkAttended() {
  var ids = getCheckedRsvpIds();
  if (!ids.length) return;
  if (!confirm('Mark ' + ids.length + ' selected RSVP(s) as attended?')) return;
  var container = document.getElementById('bulk-ids-container');
  container.innerHTML = '';
  ids.forEach(function(id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('bulk-form').submit();
}
function openEditRsvpModal(r) {
  document.getElementById('er_id').value     = r.id;
  document.getElementById('er_name').value   = r.name;
  document.getElementById('er_email').value  = r.email;
  document.getElementById('er_phone').value  = r.phone || '';
  document.getElementById('er_guests').value = r.guests || 1;
  document.getElementById('er_notes').value  = r.notes || '';
  openModal('edit-rsvp-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
