<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
// financial_analyst: view only; others: full access
if (!hasRole('admin','fleet_manager','dispatcher','financial_analyst','safety_officer')) {
    requireRole('admin'); // will redirect
}

$pdo = db();
$pageTitle = 'Trip Dispatcher';
$action = $_GET['action'] ?? 'list';
$errors = [];
$canDispatch = hasRole('admin','fleet_manager','dispatcher');

// ─── CREATE TRIP ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'create') {
    requireRole('fleet_manager', 'dispatcher');

    $vid     = (int)($_POST['vehicle_id'] ?? 0);
    $did     = (int)($_POST['driver_id'] ?? 0);
    $origin  = trim($_POST['origin'] ?? '');
    $dest    = trim($_POST['destination'] ?? '');
    $cargo   = trim($_POST['cargo_description'] ?? '');
    $weight  = (float)($_POST['cargo_weight'] ?? 0);
    $date    = $_POST['scheduled_date'] ?? '';
    $notes   = trim($_POST['notes'] ?? '');
    $dispatch = isset($_POST['dispatch_now']);

    // Validate vehicle
    $vStmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND status = 'Available'");
    $vStmt->execute([$vid]);
    $vehicle = $vStmt->fetch();
    if (!$vehicle) $errors[] = 'Selected vehicle is not available.';

    // Validate driver
    $dStmt = $pdo->prepare("SELECT *, DATEDIFF(license_expiry, CURDATE()) AS days_left FROM drivers WHERE id = ? AND status = 'Available'");
    $dStmt->execute([$did]);
    $driver = $dStmt->fetch();
    if (!$driver) {
        $errors[] = 'Selected driver is not available.';
    } elseif ($driver['days_left'] < 0) {
        $errors[] = 'Driver license has expired. Cannot dispatch.';
    }

    // Validate cargo weight
    if ($vehicle && $weight > $vehicle['max_capacity']) {
        $errors[] = sprintf('Cargo weight (%.0f kg) exceeds vehicle capacity (%.0f kg).', $weight, $vehicle['max_capacity']);
    }

    if (!$origin || !$dest || !$date) $errors[] = 'Fill all required fields.';

    if (empty($errors)) {
        $status = $dispatch ? 'Dispatched' : 'Draft';
        $pdo->prepare(
            "INSERT INTO trips (vehicle_id, driver_id, origin, destination, cargo_description, cargo_weight, status, scheduled_date, notes)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([$vid, $did, $origin, $dest, $cargo, $weight, $status, $date, $notes]);

        if ($dispatch) {
            $pdo->prepare("UPDATE vehicles SET status = 'On Trip' WHERE id = ?")->execute([$vid]);
            $pdo->prepare("UPDATE drivers SET status = 'On Duty' WHERE id = ?")->execute([$did]);
        }
        redirect('/fleetflownew/modules/trips.php', "Trip {$status}!");
    }
}

