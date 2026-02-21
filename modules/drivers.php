<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$pdo = db();
$pageTitle = 'Drivers';
$action = $_GET['action'] ?? 'list';

// ─── ADD DRIVER ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add') {
    requireRole('fleet_manager', 'safety_officer');
    $name    = trim($_POST['name'] ?? '');
    $lic     = strtoupper(trim($_POST['license_number'] ?? ''));
    $expiry  = $_POST['license_expiry'] ?? '';
    $score   = max(0, min(100, (int)($_POST['safety_score'] ?? 100)));
    $phone   = trim($_POST['phone'] ?? '');

    if (!$name || !$lic || !$expiry) {
        redirect('/fleetflow/modules/drivers.php', 'Fill required fields.', 'error');
    }
    try {
        $pdo->prepare("INSERT INTO drivers (name, license_number, license_expiry, safety_score, phone) VALUES (?,?,?,?,?)")
            ->execute([$name, $lic, $expiry, $score, $phone]);
        redirect('/fleetflow/modules/drivers.php', 'Driver added successfully.');
    } catch (PDOException $e) {
        redirect('/fleetflow/modules/drivers.php', 'License number already exists.', 'error');
    }
}

// ─── UPDATE STATUS ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'set_status') {
    requireRole('fleet_manager', 'safety_officer');
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $allowed = ['Available', 'Suspended'];
    if (in_array($status, $allowed, true)) {
        $pdo->prepare("UPDATE drivers SET status = ? WHERE id = ? AND status != 'On Duty'")->execute([$status, $id]);
    }
    redirect('/fleetflow/modules/drivers.php', 'Driver status updated.');
}

$stmt = $pdo->query(
    "SELECT *, DATEDIFF(license_expiry, CURDATE()) AS days_to_expiry FROM drivers ORDER BY name"
);
$drivers = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <div class="section-title">Driver Management</div>
        <div class="text-sm"><?= count($drivers) ?> drivers on record</div>
    </div>
    <?php if (hasRole('fleet_manager','safety_officer')): ?>
    <button class="btn btn-primary" onclick="openModal('addDriverModal')">+ Add Driver</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>License #</th>
                    <th>Expiry</th>
                    <th>Safety Score</th>
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
            $scorePill = $d['safety_score'] >= 90 ? 'pill-green' : ($d['safety_score'] >= 70 ? 'pill-yellow' : 'pill-red');
            ?>
            <tr>
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
                <td><span class="pill <?= $statusPill ?>"><?= e($d['status']) ?></span></td>
                <td><?= e($d['phone']) ?></td>
                <td>
                    <?php if (hasRole('fleet_manager','safety_officer') && $d['status'] !== 'On Duty'): ?>
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
                        <input type="text" name="name" class="form-control" required>
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
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+1-555-0100">
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
