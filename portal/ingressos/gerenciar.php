<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$campanha_id = (int)($_GET['id'] ?? 0);
if (!$campanha_id) { header('Location: /portal/ingressos/'); exit; }

$c_stmt = db()->prepare('SELECT * FROM ingressos_campanhas WHERE id = ?');
$c_stmt->execute([$campanha_id]);
$campanha = $c_stmt->fetch();
if (!$campanha) { header('Location: /portal/ingressos/'); exit; }

$titulo       = 'Ingressos — ' . $campanha['nome'];
$pagina_ativa = 'ingressos';
$ing_secao    = 'ingressos';
$ing_campanha = $campanha;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao  = $_POST['acao'] ?? '';
    $tk_id = (int)($_POST['ticket_id'] ?? 0);
    if ($tk_id) {
        if ($acao === 'devolver') {
            db()->prepare("UPDATE ingressos_tickets SET status='devolvido' WHERE id=? AND campanha_id=? AND status='distribuido'")->execute([$tk_id, $campanha_id]);
        } elseif ($acao === 'cancelar') {
            db()->prepare("UPDATE ingressos_tickets SET status='cancelado' WHERE id=? AND campanha_id=? AND status='estoque'")->execute([$tk_id, $campanha_id]);
        } elseif ($acao === 'reativar') {
            db()->prepare("UPDATE ingressos_tickets SET status='estoque', servo_id=NULL, servo_nome=NULL, data_distribuicao=NULL WHERE id=? AND campanha_id=? AND status IN ('devolvido','cancelado')")->execute([$tk_id, $campanha_id]);
        }
    }
    header("Location: /portal/ingressos/gerenciar.php?id=$campanha_id" . (($_GET['status'] ?? '') ? '&status='.$_GET['status'] : ''));
    exit;
}

/* ── Filtros ── */
$f_status = $_GET['status'] ?? '';
$f_tipo   = $_GET['tipo'] ?? '';
$f_q      = trim($_GET['q'] ?? '');

$where  = 'campanha_id = ?';
$params = [$campanha_id];
if ($f_status !== '') { $where .= ' AND status = ?'; $params[] = $f_status; }
if ($f_tipo   !== '') { $where .= ' AND tipo = ?';   $params[] = $f_tipo; }
if ($f_q      !== '') {
    $where .= ' AND (numero = ? OR servo_nome LIKE ?)';
    $params[] = is_numeric($f_q) ? (int)$f_q : -1;
    $params[] = '%' . $f_q . '%';
}

$lista = db()->prepare("SELECT * FROM ingressos_tickets WHERE $where ORDER BY numero ASC");
$lista->execute($params);
$tickets = $lista->fetchAll();

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
    FROM ingressos_tickets WHERE campanha_id = ?
");
$resumo_stmt->execute([$campanha_id]);
$r = $resumo_stmt->fetch();

$status_info = [
    'estoque'     => ['cor' => '#475569', 'bg' => '#f1f5f9', 'label' => 'Em estoque'],
    'distribuido' => ['cor' => '#92400e', 'bg' => '#fef3c7', 'label' => 'Com o servo'],
    'pago'        => ['cor' => '#166534', 'bg' => '#dcfce7', 'label' => 'Pago'],
    'devolvido'   => ['cor' => '#1e3a8a', 'bg' => '#dbeafe', 'label' => 'Devolvido'],
    'cancelado'   => ['cor' => '#991b1b', 'bg' => '#fee2e2', 'label' => 'Cancelado'],
];

include dirname(__DIR__) . '/_layout.php';
?>

<?php include __DIR__ . '/_subnav.php'; ?>

<!-- Resumo -->
<div class="cards" style="margin-bottom:24px">
  <div class="card-stat"><h3>Emitidos</h3><div class="val"><?= (int)$r['total'] ?></div></div>
  <div class="card-stat"><h3>Em estoque</h3><div class="val"><?= (int)$r['estoque'] ?></div></div>
  <div class="card-stat ouro"><h3>Com servos</h3><div class="val"><?= (int)$r['distribuido'] ?></div></div>
  <div class="card-stat verde"><h3>Pagos</h3><div class="val"><?= (int)$r['pago'] ?></div></div>
