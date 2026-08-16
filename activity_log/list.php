<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'Activity Log';

$scopedBranch = ((int)$_SESSION['role_id'] === ROLE_BRANCH_MANAGER) ? (int)$_SESSION['branch_id'] : null;

$search = sanitize($conn, $_GET['search'] ?? '');
$actionFilter = sanitize($conn, $_GET['action'] ?? '');
$dateFrom = sanitize($conn, $_GET['from'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo = sanitize($conn, $_GET['to'] ?? date('Y-m-d'));

$sql = "SELECT al.*, u.full_name, u.email
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE DATE(al.created_at) BETWEEN '$dateFrom' AND '$dateTo'";
if ($scopedBranch) {
    $sql .= " AND u.branch_id = $scopedBranch AND u.role_id != " . ROLE_ADMIN;
}
if ($search !== '') {
    $sql .= " AND (al.description LIKE '%$search%' OR u.full_name LIKE '%$search%')";
}
if ($actionFilter !== '') {
    $sql .= " AND al.action = '$actionFilter'";
}
$sql .= " ORDER BY al.log_id DESC LIMIT 300";
$logs = $conn->query($sql);

$actionsSql = "SELECT DISTINCT al.action FROM activity_logs al LEFT JOIN users u ON al.user_id = u.user_id";
if ($scopedBranch) {
    $actionsSql .= " WHERE u.branch_id = $scopedBranch AND u.role_id != " . ROLE_ADMIN;
}
$actionsSql .= " ORDER BY al.action";
$actions = $conn->query($actionsSql);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<?php if ($scopedBranch): ?>
    <p class="text-muted small mb-2">Showing activity for your branch's team. Admins can see activity across all branches.</p>
<?php endif; ?>

<form class="row g-2 mb-3" method="GET">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search description or user" value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-3">
        <select name="action" class="form-select form-select-sm">
            <option value="">All Actions</option>
            <?php while ($a = $actions->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php echo $actionFilter === $a['action'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($a['action']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $dateFrom; ?>">
    </div>
    <div class="col-md-2">
        <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $dateTo; ?>">
    </div>
    <div class="col-md-2">
        <button class="btn btn-sm btn-pc-primary w-100">Filter</button>
    </div>
</form>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead>
            <tr><th>Date & Time</th><th>User</th><th>Action</th><th>Description</th></tr>
        </thead>
        <tbody>
        <?php if ($logs->num_rows === 0): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No activity in this range.</td></tr>
        <?php else: while ($l = $logs->fetch_assoc()): ?>
            <tr>
                <td class="text-nowrap"><?php echo date('M d, Y h:i A', strtotime($l['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($l['full_name'] ?? 'Deleted User'); ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($l['action']); ?></span></td>
                <td><?php echo htmlspecialchars($l['description']); ?></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>
    <?php if ($logs->num_rows === 300): ?>
        <p class="text-muted small mt-2 mb-0">Showing the most recent 300 matching entries. Narrow the date range or search to see more specific results.</p>
    <?php endif; ?>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
