<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$campanha_id = (int)($_GET['id'] ?? 0);
$servo       = trim($_GET['servo'] ?? '');
if (!$campanha_id || $servo === '') { header('Location: /portal/ingressos/'); exit; }

$c_stmt = db()->prepare('SELECT * FROM ingressos_campanhas WHERE id = ?');
$c_stmt->execute([$campanha_id]);
$campanha = $c_stmt->fetch();
if (!$campanha) { header('Location: /portal/ingressos/'); exit; }

$titulo       = 'Acerto — ' . $servo;
$pagina_ativa = 'ingressos';
$ing_secao    = 'ingressos';
$ing_campanha = $campanha;
$erro         = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';
    $ids  = array_map('intval', $_POST['ids'] ?? []);
    $ids  = array_filter($ids);

    if (empty($ids)) {
        $erro = 'Selecione ao menos um ingresso.';
    } else {
        $ph = implode(',', array_fill(0, count($ids), '?'));

        if ($acao === 'liquidar') {
            $forma = in_array($_POST['forma_pagamento'] ?? '', ['pix', 'transferencia', 'deposito', 'dinheiro', 'outro'], true) ? $_POST['forma_pagamento'] : 'pix';
            $data_pag = $_POST['data_pagamento'] ?: date('Y-m-d');
            $obs = trim($_POST['observacao'] ?? '');

            $comprovante_nome = null;
            if (!empty($_FILES['comprovante']['tmp_name']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                $permitidos = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($_FILES['comprovante']['tmp_name']);
                if (isset($permitidos[$mime]) && $_FILES['comprovante']['size'] <= 10 * 1024 * 1024) {
                    $comprovante_nome = 'ing_' . uniqid() . '.' . $permitidos[$mime];
                    move_uploaded_file($_FILES['comprovante']['tmp_name'], __DIR__ . '/uploads/' . $comprovante_nome);
                }
            }

            $sql = "UPDATE ingressos_tickets SET status='pago', forma_pagamento=?, valor_pago=valor, data_pagamento=?, observacao=?"
                 . ($comprovante_nome ? ", comprovante=?" : "")
                 . " WHERE id IN ($ph) AND campanha_id=? AND servo_nome=? AND status='distribuido'";
            $params = [$forma, $data_pag, $obs ?: null];
            if ($comprovante_nome) $params[] = $comprovante_nome;
            $params = array_merge($params, $ids, [$campanha_id, $servo]);
            db()->prepare($sql)->execute($params);

        } elseif ($acao === 'devolver_lote') {
            db()->prepare("UPDATE ingressos_tickets SET status='devolvido' WHERE id IN ($ph) AND campanha_id=? AND servo_nome=? AND status='distribuido'")
                ->execute(array_merge($ids, [$campanha_id, $servo]));
        }

        header("Location: /portal/ingressos/acerto.php?id=$campanha_id&servo=" . urlencode($servo) . "&ok=1");
        exit;
    }
}

$lista = db()->prepare("SELECT * FROM ingressos_tickets WHERE campanha_id = ? AND servo_nome = ? ORDER BY numero ASC");
$lista->execute([$campanha_id, $servo]);
$tickets = $lista->fetchAll();

$total_pago = 0; $total_pendente = 0;
foreach ($tickets as $i) {
    if ($i['status'] === 'pago') $total_pago += (float)$i['valor_pago'];
    elseif ($i['status'] === 'distribuido') $total_pendente += (float)$i['valor'];
}

$status_info = [
    'distribuido' => ['cor' => '#92400e', 'bg' => '#fef3c7', 'label' => 'Aguardando'],
    'pago'        => ['cor' => '#166534', 'bg' => '#dcfce7', 'label' => 'Pago'],
    'devolvido'   => ['cor' => '#1e3a8a', 'bg' => '#dbeafe', 'label' => 'Devolvido'],
];

include dirname(__DIR__) . '/_layout.php';
?>

<?php include __DIR__ . '/_subnav.php'; ?>

<div style="margin-bottom:16px">
  <h2 style="font-size:1rem;font-weight:600">Acerto com <?= htmlspecialchars($servo) ?></h2>
  <a href="/portal/ingressos/gerenciar.php?id=<?= $campanha_id ?>" style="font-size:.82rem;color:var(--cinza3)">← Voltar aos ingressos de <?= htmlspecialchars($campanha['nome']) ?></a>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alerta alerta-ok" style="margin-bottom:16px">✓ Acerto registrado com sucesso.</div><?php endif; ?>
