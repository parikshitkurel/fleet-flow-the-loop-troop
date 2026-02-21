<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$pdo = db();
$pageTitle = 'Analytics';

// Fuel efficiency: km / total liters per vehicle (based on completed trips & odometer delta)
$fuelEff = $pdo->query(
    "SELECT v.license_plate, v.model, v.odometer,
            COALESCE(SUM(f.liters),0) as total_liters,
            COALESCE(SUM(f.total_cost),0) as total_fuel,
            COUNT(DISTINCT t.id) as trips_completed
     FROM vehicles v
     LEFT JOIN fuel_expenses f ON f.vehicle_id = v.id
     LEFT JOIN trips t ON t.vehicle_id = v.id AND t.status = 'Completed'
     GROUP BY v.id ORDER BY total_liters DESC"
)->fetchAll();

// Monthly fuel costs (last 6 months)
$monthlyCosts = $pdo->query(
    "SELECT DATE_FORMAT(expense_date,'%Y-%m') as month,
            SUM(total_cost) as fuel_cost
     FROM fuel_expenses
     WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY month ORDER BY month"
)->fetchAll();

// Trip stats
$tripStats = $pdo->query(
    "SELECT status, COUNT(*) as cnt FROM trips GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$totalTrips = array_sum($tripStats);

// Driver performance
$driverPerf = $pdo->query(
    "SELECT d.name, d.safety_score, COUNT(t.id) as trip_count,
            COALESCE(SUM(t.distance_km),0) as total_km
     FROM drivers d
     LEFT JOIN trips t ON t.driver_id = d.id AND t.status = 'Completed'
     GROUP BY d.id ORDER BY trip_count DESC LIMIT 6"
)->fetchAll();

// Overall KPIs
$totalFuelCost  = $pdo->query("SELECT COALESCE(SUM(total_cost),0) FROM fuel_expenses")->fetchColumn();
$totalMaintCost = $pdo->query("SELECT COALESCE(SUM(cost),0) FROM maintenance_logs")->fetchColumn();
$completedTrips = $tripStats['Completed'] ?? 0;
$totalDistance  = $pdo->query("SELECT COALESCE(SUM(distance_km),0) FROM trips WHERE status='Completed'")->fetchColumn();
$totalFuelL     = $pdo->query("SELECT COALESCE(SUM(liters),0) FROM fuel_expenses")->fetchColumn();
$fleetKmPerL    = $totalFuelL > 0 ? round($totalDistance / $totalFuelL, 2) : 0;

$vehicles = $pdo->query("SELECT * FROM vehicles")->fetchAll();
$totalVehicles = count($vehicles);
$activeVehicles = count(array_filter($vehicles, fn($v) => $v['status'] !== 'Out of Service'));
$utilRate = $totalVehicles > 0 ? round($activeVehicles / $totalVehicles * 100) : 0;

include __DIR__ . '/../includes/header.php';
?>

<!-- Summary KPIs -->
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:24px;">
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#714B6715;">📦</div>
        <div><div class="kpi-label">Trips Completed</div><div class="kpi-value"><?= $completedTrips ?></div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#dbeafe20;">🛣️</div>
        <div><div class="kpi-label">Total Distance</div><div class="kpi-value" style="font-size:20px;"><?= number_format($totalDistance) ?> km</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#dcfce720;">⛽</div>
        <div><div class="kpi-label">Fleet km/L</div><div class="kpi-value"><?= $fleetKmPerL ?></div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#fef3c720;">📊</div>
        <div><div class="kpi-label">Utilization</div><div class="kpi-value"><?= $utilRate ?>%</div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#fee2e220;">💰</div>
        <div><div class="kpi-label">Total Op. Cost</div><div class="kpi-value" style="font-size:18px;">$<?= number_format($totalFuelCost + $totalMaintCost, 0) ?></div></div>
    </div>
</div>

<div class="dash-grid">
    <!-- Fuel Efficiency by Vehicle -->
    <div class="card">
        <div class="card-header"><span class="card-title">⛽ Fuel Efficiency by Vehicle</span></div>
        <div class="card-body">
            <?php
            $maxL = max(array_column($fuelEff, 'total_liters') ?: [1]);
            foreach ($fuelEff as $fe):
                $pct = $maxL > 0 ? ($fe['total_liters'] / $maxL * 100) : 0;
                $kmL = $fe['total_liters'] > 0 && $fe['trips_completed'] > 0
                    ? round(($fe['trips_completed'] * 400) / $fe['total_liters'], 1) // approximate
                    : 'N/A';
            ?>
            <div class="chart-bar-row">
                <span class="chart-bar-label" style="font-size:11px;"><?= e($fe['license_plate']) ?></span>
                <div class="chart-bar-track">
                    <div class="chart-bar-fill" style="width:<?= max($pct,2) ?>%;">
                        <?php if ($pct > 15): ?><span class="chart-bar-val"><?= number_format($fe['total_liters'],0) ?>L</span><?php endif; ?>
                    </div>
                </div>
                <span style="font-size:11px;color:var(--text-sm);width:50px;text-align:right;"><?= $fe['total_liters'] > 0 ? number_format($fe['total_liters'],0).'L' : '0L' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Trip Status Breakdown -->
    <div class="card">
        <div class="card-header"><span class="card-title">🧭 Trip Status Distribution</span></div>
        <div class="card-body">
            <?php
            $statusColors = ['Completed'=>'#16a34a','Dispatched'=>'#2563eb','Draft'=>'#64748b','Cancelled'=>'#dc2626'];
            foreach ($tripStats as $status => $cnt):
                $pct = $totalTrips > 0 ? round($cnt / $totalTrips * 100) : 0;
                $color = $statusColors[$status] ?? '#888';
            ?>
            <div class="chart-bar-row">
                <span class="chart-bar-label" style="font-size:12px;"><?= $status ?></span>
                <div class="chart-bar-track">
                    <div class="chart-bar-fill" style="width:<?= max($pct,3) ?>%;background:<?= $color ?>;">
                        <?php if ($pct > 10): ?><span class="chart-bar-val"><?= $cnt ?> (<?= $pct ?>%)</span><?php endif; ?>
                    </div>
                </div>
                <span style="font-size:11px;color:var(--text-sm);width:30px;text-align:right;"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!$totalTrips): ?>
            <div class="empty-state"><div class="empty-state-icon">📊</div><h3>No trip data yet</h3></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Driver Performance -->
    <div class="card">
        <div class="card-header"><span class="card-title">👤 Driver Performance</span></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Driver</th><th>Trips</th><th>Distance</th><th>Safety Score</th></tr></thead>
                <tbody>
                <?php foreach ($driverPerf as $dp): ?>
                <?php $sc = $dp['safety_score']; $scPill = $sc >= 90 ? 'pill-green' : ($sc >= 70 ? 'pill-yellow' : 'pill-red'); ?>
                <tr>
                    <td class="fw-600"><?= e($dp['name']) ?></td>
                    <td><?= $dp['trip_count'] ?></td>
                    <td><?= number_format($dp['total_km']) ?> km</td>
                    <td><span class="pill <?= $scPill ?>"><?= $sc ?>/100</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Monthly Fuel Cost -->
    <div class="card">
        <div class="card-header"><span class="card-title">📈 Monthly Fuel Cost</span></div>
        <div class="card-body">
            <?php if (empty($monthlyCosts)): ?>
            <div class="empty-state"><div class="empty-state-icon">⛽</div><h3>No fuel data</h3></div>
            <?php else: ?>
            <?php $maxFuel = max(array_column($monthlyCosts, 'fuel_cost') ?: [1]); ?>
            <?php foreach ($monthlyCosts as $mc):
                $pct = $maxFuel > 0 ? ($mc['fuel_cost'] / $maxFuel * 100) : 0;
            ?>
            <div class="chart-bar-row">
                <span class="chart-bar-label" style="font-size:11px;"><?= $mc['month'] ?></span>
                <div class="chart-bar-track">
                    <div class="chart-bar-fill" style="background:#2563eb;width:<?= max($pct,2) ?>%;">
                        <?php if ($pct > 15): ?><span class="chart-bar-val">$<?= number_format($mc['fuel_cost'],0) ?></span><?php endif; ?>
                    </div>
                </div>
                <span style="font-size:11px;color:var(--text-sm);width:65px;text-align:right;">$<?= number_format($mc['fuel_cost'],0) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
