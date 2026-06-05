<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Viagens de Van';
$pagina_ativa = 'van';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    if (($_POST['acao'] ?? '') === 'excluir') {
        $vid = (int)$_POST['id'];
        db()->prepare("DELETE FROM van_viagens WHERE id=?")->execute([$vid]);
        header('Location: /portal/van/'); exit;
    }
}

try {
    $viagens = db()->query("
        SELECT v.*, COUNT(p.id) AS total_pass
        FROM van_viagens v
        LEFT JOIN van_passageiros p ON p.viagem_id = v.id
        GROUP BY v.id
        ORDER BY v.criado_em DESC
    ")->fetchAll();
    $tabela_ok = true;
} catch (PDOException $e) {
    $viagens = [];
    $tabela_ok = false;
}

include dirname(__DIR__) . '/_layout.php';
?>

<div style="max-width:960px">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <h2 style="margin:0">Viagens de Van</h2>
    <a href="/portal/van/nova.php" class="btn btn-primary">+ Nova viagem</a>
  </div>

  <?php if (!$tabela_ok): ?>
    <div class="alerta alerta-erro">
      Tabelas não encontradas. <a href="/portal/van/setup.php">Clique aqui para criar as tabelas</a>.
    </div>
  <?php elseif (isset($_GET['ok'])): ?>
    <div class="alerta alerta-ok">Viagem salva com sucesso.</div>
  <?php endif; ?>

  <?php if ($tabela_ok && empty($viagens)): ?>
    <div class="form-wrap" style="text-align:center;padding:48px 20px">
      <p style="color:var(--muted);margin-bottom:16px">Nenhuma viagem registrada ainda.</p>
      <a href="/portal/van/nova.php" class="btn btn-primary">Registrar primeira viagem</a>
    </div>
  <?php elseif ($tabela_ok): ?>

    <!-- Mobile: cards -->
    <div class="van-cards" style="display:none">
      <?php foreach ($viagens as $v): ?>
      <div class="form-wrap" style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
          <strong><?= htmlspecialchars($v['destino']) ?></strong>
          <span style="
            display:inline-block;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;
            background:<?= $v['status'] === 'finalizada' ? 'var(--green-lt)' : '#f5f0e0' ?>;
            color:<?= $v['status'] === 'finalizada' ? 'var(--green-dk)' : '#a87d28' ?>">
            <?= $v['status'] === 'finalizada' ? 'Finalizada' : 'Rascunho' ?>
          </span>
        </div>
        <div style="font-size:.82rem;color:var(--muted);margin-bottom:4px"><?= htmlspecialchars($v['data_texto']) ?></div>
        <div style="font-size:.82rem;margin-bottom:10px">
          Motorista: <?= htmlspecialchars($v['motorista_nome'] ?? '—') ?> &bull;
          <?= $v['total_pass'] ?> passageiro<?= $v['total_pass'] != 1 ? 's' : '' ?>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <a href="/portal/van/nova.php?id=<?= $v['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
          <a href="/portal/van/imprimir.php?id=<?= $v['id'] ?>" target="_blank" class="btn btn-ghost btn-sm">Imprimir</a>
          <form method="post" onsubmit="return confirm('Excluir esta viagem?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="excluir">
            <input type="hidden" name="id" value="<?= $v['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Desktop: tabela -->
    <div class="tabela-wrap van-tabela">
      <table>
        <thead>
          <tr>
            <th>Destino</th>
            <th>Data</th>
            <th>Motorista</th>
            <th style="text-align:center">Passageiros</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($viagens as $v): ?>
          <tr>
            <td><strong><?= htmlspecialchars($v['destino']) ?></strong></td>
            <td><?= htmlspecialchars($v['data_texto']) ?></td>
            <td><?= htmlspecialchars($v['motorista_nome'] ?? '—') ?></td>
            <td style="text-align:center"><?= $v['total_pass'] ?></td>
            <td>
              <span style="
                display:inline-block;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;
                background:<?= $v['status'] === 'finalizada' ? 'var(--green-lt)' : '#f5f0e0' ?>;
                color:<?= $v['status'] === 'finalizada' ? 'var(--green-dk)' : '#a87d28' ?>">
                <?= $v['status'] === 'finalizada' ? 'Finalizada' : 'Rascunho' ?>
              </span>
            </td>
            <td>
              <div style="display:flex;gap:6px;white-space:nowrap">
                <a href="/portal/van/nova.php?id=<?= $v['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
                <a href="/portal/van/imprimir.php?id=<?= $v['id'] ?>" target="_blank" class="btn btn-ghost btn-sm">Imprimir</a>
                <form method="post" onsubmit="return confirm('Excluir esta viagem?')" style="display:inline">
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

  <?php endif; ?>
</div>

<style>
@media (max-width: 680px) {
  .van-tabela { display: none !important; }
  .van-cards  { display: block !important; }
}
</style>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
