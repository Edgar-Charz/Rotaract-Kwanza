<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['total' => 0, 'sources' => new stdClass()]);
    exit;
}

echo json_encode(admin_search($conn, $q, 6));
