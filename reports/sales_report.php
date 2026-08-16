<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$pageTitle = 'Sales Report';
$scopedBranch = ((int)$_SESSION['role_id'] !== ROLE_ADMIN) ? $_SESSION['branch_id'] : null;
$dateFrom = sanitize($conn, $_GET['from'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo = sanitize($conn, $_GET['to'] ?? date('Y-m-d'));

$sql = "SELECT p.product_name, SUM(sod.quantity) AS units_sold, SUM(sod.subtotal) AS revenue
        FROM stock_out_details sod
        JOIN stock_out so ON sod.stock_out_id = so.stock_out_id
        JOIN products p ON sod.product_id = p.product_id
        WHERE so.stock_out_date BETWEEN '$dateFrom' AND '$dateTo'";
if ($scopedBranch) $sql .= " AND so.branch_id = $scopedBranch";
$sql .= " GROUP BY p.product_id ORDER BY revenue DESC";
$rows = $conn->query($sql);

if (($_GET['export'] ?? '') === 'csv') {
    exportResultAsCsv('sales_report.csv', $rows, [
        'Product' => 'product_name', 'Units Sold' => 'units_sold', 'Revenue' => 'revenue',
    ]);
}

$totalSql = "SELECT COALESCE(SUM(total_amount),0) v, COUNT(*) n FROM stock_out WHERE stock_out_date BETWEEN '$dateFrom' AND '$dateTo'";
if ($scopedBranch) $totalSql .= " AND branch_id = $scopedBranch";
$totals = $conn->query($totalSql)->fetch_assoc();

// ---- Chart data: revenue share by product (donut, top 6 + Other bucket) ----
$prodLabels = []; $prodValues = [];
$rank = 0; $otherTotal = 0;
$rows->data_seek(0);
while ($r = $rows->fetch_assoc()) {
    $rank++;
    if ($rank <= 6) {
        $prodLabels[] = $r['product_name'];
        $prodValues[] = (float)$r['revenue'];
    } else {
        $otherTotal += (float)$r['revenue'];
    }
}
if ($otherTotal > 0) {
    $prodLabels[] = 'Other';
    $prodValues[] = $otherTotal;
}
$rows->data_seek(0);

// ---- Chart data: daily revenue trend across the selected date range ----
$trendSql = "SELECT stock_out_date d, SUM(total_amount) v FROM stock_out WHERE stock_out_date BETWEEN '$dateFrom' AND '$dateTo'";
if ($scopedBranch) $trendSql .= " AND branch_id = $scopedBranch";
$trendSql .= " GROUP BY stock_out_date ORDER BY stock_out_date";
$trendRes = $conn->query($trendSql);
$trendByDate = [];
while ($t = $trendRes->fetch_assoc()) {
    $trendByDate[$t['d']] = (float)$t['v'];
}
$trendLabels = []; $trendValues = [];
$cursor = strtotime($dateFrom);
$end = strtotime($dateTo);
while ($cursor <= $end) {
    $d = date('Y-m-d', $cursor);
    $trendLabels[] = date('M d', $cursor);
    $trendValues[] = $trendByDate[$d] ?? 0;
    $cursor = strtotime('+1 day', $cursor);
}

// ---- Chart data: revenue by branch (Admin view only — branch-scoped roles already see one branch) ----
$branchRevLabels = []; $branchRevValues = [];
if (!$scopedBranch) {
    $branchRevSql = "SELECT b.branch_name, SUM(so.total_amount) v FROM stock_out so JOIN branches b ON so.branch_id = b.branch_id
        WHERE so.stock_out_date BETWEEN '$dateFrom' AND '$dateTo' GROUP BY b.branch_id ORDER BY b.branch_name";
    $branchRevRes = $conn->query($branchRevSql);
    while ($br = $branchRevRes->fetch_assoc()) {
        $branchRevLabels[] = $br['branch_name'];
        $branchRevValues[] = (float)$br['v'];
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once 'reports_nav.php';
?>

<form class="d-flex flex-wrap align-items-center gap-2 mb-3" method="GET">
    <input type="date" name="from" class="form-control form-control-sm pc-date-input" value="<?php echo $dateFrom; ?>">
    <span class="text-muted small">to</span>
    <input type="date" name="to" class="form-control form-control-sm pc-date-input" value="<?php echo $dateTo; ?>">
    <button class="btn btn-sm btn-pc-primary">Filter</button>
    <a href="?from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>&export=csv" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="pc-kpi"><div class="kpi-label">Total Revenue</div><div class="kpi-value">৳<?php echo formatMoney($totals['v']); ?></div></div></div>
    <div class="col-md-3"><div class="pc-kpi alt1"><div class="kpi-label">Transactions</div><div class="kpi-value"><?php echo number_format($totals['n']); ?></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-<?php echo $scopedBranch ? '8' : '6'; ?>">
        <div class="pc-card p-3">
            <h6 class="mb-3"><i class="bi bi-graph-up"></i> Daily Revenue Trend</h6>
            <div class="pc-chart-box"><canvas id="revenueTrendChart"></canvas></div>
        </div>
    </div>
    <div class="col-md-<?php echo $scopedBranch ? '4' : '3'; ?>">
        <div class="pc-card p-3">
            <h6 class="mb-3"><i class="bi bi-pie-chart"></i> Revenue by Product</h6>
            <div class="pc-chart-box"><canvas id="productDonutChart"></canvas></div>
        </div>
    </div>
    <?php if (!$scopedBranch): ?>
    <div class="col-md-3">
        <div class="pc-card p-3">
            <h6 class="mb-3"><i class="bi bi-bar-chart"></i> Revenue by Branch</h6>
            <div class="pc-chart-box"><canvas id="branchRevenueChart"></canvas></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead><tr><th>Product</th><th>Units Sold</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php if ($rows->num_rows === 0): ?>
            <tr><td colspan="3" class="text-center text-muted py-4">No sales in this range.</td></tr>
        <?php else: while ($r = $rows->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                <td><?php echo $r['units_sold']; ?></td>
                <td>৳<?php echo formatMoney($r['revenue']); ?></td>
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
new Chart(document.getElementById('revenueTrendChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($trendLabels); ?>,
        datasets: [{
            label: 'Revenue (৳)',
            data: <?php echo json_encode($trendValues); ?>,
            borderColor: '#a98a4d',
            backgroundColor: 'rgba(169,138,77,0.15)',
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#5c4433',
            pointRadius: 3
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('productDonutChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($prodLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($prodValues); ?>,
            backgroundColor: ['#5c4433', '#a98a4d', '#8a6f55', '#6b6b66', '#cbbba4', '#4f6f52', '#8b3a3a']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
});

<?php if (!$scopedBranch): ?>
new Chart(document.getElementById('branchRevenueChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($branchRevLabels); ?>,
        datasets: [{
            label: 'Revenue (৳)',
            data: <?php echo json_encode($branchRevValues); ?>,
            backgroundColor: '#5c4433'
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
