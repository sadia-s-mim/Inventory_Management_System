<?php
session_start();
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

if (!isset($_SESSION['pwd_reset_verified_id'])) {
    redirect('forgot_password.php');
}

$resetId = (int)$_SESSION['pwd_reset_verified_id'];
$error = '';
$success = false;

$stmt = $conn->prepare("SELECT pr.*, u.full_name, u.email FROM password_resets pr JOIN users u ON pr.user_id = u.user_id WHERE pr.reset_id = ? AND pr.verified = 1 AND pr.used = 0");
$stmt->bind_param("i", $resetId);
$stmt->execute();
$reset = $stmt->get_result()->fetch_assoc();

if (!$reset || strtotime($reset['expires_at']) < time()) {
    unset($_SESSION['pwd_reset_user_id'], $_SESSION['pwd_reset_email'], $_SESSION['pwd_reset_verified_id'], $_SESSION['pwd_reset_dev_otp'], $_SESSION['pwd_reset_send_status']);
    $error = 'This verification session has expired. Please start over.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $upd->bind_param("si", $hash, $reset['user_id']);
        $upd->execute();

        $markUsed = $conn->prepare("UPDATE password_resets SET used = 1 WHERE reset_id = ?");
        $markUsed->bind_param("i", $reset['reset_id']);
        $markUsed->execute();

        logActivity($conn, $reset['user_id'], 'Password Reset', $reset['full_name'] . ' reset their password');
        unset($_SESSION['pwd_reset_user_id'], $_SESSION['pwd_reset_email'], $_SESSION['pwd_reset_verified_id'], $_SESSION['pwd_reset_dev_otp'], $_SESSION['pwd_reset_send_status']);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — Perfect Choice</title>
<link rel="icon" type="image/svg+xml" href="assets/images/logo.svg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="pc-login-wrap">
    <div class="pc-login-card">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock-fill pc-login-icon"></i>
            <h2 class="pc-login-title">Set New Password</h2>
            <p class="pc-login-subtitle">Perfect Choice Inventory Management</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success py-2">Your password has been updated. You can now log in.</div>
            <a href="login.php" class="btn btn-pc-primary w-100">Go to Login</a>
        <?php elseif ($error && !$reset): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
            <a href="forgot_password.php" class="btn btn-pc-primary w-100">Start Over</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <p class="pc-form-hint">Resetting password for <strong><?php echo htmlspecialchars($reset['email']); ?></strong></p>
            <form method="POST" action="reset_password.php">
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6" autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-pc-primary w-100">Update Password</button>
            </form>
        <?php endif; ?>

        <p class="text-center pc-login-footer-link"><a href="login.php">← Back to Login</a></p>
    </div>
</div>
</body>
</html>
