<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/TeamMember.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();
$team = (new TeamMember($conn))->getActive();

// team.php's getActive() already orders members by their role's display_order, so grouping
// into sections just means detecting where tier_label changes as we walk the ordered list —
// no numeric tier needed, the DB-managed role list (admin/roles.php) drives both order and grouping.
$tiers = [];
foreach ($team as $tm) {
  $tiers[$tm['tier_label']][] = $tm;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Our Team &mdash; Rotaract Club of Kwanza</title>
  <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="assets/css/kwanza.css">
  <style>
    .team-tier { margin-bottom: 16px; }
    .team-tier-label {
      display: flex; align-items: center; justify-content: center; gap: 14px;
      text-align: center; font-size: 12.5px; font-weight: 800; letter-spacing: 1.5px;
      text-transform: uppercase; color: var(--pink-700); margin: 0 0 28px;
    }
    .team-tier-label::before, .team-tier-label::after {
      content: ''; flex: 1; max-width: 90px; height: 1px; background: var(--border);
    }
    .team-connector { display: flex; justify-content: center; margin: 4px 0 36px; color: var(--pink-300); }

    .team-row { display: flex; flex-wrap: wrap; justify-content: center; gap: 28px; margin-bottom: 56px; }
    .team-row .team-card { width: 260px; }

    .team-row.tier-1 .team-card { width: 290px; }
    .team-row.tier-1 .team-avatar { height: 220px; }
    .team-row.tier-1 .team-avatar-circle { width: 130px; height: 130px; font-size: 3rem; }
    .team-row.tier-1 .team-card-body h4 { font-size: 1.45rem; }

    .team-row.tier-2 .team-avatar { height: 195px; }
    .team-row.tier-2 .team-avatar-circle { width: 110px; height: 110px; }

    .team-social-row { display: flex; gap: 12px; align-items: center; justify-content: center; margin-top: 6px; flex-wrap: wrap; }
    .team-social-link { font-size: 12px; color: var(--pink-600); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
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
          dedicate their time to making a difference in Kwanza &mdash; organized by leadership structure.</p>
      </div>

      <?php if ($team): ?>
        <div style="margin-top:48px">
          <?php $tier_i = 0; foreach ($tiers as $tier_label => $members): $tier_i++; ?>
            <?php if ($tier_i > 1): ?>
              <div class="team-connector">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            <?php endif; ?>

            <div class="team-tier">
              <div class="team-tier-label"><?= e($tier_label) ?></div>

              <div class="<?= $tier_i <= 2 ? 'team-row tier-' . $tier_i : 'team-grid' ?>">
                <?php foreach ($members as $i => $tm):
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
                      <h4><?= e($tm['full_name']) ?></h4>
                      <div class="role"><?= e($tm['role']) ?></div>
                      <?php if ($tm['term'] ?? ''): ?>
                        <div style="font-size:11.5px;color:var(--text-soft);font-weight:600;letter-spacing:.3px;margin-top:2px">Term <?= e($tm['term']) ?></div>
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
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                            LinkedIn
                          </a>
                        <?php endif; ?>
                        <?php if ($tm['instagram_url'] ?? ''): ?>
                          <a href="<?= e($tm['instagram_url']) ?>" target="_blank" rel="noopener" class="team-social-link">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                            Instagram
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
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
