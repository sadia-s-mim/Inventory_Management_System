<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'Edit Product';
$errors = [];
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    flash('danger', 'Product not found.');
    redirect('products/list.php');
}

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
    $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if ($name === '') $errors[] = 'Product name is required.';
    if ($categoryId <= 0) $errors[] = 'Please select a category/type.';

    if (empty($errors)) {
        $imageFilename = $product['image'];
        $newImage = handleProductImageUpload($_FILES['product_image'] ?? []);
        if ($newImage) {
            // A new photo was uploaded — remove the old one so uploads don't
            // pile up on disk, then use the new filename.
            if (!empty($product['image'])) {
                $oldPath = __DIR__ . '/../assets/images/products/' . basename($product['image']);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $imageFilename = $newImage;
        }

        $upd = $conn->prepare("UPDATE products SET sku=?, product_name=?, category_id=?, supplier_id=?, size=?, color=?, cost_price=?, selling_price=?, reorder_level=?, status=?, image=? WHERE product_id=?");
        $upd->bind_param("ssiissddissi", $sku, $name, $categoryId, $supplierId, $size, $color, $costPrice, $sellingPrice, $reorderLevel, $status, $imageFilename, $id);
        if ($upd->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Edit Product', "Updated product: $name");
            flash('success', 'Product updated successfully.');
            redirect('products/list.php');
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}

$categories = $conn->query("SELECT category_id, category_name FROM categories WHERE cat_level = 3 ORDER BY category_name");
$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM suppliers WHERE status='active' ORDER BY supplier_name");

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
                <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($product['sku']); ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label">Product Name *</label>
                <input type="text" name="product_name" class="form-control" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
            </div>

            <div class="col-md-12">
                <label class="form-label">Product Photo</label>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <img src="<?php echo productImageUrl($product['image']); ?>" alt="" class="pc-product-thumb-lg">
                </div>
                <input type="file" name="product_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                <div class="form-text">Optional. Upload a new photo to replace the current one, or leave blank to keep it.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Category / Type *</label>
                <select name="category_id" class="form-select" required>
                    <?php while ($c = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $c['category_id']; ?>" <?php echo $c['category_id'] == $product['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['category_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-select">
                    <option value="">— None —</option>
                    <?php while ($s = $suppliers->fetch_assoc()): ?>
                        <option value="<?php echo $s['supplier_id']; ?>" <?php echo $s['supplier_id'] == $product['supplier_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['supplier_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Size</label>
                <input type="text" name="size" class="form-control" value="<?php echo htmlspecialchars($product['size']); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Color</label>
                <input type="text" name="color" class="form-control" value="<?php echo htmlspecialchars($product['color']); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Cost Price (৳)</label>
                <input type="number" step="0.01" name="cost_price" class="form-control" value="<?php echo $product['cost_price']; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Selling Price (৳) *</label>
                <input type="number" step="0.01" name="selling_price" class="form-control" value="<?php echo $product['selling_price']; ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Reorder Level</label>
                <input type="number" name="reorder_level" class="form-control" value="<?php echo $product['reorder_level']; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Update Product</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
