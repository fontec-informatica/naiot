<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$titulo       = 'Casa das Mulheres';
$pagina_ativa = 'camjc';

$status_filtro = $_GET['status'] ?? '';
$busca         = trim($_GET['q'] ?? '');

$where  = "WHERE 1=1";
$params = [];
if ($status_filtro && isset(CAMJC_STATUS[$status_filtro])) {
    $where .= " AND a.status = ?";
    $params[] = $status_filtro;
}
if ($busca) {
    $where .= " AND (a.nome LIKE ? OR a.cpf LIKE ?)";
    $params[] = "%$busca%"; $params[] = "%$busca%";
}

$st = db()->prepare("
    SELECT a.*, u.nome AS unidade_nome,
           (SELECT COUNT(*) FROM camjc_triagens t WHERE t.acolhida_id = a.id) AS total_triagens
    FROM camjc_acolhidas a
    JOIN unidades u ON u.id = a.unidade_id
    $where
    ORDER BY a.criado_em DESC
");
$st->execute($params);
$acolhidas = $st->fetchAll();

// Contagem por status (para os filtros da barra lateral)
$contagens = db()->query("SELECT status, COUNT(*) AS total FROM camjc_acolhidas GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$total_geral = array_sum($contagens);

include dirname(__DIR__) . '/_layout.php';
?>
<style>
.cj-layout{display:grid;grid-template-columns:220px 1fr;gap:0;min-height:calc(100vh - var(--topbar-h) - 48px)}
.cj-sidebar{background:#fff;border:1px solid var(--border);border-radius:var(--rl);overflow:hidden;align-self:start;position:sticky;top:calc(var(--topbar-h)+24px)}
.cj-sidebar-head{padding:11px 16px 9px;border-bottom:1px solid var(--border);background:var(--off)}
.cj-sidebar-head span{font-family:'Cinzel',serif;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--muted)}
.cj-status-list{list-style:none;padding:4px 0}
.cj-status-list li a{display:flex;align-items:center;gap:10px;padding:8px 14px;font-size:.83rem;color:var(--txt);border-left:3px solid transparent;transition:background var(--ease),color var(--ease)}
.cj-status-list li a:hover{background:var(--green-pale);color:var(--green-dk)}
.cj-status-list li a.sel{background:var(--green-pale);color:var(--green-dk);border-left-color:var(--green-dk);font-weight:600}
.cj-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.cj-count{margin-left:auto;font-size:.7rem;color:var(--muted);background:var(--bg);padding:1px 7px;border-radius:20px;flex-shrink:0}
.cj-status-list li a.sel .cj-count{background:rgba(30,107,53,.12);color:var(--green-dk)}

.cj-main{padding-left:20px;min-width:0}
.cj-header{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.cj-titulo{font-family:'Cinzel',serif;font-size:.9rem;font-weight:700;color:var(--green-dk);letter-spacing:.05em;text-transform:uppercase}
.cj-sub{font-size:.78rem;color:var(--muted);margin-top:2px}
.cj-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.cj-search{padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;background:var(--off);color:var(--text);outline:none;width:200px;font-family:inherit}
.cj-search:focus{border-color:var(--green);background:#fff;box-shadow:0 0 0 3px rgba(30,107,53,.1)}

.cj-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:16px}
.cj-card{background:#fff;border:1px solid var(--border);border-radius:var(--rl);overflow:hidden;transition:transform var(--ease),box-shadow var(--ease);display:flex;flex-direction:column}
.cj-card:hover{transform:translateY(-3px);box-shadow:var(--sh)}
.cj-card-body{padding:16px 16px 12px}
.cj-card-nome{font-size:.92rem;font-weight:700;color:var(--green-dk);line-height:1.3;margin-bottom:6px}
.cj-badge{display:inline-flex;align-items:center;gap:5px;font-size:.66rem;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px}
.cj-info-row{display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--muted);margin-top:3px}
.cj-card-footer{margin-top:auto;padding:10px 14px;border-top:1px solid var(--border);display:flex;gap:6px;justify-content:flex-end;background:var(--off)}

.cj-empty{grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--muted)}
.cj-empty svg{width:48px;height:48px;stroke:var(--border);margin:0 auto 16px}

@media(max-width:860px){
  .cj-layout{grid-template-columns:1fr;gap:16px}
  .cj-sidebar{position:static}
  .cj-status-list{display:flex;overflow-x:auto;padding:6px;gap:6px;-webkit-overflow-scrolling:touch}
  .cj-status-list li a{white-space:nowrap;border-left:none;border-radius:20px;padding:5px 13px;background:var(--off);border:1px solid var(--border)}
  .cj-status-list li a.sel{border-color:var(--green-dk);background:var(--green-pale)}
  .cj-main{padding-left:0}
}
@media(max-width:520px){
  .cj-grid{grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:12px}
  .cj-actions{width:100%;justify-content:space-between}
  .cj-search{width:160px}
}
</style>

<div class="cj-header">
  <div>
    <div class="cj-titulo"><?= $status_filtro ? htmlspecialchars(camjc_status_label($status_filtro)) : 'Todas as acolhidas' ?></div>
    <div class="cj-sub"><?= count($acolhidas) ?> registro<?= count($acolhidas) === 1 ? '' : 's' ?> encontrado<?= count($acolhidas) === 1 ? '' : 's' ?></div>
  </div>
  <div class="cj-actions">
    <form method="get" style="display:contents">
      <?php if ($status_filtro): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filtro) ?>"><?php endif; ?>
      <input type="text" name="q" class="cj-search" placeholder="Buscar por nome ou CPF…" value="<?= htmlspecialchars($busca) ?>">
    </form>
    <a href="/portal/camjc/nova.php" class="btn btn-primary btn-sm">+ Nova candidata</a>
  </div>
</div>

<div class="cj-layout">
  <div class="cj-sidebar">
    <div class="cj-sidebar-head"><span>Status</span></div>
    <ul class="cj-status-list">
      <li>
        <a href="/portal/camjc/" class="<?= !$status_filtro ? 'sel' : '' ?>">
          <span class="cj-dot" style="background:var(--green)"></span>
          Todas
          <span class="cj-count"><?= $total_geral ?></span>
        </a>
      </li>
      <?php foreach (CAMJC_STATUS as $chave => $label): ?>
      <li>
        <a href="/portal/camjc/?status=<?= $chave ?>" class="<?= $status_filtro === $chave ? 'sel' : '' ?>">
          <span class="cj-dot" style="background:<?= camjc_status_cor($chave) ?>"></span>
          <?= htmlspecialchars($label) ?>
          <span class="cj-count"><?= $contagens[$chave] ?? 0 ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="cj-main">
    <div class="cj-grid">
      <?php if (empty($acolhidas)): ?>
      <div class="cj-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
        </svg>
        <p>
          <?php if ($busca): ?>
            Nenhum registro encontrado para "<strong><?= htmlspecialchars($busca) ?></strong>".
          <?php else: ?>
            Nenhuma candidata cadastrada ainda.<br>
            <a href="/portal/camjc/nova.php" style="color:var(--green)">Cadastrar primeira candidata →</a>
          <?php endif; ?>
        </p>
      </div>
      <?php else: foreach ($acolhidas as $a):
        $idade = '';
        if ($a['data_nasc']) {
            $nasc = new DateTime($a['data_nasc']); $hoje = new DateTime();
            $idade = $nasc->diff($hoje)->y . ' anos';
        }
      ?>
      <div class="cj-card">
        <?php if (!empty($a['foto'])): ?>
        <div style="width:100%;aspect-ratio:16/9;background:var(--green-pale);overflow:hidden">
          <img src="/portal/camjc/foto.php?id=<?= $a['id'] ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block">
        </div>
        <?php endif; ?>
        <div class="cj-card-body">
          <div class="cj-card-nome"><?= htmlspecialchars($a['nome']) ?></div>
          <span class="cj-badge" style="color:<?= camjc_status_cor($a['status']) ?>;background:<?= camjc_status_cor($a['status']) ?>18">
            <?= htmlspecialchars(camjc_status_label($a['status'])) ?>
          </span>
          <?php if ($idade): ?>
          <div class="cj-info-row">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <?= $idade ?>
          </div>
          <?php endif; ?>
          <?php if ($a['data_acolhimento']): ?>
          <div class="cj-info-row">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            Acolhida em <?= date('d/m/Y', strtotime($a['data_acolhimento'])) ?>
          </div>
          <?php endif; ?>
          <?php if ($a['status'] === 'acolhida' && $a['data_acolhimento']): ?>
          <div class="cj-info-row">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            Previsão: <?= date('d/m/Y', strtotime(camjc_previsao_saida($a['data_acolhimento']))) ?>
          </div>
          <?php endif; ?>
          <?php if ($a['data_saida']): ?>
          <div class="cj-info-row">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
            Saída em <?= date('d/m/Y', strtotime($a['data_saida'])) ?>
          </div>
          <?php endif; ?>
          <div class="cj-info-row">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
            <?= (int)$a['total_triagens'] ?> triagem<?= (int)$a['total_triagens'] === 1 ? '' : 'ns' ?>
          </div>
        </div>
        <div class="cj-card-footer">
          <a href="/portal/camjc/ver.php?id=<?= $a['id'] ?>" class="btn btn-ghost btn-sm">Ver</a>
          <a href="/portal/camjc/editar.php?id=<?= $a['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