// ─── UPDATE STATUS ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update_status') {
    requireRole('fleet_manager', 'dispatcher');
    $tid    = (int)($_POST['trip_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $dist   = (int)($_POST['distance_km'] ?? 0);

    $tripStmt = $pdo->prepare("SELECT * FROM trips WHERE id = ?");
    $tripStmt->execute([$tid]);
    $trip = $tripStmt->fetch();

    if ($trip) {
        $allowed = ['Draft'=>['Dispatched','Cancelled'], 'Dispatched'=>['Completed','Cancelled']];
        if (in_array($status, $allowed[$trip['status']] ?? [], true)) {
            $completed = $status === 'Completed' ? date('Y-m-d') : null;
            $pdo->prepare("UPDATE trips SET status=?, completed_date=?, distance_km=? WHERE id=?")
                ->execute([$status, $completed, $dist ?: null, $tid]);

            if ($status === 'Completed' || $status === 'Cancelled') {
                $pdo->prepare("UPDATE vehicles SET status='Available' WHERE id=?")->execute([$trip['vehicle_id']]);
                $pdo->prepare("UPDATE drivers SET status='Available' WHERE id=?")->execute([$trip['driver_id']]);
            }
            if ($status === 'Dispatched') {
                $pdo->prepare("UPDATE vehicles SET status='On Trip' WHERE id=?")->execute([$trip['vehicle_id']]);
                $pdo->prepare("UPDATE drivers SET status='On Duty' WHERE id=?")->execute([$trip['driver_id']]);
            }
        }
    }
    redirect('/fleetflownew/modules/trips.php', 'Trip status updated.');
}

// ─── LIST ───
$filterStatus = $_GET['status'] ?? '';
$where  = $filterStatus ? "WHERE t.status = :s" : "";
$params = $filterStatus ? [':s' => $filterStatus] : [];

$stmt = $pdo->prepare(
    "SELECT t.*, v.license_plate, v.model as vehicle_model, v.max_capacity,
            d.name as driver_name, d.license_expiry,
            DATEDIFF(d.license_expiry, CURDATE()) AS driver_days_left
     FROM trips t
     JOIN vehicles v ON t.vehicle_id = v.id
     JOIN drivers d ON t.driver_id = d.id
     $where ORDER BY t.created_at DESC"
);
$stmt->execute($params);
$trips = $stmt->fetchAll();

// Available vehicles & drivers for form
$availVehicles = $pdo->query("SELECT * FROM vehicles WHERE status='Available' ORDER BY model")->fetchAll();
$availDrivers  = $pdo->query("SELECT *, DATEDIFF(license_expiry,CURDATE()) AS days_left FROM drivers WHERE status='Available' ORDER BY name")->fetchAll();

$vehicleCaps = [];
foreach ($availVehicles as $v) $vehicleCaps[$v['id']] = (float)$v['max_capacity'];

$topbarConfig = [
    'tableId' => 'mainTable',
    'filters' => ['' => 'All', 'Draft' => 'Draft', 'Dispatched' => 'Dispatched', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'],
    'sorts'   => ['0:asc' => 'ID ↑', '5:asc' => 'Date ↑', '5:desc' => 'Date ↓', '4:desc' => 'Weight ↓'],
    'groups'  => ['Status' => 'Status'],
];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="section-header">
    <div>
        <div class="section-title">Trip Dispatcher</div>
        <div class="text-sm"><?= count($trips) ?> trips<?= $filterStatus ? " — $filterStatus" : '' ?></div>
    </div>
    <?php if ($canDispatch): ?>
    <button class="btn btn-primary" onclick="openModal('tripModal')">+ Dispatch Trip</button>
    <?php endif; ?>
</div>

<!-- Filters -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach (['','Draft','Dispatched','Completed','Cancelled'] as $s): ?>
    <a href="?status=<?= urlencode($s) ?>" class="btn btn-sm <?= $filterStatus === $s ? 'btn-primary' : 'btn-ghost' ?>"><?= $s ?: 'All' ?></a>
    <?php endforeach; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table id="mainTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Route</th>
                    <th>Vehicle</th>
                    <th>Driver</th>
                    <th>Cargo</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($trips)): ?>
            <tr><td colspan="8" class="text-center" style="padding:32px;color:var(--text-sm);">No trips found</td></tr>
            <?php endif; ?>
            <?php foreach ($trips as $t): ?>
            <?php
            $sp = match($t['status']) {
                'Dispatched' => 'pill-blue', 'Completed' => 'pill-green',
                'Cancelled'  => 'pill-red',  default => 'pill-gray',
            };
            $overWeight = $t['cargo_weight'] > $t['max_capacity'];
            $driverExp  = $t['driver_days_left'] < 0;
            ?>
            <tr data-searchable="<?= e("{$t['id']} {$t['origin']} {$t['destination']} {$t['license_plate']} {$t['driver_name']} {$t['status']}") ?>">
                <td class="text-sm">#<?= $t['id'] ?></td>
                <td>
                    <div class="fw-600" style="font-size:12px;"><?= e($t['origin']) ?></div>
                    <div class="text-sm">→ <?= e($t['destination']) ?></div>
                    <?php if ($t['distance_km']): ?>
                    <div class="text-sm"><?= number_format($t['distance_km']) ?> km</div>
                    <?php endif; ?>
                </td>
                <td>
                    <div><?= e($t['license_plate']) ?></div>
                    <div class="text-sm"><?= e($t['vehicle_model']) ?></div>
                    <?php if ($overWeight): ?><span class="pill pill-red" style="font-size:10px;">OVERWEIGHT</span><?php endif; ?>
                </td>
                <td>
                    <div><?= e($t['driver_name']) ?></div>
                    <?php if ($driverExp): ?><span class="pill pill-red" style="font-size:10px;">LIC EXPIRED</span><?php endif; ?>
                </td>
                <td>
                    <div><?= number_format($t['cargo_weight']) ?> kg</div>
                    <?php if ($t['cargo_description']): ?><div class="text-sm"><?= e($t['cargo_description']) ?></div><?php endif; ?>
                </td>
                <td><?= e($t['scheduled_date']) ?></td>
                <td><span class="pill <?= $sp ?>"><?= $t['status'] ?></span></td>
                <td>
                    <?php if ($canDispatch && in_array($t['status'],['Draft','Dispatched'])): ?>
                    <button class="btn btn-sm btn-ghost" onclick="openStatusModal(<?= $t['id'] ?>,'<?= $t['status'] ?>')">Update</button>
                    <?php elseif (!$canDispatch): ?><span class="text-sm">View Only</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CREATE TRIP MODAL -->
<?php if ($canDispatch): ?>
<div class="modal-overlay" id="tripModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Dispatch New Trip</span>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" id="tripForm">
            <input type="hidden" name="_action" value="create">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Vehicle *</label>
                        <select name="vehicle_id" class="form-control" required>
                            <option value="">— Select Vehicle —</option>
                            <?php foreach ($availVehicles as $v): ?>
                            <option value="<?= $v['id'] ?>" data-cap="<?= $v['max_capacity'] ?>">
                                <?= e($v['license_plate']) ?> — <?= e($v['model']) ?> (<?= number_format($v['max_capacity']) ?> kg)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Driver *</label>
                        <select name="driver_id" class="form-control" required>
                            <option value="">— Select Driver —</option>
                            <?php foreach ($availDrivers as $d): ?>
                            <?php $expired = $d['days_left'] < 0; ?>
                            <option value="<?= $d['id'] ?>" <?= $expired ? 'disabled style="color:red"' : '' ?>>
                                <?= e($d['name']) ?><?= $expired ? ' (LICENSE EXPIRED)' : " (exp: {$d['license_expiry']})" ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Origin *</label>
                        <input type="text" name="origin" class="form-control" placeholder="Indore, MP" required>
                    </div>
                    <div class="form-group">
                        <label>Destination *</label>
                        <input type="text" name="destination" class="form-control" placeholder="Mumbai, MH" required>
                    </div>
                    <div class="form-group">
                        <label>Cargo Weight (kg) *</label>
                        <input type="number" name="cargo_weight" class="form-control" min="1" step="0.1" required>
                        <span class="form-hint" id="capacityHint">Select a vehicle to see capacity</span>
                    </div>
                    <div class="form-group">
                        <label>Scheduled Date *</label>
                        <input type="date" name="scheduled_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Cargo Description</label>
                        <input type="text" name="cargo_description" class="form-control" placeholder="e.g. Electronics, Furniture...">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('tripModal')">Cancel</button>
                <button type="submit" name="save_draft" class="btn btn-outline">Save as Draft</button>
                <button type="submit" name="dispatch_now" class="btn btn-primary">Dispatch Now</button>
            </div>
        </form>
    </div>
</div>

<!-- UPDATE STATUS MODAL -->
<div class="modal-overlay" id="statusModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <span class="modal-title">Update Trip Status</span>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="_action" value="update_status">
            <input type="hidden" name="trip_id" id="statusTripId">
            <div class="modal-body">
                <div class="form-group">
                    <label>New Status</label>
                    <select name="status" class="form-control" id="statusSelect">
                        <option value="Dispatched">Dispatched</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group" id="distanceGroup" style="display:none;margin-top:12px;">
                    <label>Distance Traveled (km)</label>
                    <input type="number" name="distance_km" class="form-control" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
window.vehicleCapacities = <?= json_encode($vehicleCaps) ?>;

function openStatusModal(tripId, currentStatus) {
    document.getElementById('statusTripId').value = tripId;
    const sel = document.getElementById('statusSelect');
    // Populate options based on current status
    sel.innerHTML = '';
    const transitions = {
        'Draft':      ['Dispatched','Cancelled'],
        'Dispatched': ['Completed','Cancelled']
    };
    (transitions[currentStatus] || []).forEach(s => {
        const opt = new Option(s, s);
        sel.appendChild(opt);
    });
    sel.dispatchEvent(new Event('change'));
    openModal('statusModal');
}

document.getElementById('statusSelect')?.addEventListener('change', function() {
    document.getElementById('distanceGroup').style.display = this.value === 'Completed' ? 'block' : 'none';
});

<?php if ($action === 'new'): ?>
document.addEventListener('DOMContentLoaded', () => openModal('tripModal'));
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
