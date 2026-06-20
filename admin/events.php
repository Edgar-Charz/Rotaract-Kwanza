<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Event.php';

$page_title = 'Events';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $ev = new Event($conn);

    if ($action === 'add') {
        $ev->create(
            trim($_POST['title']), $_POST['event_date'], trim($_POST['event_time']),
            trim($_POST['location']), trim($_POST['description']),
            trim($_POST['category']) ?: 'General', $_POST['status'] ?? 'upcoming',
            isset($_POST['is_featured']) ? 1 : 0
        );
        log_activity('add_event', "Created event: " . trim($_POST['title']) . " on " . $_POST['event_date']);
        flash('success', 'Event created.');
    }

    if ($action === 'edit') {
        $ev->update(
            (int)$_POST['id'],
            trim($_POST['title']), $_POST['event_date'], trim($_POST['event_time']),
            trim($_POST['location']), trim($_POST['description']),
            trim($_POST['category']) ?: 'General', $_POST['status'],
            isset($_POST['is_featured']) ? 1 : 0
        );
        log_activity('edit_event', "Edited event ID " . (int)$_POST['id'] . ": " . trim($_POST['title']));
        flash('success', 'Event updated.');
    }

    if ($action === 'delete') {
        $ev_title = $ev->getTitleById((int)$_POST['id']);
        $ev->delete((int)$_POST['id']);
        log_activity('delete_event', "Deleted event: $ev_title");
        flash('success', 'Event deleted.');
    }

    header('Location: ' . ADMIN_URL . '/events.php');
    exit;
}

$filter = $_GET['status'] ?? '';
$events = (new Event($conn))->getAll($filter);

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <div class="flex align-center gap-2 flex-wrap" style="flex:1">
      <div>
        <a href="?status=" class="btn btn-sm <?= !$filter ? 'btn-primary':'btn-secondary' ?>">All</a>
        <a href="?status=upcoming"  class="btn btn-sm <?= $filter==='upcoming'  ? 'btn-primary':'btn-secondary' ?>">Upcoming</a>
        <a href="?status=past"      class="btn btn-sm <?= $filter==='past'      ? 'btn-primary':'btn-secondary' ?>">Past</a>
        <a href="?status=cancelled" class="btn btn-sm <?= $filter==='cancelled' ? 'btn-primary':'btn-secondary' ?>">Cancelled</a>
      </div>
    </div>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Event
    </button>
  </div>

  <div class="table-wrap">
    <table id="dt-events">
      <thead>
        <tr><th>Title</th><th>Date</th><th>Time</th><th>Location</th><th>Category</th><th>RSVPs</th><th>Status</th><th>Featured</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($events): foreach ($events as $e): ?>
        <tr>
          <td class="fw-bold"><?= h($e['title']) ?></td>
          <td><?= date('d M Y', strtotime($e['event_date'])) ?></td>
          <td class="text-muted"><?= h($e['event_time'] ?? '—') ?></td>
          <td><?= h($e['location'] ?? '—') ?></td>
          <td><?= h($e['category'] ?? '—') ?></td>
          <td>
            <?php if ($e['rsvp_count'] > 0): ?>
            <a href="<?= ADMIN_URL ?>/rsvps.php?event=<?= $e['id'] ?>" style="font-weight:700;color:var(--primary)"><?= $e['rsvp_count'] ?></a>
            <?php else: ?><span class="text-muted">0</span><?php endif; ?>
          </td>
          <td><span class="badge badge-<?= h($e['status']) ?>"><?= h($e['status']) ?></span></td>
          <td><?= $e['is_featured'] ? '<span class="badge badge-featured">Yes</span>' : '<span class="text-muted">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-secondary" onclick="openViewModal(<?= h(json_encode($e)) ?>)">View</button>
              <button class="btn btn-sm btn-info" onclick="openEditModal(<?= h(json_encode($e)) ?>)">Edit</button>
              <form id="del-e-<?= $e['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $e['id'] ?>">
              </form>
              <button class="btn btn-sm btn-danger" onclick="confirmDelete('del-e-<?= $e['id'] ?>')">Delete</button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="add-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add New Event</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" required></div>
        <div class="form-row">
          <div class="form-group"><label>Date *</label><input type="date" name="event_date" required></div>
          <div class="form-group"><label>Time</label><input type="text" name="event_time" placeholder="e.g. 9:00 AM"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Location</label><input type="text" name="location"></div>
          <div class="form-group"><label>Category</label><input type="text" name="category" value="General"></div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description"></textarea></div>
        <div class="form-row">
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <option value="upcoming">Upcoming</option>
              <option value="past">Past</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_featured" id="a_featured" style="width:auto">
            <label for="a_featured" style="font-weight:400">Featured on homepage</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Event</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Event</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="e_id">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" id="e_title" required></div>
        <div class="form-row">
          <div class="form-group"><label>Date *</label><input type="date" name="event_date" id="e_event_date" required></div>
          <div class="form-group"><label>Time</label><input type="text" name="event_time" id="e_event_time"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Location</label><input type="text" name="location" id="e_location"></div>
          <div class="form-group"><label>Category</label><input type="text" name="category" id="e_category"></div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description" id="e_description"></textarea></div>
        <div class="form-row">
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="e_status">
              <option value="upcoming">Upcoming</option>
              <option value="past">Past</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="form-group" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
            <input type="checkbox" name="is_featured" id="e_featured" style="width:auto">
            <label for="e_featured" style="font-weight:400">Featured on homepage</label>
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

