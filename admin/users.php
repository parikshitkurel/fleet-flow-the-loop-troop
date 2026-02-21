<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// ONLY fleet_manager can access this page
requireRole('fleet_manager');

$pdo = db();
$error = '';
$success = '';

// Handle Role Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update_role') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $newRole = $_POST['role'] ?? '';

    if (!validateRole($newRole)) {
        $error = 'Invalid role selected.';
    } else {
        try {
            // Prevent changing own role (to prevent self-lockout or accidental demotion)
            if ($userId === (int)$_SESSION['user_id'] && $newRole !== 'fleet_manager') {
                $error = 'You cannot demote yourself from Fleet Manager role.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $userId]);
                $success = 'User role updated successfully.';
                
                // If the updated user is the current user, update session
                if ($userId === (int)$_SESSION['user_id']) {
                    $_SESSION['user']['role'] = $newRole;
                }
            }
        } catch (Exception $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

// Fetch Users
$search = trim($_GET['q'] ?? '');
$where = $search ? "WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$stmt = $pdo->prepare("SELECT id, name, email, role, phone, created_at FROM users $where ORDER BY id DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'User Management (Admin)';
include __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <div class="section-title">User Management</div>
        <div class="text-sm">Manage system access and roles</div>
    </div>
</div>

<?= flash() ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<!-- Search -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:10px;">
            <input type="text" name="q" class="form-control" placeholder="Search by name, email or phone..." value="<?= e($search) ?>" style="max-width:400px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search): ?><a href="users.php" class="btn btn-ghost">Clear</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email & Phone</th>
                    <th>Current Role</th>
                    <th>Update Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= e($u['name']) ?></div>
                        <div class="text-sm">Joined <?= date('d M Y', strtotime($u['created_at'])) ?></div>
                    </td>
                    <td>
                        <div><?= e($u['email']) ?></div>
                        <div class="text-sm"><?= $u['phone'] ? '+91 ' . e($u['phone']) : 'No phone' ?></div>
                    </td>
                    <td>
                        <span class="pill <?= $u['role'] === 'fleet_manager' ? 'pill-purple' : 'pill-blue' ?>">
                            <?= e(ucwords(str_replace('_', ' ', $u['role']))) ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="_action" value="update_role">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="role" class="form-control" style="font-size:12px;padding:4px 8px;width:160px;">
                                <?php foreach (ALLOWED_ROLES as $role): ?>
                                <option value="<?= $role ?>" <?= $u['role'] === $role ? 'selected' : '' ?>>
                                    <?= ucwords(str_replace('_', ' ', $role)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-ghost" onclick="return confirm('Change user role to <?= e($role) ?>?')">Update</button>
                        </form>
                    </td>
                    <td>
                        <!-- Future: Deactivate/Delete -->
                        <span class="text-sm">No other actions</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
