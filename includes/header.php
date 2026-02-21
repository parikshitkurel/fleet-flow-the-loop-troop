<?php
// includes/header.php
// Usage: include with $pageTitle set
$user = currentUser();
$roleBadge = [
    'fleet_manager'    => ['Fleet Manager', '#714B67'],
    'dispatcher'       => ['Dispatcher',    '#2E75B6'],
    'safety_officer'   => ['Safety Officer','#E67E22'],
    'financial_analyst'=> ['Fin. Analyst',  '#27AE60'],
];
$rb = $roleBadge[$user['role']] ?? ['User', '#888'];

$navItems = [
    ['href' => '/fleetflow/dashboard.php',           'icon' => '◼', 'label' => 'Dashboard'],
    ['href' => '/fleetflow/modules/vehicles.php',    'icon' => '🚛', 'label' => 'Vehicles'],
    ['href' => '/fleetflow/modules/drivers.php',     'icon' => '👤', 'label' => 'Drivers'],
    ['href' => '/fleetflow/modules/trips.php',       'icon' => '🧭', 'label' => 'Trips'],
    ['href' => '/fleetflow/modules/maintenance.php', 'icon' => '🔧', 'label' => 'Maintenance'],
    ['href' => '/fleetflow/modules/fuel.php',        'icon' => '⛽', 'label' => 'Fuel & Costs'],
    ['href' => '/fleetflow/modules/analytics.php',   'icon' => '📈', 'label' => 'Analytics'],
];
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'FleetFlow') ?> — FleetFlow</title>
    <link rel="stylesheet" href="/fleetflow/assets/css/app.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">🚛</span>
        <span class="brand-name">FleetFlow</span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= $item['href'] ?>" class="nav-link <?= str_contains($currentPath, basename($item['href'], '.php')) ? 'active' : '' ?>">
            <span class="nav-icon"><?= $item['icon'] ?></span>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="/fleetflow/logout.php" class="nav-link nav-logout">
            <span class="nav-icon">⬡</span>
            <span>Logout</span>
        </a>
    </div>
</aside>

<div class="main-wrapper">
    <header class="topbar">
        <div class="topbar-left">
            <h1 class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
        </div>
        <div class="topbar-right">
            <span class="role-badge" style="background:<?= $rb[1] ?>20;color:<?= $rb[1] ?>;border:1px solid <?= $rb[1] ?>40">
                <?= $rb[0] ?>
            </span>
            <span class="user-name"><?= e($user['name']) ?></span>
        </div>
    </header>
    <main class="main-content">
        <?= flash() ?>
