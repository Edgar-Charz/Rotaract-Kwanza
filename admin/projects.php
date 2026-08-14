<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Project.php';
require_once dirname(__DIR__) . '/classes/ProjectPhoto.php';
require_once dirname(__DIR__) . '/classes/Pledge.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$page_title = 'Projects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  require_role('editor');
  $action = $_POST['action'] ?? '';
  $proj = new Project($conn);

  if ($action === 'add') {
    $title = trim($_POST['title']);
    if ($title === '') {
      flash('error', 'Title is required.');
    } else {
      try {
        $img        = upload_image('image', 'projects') ?: '';
        if (!$img && !empty($_FILES['image']['name'])) {
          flash('error', 'Project added, but the image could not be uploaded (invalid file type or too large).');
        }
        $start_date = trim($_POST['start_date'] ?? '') ?: null;
        $end_date   = trim($_POST['end_date'] ?? '') ?: null;
        $funding_goal = money_or_null($_POST['funding_goal'] ?? '');
        $proj->create(
          $title,
          trim($_POST['description']),
          trim($_POST['impact_stat']),
          trim($_POST['impact_label']),
          trim($_POST['icon_type']) ?: 'heart',
          $_POST['status'] ?? 'active',
          isset($_POST['is_featured']) ? 1 : 0,
          $img,
          clean_url($_POST['instagram_url'] ?? ''),
          clean_url($_POST['tiktok_url'] ?? ''),
          clean_url($_POST['x_url'] ?? ''),
          $start_date,
          $end_date,
          $funding_goal
        );
        log_activity('add_project', "Added project: $title");
        if (!isset($_SESSION['flash'])) flash('success', 'Project added.');
      } catch (mysqli_sql_exception $e) {
        flash('error', 'Could not add project.');
      }
    }
  }

  if ($action === 'edit') {
    $id    = (int)$_POST['id'];
    $title = trim($_POST['title']);
    if ($title === '') {
      flash('error', 'Title is required.');
    } else {
      try {
        $oldImg = $proj->getImagePathById($id);
        if (!empty($_FILES['image']['name'])) {
          // A fresh upload always wins over a same-request "remove" checkbox.
          $img = upload_image('image', 'projects') ?: $oldImg;
          if ($img === $oldImg) {
            flash('error', 'Project updated, but the new image could not be uploaded (invalid file type or too large).');
          }
        } elseif (!empty($_POST['remove_image'])) {
          $img = '';
        } else {
          $img = $oldImg;
        }
        $start_date = trim($_POST['start_date'] ?? '') ?: null;
        $end_date   = trim($_POST['end_date'] ?? '') ?: null;
        $funding_goal = money_or_null($_POST['funding_goal'] ?? '');
        $proj->update(
          $id,
          $title,
          trim($_POST['description']),
          trim($_POST['impact_stat']),
          trim($_POST['impact_label']),
          trim($_POST['icon_type']) ?: 'heart',
          $_POST['status'],
          isset($_POST['is_featured']) ? 1 : 0,
          $img,
          clean_url($_POST['instagram_url'] ?? ''),
          clean_url($_POST['tiktok_url'] ?? ''),
          clean_url($_POST['x_url'] ?? ''),
          $start_date,
          $end_date,
          $funding_goal
        );
        if ($img !== $oldImg && $oldImg) delete_image($oldImg);
        log_activity('edit_project', "Edited project ID $id: $title");
        if (!isset($_SESSION['flash'])) flash('success', 'Project updated.');
      } catch (mysqli_sql_exception $e) {
        flash('error', 'Could not update project.');
      }
    }
  }

  if ($action === 'delete') {
    $id         = (int)$_POST['id'];
    $proj_title = $proj->getTitleById($id);
    $imgPath    = $proj->getImagePathById($id);
    $photos     = (new ProjectPhoto($conn))->getByProject($id);
    $proj->delete($id); // cascades project_photos rows via FK
    if ($imgPath) delete_image($imgPath);
    foreach ($photos as $p) delete_image($p['image_path']);
    log_activity('delete_project', "Deleted project: $proj_title");
    flash('success', 'Project deleted.');
  }

  if ($action === 'bulk_status') {
    $status = $_POST['bulk_status'] ?? '';
    $ids    = array_map('intval', $_POST['ids'] ?? []);
    if (array_key_exists($status, Project::STATUSES) && $ids) {
      $count = 0;
      foreach ($ids as $pid) {
        $p = $proj->findById($pid);
        if (!$p) continue;
        $proj->update(
          $pid,
          $p['title'],
          $p['description'] ?? '',
          $p['impact_stat'] ?? '',
          $p['impact_label'] ?? '',
          $p['icon_type'] ?: 'heart',
          $status,
          (int)$p['is_featured'],
          $p['image_path'] ?? '',
          $p['instagram_url'] ?? '',
          $p['tiktok_url'] ?? '',
          $p['x_url'] ?? '',
          $p['start_date'] ?? null,
          $p['end_date'] ?? null,
          $p['funding_goal'] ?? null
        );
        $count++;
      }
      log_activity('bulk_update_project_status', "Bulk set $count project(s) to $status");
      flash('success', "$count project(s) updated to $status.");
    }
  }

  if ($action === 'bulk_delete') {
    $ids   = array_map('intval', $_POST['ids'] ?? []);
    $count = 0;
    foreach ($ids as $pid) {
      $title = $proj->getTitleById($pid);
      if (!$title) continue;
      $imgPath = $proj->getImagePathById($pid);
      $photos  = (new ProjectPhoto($conn))->getByProject($pid);
      $proj->delete($pid);
      if ($imgPath) delete_image($imgPath);
      foreach ($photos as $p) delete_image($p['image_path']);
      $count++;
    }
    log_activity('bulk_delete_project', "Bulk deleted $count project(s)");
    flash('success', "$count project(s) deleted.");
  }

  header('Location: ' . ADMIN_URL . '/projects.php');
  exit;
}

