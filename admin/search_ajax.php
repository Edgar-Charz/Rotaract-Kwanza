<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['total' => 0, 'members' => [], 'events' => [], 'announcements' => [], 'messages' => []]);
    exit;
}

$like = '%' . $q . '%';
$lim  = 8;

$members = db_rows($conn,
    "SELECT id, first_name, last_name, email, status FROM members
     WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?
     LIMIT $lim",
    [$like, $like, $like, $like]
);

$events = db_rows($conn,
    "SELECT id, title, event_date, location, status FROM events
     WHERE title LIKE ? OR location LIKE ? OR description LIKE ?
     LIMIT $lim",
    [$like, $like, $like]
);

$posts = db_rows($conn,
    "SELECT id, title, category, is_published FROM announcements
     WHERE title LIKE ? OR content LIKE ?
     LIMIT $lim",
    [$like, $like]
);

$messages = db_rows($conn,
    "SELECT id, full_name, email, subject, status FROM contact_messages
     WHERE full_name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?
     LIMIT $lim",
    [$like, $like, $like, $like]
);

echo json_encode([
    'members'       => $members,
    'events'        => $events,
    'announcements' => $posts,
    'messages'      => $messages,
    'total'         => count($members) + count($events) + count($posts) + count($messages),
]);
