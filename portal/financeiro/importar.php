<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin','financeiro']);
require_once dirname(__DIR__) . '/lib/XlsxWriter.php';
require_once dirname(__DIR__) . '/lib/XlsxReader.php';

$pdo = db();
$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));
$mes = max(1, min(12, $mes));
$ano = max(2020, min(2099, $ano));

$formas_validas  = ['dinheiro','pix','transferencia','boleto','cartao','cheque','outro'];
$status_validos  = ['realizado','pendente','cancelado'];
$tipos_validos   = ['receita','despesa'];

// ── Gerar planilha modelo (GET ?modelo=1) ────────────────────────────────
if (($_GET['modelo'] ?? '') === '1') {
    $cats_rec  = $pdo->query("SELECT nome FROM financeiro_categorias WHERE tipo='receita' AND ativo=1 ORDER BY ordem,nome")->fetchAll(PDO::FETCH_COLUMN);
    $cats_desp = $pdo->query("SELECT nome FROM financeiro_categorias WHERE tipo='despesa' AND ativo=1 ORDER BY ordem,nome")->fetchAll(PDO::FETCH_COLUMN);

    $xlsx = new XlsxWriter();

    // ── Aba 1: Instruções ────────────────────────────────────────────────
    $inst = $xlsx->addSheet('Instrucoes');
    $inst->setColWidth(1, 18)->setColWidth(2, 60);
    $inst->writeCell(1, 1, 'INSTRUÇÕES — IMPORTAÇÃO DE LANÇAMENTOS', 3);
    $inst->writeCell(1, 2, '', 3);
    $r = 3;
    $passos = [
        ['Passo 1', 'Vá para a aba "Importar Lancamentos"'],
        ['Passo 2', 'Preencha os dados a partir da linha 3 (linha 2 é exemplo — pode apagar)'],
        ['Passo 3', 'Os campos DATA, DESCRIÇÃO, TIPO, CATEGORIA e VALOR são obrigatórios'],
        ['Passo 4', 'DATA deve estar no formato DD/MM/AAAA (ex: 15/03/2025)'],
        ['Passo 5', 'TIPO deve ser exatamente: receita  ou  despesa'],
        ['Passo 6', 'CATEGORIA deve usar os nomes das abas de referência ou um nome novo (será criado)'],
        ['Passo 7', 'VALOR use ponto ou vírgula como separador decimal (ex: 150,00 ou 150.00)'],
        ['Passo 8', 'FORMA DE PAGAMENTO válidas: dinheiro, pix, transferencia, boleto, cartao, cheque, outro'],
        ['Passo 9', 'STATUS válidos: realizado (padrão), pendente, cancelado'],
        ['Passo 10', 'COMPETÊNCIA: se deixado em branco, usa o mês e ano da data do lançamento'],
        ['Atenção',  'Não altere o nome da aba "Importar Lancamentos" nem a ordem das colunas'],
    ];
    foreach ($passos as [$label, $desc]) {
        $inst->writeCell($r, 1, $label, 1);
        $inst->writeCell($r, 2, $desc, 0);
        $r++;
    }

    // ── Aba 2: Importar Lançamentos ──────────────────────────────────────
    $imp = $xlsx->addSheet('Importar Lancamentos');
    $imp->setColWidth(1, 16)->setColWidth(2, 34)->setColWidth(3, 13)
        ->setColWidth(4, 24)->setColWidth(5, 14)->setColWidth(6, 18)
        ->setColWidth(7, 14)->setColWidth(8, 14)->setColWidth(9, 12)->setColWidth(10, 28);

    $imp->writeRow(1, [
        'Data * (DD/MM/AAAA)',
        'Descrição *',
        'Tipo * (receita/despesa)',
        'Categoria *',
        'Valor * (R$)',
        'Forma de Pagamento',
        'Status',
        'Competência Mês (1-12)',
        'Competência Ano',
        'Observações',
    ], 2);

    // Linha 2: Exemplo
    $imp->writeRow(2, [
        '15/05/2025',
        'Dízimos — culto domingo (EXEMPLO, apague)',
        'receita',
        !empty($cats_rec) ? $cats_rec[0] : 'Dízimos',
        '350,00',
        'pix',
        'realizado',
        '5',
        '2025',
        'Oferta da manhã',
    ], 8);

    // Linhas em branco formatadas
    for ($i = 3; $i <= 102; $i++) {
        $style = ($i % 2 === 0) ? 6 : 9;
        for ($c = 1; $c <= 10; $c++) $imp->writeCell($i, $c, '', $style);
    }

    // ── Abas de referência ───────────────────────────────────────────────
    // Categorias de Receita
    $shRec = $xlsx->addSheet('Categorias Receita');
    $shRec->setColWidth(1, 35);
    $shRec->writeCell(1, 1, 'CATEGORIAS — RECEITA', 2);
    $r2 = 2;
    foreach ($cats_rec as $nome) {
        $shRec->writeCell($r2, 1, $nome, ($r2 % 2 === 0) ? 6 : 9); $r2++;
    }
    if ($r2 === 2) $shRec->writeCell(2, 1, '(nenhuma cadastrada ainda)', 0);

    // Categorias de Despesa
    $shDesp = $xlsx->addSheet('Categorias Despesa');
    $shDesp->setColWidth(1, 35);
    $shDesp->writeCell(1, 1, 'CATEGORIAS — DESPESA', 4);
    $r2 = 2;
    foreach ($cats_desp as $nome) {
        $shDesp->writeCell($r2, 1, $nome, ($r2 % 2 === 0) ? 6 : 9); $r2++;
    }
    if ($r2 === 2) $shDesp->writeCell(2, 1, '(nenhuma cadastrada ainda)', 0);

    // Formas de pagamento
    $shFormas = $xlsx->addSheet('Formas de Pagamento');
    $shFormas->setColWidth(1, 20)->setColWidth(2, 20);
    $shFormas->writeRow(1, ['Código (use na planilha)', 'Nome'], 2);
    $r2 = 2;
    foreach (['dinheiro'=>'Dinheiro','pix'=>'PIX','transferencia'=>'Transferência','boleto'=>'Boleto','cartao'=>'Cartão','cheque'=>'Cheque','outro'=>'Outro'] as $cod => $nome) {
        $style = ($r2 % 2 === 0) ? 6 : 9;
        $shFormas->writeCell($r2, 1, $cod, $style);
        $shFormas->writeCell($r2, 2, $nome, $style);
        $r2++;
    }

    $xlsx->download('modelo-importacao-financeiro.xlsx');
}

