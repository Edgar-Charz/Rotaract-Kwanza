<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role('editor');

// Mirror reports.php's date-range handling exactly so the export reflects
// whatever range the admin was looking at on screen, instead of always
// dumping all-time data regardless of the dashboard's from/to filter.
function valid_ymd(?string $s): bool
{
    return $s !== null && $s !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && strtotime($s) !== false;
}
$default_from = date('Y-m-01', strtotime('-11 months'));
$default_to   = date('Y-m-d');
$from = valid_ymd($_GET['from'] ?? null) ? $_GET['from'] : $default_from;
$to   = valid_ymd($_GET['to']   ?? null) ? $_GET['to']   : $default_to;
if (strtotime($from) > strtotime($to)) {
    [$from, $to] = [$to, $from];
}
if ((strtotime($to) - strtotime($from)) > 36 * 31 * 86400) {
    $from = date('Y-m-d', strtotime($to . ' -35 months'));
}

$member_trend = monthly_series_range($conn, 'members', $from, $to);
$rsvp_trend   = monthly_series_range($conn, 'event_rsvps', $from, $to);
$dues_trend   = monthly_series_range($conn, 'member_dues', $from, $to, 'payment_date', 'amount_paid');
$msg_trend    = monthly_series_range($conn, 'contact_messages', $from, $to);

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

$rsvp_by_event = db_rows(
    $conn,
    "SELECT e.title, COUNT(r.id) AS n
     FROM event_rsvps r JOIN events e ON e.id = r.event_id
     GROUP BY r.event_id ORDER BY n DESC"
);

$attend_rows = db_rows(
    $conn,
    "SELECT e.title,
            COUNT(r.id)                                AS total,
            SUM(COALESCE(r.attended,0))                AS attended
     FROM event_rsvps r JOIN events e ON e.id = r.event_id
     GROUP BY r.event_id HAVING total > 0 ORDER BY e.event_date DESC"
);

log_activity('export_report', "Exported reports & analytics summary to CSV ($from to $to)");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Report Range', "$from to $to"]);
fputcsv($out, []);

fputcsv($out, ['Member Sign-ups by Month (selected range)']);
fputcsv($out, ['Month', 'Sign-ups']);
foreach ($member_trend as $r) {
    fputcsv($out, [$r['label'], $r['value']]);
}
fputcsv($out, []);

fputcsv($out, ['RSVPs by Month (selected range)']);
fputcsv($out, ['Month', 'RSVPs']);
foreach ($rsvp_trend as $r) {
    fputcsv($out, [$r['label'], $r['value']]);
}
fputcsv($out, []);

fputcsv($out, ['Dues Collected by Month (selected range)']);
fputcsv($out, ['Month', 'Amount Collected']);
foreach ($dues_trend as $r) {
    fputcsv($out, [$r['label'], $r['value']]);
}
fputcsv($out, []);

fputcsv($out, ['Messages by Month (selected range)']);
fputcsv($out, ['Month', 'Messages']);
foreach ($msg_trend as $r) {
    fputcsv($out, [$r['label'], $r['value']]);
}
fputcsv($out, []);

fputcsv($out, ['Member Status Breakdown (all-time)']);
fputcsv($out, ['Status', 'Count']);
foreach ($status_data as $status => $n) {
    fputcsv($out, [$status, $n]);
}
fputcsv($out, []);

fputcsv($out, ['Dues Breakdown (all-time)']);
fputcsv($out, ['Status', 'Count', 'Total Paid']);
foreach ($dues_data as $status => $d) {
    fputcsv($out, [$status, $d['count'], $d['total']]);
}
fputcsv($out, []);

fputcsv($out, ['RSVPs by Event (all-time)']);
fputcsv($out, ['Event', 'RSVPs']);
foreach ($rsvp_by_event as $r) {
    fputcsv($out, [csv_safe($r['title']), $r['n']]);
}
fputcsv($out, []);

fputcsv($out, ['Attendance by Event (all-time)']);
fputcsv($out, ['Event', 'Attended', 'Registered', 'Rate %']);
foreach ($attend_rows as $a) {
    $rate = $a['total'] > 0 ? round($a['attended'] / $a['total'] * 100) : 0;
    fputcsv($out, [csv_safe($a['title']), $a['attended'], $a['total'], $rate]);
}

fclose($out);
exit;
