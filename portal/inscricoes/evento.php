<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$evento_id = (int)($_GET['id'] ?? 0);
if (!$evento_id) { header('Location: /portal/inscricoes/'); exit; }

$ev_stmt = db()->prepare('SELECT * FROM eventos WHERE id = ?');
$ev_stmt->execute([$evento_id]);
$evento = $ev_stmt->fetch();
if (!$evento) { header('Location: /portal/inscricoes/'); exit; }

$titulo       = 'Inscrições — ' . $evento['titulo'];
$pagina_ativa = 'inscricoes';

/* ── Ações POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao   = $_POST['acao']   ?? '';
    $ins_id = (int)($_POST['ins_id'] ?? 0);

    if ($ins_id) {
        match ($acao) {
            'confirmar'       => db()->prepare("UPDATE inscricoes SET status='confirmado', updated_at=NOW() WHERE id=? AND evento_id=?")->execute([$ins_id, $evento_id]),
            'cancelar'        => db()->prepare("UPDATE inscricoes SET status='cancelado',  updated_at=NOW() WHERE id=? AND evento_id=?")->execute([$ins_id, $evento_id]),
            'checkin'         => db()->prepare("UPDATE inscricoes SET status='checkin', checkin_at=NOW(), updated_at=NOW() WHERE id=? AND evento_id=?")->execute([$ins_id, $evento_id]),
            'desfazer_checkin'=> db()->prepare("UPDATE inscricoes SET status='confirmado', checkin_at=NULL, updated_at=NOW() WHERE id=? AND evento_id=?")->execute([$ins_id, $evento_id]),
            default           => null,
        };
    }
    if ($acao === 'confirmar_pendentes') {
        db()->prepare("UPDATE inscricoes SET status='confirmado', updated_at=NOW() WHERE evento_id=? AND status='pendente'")->execute([$evento_id]);
    }

    $qs = http_build_query(array_filter(['id' => $evento_id, 'status' => $_GET['status'] ?? '', 'q' => $_GET['q'] ?? '']));
    header("Location: /portal/inscricoes/evento.php?$qs");
    exit;
}

/* ── Exportar CSV ── */
if (isset($_GET['exportar'])) {
    $rows = db()->prepare("
        SELECT i.*, l.nome AS lote_nome
        FROM inscricoes i LEFT JOIN evento_lotes l ON i.lote_id = l.id
        WHERE i.evento_id = ? ORDER BY i.created_at ASC
    ");
    $rows->execute([$evento_id]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inscricoes_evento' . $evento_id . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['#','Nome','E-mail','Telefone','CPF','Nascimento','Lote','Valor','Pagamento','Status','Observações','Check-in','Data Inscrição'], ';');
    foreach ($rows->fetchAll() as $r) {
        fputcsv($out, [
            $r['id'], $r['nome'], $r['email'], $r['telefone'] ?? '', $r['cpf'] ?? '',
            $r['data_nascimento'] ? date('d/m/Y', strtotime($r['data_nascimento'])) : '',
            $r['lote_nome'] ?? '',
            number_format($r['valor_pago'], 2, ',', '.'),
            $r['forma_pagamento'],
            $r['status'],
            $r['observacoes'] ?? '',
            $r['checkin_at'] ? date('d/m/Y H:i', strtotime($r['checkin_at'])) : '',
            date('d/m/Y H:i', strtotime($r['created_at'])),
        ], ';');
    }
    fclose($out);
    exit;
}

/* ── Filtros ── */
$status_f = $_GET['status'] ?? '';
$busca    = trim($_GET['q'] ?? '');

$where  = ['i.evento_id = :eid'];
$params = [':eid' => $evento_id];
if ($status_f) { $where[] = 'i.status = :st';  $params[':st'] = $status_f; }
if ($busca)    { $where[] = '(i.nome LIKE :q OR i.email LIKE :q OR i.cpf LIKE :q OR i.telefone LIKE :q)'; $params[':q'] = "%$busca%"; }

$sql = "SELECT i.*, l.nome AS lote_nome
        FROM inscricoes i LEFT JOIN evento_lotes l ON i.lote_id = l.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY i.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$inscricoes = $stmt->fetchAll();

/* ── Stats ── */
$stats = db()->prepare("SELECT
    COUNT(*) AS total,
    SUM(status='pendente')   AS pendentes,
    SUM(status='confirmado') AS confirmados,
    SUM(status='cancelado')  AS cancelados,
    SUM(status='checkin')    AS checkins,
    SUM(CASE WHEN status != 'cancelado' THEN valor_pago ELSE 0 END) AS receita
  FROM inscricoes WHERE evento_id = ?");
$stats->execute([$evento_id]);
$st = $stats->fetch();

$status_info = [
    'pendente'   => ['cor' => '#b45309', 'bg' => '#fef3c7', 'label' => 'Pendente'],
    'confirmado' => ['cor' => '#166534', 'bg' => '#dcfce7', 'label' => 'Confirmado'],
    'cancelado'  => ['cor' => '#dc2626', 'bg' => '#fee2e2', 'label' => 'Cancelado'],
    'checkin'    => ['cor' => '#1e3a8a', 'bg' => '#dbeafe', 'label' => 'Check-in'],
];

include dirname(__DIR__) . '/_layout.php';
?>

<!-- Stats -->
<div class="cards" style="margin-bottom:24px">
  <div class="card-stat"><h3>Total</h3><div class="val"><?= (int)$st['total'] ?><?= $evento['vagas'] ? '<span style="font-size:1rem;color:var(--cinza3)">/'.$evento['vagas'].'</span>' : '' ?></div></div>
  <div class="card-stat verde"><h3>Confirmados</h3><div class="val"><?= (int)$st['confirmados'] ?></div></div>
  <div class="card-stat ouro"><h3>Pendentes</h3><div class="val"><?= (int)$st['pendentes'] ?></div></div>
  <div class="card-stat"><h3>Check-ins</h3><div class="val"><?= (int)$st['checkins'] ?></div></div>
</div>
<?php if ($st['receita'] > 0): ?>
<div style="background:#fff;border-radius:10px;padding:14px 20px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:10px">
  <span style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;color:var(--cinza3);font-weight:600">Receita prevista</span>
  <span style="font-size:1.4rem;font-weight:700;color:var(--txt)">R$ <?= number_format($st['receita'], 2, ',', '.') ?></span>
  <span style="font-size:.75rem;color:var(--cinza3)">(inscrições não canceladas)</span>
</div>
<?php endif; ?>

<div class="tabela-wrap">
  <div class="tabela-header" style="flex-wrap:wrap;gap:10px">
    <div>
      <h2 style="margin-bottom:4px"><?= htmlspecialchars($evento['titulo']) ?></h2>
      <a href="/portal/inscricoes/" style="font-size:.82rem;color:var(--cinza3)">← Voltar</a>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?php if ($st['pendentes'] > 0): ?>
      <form method="post" onsubmit="return confirm('Confirmar todos os <?= $st['pendentes'] ?> pendentes?')">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao" value="confirmar_pendentes">
        <button class="btn btn-ouro btn-sm">✓ Confirmar todos (<?= (int)$st['pendentes'] ?>)</button>
      </form>
      <?php endif; ?>
      <a href="?id=<?= $evento_id ?>&exportar=1<?= $status_f ? '&status='.$status_f : '' ?>" class="btn btn-ghost btn-sm">⬇ Exportar CSV</a>
      <?php if ($evento['inscricoes_abertas']): ?>
      <a href="/evento.php?id=<?= $evento_id ?>" target="_blank" class="btn btn-primary btn-sm">🔗 Ver página pública</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filtros -->
  <form method="get" style="padding:14px 20px;background:var(--cinza1);border-top:1px solid var(--cinza2);display:flex;gap:10px;flex-wrap:wrap">
    <input type="hidden" name="id" value="<?= $evento_id ?>">
    <input type="text" name="q" placeholder="Buscar nome, e-mail, CPF..." value="<?= htmlspecialchars($busca) ?>"
           style="flex:1;min-width:200px;padding:8px 12px;border:1px solid var(--cinza2);border-radius:7px;font-size:.88rem">
    <select name="status" style="padding:8px 12px;border:1px solid var(--cinza2);border-radius:7px;font-size:.88rem;background:#fff">
      <option value="">Todos os status</option>
      <?php foreach ($status_info as $k => $s): ?>
      <option value="<?= $k ?>" <?= $status_f === $k ? 'selected' : '' ?>><?= $s['label'] ?> (<?= (int)$st[$k.'s'] ?>)</option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    <?php if ($busca || $status_f): ?><a href="?id=<?= $evento_id ?>" class="btn btn-ghost btn-sm">Limpar</a><?php endif; ?>
  </form>

  <?php if (empty($inscricoes)): ?>
  <div style="padding:40px;text-align:center;color:var(--cinza3)">Nenhuma inscrição encontrada.</div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Nome</th>
        <th>E-mail / Telefone</th>
        <th>Lote / Valor</th>
        <th>Status</th>
        <th>Data</th>
        <th style="text-align:right">Ações</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($inscricoes as $i): ?>
    <tr>
      <td style="color:var(--cinza3);font-size:.82rem"><?= str_pad($i['id'], 5, '0', STR_PAD_LEFT) ?></td>
      <td>
        <strong><?= htmlspecialchars($i['nome']) ?></strong>
        <?php if ($i['cpf']): ?><br><small style="color:var(--cinza3)">CPF: <?= htmlspecialchars($i['cpf']) ?></small><?php endif; ?>
      </td>
      <td>
        <?= htmlspecialchars($i['email']) ?>
        <?php if ($i['telefone']): ?><br><small style="color:var(--cinza3)"><?= htmlspecialchars($i['telefone']) ?></small><?php endif; ?>
      </td>
      <td>
        <?= $i['lote_nome'] ? htmlspecialchars($i['lote_nome']) : '—' ?>
        <?php if ($i['valor_pago'] > 0): ?>
        <br><small style="color:var(--cinza3)">R$ <?= number_format($i['valor_pago'], 2, ',', '.') ?> / <?= ucfirst($i['forma_pagamento']) ?></small>
        <?php else: ?>
        <br><small style="color:var(--verde)">Gratuito</small>
        <?php endif; ?>
      </td>
      <td>
        <?php $s = $status_info[$i['status']] ?? ['cor'=>'#666','bg'=>'#eee','label'=>$i['status']]; ?>
        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:700;color:<?= $s['cor'] ?>;background:<?= $s['bg'] ?>">
          <?= $s['label'] ?>
        </span>
        <?php if ($i['checkin_at']): ?>
        <br><small style="color:var(--cinza3)"><?= date('d/m H:i', strtotime($i['checkin_at'])) ?></small>
        <?php endif; ?>
      </td>
      <td style="font-size:.8rem;color:var(--cinza3)"><?= date('d/m/Y H:i', strtotime($i['created_at'])) ?></td>
      <td>
        <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
          <a href="/portal/inscricoes/ver.php?id=<?= $i['id'] ?>" class="btn btn-ghost btn-sm">Ver</a>

          <?php if ($i['status'] === 'pendente'): ?>
          <form method="post"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="ins_id" value="<?= $i['id'] ?>"><button name="acao" value="confirmar" class="btn btn-sm" style="background:#dcfce7;color:#166534;border:1px solid #86efac">✓ Confirmar</button></form>
          <?php endif; ?>

          <?php if ($i['status'] === 'confirmado'): ?>
          <form method="post"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="ins_id" value="<?= $i['id'] ?>"><button name="acao" value="checkin" class="btn btn-sm" style="background:#dbeafe;color:#1e3a8a;border:1px solid #93c5fd">Check-in</button></form>
          <?php endif; ?>

          <?php if ($i['status'] === 'checkin'): ?>
          <form method="post"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="ins_id" value="<?= $i['id'] ?>"><button name="acao" value="desfazer_checkin" class="btn btn-ghost btn-sm">↩ Desfazer</button></form>
          <?php endif; ?>

          <?php if (!in_array($i['status'], ['cancelado'], true)): ?>
          <form method="post" onsubmit="return confirm('Cancelar esta inscrição?')"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="ins_id" value="<?= $i['id'] ?>"><button name="acao" value="cancelar" class="btn btn-danger btn-sm">Cancelar</button></form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
