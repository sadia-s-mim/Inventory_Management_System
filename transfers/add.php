<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'New Stock Transfer';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromBranchId = (int)$_POST['from_branch_id'];
    $toBranchId = (int)$_POST['to_branch_id'];
    $date = sanitize($conn, $_POST['transfer_date']);
    $refNo = sanitize($conn, $_POST['reference_no']);
    $notes = sanitize($conn, $_POST['notes']);
    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($fromBranchId <= 0) $errors[] = 'Please select a source branch.';
    if ($toBranchId <= 0) $errors[] = 'Please select a destination branch.';
    if ($fromBranchId > 0 && $fromBranchId === $toBranchId) $errors[] = 'Source and destination branches must be different.';
    if (empty($productIds)) $errors[] = 'Add at least one product line.';

    // Validate stock availability at the source branch before committing
    if (empty($errors)) {
        foreach ($productIds as $i => $pid) {
            $pid = (int)$pid;
            $qty = (int)$quantities[$i];
            if ($pid <= 0 || $qty <= 0) continue;
            $available = getStockQty($conn, $pid, $fromBranchId);
            if ($qty > $available) {
                $nameRes = $conn->query("SELECT product_name FROM products WHERE product_id = $pid")->fetch_assoc();
                $errors[] = "Not enough stock for " . ($nameRes['product_name'] ?? 'product') . " at the source branch (available: $available, requested: $qty).";
            }
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO stock_transfers (from_branch_id, to_branch_id, user_id, reference_no, transfer_date, notes) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("iiisss", $fromBranchId, $toBranchId, $_SESSION['user_id'], $refNo, $date, $notes);
            $stmt->execute();
            $transferId = $stmt->insert_id;

            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid;
                $qty = (int)$quantities[$i];
                if ($pid <= 0 || $qty <= 0) continue;

                $detail = $conn->prepare("INSERT INTO stock_transfer_details (transfer_id, product_id, quantity) VALUES (?,?,?)");
                $detail->bind_param("iii", $transferId, $pid, $qty);
                $detail->execute();

                $qtyBeforeAtSource = getStockQty($conn, $pid, $fromBranchId);
                adjustStock($conn, $pid, $fromBranchId, -$qty);
                adjustStock($conn, $pid, $toBranchId, $qty);
                checkLowStock($conn, $pid, $fromBranchId, $qtyBeforeAtSource);
            }

            $conn->commit();
            logActivity($conn, $_SESSION['user_id'], 'Stock Transfer', "Recorded stock transfer #$transferId");
            flash('success', 'Stock transfer recorded and inventory updated at both branches.');
            redirect('transfers/view.php?id=' . $transferId);
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Transaction failed: ' . $e->getMessage();
        }
    }
}

$branches = $conn->query("SELECT branch_id, branch_name FROM branches WHERE status='active' ORDER BY branch_name");
$branchesArr = [];
while ($b = $branches->fetch_assoc()) { $branchesArr[] = $b; }

$products = $conn->query("SELECT product_id, product_name, sku FROM products WHERE status='active' ORDER BY product_name");
$productsArr = [];
while ($p = $products->fetch_assoc()) { $productsArr[] = $p; }

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="pc-card p-4">
    <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>

    <form method="POST" id="transferForm">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">From Branch *</label>
                <select name="from_branch_id" id="fromBranch" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($branchesArr as $b): ?>
                        <option value="<?php echo $b['branch_id']; ?>" <?php echo (isset($_SESSION['branch_id']) && $_SESSION['branch_id']==$b['branch_id'])?'selected':''; ?>><?php echo htmlspecialchars($b['branch_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">To Branch *</label>
                <select name="to_branch_id" id="toBranch" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($branchesArr as $b): ?>
                        <option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text text-danger d-none" id="sameBranchWarning">Source and destination must be different.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date *</label>
                <input type="date" name="transfer_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Reference No.</label>
                <input type="text" name="reference_no" class="form-control" placeholder="Optional">
            </div>
        </div>

        <div class="table-responsive">
<table class="table" id="lineItemsTable">
            <thead>
                <tr><th style="width:50%;">Product</th><th>Qty</th><th></th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <select name="product_id[]" class="form-select product-select" required>
                            <option value="">Select product</option>
                            <?php foreach ($productsArr as $p): ?>
                                <option value="<?php echo $p['product_id']; ?>">
                                    <?php echo htmlspecialchars($p['product_name'] . ' (' . $p['sku'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" name="quantity[]" class="form-control pc-line-qty" min="1" required></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button></td>
                </tr>
            </tbody>
        </table>
</div>
        <button type="button" id="addRow" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-plus"></i> Add Line</button>

        <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Save Transfer</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

    </main>
</div>

<script>
document.getElementById('addRow').addEventListener('click', function () {
    const tbody = document.querySelector('#lineItemsTable tbody');
    const newRow = tbody.rows[0].cloneNode(true);
    newRow.querySelectorAll('input').forEach(i => i.value = '');
    tbody.appendChild(newRow);
    attachRowEvents(newRow);
});
function attachRowEvents(row) {
    row.querySelector('.remove-row').addEventListener('click', function () {
        if (document.querySelectorAll('#lineItemsTable tbody tr').length > 1) row.remove();
    });
}
document.querySelectorAll('#lineItemsTable tbody tr').forEach(attachRowEvents);

// Warn (client-side convenience only — the real check is server-side) when
// the same branch is picked for both source and destination.
function checkSameBranch() {
    const from = document.getElementById('fromBranch').value;
    const to = document.getElementById('toBranch').value;
    const warning = document.getElementById('sameBranchWarning');
    const same = from && to && from === to;
    warning.classList.toggle('d-none', !same);
}
document.getElementById('fromBranch').addEventListener('change', checkSameBranch);
document.getElementById('toBranch').addEventListener('change', checkSameBranch);
</script>

<?php require_once '../includes/footer.php'; ?>
