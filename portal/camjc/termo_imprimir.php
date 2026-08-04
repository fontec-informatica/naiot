<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$acolhida_id = (int)($_GET['acolhida_id'] ?? 0);
$tipo        = $_GET['tipo'] ?? '';
if (!$acolhida_id || !isset(CAMJC_TERMOS[$tipo])) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("SELECT a.*, u.nome AS unidade_nome FROM camjc_acolhidas a JOIN unidades u ON u.id = a.unidade_id WHERE a.id = ?");
$st->execute([$acolhida_id]);
$a = $st->fetch();
if (!$a) { header('Location: /portal/camjc/'); exit; }

camjc_log('imprimiu_termo', $acolhida_id, $tipo);

$hoje = new DateTime();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= htmlspecialchars(CAMJC_TERMOS[$tipo]) ?> — <?= htmlspecialchars($a['nome']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0 }
body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; color: #000; background: #d8d8d8; }

.barra-acoes { background: #fff; border-bottom: 2px solid #2d7a45; padding: 10px 20px; display: flex; gap: 10px; align-items: center; position: sticky; top: 0; z-index: 100; flex-wrap: wrap; }
.btn-imp { background: #2d7a45; color: #fff; border: none; padding: 8px 22px; border-radius: 6px; font-size: .88rem; cursor: pointer; font-weight: 600; font-family: inherit; }
.btn-imp:hover { background: #235e36 }
.btn-vol { background: none; color: #2d7a45; border: 1.5px solid #2d7a45; padding: 7px 16px; border-radius: 6px; font-size: .88rem; cursor: pointer; font-weight: 600; text-decoration: none; font-family: inherit; }

.pagina { width: 210mm; min-height: 297mm; background: #fff; margin: 20px auto; padding: 18mm 20mm; box-shadow: 0 2px 14px rgba(0,0,0,.18); }
.cab { text-align: center; }
.cab img { max-height: 52pt; max-width: 160pt; }
.cab-linha { border: none; border-top: 1.5px solid #000; margin: 6pt 0 14pt; }
.titulo { text-align: center; font-size: 13pt; font-weight: bold; letter-spacing: .5px; margin-bottom: 20pt; text-transform: uppercase; }
.corpo { font-size: 11pt; line-height: 2; text-align: justify; }
.corpo b { font-weight: bold }
.data-local { margin-top: 26pt; font-size: 10.5pt; }
.assinaturas { margin-top: 30pt; }
.assinatura-linha { margin-bottom: 24pt; font-size: 10.5pt; }
.assinatura-linha .linha { border-bottom: 1px solid #000; width: 100%; height: 20pt; }
.assinatura-linha .rot { margin-top: 3pt; font-size: 9.5pt; }
.rodape { text-align: center; font-size: 8.5pt; line-height: 1.6; margin-top: 20pt; border-top: 1px solid #000; padding-top: 5pt; }
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
  <a class="btn-vol" href="/portal/camjc/ver.php?id=<?= $acolhida_id ?>">← Voltar</a>
  <span style="font-size:.8rem;color:#666;margin-left:8px"><?= htmlspecialchars($a['nome']) ?> — <?= htmlspecialchars(CAMJC_TERMOS[$tipo]) ?></span>
</div>

<div class="pagina">
  <div class="cab"><img src="/assets/img/logo.png" alt="NAIOT"></div>
  <hr class="cab-linha">

  <?php if ($tipo === 'compromisso_unico'): ?>
    <div class="titulo">Termo de Compromisso</div>
    <div class="corpo">
      Eu, <b><?= htmlspecialchars($a['nome']) ?></b>, e o(a) responsável pela minha internação
      <b><?= htmlspecialchars($a['responsavel_nome'] ?? '________________________________') ?></b>,
      em <?= $a['data_acolhimento'] ? date('d/m/Y', strtotime($a['data_acolhimento'])) : '____/____/____' ?>,
      na Casa de Acolhimento Mulheres de Jesus Cristo, declaramos:
      <ul style="margin:14pt 0 14pt 22pt">
        <li style="margin-bottom:8pt">Estar ciente de que caso durante o tratamento a Acolhida apresente doença pregressa que impeça a permanência (comprometimento biológico e psíquico grave) omitida na Entrevista de Perfil, poderá ser desligada do Programa de Tratamento;</li>
        <li style="margin-bottom:8pt">Estar ciente que, em caso de suspeita pela Equipe Multidisciplinar de uso de substância durante o tratamento, será solicitado Exame Toxicológico em laboratório de confiança da Instituição, e que a não concordância implicará em desligamento automático do Programa;</li>
        <li style="margin-bottom:8pt">Recebemos uma cópia do Manual de Orientação aos Familiares e Residentes, referente ao compromisso de deveres, obrigações e direitos das Acolhidas e família, durante o período de internação;</li>
        <li style="margin-bottom:8pt">Ter conhecimento de que não há nenhum tipo de restrição em conviver no Programa de Tratamento com residentes que podem apresentar Doenças Infectocontagiosas (HIV, Hepatite, entre outras);</li>
        <li>Que nos comprometemos a cumprir com o prazo de 24 horas em caso de pedido de alta solicitada (desistência) ou alta administrativa da residente, para buscá-la. Caso contrário, estamos cientes e de acordo que a mesma será liberada da Instituição, portando o valor da passagem, valor das despesas eventuais e documentação entregues na internação.</li>
      </ul>
    </div>
    <div class="data-local">Campo Limpo de Goiás, <?= $hoje->format('d') ?> de <?= mb_strftime_pt((int)$hoje->format('n')) ?> de <?= $hoje->format('Y') ?>.</div>
    <div class="assinaturas">
      <div class="assinatura-linha"><div class="linha"></div><div class="rot">Acolhida</div></div>
      <div class="assinatura-linha"><div class="linha"></div><div class="rot">Familiar ou Responsável</div></div>
    </div>

  <?php elseif ($tipo === 'submissao_voluntaria'): ?>
    <div class="titulo">Termo de Submissão Voluntária a Programa Terapêutico</div>
    <div class="corpo">
      Pelo presente termo, eu, <b><?= htmlspecialchars($a['nome']) ?></b>, brasileira,
      estado civil <?= htmlspecialchars($a['estado_civil'] ?? '_______________') ?>,
      nascida em <?= $a['data_nasc'] ? date('d/m/Y', strtotime($a['data_nasc'])) : '____/____/____' ?>,
      portadora do RG nº <?= htmlspecialchars($a['rg'] ?? '________________') ?> e
      CPF nº <?= htmlspecialchars($a['cpf'] ?? '________________') ?>,
      residente e domiciliada no endereço: <?= htmlspecialchars(implode(', ', array_filter([$a['endereco'], $a['bairro'], $a['cidade'], $a['estado']])) ?: '________________________________') ?>,
      fone: <?= htmlspecialchars($a['telefone'] ?? $a['celular'] ?? '________________') ?>,
      neste ato assistida por meu(s) responsável(is)
      <b><?= htmlspecialchars($a['responsavel_nome'] ?? '________________________________') ?></b>,
      RG nº <?= htmlspecialchars($a['responsavel_rg'] ?? '________________') ?> e
      CPF nº <?= htmlspecialchars($a['responsavel_cpf'] ?? '________________') ?>,
      venho voluntariamente aderir ao Plano Terapêutico de recuperação de dependência química oferecido pela
      Associação Senhor Jesus – Comunidade NAIOT, aceitando todos os termos regimentais dos quais fui
      expressamente esclarecida, concordando expressamente com os métodos utilizados, inclusive com a
      laborterapia — importante método de reeducação a partir da valorização do trabalho físico, intelectual e
      artístico —, estando devidamente acompanhada por meus responsáveis assistentes, gozando de sua ciência
      e permissão.
    </div>
    <div class="data-local">Campo Limpo de Goiás, <?= $hoje->format('d') ?> de <?= mb_strftime_pt((int)$hoje->format('n')) ?> de <?= $hoje->format('Y') ?>.</div>
    <div class="assinaturas">
      <div class="assinatura-linha"><div class="linha"></div><div class="rot">Interna assistida</div></div>
      <div class="assinatura-linha"><div class="linha"></div><div class="rot">Responsável assistente</div></div>
      <div class="assinatura-linha"><div class="linha"></div><div class="rot">Responsável assistente</div></div>
    </div>
    <p style="font-size:9pt;color:#333;margin-top:14pt">
      OBS.: Todos os dados sensíveis contidos nesse formulário estão sob sigilo e protegidos nos termos da
      Lei Geral de Proteção de Dados e serão destruídos quando a interna completar seu tempo de terapia.
    </p>

  <?php elseif ($tipo === 'autorizacao_imagem'): ?>
    <div class="titulo">Termo de Autorização para Uso de Imagem Pessoal</div>
    <div class="corpo">
      Neste ato, <b><?= htmlspecialchars($a['nome']) ?></b>, nacionalidade brasileira,
      estado civil <?= htmlspecialchars($a['estado_civil'] ?? '_______________') ?>,
      portadora da Cédula de Identidade RG nº <?= htmlspecialchars($a['rg'] ?? '________________') ?>,
      inscrita no CPF/MF sob nº <?= htmlspecialchars($a['cpf'] ?? '________________') ?>,
      residente à <?= htmlspecialchars($a['endereco'] ?? '________________________________') ?>,
      município de <?= htmlspecialchars($a['cidade'] ?? '________________') ?>/<?= htmlspecialchars($a['estado'] ?? 'GO') ?>,
      declaro para os devidos fins que autorizo a utilização de minha imagem, em caráter gratuito, pela
      Comunidade Católica Senhor Jesus – Casa de Retiros Naiot, associação civil privada sem fins lucrativos
      no segmento de organização de atividades religiosas e filosóficas, inscrita no CNPJ sob nº 05.834.351/0001-11,
      mantenedora da Casa de Acolhimento Mulheres de Jesus Cristo, para uso e produção em programas, projetos e
      atividades de divulgação da Comunidade e de seu Carisma, para serem utilizadas integralmente ou em parte,
      com citação de meu nome, nas condições originais da captação das imagens, sem restrição de prazos, desde
      a presente data. Esta autorização se refere a fotos ou imagens em vídeo, com ou sem captação de som,
      produzidas pela própria Comunidade ou por terceiros, para uso não comercial, para serem veiculadas em
      mídias eletrônicas e impressas. A presente autorização não permite a modificação das imagens, dos textos,
      adições, ou qualquer mudança que altere o sentido das mesmas, ou que desrespeite a inviolabilidade da
      imagem das pessoas, prevista no inciso X do Art. 5º da Constituição da República Federativa do Brasil e
      no art. 20 da Lei nº 10.406, de 2002 – Código Civil Brasileiro.
    </div>
    <div class="data-local">Campo Limpo de Goiás, <?= $hoje->format('d') ?> de <?= mb_strftime_pt((int)$hoje->format('n')) ?> de <?= $hoje->format('Y') ?>.</div>
    <div class="assinaturas">
      <div class="assinatura-linha"><div class="linha"></div><div class="rot">Assinatura</div></div>
    </div>
  <?php endif; ?>

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
