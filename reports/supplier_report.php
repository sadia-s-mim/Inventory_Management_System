<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'Supplier Report';

$sql = "SELECT s.supplier_name, s.contact_person, s.phone,
        COUNT(DISTINCT p.product_id) AS products_supplied,
        COALESCE(SUM(sid.quantity * sid.unit_cost),0) AS total_purchase_value
        FROM suppliers s
        LEFT JOIN products p ON p.supplier_id = s.supplier_id
        LEFT JOIN stock_in si ON si.supplier_id = s.supplier_id
        LEFT JOIN stock_in_details sid ON sid.stock_in_id = si.stock_in_id
        GROUP BY s.supplier_id
        ORDER BY total_purchase_value DESC";
$rows = $conn->query($sql);

if (($_GET['export'] ?? '') === 'csv') {
    exportResultAsCsv('supplier_report.csv', $rows, [
        'Supplier' => 'supplier_name', 'Contact' => 'contact_person', 'Phone' => 'phone',
        'Products Supplied' => 'products_supplied', 'Total Purchase Value' => 'total_purchase_value',
    ]);
}

// ---- Chart data: purchase value share by supplier (donut) ----
$supLabels = []; $supValues = [];
$rows->data_seek(0);
while ($r = $rows->fetch_assoc()) {
    if ((float)$r['total_purchase_value'] > 0) {
        $supLabels[] = $r['supplier_name'];
        $supValues[] = (float)$r['total_purchase_value'];
    }
}
$rows->data_seek(0);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once 'reports_nav.php';
?>

<div class="d-flex justify-content-end mb-3">
    <a href="?export=csv" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-5">
        <div class="pc-card p-3">
            <h6 class="mb-3"><i class="bi bi-pie-chart"></i> Purchase Value Share by Supplier</h6>
            <div class="pc-chart-box"><canvas id="supplierDonutChart"></canvas></div>
        </div>
    </div>
</div>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>Products Supplied</th><th>Total Purchase Value</th></tr></thead>
        <tbody>
        <?php if ($rows->num_rows === 0): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No supplier data.</td></tr>
        <?php else: while ($r = $rows->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['supplier_name']); ?></td>
                <td><?php echo htmlspecialchars($r['contact_person']); ?></td>
                <td><?php echo htmlspecialchars($r['phone']); ?></td>
                <td><?php echo $r['products_supplied']; ?></td>
                <td>৳<?php echo formatMoney($r['total_purchase_value']); ?></td>
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
new Chart(document.getElementById('supplierDonutChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($supLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($supValues); ?>,
            backgroundColor: ['#5c4433', '#a98a4d', '#8a6f55', '#6b6b66', '#cbbba4', '#4f6f52']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
});
</script>

<?php require_once '../includes/footer.php'; ?>
