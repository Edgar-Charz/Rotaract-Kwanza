<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/ProjectSignup.php';
require_once dirname(__DIR__) . '/classes/Project.php';

require_role('editor');

$project_id = (int)($_GET['project'] ?? 0);
$signup_obj = new ProjectSignup($conn);
$signups    = $project_id ? $signup_obj->getByProject($project_id) : $signup_obj->getAll();

$project_title = $project_id ? (new Project($conn))->findById($project_id)['title'] ?? '' : '';

log_activity('export_project_signups', 'Exported project sign-ups to CSV (' . count($signups) . ' records)' . ($project_title ? " — $project_title" : ''));

$filename = 'project_signups_' . date('Y-m-d') . ($project_id ? '_project' . $project_id : '') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Project', 'Name', 'Email', 'Phone', 'Member', 'Notes', 'Contacted', 'Signed Up']);

foreach ($signups as $s) {
    fputcsv($out, [
        csv_safe($s['project_title'] ?? $project_title),
        csv_safe($s['name']),
        csv_safe($s['email']),
        csv_safe($s['phone'] ?? ''),
        !empty($s['member_id']) ? 'Yes' : 'No',
        csv_safe($s['notes'] ?? ''),
        ($s['contacted'] ?? 0) ? 'Yes' : 'No',
        $s['created_at'] ? date('d M Y H:i', strtotime($s['created_at'])) : '',
    ]);
}

fclose($out);
exit;
