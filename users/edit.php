<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'Edit User';
$errors = [];
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { flash('danger', 'User not found.'); redirect('users/list.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($conn, $_POST['full_name']);
    $email = sanitize($conn, $_POST['email']);
    $roleId = (int)$_POST['role_id'];
    $branchId = (int)$_POST['branch_id'] ?: null;
    $phone = sanitize($conn, $_POST['phone']);
    $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';
    $newPassword = $_POST['password'];

    if ($name === '') $errors[] = 'Full name is required.';

    if (empty($errors)) {
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            }
        }
    }

    if (empty($errors)) {
        if (!empty($newPassword)) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET full_name=?, email=?, role_id=?, branch_id=?, phone=?, status=?, password=? WHERE user_id=?");
            $upd->bind_param("ssiisssi", $name, $email, $roleId, $branchId, $phone, $status, $hash, $id);
        } else {
            $upd = $conn->prepare("UPDATE users SET full_name=?, email=?, role_id=?, branch_id=?, phone=?, status=? WHERE user_id=?");
            $upd->bind_param("ssiissi", $name, $email, $roleId, $branchId, $phone, $status, $id);
        }
        if ($upd->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Edit User', "Updated user: $name");
            flash('success', 'User updated successfully.');
            redirect('users/list.php');
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}

$branches = $conn->query("SELECT branch_id, branch_name FROM branches WHERE status='active' ORDER BY branch_name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="pc-card p-4 pc-form-card">
    <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
    <form method="POST">
        <div class="mb-3"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div>
        <div class="mb-3"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
        <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep current"></div>
        <div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>"></div>
        <div class="mb-3">
            <label class="form-label">Role *</label>
            <select name="role_id" class="form-select" required>
                <option value="1" <?php echo $user['role_id']==1?'selected':''; ?>>Admin</option>
                <option value="2" <?php echo $user['role_id']==2?'selected':''; ?>>Branch Manager</option>
                <option value="3" <?php echo $user['role_id']==3?'selected':''; ?>>Sales User</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Branch</label>
            <select name="branch_id" class="form-select">
                <option value="">— None —</option>
                <?php while ($b = $branches->fetch_assoc()): ?>
                    <option value="<?php echo $b['branch_id']; ?>" <?php echo $b['branch_id']==$user['branch_id']?'selected':''; ?>><?php echo htmlspecialchars($b['branch_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="active" <?php echo $user['status']==='active'?'selected':''; ?>>Active</option>
                <option value="inactive" <?php echo $user['status']==='inactive'?'selected':''; ?>>Inactive</option>
            </select>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Update User</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
