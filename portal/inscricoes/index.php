<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Inscrições';
$pagina_ativa = 'inscricoes';

$totais = db()->query("SELECT
    COUNT(*) AS total,
    SUM(status = 'confirmado') AS confirmados,
    SUM(status = 'pendente')   AS pendentes,
    SUM(status = 'checkin')    AS checkins
  FROM inscricoes")->fetch();

$eventos = db()->query("
    SELECT e.id, e.titulo, e.data_evento, e.ativo, e.inscricoes_abertas, e.vagas,
           COUNT(i.id)                      AS total,
           SUM(i.status = 'confirmado')     AS confirmados,
           SUM(i.status = 'pendente')       AS pendentes,
           SUM(i.status = 'cancelado')      AS cancelados,
           SUM(i.status = 'checkin')        AS checkins,
           SUM(CASE WHEN i.status != 'cancelado' THEN i.valor_pago ELSE 0 END) AS receita
    FROM eventos e
    LEFT JOIN inscricoes i ON i.evento_id = e.id
    WHERE e.ativo = 1
    GROUP BY e.id
    ORDER BY e.inscricoes_abertas DESC, e.data_evento DESC, e.id DESC
")->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<div class="cards" style="margin-bottom:28px">
  <div class="card-stat">
    <h3>Total de inscrições</h3>
    <div class="val"><?= (int)$totais['total'] ?></div>
  </div>
  <div class="card-stat verde">
    <h3>Confirmadas</h3>
    <div class="val"><?= (int)$totais['confirmados'] ?></div>
  </div>
  <div class="card-stat ouro">
    <h3>Pendentes</h3>
    <div class="val"><?= (int)$totais['pendentes'] ?></div>
  </div>
  <div class="card-stat">
    <h3>Check-ins</h3>
    <div class="val"><?= (int)$totais['checkins'] ?></div>
  </div>
</div>

<div class="tabela-wrap">
  <div class="tabela-header">
    <h2>Inscrições por evento</h2>
  </div>

  <?php if (empty($eventos)): ?>
  <div style="padding:48px;text-align:center;color:var(--cinza3)">
    Nenhum evento ativo. Cadastre um evento em
    <a href="/portal/eventos/" style="color:var(--azul2)">Próx. Eventos</a>.
  </div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Evento</th>
        <th>Data</th>
        <th style="text-align:center">Total</th>
        <th style="text-align:center">Confirmados</th>
        <th style="text-align:center">Pendentes</th>
        <th style="text-align:center">Check-ins</th>
        <th style="text-align:right">Receita</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($eventos as $ev): ?>
    <tr>
      <td>
        <strong><?= htmlspecialchars($ev['titulo']) ?></strong>
        <?php if ($ev['inscricoes_abertas']): ?>
          <span style="margin-left:6px;font-size:.72rem;color:var(--verde);background:#dcfce7;padding:2px 9px;border-radius:20px">● aberto</span>
        <?php else: ?>
          <span style="margin-left:6px;font-size:.72rem;color:var(--cinza3);background:var(--cinza2);padding:2px 9px;border-radius:20px">encerrado</span>
        <?php endif; ?>
      </td>
      <td style="font-size:.83rem;color:var(--cinza3)">
        <?= $ev['data_evento'] ? date('d/m/Y', strtotime($ev['data_evento'])) : '—' ?>
      </td>
      <td style="text-align:center;font-weight:700"><?= (int)$ev['total'] ?><?= $ev['vagas'] ? '<small style="color:var(--cinza3);font-weight:400">/'.($ev['vagas']).'</small>' : '' ?></td>
      <td style="text-align:center;color:var(--verde);font-weight:600"><?= (int)$ev['confirmados'] ?></td>
      <td style="text-align:center;color:#b45309;font-weight:600"><?= (int)$ev['pendentes'] ?></td>
      <td style="text-align:center;color:var(--azul2);font-weight:600"><?= (int)$ev['checkins'] ?></td>
      <td style="text-align:right;font-size:.88rem;color:var(--cinza3)">
        <?= $ev['receita'] > 0 ? 'R$ ' . number_format($ev['receita'], 2, ',', '.') : '—' ?>
      </td>
      <td style="white-space:nowrap">
        <a href="/portal/inscricoes/configurar.php?id=<?= $ev['id'] ?>" class="btn btn-ouro btn-sm" style="margin-right:4px">Configurar</a>
        <a href="/portal/inscricoes/evento.php?id=<?= $ev['id'] ?>" class="btn btn-primary btn-sm">Inscritos</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
