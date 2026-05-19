<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin','financeiro']);

$titulo = 'Lançamentos';
$pagina_ativa = 'financeiro';

$mes    = (int)($_GET['mes']    ?? date('n'));
$ano    = (int)($_GET['ano']    ?? date('Y'));
$tipo   = $_GET['tipo']   ?? '';
$status = $_GET['status'] ?? '';
$cat_id = (int)($_GET['cat']   ?? 0);
$busca  = trim($_GET['q'] ?? '');
$mes = max(1,min(12,$mes)); $ano = max(2020,min(2099,$ano));

$meses_nomes = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

// Ação de exclusão
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['acao']??'')==='deletar' && csrf_valido()) {
    $del_id = (int)($_POST['id'] ?? 0);
    if ($del_id) {
        $anexos = db()->prepare("SELECT nome_arquivo FROM financeiro_anexos WHERE lancamento_id=?");
        $anexos->execute([$del_id]);
        foreach ($anexos->fetchAll() as $a) {
            @unlink(__DIR__ . '/uploads/' . $a['nome_arquivo']);
        }
        db()->prepare("DELETE FROM financeiro_anexos WHERE lancamento_id=?")->execute([$del_id]);
        db()->prepare("DELETE FROM financeiro_lancamentos WHERE id=?")->execute([$del_id]);
    }
    header("Location: /portal/financeiro/lancamentos.php?mes={$mes}&ano={$ano}&deletado=1");
    exit;
}

$categorias = db()->query("SELECT * FROM financeiro_categorias ORDER BY tipo,ordem,nome")->fetchAll();

$where = "WHERE l.competencia_mes=? AND l.competencia_ano=?";
$params = [$mes, $ano];
if ($tipo)   { $where .= " AND l.tipo=?";        $params[] = $tipo; }
if ($status) { $where .= " AND l.status=?";      $params[] = $status; }
if ($cat_id) { $where .= " AND l.categoria_id=?"; $params[] = $cat_id; }
if ($busca)  { $where .= " AND l.descricao LIKE ?"; $params[] = "%$busca%"; }

$sql = "SELECT l.*, c.nome as cat_nome, c.cor as cat_cor,
               (SELECT COUNT(*) FROM financeiro_anexos a WHERE a.lancamento_id=l.id) as n_anexos
        FROM financeiro_lancamentos l
        LEFT JOIN financeiro_categorias c ON c.id=l.categoria_id
        $where ORDER BY l.data_lancamento DESC, l.id DESC";
$st = db()->prepare($sql); $st->execute($params);
$lancamentos = $st->fetchAll();

// Totais
$total_rec  = array_sum(array_map(fn($r) => $r['tipo']==='receita' && $r['status']!=='cancelado' ? $r['valor'] : 0, $lancamentos));
$total_desp = array_sum(array_map(fn($r) => $r['tipo']==='despesa' && $r['status']!=='cancelado' ? $r['valor'] : 0, $lancamentos));

$mes_ant = $mes-1; $ano_ant = $ano; if($mes_ant<=0){$mes_ant=12;$ano_ant--;}
$mes_prox= $mes+1; $ano_prox= $ano; if($mes_prox>12){$mes_prox=1;$ano_prox++;}

$qs = fn($extra=[]) => http_build_query(array_merge(['mes'=>$mes,'ano'=>$ano,'tipo'=>$tipo,'status'=>$status,'cat'=>$cat_id,'q'=>$busca], $extra));

include dirname(__DIR__) . '/_layout.php';
?>

