<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Membros';
$pagina_ativa = 'membros';

$grupo_id = (int)($_GET['grupo'] ?? 0);
$busca    = trim($_GET['q'] ?? '');

// Ação: remover membro do grupo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'remover_grupo') {
        $gid = (int)$_POST['grupo_id'];
        $mid = (int)$_POST['membro_id'];
        db()->prepare("DELETE FROM membros_grupo_rel WHERE grupo_id=? AND membro_id=?")->execute([$gid, $mid]);
        header("Location: /portal/membros/?grupo={$gid}");
        exit;
    }
    if ($acao === 'adicionar_grupo') {
        $gid = (int)$_POST['grupo_id'];
        $mid = (int)$_POST['membro_id'];
        db()->prepare("INSERT IGNORE INTO membros_grupo_rel (grupo_id,membro_id) VALUES (?,?)")->execute([$gid, $mid]);
        header("Location: /portal/membros/?grupo={$gid}");
        exit;
    }
    if ($acao === 'toggle_ativo') {
        $mid = (int)$_POST['membro_id'];
        db()->prepare("UPDATE membros SET ativo = NOT ativo WHERE id=?")->execute([$mid]);
        header("Location: /portal/membros/" . ($grupo_id ? "?grupo={$grupo_id}" : ''));
        exit;
    }
}

$grupos = db()->query("SELECT g.*, COUNT(r.membro_id) as total FROM membros_grupos g LEFT JOIN membros_grupo_rel r ON r.grupo_id=g.id GROUP BY g.id ORDER BY g.nome")->fetchAll();

$grupo_atual = null;
if ($grupo_id) {
    foreach ($grupos as $g) { if ($g['id'] == $grupo_id) { $grupo_atual = $g; break; } }
}

// Buscar membros
$where  = "WHERE m.ativo = 1";
$params = [];

if ($grupo_id) {
    $where .= " AND EXISTS (SELECT 1 FROM membros_grupo_rel r WHERE r.grupo_id=? AND r.membro_id=m.id)";
    $params[] = $grupo_id;
}
if ($busca) {
    $where .= " AND (m.nome LIKE ? OR m.cidade LIKE ? OR m.telefone LIKE ?)";
    $params[] = "%$busca%"; $params[] = "%$busca%"; $params[] = "%$busca%";
}

$st = db()->prepare("SELECT m.* FROM membros m $where ORDER BY m.nome");
$st->execute($params);
$membros = $st->fetchAll();

$total_geral = (int)db()->query("SELECT COUNT(*) FROM membros WHERE ativo=1")->fetchColumn();

// Para o modal de adicionar ao grupo: membros que ainda NÃO estão no grupo
$nao_no_grupo = [];
if ($grupo_id) {
    $st2 = db()->prepare("SELECT id,nome FROM membros WHERE ativo=1 AND id NOT IN (SELECT membro_id FROM membros_grupo_rel WHERE grupo_id=?) ORDER BY nome");
    $st2->execute([$grupo_id]);
    $nao_no_grupo = $st2->fetchAll();
}

include dirname(__DIR__) . '/_layout.php';
?>

<style>
/* ── layout membros ── */
.mb-layout{display:grid;grid-template-columns:230px 1fr;gap:0;min-height:calc(100vh - var(--topbar-h) - 48px)}
.mb-sidebar{
  background:#fff;border:1px solid var(--border);border-radius:var(--rl);
  overflow:hidden;align-self:start;position:sticky;top:calc(var(--topbar-h)+24px);
}
.mb-sidebar-head{
  padding:14px 16px 10px;border-bottom:1px solid var(--border);background:var(--off);
  display:flex;align-items:center;justify-content:space-between;
}
.mb-sidebar-head span{
  font-family:'Cinzel',serif;font-size:.62rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.12em;color:var(--muted);
}
.mb-grupos-list{list-style:none;padding:6px 0}
.mb-grupos-list li a{
  display:flex;align-items:center;gap:10px;
  padding:8px 14px;font-size:.84rem;color:var(--txt);
  transition:background var(--ease),color var(--ease);
  border-left:3px solid transparent;
}
.mb-grupos-list li a:hover{background:var(--green-pale);color:var(--green-dk)}
.mb-grupos-list li a.sel{background:var(--green-pale);color:var(--green-dk);border-left-color:var(--green-dk);font-weight:600}
.mb-grupos-list li a.todos{font-weight:600;color:var(--muted)}
.mb-grupos-list li a.todos.sel{color:var(--green-dk);border-left-color:var(--green)}
.grupo-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.grupo-count{margin-left:auto;font-size:.72rem;color:var(--muted);background:var(--bg);padding:1px 7px;border-radius:20px}
.mb-grupos-list li a.sel .grupo-count{background:rgba(30,107,53,.12);color:var(--green-dk)}

