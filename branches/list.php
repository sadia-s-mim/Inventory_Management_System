<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'Branches';

$sql = "SELECT b.*,
        (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.branch_id) AS user_count,
        (SELECT COALESCE(SUM(i.quantity),0) FROM inventory i WHERE i.branch_id = b.branch_id) AS stock_qty
        FROM branches b
        ORDER BY b.branch_name";
$branches = $conn->query($sql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0 text-muted">Manage store locations for Perfect Choice</h6>
    <a href="add.php" class="btn btn-pc-gold"><i class="bi bi-plus-lg"></i> Add Branch</a>
</div>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead>
            <tr><th>Branch Name</th><th>Location</th><th>Phone</th><th>Users</th><th>Stock on Hand</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$branches || $branches->num_rows === 0): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No branches yet. Click "Add Branch" to create your first one.</td></tr>
        <?php else: while ($b = $branches->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($b['branch_name']); ?></td>
                <td><?php echo htmlspecialchars($b['location']); ?></td>
                <td><?php echo htmlspecialchars($b['phone']); ?></td>
                <td><?php echo (int)$b['user_count']; ?></td>
                <td><?php echo (int)$b['stock_qty']; ?> units</td>
                <td><span class="badge <?php echo $b['status']==='active'?'badge-ok':'bg-secondary'; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                <td class="text-end">
                    <a href="edit.php?id=<?php echo $b['branch_id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <a href="delete.php?id=<?php echo $b['branch_id']; ?>" class="btn btn-sm btn-outline-danger pc-confirm-delete"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
