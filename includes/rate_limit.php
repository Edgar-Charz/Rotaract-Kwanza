<?php

/**
 * DB-backed rate limiter for public forms (per IP + action), using a
 * fixed window stored in the `rate_limits` table. A single UPDATE-or-INSERT
 * statement keeps the check atomic under concurrent requests, unlike the
 * previous sys_get_temp_dir()-based file approach, which could race between
 * requests and whose state could be silently wiped by OS temp-file cleanup.
 */
function rate_limit_allow(mysqli $conn, string $action, int $max_attempts = 5, int $window_secs = 900): bool
{
    $ip  = substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
    $key = hash('sha256', $action . '|' . $ip);

    $stmt = $conn->prepare(
        'INSERT INTO rate_limits (rl_key, attempt_count, window_start)
         VALUES (?, 1, NOW())
         ON DUPLICATE KEY UPDATE
           attempt_count = IF(window_start < NOW() - INTERVAL ? SECOND, 1, attempt_count + 1),
           window_start  = IF(window_start < NOW() - INTERVAL ? SECOND, NOW(), window_start)'
    );
    $stmt->bind_param('sii', $key, $window_secs, $window_secs);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('SELECT attempt_count FROM rate_limits WHERE rl_key = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return (int) $count <= $max_attempts;
}

function rate_limit_message(int $window_secs = 900): string
{
    $mins = max(1, (int) ceil($window_secs / 60));
    return "Too many submissions. Please wait {$mins} minute(s) and try again.";
}
