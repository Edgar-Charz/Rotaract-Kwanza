<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Reports & Analytics';

// ── Member sign-ups by month (last 12 months) ─────────────────────────────────
$member_chart = monthly_counts($conn, 'members', 12);

// ── Member status breakdown ───────────────────────────────────────────────────
$status_rows = db_rows($conn, "SELECT status, COUNT(*) AS n FROM members GROUP BY status");
$status_data = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($status_rows as $r) {
    if (isset($status_data[$r['status']])) $status_data[$r['status']] = (int)$r['n'];
}

// ── Dues breakdown ────────────────────────────────────────────────────────────
$dues_rows = db_rows($conn, "SELECT status, COUNT(*) AS n, COALESCE(SUM(amount_paid),0) AS total FROM member_dues GROUP BY status");
$dues_data = ['paid' => ['count' => 0, 'total' => 0], 'partial' => ['count' => 0, 'total' => 0], 'unpaid' => ['count' => 0, 'total' => 0]];
foreach ($dues_rows as $r) {
    if (isset($dues_data[$r['status']])) {
        $dues_data[$r['status']] = ['count' => (int)$r['n'], 'total' => (float)$r['total']];
    }
}
$dues_total_collected = $dues_data['paid']['total'] + $dues_data['partial']['total'];

// ── RSVPs by event (top 10) ───────────────────────────────────────────────────
$rsvp_chart = db_rows($conn,
    "SELECT e.title, COUNT(r.id) AS n
     FROM event_rsvps r JOIN events e ON e.id = r.event_id
     GROUP BY r.event_id ORDER BY n DESC LIMIT 10"
);
$total_rsvps = (int) db_val($conn, "SELECT COUNT(*) FROM event_rsvps");

// ── Monthly RSVP trend (last 6 months) ────────────────────────────────────────
$rsvp_trend = monthly_counts($conn, 'event_rsvps', 6);

// ── Events by category (top 8) ────────────────────────────────────────────────
$cat_rows = db_rows($conn, "SELECT category, COUNT(*) AS n FROM events WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY n DESC LIMIT 8");

// ── Messages by month (last 6 months) ─────────────────────────────────────────
$msg_trend = monthly_counts($conn, 'contact_messages', 6);

// ── Attendance rate per event ─────────────────────────────────────────────────
$attend_rows = db_rows($conn,
    "SELECT e.title,
            COUNT(r.id)                                AS total,
            SUM(COALESCE(r.attended,0))                AS attended
     FROM event_rsvps r JOIN events e ON e.id = r.event_id
     GROUP BY r.event_id HAVING total > 0 ORDER BY e.event_date DESC LIMIT 8"
);

include __DIR__ . '/includes/header.php';
?>

