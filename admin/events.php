<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Event.php';
require_once dirname(__DIR__) . '/classes/EventPhoto.php';

$page_title = 'Events';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_role('editor');
    $action = $_POST['action'] ?? '';
    $ev = new Event($conn);

    if ($action === 'add') {
        $title    = trim($_POST['title']);
        $date_ok  = DateTime::createFromFormat('Y-m-d', $_POST['event_date'] ?? '') !== false;
        if ($title === '') {
            flash('error', 'Title is required.');
        } elseif (!$date_ok) {
            flash('error', 'Please provide a valid event date.');
        } else {
            try {
                $img = upload_image('image', 'events') ?: '';
                $ev->create(
                    $title, $_POST['event_date'], trim($_POST['event_time']),
                    trim($_POST['location']), trim($_POST['description']),
                    trim($_POST['category']) ?: 'General', $_POST['status'] ?? 'upcoming',
                    isset($_POST['is_featured']) ? 1 : 0, $img,
                    trim($_POST['instagram_url'] ?? ''), trim($_POST['tiktok_url'] ?? '')
                );
                log_activity('add_event', "Created event: $title on " . $_POST['event_date']);
                flash('success', 'Event created.');
            } catch (mysqli_sql_exception $e) {
                flash('error', 'Could not create event.');
            }
        }
    }

    if ($action === 'edit') {
        $id      = (int)$_POST['id'];
        $title   = trim($_POST['title']);
        $date_ok = DateTime::createFromFormat('Y-m-d', $_POST['event_date'] ?? '') !== false;
        if ($title === '') {
            flash('error', 'Title is required.');
        } elseif (!$date_ok) {
            flash('error', 'Please provide a valid event date.');
        } else {
            try {
                $oldImg = $ev->getImagePathById($id);
                $img    = upload_image('image', 'events') ?: $oldImg;
                $ev->update(
                    $id,
                    $title, $_POST['event_date'], trim($_POST['event_time']),
                    trim($_POST['location']), trim($_POST['description']),
                    trim($_POST['category']) ?: 'General', $_POST['status'],
                    isset($_POST['is_featured']) ? 1 : 0, $img,
                    trim($_POST['instagram_url'] ?? ''), trim($_POST['tiktok_url'] ?? '')
                );
                if ($img !== $oldImg && $oldImg) delete_image($oldImg);
                log_activity('edit_event', "Edited event ID $id: $title");
                flash('success', 'Event updated.');
            } catch (mysqli_sql_exception $e) {
                flash('error', 'Could not update event.');
            }
        }
    }

    if ($action === 'delete') {
        $id       = (int)$_POST['id'];
        $ev_title = $ev->getTitleById($id);
        $imgPath  = $ev->getImagePathById($id);
        $photos   = (new EventPhoto($conn))->getByEvent($id);
        $ev->delete($id); // cascades event_photos and event_rsvps rows via FK
        if ($imgPath) delete_image($imgPath);
        foreach ($photos as $p) delete_image($p['image_path']);
        log_activity('delete_event', "Deleted event: $ev_title");
        flash('success', 'Event deleted.');
    }

    if ($action === 'bulk_status') {
        $status = $_POST['bulk_status'] ?? '';
        $ids    = array_map('intval', $_POST['ids'] ?? []);
        if (in_array($status, ['upcoming', 'past', 'cancelled'], true) && $ids) {
            $count = 0;
            foreach ($ids as $eid) {
                $e = $ev->findById($eid);
                if (!$e) continue;
                $ev->update(
                    $eid, $e['title'], $e['event_date'], $e['event_time'], $e['location'],
                    $e['description'], $e['category'], $status, (int)$e['is_featured'],
                    $e['image_path'] ?? '', $e['instagram_url'] ?? '', $e['tiktok_url'] ?? ''
                );
                $count++;
            }
            log_activity('bulk_update_event_status', "Bulk set $count event(s) to $status");
            flash('success', "$count event(s) updated to $status.");
        }
    }

    if ($action === 'bulk_delete') {
        $ids   = array_map('intval', $_POST['ids'] ?? []);
        $count = 0;
        foreach ($ids as $eid) {
            $title = $ev->getTitleById($eid);
            if (!$title) continue;
            $imgPath = $ev->getImagePathById($eid);
            $photos  = (new EventPhoto($conn))->getByEvent($eid);
            $ev->delete($eid);
            if ($imgPath) delete_image($imgPath);
            foreach ($photos as $p) delete_image($p['image_path']);
            $count++;
        }
        log_activity('bulk_delete_event', "Bulk deleted $count event(s)");
        flash('success', "$count event(s) deleted.");
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
    <?php if (has_role('editor')): ?>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Event
    </button>
    <?php endif; ?>
  </div>

  <?php if (has_role('editor')): ?>
  <div class="card-header" id="bulk-bar" style="display:none;background:#fef6f0">
    <span id="bulk-count" class="text-muted" style="font-size:13px;font-weight:600"></span>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="button" class="btn btn-sm btn-secondary" onclick="bulkMark('upcoming')">Mark Upcoming</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="bulkMark('past')">Mark Past</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="bulkMark('cancelled')">Mark Cancelled</button>
      <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeleteEvents()">Delete Selected</button>
    </div>
  </div>
  <form id="bulk-form" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" id="bulk-action-field" value="">
    <input type="hidden" name="bulk_status" id="bulk-status-field" value="">
    <div id="bulk-ids-container"></div>
  </form>
  <?php endif; ?>

  <div class="table-wrap">
    <table id="dt-events">
      <thead>
        <tr>
          <?php if (has_role('editor')): ?><th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th><?php endif; ?>
          <th>Title</th><th>Image</th><th>Date</th><th>Time</th><th>Location</th><th>Category</th><th>RSVPs</th><th>Status</th><th>Featured</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($events): foreach ($events as $e): ?>
        <tr>
          <?php if (has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $e['id'] ?>" onchange="updateBulkBar()"></td><?php endif; ?>
          <td class="fw-bold"><?= h($e['title']) ?></td>
          <td>
            <?php if ($e['image_path'] ?? ''): ?>
              <img src="<?= h($e['image_path']) ?>" style="width:48px;height:36px;object-fit:cover;border-radius:5px">
            <?php else: ?>
              <span class="text-muted" style="font-size:11px">No image</span>
            <?php endif; ?>
          </td>
          <td><?= $e['event_date'] ? date('d M Y', strtotime($e['event_date'])) : '—' ?></td>
          <td class="text-muted"><?= h($e['event_time'] ?? '—') ?></td>
          <td><?= h($e['location'] ?? '—') ?></td>
          <td><?= h($e['category'] ?? '—') ?></td>
          <td>
            <?php if ($e['rsvp_count'] > 0): ?>
            <a href="rsvps.php?event=<?= $e['id'] ?>" style="font-weight:700;color:var(--primary)"><?= $e['rsvp_count'] ?></a>
            <?php else: ?><span class="text-muted">0</span><?php endif; ?>
          </td>
          <td><span class="badge badge-<?= h($e['status']) ?>"><?= h($e['status']) ?></span></td>
          <td><?= $e['is_featured'] ? '<span class="badge badge-featured">Yes</span>' : '<span class="text-muted">No</span>' ?></td>
          <td>
            <div class="table-actions">
              <button class="btn btn-icon btn-sm btn-secondary" title="View" aria-label="View" onclick="openViewModal(<?= h(json_encode($e)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
              <?php if (has_role('editor')): ?>
              <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($e)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg></button>
              <a href="event_photos.php?event=<?= $e['id'] ?>" class="btn btn-icon btn-sm btn-secondary" title="Manage Photos" aria-label="Manage Photos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></a>
              <form id="del-e-<?= $e['id'] ?>" method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $e['id'] ?>">
              </form>
              <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDeleteEvent('del-e-<?= $e['id'] ?>', <?= (int)$e['rsvp_count'] ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
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
      <span class="modal-title">Add New Event</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" required maxlength="200"></div>
        <div class="form-row">
          <div class="form-group"><label>Date *</label><input type="date" name="event_date" required></div>
          <div class="form-group"><label>Time</label><input type="text" name="event_time" placeholder="e.g. 9:00 AM"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Location</label><input type="text" name="location" maxlength="200"></div>
          <div class="form-group"><label>Category</label><input type="text" name="category" value="General" maxlength="50"></div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description"></textarea></div>
        <div class="form-group mb-2">
          <label>Event Image (optional)</label>
          <input type="file" name="image" accept="image/*" onchange="previewImage(this,'add-ev-prev')" style="padding:6px">
          <img id="add-ev-prev" src="" alt="" style="display:none;max-height:100px;margin-top:8px;border-radius:6px">
        </div>
        <div class="form-row">
          <div class="form-group"><label>Instagram URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="instagram_url" placeholder="https://instagram.com/p/..."></div>
          <div class="form-group"><label>TikTok URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="tiktok_url" placeholder="https://tiktok.com/@.../video/..."></div>
        </div>
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
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Edit Event</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="e_id">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" id="e_title" required maxlength="200"></div>
        <div class="form-row">
          <div class="form-group"><label>Date *</label><input type="date" name="event_date" id="e_event_date" required></div>
          <div class="form-group"><label>Time</label><input type="text" name="event_time" id="e_event_time"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Location</label><input type="text" name="location" id="e_location" maxlength="200"></div>
          <div class="form-group"><label>Category</label><input type="text" name="category" id="e_category" maxlength="50"></div>
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description" id="e_description"></textarea></div>
        <div class="form-group mb-2">
          <label>Replace Image (optional)</label>
          <div id="e_img_preview" style="margin-bottom:6px"></div>
          <input type="file" name="image" accept="image/*" onchange="previewImage(this,'edit-ev-prev')" style="padding:6px">
          <img id="edit-ev-prev" src="" alt="" style="display:none;max-height:100px;margin-top:8px;border-radius:6px">
        </div>
        <div class="form-row">
          <div class="form-group"><label>Instagram URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="instagram_url" id="e_instagram"></div>
          <div class="form-group"><label>TikTok URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="tiktok_url" id="e_tiktok"></div>
        </div>
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
<div class="modal fade" id="view-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content" style="max-width:540px">
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
    ${e.image_path ? `<img src="${esc(e.image_path)}" style="width:100%;max-height:180px;object-fit:cover;border-radius:8px;margin-bottom:14px">` : ''}
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
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Instagram</div><div class="view-dd">${e.instagram_url ? `<a href="${esc(e.instagram_url)}" target="_blank" rel="noopener">${esc(e.instagram_url)}</a>` : '—'}</div></div>
      <div><div class="view-dt">TikTok</div><div class="view-dd">${e.tiktok_url ? `<a href="${esc(e.tiktok_url)}" target="_blank" rel="noopener">${esc(e.tiktok_url)}</a>` : '—'}</div></div>
    </div>`;
  openModal('view-modal');
}

$(document).ready(function() {
  $('#dt-events').DataTable({
    pageLength: 25,
    order: [[<?= has_role('editor') ? 3 : 2 ?>, 'desc']],
    columnDefs: [
      { orderable: false, targets: -1 }<?= has_role('editor') ? ", { orderable: false, targets: 0 }" : '' ?>
    ]
  });
});

function confirmDeleteEvent(formId, rsvpCount) {
  var msg = rsvpCount > 0
    ? 'This event has ' + rsvpCount + ' RSVP(s). Deleting it will also permanently delete all RSVP and attendance records for this event. Continue?'
    : 'Are you sure you want to delete this event? This cannot be undone.';
  if (confirm(msg)) document.getElementById(formId).submit();
}

function getCheckedEventIds() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) { return c.value; });
}
function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(function(c) { c.checked = cb.checked; });
  updateBulkBar();
}
function updateBulkBar() {
  var ids = getCheckedEventIds();
  document.getElementById('bulk-bar').style.display = ids.length ? 'flex' : 'none';
  document.getElementById('bulk-count').textContent = ids.length + ' selected';
}
function submitBulk(action, status) {
  var ids = getCheckedEventIds();
  if (!ids.length) return;
  document.getElementById('bulk-action-field').value = action;
  document.getElementById('bulk-status-field').value = status || '';
  var container = document.getElementById('bulk-ids-container');
  container.innerHTML = '';
  ids.forEach(function(id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('bulk-form').submit();
}
function bulkMark(status) {
  var ids = getCheckedEventIds();
  if (ids.length && confirm('Mark ' + ids.length + ' selected event(s) as ' + status + '?')) submitBulk('bulk_status', status);
}
function bulkDeleteEvents() {
  var ids = getCheckedEventIds();
  if (ids.length && confirm('Permanently delete ' + ids.length + ' selected event(s), including all their RSVPs and photos? This cannot be undone.')) submitBulk('bulk_delete');
}

function openEditModal(e) {
  document.getElementById('e_id').value          = e.id;
  document.getElementById('e_title').value       = e.title;
  document.getElementById('e_event_date').value  = e.event_date;
  document.getElementById('e_event_time').value  = e.event_time || '';
  document.getElementById('e_location').value    = e.location || '';
  document.getElementById('e_category').value    = e.category || '';
  document.getElementById('e_description').value = e.description || '';
  document.getElementById('e_status').value      = e.status;
  document.getElementById('e_instagram').value   = e.instagram_url || '';
  document.getElementById('e_tiktok').value      = e.tiktok_url || '';
  document.getElementById('e_featured').checked  = e.is_featured == 1;
  const prev = document.getElementById('e_img_preview');
  prev.innerHTML = e.image_path ? '<img src="'+esc(e.image_path)+'" style="max-height:80px;border-radius:6px">' : '';
  document.getElementById('edit-ev-prev').style.display = 'none';
  openModal('edit-modal');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
