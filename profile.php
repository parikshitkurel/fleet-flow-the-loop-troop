<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
requireLogin();

$pdo = db();
$user = currentUser();
$userId = (int)$user['id'];
$pageTitle = 'My Profile';

// Fetch fresh user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = validateIndianMobile($_POST['phone'] ?? '');

        if (!$name) {
            redirect('/fleetflownew/profile.php', 'Name cannot be empty.', 'error');
        }
        if (!$phone) {
            redirect('/fleetflownew/profile.php', 'Invalid Indian mobile number.', 'error');
        }

        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $userId]);

        // Update session
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['phone'] = $phone;

        redirect('/fleetflownew/profile.php', 'Profile updated successfully.');
    }

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $userData['password'])) {
            redirect('/fleetflownew/profile.php', 'Current password is incorrect.', 'error');
        }

        if (strlen($newPass) < 6) {
            redirect('/fleetflownew/profile.php', 'New password must be at least 6 characters.', 'error');
        }

        if ($newPass !== $confirmPass) {
            redirect('/fleetflownew/profile.php', 'Passwords do not match.', 'error');
        }

        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $userId]);

        redirect('/fleetflownew/profile.php', 'Password changed successfully.');
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="dash-grid" style="grid-template-columns: 1fr 1.2fr; gap: 24px; align-items: start;">
    
    <!-- Profile Info Card -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Personal Information</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="_action" value="update_profile">
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($userData['name']) ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Email Address</label>
                    <input type="email" class="form-control" value="<?= e($userData['email']) ?>" disabled style="background: var(--bg); cursor: not-allowed;">
                    <span class="form-hint">Email address cannot be changed.</span>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Phone Number</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-sm); font-weight: 500;">+91</span>
                        <input type="text" name="phone" class="form-control" value="<?= e($userData['phone']) ?>" style="padding-left: 45px;" pattern="[6-9][0-9]{9}" maxlength="10" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Account Role</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="pill" style="background: var(--primary); color: #fff; text-transform: uppercase; font-size: 11px;"><?= str_replace('_', ' ', $userData['role']) ?></span>
                        <span class="text-sm">Account Status: <strong style="color: var(--success);"><?= ucfirst($userData['status']) ?></strong></span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Update Profile</button>
            </form>
        </div>
    </div>

    <!-- Security Card -->
    <div class="card">
        <div class="card-header" style="border-bottom: 1px solid var(--border);">
            <span class="card-title">Security & Password</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="_action" value="change_password">

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; background: #1e293b; border-color: #1e293b;">Change Password</button>
            </form>
        </div>
    </div>

</div>

<style>
    .profile-avatar-big {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: var(--primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        font-weight: 700;
        margin: 0 auto 16px;
        box-shadow: 0 4px 12px rgba(113, 75, 103, 0.3);
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
