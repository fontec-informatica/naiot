<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$campanha_id = (int)($_GET['id'] ?? 0);
if (!$campanha_id) { header('Location: /portal/ingressos/'); exit; }

$c_stmt = db()->prepare('SELECT * FROM ingressos_campanhas WHERE id = ?');
$c_stmt->execute([$campanha_id]);
$campanha = $c_stmt->fetch();
if (!$campanha) { header('Location: /portal/ingressos/'); exit; }

$titulo       = 'Posição de Ingressos — ' . $campanha['nome'];
$pagina_ativa = 'ingressos';
$ing_secao    = 'posicao';
$ing_campanha = $campanha;

$sql = "
    SELECT
        servo_nome,
        COUNT(*) AS total_distribuido,
        SUM(status='pago')        AS qtd_pago,
        SUM(status='distribuido') AS qtd_a_pagar,
        SUM(status='devolvido')   AS qtd_devolvido,
        SUM(CASE WHEN status='pago'        THEN COALESCE(valor_pago,valor) ELSE 0 END) AS valor_pago,
        SUM(CASE WHEN status='distribuido' THEN valor ELSE 0 END)                       AS valor_a_pagar
    FROM ingressos_tickets
    WHERE campanha_id = ? AND servo_nome IS NOT NULL
    GROUP BY servo_nome
    ORDER BY (SUM(status='distribuido') > 0) DESC, servo_nome ASC
";
$stmt = db()->prepare($sql);
$stmt->execute([$campanha_id]);
$posicoes = $stmt->fetchAll();

function csv_seguro(?string $v): string {
    $v = (string)($v ?? '');
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

if (isset($_GET['exportar'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="posicao_ingressos_' . $campanha_id . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Servo', 'Distribuídos', 'Pagos (qtd)', 'Pagos (R$)', 'A pagar (qtd)', 'A pagar (R$)', 'Devolvidos'], ';');
    foreach ($posicoes as $p) {
        fputcsv($out, [
            csv_seguro($p['servo_nome']),
            $p['total_distribuido'],
            $p['qtd_pago'],
            number_format($p['valor_pago'], 2, ',', ''),
            $p['qtd_a_pagar'],
            number_format($p['valor_a_pagar'], 2, ',', ''),
            $p['qtd_devolvido'],
        ], ';');
    }
    fclose($out);
    exit;
}

$tot_distribuido = array_sum(array_column($posicoes, 'total_distribuido'));
$tot_pago_qtd     = array_sum(array_column($posicoes, 'qtd_pago'));
$tot_apagar_qtd   = array_sum(array_column($posicoes, 'qtd_a_pagar'));
$tot_devolvido    = array_sum(array_column($posicoes, 'qtd_devolvido'));
$tot_pago_val     = array_sum(array_column($posicoes, 'valor_pago'));
$tot_apagar_val   = array_sum(array_column($posicoes, 'valor_a_pagar'));

include dirname(__DIR__) . '/_layout.php';
?>

<?php include __DIR__ . '/_subnav.php'; ?>

<div class="cards" style="margin-bottom:24px">
  <div class="card-stat"><h3>Distribuídos</h3><div class="val"><?= (int)$tot_distribuido ?></div></div>
  <div class="card-stat verde"><h3>Pagos</h3><div class="val"><?= (int)$tot_pago_qtd ?></div></div>
  <div class="card-stat ouro"><h3>A pagar</h3><div class="val"><?= (int)$tot_apagar_qtd ?></div></div>
  <div class="card-stat"><h3>Devolvidos</h3><div class="val"><?= (int)$tot_devolvido ?></div></div>
</div>

<div class="tabela-wrap">
  <div class="tabela-header" style="flex-wrap:wrap;gap:10px">
    <h2>Posição — semana do evento</h2>
    <a href="?id=<?= $campanha_id ?>&exportar=1" class="btn btn-ghost btn-sm" style="margin-left:auto">⬇ Exportar CSV</a>
  </div>

  <?php if (empty($posicoes)): ?>
  <div style="padding:40px;text-align:center;color:var(--cinza3)">Nenhum ingresso distribuído ainda.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Servo</th>
        <th style="text-align:center">Distribuídos</th>
        <th style="text-align:center">Pagos</th>
        <th style="text-align:right">Valor pago</th>
        <th style="text-align:center">A pagar</th>
        <th style="text-align:right">Valor a pagar</th>
        <th style="text-align:center">Devolvidos</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($posicoes as $p): ?>
    <tr>
      <td><strong><?= htmlspecialchars($p['servo_nome']) ?></strong></td>
      <td style="text-align:center"><?= (int)$p['total_distribuido'] ?></td>
      <td style="text-align:center;color:var(--verde);font-weight:600"><?= (int)$p['qtd_pago'] ?></td>
      <td style="text-align:right;font-weight:600">R$ <?= number_format($p['valor_pago'], 2, ',', '.') ?></td>
      <td style="text-align:center;<?= $p['qtd_a_pagar'] > 0 ? 'color:#b45309;font-weight:700' : 'color:var(--cinza3)' ?>"><?= (int)$p['qtd_a_pagar'] ?></td>
      <td style="text-align:right;font-weight:600;<?= $p['valor_a_pagar'] > 0 ? 'color:#b45309' : '' ?>">R$ <?= number_format($p['valor_a_pagar'], 2, ',', '.') ?></td>
      <td style="text-align:center;color:var(--cinza3)"><?= (int)$p['qtd_devolvido'] ?></td>
      <td><a href="/portal/ingressos/acerto.php?id=<?= $campanha_id ?>&servo=<?= urlencode($p['servo_nome']) ?>" class="btn btn-ghost btn-sm">Acertar</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:700;background:var(--cinza1)">
        <td>Total</td>
        <td style="text-align:center"><?= (int)$tot_distribuido ?></td>
        <td style="text-align:center"><?= (int)$tot_pago_qtd ?></td>
        <td style="text-align:right">R$ <?= number_format($tot_pago_val, 2, ',', '.') ?></td>
        <td style="text-align:center"><?= (int)$tot_apagar_qtd ?></td>
        <td style="text-align:right">R$ <?= number_format($tot_apagar_val, 2, ',', '.') ?></td>
        <td style="text-align:center"><?= (int)$tot_devolvido ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
