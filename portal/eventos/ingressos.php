<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$evento_id = (int)($_GET['id'] ?? 0);
if (!$evento_id) { header('Location: /portal/eventos/'); exit; }

$ev_stmt = db()->prepare('SELECT * FROM eventos WHERE id = ?');
$ev_stmt->execute([$evento_id]);
$evento = $ev_stmt->fetch();
if (!$evento) { header('Location: /portal/eventos/'); exit; }

$titulo       = 'Ingressos — ' . $evento['titulo'];
$pagina_ativa = 'eventos';
$erro         = '';

function ing_valor_post(string $campo): float {
    return (float)str_replace(',', '.', str_replace('.', '', preg_replace('/[^0-9,]/', '', $_POST[$campo] ?? '0')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) { $erro = 'Token inválido.'; goto fim_post; }
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'gerar_faixa') {
        $ini  = (int)($_POST['numero_inicio'] ?? -1);
        $fim  = (int)($_POST['numero_fim'] ?? -1);
        $tipo = ($_POST['tipo'] ?? '') === 'mesa' ? 'mesa' : 'individual';
        $valor = ing_valor_post('valor');

        if ($ini < 0 || $fim < $ini) {
            $erro = 'Faixa de numeração inválida.';
        } elseif (($fim - $ini + 1) > 1000) {
            $erro = 'Faixa muito grande (máximo 1000 ingressos por vez).';
        } else {
            $chk = db()->prepare('SELECT numero FROM evento_ingressos WHERE evento_id = ? AND numero BETWEEN ? AND ? LIMIT 1');
            $chk->execute([$evento_id, $ini, $fim]);
            if ($chk->fetch()) {
                $erro = 'Já existem ingressos gerados dentro dessa faixa de numeração.';
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                $ins = $pdo->prepare('INSERT INTO evento_ingressos (evento_id, numero, tipo, valor) VALUES (?,?,?,?)');
                for ($n = $ini; $n <= $fim; $n++) {
                    $ins->execute([$evento_id, $n, $tipo, $valor]);
                }
                $pdo->commit();
                header("Location: /portal/eventos/ingressos.php?id=$evento_id&ok=gerado");
                exit;
            }
        }

    } elseif ($acao === 'distribuir') {
        $servo_id     = (int)($_POST['servo_id'] ?? 0) ?: null;
        $servo_nome   = trim($_POST['servo_nome'] ?? '');
        $quantidade   = (int)($_POST['quantidade'] ?? 0);
        $tipo_filtro  = in_array($_POST['tipo_filtro'] ?? '', ['mesa', 'individual'], true) ? $_POST['tipo_filtro'] : null;

        if (!$servo_nome) {
            $erro = 'Informe o nome do servo.';
        } elseif ($quantidade < 1) {
            $erro = 'Quantidade inválida.';
        } else {
            $sql = 'SELECT id FROM evento_ingressos WHERE evento_id = ? AND status = "estoque"';
            $params = [$evento_id];
            if ($tipo_filtro) { $sql .= ' AND tipo = ?'; $params[] = $tipo_filtro; }
            $sql .= ' ORDER BY numero ASC LIMIT ' . (int)$quantidade;
            $disp = db()->prepare($sql);
            $disp->execute($params);
            $ids = $disp->fetchAll(PDO::FETCH_COLUMN);

            if (count($ids) < $quantidade) {
                $erro = 'Só há ' . count($ids) . ' ingresso(s) disponível(is) em estoque para essa seleção.';
            } else {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $upd = db()->prepare("UPDATE evento_ingressos SET status='distribuido', servo_id=?, servo_nome=?, data_distribuicao=CURDATE() WHERE id IN ($ph)");
                $upd->execute(array_merge([$servo_id, $servo_nome], $ids));
                header("Location: /portal/eventos/ingressos.php?id=$evento_id&ok=distribuido");
                exit;
            }
        }

    } elseif (in_array($acao, ['devolver', 'cancelar', 'reativar'], true)) {
        $ing_id = (int)($_POST['ingresso_id'] ?? 0);
        if ($ing_id) {
            if ($acao === 'devolver') {
                db()->prepare("UPDATE evento_ingressos SET status='devolvido' WHERE id=? AND evento_id=? AND status='distribuido'")->execute([$ing_id, $evento_id]);
            } elseif ($acao === 'cancelar') {
                db()->prepare("UPDATE evento_ingressos SET status='cancelado' WHERE id=? AND evento_id=? AND status='estoque'")->execute([$ing_id, $evento_id]);
            } elseif ($acao === 'reativar') {
                db()->prepare("UPDATE evento_ingressos SET status='estoque', servo_id=NULL, servo_nome=NULL, data_distribuicao=NULL WHERE id=? AND evento_id=? AND status IN ('devolvido','cancelado')")->execute([$ing_id, $evento_id]);
            }
        }
        header("Location: /portal/eventos/ingressos.php?id=$evento_id" . ($_GET['status'] ?? '' ? '&status='.$_GET['status'] : ''));
        exit;

    } elseif ($acao === 'marcar_prep') {
        $ini = (int)($_POST['prep_inicio'] ?? -1);
        $fim = (int)($_POST['prep_fim'] ?? -1);
        $carimbado = !empty($_POST['carimbado']) ? 1 : 0;
        $assinado  = !empty($_POST['assinado'])  ? 1 : 0;
        if ($ini >= 0 && $fim >= $ini) {
            db()->prepare('UPDATE evento_ingressos SET carimbado=?, assinado=? WHERE evento_id=? AND numero BETWEEN ? AND ?')
                ->execute([$carimbado, $assinado, $evento_id, $ini, $fim]);
            header("Location: /portal/eventos/ingressos.php?id=$evento_id&ok=prep");
            exit;
        }
        $erro = 'Faixa inválida para marcar carimbo/assinatura.';
    }
}
fim_post:

