<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
// RBAC: financial_analyst has no access to driver data
if (hasRole('financial_analyst')) {
    header('Location: /fleetflownew/dashboard.php?err=access');
    exit;
}
$canWrite = hasRole('admin', 'fleet_manager', 'safety_officer');

$pdo = db();
$pageTitle = 'Drivers';
$action = $_GET['action'] ?? 'list';

// ─── ADD DRIVER ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add') {
    requireRole('admin', 'fleet_manager', 'safety_officer');
    $name    = trim($_POST['name'] ?? '');
    $lic     = strtoupper(trim($_POST['license_number'] ?? ''));
    $expiry  = $_POST['license_expiry'] ?? '';
    $score   = max(0, min(100, (int)($_POST['safety_score'] ?? 100)));
    $phone   = trim($_POST['phone'] ?? '');

    if (!$name || !$lic || !$expiry || !$phone) {
        redirect('/fleetflownew/modules/drivers.php', 'Fill all required fields.', 'error');
    }

    $normalizedPhone = validateIndianMobile($phone);
    if (!$normalizedPhone) {
        redirect('/fleetflownew/modules/drivers.php', 'Please enter a valid Indian mobile number.', 'error');
    }
    if (preg_match('/[0-9]/', $name)) {
        redirect('/fleetflownew/modules/drivers.php', 'Driver name cannot contain numeric values.', 'error');
    }
    try {
        $pdo->prepare("INSERT INTO drivers (name, license_number, license_expiry, safety_score, phone) VALUES (?,?,?,?,?)")
            ->execute([$name, $lic, $expiry, $score, $normalizedPhone]);
        redirect('/fleetflownew/modules/drivers.php', 'Driver added successfully.');
    } catch (PDOException $e) {
        redirect('/fleetflownew/modules/drivers.php', 'License number already exists.', 'error');
    }
}

// ─── UPDATE STATUS ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'set_status') {
    requireRole('admin', 'fleet_manager', 'safety_officer');
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $allowed = ['Available', 'Suspended'];
    if (in_array($status, $allowed, true)) {
        $pdo->prepare("UPDATE drivers SET status = ? WHERE id = ? AND status != 'On Duty'")->execute([$status, $id]);
    }
    redirect('/fleetflownew/modules/drivers.php', 'Driver status updated.');
}

$stmt = $pdo->query(
    "SELECT d.*,
            DATEDIFF(d.license_expiry, CURDATE()) AS days_to_expiry,
            COUNT(DISTINCT t.id) AS total_trips,
            COUNT(DISTINCT CASE WHEN t.status='Completed' THEN t.id END) AS completed_trips,
            ROUND(
                CASE WHEN COUNT(DISTINCT t.id)>0
                     THEN COUNT(DISTINCT CASE WHEN t.status='Completed' THEN t.id END) / COUNT(DISTINCT t.id) * 100
                     ELSE 0 END, 1
            ) AS completion_rate
     FROM drivers d
     LEFT JOIN trips t ON t.driver_id = d.id
     GROUP BY d.id
     ORDER BY d.name"
);
$drivers = $stmt->fetchAll();

