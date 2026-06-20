<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/EventRSVP.php';
require_once dirname(__DIR__) . '/classes/Event.php';

$page_title = 'Event RSVPs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if ($_POST['action'] === 'delete') {
        (new EventRSVP($conn))->delete((int)$_POST['id']);
        log_activity('delete_rsvp', "Deleted RSVP ID " . (int)$_POST['id']);
        flash('success', 'RSVP removed.');
    }
    header('Location: ' . ADMIN_URL . '/rsvps.php' . (isset($_GET['event']) ? '?event='.(int)$_GET['event'] : ''));
    exit;
}

$event_id = (int)($_GET['event'] ?? 0);
$rsvp_obj = new EventRSVP($conn);

$events = (new Event($conn))->getAll();
$rsvps  = $event_id ? $rsvp_obj->getByEvent($event_id) : $rsvp_obj->getAll();

$counts = [];
foreach ($rsvp_obj->getSummaryByEvent() as $row) {
    $counts[$row['event_id']] = $row;
}

include __DIR__ . '/includes/header.php';
?>

<div style="display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start">

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

  <div class="card">
    <div class="card-header">
      <span class="card-title"><?= $event_id ? h((new Event($conn))->getTitleById($event_id) ?: 'Event') : 'All RSVPs' ?></span>
      <span class="text-muted" style="font-size:13px"><?= count($rsvps) ?> registrations &mdash; <?= array_sum(array_column($rsvps,'guests')) ?> guests total</span>
    </div>
    <div class="table-wrap">
      <table id="dt-rsvps">
        <thead><tr><?php if (!$event_id): ?><th>Event</th><?php endif; ?><th>Name</th><th>Email</th><th>Phone</th><th>Guests</th><th>Notes</th><th>Registered</th><th></th></tr></thead>
        <tbody>
          <?php if ($rsvps): foreach ($rsvps as $r): ?>
          <tr>
            <?php if (!$event_id): ?><td class="text-muted" style="font-size:12px"><?= h($r['event_title']) ?></td><?php endif; ?>
            <td class="fw-bold"><?= h($r['name']) ?></td>
            <td><?= h($r['email']) ?></td>
            <td class="text-muted"><?= h($r['phone'] ?? '—') ?></td>
            <td><?= $r['guests'] ?></td>
            <td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($r['notes'] ?? '—') ?></td>
            <td class="text-muted"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
            <td>
              <form id="del-r-<?= $r['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
              </form>
              <button class="btn btn-sm btn-danger" onclick="confirmDelete('del-r-<?= $r['id'] ?>')">Remove</button>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
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
