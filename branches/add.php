<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'Add Branch';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($conn, $_POST['branch_name']);
    $location = sanitize($conn, $_POST['location']);
    $phone = sanitize($conn, $_POST['phone']);
    $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if ($name === '') $errors[] = 'Branch name is required.';

    if (empty($errors)) {
        $check = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = 'A branch with this name already exists.';
        } else {
            $stmt = $conn->prepare("INSERT INTO branches (branch_name, location, phone, status) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $name, $location, $phone, $status);
            if ($stmt->execute()) {
                logActivity($conn, $_SESSION['user_id'], 'Add Branch', "Added branch: $name");
                flash('success', 'Branch added successfully.');
                redirect('branches/list.php');
            } else {
                $errors[] = 'Database error: ' . $conn->error;
            }
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="pc-card p-4 pc-form-card">
    <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Branch Name *</label>
            <input type="text" name="branch_name" class="form-control" placeholder="e.g. Perfect Choice - Banani" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" placeholder="Street, area, city">
        </div>
        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Save Branch</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
