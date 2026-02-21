<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;
        header('Location: dashboard.php');
        exit;
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
    <title>Login — FleetFlow</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div style="font-size:44px;">🚛</div>
            <h2>FleetFlow</h2>
            <p>Fleet & Logistics Management</p>
        </div>

        <?= flash() ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@fleetflow.com" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Sign In</button>
            </div>
            <div style="margin-top:8px; text-align:right;">
                <a href="forgot_password.php" style="color:var(--text-sm);text-decoration:none;font-size:12px;">Forgot Password?</a>
            </div>
            <div style="margin-top:16px; text-align:center;">
                <p style="font-size:13px;color:var(--text-sm);">Don't have an account? <a href="register.php" style="color:var(--primary);font-weight:600;text-decoration:none;">Create Account</a></p>
            </div>
        </form>

        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
            <p style="font-size:12px;color:var(--text-sm);margin-bottom:8px;font-weight:600;">Demo Accounts (password: <code>password</code>)</p>
            <table style="width:100%;font-size:12px;color:var(--text-sm);">
                <tr><td>manager@fleetflow.com</td><td>Fleet Manager</td></tr>
                <tr><td>dispatch@fleetflow.com</td><td>Dispatcher</td></tr>
                <tr><td>safety@fleetflow.com</td><td>Safety Officer</td></tr>
                <tr><td>finance@fleetflow.com</td><td>Financial Analyst</td></tr>
            </table>
        </div>
    </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
