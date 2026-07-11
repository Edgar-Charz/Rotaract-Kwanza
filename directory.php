<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Member.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$members = (new Member($conn))->getApprovedForDirectory();

$search = strtolower(trim($_GET['q'] ?? ''));
if ($search) {
    $members = array_filter($members, function ($m) use ($search) {
        return str_contains(strtolower($m['first_name'] . ' ' . $m['last_name'] . ' ' . $m['occupation']), $search);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Directory — Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/kwanza.css">
  <style>
    .dir-hero {
      background: linear-gradient(135deg, var(--pink-800), var(--pink-900));
      padding: 60px 0 40px;
      margin-top: 60px;
      color: #fff;
    }
    .dir-hero h1 { font-family: 'Cormorant Garamond', serif; font-size: 2.4rem; font-weight: 700; margin: 8px 0 0; }
    .dir-section { padding: 40px 0 80px; }
    .dir-search-bar {
      display: flex; gap: 10px; max-width: 480px; margin: 0 auto 36px;
    }
    .dir-search-bar input {
      flex: 1; padding: 11px 16px; border: 1.5px solid var(--border); border-radius: 10px;
      font-size: 14px; font-family: inherit; outline: none;
      transition: border-color .2s;
    }
    .dir-search-bar input:focus { border-color: var(--pink-600); }
    .dir-search-bar button {
      padding: 11px 22px; background: var(--pink-700); color: #fff; border: none;
      border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;
      font-family: inherit; transition: opacity .2s;
    }
    .dir-search-bar button:hover { opacity: .88; }

    .dir-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 20px;
    }
    .dir-card {
      background: #fff;
      border-radius: 16px;
      padding: 28px 20px 22px;
      text-align: center;
      box-shadow: 0 2px 14px rgba(0,0,0,.07);
      transition: transform .2s, box-shadow .2s;
    }
    .dir-card:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(192,57,107,.12); }
    .dir-bio { font-size: 12.5px; color: var(--text-muted); line-height: 1.5; margin-top: 8px; }
    .dir-since { font-size: 11px; color: var(--text-muted); opacity: .8; margin-top: 8px; }
    .dir-social-row { display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 8px; }
    .dir-linkedin, .dir-instagram { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--pink-600); text-decoration: none; font-weight: 600; }
    .dir-avatar {
      width: 72px; height: 72px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 26px; font-weight: 800; color: #fff;
      margin: 0 auto 14px;
      flex-shrink: 0;
    }
    .dir-name  { font-weight: 700; font-size: 15px; color: var(--text); line-height: 1.3; }
    .dir-role  { font-size: 12.5px; color: var(--text-muted); margin-top: 4px; }
    .dir-empty { text-align: center; padding: 80px 20px; color: var(--text-muted); }
    .dir-count { text-align: center; color: var(--text-muted); font-size: 13px; margin-bottom: 24px; }

    /* avatar gradient palette */
    .av-0  { background: linear-gradient(135deg,#C0396B,#8b2252); }
    .av-1  { background: linear-gradient(135deg,#D4882A,#a05e10); }
    .av-2  { background: linear-gradient(135deg,#27ae60,#1a6b3b); }
    .av-3  { background: linear-gradient(135deg,#4a7fe8,#2252b8); }
    .av-4  { background: linear-gradient(135deg,#7b5ea7,#4a2e88); }
    .av-5  { background: linear-gradient(135deg,#e07b20,#a45310); }
    .av-6  { background: linear-gradient(135deg,#16a085,#0e6655); }
    .dir-avatar-photo { background: none; overflow: hidden; }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="dir-hero">
  <div class="container">
    <div class="section-eyebrow" style="color:rgba(255,255,255,.7);justify-content:flex-start">Our Members</div>
    <h1>Member <em>Directory</em></h1>
  </div>
</div>

<div class="container dir-section">

  <form method="GET" class="dir-search-bar">
    <input type="text" name="q" value="<?= e($search) ?>"
           placeholder="Search by name or profession…" autofocus>
    <button type="submit">Search</button>
  </form>

  <?php if ($members): ?>
    <p class="dir-count"><?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?> listed</p>
    <div class="dir-grid">
      <?php foreach (array_values($members) as $i => $member): ?>
      <?php
        $initials = strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1));
        $av_class = 'av-' . ($i % 7);
      ?>
      <div class="dir-card">
        <?php if ($member['photo_path']): ?>
          <div class="dir-avatar dir-avatar-photo">
            <img src="<?= e(img_url($member['photo_path'])) ?>" alt="<?= e($initials) ?>"
                 style="width:100%;height:100%;object-fit:cover;border-radius:50%">
          </div>
        <?php else: ?>
          <div class="dir-avatar <?= $av_class ?>"><?= e($initials) ?></div>
        <?php endif; ?>
        <div class="dir-name"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></div>
        <?php if ($member['occupation']): ?>
          <div class="dir-role"><?= e($member['occupation']) ?></div>
        <?php endif; ?>
        <?php if ($member['bio'] ?? ''): ?>
          <p class="dir-bio"><?= e(mb_strimwidth($member['bio'], 0, 90, '…')) ?></p>
        <?php endif; ?>
        <?php if (($member['linkedin_url'] ?? '') || ($member['instagram_url'] ?? '')): ?>
          <div class="dir-social-row">
            <?php if ($member['linkedin_url'] ?? ''): ?>
              <a href="<?= e($member['linkedin_url']) ?>" target="_blank" rel="noopener" class="dir-linkedin">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                LinkedIn
              </a>
            <?php endif; ?>
            <?php if ($member['instagram_url'] ?? ''): ?>
              <a href="<?= e($member['instagram_url']) ?>" target="_blank" rel="noopener" class="dir-instagram">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                Instagram
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($member['created_at'] ?? ''): ?>
          <div class="dir-since">Member since <?= date('Y', strtotime($member['created_at'])) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <div class="dir-empty">
      <?php if ($search): ?>
        <p style="font-size:1.1rem;font-weight:600">No results for "<?= e($search) ?>"</p>
        <a href="directory.php" style="color:var(--pink-700);font-weight:600;margin-top:8px;display:inline-block">Clear search</a>
      <?php else: ?>
        <p style="font-size:1.1rem;font-weight:600">No members listed yet</p>
        <p style="margin-top:6px">Members must be approved and opted in to the directory by an admin.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/scripts.js"></script>
</body>
</html>
