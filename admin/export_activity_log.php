<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/ActivityLog.php';

require_role('editor');

$alog = new ActivityLog($conn);

// ?days=N exports exactly the rows a "Clear Old Logs" purge for that window
// would delete, so the admin always has a copy before running the purge.
$days     = isset($_GET['days']) ? (int) $_GET['days'] : null;
$admin_f  = trim($_GET['admin'] ?? '');
$action_f = trim($_GET['action_filter'] ?? '');

if ($days !== null) {
    $logs = $alog->getOlderThanDays($days);
} else {
    $logs = $alog->getFiltered($admin_f, $action_f);
}

log_activity('export_activity_log', 'Exported activity log to CSV (' . count($logs) . ' records)' . ($days !== null ? " — entries older than $days day(s)" : ''));

$filename = 'activity_log_' . date('Y-m-d') . ($days !== null ? "_older_than_{$days}d" : '') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['ID', 'Admin', 'Action', 'Description', 'IP Address', 'Date']);

foreach ($logs as $log) {
    fputcsv($out, [
        $log['id'],
        csv_safe($log['admin_username'] ?? ''),
        csv_safe($log['action']),
        csv_safe($log['description'] ?? ''),
        csv_safe($log['ip_address'] ?? ''),
        $log['created_at'] ? date('d M Y H:i:s', strtotime($log['created_at'])) : '',
    ]);
}

fclose($out);
exit;
