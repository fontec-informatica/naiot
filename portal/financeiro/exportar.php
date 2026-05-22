<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin','financeiro']);
require_once dirname(__DIR__) . '/lib/XlsxWriter.php';

$mes    = (int)($_GET['mes']    ?? date('n'));
$ano    = (int)($_GET['ano']    ?? date('Y'));
$tipo   = $_GET['tipo']   ?? '';
$status = $_GET['status'] ?? '';
$cat_id = (int)($_GET['cat']   ?? 0);
$busca  = trim($_GET['q'] ?? '');
$mes = max(1, min(12, $mes));
$ano = max(2020, min(2099, $ano));

$meses_nomes = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$forma_labels = ['dinheiro'=>'Dinheiro','pix'=>'PIX','transferencia'=>'Transferência',
                 'boleto'=>'Boleto','cartao'=>'Cartão','cheque'=>'Cheque','outro'=>'Outro'];

$pdo = db();
$periodo = $meses_nomes[$mes] . ' / ' . $ano;
$hoje = new DateTime();

// ── Lançamentos filtrados ─────────────────────────────────────────────────
$where  = "WHERE l.competencia_mes=? AND l.competencia_ano=?";
$params = [$mes, $ano];
if ($tipo)   { $where .= " AND l.tipo=?";           $params[] = $tipo; }
if ($status) { $where .= " AND l.status=?";         $params[] = $status; }
if ($cat_id) { $where .= " AND l.categoria_id=?";   $params[] = $cat_id; }
if ($busca)  { $where .= " AND l.descricao LIKE ?";  $params[] = "%$busca%"; }

$st = $pdo->prepare("
    SELECT l.*, c.nome as cat_nome, c.cor as cat_cor
    FROM financeiro_lancamentos l
    LEFT JOIN financeiro_categorias c ON c.id = l.categoria_id
    $where
    ORDER BY l.data_lancamento ASC, l.id ASC
");
$st->execute($params);
$lancamentos = $st->fetchAll();

// ── KPIs ─────────────────────────────────────────────────────────────────
$total_rec = 0; $total_desp = 0; $total_pend = 0; $a_receber = 0;
$rec_por_cat = []; $desp_por_cat = [];
foreach ($lancamentos as $l) {
    if ($l['status'] === 'cancelado') continue;
    if ($l['tipo'] === 'receita') {
        $total_rec += $l['valor'];
        $rec_por_cat[$l['cat_nome'] ?? 'Sem categoria'] = ($rec_por_cat[$l['cat_nome'] ?? 'Sem categoria'] ?? 0) + $l['valor'];
        if ($l['status'] === 'pendente') $a_receber += $l['valor'];
    } else {
        $total_desp += $l['valor'];
        $desp_por_cat[$l['cat_nome'] ?? 'Sem categoria'] = ($desp_por_cat[$l['cat_nome'] ?? 'Sem categoria'] ?? 0) + $l['valor'];
        if ($l['status'] === 'pendente') $total_pend += $l['valor'];
    }
}
arsort($rec_por_cat); arsort($desp_por_cat);
$saldo = $total_rec - $total_desp;

// ── Todas as categorias ───────────────────────────────────────────────────
$categorias = $pdo->query("SELECT * FROM financeiro_categorias ORDER BY tipo, ordem, nome")->fetchAll();

// ── Recorrentes ativos ────────────────────────────────────────────────────
$recorrentes = $pdo->query("
    SELECT r.*, c.nome as cat_nome
    FROM financeiro_recorrentes r
    LEFT JOIN financeiro_categorias c ON c.id = r.categoria_id
    WHERE r.status = 'ativo'
    ORDER BY r.proximo_vencimento ASC, r.descricao ASC
")->fetchAll();

// ── Últimos 6 meses (histórico) ───────────────────────────────────────────
$historico = [];
for ($i = 5; $i >= 0; $i--) {
    $m = $mes - $i; $a = $ano;
    while ($m <= 0) { $m += 12; $a--; }
    $r = $pdo->prepare("
        SELECT
          COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END),0) as rec,
          COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END),0) as desp
        FROM financeiro_lancamentos
        WHERE competencia_mes=? AND competencia_ano=? AND status!='cancelado'
    ");
    $r->execute([$m, $a]);
    $row = $r->fetch();
    $historico[] = [
        'label' => substr($meses_nomes[$m], 0, 3) . '/' . $a,
        'rec'   => (float)$row['rec'],
        'desp'  => (float)$row['desp'],
        'saldo' => (float)$row['rec'] - (float)$row['desp'],
    ];
}

// ── Montar Excel ──────────────────────────────────────────────────────────
$xlsx = new XlsxWriter();
$fmt_val = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

// ═══════════════════════════════════════════════════════
// ABA 1 — DASHBOARD
// ═══════════════════════════════════════════════════════
$dash = $xlsx->addSheet('Dashboard');
$dash->setColWidth(1, 34)->setColWidth(2, 20)->setColWidth(3, 20)->setColWidth(4, 16);