<style>
.fin-nav{display:flex;align-items:center;gap:8px;margin-bottom:24px;flex-wrap:wrap}
.fin-nav a,.fin-nav span{padding:6px 14px;border-radius:7px;font-size:.78rem;font-weight:600;text-decoration:none;border:1.5px solid var(--border);color:var(--text);background:#fff;transition:.15s}
.fin-nav a:hover{border-color:var(--green);color:var(--green)}
.fin-nav span{background:var(--green-dk);color:#fff;border-color:var(--green-dk)}
.filtros{background:#fff;border:1px solid var(--border);border-radius:var(--r);padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.filtros .form-group{margin:0;flex:1;min-width:140px}
.filtros label{font-size:.7rem;font-weight:600;color:var(--muted);margin-bottom:4px}
.filtros input,.filtros select{padding:6px 10px;font-size:.82rem}
.st{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:.66rem;font-weight:600}
.st-realizado{background:#dcfce7;color:#166534}
.st-pendente{background:#fef9c3;color:#854d0e}
.st-cancelado{background:#fee2e2;color:#991b1b}
.periodo{display:flex;align-items:center;gap:8px;margin-bottom:20px}
.periodo a{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1.5px solid var(--border);color:var(--muted);text-decoration:none;transition:.15s}
.periodo a:hover{border-color:var(--green);color:var(--green)}
.periodo strong{font-family:'Cinzel',serif;font-size:.88rem;font-weight:700;color:var(--green-dk);min-width:160px;text-align:center}

/* ── Cards mobile ─────────────────────────────────────────────────────────── */
.lanc-cards{display:none;flex-direction:column;gap:10px;padding:12px}
.lc{background:#fff;border-radius:10px;border:1px solid var(--border);overflow:hidden;transition:.15s}
.lc:hover{border-color:var(--green);box-shadow:0 2px 10px rgba(30,107,53,.08)}
.lc-top{display:flex;align-items:flex-start;gap:10px;padding:13px 14px 10px}
.lc-bar{width:4px;border-radius:2px;align-self:stretch;flex-shrink:0;min-height:40px}
.lc-main{flex:1;min-width:0}
.lc-desc{font-weight:700;font-size:.9rem;color:var(--text);line-height:1.3;margin-bottom:3px}
.lc-meta{font-size:.72rem;color:var(--muted);display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.lc-val{font-family:'Cinzel',serif;font-weight:700;font-size:1rem;white-space:nowrap;text-align:right}
.lc-tags{display:flex;gap:6px;flex-wrap:wrap;align-items:center;padding:0 14px 10px 28px}
.lc-obs{font-size:.72rem;color:var(--muted);padding:0 14px 10px 28px;font-style:italic}
.lc-actions{display:flex;gap:8px;padding:10px 14px;border-top:1px solid var(--border);background:var(--off)}
.lc-actions a,.lc-actions button{flex:1;justify-content:center;font-size:.82rem;padding:9px 0}
.lc-anx{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:.66rem;font-weight:700;background:#f0fdf4;color:#166534;text-decoration:none;border:1px solid #bbf7d0}

@media(max-width:767px){
  .tabela-wrap table,.tabela-wrap thead,.tabela-wrap tbody{display:none!important}
  .lanc-cards{display:flex}
  .fin-nav{gap:6px}
  .fin-nav a,.fin-nav span{padding:5px 10px;font-size:.73rem}
}
</style>

<div class="fin-nav">
  <a href="/portal/financeiro/?mes=<?= $mes ?>&ano=<?= $ano ?>">Dashboard</a>
  <div style="color:var(--border)">|</div>
  <span>Lançamentos</span>
  <a href="/portal/financeiro/recorrentes.php">Recorrentes</a>
  <a href="/portal/financeiro/balanco.php?mes=<?= $mes ?>&ano=<?= $ano ?>">Balanço</a>
  <a href="/portal/financeiro/novo.php" style="margin-left:auto;background:var(--green-dk);color:#fff;border-color:var(--green-dk)">+ Novo lançamento</a>
</div>

<?php if (!empty($_GET['deletado'])): ?><div class="alerta alerta-ok" style="margin-bottom:16px">Lançamento excluído.</div><?php endif; ?>

<div class="periodo">
  <a href="?<?= $qs(['mes'=>$mes_ant,'ano'=>$ano_ant]) ?>">‹</a>
  <strong><?= $meses_nomes[$mes] ?> <?= $ano ?></strong>
  <a href="?<?= $qs(['mes'=>$mes_prox,'ano'=>$ano_prox]) ?>">›</a>
</div>

<form method="get" class="filtros">
  <input type="hidden" name="mes" value="<?= $mes ?>">
  <input type="hidden" name="ano" value="<?= $ano ?>">
  <div class="form-group" style="flex:2">
    <label>Buscar</label>
    <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Descrição...">
  </div>
  <div class="form-group">
    <label>Tipo</label>
    <select name="tipo">
      <option value="">Todos</option>
      <option value="receita" <?= $tipo==='receita'?'selected':'' ?>>↑ Receita</option>
      <option value="despesa" <?= $tipo==='despesa'?'selected':'' ?>>↓ Despesa</option>
    </select>
  </div>
  <div class="form-group">
    <label>Categoria</label>
    <select name="cat">
      <option value="">Todas</option>
      <?php foreach ($categorias as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $cat_id==$c['id']?'selected':'' ?>>[<?= ucfirst($c['tipo'][0]) ?>] <?= htmlspecialchars($c['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Status</label>
    <select name="status">
      <option value="">Todos</option>
      <option value="realizado" <?= $status==='realizado'?'selected':'' ?>>Realizado</option>
      <option value="pendente"  <?= $status==='pendente'?'selected':'' ?>>Pendente</option>
      <option value="cancelado" <?= $status==='cancelado'?'selected':'' ?>>Cancelado</option>
    </select>
  </div>
  <div>
    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    <a href="/portal/financeiro/lancamentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>" class="btn btn-ghost btn-sm">Limpar</a>
  </div>
</form>

<!-- Totais -->
<div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap">
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 18px;display:flex;gap:10px;align-items:center">
    <span style="font-size:.72rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:.06em">Receitas</span>
    <span style="font-size:1rem;font-weight:700;color:#15803d">R$ <?= number_format($total_rec,2,',','.') ?></span>
  </div>
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 18px;display:flex;gap:10px;align-items:center">
    <span style="font-size:.72rem;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:.06em">Despesas</span>
    <span style="font-size:1rem;font-weight:700;color:#dc2626">R$ <?= number_format($total_desp,2,',','.') ?></span>
  </div>
  <?php $saldo = $total_rec - $total_desp; ?>
  <div style="background:<?= $saldo>=0?'#f0fdf4':'#fef2f2' ?>;border:1px solid <?= $saldo>=0?'#bbf7d0':'#fecaca' ?>;border-radius:8px;padding:10px 18px;display:flex;gap:10px;align-items:center">
    <span style="font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em">Saldo</span>
    <span style="font-size:1rem;font-weight:700;color:<?= $saldo>=0?'#15803d':'#dc2626' ?>">R$ <?= number_format($saldo,2,',','.') ?></span>
  </div>
  <span style="align-self:center;font-size:.78rem;color:var(--muted);margin-left:auto"><?= count($lancamentos) ?> lançamento(s)</span>
</div>

<div class="tabela-wrap">
  <div class="tabela-header">
    <h2>Lançamentos — <?= $meses_nomes[$mes] ?> <?= $ano ?></h2>
    <a href="/portal/financeiro/exportar.php?exp=lancamentos&<?= $qs() ?>"
       class="btn btn-ghost btn-sm" style="margin-left:auto" title="Exportar para Excel">
      📊 Exportar Excel
    </a>
  </div>
  <?php if (empty($lancamentos)): ?>
    <div style="padding:40px;text-align:center;color:var(--muted)">Nenhum lançamento encontrado.</div>
  <?php else: ?>
  <table>
    <thead><tr>
      <th>Data</th><th>Descrição</th><th>Categoria</th>
      <th>Forma</th><th>Tipo</th><th style="text-align:right">Valor</th>
      <th>Status</th><th>Anx.</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($lancamentos as $l): ?>
    <tr>
      <td style="font-size:.78rem;white-space:nowrap"><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></td>
      <td>
        <div style="font-weight:600;font-size:.84rem"><?= htmlspecialchars($l['descricao']) ?></div>
        <?php if ($l['observacoes']): ?><div style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars(mb_substr($l['observacoes'],0,50)) ?><?= mb_strlen($l['observacoes'])>50?'…':'' ?></div><?php endif; ?>
      </td>
      <td>
        <span style="display:inline-flex;align-items:center;gap:5px;font-size:.75rem">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($l['cat_cor']??'#999') ?>;flex-shrink:0"></span>
          <?= htmlspecialchars($l['cat_nome']??'—') ?>
        </span>
      </td>
      <td style="font-size:.76rem;color:var(--muted)"><?= ucfirst($l['forma_pagamento']) ?></td>
      <td>
        <span style="font-size:.72rem;font-weight:700;color:<?= $l['tipo']==='receita'?'#16a34a':'#dc2626' ?>">
          <?= $l['tipo']==='receita'?'↑ Receita':'↓ Despesa' ?>
        </span>
      </td>
      <td style="text-align:right;font-weight:700;font-size:.88rem;color:<?= $l['tipo']==='receita'?'#16a34a':'#dc2626' ?>;white-space:nowrap">
        <?= number_format($l['valor'],2,',','.') ?>
      </td>
      <td><span class="st st-<?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span></td>
      <td style="text-align:center">
        <?php if ($l['n_anexos'] > 0): ?>
          <a href="/portal/financeiro/editar.php?id=<?= $l['id'] ?>" style="font-size:.72rem;color:var(--green);font-weight:700">📎<?= $l['n_anexos'] ?></a>
        <?php else: ?>
          <span style="color:var(--muted);font-size:.72rem">—</span>
        <?php endif; ?>
      </td>
      <td>
        <div style="display:flex;gap:4px">
          <a href="/portal/financeiro/editar.php?id=<?= $l['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
          <form method="post" onsubmit="return confirm('Excluir este lançamento?')" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="deletar">
            <input type="hidden" name="id" value="<?= $l['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">✕</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- ── Cards mobile ───────────────────────────────────────────────────── -->
  <div class="lanc-cards">
  <?php foreach ($lancamentos as $l):
    $cor     = $l['tipo']==='receita' ? '#16a34a' : '#dc2626';
    $forma_l = ['dinheiro'=>'Dinheiro','pix'=>'PIX','transferencia'=>'Transf.','boleto'=>'Boleto','cartao'=>'Cartão','cheque'=>'Cheque','outro'=>'Outro'];
  ?>
  <div class="lc">
    <div class="lc-top">
      <span class="lc-bar" style="background:<?= $cor ?>"></span>
      <div class="lc-main">
        <div class="lc-desc"><?= htmlspecialchars($l['descricao']) ?></div>
        <div class="lc-meta">
          <span style="display:inline-flex;align-items:center;gap:4px">
            <span style="width:7px;height:7px;border-radius:50%;background:<?= htmlspecialchars($l['cat_cor']??'#999') ?>;flex-shrink:0"></span>
            <?= htmlspecialchars($l['cat_nome']??'—') ?>
          </span>
          <span>·</span>
          <span><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></span>
          <span>·</span>
          <span><?= $forma_l[$l['forma_pagamento']] ?? ucfirst($l['forma_pagamento']) ?></span>
        </div>
      </div>
      <div class="lc-val" style="color:<?= $cor ?>">
        <?= $l['tipo']==='receita'?'↑':'↓' ?><br>
        R$&nbsp;<?= number_format($l['valor'],2,',','.') ?>
      </div>
    </div>

    <div class="lc-tags">
      <span class="st st-<?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span>
      <?php if ($l['n_anexos'] > 0): ?>
        <a href="/portal/financeiro/editar.php?id=<?= $l['id'] ?>" class="lc-anx">📎 <?= $l['n_anexos'] ?> anexo(s)</a>
      <?php endif; ?>
    </div>

    <?php if ($l['observacoes']): ?>
    <div class="lc-obs"><?= htmlspecialchars(mb_substr($l['observacoes'],0,80)) ?><?= mb_strlen($l['observacoes'])>80?'…':'' ?></div>
    <?php endif; ?>

    <div class="lc-actions">
      <a href="/portal/financeiro/editar.php?id=<?= $l['id'] ?>" class="btn btn-ghost btn-sm">✏️ Editar / Anexos</a>
      <form method="post" onsubmit="return confirm('Excluir este lançamento?')" style="display:contents">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao" value="deletar">
        <input type="hidden" name="id" value="<?= $l['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">✕ Excluir</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  </div>

  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
