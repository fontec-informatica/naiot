<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Inscrições';
$pagina_ativa = 'inscricoes';

$totais = db()->query("SELECT
    COUNT(*) AS total,
    SUM(status = 'confirmado') AS confirmados,
    SUM(status = 'pendente')   AS pendentes,
    SUM(status = 'checkin')    AS checkins,
    SUM(CASE WHEN status IN ('confirmado','checkin') THEN valor_pago ELSE 0 END) AS receita
  FROM inscricoes")->fetch();

$inscricoes_hoje = (int)db()->query("SELECT COUNT(*) FROM inscricoes WHERE DATE(created_at) = CURDATE()")->fetchColumn();

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
    GROUP BY e.id
    ORDER BY e.inscricoes_abertas DESC, e.data_evento DESC, e.id DESC
")->fetchAll();

$ultimas = db()->query("
    SELECT i.nome, i.email, i.status, i.created_at, e.titulo AS evento, i.evento_id
    FROM inscricoes i
    JOIN eventos e ON i.evento_id = e.id
    ORDER BY i.created_at DESC LIMIT 8
")->fetchAll();

$eventos_abertos = array_filter($eventos, fn($e) => $e['inscricoes_abertas']);

include dirname(__DIR__) . '/_layout.php';

$status_style = [
    'confirmado' => 'background:#dcfce7;color:#166534',
    'pendente'   => 'background:#fef9c3;color:#854d0e',
    'checkin'    => 'background:#dbeafe;color:#1e40af',
    'cancelado'  => 'background:#fee2e2;color:#991b1b',
];
$status_label = ['confirmado'=>'Confirmado','pendente'=>'Pendente','checkin'=>'Check-in','cancelado'=>'Cancelado'];
?>

<!-- KPIs -->
<div class="cards" style="margin-bottom:24px">
  <div class="card-stat">
    <h3>Total de inscrições</h3>
    <div class="val"><?= (int)$totais['total'] ?></div>
    <div class="val-sub"><?= $inscricoes_hoje ?> hoje</div>
  </div>
  <div class="card-stat verde">
    <h3>Confirmadas</h3>
    <div class="val"><?= (int)$totais['confirmados'] ?></div>
  </div>
  <div class="card-stat ouro">
    <h3>Pendentes</h3>
    <div class="val"><?= (int)$totais['pendentes'] ?></div>
    <div class="val-sub">aguardando confirmação</div>
  </div>
  <div class="card-stat">
    <h3>Check-ins</h3>
    <div class="val"><?= (int)$totais['checkins'] ?></div>
  </div>
  <?php if ((float)$totais['receita'] > 0): ?>
  <div class="card-stat" style="border-top-color:var(--gold)">
    <h3>Receita confirmada</h3>
    <div class="val" style="font-size:1.4rem;color:var(--gold)">R$&nbsp;<?= number_format($totais['receita'],2,',','.') ?></div>
  </div>
  <?php endif; ?>
</div>

<!-- Grid: Últimas inscrições + Eventos abertos -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,400px),1fr));gap:20px;margin-bottom:24px;align-items:start">

  <!-- Últimas inscrições -->
  <div class="tabela-wrap">
    <div class="tabela-header">
      <h2>Últimas inscrições</h2>
    </div>
    <?php if (empty($ultimas)): ?>
      <div style="padding:36px;text-align:center;color:var(--muted);font-size:.88rem">Nenhuma inscrição ainda.</div>
    <?php else: ?>
    <table>
      <thead><tr><th>Participante</th><th>Evento</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($ultimas as $ins): ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($ins['nome']) ?></div>
            <div style="font-size:.73rem;color:var(--muted)"><?= htmlspecialchars($ins['email']) ?></div>
          </td>
          <td style="font-size:.82rem;color:var(--muted)">
            <a href="/portal/inscricoes/evento.php?id=<?= $ins['evento_id'] ?>" style="color:inherit;font-size:.82rem;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px"><?= htmlspecialchars($ins['evento']) ?></a>
            <span style="font-size:.72rem"><?= date('d/m H:i', strtotime($ins['created_at'])) ?></span>
          </td>
          <td><span class="badge" style="<?= $status_style[$ins['status']] ?? '' ?>"><?= $status_label[$ins['status']] ?? $ins['status'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Eventos com inscrições abertas -->
  <div class="tabela-wrap">
    <div class="tabela-header">
      <h2>Inscrições abertas</h2>
      <a href="/portal/eventos/wizard.php" class="btn btn-primary btn-sm">+ Novo evento</a>
    </div>
    <?php if (empty($eventos_abertos)): ?>
      <div style="padding:36px;text-align:center;color:var(--muted);font-size:.88rem">Nenhum evento com inscrições abertas.</div>
    <?php else: ?>
    <table>
      <thead><tr><th>Evento</th><th style="text-align:center">Inscritos</th><th style="text-align:center">Pendentes</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($eventos_abertos as $ev): ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($ev['titulo']) ?></div>
            <div style="font-size:.76rem;color:var(--muted)"><?= $ev['data_evento'] ? date('d/m/Y', strtotime($ev['data_evento'])) : 'Sem data' ?></div>
          </td>
          <td style="text-align:center;font-weight:700">
            <?= (int)$ev['total'] ?><?= $ev['vagas'] ? '<span style="font-size:.76rem;color:var(--muted);font-weight:400"> /'.((int)$ev['vagas']).'</span>' : '' ?>
          </td>
          <td style="text-align:center;color:#b45309;font-weight:600"><?= (int)$ev['pendentes'] ?></td>
          <td><a href="/portal/inscricoes/evento.php?id=<?= $ev['id'] ?>" class="btn btn-primary btn-sm">Ver</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>

<!-- Tabela completa: todos os eventos -->
<div class="tabela-wrap">
  <div class="tabela-header">
    <h2>Todos os eventos</h2>
    <a href="/portal/eventos/wizard.php" class="btn btn-ghost btn-sm">+ Novo evento</a>
  </div>

  <?php if (empty($eventos)): ?>
  <div style="padding:48px;text-align:center;color:var(--cinza3)">
    Nenhum evento cadastrado ainda.
    <a href="/portal/eventos/wizard.php" style="color:var(--azul2)">Cadastrar agora</a>
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
        <?php if (!$ev['ativo']): ?>
          <span style="margin-left:4px;font-size:.72rem;color:#92400e;background:#fef3c7;padding:2px 9px;border-radius:20px">fora do carrossel</span>
        <?php endif; ?>
      </td>
      <td style="font-size:.83rem;color:var(--cinza3)"><?= $ev['data_evento'] ? date('d/m/Y', strtotime($ev['data_evento'])) : '—' ?></td>
      <td style="text-align:center;font-weight:700"><?= (int)$ev['total'] ?><?= $ev['vagas'] ? '<small style="color:var(--cinza3);font-weight:400">/'.((int)$ev['vagas']).'</small>' : '' ?></td>
      <td style="text-align:center;color:var(--verde);font-weight:600"><?= (int)$ev['confirmados'] ?></td>
      <td style="text-align:center;color:#b45309;font-weight:600"><?= (int)$ev['pendentes'] ?></td>
      <td style="text-align:center;color:var(--azul2);font-weight:600"><?= (int)$ev['checkins'] ?></td>
      <td style="text-align:right;font-size:.88rem;color:var(--cinza3)"><?= $ev['receita'] > 0 ? 'R$ ' . number_format($ev['receita'], 2, ',', '.') : '—' ?></td>
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
