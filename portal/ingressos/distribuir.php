<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$campanha_id = (int)($_GET['id'] ?? 0);
if (!$campanha_id) { header('Location: /portal/ingressos/'); exit; }

$c_stmt = db()->prepare('SELECT * FROM ingressos_campanhas WHERE id = ?');
$c_stmt->execute([$campanha_id]);
$campanha = $c_stmt->fetch();
if (!$campanha) { header('Location: /portal/ingressos/'); exit; }

$titulo       = 'Distribuir Ingressos — ' . $campanha['nome'];
$pagina_ativa = 'ingressos';
$ing_secao    = 'distribuir';
$ing_campanha = $campanha;
$erro         = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'distribuir') {
        $servo_id     = (int)($_POST['servo_id'] ?? 0) ?: null;
        $servo_nome   = trim($_POST['servo_nome'] ?? '');
        $quantidade   = (int)($_POST['quantidade'] ?? 0);
        $tipo_filtro  = in_array($_POST['tipo_filtro'] ?? '', ['mesa', 'individual'], true) ? $_POST['tipo_filtro'] : null;

        if (!$servo_nome) {
            $erro = 'Informe o nome do servo/membro.';
        } elseif ($quantidade < 1) {
            $erro = 'Quantidade inválida.';
        } else {
            $sql = 'SELECT id FROM ingressos_tickets WHERE campanha_id = ? AND status = "estoque"';
            $params = [$campanha_id];
            if ($tipo_filtro) { $sql .= ' AND tipo = ?'; $params[] = $tipo_filtro; }
            $sql .= ' ORDER BY numero ASC LIMIT ' . (int)$quantidade;
            $disp = db()->prepare($sql);
            $disp->execute($params);
            $ids = $disp->fetchAll(PDO::FETCH_COLUMN);

            if (count($ids) < $quantidade) {
                $erro = 'Só há ' . count($ids) . ' ingresso(s) disponível(is) em estoque para essa seleção.';
            } else {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $upd = db()->prepare("UPDATE ingressos_tickets SET status='distribuido', servo_id=?, servo_nome=?, data_distribuicao=CURDATE() WHERE id IN ($ph)");
                $upd->execute(array_merge([$servo_id, $servo_nome], $ids));
                header("Location: /portal/ingressos/distribuir.php?id=$campanha_id&ok=distribuido");
                exit;
            }
        }
    }
}

