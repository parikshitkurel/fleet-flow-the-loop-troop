<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
requireLogin();

$_role   = $_SESSION['user']['role'] ?? null;
$_status = $_SESSION['user']['status'] ?? 'pending';

if (!$_role || $_status !== 'active') {
    session_destroy();
    header('Location: /fleetflownew/login.php?err=pending');
    exit;
}
if ($_role === 'admin') {
    header('Location: /fleetflownew/admin/users.php');
    exit;
}

$pdo       = db();
$pageTitle = 'Dashboard';

// ═══════════════════════════════════════════════════════
// DISPATCHER DATA
// ═══════════════════════════════════════════════════════
if ($_role === 'dispatcher') {
    $activeTrips   = $pdo->query("SELECT COUNT(*) FROM trips WHERE status='Dispatched'")->fetchColumn();
    $draftTrips    = $pdo->query("SELECT COUNT(*) FROM trips WHERE status='Draft'")->fetchColumn();
    $availVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='Available'")->fetchColumn();
    $driversOnDuty = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='On Duty'")->fetchColumn();

    // Recent trips for this dispatcher (all since dispatcher only manages trips)
    $myTrips = $pdo->query(
        "SELECT t.id, t.origin, t.destination, t.cargo_weight, t.status,
                t.scheduled_date, t.cargo_description,
                v.license_plate, v.model as vehicle_model, v.max_capacity,
                d.name as driver_name, d.license_expiry,
                DATEDIFF(d.license_expiry, CURDATE()) as driver_days_left
         FROM trips t
         JOIN vehicles v ON t.vehicle_id = v.id
         JOIN drivers d  ON t.driver_id  = d.id
         ORDER BY t.created_at DESC LIMIT 20"
    )->fetchAll();

    $availVehicleList = $pdo->query("SELECT * FROM vehicles WHERE status='Available' ORDER BY model")->fetchAll();
    $availDriverList  = $pdo->query("SELECT *, DATEDIFF(license_expiry,CURDATE()) AS days_left FROM drivers WHERE status='Available' ORDER BY name")->fetchAll();
    $vehicleCaps = [];
    foreach ($availVehicleList as $v) $vehicleCaps[$v['id']] = (float)$v['max_capacity'];

    // Handle trip creation POST
    $tripErrors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'create_trip') {
        $vid    = (int)($_POST['vehicle_id'] ?? 0);
        $did    = (int)($_POST['driver_id'] ?? 0);
        $origin = trim($_POST['origin'] ?? '');
        $dest   = trim($_POST['destination'] ?? '');
        $cargo  = trim($_POST['cargo_description'] ?? '');
        $weight = (float)($_POST['cargo_weight'] ?? 0);
        $date   = $_POST['scheduled_date'] ?? '';

        $vRow = $pdo->prepare("SELECT * FROM vehicles WHERE id=? AND status='Available'");
        $vRow->execute([$vid]); $vRow = $vRow->fetch();
        if (!$vRow) $tripErrors[] = 'Vehicle not available or under maintenance.';

        $dRow = $pdo->prepare("SELECT *, DATEDIFF(license_expiry,CURDATE()) AS dl FROM drivers WHERE id=? AND status='Available'");
        $dRow->execute([$did]); $dRow = $dRow->fetch();
        if (!$dRow) $tripErrors[] = 'Driver not available or suspended.';
        elseif ($dRow['dl'] < 0) $tripErrors[] = 'Driver license expired — cannot dispatch.';

        if ($vRow && $weight > $vRow['max_capacity'])
            $tripErrors[] = sprintf('Cargo %.0f kg exceeds vehicle capacity %.0f kg.', $weight, $vRow['max_capacity']);
        if (!$origin || !$dest || !$date) $tripErrors[] = 'Fill all required fields.';

        if (empty($tripErrors)) {
            $status = isset($_POST['dispatch_now']) ? 'Dispatched' : 'Draft';
            $pdo->prepare("INSERT INTO trips (vehicle_id,driver_id,origin,destination,cargo_description,cargo_weight,status,scheduled_date) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$vid,$did,$origin,$dest,$cargo,$weight,$status,$date]);
            if ($status === 'Dispatched') {
                $pdo->prepare("UPDATE vehicles SET status='On Trip' WHERE id=?")->execute([$vid]);
                $pdo->prepare("UPDATE drivers SET status='On Duty' WHERE id=?")->execute([$did]);
            }
            redirect('/fleetflownew/dashboard.php', "Trip $status successfully!");
        }
    }
}

