<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin','financeiro']);

$titulo = 'Novo Lançamento';
$pagina_ativa = 'financeiro';
$erro = '';
$ok   = '';

// Pré-preencher a partir de recorrente
$pre = [];
if (!empty($_GET['rec'])) {
    $st = db()->prepare("SELECT r.*,c.nome as cat_nome FROM financeiro_recorrentes r LEFT JOIN financeiro_categorias c ON c.id=r.categoria_id WHERE r.id=?");
    $st->execute([(int)$_GET['rec']]);
    $pre = $st->fetch() ?: [];
}

$categorias_rec  = db()->query("SELECT * FROM financeiro_categorias WHERE tipo='receita' AND ativo=1 ORDER BY ordem,nome")->fetchAll();
$categorias_desp = db()->query("SELECT * FROM financeiro_categorias WHERE tipo='despesa' AND ativo=1 ORDER BY ordem,nome")->fetchAll();
$recorrentes     = db()->query("SELECT * FROM financeiro_recorrentes WHERE status='ativo' ORDER BY descricao")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) { $erro = 'Token inválido.'; goto fim; }

    $tipo        = $_POST['tipo']          ?? '';
    $cat_id      = (int)($_POST['categoria_id'] ?? 0);
    $descricao   = trim($_POST['descricao']     ?? '');
    $valor       = (float)str_replace(',','.',preg_replace('/[^0-9,.]/','',$_POST['valor'] ?? '0'));
    $data        = $_POST['data_lancamento']    ?? date('Y-m-d');
    $comp_mes    = (int)($_POST['competencia_mes'] ?? date('n'));
    $comp_ano    = (int)($_POST['competencia_ano'] ?? date('Y'));
    $status      = $_POST['status']             ?? 'realizado';
    $forma       = $_POST['forma_pagamento']    ?? 'dinheiro';
    $origem      = $_POST['origem']             ?? null;
    $rec_id      = (int)($_POST['recorrente_id'] ?? 0) ?: null;
    $obs         = trim($_POST['observacoes']   ?? '');

    if (!in_array($tipo,['receita','despesa'])) { $erro='Tipo inválido.'; goto fim; }
    if (!$cat_id)     { $erro='Selecione a categoria.'; goto fim; }
    if (!$descricao)  { $erro='Descrição obrigatória.'; goto fim; }
    if ($valor <= 0)  { $erro='Valor deve ser maior que zero.'; goto fim; }

    db()->prepare("INSERT INTO financeiro_lancamentos (tipo,categoria_id,descricao,valor,data_lancamento,competencia_mes,competencia_ano,status,forma_pagamento,origem,recorrente_id,observacoes,usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$tipo,$cat_id,$descricao,$valor,$data,$comp_mes,$comp_ano,$status,$forma,$origem ?: null,$rec_id,$obs ?: null,$_SESSION['usuario_id'] ?? null]);

    $novo_id = (int)db()->lastInsertId();

    // Processar anexos
    if (!empty($_FILES['anexos']['name'][0])) {
        $permitidos = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $uploads_dir = __DIR__ . '/uploads/';
        foreach ($_FILES['anexos']['tmp_name'] as $i => $tmp) {
            if (!$tmp || $_FILES['anexos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmp);
            if (!isset($permitidos[$mime])) continue;
            if ($_FILES['anexos']['size'][$i] > 10 * 1024 * 1024) continue;
            // Extensão derivada do mime validado (nunca do nome enviado pelo usuário)
            $nome = 'fin_' . uniqid() . '.' . $permitidos[$mime];
            if (move_uploaded_file($tmp, $uploads_dir . $nome)) {
                db()->prepare("INSERT INTO financeiro_anexos (lancamento_id,nome_original,nome_arquivo,tipo_mime,tamanho,tipo_doc) VALUES (?,?,?,?,?,?)")
                    ->execute([$novo_id, $_FILES['anexos']['name'][$i], $nome, $mime, $_FILES['anexos']['size'][$i], $_POST['tipo_doc'][$i] ?? 'outro']);
            }
        }
    }

    // Atualizar proximo_vencimento do recorrente
    if ($rec_id) {
        $prox = date('Y-m-d', mktime(0,0,0,$comp_mes+1,1,$comp_ano));
        db()->prepare("UPDATE financeiro_recorrentes SET proximo_vencimento=? WHERE id=?")->execute([$prox,$rec_id]);
    }

    header("Location: /portal/financeiro/?mes={$comp_mes}&ano={$comp_ano}&ok=1");
    exit;
}
fim:

$d_mes = (int)($_POST['competencia_mes'] ?? $pre['competencia_mes'] ?? date('n'));
$d_ano = (int)($_POST['competencia_ano'] ?? $pre['competencia_ano'] ?? date('Y'));
$d_tipo= $_POST['tipo'] ?? ($pre['tipo'] ?? 'receita');

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
.anexo-list{display:flex;flex-direction:column;gap:8px;margin-top:8px}
.anexo-item{display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--off);border-radius:7px;border:1px solid var(--border)}
.anexo-item span{flex:1;font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.form-row{gap:16px}
</style>

<div class="form-wrap">
  <h2>Novo Lançamento</h2>

  <?php if ($erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

  <?php if (!empty($pre)): ?>
  <div class="alerta alerta-ok">Pré-preenchido a partir do recorrente: <strong><?= htmlspecialchars($pre['descricao']) ?></strong></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="tipo" id="tipo_hidden" value="<?= htmlspecialchars($d_tipo) ?>">
    <?php if (!empty($pre['id'])): ?>
    <input type="hidden" name="recorrente_id" value="<?= $pre['id'] ?>">
    <?php endif; ?>

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
            <option value="<?= $c['id'] ?>" <?= (($_POST['categoria_id'] ?? $pre['categoria_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="cats-desp <?= $d_tipo==='despesa'?'show':'' ?>">
          <select name="categoria_id" id="cat_desp">
            <option value="">Selecione...</option>
            <?php foreach ($categorias_desp as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (($_POST['categoria_id'] ?? $pre['categoria_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group origem-wrap <?= $d_tipo==='receita'?'show':'' ?>" id="origem_wrap">
        <label>Origem da receita</label>
        <select name="origem">
          <option value="">— Opcional —</option>
          <?php foreach (['doacao'=>'Doação','dizimo'=>'Dízimo','contribuicao'=>'Contribuição','inscricao'=>'Inscrição em evento','mensalidade'=>'Mensalidade','outro'=>'Outro'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= (($_POST['origem'] ?? '') === $v) ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Descrição <span style="color:var(--red)">*</span></label>
      <input type="text" name="descricao" value="<?= htmlspecialchars($_POST['descricao'] ?? $pre['descricao'] ?? '') ?>" placeholder="Ex: Aluguel da sede, Doação Fulano…" required>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Valor (R$) <span style="color:var(--red)">*</span></label>
        <input type="text" name="valor" id="valor_input" value="<?= htmlspecialchars($_POST['valor'] ?? ($pre['valor'] ? number_format($pre['valor'],2,',','') : '')) ?>" placeholder="0,00" required>
      </div>
      <div class="form-group">
        <label>Data do lançamento</label>
        <input type="date" name="data_lancamento" value="<?= htmlspecialchars($_POST['data_lancamento'] ?? date('Y-m-d')) ?>">
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
          <?php foreach (['dinheiro'=>'Dinheiro','pix'=>'PIX','transferencia'=>'Transferência','boleto'=>'Boleto','cartao'=>'Cartão','cheque'=>'Cheque','outro'=>'Outro'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= (($_POST['forma_pagamento'] ?? $pre['forma_pagamento'] ?? 'pix') === $v) ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="realizado" <?= (($_POST['status'] ?? 'realizado') === 'realizado') ? 'selected' : '' ?>>✓ Realizado</option>
          <option value="pendente"  <?= (($_POST['status'] ?? '') === 'pendente') ? 'selected' : '' ?>>⏳ Pendente</option>
          <option value="cancelado" <?= (($_POST['status'] ?? '') === 'cancelado') ? 'selected' : '' ?>>✗ Cancelado</option>
        </select>
      </div>
      <div class="form-group">
        <label>Vinculado a recorrente</label>
        <select name="recorrente_id" <?= !empty($pre['id']) ? 'disabled' : '' ?>>
          <option value="">— Nenhum —</option>
          <?php foreach ($recorrentes as $rec): ?>
          <option value="<?= $rec['id'] ?>" <?= (($_POST['recorrente_id'] ?? $pre['id'] ?? '') == $rec['id']) ? 'selected' : '' ?>><?= htmlspecialchars($rec['descricao']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Observações</label>
      <textarea name="observacoes" rows="2" placeholder="Notas adicionais..."><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
    </div>

    <!-- Anexos -->
    <div style="border-top:1px solid var(--border);margin:8px 0 20px;padding-top:20px">
      <label style="font-family:'Cinzel',serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--green-dk);font-weight:700;display:block;margin-bottom:12px">
        Anexos — NF, comprovante, recibo, foto
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
      <!-- input nativo para mobile (capture) -->
      <input type="file" name="anexos[]" id="camera_input" accept="image/*" capture="environment"
             style="display:none" onchange="listarAnexos(this)">
      <div class="anexo-list" id="anexo_list"></div>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">Salvar lançamento</button>
      <a href="/portal/financeiro/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<!-- Modal webcam -->
<div id="webcam_modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:14px;padding:20px;width:min(480px,94vw);box-shadow:0 8px 40px rgba(0,0,0,.35)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <strong style="font-family:'Cinzel',serif;font-size:.82rem;color:var(--green-dk)">📷 Câmera</strong>
      <button onclick="fecharWebcam()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--muted);line-height:1">×</button>
    </div>
    <div style="position:relative;background:#000;border-radius:10px;overflow:hidden;line-height:0">
      <video id="webcam_video" autoplay playsinline muted style="width:100%;max-height:320px;object-fit:cover;display:block"></video>
      <canvas id="webcam_canvas" style="display:none"></canvas>
      <!-- preview da foto tirada -->
      <img id="webcam_preview" style="display:none;width:100%;max-height:320px;object-fit:contain;border-radius:10px" alt="Preview">
    </div>
    <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
      <button id="webcam_btn_foto"   onclick="tirarFoto()"     class="btn btn-primary btn-sm">📷 Tirar foto</button>
      <button id="webcam_btn_usar"   onclick="usarFoto()"      class="btn btn-primary btn-sm" style="display:none">✓ Usar esta foto</button>
      <button id="webcam_btn_nova"   onclick="novaFoto()"      class="btn btn-ghost btn-sm"   style="display:none">↩ Nova foto</button>
      <button id="webcam_btn_virar"  onclick="virarCamera()"   class="btn btn-ghost btn-sm">⇄ Virar câmera</button>
      <button onclick="fecharWebcam()" class="btn btn-ghost btn-sm" style="margin-left:auto">Cancelar</button>
    </div>
    <p id="webcam_erro" style="color:var(--red);font-size:.78rem;margin-top:10px;display:none"></p>
  </div>
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
  this.value = (parseInt(v)/100).toFixed(2).replace('.',',');
});

function listarAnexos(input) {
  var list = document.getElementById('anexo_list');
  Array.from(input.files).forEach(function(f) {
    var item = document.createElement('div');
    item.className='anexo-item';
    item.innerHTML='<span>📄 '+f.name+'</span><span style="font-size:.72rem;color:var(--muted)">'+(f.size/1024).toFixed(0)+'KB</span>';
    list.appendChild(item);
  });
}

// ── Webcam ────────────────────────────────────────────────────────────────────
var _stream = null, _facing = 'environment', _capturedBlob = null;

function capturarCamera() {
  // No celular usa o input nativo (abre câmera do sistema)
  if (/Mobi|Android/i.test(navigator.userAgent)) {
    document.getElementById('camera_input').click();
    return;
  }
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    alert('Seu navegador não suporta acesso à câmera. Use um navegador moderno com HTTPS.');
    return;
  }
  var modal = document.getElementById('webcam_modal');
  modal.style.display = 'flex';
  document.getElementById('webcam_erro').style.display = 'none';
  _iniciarStream();
}

async function _iniciarStream() {
  if (_stream) { _stream.getTracks().forEach(t => t.stop()); _stream = null; }
  try {
    _stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: _facing, width:{ideal:1280}, height:{ideal:720} }, audio: false });
    var v = document.getElementById('webcam_video');
    v.srcObject = _stream;
    v.style.display = 'block';
    document.getElementById('webcam_preview').style.display = 'none';
    document.getElementById('webcam_btn_foto').style.display = '';
    document.getElementById('webcam_btn_usar').style.display = 'none';
    document.getElementById('webcam_btn_nova').style.display = 'none';
  } catch(e) {
    var msg = e.name === 'NotAllowedError'
      ? 'Permissão negada. Clique no cadeado na barra de endereço e permita o acesso à câmera.'
      : 'Erro ao acessar câmera: ' + e.message;
    var el = document.getElementById('webcam_erro');
    el.textContent = msg; el.style.display = 'block';
  }
}

function tirarFoto() {
  var video  = document.getElementById('webcam_video');
  var canvas = document.getElementById('webcam_canvas');
  canvas.width  = video.videoWidth  || 1280;
  canvas.height = video.videoHeight || 720;
  canvas.getContext('2d').drawImage(video, 0, 0);
  canvas.toBlob(function(blob) {
    _capturedBlob = blob;
    var url = URL.createObjectURL(blob);
    var preview = document.getElementById('webcam_preview');
    preview.src = url; preview.style.display = 'block';
    video.style.display = 'none';
    document.getElementById('webcam_btn_foto').style.display = 'none';
    document.getElementById('webcam_btn_usar').style.display = '';
    document.getElementById('webcam_btn_nova').style.display = '';
    if (_stream) { _stream.getTracks().forEach(t => t.stop()); _stream = null; }
  }, 'image/jpeg', 0.93);
}

function usarFoto() {
  if (!_capturedBlob) return;
  var nome  = 'foto_' + Date.now() + '.jpg';
  var file  = new File([_capturedBlob], nome, { type: 'image/jpeg' });
  var input = document.getElementById('anexos_input');
  var dt    = new DataTransfer();
  Array.from(input.files).forEach(f => dt.items.add(f));
  dt.items.add(file);
  input.files = dt.files;
  listarAnexos({ files: [file] });
  fecharWebcam();
}

function novaFoto() { _capturedBlob = null; _iniciarStream(); }

function virarCamera() {
  _facing = _facing === 'environment' ? 'user' : 'environment';
  _iniciarStream();
}

function fecharWebcam() {
  if (_stream) { _stream.getTracks().forEach(t => t.stop()); _stream = null; }
  _capturedBlob = null;
  document.getElementById('webcam_modal').style.display = 'none';
  document.getElementById('webcam_video').srcObject = null;
  document.getElementById('webcam_preview').style.display = 'none';
  document.getElementById('webcam_btn_foto').style.display = '';
  document.getElementById('webcam_btn_usar').style.display = 'none';
  document.getElementById('webcam_btn_nova').style.display = 'none';
}
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
