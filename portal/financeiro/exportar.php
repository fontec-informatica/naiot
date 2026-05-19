<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin','financeiro']);

$exp    = $_GET['exp']    ?? 'lancamentos'; // lancamentos | balanco
$mes    = (int)($_GET['mes']    ?? date('n'));
$ano    = (int)($_GET['ano']    ?? date('Y'));
$tipo   = $_GET['tipo']   ?? '';
$status = $_GET['status'] ?? '';
$cat_id = (int)($_GET['cat']   ?? 0);
$busca  = trim($_GET['q'] ?? '');
$mes = max(1,min(12,$mes)); $ano = max(2020,min(2099,$ano));

$meses_nomes = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$forma_labels = ['dinheiro'=>'Dinheiro','pix'=>'PIX','transferencia'=>'Transferência','boleto'=>'Boleto','cartao'=>'Cartão','cheque'=>'Cheque','outro'=>'Outro'];
$orig_labels  = ['doacao'=>'Doação','dizimo'=>'Dízimo','contribuicao'=>'Contribuição','inscricao'=>'Inscrição','mensalidade'=>'Mensalidade','outro'=>'Outro'];

// ── Busca lançamentos filtrados ──────────────────────────────────────────────
$where  = "WHERE l.competencia_mes=? AND l.competencia_ano=?";
$params = [$mes,$ano];
if ($tipo)   { $where .= " AND l.tipo=?";           $params[] = $tipo; }
if ($status) { $where .= " AND l.status=?";         $params[] = $status; }
if ($cat_id) { $where .= " AND l.categoria_id=?";   $params[] = $cat_id; }
if ($busca)  { $where .= " AND l.descricao LIKE ?";  $params[] = "%$busca%"; }

$sql = "SELECT l.*, c.nome as cat_nome FROM financeiro_lancamentos l
        LEFT JOIN financeiro_categorias c ON c.id=l.categoria_id
        $where ORDER BY l.tipo, l.data_lancamento, l.id";
$st = db()->prepare($sql); $st->execute($params);
$lancamentos = $st->fetchAll();

// ── Balanço por categoria ─────────────────────────────────────────────────────
$rec_por_cat = []; $desp_por_cat = [];
$total_rec = 0; $total_desp = 0;
foreach ($lancamentos as $l) {
    if ($l['status'] === 'cancelado') continue;
    if ($l['tipo'] === 'receita') {
        $k = $l['cat_nome'] ?? 'Sem categoria';
        $rec_por_cat[$k] = ($rec_por_cat[$k] ?? 0) + $l['valor'];
        $total_rec += $l['valor'];
    } else {
        $k = $l['cat_nome'] ?? 'Sem categoria';
        $desp_por_cat[$k] = ($desp_por_cat[$k] ?? 0) + $l['valor'];
        $total_desp += $l['valor'];
    }
}
arsort($rec_por_cat); arsort($desp_por_cat);

// ══════════════════════════════════════════════════════════════════════════════
//  Gerador XLSX (nativo — sem dependências externas)
// ══════════════════════════════════════════════════════════════════════════════

function _col($n) {
    $r = '';
    while ($n > 0) { $r = chr(64 + (($n - 1) % 26 + 1)) . $r; $n = intdiv($n - 1, 26); }
    return $r;
}

