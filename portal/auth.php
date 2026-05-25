<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ── Módulos do portal ─────────────────────────────────────────────────────
   Adicione novos módulos AQUI. Eles aparecerão automaticamente no formulário
   de permissões de usuários.
   Chave = identificador único | valor = rótulo exibido na interface
──────────────────────────────────────────────────────────────────────────── */
const MODULOS_PORTAL = [
    'dashboard'  => 'Dashboard',
    'eventos'    => 'Próx. Eventos',
    'inscricoes' => 'Inscrições',
    'financeiro' => 'Financeiro',
    'membros'    => 'Membros',
    'oracoes'    => 'Orações & Testemunhos',
    'usuarios'   => 'Usuários',
];

/* Mapeamento backward-compat: perfis antigos → módulos equivalentes */
const PERFIL_LEGADO = [
    'secretaria' => ['dashboard', 'eventos', 'inscricoes', 'membros'],
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
}

function requer_perfil(array $perfis): void {
    requer_login();
    $p = $_SESSION['usuario_perfil'] ?? '';
    if ($p === 'admin') return;
    if (in_array($p, $perfis, true)) return;
    // Verifica se algum perfil solicitado corresponde a módulos do usuário
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
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}
