<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$pageTitle = 'Stock Out Details';
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT so.*, b.branch_name, u.full_name
    FROM stock_out so
    JOIN branches b ON so.branch_id = b.branch_id
    JOIN users u ON so.user_id = u.user_id
    WHERE so.stock_out_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();

if (!$entry) { flash('danger', 'Record not found.'); redirect('stock_out/list.php'); }

$details = $conn->prepare("SELECT sod.*, p.product_name, p.sku FROM stock_out_details sod JOIN products p ON sod.product_id = p.product_id WHERE sod.stock_out_id = ?");
$details->bind_param("i", $id);
$details->execute();
$lines = $details->get_result();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="pc-card p-4">
    <div class="row mb-4">
        <div class="col-md-4"><strong>Reference:</strong><br><?php echo htmlspecialchars($entry['reference_no'] ?: ('SO-' . str_pad($entry['stock_out_id'],4,'0',STR_PAD_LEFT))); ?></div>
        <div class="col-md-4"><strong>Date:</strong><br><?php echo date('M d, Y', strtotime($entry['stock_out_date'])); ?></div>
        <div class="col-md-4"><strong>Branch:</strong><br><?php echo htmlspecialchars($entry['branch_name']); ?></div>
    </div>
    <div class="table-responsive">
<table class="table">
        <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
        <tbody>
        <?php while ($l = $lines->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($l['product_name']); ?></td>
                <td><?php echo htmlspecialchars($l['sku']); ?></td>
                <td><?php echo $l['quantity']; ?></td>
                <td>৳<?php echo formatMoney($l['unit_price']); ?></td>
                <td>৳<?php echo formatMoney($l['subtotal']); ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr><th colspan="4" class="text-end">Total Amount</th><th>৳<?php echo formatMoney($entry['total_amount']); ?></th></tr>
        </tfoot>
    </table>
</div>
    <?php if ($entry['notes']): ?><p class="text-muted">Notes: <?php echo htmlspecialchars($entry['notes']); ?></p><?php endif; ?>
    <a href="list.php" class="btn btn-outline-secondary">Back to List</a>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
