<?php
session_start();
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'config/mail.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';

if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$submitted = false;
$foundAccount = false;
$devOtp = null; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($conn, $_POST['email'] ?? '');
    $submitted = true;

    if ($email !== '') {
        $stmt = $conn->prepare("SELECT user_id, full_name, email FROM users WHERE email = ? AND status = 'active'");
        if (!$stmt) {
            
            error_log('DB prepare failed in forgot_password.php: ' . $conn->error);
            $foundAccount = false;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        }

        if ($user) {
            
            $inv = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
            $inv->bind_param("i", $user['user_id']);
            $inv->execute();

            $otp = generateOtp();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

            $ins = $conn->prepare("INSERT INTO password_resets (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("iss", $user['user_id'], $otp, $expiresAt);
            $ins->execute();

            logActivity($conn, $user['user_id'], 'Password Reset Requested', $user['full_name'] . ' requested a password reset code');

            $mailError = null;
            $sent = false;
            
            if (!MAIL_DEV_FALLBACK) {
                $sent = sendOtpEmail($user['email'], $user['full_name'], $otp, $mailError);
            }

            
            if ($sent) {
                $sendStatus = 'sent';
            } elseif (MAIL_DEV_FALLBACK) {
                $sendStatus = 'devmode';
                $devOtp = $otp;
            } else {
                $sendStatus = 'error';
                $devOtp = $otp; 
                logActivity($conn, $user['user_id'], 'Password Reset Email Failed', "Failed to send OTP to " . $user['email'] . ": " . ($mailError ?? 'unknown'));
                if ($mailError) {
                    error_log('OTP email send failed for user_id ' . $user['user_id'] . ': ' . $mailError);
                }
            }

            $_SESSION['pwd_reset_user_id'] = $user['user_id'];
            $_SESSION['pwd_reset_email'] = $user['email'];
            $_SESSION['pwd_reset_dev_otp'] = $devOtp;
            $_SESSION['pwd_reset_send_status'] = $sendStatus;

            $foundAccount = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — Perfect Choice</title>
<link rel="icon" type="image/svg+xml" href="assets/images/logo.svg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="pc-login-wrap">
    <div class="pc-login-card">
        <div class="text-center mb-4">
            <i class="bi bi-key-fill pc-login-icon"></i>
            <h2 class="pc-login-title">Forgot Password</h2>
            <p class="pc-login-subtitle">Perfect Choice Inventory Management</p>
        </div>

        <?php if (!$submitted): ?>
            <p class="pc-form-hint">Enter the email address on your account. We'll send a 6-digit verification code to it.</p>
            <form method="POST" action="forgot_password.php">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required autofocus>
                </div>
                <button type="submit" class="btn btn-pc-primary w-100">Send Verification Code</button>
            </form>
        <?php elseif ($foundAccount): ?>
            <?php if ($sendStatus === 'sent'): ?>
                <div class="alert alert-success py-2">A verification code has been sent to that email address.</div>
            <?php elseif ($sendStatus === 'devmode'): ?>
                <div class="alert alert-info py-2 pc-form-hint">
                    <strong>Demo mode:</strong> email sending isn't set up yet, so
                    your code is shown below instead. To send real emails, add a
                    Gmail App Password in <code>config/mail.php</code> (see the
                    README's Email Setup section).
                </div>
                <div class="pc-otp-preview">Code: <strong><?php echo htmlspecialchars($devOtp); ?></strong></div>
            <?php else: ?>
                <div class="alert alert-danger py-2 pc-form-hint">
                    <strong>Couldn't send the email.</strong> SMTP is configured
                    but sending failed — double-check the Gmail App Password and
                    settings in <code>config/mail.php</code>. Your code is shown
                    below so you're not blocked in the meantime.
                </div>
                <div class="pc-otp-preview">Code: <strong><?php echo htmlspecialchars($devOtp); ?></strong></div>
            <?php endif; ?>
            <a href="verify_otp.php" class="btn btn-pc-gold w-100 mt-2">Enter Verification Code</a>
        <?php else: ?>
            <div class="alert alert-success py-2">If an account exists for that email, a verification code has been sent.</div>
        <?php endif; ?>

        <p class="text-center pc-login-footer-link"><a href="login.php">← Back to Login</a></p>
    </div>
</div>
</body>
</html>
