<?php
require_once 'config/constants.php';
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "dashboard.php");
} else {
    header("Location: " . BASE_URL . "login.php");
}
exit();