$topbarConfig = [
    'tableId' => 'mainTable',
    'filters' => ['' => 'All Status', 'Available' => 'Available', 'On Duty' => 'On Duty', 'Suspended' => 'Suspended'],
    'sorts'   => ['0:asc' => 'Name A–Z', '3:desc' => 'Safety Score ↓', '4:desc' => 'Completion Rate ↓', '2:asc' => 'Expiry ↑'],
    'groups'  => ['status' => 'Status'],
];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="section-header">
    <div>
        <div class="section-title">Driver Management</div>
        <div class="text-sm"><?= count($drivers) ?> drivers on record</div>
    </div>
    <?php if ($canWrite): ?>
    <button class="btn btn-primary" onclick="openModal('addDriverModal')">+ Add Driver</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table id="mainTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>License #</th>
                    <th>Expiry</th>
                    <th>Safety Score</th>
                    <th>Completion Rate</th>
                    <th>Status</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($drivers as $d): ?>
            <?php
            $expired = $d['days_to_expiry'] < 0;
            $expiringSoon = $d['days_to_expiry'] >= 0 && $d['days_to_expiry'] <= 30;
            $statusPill = match($d['status']) {
                'Available' => 'pill-green',
                'On Duty'   => 'pill-blue',
                'Suspended' => 'pill-red',
                default     => 'pill-gray',
            };
            $scorePill = $d['safety_score'] >= 90 ? 'pill-green' : ($d['safety_score'] >= 75 ? 'pill-yellow' : 'pill-red');
            ?>
            <tr data-searchable="<?= e("{$d['name']} {$d['license_number']} {$d['status']}") ?>"
                data-groupStatus="<?= e($d['status']) ?>">
                <td class="fw-600"><?= e($d['name']) ?></td>
                <td><code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;"><?= e($d['license_number']) ?></code></td>
                <td>
                    <span style="color:<?= $expired ? 'var(--danger)' : ($expiringSoon ? 'var(--warning)' : 'inherit') ?>">
                        <?= e($d['license_expiry']) ?>
                    </span>
                    <?php if ($expired): ?>
                        <span class="pill pill-red" style="font-size:10px;">EXPIRED</span>
                    <?php elseif ($expiringSoon): ?>
                        <span class="pill pill-orange" style="font-size:10px;"><?= $d['days_to_expiry'] ?>d</span>
                    <?php endif; ?>
                </td>
                <td><span class="pill <?= $scorePill ?>"><?= $d['safety_score'] ?>/100</span></td>
                <td>
                    <?php $cr = (float)$d['completion_rate']; $crColor = $cr>=80?'#27AE60':($cr>=50?'#E67E22':'#e74c3c'); ?>
                    <div style="font-size:12px;font-weight:600;color:<?= $crColor ?>;"><?= $cr ?>%</div>
                    <div style="background:#e2e8f0;border-radius:20px;height:5px;width:80px;margin-top:3px;">
                        <div style="background:<?= $crColor ?>;height:5px;border-radius:20px;width:<?= min($cr,100) ?>%"></div>
                    </div>
                    <div class="text-sm" style="font-size:10px;"><?= $d['completed_trips'] ?>/<?= $d['total_trips'] ?> trips</div>
                </td>
                <td><span class="pill <?= $statusPill ?>"><?= e($d['status']) ?></span></td>
                <td><?= e($d['phone']) ?></td>
                <td>
                    <?php if ($canWrite && $d['status'] !== 'On Duty'): ?>
                    <form method="POST" style="display:inline-flex;gap:6px;">
                        <input type="hidden" name="_action" value="set_status">
                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                        <?php if ($d['status'] !== 'Suspended'): ?>
                        <button name="status" value="Suspended" type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Suspend this driver?')">Suspend</button>
                        <?php else: ?>
                        <button name="status" value="Available" type="submit" class="btn btn-sm btn-success" onclick="return confirm('Reinstate this driver?')">Reinstate</button>
                        <?php endif; ?>
                    </form>
                    <?php elseif ($d['status'] === 'On Duty'): ?>
                    <span class="text-sm">On active trip</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD DRIVER MODAL -->
<?php if (hasRole('fleet_manager','safety_officer')): ?>
<div class="modal-overlay" id="addDriverModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add New Driver</span>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="_action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" class="form-control" required pattern="^[^0-9]+$" title="Name cannot contain numeric values.">
                    </div>
                    <div class="form-group">
                        <label>License Number *</label>
                        <input type="text" name="license_number" class="form-control" placeholder="DL-2024-007" required>
                    </div>
                    <div class="form-group">
                        <label>License Expiry *</label>
                        <input type="date" name="license_expiry" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Safety Score (0-100)</label>
                        <input type="number" name="safety_score" class="form-control" value="100" min="0" max="100">
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <div class="phone-input-group">
                            <span class="phone-prefix">+91</span>
                            <input type="tel" name="phone" id="driverPhone" class="form-control" placeholder="9876543210" required 
                                   pattern="^[6789]\d{9}$" maxlength="10" inputmode="numeric"
                                   title="Enter valid 10-digit Indian mobile number (starting with 6-9)">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('addDriverModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Driver</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'new'): ?>
<script>document.addEventListener('DOMContentLoaded', () => openModal('addDriverModal'));</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
