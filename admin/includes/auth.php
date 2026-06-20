<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: /Rotaract_Kwanza/admin/login.php');
    exit;
}
