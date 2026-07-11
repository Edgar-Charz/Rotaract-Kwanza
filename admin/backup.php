<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role('super_admin');

$page_title = 'DB Backup';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') !== 'download') {
        header('Location: ' . ADMIN_URL . '/backup.php');
        exit;
    }

    log_activity('db_backup', 'Downloaded full database backup');
    $db = $conn;

    $filename = 'rotaract_kwanza_backup_' . date('Y-m-d_His') . '.sql';

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $tables = [];
    $res = $db->query("SHOW TABLES");
    while ($row = $res->fetch_row()) $tables[] = $row[0];

    echo "-- Rotaract Kwanza Database Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: rotaract_kwanza\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $res = $db->query("SHOW CREATE TABLE `$table`");
        $row = $res->fetch_row();
        echo "-- Table: $table\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $row[1] . ";\n\n";

        $res  = $db->query("SELECT * FROM `$table`");
        $cols = $res->field_count;

        if ($res->num_rows === 0) {
            echo "-- (no rows)\n\n";
            continue;
        }

        $chunk = [];
        while ($row = $res->fetch_row()) {
            $vals = [];
            for ($i = 0; $i < $cols; $i++) {
                $vals[] = $row[$i] === null ? 'NULL' : "'" . $db->real_escape_string($row[$i]) . "'";
            }
            $chunk[] = '(' . implode(',', $vals) . ')';

            if (count($chunk) >= 100) {
                echo "INSERT INTO `$table` VALUES\n" . implode(",\n", $chunk) . ";\n";
                $chunk = [];
            }
        }
        if ($chunk) {
            echo "INSERT INTO `$table` VALUES\n" . implode(",\n", $chunk) . ";\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

// Table size overview
$db = $conn;
$table_stats = [];
$res = $db->query(
    "SELECT table_name, table_rows, ROUND((data_length+index_length)/1024,1) AS size_kb
     FROM information_schema.TABLES
     WHERE table_schema = 'rotaract_kwanza'
     ORDER BY table_name"
);
while ($row = $res->fetch_assoc()) $table_stats[] = $row;

include __DIR__ . '/includes/header.php';
?>

<div class="split-layout" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

  <div class="card">
    <div class="card-header"><span class="card-title">Download SQL Backup</span></div>
    <div class="card-body">
      <p class="text-muted" style="font-size:13.5px;margin-bottom:20px;line-height:1.7">
        Generates a complete <code>.sql</code> dump of all tables and data. The file can be imported directly into phpMyAdmin or MySQL command line to restore the database.
      </p>
      <div style="background:#f0f9f4;border:1px solid #a3dfc0;border-radius:8px;padding:14px;margin-bottom:20px">
        <div style="font-size:13px;color:#1a5c35;font-weight:600;margin-bottom:4px">&#10003; Includes:</div>
        <ul style="font-size:13px;color:#2d6a4f;padding-left:18px;line-height:1.8;margin:0">
          <li>All table structures (CREATE TABLE)</li>
          <li>All data rows (INSERT statements)</li>
          <li>Foreign key constraints</li>
        </ul>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="download">
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;gap:10px;padding:11px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
          Download rotaract_kwanza.sql
        </button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Database Overview</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Table</th><th>Rows (approx.)</th><th>Size (KB)</th></tr></thead>
        <tbody>
          <?php foreach ($table_stats as $t): ?>
          <tr>
            <td class="fw-bold"><code><?= h($t['table_name']) ?></code></td>
            <td><?= number_format($t['table_rows']) ?></td>
            <td class="text-muted"><?= $t['size_kb'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
