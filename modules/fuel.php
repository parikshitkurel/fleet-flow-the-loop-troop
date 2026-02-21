<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$pdo = db();
$pageTitle = 'Fuel & Expenses';
$action = $_GET['action'] ?? 'list';

// ─── ADD FUEL LOG ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add_fuel') {
    requireRole('fleet_manager', 'dispatcher', 'financial_analyst');
    $vid    = (int)($_POST['vehicle_id'] ?? 0);
    $liters = (float)($_POST['liters'] ?? 0);
    $cpl    = (float)($_POST['cost_per_liter'] ?? 0);
    $odo    = (int)($_POST['odometer_reading'] ?? 0);
    $date   = $_POST['expense_date'] ?? date('Y-m-d');
    $sta    = trim($_POST['station'] ?? '');

    if (!$vid || $liters <= 0 || $cpl <= 0) {
        redirect('/fleetflow/modules/fuel.php', 'Fill all required fields.', 'error');
    }
    $pdo->prepare(
        "INSERT INTO fuel_expenses (vehicle_id, liters, cost_per_liter, odometer_reading, expense_date, station) VALUES (?,?,?,?,?,?)"
    )->execute([$vid, $liters, $cpl, $odo, $date, $sta]);

    // Update vehicle odometer if higher
    $pdo->prepare("UPDATE vehicles SET odometer = ? WHERE id = ? AND odometer < ?")->execute([$odo, $vid, $odo]);

    redirect('/fleetflow/modules/fuel.php', 'Fuel expense logged.');
}

$expenses = $pdo->query(
    "SELECT f.*, v.license_plate, v.model FROM fuel_expenses f
     JOIN vehicles v ON f.vehicle_id = v.id
     ORDER BY f.expense_date DESC, f.id DESC"
)->fetchAll();

$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY model")->fetchAll();

// Per-vehicle totals
$perVehicle = $pdo->query(
    "SELECT v.license_plate, v.model,
            COALESCE(SUM(f.liters),0) as total_liters,
            COALESCE(SUM(f.total_cost),0) as total_fuel_cost,
            COALESCE(SUM(m.cost),0) as total_maint,
            COALESCE(SUM(f.total_cost),0) + COALESCE(SUM(m.cost),0) as total_op_cost
     FROM vehicles v
     LEFT JOIN fuel_expenses f ON f.vehicle_id = v.id
     LEFT JOIN maintenance_logs m ON m.vehicle_id = v.id
     GROUP BY v.id ORDER BY total_op_cost DESC"
)->fetchAll();

$grandTotal = array_sum(array_column($perVehicle, 'total_op_cost'));

include __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <div class="section-title">Fuel & Expenses</div>
        <div class="text-sm">Total Operational Cost: <strong>$<?= number_format($grandTotal, 2) ?></strong></div>
    </div>
    <?php if (hasRole('fleet_manager','dispatcher','financial_analyst')): ?>
    <button class="btn btn-primary" onclick="openModal('fuelModal')">+ Log Fuel</button>
    <?php endif; ?>
</div>

<!-- Cost Summary Table -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><span class="card-title">Operational Cost Summary by Vehicle</span></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Vehicle</th><th>Fuel (L)</th><th>Fuel Cost</th><th>Maintenance</th><th>Total Op. Cost</th></tr>
            </thead>
            <tbody>
            <?php foreach ($perVehicle as $pv): ?>
            <tr>
                <td>
                    <div class="fw-600"><?= e($pv['license_plate']) ?></div>
                    <div class="text-sm"><?= e($pv['model']) ?></div>
                </td>
                <td><?= number_format($pv['total_liters'], 1) ?> L</td>
                <td>$<?= number_format($pv['total_fuel_cost'], 2) ?></td>
                <td>$<?= number_format($pv['total_maint'], 2) ?></td>
                <td class="fw-600">$<?= number_format($pv['total_op_cost'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:#f8fafc;font-weight:700;">
                <td>TOTAL</td>
                <td><?= number_format(array_sum(array_column($perVehicle,'total_liters')),1) ?> L</td>
                <td>$<?= number_format(array_sum(array_column($perVehicle,'total_fuel_cost')),2) ?></td>
                <td>$<?= number_format(array_sum(array_column($perVehicle,'total_maint')),2) ?></td>
                <td>$<?= number_format($grandTotal, 2) ?></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Fuel Expense Log -->
<div class="card">
    <div class="card-header"><span class="card-title">Fuel Expense Log</span></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Vehicle</th><th>Date</th><th>Liters</th><th>$/L</th><th>Total</th><th>Odometer</th><th>Station</th></tr>
            </thead>
            <tbody>
            <?php if (empty($expenses)): ?>
            <tr><td colspan="7" class="text-center" style="padding:32px;color:var(--text-sm);">No expenses logged</td></tr>
            <?php endif; ?>
            <?php foreach ($expenses as $ex): ?>
            <tr>
                <td>
                    <div class="fw-600"><?= e($ex['license_plate']) ?></div>
                    <div class="text-sm"><?= e($ex['model']) ?></div>
                </td>
                <td><?= e($ex['expense_date']) ?></td>
                <td><?= number_format($ex['liters'], 1) ?> L</td>
                <td>$<?= number_format($ex['cost_per_liter'], 3) ?></td>
                <td class="fw-600">$<?= number_format($ex['total_cost'], 2) ?></td>
                <td><?= number_format($ex['odometer_reading']) ?> km</td>
                <td><?= e($ex['station']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD FUEL MODAL -->
<?php if (hasRole('fleet_manager','dispatcher','financial_analyst')): ?>
<div class="modal-overlay" id="fuelModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Log Fuel Expense</span>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="_action" value="add_fuel">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Vehicle *</label>
                        <select name="vehicle_id" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach ($vehicles as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= e($v['license_plate']) ?> — <?= e($v['model']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Liters *</label>
                        <input type="number" name="liters" class="form-control" min="0.1" step="0.1" required placeholder="e.g. 180.5">
                    </div>
                    <div class="form-group">
                        <label>Cost per Liter ($) *</label>
                        <input type="number" name="cost_per_liter" class="form-control" min="0.01" step="0.001" required placeholder="e.g. 1.48">
                    </div>
                    <div class="form-group">
                        <label>Odometer Reading (km)</label>
                        <input type="number" name="odometer_reading" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label>Station</label>
                        <input type="text" name="station" class="form-control" placeholder="Shell, Pilot...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('fuelModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Log Expense</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'new'): ?>
<script>document.addEventListener('DOMContentLoaded', () => openModal('fuelModal'));</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
