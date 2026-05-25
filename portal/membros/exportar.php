<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);
require_once dirname(__DIR__) . '/lib/XlsxWriter.php';

$pdo = db();
$hoje = new DateTime();

// ── Dados ─────────────────────────────────────────────────────────────────

// Membros com categorias concatenadas
$membros = $pdo->query("
    SELECT m.id, m.nome, m.sexo, m.estado_civil, m.telefone, m.data_nasc, m.endereco, m.bairro, m.cidade, m.ativo,
           GROUP_CONCAT(DISTINCT g.nome ORDER BY g.nome SEPARATOR ', ') AS grupos,
           GROUP_CONCAT(DISTINCT c.nome ORDER BY c.nome SEPARATOR ', ') AS cargos,
           GROUP_CONCAT(DISTINCT h.nome ORDER BY h.nome SEPARATOR ', ') AS habilidades,
           GROUP_CONCAT(DISTINCT p.nome ORDER BY p.nome SEPARATOR ', ') AS pastoreios
    FROM membros m
    LEFT JOIN membros_grupo_rel      gr ON gr.membro_id = m.id
    LEFT JOIN membros_grupos          g  ON g.id = gr.grupo_id
    LEFT JOIN membros_cargo_rel      cr ON cr.membro_id = m.id
    LEFT JOIN membros_cargos          c  ON c.id = cr.cargo_id
    LEFT JOIN membros_habilidade_rel hr ON hr.membro_id = m.id
    LEFT JOIN membros_habilidades     h  ON h.id = hr.habilidade_id
    LEFT JOIN membros_pastoreio_rel  pr ON pr.membro_id = m.id
    LEFT JOIN membros_pastoreio       p  ON p.id = pr.pastoreio_id
    WHERE m.ativo = 1
    GROUP BY m.id
    ORDER BY m.nome
")->fetchAll();

$total = count($membros);

// Grupos com total
$grupos = $pdo->query("
    SELECT g.nome, g.cor, COUNT(r.membro_id) AS total
    FROM membros_grupos g
    LEFT JOIN membros_grupo_rel r ON r.grupo_id = g.id
    GROUP BY g.id ORDER BY g.nome
")->fetchAll();

// Cargos com total
$cargos = $pdo->query("
    SELECT c.nome, c.cor, COUNT(r.membro_id) AS total
    FROM membros_cargos c
    LEFT JOIN membros_cargo_rel r ON r.cargo_id = c.id
    GROUP BY c.id ORDER BY c.nome
")->fetchAll();

// Habilidades com total
$habilidades = $pdo->query("
    SELECT h.nome, h.cor, COUNT(r.membro_id) AS total
    FROM membros_habilidades h
    LEFT JOIN membros_habilidade_rel r ON r.habilidade_id = h.id
    GROUP BY h.id ORDER BY h.nome
")->fetchAll();

// Pastoreio com total
$pastoreios = $pdo->query("
    SELECT p.nome, p.cor, COUNT(r.membro_id) AS total
    FROM membros_pastoreio p
    LEFT JOIN membros_pastoreio_rel r ON r.pastoreio_id = p.id
    GROUP BY p.id ORDER BY p.nome
")->fetchAll();

// Aniversariantes dos próximos 60 dias
$todos_nasc = $pdo->query("
    SELECT id, nome, data_nasc, DAY(data_nasc) AS dia, MONTH(data_nasc) AS mes
    FROM membros WHERE ativo=1 AND data_nasc IS NOT NULL
    ORDER BY MONTH(data_nasc), DAY(data_nasc)
")->fetchAll();

$aniversariantes = [];
foreach ($todos_nasc as $m) {
    try {
        $bday = DateTime::createFromFormat('Y-n-j', $hoje->format('Y') . '-' . $m['mes'] . '-' . $m['dia']);
        if ($bday === false) continue;
        if ($bday < $hoje) {
            $bday->modify('+1 year');
        }
        $diff = (int)$hoje->diff($bday)->days;
        if ($diff <= 60) {
            $m['dias'] = $diff;
            $m['bday_fmt'] = $m['dia'] . '/' . str_pad($m['mes'], 2, '0', STR_PAD_LEFT);
            $aniversariantes[] = $m;
        }
    } catch (\Exception $e) { /* ignora data inválida */ }
}
usort($aniversariantes, fn($a, $b) => $a['dias'] <=> $b['dias']);

// ── Montar Excel ──────────────────────────────────────────────────────────

$xlsx = new XlsxWriter();

// ═══════════════════════════════════════════════════════
// ABA 1 — DASHBOARD
// ═══════════════════════════════════════════════════════
$dash = $xlsx->addSheet('Dashboard');
$dash->setColWidth(1, 32)->setColWidth(2, 18)->setColWidth(3, 18)->setColWidth(4, 18);

$r = 1;
$dash->writeCell($r, 1, 'RELATÓRIO DE MEMBROS — NAIOT', 3);
$dash->writeCell($r, 2, '', 3)->writeCell($r, 3, '', 3)->writeCell($r, 4, '', 3);
$r++;
$dash->writeCell($r, 1, 'Gerado em: ' . $hoje->format('d/m/Y \à\s H:i'), 1);
$r += 2;

// Resumo geral
$dash->writeCell($r, 1, 'RESUMO GERAL', 2);
$dash->writeCell($r, 2, '', 2)->writeCell($r, 3, '', 2);
$r++;
$dash->writeCell($r, 1, 'Total de membros ativos', 9);
$dash->writeCell($r, 2, $total, 1);
$r++;
$masc = count(array_filter($membros, fn($m) => $m['sexo'] === 'Masculino'));
$fem  = count(array_filter($membros, fn($m) => $m['sexo'] === 'Feminino'));
$dash->writeCell($r, 1, '↳ Masculino', 9);
$dash->writeCell($r, 2, $masc, 0);
$r++;
$dash->writeCell($r, 1, '↳ Feminino', 9);
$dash->writeCell($r, 2, $fem, 0);
$r++;
$dash->writeCell($r, 1, 'Grupos cadastrados', 9);
$dash->writeCell($r, 2, count($grupos), 0);
$r++;
$dash->writeCell($r, 1, 'Cargos cadastrados', 9);
$dash->writeCell($r, 2, count($cargos), 0);
$r++;
$dash->writeCell($r, 1, 'Habilidades cadastradas', 9);
$dash->writeCell($r, 2, count($habilidades), 0);
$r++;
$dash->writeCell($r, 1, 'Pastoreio cadastrado', 9);
$dash->writeCell($r, 2, count($pastoreios), 0);
$r += 2;

// Membros por Grupo
if ($grupos) {
    $dash->writeCell($r, 1, 'MEMBROS POR GRUPO', 2);
    $dash->writeCell($r, 2, 'Total', 2)->writeCell($r, 3, '', 2);
    $r++;
    foreach ($grupos as $g) {
        $dash->writeCell($r, 1, $g['nome'], 9);
        $dash->writeCell($r, 2, (int)$g['total'], 0);
        $r++;
    }
    $r++;
}

// Membros por Cargo
if ($cargos) {
    $dash->writeCell($r, 1, 'MEMBROS POR CARGO', 4);
    $dash->writeCell($r, 2, 'Total', 4)->writeCell($r, 3, '', 4);
    $r++;
    foreach ($cargos as $c) {
        $dash->writeCell($r, 1, $c['nome'], 9);
        $dash->writeCell($r, 2, (int)$c['total'], 0);
        $r++;
    }
    $r++;
}

// Membros por Pastoreio
if ($pastoreios) {
    $dash->writeCell($r, 1, 'MEMBROS POR PASTOREIO', 7);
    $dash->writeCell($r, 2, 'Total', 7)->writeCell($r, 3, '', 7);
    $r++;
    foreach ($pastoreios as $p) {
        $dash->writeCell($r, 1, $p['nome'], 9);
        $dash->writeCell($r, 2, (int)$p['total'], 0);
        $r++;
    }
    $r++;
}

// Aniversariantes próximos 60 dias
$dash->writeCell($r, 1, 'ANIVERSARIANTES — PRÓXIMOS 60 DIAS', 2);
$dash->writeCell($r, 2, 'Data', 2)->writeCell($r, 3, 'Dias restantes', 2);
$r++;
if ($aniversariantes) {
    foreach ($aniversariantes as $a) {
        $label = $a['dias'] === 0 ? 'HOJE!' : ($a['dias'] === 1 ? 'amanhã' : 'em ' . $a['dias'] . ' dias');
        $dash->writeCell($r, 1, $a['nome'], $a['dias'] <= 7 ? 8 : 9);
        $dash->writeCell($r, 2, $a['bday_fmt'], $a['dias'] <= 7 ? 8 : 9);
        $dash->writeCell($r, 3, $label, $a['dias'] <= 7 ? 8 : 9);
        $r++;
    }
} else {
    $dash->writeCell($r, 1, 'Nenhum aniversariante nos próximos 60 dias.', 0);
    $r++;
}

// ═══════════════════════════════════════════════════════
// ABA 2 — MEMBROS
// ═══════════════════════════════════════════════════════
$mem = $xlsx->addSheet('Membros');
$mem->setColWidth(1, 30)->setColWidth(2, 14)->setColWidth(3, 22)
    ->setColWidth(4, 18)->setColWidth(5, 16)->setColWidth(6, 30)
    ->setColWidth(7, 20)->setColWidth(8, 20)->setColWidth(9, 28)
    ->setColWidth(10, 28)->setColWidth(11, 28)->setColWidth(12, 28);

$mem->writeRow(1, ['Nome', 'Sexo', 'Estado Civil', 'Telefone', 'Data Nasc.',
                   'Endereço', 'Bairro', 'Cidade',
                   'Grupos', 'Cargos', 'Habilidades', 'Pastoreio'], 2);

$rowNum = 2;
foreach ($membros as $m) {
    $style = ($rowNum % 2 === 0) ? 6 : 9;
    $dataNasc = '';
    if (!empty($m['data_nasc'])) {
        $d = DateTime::createFromFormat('Y-m-d', $m['data_nasc']);
        if ($d) $dataNasc = $d->format('d/m/Y');
    }
    $mem->writeRow($rowNum, [
        $m['nome'],
        $m['sexo']        ?? '',
        $m['estado_civil'] ?? '',
        $m['telefone']    ?? '',
        $dataNasc,
        $m['endereco']    ?? '',
        $m['bairro']      ?? '',
        $m['cidade']      ?? '',
        $m['grupos']      ?? '',
        $m['cargos']      ?? '',
        $m['habilidades'] ?? '',
        $m['pastoreios']  ?? '',
    ], $style);
    $rowNum++;
}
if ($rowNum === 2) {
    $mem->writeCell(2, 1, 'Nenhum membro cadastrado.', 0);
}

// ═══════════════════════════════════════════════════════
// ABA 3 — GRUPOS
// ═══════════════════════════════════════════════════════
$shGrupos = $xlsx->addSheet('Grupos');
$shGrupos->setColWidth(1, 30)->setColWidth(2, 12);
$shGrupos->writeRow(1, ['Grupo', 'Total de membros'], 2);
$r2 = 2;
foreach ($grupos as $g) {
    $style = ($r2 % 2 === 0) ? 6 : 9;
    $shGrupos->writeCell($r2, 1, $g['nome'], $style);
    $shGrupos->writeCell($r2, 2, (int)$g['total'], $style);
    $r2++;
}
if ($r2 === 2) $shGrupos->writeCell(2, 1, 'Nenhum grupo cadastrado.', 0);

// ═══════════════════════════════════════════════════════
// ABA 4 — CARGOS
// ═══════════════════════════════════════════════════════
$shCargos = $xlsx->addSheet('Cargos');
$shCargos->setColWidth(1, 30)->setColWidth(2, 12);
$shCargos->writeRow(1, ['Cargo', 'Total de membros'], 4);
$r2 = 2;
foreach ($cargos as $c) {
    $style = ($r2 % 2 === 0) ? 6 : 9;
    $shCargos->writeCell($r2, 1, $c['nome'], $style);
    $shCargos->writeCell($r2, 2, (int)$c['total'], $style);
    $r2++;
}
if ($r2 === 2) $shCargos->writeCell(2, 1, 'Nenhum cargo cadastrado.', 0);

// ═══════════════════════════════════════════════════════
// ABA 5 — HABILIDADES
// ═══════════════════════════════════════════════════════
$shHab = $xlsx->addSheet('Habilidades');
$shHab->setColWidth(1, 30)->setColWidth(2, 12);
$shHab->writeRow(1, ['Habilidade', 'Total de membros'], 10);
$r2 = 2;
foreach ($habilidades as $h) {
    $style = ($r2 % 2 === 0) ? 6 : 9;
    $shHab->writeCell($r2, 1, $h['nome'], $style);
    $shHab->writeCell($r2, 2, (int)$h['total'], $style);
    $r2++;
}
if ($r2 === 2) $shHab->writeCell(2, 1, 'Nenhuma habilidade cadastrada.', 0);

// ═══════════════════════════════════════════════════════
// ABA 6 — PASTOREIO
// ═══════════════════════════════════════════════════════
$shPast = $xlsx->addSheet('Pastoreio');
$shPast->setColWidth(1, 30)->setColWidth(2, 12);
$shPast->writeRow(1, ['Pastoreio', 'Total de membros'], 7);
$r2 = 2;
foreach ($pastoreios as $p) {
    $style = ($r2 % 2 === 0) ? 6 : 9;
    $shPast->writeCell($r2, 1, $p['nome'], $style);
    $shPast->writeCell($r2, 2, (int)$p['total'], $style);
    $r2++;
}
if ($r2 === 2) $shPast->writeCell(2, 1, 'Nenhum pastoreio cadastrado.', 0);

// ── Download ──────────────────────────────────────────────────────────────
$filename = 'membros-naiot-' . $hoje->format('Y-m-d') . '.xlsx';
$xlsx->download($filename);
