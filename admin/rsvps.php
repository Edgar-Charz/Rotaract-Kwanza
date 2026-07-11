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

    header('Location: ' . ADMIN_URL . '/rsvps.php' . ($event_id ? '?event=' . $event_id : ''));
    exit;
}

$rsvp_obj = new EventRSVP($conn);
$events = (new Event($conn))->getAllTitles();
$rsvps  = $event_id ? $rsvp_obj->getByEvent($event_id) : $rsvp_obj->getAll();

$attendance = $event_id ? $rsvp_obj->getAttendanceSummary($event_id) : null;

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
    <div class="stats-grid" style="margin-bottom:16px;grid-template-columns:repeat(3,1fr)">
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
        </div>
      </div>
      <div class="table-wrap">
        <table id="dt-rsvps">
          <thead>
            <tr>
              <?php if (!$event_id): ?><th>Event</th><?php endif; ?>
              <th>Name</th><th>Email</th><th>Phone</th><th>Guests</th><th>Notes</th>
              <?php if ($event_id): ?><th>Attended</th><?php endif; ?>
              <th>Registered</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rsvps): foreach ($rsvps as $r): ?>
            <tr>
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
                <?php if (has_role('editor')): ?>
                <form id="del-r-<?= $r['id'] ?>" method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                </form>
                <button class="btn btn-icon btn-sm btn-danger" title="Remove" aria-label="Remove" onclick="confirmDelete('del-r-<?= $r['id'] ?>')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
                <?php endif; ?>
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

<script>
$(document).ready(function() {
  var lastCol = $('#dt-rsvps thead th').length - 1;
  $('#dt-rsvps').DataTable({
    pageLength: 25,
    order: [[lastCol - 1, 'desc']],
    columnDefs: [{ orderable: false, targets: lastCol }]
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