<!-- View Modal -->
<div class="modal-overlay" id="view-modal">
  <div class="modal" style="max-width:540px">
    <div class="modal-header">
      <span class="modal-title">Event Details</span>
      <button class="modal-close" onclick="closeModal('view-modal')">&times;</button>
    </div>
    <div class="modal-body" id="view-body"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('view-modal')">Close</button>
    </div>
  </div>
</div>

<script>
function openViewModal(e) {
  document.getElementById('view-body').innerHTML = `
    <div class="view-avatar-row">
      <div>
        <div class="view-name">${esc(e.title)}</div>
        <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap">
          <span class="badge badge-${esc(e.status)}">${esc(e.status)}</span>
          ${e.is_featured == 1 ? '<span class="badge badge-featured">Featured</span>' : ''}
        </div>
      </div>
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Date</div><div class="view-dd">${esc(e.event_date)}</div></div>
      <div><div class="view-dt">Time</div><div class="view-dd">${esc(e.event_time) || '—'}</div></div>
      <div><div class="view-dt">Location</div><div class="view-dd">${esc(e.location) || '—'}</div></div>
      <div><div class="view-dt">Category</div><div class="view-dd">${esc(e.category) || '—'}</div></div>
      <div><div class="view-dt">RSVPs</div><div class="view-dd">${esc(e.rsvp_count) || '0'}</div></div>
      <div><div class="view-dt">Created</div><div class="view-dd">${esc(e.created_at ? e.created_at.substring(0,10) : '')}</div></div>
    </div>
    <div class="view-full">
      <div class="view-dt">Description</div>
      <div class="view-dd">${esc(e.description) || '—'}</div>
    </div>`;
  openModal('view-modal');
}

$(document).ready(function() {
  $('#dt-events').DataTable({
    pageLength: 25,
    order: [[1, 'desc']],
    columnDefs: [{ orderable: false, targets: 8 }]
  });
});
function openEditModal(e) {
  document.getElementById('e_id').value          = e.id;
  document.getElementById('e_title').value       = e.title;
  document.getElementById('e_event_date').value  = e.event_date;
  document.getElementById('e_event_time').value  = e.event_time || '';
  document.getElementById('e_location').value    = e.location || '';
  document.getElementById('e_category').value    = e.category || '';
  document.getElementById('e_description').value = e.description || '';
  document.getElementById('e_status').value      = e.status;
  document.getElementById('e_featured').checked  = e.is_featured == 1;
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
