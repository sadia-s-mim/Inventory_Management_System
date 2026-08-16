<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'Edit Branch';
$errors = [];
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM branches WHERE branch_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();
if (!$branch) { flash('danger', 'Branch not found.'); redirect('branches/list.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($conn, $_POST['branch_name']);
    $location = sanitize($conn, $_POST['location']);
    $phone = sanitize($conn, $_POST['phone']);
    $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if ($name === '') $errors[] = 'Branch name is required.';

    if (empty($errors)) {
        $check = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = ? AND branch_id != ?");
        $check->bind_param("si", $name, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = 'Another branch already uses this name.';
        } else {
            $upd = $conn->prepare("UPDATE branches SET branch_name=?, location=?, phone=?, status=? WHERE branch_id=?");
            $upd->bind_param("ssssi", $name, $location, $phone, $status, $id);
            if ($upd->execute()) {
                logActivity($conn, $_SESSION['user_id'], 'Edit Branch', "Updated branch: $name");
                flash('success', 'Branch updated successfully.');
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
            <input type="text" name="branch_name" class="form-control" value="<?php echo htmlspecialchars($branch['branch_name']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($branch['location']); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($branch['phone']); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="active" <?php echo $branch['status']==='active'?'selected':''; ?>>Active</option>
                <option value="inactive" <?php echo $branch['status']==='inactive'?'selected':''; ?>>Inactive</option>
            </select>
            <div class="form-text">Setting a branch to "Inactive" hides it from new stock-in/stock-out forms without deleting its history.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Update Branch</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
