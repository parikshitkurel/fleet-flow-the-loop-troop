<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$pdo = db();
$action = $_GET['action'] ?? 'list';
$pageTitle = 'Vehicles';

// ─── ADD VEHICLE ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add') {
    requireRole('fleet_manager', 'dispatcher');
    $model   = trim($_POST['model'] ?? '');
    $plate   = strtoupper(trim($_POST['license_plate'] ?? ''));
    $cap     = (float)($_POST['max_capacity'] ?? 0);
    $odo     = (int)($_POST['odometer'] ?? 0);
    $year    = (int)($_POST['year'] ?? date('Y'));

    if (!$model || !$plate || $cap <= 0) {
        redirect('/fleetflow/modules/vehicles.php', 'Please fill all required fields.', 'error');
    }
    try {
        $pdo->prepare("INSERT INTO vehicles (model, license_plate, max_capacity, odometer, year) VALUES (?,?,?,?,?)")
            ->execute([$model, $plate, $cap, $odo, $year]);
        redirect('/fleetflow/modules/vehicles.php', 'Vehicle added successfully.');
    } catch (PDOException $e) {
        redirect('/fleetflow/modules/vehicles.php', 'License plate already exists.', 'error');
    }
}

// ─── TOGGLE STATUS ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'toggle') {
    requireRole('fleet_manager');
    $id  = (int)($_POST['id'] ?? 0);
    $row = $pdo->prepare("SELECT status FROM vehicles WHERE id = ?")->execute([$id]) ? $pdo->prepare("SELECT status FROM vehicles WHERE id = ?") : null;
    $stmt = $pdo->prepare("SELECT status FROM vehicles WHERE id = ?");
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if ($v) {
        $new = $v['status'] === 'Available' ? 'Out of Service' : 'Available';
        $pdo->prepare("UPDATE vehicles SET status = ? WHERE id = ?")->execute([$new, $id]);
    }
    redirect('/fleetflow/modules/vehicles.php', 'Status updated.');
}

// ─── LIST ───
$search = trim($_GET['q'] ?? '');
$where = $search ? "WHERE model LIKE ? OR license_plate LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];
$stmt = $pdo->prepare("SELECT v.*, 
    (SELECT COALESCE(SUM(f.total_cost),0) FROM fuel_expenses f WHERE f.vehicle_id=v.id) as fuel_cost,
    (SELECT COALESCE(SUM(m.cost),0) FROM maintenance_logs m WHERE m.vehicle_id=v.id) as maint_cost
    FROM vehicles v $where ORDER BY v.id");
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <div class="section-title">Vehicle Registry</div>
        <div class="text-sm"><?= count($vehicles) ?> vehicles registered</div>
    </div>
    <?php if (hasRole('fleet_manager','dispatcher')): ?>
    <button class="btn btn-primary" onclick="openModal('addVehicleModal')">+ Add Vehicle</button>
    <?php endif; ?>
</div>

<!-- Search -->
<form method="GET" style="margin-bottom:16px;display:flex;gap:10px;">
    <input type="text" name="q" class="form-control" placeholder="Search by model or plate..." value="<?= e($search) ?>" style="max-width:300px;">
    <button type="submit" class="btn btn-ghost">Search</button>
    <?php if ($search): ?><a href="/fleetflow/modules/vehicles.php" class="btn btn-ghost">Clear</a><?php endif; ?>
</form>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Model</th>
                    <th>License Plate</th>
                    <th>Year</th>
                    <th>Max Capacity</th>
                    <th>Odometer</th>
                    <th>Status</th>
                    <th>Total Cost</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($vehicles)): ?>
            <tr><td colspan="8" class="text-center" style="padding:32px;color:var(--text-sm);">No vehicles found</td></tr>
            <?php endif; ?>
            <?php foreach ($vehicles as $v): ?>
            <?php
            $statusPill = match($v['status']) {
                'Available'      => 'pill-green',
                'On Trip'        => 'pill-blue',
                'In Shop'        => 'pill-yellow',
                'Out of Service' => 'pill-red',
                default          => 'pill-gray',
            };
            $totalCost = $v['fuel_cost'] + $v['maint_cost'];
            ?>
            <tr>
                <td class="fw-600"><?= e($v['model']) ?></td>
                <td><code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;"><?= e($v['license_plate']) ?></code></td>
                <td><?= e($v['year']) ?></td>
                <td><?= number_format($v['max_capacity']) ?> kg</td>
                <td><?= number_format($v['odometer']) ?> km</td>
                <td><span class="pill <?= $statusPill ?>"><?= e($v['status']) ?></span></td>
                <td>$<?= number_format($totalCost, 2) ?></td>
                <td>
                    <?php if (hasRole('fleet_manager') && in_array($v['status'], ['Available','Out of Service'])): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="_action" value="toggle">
                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-ghost" 
                            onclick="return confirm('Toggle vehicle status?')">
                            <?= $v['status'] === 'Available' ? '⛔ Deactivate' : '✅ Activate' ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD VEHICLE MODAL -->
<?php if (hasRole('fleet_manager','dispatcher')): ?>
<div class="modal-overlay" id="addVehicleModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add New Vehicle</span>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="_action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Model *</label>
                        <input type="text" name="model" class="form-control" placeholder="e.g. Volvo FH16" required>
                    </div>
                    <div class="form-group">
                        <label>License Plate *</label>
                        <input type="text" name="license_plate" class="form-control" placeholder="TRK-006-FF" required>
                    </div>
                    <div class="form-group">
                        <label>Max Capacity (kg) *</label>
                        <input type="number" name="max_capacity" class="form-control" min="100" step="100" required>
                    </div>
                    <div class="form-group">
                        <label>Odometer (km)</label>
                        <input type="number" name="odometer" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" min="2000" max="<?= date('Y') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('addVehicleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Vehicle</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'new'): ?>
<script>document.addEventListener('DOMContentLoaded', () => openModal('addVehicleModal'));</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
