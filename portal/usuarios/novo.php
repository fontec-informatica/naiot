<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'usuarios']);

$titulo       = 'Novo Usuário';
$pagina_ativa = 'usuarios';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $nome        = trim($_POST['nome'] ?? '');
        $usuario     = trim($_POST['usuario'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $senha       = $_POST['senha'] ?? '';
        $tipo        = $_POST['tipo_acesso'] ?? 'modulos';
        $modo_senha  = $_POST['modo_senha'] ?? 'convite';

        if (!$nome || !$email) {
            $erro = 'Preencha nome e e-mail.';
        } elseif ($usuario && !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $usuario)) {
            $erro = 'Nome de usuário inválido. Use apenas letras, números, ponto, hífen e sublinhado (3–50 caracteres).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif ($modo_senha === 'definir' && !senha_valida($senha)) {
            $erro = senha_erro($senha);
        } else {
            if ($usuario) {
                $dup = db()->prepare('SELECT id FROM usuarios WHERE usuario = ?');
                $dup->execute([$usuario]);
                if ($dup->fetch()) { $erro = 'Esse nome de usuário já está em uso.'; }
            }
            if (!$erro) {
                $dup2 = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
                $dup2->execute([$email]);
                if ($dup2->fetch()) { $erro = 'Já existe um usuário com esse e-mail.'; }
            }
            if (!$erro) {
                if ($tipo === 'admin') {
                    $novo_perfil = 'admin';
                } else {
                    $modulos_sel = array_intersect(
                        $_POST['modulos'] ?? [],
                        array_keys(MODULOS_PORTAL)
                    );
                    $novo_perfil = json_encode(array_values($modulos_sel));
                }

                if ($modo_senha === 'definir') {
                    $hash_novo = password_hash($senha, PASSWORD_DEFAULT);
                } else {
                    // Senha bloqueada temporariamente; usuário define via convite
                    $hash_novo = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                }

                db()->prepare('INSERT INTO usuarios (nome, usuario, email, senha_hash, perfil) VALUES (?,?,?,?,?)')
                    ->execute([$nome, $usuario ?: null, $email, $hash_novo, $novo_perfil]);

                if ($modo_senha === 'convite') {
                    $novo_id = (int)db()->lastInsertId();
                    senha_reset_enviar($novo_id, $email, $nome, 168); // 7 dias
                    header('Location: /portal/usuarios/?criado=1&convite=1');
                } else {
                    header('Location: /portal/usuarios/?criado=1');
                }
                exit;
            }
        }
    }
}

include dirname(__DIR__) . '/_layout.php';
?>

<style>
.perm-wrap{border:1.5px solid var(--border);border-radius:var(--r);overflow:hidden}
.perm-opt{display:flex;align-items:center;gap:12px;padding:14px 16px;cursor:pointer;transition:background var(--ease)}
.perm-opt:hover{background:var(--green-pale)}
.perm-opt input[type=radio]{accent-color:var(--green);width:15px;height:15px;flex-shrink:0;cursor:pointer}
.perm-opt-info strong{display:block;font-size:.85rem;font-weight:600;color:var(--txt)}
.perm-opt-info span{font-size:.74rem;color:var(--muted)}
.perm-opt.sel{background:var(--green-pale)}
.perm-divisor{border-top:1.5px solid var(--border)}
.perm-modulos-wrap{padding:14px 16px;background:var(--off)}
.perm-modulos-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:10px}
.perm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px}
.perm-check{display:flex;align-items:center;gap:9px;padding:10px 12px;
  background:#fff;border:1.5px solid var(--border);border-radius:8px;
  cursor:pointer;transition:border-color var(--ease),background var(--ease);user-select:none}
.perm-check:hover{border-color:var(--green);background:var(--green-pale)}
.perm-check input[type=checkbox]{accent-color:var(--green);width:14px;height:14px;flex-shrink:0;cursor:pointer}
.perm-check.on{border-color:var(--green);background:var(--green-pale)}
.perm-check.disabled{opacity:.45;pointer-events:none}
@media(max-width:520px){.perm-grid{grid-template-columns:1fr}}
</style>

