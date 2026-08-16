<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);
require_once '../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Invalid request method.');
    redirect('suppliers/list.php');
}

$id = (int)($_POST['id'] ?? 0);
$csrf = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token($csrf)) {
    flash('danger', 'Invalid CSRF token.');
    redirect('suppliers/list.php');
}
$stmt = $conn->prepare("SELECT supplier_name FROM suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();

if ($supplier) {
    $del = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Delete Supplier', "Deleted supplier: " . $supplier['supplier_name']);
        flash('success', 'Supplier deleted.');
    } else {
        flash('danger', 'Could not delete — this supplier has linked products or stock-in records.');
    }
} else {
    flash('danger', 'Supplier not found.');
}
redirect('suppliers/list.php');
