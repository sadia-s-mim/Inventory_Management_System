<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$pageTitle = 'Stock In';
$scopedBranch = ((int)$_SESSION['role_id'] !== ROLE_ADMIN) ? $_SESSION['branch_id'] : null;

$sql = "SELECT si.*, s.supplier_name, b.branch_name, u.full_name
        FROM stock_in si
        JOIN suppliers s ON si.supplier_id = s.supplier_id
        JOIN branches b ON si.branch_id = b.branch_id
        JOIN users u ON si.user_id = u.user_id";
if ($scopedBranch) $sql .= " WHERE si.branch_id = $scopedBranch";
$sql .= " ORDER BY si.stock_in_date DESC, si.stock_in_id DESC";
$entries = $conn->query($sql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0 text-muted">Record of all stock received from suppliers</h6>
    <a href="add.php" class="btn btn-pc-gold"><i class="bi bi-plus-lg"></i> New Stock In</a>
</div>

<div class="pc-card p-3">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Ref No.</th><th>Date</th>
                <?php if ((int)$_SESSION['role_id'] !== ROLE_SALES_USER): ?>
                <th>Supplier</th>
                <?php endif; ?>
                <th>Branch</th><th>Recorded By</th><th>Total Cost</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php if ($entries->num_rows === 0): ?>
            <tr><td colspan="<?php echo ((int)$_SESSION['role_id'] !== ROLE_SALES_USER) ? 7 : 6; ?>" class="text-center text-muted py-4">No stock-in records yet.</td></tr>
        <?php else: while ($e = $entries->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($e['reference_no'] ?: ('SI-' . str_pad($e['stock_in_id'], 4, '0', STR_PAD_LEFT))); ?></td>
                <td><?php echo date('M d, Y', strtotime($e['stock_in_date'])); ?></td>
                <?php if ((int)$_SESSION['role_id'] !== ROLE_SALES_USER): ?>
                <td><?php echo htmlspecialchars($e['supplier_name']); ?></td>
                <?php endif; ?>
                <td><?php echo htmlspecialchars($e['branch_name']); ?></td>
                <td><?php echo htmlspecialchars($e['full_name']); ?></td>
                <td>৳<?php echo formatMoney($e['total_cost']); ?></td>
                <td class="text-end"><a href="view.php?id=<?php echo $e['stock_in_id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> View</a></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