</div>
<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 18px;display:flex;gap:10px;align-items:center">
    <span style="font-size:.72rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:.06em">Recebido</span>
    <span style="font-size:1rem;font-weight:700;color:#15803d">R$ <?= number_format($r['receita'], 2, ',', '.') ?></span>
  </div>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 18px;display:flex;gap:10px;align-items:center">
    <span style="font-size:.72rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em">A receber (com servos)</span>
    <span style="font-size:1rem;font-weight:700;color:#b45309">R$ <?= number_format($r['a_receber'], 2, ',', '.') ?></span>
  </div>
</div>

<div class="tabela-wrap">
  <div class="tabela-header" style="flex-wrap:wrap;gap:10px">
    <h2>Ingressos</h2>
    <div style="display:flex;gap:8px;margin-left:auto;flex-wrap:wrap">
      <a href="/portal/ingressos/gerar.php?id=<?= $campanha_id ?>" class="btn btn-ghost btn-sm">+ Gerar</a>
      <a href="/portal/ingressos/distribuir.php?id=<?= $campanha_id ?>" class="btn btn-ouro btn-sm">+ Distribuir</a>
    </div>
  </div>

  <form method="get" style="padding:14px 20px;background:var(--cinza1);border-top:1px solid var(--cinza2);display:flex;gap:10px;flex-wrap:wrap">
    <input type="hidden" name="id" value="<?= $campanha_id ?>">
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
    <?php if ($f_q || $f_status || $f_tipo): ?><a href="?id=<?= $campanha_id ?>" class="btn btn-ghost btn-sm">Limpar</a><?php endif; ?>
  </form>

  <?php if (empty($tickets)): ?>
  <div style="padding:40px;text-align:center;color:var(--cinza3)">
    Nenhum ingresso encontrado<?= ($f_q || $f_status || $f_tipo) ? ' para esse filtro.' : '. ' ?>
    <?php if (!$f_q && !$f_status && !$f_tipo): ?><a href="/portal/ingressos/gerar.php?id=<?= $campanha_id ?>">Gere uma faixa de numeração →</a><?php endif; ?>
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
    <?php foreach ($tickets as $i): ?>
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
          <a href="/portal/ingressos/acerto.php?id=<?= $campanha_id ?>&servo=<?= urlencode($i['servo_nome']) ?>" class="btn btn-ouro btn-sm">Acertar</a>
          <form method="post" onsubmit="return confirm('Marcar ingresso #<?= $i['numero'] ?> como devolvido (não vendido)?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="devolver">
            <input type="hidden" name="ticket_id" value="<?= $i['id'] ?>">
            <button class="btn btn-ghost btn-sm">Devolver</button>
          </form>
          <?php endif; ?>
          <?php if ($i['status'] === 'estoque'): ?>
          <form method="post" onsubmit="return confirm('Cancelar (anular) o ingresso #<?= $i['numero'] ?>?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="cancelar">
            <input type="hidden" name="ticket_id" value="<?= $i['id'] ?>">
            <button class="btn btn-danger btn-sm">Cancelar</button>
          </form>
          <?php endif; ?>
          <?php if (in_array($i['status'], ['devolvido', 'cancelado'], true)): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="reativar">
            <input type="hidden" name="ticket_id" value="<?= $i['id'] ?>">
            <button class="btn btn-ghost btn-sm">Reativar</button>
          </form>
          <?php endif; ?>
          <?php if ($i['status'] === 'pago'): ?>
          <a href="/portal/ingressos/acerto.php?id=<?= $campanha_id ?>&servo=<?= urlencode($i['servo_nome']) ?>" class="btn btn-ghost btn-sm">Ver acerto</a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
