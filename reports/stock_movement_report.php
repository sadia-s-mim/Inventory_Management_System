<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$pageTitle = 'Stock Movement Report';
$scopedBranch = ((int)$_SESSION['role_id'] !== ROLE_ADMIN) ? $_SESSION['branch_id'] : null;
$dateFrom = sanitize($conn, $_GET['from'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo = sanitize($conn, $_GET['to'] ?? date('Y-m-d'));

$inSql = "SELECT 'IN' AS type, si.stock_in_date AS move_date, p.product_name, sid.quantity, b.branch_name, u.full_name
          FROM stock_in_details sid
          JOIN stock_in si ON sid.stock_in_id = si.stock_in_id
          JOIN products p ON sid.product_id = p.product_id
          JOIN branches b ON si.branch_id = b.branch_id
          JOIN users u ON si.user_id = u.user_id
          WHERE si.stock_in_date BETWEEN '$dateFrom' AND '$dateTo'";
$outSql = "SELECT 'OUT' AS type, so.stock_out_date AS move_date, p.product_name, sod.quantity, b.branch_name, u.full_name
          FROM stock_out_details sod
          JOIN stock_out so ON sod.stock_out_id = so.stock_out_id
          JOIN products p ON sod.product_id = p.product_id
          JOIN branches b ON so.branch_id = b.branch_id
          JOIN users u ON so.user_id = u.user_id
          WHERE so.stock_out_date BETWEEN '$dateFrom' AND '$dateTo'";
if ($scopedBranch) { $inSql .= " AND si.branch_id = $scopedBranch"; $outSql .= " AND so.branch_id = $scopedBranch"; }

$rows = $conn->query("($inSql) UNION ALL ($outSql) ORDER BY move_date DESC");

if (($_GET['export'] ?? '') === 'csv') {
    exportResultAsCsv('stock_movement_report.csv', $rows, [
        'Date' => 'move_date', 'Type' => 'type', 'Product' => 'product_name',
        'Qty' => 'quantity', 'Branch' => 'branch_name', 'By' => 'full_name',
    ]);
}

// ---- Chart data: daily quantity IN vs OUT across the selected range ----
$dailyInSql = "SELECT si.stock_in_date d, SUM(sid.quantity) q FROM stock_in_details sid JOIN stock_in si ON sid.stock_in_id = si.stock_in_id WHERE si.stock_in_date BETWEEN '$dateFrom' AND '$dateTo'";
$dailyOutSql = "SELECT so.stock_out_date d, SUM(sod.quantity) q FROM stock_out_details sod JOIN stock_out so ON sod.stock_out_id = so.stock_out_id WHERE so.stock_out_date BETWEEN '$dateFrom' AND '$dateTo'";
if ($scopedBranch) { $dailyInSql .= " AND si.branch_id = $scopedBranch"; $dailyOutSql .= " AND so.branch_id = $scopedBranch"; }
$dailyInSql .= " GROUP BY si.stock_in_date";
$dailyOutSql .= " GROUP BY so.stock_out_date";

$dailyInByDate = [];
$dailyInRes = $conn->query($dailyInSql);
while ($di = $dailyInRes->fetch_assoc()) { $dailyInByDate[$di['d']] = (int)$di['q']; }

$dailyOutByDate = [];
$dailyOutRes = $conn->query($dailyOutSql);
while ($do = $dailyOutRes->fetch_assoc()) { $dailyOutByDate[$do['d']] = (int)$do['q']; }

$moveLabels = []; $moveInValues = []; $moveOutValues = [];
$cursor = strtotime($dateFrom);
$end = strtotime($dateTo);
$totalDays = max(1, round(($end - $cursor) / 86400) + 1);
// Cap the daily-trend chart at 31 points so wide date ranges stay readable
$step = (int)ceil($totalDays / 31);
$i = 0;
while ($cursor <= $end) {
    if ($i % $step === 0) {
        $d = date('Y-m-d', $cursor);
        $moveLabels[] = date('M d', $cursor);
        $moveInValues[] = $dailyInByDate[$d] ?? 0;
        $moveOutValues[] = $dailyOutByDate[$d] ?? 0;
    }
    $cursor = strtotime('+1 day', $cursor);
    $i++;
}

// ---- Chart data: total units IN vs OUT split (donut) ----
$totalInUnits = array_sum($dailyInByDate);
$totalOutUnits = array_sum($dailyOutByDate);

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
    <div class="col-md-8">
        <div class="pc-card p-3">
            <h6 class="mb-3"><i class="bi bi-graph-up"></i> Daily Stock In vs Out</h6>
            <div class="pc-chart-box"><canvas id="movementTrendChart"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="pc-card p-3">
            <h6 class="mb-3"><i class="bi bi-pie-chart"></i> Total Units: In vs Out</h6>
            <div class="pc-chart-box"><canvas id="movementDonutChart"></canvas></div>
        </div>
    </div>
</div>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead><tr><th>Date</th><th>Type</th><th>Product</th><th>Qty</th><th>Branch</th><th>By</th></tr></thead>
        <tbody>
        <?php if ($rows->num_rows === 0): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No movement in this range.</td></tr>
        <?php else: while ($r = $rows->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($r['move_date'])); ?></td>
                <td><span class="badge <?php echo $r['type']==='IN'?'badge-ok':'badge-low'; ?>"><?php echo $r['type']; ?></span></td>
                <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                <td><?php echo $r['quantity']; ?></td>
                <td><?php echo htmlspecialchars($r['branch_name']); ?></td>
                <td><?php echo htmlspecialchars($r['full_name']); ?></td>
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
new Chart(document.getElementById('movementTrendChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($moveLabels); ?>,
        datasets: [
            { label: 'Stock In', data: <?php echo json_encode($moveInValues); ?>, backgroundColor: '#a98a4d' },
            { label: 'Stock Out', data: <?php echo json_encode($moveOutValues); ?>, backgroundColor: '#5c4433' }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('movementDonutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Stock In', 'Stock Out'],
        datasets: [{
            data: [<?php echo (int)$totalInUnits; ?>, <?php echo (int)$totalOutUnits; ?>],
            backgroundColor: ['#a98a4d', '#5c4433']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require_once '../includes/footer.php'; ?>