// ═══════════════════════════════════════════════════════
// SAFETY OFFICER DATA
// ═══════════════════════════════════════════════════════
if ($_role === 'safety_officer') {
    $totalDrivers   = $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
    $verifiedDrivers= $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='Available' AND license_expiry >= CURDATE()")->fetchColumn();
    $expiringIn7    = $pdo->query("SELECT COUNT(*) FROM drivers WHERE DATEDIFF(license_expiry,CURDATE()) BETWEEN 0 AND 7")->fetchColumn();
    $blockedDrivers = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='Suspended'")->fetchColumn();

    $complianceDrivers = $pdo->query(
        "SELECT d.*,
                DATEDIFF(d.license_expiry, CURDATE()) AS days_to_expiry,
                COUNT(DISTINCT t.id) AS total_trips,
                COUNT(DISTINCT CASE WHEN t.status='Completed' THEN t.id END) AS completed_trips,
                ROUND(CASE WHEN COUNT(DISTINCT t.id)>0
                    THEN COUNT(DISTINCT CASE WHEN t.status='Completed' THEN t.id END)/COUNT(DISTINCT t.id)*100
                    ELSE 0 END, 1) AS completion_rate
         FROM drivers d
         LEFT JOIN trips t ON t.driver_id = d.id
         GROUP BY d.id ORDER BY days_to_expiry ASC"
    )->fetchAll();

    // Analytics metrics
    $docValidPct   = $totalDrivers > 0 ? round($verifiedDrivers / $totalDrivers * 100) : 0;
    $availDriverCnt= $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='Available'")->fetchColumn();
    $availPct      = $totalDrivers > 0 ? round($availDriverCnt / $totalDrivers * 100) : 0;
    $verifPct      = $totalDrivers > 0 ? round($verifiedDrivers / $totalDrivers * 100) : 0;
}

// ═══════════════════════════════════════════════════════
// FINANCIAL ANALYST DATA
// ═══════════════════════════════════════════════════════
if ($_role === 'financial_analyst') {
    $totalFuelCost  = (float)$pdo->query("SELECT COALESCE(SUM(total_cost),0) FROM fuel_expenses")->fetchColumn();
    $totalMaintCost = (float)$pdo->query("SELECT COALESCE(SUM(cost),0) FROM maintenance_logs")->fetchColumn();
    $totalOpCost    = $totalFuelCost + $totalMaintCost;
    $vehicleCount   = (int)$pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
    $avgCostPerVeh  = $vehicleCount > 0 ? round($totalOpCost / $vehicleCount, 2) : 0;

    $perVehicle = $pdo->query(
        "SELECT v.id, v.license_plate, v.model,
                COALESCE(SUM(f.liters),0)       AS total_liters,
                COALESCE(SUM(f.total_cost),0)   AS fuel_cost,
                COALESCE(SUM(m.cost),0)          AS maint_cost,
                COALESCE(SUM(f.total_cost),0)+COALESCE(SUM(m.cost),0) AS total_op
         FROM vehicles v
         LEFT JOIN fuel_expenses f    ON f.vehicle_id = v.id
         LEFT JOIN maintenance_logs m ON m.vehicle_id = v.id
         GROUP BY v.id ORDER BY total_op DESC"
    )->fetchAll();

    $fuelLog = $pdo->query(
        "SELECT f.*, v.license_plate, v.model
         FROM fuel_expenses f JOIN vehicles v ON f.vehicle_id=v.id
         ORDER BY f.expense_date DESC, f.id DESC LIMIT 15"
    )->fetchAll();

    $monthlyCosts = $pdo->query(
        "SELECT DATE_FORMAT(expense_date,'%Y-%m') as month, SUM(total_cost) as fuel_cost
         FROM fuel_expenses WHERE expense_date >= DATE_SUB(CURDATE(),INTERVAL 6 MONTH)
         GROUP BY month ORDER BY month"
    )->fetchAll();

    // Top 3 costliest vehicles
    $top3 = array_slice($perVehicle, 0, 3);
}

