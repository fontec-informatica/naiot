<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['oracoes']);

$titulo       = 'Orações & Testemunhos';
$pagina_ativa = 'oracoes';

$tabelas_validas = ['oracoes', 'testemunhos'];

$tipo          = ($_GET['tipo'] ?? '') === 'testemunhos' ? 'testemunhos' : 'oracoes';
$tabela        = $tipo;
$status_filtro = in_array($_GET['status'] ?? '', ['aprovado','rejeitado','todos'])
                 ? $_GET['status'] : 'pendente';
$editar_id     = (int)($_GET['editar'] ?? 0);

// ── Ações POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao      = $_POST['acao']  ?? '';
    $item_id   = (int)($_POST['id'] ?? 0);
    $item_tipo = ($_POST['tipo'] ?? '') === 'testemunho' ? 'testemunhos' : 'oracoes';

    if ($item_id && in_array($item_tipo, $tabelas_validas)) {
        switch ($acao) {
            case 'aprovar':
                db()->prepare("UPDATE {$item_tipo} SET status='aprovado' WHERE id=?")->execute([$item_id]);
                break;
            case 'rejeitar':
                db()->prepare("UPDATE {$item_tipo} SET status='rejeitado' WHERE id=?")->execute([$item_id]);
                break;
            case 'excluir':
                db()->prepare("DELETE FROM {$item_tipo} WHERE id=?")->execute([$item_id]);
                break;
            case 'salvar_edicao':
                $novo = mb_substr(trim(strip_tags($_POST['texto'] ?? '')), 0, 2000);
                if ($novo) {
                    db()->prepare("UPDATE {$item_tipo} SET texto=? WHERE id=?")->execute([$novo, $item_id]);
                }
                break;
        }
    }
    $redir_tipo = $item_tipo === 'oracoes' ? 'oracoes' : 'testemunhos';
    header("Location: /portal/oracoes/?tipo={$redir_tipo}&status={$status_filtro}");
    exit;
}

// ── Contagens para badges ──────────────────────────────────────────────────
$cnt = fn(string $tab, string $st) =>
    (int)db()->query("SELECT COUNT(*) FROM {$tab} WHERE status='{$st}'")->fetchColumn();

$pendentes_o = $cnt('oracoes',    'pendente');
$pendentes_t = $cnt('testemunhos','pendente');
$total_o     = (int)db()->query("SELECT COUNT(*) FROM oracoes")->fetchColumn();
$total_t     = (int)db()->query("SELECT COUNT(*) FROM testemunhos")->fetchColumn();

// ── Buscar registros ───────────────────────────────────────────────────────
if ($status_filtro === 'todos') {
    $st_q = db()->prepare("SELECT * FROM {$tabela} ORDER BY criado_em DESC");
    $st_q->execute();
} else {
    $st_q = db()->prepare("SELECT * FROM {$tabela} WHERE status=? ORDER BY criado_em DESC");
    $st_q->execute([$status_filtro]);
}
$itens = $st_q->fetchAll();

// ── Item em edição ─────────────────────────────────────────────────────────
$item_edicao = null;
if ($editar_id) {
    $st_e = db()->prepare("SELECT * FROM {$tabela} WHERE id=?");
    $st_e->execute([$editar_id]);
    $item_edicao = $st_e->fetch() ?: null;
}

include dirname(__DIR__) . '/_layout.php';
?>

