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
    $role     = $_POST['role'] ?? 'dispatcher';
    $phone    = trim($_POST['phone'] ?? '');

    if (empty($name) || empty($email) || empty($pass) || empty($role)) {
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
            <div style="font-size:44px;">🚛</div>
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
                <input type="text" name="name" class="form-control" placeholder="Rajesh Kumar" required pattern="^[^0-9]+$" title="Name cannot contain numeric values.">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@fleetflow.in" required>
            </div>
            <div class="form-group">
                <label>Mobile Number *</label>
                <input type="text" name="phone" id="userPhone" class="form-control" placeholder="9876543210" required 
                       pattern="^(\+91[\-\s]?)?[6789]\d{9}$" maxlength="13"
                       title="Please enter a valid Indian mobile number">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control" required style="appearance: none; background: var(--surface) url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E') no-repeat right 12px center; background-size: 10px auto; padding-right: 36px;">
                    <option value="fleet_manager">Fleet Manager</option>
                    <option value="dispatcher" selected>Dispatcher</option>
                    <option value="safety_officer">Safety Officer</option>
                    <option value="financial_analyst">Financial Analyst</option>
                </select>
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
