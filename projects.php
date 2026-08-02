<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Project.php';
require_once __DIR__ . '/classes/Pledge.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$project_obj = new Project($conn);
$search = trim($_GET['q'] ?? '');
$limit  = 12;
$page   = max(1, (int) ($_GET['page'] ?? 1));
$total  = $project_obj->countActive($search);
$pages  = max(1, (int) ceil($total / $limit));
$page   = min($page, $pages);
$offset = ($page - 1) * $limit;
$projects = $project_obj->getActivePage($limit, $offset, $search);
// Completed projects get their own section below, out of the main grid —
// same reasoning as events.php keeping past events below upcoming ones.
// Hidden during a search, same as events.php hides its past section then.
$completed = $search === '' ? $project_obj->getCompleted(6) : [];
$raised_by_project = (new Pledge($conn))->getConfirmedAmountsByProject();

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
              <?php if ($pj['funding_goal']): $pj_raised = $raised_by_project[$pj['id']] ?? 0; ?>
                <div style="margin-top:16px">
                  <div style="display:flex;justify-content:space-between;font-size:11.5px;color:rgba(255,255,255,.7);margin-bottom:5px">
                    <span><?= e(number_format($pj_raised, 0)) ?> raised</span>
                    <span>of <?= e(number_format((float) $pj['funding_goal'], 0)) ?></span>
                  </div>
                  <div style="background:rgba(255,255,255,.12);border-radius:20px;height:7px;overflow:hidden">
                    <div style="background:linear-gradient(90deg,var(--gold, #D4882A),var(--gold-light, #e0a34f));height:100%;border-radius:20px;width:<?= min(100, $pj['funding_goal'] > 0 ? ($pj_raised / $pj['funding_goal']) * 100 : 0) ?>%"></div>
                  </div>
                </div>
              <?php endif; ?>
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

      <?php if ($completed): ?>
        <div style="margin-top:60px">
          <h3 class="section-title" style="font-size:1.6rem;margin-bottom:24px;color:#fff">Completed <em>Projects</em></h3>
          <div class="projects-grid">
            <?php foreach ($completed as $pj): ?>
              <a href="project.php?id=<?= $pj['id'] ?>" class="project-card reveal" style="display:block;color:inherit;text-decoration:none;opacity:.75">
                <?php if ($pj['image_path'] ?? ''): ?>
                  <div class="project-icon" style="width:100%;height:140px;border-radius:12px;overflow:hidden;margin-bottom:20px;filter:grayscale(30%)">
                    <img src="<?= e(img_url($pj['image_path'])) ?>" alt="<?= e($pj['title']) ?>" style="width:100%;height:100%;object-fit:cover">
                  </div>
                <?php else: ?>
                  <div class="project-icon"><?= icon_svg($pj['icon_type'] ?: 'heart', 'var(--gold-light)') ?></div>
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
                <span style="display:inline-block;margin-top:16px;font-size:13px;font-weight:700;color:var(--gold-light, #D4882A)">View Details &rarr;</span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/scripts.js"></script>
</body>

</html>