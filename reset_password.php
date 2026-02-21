<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['reset_email']) || empty($_SESSION['otp_verified'])) {
    header('Location: forgot_password.php');
    exit;
}

$error = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        
        $stmt = db()->prepare('UPDATE users SET password = ?, otp_code = NULL, otp_expires = NULL, otp_attempts = 0 WHERE email = ?');
        $stmt->execute([$hashed, $email]);

        unset($_SESSION['reset_email']);
        unset($_SESSION['otp_verified']);

        $_SESSION['flash'] = ['msg' => 'Your password has been reset successfully. Please login with your new password.', 'type' => 'success'];
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — FleetFlow</title>
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
            <p>Set New Password</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required autofocus>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
