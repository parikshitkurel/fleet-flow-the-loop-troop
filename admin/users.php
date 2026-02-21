<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Only admin can access
requireRole('admin');

$pdo    = db();
$error  = '';
$success = '';

// ─── PROTECTED SUPER ADMIN ─────────────────────────────────────────────────
if (!defined('SUPER_ADMIN_EMAIL')) {
    define('SUPER_ADMIN_EMAIL', 'parikshitkurel@gmail.com');
}

function isSuperAdmin(array $user): bool {
    return strtolower($user['email']) === strtolower(SUPER_ADMIN_EMAIL);
}

// ─── HANDLE AJAX ROLE/STATUS UPDATE ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update_user') {
    header('Content-Type: application/json');

    $userId    = (int)($_POST['user_id'] ?? 0);
    $newRole   = (($_POST['role'] ?? '') !== '') ? $_POST['role'] : null;
    $newStatus = $_POST['status'] ?? 'pending';

    // Load target user
    $target = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $target->execute([$userId]);
    $targetUser = $target->fetch();

    if (!$targetUser) {
        echo json_encode(['ok' => false, 'msg' => 'User not found.']);
        exit;
    }

    // Block modification of super admin
    if (isSuperAdmin($targetUser)) {
        echo json_encode(['ok' => false, 'msg' => 'The primary administrator account cannot be modified.']);
        exit;
    }

    // Block self-demotion / self-suspension
    if ($userId === (int)$_SESSION['user_id']) {
        if ($newRole !== 'admin' && $newRole !== 'fleet_manager') {
            echo json_encode(['ok' => false, 'msg' => 'You cannot demote your own account.']);
            exit;
        }
        if ($newStatus !== 'active') {
            echo json_encode(['ok' => false, 'msg' => 'You cannot suspend or deactivate your own account.']);
            exit;
        }
    }

    // Validate role
    if ($newRole !== null && !validateRole($newRole)) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid role selected.']);
        exit;
    }

    // Enforce single-admin rule
    if ($newRole === 'admin') {
        $existingAdmin = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND email != '" . SUPER_ADMIN_EMAIL . "' LIMIT 1")->fetch();
        if ($existingAdmin && $existingAdmin['id'] !== $userId) {
            echo json_encode(['ok' => false, 'msg' => 'Only one system administrator is allowed.']);
            exit;
        }
        // Non-super-admin users cannot be elevated to admin via this panel
        echo json_encode(['ok' => false, 'msg' => 'The admin role is reserved for the primary administrator only.']);
        exit;
    }

    // Validate status
    $validStatus = ['active', 'pending', 'suspended'];
    if (!in_array($newStatus, $validStatus, true)) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid status selected.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
        $stmt->execute([$newRole, $newStatus, $userId]);

        // Update session if admin modified themselves
        if ($userId === (int)$_SESSION['user_id']) {
            $_SESSION['user']['role']   = $newRole;
            $_SESSION['user']['status'] = $newStatus;
        }

        echo json_encode(['ok' => true, 'msg' => 'User updated successfully.']);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ─── FETCH USERS ───────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];
$stmt   = $pdo->prepare("SELECT id, name, email, role, status, phone, created_at FROM users $where ORDER BY FIELD(role,'admin','fleet_manager','dispatcher','safety_officer','financial_analyst'), name");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'User Management';
$topbarConfig = [
    'tableId' => 'userTable',
    'filters' => ['' => 'All Status', 'active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'],
    'sorts'   => ['0:asc' => 'Name A–Z', '4:desc' => 'Recent First'],
    'groups'  => ['role' => 'Role'],
];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/topbar.php';
?>

