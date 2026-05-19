<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin']);

$titulo       = 'Editar Usuário';
$pagina_ativa = 'usuarios';
$erro = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/usuarios/'); exit; }

$usuario = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
$usuario->execute([$id]);
$u = $usuario->fetch();
if (!$u) { header('Location: /portal/usuarios/'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $perfil = $_POST['perfil'] ?? '';
        $senha  = $_POST['senha'] ?? '';
        $perfis_validos = ['admin', 'financeiro', 'secretaria'];

        if (!$nome || !$email || !$perfil) {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif (!in_array($perfil, $perfis_validos, true)) {
            $erro = 'Perfil inválido.';
        } elseif ($senha && strlen($senha) < 8) {
            $erro = 'A nova senha deve ter no mínimo 8 caracteres.';
        } else {
            $existe = db()->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ?');
            $existe->execute([$email, $id]);
            if ($existe->fetch()) {
                $erro = 'Esse e-mail já está em uso por outro usuário.';
            } else {
                if ($senha) {
                    db()->prepare('UPDATE usuarios SET nome=?, email=?, perfil=?, senha_hash=? WHERE id=?')
                        ->execute([$nome, $email, $perfil, password_hash($senha, PASSWORD_DEFAULT), $id]);
                } else {
                    db()->prepare('UPDATE usuarios SET nome=?, email=?, perfil=? WHERE id=?')
                        ->execute([$nome, $email, $perfil, $id]);
                }
                header('Location: /portal/usuarios/?editado=1');
                exit;
            }
        }
    }
    // repopular com dados postados
    $u = array_merge($u, ['nome' => $_POST['nome'] ?? '', 'email' => $_POST['email'] ?? '', 'perfil' => $_POST['perfil'] ?? '']);
}

include dirname(__DIR__) . '/_layout.php';
?>

<div class="form-wrap">
  <h2>Editar usuário</h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label for="nome">Nome completo</label>
      <input type="text" id="nome" name="nome"
             value="<?= htmlspecialchars($u['nome']) ?>" required>
    </div>

    <div class="form-group">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($u['email']) ?>" required>
    </div>

    <div class="form-group">
      <label for="senha">Nova senha <span style="font-weight:400;color:var(--cinza3)">(deixe em branco para não alterar)</span></label>
      <input type="password" id="senha" name="senha">
      <span class="form-hint">Mínimo 8 caracteres.</span>
    </div>

    <div class="form-group">
      <label for="perfil">Perfil de acesso</label>
      <select id="perfil" name="perfil" required <?= $u['perfil'] === 'admin' && $id === $_SESSION['usuario_id'] ? 'disabled' : '' ?>>
        <option value="admin"       <?= $u['perfil'] === 'admin'       ? 'selected' : '' ?>>Admin — acesso total</option>
        <option value="secretaria"  <?= $u['perfil'] === 'secretaria'  ? 'selected' : '' ?>>Secretaria — eventos e inscrições</option>
        <option value="financeiro"  <?= $u['perfil'] === 'financeiro'  ? 'selected' : '' ?>>Financeiro — módulo financeiro</option>
      </select>
      <?php if ($u['perfil'] === 'admin' && $id === $_SESSION['usuario_id']): ?>
        <input type="hidden" name="perfil" value="admin">
        <span class="form-hint">Você não pode alterar o próprio perfil.</span>
      <?php endif; ?>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/usuarios/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
