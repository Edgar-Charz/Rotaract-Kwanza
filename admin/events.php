<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Event.php';
require_once dirname(__DIR__) . '/classes/EventPhoto.php';
require_once dirname(__DIR__) . '/classes/EventRSVP.php';
require_once dirname(__DIR__) . '/classes/Category.php';

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
        $img      = upload_image('image', 'events') ?: '';
        if (!$img && !empty($_FILES['image']['name'])) {
          flash('error', 'Event created, but the image could not be uploaded (invalid file type or too large).');
        }
        $capacity = trim($_POST['capacity'] ?? '') !== '' ? max(0, (int) $_POST['capacity']) : null;
        $ev->create(
          $title,
          $_POST['event_date'],
          trim($_POST['event_time']),
          trim($_POST['location']),
          trim($_POST['description']),
          trim($_POST['category']) ?: 'General',
          $_POST['status'] ?? 'upcoming',
          isset($_POST['is_featured']) ? 1 : 0,
          $img,
          clean_url($_POST['instagram_url'] ?? ''),
          clean_url($_POST['tiktok_url'] ?? ''),
          clean_url($_POST['x_url'] ?? ''),
          $capacity
        );
        log_activity('add_event', "Created event: $title on " . $_POST['event_date']);
        if (!isset($_SESSION['flash'])) flash('success', 'Event created.');
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
        if (!empty($_FILES['image']['name'])) {
          // A fresh upload always wins over a same-request "remove" checkbox.
          $img = upload_image('image', 'events') ?: $oldImg;
          if ($img === $oldImg) {
            flash('error', 'Event updated, but the new image could not be uploaded (invalid file type or too large).');
          }
        } elseif (!empty($_POST['remove_image'])) {
          $img = '';
        } else {
          $img = $oldImg;
        }
        $capacity = trim($_POST['capacity'] ?? '') !== '' ? max(0, (int) $_POST['capacity']) : null;
        $ev->update(
          $id,
          $title,
          $_POST['event_date'],
          trim($_POST['event_time']),
          trim($_POST['location']),
          trim($_POST['description']),
          trim($_POST['category']) ?: 'General',
          $_POST['status'],
          isset($_POST['is_featured']) ? 1 : 0,
          $img,
          clean_url($_POST['instagram_url'] ?? ''),
          clean_url($_POST['tiktok_url'] ?? ''),
          clean_url($_POST['x_url'] ?? ''),
          $capacity
        );
        if ($img !== $oldImg && $oldImg) delete_image($oldImg);
        log_activity('edit_event', "Edited event ID $id: $title");
        if (!isset($_SESSION['flash'])) flash('success', 'Event updated.');
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
    if (array_key_exists($status, Event::STATUSES) && $ids) {
      $count = 0;
      foreach ($ids as $eid) {
        $e = $ev->findById($eid);
        if (!$e) continue;
        $ev->update(
          $eid,
          $e['title'],
          $e['event_date'],
          $e['event_time'],
          $e['location'],
          $e['description'],
          $e['category'],
          $status,
          (int)$e['is_featured'],
          $e['image_path'] ?? '',
          $e['instagram_url'] ?? '',
          $e['tiktok_url'] ?? '',
          $e['x_url'] ?? '',
          $e['capacity'] !== null ? (int)$e['capacity'] : null
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

$filter          = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$events          = (new Event($conn))->getAll($filter, $category_filter);

$form_categories = (new Category($conn))->getActive('event');

$rsvp_obj   = new EventRSVP($conn);
$guest_map  = [];
foreach ($events as $e) {
  $guest_map[$e['id']] = $rsvp_obj->getGuestCount((int) $e['id']);
}

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <div class="flex align-center gap-2 flex-wrap flex-grow-1">
      <div>
        <a href="?<?= http_build_query(array_filter(['category' => $category_filter])) ?>" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
        <?php foreach (Event::STATUSES as $st => $label): ?>
          <a href="?<?= http_build_query(array_filter(['category' => $category_filter, 'status' => $st])) ?>" class="btn btn-sm <?= $filter === $st ? 'btn-primary' : 'btn-secondary' ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </div>
      <?php if ($form_categories): ?>
        <div>
          <a href="?<?= http_build_query(array_filter(['status' => $filter])) ?>" class="btn btn-sm <?= !$category_filter ? 'btn-primary' : 'btn-secondary' ?>">All Categories</a>
          <?php foreach ($form_categories as $c): ?>
            <a href="?<?= http_build_query(array_filter(['status' => $filter, 'category' => $c['name']])) ?>" class="btn btn-sm <?= $category_filter === $c['name'] ? 'btn-primary' : 'btn-secondary' ?>"><?= h($c['name']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php if (has_role('editor')): ?>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-15">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Add Event
      </button>
    <?php endif; ?>
  </div>

  <?php if (has_role('editor')): ?>
    <div class="card-header bulk-bar" id="bulk-bar">
      <span id="bulk-count" class="text-muted fs-13 fw-600"></span>
      <div class="flex-gap8-wrap">
        <?php foreach (Event::STATUSES as $st => $label): ?>
          <button type="button" class="btn btn-sm btn-secondary" onclick="bulkMark('<?= $st ?>')">Mark <?= h($label) ?></button>
        <?php endforeach; ?>
        <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeleteEvents()">Delete Selected</button>
      </div>
    </div>
    <form id="bulk-form" method="POST" class="hidden">
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
          <th>Title</th>
          <th>Image</th>
          <th>Date</th>
          <th>Time</th>
          <th>Location</th>
          <th>Category</th>
          <th>RSVPs</th>
          <th>Capacity</th>
          <th>Status</th>
          <th>Featured</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($events): foreach ($events as $e): ?>
            <tr>
              <?php if (has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $e['id'] ?>" onchange="updateBulkBar()"></td><?php endif; ?>
              <td class="fw-bold"><?= h($e['title']) ?></td>
              <td>
                <?php if ($e['image_path'] ?? ''): ?>
                  <img src="<?= h($e['image_path']) ?>" class="event-thumb">
                <?php else: ?>
                  <span class="text-muted fs-11">No image</span>
                <?php endif; ?>
              </td>
              <td><?= $e['event_date'] ? date('d M Y', strtotime($e['event_date'])) : '—' ?></td>
              <td class="text-muted"><?= h($e['event_time'] ?? '—') ?></td>
              <td><?= h($e['location'] ?? '—') ?></td>
              <td><?= h($e['category'] ?? '—') ?></td>
              <td>
                <?php if ($e['rsvp_count'] > 0): ?>
                  <a href="rsvps.php?event=<?= $e['id'] ?>" class="link-primary-bold"><?= $e['rsvp_count'] ?></a>
                <?php else: ?><span class="text-muted">0</span><?php endif; ?>
              </td>
              <td>
                <?php if ($e['capacity'] !== null): $used = $guest_map[$e['id']] ?? 0;
                  $cap = (int)$e['capacity']; ?>
                  <span class="<?= $used >= $cap ? 'text-danger fw-bold' : 'text-muted' ?> fs-12-5"><?= $used ?> / <?= $cap ?></span>
                <?php else: ?>
                  <span class="text-muted fs-12">Unlimited</span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?= h($e['status']) ?>"><?= h($e['status']) ?></span></td>
              <td><?= $e['is_featured'] ? '<span class="badge badge-featured">Yes</span>' : '<span class="text-muted">No</span>' ?></td>
              <td>
                <div class="table-actions">
                  <button class="btn btn-icon btn-sm btn-secondary" title="View" aria-label="View" onclick="openViewModal(<?= h(json_encode($e)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg></button>
                  <?php if (has_role('editor')): ?>
                    <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($e)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                      </svg></button>
                    <button class="btn btn-icon btn-sm btn-secondary" title="Duplicate" aria-label="Duplicate" onclick="duplicateEvent(<?= h(json_encode($e)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" />
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                      </svg></button>
                    <a href="event_photos.php?event=<?= $e['id'] ?>" class="btn btn-icon btn-sm btn-secondary" title="Manage Photos" aria-label="Manage Photos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" />
                        <circle cx="8.5" cy="8.5" r="1.5" />
                        <polyline points="21 15 16 10 5 21" />
                      </svg></a>
                    <form id="del-e-<?= $e['id'] ?>" method="POST" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    </form>
                    <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDeleteEvent('del-e-<?= $e['id'] ?>', <?= (int)$e['rsvp_count'] ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                        <path d="M10 11v6" />
                        <path d="M14 11v6" />
                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                      </svg></button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
        <?php endforeach;
        endif; ?>
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
          <div class="form-group"><label>Category</label>
            <select name="category">
              <?php foreach ($form_categories as $c): ?><option value="<?= h($c['name']) ?>" <?= $c['name'] === 'General' ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group mb-2">
          <label>Capacity <span class="text-muted fw-normal">(max attendees, incl. guests — leave blank for unlimited)</span></label>
          <input type="number" name="capacity" min="0" placeholder="Unlimited">
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description"></textarea></div>
        <div class="form-group mb-2">
          <label>Event Image (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="ae_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <p><strong>Upload image</strong></p>
            </label>
            <input type="file" id="ae_image" name="image" accept="image/*" class="hidden" onchange="previewImage(this,'add-ev-prev')">
            <div class="thumb-col">
              <span class="thumb-col-label">Preview</span>
              <img id="add-ev-prev" src="" alt="" class="image-thumb image-thumb--wide">
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Instagram URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="instagram_url" placeholder="https://instagram.com/p/..."></div>
          <div class="form-group"><label>TikTok URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="tiktok_url" placeholder="https://tiktok.com/@.../video/..."></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>X URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="x_url" placeholder="https://x.com/.../status/..."></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <?php foreach (Event::STATUSES as $st => $label): ?><option value="<?= $st ?>"><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-group-row pt-22px">
            <input type="checkbox" name="is_featured" id="a_featured" class="w-auto">
            <label for="a_featured" class="fw-normal">Featured on homepage</label>
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
          <div class="form-group"><label>Category</label>
            <select name="category" id="e_category">
              <?php foreach ($form_categories as $c): ?><option value="<?= h($c['name']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group mb-2">
          <label>Capacity <span class="text-muted fw-normal">(max attendees, incl. guests — leave blank for unlimited)</span></label>
          <input type="number" name="capacity" id="e_capacity" min="0" placeholder="Unlimited">
        </div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description" id="e_description"></textarea></div>
        <div class="form-group mb-2">
          <label>Replace Image (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="ee_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <p><strong>Upload image</strong></p>
            </label>
            <input type="file" id="ee_image" name="image" accept="image/*" class="hidden" onchange="previewImage(this,'edit-ev-prev')">
            <div id="e_img_preview"></div>
            <div class="thumb-col">
              <span class="thumb-col-label">New</span>
              <img id="edit-ev-prev" src="" alt="" class="image-thumb image-thumb--wide">
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Instagram URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="instagram_url" id="e_instagram"></div>
          <div class="form-group"><label>TikTok URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="tiktok_url" id="e_tiktok"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>X URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="x_url" id="e_x"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="e_status">
              <?php foreach (Event::STATUSES as $st => $label): ?><option value="<?= $st ?>"><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-group-row pt-22px">
            <input type="checkbox" name="is_featured" id="e_featured" class="w-auto">
            <label for="e_featured" class="fw-normal">Featured on homepage</label>
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
  <div class="modal-dialog modal-content modal-md">
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
    ${e.image_path ? `<img src="${esc(e.image_path)}" class="view-cover-img">` : ''}
    <div class="view-avatar-row">
      <div>
        <div class="view-name">${esc(e.title)}</div>
        <div class="tag-row">
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
      <div><div class="view-dt">Capacity</div><div class="view-dd">${e.capacity !== null ? esc(e.capacity) : 'Unlimited'}</div></div>
      <div><div class="view-dt">Created</div><div class="view-dd">${esc(e.created_at ? e.created_at.substring(0,10) : '')}</div></div>
    </div>
    <div class="view-full">
      <div class="view-dt">Description</div>
      <div class="view-dd">${esc(e.description) || '—'}</div>
    </div>
    <div class="view-dl">
      <div><div class="view-dt">Instagram</div><div class="view-dd">${e.instagram_url ? `<a href="${esc(e.instagram_url)}" target="_blank" rel="noopener">${esc(e.instagram_url)}</a>` : '—'}</div></div>
      <div><div class="view-dt">TikTok</div><div class="view-dd">${e.tiktok_url ? `<a href="${esc(e.tiktok_url)}" target="_blank" rel="noopener">${esc(e.tiktok_url)}</a>` : '—'}</div></div>
      <div><div class="view-dt">X</div><div class="view-dd">${e.x_url ? `<a href="${esc(e.x_url)}" target="_blank" rel="noopener">${esc(e.x_url)}</a>` : '—'}</div></div>
    </div>`;
    openModal('view-modal');
  }

  $(document).ready(function() {
    $('#dt-events').DataTable({
      pageLength: 25,
      order: [
        [<?= has_role('editor') ? 3 : 2 ?>, 'desc']
      ],
      columnDefs: [{
          orderable: false,
          targets: -1
        }
        <?= has_role('editor') ? ", { orderable: false, targets: 0 }" : '' ?>
      ]
    });
  });

  function confirmDeleteEvent(formId, rsvpCount) {
    var msg = rsvpCount > 0 ?
      'This event has ' + rsvpCount + ' RSVP(s). Deleting it will also permanently delete all RSVP and attendance records for this event. Continue?' :
      'Are you sure you want to delete this event? This cannot be undone.';
    if (confirm(msg)) document.getElementById(formId).submit();
  }

  function getCheckedEventIds() {
    return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c) {
      return c.value;
    });
  }

  function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(function(c) {
      c.checked = cb.checked;
    });
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
      inp.type = 'hidden';
      inp.name = 'ids[]';
      inp.value = id;
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
    document.getElementById('e_id').value = e.id;
    document.getElementById('e_title').value = e.title;
    document.getElementById('e_event_date').value = e.event_date;
    document.getElementById('e_event_time').value = e.event_time || '';
    document.getElementById('e_location').value = e.location || '';
    document.getElementById('e_category').value = e.category || '';
    document.getElementById('e_capacity').value = e.capacity !== null ? e.capacity : '';
    document.getElementById('e_description').value = e.description || '';
    document.getElementById('e_status').value = e.status;
    document.getElementById('e_instagram').value = e.instagram_url || '';
    document.getElementById('e_tiktok').value = e.tiktok_url || '';
    document.getElementById('e_x').value = e.x_url || '';
    document.getElementById('e_featured').checked = e.is_featured == 1;
    const prev = document.getElementById('e_img_preview');
    prev.innerHTML = e.image_path ?
      '<div class="thumb-col"><span class="thumb-col-label">Current</span><img src="' + esc(e.image_path) + '" class="current-photo-thumb current-photo-thumb--wide">' +
      '<label class="remove-photo-label"><input type="checkbox" name="remove_image" value="1" class="w-auto"> Remove</label></div>' :
      '';
    document.getElementById('edit-ev-prev').style.display = 'none';
    openModal('edit-modal');
  }

  function duplicateEvent(e) {
    document.getElementById('add-modal').querySelector('form').reset();
    document.getElementById('add-modal').querySelector('[name="title"]').value = e.title;
    document.getElementById('add-modal').querySelector('[name="location"]').value = e.location || '';
    document.getElementById('add-modal').querySelector('[name="category"]').value = e.category || 'General';
    document.getElementById('add-modal').querySelector('[name="capacity"]').value = e.capacity !== null ? e.capacity : '';
    document.getElementById('add-modal').querySelector('[name="description"]').value = e.description || '';
    document.getElementById('add-modal').querySelector('[name="instagram_url"]').value = e.instagram_url || '';
    document.getElementById('add-modal').querySelector('[name="tiktok_url"]').value = e.tiktok_url || '';
    document.getElementById('add-modal').querySelector('[name="x_url"]').value = e.x_url || '';
    document.getElementById('add-modal').querySelector('[name="event_time"]').value = e.event_time || '';
    openModal('add-modal');
  }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>