<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'Add Category';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($conn, $_POST['category_name']);
    $parentId = (int)$_POST['parent_id'] ?: null;
    $catLevel = (int)$_POST['level'];

    if ($name === '') $errors[] = 'Category name is required.';

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO categories (category_name, parent_id, cat_level) VALUES (?,?,?)");
        $stmt->bind_param("sii", $name, $parentId, $catLevel);
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Add Category', "Added category: $name");
            flash('success', 'Category added successfully.');
            redirect('categories/list.php');
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}

$parents = $conn->query("SELECT category_id, category_name, cat_level FROM categories ORDER BY cat_level, category_name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="pc-card p-4 pc-form-card">
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger py-2"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Level *</label>
            <select name="level" class="form-select" required>
                <option value="1">1 — Gender (top level, e.g. Male/Female)</option>
                <option value="2">2 — Group (e.g. Clothing/Shoes)</option>
                <option value="3" selected>3 — Type (e.g. Abaya, Heels)</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Parent Category</label>
            <select name="parent_id" class="form-select">
                <option value="">— None (top level) —</option>
                <?php while ($p = $parents->fetch_assoc()): ?>
                    <option value="<?php echo $p['category_id']; ?>">
                        <?php echo str_repeat('— ', $p['cat_level'] - 1) . htmlspecialchars($p['category_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Category Name *</label>
            <input type="text" name="category_name" class="form-control" required>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Save Category</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
