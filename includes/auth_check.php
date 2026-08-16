<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

function requireRole($allowedRoles) {
    if (!in_array((int)$_SESSION['role_id'], $allowedRoles)) {
        header("Location: " . BASE_URL . "dashboard.php?error=access_denied");
        exit();
    }
}
