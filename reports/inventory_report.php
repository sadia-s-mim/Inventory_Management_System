<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$pageTitle = 'Current Inventory Report';
$scopedBranch = ((int)$_SESSION['role_id'] !== ROLE_ADMIN) ? $_SESSION['branch_id'] : null;

$sql = "SELECT p.product_name, p.sku, c.category_name, b.branch_name, i.quantity, p.cost_price, p.selling_price,
        (i.quantity * p.cost_price) AS stock_value
        FROM inventory i
        JOIN products p ON i.product_id = p.product_id
        JOIN branches b ON i.branch_id = b.branch_id
        LEFT JOIN categories c ON p.category_id = c.category_id";
if ($scopedBranch) $sql .= " WHERE i.branch_id = $scopedBranch";
$sql .= " ORDER BY p.product_name";
$rows = $conn->query($sql);

if (($_GET['export'] ?? '') === 'csv') {
    exportResultAsCsv('inventory_report.csv', $rows, [
        'Product' => 'product_name', 'SKU' => 'sku', 'Type' => 'category_name',
        'Branch' => 'branch_name', 'Qty on Hand' => 'quantity', 'Cost Price' => 'cost_price',
        'Selling Price' => 'selling_price', 'Stock Value' => 'stock_value',
    ]);
}

$totalValueSql = "SELECT COALESCE(SUM(i.quantity * p.cost_price),0) v FROM inventory i JOIN products p ON i.product_id=p.product_id";
if ($scopedBranch) $totalValueSql .= " WHERE i.branch_id = $scopedBranch";
$totalValue = $conn->query($totalValueSql)->fetch_assoc()['v'];

// ---- Chart data: stock value by category type (donut) ----
$typeSql = "SELECT c.category_name, SUM(i.quantity * p.cost_price) v
    FROM inventory i JOIN products p ON i.product_id = p.product_id LEFT JOIN categories c ON p.category_id = c.category_id";
if ($scopedBranch) $typeSql .= " WHERE i.branch_id = $scopedBranch";
$typeSql .= " GROUP BY c.category_id ORDER BY v DESC";
$typeRes = $conn->query($typeSql);
$typeLabels = []; $typeValues = [];
while ($t = $typeRes->fetch_assoc()) {
    $typeLabels[] = $t['category_name'] ?? 'Uncategorized';
    $typeValues[] = (float)$t['v'];
}

// ---- Chart data: stock quantity by branch (bar) ----
$branchQtySql = "SELECT b.branch_name, SUM(i.quantity) q FROM inventory i JOIN branches b ON i.branch_id = b.branch_id";
if ($scopedBranch) $branchQtySql .= " WHERE i.branch_id = $scopedBranch";
$branchQtySql .= " GROUP BY b.branch_id ORDER BY b.branch_name";
$branchQtyRes = $conn->query($branchQtySql);
$branchLabels = []; $branchQtyValues = [];
while ($bq = $branchQtyRes->fetch_assoc()) {
    $branchLabels[] = $bq['branch_name'];
    $branchQtyValues[] = (int)$bq['q'];
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once 'reports_nav.php';
?>

<div class="pc-card p-3 mb-3 d-flex justify-content-between align-items-center">
    <div><strong>Total Inventory Value:</strong> ৳<?php echo formatMoney($totalValue); ?></div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
    <a href="?export=csv" class="btn btn-sm btn-outline-secondary ms-2"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="pc-card p-3">
            <h6 class="mb-3"><i class="bi bi-pie-chart"></i> Stock Value by Type</h6>
            <div class="pc-chart-box"><canvas id="typeDonutChart"></canvas></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="pc-card p-3">
            <h6 class="mb-3"><i class="bi bi-bar-chart"></i> Stock Quantity by Branch</h6>
            <div class="pc-chart-box"><canvas id="branchBarChart"></canvas></div>
        </div>
    </div>
</div>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead><tr><th>Product</th><th>SKU</th><th>Type</th><th>Branch</th><th>Qty on Hand</th><th>Cost Price</th><th>Selling Price</th><th>Stock Value</th></tr></thead>
        <tbody>
        <?php if ($rows->num_rows === 0): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No inventory data.</td></tr>
        <?php else: while ($r = $rows->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                <td><?php echo htmlspecialchars($r['sku']); ?></td>
                <td><?php echo htmlspecialchars($r['category_name'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($r['branch_name']); ?></td>
                <td><?php echo $r['quantity']; ?></td>
                <td>৳<?php echo formatMoney($r['cost_price']); ?></td>
                <td>৳<?php echo formatMoney($r['selling_price']); ?></td>
                <td>৳<?php echo formatMoney($r['stock_value']); ?></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>
</div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('typeDonutChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($typeLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($typeValues); ?>,
            backgroundColor: ['#5c4433', '#a98a4d', '#8a6f55', '#6b6b66', '#cbbba4', '#4f6f52', '#8b3a3a', '#b9893f', '#3b2a20', '#46453f']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
});

new Chart(document.getElementById('branchBarChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($branchLabels); ?>,
        datasets: [{
            label: 'Units in Stock',
            data: <?php echo json_encode($branchQtyValues); ?>,
            backgroundColor: '#a98a4d'
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>

<?php require_once '../includes/footer.php'; ?>
