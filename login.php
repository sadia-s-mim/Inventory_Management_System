<?php
session_start();
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($conn, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.email = ? AND u.status = 'active'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Prevent session fixation
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['branch_id'] = $user['branch_id'];
            logActivity($conn, $user['user_id'], 'Login', $user['full_name'] . ' logged in');
            redirect('dashboard.php');
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Perfect Choice</title>
<link rel="icon" type="image/svg+xml" href="assets/images/logo.svg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="pc-login-wrap">
    <div class="pc-login-card">
        <div class="text-center mb-4">
            <img src="assets/images/logo.svg" alt="Perfect Choice" class="pc-login-logo">
            <h2 class="pc-login-title">Perfect Choice</h2>
            <p class="pc-login-subtitle">Inventory Management System</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3 text-end">
                <a href="forgot_password.php" class="pc-forgot-link">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-pc-primary w-100">Sign In</button>
        </form>
    </div>
</div>
</body>
</html>