/* ── área de conteúdo ── */
.mb-main{padding-left:20px;min-width:0}
.mb-header{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.mb-titulo{font-family:'Cinzel',serif;font-size:.9rem;font-weight:700;color:var(--green-dk);letter-spacing:.05em;text-transform:uppercase}
.mb-sub{font-size:.78rem;color:var(--muted);margin-top:2px}
.mb-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.mb-search{
  padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;
  font-size:.82rem;background:var(--off);color:var(--text);outline:none;width:200px;
  transition:border-color var(--ease),box-shadow var(--ease);font-family:inherit;
}
.mb-search:focus{border-color:var(--green);background:#fff;box-shadow:0 0 0 3px rgba(30,107,53,.1)}

/* ── cards grid ── */
.mb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px}
.mb-card{
  background:#fff;border:1px solid var(--border);border-radius:var(--rl);
  overflow:hidden;transition:transform var(--ease),box-shadow var(--ease);
  display:flex;flex-direction:column;
}
.mb-card:hover{transform:translateY(-3px);box-shadow:var(--sh)}
.mb-card-photo{
  width:100%;aspect-ratio:1;background:var(--green-pale);
  display:flex;align-items:center;justify-content:center;overflow:hidden;
  position:relative;
}
.mb-card-photo img{width:100%;height:100%;object-fit:cover}
.mb-card-photo .mb-inicial{
  font-family:'Cinzel',serif;font-size:2.4rem;font-weight:700;
  color:var(--green);opacity:.5;
}
.mb-card-body{padding:14px 14px 10px}
.mb-card-nome{font-size:.9rem;font-weight:700;color:var(--green-dk);line-height:1.3;margin-bottom:8px}
.mb-card-info{display:flex;flex-direction:column;gap:4px}
.mb-info-row{display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--muted)}
.mb-info-row svg{flex-shrink:0;opacity:.6}
.mb-card-footer{
  margin-top:auto;padding:10px 14px;border-top:1px solid var(--border);
  display:flex;gap:6px;justify-content:flex-end;background:var(--off);
}

/* ── grupos na parte inferior do card ── */
.mb-card-grupos{padding:0 14px 10px;display:flex;flex-wrap:wrap;gap:4px}
.mb-gtag{
  font-size:.64rem;font-weight:600;padding:2px 8px;border-radius:20px;
  color:#fff;white-space:nowrap;
}

/* ── vazio ── */
.mb-empty{
  grid-column:1/-1;text-align:center;padding:60px 20px;
  color:var(--muted);
}
.mb-empty svg{width:48px;height:48px;stroke:var(--border);margin:0 auto 16px}
.mb-empty p{font-size:.88rem}

