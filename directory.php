<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Member.php';
require_once __DIR__ . '/includes/helpers.php';

$db   = new Database();
$conn = $db->connect();

$member_obj = new Member($conn);
$search = trim($_GET['q'] ?? '');
$limit  = 24;
$page   = max(1, (int) ($_GET['page'] ?? 1));
$total  = $member_obj->countApprovedForDirectory($search);
$pages  = max(1, (int) ceil($total / $limit));
$page   = min($page, $pages);
$offset = ($page - 1) * $limit;
$members = $member_obj->getApprovedForDirectoryPage($limit, $offset, $search);

$page_title = site_title($conn, 'Member Directory');
$page_description = 'Browse members of the Rotaract Club of Kwanza directory.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <section id="directory" class="pt-100">
    <div class="container">
      <div class="projects-header">
        <div>
          <div class="section-eyebrow section-eyebrow--hero-dark reveal">Our Members</div>
          <h2 class="section-title reveal reveal-delay-1">Member <em>Directory</em></h2>
          <p class="section-lead reveal reveal-delay-2 section-lead--white">Meet the passionate young leaders who make up Rotaract Club of Kwanza.</p>
        </div>
      </div>

      <form method="GET" class="dir-search-bar reveal">
        <input type="text" name="q" value="<?= e($search) ?>"
          placeholder="Search by name or profession…" autofocus>
        <button type="submit">Search</button>
      </form>

      <?php if ($members): ?>
        <p class="dir-count reveal"><?= $total ?> member<?= $total !== 1 ? 's' : '' ?> listed</p>
        <div class="dir-grid">
          <?php foreach ($members as $i => $member): ?>
            <?php
            $initials = strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1));
            $av_class = 'av-' . ($i % 7);
            ?>
            <div class="dir-card reveal" role="link" tabindex="0" data-profile-url="member.php?source=directory&id=<?= (int) $member['id'] ?>">
              <?php if ($member['photo_path']): ?>
                <div class="dir-avatar dir-avatar-photo">
                  <img src="<?= e(img_url($member['photo_path'])) ?>" alt="<?= e($member['first_name'] . ' ' . $member['last_name']) ?>">
                </div>
              <?php else: ?>
                <div class="dir-avatar <?= $av_class ?>"><?= e($initials) ?></div>
              <?php endif; ?>
              <div class="dir-name"><a class="member-card-profile-link" href="member.php?source=directory&id=<?= (int) $member['id'] ?>"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></a></div>
              <?php if ($member['occupation']): ?>
                <div class="dir-role"><?= e($member['occupation']) ?></div>
              <?php endif; ?>
            <?php if ($member['bio'] ?? ''): ?>
              <p class="dir-bio member-card-description"><?= e($member['bio']) ?></p>
            <?php endif; ?>
            <?php if ($member['email'] ?? ''): ?>
              <div class="team-email-row">
                <a href="mailto:<?= e($member['email']) ?>" class="team-social-link"><?= e($member['email']) ?></a>
              </div>
            <?php endif; ?>
            <?php if (($member['linkedin_url'] ?? '') || ($member['instagram_url'] ?? '')): ?>
              <div class="team-social-row">
                <?php if ($member['linkedin_url'] ?? ''): ?>
                  <a href="<?= e($member['linkedin_url']) ?>" target="_blank" rel="noopener" class="team-social-link">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z" />
                        <rect x="2" y="9" width="4" height="12" />
                        <circle cx="4" cy="4" r="2" />
                      </svg>
                      LinkedIn
                    </a>
                <?php endif; ?>
                <?php if ($member['instagram_url'] ?? ''): ?>
                  <a href="<?= e($member['instagram_url']) ?>" target="_blank" rel="noopener" class="team-social-link">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                        <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                      </svg>
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

        <?php if ($pages > 1): ?>
          <div class="pagination-pub reveal">
            <a href="?page=<?= $page - 1 ?>&q=<?= e($search) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹ Prev</a>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
              <a href="?page=<?= $i ?>&q=<?= e($search) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?page=<?= $page + 1 ?>&q=<?= e($search) ?>" class="page-btn <?= $page >= $pages ? 'disabled' : '' ?>">Next ›</a>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="dir-empty reveal">
          <?php if ($search): ?>
            <p class="dir-empty-title">No results for "<?= e($search) ?>"</p>
            <a href="directory.php" class="dir-empty-clear">Clear search</a>
          <?php else: ?>
            <p class="dir-empty-title">No members listed yet</p>
            <p class="mt-6">Members must be approved and opted in to the directory by an admin.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/scripts.js"></script>
</body>

</html>
