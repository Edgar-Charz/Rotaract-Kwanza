<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role('editor');

$status_rows = db_rows($conn, "SELECT status, COUNT(*) AS n FROM members GROUP BY status");
$status_data = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($status_rows as $r) {
    if (isset($status_data[$r['status']])) $status_data[$r['status']] = (int) $r['n'];
}

$dues_rows = db_rows($conn, "SELECT status, COUNT(*) AS n, COALESCE(SUM(amount_paid),0) AS total FROM member_dues GROUP BY status");
$dues_data = ['paid' => ['count' => 0, 'total' => 0], 'partial' => ['count' => 0, 'total' => 0], 'unpaid' => ['count' => 0, 'total' => 0]];
foreach ($dues_rows as $r) {
    if (isset($dues_data[$r['status']])) {
        $dues_data[$r['status']] = ['count' => (int) $r['n'], 'total' => (float) $r['total']];
    }
}

$rsvp_by_event = db_rows($conn,
    "SELECT e.title, COUNT(r.id) AS n
     FROM event_rsvps r JOIN events e ON e.id = r.event_id
     GROUP BY r.event_id ORDER BY n DESC"
);

$attend_rows = db_rows($conn,
    "SELECT e.title,
            COUNT(r.id)                                AS total,
            SUM(COALESCE(r.attended,0))                AS attended
     FROM event_rsvps r JOIN events e ON e.id = r.event_id
     GROUP BY r.event_id HAVING total > 0 ORDER BY e.event_date DESC"
);

log_activity('export_report', 'Exported reports & analytics summary to CSV');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
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

fputcsv($out, ['Member Status Breakdown']);
fputcsv($out, ['Status', 'Count']);
foreach ($status_data as $status => $n) {
    fputcsv($out, [$status, $n]);
}
fputcsv($out, []);

fputcsv($out, ['Dues Breakdown']);
fputcsv($out, ['Status', 'Count', 'Total Paid']);
foreach ($dues_data as $status => $d) {
    fputcsv($out, [$status, $d['count'], $d['total']]);
}
fputcsv($out, []);

fputcsv($out, ['RSVPs by Event']);
fputcsv($out, ['Event', 'RSVPs']);
foreach ($rsvp_by_event as $r) {
    fputcsv($out, [csv_safe($r['title']), $r['n']]);
}
fputcsv($out, []);

fputcsv($out, ['Attendance by Event']);
fputcsv($out, ['Event', 'Attended', 'Registered', 'Rate %']);
foreach ($attend_rows as $a) {
    $rate = $a['total'] > 0 ? round($a['attended'] / $a['total'] * 100) : 0;
    fputcsv($out, [csv_safe($a['title']), $a['attended'], $a['total'], $rate]);
}

fclose($out);
exit;
