<?php


require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;


function sendOtpEmail(string $toEmail, string $toName, string $otp, ?string &$errorOut = null): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Without an explicit timeout, PHPMailer can hang for several
        // minutes if the SMTP server is unreachable (wrong network,
        // firewall blocking port 587, etc.) before finally failing. Cap it
        // so a connection problem shows the "couldn't send" error quickly
        // instead of leaving the page stuck loading.
        $mail->Timeout = 15;
        $mail->SMTPKeepAlive = false;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Your Perfect Choice password reset code';
        $mail->Body = '
            <div style="font-family:Segoe UI,Arial,sans-serif;max-width:480px;margin:0 auto;">
                <h2 style="color:#5c4433;">Perfect Choice</h2>
                <p>Hi ' . htmlspecialchars($toName) . ',</p>
                <p>Use the code below to reset your password. It expires in 10 minutes.</p>
                <div style="font-size:32px;letter-spacing:8px;font-weight:bold;color:#3b2a20;background:#f1efe9;padding:16px 20px;border-radius:8px;text-align:center;margin:20px 0;">' . htmlspecialchars($otp) . '</div>
                <p style="color:#6b6b66;font-size:13px;">If you did not request this, you can safely ignore this email — your password will not be changed.</p>
            </div>';
        $mail->AltBody = "Your Perfect Choice password reset code is: $otp (expires in 10 minutes)";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        $errorOut = $mail->ErrorInfo;
        return false;
    }
}


function generateOtp(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendLowStockAlertEmail(string $toEmail, string $toName, string $productName, string $sku, string $branchName, int $qty, ?string &$errorOut = null): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->Timeout = 15;
        $mail->SMTPKeepAlive = false;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Low stock alert: ' . $productName . ' at ' . $branchName;
        $mail->Body = '
            <div style="font-family:Segoe UI,Arial,sans-serif;max-width:480px;margin:0 auto;">
                <h2 style="color:#5c4433;">Perfect Choice — Low Stock Alert</h2>
                <p>Hi ' . htmlspecialchars($toName) . ',</p>
                <p><strong>' . htmlspecialchars($productName) . '</strong> (' . htmlspecialchars($sku) . ')
                   at <strong>' . htmlspecialchars($branchName) . '</strong> has dropped to
                   <strong style="color:#8b3a3a;">' . (int)$qty . ' unit' . ($qty === 1 ? '' : 's') . ' left</strong>.</p>
                <p style="color:#6b6b66;font-size:13px;">Consider restocking soon to avoid running out. You can log in and record a new Stock In or Stock Transfer whenever ready.</p>
            </div>';
        $mail->AltBody = "Low stock alert: $productName ($sku) at $branchName has $qty unit(s) left.";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        $errorOut = $mail->ErrorInfo;
        return false;
    }
}
