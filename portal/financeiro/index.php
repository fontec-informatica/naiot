<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin','financeiro']);

$titulo = 'Financeiro';
$pagina_ativa = 'financeiro';

$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));
$mes = max(1, min(12, $mes));
$ano = max(2020, min(2099, $ano));

$meses_nomes = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

try {
    $db = db();

    // KPIs do mês
    $r = $db->prepare("SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos WHERE tipo='receita' AND competencia_mes=? AND competencia_ano=? AND status!='cancelado'");
    $r->execute([$mes,$ano]); $receitas = (float)$r->fetchColumn();

    $r = $db->prepare("SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos WHERE tipo='despesa' AND competencia_mes=? AND competencia_ano=? AND status!='cancelado'");
    $r->execute([$mes,$ano]); $despesas = (float)$r->fetchColumn();

    $r = $db->prepare("SELECT COUNT(*) FROM financeiro_lancamentos WHERE status='pendente' AND competencia_mes=? AND competencia_ano=?");
    $r->execute([$mes,$ano]); $pendentes = (int)$r->fetchColumn();

    $r = $db->prepare("SELECT COALESCE(SUM(valor),0) FROM financeiro_lancamentos WHERE tipo='receita' AND status='pendente' AND competencia_mes=? AND competencia_ano=?");
    $r->execute([$mes,$ano]); $a_receber = (float)$r->fetchColumn();

    $saldo = $receitas - $despesas;

    // Últimos 6 meses para gráfico
    $grafico = [];
    for ($i = 5; $i >= 0; $i--) {
        $m = $mes - $i; $a = $ano;
        while ($m <= 0) { $m += 12; $a--; }
        $r = $db->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END),0) as rec, COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END),0) as desp FROM financeiro_lancamentos WHERE competencia_mes=? AND competencia_ano=? AND status!='cancelado'");
        $r->execute([$m, $a]);
        $row = $r->fetch();
        $grafico[] = ['mes' => $m, 'ano' => $a, 'label' => substr($meses_nomes[$m],0,3), 'rec' => (float)$row['rec'], 'desp' => (float)$row['desp']];
    }
    $max_grafico = max(1, max(array_merge(array_column($grafico,'rec'), array_column($grafico,'desp'))));

    // Últimos lançamentos
    $ultimos = $db->prepare("SELECT l.*, c.nome as cat_nome, c.cor as cat_cor FROM financeiro_lancamentos l LEFT JOIN financeiro_categorias c ON c.id=l.categoria_id WHERE l.competencia_mes=? AND l.competencia_ano=? ORDER BY l.data_lancamento DESC, l.id DESC LIMIT 8");
    $ultimos->execute([$mes,$ano]);
    $ultimos = $ultimos->fetchAll();

    // Recorrentes a vencer (próximos 7 dias)
    $hoje = date('Y-m-d');
    $sete = date('Y-m-d', strtotime('+7 days'));
    $vencimentos = $db->prepare("SELECT r.*, c.nome as cat_nome FROM financeiro_recorrentes r LEFT JOIN financeiro_categorias c ON c.id=r.categoria_id WHERE r.status='ativo' AND r.proximo_vencimento BETWEEN ? AND ? ORDER BY r.proximo_vencimento ASC");
    $vencimentos->execute([$hoje, $sete]);
    $vencimentos = $vencimentos->fetchAll();

    // Receitas por categoria (mês)
    $rec_cat = $db->prepare("SELECT c.nome, c.cor, SUM(l.valor) as total FROM financeiro_lancamentos l LEFT JOIN financeiro_categorias c ON c.id=l.categoria_id WHERE l.tipo='receita' AND l.competencia_mes=? AND l.competencia_ano=? AND l.status!='cancelado' GROUP BY l.categoria_id ORDER BY total DESC");
    $rec_cat->execute([$mes,$ano]); $rec_cat = $rec_cat->fetchAll();

    // Despesas por categoria (mês)
    $desp_cat = $db->prepare("SELECT c.nome, c.cor, SUM(l.valor) as total FROM financeiro_lancamentos l LEFT JOIN financeiro_categorias c ON c.id=l.categoria_id WHERE l.tipo='despesa' AND l.competencia_mes=? AND l.competencia_ano=? AND l.status!='cancelado' GROUP BY l.categoria_id ORDER BY total DESC");
    $desp_cat->execute([$mes,$ano]); $desp_cat = $desp_cat->fetchAll();

} catch (Exception $e) {
    $receitas = $despesas = $pendentes = $a_receber = $saldo = 0;
    $grafico = $ultimos = $vencimentos = $rec_cat = $desp_cat = [];
}

$fmt = fn($v) => 'R$&nbsp;' . number_format($v, 2, ',', '.');

