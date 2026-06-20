<?php
session_start();
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/TeamMember.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();
$team = (new TeamMember($conn))->getActive();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Our Team &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="/Rotaract_Kwanza/assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/Rotaract_Kwanza/assets/css/kwanza.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section id="team" style="padding-top:100px">
  <div class="container">
    <div style="text-align:center;max-width:600px;margin:0 auto 12px">
      <div class="section-eyebrow reveal" style="justify-content:center">Our Leadership</div>
      <h2 class="section-title reveal reveal-delay-1">Meet the <em>Team</em></h2>
      <p class="section-lead reveal reveal-delay-2" style="margin:0 auto">Passionate, driven young leaders who dedicate their time to making a difference in Kwanza.</p>
    </div>

    <?php if ($team): ?>
      <div class="team-grid">
        <?php foreach ($team as $i => $tm):
          $pal      = avatar_palette($i);
          $words    = array_filter(explode(' ', $tm['full_name']));
          $initials = substr(strtoupper(implode('', array_map(fn($w) => $w[0], $words))), 0, 2);
          ?>
          <div class="team-card reveal<?= $i > 0 && $i < 4 ? ' reveal-delay-' . ($i % 4) : '' ?>">
            <div class="team-avatar" style="background:<?= $pal['bg'] ?>">
              <?php if ($tm['image_path']): ?>
                <div class="team-avatar-circle" style="overflow:hidden;padding:0">
                  <img src="<?= e($tm['image_path']) ?>" alt="<?= e($tm['full_name']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">
                </div>
              <?php else: ?>
                <div class="team-avatar-circle" style="background:<?= $pal['circle'] ?>"><?= $initials ?></div>
              <?php endif; ?>
            </div>
            <div class="team-card-body">
              <h4><?= e($tm['full_name']) ?></h4>
              <div class="role"><?= e($tm['role']) ?></div>
              <?php if ($tm['description']): ?><p><?= e($tm['description']) ?></p><?php endif; ?>
              <?php if ($tm['email']): ?>
                <a href="mailto:<?= e($tm['email']) ?>" style="font-size:12px;color:var(--pink-600);text-decoration:none;margin-top:6px;display:inline-block"><?= e($tm['email']) ?></a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align:center;padding:80px 20px;color:var(--text-soft)">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="opacity:.3;margin-bottom:16px"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/></svg>
        <p style="font-size:1.2rem;font-weight:600">Team information coming soon</p>
        <p style="margin-top:8px">Team members will appear here once added through the admin dashboard.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="/Rotaract_Kwanza/assets/js/scripts.js"></script>
</body>
</html>
