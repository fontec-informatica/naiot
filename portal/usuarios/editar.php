<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'usuarios']);

$titulo       = 'Editar Usuário';
$pagina_ativa = 'usuarios';
$erro = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/usuarios/'); exit; }

$usuario = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
$usuario->execute([$id]);
$u = $usuario->fetch();
if (!$u) { header('Location: /portal/usuarios/'); exit; }

$eh_proprio = ($id === ($_SESSION['usuario_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } elseif (($_POST['acao'] ?? '') === 'deletar') {
        if ($eh_proprio) {
            $erro = 'Você não pode excluir seu próprio usuário.';
        } else {
            db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
            header('Location: /portal/usuarios/?deletado=1');
            exit;
        }
    } else {
        $nome    = trim($_POST['nome'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $senha   = $_POST['senha'] ?? '';
        $tipo    = $_POST['tipo_acesso'] ?? 'modulos';

        if (!$nome || !$email) {
            $erro = 'Preencha nome e e-mail.';
        } elseif ($usuario && !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $usuario)) {
            $erro = 'Nome de usuário inválido. Use apenas letras, números, ponto, hífen e sublinhado (3–50 caracteres).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif ($senha && !senha_valida($senha)) {
            $erro = senha_erro($senha);
        } else {
            if ($usuario) {
                $dup = db()->prepare('SELECT id FROM usuarios WHERE usuario = ? AND id != ?');
                $dup->execute([$usuario, $id]);
                if ($dup->fetch()) { $erro = 'Esse nome de usuário já está em uso por outro usuário.'; }
            }
            if (!$erro) {
                $existe = db()->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ?');
                $existe->execute([$email, $id]);
                if ($existe->fetch()) { $erro = 'Esse e-mail já está em uso por outro usuário.'; }
            }
            if (!$erro) {
                if ($eh_proprio) {
                    $novo_perfil = 'admin';
                } elseif ($tipo === 'admin') {
                    $novo_perfil = 'admin';
                } else {
                    $modulos_sel = array_intersect(
                        $_POST['modulos'] ?? [],
                        array_keys(MODULOS_PORTAL)
                    );
                    $novo_perfil = json_encode(array_values($modulos_sel));
                }

                if ($senha) {
                    db()->prepare('UPDATE usuarios SET nome=?, usuario=?, email=?, perfil=?, senha_hash=? WHERE id=?')
                        ->execute([$nome, $usuario ?: null, $email, $novo_perfil, password_hash($senha, PASSWORD_DEFAULT), $id]);
                } else {
                    db()->prepare('UPDATE usuarios SET nome=?, usuario=?, email=?, perfil=? WHERE id=?')
                        ->execute([$nome, $usuario ?: null, $email, $novo_perfil, $id]);
                }
                header('Location: /portal/usuarios/?editado=1');
                exit;
            }
        }
    } // fim else (salvar)
}

// Determina estado atual para o formulário
$perfil_atual = $u['perfil'];
$eh_admin_atual = ($perfil_atual === 'admin');
$modulos_atuais = [];
if (!$eh_admin_atual) {
    if (isset(PERFIL_LEGADO[$perfil_atual])) {
        $modulos_atuais = PERFIL_LEGADO[$perfil_atual];
    } else {
        $modulos_atuais = json_decode($perfil_atual, true) ?: [];
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
.perm-check span{font-size:.82rem;font-weight:500;color:var(--txt)}
.perm-check.disabled{opacity:.45;pointer-events:none}
@media(max-width:520px){.perm-grid{grid-template-columns:1fr}}
</style>

<div class="form-wrap">
  <h2>Editar usuário</h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label for="nome">Nome completo</label>
      <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($u['nome']) ?>" required>
    </div>

    <div class="form-group">
      <label for="usuario">Nome de usuário <span style="font-weight:400;color:var(--muted)">(opcional — para login sem e-mail)</span></label>
      <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($u['usuario'] ?? '') ?>"
             autocomplete="off" placeholder="ex: joao.silva">
      <span class="form-hint">Letras, números, ponto, hífen e sublinhado. 3–50 caracteres.</span>
    </div>

    <div class="form-group">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" required>
    </div>

    <div class="form-group">
      <label for="senha">Nova senha <span style="font-weight:400;color:var(--muted)">(deixe em branco para não alterar)</span></label>
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

    <div class="form-group">
      <label>Permissões de acesso</label>

      <?php if ($eh_proprio): ?>
        <input type="hidden" name="tipo_acesso" value="admin">
        <div class="perm-wrap">
          <div class="perm-opt sel">
            <div class="perm-opt-info">
              <strong>Administrador — acesso total</strong>
              <span>Você não pode alterar o próprio perfil.</span>
            </div>
          </div>
        </div>

      <?php else: ?>
        <div class="perm-wrap">
          <!-- Opção: Admin -->
          <label class="perm-opt <?= $eh_admin_atual ? 'sel' : '' ?>" id="opt-admin">
            <input type="radio" name="tipo_acesso" value="admin" id="radio-admin" <?= $eh_admin_atual ? 'checked' : '' ?>>
            <div class="perm-opt-info">
              <strong>Administrador</strong>
              <span>Acesso total a todos os módulos e configurações</span>
            </div>
          </label>

          <!-- Opção: Módulos personalizados -->
          <label class="perm-opt perm-divisor <?= !$eh_admin_atual ? 'sel' : '' ?>" id="opt-modulos">
            <input type="radio" name="tipo_acesso" value="modulos" id="radio-modulos" <?= !$eh_admin_atual ? 'checked' : '' ?>>
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
              <label class="perm-check <?= in_array($chave, $modulos_atuais) ? 'on' : '' ?>" id="check-<?= $chave ?>">
                <input type="checkbox" name="modulos[]" value="<?= $chave ?>"
                  <?= in_array($chave, $modulos_atuais) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($rotulo) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/usuarios/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>

  <?php if (!$eh_proprio): ?>
  <div style="margin-top:32px;padding-top:20px;border-top:1px solid var(--border)">
    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:8px">Zona de perigo</div>
    <p style="font-size:.83rem;color:var(--muted);margin:0 0 12px">A exclusão é permanente e não pode ser desfeita.</p>
    <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('modal-deletar').style.display='flex'">Excluir usuário</button>
  </div>
  <?php endif; ?>
</div>

<?php if (!$eh_proprio): ?>
<div id="modal-deletar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:800;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:var(--rl);width:100%;max-width:400px;overflow:hidden;box-shadow:var(--sh)">
    <div style="padding:20px 22px 14px;border-bottom:1px solid var(--border)">
      <div style="font-family:'Cinzel',serif;font-size:.88rem;font-weight:700;color:#b91c1c;text-transform:uppercase;letter-spacing:.05em">Excluir usuário</div>
    </div>
    <div style="padding:20px 22px">
      <p style="font-size:.88rem;margin:0 0 16px">Tem certeza que deseja excluir o usuário <strong><?= htmlspecialchars($u['nome']) ?></strong>?</p>
      <p style="font-size:.78rem;color:var(--muted);margin:0 0 20px">Esta ação não pode ser desfeita. O usuário perderá acesso imediatamente.</p>
      <form method="post" style="display:flex;gap:10px;justify-content:flex-end">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao" value="deletar">
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('modal-deletar').style.display='none'">Cancelar</button>
        <button type="submit" class="btn btn-danger btn-sm">Excluir permanentemente</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
(function(){
  var radAdmin  = document.getElementById('radio-admin');
  var radMods   = document.getElementById('radio-modulos');
  var optAdmin  = document.getElementById('opt-admin');
  var optMods   = document.getElementById('opt-modulos');
  var area      = document.getElementById('perm-modulos-area');
  var checks    = area ? area.querySelectorAll('.perm-check') : [];

  function atualizar() {
    var isAdmin = radAdmin && radAdmin.checked;
    if (optAdmin) optAdmin.classList.toggle('sel', isAdmin);
    if (optMods)  optMods.classList.toggle('sel', !isAdmin);
    checks.forEach(function(label) {
      label.classList.toggle('disabled', isAdmin);
      var cb = label.querySelector('input[type=checkbox]');
      if (cb) cb.disabled = isAdmin; // atributo HTML disabled — impede submissão e clique
    });
    if (area) area.style.opacity = isAdmin ? '.5' : '1';
  }

  if (radAdmin)  radAdmin.addEventListener('change', atualizar);
  if (radMods)   radMods.addEventListener('change', atualizar);

  checks.forEach(function(label) {
    var cb = label.querySelector('input[type=checkbox]');
    if (cb) cb.addEventListener('change', function() { label.classList.toggle('on', cb.checked); });
  });

  atualizar();

  // ── Medidor de força de senha ──
  var campo   = document.getElementById('senha');
  var sfWrap  = document.getElementById('sf-wrap');
  var sfFill  = document.getElementById('sf-fill');
  var sfLista = document.getElementById('sf-lista');
  var sfLabel = document.getElementById('sf-label');
  var niveis  = ['Fraca', 'Média', 'Forte'];
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
})();
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
