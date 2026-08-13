<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Member.php';

require_role('editor');

$page_title = 'Import Members';

$results = [];
$done    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  require_role('editor');

  if (isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
    // finfo reads the file's actual magic bytes rather than trusting the
    // client-supplied filename/extension — same approach as image uploads
    // in includes/upload.php. mime_content_type() is looser and can be
    // swayed by file content in ways finfo's MIME mode isn't.
    $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['csv']['tmp_name']);
    $valid = in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true);

    if (!$valid) {
      flash('error', 'Please upload a valid CSV file.');
      header('Location: ' . ADMIN_URL . '/import_members.php');
      exit;
    }

    $handle = fopen($_FILES['csv']['tmp_name'], 'r');
    if ($handle === false) {
      flash('error', 'Could not read the uploaded file.');
      header('Location: ' . ADMIN_URL . '/import_members.php');
      exit;
    }

    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
      fclose($handle);
      flash('error', 'CSV file is empty or malformed.');
      header('Location: ' . ADMIN_URL . '/import_members.php');
      exit;
    }

    // Normalise header keys
    $header = array_map(fn($h) => strtolower(trim($h)), $header);

    $col = fn(string $name) => array_search($name, $header);

    $row_num  = 1;
    $imported = 0;
    $skipped  = 0;
    $m        = new Member($conn);

    while (($row = fgetcsv($handle)) !== false) {
      $row_num++;
      if (count($row) < 2) {
        $skipped++;
        continue;
      }

      $get = function (string $key) use ($row, $col): string {
        $idx = $col($key);
        return $idx !== false ? trim($row[$idx] ?? '') : '';
      };

      $first  = $get('first_name') ?: $get('firstname') ?: $get('first');
      $last   = $get('last_name')  ?: $get('lastname')  ?: $get('last');
      $email  = $get('email');
      $phone  = $get('phone')      ?: $get('telephone') ?: $get('mobile');
      $occ    = $get('occupation') ?: $get('job')       ?: $get('profession');
      $year   = $get('year_of_study') ?: $get('year');
      $bday   = $get('birthday') ?: $get('date_of_birth') ?: $get('dob');
      $why    = $get('why_join')   ?: $get('why')       ?: '';
      $status = $get('status');
      if (!in_array($status, ['pending', 'approved', 'rejected'])) $status = 'pending';

      if (!$first || !$last || !$email) {
        $results[] = ['row' => $row_num, 'status' => 'skip', 'msg' => "Row $row_num skipped — missing first_name, last_name, or email."];
        $skipped++;
        continue;
      }

      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $results[] = ['row' => $row_num, 'status' => 'skip', 'msg' => "Row $row_num skipped — invalid email: $email"];
        $skipped++;
        continue;
      }

      $bday_date = $bday !== '' ? DateTime::createFromFormat('Y-m-d', $bday) : false;
      $bday = ($bday_date && $bday_date->format('Y-m-d') === $bday) ? $bday : null;

      try {
        $m->create($first, $last, $email, $phone, $occ, $why, $status, '', '', '', '', '', $year, $bday);
        $results[] = ['row' => $row_num, 'status' => 'ok', 'msg' => "Row $row_num imported: $first $last <$email>"];
        $imported++;
      } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
          $results[] = ['row' => $row_num, 'status' => 'dup', 'msg' => "Row $row_num skipped — duplicate email: $email"];
          $skipped++;
        } else {
          $results[] = ['row' => $row_num, 'status' => 'err', 'msg' => "Row $row_num error: " . $e->getMessage()];
          $skipped++;
        }
      }
    }

    fclose($handle);
    log_activity('import_members', "CSV import: $imported imported, $skipped skipped");
    $done = true;
  } else {
    flash('error', 'No file uploaded or upload error.');
    header('Location: ' . ADMIN_URL . '/import_members.php');
    exit;
  }
}

include __DIR__ . '/includes/header.php';
?>

<div class="card import-card">
  <div class="card-header">
    <span class="card-title">Import Members from CSV</span>
    <a href="members.php" class="btn btn-secondary btn-sm">← Back to Members</a>
  </div>
  <div class="card-body">

    <?php if (!$done): ?>
      <div class="import-info-box">
        <div class="import-info-title">CSV Format Requirements</div>
        <p>Your CSV must include a header row with at least these columns:</p>
        <code class="import-info-code">first_name, last_name, email</code>
        <p class="mt-1">Optional columns: <code>phone, occupation, year_of_study, birthday (YYYY-MM-DD), why_join, status</code> (pending/approved/rejected)</p>
        <p class="mt-1">Rows with duplicate emails are skipped automatically.</p>
      </div>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group mb-2">
          <label>Select CSV File</label>
          <input type="file" name="csv" accept=".csv,text/csv" required class="file-input-pad">
        </div>
        <button type="submit" class="btn btn-primary">Import Members</button>
      </form>

      <div class="import-sample-wrap">
        <div class="import-sample-title">Sample CSV</div>
        <pre class="import-sample-pre">first_name,last_name,email,phone,occupation,year_of_study,birthday,why_join,status
Maria,Santos,maria@example.com,+244900000001,Computer Science,3rd Year,2002-05-14,To serve the community,approved
João,Silva,joao@example.com,+244900000002,Medicine,2nd Year,2003-11-02,,pending</pre>
      </div>

    <?php else: ?>

      <?php
      $ok_count  = count(array_filter($results, fn($r) => $r['status'] === 'ok'));
      $dup_count = count(array_filter($results, fn($r) => $r['status'] === 'dup'));
      $err_count = count(array_filter($results, fn($r) => in_array($r['status'], ['skip', 'err'])));
      ?>

      <div class="import-results-grid">
        <div class="import-stat-box ok">
          <div class="import-stat-value"><?= $ok_count ?></div>
          <div class="import-stat-label">Imported</div>
        </div>
        <div class="import-stat-box dup">
          <div class="import-stat-value"><?= $dup_count ?></div>
          <div class="import-stat-label">Duplicates</div>
        </div>
        <div class="import-stat-box err">
          <div class="import-stat-value"><?= $err_count ?></div>
          <div class="import-stat-label">Errors/Skipped</div>
        </div>
      </div>

      <div class="import-results-list">
        <?php foreach ($results as $r):
          $bg = $r['status'] === 'ok' ? '#f0fff4' : ($r['status'] === 'dup' ? '#fffbea' : '#fff5f5');
          $icon = $r['status'] === 'ok' ? '✓' : ($r['status'] === 'dup' ? '~' : '✗');
        ?>
          <div class="import-result-row" style="background:<?= $bg ?>">
            <?= $icon ?> <?= h($r['msg']) ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="import-actions">
        <a href="members.php" class="btn btn-primary">View Members</a>
        <a href="import_members.php" class="btn btn-secondary">Import Another File</a>
      </div>

    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>