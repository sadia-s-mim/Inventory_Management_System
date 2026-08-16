<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();

if ($branch) {
    $del = $conn->prepare("DELETE FROM branches WHERE branch_id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Delete Branch', "Deleted branch: " . $branch['branch_name']);
        flash('success', 'Branch deleted.');
    } else {
        // Most likely a foreign key restriction from existing stock_in/stock_out records
        flash('danger', 'Could not delete "' . $branch['branch_name'] . '" — it has stock-in/stock-out history or other linked records. Set it to "Inactive" from Edit instead of deleting it.');
    }
} else {
    flash('danger', 'Branch not found.');
}
redirect('branches/list.php');
