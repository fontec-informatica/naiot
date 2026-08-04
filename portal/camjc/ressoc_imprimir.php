<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_ressoc_perguntas.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("
    SELECT r.*, a.nome, u.nome AS unidade_nome
    FROM camjc_ressocializacao r
    JOIN camjc_acolhidas a ON a.id = r.acolhida_id
    JOIN unidades u ON u.id = a.unidade_id
    WHERE r.id = ?
");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) { header('Location: /portal/camjc/'); exit; }

camjc_log('imprimiu_ressocializacao', $r['acolhida_id'], 'Avaliação #' . $id);

$respostas = json_decode($r['respostas'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Avaliação de Ressocialização — <?= htmlspecialchars($r['nome']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0 }
body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; color: #000; background: #d8d8d8; }
.barra-acoes { background: #fff; border-bottom: 2px solid #2d7a45; padding: 10px 20px; display: flex; gap: 10px; align-items: center; position: sticky; top: 0; z-index: 100; flex-wrap: wrap; }
.btn-imp { background: #2d7a45; color: #fff; border: none; padding: 8px 22px; border-radius: 6px; font-size: .88rem; cursor: pointer; font-weight: 600; font-family: inherit; }
.btn-imp:hover { background: #235e36 }
.btn-vol { background: none; color: #2d7a45; border: 1.5px solid #2d7a45; padding: 7px 16px; border-radius: 6px; font-size: .88rem; cursor: pointer; font-weight: 600; text-decoration: none; font-family: inherit; }

.pagina { width: 210mm; min-height: 297mm; background: #fff; margin: 20px auto; padding: 14mm 16mm; box-shadow: 0 2px 14px rgba(0,0,0,.18); }
.cab { text-align: center; }
.cab img { max-height: 52pt; max-width: 160pt; }
.cab-linha { border: none; border-top: 1.5px solid #000; margin: 6pt 0 10pt; }
.titulo { text-align: center; font-size: 13pt; font-weight: bold; letter-spacing: .5px; margin-bottom: 4pt; }
.subtitulo { text-align: center; font-size: 9.5pt; margin-bottom: 14pt; color: #333; }

.campos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2pt 18pt; font-size: 10pt; margin-bottom: 12pt; }
.campos-grid .full { grid-column: 1 / -1; }
.campo-linha { border-bottom: 1px solid #000; padding: 2pt 0 1pt; min-height: 14pt; }
.campo-linha b { font-weight: bold }

.grupo-titulo { font-size: 12pt; font-weight: bold; text-decoration: underline; margin: 18pt 0 8pt; page-break-after: avoid; }
.sec-titulo { font-size: 10.5pt; font-weight: bold; margin: 12pt 0 6pt; page-break-after: avoid; color: #333; }

.pergunta { margin-bottom: 8pt; page-break-inside: avoid; break-inside: avoid; }
.pergunta .q { font-size: 9.3pt; margin-bottom: 2pt; }
.pergunta .r { font-size: 9.8pt; font-weight: bold; padding-left: 10pt; }
.pergunta .r.vazio { color: #999; font-style: italic; font-weight: normal; }

.rodape { text-align: center; font-size: 8.5pt; line-height: 1.6; margin-top: 16pt; border-top: 1px solid #000; padding-top: 5pt; }
.rodape-print { display: none; }

@media print {
  body { background: #fff; margin: 0; padding: 0 }
  .barra-acoes { display: none }
  @page { size: A4; margin: 0 }
  .pagina { box-shadow: none !important; margin: 0 !important; width: auto !important; min-height: 0 !important; padding-bottom: 26mm !important; }
  .rodape { display: none !important }
  .rodape-print {
    display: block !important; position: fixed; left: 16mm; right: 16mm; bottom: 6mm;
    text-align: center; font-size: 8.5pt; line-height: 1.6; border-top: 1px solid #000; padding-top: 5pt; background: #fff;
  }
}
</style>
</head>
<body>

<div class="barra-acoes">
  <button class="btn-imp" onclick="window.print()">🖨 Imprimir</button>
  <a class="btn-vol" href="/portal/camjc/ver.php?id=<?= $r['acolhida_id'] ?>">← Voltar</a>
  <span style="font-size:.8rem;color:#666;margin-left:8px"><?= htmlspecialchars($r['nome']) ?> — avaliação de <?= date('d/m/Y', strtotime($r['data_resposta'])) ?></span>
</div>

<div class="pagina">
  <div class="cab"><img src="/assets/img/logo.png" alt="NAIOT"></div>
  <hr class="cab-linha">

  <div class="titulo">AVALIAÇÃO DE RESSOCIALIZAÇÃO — FAMÍLIA</div>
  <div class="subtitulo">Casa de Acolhimento Mulheres de Jesus Cristo (CAMJC) — <?= htmlspecialchars($r['unidade_nome']) ?></div>

  <div class="campos-grid">
    <div class="full campo-linha"><b>Nome da acolhida:</b> <?= htmlspecialchars($r['nome']) ?></div>
    <div class="campo-linha"><b>Nome do familiar:</b> <?= htmlspecialchars($r['nome_familiar'] ?? '') ?></div>
    <div class="campo-linha"><b>Grau de parentesco:</b> <?= htmlspecialchars($r['grau_parentesco'] ?? '') ?></div>
    <div class="campo-linha"><b>Nº da visita:</b> <?= htmlspecialchars($r['numero_visita'] ?? '') ?></div>
    <div class="campo-linha"><b>Data da resposta:</b> <?= date('d/m/Y', strtotime($r['data_resposta'])) ?></div>
  </div>

  <?php foreach (CAMJC_RESSOC_GRUPOS as $grupo): ?>
    <div class="grupo-titulo"><?= htmlspecialchars(mb_strtoupper($grupo['label'])) ?></div>
    <?php foreach ($grupo['secoes'] as $secao): ?>
      <div class="sec-titulo"><?= htmlspecialchars($secao['titulo']) ?></div>
      <?php foreach ($secao['perguntas'] as $chave => $p):
        $valor = $chave === '23_1' ? $r['observacoes_finais'] : ($respostas[$chave] ?? null);
      ?>
      <div class="pergunta">
        <div class="q"><?= htmlspecialchars($p['texto']) ?></div>
        <?php if ($valor): ?>
          <?php if (isset($p['opcoes'])): ?>
            <div class="r"><?= htmlspecialchars($p['opcoes'][$valor] ?? $valor) ?></div>
          <?php else: ?>
            <div class="r"><?= nl2br(htmlspecialchars($valor)) ?></div>
          <?php endif; ?>
        <?php else: ?>
          <div class="r vazio">Não respondida</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <div class="rodape">
    GO 330, km 20, s/n, Fazenda Poções, Zona Rural, Campo Limpo de Goiás – GO – BRA.<br>
    Comunidade Católica Senhor Jesus — Mantenedora da Casa de Acolhimento Mulheres de Jesus Cristo
  </div>
</div>

<div class="rodape-print">
  GO 330, km 20, s/n, Fazenda Poções, Zona Rural, Campo Limpo de Goiás – GO – BRA.<br>
  Comunidade Católica Senhor Jesus — Mantenedora da Casa de Acolhimento Mulheres de Jesus Cristo
</div>

<script>
document.querySelector('.btn-imp').onclick = function () { window.print(); return false; };
</script>
</body>
</html>
