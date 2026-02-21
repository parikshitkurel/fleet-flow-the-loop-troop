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
        if ($user['role'] === null || $user['status'] === 'pending') {
            $error = 'Your account is pending administrator approval.';
        } elseif ($user['status'] === 'suspended') {
            $error = 'Your account has been suspended. Please contact support.';
        } elseif (!validateRole($user['role'])) {
            $error = 'Access denied: Invalid account role.';
            // Log security warning (simulated)
            error_log("Security Warning: User ID {$user['id']} tried to login with invalid role: " . ($user['role'] ?? 'NULL'));
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = $user;
            header('Location: dashboard.php');
            exit;
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
    <title>Login — FleetFlow</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div style="margin-bottom:6px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13" rx="2"/>
                    <path d="M16 8h4l3 5v4h-7V8z"/>
                    <circle cx="5.5" cy="18.5" r="2.5"/>
                    <circle cx="18.5" cy="18.5" r="2.5"/>
                </svg>
            </div>
            <h2>FleetFlow</h2>
            <p>Fleet &amp; Logistics Management</p>
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
            <p style="font-size:12px;color:var(--text-sm);margin-bottom:8px;font-weight:600;">Demo Accounts &nbsp;<code style="background:var(--bg);padding:2px 6px;border-radius:4px;">Fleet@123</code></p>
            <table style="width:100%;font-size:11px;color:var(--text-sm);border-collapse:collapse;">
                <tr style="opacity:.7">
                    <td style="padding:4px 0;white-space:nowrap;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/><path d="M17 14l2 2 4-4"/></svg>parikshitkurel@gmail.com
                    </td>
                    <td style="padding:4px 0 4px 8px;vertical-align:middle;">Admin &nbsp;<code>12345678</code></td>
                </tr>
                <tr>
                    <td style="padding:4px 0;white-space:nowrap;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 7h8M8 12h5"/></svg>lochangarg006@gmail.com
                    </td>
                    <td style="padding:4px 0 4px 8px;vertical-align:middle;">Fleet Manager</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;white-space:nowrap;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>aashmitatiwari@gmail.com
                    </td>
                    <td style="padding:4px 0 4px 8px;vertical-align:middle;">Financial Analyst</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;white-space:nowrap;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>prince.jaiswal@gmail.com
                    </td>
                    <td style="padding:4px 0 4px 8px;vertical-align:middle;">Dispatcher</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;white-space:nowrap;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>indori.aakarshan@gmail.com
                    </td>
                    <td style="padding:4px 0 4px 8px;vertical-align:middle;">Safety Officer</td>
                </tr>
            </table>
        </div>
    </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
