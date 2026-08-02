<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/NewsletterSubscriber.php';

$page_title = 'Newsletter Subscribers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $subs   = new NewsletterSubscriber($conn);

    if ($action === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $row = $subs->findById($id);
        $subs->delete($id);
        log_activity('delete_newsletter_subscriber', 'Removed subscriber' . ($row ? ": {$row['email']}" : " ID $id"));
        flash('success', 'Subscriber removed.');
    }

    header('Location: ' . ADMIN_URL . '/newsletter.php');
    exit;
}

$subscribers = (new NewsletterSubscriber($conn))->getAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">Newsletter Subscribers</span>
    <span class="text-muted" style="font-size:13px"><?= count($subscribers) ?> subscriber<?= count($subscribers) !== 1 ? 's' : '' ?></span>
  </div>

  <div class="table-wrap">
    <table id="dt-subscribers">
      <thead>
        <tr><th>Email</th><th>Subscribed</th><th></th></tr>
      </thead>
      <tbody>
        <?php if ($subscribers): foreach ($subscribers as $s): ?>
        <tr>
          <td class="fw-bold"><?= h($s['email']) ?></td>
          <td class="text-muted"><?= $s['created_at'] ? date('d M Y H:i', strtotime($s['created_at'])) : '—' ?></td>
          <td>
            <?php if (has_role('editor')): ?>
            <div class="table-actions">
              <form id="del-sub-<?= $s['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Remove" aria-label="Remove" onclick="confirmDelete('del-sub-<?= $s['id'] ?>')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              </button>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="3" class="text-muted" style="text-align:center;padding:30px">No subscribers yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function() {
  $('#dt-subscribers').DataTable({
    pageLength: 25,
    order: [[1, 'desc']],
    columnDefs: [{ orderable: false, targets: 2 }]
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