$r = 1;
$dash->writeCell($r, 1, 'RELATÓRIO FINANCEIRO — NAIOT', 3);
$dash->writeCell($r, 2, '', 3)->writeCell($r, 3, '', 3)->writeCell($r, 4, '', 3);
$r++;
$dash->writeCell($r, 1, 'Período: ' . $periodo, 1);
$dash->writeCell($r, 3, 'Gerado em: ' . $hoje->format('d/m/Y H:i'), 0);
$r += 2;

// Resumo do período
$dash->writeCell($r, 1, 'RESUMO DO PERÍODO', 2);
$dash->writeCell($r, 2, '', 2)->writeCell($r, 3, '', 2)->writeCell($r, 4, '', 2);
$r++;
$kpis = [
    ['Total de receitas realizadas',  $fmt_val($total_rec),         $total_rec  >= 0 ? 8 : 9],
    ['Total de despesas realizadas',  $fmt_val($total_desp),        9],
    ['Saldo do período',              $fmt_val($saldo),             $saldo >= 0 ? 8 : 9],
    ['A receber (pendentes)',         $fmt_val($a_receber),         9],
    ['A pagar (pendentes)',           $fmt_val($total_pend),        9],
    ['Total de lançamentos',          count($lancamentos),          9],
];
foreach ($kpis as [$label, $val, $style]) {
    $dash->writeCell($r, 1, $label, $style);
    $dash->writeCell($r, 2, $val, $style);
    $r++;
}
$r++;

// Receitas por categoria
if ($rec_por_cat) {
    $dash->writeCell($r, 1, 'RECEITAS POR CATEGORIA', 2);
    $dash->writeCell($r, 2, 'Valor', 2)->writeCell($r, 3, '% do total', 2);
    $r++;
    foreach ($rec_por_cat as $cat => $val) {
        $pct = $total_rec > 0 ? round($val / $total_rec * 100, 1) : 0;
        $dash->writeCell($r, 1, $cat,                    9);
        $dash->writeCell($r, 2, $fmt_val($val),          9);
        $dash->writeCell($r, 3, $pct . '%',              9);
        $r++;
    }
    $dash->writeCell($r, 1, 'TOTAL',          8);
    $dash->writeCell($r, 2, $fmt_val($total_rec), 8);
    $dash->writeCell($r, 3, '100%',           8);
    $r += 2;
}

// Despesas por categoria
if ($desp_por_cat) {
    $dash->writeCell($r, 1, 'DESPESAS POR CATEGORIA', 4);
    $dash->writeCell($r, 2, 'Valor', 4)->writeCell($r, 3, '% do total', 4);
    $r++;
    foreach ($desp_por_cat as $cat => $val) {
        $pct = $total_desp > 0 ? round($val / $total_desp * 100, 1) : 0;
        $dash->writeCell($r, 1, $cat,                    9);
        $dash->writeCell($r, 2, $fmt_val($val),          9);
        $dash->writeCell($r, 3, $pct . '%',              9);
        $r++;
    }
    $dash->writeCell($r, 1, 'TOTAL',           8);
    $dash->writeCell($r, 2, $fmt_val($total_desp), 8);
    $dash->writeCell($r, 3, '100%',            8);
    $r += 2;
}

// Histórico dos últimos 6 meses
$dash->writeCell($r, 1, 'HISTÓRICO — ÚLTIMOS 6 MESES', 2);
$dash->writeCell($r, 2, 'Receitas', 2)->writeCell($r, 3, 'Despesas', 2)->writeCell($r, 4, 'Saldo', 2);
$r++;
foreach ($historico as $h) {
    $style = $h['saldo'] >= 0 ? 8 : 9;
    $dash->writeCell($r, 1, $h['label'],          $style);
    $dash->writeCell($r, 2, $fmt_val($h['rec']),  $style);
    $dash->writeCell($r, 3, $fmt_val($h['desp']), $style);
    $dash->writeCell($r, 4, $fmt_val($h['saldo']),$style);
    $r++;
}

// ═══════════════════════════════════════════════════════
// ABA 2 — LANÇAMENTOS
// ═══════════════════════════════════════════════════════
$lan = $xlsx->addSheet('Lancamentos');
$lan->setColWidth(1, 13)->setColWidth(2, 36)->setColWidth(3, 22)
    ->setColWidth(4, 11)->setColWidth(5, 14)->setColWidth(6, 16)
    ->setColWidth(7, 16)->setColWidth(8, 13)->setColWidth(9, 30);

