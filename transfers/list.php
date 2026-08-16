<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'Stock Transfers';
$scopedBranch = ((int)$_SESSION['role_id'] !== ROLE_ADMIN) ? $_SESSION['branch_id'] : null;

$sql = "SELECT t.*, fb.branch_name AS from_branch_name, tb.branch_name AS to_branch_name, u.full_name
        FROM stock_transfers t
        JOIN branches fb ON t.from_branch_id = fb.branch_id
        JOIN branches tb ON t.to_branch_id = tb.branch_id
        JOIN users u ON t.user_id = u.user_id";
if ($scopedBranch) {
    $sql .= " WHERE t.from_branch_id = $scopedBranch OR t.to_branch_id = $scopedBranch";
}
$sql .= " ORDER BY t.transfer_date DESC, t.transfer_id DESC";
$transfers = $conn->query($sql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0 text-muted">Move inventory between branches</h6>
    <a href="add.php" class="btn btn-pc-gold"><i class="bi bi-plus-lg"></i> New Transfer</a>
</div>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead>
            <tr><th>Ref No.</th><th>Date</th><th>From</th><th>To</th><th>Recorded By</th><th></th></tr>
        </thead>
        <tbody>
        <?php if ($transfers->num_rows === 0): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No stock transfers yet.</td></tr>
        <?php else: while ($t = $transfers->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($t['reference_no'] ?: ('TR-' . str_pad($t['transfer_id'], 4, '0', STR_PAD_LEFT))); ?></td>
                <td><?php echo date('M d, Y', strtotime($t['transfer_date'])); ?></td>
                <td><?php echo htmlspecialchars($t['from_branch_name']); ?></td>
                <td><?php echo htmlspecialchars($t['to_branch_name']); ?></td>
                <td><?php echo htmlspecialchars($t['full_name']); ?></td>
                <td class="text-end"><a href="view.php?id=<?php echo $t['transfer_id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> View</a></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
