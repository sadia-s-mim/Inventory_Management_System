<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$pageTitle = 'Low Stock Report';
$scopedBranch = ((int)$_SESSION['role_id'] !== ROLE_ADMIN) ? $_SESSION['branch_id'] : null;

$sql = "SELECT p.product_name, p.sku, b.branch_name, i.quantity, p.reorder_level, s.supplier_name, s.phone
        FROM inventory i
        JOIN products p ON i.product_id = p.product_id
        JOIN branches b ON i.branch_id = b.branch_id
        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
        WHERE i.quantity <= p.reorder_level";
if ($scopedBranch) $sql .= " AND i.branch_id = $scopedBranch";
$sql .= " ORDER BY i.quantity ASC";
$rows = $conn->query($sql);

if (($_GET['export'] ?? '') === 'csv') {
    $csvColumns = [
        'Product' => 'product_name', 'SKU' => 'sku', 'Branch' => 'branch_name',
        'Qty' => 'quantity', 'Reorder Level' => 'reorder_level',
    ];
    if ((int)$_SESSION['role_id'] !== ROLE_SALES_USER) {
        $csvColumns['Supplier'] = 'supplier_name';
        $csvColumns['Supplier Phone'] = 'phone';
    }
    exportResultAsCsv('low_stock_report.csv', $rows, $csvColumns);
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once 'reports_nav.php';
?>

<div class="d-flex justify-content-end mb-3">
    <a href="?export=csv" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
</div>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Product</th><th>SKU</th><th>Branch</th><th>Qty</th><th>Reorder Level</th>
                <?php if ((int)$_SESSION['role_id'] !== ROLE_SALES_USER): ?>
                <th>Supplier</th><th>Supplier Phone</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows->num_rows === 0): ?>
            <tr><td colspan="<?php echo ((int)$_SESSION['role_id'] !== ROLE_SALES_USER) ? 7 : 5; ?>" class="text-center text-muted py-4">No low stock items 🎉</td></tr>
        <?php else: while ($r = $rows->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                <td><?php echo htmlspecialchars($r['sku']); ?></td>
                <td><?php echo htmlspecialchars($r['branch_name']); ?></td>
                <td><span class="badge badge-low"><?php echo $r['quantity']; ?></span></td>
                <td><?php echo $r['reorder_level']; ?></td>
                <?php if ((int)$_SESSION['role_id'] !== ROLE_SALES_USER): ?>
                <td><?php echo htmlspecialchars($r['supplier_name'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($r['phone'] ?? '—'); ?></td>
                <?php endif; ?>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
