<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
requireRole([ROLE_ADMIN, ROLE_BRANCH_MANAGER]);

$pageTitle = 'Edit Category';
$errors = [];
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();

if (!$category) {
    flash('danger', 'Category not found.');
    redirect('categories/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($conn, $_POST['category_name']);
    $parentId = (int)$_POST['parent_id'] ?: null;
    $catLevel = (int)$_POST['level'];

    if ($name === '') $errors[] = 'Category name is required.';

    if (empty($errors)) {
        $upd = $conn->prepare("UPDATE categories SET category_name=?, parent_id=?, cat_level=? WHERE category_id=?");
        $upd->bind_param("siii", $name, $parentId, $catLevel, $id);
        if ($upd->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Edit Category', "Updated category: $name");
            flash('success', 'Category updated successfully.');
            redirect('categories/list.php');
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}


$excludeIds = [$id];
$frontier = [$id];
while (!empty($frontier)) {
    $placeholders = implode(',', array_fill(0, count($frontier), '?'));
    $childStmt = $conn->prepare("SELECT category_id FROM categories WHERE parent_id IN ($placeholders)");
    $childStmt->bind_param(str_repeat('i', count($frontier)), ...$frontier);
    $childStmt->execute();
    $childRes = $childStmt->get_result();
    $frontier = [];
    while ($c = $childRes->fetch_assoc()) {
        $childId = (int)$c['category_id'];
        if (!in_array($childId, $excludeIds, true)) {
            $excludeIds[] = $childId;
            $frontier[] = $childId;
        }
    }
}

$excludePlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));
$parentsSql = "SELECT category_id, category_name, cat_level FROM categories WHERE category_id NOT IN ($excludePlaceholders) ORDER BY cat_level, category_name";
$parentsStmt = $conn->prepare($parentsSql);
$parentsStmt->bind_param(str_repeat('i', count($excludeIds)), ...$excludeIds);
$parentsStmt->execute();
$parents = $parentsStmt->get_result();

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
                <option value="1" <?php echo $category['cat_level']==1?'selected':''; ?>>1 — Gender</option>
                <option value="2" <?php echo $category['cat_level']==2?'selected':''; ?>>2 — Group</option>
                <option value="3" <?php echo $category['cat_level']==3?'selected':''; ?>>3 — Type</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Parent Category</label>
            <select name="parent_id" class="form-select">
                <option value="">— None (top level) —</option>
                <?php while ($p = $parents->fetch_assoc()): ?>
                    <option value="<?php echo $p['category_id']; ?>" <?php echo $p['category_id']==$category['parent_id']?'selected':''; ?>>
                        <?php echo str_repeat('— ', $p['cat_level'] - 1) . htmlspecialchars($p['category_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Category Name *</label>
            <input type="text" name="category_name" class="form-control" value="<?php echo htmlspecialchars($category['category_name']); ?>" required>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-pc-primary">Update Category</button>
            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
