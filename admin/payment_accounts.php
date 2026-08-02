<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/PaymentAccount.php';

$page_title = 'Payment Accounts';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $pa = new PaymentAccount($conn);

    if ($action === 'add') {
        $type   = ($_POST['type'] ?? '') === 'mobile_money' ? 'mobile_money' : 'bank_transfer';
        $label  = trim($_POST['label'] ?? '');
        $details = mb_substr(trim($_POST['details'] ?? ''), 0, 500);
        if (!$label || !$details) {
            flash('error', 'Label and details are required.');
        } else {
            $pa->create($type, $label, $details, (int)($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0);
            log_activity('add_payment_account', "Added payment account: $label");
            flash('success', 'Payment account added.');
        }
    }

    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $type   = ($_POST['type'] ?? '') === 'mobile_money' ? 'mobile_money' : 'bank_transfer';
        $label  = trim($_POST['label'] ?? '');
        $details = mb_substr(trim($_POST['details'] ?? ''), 0, 500);
        if (!$label || !$details) {
            flash('error', 'Label and details are required.');
        } else {
            $pa->update($id, $type, $label, $details, (int)($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0);
            log_activity('edit_payment_account', "Edited payment account ID $id: $label");
            flash('success', 'Payment account updated.');
        }
    }

    if ($action === 'delete') {
        $id  = (int)$_POST['id'];
        $acc = $pa->findById($id);
        $pa->delete($id);
        log_activity('delete_payment_account', "Removed payment account: " . ($acc['label'] ?? "ID $id"));
        flash('success', 'Payment account removed. Existing donation pledges against it keep their record.');
    }

    header('Location: ' . ADMIN_URL . '/payment_accounts.php');
    exit;
}

$accounts = (new PaymentAccount($conn))->getAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($accounts) ?> Payment Account<?= count($accounts) !== 1 ? 's' : '' ?></span>
    <?php if (has_role('editor')): ?>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Account
    </button>
    <?php endif; ?>
  </div>
  <p class="text-muted" style="font-size:12.5px;padding:0 16px 12px">
    Bank and mobile-money accounts shown on the public Donate page — add as many as needed. Hide one without deleting it by unchecking "Visible".
  </p>
  <div class="table-wrap">
    <table id="dt-accounts">
      <thead>
        <tr><th>Type</th><th>Label</th><th>Details</th><th>Order</th><th>Visible</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($accounts): foreach ($accounts as $a): ?>
        <tr>
          <td><?= $a['type'] === 'mobile_money' ? 'Mobile Money' : 'Bank Transfer' ?></td>
          <td class="fw-bold"><?= h($a['label']) ?></td>
          <td class="text-muted" style="font-size:12px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h(str_replace("\n", ' · ', $a['details'])) ?></td>
          <td><?= $a['display_order'] ?></td>
          <td><?= $a['is_active'] ? '<span class="badge badge-approved">Yes</span>' : '<span class="badge badge-rejected">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditAccountModal(<?= h(json_encode($a)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <form id="del-a-<?= $a['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-a-<?= $a['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
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
      <span class="modal-title">Add Payment Account</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
          <div class="form-group"><label>Type</label>
            <select name="type">
              <option value="bank_transfer">Bank Transfer</option>
              <option value="mobile_money">Mobile Money</option>
            </select>
          </div>
          <div class="form-group"><label>Label *</label><input type="text" name="label" placeholder="e.g. Standard Bank (USD)" required></div>
        </div>
        <div class="form-group mb-2"><label>Details *</label><textarea name="details" maxlength="500" placeholder="Account name, number, bank, branch..." required></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0" min="0"></div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_active" id="aa_active" checked style="width:auto">
            <label for="aa_active" style="font-weight:400">Visible on site</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Account</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Edit Payment Account</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="ea_id">
        <div class="form-row">
          <div class="form-group"><label>Type</label>
            <select name="type" id="ea_type">
              <option value="bank_transfer">Bank Transfer</option>
              <option value="mobile_money">Mobile Money</option>
            </select>
          </div>
          <div class="form-group"><label>Label *</label><input type="text" name="label" id="ea_label" required></div>
        </div>
        <div class="form-group mb-2"><label>Details *</label><textarea name="details" id="ea_details" maxlength="500" required></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="ea_order" min="0"></div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_active" id="ea_active" style="width:auto">
            <label for="ea_active" style="font-weight:400">Visible on site</label>
          </div>
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
  $('#dt-accounts').DataTable({ pageLength: 25, order: [[0, 'asc'], [3, 'asc']], columnDefs: [{ orderable: false, targets: [5] }] });
});
function openEditAccountModal(a) {
  document.getElementById('ea_id').value      = a.id;
  document.getElementById('ea_type').value    = a.type;
  document.getElementById('ea_label').value   = a.label;
  document.getElementById('ea_details').value = a.details;
  document.getElementById('ea_order').value   = a.display_order || 0;
  document.getElementById('ea_active').checked = a.is_active == 1;
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
