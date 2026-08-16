<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);

$id = (int)($_GET['id'] ?? 0);

if ($id === (int)$_SESSION['user_id']) {
    flash('danger', 'You cannot delete your own account.');
    redirect('users/list.php');
}

$stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    $del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Delete User', "Deleted user: " . $user['full_name']);
        flash('success', 'User deleted.');
    } else {
        flash('danger', 'Could not delete — this user has existing transaction records. Consider deactivating instead.');
    }
} else {
    flash('danger', 'User not found.');
}
redirect('users/list.php');