<div class="form-wrap">
  <h2>Novo usuário</h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label for="nome">Nome completo</label>
      <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label for="usuario">Nome de usuário <span style="font-weight:400;color:var(--muted)">(opcional — para login sem e-mail)</span></label>
      <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
             autocomplete="off" placeholder="ex: joao.silva">
      <span class="form-hint">Letras, números, ponto, hífen e sublinhado. 3–50 caracteres.</span>
    </div>

    <div class="form-group">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label>Senha de acesso</label>
      <div style="display:flex;gap:0;border:1.5px solid var(--border);border-radius:var(--r);overflow:hidden;margin-bottom:10px">
        <label id="opt-convite" style="flex:1;display:flex;align-items:center;gap:8px;padding:11px 14px;cursor:pointer;background:var(--green-pale);transition:background var(--ease)">
          <input type="radio" name="modo_senha" value="convite" id="radio-convite" checked style="accent-color:var(--green)">
          <span style="font-size:.82rem"><strong>Enviar convite por e-mail</strong><br><span style="color:var(--muted);font-size:.75rem">O usuário define a própria senha (link válido por 7 dias)</span></span>
        </label>
        <label id="opt-definir" style="flex:1;display:flex;align-items:center;gap:8px;padding:11px 14px;cursor:pointer;border-left:1.5px solid var(--border);transition:background var(--ease)">
          <input type="radio" name="modo_senha" value="definir" id="radio-definir" style="accent-color:var(--green)">
          <span style="font-size:.82rem"><strong>Eu defino a senha</strong><br><span style="color:var(--muted);font-size:.75rem">Você cria e compartilha com o usuário</span></span>
        </label>
      </div>

      <div id="campo-senha-wrap" style="display:none">
        <input type="password" id="senha" name="senha" autocomplete="new-password">
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
    </div>

    <div class="form-group">
      <label>Permissões de acesso</label>
      <div class="perm-wrap">
        <!-- Opção: Admin -->
        <label class="perm-opt" id="opt-admin">
          <input type="radio" name="tipo_acesso" value="admin" id="radio-admin">
          <div class="perm-opt-info">
            <strong>Administrador</strong>
            <span>Acesso total a todos os módulos e configurações</span>
          </div>
        </label>

        <!-- Opção: Módulos personalizados -->
        <label class="perm-opt perm-divisor sel" id="opt-modulos">
          <input type="radio" name="tipo_acesso" value="modulos" id="radio-modulos" checked>
          <div class="perm-opt-info">
            <strong>Acesso personalizado</strong>
            <span>Selecione os módulos permitidos abaixo</span>
          </div>
        </label>

        <!-- Checkboxes de módulos -->
        <div class="perm-modulos-wrap perm-divisor" id="perm-modulos-area">
          <div class="perm-modulos-label">Módulos disponíveis</div>
          <div class="perm-grid">
            <?php foreach (MODULOS_PORTAL as $chave => $rotulo): ?>
            <label class="perm-check <?= in_array($chave, $_POST['modulos'] ?? []) ? 'on' : '' ?>">
              <input type="checkbox" name="modulos[]" value="<?= $chave ?>"
                <?= in_array($chave, $_POST['modulos'] ?? []) ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($rotulo) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Criar usuário</button>
      <a href="/portal/usuarios/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<script>
(function(){
  // ── Toggle permissões ──
  var radAdmin = document.getElementById('radio-admin');
  var radMods  = document.getElementById('radio-modulos');
  var optAdmin = document.getElementById('opt-admin');
  var optMods  = document.getElementById('opt-modulos');
  var area     = document.getElementById('perm-modulos-area');
  var checks   = area ? area.querySelectorAll('.perm-check') : [];

  function atualizarPerms() {
    var isAdmin = radAdmin && radAdmin.checked;
    if (optAdmin) optAdmin.classList.toggle('sel', isAdmin);
    if (optMods)  optMods.classList.toggle('sel', !isAdmin);
    checks.forEach(function(label) {
      label.classList.toggle('disabled', isAdmin);
      var cb = label.querySelector('input[type=checkbox]');
      if (cb) cb.disabled = isAdmin;
    });
    if (area) area.style.opacity = isAdmin ? '.5' : '1';
  }

  if (radAdmin) radAdmin.addEventListener('change', atualizarPerms);
  if (radMods)  radMods.addEventListener('change', atualizarPerms);

  checks.forEach(function(label) {
    var cb = label.querySelector('input[type=checkbox]');
    if (cb) cb.addEventListener('change', function() { label.classList.toggle('on', cb.checked); });
  });

  atualizarPerms();

  // ── Toggle modo senha ──
  var radConvite = document.getElementById('radio-convite');
  var radDefinir = document.getElementById('radio-definir');
  var optConvite = document.getElementById('opt-convite');
  var optDefinir = document.getElementById('opt-definir');
  var campoWrap  = document.getElementById('campo-senha-wrap');
  var campo      = document.getElementById('senha');
  var sfWrap     = document.getElementById('sf-wrap');
  var sfFill     = document.getElementById('sf-fill');
  var sfLista    = document.getElementById('sf-lista');
  var sfLabel    = document.getElementById('sf-label');

  function atualizarModo() {
    var convite = radConvite && radConvite.checked;
    if (optConvite) optConvite.style.background = convite ? 'var(--green-pale)' : '';
    if (optDefinir) optDefinir.style.background = !convite ? 'var(--green-pale)' : '';
    if (campoWrap)  campoWrap.style.display = convite ? 'none' : '';
    if (campo && convite) { campo.value = ''; if (sfWrap) sfWrap.style.display = 'none'; }
  }

  if (radConvite) radConvite.addEventListener('change', atualizarModo);
  if (radDefinir) radDefinir.addEventListener('change', atualizarModo);

  // ── Medidor de força ──
  var niveis = ['Fraca', 'Média', 'Forte'];
  var classes = ['fraca', 'media', 'forte'];
  var larguras = ['33%', '66%', '100%'];

  function checar(s) {
    return { length: s.length >= 8, upper: /[A-Z]/.test(s), lower: /[a-z]/.test(s), number: /[0-9]/.test(s), special: /[^a-zA-Z0-9]/.test(s) };
  }

  if (campo) {
    campo.addEventListener('input', function () {
      var s = campo.value;
      if (!s) { sfWrap.style.display = 'none'; return; }
      sfWrap.style.display = '';
      var r = checar(s);
      var pts = Object.values(r).filter(Boolean).length;
      sfLista.querySelectorAll('li').forEach(function(li){ li.classList.toggle('ok', r[li.dataset.req]); });
      var n = pts <= 2 ? 0 : pts <= 4 ? 1 : 2;
      sfFill.style.width = larguras[n];
      sfFill.className = 'forca-fill ' + classes[n];
      sfLabel.textContent = niveis[n];
      sfLabel.className = 'forca-label ' + classes[n];
    });
  }

  atualizarModo();
})();
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
