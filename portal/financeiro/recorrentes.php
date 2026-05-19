<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin','financeiro']);

$titulo = 'Pagamentos Recorrentes';
$pagina_ativa = 'financeiro';
$erro = '';

$categorias_rec  = db()->query("SELECT * FROM financeiro_categorias WHERE tipo='receita' AND ativo=1 ORDER BY ordem,nome")->fetchAll();
$categorias_desp = db()->query("SELECT * FROM financeiro_categorias WHERE tipo='despesa' AND ativo=1 ORDER BY ordem,nome")->fetchAll();

// Processar ações
if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    // Salvar novo / editar
    if (in_array($acao,['criar','editar'])) {
        $edit_id   = (int)($_POST['id'] ?? 0);
        $tipo      = $_POST['tipo']              ?? '';
        $cat_id    = (int)($_POST['categoria_id']  ?? 0);
        $descricao = trim($_POST['descricao']      ?? '');
        $valor     = (float)str_replace(',','.',preg_replace('/[^0-9,.]/','',$_POST['valor'] ?? '0'));
        $dia_venc  = max(1,min(31,(int)($_POST['dia_vencimento'] ?? 1)));
        $forma     = $_POST['forma_pagamento']     ?? 'pix';
        $status    = $_POST['status']              ?? 'ativo';
        $obs       = trim($_POST['observacoes']    ?? '');
        $prox_v    = $_POST['proximo_vencimento']  ?? null;

        if (!in_array($tipo,['receita','despesa'])) { $erro='Tipo inválido.'; goto fim; }
        if (!$cat_id)    { $erro='Selecione a categoria.'; goto fim; }
        if (!$descricao) { $erro='Descrição obrigatória.'; goto fim; }
        if ($valor <= 0) { $erro='Valor deve ser maior que zero.'; goto fim; }

        if ($acao==='criar') {
            db()->prepare("INSERT INTO financeiro_recorrentes (descricao,tipo,categoria_id,valor,dia_vencimento,forma_pagamento,status,proximo_vencimento,observacoes) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$descricao,$tipo,$cat_id,$valor,$dia_venc,$forma,$status,$prox_v ?: null,$obs ?: null]);
        } else {
            db()->prepare("UPDATE financeiro_recorrentes SET descricao=?,tipo=?,categoria_id=?,valor=?,dia_vencimento=?,forma_pagamento=?,status=?,proximo_vencimento=?,observacoes=? WHERE id=?")
                ->execute([$descricao,$tipo,$cat_id,$valor,$dia_venc,$forma,$status,$prox_v ?: null,$obs ?: null,$edit_id]);
        }
        header("Location: /portal/financeiro/recorrentes.php?ok=1"); exit;
    }

    // Inativar / ativar / excluir
    if ($acao==='toggle') {
        $rec_id = (int)($_POST['id'] ?? 0);
        $r = db()->prepare("SELECT status FROM financeiro_recorrentes WHERE id=?");
        $r->execute([$rec_id]); $r = $r->fetch();
        if ($r) {
            $novo = $r['status']==='ativo' ? 'inativo' : 'ativo';
            db()->prepare("UPDATE financeiro_recorrentes SET status=? WHERE id=?")->execute([$novo,$rec_id]);
        }
        header("Location: /portal/financeiro/recorrentes.php?ok=1"); exit;
    }

    if ($acao==='deletar') {
        $rec_id = (int)($_POST['id'] ?? 0);
        if ($rec_id) db()->prepare("DELETE FROM financeiro_recorrentes WHERE id=?")->execute([$rec_id]);
        header("Location: /portal/financeiro/recorrentes.php?ok=1"); exit;
    }

    // Lançar para o mês
    if ($acao==='lancar') {
        $rec_id = (int)($_POST['id'] ?? 0);
        header("Location: /portal/financeiro/novo.php?rec={$rec_id}"); exit;
    }
}
fim:

$recorrentes = db()->query("SELECT r.*, c.nome as cat_nome, c.cor as cat_cor FROM financeiro_recorrentes r LEFT JOIN financeiro_categorias c ON c.id=r.categoria_id ORDER BY r.status DESC, r.descricao")->fetchAll();

// Separa os dados para o formulário de edição se vier ?editar=ID
$edit_rec = null;
if (!empty($_GET['editar'])) {
    foreach ($recorrentes as $r) {
        if ($r['id'] == (int)$_GET['editar']) { $edit_rec = $r; break; }
    }
}

$hoje = date('Y-m-d');
$d_tipo = $edit_rec['tipo'] ?? 'despesa';

