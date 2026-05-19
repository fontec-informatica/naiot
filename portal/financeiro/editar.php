<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin','financeiro']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: /portal/financeiro/lancamentos.php"); exit; }

$l = db()->prepare("SELECT * FROM financeiro_lancamentos WHERE id=?");
$l->execute([$id]);
$l = $l->fetch();
if (!$l) { header("Location: /portal/financeiro/lancamentos.php"); exit; }

$titulo = 'Editar Lançamento';
$pagina_ativa = 'financeiro';
$erro = '';

// Excluir anexo individual
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['acao']??'')==='del_anexo' && csrf_valido()) {
    $anx_id = (int)($_POST['anx_id'] ?? 0);
    $anx = db()->prepare("SELECT nome_arquivo FROM financeiro_anexos WHERE id=? AND lancamento_id=?");
    $anx->execute([$anx_id, $id]);
    $anx = $anx->fetch();
    if ($anx) {
        @unlink(__DIR__ . '/uploads/' . $anx['nome_arquivo']);
        db()->prepare("DELETE FROM financeiro_anexos WHERE id=?")->execute([$anx_id]);
    }
    header("Location: /portal/financeiro/editar.php?id={$id}&ok_anx=1");
    exit;
}

// Salvar edição
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['acao']??'')==='salvar' && csrf_valido()) {
    $tipo      = $_POST['tipo']              ?? '';
    $cat_id    = (int)($_POST['categoria_id']  ?? 0);
    $descricao = trim($_POST['descricao']      ?? '');
    $valor     = (float)str_replace(',','.',preg_replace('/[^0-9,.]/','',$_POST['valor'] ?? '0'));
    $data      = $_POST['data_lancamento']     ?? date('Y-m-d');
    $comp_mes  = (int)($_POST['competencia_mes'] ?? date('n'));
    $comp_ano  = (int)($_POST['competencia_ano'] ?? date('Y'));
    $status    = $_POST['status']              ?? 'realizado';
    $forma     = $_POST['forma_pagamento']     ?? 'dinheiro';
    $origem    = $_POST['origem']              ?? null;
    $rec_id    = (int)($_POST['recorrente_id'] ?? 0) ?: null;
    $obs       = trim($_POST['observacoes']    ?? '');

    if (!in_array($tipo,['receita','despesa'])) { $erro='Tipo inválido.'; goto fim; }
    if (!$cat_id)    { $erro='Selecione a categoria.'; goto fim; }
    if (!$descricao) { $erro='Descrição obrigatória.'; goto fim; }
    if ($valor <= 0) { $erro='Valor deve ser maior que zero.'; goto fim; }

    db()->prepare("UPDATE financeiro_lancamentos SET tipo=?,categoria_id=?,descricao=?,valor=?,data_lancamento=?,competencia_mes=?,competencia_ano=?,status=?,forma_pagamento=?,origem=?,recorrente_id=?,observacoes=? WHERE id=?")
        ->execute([$tipo,$cat_id,$descricao,$valor,$data,$comp_mes,$comp_ano,$status,$forma,$origem ?: null,$rec_id,$obs ?: null,$id]);

    // Novos anexos
    if (!empty($_FILES['anexos']['name'][0])) {
        $permitidos = ['application/pdf','image/jpeg','image/png','image/webp','image/gif'];
        $uploads_dir = __DIR__ . '/uploads/';
        foreach ($_FILES['anexos']['tmp_name'] as $i => $tmp) {
            if (!$tmp || $_FILES['anexos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmp);
            if (!in_array($mime, $permitidos)) continue;
            if ($_FILES['anexos']['size'][$i] > 10 * 1024 * 1024) continue;
            $ext  = pathinfo($_FILES['anexos']['name'][$i], PATHINFO_EXTENSION);
            $nome = 'fin_' . uniqid() . '.' . strtolower($ext);
            if (move_uploaded_file($tmp, $uploads_dir . $nome)) {
                db()->prepare("INSERT INTO financeiro_anexos (lancamento_id,nome_original,nome_arquivo,tipo_mime,tamanho,tipo_doc) VALUES (?,?,?,?,?,?)")
                    ->execute([$id,$_FILES['anexos']['name'][$i],$nome,$mime,$_FILES['anexos']['size'][$i],$_POST['tipo_doc'][$i] ?? 'outro']);
            }
        }
    }

    $l = db()->prepare("SELECT * FROM financeiro_lancamentos WHERE id=?");
    $l->execute([$id]);
    $l = $l->fetch();
    $ok = 1;
}
fim:

$categorias_rec  = db()->query("SELECT * FROM financeiro_categorias WHERE tipo='receita' AND ativo=1 ORDER BY ordem,nome")->fetchAll();
$categorias_desp = db()->query("SELECT * FROM financeiro_categorias WHERE tipo='despesa' AND ativo=1 ORDER BY ordem,nome")->fetchAll();
$recorrentes     = db()->query("SELECT * FROM financeiro_recorrentes WHERE status='ativo' ORDER BY descricao")->fetchAll();

$st = db()->prepare("SELECT * FROM financeiro_anexos WHERE lancamento_id=? ORDER BY id");
$st->execute([$id]);
$anexos = $st->fetchAll();

$d_tipo = $l['tipo'];
$d_mes  = $l['competencia_mes'];
$d_ano  = $l['competencia_ano'];

include dirname(__DIR__) . '/_layout.php';
?>
<style>
.tipo-tabs{display:flex;gap:0;margin-bottom:24px;border-radius:9px;overflow:hidden;border:2px solid var(--border)}
.tipo-tab{flex:1;padding:10px;text-align:center;cursor:pointer;font-family:'Cinzel',serif;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;transition:.15s;user-select:none}
.tipo-tab.rec{background:#fff;color:var(--muted)}
.tipo-tab.rec.ativo{background:#16a34a;color:#fff}
.tipo-tab.desp{background:#fff;color:var(--muted)}
.tipo-tab.desp.ativo{background:#dc2626;color:#fff}
.cats-rec,.cats-desp{display:none}
.cats-rec.show,.cats-desp.show{display:block}
.origem-wrap{display:none}
.origem-wrap.show{display:block}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:600px){.form-row{grid-template-columns:1fr}}
.anx-grid{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.anx-item{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--off);border-radius:8px;border:1px solid var(--border)}
.anx-item .anx-icon{font-size:1.1rem;flex-shrink:0}
.anx-item .anx-info{flex:1;min-width:0}
.anx-info .anx-nome{font-size:.82rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.anx-info .anx-meta{font-size:.7rem;color:var(--muted)}
.anx-item .anx-actions{display:flex;gap:6px;flex-shrink:0}
</style>

<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <a href="/portal/financeiro/lancamentos.php?mes=<?= $l['competencia_mes'] ?>&ano=<?= $l['competencia_ano'] ?>"
     style="font-size:.78rem;color:var(--muted);text-decoration:none">← Voltar aos lançamentos</a>
</div>

<div class="form-wrap">
  <h2>Editar Lançamento <span style="font-size:.65em;font-family:'Inter',sans-serif;color:var(--muted)">#<?= $id ?></span></h2>

  <?php if ($erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
  <?php if (!empty($ok) || !empty($_GET['ok_anx'])): ?><div class="alerta alerta-ok">Salvo com sucesso.</div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="tipo" id="tipo_hidden" value="<?= htmlspecialchars($d_tipo) ?>">

    <!-- Tipo -->
    <div style="margin-bottom:20px">
      <div class="tipo-tabs">
        <div class="tipo-tab rec <?= $d_tipo==='receita'?'ativo':'' ?>" onclick="setTipo('receita')">↑ Receita</div>
        <div class="tipo-tab desp <?= $d_tipo==='despesa'?'ativo':'' ?>" onclick="setTipo('despesa')">↓ Despesa</div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Categoria <span style="color:var(--red)">*</span></label>
        <div class="cats-rec <?= $d_tipo==='receita'?'show':'' ?>">
          <select name="categoria_id" id="cat_rec">
            <option value="">Selecione...</option>
            <?php foreach ($categorias_rec as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $l['categoria_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="cats-desp <?= $d_tipo==='despesa'?'show':'' ?>">
          <select name="categoria_id" id="cat_desp">
            <option value="">Selecione...</option>
            <?php foreach ($categorias_desp as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $l['categoria_id']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group origem-wrap <?= $d_tipo==='receita'?'show':'' ?>" id="origem_wrap">
        <label>Origem da receita</label>
        <select name="origem">
          <option value="">— Opcional —</option>
          <?php foreach (['doacao'=>'Doação','dizimo'=>'Dízimo','contribuicao'=>'Contribuição','inscricao'=>'Inscrição em evento','mensalidade'=>'Mensalidade','outro'=>'Outro'] as $v=>$lb): ?>
          <option value="<?= $v ?>" <?= $l['origem']===$v?'selected':'' ?>><?= $lb ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Descrição <span style="color:var(--red)">*</span></label>
      <input type="text" name="descricao" value="<?= htmlspecialchars($l['descricao']) ?>" required>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Valor (R$) <span style="color:var(--red)">*</span></label>
        <input type="text" name="valor" id="valor_input" value="<?= number_format($l['valor'],2,',','') ?>" required>
      </div>
      <div class="form-group">
        <label>Data do lançamento</label>
        <input type="date" name="data_lancamento" value="<?= $l['data_lancamento'] ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Competência</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <select name="competencia_mes">
            <?php $meses=['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
            for($i=1;$i<=12;$i++): ?>
            <option value="<?= $i ?>" <?= $d_mes==$i?'selected':'' ?>><?= $meses[$i] ?></option>
            <?php endfor; ?>
          </select>
          <select name="competencia_ano">
            <?php for($y=date('Y')+1;$y>=2020;$y--): ?>
            <option value="<?= $y ?>" <?= $d_ano==$y?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Forma de pagamento</label>
        <select name="forma_pagamento">
          <?php foreach (['dinheiro'=>'Dinheiro','pix'=>'PIX','transferencia'=>'Transferência','boleto'=>'Boleto','cartao'=>'Cartão','cheque'=>'Cheque','outro'=>'Outro'] as $v=>$lb): ?>
          <option value="<?= $v ?>" <?= $l['forma_pagamento']===$v?'selected':'' ?>><?= $lb ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="realizado" <?= $l['status']==='realizado'?'selected':'' ?>>✓ Realizado</option>
          <option value="pendente"  <?= $l['status']==='pendente'?'selected':'' ?>>⏳ Pendente</option>
          <option value="cancelado" <?= $l['status']==='cancelado'?'selected':'' ?>>✗ Cancelado</option>
        </select>
      </div>
      <div class="form-group">
        <label>Vinculado a recorrente</label>
        <select name="recorrente_id">
          <option value="">— Nenhum —</option>
          <?php foreach ($recorrentes as $rec): ?>
          <option value="<?= $rec['id'] ?>" <?= $l['recorrente_id']==$rec['id']?'selected':'' ?>><?= htmlspecialchars($rec['descricao']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Observações</label>
      <textarea name="observacoes" rows="2"><?= htmlspecialchars($l['observacoes'] ?? '') ?></textarea>
    </div>

    <!-- Anexos existentes -->
    <?php if ($anexos): ?>
    <div style="border-top:1px solid var(--border);margin:8px 0 20px;padding-top:20px">
      <label style="font-family:'Cinzel',serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--green-dk);font-weight:700;display:block;margin-bottom:12px">
        Anexos existentes (<?= count($anexos) ?>)
      </label>
      <div class="anx-grid">
        <?php foreach ($anexos as $a):
          $is_img = str_starts_with($a['tipo_mime'],'image/');
          $icon = $is_img ? '🖼️' : '📄';
          $tipo_doc_labels = ['nf'=>'NF','comprovante'=>'Comprovante','recibo'=>'Recibo','foto'=>'Foto','outro'=>'Outro'];
        ?>
        <div class="anx-item">
          <span class="anx-icon"><?= $icon ?></span>
          <div class="anx-info">
            <div class="anx-nome"><?= htmlspecialchars($a['nome_original']) ?></div>
            <div class="anx-meta"><?= $tipo_doc_labels[$a['tipo_doc']] ?? 'Outro' ?> · <?= round($a['tamanho']/1024) ?>KB · <?= date('d/m/Y', strtotime($a['created_at'])) ?></div>
          </div>
          <div class="anx-actions">
            <a href="/portal/financeiro/arquivo.php?id=<?= $a['id'] ?>" target="_blank" class="btn btn-ghost btn-sm">Ver</a>
            <form method="post" onsubmit="return confirm('Excluir este anexo?')" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="acao" value="del_anexo">
              <input type="hidden" name="anx_id" value="<?= $a['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">✕</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Adicionar novos anexos -->
    <div style="border-top:1px solid var(--border);margin:8px 0 20px;padding-top:20px">
      <label style="font-family:'Cinzel',serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--green-dk);font-weight:700;display:block;margin-bottom:12px">
        Adicionar anexos
      </label>
      <div class="form-group">
        <label>Tipo do documento</label>
        <select name="tipo_doc[]" id="tipo_doc_sel">
          <option value="comprovante">Comprovante de pagamento</option>
          <option value="nf">Nota Fiscal (NF)</option>
          <option value="recibo">Recibo</option>
          <option value="foto">Foto / Digitalização</option>
          <option value="outro">Outro</option>
        </select>
      </div>
      <input type="file" name="anexos[]" id="anexos_input" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.gif"
             style="display:none" onchange="listarAnexos(this)">
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" onclick="document.getElementById('anexos_input').click()" class="btn btn-ghost btn-sm">
          📎 Selecionar arquivos
        </button>
        <button type="button" onclick="capturarCamera()" class="btn btn-ghost btn-sm">
          📷 Câmera / Scanner
        </button>
        <span style="font-size:.72rem;color:var(--muted);align-self:center">PDF, JPG, PNG — máx. 10MB cada</span>
      </div>
      <input type="file" name="anexos[]" id="camera_input" accept="image/*" capture="environment"
             style="display:none" onchange="listarAnexos(this)">
      <div id="anexo_list" style="display:flex;flex-direction:column;gap:8px;margin-top:8px"></div>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/financeiro/lancamentos.php?mes=<?= $l['competencia_mes'] ?>&ano=<?= $l['competencia_ano'] ?>" class="btn btn-ghost">Voltar</a>
    </div>
  </form>
</div>

<script>
function setTipo(t) {
  document.getElementById('tipo_hidden').value = t;
  document.querySelectorAll('.tipo-tab.rec').forEach(el => el.classList.toggle('ativo', t==='receita'));
  document.querySelectorAll('.tipo-tab.desp').forEach(el => el.classList.toggle('ativo', t==='despesa'));
  document.querySelectorAll('.cats-rec').forEach(el => el.classList.toggle('show', t==='receita'));
  document.querySelectorAll('.cats-desp').forEach(el => el.classList.toggle('show', t==='despesa'));
  document.getElementById('origem_wrap').classList.toggle('show', t==='receita');
  var active = t==='receita' ? document.getElementById('cat_rec') : document.getElementById('cat_desp');
  var other  = t==='receita' ? document.getElementById('cat_desp') : document.getElementById('cat_rec');
  active.setAttribute('name','categoria_id');
  other.removeAttribute('name');
}
setTipo(document.getElementById('tipo_hidden').value);

document.getElementById('valor_input').addEventListener('input', function() {
  var v = this.value.replace(/\D/g,'');
  if (!v) { this.value=''; return; }
  v = (parseInt(v)/100).toFixed(2);
  this.value = v.replace('.',',');
});

function listarAnexos(input) {
  var list = document.getElementById('anexo_list');
  Array.from(input.files).forEach(function(f) {
    var item = document.createElement('div');
    item.style.cssText='display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--off);border-radius:7px;border:1px solid var(--border)';
    item.innerHTML='<span style="flex:1;font-size:.8rem">📄 '+f.name+'</span><span style="font-size:.72rem;color:var(--muted)">'+(f.size/1024).toFixed(0)+'KB</span>';
    list.appendChild(item);
  });
}

function capturarCamera() {
  document.getElementById('camera_input').click();
}
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
