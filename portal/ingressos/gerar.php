<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$campanha_id = (int)($_GET['id'] ?? 0);
if (!$campanha_id) { header('Location: /portal/ingressos/'); exit; }

$c_stmt = db()->prepare('SELECT * FROM ingressos_campanhas WHERE id = ?');
$c_stmt->execute([$campanha_id]);
$campanha = $c_stmt->fetch();
if (!$campanha) { header('Location: /portal/ingressos/'); exit; }

$titulo       = 'Gerar Ingressos — ' . $campanha['nome'];
$pagina_ativa = 'ingressos';
$ing_secao    = 'gerar';
$ing_campanha = $campanha;
$erro         = '';

function ing_valor_post(string $campo): float {
    return (float)str_replace(',', '.', str_replace('.', '', preg_replace('/[^0-9,]/', '', $_POST[$campo] ?? '0')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'gerar_faixa') {
        $ini  = (int)($_POST['numero_inicio'] ?? -1);
        $fim  = (int)($_POST['numero_fim'] ?? -1);
        $tipo = ($_POST['tipo'] ?? '') === 'individual' ? 'individual' : 'mesa';
        $valor = ing_valor_post('valor');

        if ($ini < 0 || $fim < $ini) {
            $erro = 'Faixa de numeração inválida.';
        } elseif (($fim - $ini + 1) > 1000) {
            $erro = 'Faixa muito grande (máximo 1000 ingressos por vez).';
        } else {
            $chk = db()->prepare('SELECT numero FROM ingressos_tickets WHERE campanha_id = ? AND numero BETWEEN ? AND ? LIMIT 1');
            $chk->execute([$campanha_id, $ini, $fim]);
            if ($chk->fetch()) {
                $erro = 'Já existem ingressos gerados dentro dessa faixa de numeração.';
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                $ins = $pdo->prepare('INSERT INTO ingressos_tickets (campanha_id, numero, tipo, valor) VALUES (?,?,?,?)');
                for ($n = $ini; $n <= $fim; $n++) {
                    $ins->execute([$campanha_id, $n, $tipo, $valor]);
                }
                $pdo->commit();
                header("Location: /portal/ingressos/gerar.php?id=$campanha_id&ok=gerado");
                exit;
            }
        }

    } elseif ($acao === 'marcar_prep') {
        $ini = (int)($_POST['prep_inicio'] ?? -1);
        $fim = (int)($_POST['prep_fim'] ?? -1);
        $carimbado = !empty($_POST['carimbado']) ? 1 : 0;
        $assinado  = !empty($_POST['assinado'])  ? 1 : 0;
        if ($ini >= 0 && $fim >= $ini) {
            db()->prepare('UPDATE ingressos_tickets SET carimbado=?, assinado=? WHERE campanha_id=? AND numero BETWEEN ? AND ?')
                ->execute([$carimbado, $assinado, $campanha_id, $ini, $fim]);
            header("Location: /portal/ingressos/gerar.php?id=$campanha_id&ok=prep");
            exit;
        }
        $erro = 'Faixa inválida para marcar carimbo/assinatura.';
    }
}

/* ── Últimas faixas geradas (visão rápida) ── */
$faixas_stmt = db()->prepare("
    SELECT tipo, MIN(numero) AS de, MAX(numero) AS ate, COUNT(*) AS qtd, valor
    FROM ingressos_tickets
    WHERE campanha_id = ?
    GROUP BY tipo, valor
    ORDER BY de ASC
");
$faixas_stmt->execute([$campanha_id]);
$faixas = $faixas_stmt->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<?php include __DIR__ . '/_subnav.php'; ?>

<?php if (($_GET['ok'] ?? '') === 'gerado'): ?><div class="alerta alerta-ok" style="margin-bottom:16px">✓ Ingressos gerados com sucesso.</div><?php endif; ?>
<?php if (($_GET['ok'] ?? '') === 'prep'): ?><div class="alerta alerta-ok" style="margin-bottom:16px">✓ Carimbo/assinatura atualizados.</div><?php endif; ?>
<?php if ($erro): ?><div class="alerta alerta-erro" style="margin-bottom:16px"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div class="split-layout">

  <div>
    <div class="tabela-wrap">
      <div class="tabela-header"><h2>Faixas já geradas</h2></div>
      <?php if (empty($faixas)): ?>
      <div style="padding:40px;text-align:center;color:var(--cinza3)">Nenhum ingresso gerado ainda. Use o formulário ao lado →</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Tipo</th>
            <th style="text-align:center">Faixa</th>
            <th style="text-align:center">Quantidade</th>
            <th style="text-align:right">Valor unitário</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($faixas as $f): ?>
        <tr>
          <td><?= $f['tipo'] === 'mesa' ? 'Mesa' : 'Individual' ?></td>
          <td style="text-align:center"><?= str_pad((string)$f['de'], 3, '0', STR_PAD_LEFT) ?> – <?= str_pad((string)$f['ate'], 3, '0', STR_PAD_LEFT) ?></td>
          <td style="text-align:center;font-weight:600"><?= (int)$f['qtd'] ?></td>
          <td style="text-align:right;font-weight:600">R$ <?= number_format($f['valor'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="form-wrap" style="max-width:none;margin-bottom:20px">
      <h2>Gerar ingressos</h2>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao" value="gerar_faixa">
        <div class="form-row">
          <div class="form-group">
            <label>Nº inicial</label>
            <input type="number" name="numero_inicio" min="0" required placeholder="0">
          </div>
          <div class="form-group">
            <label>Nº final</label>
            <input type="number" name="numero_fim" min="0" required placeholder="450">
          </div>
        </div>
        <div class="form-group">
          <label>Tipo</label>
          <select name="tipo" id="selTipoGerar">
            <option value="mesa">Mesa</option>
            <option value="individual">Individual</option>
          </select>
        </div>
        <div class="form-group">
          <label>Valor unitário (R$)</label>
          <input type="text" name="valor" id="valorGerar" placeholder="0,00" value="<?= $campanha['valor_mesa'] > 0 ? number_format($campanha['valor_mesa'], 2, ',', '') : '' ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Gerar faixa</button>
        <span class="form-hint">Repita o formulário para gerar Mesas e Individuais em faixas de numeração separadas.</span>
      </form>
    </div>

    <div class="form-wrap" style="max-width:none">
      <h2>Marcar carimbo/assinatura</h2>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao" value="marcar_prep">
        <div class="form-row">
          <div class="form-group">
            <label>Nº inicial</label>
            <input type="number" name="prep_inicio" min="0" required>
          </div>
          <div class="form-group">
            <label>Nº final</label>
            <input type="number" name="prep_fim" min="0" required>
          </div>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="carimbado" value="1" checked> Carimbado
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:6px">
            <input type="checkbox" name="assinado" value="1" checked> Assinado
          </label>
        </div>
        <button type="submit" class="btn btn-ouro" style="width:100%">Atualizar faixa</button>
      </form>
    </div>
  </div>

</div>

<script>
var VALOR_MESA = <?= (float)$campanha['valor_mesa'] ?>;
var VALOR_INDIVIDUAL = <?= (float)$campanha['valor_individual'] ?>;
document.getElementById('selTipoGerar').addEventListener('change', function(){
  var v = this.value === 'mesa' ? VALOR_MESA : VALOR_INDIVIDUAL;
  if (v > 0) document.getElementById('valorGerar').value = v.toFixed(2).replace('.', ',');
});
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
