<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'Add User';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($conn, $_POST['full_name']);
    $email = sanitize($conn, $_POST['email']);
    $password = $_POST['password'];
    $roleId = (int)$_POST['role_id'];
    $branchId = (int)$_POST['branch_id'] ?: null;
    $phone = sanitize($conn, $_POST['phone']);

    if ($name === '') $errors[] = 'Full name is required.';
    if ($email === '') $errors[] = 'Email is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors)) {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = 'This email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role_id, branch_id, phone) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("sssiis", $name, $email, $hash, $roleId, $branchId, $phone);
            if ($stmt->execute()) {
                logActivity($conn, $_SESSION['user_id'], 'Add User', "Added user: $name");
                flash('success', 'User created successfully.');
                redirect('users/list.php');
            } else {
                $errors[] = 'Database error: ' . $conn->error;
            }
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
        <div class="mb-3"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
        <div class="mb-3">
            <label class="form-label">Role *</label>
            <select name="role_id" class="form-select" required>
                <option value="1">Admin</option>
                <option value="2">Branch Manager</option>
                <option value="3" selected>Sales User</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Branch</label>
            <select name="branch_id" class="form-select">
                <option value="">— None —</option>
                <?php while ($b = $branches->fetch_assoc()): ?>
                    <option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Save User</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
