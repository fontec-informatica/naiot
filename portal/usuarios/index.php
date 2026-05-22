<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'usuarios']);

$titulo       = 'Usuários';
$pagina_ativa = 'usuarios';

// Ação: ativar/desativar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);

    if ($acao === 'toggle' && $id) {
        db()->prepare('UPDATE usuarios SET ativo = NOT ativo WHERE id = ? AND perfil != "admin"')
            ->execute([$id]);
    }
    header('Location: /portal/usuarios/');
    exit;
}

$usuarios = db()->query('SELECT id, nome, email, perfil, ativo, criado_em, ultimo_acesso FROM usuarios ORDER BY nome')->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<?php if (!empty($_GET['deletado'])): ?>
  <div class="alerta alerta-ok" style="margin-bottom:16px">Usuário excluído com sucesso.</div>
<?php endif; ?>
<?php if (!empty($_GET['editado'])): ?>
  <div class="alerta alerta-ok" style="margin-bottom:16px">Alterações salvas com sucesso.</div>
<?php endif; ?>

<div class="tabela-wrap">
  <div class="tabela-header">
    <h2>Usuários do sistema</h2>
    <a href="/portal/usuarios/novo.php" class="btn btn-primary btn-sm">+ Novo usuário</a>
  </div>
  <table>
    <thead>
      <tr>
        <th>Nome</th>
        <th>E-mail</th>
        <th>Perfil</th>
        <th>Último acesso</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($usuarios as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['nome']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td>
          <?php
            $lbl = label_perfil($u['perfil']);
            $cls = $u['perfil'] === 'admin' ? 'badge-admin' : 'badge-secretaria';
          ?>
          <span class="badge <?= $cls ?>"><?= htmlspecialchars($lbl) ?></span>
        </td>
        <td style="color:var(--cinza3);font-size:.82rem">
          <?= $u['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acesso'])) : '—' ?>
        </td>
        <td>
          <?php if ($u['ativo']): ?>
            <span style="color:var(--verde);font-size:.82rem">● Ativo</span>
          <?php else: ?>
            <span class="badge badge-inativo">Inativo</span>
          <?php endif; ?>
        </td>
        <td style="display:flex;gap:8px">
          <a href="/portal/usuarios/editar.php?id=<?= $u['id'] ?>"
             class="btn btn-ghost btn-sm">Editar</a>
          <?php if ($u['perfil'] !== 'admin'): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="toggle">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button type="submit" class="btn btn-sm <?= $u['ativo'] ? 'btn-danger' : 'btn-ouro' ?>">
              <?= $u['ativo'] ? 'Desativar' : 'Ativar' ?>
            </button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
