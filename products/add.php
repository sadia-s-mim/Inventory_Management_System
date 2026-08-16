<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'Add Product';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = sanitize($conn, $_POST['sku']);
    $name = sanitize($conn, $_POST['product_name']);
    $categoryId = (int)$_POST['category_id'];
    $supplierId = (int)$_POST['supplier_id'] ?: null;
    $size = sanitize($conn, $_POST['size']);
    $color = sanitize($conn, $_POST['color']);
    $costPrice = (float)$_POST['cost_price'];
    $sellingPrice = (float)$_POST['selling_price'];
    $reorderLevel = (int)$_POST['reorder_level'];
    $openingQty = (int)$_POST['opening_qty'];
    $branchId = (int)$_POST['branch_id'];

    if ($name === '') $errors[] = 'Product name is required.';
    if ($categoryId <= 0) $errors[] = 'Please select a category/type.';
    if ($sellingPrice <= 0) $errors[] = 'Selling price must be greater than zero.';

    if (empty($errors)) {
        $imageFilename = handleProductImageUpload($_FILES['product_image'] ?? []);

        $stmt = $conn->prepare("INSERT INTO products (sku, product_name, category_id, supplier_id, size, color, cost_price, selling_price, reorder_level, image) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssiissddis", $sku, $name, $categoryId, $supplierId, $size, $color, $costPrice, $sellingPrice, $reorderLevel, $imageFilename);
        if ($stmt->execute()) {
            $productId = $stmt->insert_id;
            if ($openingQty > 0 && $branchId > 0) {
                adjustStock($conn, $productId, $branchId, $openingQty);
            }
            logActivity($conn, $_SESSION['user_id'], 'Add Product', "Added product: $name");
            flash('success', 'Product added successfully.');
            redirect('products/list.php');
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}

$categories = $conn->query("SELECT category_id, category_name FROM categories WHERE cat_level = 3 ORDER BY category_name");
$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM suppliers WHERE status='active' ORDER BY supplier_name");
$branches = $conn->query("SELECT branch_id, branch_name FROM branches WHERE status='active' ORDER BY branch_name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="pc-card p-4 pc-form-card-wide">
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control" placeholder="Auto or custom code">
            </div>
            <div class="col-md-8">
                <label class="form-label">Product Name *</label>
                <input type="text" name="product_name" class="form-control" required>
            </div>

            <div class="col-md-12">
                <label class="form-label">Product Photo</label>
                <input type="file" name="product_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                <div class="form-text">Optional. JPG, PNG, or WEBP, up to 2MB. Leave blank to use a placeholder.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Category / Type *</label>
                <select name="category_id" class="form-select" required>
                    <option value="">Select type</option>
                    <?php while ($c = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $c['category_id']; ?>"><?php echo htmlspecialchars($c['category_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-select">
                    <option value="">— None —</option>
                    <?php while ($s = $suppliers->fetch_assoc()): ?>
                        <option value="<?php echo $s['supplier_id']; ?>"><?php echo htmlspecialchars($s['supplier_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Size</label>
                <input type="text" name="size" class="form-control" placeholder="e.g. M, 38">
            </div>
            <div class="col-md-3">
                <label class="form-label">Color</label>
                <input type="text" name="color" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Cost Price (৳)</label>
                <input type="number" step="0.01" name="cost_price" class="form-control" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label">Selling Price (৳) *</label>
                <input type="number" step="0.01" name="selling_price" class="form-control" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Reorder Level</label>
                <input type="number" name="reorder_level" class="form-control" value="10">
            </div>
            <div class="col-md-4">
                <label class="form-label">Opening Stock Qty</label>
                <input type="number" name="opening_qty" class="form-control" value="0">
            </div>
            <div class="col-md-4">
                <label class="form-label">Branch (for opening stock)</label>
                <select name="branch_id" class="form-select">
                    <option value="">— Select —</option>
                    <?php while ($b = $branches->fetch_assoc()): ?>
                        <option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Save Product</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
