<?php
require_once __DIR__ . '/auth.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . home_por_perfil($_SESSION['usuario_perfil'] ?? ''));
    exit;
}

$token    = $_GET['token'] ?? $_POST['token'] ?? '';
$erro     = '';
$sucesso  = false;
$usuario  = null;
$csrf     = csrf_token();

// Valida o token
if ($token) {
    try {
        $hash = hash('sha256', $token);
        $st   = db()->prepare("
            SELECT u.id, u.nome, u.email
            FROM senha_resets r
            JOIN usuarios u ON u.id = r.usuario_id
            WHERE r.token_hash = ? AND r.usado = 0 AND r.expira_em > NOW() AND u.ativo = 1
            LIMIT 1
        ");
        $st->execute([$hash]);
        $usuario = $st->fetch();
    } catch (Exception $e) {}
}

if (!$token || !$usuario) {
    $erro = 'Link inválido ou expirado. Solicite um novo link de redefinição.';
}

if (!$erro && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $senha  = $_POST['senha']  ?? '';
        $senha2 = $_POST['senha2'] ?? '';

        if (!senha_valida($senha)) {
            $erro = senha_erro($senha);
        } elseif ($senha !== $senha2) {
            $erro = 'As senhas não coincidem.';
        } else {
            try {
                $hash_token = hash('sha256', $token);
                db()->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")
                    ->execute([password_hash($senha, PASSWORD_DEFAULT), $usuario['id']]);
                db()->prepare("UPDATE senha_resets SET usado = 1 WHERE token_hash = ?")
                    ->execute([$hash_token]);
                // Invalida dispositivos MFA existentes para forçar re-verificação
                db()->prepare("DELETE FROM mfa_dispositivos WHERE usuario_id = ?")
                    ->execute([$usuario['id']]);
                $sucesso = true;
            } catch (Exception $e) {
                $erro = 'Erro ao salvar a senha. Tente novamente.';
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
<title>Definir senha — Portal NAIOT</title>
<link rel="icon" href="/assets/img/logo.png" type="image/png">
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
      <h2><?= $usuario && !$sucesso ? 'Olá, ' . htmlspecialchars(explode(' ', $usuario['nome'])[0]) . '!' : 'Definir senha' ?></h2>
    </div>

    <?php if ($sucesso): ?>
      <div class="alerta alerta-ok">Senha definida com sucesso! Faça login com a nova senha.</div>
      <a href="/portal/login.php" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px">
        Ir para o login
      </a>

    <?php elseif (!$usuario): ?>
      <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
      <p style="text-align:center;margin-top:8px">
        <a href="/portal/esqueci-senha.php" style="color:var(--green);font-size:.84rem">Solicitar novo link</a>
      </p>

    <?php else: ?>
      <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>

      <p style="font-size:.84rem;color:var(--muted);margin-bottom:20px">
        Crie uma senha segura para a conta <strong><?= htmlspecialchars($usuario['email']) ?></strong>.
      </p>

      <form method="post" id="form-senha" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group">
          <label for="senha">Nova senha</label>
          <input type="password" id="senha" name="senha" autocomplete="new-password" required>
          <div class="senha-forca" id="sf-wrap" style="display:none">
            <div class="forca-barra"><div class="forca-fill" id="sf-fill"></div></div>
            <ul class="forca-lista" id="sf-lista">
              <li data-req="length">Mínimo 8 caracteres</li>
              <li data-req="upper">Letra maiúscula (A–Z)</li>
              <li data-req="lower">Letra minúscula (a–z)</li>
              <li data-req="number">Número (0–9)</li>
              <li data-req="special">Caractere especial (!@#...)</li>
            </ul>
            <div class="forca-label" id="sf-label"></div>
          </div>
        </div>

        <div class="form-group">
          <label for="senha2">Confirmar senha</label>
          <input type="password" id="senha2" name="senha2" autocomplete="new-password" required>
          <span class="form-hint" id="senha2-hint" style="display:none;color:var(--vermelho)">As senhas não coincidem.</span>
        </div>

        <button type="submit" class="btn btn-primary" id="btn-salvar"
                style="width:100%;justify-content:center;margin-top:4px">
          Salvar senha
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>
<script>
(function () {
  var campo  = document.getElementById('senha');
  var campo2 = document.getElementById('senha2');
  var wrap   = document.getElementById('sf-wrap');
  var fill   = document.getElementById('sf-fill');
  var lista  = document.getElementById('sf-lista');
  var label  = document.getElementById('sf-label');
  var hint2  = document.getElementById('senha2-hint');
  var btn    = document.getElementById('btn-salvar');
  if (!campo) return;

  var niveis = ['Fraca', 'Média', 'Forte'];
  var classes = ['fraca', 'media', 'forte'];
  var larguras = ['33%', '66%', '100%'];

  function checar(s) {
    return {
      length:  s.length >= 8,
      upper:   /[A-Z]/.test(s),
      lower:   /[a-z]/.test(s),
      number:  /[0-9]/.test(s),
      special: /[^a-zA-Z0-9]/.test(s),
    };
  }

  campo.addEventListener('input', function () {
    var s = campo.value;
    if (!s) { wrap.style.display = 'none'; return; }
    wrap.style.display = '';

    var r = checar(s);
    var pts = Object.values(r).filter(Boolean).length;

    lista.querySelectorAll('li').forEach(function (li) {
      li.classList.toggle('ok', r[li.dataset.req]);
    });

    var nivel = pts <= 2 ? 0 : pts <= 4 ? 1 : 2;
    fill.style.width = larguras[nivel];
    fill.className = 'forca-fill ' + classes[nivel];
    label.textContent = niveis[nivel];
    label.className = 'forca-label ' + classes[nivel];

    verificarConcordar();
  });

  function verificarConcordar() {
    if (!campo2.value) { hint2.style.display = 'none'; return; }
    var ok = campo.value === campo2.value;
    hint2.style.display = ok ? 'none' : '';
  }

  if (campo2) campo2.addEventListener('input', verificarConcordar);

  document.getElementById('form-senha').addEventListener('submit', function (e) {
    var r = checar(campo.value);
    var valida = Object.values(r).every(Boolean);
    if (!valida) {
      e.preventDefault();
      wrap.style.display = '';
      campo.focus();
      return;
    }
    if (campo.value !== campo2.value) {
      e.preventDefault();
      hint2.style.display = '';
      campo2.focus();
    }
  });
})();
</script>
</body>
</html>
