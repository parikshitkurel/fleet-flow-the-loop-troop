<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    // Security: New accounts are NULL role (pending approval)
    $role     = null;
    $phone    = trim($_POST['phone'] ?? '');

    if (empty($name) || empty($email) || empty($pass)) {
        $error = 'All fields are required.';
    } elseif (preg_match('/[0-9]/', $name)) {
        $error = 'Name cannot contain numeric values.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (!($normalizedPhone = validateIndianMobile($phone))) {
        $error = 'Please enter a valid Indian mobile number.';
    } else {
        $db = db();
        // Check if email exists
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered.';
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)');
            try {
                $stmt->execute([$name, $email, $hashed, $role, $normalizedPhone]);
                $success = 'Account created successfully! You can now login.';
            } catch (Exception $e) {
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — FleetFlow</title>
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
            <p>Create Your Account</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" class="form-control" placeholder="Your Name" required pattern="^[^0-9]+$" title="Name cannot contain numeric values.">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@fleetflow.in" required>
            </div>
            <div class="form-group">
                <label>Mobile Number *</label>
                <div class="phone-input-group">
                    <span class="phone-prefix">+91</span>
                    <input type="tel" name="phone" id="userPhone" class="form-control" placeholder="9876543210" required 
                           pattern="^[6789]\d{9}$" maxlength="10" inputmode="numeric"
                           title="Enter valid 10-digit Indian mobile number (starting with 6-9)">
                </div>
            </div>
            <div class="form-group" style="background:var(--bg);padding:10px;border-radius:var(--radius);border:1px dashed var(--border);">
                <label style="margin-bottom:0;color:var(--text);font-size:13px;">Account Status: Pending Approval</label>
                <p style="font-size:11px;color:var(--text-sm);margin-top:4px;">Your account will be activated by the system administrator after verification.</p>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Register</button>
            </div>
        </form>

        <div style="margin-top:20px; text-align:center;">
            <p style="font-size:13px;color:var(--text-sm);">Already have an account? <a href="login.php" style="color:var(--primary);font-weight:600;text-decoration:none;">Sign In</a></p>
        </div>
    </div>
</div>
</body>
</html>
