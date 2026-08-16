<?php

function sanitize($conn, $value) {
    return $conn->real_escape_string(trim($value));
}

function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit();
}

function logActivity($conn, $userId, $action, $description = '') {
    $action = sanitize($conn, $action);
    $description = sanitize($conn, $description);
    $conn->query("INSERT INTO activity_logs (user_id, action, description) VALUES ('$userId', '$action', '$description')");
}

function formatMoney($amount) {
    return number_format((float)$amount, 2);
}

function roleName($roleId) {
    switch ((int)$roleId) {
        case ROLE_ADMIN: return 'Admin';
        case ROLE_BRANCH_MANAGER: return 'Branch Manager';
        case ROLE_SALES_USER: return 'Sales User';
        default: return 'Unknown';
    }
}

// Returns current stock quantity for a product at a branch (0 if none)
function getStockQty($conn, $productId, $branchId) {
    $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND branch_id = ?");
    $stmt->bind_param("ii", $productId, $branchId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        return (int)$row['quantity'];
    }
    return 0;
}

// Adjusts (increments/decrements) stock for a product at a branch, creating the row if needed
function adjustStock($conn, $productId, $branchId, $delta) {
    $current = getStockQty($conn, $productId, $branchId);
    $exists = $current !== null;
    $stmt = $conn->prepare("SELECT inventory_id FROM inventory WHERE product_id = ? AND branch_id = ?");
    $stmt->bind_param("ii", $productId, $branchId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $upd = $conn->prepare("UPDATE inventory SET quantity = quantity + ? WHERE product_id = ? AND branch_id = ?");
        $upd->bind_param("iii", $delta, $productId, $branchId);
        $upd->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO inventory (product_id, branch_id, quantity) VALUES (?, ?, ?)");
        $ins->bind_param("iii", $productId, $branchId, $delta);
        $ins->execute();
    }
}

// Checks reorder level and creates a notification if low
function checkLowStock($conn, $productId, $branchId, $qtyBefore = null) {
    $qty = getStockQty($conn, $productId, $branchId);
    $stmt = $conn->prepare("SELECT product_name, sku, reorder_level FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    if (!$product) return;

    if ($qty <= (int)$product['reorder_level']) {
        $msg = "Low stock: " . $product['product_name'] . " (" . $qty . " left)";
        $msgEsc = sanitize($conn, $msg);
        $conn->query("INSERT INTO notifications (product_id, branch_id, message) VALUES ('$productId', '$branchId', '$msgEsc')");
    }

    // Separate, fixed-threshold EMAIL alert (independent of each product's
    // own reorder_level). Only fires the moment stock crosses below the
    // threshold — not on every later sale while it stays low — so passing
    // the pre-change quantity avoids spamming recipients.
    $justCrossed = ($qtyBefore === null) || ((int)$qtyBefore >= LOW_STOCK_EMAIL_THRESHOLD);
    if ($qty < LOW_STOCK_EMAIL_THRESHOLD && $justCrossed) {
        notifyLowStockByEmail($conn, $branchId, $product['product_name'], $product['sku'] ?? '', $qty);
    }
}

// Emails every active Admin, plus the Branch Manager assigned to the given
// branch, that a product has dropped below the low-stock email threshold.
// Silently does nothing if SMTP isn't configured yet (MAIL_DEV_FALLBACK) —
// this runs as a side effect of a sale/transfer, not an interactive flow,
// so there's no page to show a "demo mode" banner on.
function notifyLowStockByEmail($conn, $branchId, $productName, $sku, $qty) {
    require_once __DIR__ . '/../config/mail.php';
    require_once __DIR__ . '/mailer.php';

    if (MAIL_DEV_FALLBACK) {
        return;
    }

    $branchStmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
    $branchStmt->bind_param("i", $branchId);
    $branchStmt->execute();
    $branch = $branchStmt->get_result()->fetch_assoc();
    $branchName = $branch['branch_name'] ?? 'Unknown Branch';

    $adminRole = ROLE_ADMIN;
    $managerRole = ROLE_BRANCH_MANAGER;
    $recipStmt = $conn->prepare("SELECT DISTINCT email, full_name FROM users WHERE status = 'active' AND (role_id = ? OR (role_id = ? AND branch_id = ?))");
    $recipStmt->bind_param("iii", $adminRole, $managerRole, $branchId);
    $recipStmt->execute();
    $recipients = $recipStmt->get_result();

    while ($r = $recipients->fetch_assoc()) {
        $mailError = null;
        $sent = sendLowStockAlertEmail($r['email'], $r['full_name'], $productName, $sku, $branchName, $qty, $mailError);
        if (!$sent && $mailError) {
            error_log('Low stock alert email failed for ' . $r['email'] . ': ' . $mailError);
        }
    }
}

function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// Streams a result set out as a downloadable CSV file and ends the
// request. $columns maps the header label shown in the CSV to the field
// name to pull from each row of the mysqli result.
function exportResultAsCsv($filename, $result, $columns) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, array_keys($columns));

    while ($row = $result->fetch_assoc()) {
        $line = [];
        foreach ($columns as $field) {
            $line[] = $row[$field] ?? '';
        }
        fputcsv($out, $line);
    }

    fclose($out);
    exit();
}

// Returns a safe, browser-usable URL for a product's photo, falling back
// to a generic placeholder icon when no image was uploaded (or the file
// is missing on disk for any reason).
function productImageUrl($imageFilename) {
    if (!empty($imageFilename)) {
        $path = __DIR__ . '/../assets/images/products/' . basename($imageFilename);
        if (is_file($path)) {
            return BASE_URL . 'assets/images/products/' . rawurlencode(basename($imageFilename));
        }
    }
    return BASE_URL . 'assets/images/product-placeholder.svg';
}

// Handles an uploaded product photo: validates type/size, saves it with a
// random filename under assets/images/products/, and returns the stored
// filename (or null if no file was uploaded / upload failed validation).
function handleProductImageUpload($fileInput) {
    if (empty($fileInput['name']) || $fileInput['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($fileInput['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $maxBytes = 2 * 1024 * 1024; // 2MB

    if ($fileInput['size'] > $maxBytes) {
        return null;
    }

    $ext = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        return null;
    }

    // Verify it's actually an image (not just a renamed file)
    $imageInfo = @getimagesize($fileInput['tmp_name']);
    if ($imageInfo === false) {
        return null;
    }

    $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = __DIR__ . '/../assets/images/products/' . $newFilename;

    if (move_uploaded_file($fileInput['tmp_name'], $destination)) {
        return $newFilename;
    }
    return null;
}
