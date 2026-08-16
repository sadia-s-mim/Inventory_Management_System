<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'Edit Supplier';
$errors = [];
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
if (!$supplier) { flash('danger', 'Supplier not found.'); redirect('suppliers/list.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($conn, $_POST['supplier_name']);
    $contact = sanitize($conn, $_POST['contact_person']);
    $phone = sanitize($conn, $_POST['phone']);
    $email = sanitize($conn, $_POST['email']);
    $address = sanitize($conn, $_POST['address']);
    $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if ($name === '') $errors[] = 'Supplier name is required.';

    if (empty($errors)) {
        $upd = $conn->prepare("UPDATE suppliers SET supplier_name=?, contact_person=?, phone=?, email=?, address=?, status=? WHERE supplier_id=?");
        $upd->bind_param("ssssssi", $name, $contact, $phone, $email, $address, $status, $id);
        if ($upd->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Edit Supplier', "Updated supplier: $name");
            flash('success', 'Supplier updated successfully.');
            redirect('suppliers/list.php');
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="pc-card p-4 pc-form-card">
    <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
    <form method="POST">
        <div class="mb-3"><label class="form-label">Supplier Name *</label><input type="text" name="supplier_name" class="form-control" value="<?php echo htmlspecialchars($supplier['supplier_name']); ?>" required></div>
        <div class="mb-3"><label class="form-label">Contact Person</label><input type="text" name="contact_person" class="form-control" value="<?php echo htmlspecialchars($supplier['contact_person']); ?>"></div>
        <div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($supplier['phone']); ?>"></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($supplier['email']); ?>"></div>
        <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($supplier['address']); ?></textarea></div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="active" <?php echo $supplier['status']==='active'?'selected':''; ?>>Active</option>
                <option value="inactive" <?php echo $supplier['status']==='inactive'?'selected':''; ?>>Inactive</option>
            </select>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Update Supplier</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