// ── Processar importação (POST) ──────────────────────────────────────────
$resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $erros   = [];
    $criados = 0;
    $ignorados = 0;
    $erros_linha = [];

    if (empty($_FILES['planilha']['tmp_name'])) {
        $erros[] = 'Nenhum arquivo enviado.';
    } else {
        $tmp = $_FILES['planilha']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['planilha']['name'], PATHINFO_EXTENSION));

        if ($ext !== 'xlsx') {
            $erros[] = 'Apenas arquivos .xlsx são aceitos.';
        } else {
            try {
                $reader = new XlsxReader();
                $rows   = $reader->readSheet($tmp, 1); // aba "Importar Lancamentos"

                if (empty($rows)) {
                    $erros[] = 'Planilha vazia ou aba "Importar Lancamentos" não encontrada.';
                } else {
                    $cacheCategoria = [];

                    $fnGetOuCriaCategoria = function(string $nome, string $tipo) use ($pdo, &$cacheCategoria): int {
                        $key = strtolower($tipo) . '|' . strtolower(trim($nome));
                        if (isset($cacheCategoria[$key])) return $cacheCategoria[$key];
                        $st = $pdo->prepare("SELECT id FROM financeiro_categorias WHERE nome=? AND tipo=?");
                        $st->execute([trim($nome), $tipo]);
                        $id = $st->fetchColumn();
                        if (!$id) {
                            $pdo->prepare("INSERT INTO financeiro_categorias (nome,tipo,cor,ativo) VALUES (?,?,'#6b7280',1)")->execute([trim($nome), $tipo]);
                            $id = (int)$pdo->lastInsertId();
                        }
                        return $cacheCategoria[$key] = (int)$id;
                    };

                    foreach ($rows as $i => $row) {
                        $numLinha = $i + 1;
                        if ($i === 0) continue; // cabeçalho

                        $raw_data    = trim($row[0] ?? '');
                        $descricao   = trim($row[1] ?? '');
                        $tipo        = strtolower(trim($row[2] ?? ''));
                        $cat_nome    = trim($row[3] ?? '');
                        $raw_valor   = trim($row[4] ?? '');

                        // Pula linha do exemplo ou em branco
                        if (!$descricao || str_contains(strtolower($descricao), 'exemplo')) {
                            $ignorados++;
                            continue;
                        }

                        // Validações
        $linha_erros = [];
                        if (!$raw_data) { $linha_erros[] = 'data vazia'; }
                        if (!in_array($tipo, $tipos_validos)) { $linha_erros[] = "tipo inválido: '$tipo'"; }
                        if (!$cat_nome)  { $linha_erros[] = 'categoria vazia'; }
                        if (!$raw_valor) { $linha_erros[] = 'valor vazio'; }

                        if ($linha_erros) {
                            $erros_linha[] = "Linha $numLinha: " . implode('; ', $linha_erros);
                            continue;
                        }

                        // Parse data DD/MM/AAAA
                        $data_lancamento = null;
                        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw_data, $dm)) {
                            if (checkdate((int)$dm[2], (int)$dm[1], (int)$dm[3])) {
                                $data_lancamento = sprintf('%04d-%02d-%02d', $dm[3], $dm[2], $dm[1]);
                            }
                        }
                        if (!$data_lancamento) {
                            $erros_linha[] = "Linha $numLinha: data inválida '$raw_data' (use DD/MM/AAAA)";
                            continue;
                        }

                        // Parse valor — aceita "150,00", "150.00", "1.234,56" e "1,234.56"
                        $raw_valor = str_replace(['R$','r$',' '], '', $raw_valor);
                        $tem_virgula = str_contains($raw_valor, ',');
                        $tem_ponto   = str_contains($raw_valor, '.');
                        if ($tem_virgula && $tem_ponto) {
                            // o separador mais à direita é o decimal; o outro é milhar
                            if (strrpos($raw_valor, ',') > strrpos($raw_valor, '.')) {
                                $raw_valor = str_replace('.', '', $raw_valor);
                                $raw_valor = str_replace(',', '.', $raw_valor);
                            } else {
                                $raw_valor = str_replace(',', '', $raw_valor);
                            }
                        } elseif ($tem_virgula) {
                            $raw_valor = str_replace(',', '.', $raw_valor);
                        }
                        // só ponto (ou nenhum separador): já está no formato correto
                        $valor = (float)$raw_valor;
                        if ($valor <= 0) {
                            $erros_linha[] = "Linha $numLinha: valor inválido ou zero";
                            continue;
                        }

                        // Campos opcionais
                        $forma  = strtolower(trim($row[5] ?? ''));
                        if (!in_array($forma, $formas_validas)) $forma = 'dinheiro';
                        $status_val = strtolower(trim($row[6] ?? ''));
                        if (!in_array($status_val, $status_validos)) $status_val = 'realizado';

                        // Competência
                        $comp_mes_raw = trim($row[7] ?? '');
                        $comp_ano_raw = trim($row[8] ?? '');
                        $comp_mes = $comp_mes_raw !== '' ? (int)$comp_mes_raw : (int)substr($data_lancamento, 5, 2);
                        $comp_ano = $comp_ano_raw !== '' ? (int)$comp_ano_raw : (int)substr($data_lancamento, 0, 4);
                        $comp_mes = max(1, min(12, $comp_mes));
                        $comp_ano = max(2000, min(2099, $comp_ano));

                        $obs = trim($row[9] ?? '') ?: null;

                        // Categoria
                        $cat_id = $fnGetOuCriaCategoria($cat_nome, $tipo);

                        $pdo->prepare("
                            INSERT INTO financeiro_lancamentos
                              (tipo, categoria_id, descricao, valor, data_lancamento,
                               competencia_mes, competencia_ano, status, forma_pagamento,
                               observacoes, usuario_id)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?)
                        ")->execute([
                            $tipo, $cat_id, $descricao, $valor, $data_lancamento,
                            $comp_mes, $comp_ano, $status_val, $forma,
                            $obs, $_SESSION['usuario_id'] ?? null,
                        ]);
                        $criados++;
                    }
                }
            } catch (\Exception $e) {
                $erros[] = 'Erro ao processar planilha: ' . $e->getMessage();
            }
        }
    }

    $resultado = compact('erros', 'criados', 'ignorados', 'erros_linha');
}

