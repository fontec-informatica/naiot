<?php
require_once __DIR__ . '/auth.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . home_por_perfil($_SESSION['usuario_perfil'] ?? ''));
    exit;
}

$erro        = '';
$bloqueado   = false;
$ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$limite      = 10;  // tentativas máximas
$janela_min  = 15;  // janela de bloqueio em minutos

// Inicializar CSRF para o formulário de login
$login_csrf = csrf_token();

// Verificar bloqueio por rate limit
try {
    db()->prepare("DELETE FROM login_tentativas WHERE em < DATE_SUB(NOW(), INTERVAL {$janela_min} MINUTE)")->execute();
    $st = db()->prepare("SELECT COUNT(*) FROM login_tentativas WHERE ip = ? AND em > DATE_SUB(NOW(), INTERVAL {$janela_min} MINUTE)");
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= $limite) {
        $bloqueado = true;
        $erro = "Muitas tentativas. Aguarde {$janela_min} minutos e tente novamente.";
    }
} catch (Exception $e) {
    // Tabela pode não existir ainda — não bloquear
}

if (!$bloqueado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF do formulário de login
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $login = trim($_POST['login'] ?? '');
        $senha = $_POST['senha'] ?? '';

        // Limite de comprimento para evitar DoS via bcrypt
        if ($login && $senha && strlen($senha) <= 1000) {
            try {
                $stmt = db()->prepare('SELECT id, nome, senha_hash, perfil, ativo FROM usuarios WHERE (email = ? OR usuario = ?) AND ativo = 1 LIMIT 1');
                $stmt->execute([$login, $login]);
            } catch (Exception $e) {
                $stmt = db()->prepare('SELECT id, nome, senha_hash, perfil, ativo FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1');
                $stmt->execute([$login]);
            }
            $usuario = $stmt->fetch();

            if ($usuario && $usuario['ativo'] && password_verify($senha, $usuario['senha_hash'])) {
                // Login bem-sucedido
                session_regenerate_id(true);
                $_SESSION['usuario_id']     = $usuario['id'];
                $_SESSION['usuario_nome']   = $usuario['nome'];
                $_SESSION['usuario_perfil'] = $usuario['perfil'];
                $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
                $_SESSION['_ultimo_ativo']  = time();

                db()->prepare('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?')
                    ->execute([$usuario['id']]);

                header('Location: ' . home_por_perfil($usuario['perfil']));
                exit;
            }
        }

        // Registrar tentativa falha
        try {
            db()->prepare("INSERT INTO login_tentativas (ip) VALUES (?)")->execute([$ip]);
        } catch (Exception $e) {}

        $erro = 'Usuário/e-mail ou senha incorretos.';
    }
}

// Mostrar aviso de sessão expirada
$expirado = isset($_GET['expirado']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Portal NAIOT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/portal/assets/css/portal.css?v=<?= filemtime(__DIR__ . '/assets/css/portal.css') ?>">
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

    <?php if ($erro): ?>
      <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if (!$bloqueado): ?>
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($login_csrf) ?>">
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
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">
        Entrar
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