include dirname(__DIR__) . '/_layout.php';
?>
<style>
.fin-nav{display:flex;align-items:center;gap:8px;margin-bottom:24px;flex-wrap:wrap}
.fin-nav a,.fin-nav span{padding:6px 14px;border-radius:7px;font-size:.78rem;font-weight:600;text-decoration:none;border:1.5px solid var(--border);color:var(--text);background:#fff;transition:.15s}
.fin-nav a:hover{border-color:var(--green);color:var(--green)}
.fin-nav span{background:var(--green-dk);color:#fff;border-color:var(--green-dk)}
.tipo-tabs{display:flex;gap:0;border-radius:9px;overflow:hidden;border:2px solid var(--border);margin-bottom:20px}
.tipo-tab{flex:1;padding:9px;text-align:center;cursor:pointer;font-family:'Cinzel',serif;font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;transition:.15s;user-select:none}
.tipo-tab.rec.ativo{background:#16a34a;color:#fff}
.tipo-tab.desp.ativo{background:#dc2626;color:#fff}
.tipo-tab{background:#fff;color:var(--muted)}
.cats-rec,.cats-desp{display:none}
.cats-rec.show,.cats-desp.show{display:block}
.origem-wrap{display:none}.origem-wrap.show{display:block}
.form-row{gap:16px}
.rec-card{background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px 20px;display:flex;align-items:center;gap:12px;transition:.15s}
.rec-card:hover{border-color:var(--green);box-shadow:0 2px 8px rgba(30,107,53,.08)}
.rec-card.inativo{opacity:.6}
.rec-card .rc-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.rec-card .rc-info{flex:1;min-width:0}
.rec-card .rc-desc{font-weight:600;font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rec-card .rc-meta{font-size:.72rem;color:var(--muted);margin-top:2px}
.rec-card .rc-valor{font-family:'Cinzel',serif;font-weight:700;font-size:.9rem;white-space:nowrap}
.rec-card .rc-actions{display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap}
.venc-badge{display:inline-block;padding:1px 7px;border-radius:20px;font-size:.65rem;font-weight:700}
.venc-hoje{background:#fef9c3;color:#854d0e}
.venc-atrasado{background:#fee2e2;color:#991b1b}
.venc-ok{background:#dcfce7;color:#166534}
.rec-grid{display:flex;flex-direction:column;gap:8px}
</style>

<div class="fin-nav">
  <a href="/portal/financeiro/">Dashboard</a>
  <div style="color:var(--border)">|</div>
  <a href="/portal/financeiro/lancamentos.php">Lançamentos</a>
  <span>Recorrentes</span>
  <a href="/portal/financeiro/balanco.php">Balanço</a>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alerta alerta-ok" style="margin-bottom:16px">Operação realizada com sucesso.</div><?php endif; ?>
<?php if ($erro): ?><div class="alerta alerta-erro" style="margin-bottom:16px"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<!-- Formulário criar / editar -->
<div class="tabela-wrap" style="margin-bottom:24px">
  <div class="tabela-header">
    <h2><?= $edit_rec ? 'Editar recorrente #'.$edit_rec['id'] : 'Novo recorrente' ?></h2>
  </div>
  <div style="padding:20px">
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="<?= $edit_rec ? 'editar' : 'criar' ?>">
      <?php if ($edit_rec): ?><input type="hidden" name="id" value="<?= $edit_rec['id'] ?>"><?php endif; ?>
      <input type="hidden" name="tipo" id="tipo_hidden" value="<?= htmlspecialchars($d_tipo) ?>">

      <div class="tipo-tabs">
        <div class="tipo-tab rec <?= $d_tipo==='receita'?'ativo':'' ?>" onclick="setTipo('receita')">↑ Receita</div>
        <div class="tipo-tab desp <?= $d_tipo==='despesa'?'ativo':'' ?>" onclick="setTipo('despesa')">↓ Despesa</div>
      </div>

      <div class="form-row">
        <div class="form-group" style="grid-column:1/-1">
          <label>Descrição <span style="color:var(--red)">*</span></label>
          <input type="text" name="descricao" value="<?= htmlspecialchars($edit_rec['descricao'] ?? '') ?>" placeholder="Ex: Aluguel da sede, Energia elétrica…" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Categoria <span style="color:var(--red)">*</span></label>
          <div class="cats-rec <?= $d_tipo==='receita'?'show':'' ?>">
            <select name="categoria_id" id="cat_rec">
              <option value="">Selecione...</option>
              <?php foreach ($categorias_rec as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($edit_rec['categoria_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="cats-desp <?= $d_tipo==='despesa'?'show':'' ?>">
            <select name="categoria_id" id="cat_desp">
              <option value="">Selecione...</option>
              <?php foreach ($categorias_desp as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($edit_rec['categoria_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Valor (R$) <span style="color:var(--red)">*</span></label>
          <input type="text" name="valor" id="valor_input" value="<?= $edit_rec ? number_format($edit_rec['valor'],2,',','') : '' ?>" placeholder="0,00" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Dia de vencimento</label>
          <input type="number" name="dia_vencimento" min="1" max="31" value="<?= $edit_rec['dia_vencimento'] ?? 1 ?>">
        </div>
        <div class="form-group">
          <label>Próximo vencimento</label>
          <input type="date" name="proximo_vencimento" value="<?= htmlspecialchars($edit_rec['proximo_vencimento'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Forma de pagamento</label>
          <select name="forma_pagamento">
            <?php foreach (['dinheiro'=>'Dinheiro','pix'=>'PIX','transferencia'=>'Transferência','boleto'=>'Boleto','cartao'=>'Cartão','cheque'=>'Cheque','outro'=>'Outro'] as $v=>$lb): ?>
            <option value="<?= $v ?>" <?= ($edit_rec['forma_pagamento'] ?? 'pix') === $v ? 'selected' : '' ?>><?= $lb ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="ativo"   <?= ($edit_rec['status'] ?? 'ativo')==='ativo' ? 'selected' : '' ?>>Ativo</option>
            <option value="inativo" <?= ($edit_rec['status'] ?? '')==='inativo'    ? 'selected' : '' ?>>Inativo</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Observações</label>
        <textarea name="observacoes" rows="2" placeholder="Notas..."><?= htmlspecialchars($edit_rec['observacoes'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary btn-sm"><?= $edit_rec ? 'Salvar alterações' : 'Criar recorrente' ?></button>
        <?php if ($edit_rec): ?>
        <a href="/portal/financeiro/recorrentes.php" class="btn btn-ghost btn-sm">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Lista de recorrentes -->
<div class="tabela-wrap">
  <div class="tabela-header">
    <h2>Recorrentes cadastrados (<?= count($recorrentes) ?>)</h2>
  </div>
  <?php if (empty($recorrentes)): ?>
    <div style="padding:40px;text-align:center;color:var(--muted)">Nenhum recorrente cadastrado.</div>
  <?php else: ?>
  <div style="padding:16px">
    <div class="rec-grid">
    <?php foreach ($recorrentes as $r):
      $prox = $r['proximo_vencimento'];
      $badge = '';
      if ($prox) {
          if ($prox < $hoje) $badge = '<span class="venc-badge venc-atrasado">Vencido</span>';
          elseif ($prox === $hoje) $badge = '<span class="venc-badge venc-hoje">Hoje</span>';
          else {
              $diff = (strtotime($prox) - strtotime($hoje)) / 86400;
              if ($diff <= 7) $badge = '<span class="venc-badge venc-hoje">Em '.round($diff).'d</span>';
              else $badge = '<span class="venc-badge venc-ok">'.date('d/m', strtotime($prox)).'</span>';
          }
      }
    ?>
    <div class="rec-card <?= $r['status']==='inativo'?'inativo':'' ?>">
      <span class="rc-dot" style="background:<?= htmlspecialchars($r['cat_cor']??'#999') ?>"></span>
      <div class="rc-info">
        <div class="rc-desc"><?= htmlspecialchars($r['descricao']) ?></div>
        <div class="rc-meta">
          <?= htmlspecialchars($r['cat_nome']??'—') ?> ·
          Dia <?= $r['dia_vencimento'] ?> ·
          <?= ucfirst($r['forma_pagamento']) ?>
          <?php if ($badge): ?> · <?= $badge ?><?php endif; ?>
          <?php if ($r['status']==='inativo'): ?> · <span style="color:var(--muted);font-size:.65rem;font-weight:700">INATIVO</span><?php endif; ?>
        </div>
      </div>
      <div class="rc-valor" style="color:<?= $r['tipo']==='receita'?'#16a34a':'#dc2626' ?>">
        <?= $r['tipo']==='receita'?'↑':'↓' ?> R$ <?= number_format($r['valor'],2,',','.') ?>
      </div>
      <div class="rc-actions">
        <!-- Lançar para o mês -->
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="lancar">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button type="submit" class="btn btn-primary btn-sm" title="Gerar lançamento deste mês">Lançar</button>
        </form>
        <!-- Editar -->
        <a href="?editar=<?= $r['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
        <!-- Ativar/Inativar -->
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="toggle">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm"><?= $r['status']==='ativo'?'Pausar':'Ativar' ?></button>
        </form>
        <!-- Excluir -->
        <form method="post" onsubmit="return confirm('Excluir este recorrente? Os lançamentos já gerados não serão afetados.')" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="deletar">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">✕</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function setTipo(t) {
  document.getElementById('tipo_hidden').value = t;
  document.querySelectorAll('.tipo-tab.rec').forEach(el => el.classList.toggle('ativo', t==='receita'));
  document.querySelectorAll('.tipo-tab.desp').forEach(el => el.classList.toggle('ativo', t==='despesa'));
  document.querySelectorAll('.cats-rec').forEach(el => el.classList.toggle('show', t==='receita'));
  document.querySelectorAll('.cats-desp').forEach(el => el.classList.toggle('show', t==='despesa'));
  var active = t==='receita' ? document.getElementById('cat_rec') : document.getElementById('cat_desp');
  var other  = t==='receita' ? document.getElementById('cat_desp') : document.getElementById('cat_rec');
  active.setAttribute('name','categoria_id');
  other.removeAttribute('name');
}
setTipo(document.getElementById('tipo_hidden').value);

document.getElementById('valor_input').addEventListener('input', function() {
  var v = this.value.replace(/\D/g,'');
  if (!v) { this.value=''; return; }
  this.value = (parseInt(v)/100).toFixed(2).replace('.',',');
});
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
