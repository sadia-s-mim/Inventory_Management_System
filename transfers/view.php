<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'Stock Transfer Details';
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT t.*, fb.branch_name AS from_branch_name, tb.branch_name AS to_branch_name, u.full_name
    FROM stock_transfers t
    JOIN branches fb ON t.from_branch_id = fb.branch_id
    JOIN branches tb ON t.to_branch_id = tb.branch_id
    JOIN users u ON t.user_id = u.user_id
    WHERE t.transfer_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$transfer = $stmt->get_result()->fetch_assoc();

if (!$transfer) { flash('danger', 'Transfer not found.'); redirect('transfers/list.php'); }

$details = $conn->prepare("SELECT std.*, p.product_name, p.sku FROM stock_transfer_details std JOIN products p ON std.product_id = p.product_id WHERE std.transfer_id = ?");
$details->bind_param("i", $id);
$details->execute();
$lines = $details->get_result();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="pc-card p-4">
    <div class="row mb-4">
        <div class="col-md-3"><strong>Reference:</strong><br><?php echo htmlspecialchars($transfer['reference_no'] ?: ('TR-' . str_pad($transfer['transfer_id'],4,'0',STR_PAD_LEFT))); ?></div>
        <div class="col-md-3"><strong>Date:</strong><br><?php echo date('M d, Y', strtotime($transfer['transfer_date'])); ?></div>
        <div class="col-md-3"><strong>From:</strong><br><?php echo htmlspecialchars($transfer['from_branch_name']); ?></div>
        <div class="col-md-3"><strong>To:</strong><br><?php echo htmlspecialchars($transfer['to_branch_name']); ?></div>
    </div>
    <div class="table-responsive">
<table class="table">
        <thead><tr><th>Product</th><th>SKU</th><th>Qty Transferred</th></tr></thead>
        <tbody>
        <?php while ($l = $lines->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($l['product_name']); ?></td>
                <td><?php echo htmlspecialchars($l['sku']); ?></td>
                <td><?php echo $l['quantity']; ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
    <?php if ($transfer['notes']): ?><p class="text-muted">Notes: <?php echo htmlspecialchars($transfer['notes']); ?></p><?php endif; ?>
    <p class="text-muted small">Recorded by <?php echo htmlspecialchars($transfer['full_name']); ?></p>
    <a href="list.php" class="btn btn-outline-secondary">Back to List</a>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
