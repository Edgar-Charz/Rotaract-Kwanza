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

  <section id="projects" class="pt-100">
    <div class="container"> 
      <div class="projects-header">
        <div>
          <div class="section-eyebrow reveal">Our Impact</div>
          <h2 class="section-title reveal reveal-delay-1">Community <em>Projects</em></h2>
          <p class="section-lead reveal reveal-delay-2 section-lead--white">All the initiatives and programmes Rotaract Kwanza runs to serve our community.</p>
        </div>
      </div>

      <form method="GET" class="projects-search-form reveal">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search projects…"
          class="projects-search-input">
        <button type="submit" class="projects-search-btn">Search</button>
      </form>

      <?php if ($search && $projects): ?>
        <p class="projects-results-count"><?= $total ?> result<?= $total !== 1 ? 's' : '' ?> for "<?= e($search) ?>" &middot; <a href="projects.php" class="link-gold">Clear</a></p>
      <?php endif; ?>

      <?php if ($projects): ?>
        <div class="projects-grid">
          <?php foreach ($projects as $i => $pj): ?>
            <a href="project.php?id=<?= $pj['id'] ?>" class="project-card reveal<?= $i > 0 ? ' reveal-delay-' . ($i % 4) : '' ?>">
              <?php if ($pj['image_path'] ?? ''): ?>
                <div class="project-icon project-icon--photo">
                  <img src="<?= e(img_url($pj['image_path'])) ?>" alt="<?= e($pj['title']) ?>">
                </div>
              <?php else: ?>
                <div class="project-icon"><?= icon_svg($pj['icon_type'] ?: 'heart', 'var(--gold-light)') ?></div>
              <?php endif; ?>
              <?php if ($pj['is_featured']): ?>
                <span class="project-featured-badge">Featured</span>
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
                  <div class="impact-num impact-num--status"><?= e($pj['status']) ?></div>
                  <div class="impact-label">Status</div>
                </div>
              </div>
              <?php if ($pj['funding_goal']): $pj_raised = $raised_by_project[$pj['id']] ?? 0; ?>
                <div class="pj-funding-mini">
                  <div class="pj-funding-mini-labels">
                    <span><?= e(number_format($pj_raised, 0)) ?> raised</span>
                    <span>of <?= e(number_format((float) $pj['funding_goal'], 0)) ?></span>
                  </div>
                  <div class="pj-funding-mini-track">
                    <div class="pj-funding-mini-fill" style="width:<?= min(100, $pj['funding_goal'] > 0 ? ($pj_raised / $pj['funding_goal']) * 100 : 0) ?>%"></div>
                  </div>
                </div>
              <?php endif; ?>
              <span class="project-view-btn">View Details &rarr;</span>
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
        <div class="gallery-empty gallery-empty--dark">
          <?php if ($search): ?>
            <p class="gallery-empty-title">No projects found for "<?= e($search) ?>"</p>
            <p class="mt-8"><a href="projects.php" class="link-gold">Clear search</a></p>
          <?php else: ?>
            <p class="gallery-empty-title">No projects yet</p>
            <p class="mt-8">Projects will appear here once added through the admin dashboard.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($completed): ?>
        <div class="mt-60">
          <h3 class="section-title section-title--gallery text-white">Completed <em>Projects</em></h3>
          <div class="projects-grid">
            <?php foreach ($completed as $pj): ?>
              <a href="project.php?id=<?= $pj['id'] ?>" class="project-card project-card--completed reveal">
                <?php if ($pj['image_path'] ?? ''): ?>
                  <div class="project-icon project-icon--photo project-icon--gray">
                    <img src="<?= e(img_url($pj['image_path'])) ?>" alt="<?= e($pj['title']) ?>">
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
                    <div class="impact-num impact-num--status"><?= e($pj['status']) ?></div>
                    <div class="impact-label">Status</div>
                  </div>
                </div>
                <span class="project-view-btn--text">View Details &rarr;</span>
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