function _esc($v) { return htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

/**
 * Gera um arquivo XLSX em memória.
 *
 * $sheets = [
 *   [
 *     'title'      => 'Nome da aba',
 *     'headers'    => ['Col A', 'Col B', ...],
 *     'rows'       => [ [val, val, ...], ... ],
 *     'col_widths' => [20, 30, ...],   // opcional, em caracteres
 *     'num_cols'   => [2, 5],          // índices (0-based) com formato numérico
 *     'cur_cols'   => [5],             // índices (0-based) com formato monetário R$
 *   ]
 * ]
 */
function make_xlsx(array $sheets): string {
    $ns_pkg  = 'http://schemas.openxmlformats.org/package/2006/';
    $ns_xl   = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $ns_r    = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';

    // ── [Content_Types].xml ──────────────────────────────────────────────────
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="'.$ns_pkg.'content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml"  ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml"   ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    foreach (array_keys($sheets) as $i) {
        $ct .= '<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $ct .= '</Types>';

    // ── _rels/.rels ──────────────────────────────────────────────────────────
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="'.$ns_pkg.'relationships">'
          . '<Relationship Id="rId1" Type="'.$ns_r.'officeDocument" Target="xl/workbook.xml"/>'
          . '</Relationships>';

    // ── xl/workbook.xml ──────────────────────────────────────────────────────
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="'.$ns_xl.'" xmlns:r="'.$ns_r.'">'
        . '<sheets>';
    foreach ($sheets as $i => $sh) {
        $wb .= '<sheet name="'._esc($sh['title']).'" sheetId="'.($i+1).'" r:id="rId'.($i+1).'"/>';
    }
    $wb .= '</sheets></workbook>';

    // ── xl/_rels/workbook.xml.rels ───────────────────────────────────────────
    $wbr = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<Relationships xmlns="'.$ns_pkg.'relationships">';
    foreach (array_keys($sheets) as $i) {
        $wbr .= '<Relationship Id="rId'.($i+1).'" Type="'.$ns_r.'worksheet" Target="worksheets/sheet'.($i+1).'.xml"/>';
    }
    $wbr .= '<Relationship Id="rId'.(count($sheets)+1).'" Type="'.$ns_r.'styles" Target="styles.xml"/>';
    $wbr .= '</Relationships>';

    // ── xl/styles.xml ────────────────────────────────────────────────────────
    // xf index: 0=normal, 1=bold(header), 2=currency, 3=number
    $sty = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<styleSheet xmlns="'.$ns_xl.'">'
         . '<numFmts count="2">'
         .   '<numFmt numFmtId="164" formatCode="&quot;R$&quot;\ #,##0.00"/>'
         .   '<numFmt numFmtId="165" formatCode="#,##0.00"/>'
         . '</numFmts>'
         . '<fonts count="3">'
         .   '<font><sz val="10"/><name val="Calibri"/></font>'
         .   '<font><b/><sz val="10"/><name val="Calibri"/></font>'
         .   '<font><b/><sz val="11"/><color rgb="FF163D22"/><name val="Calibri"/></font>'
         . '</fonts>'
         . '<fills count="3">'
         .   '<fill><patternFill patternType="none"/></fill>'
         .   '<fill><patternFill patternType="gray125"/></fill>'
         .   '<fill><patternFill patternType="solid"><fgColor rgb="FFE8F5E9"/></patternFill></fill>'
         . '</fills>'
         . '<borders count="2">'
         .   '<border><left/><right/><top/><bottom/><diagonal/></border>'
         .   '<border><left style="thin"><color rgb="FFD0D0D0"/></left>'
         .     '<right style="thin"><color rgb="FFD0D0D0"/></right>'
         .     '<top style="thin"><color rgb="FFD0D0D0"/></top>'
         .     '<bottom style="thin"><color rgb="FFD0D0D0"/></bottom>'
         .     '<diagonal/></border>'
         . '</borders>'
         . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
         . '<cellXfs count="5">'
         .   '<xf numFmtId="0"   fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'        // 0: normal
         .   '<xf numFmtId="0"   fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>' // 1: bold header verde
         .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'       // 2: moeda R$
         .   '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'       // 3: número
         .   '<xf numFmtId="0"   fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'           // 4: título
         . '</cellXfs>'
         . '</styleSheet>';

    // ── Worksheets ───────────────────────────────────────────────────────────
    $ws_xmls = [];
    foreach ($sheets as $sh) {
        $num_cols = $sh['num_cols'] ?? [];
        $cur_cols = $sh['cur_cols'] ?? [];
        $widths   = $sh['col_widths'] ?? [];

        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
              . '<worksheet xmlns="'.$ns_xl.'" xmlns:r="'.$ns_r.'">';

        if ($widths) {
            $xml .= '<cols>';
            foreach ($widths as $ci => $w) {
                $xml .= '<col min="'.($ci+1).'" max="'.($ci+1).'" width="'.$w.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        $rn = 1;

        // Title row (optional)
        if (!empty($sh['report_title'])) {
            $ncols = max(count($sh['headers'] ?? [1]), 1);
            $xml .= '<row r="'.$rn.'">'
                  . '<c r="A'.$rn.'" t="inlineStr" s="4"><is><t>'._esc($sh['report_title']).'</t></is></c>'
                  . '</row>';
            $rn++;
        }

        // Headers
        if (!empty($sh['headers'])) {
            $xml .= '<row r="'.$rn.'">';
            foreach ($sh['headers'] as $ci => $h) {
                $xml .= '<c r="'._col($ci+1).$rn.'" t="inlineStr" s="1"><is><t>'._esc($h).'</t></is></c>';
            }
            $xml .= '</row>';
            $rn++;
        }

        // Data
        foreach ($sh['rows'] as $row) {
            $xml .= '<row r="'.$rn.'">';
            foreach (array_values($row) as $ci => $val) {
                $ref = _col($ci+1).$rn;
                if (in_array($ci, $cur_cols) && is_numeric($val)) {
                    $xml .= '<c r="'.$ref.'" s="2"><v>'.(float)$val.'</v></c>';
                } elseif (in_array($ci, $num_cols) && is_numeric($val)) {
                    $xml .= '<c r="'.$ref.'" s="3"><v>'.(float)$val.'</v></c>';
                } elseif (is_numeric($val) && !is_string($val)) {
                    $xml .= '<c r="'.$ref.'" s="0"><v>'.$val.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" t="inlineStr" s="0"><is><t>'._esc($val).'</t></is></c>';
                }
            }
            $xml .= '</row>';
            $rn++;
        }

        $xml .= '</sheetData>';

        // AutoFilter
        if (!empty($sh['headers']) && !empty($sh['rows'])) {
            $last_col = _col(count($sh['headers']));
            $header_row = !empty($sh['report_title']) ? 2 : 1;
            $xml .= '<autoFilter ref="A'.$header_row.':'.$last_col.$header_row.'"/>';
        }

        $xml .= '</worksheet>';
        $ws_xmls[] = $xml;
    }

    // ── Montar ZIP ───────────────────────────────────────────────────────────
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Não foi possível criar o arquivo XLSX.');
    }
    $zip->addFromString('[Content_Types].xml',       $ct);
    $zip->addFromString('_rels/.rels',               $rels);
    $zip->addFromString('xl/workbook.xml',           $wb);
    $zip->addFromString('xl/_rels/workbook.xml.rels',$wbr);
    $zip->addFromString('xl/styles.xml',             $sty);
    foreach ($ws_xmls as $i => $ws_xml) {
        $zip->addFromString('xl/worksheets/sheet'.($i+1).'.xml', $ws_xml);
    }
    $zip->close();
    $data = file_get_contents($tmp);
    @unlink($tmp);
    return $data;
}

// ══════════════════════════════════════════════════════════════════════════════
//  Montar planilhas conforme tipo de exportação
// ══════════════════════════════════════════════════════════════════════════════

$periodo_label = $meses_nomes[$mes] . ' ' . $ano;

if ($exp === 'balanco') {

    // ── Aba 1: DRE ──────────────────────────────────────────────────────────
    $dre_rows = [];
    $dre_rows[] = ['RECEITAS', '', '', ''];
    foreach ($rec_por_cat as $cat => $val) {
        $pct = $total_rec > 0 ? round($val / $total_rec * 100, 2) : 0;
        $dre_rows[] = ['  ' . $cat, 'Receita', $val, $pct];
    }
    $dre_rows[] = ['TOTAL RECEITAS', '', $total_rec, ''];
    $dre_rows[] = ['', '', '', ''];
    $dre_rows[] = ['DESPESAS', '', '', ''];
    foreach ($desp_por_cat as $cat => $val) {
        $pct = $total_desp > 0 ? round($val / $total_desp * 100, 2) : 0;
        $dre_rows[] = ['  ' . $cat, 'Despesa', $val, $pct];
    }
    $dre_rows[] = ['TOTAL DESPESAS', '', $total_desp, ''];
    $dre_rows[] = ['', '', '', ''];
    $saldo = $total_rec - $total_desp;
    $dre_rows[] = ['RESULTADO DO MÊS', '', $saldo, $total_rec > 0 ? round($saldo / $total_rec * 100, 2) : 0];

    $sheets = [
        [
            'title'        => 'DRE',
            'report_title' => 'Balanço Gerencial — ' . $periodo_label,
            'headers'      => ['Categoria', 'Tipo', 'Valor (R$)', '% do Total'],
            'rows'         => $dre_rows,
            'cur_cols'     => [2],
            'num_cols'     => [3],
            'col_widths'   => [38, 12, 18, 14],
        ],
        [
            'title'        => 'Lançamentos',
            'report_title' => 'Lançamentos — ' . $periodo_label,
            'headers'      => ['Data', 'Descrição', 'Categoria', 'Tipo', 'Origem', 'Forma', 'Valor (R$)', 'Status', 'Observações'],
            'rows'         => array_map(fn($l) => [
                date('d/m/Y', strtotime($l['data_lancamento'])),
                $l['descricao'],
                $l['cat_nome'] ?? '—',
                ucfirst($l['tipo']),
                $l['origem'] ? ($orig_labels[$l['origem']] ?? $l['origem']) : '—',
                $forma_labels[$l['forma_pagamento']] ?? ucfirst($l['forma_pagamento']),
                (float)$l['valor'],
                ucfirst($l['status']),
                $l['observacoes'] ?? '',
            ], $lancamentos),
            'cur_cols'     => [6],
            'col_widths'   => [12, 36, 22, 10, 16, 14, 16, 12, 30],
        ],
    ];

    $filename = 'balanco_' . $ano . '_' . str_pad($mes,2,'0',STR_PAD_LEFT) . '.xlsx';

} else {

    // ── Lançamentos ──────────────────────────────────────────────────────────
    $sheets = [
        [
            'title'        => 'Lançamentos',
            'report_title' => 'Lançamentos — ' . $periodo_label,
            'headers'      => ['Data', 'Descrição', 'Categoria', 'Tipo', 'Origem', 'Forma Pagamento', 'Valor (R$)', 'Status', 'Observações'],
            'rows'         => array_map(fn($l) => [
                date('d/m/Y', strtotime($l['data_lancamento'])),
                $l['descricao'],
                $l['cat_nome'] ?? '—',
                ucfirst($l['tipo']),
                $l['origem'] ? ($orig_labels[$l['origem']] ?? $l['origem']) : '—',
                $forma_labels[$l['forma_pagamento']] ?? ucfirst($l['forma_pagamento']),
                (float)$l['valor'],
                ucfirst($l['status']),
                $l['observacoes'] ?? '',
            ], $lancamentos),
            'cur_cols'     => [6],
            'col_widths'   => [12, 36, 22, 10, 16, 18, 16, 12, 30],
        ],
        [
            'title'   => 'Totais',
            'headers' => ['', 'Valor (R$)'],
            'rows'    => [
                ['Receitas realizadas', $total_rec],
                ['Despesas realizadas', $total_desp],
                ['Saldo',              $total_rec - $total_desp],
                ['Total de registros', count($lancamentos)],
            ],
            'cur_cols'   => [1],
            'col_widths' => [28, 18],
        ],
    ];

    $filename = 'lancamentos_' . $ano . '_' . str_pad($mes,2,'0',STR_PAD_LEFT) . '.xlsx';
}

// ── Enviar arquivo ────────────────────────────────────────────────────────────
$xlsx = make_xlsx($sheets);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($xlsx));
header('Cache-Control: private, max-age=0');
header('Pragma: no-cache');
echo $xlsx;
exit;
