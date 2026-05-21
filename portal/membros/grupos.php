<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Grupos de Membros';
$pagina_ativa = 'membros';

$erros = [];
$ok    = '';

$editando = null;
$edit_id  = (int)($_GET['editar'] ?? 0);
if ($edit_id) {
    $st = db()->prepare("SELECT * FROM membros_grupos WHERE id=?");
    $st->execute([$edit_id]);
    $editando = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar' || $acao === 'editar') {
        $nome = trim($_POST['nome'] ?? '');
        $desc = trim($_POST['descricao'] ?? '');
        $cor  = $_POST['cor'] ?? '#1e6b35';
        if (!preg_match('/^#[0-9a-f]{6}$/i', $cor)) $cor = '#1e6b35';

        if (!$nome) $erros[] = 'O nome do grupo é obrigatório.';

        if (!$erros) {
            if ($acao === 'criar') {
                db()->prepare("INSERT INTO membros_grupos (nome,descricao,cor) VALUES (?,?,?)")->execute([$nome,$desc,$cor]);
                $ok = 'Grupo criado com sucesso.';
            } else {
                $gid = (int)$_POST['id'];
                db()->prepare("UPDATE membros_grupos SET nome=?,descricao=?,cor=? WHERE id=?")->execute([$nome,$desc,$cor,$gid]);
                $ok = 'Grupo atualizado.';
                $editando = null; $edit_id = 0;
            }
        }
    }

    if ($acao === 'excluir') {
        $gid = (int)$_POST['id'];
        db()->prepare("DELETE FROM membros_grupos WHERE id=?")->execute([$gid]);
        $ok = 'Grupo excluído.';
    }
}

$grupos = db()->query("SELECT g.*, COUNT(r.membro_id) as total FROM membros_grupos g LEFT JOIN membros_grupo_rel r ON r.grupo_id=g.id GROUP BY g.id ORDER BY g.nome")->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<div style="max-width:820px">

  <div style="margin-bottom:20px">
    <a href="/portal/membros/" class="btn btn-ghost btn-sm">← Voltar para membros</a>
  </div>

  <?php if ($ok): ?><div class="alerta alerta-ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
  <?php if ($erros): ?><div class="alerta alerta-erro"><?= implode('<br>', array_map('htmlspecialchars', $erros)) ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

    <!-- Lista de grupos -->
    <div class="tabela-wrap">
      <div class="tabela-header">
        <h2>Grupos</h2>
        <span style="font-size:.78rem;color:var(--muted)"><?= count($grupos) ?> grupo<?= count($grupos) !== 1 ? 's' : '' ?></span>
      </div>
      <?php if (empty($grupos)): ?>
        <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:.85rem">
          Nenhum grupo criado ainda.<br>Use o formulário ao lado para criar o primeiro grupo.
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Grupo</th>
            <th>Membros</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($grupos as $g): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:9px">
                <span style="width:13px;height:13px;border-radius:50%;background:<?= htmlspecialchars($g['cor']) ?>;flex-shrink:0;display:inline-block"></span>
                <div>
                  <div style="font-weight:600"><?= htmlspecialchars($g['nome']) ?></div>
                  <?php if ($g['descricao']): ?>
                    <div style="font-size:.74rem;color:var(--muted)"><?= htmlspecialchars($g['descricao']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <a href="/portal/membros/?grupo=<?= $g['id'] ?>" style="font-weight:600;color:var(--green-dk)">
                <?= $g['total'] ?> membro<?= $g['total'] !== 1 ? 's' : '' ?>
              </a>
            </td>
            <td style="display:flex;gap:6px">
              <a href="?editar=<?= $g['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
              <form method="post" onsubmit="return confirm('Excluir o grupo <?= htmlspecialchars(addslashes($g['nome'])) ?>? Os membros não serão excluídos.')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <!-- Formulário criar/editar -->
    <div class="form-wrap" style="border-top-color:var(--green)">
      <h2><?= $editando ? 'Editar grupo' : 'Novo grupo' ?></h2>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao" value="<?= $editando ? 'editar' : 'criar' ?>">
        <?php if ($editando): ?>
          <input type="hidden" name="id" value="<?= $editando['id'] ?>">
        <?php endif; ?>

        <div class="form-group">
          <label>Nome do grupo <span style="color:var(--red)">*</span></label>
          <input type="text" name="nome" value="<?= htmlspecialchars($editando['nome'] ?? '') ?>" required maxlength="100" placeholder="Ex.: Grupo do Caminho">
        </div>

        <div class="form-group">
          <label>Descrição <span style="font-weight:400;color:var(--muted)">(opcional)</span></label>
          <textarea name="descricao" rows="2" placeholder="Descrição ou observação sobre o grupo…" style="min-height:56px"><?= htmlspecialchars($editando['descricao'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label>Cor de identificação</label>
          <div style="display:flex;align-items:center;gap:10px">
            <input type="color" name="cor" value="<?= htmlspecialchars($editando['cor'] ?? '#1e6b35') ?>"
              style="width:44px;height:36px;padding:2px 4px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;background:var(--off)">
            <span style="font-size:.78rem;color:var(--muted)">Usada nos cards e na sidebar</span>
          </div>
        </div>

        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary"><?= $editando ? 'Salvar alterações' : 'Criar grupo' ?></button>
          <?php if ($editando): ?>
            <a href="/portal/membros/grupos.php" class="btn btn-ghost">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

  </div>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