<style>
.user-row-admin   { background: linear-gradient(90deg, rgba(192,57,43,.05) 0%, transparent 60%); }
.badge-super-admin {
    display: inline-flex; align-items: center; gap: 4px;
    background: #C0392B18; color: #C0392B;
    border: 1px solid #C0392B40;
    border-radius: 20px; padding: 2px 10px; font-size: 11px; font-weight: 700;
    letter-spacing: .3px;
}
.toast-notification {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    padding: 12px 20px; border-radius: 8px;
    font-size: 13px; font-weight: 600;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    opacity: 0; transform: translateY(10px);
    transition: all .25s ease;
    pointer-events: none;
}
.toast-notification.show { opacity: 1; transform: translateY(0); }
.toast-success { background: #27AE60; color: #fff; }
.toast-error   { background: #C0392B; color: #fff; }
.user-actions-row { display: flex; flex-direction: column; gap: 8px; }
.dropdowns-row    { display: flex; gap: 8px; }
select.form-control.sm { font-size: 12px; height: 32px; padding: 0 8px; flex: 1; }
.btn-save { width: 100%; }
tr.protected td { opacity: .75; }
</style>

<div class="section-header">
    <div>
        <div class="section-title">User Management</div>
        <div class="text-sm"><?= count($users) ?> users — manage roles, status and access</div>
    </div>
</div>

<!-- Topbar handles search -->

<div class="card">
    <div class="table-responsive">
        <table id="userTable">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Contact</th>
                    <th>Role & Status</th>
                    <th style="min-width:300px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $isSuper    = isSuperAdmin($u);
                $isSelf     = (int)$u['id'] === (int)$_SESSION['user_id'];
                $rolePill   = match($u['role']) {
                    'admin'            => 'pill-red',
                    'fleet_manager'    => 'pill-purple',
                    'dispatcher'       => 'pill-blue',
                    'safety_officer'   => 'pill-yellow',
                    'financial_analyst'=> 'pill-green',
                    default            => 'pill-gray',
                };
                $statusPill = match($u['status'] ?? 'pending') {
                    'active'    => 'pill-green',
                    'suspended' => 'pill-red',
                    default     => 'pill-yellow',
                };
                $roleLabel   = $u['role'] ? ucwords(str_replace('_', ' ', $u['role'])) : 'No Role';
                $statusLabel = ucfirst($u['status'] ?? 'pending');
            ?>
            <tr class="<?= $isSuper ? 'user-row-admin protected' : '' ?>" 
                data-user-id="<?= $u['id'] ?>"
                data-searchable="<?= e("{$u['name']} {$u['email']} {$u['role']} {$u['status']} {$u['phone']}") ?>"
                data-groupRole="<?= e($u['role'] ?: 'No Role') ?>">
                <td>
                    <div class="fw-600" style="display:flex;align-items:center;gap:8px;">
                        <?= e($u['name']) ?>
                        <?php if ($isSuper): ?>
                        <span class="badge-super-admin">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:2px;"><path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/></svg>
                            Primary Admin
                        </span>
                        <?php elseif ($isSelf): ?>
                        <span style="font-size:10px;color:var(--text-sm);">(you)</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm"><?= e($u['email']) ?></div>
                    <div class="text-sm" style="color:var(--text-sm);font-size:10px;">
                        Since <?= date('d M Y', strtotime($u['created_at'])) ?>
                    </div>
                </td>
                <td>
                    <div><?= $u['phone'] ? '+91 ' . e($u['phone']) : '<span class="text-sm">No phone</span>' ?></div>
                </td>
                <td>
                    <div style="margin-bottom:4px;">
                        <span class="pill <?= $rolePill ?>"><?= $roleLabel ?></span>
                    </div>
                    <div>
                        <span class="pill <?= $statusPill ?>"><?= $statusLabel ?></span>
                    </div>
                </td>
                <td>
                    <?php if ($isSuper): ?>
                    <div style="color:var(--text-sm);font-size:12px;font-style:italic;display:flex;align-items:center;gap:4px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Protected — cannot be modified
                    </div>
                    <?php else: ?>
                    <div class="user-actions-row">
                        <div class="dropdowns-row">
                            <!-- Role dropdown — exclude 'admin' for non-super users -->
                            <select name="role" class="form-control sm" data-field="role">
                                <option value="">— No Role —</option>
                                <?php foreach (ALLOWED_ROLES as $r):
                                    if ($r === 'admin') continue; // admin reserved for super admin only
                                ?>
                                <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>>
                                    <?= ucwords(str_replace('_', ' ', $r)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Status dropdown -->
                            <select name="status" class="form-control sm" data-field="status">
                                <option value="pending"   <?= ($u['status'] ?? '') === 'pending'   ? 'selected' : '' ?>>Pending</option>
                                <option value="active"    <?= ($u['status'] ?? '') === 'active'    ? 'selected' : '' ?>>Active</option>
                                <option value="suspended" <?= ($u['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </div>

                        <button class="btn btn-sm btn-primary btn-save"
                                onclick="saveUser(this, <?= $u['id'] ?>)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Changes
                        </button>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast-notification"></div>

<script>
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'toast-notification toast-' + type + ' show';
    setTimeout(() => t.classList.remove('show'), 3500);
}

function saveUser(btn, userId) {
    const row     = btn.closest('tr');
    const role    = row.querySelector('[data-field="role"]').value;
    const status  = row.querySelector('[data-field="status"]').value;

    btn.disabled    = true;
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="spin" style="vertical-align:middle;margin-right:4px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg> Saving…';

    const fd = new FormData();
    fd.append('_action',  'update_user');
    fd.append('user_id',  userId);
    fd.append('role',     role);
    fd.append('status',   status);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showToast(data.msg, data.ok ? 'success' : 'error');
            if (data.ok) {
                // Refresh only the role/status pills in this row without full reload
                const roleLabel   = role   ? role.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase()) : 'No Role';
                const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
                const pillCol     = row.querySelector('td:nth-child(3)');
                const rolePill    = role === 'fleet_manager' ? 'pill-purple' :
                                    role === 'dispatcher'    ? 'pill-blue'   :
                                    role === 'admin'         ? 'pill-red'    :
                                    role === 'safety_officer'? 'pill-yellow' :
                                    role === 'financial_analyst' ? 'pill-green' : 'pill-gray';
                const stPill      = status === 'active' ? 'pill-green' : status === 'suspended' ? 'pill-red' : 'pill-yellow';
                pillCol.innerHTML = `
                    <div style="margin-bottom:4px;"><span class="pill ${rolePill}">${roleLabel || 'No Role'}</span></div>
                    <div><span class="pill ${stPill}">${statusLabel}</span></div>`;
            }
        })
        .catch(() => showToast('Network error. Please try again.', 'error'))
        .finally(() => {
            btn.disabled  = false;
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Changes';
        });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