// ═══════════════════════════════════════════════════════
// FLEET MANAGER DATA (generic overview)
// ═══════════════════════════════════════════════════════
if ($_role === 'fleet_manager') {
    $activeFleet      = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status NOT IN ('Out of Service')")->fetchColumn();
    $totalFleet       = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
    $maintenanceAlert = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='In Shop'")->fetchColumn();
    $pendingCargo     = $pdo->query("SELECT COUNT(*) FROM trips WHERE status IN ('Draft','Dispatched')")->fetchColumn();
    $activeDrivers    = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='On Duty'")->fetchColumn();
    $utilRate         = $totalFleet > 0 ? round(($activeFleet / $totalFleet) * 100) : 0;
    $opCost           = (float)$pdo->query("SELECT (SELECT COALESCE(SUM(total_cost),0) FROM fuel_expenses)+(SELECT COALESCE(SUM(cost),0) FROM maintenance_logs) AS total")->fetchColumn();
    $expiring         = $pdo->query("SELECT name,license_expiry,DATEDIFF(license_expiry,CURDATE()) AS days_left FROM drivers WHERE DATEDIFF(license_expiry,CURDATE())<=60 ORDER BY days_left ASC LIMIT 5")->fetchAll();
    $recentTrips      = $pdo->query("SELECT t.*,v.license_plate,d.name as driver_name FROM trips t JOIN vehicles v ON t.vehicle_id=v.id JOIN drivers d ON t.driver_id=d.id ORDER BY t.created_at DESC LIMIT 6")->fetchAll();
    $vStatusRows      = $pdo->query("SELECT status,COUNT(*) as cnt FROM vehicles GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Topbar config
$topbarConfig = [
    'tableId' => 'mainTable',
    'groups'  => [],
    'filters' => [],
    'sorts'   => [],
];
if ($_role === 'dispatcher') {
    $topbarConfig['filters'] = ['' => 'All Status', 'Draft' => 'Draft', 'Dispatched' => 'Dispatched', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];
    $topbarConfig['sorts']   = ['6:asc' => 'Date ↑', '6:desc' => 'Date ↓', '4:desc' => 'Weight ↓'];
    $topbarConfig['groups']  = ['status' => 'Status', 'vehicle' => 'Vehicle'];
}
if ($_role === 'safety_officer') {
    $topbarConfig['filters'] = ['' => 'All', 'Available' => 'Available', 'On Duty' => 'On Duty', 'Suspended' => 'Suspended'];
    $topbarConfig['sorts']   = ['2:asc' => 'Expiry ↑ (soonest)', '3:desc' => 'Safety Score ↓'];
}
if ($_role === 'financial_analyst') {
    $topbarConfig['sorts'] = ['4:desc' => 'Total Cost ↓', '4:asc' => 'Total Cost ↑', '2:desc' => 'Fuel Cost ↓'];
    $topbarConfig['groups'] = ['vehicle' => 'Vehicle'];
}

include __DIR__ . '/includes/header.php';
// include __DIR__ . '/includes/topbar.php'; // Removed from dashboard per request

// ─── Error display (dispatcher trip form) ──────────────────────────────────
if (!empty($tripErrors)):
?>
<div class="alert alert-error"><?= implode('<br>', array_map('e', $tripErrors)) ?></div>
<?php endif; ?>

<?php

// ═══════════════════════════════════════════════════════
// ██ DISPATCHER DASHBOARD
// ═══════════════════════════════════════════════════════
if ($_role === 'dispatcher'): ?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <?php
    $kpis = [
        ['Active Trips',       $activeTrips,    'dispatched + in progress', '#2563eb', '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>'],
        ['Draft Trips',        $draftTrips,     'pending dispatch',          '#d97706', '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 7h8M8 12h5"/>'],
        ['Available Vehicles', $availVehicles,  'ready to assign',           '#16a34a', '<rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>'],
        ['Drivers On Duty',    $driversOnDuty,  'currently on trips',        '#714B67', '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/>'],
    ];
    foreach ($kpis as [$label, $val, $sub, $color, $icon]): ?>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:<?= $color ?>18;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                 fill="none" stroke="<?= $color ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <?= $icon ?>
            </svg>
        </div>
        <div>
            <div class="kpi-label"><?= $label ?></div>
            <div class="kpi-value" style="color:<?= $color ?>"><?= $val ?></div>
            <div class="kpi-sub"><?= $sub ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Section header + create button -->
<div class="section-header" style="margin-bottom:16px;">
    <div>
        <div class="section-title">Trip Management</div>
        <div class="text-sm"><?= count($myTrips) ?> trips in system</div>
    </div>
    <button class="btn btn-primary" onclick="openModal('newTripModal')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Dispatch Trip
    </button>
</div>

<!-- Trip Table -->
<div class="card">
    <div class="table-responsive">
        <table id="mainTable">
            <thead>
                <tr>
                    <th>#</th><th>Route</th><th>Vehicle</th><th>Driver</th>
                    <th>Cargo (kg)</th><th>Est. Fuel</th><th>Date</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($myTrips)): ?>
            <tr><td colspan="9" class="text-center" style="padding:32px;color:var(--text-sm);">No trips yet — dispatch your first trip!</td></tr>
            <?php endif; ?>
            <?php foreach ($myTrips as $t):
                $sp = match($t['status']) {
                    'Dispatched' => 'pill-blue', 'Completed' => 'pill-green',
                    'Cancelled'  => 'pill-red',  default => 'pill-gray',
                };
                $overW    = $t['cargo_weight'] > $t['max_capacity'];
                $dExpired = $t['driver_days_left'] < 0;
                // Simplified fuel cost estimate: weight * 0.05 (placeholder formula)
                $estFuel  = number_format($t['cargo_weight'] * 0.05, 0);
            ?>
            <tr data-searchable="<?= e("{$t['id']} {$t['origin']} {$t['destination']} {$t['license_plate']} {$t['driver_name']} {$t['status']}") ?>"
                data-groupStatus="<?= e($t['status']) ?>"
                data-groupVehicle="<?= e($t['license_plate']) ?>">
                <td class="text-sm">#<?= $t['id'] ?></td>
                <td>
                    <div class="fw-600" style="font-size:12px;"><?= e($t['origin']) ?></div>
                    <div class="text-sm">→ <?= e($t['destination']) ?></div>
                </td>
                <td>
                    <div><?= e($t['license_plate']) ?></div>
                    <div class="text-sm"><?= e($t['vehicle_model']) ?></div>
                    <?php if ($overW): ?><span class="pill pill-red" style="font-size:10px;">OVERWEIGHT</span><?php endif; ?>
                </td>
                <td>
                    <div><?= e($t['driver_name']) ?></div>
                    <?php if ($dExpired): ?><span class="pill pill-red" style="font-size:10px;">LIC EXPIRED</span><?php endif; ?>
                </td>
                <td><?= number_format($t['cargo_weight']) ?></td>
                <td class="text-sm">₹<?= $estFuel ?></td>
                <td class="text-sm"><?= e($t['scheduled_date']) ?></td>
                <td><span class="pill <?= $sp ?>"><?= $t['status'] ?></span></td>
                <td>
                    <?php if (in_array($t['status'], ['Draft', 'Dispatched'])): ?>
                    <button class="btn btn-sm btn-ghost" onclick="openStatusModal(<?= $t['id'] ?>,'<?= $t['status'] ?>')">Update</button>
                    <?php else: ?>
                    <span class="text-sm">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- NEW TRIP MODAL -->
<div class="modal-overlay" id="newTripModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Dispatch New Trip</span>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" id="tripForm">
            <input type="hidden" name="_action" value="create_trip">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Vehicle *</label>
                        <select name="vehicle_id" class="form-control" required>
                            <option value="">— Select Vehicle —</option>
                            <?php foreach ($availVehicleList as $v): ?>
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
                            <?php foreach ($availDriverList as $d):
                                $expired = $d['days_left'] < 0; ?>
                            <option value="<?= $d['id'] ?>" <?= $expired ? 'disabled style="color:red"' : '' ?>>
                                <?= e($d['name']) ?><?= $expired ? ' ⚠ LIC EXPIRED' : " (exp: {$d['license_expiry']})" ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Origin *</label>
                        <input type="text" name="origin" class="form-control" placeholder="City, State" required>
                    </div>
                    <div class="form-group">
                        <label>Destination *</label>
                        <input type="text" name="destination" class="form-control" placeholder="City, State" required>
                    </div>
                    <div class="form-group">
                        <label>Cargo Description</label>
                        <input type="text" name="cargo_description" class="form-control" placeholder="e.g. Electronics">
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
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('newTripModal')">Cancel</button>
                <button type="submit" class="btn btn-outline">Save Draft</button>
                <button type="submit" name="dispatch_now" class="btn btn-primary">Dispatch Now</button>
            </div>
        </form>
    </div>
</div>

<!-- STATUS UPDATE MODAL -->
<div class="modal-overlay" id="statusModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <span class="modal-title">Update Trip Status</span>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" action="/fleetflownew/modules/trips.php">
            <input type="hidden" name="_action" value="update_status">
            <input type="hidden" name="trip_id" id="statusTripId">
            <div class="modal-body">
                <div class="form-group">
                    <label>New Status</label>
                    <select name="status" class="form-control" id="statusSelect"></select>
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

<script>
window.vehicleCapacities = <?= json_encode($vehicleCaps) ?>;
function openStatusModal(id, cur){
    document.getElementById('statusTripId').value = id;
    const sel = document.getElementById('statusSelect');
    sel.innerHTML = '';
    ({'Draft':['Dispatched','Cancelled'],'Dispatched':['Completed','Cancelled']}[cur]||[])
        .forEach(s => sel.appendChild(new Option(s,s)));
    sel.dispatchEvent(new Event('change'));
    openModal('statusModal');
}
document.getElementById('statusSelect')?.addEventListener('change', function(){
    document.getElementById('distanceGroup').style.display = this.value==='Completed' ? 'block' : 'none';
});
</script>

<?php
// ═══════════════════════════════════════════════════════
// ██ SAFETY OFFICER DASHBOARD
// ═══════════════════════════════════════════════════════
elseif ($_role === 'safety_officer'): ?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <?php
    $kpis = [
        ['Total Drivers',       $totalDrivers,    'on record',              '#714B67', '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/>'],
        ['Verified Drivers',    $verifiedDrivers, 'valid license + active', '#16a34a', '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
        ['Expiring (7 days)',   $expiringIn7,     'need urgent renewal',    '#d97706', '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'],
        ['Blocked Drivers',     $blockedDrivers,  'suspended from duty',    '#dc2626', '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>'],
    ];
    foreach ($kpis as [$label, $val, $sub, $color, $icon]): ?>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:<?= $color ?>18;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                 fill="none" stroke="<?= $color ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <?= $icon ?>
            </svg>
        </div>
        <div>
            <div class="kpi-label"><?= $label ?></div>
            <div class="kpi-value" style="color:<?= $color ?>"><?= $val ?></div>
            <div class="kpi-sub"><?= $sub ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Driver Compliance Table -->
<div class="section-header" style="margin-bottom:12px;">
    <div class="section-title">Driver Compliance Overview</div>
    <a href="/fleetflownew/modules/drivers.php" class="btn btn-sm btn-ghost">Manage Drivers</a>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="table-responsive">
        <table id="mainTable">
            <thead>
                <tr>
                    <th>Driver</th><th>License #</th><th>Expiry</th>
                    <th>Safety Score</th><th>Completion Rate</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($complianceDrivers as $d):
                $expired      = $d['days_to_expiry'] < 0;
                $expiringSoon = !$expired && $d['days_to_expiry'] <= 7;
                $warnSoon     = !$expired && $d['days_to_expiry'] <= 30;
                $scorePill    = $d['safety_score'] >= 90 ? 'pill-green' : ($d['safety_score'] >= 75 ? 'pill-yellow' : 'pill-red');
                $statusPill   = match($d['status']) { 'Available'=>'pill-green','On Duty'=>'pill-blue','Suspended'=>'pill-red', default=>'pill-gray' };
                $cr = (float)$d['completion_rate'];
                $crColor = $cr >= 80 ? '#16a34a' : ($cr >= 50 ? '#d97706' : '#dc2626');
                $rowStyle = $expired ? 'background:#fff5f5;' : ($expiringSoon ? 'background:#fffbeb;' : '');
            ?>
            <tr data-searchable="<?= e("{$d['name']} {$d['license_number']} {$d['status']}") ?>"
                data-groupStatus="<?= e($d['status']) ?>"
                style="<?= $rowStyle ?>">
                <td class="fw-600"><?= e($d['name']) ?></td>
                <td><code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;"><?= e($d['license_number']) ?></code></td>
                <td>
                    <span style="color:<?= $expired ? 'var(--danger)' : ($warnSoon ? 'var(--warning)' : 'inherit') ?>;">
                        <?= e($d['license_expiry']) ?>
                    </span>
                    <?php if ($expired): ?>
                        <span class="pill pill-red" style="font-size:10px;">EXPIRED</span>
                    <?php elseif ($expiringSoon): ?>
                        <span class="pill pill-orange" style="font-size:10px;"><?= $d['days_to_expiry'] ?>d left</span>
                    <?php elseif ($warnSoon): ?>
                        <span class="pill pill-yellow" style="font-size:10px;"><?= $d['days_to_expiry'] ?>d</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="pill <?= $scorePill ?>"><?= $d['safety_score'] ?>/100</span>
                    <?php if ($d['safety_score'] < 75): ?>
                    <div class="text-sm" style="color:var(--warning);font-size:10px;">⚠ Below threshold</div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-size:12px;font-weight:600;color:<?= $crColor ?>;"><?= $cr ?>%</div>
                    <div style="background:#e2e8f0;border-radius:20px;height:5px;width:80px;margin-top:3px;">
                        <div style="background:<?= $crColor ?>;height:5px;border-radius:20px;width:<?= min($cr,100) ?>%;"></div>
                    </div>
                    <div class="text-sm" style="font-size:10px;"><?= $d['completed_trips'] ?>/<?= $d['total_trips'] ?> trips</div>
                </td>
                <td><span class="pill <?= $statusPill ?>"><?= e($d['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Performance Analytics -->
<div class="section-title" style="margin-bottom:12px;">Compliance Analytics</div>
<div class="dash-grid">
    <div class="card">
        <div class="card-header"><span class="card-title">Driver Health Metrics</span></div>
        <div class="card-body">
            <?php
            $metrics = [
                ['Driver Verification', $verifPct, '#16a34a'],
                ['Document Validity',   $docValidPct, '#2563eb'],
                ['Driver Availability', $availPct,  '#714B67'],
            ];
            foreach ($metrics as [$label, $pct, $color]): ?>
            <div style="margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-size:13px;font-weight:500;"><?= $label ?></span>
                    <span style="font-size:13px;font-weight:700;color:<?= $color ?>;"><?= $pct ?>%</span>
                </div>
                <div style="background:#f1f5f9;border-radius:6px;height:8px;overflow:hidden;">
                    <div style="background:<?= $color ?>;height:8px;border-radius:6px;width:<?= $pct ?>%;transition:width .5s;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Safety Score Distribution</span></div>
        <div class="card-body">
            <?php
            $ranges = [
                ['Excellent (90–100)', 90, 101, '#16a34a'],
                ['Good (75–89)',       75, 90,  '#2563eb'],
                ['Warning (50–74)',    50, 75,  '#d97706'],
                ['Critical (<50)',      0, 50,  '#dc2626'],
            ];
            foreach ($ranges as [$lbl, $lo, $hi, $col]):
                $cnt = count(array_filter($complianceDrivers, fn($d) => $d['safety_score'] >= $lo && $d['safety_score'] < $hi));
                $pct = $totalDrivers > 0 ? round($cnt / $totalDrivers * 100) : 0;
            ?>
            <div class="chart-bar-row">
                <span class="chart-bar-label" style="font-size:11px;"><?= $lbl ?></span>
                <div class="chart-bar-track">
                    <div class="chart-bar-fill" style="width:<?= max($pct,2) ?>%;background:<?= $col ?>;">
                        <?php if ($pct > 12): ?><span class="chart-bar-val"><?= $cnt ?></span><?php endif; ?>
                    </div>
                </div>
                <span style="font-size:11px;color:var(--text-sm);width:20px;text-align:right;"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
// ═══════════════════════════════════════════════════════
// ██ FINANCIAL ANALYST DASHBOARD
// ═══════════════════════════════════════════════════════
elseif ($_role === 'financial_analyst'): ?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <?php
    $kpis = [
        ['Total Fuel Cost',       '₹'.number_format($totalFuelCost,0),   'all vehicles',         '#2563eb', '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>'],
        ['Total Maintenance',     '₹'.number_format($totalMaintCost,0),  'service & repairs',     '#d97706', '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>'],
        ['Total Operational Cost','₹'.number_format($totalOpCost,0),     'fuel + maintenance',    '#714B67', '<path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"/>'],
        ['Avg Cost / Vehicle',    '₹'.number_format($avgCostPerVeh,0),   'across '.$vehicleCount.' vehicles','#16a34a','<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>'],
    ];
    foreach ($kpis as [$label, $val, $sub, $color, $icon]): ?>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:<?= $color ?>18;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                 fill="none" stroke="<?= $color ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <?= $icon ?>
            </svg>
        </div>
        <div>
            <div class="kpi-label"><?= $label ?></div>
            <div class="kpi-value" style="font-size:20px;color:<?= $color ?>"><?= $val ?></div>
            <div class="kpi-sub"><?= $sub ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Vehicle Cost Table -->
<div class="section-header" style="margin-bottom:12px;">
    <div class="section-title">Vehicle-wise Operational Costs</div>
    <a href="/fleetflownew/modules/fuel.php" class="btn btn-sm btn-ghost">Fuel Module</a>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="table-responsive">
        <table id="mainTable">
            <thead>
                <tr><th>Vehicle</th><th>Fuel (L)</th><th>Fuel Cost</th><th>Maintenance</th><th>Total Op. Cost</th><th>Cost Flag</th></tr>
            </thead>
            <tbody>
            <?php
            $maxOpCost = max(array_column($perVehicle,'total_op') ?: [1]);
            foreach ($perVehicle as $pv):
                $flagColor = $pv['total_op'] >= $maxOpCost * 0.8 ? '#dc2626' : ($pv['total_op'] >= $maxOpCost * 0.5 ? '#d97706' : '#16a34a');
            ?>
            <tr data-searchable="<?= e("{$pv['license_plate']} {$pv['model']}") ?>"
                data-groupVehicle="<?= e($pv['license_plate']) ?>">
                <td>
                    <div class="fw-600"><?= e($pv['license_plate']) ?></div>
                    <div class="text-sm"><?= e($pv['model']) ?></div>
                </td>
                <td><?= number_format($pv['total_liters'],1) ?> L</td>
                <td>₹<?= number_format($pv['fuel_cost'],2) ?></td>
                <td>₹<?= number_format($pv['maint_cost'],2) ?></td>
                <td class="fw-600">₹<?= number_format($pv['total_op'],2) ?></td>
                <td>
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $flagColor ?>;"></span>
                    <?php if ($pv['total_op'] >= $maxOpCost * 0.8): ?>
                    <span class="pill pill-red" style="font-size:10px;">HIGH</span>
                    <?php elseif ($pv['total_op'] >= $maxOpCost * 0.5): ?>
                    <span class="pill pill-yellow" style="font-size:10px;">MED</span>
                    <?php else: ?>
                    <span class="pill pill-green" style="font-size:10px;">LOW</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:#f8fafc;font-weight:700;">
                <td>TOTAL</td>
                <td><?= number_format(array_sum(array_column($perVehicle,'total_liters')),1) ?> L</td>
                <td>₹<?= number_format(array_sum(array_column($perVehicle,'fuel_cost')),2) ?></td>
                <td>₹<?= number_format(array_sum(array_column($perVehicle,'maint_cost')),2) ?></td>
                <td>₹<?= number_format($totalOpCost,2) ?></td>
                <td>—</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Financial Charts + Fuel Log -->
<div class="dash-grid">
    <!-- Monthly Cost Trend -->
    <div class="card">
        <div class="card-header"><span class="card-title">Monthly Fuel Cost Trend</span></div>
        <div class="card-body">
            <?php if (empty($monthlyCosts)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>
                </div>
                <h3>No fuel data yet</h3>
            </div>
            <?php else:
                $maxF = max(array_column($monthlyCosts,'fuel_cost') ?: [1]);
                foreach ($monthlyCosts as $mc):
                    $pct = $maxF > 0 ? ($mc['fuel_cost']/$maxF*100) : 0;
            ?>
            <div class="chart-bar-row">
                <span class="chart-bar-label" style="font-size:11px;"><?= $mc['month'] ?></span>
                <div class="chart-bar-track">
                    <div class="chart-bar-fill" style="background:#2563eb;width:<?= max($pct,2) ?>%;">
                        <?php if ($pct > 15): ?><span class="chart-bar-val">₹<?= number_format($mc['fuel_cost'],0) ?></span><?php endif; ?>
                    </div>
                </div>
                <span style="font-size:11px;color:var(--text-sm);width:70px;text-align:right;">₹<?= number_format($mc['fuel_cost'],0) ?></span>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Top 3 Costliest Vehicles -->
    <div class="card">
        <div class="card-header"><span class="card-title">Top Costliest Vehicles</span></div>
        <div class="card-body">
            <?php foreach ($top3 as $i => $v): ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div style="width:28px;height:28px;border-radius:50%;background:<?= ['#dc2626','#d97706','#f59e0b'][$i] ?>20;
                            color:<?= ['#dc2626','#d97706','#f59e0b'][$i] ?>;font-weight:700;font-size:13px;
                            display:flex;align-items:center;justify-content:center;"><?= $i+1 ?></div>
                <div style="flex:1;">
                    <div class="fw-600"><?= e($v['license_plate']) ?> <span class="text-sm"><?= e($v['model']) ?></span></div>
                    <div style="background:#f1f5f9;border-radius:6px;height:6px;margin-top:4px;overflow:hidden;">
                        <?php $pct = $maxOpCost > 0 ? ($v['total_op']/$maxOpCost*100) : 0; ?>
                        <div style="background:<?= ['#dc2626','#d97706','#f59e0b'][$i] ?>;height:6px;width:<?= $pct ?>%;"></div>
                    </div>
                </div>
                <span class="fw-600" style="font-size:13px;">₹<?= number_format($v['total_op'],0) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recent Fuel Log -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Fuel Expense Log</span>
        <a href="/fleetflownew/modules/fuel.php" class="btn btn-sm btn-ghost">View All</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Vehicle</th><th>Date</th><th>Liters</th><th>₹/L</th><th>Total</th><th>Odometer</th><th>Station</th></tr></thead>
            <tbody>
            <?php foreach ($fuelLog as $fx): ?>
            <tr>
                <td><div class="fw-600"><?= e($fx['license_plate']) ?></div><div class="text-sm"><?= e($fx['model']) ?></div></td>
                <td><?= e($fx['expense_date']) ?></td>
                <td><?= number_format($fx['liters'],1) ?> L</td>
                <td>₹<?= number_format($fx['cost_per_liter'],3) ?></td>
                <td class="fw-600">₹<?= number_format($fx['total_cost'],2) ?></td>
                <td><?= number_format($fx['odometer_reading']) ?> km</td>
                <td><?= e($fx['station']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($fuelLog)): ?>
            <tr><td colspan="7" class="text-center" style="padding:24px;color:var(--text-sm);">No fuel entries yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// ═══════════════════════════════════════════════════════
// ██ FLEET MANAGER DASHBOARD (unchanged original)
// ═══════════════════════════════════════════════════════
else: ?>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#714B6715;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#714B67" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        </div>
        <div><div class="kpi-label">Active Fleet</div><div class="kpi-value"><?= $activeFleet ?></div><div class="kpi-sub">of <?= $totalFleet ?> total</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#fef3c720;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
        </div>
        <div><div class="kpi-label">In Maintenance</div><div class="kpi-value" style="color:<?= $maintenanceAlert > 0 ? 'var(--warning)' : 'var(--text)' ?>"><?= $maintenanceAlert ?></div><div class="kpi-sub">vehicles in shop</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#dbeafe20;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>
        </div>
        <div><div class="kpi-label">Utilization Rate</div><div class="kpi-value"><?= $utilRate ?>%</div><div class="kpi-sub"><?= $activeDrivers ?> drivers on duty</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#dcfce720;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
        </div>
        <div><div class="kpi-label">Active Trips</div><div class="kpi-value"><?= $pendingCargo ?></div><div class="kpi-sub">draft + dispatched</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#fee2e220;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        </div>
        <div><div class="kpi-label">Total Op. Cost</div><div class="kpi-value" style="font-size:20px;">₹<?= number_format($opCost,0) ?></div><div class="kpi-sub">fuel + maintenance</div></div>
    </div>
</div>

<div class="dash-grid">
    <div class="card">
        <div class="card-header"><span class="card-title">Recent Trips</span><a href="/fleetflownew/modules/trips.php" class="btn btn-sm btn-ghost">View All</a></div>
        <div class="table-responsive">
            <table><thead><tr><th>Route</th><th>Vehicle</th><th>Driver</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentTrips as $t):
                $sp = match($t['status']){'Dispatched'=>'pill-blue','Completed'=>'pill-green','Cancelled'=>'pill-red',default=>'pill-gray'};
            ?>
            <tr><td><div class="fw-600" style="font-size:12px;"><?= e($t['origin']) ?></div><div class="text-sm">→ <?= e($t['destination']) ?></div></td>
            <td><?= e($t['license_plate']) ?></td><td><?= e($t['driver_name']) ?></td>
            <td><span class="pill <?= $sp ?>"><?= $t['status'] ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">⚠ License Expiry Alerts</span></div>
        <div class="card-body">
            <?php if (empty($expiring)): ?>
            <div class="empty-state"><div class="empty-state-icon"><svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div><h3>All licenses valid</h3></div>
            <?php else: foreach ($expiring as $d): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
                <div><div class="fw-600"><?= e($d['name']) ?></div><div class="text-sm">Expires <?= e($d['license_expiry']) ?></div></div>
                <?php if ($d['days_left'] < 0): ?><span class="pill pill-red">EXPIRED</span>
                <?php elseif ($d['days_left'] <= 30): ?><span class="pill pill-orange"><?= $d['days_left'] ?>d left</span>
                <?php else: ?><span class="pill pill-yellow"><?= $d['days_left'] ?>d left</span><?php endif; ?>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Fleet Status Breakdown</span></div>
        <div class="card-body">
            <?php $statuses=['Available'=>'#16a34a','On Trip'=>'#2563eb','In Shop'=>'#d97706','Out of Service'=>'#dc2626'];
            foreach ($statuses as $s => $col):
                $cnt = $vStatusRows[$s] ?? 0;
                $pct = $totalFleet > 0 ? round(($cnt/$totalFleet)*100) : 0; ?>
            <div class="chart-bar-row">
                <span class="chart-bar-label"><?= $s ?></span>
                <div class="chart-bar-track">
                    <div class="chart-bar-fill" style="width:<?= max($pct,3) ?>%;background:<?= $col ?>;">
                        <?php if ($pct > 10): ?><span class="chart-bar-val"><?= $cnt ?></span><?php endif; ?>
                    </div>
                </div>
                <span style="font-size:12px;color:var(--text-sm);width:30px;text-align:right;"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Quick Actions</span></div>
        <div class="card-body" style="display:grid;gap:10px;">
            <a href="/fleetflownew/modules/trips.php?action=new" class="btn btn-primary">+ Dispatch New Trip</a>
            <a href="/fleetflownew/modules/vehicles.php?action=new" class="btn btn-outline">+ Add Vehicle</a>
            <a href="/fleetflownew/modules/drivers.php?action=new" class="btn btn-outline">+ Add Driver</a>
            <a href="/fleetflownew/modules/maintenance.php?action=new" class="btn btn-outline">+ Log Maintenance</a>
            <a href="/fleetflownew/modules/fuel.php?action=new" class="btn btn-outline">+ Log Fuel</a>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