/* ── Filtros ── */
$f_status = $_GET['status'] ?? '';
$f_tipo   = $_GET['tipo'] ?? '';
$f_q      = trim($_GET['q'] ?? '');

$where  = 'evento_id = ?';
$params = [$evento_id];
if ($f_status !== '') { $where .= ' AND status = ?'; $params[] = $f_status; }
if ($f_tipo   !== '') { $where .= ' AND tipo = ?';   $params[] = $f_tipo; }
if ($f_q      !== '') {
    $where .= ' AND (numero = ? OR servo_nome LIKE ?)';
    $params[] = is_numeric($f_q) ? (int)$f_q : -1;
    $params[] = '%' . $f_q . '%';
}

$lista = db()->prepare("SELECT * FROM evento_ingressos WHERE $where ORDER BY numero ASC");
$lista->execute($params);
$ingressos = $lista->fetchAll();

/* ── Resumo ── */
$resumo_stmt = db()->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status='estoque')     AS estoque,
        SUM(status='distribuido') AS distribuido,
        SUM(status='pago')        AS pago,
        SUM(status='devolvido')   AS devolvido,
        SUM(status='cancelado')   AS cancelado,
        SUM(CASE WHEN status='pago'        THEN COALESCE(valor_pago,valor) ELSE 0 END) AS receita,
        SUM(CASE WHEN status='distribuido' THEN valor ELSE 0 END)                       AS a_receber
    FROM evento_ingressos WHERE evento_id = ?
");
$resumo_stmt->execute([$evento_id]);
$r = $resumo_stmt->fetch();

$status_info = [
    'estoque'     => ['cor' => '#475569', 'bg' => '#f1f5f9', 'label' => 'Em estoque'],
    'distribuido' => ['cor' => '#92400e', 'bg' => '#fef3c7', 'label' => 'Distribuído'],
    'pago'        => ['cor' => '#166534', 'bg' => '#dcfce7', 'label' => 'Pago'],
    'devolvido'   => ['cor' => '#1e3a8a', 'bg' => '#dbeafe', 'label' => 'Devolvido'],
    'cancelado'   => ['cor' => '#991b1b', 'bg' => '#fee2e2', 'label' => 'Cancelado'],
];

include dirname(__DIR__) . '/_layout.php';
?>

