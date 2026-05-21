<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

/* ── AJAX: criação inline ── */
if (($_GET['ajax'] ?? '') === '1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_valido()) { echo json_encode(['ok'=>false,'erro'=>'Token inválido.']); exit; }
    $nome = trim($_POST['nome'] ?? '');
    $desc = trim($_POST['descricao'] ?? '');
    $cor  = $_POST['cor'] ?? '#8b44a8';
    if (!preg_match('/^#[0-9a-f]{6}$/i', $cor)) $cor = '#8b44a8';
    if (!$nome) { echo json_encode(['ok'=>false,'erro'=>'O nome é obrigatório.']); exit; }
    db()->prepare("INSERT INTO membros_pastoreio (nome,descricao,cor) VALUES (?,?,?)")->execute([$nome,$desc,$cor]);
    $id = (int)db()->lastInsertId();
    echo json_encode(['ok'=>true,'id'=>$id,'nome'=>$nome,'cor'=>$cor]);
    exit;
}

$titulo       = 'Pastoreio';
$pagina_ativa = 'membros';

$erros = [];
$ok    = '';

$editando = null;
$edit_id  = (int)($_GET['editar'] ?? 0);
if ($edit_id) {
    $st = db()->prepare("SELECT * FROM membros_pastoreio WHERE id=?");
    $st->execute([$edit_id]);
    $editando = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar' || $acao === 'editar') {
        $nome = trim($_POST['nome'] ?? '');
        $desc = trim($_POST['descricao'] ?? '');
        $cor  = $_POST['cor'] ?? '#8b44a8';
        if (!preg_match('/^#[0-9a-f]{6}$/i', $cor)) $cor = '#8b44a8';
        if (!$nome) $erros[] = 'O nome é obrigatório.';
        if (!$erros) {
            if ($acao === 'criar') {
                db()->prepare("INSERT INTO membros_pastoreio (nome,descricao,cor) VALUES (?,?,?)")->execute([$nome,$desc,$cor]);
                $ok = 'Pastoreio criado com sucesso.';
            } else {
                $pid = (int)$_POST['id'];
                db()->prepare("UPDATE membros_pastoreio SET nome=?,descricao=?,cor=? WHERE id=?")->execute([$nome,$desc,$cor,$pid]);
                $ok = 'Pastoreio atualizado.';
                $editando = null; $edit_id = 0;
            }
        }
    }

    if ($acao === 'excluir') {
        $pid = (int)$_POST['id'];
        db()->prepare("DELETE FROM membros_pastoreio WHERE id=?")->execute([$pid]);
        $ok = 'Pastoreio excluído.';
    }
}

$pastoreios = db()->query("SELECT p.*, COUNT(r.membro_id) as total FROM membros_pastoreio p LEFT JOIN membros_pastoreio_rel r ON r.pastoreio_id=p.id GROUP BY p.id ORDER BY p.nome")->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<div style="max-width:820px">

  <div style="margin-bottom:20px">
    <a href="/portal/membros/" class="btn btn-ghost btn-sm">← Voltar para membros</a>
  </div>

  <?php if ($ok): ?><div class="alerta alerta-ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
  <?php if ($erros): ?><div class="alerta alerta-erro"><?= implode('<br>', array_map('htmlspecialchars', $erros)) ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

    <!-- Lista -->
    <div class="tabela-wrap">
      <div class="tabela-header">
        <h2>Pastoreio</h2>
        <span style="font-size:.78rem;color:var(--muted)"><?= count($pastoreios) ?> registro<?= count($pastoreios) !== 1 ? 's' : '' ?></span>
      </div>
      <?php if (empty($pastoreios)): ?>
        <div style="padding:40px 20px;text-align:center;color:var(--muted);font-size:.85rem">
          Nenhum registro de pastoreio criado ainda.<br>Use o formulário ao lado para criar o primeiro.
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Membros</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pastoreios as $p): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:9px">
                <span style="width:12px;height:12px;border-radius:50%;border:2px solid <?= htmlspecialchars($p['cor']) ?>;background:<?= htmlspecialchars($p['cor']) ?>22;flex-shrink:0;display:inline-block;box-sizing:border-box"></span>
                <div>
                  <div style="font-weight:600"><?= htmlspecialchars($p['nome']) ?></div>
                  <?php if ($p['descricao']): ?>
                    <div style="font-size:.74rem;color:var(--muted)"><?= htmlspecialchars($p['descricao']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <a href="/portal/membros/?pastoreio=<?= $p['id'] ?>" style="font-weight:600;color:var(--green-dk)">
                <?= $p['total'] ?> membro<?= $p['total'] !== 1 ? 's' : '' ?>
              </a>
            </td>
            <td style="display:flex;gap:6px">
              <a href="?editar=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
              <form method="post" onsubmit="return confirm('Excluir <?= htmlspecialchars(addslashes($p['nome'])) ?>? Os membros não serão excluídos.')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <!-- Formulário -->
    <div class="form-wrap" style="border-top-color:#8b44a8">
      <h2><?= $editando ? 'Editar pastoreio' : 'Novo pastoreio' ?></h2>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao" value="<?= $editando ? 'editar' : 'criar' ?>">
        <?php if ($editando): ?>
          <input type="hidden" name="id" value="<?= $editando['id'] ?>">
        <?php endif; ?>

        <div class="form-group">
          <label>Nome <span style="color:var(--red)">*</span></label>
          <input type="text" name="nome" value="<?= htmlspecialchars($editando['nome'] ?? '') ?>" required maxlength="100" placeholder="Ex.: Célula A, Região Norte…">
        </div>

        <div class="form-group">
          <label>Descrição <span style="font-weight:400;color:var(--muted)">(opcional)</span></label>
          <textarea name="descricao" rows="2" placeholder="Observação…" style="min-height:56px"><?= htmlspecialchars($editando['descricao'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label>Cor de identificação</label>
          <div style="display:flex;align-items:center;gap:10px">
            <input type="color" name="cor" value="<?= htmlspecialchars($editando['cor'] ?? '#8b44a8') ?>"
              style="width:44px;height:36px;padding:2px 4px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;background:var(--off)">
            <span style="font-size:.78rem;color:var(--muted)">Usada nos cards e na sidebar</span>
          </div>
        </div>

        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary"><?= $editando ? 'Salvar alterações' : 'Criar pastoreio' ?></button>
          <?php if ($editando): ?>
            <a href="/portal/membros/pastoreio.php" class="btn btn-ghost">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

  </div>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
