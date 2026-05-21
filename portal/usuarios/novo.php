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
        $nome  = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $tipo  = $_POST['tipo_acesso'] ?? 'modulos';

        if (!$nome || !$email || !$senha) {
            $erro = 'Preencha todos os campos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif (strlen($senha) < 8) {
            $erro = 'A senha deve ter no mínimo 8 caracteres.';
        } else {
            $existe = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
            $existe->execute([$email]);
            if ($existe->fetch()) {
                $erro = 'Já existe um usuário com esse e-mail.';
            } else {
                if ($tipo === 'admin') {
                    $novo_perfil = 'admin';
                } else {
                    $modulos_sel = array_intersect(
                        $_POST['modulos'] ?? [],
                        array_keys(MODULOS_PORTAL)
                    );
                    $novo_perfil = json_encode(array_values($modulos_sel));
                }
                db()->prepare('INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?,?,?,?)')
                    ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $novo_perfil]);
                header('Location: /portal/usuarios/?criado=1');
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
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" required>
      <span class="form-hint">Mínimo 8 caracteres.</span>
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
  var radAdmin = document.getElementById('radio-admin');
  var radMods  = document.getElementById('radio-modulos');
  var optAdmin = document.getElementById('opt-admin');
  var optMods  = document.getElementById('opt-modulos');
  var area     = document.getElementById('perm-modulos-area');
  var checks   = area ? area.querySelectorAll('.perm-check') : [];

  function atualizar() {
    var isAdmin = radAdmin && radAdmin.checked;
    if (optAdmin) optAdmin.classList.toggle('sel', isAdmin);
    if (optMods)  optMods.classList.toggle('sel', !isAdmin);
    checks.forEach(function(c) { c.classList.toggle('disabled', isAdmin); });
    if (area) area.style.opacity = isAdmin ? '.5' : '1';
  }

  if (radAdmin) radAdmin.addEventListener('change', atualizar);
  if (radMods)  radMods.addEventListener('change', atualizar);

  checks.forEach(function(label) {
    var cb = label.querySelector('input[type=checkbox]');
    if (cb) cb.addEventListener('change', function() {
      label.classList.toggle('on', cb.checked);
    });
  });

  atualizar();
})();
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
