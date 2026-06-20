<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Admin.php';
require_once dirname(__DIR__) . '/classes/SiteSettings.php';

$page_title = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $keys = ['site_name','contact_email','contact_phone','contact_address',
                 'facebook_url','instagram_url','twitter_url','linkedin_url',
                 'about_text','hero_stats_members','hero_stats_projects',
                 'hero_stats_lives','hero_stats_years'];
        $ss = new SiteSettings($conn);
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $ss->set($key, trim($_POST[$key]));
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
           'hero_stats_lives','hero_stats_years'];
$settings = [];
foreach ($s_keys as $k) {
    $settings[$k] = get_setting($k);
}

include __DIR__ . '/includes/header.php';
?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

  <form method="POST">
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
          <label>About Text</label>
          <textarea name="about_text" style="min-height:100px"><?= h($settings['about_text']) ?></textarea>
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

    <button type="submit" class="btn btn-primary">Save All Settings</button>
  </form>

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
        <a href="<?= ADMIN_URL ?>/logout.php" class="btn btn-danger btn-sm mt-1">Logout</a>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
