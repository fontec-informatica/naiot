<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("
    SELECT pv.*, a.nome, u.nome AS unidade_nome
    FROM camjc_projetos_vida pv
    JOIN camjc_acolhidas a ON a.id = pv.acolhida_id
    JOIN unidades u ON u.id = a.unidade_id
    WHERE pv.id = ?
");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) { header('Location: /portal/camjc/'); exit; }

camjc_log('imprimiu_projeto_vida', $r['acolhida_id'], 'Projeto de Vida #' . $id);

$secoes = [
    'Autoconhecimento' => [
        'valores_base'         => 'Valores de base',
        'pontos_fortes'        => 'Pontos fortes',
        'pontos_melhorar'      => 'Em que preciso melhorar',
        'oportunidades'        => 'Oportunidades que vejo em minha vida',
        'ameacas'              => 'O que ameaça meus planos',
        'tempo_planejamento'   => 'Tempo dedicado a planejar o futuro',
        'recursos_financeiros' => 'Recursos financeiros para colocar os projetos em prática',
        'missao'               => 'Missão para minha vida',
    ],
    'Saúde física' => [
        'saude_fisica_atual'       => 'O que faz para manter o corpo saudável',
        'saude_fisica_alimentacao' => 'Alimentação',
        'saude_fisica_sono'        => 'Sono',
        'saude_fisica_meta'        => 'O que precisa melhorar / meta',
        'saude_fisica_pratica'     => 'O que precisa fazer para colocar em prática',
    ],
    'Saúde espiritual' => [
        'saude_espiritual_estabilidade' => 'Estabilidade emocional',
        'saude_espiritual_interior'     => 'Contato com questões interiores',
        'saude_espiritual_capela'       => 'Comportamento na Capela',
        'saude_espiritual_oracao'       => 'Oração pessoal por conta própria',
        'saude_espiritual_metas'        => 'Metas para a vida espiritual',
    ],
    'Saúde intelectual' => [
        'saude_intelectual_leituras' => 'Leituras',
        'saude_intelectual_curso'    => 'Cursos',
        'saude_intelectual_estudo'   => 'Estudo recente',
    ],
    'Saúde familiar' => [
        'saude_familiar_tratamento'    => 'Como tem tratado os familiares',
        'saude_familiar_impedimento'   => 'O que impede um relacionamento mais harmonioso',
        'saude_familiar_reconciliacao' => 'Pessoas com quem pretende se reconciliar / meta',
    ],
    'Saúde social' => [
        'saude_social_sociedade' => 'Sociedade mais justa',
        'saude_social_ajuda'     => 'Ajuda ao próximo',
        'saude_social_cidadania' => 'Exercício da cidadania',
    ],
    'Saúde financeira' => [
        'saude_financeira_recursos'     => 'Recursos financeiros para o sustento',
        'saude_financeira_profissao'    => 'Profissão atual / desejada',
        'saude_financeira_planejamento' => 'Planejamento financeiro',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Projeto de Vida — <?= htmlspecialchars($r['nome']) ?></title>
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
.sec-titulo { font-size: 11.5pt; font-weight: bold; text-decoration: underline; margin: 16pt 0 8pt; page-break-after: avoid; }
.pergunta { margin-bottom: 10pt; page-break-inside: avoid; break-inside: avoid; }
.pergunta .q { font-size: 9.8pt; font-weight: bold; margin-bottom: 3pt; }
.pergunta .r { font-size: 10pt; min-height: 13pt; border-bottom: 1px solid #999; padding-bottom: 2pt; white-space: pre-wrap; }
.pergunta .r.vazio { color: #999; font-style: italic; }
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
  <span style="font-size:.8rem;color:#666;margin-left:8px"><?= htmlspecialchars($r['nome']) ?> — projeto de <?= date('d/m/Y', strtotime($r['data_projeto'])) ?></span>
</div>

<div class="pagina">
  <div class="cab"><img src="/assets/img/logo.png" alt="NAIOT"></div>
  <hr class="cab-linha">

  <div class="titulo">PROJETO DE VIDA</div>
  <div class="subtitulo"><?= htmlspecialchars($r['nome']) ?> — <?= htmlspecialchars($r['unidade_nome']) ?> — <?= date('d/m/Y', strtotime($r['data_projeto'])) ?></div>

  <?php foreach ($secoes as $titulo_secao => $perguntas): ?>
    <div class="sec-titulo"><?= htmlspecialchars(mb_strtoupper($titulo_secao)) ?></div>
    <?php foreach ($perguntas as $campo => $pergunta): ?>
    <div class="pergunta">
      <div class="q"><?= htmlspecialchars($pergunta) ?></div>
      <?php if (!empty($r[$campo])): ?>
        <div class="r"><?= nl2br(htmlspecialchars($r[$campo])) ?></div>
      <?php else: ?>
        <div class="r vazio">Não informado</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <div class="sec-titulo">METAS</div>
  <div class="pergunta">
    <?php if (!empty($r['metas'])): ?>
      <div class="r"><?= nl2br(htmlspecialchars($r['metas'])) ?></div>
    <?php else: ?>
      <div class="r vazio">&nbsp;</div>
    <?php endif; ?>
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
