<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Member.php';

require_role('editor');

$filter_raw = $_GET['status'] ?? '';
$filter     = in_array($filter_raw, ['pending', 'approved', 'rejected'], true) ? $filter_raw : '';
$members    = (new Member($conn))->getAll($filter);

log_activity('export_members', 'Exported members list to CSV (' . count($members) . ' records)');

$filename = 'members_' . date('Y-m-d') . ($filter ? "_$filter" : '') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

fputcsv($out, ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Occupation', 'Year of Study', 'Birthday', 'Why Join', 'Status', 'Notes', 'Applied Date']);

foreach ($members as $m) {
    fputcsv($out, [
        $m['id'],
        csv_safe($m['first_name']),
        csv_safe($m['last_name']),
        csv_safe($m['email']),
        csv_safe($m['phone'] ?? ''),
        csv_safe($m['occupation'] ?? ''),
        csv_safe($m['year_of_study'] ?? ''),
        $m['birthday'] ? date('d M Y', strtotime($m['birthday'])) : '',
        csv_safe($m['why_join'] ?? ''),
        $m['status'],
        csv_safe($m['notes'] ?? ''),
        date('d M Y', strtotime($m['created_at'])),
    ]);
}
fclose($out);
exit;
