<?php
session_start();

const ALLOWED_ROLES = [
    'admin',
    'fleet_manager',
    'dispatcher',
    'safety_officer',
    'financial_analyst'
];

function validateRole(?string $role): bool {
    return in_array($role, ALLOWED_ROLES, true);
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /fleetflownew/login.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function hasRole(string ...$roles): bool {
    $userRole = $_SESSION['user']['role'] ?? '';
    // admin bypasses all role checks
    if ($userRole === 'admin') return true;
    return in_array($userRole, $roles, true);
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!hasRole(...$roles)) {
        header('Location: /fleetflownew/dashboard.php?error=access_denied');
        exit;
    }
}

function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url, string $msg = '', string $type = 'success'): never {
    if ($msg) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
    }
    header("Location: $url");
    exit;
}

function flash(): string {
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $cls = $f['type'] === 'error' ? 'alert-error' : ($f['type'] === 'warning' ? 'alert-warning' : 'alert-success');
    return "<div class=\"alert $cls\">" . htmlspecialchars($f['msg']) . "</div>";
}

function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Validates and normalizes Indian mobile numbers.
 * Rules: Exactly 10 digits, starts with 6-9.
 * 
 * @param string $mobile
 * @return string|bool 10-digit string if valid, false otherwise.
 */
function validateIndianMobile(string $mobile): string|bool {
    // 1. Remove all whitespace
    $mobile = trim($mobile);
    
    // 2. Ensure exactly 10 digits and starts with 6-9
    if (preg_match('/^[6-9]\d{9}$/', $mobile)) {
        return $mobile;
    }

    return false;
}

/**
 * Validates an Indian Vehicle Registration Plate (e.g. MP09 AB 1234)
 * Pattern: [State][RTO] [Series] [Number]
 * 
 * @param string $plate
 * @return string|bool Normalized plate string or false
 */
function validateIndianPlate(string $plate): string|bool {
    $plate = strtoupper(trim($plate));
    // Pattern: 2 Letters, 2 Digits, Space, 1-2 Letters, Space, 4 Digits
    $pattern = '/^[A-Z]{2}[0-9]{2}\s[A-Z]{1,2}\s[0-9]{4}$/';
    
    if (preg_match($pattern, $plate)) {
        return $plate;
    }
    
    return false;
}
