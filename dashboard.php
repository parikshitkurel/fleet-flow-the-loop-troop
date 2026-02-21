<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
requireLogin();

$pageTitle = 'Dashboard';

$pdo = db();

// KPIs
$activeFleet    = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status NOT IN ('Out of Service')")->fetchColumn();
$totalFleet     = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$maintenanceAlert = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'In Shop'")->fetchColumn();
$pendingCargo   = $pdo->query("SELECT COUNT(*) FROM trips WHERE status IN ('Draft','Dispatched')")->fetchColumn();
$activeDrivers  = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status = 'On Duty'")->fetchColumn();
$utilRate       = $totalFleet > 0 ? round(($activeFleet / $totalFleet) * 100) : 0;

// Expiring licenses (within 60 days or expired)
$expiring = $pdo->query(
    "SELECT name, license_expiry, DATEDIFF(license_expiry, CURDATE()) AS days_left
     FROM drivers WHERE DATEDIFF(license_expiry, CURDATE()) <= 60
     ORDER BY days_left ASC LIMIT 5"
)->fetchAll();

// Recent trips
$recentTrips = $pdo->query(
    "SELECT t.*, v.license_plate, d.name as driver_name
     FROM trips t
     JOIN vehicles v ON t.vehicle_id = v.id
     JOIN drivers d ON t.driver_id = d.id
     ORDER BY t.created_at DESC LIMIT 6"
)->fetchAll();

// Total operational cost
$opCost = $pdo->query(
    "SELECT (SELECT COALESCE(SUM(total_cost),0) FROM fuel_expenses) +
            (SELECT COALESCE(SUM(cost),0) FROM maintenance_logs) AS total"
)->fetchColumn();

// Vehicle status breakdown
$vStatusRows = $pdo->query(
    "SELECT status, COUNT(*) as cnt FROM vehicles GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

include __DIR__ . '/includes/header.php';
?>

<!-- KPI CARDS -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#714B6715;">🚛</div>
        <div>
            <div class="kpi-label">Active Fleet</div>
            <div class="kpi-value"><?= $activeFleet ?></div>
            <div class="kpi-sub">of <?= $totalFleet ?> total vehicles</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#fef3c720;">🔧</div>
        <div>
            <div class="kpi-label">In Maintenance</div>
            <div class="kpi-value" style="color:<?= $maintenanceAlert > 0 ? 'var(--warning)' : 'var(--text)' ?>"><?= $maintenanceAlert ?></div>
            <div class="kpi-sub">vehicles in shop</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#dbeafe20;">📊</div>
        <div>
            <div class="kpi-label">Utilization Rate</div>
            <div class="kpi-value"><?= $utilRate ?>%</div>
            <div class="kpi-sub"><?= $activeDrivers ?> drivers on duty</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#dcfce720;">📦</div>
        <div>
            <div class="kpi-label">Active Trips</div>
            <div class="kpi-value"><?= $pendingCargo ?></div>
            <div class="kpi-sub">draft + dispatched</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#fee2e220;">💰</div>
        <div>
            <div class="kpi-label">Total Op. Cost</div>
            <div class="kpi-value" style="font-size:20px;">$<?= number_format($opCost, 0) ?></div>
            <div class="kpi-sub">fuel + maintenance</div>
        </div>
    </div>
</div>

<!-- GRID -->
<div class="dash-grid">
    <!-- Recent Trips -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Trips</span>
            <a href="/fleetflow/modules/trips.php" class="btn btn-sm btn-ghost">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Route</th><th>Vehicle</th><th>Driver</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentTrips as $t): ?>
                    <?php
                    $statusPill = match($t['status']) {
                        'Dispatched' => 'pill-blue',
                        'Completed'  => 'pill-green',
                        'Cancelled'  => 'pill-red',
                        default      => 'pill-gray',
                    };
                    ?>
                    <tr>
                        <td>
                            <div class="fw-600" style="font-size:12px;"><?= e($t['origin']) ?></div>
                            <div class="text-sm">→ <?= e($t['destination']) ?></div>
                        </td>
                        <td><?= e($t['license_plate']) ?></td>
                        <td><?= e($t['driver_name']) ?></td>
                        <td><span class="pill <?= $statusPill ?>"><?= $t['status'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- License Alerts -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">⚠ License Expiry Alerts</span>
        </div>
        <div class="card-body">
            <?php if (empty($expiring)): ?>
            <div class="empty-state"><div class="empty-state-icon">✅</div><h3>All licenses valid</h3></div>
            <?php else: ?>
            <?php foreach ($expiring as $d): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
                <div>
                    <div class="fw-600"><?= e($d['name']) ?></div>
                    <div class="text-sm">Expires <?= e($d['license_expiry']) ?></div>
                </div>
                <?php if ($d['days_left'] < 0): ?>
                    <span class="pill pill-red">EXPIRED</span>
                <?php elseif ($d['days_left'] <= 30): ?>
                    <span class="pill pill-orange"><?= $d['days_left'] ?>d left</span>
                <?php else: ?>
                    <span class="pill pill-yellow"><?= $d['days_left'] ?>d left</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Vehicle Status Breakdown -->
    <div class="card">
        <div class="card-header"><span class="card-title">Fleet Status Breakdown</span></div>
        <div class="card-body">
            <?php
            $statuses = ['Available' => 'pill-green', 'On Trip' => 'pill-blue', 'In Shop' => 'pill-yellow', 'Out of Service' => 'pill-red'];
            foreach ($statuses as $s => $cls):
                $cnt = $vStatusRows[$s] ?? 0;
                $pct = $totalFleet > 0 ? round(($cnt / $totalFleet) * 100) : 0;
            ?>
            <div class="chart-bar-row">
                <span class="chart-bar-label"><?= $s ?></span>
                <div class="chart-bar-track">
                    <div class="chart-bar-fill" style="width:<?= max($pct,3) ?>%;background:<?= ['Available'=>'#16a34a','On Trip'=>'#2563eb','In Shop'=>'#d97706','Out of Service'=>'#dc2626'][$s] ?>">
                        <?php if ($pct > 10): ?><span class="chart-bar-val"><?= $cnt ?></span><?php endif; ?>
                    </div>
                </div>
                <span style="font-size:12px;color:var(--text-sm);width:30px;text-align:right;"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header"><span class="card-title">Quick Actions</span></div>
        <div class="card-body" style="display:grid;gap:10px;">
            <a href="/fleetflow/modules/trips.php?action=new" class="btn btn-primary">🧭 Dispatch New Trip</a>
            <a href="/fleetflow/modules/vehicles.php?action=new" class="btn btn-outline">🚛 Add Vehicle</a>
            <a href="/fleetflow/modules/drivers.php?action=new" class="btn btn-outline">👤 Add Driver</a>
            <a href="/fleetflow/modules/maintenance.php?action=new" class="btn btn-outline">🔧 Log Maintenance</a>
            <a href="/fleetflow/modules/fuel.php?action=new" class="btn btn-outline">⛽ Log Fuel</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
