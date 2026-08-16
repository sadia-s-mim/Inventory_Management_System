<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'Users';
$users = $conn->query("SELECT u.*, r.role_name, b.branch_name FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN branches b ON u.branch_id = b.branch_id
    ORDER BY u.full_name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0 text-muted">Manage system users and role-based access</h6>
    <a href="add.php" class="btn btn-pc-gold"><i class="bi bi-plus-lg"></i> Add User</a>
</div>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Branch</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php while ($u = $users->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($u['role_name']); ?></span></td>
                <td><?php echo htmlspecialchars($u['branch_name'] ?? '—'); ?></td>
                <td><span class="badge <?php echo $u['status']==='active'?'badge-ok':'bg-secondary'; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                <td class="text-end">
                    <a href="edit.php?id=<?php echo $u['user_id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                    <a href="delete.php?id=<?php echo $u['user_id']; ?>" class="btn btn-sm btn-outline-danger pc-confirm-delete"><i class="bi bi-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
