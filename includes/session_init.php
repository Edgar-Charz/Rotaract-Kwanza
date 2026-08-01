<?php
require_once __DIR__ . '/error_handler.php';

// Baseline security headers for every request, public and admin alike —
// previously only admin/includes/header.php set these, leaving the public
// site with none (no clickjacking/MIME-sniffing/referrer-leak protection).
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
