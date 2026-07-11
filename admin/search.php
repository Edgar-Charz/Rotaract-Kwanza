<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Search';

$q       = trim($_GET['q'] ?? '');
$results = [];

if (strlen($q) >= 2) {
    $like = '%' . $q . '%';

    // Members
    $members = db_rows($conn,
        "SELECT id, first_name, last_name, email, status, 'member' AS type FROM members
         WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?
         LIMIT 10",
        [$like, $like, $like, $like]
    );

    // Events
    $events = db_rows($conn,
        "SELECT id, title, event_date, status, 'event' AS type FROM events
         WHERE title LIKE ? OR location LIKE ? OR description LIKE ?
         LIMIT 10",
        [$like, $like, $like]
    );

    // Announcements
    $posts = db_rows($conn,
        "SELECT id, title, slug, category, is_published, 'announcement' AS type FROM announcements
         WHERE title LIKE ? OR content LIKE ?
         LIMIT 10",
        [$like, $like]
    );

    // Messages
    $messages = db_rows($conn,
        "SELECT id, full_name, email, subject, status, 'message' AS type FROM contact_messages
         WHERE full_name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?
         LIMIT 10",
        [$like, $like, $like, $like]
    );

    $results = [
        'members'       => $members,
        'events'        => $events,
        'announcements' => $posts,
        'messages'      => $messages,
    ];
}

$total = array_sum(array_map('count', $results));

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom:20px;display:flex;gap:10px;max-width:560px">
  <div style="flex:1;position:relative">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:17px;height:17px;color:#aaa;pointer-events:none">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input type="text" id="search-page-input" name="q" value="<?= h($q) ?>"
           placeholder="Search members, events, announcements, messages…" autofocus
           style="width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;font-family:inherit">
  </div>
</div>
<div id="search-status" class="text-muted" style="margin-bottom:16px;font-size:13.5px">
<?php if ($q !== '' && strlen($q) < 2): ?>
  Enter at least 2 characters.
<?php elseif ($q !== '' && $total === 0): ?>
  No results found for <strong><?= h($q) ?></strong>.
<?php elseif ($q !== ''): ?>
  <?= $total ?> result<?= $total !== 1 ? 's' : '' ?> for <strong><?= h($q) ?></strong>
<?php endif; ?>
</div>

<div id="search-results">
<?php if ($q !== '' && $total > 0): ?>

  <?php if ($results['members']): ?>
  <div class="card mb-2">
    <div class="card-header"><span class="card-title">Members (<?= count($results['members']) ?>)</span><a href="members.php" class="btn btn-sm btn-secondary">All Members</a></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($results['members'] as $m): ?>
          <tr>
            <td class="fw-bold"><?= h($m['first_name'] . ' ' . $m['last_name']) ?></td>
            <td><?= h($m['email']) ?></td>
            <td><span class="badge badge-<?= h($m['status']) ?>"><?= h($m['status']) ?></span></td>
            <td><a href="members.php" class="btn btn-sm btn-secondary">View</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($results['events']): ?>
  <div class="card mb-2">
    <div class="card-header"><span class="card-title">Events (<?= count($results['events']) ?>)</span><a href="events.php" class="btn btn-sm btn-secondary">All Events</a></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Title</th><th>Date</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($results['events'] as $e): ?>
          <tr>
            <td class="fw-bold"><?= h($e['title']) ?></td>
            <td class="text-muted"><?= date('d M Y', strtotime($e['event_date'])) ?></td>
            <td><span class="badge badge-<?= h($e['status']) ?>"><?= h($e['status']) ?></span></td>
            <td><a href="rsvps.php?event=<?= $e['id'] ?>" class="btn btn-sm btn-secondary">RSVPs</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($results['announcements']): ?>
  <div class="card mb-2">
    <div class="card-header"><span class="card-title">Announcements (<?= count($results['announcements']) ?>)</span><a href="announcements.php" class="btn btn-sm btn-secondary">All Posts</a></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Title</th><th>Category</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($results['announcements'] as $p): ?>
          <tr>
            <td class="fw-bold"><?= h($p['title']) ?></td>
            <td><?= h($p['category']) ?></td>
            <td><?= $p['is_published'] ? '<span class="badge badge-approved">Published</span>' : '<span class="badge badge-pending">Draft</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($results['messages']): ?>
  <div class="card mb-2">
    <div class="card-header"><span class="card-title">Messages (<?= count($results['messages']) ?>)</span><a href="messages.php" class="btn btn-sm btn-secondary">All Messages</a></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>From</th><th>Subject</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($results['messages'] as $msg): ?>
          <tr>
            <td>
              <div class="fw-bold"><?= h($msg['full_name']) ?></div>
              <div class="text-muted" style="font-size:11.5px"><?= h($msg['email']) ?></div>
            </td>
            <td><?= h($msg['subject'] ?? '(no subject)') ?></td>
            <td><span class="badge badge-<?= h($msg['status']) ?>"><?= h($msg['status']) ?></span></td>
            <td><a href="messages.php?view=<?= $msg['id'] ?>" class="btn btn-sm btn-secondary">View</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

<?php endif; ?>
</div><!-- #search-results -->

<?php include __DIR__ . '/includes/footer.php'; ?>
