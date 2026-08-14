<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/MonthlyRecognition.php';
$page_title = 'Rotaractor of the Month';
$recognitions = new MonthlyRecognition($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  require_role('editor');
  
  $action = $_POST['action'] ?? '';
  if ($action === 'choose_month') {
    $month = trim($_POST['recognition_month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $month) || $month > date('Y-m')) {
      flash('error', 'Choose the current month or an earlier month.');
    } else {
      $existing = $recognitions->findByMonth($month . '-01');
      if ($existing) {
        header('Location: ' . ADMIN_URL . '/recognition_recipients.php?id=' . (int) $existing['id']);
      } else {
        header('Location: ' . ADMIN_URL . '/members.php?status=approved&recognition_month=' . rawurlencode($month));
      }
      exit;
    }
  } elseif ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $recognitions->delete($id);
    log_activity('delete_monthly_recognition', 'Deleted monthly recognition ID ' . $id);
    flash('success', 'Recognition deleted.');
  }
  header('Location: ' . ADMIN_URL . '/recognitions.php');
  exit;
}

$month = $_GET['month'] ?? date('Y-m');
$month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
$items = $recognitions->getAll();
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-header">
    <span class="card-title">Choose Recognition Month</span>
  </div>
  <p class="text-muted section-hint">Select a month, then choose recipients from the searchable Members table.</p>
  <form method="POST" class="recognition-month-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="choose_month">
    <div class="form-group" style="margin-left: 10px;">
      <label for="recognition_month">Recognition Month *</label>
      <input id="recognition_month" type="month" name="recognition_month" value="<?= h($month) ?>" max="<?= date('Y-m') ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Choose Recipients</button>
  </form>
  <br>
</div>
<br>
<br>
<div class="card mt-20">
  <div class="card-header"><span class="card-title">Recognition Archive</span></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Month</th>
          <th>Recipients</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($items): foreach ($items as $item): ?><tr>
              <td class="fw-bold"><?= h(date('F Y', strtotime($item['recognition_month']))) ?></td>
              <td><?= (int) $item['member_count'] ?> recipient<?= (int) $item['member_count'] === 1 ? '' : 's' ?></td>
              <td><span class="badge <?= $item['is_published'] ? 'badge-approved' : 'badge-rejected' ?>"><?= $item['is_published'] ? 'Published' : 'Draft' ?></span></td>
              <td class="table-actions">
                <a class="btn btn-icon btn-sm btn-secondary" title="Rotaractors of the month" href="recognition_recipients.php?id=<?= (int) $item['id'] ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                  </svg>
                </a>
                <a class="btn btn-icon btn-sm btn-secondary" title="Edit Message" aria-label="Edit Message" href="recognition_recipients.php?id=<?= (int) $item['id'] ?>&amp;settings=1">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                  </svg>
                </a>
                <?php if (has_role('editor')): ?>
                  <form id="delete-recognition-<?= (int) $item['id'] ?>" method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <button type="button" class="btn btn-icon btn-sm btn-danger" title="Delete recognition" aria-label="Delete recognition" onclick="deleteRecognition(<?= (int) $item['id'] ?>)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                        <path d="M10 11v6" />
                        <path d="M14 11v6" />
                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                      </svg>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach;
        else: ?><tr>
            <td colspan="4" class="text-muted">No monthly recognitions have been created.</td>
          </tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
  function deleteRecognition(id) {
    Swal.fire({
      title: 'Delete recognition?',
      text: 'This will permanently remove the recognition and all of its recipients.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Delete',
      confirmButtonColor: '#dc3545'
    }).then(function(result) {
      if (result.isConfirmed) document.getElementById('delete-recognition-' + id).submit();
    });
  }
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>