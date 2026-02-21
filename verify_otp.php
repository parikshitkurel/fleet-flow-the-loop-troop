<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit;
}

$error = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    $stmt = db()->prepare('SELECT id, otp_code, otp_expires, otp_attempts FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['otp_attempts'] >= 5) {
            $error = 'Too many failed attempts. Please request a new code.';
        } elseif (strtotime($user['otp_expires']) < time()) {
            $error = 'Verification code has expired. Please request a new one.';
        } elseif ($user['otp_code'] !== $otp) {
            // Increment attempts
            $stmt = db()->prepare('UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id = ?');
            $stmt->execute([$user['id']]);
            $error = 'Invalid verification code. Attempts remaining: ' . (4 - $user['otp_attempts']);
        } else {
            // Correct OTP
            $_SESSION['otp_verified'] = true;
            header('Location: reset_password.php');
            exit;
        }
    } else {
        header('Location: forgot_password.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP — FleetFlow</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div style="font-size:44px; color: var(--primary);">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polyline points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <h2>FleetFlow</h2>
            <p>Verification Required</p>
        </div>

        <?= flash() ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <p style="text-align:center; font-size:13px; color:var(--text-sm); margin-bottom:20px;">
            We've sent a 6-digit code to <strong><?= e($email) ?></strong>.
        </p>

        <form method="POST">
            <div class="form-group">
                <label>Verification Code</label>
                <input type="text" name="otp" class="form-control" placeholder="000000" pattern="\d{6}" maxlength="6" required autofocus style="text-align:center; letter-spacing:8px; font-size:24px; font-weight:700;">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Verify Code</button>
            </div>
            <div style="margin-top:16px; text-align:center;">
                <p style="font-size:12px; color:var(--text-sm);">Didn't receive the code? <a href="forgot_password.php" style="color:var(--primary); font-weight:600; text-decoration:none;">Resend Code</a></p>
            </div>
        </form>
    </div>
</div>
</body>
</html>
