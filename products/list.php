<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$pageTitle = 'Products';

$search = sanitize($conn, $_GET['search'] ?? '');
$categoryFilter = (int)($_GET['category'] ?? 0);

$sql = "SELECT p.*, c.category_name, s.supplier_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
        WHERE 1=1";

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE '%$search%' OR p.sku LIKE '%$search%')";
}
if ($categoryFilter > 0) {
    $sql .= " AND p.category_id = $categoryFilter";
}
$sql .= " ORDER BY p.created_at DESC";
$products = $conn->query($sql);

$categories = $conn->query("SELECT category_id, category_name FROM categories WHERE cat_level = 3 ORDER BY category_name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2 pc-toolbar-search-wide" method="GET">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or SKU" value="<?php echo htmlspecialchars($search); ?>">
        <select name="category" class="form-select form-select-sm">
            <option value="0">All Types</option>
            <?php while ($c = $categories->fetch_assoc()): ?>
                <option value="<?php echo $c['category_id']; ?>" <?php echo $categoryFilter == $c['category_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['category_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button class="btn btn-sm btn-pc-primary"><i class="bi bi-search"></i></button>
    </form>
    <a href="add.php" class="btn btn-pc-gold"><i class="bi bi-plus-lg"></i> Add Product</a>
</div>

<div class="pc-card p-3">
    <div class="table-responsive">
<table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Photo</th>
                <th>SKU</th>
                <th>Product</th>
                <th>Type</th>
                <th>Size / Color</th>
                <?php if ((int)$_SESSION['role_id'] !== ROLE_SALES_USER): ?>
                <th>Supplier</th>
                <?php endif; ?>
                <th>Cost</th>
                <th>Price</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if ($products->num_rows === 0): ?>
            <tr><td colspan="<?php echo ((int)$_SESSION['role_id'] !== ROLE_SALES_USER) ? 10 : 9; ?>" class="text-center text-muted py-4">No products found.</td></tr>
        <?php else: while ($p = $products->fetch_assoc()): ?>
            <tr>
                <td><img src="<?php echo productImageUrl($p['image']); ?>" alt="" class="pc-product-thumb"></td>
                <td><?php echo htmlspecialchars($p['sku']); ?></td>
                <td><?php echo htmlspecialchars($p['product_name']); ?></td>
                <td><?php echo htmlspecialchars($p['category_name'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($p['size']); ?> / <?php echo htmlspecialchars($p['color']); ?></td>
                <?php if ((int)$_SESSION['role_id'] !== ROLE_SALES_USER): ?>
                <td><?php echo htmlspecialchars($p['supplier_name'] ?? '—'); ?></td>
                <?php endif; ?>
                <td>৳<?php echo formatMoney($p['cost_price']); ?></td>
                <td>৳<?php echo formatMoney($p['selling_price']); ?></td>
                <td><span class="badge <?php echo $p['status'] === 'active' ? 'badge-ok' : 'bg-secondary'; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                <td class="text-end">
                    <a href="edit.php?id=<?php echo $p['product_id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <?php if ((int)$_SESSION['role_id'] === ROLE_ADMIN): ?>
                    <a href="delete.php?id=<?php echo $p['product_id']; ?>" class="btn btn-sm btn-outline-danger pc-confirm-delete"><i class="bi bi-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
