<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Admin.php';
require_once dirname(__DIR__) . '/classes/SiteSettings.php';

$page_title = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_role' && has_role('super_admin')) {
        $target_id = (int)$_POST['target_id'];
        $new_role  = $_POST['role'] ?? 'viewer';
        if (in_array($new_role, ['super_admin','editor','viewer']) && $target_id !== (int)$_SESSION['admin_id']) {
            db_exec($conn, 'UPDATE admins SET role=? WHERE id=?', [$new_role, $target_id]);
            log_activity('update_role', "Set admin ID $target_id role to $new_role");
            flash('success', 'Admin role updated.');
        }
        header('Location: ' . ADMIN_URL . '/settings.php');
        exit;
    }

    if ($action === 'save_settings') {
        require_role('editor');
        $keys = ['site_name','contact_email','contact_phone','contact_address',
                 'facebook_url','instagram_url','twitter_url','linkedin_url',
                 'about_text','hero_stats_members','hero_stats_projects',
                 'hero_stats_lives','hero_stats_years',
                 'founding_year','motto_text','mission_text','sponsor_club','sponsor_club_url',
                 'meeting_day','meeting_time','meeting_location',
                 'hero_badge_year','hero_badge_label',
                 'mail_from_name','mail_from_email'];
        $ss = new SiteSettings($conn);
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $ss->set($key, trim($_POST[$key]));
            }
        }

        foreach (['hero_image', 'about_image'] as $img_key) {
            $img = upload_image($img_key, 'site');
            if ($img) {
                $old = $ss->get($img_key);
                $ss->set($img_key, $img);
                if ($old) delete_image($old);
            }
        }

        log_activity('update_settings', 'Updated site settings');
        flash('success', 'Settings saved.');
    }

    if ($action === 'change_password') {
        $admin_id = (int)$_SESSION['admin_id'];
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $adm      = new Admin($conn);

        if (!$adm->verifyPassword($admin_id, $current)) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'Passwords do not match.');
        } else {
            $adm->updatePassword($admin_id, $new);
            log_activity('change_password', 'Admin password changed');
            flash('success', 'Password changed.');
        }
    }

    header('Location: ' . ADMIN_URL . '/settings.php');
    exit;
}

$s_keys = ['site_name','contact_email','contact_phone','contact_address',
           'facebook_url','instagram_url','twitter_url','linkedin_url',
           'about_text','hero_stats_members','hero_stats_projects',
           'hero_stats_lives','hero_stats_years',
           'founding_year','motto_text','mission_text','sponsor_club','sponsor_club_url',
           'meeting_day','meeting_time','meeting_location',
           'hero_badge_year','hero_badge_label',
           'mail_from_name','mail_from_email',
           'hero_image','about_image'];
$settings = [];
foreach ($s_keys as $k) {
    $settings[$k] = get_setting($k);
}

$all_admins = has_role('super_admin')
    ? db_rows($conn, "SELECT id, username, full_name, COALESCE(role,'super_admin') AS role FROM admins ORDER BY id")
    : [];

include __DIR__ . '/includes/header.php';
?>

