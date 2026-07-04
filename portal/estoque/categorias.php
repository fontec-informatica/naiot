<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'estoque']);

$titulo       = 'Livraria — Categorias';
$pagina_ativa = 'estoque';
$loja_secao   = 'categorias';
$erro         = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'add') {
        $nome = trim($_POST['nome'] ?? '');
        $cor  = trim($_POST['cor'] ?? '') ?: '#6b7280';

        if (!$nome) {
            $erro = 'Nome da categoria é obrigatório.';
        } else {
            $ordem = (int)db()->query('SELECT COALESCE(MAX(ordem),0)+1 FROM estoque_categorias')->fetchColumn();
            db()->prepare('INSERT INTO estoque_categorias (nome, cor, ordem) VALUES (?,?,?)')
                ->execute([$nome, $cor, $ordem]);
            header('Location: /portal/estoque/categorias.php?criado=1');
            exit;
        }
    } elseif ($acao === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) db()->prepare('UPDATE estoque_categorias SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
        header('Location: /portal/estoque/categorias.php');
        exit;
    } elseif ($acao === 'deletar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM estoque_produtos WHERE categoria_id = ?');
            $stmt->execute([$id]);
            $em_uso = (int)$stmt->fetchColumn();
            if ($em_uso > 0) {
                $erro = 'Não é possível excluir: existem ' . $em_uso . ' produto(s) nesta categoria.';
            } else {
                db()->prepare('DELETE FROM estoque_categorias WHERE id = ?')->execute([$id]);
                header('Location: /portal/estoque/categorias.php?excluido=1');
                exit;
            }
        }
    }
}

$categorias = db()->query('
    SELECT c.*, (SELECT COUNT(*) FROM estoque_produtos p WHERE p.categoria_id = c.id) AS total_produtos
    FROM estoque_categorias c ORDER BY c.ordem ASC, c.nome ASC
')->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<?php include __DIR__ . '/_subnav.php'; ?>

<?php if (!empty($_GET['criado'])): ?>
  <div class="alerta alerta-ok" style="margin-bottom:16px">Categoria criada com sucesso.</div>
<?php endif; ?>
<?php if (!empty($_GET['excluido'])): ?>
  <div class="alerta alerta-ok" style="margin-bottom:16px">Categoria excluída com sucesso.</div>
<?php endif; ?>

<div class="split-layout">

  <div>
    <div class="tabela-wrap">
      <div class="tabela-header">
        <h2>Categorias</h2>
      </div>
      <?php if (empty($categorias)): ?>
        <div style="padding:40px;text-align:center;color:var(--cinza3)">Nenhuma categoria cadastrada ainda.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th style="text-align:center">Produtos</th>
            <th style="text-align:center">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($categorias as $c): ?>
          <tr>
            <td>
              <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:<?= htmlspecialchars($c['cor']) ?>;margin-right:8px"></span>
              <?= htmlspecialchars($c['nome']) ?>
            </td>
            <td style="text-align:center"><?= (int)$c['total_produtos'] ?></td>
            <td style="text-align:center">
              <?php if ($c['ativo']): ?>
                <span style="color:var(--verde);font-size:.8rem">● Ativo</span>
              <?php else: ?>
                <span class="badge badge-inativo">Inativo</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button name="acao" value="toggle" class="btn btn-ghost btn-sm"><?= $c['ativo'] ? 'Desativar' : 'Ativar' ?></button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('Excluir esta categoria?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button name="acao" value="deletar" class="btn btn-danger btn-sm">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-wrap" style="max-width:none">
    <h2>Nova categoria</h2>
    <?php if ($erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="add">
      <div class="form-group">
        <label>Nome</label>
        <input type="text" name="nome" placeholder="Ex: Alimentos" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Cor <span style="font-weight:400;color:var(--cinza3)">(identificação visual)</span></label>
        <input type="color" name="cor" value="<?= htmlspecialchars($_POST['cor'] ?? '#6b7280') ?>" style="height:38px;padding:4px;background:var(--off)">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">Adicionar categoria</button>
    </form>
  </div>

</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