<style>
.report-grid   { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
.report-grid.thirds { grid-template-columns:1fr 1fr 1fr; }
.chart-card    { background:#fff; border-radius:14px; padding:24px; box-shadow:0 1px 8px rgba(0,0,0,.07); }
.chart-title   { font-size:14px; font-weight:700; color:var(--text); margin-bottom:4px; }
.chart-sub     { font-size:12px; color:var(--text-muted,#888); margin-bottom:18px; }
.chart-wrap    { position:relative; }
.kpi-row       { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
.kpi-card      { background:#fff; border-radius:12px; padding:18px 20px; box-shadow:0 1px 8px rgba(0,0,0,.07); }
.kpi-label     { font-size:11.5px; color:var(--text-muted,#888); font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; }
.kpi-value     { font-size:1.7rem; font-weight:800; color:var(--text); line-height:1; }
.kpi-sub       { font-size:11.5px; color:var(--text-muted,#888); margin-top:4px; }
.attend-table  { width:100%; border-collapse:collapse; font-size:13px; }
.attend-table th { padding:8px 10px; text-align:left; font-size:11.5px; text-transform:uppercase; letter-spacing:.5px; color:#888; border-bottom:1.5px solid var(--border); }
.attend-table td { padding:9px 10px; border-bottom:1px solid var(--border,#f0f0f0); }
.attend-bar    { background:#f0f0f0; border-radius:4px; height:8px; overflow:hidden; margin-top:4px; }
.attend-fill   { background:linear-gradient(90deg,var(--primary,#C0396B),#e88ab5); height:100%; border-radius:4px; }
@media (max-width:900px) { .report-grid,.report-grid.thirds,.kpi-row { grid-template-columns:1fr; } }
</style>

<div style="display:flex;justify-content:flex-end;margin-bottom:14px">
  <a href="export_report.php" class="btn btn-sm btn-secondary">Export Report (CSV)</a>
</div>

<!-- KPI row -->
<div class="kpi-row">
  <div class="kpi-card">
    <div class="kpi-label">Total Members</div>
    <div class="kpi-value"><?= $status_data['pending'] + $status_data['approved'] + $status_data['rejected'] ?></div>
    <div class="kpi-sub"><?= $status_data['approved'] ?> approved</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">Total RSVPs</div>
    <div class="kpi-value"><?= $total_rsvps ?></div>
    <div class="kpi-sub">across all events</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">Dues Collected</div>
    <div class="kpi-value"><?= number_format($dues_total_collected, 0, '.', ',') ?></div>
    <div class="kpi-sub"><?= $dues_data['paid']['count'] + $dues_data['partial']['count'] ?> payments</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">Pending Applications</div>
    <div class="kpi-value"><?= $status_data['pending'] ?></div>
    <div class="kpi-sub">awaiting review</div>
  </div>
</div>

<!-- Row 1: member sign-ups + member status doughnut -->
<div class="report-grid">
  <div class="chart-card">
    <div class="chart-title">Member Sign-ups</div>
    <div class="chart-sub">New applications over the last 12 months</div>
    <div class="chart-wrap"><canvas id="memberChart" height="220" role="img" aria-label="Bar chart of new member applications per month, last 12 months"></canvas></div>
  </div>
  <div class="chart-card">
    <div class="chart-title">Member Status Breakdown</div>
    <div class="chart-sub">Current distribution by application status</div>
    <div class="chart-wrap" style="max-width:280px;margin:0 auto"><canvas id="statusChart" height="220" role="img" aria-label="Doughnut chart of member application status breakdown"></canvas></div>
  </div>
</div>

<!-- Row 2: RSVPs by event + monthly RSVP trend -->
<div class="report-grid">
  <div class="chart-card">
    <div class="chart-title">RSVPs by Event</div>
    <div class="chart-sub">Top 10 events by registration count</div>
    <div class="chart-wrap"><canvas id="rsvpChart" height="260" role="img" aria-label="Horizontal bar chart of RSVP counts for the top 10 events"></canvas></div>
  </div>
  <div class="chart-card">
    <div class="chart-title">Monthly RSVP Trend</div>
    <div class="chart-sub">Registrations per month — last 6 months</div>
    <div class="chart-wrap"><canvas id="rsvpTrendChart" height="260" role="img" aria-label="Line chart of RSVP registrations per month, last 6 months"></canvas></div>
  </div>
</div>

<!-- Row 3: Dues doughnut + Events by category -->
<div class="report-grid">
  <div class="chart-card">
    <div class="chart-title">Dues Overview</div>
    <div class="chart-sub">Records by payment status</div>
    <div class="chart-wrap" style="max-width:280px;margin:0 auto"><canvas id="duesChart" height="220" role="img" aria-label="Doughnut chart of member dues records by payment status"></canvas></div>
  </div>
  <div class="chart-card">
    <div class="chart-title">Events by Category</div>
    <div class="chart-sub">Top 8 categories, all-time</div>
    <div class="chart-wrap"><canvas id="catChart" height="220" role="img" aria-label="Bar chart of event counts by category"></canvas></div>
  </div>
</div>

<!-- Row 4: Attendance + Messages trend -->
<div class="report-grid">
  <div class="chart-card">
    <div class="chart-title">Attendance Rate by Event</div>
    <div class="chart-sub">Recent events — check-ins vs registrations</div>
    <?php if ($attend_rows): ?>
    <table class="attend-table">
      <thead><tr><th>Event</th><th style="width:80px;text-align:right">Attended</th><th style="width:80px;text-align:right">Registered</th><th style="min-width:100px">Rate</th></tr></thead>
      <tbody>
        <?php foreach ($attend_rows as $ar):
            $rate = $ar['total'] > 0 ? round($ar['attended'] / $ar['total'] * 100) : 0;
        ?>
        <tr>
          <td><?= h(mb_strimwidth($ar['title'], 0, 40, '…')) ?></td>
          <td style="text-align:right;font-weight:700"><?= $ar['attended'] ?></td>
          <td style="text-align:right;color:#888"><?= $ar['total'] ?></td>
          <td>
            <div style="font-size:11.5px;font-weight:700;color:var(--primary)"><?= $rate ?>%</div>
            <div class="attend-bar"><div class="attend-fill" style="width:<?= $rate ?>%"></div></div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p class="text-muted" style="text-align:center;padding:30px 0">No attendance data yet.</p>
    <?php endif; ?>
  </div>
  <div class="chart-card">
    <div class="chart-title">Incoming Messages</div>
    <div class="chart-sub">Contact form submissions per month — last 6 months</div>
    <div class="chart-wrap"><canvas id="msgChart" height="220" role="img" aria-label="Line chart of contact form submissions per month, last 6 months"></canvas></div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
(function() {
  var pink      = '#C0396B';
  var pinkLight = 'rgba(192,57,107,0.15)';
  var gold      = '#D4882A';
  var green     = '#27ae60';
  var gray      = '#b2bec3';
  var blue      = '#2980b9';

  var defaults = {
    plugins: [], responsive: true, maintainAspectRatio: true,
    plugins: { legend: { display: false }, tooltip: { callbacks: {} } },
    scales: {}
  };

  function gridColor() { return 'rgba(0,0,0,0.06)'; }

  // ── Member sign-ups bar ───────────────────────────────────────────────────
  var mLabels = <?= json_encode(array_column($member_chart, 'label')) ?>;
  var mValues = <?= json_encode(array_column($member_chart, 'value')) ?>;
  new Chart(document.getElementById('memberChart'), {
    type: 'bar',
    data: {
      labels: mLabels,
      datasets: [{ label: 'Sign-ups', data: mValues, backgroundColor: pink, borderRadius: 6 }]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor() } },
        x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 30, font: { size: 10 } } }
      }
    }
  });

  // ── Member status doughnut ────────────────────────────────────────────────
  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
      labels: ['Approved', 'Pending', 'Rejected'],
      datasets: [{ data: [<?= $status_data['approved'] ?>, <?= $status_data['pending'] ?>, <?= $status_data['rejected'] ?>],
        backgroundColor: [green, gold, '#e74c3c'], borderWidth: 2 }]
    },
    options: {
      responsive: true, cutout: '68%',
      plugins: { legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 14 } } }
    }
  });

  // ── RSVPs by event horizontal bar ─────────────────────────────────────────
  var revLabels = <?= json_encode(array_column($rsvp_chart, 'title')) ?>;
  var revValues = <?= json_encode(array_column($rsvp_chart, 'n')) ?>;
  new Chart(document.getElementById('rsvpChart'), {
    type: 'bar',
    data: {
      labels: revLabels,
      datasets: [{ label: 'RSVPs', data: revValues, backgroundColor: 'rgba(212,136,42,0.85)', borderRadius: 5 }]
    },
    options: {
      indexAxis: 'y', responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor() } },
        y: { grid: { display: false }, ticks: { font: { size: 11 } } }
      }
    }
  });

  // ── Monthly RSVP trend line ───────────────────────────────────────────────
  var rtLabels = <?= json_encode(array_column($rsvp_trend, 'label')) ?>;
  var rtValues = <?= json_encode(array_column($rsvp_trend, 'value')) ?>;
  new Chart(document.getElementById('rsvpTrendChart'), {
    type: 'line',
    data: {
      labels: rtLabels,
      datasets: [{
        label: 'RSVPs', data: rtValues,
        borderColor: pink, backgroundColor: pinkLight, fill: true,
        tension: 0.4, pointBackgroundColor: pink, pointRadius: 4
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor() } },
        x: { grid: { display: false } }
      }
    }
  });

  // ── Dues doughnut ─────────────────────────────────────────────────────────
  new Chart(document.getElementById('duesChart'), {
    type: 'doughnut',
    data: {
      labels: ['Paid', 'Partial', 'Unpaid'],
      datasets: [{ data: [<?= $dues_data['paid']['count'] ?>, <?= $dues_data['partial']['count'] ?>, <?= $dues_data['unpaid']['count'] ?>],
        backgroundColor: [green, blue, '#e74c3c'], borderWidth: 2 }]
    },
    options: {
      responsive: true, cutout: '68%',
      plugins: { legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 14 } } }
    }
  });

  // ── Events by category bar ────────────────────────────────────────────────
  var catLabels = <?= json_encode(array_column($cat_rows, 'category')) ?>;
  var catValues = <?= json_encode(array_column($cat_rows, 'n')) ?>;
  new Chart(document.getElementById('catChart'), {
    type: 'bar',
    data: {
      labels: catLabels,
      datasets: [{ label: 'Events', data: catValues, backgroundColor: blue, borderRadius: 6 }]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor() } },
        x: { grid: { display: false } }
      }
    }
  });

  // ── Messages trend line ───────────────────────────────────────────────────
  var msgLabels = <?= json_encode(array_column($msg_trend, 'label')) ?>;
  var msgValues = <?= json_encode(array_column($msg_trend, 'value')) ?>;
  new Chart(document.getElementById('msgChart'), {
    type: 'line',
    data: {
      labels: msgLabels,
      datasets: [{
        label: 'Messages', data: msgValues,
        borderColor: blue, backgroundColor: 'rgba(41,128,185,0.12)', fill: true,
        tension: 0.4, pointBackgroundColor: blue, pointRadius: 4
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor() } },
        x: { grid: { display: false } }
      }
    }
  });
})();
</script>
