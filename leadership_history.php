<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/LeadershipTerm.php';
require_once __DIR__ . '/includes/helpers.php';

$db = new Database();
$conn = $db->connect();
$termModel = new LeadershipTerm($conn);
$termsData = $termModel->getActiveWithMembers();
$termLabels = array_column($termsData, 'term_label');
$hasRecords = !empty($termsData);
$selectedTerm = isset($_GET['term']) ? trim((string) $_GET['term']) : '';
if ($selectedTerm !== '' && !in_array($selectedTerm, $termLabels, true)) {
    $selectedTerm = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(site_title($conn, 'Leadership History')) ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo1.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/kwanza.css">
  <style>
    .history-actions { display:flex; flex-wrap:wrap; gap:12px; justify-content:center; margin-top:32px; }
    .history-controls {
      display:flex; flex-direction:column; align-items:center; gap:12px;
      margin-top:36px; max-width:480px; margin-left:auto; margin-right:auto;
    }
    .history-controls label {
      font-size:12px; font-weight:800; letter-spacing:1.2px; text-transform:uppercase;
      color:var(--pink-700);
    }
    .history-term-picker {
      width:100%;
      background:linear-gradient(135deg,#fff 0%,var(--pink-50) 100%);
      border:1px solid rgba(192,57,107,.15);
      border-radius:18px;
      padding:5px;
      box-shadow:0 14px 36px rgba(192,57,107,.1);
    }
    .history-term-picker-inner {
      position:relative;
      display:flex;
      align-items:center;
      gap:12px;
      background:#fff;
      border-radius:13px;
      padding:0 44px 0 16px;
      min-height:54px;
    }
    .history-term-icon {
      flex-shrink:0;
      width:20px; height:20px;
      color:var(--pink-600);
    }
    .history-term-select {
      flex:1;
      width:100%;
      border:none;
      background:transparent;
      appearance:none;
      -webkit-appearance:none;
      padding:14px 0;
      font-family:'Cormorant Garamond',serif;
      font-size:1.2rem;
      font-weight:600;
      color:var(--pink-800);
      cursor:pointer;
      line-height:1.2;
    }
    .history-term-select:focus { outline:none; }
    .history-term-chevron {
      position:absolute;
      right:16px;
      top:50%;
      transform:translateY(-50%);
      width:18px; height:18px;
      color:var(--pink-500);
      pointer-events:none;
    }
    .history-term-picker:focus-within {
      border-color:var(--pink-400);
      box-shadow:0 0 0 3px var(--pink-50), 0 14px 36px rgba(192,57,107,.12);
    }
    .history-term-summary {
      text-align:center; max-width:640px; margin:0 auto 24px;
      color:var(--text-muted); line-height:1.75; font-size:15px;
    }
    .history-term-photo {
      display:block; max-width:420px; width:100%; margin:0 auto 28px;
      border-radius:16px; border:1px solid var(--border);
      box-shadow:0 12px 32px rgba(0,0,0,.06);
    }
    .history-filter-hint {
      font-size:13px; color:var(--text-soft); text-align:center; margin:0;
    }
    .history-timeline { position:relative; max-width:960px; margin:56px auto 0; padding-left:36px; }
    .history-timeline::before {
      content:''; position:absolute; left:10px; top:8px; bottom:8px; width:2px;
      background:linear-gradient(180deg,var(--pink-200),var(--pink-400));
    }
    .history-term-block { position:relative; margin-bottom:48px; }
    .history-term-block:last-child { margin-bottom:0; }
    .history-term-marker {
      position:absolute; left:-36px; top:4px; width:22px; height:22px; border-radius:50%;
      background:#fff; border:3px solid var(--pink-600); box-shadow:0 0 0 6px var(--pink-50);
    }
    .history-term-label {
      font-family:'Cormorant Garamond',serif; font-size:1.75rem; font-weight:700;
      color:var(--pink-800); margin:0 0 8px;
    }
    .history-term-meta {
      font-size:13px; color:var(--text-soft); font-weight:600; margin:0 0 20px;
    }
    .history-term-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:24px; }
    .history-card {
      background:#fff; border:1px solid var(--border); border-radius:20px; overflow:hidden;
      box-shadow:0 14px 40px rgba(0,0,0,.05); text-align:center;
      transition:transform .25s ease, box-shadow .25s ease;
    }
    .history-card:hover {
      transform:translateY(-4px);
      box-shadow:0 18px 44px rgba(0,0,0,.08);
    }
    .history-avatar {
      height:150px;
      display:flex; align-items:center; justify-content:center;
    }
    .history-avatar-circle {
      width:96px; height:96px; border-radius:50%; overflow:hidden;
      display:flex; align-items:center; justify-content:center;
      font-family:'Cormorant Garamond',serif;
      font-size:2rem; font-weight:700; color:#fff;
      box-shadow:0 8px 24px rgba(180,20,90,.25);
    }
    .history-avatar-circle img {
      width:100%; height:100%; object-fit:cover; display:block;
    }
    .history-card-body { padding:22px 24px 26px; }
    .history-role {
      font-size:12px; font-weight:700; color:var(--pink-600);
      letter-spacing:.8px; text-transform:uppercase; margin:0 0 8px;
    }
    .history-card-body h3 {
      font-family:'Cormorant Garamond',serif;
      margin:0 0 12px; font-size:1.25rem; font-weight:600; color:var(--text-dark);
    }
    .history-description { color:var(--text-muted); line-height:1.65; font-size:13px; margin:0; }

    /* Focused single-term view */
    .history-timeline.timeline-focused {
      max-width:1100px; padding-left:0; margin-top:40px;
    }
    .history-timeline.timeline-focused::before { display:none; }
    .history-timeline.timeline-focused .history-term-marker { display:none; }
    .history-timeline.timeline-focused .history-term-label {
      text-align:center; font-size:2.1rem; margin-bottom:6px;
    }
    .history-timeline.timeline-focused .history-term-meta { text-align:center; margin-bottom:32px; }

    @media (max-width:640px) {
      .history-timeline { padding-left:28px; }
      .history-term-marker { left:-28px; width:18px; height:18px; }
      .history-timeline.timeline-focused { padding-left:0; }
    }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <section id="leadership-history" style="padding-top:100px">
    <div class="container">
      <div style="text-align:center;max-width:700px;margin:0 auto 24px">
        <div class="section-eyebrow reveal" style="justify-content:center">Leadership Through Time</div>
        <h2 class="section-title reveal reveal-delay-1">Club Leadership History</h2>
        <p class="section-lead reveal reveal-delay-2" style="margin:0 auto">Explore the officers, terms, and past leaders who have helped shape the Rotaract Club of Kwanza.</p>
      </div>

      <div class="history-actions reveal reveal-delay-2">
        <a href="team.php" class="btn-secondary">View Current Team</a>
      </div>

      <?php if ($hasRecords): ?>
        <?php if (count($termLabels) > 1): ?>
        <div class="history-controls reveal reveal-delay-3">
          <label for="term-filter">Browse by Term</label>
          <div class="history-term-picker">
            <div class="history-term-picker-inner">
              <svg class="history-term-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
              </svg>
              <select id="term-filter" class="history-term-select" aria-label="Select leadership term">
                <option value="all"<?= $selectedTerm === '' ? ' selected' : '' ?>>All Terms — Full Timeline</option>
                <?php foreach ($termsData as $t): ?>
                <option value="<?= e($t['term_label']) ?>"<?= $selectedTerm === $t['term_label'] ? ' selected' : '' ?>><?= e($t['term_label']) ?> Leadership Team</option>
                <?php endforeach; ?>
              </select>
              <svg class="history-term-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </div>
          </div>
          <p class="history-filter-hint" id="filter-hint">
            <?= $selectedTerm !== '' ? 'Showing the ' . e($selectedTerm) . ' leadership team.' : 'Select a term to view that period\'s full leadership team.' ?>
          </p>
        </div>
        <?php endif; ?>

        <div class="history-timeline<?= $selectedTerm !== '' ? ' timeline-focused' : '' ?>" id="history-timeline">
          <?php foreach ($termsData as $termRow):
            $label = $termRow['term_label'];
            $records = $termRow['members'];
            $count = count($records);
            $hidden = $selectedTerm !== '' && $selectedTerm !== $label;
          ?>
          <article class="history-term-block reveal" data-term="<?= e($label) ?>"<?= $hidden ? ' style="display:none"' : '' ?>>
            <div class="history-term-marker" aria-hidden="true"></div>
            <h3 class="history-term-label"><?= e($label) ?></h3>
            <p class="history-term-meta"><?= $count ?> officer<?= $count !== 1 ? 's' : '' ?> served this term</p>
            <?php if ($termRow['summary']): ?>
            <p class="history-term-summary"><?= e($termRow['summary']) ?></p>
            <?php endif; ?>
            <?php if ($termRow['image_path']): ?>
            <img class="history-term-photo reveal" src="<?= e(img_url($termRow['image_path'])) ?>" alt="<?= e($label) ?> leadership team">
            <?php endif; ?>
            <div class="history-term-grid">
              <?php foreach ($records as $i => $record):
                $pal = avatar_palette($i);
                $words = array_filter(explode(' ', $record['full_name']));
                $initials = substr(strtoupper(implode('', array_map(fn($w) => $w[0], $words))), 0, 2);
                $photo = $record['photo_path'] ?? '';
              ?>
              <div class="history-card">
                <div class="history-avatar" style="background:<?= $pal['bg'] ?>">
                  <div class="history-avatar-circle" style="<?= $photo ? 'padding:0;background:none' : 'background:' . $pal['circle'] ?>">
                    <?php if ($photo): ?>
                      <img src="<?= e(img_url($photo)) ?>" alt="<?= e($record['full_name']) ?>">
                    <?php else: ?>
                      <?= e($initials) ?>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="history-card-body">
                  <p class="history-role"><?= e($record['role']) ?></p>
                  <h3><?= e($record['full_name']) ?></h3>
                  <?php if ($record['description']): ?>
                    <p class="history-description"><?= e($record['description']) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
      <div style="text-align:center;padding:80px 20px;color:var(--text-soft)">
        <p style="font-size:1.2rem;font-weight:600">No history records available yet.</p>
        <p style="margin-top:8px">Leadership archives will appear here once added through the admin panel.</p>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/scripts.js"></script>
  <?php if ($hasRecords && count($termLabels) > 1): ?>
  <script>
  (function () {
    const select = document.getElementById('term-filter');
    const timeline = document.getElementById('history-timeline');
    const hint = document.getElementById('filter-hint');
    const blocks = document.querySelectorAll('.history-term-block');
    if (!select || !timeline) return;

    function applyTermFilter(term) {
      const isAll = term === 'all';
      timeline.classList.toggle('timeline-focused', !isAll);
      blocks.forEach((block) => {
        block.style.display = isAll || block.dataset.term === term ? '' : 'none';
      });
      if (hint) {
        hint.textContent = isAll
          ? 'Select a term to view that period\'s full leadership team.'
          : 'Showing the ' + term + ' leadership team.';
      }
      const url = new URL(window.location.href);
      if (isAll) url.searchParams.delete('term');
      else url.searchParams.set('term', term);
      history.replaceState(null, '', url);
    }

    select.addEventListener('change', function () {
      applyTermFilter(select.value);
    });
  })();
  </script>
  <?php endif; ?>
</body>
</html>
