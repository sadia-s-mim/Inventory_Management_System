<?php
session_start();
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    logActivity($conn, $_SESSION['user_id'], 'Logout', $_SESSION['full_name'] . ' logged out');
}
$_SESSION = [];
session_destroy();
header("Location: " . BASE_URL . "login.php");
exit();
