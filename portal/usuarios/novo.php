<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin']);

$titulo       = 'Novo Usuário';
$pagina_ativa = 'usuarios';
$erro = '';
$ok   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $senha  = $_POST['senha'] ?? '';
        $perfil = $_POST['perfil'] ?? '';
        $perfis_validos = ['admin', 'financeiro', 'secretaria'];

        if (!$nome || !$email || !$senha || !$perfil) {
            $erro = 'Preencha todos os campos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } elseif (!in_array($perfil, $perfis_validos, true)) {
            $erro = 'Perfil inválido.';
        } elseif (strlen($senha) < 8) {
            $erro = 'A senha deve ter no mínimo 8 caracteres.';
        } else {
            $existe = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
            $existe->execute([$email]);
            if ($existe->fetch()) {
                $erro = 'Já existe um usuário com esse e-mail.';
            } else {
                db()->prepare('INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?,?,?,?)')
                    ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $perfil]);
                header('Location: /portal/usuarios/?criado=1');
                exit;
            }
        }
    }
}

include dirname(__DIR__) . '/_layout.php';
?>

<div class="form-wrap">
  <h2>Novo usuário</h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label for="nome">Nome completo</label>
      <input type="text" id="nome" name="nome"
             value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" required>
      <span class="form-hint">Mínimo 8 caracteres.</span>
    </div>

    <div class="form-group">
      <label for="perfil">Perfil de acesso</label>
      <select id="perfil" name="perfil" required>
        <option value="">Selecione…</option>
        <option value="admin"       <?= ($_POST['perfil'] ?? '') === 'admin'       ? 'selected' : '' ?>>Admin — acesso total</option>
        <option value="secretaria"  <?= ($_POST['perfil'] ?? '') === 'secretaria'  ? 'selected' : '' ?>>Secretaria — eventos e inscrições</option>
        <option value="financeiro"  <?= ($_POST['perfil'] ?? '') === 'financeiro'  ? 'selected' : '' ?>>Financeiro — módulo financeiro</option>
      </select>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Criar usuário</button>
      <a href="/portal/usuarios/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
