<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Project.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$project_obj = new Project($conn);
$search = trim($_GET['q'] ?? '');
$limit  = 12;
$page   = max(1, (int) ($_GET['page'] ?? 1));
$total  = $project_obj->count($search);
$pages  = max(1, (int) ceil($total / $limit));
$page   = min($page, $pages);
$offset = ($page - 1) * $limit;
$projects = $project_obj->getPage($limit, $offset, $search);

$page_title = site_title($conn, 'Projects');
$page_description = 'All the community service and impact initiatives run by the Rotaract Club of Kwanza.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
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

      <form method="GET" style="display:flex;gap:10px;max-width:420px;margin:0 0 32px">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search projects…"
          style="flex:1;padding:11px 16px;border:1.5px solid rgba(255,255,255,.25);border-radius:10px;font-size:14px;font-family:inherit;outline:none;background:rgba(255,255,255,.06);color:#fff">
        <button type="submit" style="padding:11px 22px;background:var(--gold, #D4882A);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit">Search</button>
      </form>

      <?php if ($search && $projects): ?>
        <p style="color:rgba(255,255,255,.65);font-size:13px;margin:-20px 0 24px"><?= $total ?> result<?= $total !== 1 ? 's' : '' ?> for "<?= e($search) ?>" &middot; <a href="projects.php" style="color:var(--gold-light, #D4882A)">Clear</a></p>
      <?php endif; ?>

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
              <span style="display:inline-block;margin-top:16px;padding:9px 20px;background:linear-gradient(135deg,var(--gold, #D4882A),#b06a1e);color:#fff;border-radius:8px;font-size:13px;font-weight:700">View Details &rarr;</span>
            </a>
          <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
          <div class="pagination-pub">
            <a href="?page=<?= $page - 1 ?>&q=<?= e($search) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹ Prev</a>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
              <a href="?page=<?= $i ?>&q=<?= e($search) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?page=<?= $page + 1 ?>&q=<?= e($search) ?>" class="page-btn <?= $page >= $pages ? 'disabled' : '' ?>">Next ›</a>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div style="text-align:center;padding:80px 20px;color:rgba(255,255,255,0.6)">
          <?php if ($search): ?>
            <p style="font-size:1.2rem;font-weight:600">No projects found for "<?= e($search) ?>"</p>
            <p style="margin-top:8px"><a href="projects.php" style="color:var(--gold-light, #D4882A)">Clear search</a></p>
          <?php else: ?>
            <p style="font-size:1.2rem;font-weight:600">No projects yet</p>
            <p style="margin-top:8px">Projects will appear here once added through the admin dashboard.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/scripts.js"></script>
</body>

</html>