<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/inscricoes/'); exit; }

$stmt = db()->prepare("
    SELECT i.*, e.titulo AS evento_titulo, e.id AS evento_id, l.nome AS lote_nome
    FROM inscricoes i
    JOIN eventos e ON i.evento_id = e.id
    LEFT JOIN evento_lotes l ON i.lote_id = l.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$ins = $stmt->fetch();
if (!$ins) { header('Location: /portal/inscricoes/'); exit; }

$titulo       = 'Inscrição #' . str_pad($id, 5, '0', STR_PAD_LEFT);
$pagina_ativa = 'inscricoes';

$salvo = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $novo_status = $_POST['status'] ?? $ins['status'];
    $obs         = trim($_POST['observacoes'] ?? '');

    $checkin_at = $ins['checkin_at'];
    if ($novo_status === 'checkin' && !$ins['checkin_at']) {
        $checkin_at = date('Y-m-d H:i:s');
    } elseif ($novo_status !== 'checkin') {
        $checkin_at = null;
    }

    db()->prepare("UPDATE inscricoes SET status=?, observacoes=?, checkin_at=?, updated_at=NOW() WHERE id=?")
        ->execute([$novo_status, $obs ?: null, $checkin_at, $id]);

    header("Location: /portal/inscricoes/ver.php?id=$id&salvo=1");
    exit;
}

$status_info = [
    'pendente'   => ['cor' => '#b45309', 'bg' => '#fef3c7', 'label' => 'Pendente'],
    'confirmado' => ['cor' => '#166534', 'bg' => '#dcfce7', 'label' => 'Confirmado'],
    'cancelado'  => ['cor' => '#dc2626', 'bg' => '#fee2e2', 'label' => 'Cancelado'],
    'checkin'    => ['cor' => '#1e3a8a', 'bg' => '#dbeafe', 'label' => 'Check-in realizado'],
];

include dirname(__DIR__) . '/_layout.php';
?>

<?php if (isset($_GET['salvo'])): ?>
<div class="alerta alerta-ok" style="margin-bottom:20px">✓ Inscrição atualizada com sucesso.</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start">

  <!-- Dados -->
  <div class="form-wrap" style="max-width:none">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--cinza2)">
      <h2 style="margin:0;padding:0;border:none">Inscrição <?= htmlspecialchars($titulo) ?></h2>
      <a href="/portal/inscricoes/evento.php?id=<?= $ins['evento_id'] ?>" style="font-size:.82rem;color:var(--cinza3)">← Voltar ao evento</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">Evento</div>
        <div style="font-weight:600"><?= htmlspecialchars($ins['evento_titulo']) ?></div>
      </div>
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">Data da inscrição</div>
        <div><?= date('d/m/Y \à\s H:i', strtotime($ins['created_at'])) ?></div>
      </div>
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">Nome</div>
        <div style="font-weight:600"><?= htmlspecialchars($ins['nome']) ?></div>
      </div>
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">E-mail</div>
        <div><?= htmlspecialchars($ins['email']) ?></div>
      </div>
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">Telefone</div>
        <div><?= htmlspecialchars($ins['telefone'] ?? '—') ?></div>
      </div>
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">CPF</div>
        <div><?= htmlspecialchars($ins['cpf'] ?? '—') ?></div>
      </div>
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">Data de nascimento</div>
        <div><?= $ins['data_nascimento'] ? date('d/m/Y', strtotime($ins['data_nascimento'])) : '—' ?></div>
      </div>
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">Lote / Categoria</div>
        <div><?= htmlspecialchars($ins['lote_nome'] ?? '—') ?></div>
      </div>
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">Valor pago</div>
        <div><?= $ins['valor_pago'] > 0 ? 'R$ ' . number_format($ins['valor_pago'], 2, ',', '.') . ' — ' . ucfirst($ins['forma_pagamento']) : 'Gratuito' ?></div>
      </div>
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">Check-in</div>
        <div><?= $ins['checkin_at'] ? date('d/m/Y \à\s H:i', strtotime($ins['checkin_at'])) : '—' ?></div>
      </div>
      <div>
        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:4px">IP de origem</div>
        <div style="color:var(--cinza3);font-size:.85rem"><?= htmlspecialchars($ins['ip'] ?? '—') ?></div>
      </div>
    </div>

    <?php if ($ins['observacoes']): ?>
    <div style="background:var(--cinza1);border-radius:8px;padding:14px;margin-bottom:20px">
      <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600;margin-bottom:6px">Observações do inscrito</div>
      <div style="font-size:.9rem"><?= nl2br(htmlspecialchars($ins['observacoes'])) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Ações -->
  <div>
    <div class="form-wrap" style="max-width:none">
      <h2 style="font-size:.9rem;padding-bottom:12px;border-bottom:1px solid var(--cinza2);margin-bottom:16px">Atualizar status</h2>

      <?php $s = $status_info[$ins['status']] ?? ['cor'=>'#666','bg'=>'#eee','label'=>$ins['status']]; ?>
      <div style="margin-bottom:16px">
        <span style="font-size:.8rem;color:var(--cinza3)">Status atual: </span>
        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 12px;border-radius:20px;font-size:.78rem;font-weight:700;color:<?= $s['cor'] ?>;background:<?= $s['bg'] ?>">
          <?= $s['label'] ?>
        </span>
      </div>

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group">
          <label style="font-size:.82rem;font-weight:600;margin-bottom:6px;display:block">Novo status</label>
          <select name="status">
            <?php foreach ($status_info as $k => $si): ?>
            <option value="<?= $k ?>" <?= $ins['status'] === $k ? 'selected' : '' ?>><?= $si['label'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label style="font-size:.82rem;font-weight:600;margin-bottom:6px;display:block">Notas internas</label>
          <textarea name="observacoes" style="width:100%;padding:9px 12px;border:1px solid var(--cinza2);border-radius:7px;font-size:.88rem;min-height:80px;resize:vertical"><?= htmlspecialchars($ins['observacoes'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%">Salvar alterações</button>
      </form>
    </div>

    <!-- Atalhos rápidos -->
    <div class="form-wrap" style="max-width:none;margin-top:16px">
      <h2 style="font-size:.9rem;padding-bottom:12px;border-bottom:1px solid var(--cinza2);margin-bottom:14px">Ações rápidas</h2>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php if ($ins['status'] === 'confirmado'): ?>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="status" value="checkin"><button type="submit" class="btn btn-primary" style="width:100%">✓ Realizar check-in</button></form>
        <?php endif; ?>
        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $ins['telefone'] ?? '') ?>" target="_blank" class="btn btn-ghost" style="width:100%;text-align:center">💬 Contato via WhatsApp</a>
        <a href="mailto:<?= htmlspecialchars($ins['email']) ?>" class="btn btn-ghost" style="width:100%;text-align:center">✉ Enviar e-mail</a>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
