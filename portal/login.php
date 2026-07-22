<?php
require_once __DIR__ . '/auth.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . home_por_perfil($_SESSION['usuario_perfil'] ?? ''));
    exit;
}

// CSP estendida para permitir reCAPTCHA v3 apenas na página de login
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob: https://www.gstatic.com; frame-src https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/; connect-src 'self' https://www.google.com; base-uri 'self'; form-action 'self'");
}

$erro      = '';
$bloqueado = false;
$ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$limite    = 10;
$janela    = 15;

$login_csrf = csrf_token();

// Rate limiting
try {
    db()->prepare("DELETE FROM login_tentativas WHERE em < DATE_SUB(NOW(), INTERVAL {$janela} MINUTE)")->execute();
    $st = db()->prepare("SELECT COUNT(*) FROM login_tentativas WHERE ip = ? AND em > DATE_SUB(NOW(), INTERVAL {$janela} MINUTE)");
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= $limite) {
        $bloqueado = true;
        $erro = "Muitas tentativas. Aguarde {$janela} minutos e tente novamente.";
    }
} catch (Exception $e) {}

// Verificação do reCAPTCHA v3
function verificar_recaptcha(string $token): bool {
    if (!$token || !RECAPTCHA_SECRET_KEY) return true; // se não configurado, deixa passar
    $resp = @file_get_contents('https://www.google.com/recaptcha/api/siteverify?' . http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]));
    if (!$resp) return true; // se API indisponível, não bloquear
    $dados = json_decode($resp, true);
    return ($dados['success'] ?? false) && ($dados['score'] ?? 0) >= 0.5 && ($dados['action'] ?? '') === 'login';
}

if (!$bloqueado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $erro = 'Token inválido. Recarregue a página.';
    } elseif (!verificar_recaptcha($_POST['g-recaptcha-response'] ?? '')) {
        $erro = 'Verificação de segurança falhou. Tente novamente.';
        try { db()->prepare("INSERT INTO login_tentativas (ip) VALUES (?)")->execute([$ip]); } catch (Exception $e) {}
    } else {
        $login = trim($_POST['login'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if ($login && $senha && strlen($senha) <= 1000) {
            try {
                $stmt = db()->prepare('SELECT id, nome, email, senha_hash, perfil, ativo FROM usuarios WHERE (email = ? OR usuario = ?) AND ativo = 1 LIMIT 1');
                $stmt->execute([$login, $login]);
            } catch (Exception $e) {
                $stmt = db()->prepare('SELECT id, nome, email, senha_hash, perfil, ativo FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1');
                $stmt->execute([$login]);
            }
            $usuario = $stmt->fetch();

            if ($usuario && $usuario['ativo'] && password_verify($senha, $usuario['senha_hash'])) {
                session_regenerate_id(true);

                if (mfa_dispositivo_confiavel((int)$usuario['id'])) {
                    $_SESSION['usuario_id']     = $usuario['id'];
                    $_SESSION['usuario_nome']   = $usuario['nome'];
                    $_SESSION['usuario_perfil'] = $usuario['perfil'];
                    $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
                    $_SESSION['_ultimo_ativo']  = time();
                    db()->prepare('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?')->execute([$usuario['id']]);
                    header('Location: ' . home_por_perfil($usuario['perfil']));
                    exit;
                }

                // Dispositivo não reconhecido — exige verificação por e-mail
                $_SESSION['mfa_pendente'] = [
                    'id'     => (int)$usuario['id'],
                    'nome'   => $usuario['nome'],
                    'email'  => $usuario['email'],
                    'perfil' => $usuario['perfil'],
                ];
                mfa_enviar_codigo((int)$usuario['id'], $usuario['email'], $usuario['nome']);
                header('Location: /portal/mfa.php');
                exit;
            }
        }

        try { db()->prepare("INSERT INTO login_tentativas (ip) VALUES (?)")->execute([$ip]); } catch (Exception $e) {}
        $erro = 'Usuário/e-mail ou senha incorretos.';
    }
}

$expirado   = isset($_GET['expirado']);
$site_key   = RECAPTCHA_SITE_KEY;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Portal NAIOT</title>
<link rel="icon" href="/assets/img/logo.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/portal/assets/css/portal.css?v=<?= filemtime(__DIR__ . '/assets/css/portal.css') ?>">
<?php if ($site_key): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($site_key) ?>"></script>
<?php endif; ?>
<style>
.grecaptcha-badge { visibility: hidden; }
.recaptcha-aviso { font-size: .72rem; color: var(--muted); text-align: center; margin-top: 14px; }
.recaptcha-aviso a { color: var(--green); }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">
      <img src="/assets/img/logo.png" alt="NAIOT"
           onerror="this.style.display='none';document.querySelector('.login-logo-txt').style.display='block'">
      <span class="login-logo-txt">NAIOT</span>
      <h2>Portal Administrativo</h2>
      <p>NAIOT — Comunidade Católica Senhor Jesus</p>
    </div>

    <?php if ($expirado && !$erro): ?>
      <div class="alerta alerta-aviso">Sua sessão expirou por inatividade. Faça login novamente.</div>
    <?php endif; ?>

    <?php if (isset($_GET['mfa_bloqueado']) && !$erro): ?>
      <div class="alerta alerta-erro">Muitas tentativas de verificação. Faça login novamente.</div>
    <?php endif; ?>

    <?php if ($erro): ?>
      <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if (!$bloqueado): ?>
    <form method="post" id="form-login" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($login_csrf) ?>">
      <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
      <div class="form-group">
        <label for="login">E-mail ou nome de usuário</label>
        <input type="text" id="login" name="login"
               value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
               autocomplete="username" required>
      </div>
      <div class="form-group">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary" id="btn-entrar" style="width:100%;justify-content:center;margin-top:8px">
        Entrar
      </button>
      <p style="text-align:center;margin-top:14px">
        <a href="/portal/esqueci-senha.php" style="font-size:.81rem;color:var(--muted)">Esqueci minha senha</a>
      </p>
    </form>
    <?php if ($site_key): ?>
    <p class="recaptcha-aviso">
      Protegido por reCAPTCHA —
      <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacidade</a> e
      <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Termos</a>
    </p>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php if ($site_key): ?>
<script>
(function() {
  var form = document.getElementById('form-login');
  if (!form) return;
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('btn-entrar');
    btn.disabled = true;
    btn.textContent = 'Verificando...';
    grecaptcha.ready(function() {
      grecaptcha.execute('<?= htmlspecialchars($site_key) ?>', {action: 'login'}).then(function(token) {
        document.getElementById('g-recaptcha-response').value = token;
        form.submit();
      }).catch(function() {
        btn.disabled = false;
        btn.textContent = 'Entrar';
        form.submit(); // envia mesmo sem token se a API falhar
      });
    });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
