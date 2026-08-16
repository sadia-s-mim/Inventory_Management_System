<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

$pageTitle = 'Categories';


$all = $conn->query("SELECT * FROM categories ORDER BY cat_level, category_name");
$tree = [];
$byId = [];

if ($all === false) {
    $queryError = $conn->error;
} else {
    while ($row = $all->fetch_assoc()) {
        $row['category_id'] = (int)$row['category_id'];
        $row['parent_id'] = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
        $row['children'] = [];
        $byId[$row['category_id']] = $row;
    }

    $inCycle = [];
    foreach ($byId as $startId => $row) {
        $seen = [];
        $cur = $startId;
        while ($cur !== null && isset($byId[$cur]) && !isset($seen[$cur])) {
            $seen[$cur] = true;
            $cur = $byId[$cur]['parent_id'];
        }
        if ($cur !== null && isset($seen[$cur])) {
            $inCycle[$startId] = true;
        }
    }

    // Now link children — a category with no parent, a missing parent, or
    // that sits on a cycle is placed at the top level instead. Cyclic
    // categories therefore never get linked into another node's children
    // array, so no live circular reference can form and renderTree() can
    // never recurse infinitely, no matter how tangled the stored data is.
    foreach ($byId as $id => $row) {
        $parentId = $row['parent_id'];
        if ($parentId === null || !isset($byId[$parentId]) || isset($inCycle[$id])) {
            $tree[$id] = &$byId[$id];
        } else {
            $byId[$parentId]['children'][$id] = &$byId[$id];
        }
    }
}

function renderTree($nodes) {
    echo '<ul>';
    foreach ($nodes as $id => $node) {
        $hasChildren = count($node['children']) > 0;
        echo '<li>';
        if ($hasChildren) {
            echo '<span class="tree-toggle" data-target="node-' . $id . '"><i class="bi bi-folder-minus"></i>' . htmlspecialchars($node['category_name']) . '</span>';
        } else {
            echo '<span><i class="bi bi-tag text-muted"></i> ' . htmlspecialchars($node['category_name']) . '</span>';
        }
        echo ' <a href="edit.php?id=' . $id . '" class="text-decoration-none ms-2"><i class="bi bi-pencil text-secondary"></i></a>';
        if ($_SESSION['role_id'] == ROLE_ADMIN) {
            echo ' <a href="delete.php?id=' . $id . '" class="text-decoration-none pc-confirm-delete"><i class="bi bi-trash text-danger"></i></a>';
        }
        if ($hasChildren) {
            echo '<div id="node-' . $id . '">';
            renderTree($node['children']);
            echo '</div>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0 text-muted">Gender → Group → Type hierarchy</h6>
    <a href="add.php" class="btn btn-pc-gold"><i class="bi bi-plus-lg"></i> Add Category</a>
</div>

<div class="pc-card p-4">
    <div class="pc-tree">
        <?php if (isset($queryError)): ?>
            <div class="alert alert-danger">Could not load categories: <?php echo htmlspecialchars($queryError); ?></div>
        <?php elseif (empty($tree)): ?>
            <p class="text-muted text-center py-4 mb-0">No categories yet. Click "Add Category" to create your first one.</p>
        <?php else: ?>
            <?php renderTree($tree); ?>
        <?php endif; ?>
    </div>
</div>

    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