$lan->writeRow(1, ['Data', 'Descrição', 'Categoria', 'Tipo', 'Forma Pagto.', 'Valor (R$)', 'Status', 'Competência', 'Observações'], 2);
$rn = 2;
foreach ($lancamentos as $l) {
    $style = ($rn % 2 === 0) ? 6 : 9;
    $dataFmt = '';
    if (!empty($l['data_lancamento'])) {
        $d = DateTime::createFromFormat('Y-m-d', $l['data_lancamento']);
        if ($d) $dataFmt = $d->format('d/m/Y');
    }
    $lan->writeRow($rn, [
        $dataFmt,
        $l['descricao'],
        $l['cat_nome']     ?? '',
        ucfirst($l['tipo']),
        $forma_labels[$l['forma_pagamento']] ?? ucfirst($l['forma_pagamento']),
        'R$ ' . number_format((float)$l['valor'], 2, ',', '.'),
        ucfirst($l['status']),
        str_pad($l['competencia_mes'], 2, '0', STR_PAD_LEFT) . '/' . $l['competencia_ano'],
        $l['observacoes'] ?? '',
    ], $style);
    $rn++;
}
if ($rn === 2) $lan->writeCell(2, 1, 'Nenhum lançamento no período.', 0);

// ═══════════════════════════════════════════════════════
// ABA 3 — DRE (Demonstrativo)
// ═══════════════════════════════════════════════════════
$dre = $xlsx->addSheet('DRE');
$dre->setColWidth(1, 36)->setColWidth(2, 18)->setColWidth(3, 14);

$dre->writeRow(1, ['DRE — ' . $periodo, 'Valor (R$)', '% do Total'], 3);
$rn = 2;
if ($rec_por_cat) {
    $dre->writeRow($rn, ['RECEITAS', '', ''], 2); $rn++;
    foreach ($rec_por_cat as $cat => $val) {
        $pct = $total_rec > 0 ? round($val / $total_rec * 100, 1) : 0;
        $dre->writeRow($rn, ['  ' . $cat, $fmt_val($val), $pct . '%'], 9); $rn++;
    }
    $dre->writeRow($rn, ['TOTAL RECEITAS', $fmt_val($total_rec), ''], 8); $rn++;
}
$rn++;
if ($desp_por_cat) {
    $dre->writeRow($rn, ['DESPESAS', '', ''], 4); $rn++;
    foreach ($desp_por_cat as $cat => $val) {
        $pct = $total_desp > 0 ? round($val / $total_desp * 100, 1) : 0;
        $dre->writeRow($rn, ['  ' . $cat, $fmt_val($val), $pct . '%'], 9); $rn++;
    }
    $dre->writeRow($rn, ['TOTAL DESPESAS', $fmt_val($total_desp), ''], 8); $rn++;
}
$rn++;
$sStyle = $saldo >= 0 ? 8 : 9;
$dre->writeRow($rn, ['RESULTADO DO PERÍODO', $fmt_val($saldo), ''], $sStyle);

// ═══════════════════════════════════════════════════════
// ABA 4 — CATEGORIAS
// ═══════════════════════════════════════════════════════
$cats = $xlsx->addSheet('Categorias');
$cats->setColWidth(1, 32)->setColWidth(2, 12)->setColWidth(3, 10);
$cats->writeRow(1, ['Categoria', 'Tipo', 'Ativa'], 2);
$rn = 2;
foreach ($categorias as $c) {
    $style = ($rn % 2 === 0) ? 6 : 9;
    $cats->writeRow($rn, [
        $c['nome'],
        $c['tipo'] === 'receita' ? 'Receita' : 'Despesa',
        $c['ativo'] ? 'Sim' : 'Não',
    ], $style);
    $rn++;
}
if ($rn === 2) $cats->writeCell(2, 1, 'Nenhuma categoria cadastrada.', 0);

// ═══════════════════════════════════════════════════════
// ABA 5 — RECORRENTES
// ═══════════════════════════════════════════════════════
$rec = $xlsx->addSheet('Recorrentes');
$rec->setColWidth(1, 34)->setColWidth(2, 11)->setColWidth(3, 22)
    ->setColWidth(4, 16)->setColWidth(5, 14)->setColWidth(6, 16);
$rec->writeRow(1, ['Descrição', 'Tipo', 'Categoria', 'Valor', 'Dia Venc.', 'Próx. Vencimento'], 2);
$rn = 2;
foreach ($recorrentes as $rc) {
    $style = ($rn % 2 === 0) ? 6 : 9;
    $proxVenc = '';
    if (!empty($rc['proximo_vencimento'])) {
        $d = DateTime::createFromFormat('Y-m-d', $rc['proximo_vencimento']);
        if ($d) $proxVenc = $d->format('d/m/Y');
    }
    $rec->writeRow($rn, [
        $rc['descricao'],
        $rc['tipo'] === 'receita' ? 'Receita' : 'Despesa',
        $rc['cat_nome'] ?? '',
        'R$ ' . number_format((float)$rc['valor'], 2, ',', '.'),
        'Dia ' . $rc['dia_vencimento'],
        $proxVenc,
    ], $style);
    $rn++;
}
if ($rn === 2) $rec->writeCell(2, 1, 'Nenhum recorrente ativo.', 0);

// ── Download ──────────────────────────────────────────────────────────────
$filename = 'financeiro-naiot-' . $ano . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.xlsx';
$xlsx->download($filename);
