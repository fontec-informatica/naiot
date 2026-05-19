<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$evento_id = (int)($_GET['id'] ?? 0);
if (!$evento_id) { header('Location: /portal/eventos/'); exit; }

$ev_stmt = db()->prepare('SELECT * FROM eventos WHERE id = ?');
$ev_stmt->execute([$evento_id]);
$evento = $ev_stmt->fetch();
if (!$evento) { header('Location: /portal/eventos/'); exit; }

$titulo       = 'Lotes — ' . $evento['titulo'];
$pagina_ativa = 'eventos';
$erro         = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'add') {
        $nome       = trim($_POST['nome'] ?? '');
        $descricao  = trim($_POST['descricao'] ?? '');
        $valor      = (float)str_replace(',', '.', str_replace('.', '', preg_replace('/[^0-9,]/', '', $_POST['valor'] ?? '0')));
        $vagas      = (int)($_POST['vagas'] ?? 0) ?: null;
        $data_ini   = $_POST['data_inicio'] ?: null;
        $data_fim   = $_POST['data_fim']    ?: null;
        $ordem      = (int)($_POST['ordem'] ?? 0);

        if (!$nome) {
            $erro = 'Nome do lote é obrigatório.';
        } else {
            db()->prepare("INSERT INTO evento_lotes (evento_id, nome, descricao, valor, vagas, data_inicio, data_fim, ordem) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$evento_id, $nome, $descricao ?: null, $valor, $vagas, $data_ini, $data_fim, $ordem]);
            header("Location: /portal/eventos/lotes.php?id=$evento_id&ok=1");
            exit;
        }

    } elseif ($acao === 'deletar') {
        $lote_id = (int)($_POST['lote_id'] ?? 0);
        if ($lote_id) {
            db()->prepare("DELETE FROM evento_lotes WHERE id = ? AND evento_id = ?")->execute([$lote_id, $evento_id]);
        }
        header("Location: /portal/eventos/lotes.php?id=$evento_id");
        exit;

    } elseif ($acao === 'toggle') {
        $lote_id = (int)($_POST['lote_id'] ?? 0);
        if ($lote_id) {
            db()->prepare("UPDATE evento_lotes SET ativo = NOT ativo WHERE id = ? AND evento_id = ?")->execute([$lote_id, $evento_id]);
        }
        header("Location: /portal/eventos/lotes.php?id=$evento_id");
        exit;
    }
}

$lotes = db()->prepare("
    SELECT l.*,
           (SELECT COUNT(*) FROM inscricoes i WHERE i.lote_id = l.id AND i.status != 'cancelado') AS inscritos
    FROM evento_lotes l WHERE l.evento_id = ? ORDER BY l.ordem ASC, l.id ASC
");
$lotes->execute([$evento_id]);
$lotes_list = $lotes->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<?php if (isset($_GET['ok'])): ?>
<div class="alerta alerta-ok" style="margin-bottom:20px">✓ Lote adicionado com sucesso.</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start">

  <!-- Lista de lotes -->
  <div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div>
        <h2 style="font-size:1rem;font-weight:600"><?= htmlspecialchars($evento['titulo']) ?></h2>
        <a href="/portal/eventos/editar.php?id=<?= $evento_id ?>" style="font-size:.82rem;color:var(--cinza3)">← Editar evento</a>
      </div>
    </div>

    <?php if (empty($lotes_list)): ?>
    <div style="background:#fff;border-radius:10px;padding:32px;text-align:center;color:var(--cinza3);box-shadow:0 1px 4px rgba(0,0,0,.06)">
      Nenhum lote cadastrado. Adicione ao lado →
    </div>
    <?php else: ?>
    <div class="tabela-wrap">
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th style="text-align:right">Valor</th>
            <th style="text-align:center">Vagas</th>
            <th>Período</th>
            <th style="text-align:center">Inscritos</th>
            <th style="text-align:center">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($lotes_list as $l): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($l['nome']) ?></strong>
            <?php if ($l['descricao']): ?><br><small style="color:var(--cinza3)"><?= htmlspecialchars($l['descricao']) ?></small><?php endif; ?>
          </td>
          <td style="text-align:right;font-weight:700">
            <?= $l['valor'] > 0 ? 'R$ '.number_format($l['valor'],2,',','.') : '<span style="color:var(--verde)">Gratuito</span>' ?>
          </td>
          <td style="text-align:center"><?= $l['vagas'] ?? '∞' ?></td>
          <td style="font-size:.82rem;color:var(--cinza3)">
            <?php if ($l['data_inicio'] || $l['data_fim']): ?>
              <?= $l['data_inicio'] ? date('d/m/Y', strtotime($l['data_inicio'])) : '—' ?>
              <?= ($l['data_inicio'] && $l['data_fim']) ? ' a ' : '' ?>
              <?= $l['data_fim'] ? date('d/m/Y', strtotime($l['data_fim'])) : '' ?>
            <?php else: ?>
              Sempre
            <?php endif; ?>
          </td>
          <td style="text-align:center;font-weight:600"><?= (int)$l['inscritos'] ?><?= $l['vagas'] ? '<span style="color:var(--cinza3);font-weight:400">/'.($l['vagas']).'</span>' : '' ?></td>
          <td style="text-align:center">
            <?php if ($l['ativo']): ?>
              <span style="color:var(--verde);font-size:.8rem">● Ativo</span>
            <?php else: ?>
              <span class="badge badge-inativo">Inativo</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap">
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="lote_id" value="<?= $l['id'] ?>">
              <button name="acao" value="toggle" class="btn btn-ghost btn-sm"><?= $l['ativo'] ? 'Pausar' : 'Ativar' ?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Excluir este lote?')">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="lote_id" value="<?= $l['id'] ?>">
              <button name="acao" value="deletar" class="btn btn-danger btn-sm">Excluir</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Adicionar lote -->
  <div class="form-wrap" style="max-width:none">
    <h2>Novo lote</h2>

    <?php if ($erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="add">

      <div class="form-group">
        <label>Nome do lote</label>
        <input type="text" name="nome" placeholder="Ex: 1º Lote, Adulto, Criança..." value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label>Descrição <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="text" name="descricao" placeholder="Ex: Inclui kit completo" value="<?= htmlspecialchars($_POST['descricao'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Valor (R$)</label>
        <input type="text" name="valor" placeholder="0,00" value="<?= htmlspecialchars($_POST['valor'] ?? '') ?>">
        <span class="form-hint">Use 0,00 para lote gratuito.</span>
      </div>

      <div class="form-group">
        <label>Vagas neste lote <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="number" name="vagas" min="1" placeholder="Deixe vazio para ilimitado" value="<?= htmlspecialchars($_POST['vagas'] ?? '') ?>">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Válido a partir de</label>
          <input type="date" name="data_inicio" value="<?= htmlspecialchars($_POST['data_inicio'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Válido até</label>
          <input type="date" name="data_fim" value="<?= htmlspecialchars($_POST['data_fim'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Ordem de exibição</label>
        <input type="number" name="ordem" min="0" value="<?= htmlspecialchars($_POST['ordem'] ?? '0') ?>">
        <span class="form-hint">Menor número aparece primeiro.</span>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%">Adicionar lote</button>
    </form>
  </div>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
