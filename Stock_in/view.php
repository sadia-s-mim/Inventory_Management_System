<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$pageTitle = 'Stock In Details';
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT si.*, s.supplier_name, b.branch_name, u.full_name
    FROM stock_in si
    JOIN suppliers s ON si.supplier_id = s.supplier_id
    JOIN branches b ON si.branch_id = b.branch_id
    JOIN users u ON si.user_id = u.user_id
    WHERE si.stock_in_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();

if (!$entry) { flash('danger', 'Record not found.'); redirect('stock_in/list.php'); }

$details = $conn->prepare("SELECT sid.*, p.product_name, p.sku FROM stock_in_details sid JOIN products p ON sid.product_id = p.product_id WHERE sid.stock_in_id = ?");
$details->bind_param("i", $id);
$details->execute();
$lines = $details->get_result();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="pc-card p-4">
    <div class="row mb-4">
        <div class="col-md-3"><strong>Reference:</strong><br><?php echo htmlspecialchars($entry['reference_no'] ?: ('SI-' . str_pad($entry['stock_in_id'],4,'0',STR_PAD_LEFT))); ?></div>
        <div class="col-md-3"><strong>Date:</strong><br><?php echo date('M d, Y', strtotime($entry['stock_in_date'])); ?></div>
        <?php if ((int)$_SESSION['role_id'] !== ROLE_SALES_USER): ?>
        <div class="col-md-3"><strong>Supplier:</strong><br><?php echo htmlspecialchars($entry['supplier_name']); ?></div>
        <?php endif; ?>
        <div class="col-md-3"><strong>Branch:</strong><br><?php echo htmlspecialchars($entry['branch_name']); ?></div>
    </div>
    <table class="table">
        <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Unit Cost</th><th>Subtotal</th></tr></thead>
        <tbody>
        <?php while ($l = $lines->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($l['product_name']); ?></td>
                <td><?php echo htmlspecialchars($l['sku']); ?></td>
                <td><?php echo $l['quantity']; ?></td>
                <td>৳<?php echo formatMoney($l['unit_cost']); ?></td>
                <td>৳<?php echo formatMoney($l['subtotal']); ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr><th colspan="4" class="text-end">Total Cost</th><th>৳<?php echo formatMoney($entry['total_cost']); ?></th></tr>
        </tfoot>
    </table>
    <?php if ($entry['notes']): ?><p class="text-muted">Notes: <?php echo htmlspecialchars($entry['notes']); ?></p><?php endif; ?>
    <a href="list.php" class="btn btn-outline-secondary">Back to List</a>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
