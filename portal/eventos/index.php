<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Próximos Eventos';
$pagina_ativa = 'eventos';

// Ações rápidas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);

    if ($id) {
        if ($acao === 'toggle') {
            db()->prepare('UPDATE eventos SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
        } elseif ($acao === 'subir') {
            $ev = db()->prepare('SELECT id, ordem FROM eventos WHERE id = ?');
            $ev->execute([$id]);
            $atual = $ev->fetch();
            $ant = db()->prepare('SELECT id, ordem FROM eventos WHERE ordem < ? ORDER BY ordem DESC LIMIT 1');
            $ant->execute([$atual['ordem']]);
            $anterior = $ant->fetch();
            if ($anterior) {
                db()->prepare('UPDATE eventos SET ordem = ? WHERE id = ?')->execute([$anterior['ordem'], $id]);
                db()->prepare('UPDATE eventos SET ordem = ? WHERE id = ?')->execute([$atual['ordem'], $anterior['id']]);
            }
        } elseif ($acao === 'descer') {
            $ev = db()->prepare('SELECT id, ordem FROM eventos WHERE id = ?');
            $ev->execute([$id]);
            $atual = $ev->fetch();
            $prx = db()->prepare('SELECT id, ordem FROM eventos WHERE ordem > ? ORDER BY ordem ASC LIMIT 1');
            $prx->execute([$atual['ordem']]);
            $proximo = $prx->fetch();
            if ($proximo) {
                db()->prepare('UPDATE eventos SET ordem = ? WHERE id = ?')->execute([$proximo['ordem'], $id]);
                db()->prepare('UPDATE eventos SET ordem = ? WHERE id = ?')->execute([$atual['ordem'], $proximo['id']]);
            }
        }
    }
    header('Location: /portal/eventos/');
    exit;
}

$eventos = db()->query('SELECT * FROM eventos ORDER BY ordem ASC, id ASC')->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<div class="tabela-wrap">
  <div class="tabela-header">
    <h2>Próximos eventos</h2>
    <a href="/portal/eventos/novo.php" class="btn btn-primary btn-sm">+ Novo evento</a>
  </div>

  <?php if (empty($eventos)): ?>
    <div style="padding:40px;text-align:center;color:var(--cinza3)">
      Nenhum evento cadastrado ainda.
    </div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:80px">Arte</th>
        <th>Título</th>
        <th>Data do evento</th>
        <th>Ordem</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($eventos as $ev): ?>
      <tr>
        <td>
          <img src="/assets/img/eventos/<?= htmlspecialchars($ev['imagem']) ?>"
               alt="" style="height:48px;width:auto;border-radius:4px;object-fit:cover">
        </td>
        <td>
          <strong><?= htmlspecialchars($ev['titulo']) ?></strong>
          <?php if ($ev['descricao']): ?>
          <br><small style="color:var(--cinza3)"><?= htmlspecialchars(mb_substr($ev['descricao'],0,60)) ?>…</small>
          <?php endif; ?>
        </td>
        <td style="color:var(--cinza3);font-size:.85rem">
          <?= $ev['data_evento'] ? date('d/m/Y', strtotime($ev['data_evento'])) : '—' ?>
        </td>
        <td>
          <form method="post" style="display:flex;gap:4px;align-items:center">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="id" value="<?= $ev['id'] ?>">
            <button name="acao" value="subir"  class="btn btn-ghost btn-sm" title="Subir">↑</button>
            <button name="acao" value="descer" class="btn btn-ghost btn-sm" title="Descer">↓</button>
          </form>
        </td>
        <td>
          <?php if ($ev['ativo']): ?>
            <span style="color:var(--verde);font-size:.82rem">● Ativo</span>
          <?php else: ?>
            <span class="badge badge-inativo">Inativo</span>
          <?php endif; ?>
        </td>
        <td style="display:flex;gap:8px">
          <a href="/portal/eventos/editar.php?id=<?= $ev['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="toggle">
            <input type="hidden" name="id" value="<?= $ev['id'] ?>">
            <button type="submit" class="btn btn-sm <?= $ev['ativo'] ? 'btn-danger' : 'btn-ouro' ?>">
              <?= $ev['ativo'] ? 'Desativar' : 'Ativar' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