<div style="margin-bottom:16px">
  <h2 style="font-size:1rem;font-weight:600"><?= htmlspecialchars($evento['titulo']) ?></h2>
  <a href="/portal/eventos/editar.php?id=<?= $evento_id ?>" style="font-size:.82rem;color:var(--cinza3)">← Editar evento</a>
  ·
  <a href="/portal/eventos/ingressos-posicao.php?id=<?= $evento_id ?>" style="font-size:.82rem;color:var(--cinza3)">Ver posição por servo →</a>
</div>

<?php if (($_GET['ok'] ?? '') === 'gerado'): ?><div class="alerta alerta-ok" style="margin-bottom:16px">✓ Ingressos gerados com sucesso.</div><?php endif; ?>
<?php if (($_GET['ok'] ?? '') === 'distribuido'): ?><div class="alerta alerta-ok" style="margin-bottom:16px">✓ Ingressos distribuídos com sucesso.</div><?php endif; ?>
<?php if (($_GET['ok'] ?? '') === 'prep'): ?><div class="alerta alerta-ok" style="margin-bottom:16px">✓ Carimbo/assinatura atualizados.</div><?php endif; ?>

<!-- Resumo -->
<div class="cards" style="margin-bottom:24px">
  <div class="card-stat"><h3>Emitidos</h3><div class="val"><?= (int)$r['total'] ?></div></div>
  <div class="card-stat"><h3>Em estoque</h3><div class="val"><?= (int)$r['estoque'] ?></div></div>
  <div class="card-stat ouro"><h3>Distribuídos</h3><div class="val"><?= (int)$r['distribuido'] ?></div></div>
  <div class="card-stat verde"><h3>Pagos</h3><div class="val"><?= (int)$r['pago'] ?></div></div>
</div>
<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 18px;display:flex;gap:10px;align-items:center">
    <span style="font-size:.72rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:.06em">Recebido</span>
    <span style="font-size:1rem;font-weight:700;color:#15803d">R$ <?= number_format($r['receita'], 2, ',', '.') ?></span>
  </div>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 18px;display:flex;gap:10px;align-items:center">
    <span style="font-size:.72rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em">A receber (distribuídos)</span>
    <span style="font-size:1rem;font-weight:700;color:#b45309">R$ <?= number_format($r['a_receber'], 2, ',', '.') ?></span>
  </div>
</div>

