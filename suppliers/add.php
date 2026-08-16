<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'Add Supplier';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($conn, $_POST['supplier_name']);
    $contact = sanitize($conn, $_POST['contact_person']);
    $phone = sanitize($conn, $_POST['phone']);
    $email = sanitize($conn, $_POST['email']);
    $address = sanitize($conn, $_POST['address']);

    if ($name === '') $errors[] = 'Supplier name is required.';

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $name, $contact, $phone, $email, $address);
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Add Supplier', "Added supplier: $name");
            flash('success', 'Supplier added successfully.');
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
        <div class="mb-3"><label class="form-label">Supplier Name *</label><input type="text" name="supplier_name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Contact Person</label><input type="text" name="contact_person" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Save Supplier</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
