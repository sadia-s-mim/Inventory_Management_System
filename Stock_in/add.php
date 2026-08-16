<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'New Stock In';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId = (int)$_POST['supplier_id'];
    $branchId = (int)$_POST['branch_id'];
    $date = sanitize($conn, $_POST['stock_in_date']);
    $refNo = sanitize($conn, $_POST['reference_no']);
    $notes = sanitize($conn, $_POST['notes']);
    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $costs = $_POST['unit_cost'] ?? [];

    if ($supplierId <= 0) $errors[] = 'Please select a supplier.';
    if ($branchId <= 0) $errors[] = 'Please select a branch.';
    if (empty($productIds)) $errors[] = 'Add at least one product line.';

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $totalCost = 0;
            foreach ($quantities as $i => $q) {
                $totalCost += (float)$q * (float)$costs[$i];
            }

            $stmt = $conn->prepare("INSERT INTO stock_in (supplier_id, branch_id, user_id, reference_no, stock_in_date, total_cost, notes) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("iiissds", $supplierId, $branchId, $_SESSION['user_id'], $refNo, $date, $totalCost, $notes);
            $stmt->execute();
            $stockInId = $stmt->insert_id;

            foreach ($productIds as $i => $pid) {
                $pid = (int)$pid;
                $qty = (int)$quantities[$i];
                $cost = (float)$costs[$i];
                if ($pid <= 0 || $qty <= 0) continue;

                $detail = $conn->prepare("INSERT INTO stock_in_details (stock_in_id, product_id, quantity, unit_cost) VALUES (?,?,?,?)");
                $detail->bind_param("iiid", $stockInId, $pid, $qty, $cost);
                $detail->execute();

                adjustStock($conn, $pid, $branchId, $qty);
            }

            $conn->commit();
            logActivity($conn, $_SESSION['user_id'], 'Stock In', "Recorded stock-in #$stockInId");
            flash('success', 'Stock-in recorded and inventory updated.');
            redirect('stock_in/view.php?id=' . $stockInId);
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Transaction failed: ' . $e->getMessage();
        }
    }
}

$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM suppliers WHERE status='active' ORDER BY supplier_name");
$branches = $conn->query("SELECT branch_id, branch_name FROM branches WHERE status='active' ORDER BY branch_name");
$products = $conn->query("SELECT product_id, product_name, sku, cost_price FROM products WHERE status='active' ORDER BY product_name");
$productsArr = [];
while ($p = $products->fetch_assoc()) { $productsArr[] = $p; }

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="pc-card p-4">
    <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>

    <form method="POST" id="stockInForm">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Supplier *</label>
                <select name="supplier_id" class="form-select" required>
                    <option value="">Select</option>
                    <?php while ($s = $suppliers->fetch_assoc()): ?>
                        <option value="<?php echo $s['supplier_id']; ?>"><?php echo htmlspecialchars($s['supplier_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Branch *</label>
                <select name="branch_id" class="form-select" required>
                    <option value="">Select</option>
                    <?php while ($b = $branches->fetch_assoc()): ?>
                        <option value="<?php echo $b['branch_id']; ?>" <?php echo (isset($_SESSION['branch_id']) && $_SESSION['branch_id']==$b['branch_id'])?'selected':''; ?>><?php echo htmlspecialchars($b['branch_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date *</label>
                <input type="date" name="stock_in_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Reference No.</label>
                <input type="text" name="reference_no" class="form-control" placeholder="Optional">
            </div>
        </div>

        <table class="table" id="lineItemsTable">
            <thead>
                <tr><th style="width:40%;">Product</th><th>Qty</th><th>Unit Cost (৳)</th><th>Subtotal</th><th></th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <select name="product_id[]" class="form-select product-select" required>
                            <option value="">Select product</option>
                            <?php foreach ($productsArr as $p): ?>
                                <option value="<?php echo $p['product_id']; ?>" data-cost="<?php echo $p['cost_price']; ?>">
                                    <?php echo htmlspecialchars($p['product_name'] . ' (' . $p['sku'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" name="quantity[]" class="form-control pc-line-qty" min="1" required></td>
                    <td><input type="number" step="0.01" name="unit_cost[]" class="form-control pc-line-price" required></td>
                    <td class="pc-line-subtotal align-middle">0.00</td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button></td>
                </tr>
            </tbody>
        </table>
        <button type="button" id="addRow" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-plus"></i> Add Line</button>

        <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Save Stock In</button>
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
    newRow.querySelector('.pc-line-subtotal').textContent = '0.00';
    tbody.appendChild(newRow);
    attachRowEvents(newRow);
});

function attachRowEvents(row) {
    row.querySelector('.remove-row').addEventListener('click', function () {
        if (document.querySelectorAll('#lineItemsTable tbody tr').length > 1) row.remove();
    });
    const select = row.querySelector('.product-select');
    select.addEventListener('change', function () {
        const opt = select.options[select.selectedIndex];
        const cost = opt.getAttribute('data-cost');
        if (cost) row.querySelector('.pc-line-price').value = cost;
        recalcRow(row);
    });
    row.querySelector('.pc-line-qty').addEventListener('input', () => recalcRow(row));
    row.querySelector('.pc-line-price').addEventListener('input', () => recalcRow(row));
}
function recalcRow(row) {
    const qty = parseFloat(row.querySelector('.pc-line-qty').value) || 0;
    const price = parseFloat(row.querySelector('.pc-line-price').value) || 0;
    row.querySelector('.pc-line-subtotal').textContent = (qty * price).toFixed(2);
}
document.querySelectorAll('#lineItemsTable tbody tr').forEach(attachRowEvents);
</script>

<?php require_once '../includes/footer.php'; ?>
