<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function usuario_logado(): bool {
    return isset($_SESSION['usuario_id']);
}

function requer_login(): void {
    if (!usuario_logado()) {
        header('Location: /portal/login.php');
        exit;
    }
}

function requer_perfil(array $perfis): void {
    requer_login();
    if (!in_array($_SESSION['usuario_perfil'], $perfis, true)) {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valido(): bool {
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}
