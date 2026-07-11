<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Member.php';

require_role('editor');

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

// Prefix values that a spreadsheet app would interpret as a formula
// (member-supplied fields originate from the public join form).
function csv_safe($value): string {
    $value = (string)$value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }
    return $value;
}

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

fputcsv($out, ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Occupation', 'Why Join', 'Status', 'Notes', 'Applied Date']);

foreach ($members as $m) {
    fputcsv($out, [
        $m['id'],
        csv_safe($m['first_name']),
        csv_safe($m['last_name']),
        csv_safe($m['email']),
        csv_safe($m['phone'] ?? ''),
        csv_safe($m['occupation'] ?? ''),
        csv_safe($m['why_join'] ?? ''),
        $m['status'],
        csv_safe($m['notes'] ?? ''),
        date('d M Y', strtotime($m['created_at'])),
    ]);
}
fclose($out);
exit;
