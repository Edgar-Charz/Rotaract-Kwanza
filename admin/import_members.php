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
        $mime  = mime_content_type($_FILES['csv']['tmp_name']);
        $valid = in_array($mime, ['text/csv','text/plain','application/csv','application/vnd.ms-excel']);

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
            if (count($row) < 2) { $skipped++; continue; }

            $get = function(string $key) use ($row, $col): string {
                $idx = $col($key);
                return $idx !== false ? trim($row[$idx] ?? '') : '';
            };

            $first  = $get('first_name') ?: $get('firstname') ?: $get('first');
            $last   = $get('last_name')  ?: $get('lastname')  ?: $get('last');
            $email  = $get('email');
            $phone  = $get('phone')      ?: $get('telephone') ?: $get('mobile');
            $occ    = $get('occupation') ?: $get('job')       ?: $get('profession');
            $why    = $get('why_join')   ?: $get('why')       ?: '';
            $status = $get('status');
            if (!in_array($status, ['pending','approved','rejected'])) $status = 'pending';

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

            try {
                $m->create($first, $last, $email, $phone, $occ, $why, $status);
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

<div class="card" style="max-width:700px">
  <div class="card-header">
    <span class="card-title">Import Members from CSV</span>
    <a href="members.php" class="btn btn-secondary btn-sm">← Back to Members</a>
  </div>
  <div class="card-body">

    <?php if (!$done): ?>
    <div style="background:#f0f6ff;border:1.5px solid #cce0ff;border-radius:10px;padding:16px 20px;margin-bottom:20px;font-size:13px">
      <div style="font-weight:700;margin-bottom:6px">CSV Format Requirements</div>
      <p>Your CSV must include a header row with at least these columns:</p>
      <code style="background:#fff;padding:4px 8px;border-radius:4px;display:inline-block;margin-top:4px">first_name, last_name, email</code>
      <p style="margin-top:8px">Optional columns: <code>phone, occupation, why_join, status</code> (pending/approved/rejected)</p>
      <p style="margin-top:8px">Rows with duplicate emails are skipped automatically.</p>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-group mb-2">
        <label>Select CSV File</label>
        <input type="file" name="csv" accept=".csv,text/csv" required style="padding:8px">
      </div>
      <button type="submit" class="btn btn-primary">Import Members</button>
    </form>

    <div style="margin-top:20px">
      <div style="font-size:12px;color:var(--text-muted);font-weight:600;margin-bottom:6px">Sample CSV</div>
      <pre style="background:#f5f5f7;padding:10px;border-radius:6px;font-size:12px;overflow-x:auto">first_name,last_name,email,phone,occupation,why_join,status
Maria,Santos,maria@example.com,+244900000001,Engineer,To serve the community,approved
João,Silva,joao@example.com,+244900000002,Teacher,,pending</pre>
    </div>

    <?php else: ?>

    <?php
      $ok_count  = count(array_filter($results, fn($r) => $r['status'] === 'ok'));
      $dup_count = count(array_filter($results, fn($r) => $r['status'] === 'dup'));
      $err_count = count(array_filter($results, fn($r) => in_array($r['status'], ['skip','err'])));
    ?>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px">
      <div style="background:#d1f2e0;border-radius:8px;padding:12px;text-align:center">
        <div style="font-size:24px;font-weight:800;color:#27ae60"><?= $ok_count ?></div>
        <div style="font-size:12px;color:#27ae60">Imported</div>
      </div>
      <div style="background:#fff3cd;border-radius:8px;padding:12px;text-align:center">
        <div style="font-size:24px;font-weight:800;color:#856404"><?= $dup_count ?></div>
        <div style="font-size:12px;color:#856404">Duplicates</div>
      </div>
      <div style="background:#fde8e8;border-radius:8px;padding:12px;text-align:center">
        <div style="font-size:24px;font-weight:800;color:#c0392b"><?= $err_count ?></div>
        <div style="font-size:12px;color:#c0392b">Errors/Skipped</div>
      </div>
    </div>

    <div style="max-height:300px;overflow-y:auto;border:1px solid var(--border);border-radius:8px">
      <?php foreach ($results as $r):
        $bg = $r['status'] === 'ok' ? '#f0fff4' : ($r['status'] === 'dup' ? '#fffbea' : '#fff5f5');
        $icon = $r['status'] === 'ok' ? '✓' : ($r['status'] === 'dup' ? '~' : '✗');
      ?>
      <div style="padding:8px 14px;border-bottom:1px solid var(--border);font-size:12.5px;background:<?= $bg ?>">
        <?= $icon ?> <?= h($r['msg']) ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:16px;display:flex;gap:10px">
      <a href="members.php" class="btn btn-primary">View Members</a>
      <a href="import_members.php" class="btn btn-secondary">Import Another File</a>
    </div>

    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