/* ── modal ── */
.modal-bg{
  display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);
  z-index:500;align-items:center;justify-content:center;padding:20px;
}
.modal-bg.open{display:flex}
.modal{background:#fff;border-radius:var(--rl);width:100%;max-width:420px;box-shadow:var(--sh)}
.modal-head{padding:18px 20px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-head h3{font-family:'Cinzel',serif;font-size:.82rem;font-weight:700;color:var(--green-dk);letter-spacing:.05em;text-transform:uppercase}
.modal-close{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--muted);cursor:pointer;transition:background var(--ease),color var(--ease)}
.modal-close:hover{background:var(--bg);color:var(--txt)}
.modal-body{padding:20px}
.modal-body select{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;font-family:inherit;background:var(--off)}
.modal-body select:focus{border-color:var(--green);outline:none;box-shadow:0 0 0 3px rgba(30,107,53,.1)}
.modal-foot{padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px}

/* ── responsivo ── */
@media(max-width:860px){
  .mb-layout{grid-template-columns:1fr;gap:16px}
  .mb-sidebar{position:static}
  .mb-grupos-list{display:flex;overflow-x:auto;padding:6px;gap:6px}
  .mb-grupos-list li a{
    white-space:nowrap;border-left:none;border-bottom:2px solid transparent;
    border-radius:20px;padding:5px 13px;
    background:var(--off);border:1px solid var(--border);
  }
  .mb-grupos-list li a.sel{border-color:var(--green-dk);background:var(--green-pale)}
  .mb-main{padding-left:0}
}
@media(max-width:520px){
  .mb-grid{grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:12px}
  .mb-actions{width:100%;justify-content:space-between}
  .mb-search{width:160px}
}
</style>

<!-- cabeçalho da página -->
<div class="mb-header">
  <div>
    <div class="mb-titulo"><?= $grupo_atual ? htmlspecialchars($grupo_atual['nome']) : 'Todos os membros' ?></div>
    <div class="mb-sub"><?= count($membros) ?> <?= count($membros) === 1 ? 'membro' : 'membros' ?> encontrado<?= count($membros) === 1 ? '' : 's' ?></div>
  </div>
  <div class="mb-actions">
    <form method="get" style="display:contents">
      <?php if ($grupo_id): ?><input type="hidden" name="grupo" value="<?= $grupo_id ?>"><?php endif; ?>
      <input type="text" name="q" class="mb-search" placeholder="Buscar membro…" value="<?= htmlspecialchars($busca) ?>">
    </form>
    <?php if ($grupo_id && !empty($nao_no_grupo)): ?>
      <button class="btn btn-ouro btn-sm" onclick="document.getElementById('modal-add').classList.add('open')">+ Adicionar ao grupo</button>
    <?php endif; ?>
    <a href="/portal/membros/novo.php<?= $grupo_id ? "?grupo={$grupo_id}" : '' ?>" class="btn btn-primary btn-sm">+ Novo membro</a>
    <a href="/portal/membros/grupos.php" class="btn btn-ghost btn-sm">Gerenciar grupos</a>
  </div>
</div>

<div class="mb-layout">

  <!-- SIDEBAR GRUPOS -->
  <div>
    <div class="mb-sidebar">
      <div class="mb-sidebar-head">
        <span>Grupos</span>
        <a href="/portal/membros/grupos.php" title="Gerenciar grupos" style="color:var(--muted);font-size:.72rem">⚙</a>
      </div>
      <ul class="mb-grupos-list">
        <li>
          <a href="/portal/membros/" class="todos <?= !$grupo_id ? 'sel' : '' ?>">
            <span class="grupo-dot" style="background:var(--green)"></span>
            Todos
            <span class="grupo-count"><?= $total_geral ?></span>
          </a>
        </li>
        <?php foreach ($grupos as $g): ?>
        <li>
          <a href="/portal/membros/?grupo=<?= $g['id'] ?>" class="<?= $grupo_id == $g['id'] ? 'sel' : '' ?>">
            <span class="grupo-dot" style="background:<?= htmlspecialchars($g['cor']) ?>"></span>
            <?= htmlspecialchars($g['nome']) ?>
            <span class="grupo-count"><?= $g['total'] ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php if (empty($grupos)): ?>
        <div style="padding:16px;font-size:.78rem;color:var(--muted);text-align:center">
          Nenhum grupo criado.<br>
          <a href="/portal/membros/grupos.php" style="color:var(--green)">Criar agora →</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ÁREA PRINCIPAL -->
  <div class="mb-main">
    <div class="mb-grid">

      <?php if (empty($membros)): ?>
      <div class="mb-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
        </svg>
        <p>
          <?php if ($busca): ?>
            Nenhum membro encontrado para "<strong><?= htmlspecialchars($busca) ?></strong>".
          <?php elseif ($grupo_id): ?>
            Este grupo ainda não tem membros.<br>
            <a href="#" onclick="document.getElementById('modal-add').classList.add('open');return false" style="color:var(--green)">Adicionar membros →</a>
          <?php else: ?>
            Nenhum membro cadastrado ainda.<br>
            <a href="/portal/membros/novo.php" style="color:var(--green)">Cadastrar primeiro membro →</a>
          <?php endif; ?>
        </p>
      </div>

      <?php else: ?>
      <?php
        // Pega grupos de cada membro para exibir as tags
        $ids = array_column($membros, 'id');
        $grupo_map = []; // membro_id => [{nome, cor}]
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $st3 = db()->prepare("SELECT r.membro_id, g.nome, g.cor FROM membros_grupo_rel r JOIN membros_grupos g ON g.id=r.grupo_id WHERE r.membro_id IN ($ph)");
            $st3->execute($ids);
            foreach ($st3->fetchAll() as $row) {
                $grupo_map[$row['membro_id']][] = $row;
            }
        }
      ?>
      <?php foreach ($membros as $m): ?>
      <?php
        $inicial = mb_strtoupper(mb_substr(trim($m['nome']), 0, 1));
        $idade = '';
        if ($m['data_nasc']) {
            $nasc = new DateTime($m['data_nasc']);
            $hoje = new DateTime();
            $idade = $nasc->diff($hoje)->y . ' anos';
        }
        $gtags = $grupo_map[$m['id']] ?? [];
      ?>
      <div class="mb-card">
        <!-- foto -->
        <div class="mb-card-photo">
          <?php if ($m['foto'] && file_exists(__DIR__ . '/fotos/' . $m['foto'])): ?>
            <img src="/portal/membros/fotos/<?= htmlspecialchars($m['foto']) ?>?v=<?= filemtime(__DIR__.'/fotos/'.$m['foto']) ?>" alt="<?= htmlspecialchars($m['nome']) ?>">
          <?php else: ?>
            <span class="mb-inicial"><?= $inicial ?></span>
          <?php endif; ?>
        </div>

        <!-- body -->
        <div class="mb-card-body">
          <div class="mb-card-nome"><?= htmlspecialchars($m['nome']) ?></div>
          <div class="mb-card-info">
            <?php if ($m['telefone']): ?>
            <div class="mb-info-row">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
              <?= htmlspecialchars($m['telefone']) ?>
            </div>
            <?php endif; ?>
            <?php if ($m['cidade'] || $m['bairro']): ?>
            <div class="mb-info-row">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <?= htmlspecialchars(implode(', ', array_filter([$m['bairro'], $m['cidade']]))) ?>
            </div>
            <?php endif; ?>
            <?php if ($idade): ?>
            <div class="mb-info-row">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              <?= $idade ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- tags de grupos -->
        <?php if ($gtags): ?>
        <div class="mb-card-grupos">
          <?php foreach ($gtags as $gt): ?>
            <span class="mb-gtag" style="background:<?= htmlspecialchars($gt['cor']) ?>"><?= htmlspecialchars($gt['nome']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ações -->
        <div class="mb-card-footer">
          <?php if ($grupo_id): ?>
          <form method="post" onsubmit="return confirm('Remover <?= htmlspecialchars(addslashes($m['nome'])) ?> do grupo?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="remover_grupo">
            <input type="hidden" name="grupo_id" value="<?= $grupo_id ?>">
            <input type="hidden" name="membro_id" value="<?= $m['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Remover</button>
          </form>
          <?php endif; ?>
          <a href="/portal/membros/ver.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-sm">Ver</a>
          <a href="/portal/membros/editar.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

    </div>
  </div><!-- /.mb-main -->
</div><!-- /.mb-layout -->

<!-- Modal: adicionar membro ao grupo -->
<?php if ($grupo_id && !empty($nao_no_grupo)): ?>
<div class="modal-bg" id="modal-add">
  <div class="modal">
    <div class="modal-head">
      <h3>Adicionar ao grupo</h3>
      <button class="modal-close" onclick="document.getElementById('modal-add').classList.remove('open')">✕</button>
    </div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao" value="adicionar_grupo">
        <input type="hidden" name="grupo_id" value="<?= $grupo_id ?>">
        <label style="margin-bottom:8px;display:block">Selecione o membro:</label>
        <select name="membro_id" required>
          <option value="">— escolha —</option>
          <?php foreach ($nao_no_grupo as $nm): ?>
            <option value="<?= $nm['id'] ?>"><?= htmlspecialchars($nm['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('modal-add').classList.remove('open')">Cancelar</button>
        <button type="submit" class="btn btn-primary btn-sm">Adicionar</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('modal-add').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
