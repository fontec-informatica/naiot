<?php
require_once __DIR__ . '/auth.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . home_por_perfil($_SESSION['usuario_perfil'] ?? ''));
    exit;
}

$enviado = false;
$erro    = '';
$csrf    = csrf_token();
$ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        // Rate limit: máx 3 pedidos por IP em 30 minutos
        try {
            db()->prepare("DELETE FROM login_tentativas WHERE em < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->execute();
            $st = db()->prepare("SELECT COUNT(*) FROM login_tentativas WHERE ip = ? AND em > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
            $st->execute([$ip]);
            if ((int)$st->fetchColumn() >= 3) {
                $erro = 'Muitas solicitações. Aguarde 30 minutos e tente novamente.';
            }
        } catch (Exception $e) {}

        if (!$erro) {
            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erro = 'E-mail inválido.';
            } else {
                // Sempre mostra mensagem de sucesso (não vazar se e-mail existe)
                try {
                    $st = db()->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
                    $st->execute([$email]);
                    $u = $st->fetch();
                    if ($u) {
                        senha_reset_enviar((int)$u['id'], $email, $u['nome'], 1);
                    }
                    db()->prepare("INSERT INTO login_tentativas (ip) VALUES (?)")->execute([$ip]);
                } catch (Exception $e) {}

                $enviado = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Esqueci minha senha — Portal NAIOT</title>
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
      <h2>Redefinir senha</h2>
    </div>

    <?php if ($enviado): ?>
      <div class="alerta alerta-ok">
        Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha em instantes.<br>
        <span style="font-size:.8rem">Verifique também a pasta de spam.</span>
      </div>
      <p style="text-align:center;margin-top:4px">
        <a href="/portal/login.php" style="color:var(--green);font-size:.85rem">← Voltar ao login</a>
      </p>
    <?php else: ?>

      <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>

      <p style="font-size:.84rem;color:var(--muted);margin-bottom:20px">
        Informe seu e-mail cadastrado e enviaremos um link para você criar uma nova senha.
      </p>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="form-group">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 autocomplete="email" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px">
          Enviar link
        </button>
      </form>

      <p style="text-align:center;margin-top:18px">
        <a href="/portal/login.php" style="color:var(--green);font-size:.83rem">← Voltar ao login</a>
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
