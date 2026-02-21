<?php
session_start();

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /fleetflow/login.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function hasRole(string ...$roles): bool {
    return in_array($_SESSION['user']['role'] ?? '', $roles, true);
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!hasRole(...$roles)) {
        header('Location: /fleetflow/dashboard.php?error=access_denied');
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
