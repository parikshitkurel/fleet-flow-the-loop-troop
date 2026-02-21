<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
// RBAC: all roles can view maintenance; only fleet_manager, safety_officer, financial_analyst can write
if (!hasRole('admin','fleet_manager','dispatcher','safety_officer','financial_analyst')) {
    requireRole('admin'); // redirects unauthorized
}
$canWrite = hasRole('admin','fleet_manager','safety_officer','financial_analyst');

$pdo = db();
$pageTitle = 'Maintenance Logs';
$action = $_GET['action'] ?? 'list';

// ─── ADD LOG ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add') {
    requireRole('admin', 'fleet_manager', 'safety_officer', 'financial_analyst');

    $vid   = (int)($_POST['vehicle_id'] ?? 0);
    $type  = trim($_POST['service_type'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $cost  = (float)($_POST['cost'] ?? 0);
    $date  = $_POST['service_date'] ?? date('Y-m-d');
    $tech  = trim($_POST['technician'] ?? '');
    $stat  = $_POST['status'] ?? 'Scheduled';

    if (!$vid || !$type) {
        redirect('/fleetflownew/modules/maintenance.php', 'Select a vehicle and service type.', 'error');
    }
    if ($tech && preg_match('/[0-9]/', $tech)) {
        redirect('/fleetflownew/modules/maintenance.php', 'Technician name cannot contain numeric values.', 'error');
    }

    // Auto-generate log_id: LOG-XXXXXX
    $logId = 'LOG-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

    $pdo->prepare(
        "INSERT INTO maintenance_logs (log_id, vehicle_id, service_type, description, cost, service_date, technician, status) VALUES (?,?,?,?,?,?,?,?)"
    )->execute([$logId, $vid, $type, $desc, $cost, $date, $tech, $stat]);

    // Auto: set vehicle to In Shop if In Progress
    if ($stat === 'In Progress') {
        $pdo->prepare("UPDATE vehicles SET status='In Shop' WHERE id=? AND status='Available'")->execute([$vid]);
    }
    redirect('/fleetflownew/modules/maintenance.php', 'Maintenance log added.');
}

// ─── COMPLETE / UPDATE ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'complete') {
    requireRole('admin', 'fleet_manager', 'safety_officer', 'financial_analyst');
    $id = (int)($_POST['id'] ?? 0);
    // Get vehicle_id
    $row = $pdo->prepare("SELECT vehicle_id FROM maintenance_logs WHERE id=?");
    $row->execute([$id]);
    $ml = $row->fetch();
    if ($ml) {
        $pdo->prepare("UPDATE maintenance_logs SET status='Completed' WHERE id=?")->execute([$id]);
        // Release vehicle back to Available
        $pdo->prepare("UPDATE vehicles SET status='Available' WHERE id=? AND status='In Shop'")->execute([$ml['vehicle_id']]);
    }
    redirect('/fleetflownew/modules/maintenance.php', 'Service completed. Vehicle released.');
}

$logs = $pdo->query(
    "SELECT ml.*, v.license_plate, v.model 
     FROM maintenance_logs ml
     JOIN vehicles v ON ml.vehicle_id = v.id
     ORDER BY ml.service_date DESC"
)->fetchAll();

$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY model")->fetchAll();

// Summary per vehicle
$summary = $pdo->query(
    "SELECT v.license_plate, v.model, COALESCE(SUM(ml.cost),0) as total_maint
     FROM vehicles v
     LEFT JOIN maintenance_logs ml ON ml.vehicle_id = v.id
     GROUP BY v.id ORDER BY total_maint DESC"
)->fetchAll();

