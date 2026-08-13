<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Member.php';
require_once __DIR__ . '/classes/TeamMember.php';
require_once __DIR__ . '/classes/LeadershipMember.php';
require_once __DIR__ . '/classes/LeadershipTerm.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();
$source = $_GET['source'] ?? '';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$profile = false;

if ($source === 'directory' && $id) {
    $row = (new Member($conn))->findById($id);
    if ($row && $row['status'] === 'approved' && (int) $row['show_in_directory'] === 1) {
        $profile = [
            'name' => trim($row['first_name'] . ' ' . $row['last_name']), 'photo' => $row['photo_path'] ?? '',
            'role' => $row['occupation'] ?? '', 'bio' => $row['bio'] ?? '', 'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '', 'year_of_study' => $row['year_of_study'] ?? '',
            'linkedin_url' => $row['linkedin_url'] ?? '', 'instagram_url' => $row['instagram_url'] ?? '',
            'membership_year' => !empty($row['created_at']) ? date('Y', strtotime($row['created_at'])) : '',
        ];
    }
} elseif ($source === 'team' && $id) {
    $row = (new TeamMember($conn))->findById($id);
    if ($row && (int) $row['is_active'] === 1) {
        $profile = [
            'name' => $row['full_name'], 'photo' => $row['image_path'] ?? '', 'role' => $row['role'] ?? '',
            'bio' => $row['description'] ?? '', 'email' => $row['email'] ?? '', 'term' => $row['term'] ?? '',
            'linkedin_url' => $row['linkedin_url'] ?? '', 'instagram_url' => $row['instagram_url'] ?? '',
        ];
    }
} elseif ($source === 'leadership' && $id) {
    $row = (new LeadershipMember($conn))->findById($id);
    if ($row && (int) $row['is_active'] === 1) {
        $term = (new LeadershipTerm($conn))->findById((int) $row['term_id']);
        $profile = [
            'name' => $row['full_name'], 'photo' => $row['photo_path'] ?? '', 'role' => $row['role'] ?? '',
            'bio' => $row['description'] ?? '', 'email' => $row['email'] ?? '',
            'term' => $term['term_label'] ?? '', 'linkedin_url' => $row['linkedin_url'] ?? '',
            'instagram_url' => $row['instagram_url'] ?? '',
        ];
    }
}

