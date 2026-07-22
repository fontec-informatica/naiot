<?php
require_once __DIR__ . '/auth.php';

// Redireciona se já logado
if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . home_por_perfil($_SESSION['usuario_perfil'] ?? ''));
    exit;
}

// Exige que haja uma autenticação de senha pendente
if (empty($_SESSION['mfa_pendente'])) {
    header('Location: /portal/login.php');
    exit;
}

$pendente = $_SESSION['mfa_pendente'];
$erro     = '';
$csrf     = csrf_token();

// Inicializa contador de tentativas na sessão
if (!isset($_SESSION['mfa_tentativas'])) $_SESSION['mfa_tentativas'] = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $erro = 'Token inválido. Recarregue a página.';
    } elseif ($_SESSION['mfa_tentativas'] >= MFA_TENTATIVAS_MAX) {
        session_unset();
        session_destroy();
        header('Location: /portal/login.php?mfa_bloqueado=1');
        exit;
    } else {
        $codigo = preg_replace('/\D/', '', $_POST['codigo'] ?? '');

        if (strlen($codigo) === 6 && mfa_verificar_codigo($pendente['id'], $codigo)) {
            // Código correto — finaliza o login
            session_regenerate_id(true);
            $_SESSION['usuario_id']     = $pendente['id'];
            $_SESSION['usuario_nome']   = $pendente['nome'];
            $_SESSION['usuario_perfil'] = $pendente['perfil'];
            $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
            $_SESSION['_ultimo_ativo']  = time();
            unset($_SESSION['mfa_pendente'], $_SESSION['mfa_tentativas']);

            mfa_registrar_dispositivo($pendente['id']);

            try {
                db()->prepare('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?')->execute([$pendente['id']]);
            } catch (Exception $e) {}

            header('Location: ' . home_por_perfil($pendente['perfil']));
            exit;
        } else {
            $_SESSION['mfa_tentativas']++;
            $restantes = MFA_TENTATIVAS_MAX - $_SESSION['mfa_tentativas'];
            if ($restantes <= 0) {
                session_unset();
                session_destroy();
                header('Location: /portal/login.php?mfa_bloqueado=1');
                exit;
            }
            $erro = 'Código incorreto ou expirado. ' . $restantes . ' tentativa' . ($restantes === 1 ? '' : 's') . ' restante' . ($restantes === 1 ? '' : 's') . '.';
        }
    }
}

// Reenvio de código
if (isset($_GET['reenviar'])) {
    mfa_enviar_codigo($pendente['id'], $pendente['email'], $pendente['nome']);
    header('Location: /portal/mfa.php?enviado=1');
    exit;
}

$email_mascarado = preg_replace_callback('/^(.{1,3})(.*)(@.*)$/', function ($m) {
    return $m[1] . str_repeat('*', max(1, strlen($m[2]))) . $m[3];
}, $pendente['email']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verificação — Portal NAIOT</title>
<link rel="icon" href="/assets/img/logo.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/portal/assets/css/portal.css?v=<?= filemtime(__DIR__ . '/assets/css/portal.css') ?>">
<style>
.mfa-codigo-wrap { display:flex; gap:8px; justify-content:center; margin:20px 0; }
.mfa-codigo-wrap input[type=text] {
  width:44px; height:54px; text-align:center; font-size:22px; font-weight:700;
  border:2px solid var(--border); border-radius:8px; outline:none;
  transition:border-color .2s;
}
.mfa-codigo-wrap input[type=text]:focus { border-color:var(--green); }
.mfa-info { font-size:.82rem; color:var(--muted); text-align:center; margin-bottom:4px; }
.mfa-reenviar { font-size:.82rem; text-align:center; margin-top:14px; }
.mfa-reenviar a { color:var(--green); text-decoration:underline; cursor:pointer; }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">
      <img src="/assets/img/logo.png" alt="NAIOT"
           onerror="this.style.display='none';document.querySelector('.login-logo-txt').style.display='block'">
      <span class="login-logo-txt">NAIOT</span>
      <h2>Verificação de acesso</h2>
    </div>

    <?php if (isset($_GET['enviado'])): ?>
      <div class="alerta alerta-ok">Novo código enviado para o seu e-mail.</div>
    <?php endif; ?>

    <?php if ($erro): ?>
      <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <p class="mfa-info">
      Enviamos um código de 6 dígitos para<br>
      <strong><?= htmlspecialchars($email_mascarado) ?></strong>
    </p>
    <p class="mfa-info">Válido por <?= MFA_MINUTOS ?> minutos.</p>

    <form method="post" id="form-mfa" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="codigo" id="codigo-hidden">

      <div class="mfa-codigo-wrap" aria-label="Digite o código de 6 dígitos">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
               autocomplete="one-time-code" class="dig" data-idx="<?= $i ?>">
        <?php endfor; ?>
      </div>

      <button type="submit" class="btn btn-primary" id="btn-verificar"
              style="width:100%;justify-content:center;margin-top:4px">
        Verificar
      </button>
    </form>

    <p class="mfa-reenviar">
      Não recebeu? <a href="/portal/mfa.php?reenviar=1">Reenviar código</a>
    </p>
    <p class="mfa-reenviar" style="margin-top:6px">
      <a href="/portal/login.php">← Voltar ao login</a>
    </p>
  </div>
</div>
<script>
(function () {
  var digs = Array.from(document.querySelectorAll('.dig'));
  var hidden = document.getElementById('codigo-hidden');

  function sync() {
    hidden.value = digs.map(function(d){ return d.value; }).join('');
  }

  digs.forEach(function (el, i) {
    el.addEventListener('input', function () {
      // aceita apenas dígitos
      el.value = el.value.replace(/\D/g, '').slice(-1);
      sync();
      if (el.value && i < digs.length - 1) digs[i + 1].focus();
      // auto-submit quando todos preenchidos
      if (digs.every(function(d){ return d.value.length === 1; })) {
        document.getElementById('form-mfa').submit();
      }
    });

    el.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !el.value && i > 0) {
        digs[i - 1].value = '';
        digs[i - 1].focus();
        sync();
      }
      if (e.key === 'ArrowLeft'  && i > 0) digs[i - 1].focus();
      if (e.key === 'ArrowRight' && i < digs.length - 1) digs[i + 1].focus();
    });

    el.addEventListener('paste', function (e) {
      e.preventDefault();
      var txt = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      txt.split('').forEach(function (c, j) {
        if (digs[j]) digs[j].value = c;
      });
      sync();
      var next = Math.min(txt.length, digs.length - 1);
      digs[next].focus();
      if (txt.length >= 6) document.getElementById('form-mfa').submit();
    });
  });

  // Foca no primeiro campo automaticamente
  if (digs[0]) digs[0].focus();
})();
</script>
</body>
</html>
