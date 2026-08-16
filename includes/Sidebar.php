<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
function navActive($dirOrFile, $currentDir, $currentPage) {
    return ($dirOrFile === $currentDir || $dirOrFile === $currentPage) ? 'active' : '';
}
?>
<nav class="pc-sidebar">
    <div class="pc-brand">
        <i class="bi bi-bag-heart-fill"></i>
        <span>Perfect Choice</span>
    </div>
    <ul class="pc-menu">
        <li class="<?php echo navActive('dashboard.php', $currentDir, $currentPage); ?>">
            <a href="<?php echo BASE_URL; ?>dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>
        <li class="<?php echo navActive('products', $currentDir, $currentPage); ?>">
            <a href="<?php echo BASE_URL; ?>products/list.php"><i class="bi bi-box-seam"></i> Products</a>
        </li>
        <li class="<?php echo navActive('categories', $currentDir, $currentPage); ?>">
            <a href="<?php echo BASE_URL; ?>categories/list.php"><i class="bi bi-diagram-3"></i> Categories</a>
        </li>
        <li class="<?php echo navActive('stock_in', $currentDir, $currentPage); ?>">
            <a href="<?php echo BASE_URL; ?>stock_in/list.php"><i class="bi bi-box-arrow-in-down"></i> Stock In</a>
        </li>
        <li class="<?php echo navActive('stock_out', $currentDir, $currentPage); ?>">
            <a href="<?php echo BASE_URL; ?>stock_out/list.php"><i class="bi bi-box-arrow-up"></i> Stock Out</a>
        </li>
        <?php if ((int)$_SESSION['role_id'] !== ROLE_SALES_USER): ?>
        <li class="<?php echo navActive('suppliers', $currentDir, $currentPage); ?>">
            <a href="<?php echo BASE_URL; ?>suppliers/list.php"><i class="bi bi-truck"></i> Suppliers</a>
        </li>
        <?php endif; ?>
        <li class="<?php echo navActive('reports', $currentDir, $currentPage); ?>">
            <a href="<?php echo BASE_URL; ?>reports/inventory_report.php"><i class="bi bi-bar-chart-line"></i> Reports</a>
        </li>
        <?php if ((int)$_SESSION['role_id'] === ROLE_ADMIN): ?>
        <li class="<?php echo navActive('branches', $currentDir, $currentPage); ?>">
            <a href="<?php echo BASE_URL; ?>branches/list.php"><i class="bi bi-shop"></i> Branches</a>
        </li>
        <li class="<?php echo navActive('users', $currentDir, $currentPage); ?>">
            <a href="<?php echo BASE_URL; ?>users/list.php"><i class="bi bi-people"></i> Users</a>
        </li>
        <?php endif; ?>
    </ul>
    <div class="pc-sidebar-footer">
        <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</nav>

<div class="pc-main">
    <header class="pc-topbar d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></h5>
        <div class="pc-user d-flex align-items-center gap-2">
            <div class="text-end">
                <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                <small class="text-muted"><?php echo roleName($_SESSION['role_id']); ?></small>
            </div>
            <div class="pc-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
        </div>
    </header>
    <main class="pc-content">
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