$projects = (new Project($conn))->getAll();
$icons    = icon_options();
$raised_by_project = (new Pledge($conn))->getConfirmedAmountsByProject();

include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= count($projects) ?> Project<?= count($projects) !== 1 ? 's' : '' ?></span>
    <?php if (has_role('editor')): ?>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-15">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Add Project
      </button>
    <?php endif; ?>
  </div>

  <?php if (has_role('editor')): ?>
    <div class="card-header bulk-bar" id="bulk-bar">
      <span id="bulk-count" class="text-muted fs-13 fw-600"></span>
      <div class="flex-gap8-wrap">
        <?php foreach (Project::STATUSES as $st => $label): ?>
          <button type="button" class="btn btn-sm btn-secondary" onclick="bulkMark('<?= $st ?>')">Mark <?= h($label) ?></button>
        <?php endforeach; ?>
        <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeleteProjects()">Delete Selected</button>
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
    <table id="dt-projects">
      <thead>
        <tr>
          <?php if (has_role('editor')): ?><th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th><?php endif; ?>
          <th>Icon</th>
          <th>Title</th>
          <th>Impact</th>
          <th>Timeline</th>
          <th>Funding</th>
          <th>Status</th>
          <th>Featured</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($projects): foreach ($projects as $p): ?>
            <tr>
              <?php if (has_role('editor')): ?><td><input type="checkbox" class="row-check" value="<?= $p['id'] ?>" onchange="updateBulkBar()"></td><?php endif; ?>
              <td>
                <div class="project-icon"><?= icon_svg($p['icon_type'] ?: 'heart') ?></div>
              </td>
              <td>
                <div class="fw-bold"><?= h($p['title']) ?></div>
                <div class="text-muted text-clamp-300"><?= h($p['description'] ?? '') ?></div>
              </td>
              <td>
                <?php if ($p['impact_stat']): ?>
                  <div class="fw-bold text-primary"><?= h($p['impact_stat']) ?></div>
                  <div class="text-muted fs-12"><?= h($p['impact_label'] ?? '') ?></div>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
              </td>
              <td class="text-muted fs-12">
                <?php if ($p['start_date'] || $p['end_date']): ?>
                  <?= $p['start_date'] ? date('M Y', strtotime($p['start_date'])) : '?' ?> &ndash; <?= $p['end_date'] ? date('M Y', strtotime($p['end_date'])) : 'ongoing' ?>
                  <?php else: ?>—<?php endif; ?>
              </td>
              <td class="fs-12">
                <?php if ($p['funding_goal']): ?>
                  <span class="fw-bold"><?= number_format((float)($raised_by_project[$p['id']] ?? 0), 2) ?></span>
                  <span class="text-muted"> / <?= number_format((float)$p['funding_goal'], 2) ?></span>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
              </td>
              <td><span class="badge badge-<?= h($p['status']) ?>"><?= h($p['status']) ?></span></td>
              <td><?= $p['is_featured'] ? '<span class="badge badge-featured">Featured</span>' : '<span class="text-muted">No</span>' ?></td>
              <td class="text-muted"><?= $p['created_at'] ? date('d M Y', strtotime($p['created_at'])) : '—' ?></td>
              <td>
                <div class="table-actions">
                  <a href="project_details.php?id=<?= (int) $p['id'] ?>" class="btn btn-icon btn-sm btn-secondary" title="View project details" aria-label="View project details"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg></a>
                  <?php if (has_role('editor')): ?>
                    <button class="btn btn-icon btn-sm btn-info" title="Edit" aria-label="Edit" onclick="openEditModal(<?= h(json_encode($p)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 3a2.85 2.86 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                      </svg></button>
                    <button class="btn btn-icon btn-sm btn-secondary" title="Duplicate" aria-label="Duplicate" onclick="duplicateProject(<?= h(json_encode($p)) ?>)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" />
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                      </svg></button>
                    <a href="project_photos.php?project=<?= $p['id'] ?>" class="btn btn-icon btn-sm btn-secondary" title="Manage Photos" aria-label="Manage Photos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" />
                        <circle cx="8.5" cy="8.5" r="1.5" />
                        <polyline points="21 15 16 10 5 21" />
                      </svg></a>
                    <form id="del-p-<?= $p['id'] ?>" method="POST" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    </form>
                    <button class="btn btn-icon btn-sm btn-danger" title="Delete" aria-label="Delete" onclick="confirmDelete('del-p-<?= $p['id'] ?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
  <div class="modal-dialog modal-content modal-xl">
    <div class="modal-header">
      <span class="modal-title">Add New Project</span>
      <button class="modal-close" onclick="closeModal('add-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" required></div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description"></textarea></div>
        <div class="form-group mb-2">
          <label>Cover Image (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="ap_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <p><strong>Upload cover</strong></p>
            </label>
            <input type="file" id="ap_image" name="image" accept="image/*" class="hidden" onchange="previewImage(this,'add-proj-prev')">
            <div class="thumb-col">
              <span class="thumb-col-label">Preview</span>
              <img id="add-proj-prev" src="" alt="" class="image-thumb image-thumb--wide">
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Impact Stat</label><input type="text" name="impact_stat" placeholder="e.g. 1,200+"></div>
          <div class="form-group"><label>Impact Label</label><input type="text" name="impact_label" placeholder="e.g. children reached"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Start Date <span class="text-muted fw-normal">(optional)</span></label><input type="date" name="start_date"></div>
          <div class="form-group"><label>End Date <span class="text-muted fw-normal">(optional, blank = ongoing)</span></label><input type="date" name="end_date"></div>
        </div>
        <div class="form-group mb-2">
          <label>Funding Goal <span class="text-muted fw-normal">(optional)</span></label>
          <input type="text" inputmode="decimal" class="money-input" name="funding_goal" placeholder="Leave blank if not seeking sponsorship">
        </div>
        <div class="form-row">
          <div class="form-group"><label>Icon <span class="text-muted fw-normal">(shown when there's no cover image)</span></label>
            <select name="icon_type">
              <?php foreach ($icons as $k => $label): ?><option value="<?= h($k) ?>"><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <?php foreach (Project::STATUSES as $st => $label): ?><option value="<?= $st ?>"><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Instagram URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="instagram_url" placeholder="https://instagram.com/p/..."></div>
          <div class="form-group"><label>TikTok URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="tiktok_url" placeholder="https://tiktok.com/@.../video/..."></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>X URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="x_url" placeholder="https://x.com/.../status/..."></div>
        </div>
        <div class="form-group form-group-row mt-1">
          <input type="checkbox" name="is_featured" id="a_feat" class="w-auto">
          <label for="a_feat" class="fw-normal">Show on homepage</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Project</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-content modal-xl">
    <div class="modal-header">
      <span class="modal-title">Edit Project</span>
      <button class="modal-close" onclick="closeModal('edit-modal')">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="ep_id">
        <div class="form-group mb-2"><label>Title *</label><input type="text" name="title" id="ep_title" required></div>
        <div class="form-group mb-2"><label>Description</label><textarea name="description" id="ep_description"></textarea></div>
        <div class="form-group mb-2">
          <label>Replace Cover Image (optional)</label>
          <div class="image-field">
            <label class="upload-area" for="ep_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <p><strong>Upload cover</strong></p>
            </label>
            <input type="file" id="ep_image" name="image" accept="image/*" class="hidden" onchange="previewImage(this,'edit-proj-prev')">
            <div id="ep_img_preview"></div>
            <div class="thumb-col">
              <span class="thumb-col-label">New</span>
              <img id="edit-proj-prev" src="" alt="" class="image-thumb image-thumb--wide">
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Impact Stat</label><input type="text" name="impact_stat" id="ep_impact_stat"></div>
          <div class="form-group"><label>Impact Label</label><input type="text" name="impact_label" id="ep_impact_label"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Start Date <span class="text-muted fw-normal">(optional)</span></label><input type="date" name="start_date" id="ep_start_date"></div>
          <div class="form-group"><label>End Date <span class="text-muted fw-normal">(optional, blank = ongoing)</span></label><input type="date" name="end_date" id="ep_end_date"></div>
        </div>
        <div class="form-group mb-2">
          <label>Funding Goal <span class="text-muted fw-normal">(optional)</span></label>
          <input type="text" inputmode="decimal" class="money-input" name="funding_goal" id="ep_funding_goal" placeholder="Leave blank if not seeking sponsorship">
        </div>
        <div class="form-row">
          <div class="form-group"><label>Icon <span class="text-muted fw-normal">(shown when there's no cover image)</span></label>
            <select name="icon_type" id="ep_icon_type">
              <?php foreach ($icons as $k => $label): ?><option value="<?= h($k) ?>"><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="ep_status">
              <?php foreach (Project::STATUSES as $st => $label): ?><option value="<?= $st ?>"><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Instagram URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="instagram_url" id="ep_instagram"></div>
          <div class="form-group"><label>TikTok URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="tiktok_url" id="ep_tiktok"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>X URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="x_url" id="ep_x"></div>
        </div>
        <div class="form-group form-group-row mt-1">
          <input type="checkbox" name="is_featured" id="ep_feat" class="w-auto">
          <label for="ep_feat" class="fw-normal">Show on homepage</label>
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
    $('#dt-projects').DataTable({
      pageLength: 25,
      columnDefs: [{
        orderable: false,
        targets: <?= has_role('editor') ? '[0, 1, 9]' : '[0, 8]' ?>
      }]
    });
  });

  function openEditModal(p) {
    document.getElementById('ep_id').value = p.id;
    document.getElementById('ep_title').value = p.title;
    document.getElementById('ep_description').value = p.description || '';
    document.getElementById('ep_impact_stat').value = p.impact_stat || '';
    document.getElementById('ep_impact_label').value = p.impact_label || '';
    document.getElementById('ep_start_date').value = p.start_date || '';
    document.getElementById('ep_end_date').value = p.end_date || '';
    var epFundingGoal = document.getElementById('ep_funding_goal');
    epFundingGoal.value = p.funding_goal || '';
    epFundingGoal.dispatchEvent(new Event('input'));
    document.getElementById('ep_icon_type').value = p.icon_type || 'heart';
    document.getElementById('ep_status').value = p.status;
    document.getElementById('ep_instagram').value = p.instagram_url || '';
    document.getElementById('ep_tiktok').value = p.tiktok_url || '';
    document.getElementById('ep_x').value = p.x_url || '';
    document.getElementById('ep_feat').checked = p.is_featured == 1;
    const prev = document.getElementById('ep_img_preview');
    prev.innerHTML = p.image_path ?
      '<div class="thumb-col"><span class="thumb-col-label">Current</span><img src="' + esc(p.image_path) + '" class="current-photo-thumb current-photo-thumb--wide">' +
      '<label class="remove-photo-label"><input type="checkbox" name="remove_image" value="1" class="w-auto"> Remove</label></div>' :
      '';
    document.getElementById('edit-proj-prev').style.display = 'none';
    openModal('edit-modal');
  }

  function duplicateProject(p) {
    const form = document.getElementById('add-modal').querySelector('form');
    form.reset();
    form.querySelector('[name="title"]').value = p.title;
    form.querySelector('[name="description"]').value = p.description || '';
    form.querySelector('[name="impact_stat"]').value = p.impact_stat || '';
    form.querySelector('[name="impact_label"]').value = p.impact_label || '';
    form.querySelector('[name="icon_type"]').value = p.icon_type || 'heart';
    form.querySelector('[name="instagram_url"]').value = p.instagram_url || '';
    form.querySelector('[name="tiktok_url"]').value = p.tiktok_url || '';
    form.querySelector('[name="x_url"]').value = p.x_url || '';
    openModal('add-modal');
  }

  function getCheckedProjectIds() {
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
    var ids = getCheckedProjectIds();
    document.getElementById('bulk-bar').style.display = ids.length ? 'flex' : 'none';
    document.getElementById('bulk-count').textContent = ids.length + ' selected';
  }

  function submitBulk(action, status) {
    var ids = getCheckedProjectIds();
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
    var ids = getCheckedProjectIds();
    if (ids.length && confirm('Mark ' + ids.length + ' selected project(s) as ' + status + '?')) submitBulk('bulk_status', status);
  }

  function bulkDeleteProjects() {
    var ids = getCheckedProjectIds();
    if (ids.length && confirm('Permanently delete ' + ids.length + ' selected project(s), including all their photos? This cannot be undone.')) submitBulk('bulk_delete');
  }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