$mes_ant = $mes - 1; $ano_ant = $ano;
if ($mes_ant <= 0) { $mes_ant = 12; $ano_ant--; }
$mes_prox = $mes + 1; $ano_prox = $ano;
if ($mes_prox > 12) { $mes_prox = 1; $ano_prox++; }

include dirname(__DIR__) . '/_layout.php';
?>

<style>
.fin-nav{display:flex;align-items:center;gap:8px;margin-bottom:24px;flex-wrap:wrap}
.fin-nav a,.fin-nav span{padding:6px 14px;border-radius:7px;font-size:.78rem;font-weight:600;text-decoration:none;border:1.5px solid var(--border);color:var(--text);background:#fff;transition:.15s}
.fin-nav a:hover{border-color:var(--green);color:var(--green)}
.fin-nav span{background:var(--green-dk);color:#fff;border-color:var(--green-dk)}
.fin-nav .sep{color:var(--border);font-size:1.1rem;padding:0}

.periodo{display:flex;align-items:center;gap:8px;margin-bottom:28px}
.periodo a{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1.5px solid var(--border);color:var(--muted);text-decoration:none;font-size:1rem;transition:.15s}
.periodo a:hover{border-color:var(--green);color:var(--green)}
.periodo strong{font-family:'Cinzel',serif;font-size:.9rem;font-weight:700;color:var(--green-dk);letter-spacing:.06em;min-width:160px;text-align:center}

.kpis{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:28px}
.kpi{background:#fff;border-radius:var(--rl);padding:22px 22px 16px;box-shadow:var(--sh-sm);border:1px solid var(--border);position:relative;overflow:hidden}
.kpi-label{font-family:'Cinzel',serif;font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:10px}
.kpi-val{font-size:1.7rem;font-weight:700;line-height:1;color:var(--green-dk)}
.kpi-sub{font-size:.72rem;color:var(--muted);margin-top:5px}
.kpi.receita{border-top:3px solid #16a34a}
.kpi.despesa{border-top:3px solid var(--red)}
.kpi.saldo-pos{border-top:3px solid var(--green)}
.kpi.saldo-neg{border-top:3px solid var(--red)}
.kpi.pendente{border-top:3px solid var(--gold)}

.fin-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
@media(max-width:860px){.fin-grid{grid-template-columns:1fr}}

.fin-card{background:#fff;border-radius:var(--rl);box-shadow:var(--sh-sm);border:1px solid var(--border);overflow:hidden}
.fin-card-head{padding:14px 20px;background:var(--off);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.fin-card-head h3{font-family:'Cinzel',serif;font-size:.78rem;font-weight:700;color:var(--green-dk);text-transform:uppercase;letter-spacing:.06em}
.fin-card-body{padding:16px 20px}

/* Gráfico */
.chart-wrap{display:flex;gap:6px;align-items:flex-end;height:120px;padding:0 4px}
.chart-col{display:flex;flex-direction:column;align-items:center;gap:2px;flex:1}
.chart-bars{display:flex;gap:2px;align-items:flex-end;flex:1;width:100%}
.bar-rec{background:#16a34a;border-radius:3px 3px 0 0;min-height:3px;flex:1;transition:.3s}
.bar-desp{background:#ef4444;border-radius:3px 3px 0 0;min-height:3px;flex:1;transition:.3s}
.chart-lbl{font-size:.6rem;color:var(--muted);text-align:center;font-weight:600}
.chart-legenda{display:flex;gap:16px;margin-top:10px;font-size:.72rem;color:var(--muted)}
.chart-legenda span{display:flex;align-items:center;gap:5px}
.chart-legenda i{width:10px;height:10px;border-radius:2px;display:inline-block}

/* Cat bars */
.cat-list{display:flex;flex-direction:column;gap:8px}
.cat-item{display:flex;flex-direction:column;gap:3px}
.cat-item-top{display:flex;justify-content:space-between;font-size:.76rem}
.cat-item-top span:first-child{color:var(--text);font-weight:500}
.cat-item-top span:last-child{color:var(--muted);font-weight:600}
.cat-bar-track{height:5px;background:var(--off);border-radius:3px;overflow:hidden}
.cat-bar-fill{height:100%;border-radius:3px;transition:.4s}

/* Status badge */
.st{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:.66rem;font-weight:600;white-space:nowrap}
.st-realizado{background:#dcfce7;color:#166534}
.st-pendente{background:#fef9c3;color:#854d0e}
.st-cancelado{background:#fee2e2;color:#991b1b}

.tipo-badge{display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:700}
.tipo-rec{color:#16a34a}.tipo-desp{color:#dc2626}

.venc-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)}
.venc-item:last-child{border-bottom:none}
.venc-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.venc-info{flex:1;min-width:0}
.venc-nome{font-size:.82rem;font-weight:600;color:var(--text)}
.venc-data{font-size:.72rem;color:var(--muted)}
.venc-val{font-size:.82rem;font-weight:700}
</style>

<!-- Navegação -->
<div class="fin-nav">
  <span>Dashboard</span>
  <div class="sep">|</div>
  <a href="/portal/financeiro/lancamentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>">Lançamentos</a>
  <a href="/portal/financeiro/recorrentes.php">Recorrentes</a>
  <a href="/portal/financeiro/balanco.php?mes=<?= $mes ?>&ano=<?= $ano ?>">Balanço</a>
  <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap">
    <a href="/portal/financeiro/importar.php?mes=<?= $mes ?>&ano=<?= $ano ?>">↑ Importar</a>
    <a href="/portal/financeiro/exportar.php?mes=<?= $mes ?>&ano=<?= $ano ?>">↓ Exportar</a>
    <a href="/portal/financeiro/novo.php" style="background:var(--green-dk);color:#fff;border-color:var(--green-dk)">+ Novo lançamento</a>
  </div>
</div>

<!-- Período -->
<div class="periodo">
  <a href="?mes=<?= $mes_ant ?>&ano=<?= $ano_ant ?>">‹</a>
  <strong><?= $meses_nomes[$mes] ?> <?= $ano ?></strong>
  <a href="?mes=<?= $mes_prox ?>&ano=<?= $ano_prox ?>">›</a>
  <?php if ($mes != date('n') || $ano != date('Y')): ?>
    <a href="?" style="font-size:.72rem;color:var(--muted);margin-left:4px;border:none;width:auto;padding:4px 8px">Mês atual</a>
  <?php endif; ?>
</div>

<!-- KPIs -->
<div class="kpis">
  <div class="kpi receita">
    <div class="kpi-label">Receitas</div>
    <div class="kpi-val" style="color:#16a34a"><?= $fmt($receitas) ?></div>
    <div class="kpi-sub">realizadas + pendentes</div>
  </div>
  <div class="kpi despesa">
    <div class="kpi-label">Despesas</div>
    <div class="kpi-val" style="color:#dc2626"><?= $fmt($despesas) ?></div>
    <div class="kpi-sub">realizadas + pendentes</div>
  </div>
  <div class="kpi <?= $saldo >= 0 ? 'saldo-pos' : 'saldo-neg' ?>">
    <div class="kpi-label">Saldo do mês</div>
    <div class="kpi-val" style="color:<?= $saldo >= 0 ? '#163d22' : '#dc2626' ?>"><?= $fmt($saldo) ?></div>
    <div class="kpi-sub"><?= $saldo >= 0 ? 'positivo' : 'negativo' ?></div>
  </div>
  <div class="kpi pendente">
    <div class="kpi-label">Pendentes</div>
    <div class="kpi-val"><?= $pendentes ?></div>
    <div class="kpi-sub"><?= $a_receber > 0 ? 'a receber: ' . $fmt($a_receber) : 'lançamentos' ?></div>
  </div>
</div>

<!-- Grid principal -->
<div class="fin-grid" style="margin-bottom:20px">

  <!-- Gráfico 6 meses -->
  <div class="fin-card">
    <div class="fin-card-head">
      <h3>Fluxo — últimos 6 meses</h3>
    </div>
    <div class="fin-card-body">
      <div class="chart-wrap">
        <?php foreach ($grafico as $g): $hr = $g['rec']/$max_grafico*100; $hd = $g['desp']/$max_grafico*100; ?>
        <div class="chart-col">
          <div class="chart-bars">
            <div class="bar-rec" style="height:<?= max(3,round($hr)) ?>%"></div>
            <div class="bar-desp" style="height:<?= max(3,round($hd)) ?>%"></div>
          </div>
          <div class="chart-lbl"><?= $g['label'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="chart-legenda">
        <span><i style="background:#16a34a"></i> Receitas</span>
        <span><i style="background:#ef4444"></i> Despesas</span>
      </div>
    </div>
  </div>

  <!-- Vencimentos próximos -->
  <div class="fin-card">
    <div class="fin-card-head">
      <h3>Vencimentos — próx. 7 dias</h3>
      <a href="/portal/financeiro/recorrentes.php" class="btn btn-ghost btn-sm">Ver todos</a>
    </div>
    <div class="fin-card-body">
      <?php if (empty($vencimentos)): ?>
        <p style="color:var(--muted);font-size:.82rem;text-align:center;padding:20px 0">Nenhum vencimento próximo.</p>
      <?php else: ?>
        <?php foreach ($vencimentos as $v): ?>
        <div class="venc-item">
          <div class="venc-dot" style="background:<?= $v['tipo']==='receita' ? '#16a34a' : '#dc2626' ?>"></div>
          <div class="venc-info">
            <div class="venc-nome"><?= htmlspecialchars($v['descricao']) ?></div>
            <div class="venc-data"><?= date('d/m', strtotime($v['proximo_vencimento'])) ?> · <?= htmlspecialchars($v['cat_nome'] ?? '') ?></div>
          </div>
          <div class="venc-val" style="color:<?= $v['tipo']==='receita' ? '#16a34a' : '#dc2626' ?>"><?= number_format($v['valor'],2,',','.') ?></div>
          <a href="/portal/financeiro/novo.php?rec=<?= $v['id'] ?>" class="btn btn-primary btn-sm">Lançar</a>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Grid categorias -->
<div class="fin-grid" style="margin-bottom:20px">
  <div class="fin-card">
    <div class="fin-card-head"><h3>Receitas por categoria</h3></div>
    <div class="fin-card-body">
      <?php if (empty($rec_cat)): ?>
        <p style="color:var(--muted);font-size:.82rem;text-align:center;padding:16px 0">Sem receitas no período.</p>
      <?php else: $max_rc = max(array_column($rec_cat,'total')); ?>
        <div class="cat-list">
          <?php foreach ($rec_cat as $c): ?>
          <div class="cat-item">
            <div class="cat-item-top">
              <span><?= htmlspecialchars($c['nome']) ?></span>
              <span>R$ <?= number_format($c['total'],2,',','.') ?></span>
            </div>
            <div class="cat-bar-track">
              <div class="cat-bar-fill" style="width:<?= round($c['total']/$max_rc*100) ?>%;background:<?= htmlspecialchars($c['cor']) ?>"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="fin-card">
    <div class="fin-card-head"><h3>Despesas por categoria</h3></div>
    <div class="fin-card-body">
      <?php if (empty($desp_cat)): ?>
        <p style="color:var(--muted);font-size:.82rem;text-align:center;padding:16px 0">Sem despesas no período.</p>
      <?php else: $max_dc = max(array_column($desp_cat,'total')); ?>
        <div class="cat-list">
          <?php foreach ($desp_cat as $c): ?>
          <div class="cat-item">
            <div class="cat-item-top">
              <span><?= htmlspecialchars($c['nome']) ?></span>
              <span>R$ <?= number_format($c['total'],2,',','.') ?></span>
            </div>
            <div class="cat-bar-track">
              <div class="cat-bar-fill" style="width:<?= round($c['total']/$max_dc*100) ?>%;background:<?= htmlspecialchars($c['cor']) ?>"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Últimos lançamentos -->
<div class="tabela-wrap">
  <div class="tabela-header">
    <h2>Últimos lançamentos — <?= $meses_nomes[$mes] ?></h2>
    <div style="display:flex;gap:8px">
      <a href="/portal/financeiro/lancamentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>" class="btn btn-ghost btn-sm">Ver todos</a>
      <a href="/portal/financeiro/novo.php" class="btn btn-primary btn-sm">+ Novo</a>
    </div>
  </div>
  <?php if (empty($ultimos)): ?>
    <div style="padding:36px;text-align:center;color:var(--muted);font-size:.88rem">Nenhum lançamento neste mês.</div>
  <?php else: ?>
  <table>
    <thead><tr>
      <th>Data</th><th>Descrição</th><th>Categoria</th>
      <th>Tipo</th><th style="text-align:right">Valor</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($ultimos as $l): ?>
    <tr>
      <td style="font-size:.78rem;color:var(--muted)"><?= date('d/m', strtotime($l['data_lancamento'])) ?></td>
      <td style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($l['descricao']) ?></td>
      <td>
        <span style="display:inline-flex;align-items:center;gap:5px;font-size:.75rem">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($l['cat_cor'] ?? '#999') ?>;flex-shrink:0"></span>
          <?= htmlspecialchars($l['cat_nome'] ?? '—') ?>
        </span>
      </td>
      <td><span class="tipo-badge <?= $l['tipo']==='receita' ? 'tipo-rec' : 'tipo-desp' ?>"><?= $l['tipo']==='receita' ? '↑ Receita' : '↓ Despesa' ?></span></td>
      <td style="text-align:right;font-weight:700;font-size:.88rem;color:<?= $l['tipo']==='receita' ? '#16a34a' : '#dc2626' ?>">
        <?= number_format($l['valor'],2,',','.') ?>
      </td>
      <td><span class="st st-<?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span></td>
      <td><a href="/portal/financeiro/editar.php?id=<?= $l['id'] ?>" class="btn btn-ghost btn-sm">Editar</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
