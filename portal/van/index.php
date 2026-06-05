<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Missões Van';
$pagina_ativa = 'van';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    if (($_POST['acao'] ?? '') === 'excluir') {
        db()->prepare("DELETE FROM van_viagens WHERE id=?")->execute([(int)$_POST['id']]);
        header('Location: /portal/van/'); exit;
    }
}

try {
    $todas = db()->query("
        SELECT v.*, COUNT(p.id) AS total_pass
        FROM van_viagens v
        LEFT JOIN van_passageiros p ON p.viagem_id = v.id
        GROUP BY v.id
    ")->fetchAll();
    $tabela_ok = true;
} catch (PDOException $e) {
    $todas = [];
    $tabela_ok = false;
}

// Separa e ordena
$agendadas  = array_values(array_filter($todas, fn($v) => $v['status'] === 'agendada'));
$historico  = array_values(array_filter($todas, fn($v) => $v['status'] !== 'agendada'));

usort($agendadas, fn($a,$b) => strcmp($a['data_saida'] ?? '9999-12-31', $b['data_saida'] ?? '9999-12-31'));
usort($historico, fn($a,$b) => strcmp($b['data_saida'] ?? '0000-00-00', $a['data_saida'] ?? '0000-00-00'));

$status_cfg = [
    'agendada'  => ['label' => 'Agendada',  'bg' => '#e8f0fb', 'color' => '#3b6cb7'],
    'concluida' => ['label' => 'Concluída', 'bg' => 'var(--green-lt)', 'color' => 'var(--green-dk)'],
    'cancelada' => ['label' => 'Cancelada', 'bg' => '#f5f5f5', 'color' => '#888'],
];

function badge_status(array $cfg): string {
    return '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:600;background:'
        . $cfg['bg'] . ';color:' . $cfg['color'] . '">' . $cfg['label'] . '</span>';
}

include dirname(__DIR__) . '/_layout.php';
?>

<style>
.grupo-titulo {
  font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
  color:var(--muted);margin:20px 0 10px;display:flex;align-items:center;gap:8px
}
.grupo-titulo::after { content:'';flex:1;height:1px;background:var(--border) }

/* Cards mobile */
.van-cards { display:none }
@media(max-width:700px){ .van-tabela{display:none!important} .van-cards{display:block!important} }
</style>

<?php if (!$tabela_ok): ?>
  <div class="alerta alerta-erro">
    Tabelas não encontradas. <a href="/portal/van/setup.php">Criar tabelas</a>.
  </div>
<?php else: ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;flex-wrap:wrap;gap:10px">
  <h2 style="margin:0">Missões Van</h2>
  <a href="/portal/van/nova.php" class="btn btn-primary">+ Nova missão</a>
</div>

<?php if (isset($_GET['ok'])): ?>
  <div class="alerta alerta-ok">Missão salva com sucesso.</div>
<?php endif; ?>

<?php if (empty($todas)): ?>
  <div class="form-wrap" style="text-align:center;padding:48px 20px;margin-top:16px">
    <p style="color:var(--muted);margin-bottom:16px">Nenhuma missão registrada ainda.</p>
    <a href="/portal/van/nova.php" class="btn btn-primary">Agendar primeira missão</a>
  </div>
<?php else: ?>

  <?php
  $grupos = [];
  if ($agendadas) $grupos[] = ['titulo' => 'Próximas missões', 'icone' => '📅', 'itens' => $agendadas];
  if ($historico)  $grupos[] = ['titulo' => 'Histórico',        'icone' => '📋', 'itens' => $historico];

  foreach ($grupos as $grupo):
  ?>

  <div class="grupo-titulo"><?= $grupo['icone'] ?> <?= $grupo['titulo'] ?></div>

  <!-- Cards mobile -->
  <div class="van-cards">
    <?php foreach ($grupo['itens'] as $v):
      $sc = $status_cfg[$v['status']] ?? $status_cfg['agendada'];
    ?>
    <div class="form-wrap" style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:6px">
        <strong><?= htmlspecialchars($v['destino']) ?></strong>
        <?= badge_status($sc) ?>
      </div>
      <div style="font-size:.82rem;color:var(--muted);margin-bottom:2px"><?= htmlspecialchars($v['data_texto']) ?></div>
      <div style="font-size:.82rem;margin-bottom:10px">
        Motorista: <?= htmlspecialchars($v['motorista_nome'] ?? '—') ?>
        <?php if ($v['coordenador_nome']): ?> &bull; Coord: <?= htmlspecialchars($v['coordenador_nome']) ?><?php endif; ?>
        &bull; <?= $v['total_pass'] ?> passageiro<?= $v['total_pass'] != 1 ? 's' : '' ?>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="/portal/van/nova.php?id=<?= $v['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
        <a href="/portal/van/imprimir.php?id=<?= $v['id'] ?>" target="_blank" class="btn btn-ghost btn-sm">Imprimir</a>
        <form method="post" onsubmit="return confirm('Excluir esta missão?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="excluir">
          <input type="hidden" name="id" value="<?= $v['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tabela desktop -->
  <div class="tabela-wrap van-tabela" style="margin-bottom:8px">
    <table>
      <thead>
        <tr>
          <th>Destino</th>
          <th>Data</th>
          <th>Motorista</th>
          <th>Coordenador</th>
          <th style="text-align:center">Pass.</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($grupo['itens'] as $v):
          $sc = $status_cfg[$v['status']] ?? $status_cfg['agendada'];
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($v['destino']) ?></strong></td>
          <td style="white-space:nowrap"><?= htmlspecialchars($v['data_texto']) ?></td>
          <td><?= htmlspecialchars($v['motorista_nome'] ?? '—') ?></td>
          <td><?= htmlspecialchars($v['coordenador_nome'] ?? '—') ?></td>
          <td style="text-align:center"><?= $v['total_pass'] ?></td>
          <td><?= badge_status($sc) ?></td>
          <td>
            <div style="display:flex;gap:6px;white-space:nowrap">
              <a href="/portal/van/nova.php?id=<?= $v['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
              <a href="/portal/van/imprimir.php?id=<?= $v['id'] ?>" target="_blank" class="btn btn-ghost btn-sm">Imprimir</a>
              <form method="post" onsubmit="return confirm('Excluir esta missão?')" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
