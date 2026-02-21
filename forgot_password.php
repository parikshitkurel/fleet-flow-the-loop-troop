<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/mail.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $stmt = db()->prepare('UPDATE users SET otp_code = ?, otp_expires = ?, otp_attempts = 0 WHERE id = ?');
            $stmt->execute([$otp, $expires, $user['id']]);

            if (sendOTPEmail($email, $otp)) {
                $_SESSION['reset_email'] = $email;
                $_SESSION['flash'] = ['msg' => 'Verification code sent to your email.', 'type' => 'success'];
                header('Location: verify_otp.php');
                exit;
            } else {
                $error = 'Failed to send verification email. Please try again.';
                if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
                    $error .= ' [Debug: Check server error_log or run test_mail.php]';
                }
            }
        } else {
            // Security: Don't reveal if account exists, but for internal tools it's often better to show the error
            $error = 'No account found with that email address.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — FleetFlow</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div style="font-size:44px;">🚛</div>
            <h2>FleetFlow</h2>
            <p>Reset Your Password</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@fleetflow.com" required autofocus>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Send Reset Link</button>
            </div>
            <div style="margin-top:16px; text-align:center;">
                <a href="login.php" style="color:var(--primary);font-weight:600;text-decoration:none;font-size:13px;">Back to Login</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