// ── Interface ────────────────────────────────────────────────────────────
$titulo       = 'Importar Lançamentos';
$pagina_ativa = 'financeiro';
include dirname(__DIR__) . '/_layout.php';
?>
<style>
.imp-card{background:#fff;border:1px solid var(--border);border-radius:var(--rl);max-width:660px;margin:0 auto;overflow:hidden}
.imp-head{padding:20px 24px 16px;border-bottom:1px solid var(--border);background:var(--off)}
.imp-body{padding:24px}
.imp-titulo{font-family:'Cinzel',serif;font-size:.9rem;font-weight:700;color:var(--green-dk);letter-spacing:.05em;text-transform:uppercase}
.imp-sub{font-size:.78rem;color:var(--muted);margin-top:4px}
.imp-steps{list-style:none;padding:0;margin:0 0 24px;display:flex;flex-direction:column;gap:10px}
.imp-steps li{display:flex;gap:12px;align-items:flex-start;font-size:.84rem;color:var(--txt)}
.imp-step-num{width:22px;height:22px;border-radius:50%;background:var(--green);color:#fff;font-size:.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.imp-dropzone{border:2px dashed var(--border);border-radius:12px;padding:36px 20px;text-align:center;cursor:pointer;transition:border-color var(--ease),background var(--ease);margin-bottom:16px}
.imp-dropzone:hover,.imp-dropzone.over{border-color:var(--green);background:var(--green-pale)}
.imp-dropzone input[type=file]{display:none}
.imp-dropzone-icon{font-size:2rem;margin-bottom:8px}
.imp-dropzone p{font-size:.83rem;color:var(--muted);margin:0}
.imp-dropzone strong{display:block;font-size:.88rem;color:var(--txt);margin-bottom:4px}
.imp-nome-arquivo{font-size:.8rem;color:var(--green-dk);margin-top:8px;font-weight:600}
.resultado-ok{background:#e8f5ed;border:1px solid #b7dfc4;border-radius:10px;padding:16px 20px;margin-bottom:20px}
.resultado-erro{background:#fde8e8;border:1px solid #f5b7b7;border-radius:10px;padding:16px 20px;margin-bottom:20px}
.resultado-ok h4,.resultado-erro h4{font-size:.88rem;font-weight:700;margin:0 0 8px}
.resultado-ok h4{color:#1e6b35}.resultado-erro h4{color:#b91c1c}
.resultado-ok p,.resultado-erro p,.resultado-erro ul,.resultado-aviso{font-size:.82rem;margin:0}
.resultado-aviso{background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;margin-top:12px}
.resultado-aviso h5{font-size:.82rem;font-weight:700;color:#854d0e;margin:0 0 6px}
.resultado-aviso ul{padding-left:14px;margin:0;color:#78350f}
@media(max-width:640px){.imp-body{padding:16px}}
</style>

<!-- Navegação igual a lancamentos.php -->
<div class="fin-nav">
  <a href="/portal/financeiro/?mes=<?= $mes ?>&ano=<?= $ano ?>">Dashboard</a>
  <div style="color:var(--border)">|</div>
  <a href="/portal/financeiro/lancamentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>">Lançamentos</a>
  <a href="/portal/financeiro/recorrentes.php">Recorrentes</a>
  <a href="/portal/financeiro/balanco.php?mes=<?= $mes ?>&ano=<?= $ano ?>">Balanço</a>
  <span style="margin-left:auto">Importar</span>
</div>

<div style="max-width:760px;margin:24px auto 0;padding:0 4px">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;flex-wrap:wrap">
    <div style="flex:1;min-width:0">
      <div style="font-family:'Cinzel',serif;font-size:.85rem;font-weight:700;color:var(--green-dk);text-transform:uppercase;letter-spacing:.06em">Importar Lançamentos</div>
      <div style="font-size:.76rem;color:var(--muted);margin-top:2px">Importe lançamentos financeiros em lote a partir de uma planilha Excel (.xlsx)</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="/portal/financeiro/importar.php?modelo=1&mes=<?= $mes ?>&ano=<?= $ano ?>" class="btn btn-ouro btn-sm">↓ Baixar planilha modelo</a>
      <a href="/portal/financeiro/lancamentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>" class="btn btn-ghost btn-sm">← Voltar</a>
    </div>
  </div>

  <?php if ($resultado): ?>
    <?php if (!empty($resultado['erros'])): ?>
    <div class="resultado-erro">
      <h4>Erro na importação</h4>
      <ul><?php foreach ($resultado['erros'] as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php else: ?>
    <div class="resultado-ok">
      <h4>Importação concluída!</h4>
      <p>
        <strong><?= $resultado['criados'] ?></strong> lançamento<?= $resultado['criados'] !== 1 ? 's' : '' ?> importado<?= $resultado['criados'] !== 1 ? 's' : '' ?> com sucesso.
        <?php if ($resultado['ignorados']): ?>
          <?= $resultado['ignorados'] ?> linha<?= $resultado['ignorados'] !== 1 ? 's' : '' ?> ignorada<?= $resultado['ignorados'] !== 1 ? 's' : '' ?>.
        <?php endif; ?>
      </p>
      <?php if ($resultado['criados'] > 0): ?>
      <p style="margin-top:8px"><a href="/portal/financeiro/lancamentos.php?mes=<?= $mes ?>&ano=<?= $ano ?>" style="color:var(--green);font-weight:600">Ver lançamentos →</a></p>
      <?php endif; ?>
    </div>
    <?php if (!empty($resultado['erros_linha'])): ?>
    <div class="resultado-aviso">
      <h5>Linhas com problema (ignoradas):</h5>
      <ul><?php foreach ($resultado['erros_linha'] as $el): ?><li><?= htmlspecialchars($el) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>

  <div class="imp-card">
    <div class="imp-head">
      <div class="imp-titulo">Como importar</div>
      <div class="imp-sub">Siga os passos abaixo para importar lançamentos em lote</div>
    </div>
    <div class="imp-body">
      <ol class="imp-steps">
        <li><span class="imp-step-num">1</span><span>Baixe a <a href="/portal/financeiro/importar.php?modelo=1" style="color:var(--green);font-weight:600">planilha modelo</a> — ela já inclui as categorias e formas de pagamento disponíveis como referência.</span></li>
        <li><span class="imp-step-num">2</span><span>Preencha os dados na aba <strong>"Importar Lancamentos"</strong> a partir da linha 3. Campos obrigatórios: Data, Descrição, Tipo, Categoria e Valor.</span></li>
        <li><span class="imp-step-num">3</span><span>Categorias não encontradas serão criadas automaticamente com a cor padrão — ajuste depois em Categorias.</span></li>
        <li><span class="imp-step-num">4</span><span>Salve o arquivo como <strong>.xlsx</strong> e faça upload abaixo.</span></li>
      </ol>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="imp-dropzone" id="dropzone" onclick="document.getElementById('arquivo').click()">
          <div class="imp-dropzone-icon">📊</div>
          <strong>Clique para selecionar o arquivo</strong>
          <p>ou arraste e solte aqui</p>
          <p>Apenas arquivos <strong>.xlsx</strong></p>
          <div class="imp-nome-arquivo" id="nome-arquivo"></div>
          <input type="file" name="planilha" id="arquivo" accept=".xlsx" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%">↑ Importar lançamentos</button>
      </form>
    </div>
  </div>
</div>

<script>
const arquivo  = document.getElementById('arquivo');
const nomeArq  = document.getElementById('nome-arquivo');
const dropzone = document.getElementById('dropzone');
arquivo.addEventListener('change', () => { nomeArq.textContent = arquivo.files[0] ? arquivo.files[0].name : ''; });
dropzone.addEventListener('dragover', e  => { e.preventDefault(); dropzone.classList.add('over'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('over'));
dropzone.addEventListener('drop', e => {
  e.preventDefault(); dropzone.classList.remove('over');
  if (e.dataTransfer.files[0]) { arquivo.files = e.dataTransfer.files; nomeArq.textContent = e.dataTransfer.files[0].name; }
});
</script>
<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
