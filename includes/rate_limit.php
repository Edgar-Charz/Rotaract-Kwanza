<?php
/**
 * Simple file-based rate limiter for public forms (per IP + action).
 * Returns true if the request is allowed, false if rate limited.
 */
function rate_limit_allow(string $action, int $max_attempts = 5, int $window_secs = 900): bool
{
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
    $key = hash('sha256', $action . '|' . $ip);
    $dir = sys_get_temp_dir() . '/rotaract_kwanza_rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $file = $dir . '/' . $key;
    $now  = time();
    $data = ['count' => 0, 'start' => $now];

    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
                $data = $decoded;
            }
        }
    }

    if ($now - (int) $data['start'] >= $window_secs) {
        $data = ['count' => 0, 'start' => $now];
    }

    if ((int) $data['count'] >= $max_attempts) {
        return false;
    }

    $data['count'] = (int) $data['count'] + 1;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

function rate_limit_message(int $window_secs = 900): string
{
    $mins = max(1, (int) ceil($window_secs / 60));
    return "Too many submissions. Please wait {$mins} minute(s) and try again.";
}
