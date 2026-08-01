<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Admin.php';
require_once dirname(__DIR__) . '/classes/SiteSettings.php';

$page_title = 'Settings';

// Single source of truth for the plain-text site_settings keys — used by both
// the save handler and the display/prefill loop below so a key can't drift
// out of sync between the two (image and security-threshold keys are handled
// separately since they go through different POST actions/validation).
const TEXT_SETTING_KEYS = [
  'site_name',
  'contact_email',
  'contact_phone',
  'contact_address',
  'facebook_url',
  'instagram_url',
  'twitter_url',
  'linkedin_url',
  'about_text',
  'hero_stats_members',
  'hero_stats_projects',
  'hero_stats_lives',
  'hero_stats_years',
  'founding_year',
  'motto_text',
  'mission_text',
  'sponsor_club',
  'sponsor_club_url',
  'meeting_day',
  'meeting_time',
  'meeting_location',
  'hero_badge_year',
  'hero_badge_label',
  'mail_from_name',
  'mail_from_email',
  'brand_initials',
  'footer_description',
  'footer_tagline',
  'contact_hours',
  'hero_eyebrow',
  'hero_title',
  'hero_subtitle',
  'hero_description',
  'home_about_highlight',
  'home_about_description',
  'home_events_description',
  'home_projects_description',
  'home_team_description',
  'home_join_description',
  'home_news_description',
  'home_gallery_description',
  'contact_intro',
  'donate_intro',
  'donate_bank_details',
  'donate_mobile_money',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  if ($action === 'update_role') {
    require_role('super_admin');
    $target_id = (int)$_POST['target_id'];
    $new_role  = $_POST['role'] ?? 'viewer';
    if ($target_id === (int)$_SESSION['admin_id']) {
      flash('error', 'You cannot change your own role.');
    } elseif (!in_array($new_role, ['super_admin', 'editor', 'viewer'], true)) {
      flash('error', 'Invalid role.');
    } else {
      db_exec($conn, 'UPDATE admins SET role=? WHERE id=?', [$new_role, $target_id]);
      log_activity('update_role', "Set admin ID $target_id role to $new_role");
      flash('success', 'Admin role updated.');
    }
    header('Location: ' . ADMIN_URL . '/settings.php');
    exit;
  }

  if ($action === 'create_admin') {
    require_role('super_admin');
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? 'editor';
    $adm       = new Admin($conn);

    if ($username === '' || $password === '') {
      flash('error', 'Username and password are required.');
    } elseif (strlen($password) < 8) {
      flash('error', 'Password must be at least 8 characters.');
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
      flash('error', 'Username may only contain letters, numbers, dots, dashes, and underscores.');
    } elseif ($adm->usernameExists($username)) {
      flash('error', 'That username is already taken.');
    } else {
      $id = $adm->create($username, $password, $full_name, $role);
      if ($id) {
        log_activity('create_admin', "Created admin account: $username ($role)");
        flash('success', 'Admin account created.');
      } else {
        flash('error', 'Could not create admin account.');
      }
    }
    header('Location: ' . ADMIN_URL . '/settings.php');
    exit;
  }

  if ($action === 'save_settings') {
    require_role('editor');
    $keys = TEXT_SETTING_KEYS;
    $ss = new SiteSettings($conn);
    foreach ($keys as $key) {
      if (isset($_POST[$key])) {
        $value = str_ends_with($key, '_url') ? clean_url($_POST[$key]) : trim($_POST[$key]);
        $ss->set($key, $value);
      }
    }

    foreach (['hero_image', 'about_image'] as $img_key) {
      if (empty($_FILES[$img_key]['name'])) continue;
      $img = upload_image($img_key, 'site');
      if ($img) {
        $old = $ss->get($img_key);
        $ss->set($img_key, $img);
        if ($old) delete_image($old);
      } else {
        flash('error', ucfirst(str_replace('_', ' ', $img_key)) . ' could not be uploaded (invalid file type or too large).');
      }
    }

    log_activity('update_settings', 'Updated site settings');
    if (!isset($_SESSION['flash'])) flash('success', 'Settings saved.');
  }

  if ($action === 'save_security_settings') {
    require_role('super_admin');
    $ss = new SiteSettings($conn);

    // Bounded so an admin can't lock everyone out (e.g. a 0-minute idle
    // timeout) or defeat the point of the setting (e.g. a 0-attempt lockout).
    $max_attempts   = max(3, min(20, (int) ($_POST['login_max_attempts'] ?? 5)));
    $lockout_mins   = max(1, min(120, (int) ($_POST['login_lockout_minutes'] ?? 15)));
    $idle_mins      = max(5, min(240, (int) ($_POST['session_idle_minutes'] ?? 30)));
    $absolute_hours = max(1, min(72, (int) ($_POST['session_absolute_hours'] ?? 12)));

    $ss->set('login_max_attempts', (string) $max_attempts);
    $ss->set('login_lockout_minutes', (string) $lockout_mins);
    $ss->set('session_idle_minutes', (string) $idle_mins);
    $ss->set('session_absolute_hours', (string) $absolute_hours);

    log_activity('update_security_settings', "Updated login/session security (max attempts=$max_attempts, lockout={$lockout_mins}m, idle={$idle_mins}m, absolute={$absolute_hours}h)");
    flash('success', 'Login & session security settings saved.');
    header('Location: ' . ADMIN_URL . '/settings.php');
    exit;
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

$s_keys = [
  ...TEXT_SETTING_KEYS,
  'hero_image',
  'about_image',
  'login_max_attempts',
  'login_lockout_minutes',
  'session_idle_minutes',
  'session_absolute_hours',
];
$setting_defaults = [
  'brand_initials'          => 'RK',
  'footer_description'      => 'A vibrant community of young leaders united by the spirit of service, fellowship, and positive change in Kwanza and beyond.',
  'footer_tagline'          => 'Made with ♥ for community & service',
  'contact_hours'           => 'Mon – Fri, 8:00 AM – 5:00 PM',
  'hero_eyebrow'            => 'Rotaract International · Kwanza',
  'hero_title'              => 'Serving Communities, Changing Lives',
  'hero_subtitle'           => 'Together we make a difference',
  'hero_description'        => 'The Rotaract Club of Kwanza is a vibrant community of young leaders committed to fellowship, professional development, and meaningful service to our community and beyond.',
  'home_about_highlight'    => 'Over a decade of community service and fellowship in Kwanza',
  'home_about_description'  => 'The Rotaract Club of Kwanza is a Rotary International-sponsored organization bringing together young professionals and leaders aged 18–30 to create lasting change in our community.',
  'home_events_description' => 'Discover our next service days, leadership forums, and fellowship celebrations. Join Rotaract Kwanza for meaningful impact.',
  'home_projects_description' => 'Discover our projects',
  'home_team_description'   => 'Passionate, driven young leaders who dedicate their time to making a difference in Kwanza.',
  'home_join_description'   => 'Join a community of passionate young leaders making real change in Kwanza. Membership is open to all aged 18–30.',
  'home_news_description'   => 'Club updates, meeting minutes, and announcements from Rotaract Club of Kwanza.',
  'home_gallery_description' => 'A glimpse into our community service, events, and fellowship moments.',
  'contact_intro'           => 'Whether you have a question, partnership opportunity, or just want to say hello — our doors are always open.',
  'donate_intro'            => 'Every contribution helps us fund community service projects, from clean water initiatives to educational scholarships. Your generosity directly changes lives in Kwanza.',
  'donate_bank_details'     => '',
  'donate_mobile_money'     => '',
  'login_max_attempts'      => '5',
  'login_lockout_minutes'   => '15',
  'session_idle_minutes'    => '30',
  'session_absolute_hours'  => '12',
];
$settings = [];
foreach ($s_keys as $k) {
  $settings[$k] = get_setting($k, $setting_defaults[$k] ?? '');
}

$all_admins = has_role('super_admin')
  ? db_rows($conn, "SELECT id, username, full_name, COALESCE(role,'viewer') AS role FROM admins ORDER BY id")
  : [];

// Rendered once, then reused both for the "Security" tab (editors+) and the
// read-only view (viewers, who don't get the tabbed settings UI at all but
// can still manage their own account).
ob_start();
?>
  <div class="card mb-2">
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

  <div class="card mb-2">
    <div class="card-header"><span class="card-title">Admin Account</span></div>
    <div class="card-body">
      <p class="text-muted" style="font-size:13px">Logged in as:</p>
      <p class="fw-bold" style="font-size:15px;margin-top:4px"><?= h($_SESSION['admin_username'] ?? '') ?></p>
      <p class="text-muted" style="font-size:12px;margin-top:2px">Role: <?= h($_SESSION['admin_role'] ?? 'super_admin') ?></p>
      <a href="logout.php" class="btn btn-danger btn-sm mt-1">Logout</a>
    </div>
  </div>

  <?php if (has_role('super_admin')): ?>
    <div class="card mb-2">
      <div class="card-header"><span class="card-title">Login &amp; Session Security</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="save_security_settings">
          <div class="form-group mb-2">
            <label>Max failed login attempts</label>
            <input type="number" name="login_max_attempts" value="<?= (int) $settings['login_max_attempts'] ?>" min="3" max="20" required>
            <span class="form-hint">Account locks out after this many failed attempts (3–20).</span>
          </div>
          <div class="form-group mb-2">
            <label>Lockout duration (minutes)</label>
            <input type="number" name="login_lockout_minutes" value="<?= (int) $settings['login_lockout_minutes'] ?>" min="1" max="120" required>
            <span class="form-hint">How long a locked-out account must wait (1–120).</span>
          </div>
          <div class="form-group mb-2">
            <label>Idle session timeout (minutes)</label>
            <input type="number" name="session_idle_minutes" value="<?= (int) $settings['session_idle_minutes'] ?>" min="5" max="240" required>
            <span class="form-hint">Auto-logout after this much inactivity (5–240).</span>
          </div>
          <div class="form-group mb-2">
            <label>Absolute session timeout (hours)</label>
            <input type="number" name="session_absolute_hours" value="<?= (int) $settings['session_absolute_hours'] ?>" min="1" max="72" required>
            <span class="form-hint">Force re-login after this long, regardless of activity (1–72).</span>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Save Security Settings</button>
        </form>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-header"><span class="card-title">Create Admin</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="create_admin">
          <div class="form-group mb-2">
            <label>Username *</label>
            <input type="text" name="username" required pattern="[A-Za-z0-9_.-]+" autocomplete="off">
          </div>
          <div class="form-group mb-2">
            <label>Full Name</label>
            <input type="text" name="full_name" autocomplete="off">
          </div>
          <div class="form-group mb-2">
            <label>Password *</label>
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
            <span class="form-hint">At least 8 characters</span>
          </div>
          <div class="form-group mb-2">
            <label>Role</label>
            <select name="role">
              <option value="editor">Editor</option>
              <option value="viewer">Viewer</option>
              <option value="super_admin">Super Admin</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Create Account</button>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($all_admins): ?>
    <div class="card mb-2">
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
                  <option value="super_admin" <?= $adm['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                  <option value="editor" <?= $adm['role'] === 'editor'     ? 'selected' : '' ?>>Editor</option>
                  <option value="viewer" <?= $adm['role'] === 'viewer'     ? 'selected' : '' ?>>Viewer</option>
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
<?php
$security_content = ob_get_clean();

include __DIR__ . '/includes/header.php';
?>

<div class="split-layout">

  <?php if (!has_role('editor')): ?>
    <div class="card mb-2">
      <div class="card-body">
        <p class="text-muted" style="font-size:13.5px">
          Your role (<strong><?= h($_SESSION['admin_role'] ?? 'viewer') ?></strong>) has view-only access to site settings.
          Contact an editor or super admin to make changes.
        </p>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;align-items:start">
      <?= $security_content ?>
    </div>
  <?php else: ?>
  <div>
    <div class="settings-tabs" role="tablist">
      <button type="button" class="settings-tab active" data-tab="general" role="tab" aria-selected="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        General
      </button>
      <button type="button" class="settings-tab" data-tab="branding" role="tab" aria-selected="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        Branding &amp; Media
      </button>
      <button type="button" class="settings-tab" data-tab="homepage" role="tab" aria-selected="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
        Homepage Content
      </button>
      <button type="button" class="settings-tab" data-tab="about" role="tab" aria-selected="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        About Page
      </button>
      <button type="button" class="settings-tab" data-tab="contact" role="tab" aria-selected="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        Contact &amp; Social
      </button>
      <button type="button" class="settings-tab" data-tab="email" role="tab" aria-selected="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Email Delivery
      </button>
      <button type="button" class="settings-tab" data-tab="security" role="tab" aria-selected="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/></svg>
        Security &amp; Admins
      </button>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_settings">

      <div class="settings-panel active" data-panel="general">
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
      </div>

      <div class="settings-panel" data-panel="branding">
        <div class="card mb-2">
          <div class="card-header"><span class="card-title">Branding &amp; Footer</span></div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group"><label>Logo Initials</label><input type="text" name="brand_initials" value="<?= h($settings['brand_initials']) ?>" maxlength="4" placeholder="RK"></div>
              <div class="form-group"><label>Footer Tagline</label><input type="text" name="footer_tagline" value="<?= h($settings['footer_tagline']) ?>" placeholder="Made with ♥ for community &amp; service"></div>
            </div>
            <div class="form-group"><label>Footer Description</label><textarea name="footer_description" style="min-height:72px"><?= h($settings['footer_description']) ?></textarea></div>
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
                <div class="image-field">
                  <label class="upload-area" for="hero_image">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                      <polyline points="17 8 12 3 7 8" />
                      <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                    <p><strong>Click to upload</strong></p>
                    <p style="font-size:11px;margin-top:4px">JPG, PNG, GIF, WEBP — max 5 MB</p>
                  </label>
                  <input type="file" id="hero_image" name="hero_image" accept="image/*" style="display:none" onchange="previewImage(this,'hero-img-preview')">
                  <div class="thumb-col">
                    <span class="thumb-col-label">Current</span>
                    <img id="hero-img-preview" src="<?= h($settings['hero_image']) ?>" alt="Hero preview" class="image-thumb image-thumb--hero" style="<?= $settings['hero_image'] ? 'display:block' : '' ?>">
                  </div>
                </div>
              </div>
              <div class="form-group mb-2">
                <label>About Image</label>
                <div class="image-field">
                  <label class="upload-area" for="about_image">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                      <polyline points="17 8 12 3 7 8" />
                      <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                    <p><strong>Click to upload</strong></p>
                    <p style="font-size:11px;margin-top:4px">JPG, PNG, GIF, WEBP — max 5 MB</p>
                  </label>
                  <input type="file" id="about_image" name="about_image" accept="image/*" style="display:none" onchange="previewImage(this,'about-img-preview')">
                  <div class="thumb-col">
                    <span class="thumb-col-label">Current</span>
                    <img id="about-img-preview" src="<?= h($settings['about_image']) ?>" alt="About preview" class="image-thumb image-thumb--hero" style="<?= $settings['about_image'] ? 'display:block' : '' ?>">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="settings-panel" data-panel="homepage">
        <div class="card mb-2">
          <div class="card-header"><span class="card-title">Homepage Hero &amp; Section Copy</span></div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group"><label>Hero Eyebrow</label><input type="text" name="hero_eyebrow" value="<?= h($settings['hero_eyebrow']) ?>"></div>
              <div class="form-group"><label>Hero Title</label><input type="text" name="hero_title" value="<?= h($settings['hero_title']) ?>"></div>
            </div>
            <div class="form-group mb-2"><label>Hero Subtitle</label><input type="text" name="hero_subtitle" value="<?= h($settings['hero_subtitle']) ?>"></div>
            <div class="form-group mb-2"><label>Hero Description</label><textarea name="hero_description" style="min-height:72px"><?= h($settings['hero_description']) ?></textarea></div>
            <div class="form-group mb-2"><label>About Highlight</label><input type="text" name="home_about_highlight" value="<?= h($settings['home_about_highlight']) ?>"></div>
            <div class="form-group mb-2"><label>Homepage About Description</label><textarea name="home_about_description" style="min-height:72px"><?= h($settings['home_about_description']) ?></textarea></div>
            <div class="form-group mb-2"><label>Homepage Events Description</label><textarea name="home_events_description" style="min-height:72px"><?= h($settings['home_events_description']) ?></textarea></div>
            <div class="form-group mb-2"><label>Homepage Projects Description</label><textarea name="home_projects_description" style="min-height:72px"><?= h($settings['home_projects_description']) ?></textarea></div>
            <div class="form-group mb-2"><label>Homepage Team Description</label><textarea name="home_team_description" style="min-height:72px"><?= h($settings['home_team_description']) ?></textarea></div>
            <div class="form-group mb-2"><label>Homepage Join Description</label><textarea name="home_join_description" style="min-height:72px"><?= h($settings['home_join_description']) ?></textarea></div>
            <div class="form-group mb-2"><label>Homepage &amp; News Page Description</label><textarea name="home_news_description" style="min-height:72px"><?= h($settings['home_news_description']) ?></textarea></div>
            <div class="form-group"><label>Homepage &amp; Gallery Page Description</label><textarea name="home_gallery_description" style="min-height:72px"><?= h($settings['home_gallery_description']) ?></textarea></div>
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
      </div>

      <div class="settings-panel" data-panel="about">
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
      </div>

      <div class="settings-panel" data-panel="contact">
        <div class="card mb-2">
          <div class="card-header"><span class="card-title">Contact Information</span></div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group"><label>Email</label><input type="email" name="contact_email" value="<?= h($settings['contact_email']) ?>"></div>
              <div class="form-group"><label>Phone</label><input type="tel" name="contact_phone" value="<?= h($settings['contact_phone']) ?>"></div>
            </div>
            <div class="form-row">
              <div class="form-group mt-1"><label>Address</label><input type="text" name="contact_address" value="<?= h($settings['contact_address']) ?>"></div>
              <div class="form-group mt-1"><label>Contact Hours</label><input type="text" name="contact_hours" value="<?= h($settings['contact_hours']) ?>" placeholder="Mon – Fri, 8:00 AM – 5:00 PM"></div>
            </div>
            <div class="form-group mt-1"><label>Contact Page Intro</label><textarea name="contact_intro" style="min-height:72px"><?= h($settings['contact_intro']) ?></textarea></div>
          </div>
        </div>

        <div class="card mb-2">
          <div class="card-header"><span class="card-title">Donate / Support Us</span></div>
          <div class="card-body">
            <div class="form-group"><label>Donate Page Intro</label><textarea name="donate_intro" style="min-height:72px"><?= h($settings['donate_intro']) ?></textarea></div>
            <div class="form-row">
              <div class="form-group mt-1"><label>Bank Transfer Details</label><textarea name="donate_bank_details" style="min-height:72px" placeholder="Account name, number, bank, branch..."><?= h($settings['donate_bank_details']) ?></textarea></div>
              <div class="form-group mt-1"><label>Mobile Money Details</label><textarea name="donate_mobile_money" style="min-height:72px" placeholder="Provider, number, account name..."><?= h($settings['donate_mobile_money']) ?></textarea></div>
            </div>
            <p class="text-muted" style="font-size:12.5px;margin-top:4px">Leave either blank to hide that payment method on the public Donate page.</p>
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
      </div>

      <div class="settings-panel" data-panel="email">
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
      </div>

      <button type="submit" class="btn btn-primary">Save All Settings</button>
    </form>

    <div class="settings-panel" data-panel="security">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;align-items:start">
        <?= $security_content ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
  (function() {
    var tabs = document.querySelectorAll('.settings-tab');
    if (!tabs.length) return;

    function activate(name) {
      var found = false;
      tabs.forEach(function(btn) {
        var match = btn.dataset.tab === name;
        btn.classList.toggle('active', match);
        btn.setAttribute('aria-selected', match ? 'true' : 'false');
        if (match) found = true;
      });
      document.querySelectorAll('.settings-panel').forEach(function(panel) {
        panel.classList.toggle('active', panel.dataset.panel === name);
      });
      return found;
    }

    tabs.forEach(function(btn) {
      btn.addEventListener('click', function() {
        activate(btn.dataset.tab);
        history.replaceState(null, '', '#' + btn.dataset.tab);
      });
    });

    var initial = window.location.hash.replace('#', '');
    if (!initial || !activate(initial)) activate(tabs[0].dataset.tab);
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
