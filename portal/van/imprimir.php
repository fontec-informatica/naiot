<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/van/'); exit; }

$st = db()->prepare("SELECT * FROM van_viagens WHERE id=?");
$st->execute([$id]);
$viagem = $st->fetch();
if (!$viagem) { header('Location: /portal/van/'); exit; }

$pst = db()->prepare("SELECT * FROM van_passageiros WHERE viagem_id=? ORDER BY ordem");
$pst->execute([$id]);
$passageiros = $pst->fetchAll();

// Padded até 19 linhas
while (count($passageiros) < 19) {
    $passageiros[] = ['nome' => '', 'cpf_rg' => ''];
}

// Data da declaração
$meses = ['JANEIRO','FEVEREIRO','MARÇO','ABRIL','MAIO','JUNHO','JULHO',
          'AGOSTO','SETEMBRO','OUTUBRO','NOVEMBRO','DEZEMBRO'];
$hoje = new DateTime();
$data_decl = sprintf('%02d DE %s DE %d',
    (int)$hoje->format('d'), $meses[(int)$hoje->format('n') - 1], (int)$hoje->format('Y'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Relação de Passageiros — <?= htmlspecialchars($viagem['destino']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0 }

body {
  font-family: 'Times New Roman', Times, serif;
  font-size: 11pt;
  color: #000;
  background: #e0e0e0;
}

.pagina {
  width: 210mm;
  min-height: 297mm;
  background: #fff;
  margin: 0 auto 20px;
  padding: 14mm 16mm 14mm;
  box-shadow: 0 2px 12px rgba(0,0,0,.15);
}

/* ── Cabeçalho ── */
.cab-logo {
  text-align: center;
  margin-bottom: 6pt;
}
.cab-logo img {
  max-height: 60pt;
  max-width: 180pt;
}
.cab-linha {
  border: none;
  border-top: 1px solid #000;
  margin: 6pt 0;
}

/* ── Título ── */
.titulo {
  text-align: center;
  font-size: 12pt;
  font-weight: bold;
  text-decoration: underline;
  text-underline-offset: 3px;
  letter-spacing: .5px;
  margin: 10pt 0 8pt;
}

/* ── Bloco empresa ── */
.empresa {
  text-align: center;
  font-size: 10pt;
  font-weight: bold;
  line-height: 1.55;
  margin-bottom: 10pt;
}

/* ── Campos variáveis ── */
.campos {
  font-size: 10.5pt;
  margin-bottom: 10pt;
  line-height: 1.7;
}
.campos span { font-weight: bold }

/* ── Motorista ── */
.motorista {
  font-size: 10.5pt;
  font-weight: bold;
  margin-bottom: 6pt;
}

/* ── Tabela de passageiros ── */
.tabela-pass {
  width: 100%;
  border-collapse: collapse;
  font-size: 9.5pt;
}
.tabela-pass th {
  border: 1px solid #000;
  padding: 4pt 5pt;
  background: #f0f0f0;
  font-weight: bold;
  text-align: center;
}
.tabela-pass td {
  border: 1px solid #000;
  padding: 3pt 5pt;
  height: 13pt;
}
.tabela-pass .col-num  { width: 5%;  text-align: center; font-weight: bold }
.tabela-pass .col-nome { width: 46% }
.tabela-pass .col-cpf  { width: 24% }
.tabela-pass .col-asn  { width: 25% }

/* ── Rodapé ── */
.rodape {
  text-align: center;
  font-size: 8.5pt;
  line-height: 1.5;
  margin-top: 8pt;
  border-top: 1px solid #000;
  padding-top: 4pt;
}

/* ── Página 2 ── */
.declaracao {
  font-size: 10.5pt;
  line-height: 1.9;
  text-align: justify;
  margin: 14pt 0 18pt;
}
.data-local {
  font-size: 10.5pt;
  margin-bottom: 40pt;
}
.assinatura {
  text-align: center;
  margin-bottom: 30pt;
}
.assinatura .nome-pres {
  font-size: 11pt;
  margin-bottom: 2pt;
}
.assinatura .cargo-pres {
  font-size: 10pt;
}
.assinatura .org {
  font-weight: bold;
  font-size: 11pt;
  margin-bottom: 2pt;
}
.assinatura .sub-org {
  font-weight: bold;
  font-size: 10pt;
  margin-bottom: 20pt;
}
.agr-box {
  border: 2px solid #000;
  width: 80mm;
  margin: 0 auto;
}
.agr-box th {
  display: block;
  padding: 5pt 8pt;
  font-size: 11pt;
  font-weight: bold;
  text-align: center;
  border-bottom: 1px solid #000;
}
.agr-box .agr-corpo {
  height: 30mm;
  border-bottom: 1px solid #000;
}
.agr-box .agr-rodape {
  padding: 5pt 8pt;
  font-size: 10.5pt;
  font-weight: bold;
}

/* ── Barra de ações (tela) ── */
.barra-acoes {
  background: #fff;
  border-bottom: 2px solid #2d7a45;
  padding: 10px 16px;
  display: flex;
  gap: 10px;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 100;
}
.btn-imp {
  background: #2d7a45; color: #fff; border: none;
  padding: 8px 20px; border-radius: 6px; font-size: .9rem;
  cursor: pointer; font-weight: 600;
}
.btn-vol {
  background: none; color: #2d7a45; border: 1.5px solid #2d7a45;
  padding: 7px 16px; border-radius: 6px; font-size: .9rem;
  cursor: pointer; font-weight: 600; text-decoration: none;
}

@media print {
  body { background: #fff }
  .barra-acoes { display: none }
  .pagina { margin: 0; box-shadow: none; padding: 10mm 14mm }
  @page { size: A4; margin: 0 }
  .quebra { page-break-before: always }
}
</style>
</head>
<body>

<!-- Barra de ações (não imprime) -->
<div class="barra-acoes">
  <button class="btn-imp" onclick="window.print()">🖨 Imprimir</button>
  <a class="btn-vol" href="/portal/van/nova.php?id=<?= $id ?>">← Editar viagem</a>
  <a class="btn-vol" href="/portal/van/">Lista de viagens</a>
</div>

<!-- ══════════════════════════════════════════════════ -->
<!-- PÁGINA 1 — Relação de passageiros                 -->
<!-- ══════════════════════════════════════════════════ -->
<div class="pagina">

  <div class="cab-logo">
    <img src="/assets/img/logo.png" alt="NAIOT">
  </div>
  <hr class="cab-linha">

  <div class="titulo">RELAÇÃO DE PASSAGEIROS / ITINERÁRIO</div>

  <div class="empresa">
    RAZÃO SOCIAL: Comunidade Católica Senhor Jesus - Casa de Retiros Naiot<br>
    CNPJ: 05.834.351.0001 - 11<br>
    GO 330, Km 20, Zona Rural de Campo Limpo de Goiás.<br>
    MANTENEDORA DA CASA DE ACOLHIMENTO MULHERES DE JESUS CRISTO
  </div>

  <div class="campos">
    <span>Origem:</span> Comunidade Católica Senhor Jesus - Naiot (Campo Limpo - Go)<br>
    <span>Destino:</span> <?= htmlspecialchars(strtoupper($viagem['destino'])) ?><br>
    <span>Data:</span> <?= htmlspecialchars($viagem['data_texto']) ?>
  </div>

  <div class="motorista">
    1. &nbsp;Motorista: <?= htmlspecialchars($viagem['motorista_nome'] ?? '') ?>
    <?php if ($viagem['motorista_cpf']): ?>
      - CPF: <?= htmlspecialchars($viagem['motorista_cpf']) ?>
    <?php endif; ?>
  </div>

  <table class="tabela-pass">
    <thead>
      <tr>
        <th class="col-num">&gt;&gt;</th>
        <th class="col-nome">Nome Completo:</th>
        <th class="col-cpf">RG/CPF</th>
        <th class="col-asn">Assinatura do passageiro:</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($passageiros as $i => $p): ?>
      <tr>
        <td class="col-num"><?= $i + 2 ?></td>
        <td class="col-nome"><?= htmlspecialchars($p['nome']) ?></td>
        <td class="col-cpf"><?= htmlspecialchars($p['cpf_rg'] ?? '') ?></td>
        <td class="col-asn">&nbsp;</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="rodape">
    GO 330, km 20, s/n, Fazenda Poções, Zona Rural, Campo Limpo de Goiás – GO – BRA.<br>
    ● 62 – 99127 - 5563 / 99404 - 1501 &nbsp;&nbsp; http://naiot.com.br/novo/
  </div>

</div>

<!-- ══════════════════════════════════════════════════ -->
<!-- PÁGINA 2 — Declaração                             -->
<!-- ══════════════════════════════════════════════════ -->
<div class="pagina quebra">

  <div class="cab-logo">
    <img src="/assets/img/logo.png" alt="NAIOT">
  </div>
  <hr class="cab-linha">

  <div class="declaracao">
    Declaro sob as penas da lei, que todos os passageiros acima descritos são servos ou membros da
    Comunidade Católica Senhor Jesus - Casa de Retiros Naiot, assim declarados nos termos de seu estatuto
    pelo Presidente abaixo firmado. Declaro ainda que as informações prestadas são verdadeiras e estão de
    acordo com os critérios estabelecidos pela AGR.
  </div>

  <div class="data-local">
    CAMPO LIMPO DE GOIÁS, <?= $data_decl ?>.
  </div>

  <div class="assinatura">
    <div class="org">COMUNIDADE CATÓLICA SENHOR JESUS</div>
    <div class="sub-org">Mantenedora da Casa de Acolhimento Mulheres de Jesus Cristo</div>
    <div class="nome-pres">José Antônio Ferreira</div>
    <div class="cargo-pres">Presidente Fundador</div>
  </div>

  <div style="border:2px solid #000;width:80mm;margin:0 auto">
    <div style="padding:5pt 8pt;font-size:11pt;font-weight:bold;text-align:center;border-bottom:1px solid #000">PARA USO DA AGR</div>
    <div style="height:30mm;border-bottom:1px solid #000">&nbsp;</div>
    <div style="padding:5pt 8pt;font-size:10.5pt;font-weight:bold">LOCAL E DATA:</div>
  </div>

  <div class="rodape" style="margin-top:40pt">
    GO 330, km 20, s/n, Fazenda Poções, Zona Rural, Campo Limpo de Goiás – GO – BRA.<br>
    ● 62 – 99127 - 5563 / 99404 - 1501 &nbsp;&nbsp; http://naiot.com.br/novo/
  </div>

</div>

</body>
</html>
