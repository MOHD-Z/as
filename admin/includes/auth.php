<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

function admin_logged_in() {
    return !empty($_SESSION['admin_id']);
}

function require_admin() {
    if (!admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_flash($msg = null) {
    if ($msg !== null) {
        $_SESSION['admin_flash'] = $msg;
        return;
    }
    $m = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    return $m;
}