<style>
/* ── Tabs tipo/status ── */
.ot-tabs{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap}
.ot-tab{display:inline-flex;align-items:center;gap:7px;padding:7px 16px;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;color:var(--muted);background:var(--off);border:1.5px solid var(--border);transition:.15s}
.ot-tab:hover{color:var(--green);border-color:var(--green)}
.ot-tab.ativo{color:#fff;background:var(--green);border-color:var(--green)}
.ot-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:10px;font-size:.65rem;font-weight:700;background:rgba(255,255,255,.25);color:inherit}
.ot-tab:not(.ativo) .ot-badge{background:var(--red);color:#fff}

.status-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:22px}
.status-tab{padding:5px 14px;border-radius:20px;font-size:.76rem;font-weight:600;text-decoration:none;color:var(--muted);background:var(--off);border:1.5px solid var(--border);transition:.15s}
.status-tab:hover{border-color:var(--green);color:var(--green)}
.status-tab.ativo{background:var(--green);color:#fff;border-color:var(--green)}

/* ── Cards ── */
.ot-lista{display:flex;flex-direction:column;gap:12px}
.ot-card{background:#fff;border:1px solid var(--border);border-radius:var(--rl);box-shadow:var(--sh-sm);overflow:hidden}
.ot-card-head{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border);background:var(--off)}
.ot-card-data{font-size:.72rem;color:var(--muted)}
.ot-status{display:inline-block;padding:2px 10px;border-radius:20px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.ot-status.pendente{background:#fff3cd;color:#856404}
.ot-status.aprovado{background:#d1fae5;color:#065f46}
.ot-status.rejeitado{background:#fee2e2;color:#991b1b}
.ot-card-body{padding:14px 16px}
.ot-texto{font-size:.9rem;color:var(--txt);line-height:1.7;white-space:pre-wrap}
.ot-acoes{display:flex;gap:7px;flex-wrap:wrap;padding:10px 16px;border-top:1px solid var(--border);background:var(--off)}

/* ── Form edição ── */
.ot-edit-form{padding:14px 16px;border-top:1px solid var(--border)}
.ot-edit-form textarea{width:100%;min-height:140px;resize:vertical;padding:10px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:.9rem;line-height:1.6;box-sizing:border-box}
.ot-edit-form textarea:focus{outline:none;border-color:var(--green)}
.ot-edit-btns{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}

/* ── Vazio ── */
.ot-vazio{text-align:center;padding:48px 20px;color:var(--muted);font-size:.9rem}
.ot-vazio svg{width:40px;height:40px;stroke:var(--border);margin-bottom:12px;display:block;margin-left:auto;margin-right:auto}
</style>

<!-- Topo -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px">
  <div>
    <h1 style="font-family:'Cinzel',serif;font-size:1.1rem;font-weight:700;color:var(--green-dk);margin:0">Orações &amp; Testemunhos</h1>
    <p style="font-size:.78rem;color:var(--muted);margin:4px 0 0">Modere os envios do site antes de publicar.</p>
  </div>
  <a href="/portal/oracoes/exportar.php" class="btn btn-ghost btn-sm">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Exportar Excel
  </a>
</div>

<!-- Tabs: Orações / Testemunhos -->
<div class="ot-tabs">
  <a href="/portal/oracoes/?tipo=oracoes&status=<?= $status_filtro ?>"
     class="ot-tab <?= $tipo==='oracoes' ? 'ativo' : '' ?>">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
    Pedidos de Oração
    <?php if ($pendentes_o > 0): ?>
      <span class="ot-badge"><?= $pendentes_o ?></span>
    <?php endif; ?>
  </a>
  <a href="/portal/oracoes/?tipo=testemunhos&status=<?= $status_filtro ?>"
     class="ot-tab <?= $tipo==='testemunhos' ? 'ativo' : '' ?>">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    Testemunhos
    <?php if ($pendentes_t > 0): ?>
      <span class="ot-badge"><?= $pendentes_t ?></span>
    <?php endif; ?>
  </a>
</div>

<!-- Tabs de status -->
<div class="status-tabs">
  <?php
  $status_opts = [
    'pendente'  => 'Pendentes',
    'aprovado'  => 'Aprovados',
    'rejeitado' => 'Rejeitados',
    'todos'     => 'Todos',
  ];
  foreach ($status_opts as $val => $label):
  ?>
  <a href="/portal/oracoes/?tipo=<?= $tipo ?>&status=<?= $val ?>"
     class="status-tab <?= $status_filtro===$val ? 'ativo' : '' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Lista -->
<?php if (empty($itens)): ?>
<div class="ot-vazio">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
  Nenhum registro <?= $status_filtro !== 'todos' ? 'com status "' . htmlspecialchars($status_filtro) . '"' : '' ?>.
</div>
<?php else: ?>
<div class="ot-lista">
  <?php foreach ($itens as $item): ?>
  <?php
    $editando    = ($editar_id === (int)$item['id']);
    $tipo_param  = $tipo === 'oracoes' ? 'oracao' : 'testemunho';
    $data_fmt    = date('d/m/Y \à\s H:i', strtotime($item['criado_em']));
  ?>
  <div class="ot-card">
    <div class="ot-card-head">
      <span class="ot-status <?= htmlspecialchars($item['status']) ?>">
        <?= $item['status'] === 'pendente' ? 'Pendente' : ($item['status'] === 'aprovado' ? 'Aprovado' : 'Rejeitado') ?>
      </span>
      <span class="ot-card-data"><?= $data_fmt ?></span>
      <span style="margin-left:auto;font-size:.7rem;color:var(--muted)">#<?= $item['id'] ?></span>
    </div>

    <?php if ($editando): ?>
    <!-- Formulário de edição -->
    <form method="POST" class="ot-edit-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao"  value="salvar_edicao">
      <input type="hidden" name="id"    value="<?= $item['id'] ?>">
      <input type="hidden" name="tipo"  value="<?= $tipo_param ?>">
      <textarea name="texto"><?= htmlspecialchars($item['texto']) ?></textarea>
      <div class="ot-edit-btns">
        <button type="submit" class="btn btn-primary btn-sm">Salvar alterações</button>
        <a href="/portal/oracoes/?tipo=<?= $tipo ?>&status=<?= $status_filtro ?>" class="btn btn-ghost btn-sm">Cancelar</a>
      </div>
    </form>
    <?php else: ?>
    <div class="ot-card-body">
      <p class="ot-texto"><?= htmlspecialchars($item['texto']) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!$editando): ?>
    <div class="ot-acoes">
      <?php if ($item['status'] !== 'aprovado'): ?>
      <form method="POST" style="display:contents">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao"  value="aprovar">
        <input type="hidden" name="id"    value="<?= $item['id'] ?>">
        <input type="hidden" name="tipo"  value="<?= $tipo_param ?>">
        <button type="submit" class="btn btn-primary btn-sm">✓ Aprovar</button>
      </form>
      <?php endif; ?>

      <?php if ($item['status'] !== 'rejeitado'): ?>
      <form method="POST" style="display:contents">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao"  value="rejeitar">
        <input type="hidden" name="id"    value="<?= $item['id'] ?>">
        <input type="hidden" name="tipo"  value="<?= $tipo_param ?>">
        <button type="submit" class="btn btn-ghost btn-sm">✕ Rejeitar</button>
      </form>
      <?php endif; ?>

      <a href="/portal/oracoes/?tipo=<?= $tipo ?>&status=<?= $status_filtro ?>&editar=<?= $item['id'] ?>"
         class="btn btn-ghost btn-sm">✎ Editar</a>

      <form method="POST" style="display:contents"
            onsubmit="return confirm('Excluir permanentemente este registro?')">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao"  value="excluir">
        <input type="hidden" name="id"    value="<?= $item['id'] ?>">
        <input type="hidden" name="tipo"  value="<?= $tipo_param ?>">
        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red);border-color:var(--red)">Excluir</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
