<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role('super_admin');

$page_title = 'DB Backup';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $backup_action = $_POST['action'] ?? '';
  if ($backup_action !== 'download' && $backup_action !== 'download_full') {
    header('Location: ' . ADMIN_URL . '/backup.php');
    exit;
  }

  // Large tables (activity_log, gallery, event_photos, ...) can exceed PHP's
  // memory/time limits with mysqli's default buffered queries, which load an
  // entire result set into memory before the loop even starts. Unbuffered
  // mode streams row-by-row instead, and the time limit is lifted since a
  // full dump can legitimately take longer than the default 30s.
  set_time_limit(0);
  mysqli_report(MYSQLI_REPORT_OFF);
  $db = $conn;

  $sql_filename = 'rotaract_kwanza_backup_' . date('Y-m-d_His') . '.sql';

  $dumpSql = function () use ($db): string {
    $tables = [];
    $res = $db->query("SHOW TABLES");
    while ($row = $res->fetch_row()) $tables[] = $row[0];

    $out = "-- Rotaract Kwanza Database Backup\n";
    $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $out .= "-- Database: rotaract_kwanza\n\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
      $res = $db->query("SHOW CREATE TABLE `$table`");
      $row = $res->fetch_row();
      $out .= "-- Table: $table\n";
      $out .= "DROP TABLE IF EXISTS `$table`;\n";
      $out .= $row[1] . ";\n\n";

      // MYSQLI_USE_RESULT streams rows from the server one at a time
      // instead of buffering the whole table in PHP memory first.
      $db->real_query("SELECT * FROM `$table`");
      $res  = $db->use_result();
      $cols = $res->field_count;

      $rowCount = 0;
      $chunk = [];
      while ($row = $res->fetch_row()) {
        $rowCount++;
        $vals = [];
        for ($i = 0; $i < $cols; $i++) {
          $vals[] = $row[$i] === null ? 'NULL' : "'" . $db->real_escape_string($row[$i]) . "'";
        }
        $chunk[] = '(' . implode(',', $vals) . ')';

        if (count($chunk) >= 100) {
          $out .= "INSERT INTO `$table` VALUES\n" . implode(",\n", $chunk) . ";\n";
          $chunk = [];
        }
      }
      $res->free();
      if ($chunk) {
        $out .= "INSERT INTO `$table` VALUES\n" . implode(",\n", $chunk) . ";\n";
      }
      if ($rowCount === 0) {
        $out .= "-- (no rows)\n";
      }
      $out .= "\n";
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
  };

  if ($backup_action === 'download_full') {
    // Bundle the SQL dump with the uploads directory so restoring doesn't
    // leave every photo/image reference pointing at a file that no longer
    // exists — a DB-only backup silently breaks every image on restore.
    $zip_filename = 'rotaract_kwanza_backup_' . date('Y-m-d_His') . '.zip';
    $tmp_zip = tempnam(sys_get_temp_dir(), 'rkbackup_');

    $zip = new ZipArchive();
    $openResult = $zip->open($tmp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($openResult !== true) {
      @unlink($tmp_zip);
      flash('error', 'Could not create the backup archive. Please try again.');
      header('Location: ' . ADMIN_URL . '/backup.php');
      exit;
    }
    $zip->addFromString($sql_filename, $dumpSql());

    $uploadsDir = dirname(__DIR__) . '/admin/uploads';
    if (is_dir($uploadsDir)) {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS)
      );
      foreach ($iterator as $file) {
        if ($file->isFile()) {
          $localPath = 'uploads/' . substr($file->getPathname(), strlen($uploadsDir) + 1);
          $zip->addFile($file->getPathname(), str_replace('\\', '/', $localPath));
        }
      }
    }
    $closed = $zip->close();
    if (!$closed || !is_file($tmp_zip) || filesize($tmp_zip) === 0) {
      @unlink($tmp_zip);
      flash('error', 'The backup archive could not be finalized. Please try again.');
      header('Location: ' . ADMIN_URL . '/backup.php');
      exit;
    }

    // Log after the archive is actually built, not before — if ZipArchive
    // or the dump itself failed partway, the log shouldn't claim success.
    log_activity('db_backup', 'Downloaded full backup (database + uploads, .zip)');

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . filesize($tmp_zip));
    readfile($tmp_zip);
    unlink($tmp_zip);
    exit;
  }

  $sql = $dumpSql();

  // Logged after the dump string is fully built (not before), so a mid-dump
  // failure (memory/time exhaustion on a very large table) doesn't get
  // recorded as a successful backup when nothing was actually downloaded.
  log_activity('db_backup', 'Downloaded full database backup (.sql)');

  header('Content-Type: application/octet-stream');
  header('Content-Disposition: attachment; filename="' . $sql_filename . '"');
  header('Pragma: no-cache');
  header('Expires: 0');
  header('Content-Length: ' . strlen($sql));
  echo $sql;
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

<div class="split-layout backup-grid">

  <div class="card">
    <div class="card-header"><span class="card-title">Download SQL Backup</span></div>
    <div class="card-body">
      <p class="text-muted backup-desc">
        Generates a complete <code>.sql</code> dump of all tables and data. The file can be imported directly into phpMyAdmin or MySQL command line to restore the database.
      </p>
      <div class="backup-includes-box">
        <div class="backup-includes-title">&#10003; Includes:</div>
        <ul class="backup-includes-list">
          <li>All table structures (CREATE TABLE)</li>
          <li>All data rows (INSERT statements)</li>
          <li>Foreign key constraints</li>
        </ul>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="download">
        <button type="submit" class="btn btn-primary backup-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="icon-18">
            <polyline points="8 17 12 21 16 17" />
            <line x1="12" y1="12" x2="12" y2="21" />
            <path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29" />
          </svg>
          Download rotaract_kwanza.sql
        </button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">Database Overview</span></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Table</th>
            <th>Rows (approx.)</th>
            <th>Size (KB)</th>
          </tr>
        </thead>
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