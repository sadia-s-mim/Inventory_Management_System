<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();

if ($category) {
    $del = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Delete Category', "Deleted category: " . $category['category_name']);
        flash('success', 'Category deleted (and any sub-categories).');
    } else {
        flash('danger', 'Could not delete — this category has products linked to it.');
    }
} else {
    flash('danger', 'Category not found.');
}
redirect('categories/list.php');