<div class="split-layout">

  <!-- Lista de ingressos -->
  <div>
    <div class="tabela-wrap">
      <div class="tabela-header" style="flex-wrap:wrap;gap:10px">
        <h2>Ingressos</h2>
      </div>

      <form method="get" style="padding:14px 20px;background:var(--cinza1);border-top:1px solid var(--cinza2);display:flex;gap:10px;flex-wrap:wrap">
        <input type="hidden" name="id" value="<?= $evento_id ?>">
        <input type="text" name="q" placeholder="Nº do ingresso ou nome do servo..." value="<?= htmlspecialchars($f_q) ?>"
               style="flex:1;min-width:180px;padding:8px 12px;border:1px solid var(--cinza2);border-radius:7px;font-size:.88rem">
        <select name="status" style="padding:8px 12px;border:1px solid var(--cinza2);border-radius:7px;font-size:.88rem;background:#fff">
          <option value="">Todos os status</option>
          <?php foreach ($status_info as $k => $s): ?>
          <option value="<?= $k ?>" <?= $f_status === $k ? 'selected' : '' ?>><?= $s['label'] ?></option>
          <?php endforeach; ?>
        </select>
        <select name="tipo" style="padding:8px 12px;border:1px solid var(--cinza2);border-radius:7px;font-size:.88rem;background:#fff">
          <option value="">Mesa e Individual</option>
          <option value="mesa" <?= $f_tipo === 'mesa' ? 'selected' : '' ?>>Mesa</option>
          <option value="individual" <?= $f_tipo === 'individual' ? 'selected' : '' ?>>Individual</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        <?php if ($f_q || $f_status || $f_tipo): ?><a href="?id=<?= $evento_id ?>" class="btn btn-ghost btn-sm">Limpar</a><?php endif; ?>
      </form>

      <?php if (empty($ingressos)): ?>
      <div style="padding:40px;text-align:center;color:var(--cinza3)">
        Nenhum ingresso encontrado<?= ($f_q || $f_status || $f_tipo) ? ' para esse filtro.' : '. Gere uma faixa de numeração ao lado →' ?>
      </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Tipo</th>
            <th style="text-align:right">Valor</th>
            <th>Prep.</th>
            <th>Servo</th>
            <th>Status</th>
            <th style="text-align:right">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($ingressos as $i): ?>
        <tr>
          <td style="font-weight:700"><?= str_pad((string)$i['numero'], 3, '0', STR_PAD_LEFT) ?></td>
          <td style="font-size:.8rem;color:var(--cinza3)"><?= $i['tipo'] === 'mesa' ? 'Mesa' : 'Individual' ?></td>
          <td style="text-align:right;font-weight:600">R$ <?= number_format($i['valor'], 2, ',', '.') ?></td>
          <td style="font-size:.78rem;color:var(--cinza3)">
            <?= $i['carimbado'] ? '✓ Carimbo' : '— Carimbo' ?><br><?= $i['assinado'] ? '✓ Assinado' : '— Assinado' ?>
          </td>
          <td style="font-size:.84rem">
            <?= $i['servo_nome'] ? htmlspecialchars($i['servo_nome']) : '<span style="color:var(--cinza3)">—</span>' ?>
            <?php if ($i['data_distribuicao']): ?><br><small style="color:var(--cinza3)"><?= date('d/m/Y', strtotime($i['data_distribuicao'])) ?></small><?php endif; ?>
          </td>
          <td>
            <?php $s = $status_info[$i['status']]; ?>
            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:700;color:<?= $s['cor'] ?>;background:<?= $s['bg'] ?>"><?= $s['label'] ?></span>
          </td>
          <td>
            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
              <?php if ($i['status'] === 'distribuido' && $i['servo_nome']): ?>
              <a href="/portal/eventos/ingressos-acerto.php?id=<?= $evento_id ?>&servo=<?= urlencode($i['servo_nome']) ?>" class="btn btn-ouro btn-sm">Acertar</a>
              <form method="post" onsubmit="return confirm('Marcar ingresso #<?= $i['numero'] ?> como devolvido (não vendido)?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="acao" value="devolver">
                <input type="hidden" name="ingresso_id" value="<?= $i['id'] ?>">
                <button class="btn btn-ghost btn-sm">Devolver</button>
              </form>
              <?php endif; ?>
              <?php if ($i['status'] === 'estoque'): ?>
              <form method="post" onsubmit="return confirm('Cancelar (anular) o ingresso #<?= $i['numero'] ?>?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="acao" value="cancelar">
                <input type="hidden" name="ingresso_id" value="<?= $i['id'] ?>">
                <button class="btn btn-danger btn-sm">Cancelar</button>
              </form>
              <?php endif; ?>
              <?php if (in_array($i['status'], ['devolvido', 'cancelado'], true)): ?>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="acao" value="reativar">
                <input type="hidden" name="ingresso_id" value="<?= $i['id'] ?>">
                <button class="btn btn-ghost btn-sm">Reativar</button>
              </form>
              <?php endif; ?>
              <?php if ($i['status'] === 'pago'): ?>
              <a href="/portal/eventos/ingressos-acerto.php?id=<?= $evento_id ?>&servo=<?= urlencode($i['servo_nome']) ?>" class="btn btn-ghost btn-sm">Ver acerto</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Painel de ações -->
  <div>
    <div class="form-wrap" style="max-width:none;margin-bottom:20px">
      <h2>Gerar ingressos</h2>
      <?php if ($erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
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
          <select name="tipo">
            <option value="individual">Individual</option>
            <option value="mesa">Mesa</option>
          </select>
        </div>
        <div class="form-group">
          <label>Valor unitário (R$)</label>
          <input type="text" name="valor" placeholder="0,00">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Gerar faixa</button>
        <span class="form-hint">Repita o formulário para gerar Mesas e Individuais em faixas de numeração separadas.</span>
      </form>
    </div>

    <div class="form-wrap" style="max-width:none;margin-bottom:20px">
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
      fetch('/portal/eventos/ingressos-buscar.php?q=' + encodeURIComponent(q))
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
