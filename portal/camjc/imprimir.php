<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("
    SELECT t.*, a.*, u.nome AS unidade_nome, t.id AS triagem_id
    FROM camjc_triagens t
    JOIN camjc_acolhidas a ON a.id = t.acolhida_id
    JOIN unidades u ON u.id = a.unidade_id
    WHERE t.id = ?
");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) { header('Location: /portal/camjc/'); exit; }

camjc_log('imprimiu', $r['acolhida_id'], 'Triagem #' . $id);

$perguntas = [
    'motivo_encaminhamento'          => 'Motivo do encaminhamento:',
    'tipo_droga_padrao_uso'          => 'Qual(is) tipo(s) de droga e qual o padrão de uso pelo paciente que justificam e motivam o seu encaminhamento para comunidade terapêutica?',
    'tentativas_tratamento'          => 'Quais as tentativas de tratamento? Foram boas ou más sucedidas? Já passou por tratamento em Comunidade Terapêutica? Se sim, informar ano, período e nome do estabelecimento.',
    'internacao_hospitalar'          => 'Possui período de internação hospitalar recente? Realiza algum tratamento ambulatorial?',
    'historico_sobriedade'           => 'Depois do início do uso contínuo, já ficou sóbria? Como conseguiu manter a sobriedade? Por quanto tempo? Por que recaiu?',
    'transtorno_mental'              => 'É pessoa com transtorno mental (ex: esquizofrenia, bipolaridade)? Se sim, há quanto tempo foi diagnosticado? Já realizou tratamento anterior em Comunidade Terapêutica nos últimos 3 (três) anos?',
    'comprometimento_biologico'      => 'Possui grave comprometimento biológico? Se sim, quais? Exige atenção médico-hospitalar contínua ou de emergência?',
    'deficiencia_fisica'             => 'Portadora de alguma deficiência física:',
    'hematomas_fraturas'             => 'Possui hematomas ou fraturas neste momento?',
    'tatuagens_cicatriz_piercing'    => 'Possui tatuagens, cicatriz ou piercing?',
    'restricao_atividade_fisica'     => 'Possui alguma restrição que a impeça de fazer exercício físico ou outro tipo de atividade?',
    'tratamento_medico_outra_doenca' => 'Faz tratamento médico para alguma outra doença?',
    'comprometimento_psicologico'    => 'Possui grave comprometimento psicológico? Se sim, quais? Exige atenção médico-hospitalar contínua ou de emergência?',
    'tentativa_suicidio'             => 'Já tentou suicídio? Explique.',
    'beneficio_loas_aposentadoria'   => 'É beneficiária do LOAS ou recebe aposentadoria por invalidez, ou qualquer tipo de benefício?',
    'antecedentes_criminais'         => 'Já foi presa? Responde algum processo judicial?',
    'vinculos_familiares'            => 'Os vínculos familiares estão presentes? Quais são as pessoas que mais possuem vínculo e confiança do paciente.',
    'mora_com_quem'                  => 'Mora com quem?',
    'filhos'                         => 'Tem filhos? Listar nome, idade e com quem moram.',
    'rede_apoio'                     => 'Quem irá participar do programa terapêutico junto com a acolhida? Listar as pessoas que poderão ligar e fazer visitas. Nome completo, grau de parentesco e contato.',
    'moradia_pos_tratamento'         => 'Terá onde morar após completar o tratamento para dependência química?',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Triagem — <?= htmlspecialchars($r['nome']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0 }
body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; color: #000; background: #d8d8d8; }

.barra-acoes {
  background: #fff; border-bottom: 2px solid #2d7a45; padding: 10px 20px;
  display: flex; gap: 10px; align-items: center; position: sticky; top: 0; z-index: 100; flex-wrap: wrap;
}
.btn-imp { background: #2d7a45; color: #fff; border: none; padding: 8px 22px; border-radius: 6px; font-size: .88rem; cursor: pointer; font-weight: 600; font-family: inherit; }
.btn-imp:hover { background: #235e36 }
.btn-vol { background: none; color: #2d7a45; border: 1.5px solid #2d7a45; padding: 7px 16px; border-radius: 6px; font-size: .88rem; cursor: pointer; font-weight: 600; text-decoration: none; font-family: inherit; }

.pagina { width: 210mm; min-height: 297mm; background: #fff; margin: 20px auto; padding: 14mm 16mm; box-shadow: 0 2px 14px rgba(0,0,0,.18); }

.cab { text-align: center; }
.cab img { max-height: 52pt; max-width: 160pt; }
.cab-linha { border: none; border-top: 1.5px solid #000; margin: 6pt 0 10pt; }
.titulo { text-align: center; font-size: 13pt; font-weight: bold; letter-spacing: .5px; margin-bottom: 4pt; }
.subtitulo { text-align: center; font-size: 9.5pt; margin-bottom: 14pt; color: #333; }

.campos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2pt 18pt; font-size: 10pt; margin-bottom: 10pt; }
.campos-grid .full { grid-column: 1 / -1; }
.campo-linha { border-bottom: 1px solid #000; padding: 2pt 0 1pt; min-height: 14pt; }
.campo-linha b { font-weight: bold }

.sec-titulo { text-align: center; font-size: 11.5pt; font-weight: bold; text-decoration: underline; margin: 16pt 0 10pt; }

.pergunta { margin-bottom: 11pt; page-break-inside: avoid; break-inside: avoid; }
.pergunta .q { font-size: 9.8pt; font-weight: bold; margin-bottom: 3pt; }
.pergunta .r { font-size: 10pt; min-height: 13pt; border-bottom: 1px solid #999; padding-bottom: 2pt; white-space: pre-wrap; }
.pergunta .r.vazio { color: #999; font-style: italic; }

.assinaturas { margin-top: 26pt; page-break-inside: avoid; break-inside: avoid; }
.assinatura-linha { margin-bottom: 22pt; font-size: 10.5pt; }
.assinatura-linha .linha { border-bottom: 1px solid #000; width: 100%; height: 20pt; }
.assinatura-linha .rot { margin-top: 3pt; font-size: 9.5pt; }

.rodape { text-align: center; font-size: 8.5pt; line-height: 1.6; margin-top: 16pt; border-top: 1px solid #000; padding-top: 5pt; }

/* Rodapé fixo — só usado na impressão (o Chrome repete elementos
   position:fixed no rodapé de TODAS as páginas impressas). O .rodape
   normal (que flui após o conteúdo) é escondido no print para não duplicar. */
.rodape-print { display: none; }

@media print {
  body { background: #fff; margin: 0; padding: 0 }
  .barra-acoes { display: none }
  @page { size: A4; margin: 0 }
  .pagina { box-shadow: none !important; margin: 0 !important; width: auto !important; min-height: 0 !important; padding-bottom: 26mm !important; }
  .rodape { display: none !important }
  .rodape-print {
    display: block !important;
    position: fixed;
    left: 16mm;
    right: 16mm;
    bottom: 6mm;
    text-align: center;
    font-size: 8.5pt;
    line-height: 1.6;
    border-top: 1px solid #000;
    padding-top: 5pt;
    background: #fff;
  }
}
</style>
</head>
<body>

<div class="barra-acoes">
  <button class="btn-imp" onclick="window.print()">🖨 Imprimir</button>
  <a class="btn-vol" href="/portal/camjc/ver.php?id=<?= $r['acolhida_id'] ?>">← Voltar</a>
  <span style="font-size:.8rem;color:#666;margin-left:8px"><?= htmlspecialchars($r['nome']) ?> — triagem de <?= date('d/m/Y', strtotime($r['data_triagem'])) ?></span>
</div>

<div class="pagina">
  <div class="cab"><img src="/assets/img/logo.png" alt="NAIOT"></div>
  <hr class="cab-linha">

  <div class="titulo">TRIAGEM — ETAPA 1</div>
  <div class="subtitulo">Casa de Acolhimento Mulheres de Jesus Cristo (CAMJC) — <?= htmlspecialchars($r['unidade_nome'] ?? 'Casa das Mulheres') ?></div>

  <div class="campos-grid">
    <div class="full campo-linha"><b>Nome candidata:</b> <?= htmlspecialchars($r['nome']) ?></div>
    <div class="campo-linha"><b>Idade:</b> <?= $r['data_nasc'] ? (new DateTime($r['data_nasc']))->diff(new DateTime())->y . ' anos' : '' ?></div>
    <div class="campo-linha"><b>Data de nasc.:</b> <?= $r['data_nasc'] ? date('d/m/Y', strtotime($r['data_nasc'])) : '' ?></div>
    <div class="campo-linha"><b>Est. civil:</b> <?= htmlspecialchars($r['estado_civil'] ?? '') ?></div>
    <div class="campo-linha"><b>Doc. identidade:</b> <?= htmlspecialchars($r['rg'] ?? '') ?></div>
    <div class="campo-linha"><b>CPF:</b> <?= htmlspecialchars($r['cpf'] ?? '') ?></div>
    <div class="full campo-linha"><b>Endereço:</b> <?= htmlspecialchars($r['endereco'] ?? '') ?></div>
    <div class="campo-linha"><b>Bairro:</b> <?= htmlspecialchars($r['bairro'] ?? '') ?></div>
    <div class="campo-linha"><b>CEP:</b> <?= htmlspecialchars($r['cep'] ?? '') ?></div>
    <div class="campo-linha"><b>Cidade atual:</b> <?= htmlspecialchars($r['cidade'] ?? '') ?></div>
    <div class="campo-linha"><b>Estado:</b> <?= htmlspecialchars($r['estado'] ?? '') ?></div>
    <div class="campo-linha"><b>Fones:</b> <?= htmlspecialchars($r['telefone'] ?? '') ?></div>
    <div class="campo-linha"><b>Celular:</b> <?= htmlspecialchars($r['celular'] ?? '') ?></div>
    <div class="full campo-linha"><b>Nome do responsável:</b> <?= htmlspecialchars($r['responsavel_nome'] ?? '') ?></div>
    <div class="full campo-linha"><b>Endereço do responsável:</b> <?= htmlspecialchars($r['responsavel_endereco'] ?? '') ?></div>
    <div class="campo-linha"><b>RG:</b> <?= htmlspecialchars($r['responsavel_rg'] ?? '') ?></div>
    <div class="campo-linha"><b>CPF:</b> <?= htmlspecialchars($r['responsavel_cpf'] ?? '') ?></div>
    <div class="campo-linha"><b>Data nasc.:</b> <?= $r['responsavel_data_nasc'] ? date('d/m/Y', strtotime($r['responsavel_data_nasc'])) : '' ?></div>
    <div class="campo-linha"><b>Telefone:</b> <?= htmlspecialchars($r['responsavel_telefone'] ?? '') ?></div>
    <div class="full campo-linha"><b>Data de acolhimento:</b> <?= $r['data_acolhimento'] ? date('d/m/Y', strtotime($r['data_acolhimento'])) : '' ?></div>
  </div>

  <div class="sec-titulo">ENTREVISTA DE PERFIL</div>

  <?php foreach ($perguntas as $campo => $pergunta): ?>
  <div class="pergunta">
    <div class="q"><?= htmlspecialchars($pergunta) ?></div>
    <?php if (!empty($r[$campo])): ?>
      <div class="r"><?= htmlspecialchars($r[$campo]) ?></div>
    <?php else: ?>
      <div class="r vazio">Não informado</div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div class="pergunta">
    <div class="q">Observações</div>
    <?php if (!empty($r['observacoes'])): ?>
      <div class="r"><?= htmlspecialchars($r['observacoes']) ?></div>
    <?php else: ?>
      <div class="r vazio">&nbsp;</div>
    <?php endif; ?>
  </div>

  <div class="assinaturas">
    <div class="assinatura-linha">
      <div class="linha"></div>
      <div class="rot">Assinatura do Responsável</div>
    </div>
    <div class="assinatura-linha">
      <div class="linha"></div>
      <div class="rot">Assinatura da candidata</div>
    </div>
    <div class="assinatura-linha">
      <div class="linha"></div>
      <div class="rot">Responsável pela triagem<?= !empty($r['responsavel_triagem_nome']) ? ' — ' . htmlspecialchars($r['responsavel_triagem_nome']) : '' ?></div>
    </div>
  </div>

  <div class="rodape">
    GO 330, km 20, s/n, Fazenda Poções, Zona Rural, Campo Limpo de Goiás – GO – BRA.<br>
    Comunidade Católica Senhor Jesus — Mantenedora da Casa de Acolhimento Mulheres de Jesus Cristo
  </div>
</div>

<!-- Rodapé fixo — só aparece na impressão, repetido em todas as páginas -->
<div class="rodape-print">
  GO 330, km 20, s/n, Fazenda Poções, Zona Rural, Campo Limpo de Goiás – GO – BRA.<br>
  Comunidade Católica Senhor Jesus — Mantenedora da Casa de Acolhimento Mulheres de Jesus Cristo
</div>

<script>
document.querySelector('.btn-imp').onclick = function () { window.print(); return false; };
</script>
</body>
</html>
