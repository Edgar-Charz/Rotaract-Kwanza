<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/ContactMessage.php';

require_role('editor');

$filter   = $_GET['status'] ?? '';
$messages = (new ContactMessage($conn))->getAll(
    ($filter && in_array($filter, ['unread', 'read', 'replied'])) ? $filter : ''
);

log_activity('export_messages', 'Exported messages list to CSV (' . count($messages) . ' records)');

$filename = 'messages_' . date('Y-m-d') . ($filter ? "_$filter" : '') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

function csv_safe($value): string {
    $value = (string) $value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }
    return $value;
}

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Name', 'Email', 'Subject', 'Message', 'Status', 'Admin Notes', 'Date']);

foreach ($messages as $msg) {
    fputcsv($out, [
        csv_safe($msg['full_name']),
        csv_safe($msg['email']),
        csv_safe($msg['subject'] ?? ''),
        csv_safe($msg['message']),
        $msg['status'],
        csv_safe($msg['admin_notes'] ?? ''),
        $msg['created_at'] ? date('d M Y H:i', strtotime($msg['created_at'])) : '',
    ]);
}

fclose($out);
exit;
