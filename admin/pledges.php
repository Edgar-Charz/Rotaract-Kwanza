<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Pledge.php';

$page_title = 'Pledges';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $pledge = new Pledge($conn);

    if ($action === 'confirm') {
        $pledge->updateStatus($id, 'confirmed');
        log_activity('confirm_pledge', "Marked pledge ID $id as confirmed");
        flash('success', 'Pledge marked as confirmed.');
    }

    if ($action === 'decline') {
        $pledge->updateStatus($id, 'declined');
        log_activity('decline_pledge', "Marked pledge ID $id as declined");
        flash('success', 'Pledge marked as declined.');
    }

    if ($action === 'unverify') {
        $pledge->updateStatus($id, 'unverified');
        log_activity('unverify_pledge', "Reset pledge ID $id to unverified");
        flash('success', 'Pledge reset to unverified.');
    }

    if ($action === 'delete') {
        $pledge->delete($id);
        log_activity('delete_pledge', "Deleted pledge ID $id");
        flash('success', 'Pledge deleted.');
    }

    $filter_param = isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '';
    header('Location: ' . ADMIN_URL . "/pledges.php$filter_param");
    exit;
}

$pledge_obj = new Pledge($conn);
$filter     = $_GET['status'] ?? '';
$pledges    = $pledge_obj->getAll(in_array($filter, ['unverified', 'confirmed', 'declined'], true) ? $filter : '');

$total_count      = $pledge_obj->count();
$unverified_count = $pledge_obj->count('unverified');
$confirmed_count  = $pledge_obj->count('confirmed');
$confirmed_amount = $pledge_obj->sumConfirmedAmount();

include __DIR__ . '/includes/header.php';
?>

<div class="stats-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px">
  <div class="card"><div class="card-body"><div class="text-muted" style="font-size:12px">Total Pledges</div><div style="font-size:1.6rem;font-weight:700"><?= $total_count ?></div></div></div>
  <div class="card"><div class="card-body"><div class="text-muted" style="font-size:12px">Unverified</div><div style="font-size:1.6rem;font-weight:700"><?= $unverified_count ?></div></div></div>
  <div class="card"><div class="card-body"><div class="text-muted" style="font-size:12px">Confirmed</div><div style="font-size:1.6rem;font-weight:700"><?= $confirmed_count ?></div></div></div>
  <div class="card"><div class="card-body"><div class="text-muted" style="font-size:12px">Confirmed Amount</div><div style="font-size:1.6rem;font-weight:700"><?= number_format($confirmed_amount, 2) ?></div></div></div>
</div>

<div class="card">
  <div class="card-header" style="flex-wrap:wrap;gap:8px">
    <span class="card-title">Pledges</span>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <a href="?" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
      <a href="?status=unverified" class="btn btn-sm <?= $filter === 'unverified' ? 'btn-primary' : 'btn-secondary' ?>">Unverified</a>
      <a href="?status=confirmed" class="btn btn-sm <?= $filter === 'confirmed' ? 'btn-primary' : 'btn-secondary' ?>">Confirmed</a>
      <a href="?status=declined" class="btn btn-sm <?= $filter === 'declined' ? 'btn-primary' : 'btn-secondary' ?>">Declined</a>
    </div>
  </div>

  <div class="table-wrap">
    <table id="dt-pledges">
      <thead>
        <tr>
          <th>Name</th><th>Contact</th><th>Method</th><th>Project</th><th>Amount</th><th>Note</th><th>Status</th><th>Date</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pledges): foreach ($pledges as $p):
          $badge = $p['status'] === 'confirmed' ? 'approved' : ($p['status'] === 'declined' ? 'rejected' : 'pending');
        ?>
        <tr>
          <td class="fw-bold"><?= h($p['name']) ?></td>
          <td>
            <div style="font-size:12.5px"><?= h($p['email']) ?></div>
            <div class="text-muted" style="font-size:11.5px"><?= h($p['phone'] ?: '—') ?></div>
          </td>
          <td>
            <div class="fw-bold" style="font-size:12.5px"><?= h($p['method_label'] ?: ($p['method'] === 'mobile_money' ? 'Mobile Money' : 'Bank Transfer')) ?></div>
            <div class="text-muted" style="font-size:11px"><?= $p['method'] === 'mobile_money' ? 'Mobile Money' : 'Bank Transfer' ?></div>
          </td>
          <td class="text-muted" style="font-size:12.5px"><?= h($p['project_title'] ?: 'General Donation') ?></td>
          <td><?= $p['amount'] !== null ? number_format((float)$p['amount'], 2) : '—' ?></td>
          <td class="text-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($p['note'] ?: '—') ?></td>
          <td><span class="badge badge-<?= $badge ?>"><?= h(ucfirst($p['status'])) ?></span></td>
          <td class="text-muted" style="white-space:nowrap"><?= $p['created_at'] ? date('d M Y H:i', strtotime($p['created_at'])) : '—' ?></td>
          <td>
            <div class="table-actions" style="white-space:nowrap">
              <?php if (has_role('editor')): ?>
                <?php if ($p['status'] !== 'confirmed'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="confirm">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-success" title="Confirm">Confirm</button>
                </form>
                <?php endif; ?>
                <?php if ($p['status'] !== 'declined'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="decline">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" title="Decline">Decline</button>
                </form>
                <?php endif; ?>
                <form id="del-p-<?= $p['id'] ?>" method="POST" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                </form>
                <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-p-<?= $p['id'] ?>')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="9" class="text-muted" style="text-align:center;padding:30px">No pledges found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function() {
  $('#dt-pledges').DataTable({
    pageLength: 25,
    order: [[7, 'desc']],
    columnDefs: [{ orderable: false, targets: [8] }]
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
