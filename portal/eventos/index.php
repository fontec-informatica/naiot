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
        } elseif ($acao === 'subir' || $acao === 'descer') {
            $ids = db()->query('SELECT id FROM eventos ORDER BY ordem ASC, id ASC')
                       ->fetchAll(PDO::FETCH_COLUMN);
            $ids = array_map('intval', $ids);
            $pos = array_search($id, $ids, true);
            if ($pos !== false) {
                $alvo = $acao === 'subir' ? $pos - 1 : $pos + 1;
                if ($alvo >= 0 && $alvo < count($ids)) {
                    [$ids[$pos], $ids[$alvo]] = [$ids[$alvo], $ids[$pos]];
                    $upd = db()->prepare('UPDATE eventos SET ordem = ? WHERE id = ?');
                    foreach ($ids as $i => $eid) { $upd->execute([$i, $eid]); }
                }
            }
        }
    }
    header('Location: /portal/eventos/');
    exit;
}

$eventos = db()->query("SELECT * FROM eventos ORDER BY COALESCE(data_evento,'9999-12-31') ASC, ordem ASC, id ASC")->fetchAll();

/* ── Normalizar ordens para manter consistência com a ordem de exibição ── */
$upd = db()->prepare('UPDATE eventos SET ordem = ? WHERE id = ?');
foreach ($eventos as $i => $ev) { $upd->execute([$i, $ev['id']]); }
$eventos = db()->query("SELECT * FROM eventos ORDER BY COALESCE(data_evento,'9999-12-31') ASC, ordem ASC, id ASC")->fetchAll();

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
