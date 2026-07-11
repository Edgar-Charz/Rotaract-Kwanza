<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/EventRSVP.php';
require_once dirname(__DIR__) . '/classes/Event.php';

require_role('editor');

$event_id = (int)($_GET['event'] ?? 0);
$rsvp_obj = new EventRSVP($conn);
$rsvps    = $event_id ? $rsvp_obj->getByEvent($event_id) : $rsvp_obj->getAll();

$event_title = $event_id ? (new Event($conn))->getTitleById($event_id) : '';

log_activity('export_rsvps', 'Exported RSVPs to CSV (' . count($rsvps) . ' records)' . ($event_title ? " — $event_title" : ''));

$filename = 'rsvps_' . date('Y-m-d') . ($event_id ? '_event' . $event_id : '') . '.csv';

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

fputcsv($out, ['Event', 'Name', 'Email', 'Phone', 'Guests', 'Notes', 'Attended', 'Registered']);

foreach ($rsvps as $r) {
    fputcsv($out, [
        csv_safe($r['event_title'] ?? $event_title),
        csv_safe($r['name']),
        csv_safe($r['email']),
        csv_safe($r['phone'] ?? ''),
        $r['guests'],
        csv_safe($r['notes'] ?? ''),
        ($r['attended'] ?? 0) ? 'Yes' : 'No',
        $r['created_at'] ? date('d M Y H:i', strtotime($r['created_at'])) : '',
    ]);
}

fclose($out);
exit;