if (!$profile) {
    http_response_code(404);
    $page_title = site_title($conn, 'Member Not Found');
    $page_description = 'The requested member profile could not be found.';
} else {
    $page_title = site_title($conn, $profile['name']);
    $page_description = 'Learn more about ' . $profile['name'] . ' of the Rotaract Club of Kwanza.';
    $words = array_filter(explode(' ', $profile['name']));
    $initials = substr(strtoupper(implode('', array_map(fn($word) => $word[0], $words))), 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
  <style>
    .member-profile { padding: 130px 0 80px; background: linear-gradient(180deg, var(--pink-50), #fff 52%); min-height: 70vh; }
    .profile-shell { max-width: 900px; margin: auto; background: #fff; border: 1px solid var(--border); border-radius: 28px; overflow: hidden; box-shadow: 0 18px 55px rgba(91, 28, 51, .10); }
    .profile-hero { min-height: 260px; padding: 42px; display: grid; grid-template-columns: 190px 1fr; align-items: center; gap: 36px; background: linear-gradient(135deg, var(--pink-100), var(--peach-100, #ffe4c9)); }
    .profile-photo { width: 180px; height: 180px; border-radius: 50%; overflow: hidden; background: var(--pink-700); color: #fff; display: grid; place-items: center; font: 700 4rem 'Cormorant Garamond', serif; box-shadow: 0 12px 30px rgba(128, 30, 75, .23); }
    .profile-photo img { width: 100%; height: 100%; object-fit: cover; }
    .profile-kicker { color: var(--pink-700); text-transform: uppercase; letter-spacing: 1px; font-size: 12px; font-weight: 800; margin: 0 0 8px; }
    .profile-hero h1 { font: 700 clamp(2.2rem, 5vw, 3.3rem) 'Cormorant Garamond', serif; color: var(--text-dark); margin: 0; }
    .profile-role { color: var(--text-mid); font-size: 1.05rem; margin: 8px 0 0; }
    .profile-content { padding: 38px 42px 46px; }
    .profile-content h2 { font: 700 1.7rem 'Cormorant Garamond', serif; margin: 0 0 12px; color: var(--text-dark); }
    .profile-bio { color: var(--text-mid); line-height: 1.75; margin: 0 0 30px; white-space: pre-line; }
    .profile-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .profile-detail { padding: 16px 18px; border-radius: 12px; background: var(--pink-50); }
    .profile-detail dt { color: var(--text-soft); font-size: 11px; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; }
    .profile-detail dd { margin: 5px 0 0; color: var(--text-dark); overflow-wrap: anywhere; }
    .profile-detail a, .profile-social a { color: var(--pink-700); text-decoration: none; font-weight: 600; }
    .profile-social { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 28px; }
    .profile-back { display: inline-block; margin-top: 30px; color: var(--pink-700); font-weight: 700; text-decoration: none; }
    .profile-missing { max-width: 640px; margin: auto; text-align: center; padding: 70px 25px; }
    .profile-shell { max-width: 1120px; }
    .profile-hero { grid-template-columns: 390px 1fr; min-height: 390px; padding: 0; gap: 0; }
    .profile-photo { width: 390px; height: 390px; border-radius: 0; border: 0; cursor: zoom-in; box-shadow: none; }
    .profile-photo:focus-visible { outline: 4px solid var(--pink-700); outline-offset: -4px; }
    .profile-identity { padding: 52px; }
    .profile-dialog { display: none; position: fixed; inset: 0; z-index: 10000; box-sizing: border-box; align-items: center; justify-content: center; padding: 28px; overflow: hidden; background: rgba(10, 5, 8, .92); }
    .profile-dialog.open { display: flex; }
    .profile-dialog figure { position: relative; max-width: 94vw; max-height: 92dvh; margin: 0; display: flex; flex-direction: column; align-items: center; }
    .profile-dialog img { display: block; max-width: 94vw; max-height: calc(88dvh - 62px); object-fit: contain; border-radius: 14px; box-shadow: 0 24px 64px rgba(0, 0, 0, .6); }
    .profile-dialog figcaption { padding: 13px 58px 0; color: #fff; font: 700 1.15rem 'Cormorant Garamond', serif; text-align: center; }
    .profile-dialog button { position: fixed; top: 20px; right: 20px; width: 48px; height: 48px; border: 0; border-radius: 50%; color: #fff; background: rgba(255, 255, 255, .18); font-size: 30px; cursor: pointer; }
    @media (max-width: 760px) { .profile-hero { grid-template-columns: 1fr; min-height: 0; } .profile-photo { width: 100%; height: min(92vw, 440px); } .profile-identity { padding: 34px 24px; text-align: center; } }
    @media (max-width: 640px) { .profile-dialog { padding: 18px; } .profile-dialog figure, .profile-dialog img { max-width: 94vw; } .profile-dialog img { max-height: calc(84dvh - 58px); } .profile-dialog figcaption { font-size: 1rem; padding-top: 10px; } .profile-dialog button { top: 10px; right: 10px; width: 42px; height: 42px; } }
    @media (max-width: 640px) { .member-profile { padding-top: 100px; } .profile-hero { grid-template-columns: 1fr; text-align: center; justify-items: center; padding: 34px 24px; gap: 20px; } .profile-photo { width: 150px; height: 150px; } .profile-content { padding: 30px 24px; } .profile-details { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/includes/navbar.php'; ?>
  <main class="member-profile"><div class="container">
    <?php if (!$profile): ?>
      <div class="profile-missing"><h1>Member profile not found</h1><p>This profile is unavailable or is no longer public.</p><a class="profile-back" href="directory.php">Browse the member directory</a></div>
    <?php else: ?>
      <article class="profile-shell">
        <header class="profile-hero">
          <?php if ($profile['photo']): ?><button class="profile-photo" type="button" id="profile-photo-open" aria-label="View <?= e($profile['name']) ?>'s photo full size"><img src="<?= e(img_url($profile['photo'])) ?>" alt="<?= e($profile['name']) ?>"></button><?php else: ?><div class="profile-photo"><?= e($initials) ?></div><?php endif; ?>
          <div class="profile-identity"><p class="profile-kicker"><?= $source === 'leadership' ? 'Leadership history' : ($source === 'team' ? 'Current team' : 'Member directory') ?></p><h1><?= e($profile['name']) ?></h1><?php if ($profile['role']): ?><p class="profile-role"><?= e($profile['role']) ?></p><?php endif; ?></div>
        </header>
        <div class="profile-content">
          <?php if ($profile['bio']): ?><h2>About <?= e($profile['name']) ?></h2><p class="profile-bio"><?= e($profile['bio']) ?></p><?php endif; ?>
          <?php if ($profile['email'] || $profile['phone'] || $profile['year_of_study'] || $profile['term'] || $profile['membership_year']): ?><dl class="profile-details">
            <?php if ($profile['email']): ?><div class="profile-detail"><dt>Email</dt><dd><a href="mailto:<?= e($profile['email']) ?>"><?= e($profile['email']) ?></a></dd></div><?php endif; ?>
            <?php if ($profile['phone']): ?><div class="profile-detail"><dt>Phone</dt><dd><a href="tel:<?= e($profile['phone']) ?>"><?= e($profile['phone']) ?></a></dd></div><?php endif; ?>
            <?php if ($profile['year_of_study']): ?><div class="profile-detail"><dt>Year of study</dt><dd><?= e($profile['year_of_study']) ?></dd></div><?php endif; ?>
            <?php if ($profile['term']): ?><div class="profile-detail"><dt>Term served</dt><dd><?= e($profile['term']) ?></dd></div><?php endif; ?>
            <?php if ($profile['membership_year']): ?><div class="profile-detail"><dt>Member since</dt><dd><?= e($profile['membership_year']) ?></dd></div><?php endif; ?>
          </dl><?php endif; ?>
          <?php if ($profile['linkedin_url'] || $profile['instagram_url']): ?><div class="profile-social"><?php if ($profile['linkedin_url']): ?><a href="<?= e($profile['linkedin_url']) ?>" target="_blank" rel="noopener">LinkedIn</a><?php endif; ?><?php if ($profile['instagram_url']): ?><a href="<?= e($profile['instagram_url']) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?></div><?php endif; ?>
          <a class="profile-back" href="<?= $source === 'team' ? 'team.php' : ($source === 'leadership' ? 'leadership_history.php' : 'directory.php') ?>">&larr; Back</a>
        </div>
      </article>
      <?php if ($profile['photo']): ?><div class="profile-dialog" id="profile-photo-dialog" role="dialog" aria-modal="true" aria-label="<?= e($profile['name']) ?> photo"><figure><img src="<?= e(img_url($profile['photo'])) ?>" alt="<?= e($profile['name']) ?>"><figcaption><?= e($profile['name']) ?></figcaption><button type="button" id="profile-photo-close" aria-label="Close full-size photo">&times;</button></figure></div><?php endif; ?>
    <?php endif; ?>
  </div></main>
  <?php require_once __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/scripts.js"></script>
  <?php if ($profile && $profile['photo']): ?><script>
    const photoDialog = document.getElementById('profile-photo-dialog');
    const closePhotoDialog = () => photoDialog.classList.remove('open');
    document.getElementById('profile-photo-open').addEventListener('click', () => photoDialog.classList.add('open'));
    document.getElementById('profile-photo-close').addEventListener('click', closePhotoDialog);
    photoDialog.addEventListener('click', (event) => { if (event.target === photoDialog) closePhotoDialog(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closePhotoDialog(); });
  </script><?php endif; ?>
</body>
</html>
