<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Member.php';

$filter  = $_GET['status'] ?? '';
$members = (new Member($conn))->getAll(
    ($filter && in_array($filter, ['pending','approved','rejected'])) ? $filter : ''
);

log_activity('export_members', 'Exported members list to CSV (' . count($members) . ' records)');

$filename = 'members_' . date('Y-m-d') . ($filter ? "_$filter" : '') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

fputcsv($out, ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Occupation', 'Why Join', 'Status', 'Notes', 'Applied Date']);

foreach ($members as $m) {
    fputcsv($out, [
        $m['id'],
        $m['first_name'],
        $m['last_name'],
        $m['email'],
        $m['phone'] ?? '',
        $m['occupation'] ?? '',
        $m['why_join'] ?? '',
        $m['status'],
        $m['notes'] ?? '',
        date('d M Y', strtotime($m['created_at'])),
    ]);
}
fclose($out);
exit;