$topbarConfig = [
    'tableId' => 'maintTable',
    'filters' => ['' => 'All', 'Scheduled' => 'Scheduled', 'In Progress' => 'In Progress', 'Completed' => 'Completed'],
    'sorts'   => ['5:desc' => 'Date ↓', '5:asc' => 'Date ↑', '4:desc' => 'Cost ↓'],
    'groups'  => ['vehicle' => 'Vehicle', 'status' => 'Status'],
];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="section-header">
    <div>
        <div class="section-title">Maintenance Logs</div>
        <div class="text-sm"><?= count($logs) ?> service records</div>
    </div>
    <?php if ($canWrite): ?>
    <button class="btn btn-primary" onclick="openModal('maintModal')">+ Log Service</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table id="maintTable">
            <thead>
                <tr><th>Log ID</th><th>Vehicle</th><th>Service Type</th><th>Description</th><th>Cost</th><th>Date</th><th>Technician</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="8" class="text-center" style="padding:32px;color:var(--text-sm);">No logs yet</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $l): ?>
            <?php
            $sp = match($l['status']) {
                'Completed'   => 'pill-green',
                'In Progress' => 'pill-yellow',
                'Scheduled'   => 'pill-blue',
                default       => 'pill-gray',
            };
            ?>
            <tr data-searchable="<?= e("{$l['log_id']} {$l['license_plate']} {$l['model']} {$l['service_type']} {$l['technician']} {$l['status']}") ?>"
                data-groupVehicle="<?= e($l['license_plate']) ?>"
                data-groupStatus="<?= e($l['status']) ?>">
                <td><code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;font-size:11px;"><?= e($l['log_id'] ?? '—') ?></code></td>
                <td>
                    <div class="fw-600"><?= e($l['license_plate']) ?></div>
                    <div class="text-sm"><?= e($l['model']) ?></div>
                </td>
                <td class="fw-600"><?= e($l['service_type']) ?></td>
                <td style="max-width:200px;white-space:normal;"><?= e($l['description']) ?></td>
                <td>₹<?= number_format($l['cost'], 2) ?></td>
                <td><?= e($l['service_date']) ?></td>
                <td><?= e($l['technician']) ?></td>
                <td><span class="pill <?= $sp ?>"><?= $l['status'] ?></span></td>
                <td><?php if ($canWrite && $l['status'] !== 'Completed'): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="_action" value="complete">
                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Mark as completed and release vehicle?')">✓ Complete</button>
                    </form>
                    <?php elseif (!$canWrite): ?><span class="text-sm">View Only</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Cost Summary -->
<div class="card">
    <div class="card-header"><span class="card-title">Maintenance Cost by Vehicle</span></div>
    <div class="card-body">
        <?php
        $maxCost = max(array_column($summary, 'total_maint') ?: [1]);
        foreach ($summary as $s):
            $pct = $maxCost > 0 ? ($s['total_maint'] / $maxCost * 100) : 0;
        ?>
        <div class="chart-bar-row">
            <span class="chart-bar-label" style="font-size:11px;"><?= e($s['license_plate']) ?></span>
            <div class="chart-bar-track">
                <div class="chart-bar-fill" style="width:<?= max($pct,2) ?>%;">
                    <?php if ($pct > 15): ?><span class="chart-bar-val">₹<?= number_format($s['total_maint'], 0) ?></span><?php endif; ?>
                </div>
            </div>
            <span style="font-size:11px;color:var(--text-sm);width:70px;text-align:right;">₹<?= number_format($s['total_maint'],0) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ADD MODAL -->
<?php if ($canWrite): ?>
<div class="modal-overlay" id="maintModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Log Maintenance Service</span>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="_action" value="add">
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
                        <label>Service Type *</label>
                        <input type="text" name="service_type" class="form-control" placeholder="Oil Change, Brake Repair..." required>
                    </div>
                    <div class="form-group">
                        <label>Cost (₹)</label>
                        <input type="number" name="cost" class="form-control" value="0" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Service Date</label>
                        <input type="date" name="service_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Technician</label>
                        <input type="text" name="technician" class="form-control" pattern="^[^0-9]*$" title="Technician name cannot contain numeric values.">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Scheduled">Scheduled</option>
                            <option value="In Progress">In Progress (puts vehicle In Shop)</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('maintModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Log</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($action === 'new'): ?>
<script>document.addEventListener('DOMContentLoaded', () => openModal('maintModal'));</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
