<?php
require_once __DIR__ . '/config.php';

define('SESSION_TIMEOUT', 1800); // 30 minutos de inatividade

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
          || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Headers de segurança HTTP (aplicados a todas as páginas do portal)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; base-uri 'self'; form-action 'self'");
}

/* ── Módulos do portal ───────────────────────────────────────────────────── */
const MODULOS_PORTAL = [
    'dashboard'  => 'Dashboard',
    'eventos'    => 'Próx. Eventos',
    'inscricoes' => 'Inscrições',
    'financeiro' => 'Financeiro',
    'membros'    => 'Membros',
    'van'        => 'Viagens de Van',
    'oracoes'    => 'Orações & Testemunhos',
    'usuarios'   => 'Usuários',
];

const PERFIL_LEGADO = [
    'secretaria' => ['dashboard', 'eventos', 'inscricoes', 'membros', 'van'],
    'financeiro' => ['dashboard', 'financeiro'],
];

/* ── Helpers de sessão ───────────────────────────────────────────────────── */
function usuario_logado(): bool {
    return isset($_SESSION['usuario_id']);
}

function _modulos_do_usuario(): array {
    $p = $_SESSION['usuario_perfil'] ?? '';
    if ($p === 'admin') return array_keys(MODULOS_PORTAL);
    if (isset(PERFIL_LEGADO[$p])) return PERFIL_LEGADO[$p];
    return json_decode($p, true) ?: [];
}

function tem_modulo(string $modulo): bool {
    if (!usuario_logado()) return false;
    if (($_SESSION['usuario_perfil'] ?? '') === 'admin') return true;
    return in_array($modulo, _modulos_do_usuario(), true);
}

/* ── Guards ──────────────────────────────────────────────────────────────── */
function requer_login(): void {
    if (!usuario_logado()) {
        header('Location: /portal/login.php');
        exit;
    }
    // Verificar timeout de inatividade
    $ultimo = $_SESSION['_ultimo_ativo'] ?? time();
    if (time() - $ultimo > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: /portal/login.php?expirado=1');
        exit;
    }
    $_SESSION['_ultimo_ativo'] = time();
}

function requer_perfil(array $perfis): void {
    requer_login();
    $p = $_SESSION['usuario_perfil'] ?? '';
    if ($p === 'admin') return;
    if (in_array($p, $perfis, true)) return;
    $modulos = _modulos_do_usuario();
    foreach ($perfis as $req) {
        if (in_array($req, $modulos, true)) return;
        foreach (PERFIL_LEGADO[$req] ?? [] as $m) {
            if (in_array($m, $modulos, true)) return;
        }
    }
    http_response_code(403);
    include __DIR__ . '/403.php';
    exit;
}

function requer_admin(): void {
    requer_login();
    if (($_SESSION['usuario_perfil'] ?? '') !== 'admin') {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
}

/* ── Utilitários ─────────────────────────────────────────────────────────── */
function home_por_perfil(string $perfil): string {
    if ($perfil === 'admin')      return '/portal/';
    if ($perfil === 'secretaria') return '/portal/inscricoes/';
    if ($perfil === 'financeiro') return '/portal/financeiro/';
    $mods = json_decode($perfil, true) ?: [];
    $prioridade = ['financeiro', 'inscricoes', 'eventos', 'membros'];
    $rotas = [
        'financeiro' => '/portal/financeiro/',
        'inscricoes' => '/portal/inscricoes/',
        'eventos'    => '/portal/eventos/',
        'membros'    => '/portal/membros/',
    ];
    foreach ($prioridade as $m) {
        if (in_array($m, $mods, true)) return $rotas[$m];
    }
    return '/portal/';
}

function label_perfil(string $perfil): string {
    if ($perfil === 'admin')      return 'Administrador';
    if ($perfil === 'secretaria') return 'Secretaria';
    if ($perfil === 'financeiro') return 'Financeiro';
    $mods = json_decode($perfil, true) ?: [];
    if (empty($mods)) return 'Sem acesso';
    $labels = array_map(fn($m) => MODULOS_PORTAL[$m] ?? $m, $mods);
    return count($labels) <= 2
        ? implode(' + ', $labels)
        : count($labels) . ' módulos';
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valido(): bool {
    return isset($_POST['csrf_token'])
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