<?php if ($erro): ?><div class="alerta alerta-erro" style="margin-bottom:16px"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 18px">
    <span style="font-size:.72rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:.06em">Pago</span><br>
    <span style="font-size:1rem;font-weight:700;color:#15803d">R$ <?= number_format($total_pago, 2, ',', '.') ?></span>
  </div>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 18px">
    <span style="font-size:.72rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em">A pagar</span><br>
    <span style="font-size:1rem;font-weight:700;color:#b45309">R$ <?= number_format($total_pendente, 2, ',', '.') ?></span>
  </div>
</div>

<form method="post" enctype="multipart/form-data" id="formAcerto">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

<div class="tabela-wrap" style="margin-bottom:20px">
  <div class="tabela-header"><h2>Ingressos com <?= htmlspecialchars($servo) ?></h2></div>
  <?php if (empty($tickets)): ?>
    <div style="padding:40px;text-align:center;color:var(--cinza3)">Nenhum ingresso encontrado para esse servo.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:36px"></th>
        <th>#</th>
        <th>Tipo</th>
        <th style="text-align:right">Valor</th>
        <th>Status</th>
        <th>Data pagamento / comprovante</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($tickets as $i): ?>
    <tr>
      <td>
        <?php if ($i['status'] === 'distribuido'): ?>
        <input type="checkbox" name="ids[]" value="<?= $i['id'] ?>" class="chk-ing">
        <?php endif; ?>
      </td>
      <td style="font-weight:700"><?= str_pad((string)$i['numero'], 3, '0', STR_PAD_LEFT) ?></td>
      <td style="font-size:.8rem;color:var(--cinza3)"><?= $i['tipo'] === 'mesa' ? 'Mesa' : 'Individual' ?></td>
      <td style="text-align:right;font-weight:600">R$ <?= number_format($i['valor'], 2, ',', '.') ?></td>
      <td>
        <?php $s = $status_info[$i['status']] ?? null; ?>
        <?php if ($s): ?>
        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:700;color:<?= $s['cor'] ?>;background:<?= $s['bg'] ?>"><?= $s['label'] ?></span>
        <?php endif; ?>
      </td>
      <td style="font-size:.8rem;color:var(--cinza3)">
        <?php if ($i['status'] === 'pago'): ?>
          <?= ucfirst($i['forma_pagamento']) ?> em <?= $i['data_pagamento'] ? date('d/m/Y', strtotime($i['data_pagamento'])) : '—' ?>
          <?php if ($i['comprovante']): ?><br><a href="/portal/ingressos/uploads/<?= htmlspecialchars($i['comprovante']) ?>" target="_blank">Ver comprovante</a><?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if ($total_pendente > 0): ?>
<div class="form-wrap" style="max-width:none">
  <h2>Registrar acerto dos ingressos selecionados</h2>
  <div class="form-row">
    <div class="form-group">
      <label>Forma de pagamento</label>
      <select name="forma_pagamento">
        <option value="pix">PIX</option>
        <option value="transferencia">Transferência</option>
        <option value="deposito">Depósito</option>
        <option value="dinheiro">Dinheiro</option>
        <option value="outro">Outro</option>
      </select>
    </div>
    <div class="form-group">
      <label>Data do pagamento</label>
      <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>">
    </div>
  </div>
  <div class="form-group">
    <label>Comprovante <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
    <input type="file" name="comprovante" accept="image/jpeg,image/png,image/webp,application/pdf">
    <span class="form-hint">JPG, PNG, WebP ou PDF — máximo 10 MB.</span>
  </div>
  <div class="form-group">
    <label>Observação <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
    <input type="text" name="observacao" placeholder="Ex: pagou junto com outro servo...">
  </div>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <button type="submit" name="acao" value="liquidar" class="btn btn-primary">✓ Marcar selecionados como pagos</button>
    <button type="submit" name="acao" value="devolver_lote" class="btn btn-ghost" onclick="return confirm('Marcar os ingressos selecionados como devolvidos (não vendidos)?')">Devolver selecionados</button>
  </div>
</div>
<?php endif; ?>
</form>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
