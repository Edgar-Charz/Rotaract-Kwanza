<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Project.php';
require_once __DIR__ . '/includes/helpers.php';

$db       = new Database();
$conn     = $db->connect();
$projects = (new Project($conn))->getAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Projects &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/kwanza.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section id="projects" style="padding-top:100px">
  <div class="container">
    <div class="projects-header">
      <div>
        <div class="section-eyebrow reveal">Our Impact</div>
        <h2 class="section-title reveal reveal-delay-1">Community <em>Projects</em></h2>
        <p class="section-lead reveal reveal-delay-2" style="color:#fff">All the initiatives and programmes Rotaract Kwanza runs to serve our community.</p>
      </div>
    </div>

    <?php if ($projects): ?>
      <div class="projects-grid">
        <?php foreach ($projects as $i => $pj): ?>
          <a href="project.php?id=<?= $pj['id'] ?>" class="project-card reveal<?= $i > 0 ? ' reveal-delay-' . ($i % 4) : '' ?>" style="display:block;color:inherit;text-decoration:none">
            <?php if ($pj['image_path'] ?? ''): ?>
              <div class="project-icon" style="width:100%;height:140px;border-radius:12px;overflow:hidden;margin-bottom:20px">
                <img src="<?= e(img_url($pj['image_path'])) ?>" alt="<?= e($pj['title']) ?>" style="width:100%;height:100%;object-fit:cover">
              </div>
            <?php else: ?>
              <div class="project-icon"><?= icon_svg($pj['icon_type'] ?: 'heart', 'var(--gold-light)') ?></div>
            <?php endif; ?>
            <?php if ($pj['is_featured']): ?>
              <span style="display:inline-block;background:rgba(212,136,42,0.25);color:var(--gold-light);border:1px solid rgba(212,136,42,0.4);border-radius:20px;font-size:11px;font-weight:700;padding:2px 10px;margin-bottom:8px;letter-spacing:.5px;text-transform:uppercase">Featured</span>
            <?php endif; ?>
            <h3><?= e($pj['title']) ?></h3>
            <?php if ($pj['description']): ?><p><?= e($pj['description']) ?></p><?php endif; ?>
            <div class="project-impact">
              <?php if ($pj['impact_stat']): ?>
                <div class="impact-stat">
                  <div class="impact-num"><?= e($pj['impact_stat']) ?></div>
                  <div class="impact-label"><?= e($pj['impact_label'] ?? '') ?></div>
                </div>
              <?php endif; ?>
              <div class="impact-stat">
                <div class="impact-num" style="font-size:1rem;text-transform:capitalize"><?= e($pj['status']) ?></div>
                <div class="impact-label">Status</div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align:center;padding:80px 20px;color:rgba(255,255,255,0.6)">
        <p style="font-size:1.2rem;font-weight:600">No projects yet</p>
        <p style="margin-top:8px">Projects will appear here once added through the admin dashboard.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/scripts.js"></script>
</body>
</html>
