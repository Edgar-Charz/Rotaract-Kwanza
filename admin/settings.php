<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Admin.php';
require_once dirname(__DIR__) . '/classes/SiteSettings.php';
require_once dirname(__DIR__) . '/classes/Mailer.php';

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
  'tiktok_url',
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
  'hero_pill_text',
  'mail_from_name',
  'mail_from_email',
  'smtp_host',
  'smtp_port',
  'smtp_username',
  'smtp_password',
  'smtp_encryption',
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
  'donate_gratitude_message',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  if ($action === 'update_admin_email') {
    $target_id = (int)$_POST['target_id'];
    $new_email = trim($_POST['email'] ?? '');
    $is_self   = $target_id === (int)$_SESSION['admin_id'];
    if (!$is_self) {
      require_role('super_admin');
    }
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
      flash('error', 'Please enter a valid email address.');
    } else {
      (new Admin($conn))->updateEmail($target_id, $new_email);
      log_activity('update_admin_email', $is_self ? 'Updated own email address' : "Updated email for admin ID $target_id");
      flash('success', 'Email updated.');
    }
    header('Location: ' . ADMIN_URL . '/settings.php');
    exit;
  }

  if ($action === 'update_admin_username') {
    $target_id    = (int)$_POST['target_id'];
    $new_username = trim($_POST['username'] ?? '');
    $is_self      = $target_id === (int)$_SESSION['admin_id'];
    if (!$is_self) {
      require_role('super_admin');
    }
    if ($new_username === '') {
      flash('error', 'Username is required.');
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $new_username)) {
      flash('error', 'Username may only contain letters, numbers, dots, dashes, and underscores.');
    } else {
      $adm = new Admin($conn);
      if ($adm->updateUsername($target_id, $new_username)) {
        log_activity('update_admin_username', $is_self ? "Changed own username to $new_username" : "Changed username for admin ID $target_id to $new_username");
        if ($is_self) {
          // Keep the active session in sync so the topbar and future activity
          // log entries reflect the new username immediately, without
          // requiring a fresh login.
          $_SESSION['admin_username'] = $new_username;
        }
        flash('success', 'Username updated.');
      } else {
        flash('error', 'That username is already taken.');
      }
    }
    header('Location: ' . ADMIN_URL . '/settings.php');
    exit;
  }

  if ($action === 'update_role') {
    require_role('super_admin');
    $target_id = (int)$_POST['target_id'];
    $new_role  = $_POST['role'] ?? 'viewer';
    if ($target_id === (int)$_SESSION['admin_id']) {
      flash('error', 'You cannot change your own role.');
    } elseif (!in_array($new_role, ['super_admin', 'editor', 'viewer'], true)) {
      flash('error', 'Invalid role.');
    } else {
      $target_admin = (new Admin($conn))->findById($target_id);
      db_exec($conn, 'UPDATE admins SET role=? WHERE id=?', [$new_role, $target_id]);
      log_activity('update_role', "Set admin ID $target_id role to $new_role");
      try {
        if ($target_admin && $target_admin['email']) {
          $mailer = Mailer::fromSettings($conn);
          $site_name = get_setting('site_name', 'Rotaract Kwanza');
          $mailer->send(
            $target_admin['email'],
            $target_admin['full_name'] ?: $target_admin['username'],
            "Your admin role has been changed",
            "<p>Hello,</p><p>Your admin role for <strong>$site_name</strong> has been changed to <strong>" . ucfirst($new_role) . "</strong>.</p><p>If you did not authorize this change, please contact a super admin immediately.</p>"
          );
        }
      } catch (Throwable $e) {
      }
      flash('success', 'Admin role updated.');
    }
    header('Location: ' . ADMIN_URL . '/settings.php');
    exit;
  }

  if ($action === 'reset_admin_password') {
    require_role('super_admin');
    $target_id = (int)$_POST['target_id'];
    $adm       = new Admin($conn);
    if ($target_id === (int)$_SESSION['admin_id']) {
      flash('error', 'Use "Change Password" below to change your own password.');
    } else {
      $target = $adm->findById($target_id);
      if (!$target) {
        flash('error', 'Admin account not found.');
      } else {
        // Random, human-typeable temporary password.
        $temp_password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'), 0, 10);
        $ok = $adm->resetPassword($target_id, $temp_password);
        if (!$ok) {
          flash('error', 'Could not reset that admin\'s password.');
        } else {
          log_activity('reset_admin_password', "Reset password for admin ID $target_id ({$target['username']}); they must set a new one on next login");
          if ($target['email']) {
            $login_url = ADMIN_URL . '/login.php';
            // Absolute-ish URL for the email link — best effort from the current request.
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $login_url_abs = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $login_url;
            $sent = Mailer::fromSettings($conn)->adminPasswordReset(
              $target['email'],
              $target['full_name'] ?: $target['username'],
              $target['username'],
              $temp_password,
              $login_url_abs
            );
            if ($sent) {
              flash('success', "A temporary password was emailed to {$target['email']}. They'll be asked to set a new password the next time they log in.");
            } else {
              // The 4-second auto-dismissing flash toast isn't enough time to
              // copy a generated password, so this goes in a separate,
              // manually-dismissed reveal modal instead (see below).
              flash('error', 'Password reset, but the email could not be sent — see the popup for the temporary password.');
              $_SESSION['reveal_password'] = ['username' => $target['username'], 'password' => $temp_password];
            }
          } else {
            flash('success', "\"{$target['username']}\" has no email on file — see the popup for the temporary password.");
            $_SESSION['reveal_password'] = ['username' => $target['username'], 'password' => $temp_password];
          }
        }
      }
    }
    header('Location: ' . ADMIN_URL . '/settings.php');
    exit;
  }

  if ($action === 'create_admin') {
    require_role('super_admin');
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? 'editor';
    $adm       = new Admin($conn);

    if ($username === '' || $password === '') {
      flash('error', 'Username and password are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash('error', 'Please enter a valid email address.');
    } elseif (strlen($password) < 8) {
      flash('error', 'Password must be at least 8 characters.');
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
      flash('error', 'Username may only contain letters, numbers, dots, dashes, and underscores.');
    } elseif ($adm->usernameExists($username)) {
      flash('error', 'That username is already taken.');
    } else {
      $id = $adm->create($username, $password, $full_name, $role, $email);
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

    // Freeform donate-page field gets a length cap — nothing else on this
    // form is admin-typed prose meant to render publicly at this size, so
    // this guard is scoped to just this one rather than every text key.
    // (Bank/mobile-money account details now live in admin/payment_accounts.php.)
    $donate_limits = ['donate_gratitude_message' => 300];
    foreach ($donate_limits as $dkey => $max) {
      if (isset($_POST[$dkey]) && mb_strlen(trim($_POST[$dkey])) > $max) {
        flash('error', ucfirst(str_replace('_', ' ', $dkey)) . " must be $max characters or fewer.");
        unset($_POST[$dkey]);
      }
    }

    foreach ($keys as $key) {
      if (isset($_POST[$key])) {
        $value = str_ends_with($key, '_url') ? clean_url($_POST[$key]) : trim($_POST[$key]);
        $ss->set($key, $value);
      }
    }

    foreach (['hero_image', 'about_image', 'site_logo'] as $img_key) {
      if (!empty($_FILES[$img_key]['name'])) {
        $img = upload_image($img_key, 'site');
        if ($img) {
          $old = $ss->get($img_key);
          $ss->set($img_key, $img);
          if ($old) delete_image($old);
        } else {
          flash('error', ucfirst(str_replace('_', ' ', $img_key)) . ' could not be uploaded (invalid file type or too large).');
        }
      }
    }

    log_activity('update_settings', 'Updated site settings');
    if (!isset($_SESSION['flash'])) flash('success', 'Settings saved.');

    // "Save & Send Test Email" is the same submit as the main form (just a
    // second named submit button), so the mailer picks up whatever SMTP
    // settings were just saved above in this same request, not stale ones.
    if (!empty($_POST['send_test_email'])) {
      $test_to = trim($_POST['test_email_to'] ?? '');
      if (!filter_var($test_to, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Settings saved, but "Send a Test Email To" needs a valid address.');
      } else {
        try {
          $ok = Mailer::fromSettings($conn)->send(
            $test_to,
            $test_to,
            'Test Email — ' . $ss->get('site_name', 'Rotaract Kwanza'),
            '<p>This is a test email from your Rotaract Kwanza site.</p><p>If you can read this, outbound email is configured correctly.</p>'
          );
          log_activity('send_test_email', ($ok ? 'Sent' : 'Failed to send') . " test email to $test_to");
          flash($ok ? 'success' : 'error', $ok
            ? "Settings saved. Test email sent to $test_to."
            : 'Settings saved, but the test email failed to send — check error_log for details.');
        } catch (Throwable $e) {
          flash('error', 'Settings saved, but the test email failed: ' . $e->getMessage());
        }
      }
    }
  }

  // Removing an image and choosing where the logo displays both take effect
  // immediately (their own action + redirect) instead of waiting on the big
  // "Save Settings" button below — auto-submitting *that* whole form on every
  // click would also save whatever else the admin happens to be mid-editing
  // elsewhere on this page at that moment.
  if ($action === 'remove_setting_image') {
    require_role('editor');
    $img_key = $_POST['key'] ?? '';
    if (!in_array($img_key, ['hero_image', 'about_image', 'site_logo'], true)) {
      flash('error', 'Invalid image field.');
    } else {
      $ss  = new SiteSettings($conn);
      $old = $ss->get($img_key);
      if ($old) {
        delete_image($old);
        $ss->set($img_key, '');
        log_activity('update_settings', 'Removed ' . str_replace('_', ' ', $img_key));
      }
      flash('success', ucfirst(str_replace('_', ' ', $img_key)) . ' removed.');
    }
    header('Location: ' . ADMIN_URL . '/settings.php#branding');
    exit;
  }

  if ($action === 'update_logo_display') {
    require_role('editor');
    $location = $_POST['location'] ?? '';
    $value    = $_POST['value'] ?? '';
    $field    = $location === 'navbar' ? 'navbar_logo_display' : ($location === 'footer' ? 'footer_logo_display' : '');
    if ($field === '' || !in_array($value, ['logo', 'initials'], true)) {
      flash('error', 'Invalid selection.');
    } else {
      (new SiteSettings($conn))->set($field, $value);
      log_activity('update_settings', "Set $field to $value");
      flash('success', ($location === 'navbar' ? 'Topbar' : 'Footer') . ' display updated.');
    }
    header('Location: ' . ADMIN_URL . '/settings.php#branding');
    exit;
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

  // Locking is an automatic, time-limited response to failed logins. It is
  // intentionally separate from suspension, which is an admin decision and
  // remains in effect until a super admin reactivates the account.
  if ($action === 'unlock_admin_account') {
    require_role('super_admin');
    $username = trim($_POST['username'] ?? '');
    $account = $username === '' ? false : db_row($conn, 'SELECT id FROM admins WHERE username = ?', [$username]);
    if (!$account) {
      flash('error', 'Admin account not found.');
    } elseif ((new Admin($conn))->unlockAccount($username)) {
      log_activity('unlock_admin_account', "Unlocked account: $username");
      flash('success', "Account '$username' has been unlocked.");
    } else {
      flash('error', "Account '$username' is not currently locked.");
    }
    header('Location: ' . ADMIN_URL . '/settings.php#security');
    exit;
  }

  if ($action === 'unsuspend_admin_account') {
    require_role('super_admin');
    $username = trim($_POST['username'] ?? '');
    if ($username === '') {
      flash('error', 'Username is required.');
    } else {
      $adm = new Admin($conn);
      $admin_row = db_row($conn, 'SELECT id, email, full_name FROM admins WHERE username = ?', [$username]);
      if ($adm->unsuspendAccount($username)) {
        log_activity('unsuspend_admin_account', "Unsuspended account: $username");
        try {
          if ($admin_row && $admin_row['email']) {
            $mailer = Mailer::fromSettings($conn);
            $site_name = get_setting('site_name', 'Rotaract Kwanza');
            $mailer->send(
              $admin_row['email'],
              $admin_row['full_name'] ?: $username,
              "Your account has been reactivated",
              "<p>Hello,</p><p>Your account for <strong>$site_name</strong> admin panel has been reactivated by an administrator and you can now log in again.</p>"
            );
          }
        } catch (Throwable $e) {
        }
        flash('success', "Account '$username' has been reactivated.");
      } else {
        flash('error', "Account '$username' could not be reactivated.");
      }
    }
    header('Location: ' . ADMIN_URL . '/settings.php#security');
    exit;
  }

  if ($action === 'suspend_admin_account') {
    require_role('super_admin');
    $username = trim($_POST['username'] ?? '');
    if ($username === '') {
      flash('error', 'Username is required.');
    } else {
      $admin_row = db_row($conn, 'SELECT id, email, full_name FROM admins WHERE username = ?', [$username]);
      if (!$admin_row) {
        flash('error', 'Admin account not found.');
      } elseif ($admin_row['id'] === (int)$_SESSION['admin_id']) {
        flash('error', 'You cannot suspend your own account.');
      } else {
        $adm = new Admin($conn);
        if ($adm->suspendAccount($username)) {
          log_activity('suspend_admin_account', "Suspended account: $username");
          try {
            if ($admin_row['email']) {
              $mailer = Mailer::fromSettings($conn);
              $site_name = get_setting('site_name', 'Rotaract Kwanza');
              $mailer->send(
                $admin_row['email'],
                $admin_row['full_name'] ?: $username,
                "Your admin account has been suspended",
                "<p>Hello,</p><p>Your account for <strong>$site_name</strong> admin panel has been suspended by an administrator. You will not be able to log in until it is reactivated.</p><p>If you believe this is in error, please contact a super admin.</p>"
              );
            }
          } catch (Throwable $e) {
          }
          flash('success', "Account '$username' has been suspended.");
        } else {
          flash('error', "Account '$username' could not be suspended.");
        }
      }
    }
    header('Location: ' . ADMIN_URL . '/settings.php#security');
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

// One-time reveal for a just-generated temporary password (set by
// reset_admin_password above) — shown in a manually-dismissed modal instead
// of the auto-dismissing flash toast, which isn't on screen long enough to
// copy a password from.
$reveal_password = $_SESSION['reveal_password'] ?? null;
unset($_SESSION['reveal_password']);

$s_keys = [
  ...TEXT_SETTING_KEYS,
  'hero_image',
  'about_image',
  'site_logo',
  'navbar_logo_display',
  'footer_logo_display',
  'login_max_attempts',
  'login_lockout_minutes',
  'session_idle_minutes',
  'session_absolute_hours',
];
$setting_defaults = [
  'smtp_port'               => '1025',
  'brand_initials'          => 'RK',
  'footer_description'      => 'A vibrant community of young leaders united by the spirit of service, fellowship, and positive change in Kwanza and beyond.',
  'footer_tagline'          => 'Made with ♥ for community & service',
  'contact_hours'           => 'Mon – Fri, 8:00 AM – 5:00 PM',
  'hero_eyebrow'            => 'Rotaract International · Kwanza',
  'hero_title'              => 'Serving Communities, Changing Lives',
  'hero_subtitle'           => 'Together we make a difference',
  'hero_description'        => 'The Rotaract Club of Kwanza is a vibrant community of young leaders committed to fellowship, professional development, and meaningful service to our community and beyond.',
  'hero_pill_text'          => 'Service in Action',
  'navbar_logo_display'     => 'logo',
  'footer_logo_display'     => 'logo',
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
  'donate_gratitude_message' => 'Thank you for even considering a gift — every contribution, big or small, helps us keep serving Kwanza.',
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
  ? db_rows($conn, "SELECT id, username, full_name, email, COALESCE(role,'viewer') AS role, must_change_password, is_suspended FROM admins ORDER BY id")
  : [];
$my_email = db_val($conn, 'SELECT email FROM admins WHERE id=?', [(int) $_SESSION['admin_id']]) ?? '';

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

<div class="card mb-2" id="admin-account">
  <div class="card-header"><span class="card-title">Admin Account</span></div>
  <div class="card-body">
    <p class="text-muted fs-13">Logged in as:</p>
    <p class="fw-bold fs-15 mt-4px"><?= h($_SESSION['admin_username'] ?? '') ?></p>
    <p class="text-muted fs-12 mt-2px">Role: <?= h($_SESSION['admin_role'] ?? 'super_admin') ?></p>
    <form method="POST" class="mt-10px">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="update_admin_username">
      <input type="hidden" name="target_id" value="<?= (int) $_SESSION['admin_id'] ?>">
      <div class="form-group mb-2">
        <label>Username</label>
        <input type="text" name="username" value="<?= h($_SESSION['admin_username'] ?? '') ?>" required pattern="[A-Za-z0-9_.-]+">
        <span class="form-hint">Letters, numbers, dots, dashes, and underscores only</span>
      </div>
      <button type="submit" class="btn btn-secondary btn-sm">Save Username</button>
    </form>
    <form method="POST" class="mt-10px">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="update_admin_email">
      <input type="hidden" name="target_id" value="<?= (int) $_SESSION['admin_id'] ?>">
      <div class="form-group mb-2">
        <label>Email</label>
        <input type="email" name="email" value="<?= h($my_email) ?>" required>
        <span class="form-hint">Where password reset notifications are sent</span>
      </div>
      <button type="submit" class="btn btn-secondary btn-sm">Save Email</button>
    </form>
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
          <label>Email *</label>
          <input type="email" name="email" required autocomplete="off">
          <span class="form-hint">Used to send password reset notifications</span>
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
    <div class="card-body p-0">
      <?php foreach ($all_admins as $adm): ?>
        <div class="admin-role-row">
          <div class="admin-avatar admin-avatar-sm">
            <?= strtoupper(substr($adm['username'], 0, 1)) ?>
          </div>
          <div class="min-w-0-fill">
            <div class="fw-bold admin-role-name">
              <span id="uname-display-<?= $adm['id'] ?>"><?= h($adm['username']) ?></span>
              <?php if ($adm['id'] !== (int)$_SESSION['admin_id']): ?>
                <button type="button" class="link-btn-edit"
                  onclick="document.getElementById('uname-form-<?= $adm['id'] ?>').style.display='flex';this.style.display='none'">edit</button>
              <?php endif; ?>
            </div>
            <?php if ($adm['id'] !== (int)$_SESSION['admin_id']): ?>
              <form method="POST" id="uname-form-<?= $adm['id'] ?>" class="inline-edit-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="update_admin_username">
                <input type="hidden" name="target_id" value="<?= $adm['id'] ?>">
                <input type="text" name="username" value="<?= h($adm['username']) ?>" required pattern="[A-Za-z0-9_.-]+" class="role-inline-input">
                <button type="submit" class="btn btn-sm btn-secondary role-inline-save">Save</button>
              </form>
            <?php endif; ?>
            <?php if ($adm['full_name']): ?><div class="text-muted fs-11"><?= h($adm['full_name']) ?></div><?php endif; ?>
            <?php if ($adm['email']): ?>
              <div class="text-muted fs-11"><?= h($adm['email']) ?></div>
            <?php elseif ($adm['id'] !== (int)$_SESSION['admin_id']): ?>
              <form method="POST" class="inline-form-row">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="update_admin_email">
                <input type="hidden" name="target_id" value="<?= $adm['id'] ?>">
                <input type="email" name="email" required placeholder="Add email address" class="role-inline-input role-inline-input--email">
                <button type="submit" class="btn btn-sm btn-secondary role-inline-save">Save</button>
              </form>
            <?php endif; ?>
            <?php if ($adm['must_change_password']): ?><div class="badge badge-pending fs-10 mt-3px">Password reset pending</div><?php endif; ?>
            <?php if ($adm['is_suspended']): ?><div class="badge badge-pending fs-10 mt-3px">Suspended</div><?php endif; ?>
          </div>
          <?php if ($adm['id'] !== (int)$_SESSION['admin_id']): ?>
            <form method="POST" class="role-select-form" onchange="this.submit()">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="update_role">
              <input type="hidden" name="target_id" value="<?= $adm['id'] ?>">
              <select name="role" class="role-select">
                <option value="super_admin" <?= $adm['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                <option value="editor" <?= $adm['role'] === 'editor'     ? 'selected' : '' ?>>Editor</option>
                <option value="viewer" <?= $adm['role'] === 'viewer'     ? 'selected' : '' ?>>Viewer</option>
              </select>
            </form>
            <?php if (!(int) $adm['is_suspended']): ?>
              <form method="POST" class="flex-shrink-0" id="suspend-form-<?= $adm['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="suspend_admin_account">
                <input type="hidden" name="username" value="<?= h($adm['username']) ?>">
                <button type="button" class="btn btn-sm btn-danger" onclick="showSuspendModal('<?= h(addslashes($adm['username'])) ?>', 'suspend-form-<?= $adm['id'] ?>')">Suspend</button>
              </form>
            <?php endif; ?>
            <form method="POST" class="flex-shrink-0" id="reset-pwd-form-<?= $adm['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="reset_admin_password">
              <input type="hidden" name="target_id" value="<?= $adm['id'] ?>">
              <button type="button" class="btn btn-sm btn-warning" onclick="showResetPwdModal('<?= h(addslashes($adm['username'])) ?>', 'reset-pwd-form-<?= $adm['id'] ?>')">Reset Password</button>
            </form>
          <?php else: ?>
            <span class="badge badge-approved fs-11"><?= h($adm['role']) ?> (you)</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if (has_role('super_admin')): ?>
  <?php
  $locked_accounts = (new Admin($conn))->getLockedAccounts();
  $suspended_accounts = (new Admin($conn))->getSuspendedAccounts();
  ?>
  <?php if ($locked_accounts): ?>
    <div class="card mb-2">
      <div class="card-header"><span class="card-title">Locked Accounts</span></div>
      <div class="card-body p-0">
        <p class="text-muted fs-12-5 p-16px mb-0">
          These accounts were automatically locked after too many failed login attempts. Unlocking clears only the temporary lockout; it does not reactivate a suspended account.
        </p>
        <?php foreach ($locked_accounts as $locked_account): ?>
          <div class="admin-role-row">
            <div class="admin-avatar admin-avatar-sm" style="background: var(--warning);">
              <?= strtoupper(substr($locked_account['username'], 0, 1)) ?>
            </div>
            <div class="min-w-0-fill">
              <div class="fw-bold admin-role-name"><?= h($locked_account['username']) ?></div>
              <?php if ($locked_account['full_name']): ?><div class="text-muted fs-11"><?= h($locked_account['full_name']) ?></div><?php endif; ?>
              <div class="text-muted fs-11">Auto-unlocks in <span class="lockout-timer" data-unlock-time="<?= (int) $locked_account['locked_until'] ?>">calculating…</span></div>
            </div>
            <form method="POST" class="flex-shrink-0" id="unlock-form-<?= (int) $locked_account['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="unlock_admin_account">
              <input type="hidden" name="username" value="<?= h($locked_account['username']) ?>">
              <button type="button" class="btn btn-sm btn-success" onclick="showUnlockModal('<?= h(addslashes($locked_account['username'])) ?>', 'unlock-form-<?= (int) $locked_account['id'] ?>')">Unlock</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($suspended_accounts): ?>
    <div class="card mb-2">
      <div class="card-header"><span class="card-title">Suspended Accounts</span></div>
      <div class="card-body p-0">
        <p class="text-muted fs-12-5 p-16px mb-0">
          These accounts were manually suspended by an administrator and cannot log in until reactivated. Reactivating does not clear an active failed-login lockout.
        </p>
        <?php foreach ($suspended_accounts as $suspended): ?>
          <div class="admin-role-row">
            <div class="admin-avatar admin-avatar-sm" style="background: var(--danger);">
              <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" style="width: 16px; height: 16px;">
                <path d="M12 1C6.48 1 2 5.48 2 11s4.48 10 10 10 10-4.48 10-10S17.52 1 12 1zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 7 15.5 7 14 7.67 14 8.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 7 8.5 7 7 7.67 7 8.5 7.67 10 8.5 10zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z" />
              </svg>
            </div>
            <div class="min-w-0-fill">
              <div class="fw-bold admin-role-name"><?= h($suspended['username']) ?></div>
              <?php if ($suspended['full_name']): ?><div class="text-muted fs-11"><?= h($suspended['full_name']) ?></div><?php endif; ?>
            </div>
            <form method="POST" class="flex-shrink-0" id="unsuspend-form-<?= $suspended['username'] ?>">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="unsuspend_admin_account">
              <input type="hidden" name="username" value="<?= h($suspended['username']) ?>">
              <button type="button" class="btn btn-sm btn-success" onclick="showUnsuspendModal('<?= h(addslashes($suspended['username'])) ?>', 'unsuspend-form-<?= $suspended['username'] ?>')">Reactivate</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
$security_content = ob_get_clean();

include __DIR__ . '/includes/header.php';
?>

<div class="split-layout">

  <?php if (!has_role('editor')): ?>
    <div class="card mb-2">
      <div class="card-body">
        <p class="text-muted fs-13-5">
          Your role (<strong><?= h($_SESSION['admin_role'] ?? 'viewer') ?></strong>) has view-only access to site settings.
          Contact an editor or super admin to make changes.
        </p>
      </div>
    </div>
    <div class="settings-grid">
      <?= $security_content ?>
    </div>
  <?php else: ?>
    <div>
      <div class="settings-tabs" role="tablist">
        <button type="button" class="settings-tab active" data-tab="general" role="tab" aria-selected="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" />
          </svg>
          General
        </button>
        <button type="button" class="settings-tab" data-tab="branding" role="tab" aria-selected="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <path d="M21 15l-5-5L5 21" />
          </svg>
          Branding &amp; Media
        </button>
        <button type="button" class="settings-tab" data-tab="homepage" role="tab" aria-selected="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <line x1="3" y1="9" x2="21" y2="9" />
            <line x1="9" y1="21" x2="9" y2="9" />
          </svg>
          Homepage Content
        </button>
        <button type="button" class="settings-tab" data-tab="about" role="tab" aria-selected="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </svg>
          About Page
        </button>
        <button type="button" class="settings-tab" data-tab="contact" role="tab" aria-selected="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
            <polyline points="22,6 12,13 2,6" />
          </svg>
          Contact &amp; Social
        </button>
        <button type="button" class="settings-tab" data-tab="email" role="tab" aria-selected="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="22" y1="2" x2="11" y2="13" />
            <polygon points="22 2 15 22 11 13 2 9 22 2" />
          </svg>
          Email Delivery
        </button>
        <button type="button" class="settings-tab" data-tab="security" role="tab" aria-selected="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z" />
          </svg>
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
                <label>About Text <span class="text-muted fw-normal">(club history / story — shown on the About page)</span></label>
                <textarea name="about_text" class="mh-100"><?= h($settings['about_text']) ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="settings-panel" data-panel="branding">
          <div class="card mb-2">
            <div class="card-header"><span class="card-title">Branding &amp; Footer</span></div>
            <div class="card-body">
              <div class="form-group mb-2">
                <label>Site Logo</label>
                <p class="text-muted fs-12-5 mb-10px">
                  Uploading a logo doesn't switch it on everywhere automatically — pick where it should appear below. Leave unset (or pick Initials) to keep the badge instead. A square/round image works best.
                </p>
                <div class="image-field">
                  <label class="upload-area" for="site_logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                      <polyline points="17 8 12 3 7 8" />
                      <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                    <p><strong>Click to upload</strong></p>
                    <p class="fs-11 mt-4px">JPG, PNG, GIF, WEBP — max 5 MB</p>
                  </label>
                  <input type="file" id="site_logo" name="site_logo" accept="image/*" class="hidden" onchange="previewImage(this,'logo-img-preview')">
                  <div class="thumb-col">
                    <span class="thumb-col-label">Current</span>
                    <img id="logo-img-preview" src="<?= h($settings['site_logo']) ?>" alt="Logo preview" class="image-thumb image-thumb--avatar <?= $settings['site_logo'] ? 'is-visible' : '' ?>">
                    <?php if ($settings['site_logo']): ?>
                      <button type="submit" form="rm-site_logo-form" class="btn btn-sm btn-danger btn-remove-photo" onclick="return confirm('Remove the current site logo?')">Remove</button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Show in Topbar</label>
                  <select name="value" form="navbar-logo-display-form" onchange="this.form.requestSubmit()">
                    <option value="logo" <?= $settings['navbar_logo_display'] === 'logo' ? 'selected' : '' ?>>Logo image</option>
                    <option value="initials" <?= $settings['navbar_logo_display'] === 'initials' ? 'selected' : '' ?>>Initials badge</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Show in Footer</label>
                  <select name="value" form="footer-logo-display-form" onchange="this.form.requestSubmit()">
                    <option value="logo" <?= $settings['footer_logo_display'] === 'logo' ? 'selected' : '' ?>>Logo image</option>
                    <option value="initials" <?= $settings['footer_logo_display'] === 'initials' ? 'selected' : '' ?>>Initials badge</option>
                  </select>
                </div>
              </div>
              <p class="text-muted hint-tight">These two save instantly when changed. If no logo has been uploaded yet, the initials badge always shows regardless of this choice.</p>
              <div class="form-row">
                <div class="form-group"><label>Logo Initials</label><input type="text" name="brand_initials" value="<?= h($settings['brand_initials']) ?>" maxlength="4" placeholder="RK"></div>
                <div class="form-group"><label>Footer Tagline</label><input type="text" name="footer_tagline" value="<?= h($settings['footer_tagline']) ?>" placeholder="Made with ♥ for community &amp; service"></div>
              </div>
              <div class="form-group"><label>Footer Description</label><textarea name="footer_description" class="mh-72"><?= h($settings['footer_description']) ?></textarea></div>
            </div>
          </div>

          <div class="card mb-2">
            <div class="card-header"><span class="card-title">Homepage Hero Badge</span></div>
            <div class="card-body">
              <p class="text-muted fs-12-5 mb-14px">
                The small badge on the homepage hero image (e.g. "2025 Outstanding Club Award"). Leave the label blank to hide the badge entirely.
              </p>
              <div class="form-row">
                <div class="form-group"><label>Badge Year</label><input type="text" name="hero_badge_year" value="<?= h($settings['hero_badge_year']) ?>" placeholder="2025"></div>
                <div class="form-group"><label>Badge Label</label><input type="text" name="hero_badge_label" value="<?= h($settings['hero_badge_label']) ?>" placeholder="Outstanding Club Award"></div>
              </div>
              <div class="form-group mt-1"><label>Floating Pill Text</label><input type="text" name="hero_pill_text" value="<?= h($settings['hero_pill_text']) ?>" placeholder="Service in Action"></div>
            </div>
          </div>

          <div class="card mb-2">
            <div class="card-header"><span class="card-title">Homepage &amp; About Images</span></div>
            <div class="card-body">
              <p class="text-muted fs-12-5 mb-14px">
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
                      <p class="fs-11 mt-4px">JPG, PNG, GIF, WEBP — max 5 MB</p>
                    </label>
                    <input type="file" id="hero_image" name="hero_image" accept="image/*" class="hidden" onchange="previewImage(this,'hero-img-preview')">
                    <div class="thumb-col">
                      <span class="thumb-col-label">Current</span>
                      <img id="hero-img-preview" src="<?= h($settings['hero_image']) ?>" alt="Hero preview" class="image-thumb image-thumb--hero <?= $settings['hero_image'] ? 'is-visible' : '' ?>">
                      <?php if ($settings['hero_image']): ?>
                        <button type="submit" form="rm-hero_image-form" class="btn btn-sm btn-danger btn-remove-photo" onclick="return confirm('Remove the current hero image?')">Remove</button>
                      <?php endif; ?>
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
                      <p class="fs-11 mt-4px">JPG, PNG, GIF, WEBP — max 5 MB</p>
                    </label>
                    <input type="file" id="about_image" name="about_image" accept="image/*" class="hidden" onchange="previewImage(this,'about-img-preview')">
                    <div class="thumb-col">
                      <span class="thumb-col-label">Current</span>
                      <img id="about-img-preview" src="<?= h($settings['about_image']) ?>" alt="About preview" class="image-thumb image-thumb--hero <?= $settings['about_image'] ? 'is-visible' : '' ?>">
                      <?php if ($settings['about_image']): ?>
                        <button type="submit" form="rm-about_image-form" class="btn btn-sm btn-danger btn-remove-photo" onclick="return confirm('Remove the current about image?')">Remove</button>
                      <?php endif; ?>
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
              <div class="form-group mb-2"><label>Hero Description</label><textarea name="hero_description" class="mh-72"><?= h($settings['hero_description']) ?></textarea></div>
              <div class="form-group mb-2"><label>About Highlight</label><input type="text" name="home_about_highlight" value="<?= h($settings['home_about_highlight']) ?>"></div>
              <div class="form-group mb-2"><label>Homepage About Description</label><textarea name="home_about_description" class="mh-72"><?= h($settings['home_about_description']) ?></textarea></div>
              <div class="form-group mb-2"><label>Homepage Events Description</label><textarea name="home_events_description" class="mh-72"><?= h($settings['home_events_description']) ?></textarea></div>
              <div class="form-group mb-2"><label>Homepage Projects Description</label><textarea name="home_projects_description" class="mh-72"><?= h($settings['home_projects_description']) ?></textarea></div>
              <div class="form-group mb-2"><label>Homepage Team Description</label><textarea name="home_team_description" class="mh-72"><?= h($settings['home_team_description']) ?></textarea></div>
              <div class="form-group mb-2"><label>Homepage Join Description</label><textarea name="home_join_description" class="mh-72"><?= h($settings['home_join_description']) ?></textarea></div>
              <div class="form-group mb-2"><label>Homepage &amp; News Page Description</label><textarea name="home_news_description" class="mh-72"><?= h($settings['home_news_description']) ?></textarea></div>
              <div class="form-group"><label>Homepage &amp; Gallery Page Description</label><textarea name="home_gallery_description" class="mh-72"><?= h($settings['home_gallery_description']) ?></textarea></div>
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
              <p class="text-muted fs-12-5 mb-14px">
                Powers the Mission, Motto, and Meeting Info sections on the public About page.
              </p>
              <div class="form-row">
                <div class="form-group"><label>Founding Year</label><input type="text" name="founding_year" value="<?= h($settings['founding_year']) ?>" placeholder="2012"></div>
                <div class="form-group"><label>Club Motto</label><input type="text" name="motto_text" value="<?= h($settings['motto_text']) ?>" placeholder="Service Above Self"></div>
              </div>
              <div class="form-group mb-2">
                <label>Mission Statement</label>
                <textarea name="mission_text" class="mh-80"><?= h($settings['mission_text']) ?></textarea>
              </div>
              <div class="form-row">
                <div class="form-group"><label>Sponsoring Rotary Club</label><input type="text" name="sponsor_club" value="<?= h($settings['sponsor_club']) ?>" placeholder="Rotary Club of Kwanza"></div>
                <div class="form-group"><label>Sponsoring Club URL <span class="text-muted fw-normal">(optional)</span></label><input type="text" name="sponsor_club_url" value="<?= h($settings['sponsor_club_url']) ?>" placeholder="https://..."></div>
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
              <div class="form-group mt-1"><label>Contact Page Intro</label><textarea name="contact_intro" class="mh-72"><?= h($settings['contact_intro']) ?></textarea></div>
            </div>
          </div>

          <div class="card mb-2">
            <div class="card-header"><span class="card-title">Donate / Support Us</span></div>
            <div class="card-body">
              <div class="form-group"><label>Donate Page Intro</label><textarea name="donate_intro" class="mh-72"><?= h($settings['donate_intro']) ?></textarea></div>
              <div class="form-group mt-1">
                <label>Gratitude Message</label>
                <textarea name="donate_gratitude_message" class="mh-56" maxlength="300" placeholder="Shown above the payment cards, only when at least one payment method is filled in below."><?= h($settings['donate_gratitude_message']) ?></textarea>
                <p class="text-muted fs-11-5 mt-2px">Max 300 characters.</p>
              </div>
              <p class="text-muted fs-12-5">Bank accounts and mobile-money numbers are managed on the <a href="payment_accounts.php">Payment Accounts</a> page — add as many as needed.</p>
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
                <div class="form-group"><label>X (Twitter) URL</label><input type="text" name="twitter_url" value="<?= h($settings['twitter_url']) ?>"></div>
                <div class="form-group"><label>TikTok URL</label><input type="text" name="tiktok_url" value="<?= h($settings['tiktok_url']) ?>"></div>
              </div>
              <div class="form-row">
                <div class="form-group"><label>LinkedIn URL</label><input type="text" name="linkedin_url" value="<?= h($settings['linkedin_url']) ?>"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="settings-panel" data-panel="email">
          <div class="card mb-2">
            <div class="card-header"><span class="card-title">Email (From) Settings</span></div>
            <div class="card-body">
              <p class="text-muted fs-12-5 mb-12px">
                Used as the sender name/address for all outbound notifications (applications, RSVPs, donation thanks, password resets, newsletter posts, and more).
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

          <div class="card mb-2">
            <div class="card-header"><span class="card-title">Outbound Mail (SMTP)</span></div>
            <div class="card-body">
              <p class="text-muted fs-12-5 mb-12px">
                When an SMTP host is set below, every email this site sends goes out through it instead of the server's local <code>mail()</code>/sendmail — the more reliable option, and the only one that works against a local catcher like MailHog. Leave the host blank to fall back to <code>mail()</code>.
              </p>
              <div class="form-row">
                <div class="form-group">
                  <label>SMTP Host</label>
                  <input type="text" name="smtp_host" value="<?= h($settings['smtp_host']) ?>" placeholder="127.0.0.1 (MailHog) or smtp.yourprovider.com">
                </div>
                <div class="form-group">
                  <label>Port</label>
                  <input type="number" name="smtp_port" value="<?= h($settings['smtp_port']) ?>" placeholder="1025" min="1" max="65535">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Encryption</label>
                  <select name="smtp_encryption">
                    <option value="" <?= $settings['smtp_encryption'] === '' ? 'selected' : '' ?>>None (MailHog / local testing)</option>
                    <option value="tls" <?= $settings['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (typically port 587)</option>
                    <option value="ssl" <?= $settings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS (typically port 465)</option>
                  </select>
                </div>
                <div class="form-group"></div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Username <span class="text-muted fw-normal">(optional — leave blank for MailHog)</span></label>
                  <input type="text" name="smtp_username" value="<?= h($settings['smtp_username']) ?>" autocomplete="off">
                </div>
                <div class="form-group">
                  <label>Password <span class="text-muted fw-normal">(optional)</span></label>
                  <input type="password" name="smtp_password" value="<?= h($settings['smtp_password']) ?>" autocomplete="new-password">
                </div>
              </div>
              <div class="form-group mt-1">
                <label>Send a Test Email To</label>
                <div class="test-email-row">
                  <input type="email" name="test_email_to" placeholder="you@example.com" class="flex-fill-220">
                  <button type="submit" name="send_test_email" value="1" class="btn btn-secondary">Save &amp; Send Test Email</button>
                </div>
                <p class="text-muted fs-11-5 mt-6px">Saves everything on this page first, then sends a test using the settings you just entered — so you can confirm it actually works before relying on it.</p>
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">Save All Settings</button>
      </form>

      <!-- Standalone forms for the branding controls that act immediately
         (image remove buttons, logo-display selects) instead of waiting on
         the big form's Save button above. Associated to their trigger
         elements — which stay visually inside the branding card — via the
         HTML5 form="" attribute rather than DOM nesting, since a <form>
         can't nest inside another <form>. -->
      <?php foreach (['site_logo', 'hero_image', 'about_image'] as $img_key): ?>
        <form method="POST" id="rm-<?= $img_key ?>-form" class="hidden">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="remove_setting_image">
          <input type="hidden" name="key" value="<?= $img_key ?>">
        </form>
      <?php endforeach; ?>
      <?php foreach (['navbar', 'footer'] as $loc): ?>
        <form method="POST" id="<?= $loc ?>-logo-display-form" class="hidden">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="update_logo_display">
          <input type="hidden" name="location" value="<?= $loc ?>">
        </form>
      <?php endforeach; ?>

      <div class="settings-panel" data-panel="security">
        <div class="settings-grid">
          <?= $security_content ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php if ($reveal_password): ?>
  <div class="modal fade" id="reveal-password-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-content modal-sm">
      <div class="modal-header">
        <span class="modal-title">Temporary Password</span>
        <button class="modal-close" onclick="closeModal('reveal-password-modal')">&times;</button>
      </div>
      <div class="modal-body">
        <p class="text-muted fs-13">
          For <strong><?= h($reveal_password['username']) ?></strong> — this won't be shown again, so copy it now.
          They'll be asked to set a new password the next time they log in.
        </p>
        <div class="form-group mb-2">
          <label>Temporary Password</label>
          <div class="reveal-password-row">
            <input type="text" id="reveal-password-value" value="<?= h($reveal_password['password']) ?>" readonly
              class="reveal-password-input">
            <button type="button" class="btn btn-secondary btn-sm" id="reveal-password-copy-btn" onclick="copyRevealPassword()">Copy</button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" onclick="closeModal('reveal-password-modal')">Done</button>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      openModal('reveal-password-modal');
    });

    function copyRevealPassword() {
      var input = document.getElementById('reveal-password-value');
      var btn = document.getElementById('reveal-password-copy-btn');
      var done = function() {
        btn.textContent = 'Copied!';
        setTimeout(function() {
          btn.textContent = 'Copy';
        }, 1500);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(done);
      } else {
        input.removeAttribute('readonly');
        input.select();
        document.execCommand('copy');
        input.setAttribute('readonly', 'readonly');
        done();
      }
    }
  </script>
<?php endif; ?>

<!-- Unlock admin account confirmation modal -->
<div class="modal fade" id="unlock-admin-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Unlock Account</span>
      <button class="modal-close" onclick="closeModal('unlock-admin-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <p class="text-muted fs-13-5">
        Are you sure you want to unlock the account <strong id="unlock-modal-username"></strong>? This will immediately allow them to log in again.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('unlock-admin-modal')">Cancel</button>
      <button type="button" class="btn btn-success" onclick="submitUnlockForm()">Unlock</button>
    </div>
  </div>
</div>

<!-- Suspend admin account confirmation modal -->
<div class="modal fade" id="suspend-admin-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Suspend Account</span>
      <button class="modal-close" onclick="closeModal('suspend-admin-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <p class="text-muted fs-13-5">
        Are you sure you want to suspend the account <strong id="suspend-modal-username"></strong>? They will not be able to log in until you reactivate the account.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('suspend-admin-modal')">Cancel</button>
      <button type="button" class="btn btn-danger" onclick="submitSuspendForm()">Suspend</button>
    </div>
  </div>
</div>

<!-- Unsuspend admin account confirmation modal -->
<div class="modal fade" id="unsuspend-admin-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Reactivate Account</span>
      <button class="modal-close" onclick="closeModal('unsuspend-admin-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <p class="text-muted fs-13-5">
        Are you sure you want to reactivate the account <strong id="unsuspend-modal-username"></strong>? They will be able to log in again.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('unsuspend-admin-modal')">Cancel</button>
      <button type="button" class="btn btn-success" onclick="submitUnsuspendForm()">Reactivate</button>
    </div>
  </div>
</div>

<!-- Reset password confirmation modal -->
<div class="modal fade" id="reset-pwd-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-content">
    <div class="modal-header">
      <span class="modal-title">Reset Password</span>
      <button class="modal-close" onclick="closeModal('reset-pwd-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <p class="text-muted fs-13-5">
        Are you sure you want to reset the password for <strong id="reset-pwd-modal-username"></strong>? They will need to set a new password before they can use the dashboard again.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('reset-pwd-modal')">Cancel</button>
      <button type="button" class="btn btn-warning" onclick="submitResetPwdForm()">Reset Password</button>
    </div>
  </div>
</div>

<script>
  var unlockFormId = null;

  function showUnlockModal(username, formId) {
    unlockFormId = formId;
    document.getElementById('unlock-modal-username').textContent = username;
    openModal('unlock-admin-modal');
  }

  function submitUnlockForm() {
    if (unlockFormId) {
      document.getElementById(unlockFormId).submit();
    }
    closeModal('unlock-admin-modal');
  }

  var suspendFormId = null;

  function showSuspendModal(username, formId) {
    suspendFormId = formId;
    document.getElementById('suspend-modal-username').textContent = username;
    openModal('suspend-admin-modal');
  }

  function submitSuspendForm() {
    if (suspendFormId) {
      document.getElementById(suspendFormId).submit();
    }
    closeModal('suspend-admin-modal');
  }

  var unsuspendFormId = null;

  function showUnsuspendModal(username, formId) {
    unsuspendFormId = formId;
    document.getElementById('unsuspend-modal-username').textContent = username;
    openModal('unsuspend-admin-modal');
  }

  function submitUnsuspendForm() {
    if (unsuspendFormId) {
      document.getElementById(unsuspendFormId).submit();
    }
    closeModal('unsuspend-admin-modal');
  }

  var resetPwdFormId = null;

  function showResetPwdModal(username, formId) {
    resetPwdFormId = formId;
    document.getElementById('reset-pwd-modal-username').textContent = username;
    openModal('reset-pwd-modal');
  }

  function submitResetPwdForm() {
    if (resetPwdFormId) {
      document.getElementById(resetPwdFormId).submit();
    }
    closeModal('reset-pwd-modal');
  }

  // Update lockout timers every second
  function updateLockoutTimers() {
    var timers = document.querySelectorAll('.lockout-timer');
    timers.forEach(function(timer) {
      var unlocksAt = parseInt(timer.getAttribute('data-unlock-time')) * 1000; // Convert to milliseconds
      var now = Date.now();
      var remaining = Math.max(0, Math.floor((unlocksAt - now) / 1000));

      if (remaining <= 0) {
        timer.textContent = '0:00 remaining';
      } else {
        var minutes = Math.floor(remaining / 60);
        var seconds = remaining % 60;
        var secsStr = seconds < 10 ? '0' + seconds : seconds;
        timer.textContent = minutes + ':' + secsStr + ' remaining';
      }
    });
  }

  // Update timers immediately and then every second
  document.addEventListener('DOMContentLoaded', function() {
    updateLockoutTimers();
    setInterval(updateLockoutTimers, 1000);
  });
</script>

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
    if (initial && activate(initial)) {
      // hash matched a tab name directly — nothing else to do
    } else if (initial) {
      // hash points at an element inside a tab (e.g. #admin-account) rather
      // than a tab name — find which panel holds it, switch to that tab, then
      // scroll the element into view since it was hidden when the browser's
      // own anchor-scroll ran.
      var target = document.getElementById(initial);
      var panel = target ? target.closest('.settings-panel') : null;
      if (panel && activate(panel.dataset.panel)) {
        target.scrollIntoView({
          block: 'start'
        });
      } else {
        activate(tabs[0].dataset.tab);
      }
    } else {
      activate(tabs[0].dataset.tab);
    }
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>