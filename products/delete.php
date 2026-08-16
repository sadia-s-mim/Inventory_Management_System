<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if ($product) {
    $del = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Delete Product', "Deleted product: " . $product['product_name']);
        flash('success', 'Product deleted.');
    } else {
        flash('danger', 'Could not delete — this product has existing stock movement records.');
    }
} else {
    flash('danger', 'Product not found.');
}
redirect('products/list.php');
