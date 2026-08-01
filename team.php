<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/TeamMember.php';
require_once __DIR__ . '/classes/TeamPhoto.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();
$team = (new TeamMember($conn))->getActive();
$team_photos = array_column((new TeamPhoto($conn))->getAll(), 'image_path');

$page_title = site_title($conn, 'Our Team');
$page_description = 'Meet the passionate leaders driving community service at Rotaract Club of Kwanza.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php require __DIR__ . '/includes/public_head.php'; ?>
  <style>
    .team-social-row {
      display: flex;
      gap: 12px;
      align-items: center;
      justify-content: center;
      margin-top: 6px;
      flex-wrap: wrap;
    }

    .team-social-link {
      font-size: 12px;
      color: var(--pink-600);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .team-photo-slider {
      max-width: 720px;
      height: 360px;
      margin: 32px auto 0;
    }

    @media (max-width: 640px) {
      .team-photo-slider {
        height: 240px;
      }
    }
  </style>
</head>

<body>

  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <section id="team" style="padding-top:100px">
    <div class="container">
      <div style="text-align:center;max-width:600px;margin:0 auto 12px">
        <div class="section-eyebrow reveal" style="justify-content:center">Our Leadership</div>
        <h2 class="section-title reveal reveal-delay-1">Meet the <em>Team</em></h2>
        <p class="section-lead reveal reveal-delay-2" style="margin:0 auto">Passionate, driven young leaders who
          dedicate their time to making a difference in Kwanza &mdash; organized by leadership structure.
        </p>
      </div>

      <?php if ($team_photos): ?>
        <div class="team-photo-slider reveal reveal-delay-2">
          <?= render_photo_slider($team_photos, 'Current Rotaract Kwanza leadership team') ?>
        </div>
      <?php endif; ?>

      <?php if ($team): ?>
        <div class="team-grid" style="margin-top:48px">
          <?php foreach ($team as $i => $tm):
            $pal = avatar_palette($i);
            $words = array_filter(explode(' ', $tm['full_name']));
            $initials = substr(strtoupper(implode('', array_map(fn($w) => $w[0], $words))), 0, 2);
            ?>
            <div class="team-card reveal<?= $i > 0 && $i < 4 ? ' reveal-delay-' . ($i % 4) : '' ?>">
              <div class="team-avatar" style="background:<?= $pal['bg'] ?>">
                <?php if ($tm['image_path']): ?>
                  <div class="team-avatar-circle" style="overflow:hidden;padding:0">
                    <img src="<?= e(img_url($tm['image_path'])) ?>" alt="<?= e($tm['full_name']) ?>"
                      style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">
                  </div>
                <?php else: ?>
                  <div class="team-avatar-circle" style="background:<?= $pal['circle'] ?>"><?= $initials ?></div>
                <?php endif; ?>
              </div>
              <div class="team-card-body">
                <div class="role"><?= e($tm['role']) ?></div>
                <h4><?= e($tm['full_name']) ?></h4>
                <?php if ($tm['term'] ?? ''): ?>
                  <div style="font-size:11.5px;color:var(--text-soft);font-weight:600;letter-spacing:.3px;margin:-6px 0 14px">
                    Term <?= e($tm['term']) ?></div>
                <?php endif; ?>
                <?php if ($tm['description']): ?>
                  <p><?= e($tm['description']) ?></p><?php endif; ?>
                <div class="team-social-row">
                  <?php if ($tm['email']): ?>
                    <a href="mailto:<?= e($tm['email']) ?>" class="team-social-link">
                      <?= e($tm['email']) ?>
                    </a>
                  <?php endif; ?>
                  <?php if ($tm['linkedin_url'] ?? ''): ?>
                    <a href="<?= e($tm['linkedin_url']) ?>" target="_blank" rel="noopener" class="team-social-link">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z" />
                        <rect x="2" y="9" width="4" height="12" />
                        <circle cx="4" cy="4" r="2" />
                      </svg>
                      LinkedIn
                    </a>
                  <?php endif; ?>
                  <?php if ($tm['instagram_url'] ?? ''): ?>
                    <a href="<?= e($tm['instagram_url']) ?>" target="_blank" rel="noopener" class="team-social-link">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round">
                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                        <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" />
                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                      </svg>
                      Instagram
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:80px 20px;color:var(--text-soft)">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"
            style="opacity:.3;margin-bottom:16px">
            <circle cx="12" cy="8" r="4" />
            <path d="M6 20v-2a6 6 0 0 1 12 0v2" />
          </svg>
          <p style="font-size:1.2rem;font-weight:600">Team information coming soon</p>
          <p style="margin-top:8px">Team members will appear here once added through the admin dashboard.</p>
        </div>
      <?php endif; ?>

      <div style="text-align:center;margin-top:48px">
        <a href="leadership_history.php" class="btn-secondary reveal">View Leadership History</a>
      </div>
    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="assets/js/scripts.js"></script>
</body>

</html>