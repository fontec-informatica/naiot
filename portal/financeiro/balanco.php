<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin','financeiro']);

$titulo = 'Balanço';
$pagina_ativa = 'financeiro';

$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));
$mes = max(1,min(12,$mes)); $ano = max(2020,min(2099,$ano));

$mes_ant = $mes-1; $ano_ant = $ano; if($mes_ant<=0){$mes_ant=12;$ano_ant--;}
$mes_prox= $mes+1; $ano_prox= $ano; if($mes_prox>12){$mes_prox=1;$ano_prox++;}

$meses_nomes = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$fmt = fn($v) => 'R$ ' . number_format($v,2,',','.');

// Lançamentos do mês (exceto cancelado)
$st = db()->prepare("
    SELECT l.tipo, l.valor, l.status, l.categoria_id, c.nome as cat_nome, c.cor as cat_cor, c.tipo as cat_tipo,
           l.forma_pagamento, l.origem
    FROM financeiro_lancamentos l
    LEFT JOIN financeiro_categorias c ON c.id=l.categoria_id
    WHERE l.competencia_mes=? AND l.competencia_ano=? AND l.status != 'cancelado'
    ORDER BY l.tipo, c.nome
");
$st->execute([$mes,$ano]);
$lancamentos = $st->fetchAll();

// Totais gerais
$total_rec  = array_sum(array_column(array_filter($lancamentos, fn($r) => $r['tipo']==='receita'), 'valor'));
$total_desp = array_sum(array_column(array_filter($lancamentos, fn($r) => $r['tipo']==='despesa'), 'valor'));
$saldo = $total_rec - $total_desp;

// Receitas por categoria
$rec_por_cat = [];
foreach ($lancamentos as $l) {
    if ($l['tipo'] !== 'receita') continue;
    $k = $l['categoria_id'];
    if (!isset($rec_por_cat[$k])) $rec_por_cat[$k] = ['nome'=>$l['cat_nome']??'Sem categoria','cor'=>$l['cat_cor']??'#999','total'=>0];
    $rec_por_cat[$k]['total'] += $l['valor'];
}
usort($rec_por_cat, fn($a,$b) => $b['total']<=>$a['total']);

// Despesas por categoria
$desp_por_cat = [];
foreach ($lancamentos as $l) {
    if ($l['tipo'] !== 'despesa') continue;
    $k = $l['categoria_id'];
    if (!isset($desp_por_cat[$k])) $desp_por_cat[$k] = ['nome'=>$l['cat_nome']??'Sem categoria','cor'=>$l['cat_cor']??'#999','total'=>0];
    $desp_por_cat[$k]['total'] += $l['valor'];
}
usort($desp_por_cat, fn($a,$b) => $b['total']<=>$a['total']);

// Receitas por origem
$rec_por_origem = [];
foreach ($lancamentos as $l) {
    if ($l['tipo'] !== 'receita') continue;
    $orig = $l['origem'] ?: 'outro';
    $rec_por_origem[$orig] = ($rec_por_origem[$orig] ?? 0) + $l['valor'];
}
arsort($rec_por_origem);

// Despesas por forma de pagamento
$desp_por_forma = [];
foreach ($lancamentos as $l) {
    if ($l['tipo'] !== 'despesa') continue;
    $f = $l['forma_pagamento'] ?: 'outro';
    $desp_por_forma[$f] = ($desp_por_forma[$f] ?? 0) + $l['valor'];
}
arsort($desp_por_forma);

// Pendentes do mês
$pend_rec  = 0; $pend_desp = 0;
$st2 = db()->prepare("SELECT tipo, SUM(valor) as total FROM financeiro_lancamentos WHERE competencia_mes=? AND competencia_ano=? AND status='pendente' GROUP BY tipo");
$st2->execute([$mes,$ano]);
foreach ($st2->fetchAll() as $p) {
    if ($p['tipo']==='receita') $pend_rec = $p['total'];
    else $pend_desp = $p['total'];
}

// Evolução 6 meses (para gráfico)
$evolucao = [];
for ($i=5; $i>=0; $i--) {
    $m = $mes - $i; $y = $ano;
    while($m<=0){$m+=12;$y--;}
    $st3 = db()->prepare("SELECT tipo, SUM(valor) as total FROM financeiro_lancamentos WHERE competencia_mes=? AND competencia_ano=? AND status!='cancelado' GROUP BY tipo");
    $st3->execute([$m,$y]);
    $row = ['mes'=>$m,'ano'=>$y,'rec'=>0,'desp'=>0,'label'=>mb_substr($meses_nomes[$m],0,3).'/'.$y];
    foreach ($st3->fetchAll() as $r) {
        if ($r['tipo']==='receita') $row['rec']=$r['total'];
        else $row['desp']=$r['total'];
    }
    $evolucao[] = $row;
}
$max_ev = max(1, max(array_merge(array_column($evolucao,'rec'),array_column($evolucao,'desp'))));

include dirname(__DIR__) . '/_layout.php';
?>
<style>
.fin-nav{display:flex;align-items:center;gap:8px;margin-bottom:24px;flex-wrap:wrap}
.fin-nav a,.fin-nav span{padding:6px 14px;border-radius:7px;font-size:.78rem;font-weight:600;text-decoration:none;border:1.5px solid var(--border);color:var(--text);background:#fff;transition:.15s}
.fin-nav a:hover{border-color:var(--green);color:var(--green)}
.fin-nav span{background:var(--green-dk);color:#fff;border-color:var(--green-dk)}
.periodo{display:flex;align-items:center;gap:8px;margin-bottom:24px}
.periodo a{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1.5px solid var(--border);color:var(--muted);text-decoration:none;transition:.15s}
.periodo a:hover{border-color:var(--green);color:var(--green)}
.periodo strong{font-family:'Cinzel',serif;font-size:.88rem;font-weight:700;color:var(--green-dk);min-width:160px;text-align:center}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px}
.kpi{background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px 20px}
.kpi .kpi-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:6px}
.kpi .kpi-val{font-family:'Cinzel',serif;font-size:1.25rem;font-weight:700}
.kpi .kpi-sub{font-size:.7rem;color:var(--muted);margin-top:4px}
.bal-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
@media(max-width:720px){.bal-grid{grid-template-columns:1fr}}
.hbar-row{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.hbar-label{width:140px;font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0}
.hbar-track{flex:1;height:12px;background:var(--off);border-radius:6px;overflow:hidden}
.hbar-fill{height:100%;border-radius:6px;transition:.3s}
.hbar-val{font-size:.75rem;font-weight:700;white-space:nowrap;width:90px;text-align:right}
.ev-chart{display:flex;align-items:flex-end;gap:6px;height:120px;padding:0 4px}
.ev-col{display:flex;flex-direction:column;align-items:center;gap:2px;flex:1}
.ev-bars{display:flex;gap:2px;align-items:flex-end;width:100%;height:100px}
.ev-bar{flex:1;border-radius:3px 3px 0 0;min-height:2px;transition:.3s}
.ev-bar.rec{background:#16a34a}
.ev-bar.desp{background:#dc2626}
.ev-col-label{font-size:.6rem;color:var(--muted);white-space:nowrap;margin-top:4px}
.dre-table{width:100%;border-collapse:collapse}
.dre-table th{text-align:left;padding:8px 12px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);background:var(--off);border-bottom:1px solid var(--border)}
.dre-table td{padding:9px 12px;font-size:.82rem;border-bottom:1px solid var(--border)}
.dre-table .dre-section{background:var(--off);font-family:'Cinzel',serif;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--green-dk)}
.dre-table .dre-total{font-weight:700;background:#f9f9f7}
.dre-table .dre-saldo{font-family:'Cinzel',serif;font-weight:700;font-size:.88rem;background:var(--off)}
.origem-labels{doacao:'Doação',dizimo:'Dízimo',contribuicao:'Contribuição',inscricao:'Inscrição',mensalidade:'Mensalidade',outro:'Outro'}
</style>

<div class="fin-nav">
  <a href="/portal/financeiro/">Dashboard</a>
  <div style="color:var(--border)">|</div>
  <a href="/portal/financeiro/lancamentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>">Lançamentos</a>
  <a href="/portal/financeiro/recorrentes.php">Recorrentes</a>
  <span>Balanço</span>
</div>

<div class="periodo">
  <a href="?mes=<?= $mes_ant ?>&ano=<?= $ano_ant ?>">‹</a>
  <strong><?= $meses_nomes[$mes] ?> <?= $ano ?></strong>
  <a href="?mes=<?= $mes_prox ?>&ano=<?= $ano_prox ?>">›</a>
</div>

<!-- KPIs -->
<div class="kpi-grid">
  <div class="kpi" style="border-top:3px solid #16a34a">
    <div class="kpi-label">Receitas realizadas</div>
    <div class="kpi-val" style="color:#15803d"><?= $fmt($total_rec) ?></div>
    <?php if ($pend_rec > 0): ?><div class="kpi-sub">+ <?= $fmt($pend_rec) ?> pendente</div><?php endif; ?>
  </div>
  <div class="kpi" style="border-top:3px solid #dc2626">
    <div class="kpi-label">Despesas realizadas</div>
    <div class="kpi-val" style="color:#dc2626"><?= $fmt($total_desp) ?></div>
    <?php if ($pend_desp > 0): ?><div class="kpi-sub">+ <?= $fmt($pend_desp) ?> pendente</div><?php endif; ?>
  </div>
  <div class="kpi" style="border-top:3px solid <?= $saldo>=0?'#16a34a':'#dc2626' ?>">
    <div class="kpi-label">Saldo do mês</div>
    <div class="kpi-val" style="color:<?= $saldo>=0?'#15803d':'#dc2626' ?>"><?= $fmt($saldo) ?></div>
    <?php if ($total_rec > 0): ?><div class="kpi-sub">Margem: <?= number_format($saldo/$total_rec*100,1,',','.') ?>%</div><?php endif; ?>
  </div>
  <div class="kpi" style="border-top:3px solid var(--gold)">
    <div class="kpi-label">Lançamentos</div>
    <div class="kpi-val" style="color:var(--gold)"><?= count($lancamentos) ?></div>
    <div class="kpi-sub"><?= count(array_filter($lancamentos,fn($r)=>$r['status']==='pendente')) ?> pendente(s)</div>
  </div>
</div>

<!-- Gráfico evolução -->
<div class="tabela-wrap" style="margin-bottom:20px">
  <div class="tabela-header"><h2>Evolução — últimos 6 meses</h2></div>
  <div style="padding:20px">
    <div style="display:flex;gap:16px;margin-bottom:12px">
      <span style="font-size:.72rem;display:flex;align-items:center;gap:5px"><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#16a34a"></span> Receitas</span>
      <span style="font-size:.72rem;display:flex;align-items:center;gap:5px"><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#dc2626"></span> Despesas</span>
    </div>
    <div class="ev-chart">
      <?php foreach ($evolucao as $ev): ?>
      <div class="ev-col">
        <div class="ev-bars">
          <div class="ev-bar rec" style="height:<?= round($ev['rec']/$max_ev*100) ?>%"
               title="Receitas: <?= $fmt($ev['rec']) ?>"></div>
          <div class="ev-bar desp" style="height:<?= round($ev['desp']/$max_ev*100) ?>%"
               title="Despesas: <?= $fmt($ev['desp']) ?>"></div>
        </div>
        <span class="ev-col-label"><?= $ev['label'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Breakdown receitas / despesas -->
<div class="bal-grid">
  <!-- Receitas por categoria -->
  <div class="tabela-wrap">
    <div class="tabela-header"><h2>Receitas por categoria</h2></div>
    <div style="padding:16px 20px">
      <?php if (empty($rec_por_cat)): ?>
        <p style="color:var(--muted);font-size:.82rem">Nenhuma receita no período.</p>
      <?php else: ?>
        <?php foreach ($rec_por_cat as $c): $pct = $total_rec>0?$c['total']/$total_rec*100:0; ?>
        <div class="hbar-row">
          <div class="hbar-label" title="<?= htmlspecialchars($c['nome']) ?>">
            <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?= htmlspecialchars($c['cor']) ?>;margin-right:5px;vertical-align:middle"></span>
            <?= htmlspecialchars($c['nome']) ?>
          </div>
          <div class="hbar-track"><div class="hbar-fill" style="width:<?= round($pct) ?>%;background:<?= htmlspecialchars($c['cor']) ?>"></div></div>
          <div class="hbar-val" style="color:#15803d"><?= $fmt($c['total']) ?></div>
        </div>
        <?php endforeach; ?>
        <div style="text-align:right;font-size:.7rem;color:var(--muted);margin-top:8px;border-top:1px solid var(--border);padding-top:8px">
          Total: <strong style="color:#15803d"><?= $fmt($total_rec) ?></strong>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Despesas por categoria -->
  <div class="tabela-wrap">
    <div class="tabela-header"><h2>Despesas por categoria</h2></div>
    <div style="padding:16px 20px">
      <?php if (empty($desp_por_cat)): ?>
        <p style="color:var(--muted);font-size:.82rem">Nenhuma despesa no período.</p>
      <?php else: ?>
        <?php foreach ($desp_por_cat as $c): $pct = $total_desp>0?$c['total']/$total_desp*100:0; ?>
        <div class="hbar-row">
          <div class="hbar-label" title="<?= htmlspecialchars($c['nome']) ?>">
            <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?= htmlspecialchars($c['cor']) ?>;margin-right:5px;vertical-align:middle"></span>
            <?= htmlspecialchars($c['nome']) ?>
          </div>
          <div class="hbar-track"><div class="hbar-fill" style="width:<?= round($pct) ?>%;background:<?= htmlspecialchars($c['cor']) ?>"></div></div>
          <div class="hbar-val" style="color:#dc2626"><?= $fmt($c['total']) ?></div>
        </div>
        <?php endforeach; ?>
        <div style="text-align:right;font-size:.7rem;color:var(--muted);margin-top:8px;border-top:1px solid var(--border);padding-top:8px">
          Total: <strong style="color:#dc2626"><?= $fmt($total_desp) ?></strong>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Receitas por origem / Despesas por forma -->
<div class="bal-grid" style="margin-bottom:24px">
  <div class="tabela-wrap">
    <div class="tabela-header"><h2>Receitas por origem</h2></div>
    <div style="padding:16px 20px">
      <?php if (empty($rec_por_origem)): ?>
        <p style="color:var(--muted);font-size:.82rem">Nenhuma receita no período.</p>
      <?php else:
        $orig_labels = ['doacao'=>'Doação','dizimo'=>'Dízimo','contribuicao'=>'Contribuição','inscricao'=>'Inscrição em evento','mensalidade'=>'Mensalidade','outro'=>'Outro'];
        foreach ($rec_por_origem as $orig => $val): $pct = $total_rec>0?$val/$total_rec*100:0; ?>
        <div class="hbar-row">
          <div class="hbar-label"><?= $orig_labels[$orig] ?? ucfirst($orig) ?></div>
          <div class="hbar-track"><div class="hbar-fill" style="width:<?= round($pct) ?>%;background:#16a34a"></div></div>
          <div class="hbar-val" style="color:#15803d"><?= $fmt($val) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="tabela-wrap">
    <div class="tabela-header"><h2>Despesas por forma de pagamento</h2></div>
    <div style="padding:16px 20px">
      <?php if (empty($desp_por_forma)): ?>
        <p style="color:var(--muted);font-size:.82rem">Nenhuma despesa no período.</p>
      <?php else:
        $forma_labels = ['dinheiro'=>'Dinheiro','pix'=>'PIX','transferencia'=>'Transferência','boleto'=>'Boleto','cartao'=>'Cartão','cheque'=>'Cheque','outro'=>'Outro'];
        foreach ($desp_por_forma as $f => $val): $pct = $total_desp>0?$val/$total_desp*100:0; ?>
        <div class="hbar-row">
          <div class="hbar-label"><?= $forma_labels[$f] ?? ucfirst($f) ?></div>
          <div class="hbar-track"><div class="hbar-fill" style="width:<?= round($pct) ?>%;background:#7c3aed"></div></div>
          <div class="hbar-val" style="color:#7c3aed"><?= $fmt($val) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- DRE Gerencial -->
<div class="tabela-wrap">
  <div class="tabela-header">
    <h2>DRE Gerencial — <?= $meses_nomes[$mes] ?> <?= $ano ?></h2>
    <div style="margin-left:auto;display:flex;gap:8px">
      <a href="/portal/financeiro/exportar.php?exp=balanco&mes=<?= $mes ?>&ano=<?= $ano ?>"
         class="btn btn-ghost btn-sm" title="Exportar balanço completo para Excel">📊 Exportar Excel</a>
      <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨️ Imprimir</button>
    </div>
  </div>
  <table class="dre-table">
    <tbody>
      <!-- RECEITAS -->
      <tr><td colspan="3" class="dre-section">Receitas</td></tr>
      <?php foreach ($rec_por_cat as $c): ?>
      <tr>
        <td style="padding-left:24px">
          <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?= htmlspecialchars($c['cor']) ?>;margin-right:8px;vertical-align:middle"></span>
          <?= htmlspecialchars($c['nome']) ?>
        </td>
        <td style="text-align:right;color:#15803d;font-weight:600"><?= $fmt($c['total']) ?></td>
        <td style="text-align:right;color:var(--muted);font-size:.72rem"><?= $total_rec>0?number_format($c['total']/$total_rec*100,1,',','.').'%':'-' ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="dre-total">
        <td><strong>Total receitas</strong></td>
        <td style="text-align:right;color:#15803d"><strong><?= $fmt($total_rec) ?></strong></td>
        <td style="text-align:right;font-size:.72rem;color:var(--muted)">100%</td>
      </tr>

      <!-- DESPESAS -->
      <tr><td colspan="3" class="dre-section" style="padding-top:12px">Despesas</td></tr>
      <?php foreach ($desp_por_cat as $c): ?>
      <tr>
        <td style="padding-left:24px">
          <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?= htmlspecialchars($c['cor']) ?>;margin-right:8px;vertical-align:middle"></span>
          <?= htmlspecialchars($c['nome']) ?>
        </td>
        <td style="text-align:right;color:#dc2626;font-weight:600"><?= $fmt($c['total']) ?></td>
        <td style="text-align:right;color:var(--muted);font-size:.72rem"><?= $total_desp>0?number_format($c['total']/$total_desp*100,1,',','.').'%':'-' ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="dre-total">
        <td><strong>Total despesas</strong></td>
        <td style="text-align:right;color:#dc2626"><strong><?= $fmt($total_desp) ?></strong></td>
        <td style="text-align:right;font-size:.72rem;color:var(--muted)">100%</td>
      </tr>

      <!-- SALDO -->
      <tr class="dre-saldo">
        <td><strong>Resultado do mês</strong></td>
        <td style="text-align:right;color:<?= $saldo>=0?'#15803d':'#dc2626' ?>">
          <strong><?= $fmt($saldo) ?></strong>
        </td>
        <td style="text-align:right;font-size:.72rem;color:var(--muted)">
          <?= $total_rec>0?number_format($saldo/$total_rec*100,1,',','.').'%':'-' ?>
        </td>
      </tr>

      <!-- Pendentes -->
      <?php if ($pend_rec > 0 || $pend_desp > 0): ?>
      <tr><td colspan="3" class="dre-section" style="padding-top:12px">Pendentes (não incluídos acima)</td></tr>
      <?php if ($pend_rec > 0): ?>
      <tr><td style="padding-left:24px;color:var(--muted)">Receitas pendentes</td>
          <td style="text-align:right;color:#854d0e;font-weight:600"><?= $fmt($pend_rec) ?></td><td></td></tr>
      <?php endif; ?>
      <?php if ($pend_desp > 0): ?>
      <tr><td style="padding-left:24px;color:var(--muted)">Despesas pendentes</td>
          <td style="text-align:right;color:#854d0e;font-weight:600"><?= $fmt($pend_desp) ?></td><td></td></tr>
      <?php endif; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<style>
@media print {
  .sidebar,.topbar,.fin-nav,.periodo,.kpi-grid,.bal-grid,.ev-chart,.tabela-wrap:not(:last-child) { display:none !important }
  .main-content { margin:0 !important; padding:0 !important }
  .dre-table { font-size:11pt }
}
</style>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
