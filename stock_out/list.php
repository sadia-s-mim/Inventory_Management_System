<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$pageTitle = 'Stock Out';
$scopedBranch = ((int)$_SESSION['role_id'] !== ROLE_ADMIN) ? $_SESSION['branch_id'] : null;

$sql = "SELECT so.*, b.branch_name, u.full_name
        FROM stock_out so
        JOIN branches b ON so.branch_id = b.branch_id
        JOIN users u ON so.user_id = u.user_id";
if ($scopedBranch) $sql .= " WHERE so.branch_id = $scopedBranch";
$sql .= " ORDER BY so.stock_out_date DESC, so.stock_out_id DESC";
$entries = $conn->query($sql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0 text-muted">Record of sales and stock leaving inventory</h6>
    <a href="add.php" class="btn btn-pc-gold"><i class="bi bi-plus-lg"></i> New Stock Out</a>
</div>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead>
            <tr><th>Ref No.</th><th>Date</th><th>Branch</th><th>Recorded By</th><th>Total Amount</th><th></th></tr>
        </thead>
        <tbody>
        <?php if ($entries->num_rows === 0): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No stock-out records yet.</td></tr>
        <?php else: while ($e = $entries->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($e['reference_no'] ?: ('SO-' . str_pad($e['stock_out_id'], 4, '0', STR_PAD_LEFT))); ?></td>
                <td><?php echo date('M d, Y', strtotime($e['stock_out_date'])); ?></td>
                <td><?php echo htmlspecialchars($e['branch_name']); ?></td>
                <td><?php echo htmlspecialchars($e['full_name']); ?></td>
                <td>৳<?php echo formatMoney($e['total_amount']); ?></td>
                <td class="text-end"><a href="view.php?id=<?php echo $e['stock_out_id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> View</a></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
