<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("
    SELECT p.*, a.nome, a.data_nasc, u.nome AS unidade_nome
    FROM camjc_pas p
    JOIN camjc_acolhidas a ON a.id = p.acolhida_id
    JOIN unidades u ON u.id = a.unidade_id
    WHERE p.id = ?
");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) { header('Location: /portal/camjc/'); exit; }

camjc_log('imprimiu_pas', $r['acolhida_id'], 'PAS #' . $id);

$atividades      = json_decode($r['atividades'] ?? '[]', true) ?: [];
$encaminhamentos = json_decode($r['encaminhamentos'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Evolução / PAS — <?= htmlspecialchars($r['nome']) ?></title>
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

.sec-titulo { font-size: 11.5pt; font-weight: bold; text-decoration: underline; margin: 16pt 0 8pt; page-break-after: avoid; }

.tabela-esc { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-bottom: 10pt; }
.tabela-esc td { border: 1px solid #999; padding: 4pt 6pt; }
.tabela-esc td.item { width: 65% }
.tabela-esc td.valor { text-align: center; font-weight: bold }

.pergunta { margin-bottom: 10pt; page-break-inside: avoid; break-inside: avoid; }
.pergunta .q { font-size: 9.8pt; font-weight: bold; margin-bottom: 3pt; }
.pergunta .r { font-size: 10pt; min-height: 13pt; border-bottom: 1px solid #999; padding-bottom: 2pt; white-space: pre-wrap; }
.pergunta .r.vazio { color: #999; font-style: italic; }

.ativ-lista { font-size: 9.5pt; margin-bottom: 10pt; }
.ativ-lista div { padding: 3pt 0; border-bottom: 1px dotted #ccc; display: flex; justify-content: space-between; gap: 10pt; }

.assinaturas { margin-top: 24pt; page-break-inside: avoid; break-inside: avoid; }
.assinatura-linha { margin-bottom: 20pt; font-size: 10.5pt; }
.assinatura-linha .linha { border-bottom: 1px solid #000; width: 100%; height: 20pt; }
.assinatura-linha .rot { margin-top: 3pt; font-size: 9.5pt; }

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
  <span style="font-size:.8rem;color:#666;margin-left:8px"><?= htmlspecialchars($r['nome']) ?> — avaliação de <?= date('d/m/Y', strtotime($r['data_avaliacao'])) ?></span>
</div>

<div class="pagina">
  <div class="cab"><img src="/assets/img/logo.png" alt="NAIOT"></div>
  <hr class="cab-linha">

  <div class="titulo">PLANO DE ATENDIMENTO SINGULAR — MENSAL</div>
  <div class="subtitulo">Casa de Acolhimento Mulheres de Jesus Cristo (CAMJC) — <?= htmlspecialchars($r['unidade_nome']) ?></div>

  <div class="campos-grid">
    <div class="full campo-linha"><b>Nome:</b> <?= htmlspecialchars($r['nome']) ?></div>
    <div class="campo-linha"><b>Data da avaliação:</b> <?= date('d/m/Y', strtotime($r['data_avaliacao'])) ?></div>
    <div class="campo-linha"><b>Houve alteração na medicação:</b> <?= $r['houve_alteracao_medicacao'] === 'sim' ? 'Sim' : 'Não' ?></div>
  </div>

  <?php if (!empty($r['medicamentos'])): ?>
  <div class="pergunta">
    <div class="q">Medicamentos em uso</div>
    <div class="r"><?= nl2br(htmlspecialchars($r['medicamentos'])) ?></div>
  </div>
  <?php endif; ?>

  <div class="sec-titulo">VÍNCULOS FAMILIARES</div>
  <div class="campos-grid">
    <div class="full campo-linha"><b>Situação:</b> <?= htmlspecialchars(!empty($r['vinculo_situacao']) ? (CAMJC_VINCULO_SITUACOES[$r['vinculo_situacao']] ?? $r['vinculo_situacao']) : 'Não informado') ?></div>
    <div class="full campo-linha"><b>Qualidade dos vínculos:</b> <?= htmlspecialchars(camjc_escala_label($r['vinculo_qualidade']) ?: 'Não informado') ?></div>
  </div>
  <?php if (!empty($r['familiares_contato'])): ?>
  <div class="pergunta">
    <div class="q">Familiares com quem manteve contato</div>
    <div class="r"><?= nl2br(htmlspecialchars($r['familiares_contato'])) ?></div>
  </div>
  <?php endif; ?>

  <div class="sec-titulo">AVALIAÇÃO SOBRE A ACOLHIDA</div>
  <table class="tabela-esc">
    <?php foreach (CAMJC_AVALIACAO_ACOLHIDA as $chave => $label): ?>
    <tr><td class="item"><?= htmlspecialchars($label) ?></td><td class="valor"><?= htmlspecialchars(camjc_escala_label($r[$chave]) ?: '—') ?></td></tr>
    <?php endforeach; ?>
  </table>

  <div class="sec-titulo">ATIVIDADES</div>
  <div class="ativ-lista">
    <?php foreach (CAMJC_ATIVIDADES_PAS as $chave => $label): ?>
    <?php $ativ_valor = $atividades[$chave] ?? ''; ?>
    <div><span><?= htmlspecialchars($label) ?></span><strong><?= $ativ_valor === 'participa' ? 'Participa' : ($ativ_valor === 'dispensado' ? 'Dispensado' : '—') ?></strong></div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($encaminhamentos)): ?>
  <div class="sec-titulo">ENCAMINHAMENTOS E REFERENCIAMENTOS</div>
  <div class="ativ-lista">
    <?php foreach ($encaminhamentos as $chave): ?>
    <div><span><?= htmlspecialchars(CAMJC_ENCAMINHAMENTOS_PAS[$chave] ?? $chave) ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="sec-titulo">PERCEPÇÃO SOBRE AS ÁREAS DA VIDA</div>
  <?php foreach (CAMJC_PERCEPCAO_AREAS as $area => $label): ?>
  <div class="pergunta">
    <div class="q"><?= htmlspecialchars($label) ?> — <?= htmlspecialchars(camjc_escala_label($r['percepcao_' . $area]) ?: 'não avaliado') ?></div>
    <?php if (!empty($r['percepcao_' . $area . '_melhorar'])): ?>
      <div class="r">O que pode melhorar: <?= htmlspecialchars($r['percepcao_' . $area . '_melhorar']) ?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div class="sec-titulo">AVALIAÇÃO DE IMPORTÂNCIA E CONFIANÇA</div>
  <div class="campos-grid">
    <div class="campo-linha"><b>Importância de mudar (0-10):</b> <?= $r['importancia_mudanca'] ?? '—' ?></div>
    <div class="campo-linha"><b>Confiança na abstinência (0-10):</b> <?= $r['confianca_abstinencia'] ?? '—' ?></div>
  </div>

  <?php if (!empty($r['requerimentos_acolhida'])): ?>
  <div class="pergunta">
    <div class="q">Requerimentos e solicitações da acolhida</div>
    <div class="r"><?= nl2br(htmlspecialchars($r['requerimentos_acolhida'])) ?></div>
  </div>
  <?php endif; ?>

  <div class="sec-titulo">PARECER DO PROFISSIONAL</div>
  <div class="pergunta">
    <?php if (!empty($r['parecer_profissional'])): ?>
      <div class="r"><?= nl2br(htmlspecialchars($r['parecer_profissional'])) ?></div>
    <?php else: ?>
      <div class="r vazio">&nbsp;</div>
    <?php endif; ?>
  </div>

  <div class="assinaturas">
    <div class="assinatura-linha">
      <div class="linha"></div>
      <div class="rot">Assinatura da acolhida — afirmo que participei da elaboração deste plano e as informações são verdadeiras</div>
    </div>
    <div class="assinatura-linha">
      <div class="linha"></div>
      <div class="rot">Profissional responsável<?= !empty($r['profissional_nome']) ? ' — ' . htmlspecialchars($r['profissional_nome']) : '' ?></div>
    </div>
  </div>

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
