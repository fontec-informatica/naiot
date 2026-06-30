<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['oracoes']);
require_once dirname(__DIR__) . '/lib/XlsxWriter.php';

$pdo  = db();
$hoje = new DateTime();

$oracoes     = $pdo->query("SELECT * FROM oracoes     ORDER BY criado_em DESC")->fetchAll();
$testemunhos = $pdo->query("SELECT * FROM testemunhos ORDER BY criado_em DESC")->fetchAll();

$xlsx = new XlsxWriter();

// ═══ ABA 1 — PEDIDOS DE ORAÇÃO ═══
$shO = $xlsx->addSheet('Pedidos de Oração');
$shO->setColWidth(1, 8)->setColWidth(2, 65)->setColWidth(3, 14)->setColWidth(4, 20);
$shO->writeRow(1, ['#', 'Texto', 'Status', 'Data de envio'], 2);

$r = 2;
foreach ($oracoes as $item) {
    $style = ($r % 2 === 0) ? 6 : 9;
    $status_label = $item['status'] === 'aprovado' ? 'Aprovado'
                  : ($item['status'] === 'rejeitado' ? 'Rejeitado' : 'Pendente');
    $data = date('d/m/Y H:i', strtotime($item['criado_em']));
    $shO->writeRow($r, [
        $item['id'],
        $item['texto'],
        $status_label,
        $data,
    ], $style);
    $r++;
}
if ($r === 2) $shO->writeCell(2, 1, 'Nenhum registro.', 0);

// ═══ ABA 2 — TESTEMUNHOS ═══
$shT = $xlsx->addSheet('Testemunhos');
$shT->setColWidth(1, 8)->setColWidth(2, 65)->setColWidth(3, 14)->setColWidth(4, 20);
$shT->writeRow(1, ['#', 'Texto', 'Status', 'Data de envio'], 2);

$r = 2;
foreach ($testemunhos as $item) {
    $style = ($r % 2 === 0) ? 6 : 9;
    $status_label = $item['status'] === 'aprovado' ? 'Aprovado'
                  : ($item['status'] === 'rejeitado' ? 'Rejeitado' : 'Pendente');
    $data = date('d/m/Y H:i', strtotime($item['criado_em']));
    $shT->writeRow($r, [
        $item['id'],
        $item['texto'],
        $status_label,
        $data,
    ], $style);
    $r++;
}
if ($r === 2) $shT->writeCell(2, 1, 'Nenhum registro.', 0);

// ═══ ABA 3 — RESUMO ═══
$shR = $xlsx->addSheet('Resumo');
$shR->setColWidth(1, 32)->setColWidth(2, 18);

$r = 1;
$shR->writeCell($r, 1, 'ORAÇÕES & TESTEMUNHOS — NAIOT', 3);
$shR->writeCell($r, 2, '', 3);
$r++;
$shR->writeCell($r, 1, 'Gerado em: ' . $hoje->format('d/m/Y \à\s H:i'), 1);
$r += 2;

$shR->writeCell($r, 1, 'PEDIDOS DE ORAÇÃO', 2); $shR->writeCell($r, 2, '', 2); $r++;
$shR->writeCell($r, 1, 'Total',      9); $shR->writeCell($r, 2, count($oracoes), 0); $r++;
$shR->writeCell($r, 1, 'Pendentes',  9); $shR->writeCell($r, 2, count(array_filter($oracoes, fn($i) => $i['status']==='pendente')),  0); $r++;
$shR->writeCell($r, 1, 'Aprovados',  9); $shR->writeCell($r, 2, count(array_filter($oracoes, fn($i) => $i['status']==='aprovado')),  0); $r++;
$shR->writeCell($r, 1, 'Rejeitados', 9); $shR->writeCell($r, 2, count(array_filter($oracoes, fn($i) => $i['status']==='rejeitado')), 0); $r += 2;

$shR->writeCell($r, 1, 'TESTEMUNHOS', 2); $shR->writeCell($r, 2, '', 2); $r++;
$shR->writeCell($r, 1, 'Total',      9); $shR->writeCell($r, 2, count($testemunhos), 0); $r++;
$shR->writeCell($r, 1, 'Pendentes',  9); $shR->writeCell($r, 2, count(array_filter($testemunhos, fn($i) => $i['status']==='pendente')),  0); $r++;
$shR->writeCell($r, 1, 'Aprovados',  9); $shR->writeCell($r, 2, count(array_filter($testemunhos, fn($i) => $i['status']==='aprovado')),  0); $r++;
$shR->writeCell($r, 1, 'Rejeitados', 9); $shR->writeCell($r, 2, count(array_filter($testemunhos, fn($i) => $i['status']==='rejeitado')), 0);

$filename = 'oracoes-testemunhos-naiot-' . $hoje->format('Y-m-d') . '.xlsx';
$xlsx->download($filename);
