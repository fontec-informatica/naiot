<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);
require_once dirname(__DIR__) . '/lib/XlsxWriter.php';
require_once dirname(__DIR__) . '/lib/XlsxReader.php';

$pdo = db();

// ── Gerar planilha modelo (GET ?modelo=1) ────────────────────────────────
if (($_GET['modelo'] ?? '') === '1') {
    $grupos      = $pdo->query("SELECT nome FROM membros_grupos ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
    $cargos      = $pdo->query("SELECT nome FROM membros_cargos ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
    $habilidades = $pdo->query("SELECT nome FROM membros_habilidades ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
    $pastoreios  = $pdo->query("SELECT nome FROM membros_pastoreio ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);

    $xlsx = new XlsxWriter();

    // ── Aba 1: Instruções ────────────────────────────────────────────────
    $inst = $xlsx->addSheet('Instrucoes');
    $inst->setColWidth(1, 16)->setColWidth(2, 60);

    $inst->writeCell(1, 1, 'INSTRUÇÕES DE IMPORTAÇÃO', 3);
    $inst->writeCell(1, 2, '', 3);
    $r = 3;
    $passos = [
        ['Passo 1', 'Vá para a aba "Importar Membros"'],
        ['Passo 2', 'Preencha os dados a partir da linha 3 (linha 2 é exemplo — pode apagar)'],
        ['Passo 3', 'O campo NOME é obrigatório. Os demais são opcionais'],
        ['Passo 4', 'Data de Nascimento deve estar no formato DD/MM/AAAA (ex: 15/03/1990)'],
        ['Passo 5', 'Para Grupos, Cargos, Habilidades e Pastoreio: use os nomes EXATAMENTE como nas abas de referência'],
        ['Passo 6', 'Para atribuir mais de um grupo/cargo/habilidade/pastoreio, separe por vírgula (ex: Grupo A, Grupo B)'],
        ['Passo 7', 'Categorias não encontradas serão criadas automaticamente'],
        ['Passo 8', 'Salve este arquivo normalmente (.xlsx) e faça o upload na página de Importação'],
        ['Atenção',  'Não altere o nome da aba "Importar Membros" nem a ordem das colunas'],
    ];
    foreach ($passos as [$label, $desc]) {
        $inst->writeCell($r, 1, $label, 1);
        $inst->writeCell($r, 2, $desc, 0);
        $r++;
    }

    // ── Aba 2: Importar Membros ──────────────────────────────────────────
    $imp = $xlsx->addSheet('Importar Membros');
    $imp->setColWidth(1, 30)->setColWidth(2, 18)->setColWidth(3, 16)
        ->setColWidth(4, 30)->setColWidth(5, 20)->setColWidth(6, 20)
        ->setColWidth(7, 28)->setColWidth(8, 28)->setColWidth(9, 28)->setColWidth(10, 28);

    // Linha 1: Cabeçalho
    $imp->writeRow(1, [
        'Nome *',
        'Telefone',
        'Data Nasc. (DD/MM/AAAA)',
        'Endereço',
        'Bairro',
        'Cidade',
        'Grupos (separar por vírgula)',
        'Cargos (separar por vírgula)',
        'Habilidades (separar por vírgula)',
        'Pastoreio (separar por vírgula)',
    ], 2);

    // Linha 2: Exemplo (estilo diferente para indicar que é exemplo)
    $imp->writeRow(2, [
        'Maria da Silva (exemplo — apague esta linha)',
        '(11) 99999-0000',
        '15/03/1990',
        'Rua das Flores, 123',
        'Centro',
        'São Paulo',
        !empty($grupos) ? $grupos[0] : 'Grupo A',
        !empty($cargos) ? $cargos[0] : '',
        '',
        !empty($pastoreios) ? $pastoreios[0] : '',
    ], 8);

    // Linhas 3..52: Espaço em branco para preenchimento
    for ($i = 3; $i <= 52; $i++) {
        $style = ($i % 2 === 0) ? 6 : 9;
        for ($c = 1; $c <= 10; $c++) {
            $imp->writeCell($i, $c, '', $style);
        }
    }

    // ── Abas de referência ───────────────────────────────────────────────
    $refs = [
        ['nome' => 'Grupos disponiveis',      'dados' => $grupos,      'estilo' => 2],
        ['nome' => 'Cargos disponiveis',       'dados' => $cargos,      'estilo' => 4],
        ['nome' => 'Habilidades disponiveis',  'dados' => $habilidades, 'estilo' => 10],
        ['nome' => 'Pastoreio disponivel',     'dados' => $pastoreios,  'estilo' => 7],
    ];
    foreach ($refs as $ref) {
        $sh = $xlsx->addSheet($ref['nome']);
        $sh->setColWidth(1, 35);
        $sh->writeCell(1, 1, strtoupper(str_replace(' disponiveis', '', str_replace(' disponivel', '', $ref['nome']))), $ref['estilo']);
        $r2 = 2;
        if ($ref['dados']) {
            foreach ($ref['dados'] as $nome) {
                $sh->writeCell($r2, 1, $nome, ($r2 % 2 === 0) ? 6 : 9);
                $r2++;
            }
        } else {
            $sh->writeCell(2, 1, '(nenhum cadastrado ainda)', 0);
        }
    }

    $xlsx->download('modelo-importacao-membros.xlsx');
}

// ── Processar importação (POST) ──────────────────────────────────────────
$resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $erros    = [];
    $criados  = 0;
    $ignorados = 0;

    if (empty($_FILES['planilha']['tmp_name'])) {
        $erros[] = 'Nenhum arquivo enviado.';
    } else {
        $tmp  = $_FILES['planilha']['tmp_name'];
        $nome = $_FILES['planilha']['name'];
        $ext  = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

        if ($ext !== 'xlsx') {
            $erros[] = 'Apenas arquivos .xlsx são aceitos.';
        } else {
            try {
                $reader = new XlsxReader();
                $rows   = $reader->readSheet($tmp, 1); // Aba 1 = "Importar Membros" (0-indexed)

                if (empty($rows)) {
                    $erros[] = 'Planilha vazia ou aba "Importar Membros" não encontrada.';
                } else {
                    // Caches de categorias para evitar SELECT repetido
                    $cacheGrupos     = [];
                    $cacheCargos     = [];
                    $cacheHabilidades = [];
                    $cachePastoreio  = [];

                    $fnGetOuCria = function(string $tabela, string $campo, string $nome, array &$cache) use ($pdo): int {
                        $nome = trim($nome);
                        if (!$nome) return 0;
                        if (isset($cache[$nome])) return $cache[$nome];
                        $st = $pdo->prepare("SELECT id FROM {$tabela} WHERE nome = ?");
                        $st->execute([$nome]);
                        $id = $st->fetchColumn();
                        if (!$id) {
                            $pdo->prepare("INSERT INTO {$tabela} (nome) VALUES (?)")->execute([$nome]);
                            $id = (int)$pdo->lastInsertId();
                        }
                        return $cache[$nome] = (int)$id;
                    };

                    foreach ($rows as $i => $row) {
                        // Pula cabeçalho (linha 0 = row de header da planilha)
                        if ($i === 0) continue;

                        $nome_membro = trim($row[0] ?? '');
                        if (!$nome_membro || str_contains($nome_membro, '(exemplo')) {
                            $ignorados++;
                            continue;
                        }

                        // Parse de data DD/MM/AAAA → AAAA-MM-DD
                        $data_nasc = null;
                        $raw_data  = trim($row[2] ?? '');
                        if ($raw_data && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw_data, $dm)) {
                            $data_nasc = sprintf('%04d-%02d-%02d', $dm[3], $dm[2], $dm[1]);
                            // Valida data
                            if (!checkdate((int)$dm[2], (int)$dm[1], (int)$dm[3])) $data_nasc = null;
                        }

                        $telefone = trim($row[1] ?? '');
                        $endereco = trim($row[3] ?? '');
                        $bairro   = trim($row[4] ?? '');
                        $cidade   = trim($row[5] ?? '');

                        // Insere membro
                        $pdo->prepare("
                            INSERT INTO membros (nome, telefone, data_nasc, endereco, bairro, cidade, ativo)
                            VALUES (?, ?, ?, ?, ?, ?, 1)
                        ")->execute([$nome_membro, $telefone ?: null, $data_nasc, $endereco ?: null, $bairro ?: null, $cidade ?: null]);
                        $membro_id = (int)$pdo->lastInsertId();

                        // Grupos
                        foreach (explode(',', $row[6] ?? '') as $g) {
                            $g = trim($g);
                            if (!$g) continue;
                            $gid = $fnGetOuCria('membros_grupos', 'nome', $g, $cacheGrupos);
                            if ($gid) $pdo->prepare("INSERT IGNORE INTO membros_grupo_rel (grupo_id,membro_id) VALUES (?,?)")->execute([$gid, $membro_id]);
                        }
                        // Cargos
                        foreach (explode(',', $row[7] ?? '') as $c) {
                            $c = trim($c);
                            if (!$c) continue;
                            $cid = $fnGetOuCria('membros_cargos', 'nome', $c, $cacheCargos);
                            if ($cid) $pdo->prepare("INSERT IGNORE INTO membros_cargo_rel (cargo_id,membro_id) VALUES (?,?)")->execute([$cid, $membro_id]);
                        }
                        // Habilidades
                        foreach (explode(',', $row[8] ?? '') as $h) {
                            $h = trim($h);
                            if (!$h) continue;
                            $hid = $fnGetOuCria('membros_habilidades', 'nome', $h, $cacheHabilidades);
                            if ($hid) $pdo->prepare("INSERT IGNORE INTO membros_habilidade_rel (habilidade_id,membro_id) VALUES (?,?)")->execute([$hid, $membro_id]);
                        }
                        // Pastoreio
                        foreach (explode(',', $row[9] ?? '') as $p) {
                            $p = trim($p);
                            if (!$p) continue;
                            $pid = $fnGetOuCria('membros_pastoreio', 'nome', $p, $cachePastoreio);
                            if ($pid) $pdo->prepare("INSERT IGNORE INTO membros_pastoreio_rel (pastoreio_id,membro_id) VALUES (?,?)")->execute([$pid, $membro_id]);
                        }

                        $criados++;
                    }
                }
            } catch (\Exception $e) {
                $erros[] = 'Erro ao processar planilha: ' . $e->getMessage();
            }
        }
    }

    $resultado = compact('erros', 'criados', 'ignorados');
}

// ── Interface ────────────────────────────────────────────────────────────
$titulo       = 'Importar Membros';
$pagina_ativa = 'membros';
include dirname(__DIR__) . '/_layout.php';
?>
<style>
.imp-card{background:#fff;border:1px solid var(--border);border-radius:var(--rl);max-width:640px;margin:0 auto;overflow:hidden}
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
.resultado-ok p,.resultado-erro p,.resultado-erro ul{font-size:.82rem;margin:0}
.resultado-erro ul{padding-left:16px}
@media(max-width:640px){.imp-body{padding:16px}}
</style>

<div style="max-width:800px;margin:0 auto;padding:0 4px">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;flex-wrap:wrap">
    <div style="flex:1;min-width:0">
      <div style="font-family:'Cinzel',serif;font-size:.85rem;font-weight:700;color:var(--green-dk);text-transform:uppercase;letter-spacing:.06em">Importar Membros</div>
      <div style="font-size:.76rem;color:var(--muted);margin-top:2px">Importe membros em lote a partir de uma planilha Excel (.xlsx)</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="/portal/membros/importar.php?modelo=1" class="btn btn-ouro btn-sm">↓ Baixar planilha modelo</a>
      <a href="/portal/membros/" class="btn btn-ghost btn-sm">← Voltar</a>
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
        <strong><?= $resultado['criados'] ?></strong> membro<?= $resultado['criados'] !== 1 ? 's' : '' ?> importado<?= $resultado['criados'] !== 1 ? 's' : '' ?> com sucesso.
        <?php if ($resultado['ignorados']): ?>
          <?= $resultado['ignorados'] ?> linha<?= $resultado['ignorados'] !== 1 ? 's' : '' ?> ignorada<?= $resultado['ignorados'] !== 1 ? 's' : '' ?> (cabeçalho ou em branco).
        <?php endif; ?>
      </p>
      <?php if ($resultado['criados'] > 0): ?>
      <p style="margin-top:8px"><a href="/portal/membros/" style="color:var(--green);font-weight:600">Ver membros importados →</a></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="imp-card">
    <div class="imp-head">
      <div class="imp-titulo">Como importar</div>
      <div class="imp-sub">Siga os passos abaixo para importar membros em lote</div>
    </div>
    <div class="imp-body">
      <ol class="imp-steps">
        <li><span class="imp-step-num">1</span><span>Baixe a <a href="/portal/membros/importar.php?modelo=1" style="color:var(--green);font-weight:600">planilha modelo</a> — ela já vem com os grupos, cargos, habilidades e pastoreios cadastrados como referência.</span></li>
        <li><span class="imp-step-num">2</span><span>Preencha os dados na aba <strong>"Importar Membros"</strong>, a partir da linha 3 (apague o exemplo da linha 2 se quiser).</span></li>
        <li><span class="imp-step-num">3</span><span>Para categorias (grupos, cargos, etc.), use os nomes exatos das abas de referência ou escreva um nome novo — ele será criado automaticamente.</span></li>
        <li><span class="imp-step-num">4</span><span>Salve o arquivo normalmente como <strong>.xlsx</strong> e faça o upload abaixo.</span></li>
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

        <button type="submit" class="btn btn-primary" style="width:100%">↑ Importar membros</button>
      </form>
    </div>
  </div>
</div>

<script>
const arquivo  = document.getElementById('arquivo');
const nomeArq  = document.getElementById('nome-arquivo');
const dropzone = document.getElementById('dropzone');

arquivo.addEventListener('change', () => {
  nomeArq.textContent = arquivo.files[0] ? arquivo.files[0].name : '';
});

dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('over'); });
dropzone.addEventListener('dragleave', ()   => dropzone.classList.remove('over'));
dropzone.addEventListener('drop', e => {
  e.preventDefault();
  dropzone.classList.remove('over');
  if (e.dataTransfer.files[0]) {
    arquivo.files = e.dataTransfer.files;
    nomeArq.textContent = e.dataTransfer.files[0].name;
  }
});
</script>
<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