<div class="split-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

  <?php if (!has_role('editor')): ?>
  <div class="card">
    <div class="card-body">
      <p class="text-muted" style="font-size:13.5px">
        Your role (<strong><?= h($_SESSION['admin_role'] ?? 'viewer') ?></strong>) has view-only access to site settings.
        Contact an editor or super admin to make changes.
      </p>
    </div>
  </div>
  <?php else: ?>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="save_settings">

    <div class="card mb-2">
      <div class="card-header"><span class="card-title">General Settings</span></div>
      <div class="card-body">
        <div class="form-group mb-2">
          <label>Site / Club Name</label>
          <input type="text" name="site_name" value="<?= h($settings['site_name']) ?>">
        </div>
        <div class="form-group mb-2">
          <label>About Text <span class="text-muted" style="font-weight:400">(club history / story — shown on the About page)</span></label>
          <textarea name="about_text" style="min-height:100px"><?= h($settings['about_text']) ?></textarea>
        </div>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-header"><span class="card-title">About Page Content</span></div>
      <div class="card-body">
        <p class="text-muted" style="font-size:12.5px;margin-bottom:14px">
          Powers the Mission, Motto, and Meeting Info sections on the public About page.
        </p>
        <div class="form-row">
          <div class="form-group"><label>Founding Year</label><input type="text" name="founding_year" value="<?= h($settings['founding_year']) ?>" placeholder="2012"></div>
          <div class="form-group"><label>Club Motto</label><input type="text" name="motto_text" value="<?= h($settings['motto_text']) ?>" placeholder="Service Above Self"></div>
        </div>
        <div class="form-group mb-2">
          <label>Mission Statement</label>
          <textarea name="mission_text" style="min-height:80px"><?= h($settings['mission_text']) ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Sponsoring Rotary Club</label><input type="text" name="sponsor_club" value="<?= h($settings['sponsor_club']) ?>" placeholder="Rotary Club of Kwanza"></div>
          <div class="form-group"><label>Sponsoring Club URL <span class="text-muted" style="font-weight:400">(optional)</span></label><input type="text" name="sponsor_club_url" value="<?= h($settings['sponsor_club_url']) ?>" placeholder="https://..."></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Meeting Day</label><input type="text" name="meeting_day" value="<?= h($settings['meeting_day']) ?>" placeholder="Every Thursday"></div>
          <div class="form-group"><label>Meeting Time</label><input type="text" name="meeting_time" value="<?= h($settings['meeting_time']) ?>" placeholder="6:00 PM"></div>
        </div>
        <div class="form-group">
          <label>Meeting Location</label>
          <input type="text" name="meeting_location" value="<?= h($settings['meeting_location']) ?>" placeholder="Leave blank to use the club address">
        </div>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-header"><span class="card-title">Homepage Hero Badge</span></div>
      <div class="card-body">
        <p class="text-muted" style="font-size:12.5px;margin-bottom:14px">
          The small badge on the homepage hero image (e.g. "2025 Outstanding Club Award"). Leave the label blank to hide the badge entirely.
        </p>
        <div class="form-row">
          <div class="form-group"><label>Badge Year</label><input type="text" name="hero_badge_year" value="<?= h($settings['hero_badge_year']) ?>" placeholder="2025"></div>
          <div class="form-group"><label>Badge Label</label><input type="text" name="hero_badge_label" value="<?= h($settings['hero_badge_label']) ?>" placeholder="Outstanding Club Award"></div>
        </div>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-header"><span class="card-title">Homepage &amp; About Images</span></div>
      <div class="card-body">
        <p class="text-muted" style="font-size:12.5px;margin-bottom:14px">
          Replace the decorative illustrations on the homepage hero and About section with real photos. Leave blank to keep the current illustration.
        </p>
        <div class="form-row">
          <div class="form-group mb-2">
            <label>Hero Image (homepage banner)</label>
            <label class="upload-area" for="hero_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p><strong>Click to upload</strong></p>
              <p style="font-size:11px;margin-top:4px">JPG, PNG, GIF, WEBP — max 5 MB</p>
            </label>
            <input type="file" id="hero_image" name="hero_image" accept="image/*" style="display:none" onchange="previewImage(this,'hero-img-preview')">
            <img id="hero-img-preview" src="<?= h($settings['hero_image']) ?>" alt="Hero preview" style="<?= $settings['hero_image'] ? 'display:block' : '' ?>">
          </div>
          <div class="form-group mb-2">
            <label>About Image</label>
            <label class="upload-area" for="about_image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p><strong>Click to upload</strong></p>
              <p style="font-size:11px;margin-top:4px">JPG, PNG, GIF, WEBP — max 5 MB</p>
            </label>
            <input type="file" id="about_image" name="about_image" accept="image/*" style="display:none" onchange="previewImage(this,'about-img-preview')">
            <img id="about-img-preview" src="<?= h($settings['about_image']) ?>" alt="About preview" style="<?= $settings['about_image'] ? 'display:block' : '' ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-header"><span class="card-title">Contact Information</span></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group"><label>Email</label><input type="email" name="contact_email" value="<?= h($settings['contact_email']) ?>"></div>
          <div class="form-group"><label>Phone</label><input type="tel" name="contact_phone" value="<?= h($settings['contact_phone']) ?>"></div>
        </div>
        <div class="form-group mt-1">
          <label>Address</label>
          <input type="text" name="contact_address" value="<?= h($settings['contact_address']) ?>">
        </div>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-header"><span class="card-title">Social Media Links</span></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group"><label>Facebook URL</label><input type="text" name="facebook_url" value="<?= h($settings['facebook_url']) ?>"></div>
          <div class="form-group"><label>Instagram URL</label><input type="text" name="instagram_url" value="<?= h($settings['instagram_url']) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Twitter / X URL</label><input type="text" name="twitter_url" value="<?= h($settings['twitter_url']) ?>"></div>
          <div class="form-group"><label>LinkedIn URL</label><input type="text" name="linkedin_url" value="<?= h($settings['linkedin_url']) ?>"></div>
        </div>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-header"><span class="card-title">Homepage Statistics</span></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group"><label>Members Stat</label><input type="text" name="hero_stats_members" value="<?= h($settings['hero_stats_members']) ?>"></div>
          <div class="form-group"><label>Projects Stat</label><input type="text" name="hero_stats_projects" value="<?= h($settings['hero_stats_projects']) ?>"></div>
          <div class="form-group"><label>Lives Touched</label><input type="text" name="hero_stats_lives" value="<?= h($settings['hero_stats_lives']) ?>"></div>
          <div class="form-group"><label>Years Active</label><input type="text" name="hero_stats_years" value="<?= h($settings['hero_stats_years']) ?>"></div>
        </div>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-header"><span class="card-title">Email (From) Settings</span></div>
      <div class="card-body">
        <p class="text-muted" style="font-size:12.5px;margin-bottom:12px">
          Used as the sender name/address for all outbound notifications. Make sure your server has <code>mail()</code> configured (XAMPP: Mercury mail, or configure php.ini SMTP).
        </p>
        <div class="form-row">
          <div class="form-group">
            <label>From Name</label>
            <input type="text" name="mail_from_name" value="<?= h($settings['mail_from_name']) ?>" placeholder="Rotaract Kwanza">
          </div>
          <div class="form-group">
            <label>From Email Address</label>
            <input type="email" name="mail_from_email" value="<?= h($settings['mail_from_email']) ?>" placeholder="noreply@rotaractkwanza.org">
          </div>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Save All Settings</button>
  </form>
  <?php endif; ?>

  <div>
    <div class="card">
      <div class="card-header"><span class="card-title">Change Password</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="change_password">
          <div class="form-group mb-2">
            <label>Current Password</label>
            <input type="password" name="current_password" required autocomplete="current-password">
          </div>
          <div class="form-group mb-2">
            <label>New Password</label>
            <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
            <span class="form-hint">At least 8 characters</span>
          </div>
          <div class="form-group mb-2">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required autocomplete="new-password">
          </div>
          <button type="submit" class="btn btn-warning">Change Password</button>
        </form>
      </div>
    </div>

    <div class="card mt-2">
      <div class="card-header"><span class="card-title">Admin Account</span></div>
      <div class="card-body">
        <p class="text-muted" style="font-size:13px">Logged in as:</p>
        <p class="fw-bold" style="font-size:15px;margin-top:4px"><?= h($_SESSION['admin_username'] ?? '') ?></p>
        <p class="text-muted" style="font-size:12px;margin-top:2px">Role: <?= h($_SESSION['admin_role'] ?? 'super_admin') ?></p>
        <a href="logout.php" class="btn btn-danger btn-sm mt-1">Logout</a>
      </div>
    </div>

    <?php if ($all_admins): ?>
    <div class="card mt-2">
      <div class="card-header"><span class="card-title">Admin Roles</span></div>
      <div class="card-body" style="padding:0">
        <?php foreach ($all_admins as $adm): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border)">
          <div class="admin-avatar" style="width:32px;height:32px;font-size:12px;flex-shrink:0">
            <?= strtoupper(substr($adm['username'], 0, 1)) ?>
          </div>
          <div style="flex:1;min-width:0">
            <div class="fw-bold" style="font-size:13px"><?= h($adm['username']) ?></div>
            <?php if ($adm['full_name']): ?><div class="text-muted" style="font-size:11px"><?= h($adm['full_name']) ?></div><?php endif; ?>
          </div>
          <?php if ($adm['id'] !== (int)$_SESSION['admin_id']): ?>
          <form method="POST" style="display:flex;gap:6px;align-items:center;flex-shrink:0">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="target_id" value="<?= $adm['id'] ?>">
            <select name="role" style="padding:4px 8px;border:1.5px solid var(--border);border-radius:6px;font-size:12px">
              <option value="super_admin" <?= $adm['role']==='super_admin'?'selected':'' ?>>Super Admin</option>
              <option value="editor"      <?= $adm['role']==='editor'     ?'selected':'' ?>>Editor</option>
              <option value="viewer"      <?= $adm['role']==='viewer'     ?'selected':'' ?>>Viewer</option>
            </select>
            <button type="submit" class="btn btn-sm btn-secondary">Set</button>
          </form>
          <?php else: ?>
          <span class="badge badge-approved" style="font-size:11px"><?= h($adm['role']) ?> (you)</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
