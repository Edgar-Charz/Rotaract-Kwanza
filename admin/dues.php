<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/MemberDues.php';
require_once dirname(__DIR__) . '/classes/Member.php';

$page_title = 'Dues Tracking';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $member_id    = (int)$_POST['member_id'];
        $year         = (int)$_POST['year'];
        $amount_due   = (float)$_POST['amount_due'];
        $amount_paid  = (float)$_POST['amount_paid'];
        $payment_date = $_POST['payment_date'] ?: '';
        $notes        = trim($_POST['notes']);

        $status = 'unpaid';
        if ($amount_paid >= $amount_due && $amount_due > 0) $status = 'paid';
        elseif ($amount_paid > 0) $status = 'partial';

        (new MemberDues($conn))->save($member_id, $year, $amount_due, $amount_paid, $payment_date, $notes, $status);
        $name = (new Member($conn))->getFullName($member_id);
        log_activity('update_dues', "Updated dues for $name — $year — $status");
        flash('success', 'Dues record saved.');
    }

    if ($action === 'delete') {
        (new MemberDues($conn))->delete((int)$_POST['id']);
        flash('success', 'Dues record deleted.');
    }

    header('Location: ' . ADMIN_URL . '/dues.php?year=' . ($_POST['year'] ?? date('Y')));
    exit;
}

$year    = (int)($_GET['year'] ?? date('Y'));
$years   = range(date('Y'), 2020);
$members = (new Member($conn))->getWithDues($year);

$stats = [
    'total'   => count($members),
    'paid'    => count(array_filter($members, fn($m) => $m['dues_status'] === 'paid')),
    'partial' => count(array_filter($members, fn($m) => $m['dues_status'] === 'partial')),
    'unpaid'  => count(array_filter($members, fn($m) => $m['dues_status'] === 'unpaid')),
    'no_rec'  => count(array_filter($members, fn($m) => $m['dues_id'] === null)),
];
$total_due  = array_sum(array_column($members, 'amount_due'));
$total_paid = array_sum(array_column($members, 'amount_paid'));

include __DIR__ . '/includes/header.php';
?>

<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div><div><div class="stat-label">Paid</div><div class="stat-value"><?= $stats['paid'] ?></div></div></div>
  <div class="stat-card"><div class="stat-icon warning" style="background:#fff3cd;color:#856404"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div><div class="stat-label">Partial</div><div class="stat-value"><?= $stats['partial'] ?></div></div></div>
  <div class="stat-card"><div class="stat-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div><div class="stat-label">Unpaid / No Record</div><div class="stat-value"><?= $stats['unpaid'] + $stats['no_rec'] ?></div></div></div>
  <div class="stat-card"><div class="stat-icon gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div><div class="stat-label">Collected / Due</div><div class="stat-value" style="font-size:18px"><?= number_format($total_paid,2) ?> / <?= number_format($total_due,2) ?></div></div></div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Dues &mdash; <?= $year ?></span>
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <select name="year" onchange="this.form.submit()" style="padding:7px 12px;border:1.5px solid var(--border);border-radius:7px;font-size:13px">
        <?php foreach ($years as $y): ?>
        <option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="table-wrap">
    <table id="dt-dues">
      <thead><tr><th>Member</th><th>Email</th><th>Amount Due</th><th>Amount Paid</th><th>Payment Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
          <td class="fw-bold"><?= h($m['first_name'] . ' ' . $m['last_name']) ?></td>
          <td class="text-muted"><?= h($m['email']) ?></td>
          <td><?= $m['amount_due'] ? number_format($m['amount_due'], 2) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $m['amount_paid'] ? number_format($m['amount_paid'], 2) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-muted"><?= $m['payment_date'] ? date('d M Y', strtotime($m['payment_date'])) : '—' ?></td>
          <td>
            <?php if ($m['dues_id']): ?>
            <span class="badge badge-<?= $m['dues_status'] ?>"><?= $m['dues_status'] ?></span>
            <?php else: ?>
            <span class="text-muted" style="font-size:12px">No record</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-sm btn-info"
              data-member-id="<?= $m['id'] ?>"
              data-name="<?= h($m['first_name'].' '.$m['last_name']) ?>"
              data-amount-due="<?= $m['amount_due'] ?? 0 ?>"
              data-amount-paid="<?= $m['amount_paid'] ?? 0 ?>"
              data-payment-date="<?= $m['payment_date'] ?? '' ?>"
              data-notes="<?= h($m['notes'] ?? '') ?>"
              onclick="openDuesModalFromBtn(this)">
              <?= $m['dues_id'] ? 'Edit' : 'Add' ?>
            </button>
            <?php if ($m['dues_id']): ?>
            <form id="del-d-<?= $m['dues_id'] ?>" method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $m['dues_id'] ?>">
              <input type="hidden" name="year" value="<?= $year ?>">
            </form>
            <button class="btn btn-sm btn-danger" onclick="confirmDelete('del-d-<?= $m['dues_id'] ?>')">Del</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Dues Modal -->
<div class="modal-overlay" id="dues-modal">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <span class="modal-title">Dues Record — <span id="dm_name"></span></span>
      <button class="modal-close" onclick="closeModal('dues-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="member_id" id="dm_member_id">
        <input type="hidden" name="year" value="<?= $year ?>">
        <div class="form-row">
          <div class="form-group"><label>Amount Due</label><input type="number" name="amount_due" id="dm_amount_due" step="0.01" min="0" value="0"></div>
          <div class="form-group"><label>Amount Paid</label><input type="number" name="amount_paid" id="dm_amount_paid" step="0.01" min="0" value="0"></div>
        </div>
        <div class="form-group mb-2"><label>Payment Date</label><input type="date" name="payment_date" id="dm_payment_date"></div>
        <div class="form-group"><label>Notes</label><textarea name="notes" id="dm_notes" style="min-height:70px"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('dues-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Record</button>
      </div>
    </form>
  </div>
</div>

<script>
$(document).ready(function() {
  $('#dt-dues').DataTable({
    pageLength: 25,
    columnDefs: [{ orderable: false, targets: 6 }]
  });
});
function openDuesModalFromBtn(btn) {
  const d = btn.dataset;
  document.getElementById('dm_member_id').value    = d.memberId;
  document.getElementById('dm_name').textContent   = d.name;
  document.getElementById('dm_amount_due').value   = d.amountDue  || 0;
  document.getElementById('dm_amount_paid').value  = d.amountPaid || 0;
  document.getElementById('dm_payment_date').value = d.paymentDate || '';
  document.getElementById('dm_notes').value        = d.notes || '';
  openModal('dues-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
