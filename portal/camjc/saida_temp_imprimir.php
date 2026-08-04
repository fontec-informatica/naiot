<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("
    SELECT s.*, a.nome, a.cpf, a.rg
    FROM camjc_saidas_temporarias s
    JOIN camjc_acolhidas a ON a.id = s.acolhida_id
    WHERE s.id = ?
");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) { header('Location: /portal/camjc/'); exit; }

camjc_log('imprimiu_saida_temporaria', $r['acolhida_id'], 'Saída temp. #' . $id);

$d = new DateTime($r['data_saida']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Termo de Saída Temporária — <?= htmlspecialchars($r['nome']) ?></title>
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
@media print {
  body { background: #fff; margin: 0; padding: 0 }
  .barra-acoes { display: none }
  @page { size: A4; margin: 0 }
  .pagina { box-shadow: none !important; margin: 0 !important; width: auto !important; min-height: 0 !important; }
}
</style>
</head>
<body>

<div class="barra-acoes">
  <button class="btn-imp" onclick="window.print()">🖨 Imprimir</button>
  <a class="btn-vol" href="/portal/camjc/ver.php?id=<?= $r['acolhida_id'] ?>">← Voltar</a>
  <span style="font-size:.8rem;color:#666;margin-left:8px"><?= htmlspecialchars($r['nome']) ?> — saída de <?= date('d/m/Y', strtotime($r['data_saida'])) ?></span>
</div>

<div class="pagina">
  <div class="cab"><img src="/assets/img/logo.png" alt="NAIOT"></div>
  <hr class="cab-linha">

  <div class="titulo">Termo de Saída Temporária</div>
  <div class="corpo">
    Nesta data, encaminhamos a Acolhida <b><?= htmlspecialchars($r['nome']) ?></b>,
    portadora do CPF <b><?= htmlspecialchars($r['cpf'] ?? '________________') ?></b> e
    RG <b><?= htmlspecialchars($r['rg'] ?? '________________') ?></b>,
    aos cuidados de seu(sua) responsável <b><?= htmlspecialchars($r['responsavel_nome'] ?? '________________________________') ?></b>,
    tendo em vista autorização para saída temporária, a fim de
    <b><?= htmlspecialchars($r['destino_motivo'] ?? '________________________________') ?></b>,
    no dia <b><?= $d->format('d') ?></b> de <b><?= mb_strftime_pt((int)$d->format('n')) ?></b> de <b><?= $d->format('Y') ?></b>.
    <br><br>
    O responsável compromete-se a conduzir a Acolhida diretamente ao destino e trazê-la à Comunidade imediatamente
    após a conclusão. O desrespeito a este compromisso — havendo desvio de percurso ou alteração do objetivo da
    saída, como por exemplo sair a passeio, visitar amigos, parentes ou a própria residência, realizar compras ou
    atender outros interesses (dirigir o veículo, namorar, consumir bebidas alcoólicas, etc.) — é fato grave e
    poderá resultar em desligamento imediato da Acolhida e de sua família de nossa Comunidade Terapêutica.
  </div>

  <div class="data-local">Campo Limpo de Goiás, <?= $d->format('d') ?> de <?= mb_strftime_pt((int)$d->format('n')) ?> de <?= $d->format('Y') ?>.</div>

  <div class="assinaturas">
    <div class="assinatura-linha">
      <div class="linha"></div>
      <div class="rot">
        Assinatura do responsável
        <?= !empty($r['responsavel_rg']) || !empty($r['responsavel_cpf']) ? ' — RG ' . htmlspecialchars($r['responsavel_rg'] ?? '—') . ' / CPF ' . htmlspecialchars($r['responsavel_cpf'] ?? '—') : '' ?>
        <?= !empty($r['responsavel_telefone']) ? ' — Fone ' . htmlspecialchars($r['responsavel_telefone']) : '' ?>
      </div>
    </div>
  </div>

  <div class="rodape">
    GO 330, km 20, s/n, Fazenda Poções, Zona Rural, Campo Limpo de Goiás – GO – BRA.<br>
    Comunidade Católica Senhor Jesus — Mantenedora da Casa de Acolhimento Mulheres de Jesus Cristo
  </div>
</div>

<script>
document.querySelector('.btn-imp').onclick = function () { window.print(); return false; };
</script>
</body>
</html>
