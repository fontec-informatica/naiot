<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: /portal/');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email && $senha) {
        $stmt = db()->prepare('SELECT id, nome, senha_hash, perfil, ativo FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && $usuario['ativo'] && password_verify($senha, $usuario['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id']     = $usuario['id'];
            $_SESSION['usuario_nome']   = $usuario['nome'];
            $_SESSION['usuario_perfil'] = $usuario['perfil'];
            $_SESSION['csrf_token']     = bin2hex(random_bytes(32));

            db()->prepare('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?')
                ->execute([$usuario['id']]);

            header('Location: /portal/');
            exit;
        }
    }

    $erro = 'E-mail ou senha incorretos.';
}
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
<link rel="stylesheet" href="/portal/assets/css/portal.css?v=20260519b">
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

    <?php if ($erro): ?>
      <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="form-group">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               autocomplete="email" required>
      </div>
      <div class="form-group">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">
        Entrar
      </button>
    </form>
  </div>
</div>
</body>
</html>
