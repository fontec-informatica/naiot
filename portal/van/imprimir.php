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
$todos = $pst->fetchAll();

// Separa quem tem assento de quem vai no colo
$passageiros = array_values(array_filter($todos, fn($p) => ($p['tipo'] ?? 'normal') !== 'colo'));
$no_colo     = array_values(array_filter($todos, fn($p) => ($p['tipo'] ?? 'normal') === 'colo'));

// Garante mínimo de 19 linhas na tabela de assentos
$minLinhas = 19;
while (count($passageiros) < $minLinhas) {
    $passageiros[] = ['nome' => '', 'cpf_rg' => '', 'nota' => '', 'tipo' => 'normal'];
}

// Data da declaração (hoje)
$meses = ['JANEIRO','FEVEREIRO','MARÇO','ABRIL','MAIO','JUNHO',
          'JULHO','AGOSTO','SETEMBRO','OUTUBRO','NOVEMBRO','DEZEMBRO'];
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
  background: #d8d8d8;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

/* ── Barra de ações (tela apenas) ── */
.barra-acoes {
  background: #fff;
  border-bottom: 2px solid #2d7a45;
  padding: 10px 20px;
  display: flex;
  gap: 10px;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 100;
  flex-wrap: wrap;
}
.btn-imp {
  background: #2d7a45; color: #fff; border: none;
  padding: 8px 22px; border-radius: 6px; font-size: .88rem;
  cursor: pointer; font-weight: 600; font-family: inherit;
}
.btn-imp:hover { background: #235e36 }
.btn-vol {
  background: none; color: #2d7a45; border: 1.5px solid #2d7a45;
  padding: 7px 16px; border-radius: 6px; font-size: .88rem;
  cursor: pointer; font-weight: 600; text-decoration: none; font-family: inherit;
}

/* ── Páginas ── */
.pagina {
  width: 210mm;
  min-height: 297mm;
  background: #fff;
  margin: 20px auto;
  padding: 14mm 16mm 14mm;
  box-shadow: 0 2px 14px rgba(0,0,0,.18);
  display: flex;
  flex-direction: column;
}

/* ── Cabeçalho comum ── */
.cab { text-align: center; }
.cab img { max-height: 58pt; max-width: 170pt; }
.cab-linha { border: none; border-top: 1.5px solid #000; margin: 6pt 0 10pt; }

/* ── Página 1 ── */
.titulo {
  text-align: center;
  font-size: 12.5pt;
  font-weight: bold;
  text-decoration: underline;
  letter-spacing: .4px;
  margin-bottom: 12pt;
}
.empresa {
  text-align: center;
  font-size: 10pt;
  font-weight: bold;
  line-height: 1.6;
  margin-bottom: 12pt;
}
.campos {
  font-size: 10.5pt;
  line-height: 1.75;
  margin-bottom: 10pt;
}
.campos b { font-weight: bold }
.rot-extra {
  font-size: 10.5pt;
  font-weight: bold;
  margin-bottom: 8pt;
}
.tabela-pass {
  width: 100%;
  border-collapse: collapse;
  font-size: 9.5pt;
  flex-shrink: 0;
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
  vertical-align: middle;
}
.tabela-pass .c-num  { width: 5%;  text-align: center; font-weight: bold }
.tabela-pass .c-nome { width: 46% }
.tabela-pass .c-cpf  { width: 24%; text-align: center }
.tabela-pass .c-asn  { width: 25% }

/* ── Rodapé ── */
.spacer { flex: 1 }
.rodape {
  text-align: center;
  font-size: 8.5pt;
  line-height: 1.6;
  margin-top: 10pt;
  border-top: 1px solid #000;
  padding-top: 5pt;
}

/* ── Página 2 ── */
.declaracao {
  font-size: 10.5pt;
  line-height: 2;
  text-align: justify;
  margin: 12pt 0 20pt;
}
.data-local { font-size: 10.5pt; margin-bottom: 50pt }
.assinatura {
  text-align: center;
  margin-bottom: 36pt;
  line-height: 1.7;
}
.assinatura .o1 { font-size: 11pt; font-weight: bold }
.assinatura .o2 { font-size: 10.5pt; font-weight: bold; margin-bottom: 16pt }
.assinatura .p1 { font-size: 10.5pt }
.assinatura .p2 { font-size: 10pt; color: #333 }
.agr-caixa {
  width: 90mm;
  margin: 0 auto;
  border: 2px solid #000;
}
.agr-titulo  { text-align:center; font-weight:bold; font-size:11pt; padding:5pt 8pt; border-bottom:1px solid #000 }
.agr-corpo   { height: 32mm; border-bottom: 1px solid #000 }
.agr-rodape  { padding: 5pt 8pt; font-size:10.5pt; font-weight:bold }

/* ── Print ── */
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

<!-- Barra de ações -->
<div class="barra-acoes">
  <button class="btn-imp" onclick="window.print()">🖨 Imprimir</button>
  <a class="btn-vol" href="/portal/van/nova.php?id=<?= $id ?>">← Editar viagem</a>
  <a class="btn-vol" href="/portal/van/">Lista de viagens</a>
  <span style="font-size:.8rem;color:#666;margin-left:8px">
    <?= htmlspecialchars($viagem['destino']) ?> — <?= htmlspecialchars($viagem['data_texto']) ?>
  </span>
</div>

<!-- ══════════════════════════════════════
     PÁGINA 1 — Relação de passageiros
══════════════════════════════════════ -->
<div class="pagina">

  <div class="cab">
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
    <b>Origem:</b> Comunidade Católica Senhor Jesus - Naiot (Campo Limpo - Go)<br>
    <b>Destino:</b> <?= htmlspecialchars(mb_strtoupper($viagem['destino'], 'UTF-8')) ?><br>
    <b>Data:</b> <?= htmlspecialchars($viagem['data_texto']) ?>
  </div>

  <div class="rot-extra">
    1.&nbsp;&nbsp;Motorista:
    <?= htmlspecialchars($viagem['motorista_nome'] ?? '') ?>
    <?php if (!empty($viagem['motorista_cpf'])): ?>
      - CPF: <?= htmlspecialchars($viagem['motorista_cpf']) ?>
    <?php endif; ?>
  </div>

  <?php if (!empty($viagem['coordenador_nome'])): ?>
  <div class="rot-extra" style="margin-bottom:10pt">
    Coordenador da missão:
    <?= htmlspecialchars($viagem['coordenador_nome']) ?>
    <?php if (!empty($viagem['coordenador_cpf'])): ?>
      - CPF: <?= htmlspecialchars($viagem['coordenador_cpf']) ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <table class="tabela-pass">
    <thead>
      <tr>
        <th class="c-num">&gt;&gt;</th>
        <th class="c-nome">Nome Completo:</th>
        <th class="c-cpf">RG/CPF</th>
        <th class="c-asn">Assinatura do passageiro:</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($passageiros as $i => $p):
        $nomeFull = htmlspecialchars($p['nome']);
        if (($p['tipo'] ?? '') === 'cadeirinha') $nomeFull .= ' <em style="font-size:8pt;color:#555">(cadeirinha)</em>';
        if (!empty($p['nota'])) $nomeFull .= ' <em style="font-size:8pt;color:#555">('.htmlspecialchars($p['nota']).')</em>';
      ?>
      <tr>
        <td class="c-num"><?= $p['nome'] ? $i + 2 : '' ?></td>
        <td class="c-nome"><?= $nomeFull ?></td>
        <td class="c-cpf"><?= htmlspecialchars($p['cpf_rg'] ?? '') ?></td>
        <td class="c-asn">&nbsp;</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if (!empty($no_colo)): ?>
  <div style="margin-top:8pt;font-size:9.5pt;border-top:1px dashed #000;padding-top:6pt">
    <strong>Crianças / bebês no colo:</strong>
    <?php foreach ($no_colo as $j => $c):
      $cn = htmlspecialchars($c['nome']);
      if (!empty($c['cpf_rg'])) $cn .= ' (CPF/RG: '.htmlspecialchars($c['cpf_rg']).')';
      if (!empty($c['nota']))   $cn .= ' — '.htmlspecialchars($c['nota']);
      echo ($j > 0 ? ' &bull; ' : '') . $cn;
    endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="spacer"></div>

  <div class="rodape">
    GO 330, km 20, s/n, Fazenda Poções, Zona Rural, Campo Limpo de Goiás – GO – BRA.<br>
    ● 62 – 99127 - 5563 / 99404 - 1501 &nbsp;&nbsp; http://naiot.com.br/novo/
  </div>

</div>

<!-- ══════════════════════════════════════
     PÁGINA 2 — Declaração
══════════════════════════════════════ -->
<div class="pagina quebra">

  <div class="cab">
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
    <div class="o1">COMUNIDADE CATÓLICA SENHOR JESUS</div>
    <div class="o2">Mantenedora da Casa de Acolhimento Mulheres de Jesus Cristo</div>
    <div class="p1">José Antônio Ferreira</div>
    <div class="p2">Presidente Fundador</div>
  </div>

  <div class="agr-caixa">
    <div class="agr-titulo">PARA USO DA AGR</div>
    <div class="agr-corpo"></div>
    <div class="agr-rodape">LOCAL E DATA:</div>
  </div>

  <div class="spacer"></div>

  <div class="rodape">
    GO 330, km 20, s/n, Fazenda Poções, Zona Rural, Campo Limpo de Goiás – GO – BRA.<br>
    ● 62 – 99127 - 5563 / 99404 - 1501 &nbsp;&nbsp; http://naiot.com.br/novo/
  </div>

</div>

</body>
</html>
