<?php
// includes/header.php
$user = currentUser();
$role = $user['role'] ?? '';

$roleBadge = [
    'admin'             => ['Administrator', '#C0392B'],
    'fleet_manager'     => ['Fleet Manager',  '#714B67'],
    'dispatcher'        => ['Dispatcher',     '#2E75B6'],
    'safety_officer'    => ['Safety Officer', '#E67E22'],
    'financial_analyst' => ['Fin. Analyst',   '#27AE60'],
];
$rb = $roleBadge[$role] ?? ['User', '#888'];

// ── Active path helper ───────────────────────────────────────────────────────
function isActive(string $href): string {
    $current = $_SERVER['PHP_SELF'];
    // Normalize paths for comparison
    $target = parse_url($href, PHP_URL_PATH);
    if ($current === $target) return 'active';
    
    // Fallback for subpaths or index
    $baseTarget = basename($target, '.php');
    $baseCurrent = basename($current, '.php');
    if ($baseTarget === $baseCurrent && $baseTarget !== 'index') return 'active';
    
    // Special case for dashboard root
    if ($baseTarget === 'dashboard' && ($current === '/fleetflownew/index.php' || $current === '/fleetflownew/')) return 'active';
    
    return '';
}

// ── Nav definition (SVG icons inline) ───────────────────────────────────────
// Each item: href, label, icon (SVG path data), roles (empty = seen by all)
$NAV_SECTIONS = [];

// ── CORE (always visible) ────────────────────────────────────────────────────
$coreItems = [];
$dashHref = hasRole('admin') ? '/fleetflownew/admin/users.php' : '/fleetflownew/dashboard.php';
$coreItems[] = [
    'href'  => $dashHref,
    'label' => 'Dashboard',
    'icon'  => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
];

// ── OPERATIONS ───────────────────────────────────────────────────────────────
$opsItems = [];
if (hasRole('admin', 'fleet_manager', 'dispatcher')) {
    $opsItems[] = [
        'href'  => '/fleetflownew/modules/vehicles.php',
        'label' => 'Vehicles',
        'icon'  => '<rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    ];
}
if (hasRole('admin', 'fleet_manager', 'dispatcher', 'safety_officer')) {
    $opsItems[] = [
        'href'  => '/fleetflownew/modules/drivers.php',
        'label' => 'Drivers',
        'icon'  => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/>',
    ];
}
if (hasRole('admin', 'fleet_manager', 'dispatcher')) {
    $opsItems[] = [
        'href'  => '/fleetflownew/modules/trips.php',
        'label' => 'Trips',
        'icon'  => '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
    ];
}
if (hasRole('admin', 'fleet_manager')) {
    $opsItems[] = [
        'href'  => '/fleetflownew/modules/maintenance.php',
        'label' => 'Maintenance',
        'icon'  => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    ];
}
if (hasRole('admin', 'safety_officer')) {
    $opsItems[] = [
        'href'  => '/fleetflownew/modules/compliance.php',
        'label' => 'Compliance',
        'icon'  => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    ];
}

// ── FINANCIAL ────────────────────────────────────────────────────────────────
$finItems = [];
if (hasRole('admin', 'fleet_manager', 'financial_analyst')) {
    $finItems[] = [
        'href'  => '/fleetflownew/modules/fuel.php',
        'label' => 'Fuel & Costs',
        'icon'  => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
    ];
    $finItems[] = [
        'href'  => '/fleetflownew/modules/analytics.php',
        'label' => 'Analytics',
        'icon'  => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/>',
    ];
}

// ── ADMIN ────────────────────────────────────────────────────────────────────
$adminItems = [];
if (hasRole('admin')) {
    $adminItems[] = [
        'href'  => '/fleetflownew/admin/users.php',
        'label' => 'User Management',
        'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    ];
}

// Build sections (only if they have items)
if (!empty($coreItems))  $NAV_SECTIONS[] = ['label' => null,          'items' => $coreItems];
if (!empty($opsItems))   $NAV_SECTIONS[] = ['label' => 'OPERATIONS',  'items' => $opsItems];
if (!empty($finItems))   $NAV_SECTIONS[] = ['label' => 'FINANCIALS',  'items' => $finItems];
if (!empty($adminItems)) $NAV_SECTIONS[] = ['label' => 'ADMIN',       'items' => $adminItems];

// ── User initials for avatar ─────────────────────────────────────────────────
$nameParts = explode(' ', trim($user['name'] ?? 'U'));
$initials   = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'FleetFlow') ?> — FleetFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/fleetflownew/assets/css/app.css?v=1.1">
</head>
<body>

<!-- ░░ MOBILE OVERLAY ░░ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ░░ SIDEBAR ░░ -->
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-logo">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13" rx="2"/>
                <path d="M16 8h4l3 5v4h-7V8z"/>
                <circle cx="5.5" cy="18.5" r="2.5"/>
                <circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
        </div>
        <span class="brand-name">FleetFlow</span>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav" role="navigation" aria-label="Main navigation">
        <?php foreach ($NAV_SECTIONS as $section): ?>

            <?php if ($section['label']): ?>
            <div class="nav-section-label"><?= $section['label'] ?></div>
            <?php endif; ?>

            <?php foreach ($section['items'] as $item):
                $active = isActive($item['href']);
            ?>
            <a href="<?= $item['href'] ?>"
               class="nav-link <?= $active ?>"
               title="<?= e($item['label']) ?>"
               aria-current="<?= $active ? 'page' : 'false' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <?= $item['icon'] ?>
                    </svg>
                </span>
                <span class="nav-label"><?= e($item['label']) ?></span>
                <?php if ($active): ?>
                <span class="nav-active-pip" aria-hidden="true"></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>

        <?php endforeach; ?>
    </nav>

    <!-- User profile + logout -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar" style="background:<?= $rb[1] ?>22;color:<?= $rb[1] ?>;"><?= $initials ?></div>
            <div class="user-info">
                <span class="user-name-sm"><?= e($user['name'] ?? 'User') ?></span>
                <span class="user-role-sm" style="color:<?= $rb[1] ?>;"><?= $rb[0] ?></span>
            </div>
        </div>
        <a href="/fleetflownew/profile.php" class="nav-link <?= isActive('/fleetflownew/profile.php') ?>" title="Profile" aria-label="My Profile">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
            </span>
            <span class="nav-label">My Profile</span>
        </a>

        <a href="/fleetflownew/logout.php" class="nav-link nav-logout" title="Logout" aria-label="Logout">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </span>
            <span class="nav-label">Logout</span>
        </a>
    </div>

</aside>

<!-- ░░ MAIN WRAPPER ░░ -->
<div class="main-wrapper" id="mainWrapper">
    <header class="topbar">
        <div class="topbar-left">
            <!-- Mobile hamburger -->
            <button class="menu-toggle" id="sidebarToggle" aria-label="Toggle Menu">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="6"  x2="21" y2="6"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <h1 class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
        </div>
        <div class="topbar-right">
            <span class="role-badge"
                  style="background:<?= $rb[1] ?>18;color:<?= $rb[1] ?>;border:1px solid <?= $rb[1] ?>30;">
                <?= $rb[0] ?>
            </span>
            <span class="user-name"><?= e($user['name'] ?? '') ?></span>
        </div>
    </header>
    <main class="main-content">
        <?= flash() ?>
