<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

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
    'van'        => 'Missões Van',
    'oracoes'    => 'Orações & Testemunhos',
    'estoque'    => 'Livraria',
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
    $prioridade = ['financeiro', 'inscricoes', 'eventos', 'membros', 'oracoes'];
    $rotas = [
        'financeiro' => '/portal/financeiro/',
        'inscricoes' => '/portal/inscricoes/',
        'eventos'    => '/portal/eventos/',
        'membros'    => '/portal/membros/',
        'oracoes'    => '/portal/oracoes/',
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

/* ── Validação de senha forte ────────────────────────────────────────────── */
function senha_valida(string $s): bool {
    return strlen($s) >= 8
        && preg_match('/[A-Z]/', $s)
        && preg_match('/[a-z]/', $s)
        && preg_match('/[0-9]/', $s)
        && preg_match('/[^a-zA-Z0-9]/', $s);
}

function senha_erro(string $s): string {
    if (strlen($s) < 8)                    return 'A senha deve ter no mínimo 8 caracteres.';
    if (!preg_match('/[A-Z]/', $s))        return 'A senha deve conter pelo menos uma letra maiúscula.';
    if (!preg_match('/[a-z]/', $s))        return 'A senha deve conter pelo menos uma letra minúscula.';
    if (!preg_match('/[0-9]/', $s))        return 'A senha deve conter pelo menos um número.';
    if (!preg_match('/[^a-zA-Z0-9]/', $s)) return 'A senha deve conter pelo menos um caractere especial (!@#$%...).';
    return '';
}

/* ── Reset / convite de senha ────────────────────────────────────────────── */
function senha_reset_enviar(int $usuario_id, string $email, string $nome, int $horas = 1): void {
    try {
        db()->prepare("UPDATE senha_resets SET usado = 1 WHERE usuario_id = ? AND usado = 0")->execute([$usuario_id]);
    } catch (Exception $e) {}

    $token  = bin2hex(random_bytes(32));
    $hash   = hash('sha256', $token);
    $expira = date('Y-m-d H:i:s', time() + $horas * 3600);

    try {
        db()->prepare("INSERT INTO senha_resets (usuario_id, token_hash, expira_em) VALUES (?, ?, ?)")
            ->execute([$usuario_id, $hash, $expira]);
    } catch (Exception $e) { return; }

    $link     = 'https://naiot.com.br/portal/redefinir-senha.php?token=' . urlencode($token);
    $nome_esc = htmlspecialchars($nome);
    $convite  = $horas > 2;
    $titulo   = $convite ? 'Acesso ao Portal NAIOT' : 'Redefinição de senha — Portal NAIOT';
    $intro    = $convite
        ? 'Você foi cadastrado no Portal NAIOT. Clique no botão abaixo para definir sua senha e acessar o sistema.'
        : 'Recebemos uma solicitação de redefinição de senha para esta conta.';
    $validade = $horas === 1 ? '1 hora' : "$horas horas";

    $msg = <<<HTML
<html><body style="font-family:Inter,Arial,sans-serif;color:#1f1f1f;max-width:520px;margin:0 auto;padding:32px">
  <div style="border-top:3px solid #1e6b35;padding-top:24px">
    <h2 style="color:#1e6b35;margin:0 0 4px">Portal NAIOT</h2>
    <p style="color:#6a6a6a;margin:0 0 24px;font-size:13px">Comunidade Católica Senhor Jesus</p>
    <p>Olá, <strong>{$nome_esc}</strong>.</p>
    <p>{$intro}</p>
    <div style="text-align:center;margin:28px 0">
      <a href="{$link}" style="display:inline-block;background:#1e6b35;color:#fff;
         padding:14px 32px;border-radius:8px;font-weight:600;font-size:15px;text-decoration:none">
        {$titulo}
      </a>
    </div>
    <p style="color:#6a6a6a;font-size:12px">Link válido por <strong>{$validade}</strong>.</p>
    <p style="color:#6a6a6a;font-size:12px;margin-top:8px">Se não foi você, ignore este e-mail.</p>
    <p style="color:#6a6a6a;font-size:11px;word-break:break-all;margin-top:12px">Ou copie: {$link}</p>
  </div>
</body></html>
HTML;

    mailer_enviar($email, $titulo, $msg);
}

/* ── MFA — 2FA por e-mail ────────────────────────────────────────────────── */
define('MFA_DIAS',    30);
define('MFA_MINUTOS', 10);
define('MFA_COOKIE',  'naiot_dt');
define('MFA_TENTATIVAS_MAX', 5);

function mfa_dispositivo_confiavel(int $usuario_id): bool {
    $token = $_COOKIE[MFA_COOKIE] ?? '';
    if (!$token || strlen($token) < 32) return false;
    try {
        $st = db()->prepare("SELECT id FROM mfa_dispositivos WHERE usuario_id = ? AND token_hash = ? AND expira_em > NOW() LIMIT 1");
        $st->execute([$usuario_id, hash('sha256', $token)]);
        return (bool)$st->fetch();
    } catch (Exception $e) { return false; }
}

function mfa_enviar_codigo(int $usuario_id, string $email, string $nome): void {
    try {
        db()->prepare("UPDATE mfa_codigos SET usado = 1 WHERE usuario_id = ? AND usado = 0")->execute([$usuario_id]);
    } catch (Exception $e) {}

    $codigo  = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash    = password_hash($codigo, PASSWORD_BCRYPT);
    $expira  = date('Y-m-d H:i:s', time() + MFA_MINUTOS * 60);

    try {
        db()->prepare("INSERT INTO mfa_codigos (usuario_id, codigo_hash, expira_em) VALUES (?, ?, ?)")
            ->execute([$usuario_id, $hash, $expira]);
    } catch (Exception $e) { return; }

    $nome_esc = htmlspecialchars($nome);
    $msg = <<<HTML
<html><body style="font-family:Inter,Arial,sans-serif;color:#1f1f1f;max-width:520px;margin:0 auto;padding:32px">
  <div style="border-top:3px solid #1e6b35;padding-top:24px">
    <h2 style="color:#1e6b35;margin:0 0 4px">Portal NAIOT</h2>
    <p style="color:#6a6a6a;margin:0 0 24px;font-size:13px">Comunidade Católica Senhor Jesus</p>
    <p>Olá, <strong>{$nome_esc}</strong>.</p>
    <p>Seu código de verificação de acesso é:</p>
    <div style="font-size:40px;font-weight:700;letter-spacing:10px;text-align:center;
                padding:28px 16px;background:#f0f7f2;border-radius:10px;
                color:#163d22;margin:20px 0">{$codigo}</div>
    <p>Válido por <strong>10 minutos</strong>. Não compartilhe este código.</p>
    <p style="color:#6a6a6a;font-size:12px;margin-top:24px">
      Se você não tentou fazer login, ignore este e-mail.
    </p>
  </div>
</body></html>
HTML;

    mailer_enviar($email, 'Código de acesso NAIOT: ' . $codigo, $msg);
}

function mfa_verificar_codigo(int $usuario_id, string $codigo): bool {
    try {
        $st = db()->prepare("SELECT id, codigo_hash FROM mfa_codigos WHERE usuario_id = ? AND usado = 0 AND expira_em > NOW() ORDER BY id DESC LIMIT 1");
        $st->execute([$usuario_id]);
        $row = $st->fetch();
        if (!$row || !password_verify($codigo, $row['codigo_hash'])) return false;
        db()->prepare("UPDATE mfa_codigos SET usado = 1 WHERE id = ?")->execute([$row['id']]);
        return true;
    } catch (Exception $e) { return false; }
}

function mfa_registrar_dispositivo(int $usuario_id): void {
    $token  = bin2hex(random_bytes(32));
    $hash   = hash('sha256', $token);
    $expira = date('Y-m-d H:i:s', time() + MFA_DIAS * 86400);
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    try {
        db()->prepare("DELETE FROM mfa_dispositivos WHERE usuario_id = ? AND expira_em < NOW()")->execute([$usuario_id]);
        db()->prepare("INSERT INTO mfa_dispositivos (usuario_id, token_hash, ip, user_agent, expira_em) VALUES (?, ?, ?, ?, ?)")
            ->execute([$usuario_id, $hash, $ip, $ua, $expira]);
    } catch (Exception $e) { return; }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    setcookie(MFA_COOKIE, $token, [
        'expires'  => time() + MFA_DIAS * 86400,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/* ────────────────────────────────────────────────────────────────────────── */

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
