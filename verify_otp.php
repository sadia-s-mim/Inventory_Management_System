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

if (!isset($_SESSION['pwd_reset_user_id'])) {
    redirect('forgot_password.php');
}

$userId = (int)$_SESSION['pwd_reset_user_id'];
$email = $_SESSION['pwd_reset_email'] ?? '';
$error = '';
$resent = false;
$cooldownError = false;
$devOtp = $_SESSION['pwd_reset_dev_otp'] ?? null;
$sendStatus = $_SESSION['pwd_reset_send_status'] ?? ($devOtp ? 'devmode' : 'sent');

$stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) {
    unset($_SESSION['pwd_reset_user_id'], $_SESSION['pwd_reset_email'], $_SESSION['pwd_reset_dev_otp'], $_SESSION['pwd_reset_send_status']);
    redirect('forgot_password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    // Server-side cooldown check — this is enforced here, not just hidden
    // in JS, so refreshing the page or replaying the request can't be used
    // to bypass it and spam OTP emails.
    $latestStmt = $conn->prepare("SELECT created_at FROM password_resets WHERE user_id = ? ORDER BY reset_id DESC LIMIT 1");
    $latestStmt->bind_param("i", $userId);
    $latestStmt->execute();
    $latest = $latestStmt->get_result()->fetch_assoc();
    $secondsSinceLastSend = $latest ? (time() - strtotime($latest['created_at'])) : OTP_RESEND_COOLDOWN_SECONDS;

    if ($secondsSinceLastSend < OTP_RESEND_COOLDOWN_SECONDS) {
        $cooldownError = true;
        $error = 'Please wait before requesting another code.';
    } else {
        $inv = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
        $inv->bind_param("i", $userId);
        $inv->execute();

        $otp = generateOtp();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        $ins = $conn->prepare("INSERT INTO password_resets (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
        $ins->bind_param("iss", $userId, $otp, $expiresAt);
        $ins->execute();

        $mailError = null;
        $sent = false;
        if (!MAIL_DEV_FALLBACK) {
            $sent = sendOtpEmail($email, $user['full_name'], $otp, $mailError);
        }

        if ($sent) {
            $sendStatus = 'sent';
            $devOtp = null;
        } elseif (MAIL_DEV_FALLBACK) {
            $sendStatus = 'devmode';
            $devOtp = $otp;
        } else {
            $sendStatus = 'error';
            $devOtp = $otp;
            logActivity($conn, $userId, 'Password Reset Email Failed', "Failed to resend OTP to " . $email . ": " . ($mailError ?? 'unknown'));
            if ($mailError) {
                error_log('OTP resend failed for user_id ' . $userId . ': ' . $mailError);
            }
        }
        $_SESSION['pwd_reset_dev_otp'] = $devOtp;
        $_SESSION['pwd_reset_send_status'] = $sendStatus;
        $resent = true;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = sanitize($conn, $_POST['otp'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE user_id = ? AND used = 0 ORDER BY reset_id DESC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();

    if (!$reset || strtotime($reset['expires_at']) < time()) {
        $error = 'This code has expired. Please request a new one.';
    } elseif ((int)$reset['attempts'] >= 5) {
        $upd = $conn->prepare("UPDATE password_resets SET used = 1 WHERE reset_id = ?");
        $upd->bind_param("i", $reset['reset_id']);
        $upd->execute();
        $error = 'Too many incorrect attempts. Please request a new code.';
    } elseif (!hash_equals($reset['otp_code'], $code)) {
        $upd = $conn->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE reset_id = ?");
        $upd->bind_param("i", $reset['reset_id']);
        $upd->execute();
        $error = 'Incorrect code. Please try again.';
    } else {
        $upd = $conn->prepare("UPDATE password_resets SET verified = 1 WHERE reset_id = ?");
        $upd->bind_param("i", $reset['reset_id']);
        $upd->execute();
        $_SESSION['pwd_reset_verified_id'] = (int)$reset['reset_id'];
        redirect('reset_password.php');
    }
}

// Fetch the currently active code's timestamps (fresh, post any resend
// above) so the countdowns shown to the user always reflect the code
// that's actually valid right now — not a stale value from page load.
$activeStmt = $conn->prepare("SELECT created_at, expires_at FROM password_resets WHERE user_id = ? AND used = 0 ORDER BY reset_id DESC LIMIT 1");
$activeStmt->bind_param("i", $userId);
$activeStmt->execute();
$active = $activeStmt->get_result()->fetch_assoc();

$expiresAtMs = $active ? strtotime($active['expires_at']) * 1000 : 0;
$resendAvailableAtMs = $active ? (strtotime($active['created_at']) + OTP_RESEND_COOLDOWN_SECONDS) * 1000 : 0;
$serverNowMs = time() * 1000;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Code — Perfect Choice</title>
<link rel="icon" type="image/svg+xml" href="assets/images/logo.svg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="pc-login-wrap">
    <div class="pc-login-card">
        <div class="text-center mb-4">
            <i class="bi bi-shield-check pc-login-icon"></i>
            <h2 class="pc-login-title">Enter Verification Code</h2>
            <p class="pc-login-subtitle">Sent to <?php echo htmlspecialchars($email); ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($resent && $sendStatus === 'sent'): ?>
            <div class="alert alert-success py-2">A new code has been sent.</div>
        <?php endif; ?>
        <?php if ($sendStatus === 'devmode'): ?>
            <div class="alert alert-info py-2 pc-form-hint">
                <strong>Demo mode:</strong> email sending isn't set up yet, so
                your code is shown below instead. To send real emails, add a
                Gmail App Password in <code>config/mail.php</code>.
            </div>
            <div class="pc-otp-preview">Code: <strong><?php echo htmlspecialchars($devOtp); ?></strong></div>
        <?php elseif ($sendStatus === 'error'): ?>
            <div class="alert alert-danger py-2 pc-form-hint">
                <strong>Couldn't send the email.</strong> SMTP is configured but
                sending failed — double-check the Gmail App Password in
                <code>config/mail.php</code>. Your code is shown below so
                you're not blocked in the meantime.
            </div>
            <div class="pc-otp-preview">Code: <strong><?php echo htmlspecialchars($devOtp); ?></strong></div>
        <?php endif; ?>

        <p class="pc-otp-timer text-center" id="expiryTimer"></p>

        <form method="POST" action="verify_otp.php" id="otpForm">
            <div class="mb-3">
                <label class="form-label">6-Digit Code</label>
                <input type="text" name="otp" class="form-control pc-otp-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
            </div>
            <button type="submit" class="btn btn-pc-primary w-100" id="verifyBtn">Verify Code</button>
        </form>

        <form method="POST" action="verify_otp.php" class="mt-2" id="resendForm">
            <input type="hidden" name="resend" value="1">
            <button type="submit" class="btn btn-link w-100 pc-resend-link" id="resendBtn">Resend Code</button>
        </form>

        <p class="text-center pc-login-footer-link"><a href="login.php">← Back to Login</a></p>
    </div>
</div>

<script>
(function () {
    
    var serverNowMs = <?php echo (int)$serverNowMs; ?>;
    var expiresAtMs = <?php echo (int)$expiresAtMs; ?>;
    var resendAvailableAtMs = <?php echo (int)$resendAvailableAtMs; ?>;
    var clientLoadMs = Date.now();
    var clockOffset = clientLoadMs - serverNowMs; 

    var expiryEl = document.getElementById('expiryTimer');
    var verifyBtn = document.getElementById('verifyBtn');
    var resendBtn = document.getElementById('resendBtn');
    var resendDefaultText = 'Resend Code';

    function formatMMSS(totalSeconds) {
        var m = Math.floor(totalSeconds / 60);
        var s = totalSeconds % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function tick() {
        var nowMs = Date.now() - clockOffset;

        // Expiry countdown
        var remainingExpiry = Math.max(0, Math.round((expiresAtMs - nowMs) / 1000));
        if (remainingExpiry > 0) {
            expiryEl.textContent = 'Code expires in ' + formatMMSS(remainingExpiry);
            expiryEl.classList.remove('pc-otp-timer-expired');
            verifyBtn.disabled = false;
        } else {
            expiryEl.textContent = 'This code has expired — request a new one.';
            expiryEl.classList.add('pc-otp-timer-expired');
            verifyBtn.disabled = true;
        }

      
        var remainingCooldown = Math.max(0, Math.round((resendAvailableAtMs - nowMs) / 1000));
        if (remainingCooldown > 0) {
            resendBtn.disabled = true;
            resendBtn.textContent = 'Resend available in ' + remainingCooldown + 's';
        } else {
            resendBtn.disabled = false;
            resendBtn.textContent = resendDefaultText;
        }
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
</body>
</html>