/* ── Estoque disponível (visão rápida) ── */
$estoque_stmt = db()->prepare("
    SELECT tipo, COUNT(*) AS qtd, MIN(numero) AS proximo
    FROM ingressos_tickets
    WHERE campanha_id = ? AND status = 'estoque'
    GROUP BY tipo
");
$estoque_stmt->execute([$campanha_id]);
$estoque = $estoque_stmt->fetchAll();

/* ── Últimas distribuições ── */
$recentes_stmt = db()->prepare("
    SELECT servo_nome, tipo, COUNT(*) AS qtd, MIN(numero) AS de, MAX(numero) AS ate, MAX(data_distribuicao) AS quando
    FROM ingressos_tickets
    WHERE campanha_id = ? AND servo_nome IS NOT NULL
    GROUP BY servo_nome, tipo, data_distribuicao
    ORDER BY data_distribuicao DESC, servo_nome ASC
    LIMIT 20
");
$recentes_stmt->execute([$campanha_id]);
$recentes = $recentes_stmt->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<?php include __DIR__ . '/_subnav.php'; ?>

<?php if (($_GET['ok'] ?? '') === 'distribuido'): ?><div class="alerta alerta-ok" style="margin-bottom:16px">✓ Ingressos distribuídos com sucesso.</div><?php endif; ?>
<?php if ($erro): ?><div class="alerta alerta-erro" style="margin-bottom:16px"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<?php if (!empty($estoque)): ?>
<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($estoque as $e): ?>
  <div style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;padding:10px 18px">
    <span style="font-size:.72rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em"><?= $e['tipo'] === 'mesa' ? 'Mesa' : 'Individual' ?> em estoque</span><br>
    <span style="font-size:1rem;font-weight:700;color:#334155"><?= (int)$e['qtd'] ?></span>
    <span style="font-size:.78rem;color:var(--cinza3)"> — próximo nº <?= str_pad((string)$e['proximo'], 3, '0', STR_PAD_LEFT) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="split-layout">

  <div>
    <div class="tabela-wrap">
      <div class="tabela-header"><h2>Distribuições recentes</h2></div>
      <?php if (empty($recentes)): ?>
      <div style="padding:40px;text-align:center;color:var(--cinza3)">Nenhuma distribuição feita ainda. Use o formulário ao lado →</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Servo</th>
            <th>Tipo</th>
            <th style="text-align:center">Faixa</th>
            <th style="text-align:center">Qtd</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($recentes as $rc): ?>
        <tr>
          <td><strong><?= htmlspecialchars($rc['servo_nome']) ?></strong></td>
          <td style="font-size:.8rem;color:var(--cinza3)"><?= $rc['tipo'] === 'mesa' ? 'Mesa' : 'Individual' ?></td>
          <td style="text-align:center"><?= str_pad((string)$rc['de'], 3, '0', STR_PAD_LEFT) ?> – <?= str_pad((string)$rc['ate'], 3, '0', STR_PAD_LEFT) ?></td>
          <td style="text-align:center;font-weight:600"><?= (int)$rc['qtd'] ?></td>
          <td style="font-size:.8rem;color:var(--cinza3)"><?= $rc['quando'] ? date('d/m/Y', strtotime($rc['quando'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="form-wrap" style="max-width:none">
      <h2>Distribuir a um servo</h2>
      <form method="post" novalidate autocomplete="off" style="position:relative">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao" value="distribuir">
        <input type="hidden" name="servo_id" id="servoId">

        <div class="form-group" style="position:relative">
          <label>Servo</label>
          <input type="text" id="servoBusca" placeholder="Buscar membro cadastrado...">
          <div class="srch-drop" id="servoDrop"></div>
          <input type="text" name="servo_nome" id="servoNome" placeholder="Ou digite o nome livremente" required style="margin-top:6px">
        </div>

        <div class="form-group">
          <label>Quantidade de ingressos</label>
          <input type="number" name="quantidade" min="1" required placeholder="Ex: 10">
          <span class="form-hint">São escolhidos automaticamente os de menor número ainda em estoque.</span>
        </div>

        <div class="form-group">
          <label>Tipo <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
          <select name="tipo_filtro">
            <option value="">Tanto faz</option>
            <option value="mesa">Somente Mesa</option>
            <option value="individual">Somente Individual</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%">Distribuir</button>
      </form>
    </div>
  </div>

</div>

<style>
.srch-drop{position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:99;background:#fff;border:1px solid var(--cinza2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);max-height:220px;overflow-y:auto;display:none}
.srch-drop.aberto{display:block}
.srch-item{padding:9px 14px;cursor:pointer;border-bottom:1px solid var(--cinza2)}
.srch-item:last-child{border-bottom:none}
.srch-item:hover{background:var(--cinza1)}
.srch-item strong{display:block;font-size:.87rem}
.srch-item span{font-size:.74rem;color:var(--cinza3)}
</style>

<script>
(function(){
  var $busca = document.getElementById('servoBusca');
  var $drop  = document.getElementById('servoDrop');
  var $id    = document.getElementById('servoId');
  var $nome  = document.getElementById('servoNome');
  var timer;

  function esc(s){ return (s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

  $busca.addEventListener('input', function(){
    clearTimeout(timer);
    var q = this.value.trim();
    $id.value = '';
    if (q.length < 2) { $drop.classList.remove('aberto'); return; }
    timer = setTimeout(function(){
      fetch('/portal/ingressos/buscar.php?q=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (!data.length) { $drop.classList.remove('aberto'); return; }
          $drop.innerHTML = data.map(function(m){
            return '<div class="srch-item" data-id="'+m.id+'" data-nome="'+esc(m.nome)+'"><strong>'+esc(m.nome)+'</strong>'+(m.telefone?'<span>'+esc(m.telefone)+'</span>':'')+'</div>';
          }).join('');
          $drop.classList.add('aberto');
        });
    }, 260);
  });

  $drop.addEventListener('click', function(e){
    var item = e.target.closest('.srch-item');
    if (!item) return;
    $id.value = item.dataset.id;
    $nome.value = item.dataset.nome;
    $busca.value = item.dataset.nome;
    $drop.classList.remove('aberto');
  });

  document.addEventListener('click', function(e){
    if (!$drop.contains(e.target) && e.target !== $busca) $drop.classList.remove('aberto');
  });
})();
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
