<?php
require_once __DIR__ . '/auth.php';
requer_login();

$titulo       = 'Dashboard';
$pagina_ativa = 'dashboard';

/* ── Dados reais ── */
try {
    $total_usuarios  = (int)db()->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1")->fetchColumn();
    $total_eventos   = (int)db()->query("SELECT COUNT(*) FROM eventos WHERE ativo = 1")->fetchColumn();
    $total_inscricoes = (int)db()->query("SELECT COUNT(*) FROM inscricoes WHERE status != 'cancelado'")->fetchColumn();
    $inscricoes_hoje = (int)db()->query("SELECT COUNT(*) FROM inscricoes WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $receita_total   = (float)db()->query("SELECT COALESCE(SUM(valor_pago),0) FROM inscricoes WHERE status IN ('confirmado','checkin')")->fetchColumn();
    $pendentes       = (int)db()->query("SELECT COUNT(*) FROM inscricoes WHERE status = 'pendente'")->fetchColumn();

    /* Últimas inscrições */
    $ultimas = db()->query("
        SELECT i.nome, i.email, i.status, i.created_at, e.titulo AS evento
        FROM inscricoes i
        JOIN eventos e ON i.evento_id = e.id
        ORDER BY i.created_at DESC LIMIT 6
    ")->fetchAll();

    /* Eventos com inscrições abertas */
    $eventos_abertos = db()->query("
        SELECT e.id, e.titulo, e.data_evento,
               COUNT(i.id) AS total,
               e.vagas
        FROM eventos e
        LEFT JOIN inscricoes i ON i.evento_id = e.id AND i.status != 'cancelado'
        WHERE e.inscricoes_abertas = 1
        GROUP BY e.id
        ORDER BY e.data_evento ASC LIMIT 4
    ")->fetchAll();
} catch (Exception $e) {
    $total_usuarios = $total_eventos = $total_inscricoes = $inscricoes_hoje = $pendentes = 0;
    $receita_total  = 0;
    $ultimas = $eventos_abertos = [];
}

include __DIR__ . '/_layout.php';
?>

<!-- Cards -->
<div class="cards">
  <div class="card-stat">
    <h3>Eventos ativos</h3>
    <div class="val"><?= $total_eventos ?></div>
    <div class="val-sub">no carrossel do site</div>
  </div>
  <div class="card-stat verde">
    <h3>Inscrições</h3>
    <div class="val"><?= $total_inscricoes ?></div>
    <div class="val-sub"><?= $inscricoes_hoje ?> hoje</div>
  </div>
  <div class="card-stat ouro">
    <h3>Aguardando</h3>
    <div class="val"><?= $pendentes ?></div>
    <div class="val-sub">pendentes de confirmação</div>
  </div>
  <?php if (in_array($_SESSION['usuario_perfil'] ?? '', ['admin','financeiro'])): ?>
  <div class="card-stat">
    <h3>Receita confirmada</h3>
    <div class="val" style="font-size:1.5rem"><?= $receita_total > 0 ? 'R$&nbsp;' . number_format($receita_total, 2, ',', '.') : '—' ?></div>
    <div class="val-sub">inscrições confirmadas + check-in</div>
  </div>
  <?php endif; ?>
  <div class="card-stat">
    <h3>Usuários ativos</h3>
    <div class="val"><?= $total_usuarios ?></div>
    <div class="val-sub">com acesso ao portal</div>
  </div>
</div>

<!-- Grid: Últimas inscrições + Eventos abertos -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:20px;align-items:start">

  <!-- Últimas inscrições -->
  <div class="tabela-wrap">
    <div class="tabela-header">
      <h2>Últimas inscrições</h2>
      <a href="/portal/inscricoes/" class="btn btn-ghost btn-sm">Ver todas</a>
    </div>
    <?php if (empty($ultimas)): ?>
      <div style="padding:36px;text-align:center;color:var(--muted);font-size:.88rem">Nenhuma inscrição ainda.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Participante</th>
          <th>Evento</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $status_style = [
          'confirmado' => 'background:#dcfce7;color:#166534',
          'pendente'   => 'background:#fef9c3;color:#854d0e',
          'checkin'    => 'background:#dbeafe;color:#1e40af',
          'cancelado'  => 'background:#fee2e2;color:#991b1b',
        ];
        $status_label = ['confirmado'=>'Confirmado','pendente'=>'Pendente','checkin'=>'Check-in','cancelado'=>'Cancelado'];
        foreach ($ultimas as $ins): ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($ins['nome']) ?></div>
            <div style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($ins['email']) ?></div>
          </td>
          <td style="font-size:.82rem;color:var(--muted);max-width:160px">
            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block"><?= htmlspecialchars($ins['evento']) ?></span>
            <span style="font-size:.73rem"><?= date('d/m H:i', strtotime($ins['created_at'])) ?></span>
          </td>
          <td>
            <span class="badge" style="<?= $status_style[$ins['status']] ?? '' ?>">
              <?= $status_label[$ins['status']] ?? $ins['status'] ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Eventos com inscrições abertas -->
  <div class="tabela-wrap">
    <div class="tabela-header">
      <h2>Inscrições abertas</h2>
      <a href="/portal/inscricoes/" class="btn btn-ghost btn-sm">Gerenciar</a>
    </div>
    <?php if (empty($eventos_abertos)): ?>
      <div style="padding:36px;text-align:center;color:var(--muted);font-size:.88rem">
        Nenhum evento com inscrições abertas.
      </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Evento</th>
          <th style="text-align:center">Inscritos</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($eventos_abertos as $ev): ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($ev['titulo']) ?></div>
            <div style="font-size:.76rem;color:var(--muted)">
              <?= $ev['data_evento'] ? date('d/m/Y', strtotime($ev['data_evento'])) : 'Sem data' ?>
            </div>
          </td>
          <td style="text-align:center;font-weight:700">
            <?= (int)$ev['total'] ?>
            <?php if ($ev['vagas']): ?>
              <span style="font-size:.76rem;color:var(--muted);font-weight:400"> / <?= $ev['vagas'] ?></span>
            <?php endif; ?>
          </td>
          <td>
            <a href="/portal/inscricoes/evento.php?id=<?= $ev['id'] ?>" class="btn btn-primary btn-sm">Ver</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/_layout_end.php'; ?>
