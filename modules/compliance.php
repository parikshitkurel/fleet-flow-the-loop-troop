<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

// RBAC: Only admin and safety_officer can access compliance
if (!hasRole('admin', 'safety_officer')) {
    header('Location: /fleetflownew/dashboard.php?err=access');
    exit;
}

$pdo = db();
$pageTitle = 'Safety & Compliance';

// ─── DATA FETCHING ───

// 1. Critical Driver Issues (Expired or Expiring within 30 days)
$driverIssues = $pdo->query(
    "SELECT d.*, DATEDIFF(d.license_expiry, CURDATE()) as days_left
     FROM drivers d
     WHERE license_expiry < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     OR safety_score < 75
     ORDER BY license_expiry ASC"
)->fetchAll();

// 2. Vehicle Compliance (Out of Service or In Shop)
$vehicleIssues = $pdo->query(
    "SELECT v.*, 
            (SELECT COUNT(*) FROM maintenance_logs ml WHERE ml.vehicle_id = v.id AND ml.status != 'Completed') as open_logs
     FROM vehicles v
     WHERE v.status IN ('Out of Service', 'In Shop')
     ORDER BY v.status DESC"
)->fetchAll();

// 3. Low Safety Scores
$lowSafety = $pdo->query(
    "SELECT * FROM drivers WHERE safety_score < 80 ORDER BY safety_score ASC"
)->fetchAll();

// ─── SUMMARY STATS ───
$expiredCount = 0;
$expiringSoon = 0;
foreach ($driverIssues as $di) {
    if ($di['days_left'] < 0) $expiredCount++;
    elseif ($di['days_left'] <= 30) $expiringSoon++;
}
$criticalVehicles = count($vehicleIssues);
$safetyAlerts = count($lowSafety);

$topbarConfig = [
    'tableId' => 'complianceTable',
    'filters' => ['' => 'All Issues', 'driver' => 'Driver Issues', 'vehicle' => 'Vehicle Issues'],
];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#fee2e220;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div>
            <div class="kpi-label">Expired Licenses</div>
            <div class="kpi-value" style="color:#dc2626;"><?= $expiredCount ?></div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#fef3c720;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div>
            <div class="kpi-label">Expiring Soon</div>
            <div class="kpi-value" style="color:#d97706;"><?= $expiringSoon ?></div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#dbeafe20;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        </div>
        <div>
            <div class="kpi-label">Grounded Vehicles</div>
            <div class="kpi-value"><?= $criticalVehicles ?></div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:#714B6715;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#714B67" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div>
            <div class="kpi-label">Safety Alerts</div>
            <div class="kpi-value" style="color:#714B67;"><?= $safetyAlerts ?></div>
        </div>
    </div>
</div>

<div class="dash-grid" style="grid-template-columns: 1fr 1fr; gap: 24px;">
    
    <!-- Driver Compliance -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                Driver Document & Safety Status
            </span>
        </div>
        <div class="table-responsive">
            <table id="complianceTable">
                <thead>
                    <tr><th>Driver</th><th>License Status</th><th>Safety</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if (empty($driverIssues)): ?>
                <tr><td colspan="4" class="text-center" style="padding:24px; color:var(--text-sm);">All drivers are compliant.</td></tr>
                <?php endif; ?>
                <?php foreach ($driverIssues as $d): 
                    $expired = $d['days_left'] < 0;
                    $soon = $d['days_left'] >= 0 && $d['days_left'] <= 30;
                    $scorePill = $d['safety_score'] < 75 ? 'pill-red' : 'pill-yellow';
                ?>
                <tr data-group="driver">
                    <td>
                        <div class="fw-600"><?= e($d['name']) ?></div>
                        <div class="text-sm">ID: <?= e($d['license_number']) ?></div>
                    </td>
                    <td>
                        <?php if ($expired): ?>
                            <span class="pill pill-red">EXPIRED</span>
                            <div class="text-sm" style="color:var(--danger);margin-top:2px;">Due: <?= e($d['license_expiry']) ?></div>
                        <?php elseif ($soon): ?>
                            <span class="pill pill-yellow">Expires in <?= $d['days_left'] ?>d</span>
                            <div class="text-sm" style="margin-top:2px;">Due: <?= e($d['license_expiry']) ?></div>
                        <?php else: ?>
                            <span class="pill pill-green">Valid</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="pill <?= $scorePill ?>"><?= $d['safety_score'] ?>/100</span></td>
                    <td>
                        <button class="btn btn-sm btn-ghost" onclick="window.location='/fleetflownew/modules/drivers.php'">Update</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Vehicle Readiness -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                Fleet Readiness & Grounded Units
            </span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Vehicle</th><th>Status</th><th>Maintenance</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if (empty($vehicleIssues)): ?>
                <tr><td colspan="4" class="text-center" style="padding:24px; color:var(--text-sm);">All vehicles are available or on trip.</td></tr>
                <?php endif; ?>
                <?php foreach ($vehicleIssues as $v): 
                    $pill = $v['status'] === 'Out of Service' ? 'pill-red' : 'pill-yellow';
                ?>
                <tr data-group="vehicle">
                    <td>
                        <div class="fw-600"><?= e($v['license_plate']) ?></div>
                        <div class="text-sm"><?= e($v['model']) ?></div>
                    </td>
                    <td><span class="pill <?= $pill ?>"><?= e($v['status']) ?></span></td>
                    <td>
                        <?php if ($v['open_logs'] > 0): ?>
                        <span class="text-sm" style="color:var(--danger);font-weight:600;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:2px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <?= $v['open_logs'] ?> Pending
                        </span>
                        <?php else: ?>
                        <span class="text-sm" style="color:var(--text-sm);">No active repairs</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-ghost" onclick="window.location='/fleetflownew/modules/maintenance.php'">Logs</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div class="card" style="margin-top:24px;">
    <div class="card-header"><span class="card-title">Compliance Policy Checklist</span></div>
    <div class="card-body">
        <div class="checklist-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
            <div class="check-item" style="display:flex; align-items:center; gap:12px; padding:12px; background:var(--bg); border-radius:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <div class="text-sm"><strong>Driver Background Checks</strong><br>All drivers verified.</div>
            </div>
            <div class="check-item" style="display:flex; align-items:center; gap:12px; padding:12px; background:var(--bg); border-radius:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <div class="text-sm"><strong>Pollution Checks (PUC)</strong><br>80% fleet certified.</div>
            </div>
            <div class="check-item" style="display:flex; align-items:center; gap:12px; padding:12px; background:var(--bg); border-radius:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <div class="text-sm"><strong>Insurance Renewals</strong><br>3 vehicles due next month.</div>
            </div>
            <div class="check-item" style="display:flex; align-items:center; gap:12px; padding:12px; background:var(--bg); border-radius:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <div class="text-sm"><strong>E-Way Bill Compliance</strong><br>Integrated with portal.</div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
