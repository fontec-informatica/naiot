<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Novo Membro';
$pagina_ativa = 'membros';

$grupo_id     = (int)($_GET['grupo']     ?? 0);
$cargo_id     = (int)($_GET['cargo']     ?? 0);
$habilidade_id = (int)($_GET['habilidade'] ?? 0);
$erros        = [];
$dados        = ['nome'=>'','telefone'=>'','data_nasc'=>'','endereco'=>'','bairro'=>'','cidade'=>''];

$grupos      = db()->query("SELECT * FROM membros_grupos ORDER BY nome")->fetchAll();
$cargos      = db()->query("SELECT * FROM membros_cargos ORDER BY nome")->fetchAll();
$habilidades = db()->query("SELECT * FROM membros_habilidades ORDER BY nome")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $dados['nome']      = trim($_POST['nome']      ?? '');
    $dados['telefone']  = trim($_POST['telefone']  ?? '');
    $dados['data_nasc'] = trim($_POST['data_nasc'] ?? '');
    $dados['endereco']  = trim($_POST['endereco']  ?? '');
    $dados['bairro']    = trim($_POST['bairro']    ?? '');
    $dados['cidade']    = trim($_POST['cidade']    ?? '');
    $grupos_sel         = array_map('intval', (array)($_POST['grupos']      ?? []));
    $cargos_sel         = array_map('intval', (array)($_POST['cargos']      ?? []));
    $habilidades_sel    = array_map('intval', (array)($_POST['habilidades'] ?? []));

    if (!$dados['nome']) $erros[] = 'O nome é obrigatório.';

    $foto_nome = null;
    // Foto via webcam (base64)
    $foto_b64 = trim($_POST['foto_webcam'] ?? '');
    if ($foto_b64 && preg_match('/^data:image\/(jpeg|png|webp);base64,/', $foto_b64, $m_ext)) {
        $ext       = $m_ext[1] === 'jpeg' ? 'jpg' : $m_ext[1];
        $foto_nome = uniqid('mb_', true) . '.' . $ext;
        $img_data  = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $foto_b64));
        $dir_fotos = __DIR__ . '/fotos/';
        if (!is_dir($dir_fotos)) mkdir($dir_fotos, 0755, true);
        file_put_contents($dir_fotos . $foto_nome, $img_data);
    } elseif (!empty($_FILES['foto']['tmp_name'])) {
        $f = $_FILES['foto'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
            $erros[] = 'Foto: somente JPG, PNG ou WEBP.';
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $erros[] = 'Foto: máximo 5 MB.';
        } else {
            $foto_nome = uniqid('mb_', true) . '.' . $ext;
        }
    }

    if (!$erros) {
        if ($foto_nome && empty($foto_b64) && !empty($_FILES['foto']['tmp_name'])) {
            $dir_fotos = __DIR__ . '/fotos/';
            if (!is_dir($dir_fotos)) mkdir($dir_fotos, 0755, true);
            move_uploaded_file($_FILES['foto']['tmp_name'], $dir_fotos . $foto_nome);
        }
        $st = db()->prepare("INSERT INTO membros (nome,foto,data_nasc,endereco,bairro,cidade,telefone) VALUES (?,?,?,?,?,?,?)");
        $st->execute([$dados['nome'], $foto_nome, $dados['data_nasc'] ?: null, $dados['endereco'], $dados['bairro'], $dados['cidade'], $dados['telefone']]);
        $novo_id = (int)db()->lastInsertId();

        foreach ($grupos_sel as $gid) {
            if ($gid) db()->prepare("INSERT IGNORE INTO membros_grupo_rel (grupo_id,membro_id) VALUES (?,?)")->execute([$gid, $novo_id]);
        }
        foreach ($cargos_sel as $cid) {
            if ($cid) db()->prepare("INSERT IGNORE INTO membros_cargo_rel (cargo_id,membro_id) VALUES (?,?)")->execute([$cid, $novo_id]);
        }
        foreach ($habilidades_sel as $hid) {
            if ($hid) db()->prepare("INSERT IGNORE INTO membros_habilidade_rel (habilidade_id,membro_id) VALUES (?,?)")->execute([$hid, $novo_id]);
        }
        $redir = $grupo_id ? "/portal/membros/?grupo={$grupo_id}"
               : ($cargo_id ? "/portal/membros/?cargo={$cargo_id}"
               : ($habilidade_id ? "/portal/membros/?habilidade={$habilidade_id}"
               : "/portal/membros/?ok=1"));
        header("Location: $redir");
        exit;
    }
}

include dirname(__DIR__) . '/_layout.php';
?>

<div style="margin-bottom:20px;display:flex;align-items:center;gap:10px">
  <a href="/portal/membros/<?= $grupo_id ? "?grupo={$grupo_id}" : '' ?>" class="btn btn-ghost btn-sm">← Voltar</a>
</div>

  <?php if ($erros): ?>
    <div class="alerta alerta-erro"><?= implode('<br>', array_map('htmlspecialchars', $erros)) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <div class="form-wrap">
      <h2>Novo Membro</h2>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <!-- Foto -->
      <div class="form-group">
        <label>Foto</label>
        <input type="hidden" name="foto_webcam" id="foto_webcam">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
          <div id="preview-wrap" style="width:90px;height:90px;border-radius:50%;border:2px dashed var(--border);overflow:hidden;background:var(--green-pale);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <span style="font-family:'Cinzel',serif;font-size:2rem;color:var(--green);opacity:.4" id="preview-inicial"><?= mb_strtoupper(mb_substr($dados['nome'],0,1)) ?: '?' ?></span>
            <img id="preview-img" src="" style="display:none;width:100%;height:100%;object-fit:cover">
          </div>
          <div style="display:flex;flex-direction:column;gap:6px">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <input type="file" name="foto" id="foto-input" accept="image/*" style="display:none" onchange="previewFoto(this)">
              <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('foto-input').click()">📁 Arquivo</button>
              <button type="button" class="btn btn-ghost btn-sm" onclick="abrirWebcam()">📷 Webcam</button>
            </div>
            <div class="form-hint">JPG, PNG ou WEBP · Máx. 5 MB</div>
          </div>
        </div>
      </div>

      <!-- Nome -->
      <div class="form-group">
        <label>Nome completo <span style="color:var(--red)">*</span></label>
        <input type="text" name="nome" value="<?= htmlspecialchars($dados['nome']) ?>" required maxlength="150" id="nome-input" oninput="atualizarInicial()">
      </div>

      <div class="form-row">
        <!-- Data de nascimento -->
        <div class="form-group">
          <label>Data de nascimento</label>
          <input type="date" name="data_nasc" value="<?= htmlspecialchars($dados['data_nasc']) ?>">
        </div>
        <!-- Telefone -->
        <div class="form-group">
          <label>Telefone / WhatsApp</label>
          <input type="tel" name="telefone" value="<?= htmlspecialchars($dados['telefone']) ?>" maxlength="30" placeholder="(00) 90000-0000">
        </div>
      </div>

      <!-- Endereço -->
      <div class="form-group">
        <label>Endereço</label>
        <input type="text" name="endereco" value="<?= htmlspecialchars($dados['endereco']) ?>" maxlength="255" placeholder="Rua, número…">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Bairro</label>
          <input type="text" name="bairro" value="<?= htmlspecialchars($dados['bairro']) ?>" maxlength="100">
        </div>
        <div class="form-group">
          <label>Cidade</label>
          <input type="text" name="cidade" value="<?= htmlspecialchars($dados['cidade']) ?>" maxlength="100" autocomplete="off" placeholder="Digite para buscar…" data-cidade-ac>
        </div>
      </div>

      <!-- Grupos -->
      <?php if ($grupos): ?>
      <div class="form-group">
        <label>Grupos</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0">
          <?php foreach ($grupos as $g): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)">
            <input type="checkbox" name="grupos[]" value="<?= $g['id'] ?>" <?= $g['id'] == $grupo_id ? 'checked' : '' ?>>
            <span style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($g['cor']) ?>;display:inline-block;flex-shrink:0"></span>
            <?= htmlspecialchars($g['nome']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Cargos -->
      <?php if ($cargos): ?>
      <div class="form-group">
        <label>Cargos</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0">
          <?php foreach ($cargos as $c): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)">
            <input type="checkbox" name="cargos[]" value="<?= $c['id'] ?>" <?= $c['id'] == $cargo_id ? 'checked' : '' ?>>
            <span style="width:10px;height:10px;border-radius:3px;background:<?= htmlspecialchars($c['cor']) ?>;display:inline-block;flex-shrink:0"></span>
            <?= htmlspecialchars($c['nome']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Habilidades -->
      <div class="form-group">
        <label style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px">
          Habilidades
          <button type="button" onclick="abrirModalHabilidade()" style="font-size:.72rem;padding:3px 10px;border:1px solid var(--green);border-radius:6px;background:none;color:var(--green);cursor:pointer;font-family:inherit;font-weight:600">+ Nova habilidade</button>
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0" id="habilidades-lista">
          <?php foreach ($habilidades as $h): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)">
            <input type="checkbox" name="habilidades[]" value="<?= $h['id'] ?>" <?= $h['id'] == $habilidade_id ? 'checked' : '' ?>>
            <span style="width:10px;height:10px;border-radius:2px;transform:rotate(45deg);background:<?= htmlspecialchars($h['cor']) ?>;display:inline-block;flex-shrink:0"></span>
            <?= htmlspecialchars($h['nome']) ?>
          </label>
          <?php endforeach; ?>
          <?php if (empty($habilidades)): ?>
            <span class="sem-hab" style="font-size:.78rem;color:var(--muted)">Nenhuma habilidade criada. <a href="/portal/membros/habilidades.php" target="_blank" style="color:var(--green)">Gerenciar →</a></span>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Salvar membro</button>
        <a href="/portal/membros/<?= $grupo_id ? "?grupo={$grupo_id}" : '' ?>" class="btn btn-ghost">Cancelar</a>
      </div>
    </div>
  </form>

<!-- Modal Nova Habilidade -->
<div id="modal-habilidade" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:900;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:14px;padding:24px;max-width:400px;width:100%;display:flex;flex-direction:column;gap:16px;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <h3 style="margin:0;font-size:1rem;font-family:'Cinzel',serif;color:var(--green-dk)">Nova habilidade</h3>
      <button type="button" onclick="fecharModalHabilidade()" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--muted);line-height:1">×</button>
    </div>
    <div>
      <label style="font-size:.8rem;font-weight:600;color:var(--txt);display:block;margin-bottom:5px">Nome <span style="color:var(--red)">*</span></label>
      <input type="text" id="nova-hab-nome" placeholder="Ex.: Musicalidade, Liderança…" maxlength="100"
        style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;font-family:inherit;outline:none;box-sizing:border-box"
        onkeydown="if(event.key==='Enter'){event.preventDefault();salvarNovaHabilidade(document.getElementById('btn-criar-hab'));}">
    </div>
    <div style="display:flex;align-items:center;gap:12px">
      <div>
        <label style="font-size:.8rem;font-weight:600;color:var(--txt);display:block;margin-bottom:5px">Cor</label>
        <input type="color" id="nova-hab-cor" value="#1a6b8a"
          style="width:44px;height:36px;padding:2px 4px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer">
      </div>
      <div style="flex:1">
        <label style="font-size:.8rem;font-weight:600;color:var(--txt);display:block;margin-bottom:5px">Descrição <span style="font-weight:400;color:var(--muted)">(opcional)</span></label>
        <input type="text" id="nova-hab-desc" placeholder="Observação breve…"
          style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;font-family:inherit;outline:none;box-sizing:border-box">
      </div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button type="button" class="btn btn-ghost btn-sm" onclick="fecharModalHabilidade()">Cancelar</button>
      <button type="button" id="btn-criar-hab" class="btn btn-primary btn-sm" onclick="salvarNovaHabilidade(this)">Criar habilidade</button>
    </div>
  </div>
</div>

<!-- Modal Webcam -->
<div id="webcam-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:14px;padding:24px;max-width:480px;width:90%;display:flex;flex-direction:column;gap:14px;box-shadow:0 8px 32px rgba(0,0,0,.3)">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <h3 style="margin:0;font-size:1rem;font-family:'Cinzel',serif;color:var(--green-dk)">Capturar foto</h3>
      <button type="button" onclick="fecharWebcam()" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--muted);line-height:1">×</button>
    </div>
    <video id="webcam-video" autoplay playsinline muted style="width:100%;border-radius:8px;background:#111;aspect-ratio:4/3;object-fit:cover"></video>
    <canvas id="webcam-canvas" style="display:none"></canvas>
    <div style="display:flex;gap:10px;justify-content:center">
      <button type="button" class="btn btn-primary" onclick="capturarFoto()">📸 Capturar</button>
      <button type="button" class="btn btn-ghost" onclick="fecharWebcam()">Cancelar</button>
    </div>
  </div>
</div>

<script>
(function(){
  var cache=null,KEY='naiot_cidades_v1';
  var RE=new RegExp('['+String.fromCharCode(768)+'-'+String.fromCharCode(879)+']','g');
  function norm(s){return s.toLowerCase().normalize('NFD').replace(RE,'');}
  function filtrar(l,q){var n=norm(q),r=l.filter(function(c){return norm(c.nome).indexOf(n)===0;});return r.length?r.slice(0,10):l.filter(function(c){return norm(c.nome).indexOf(n)!==-1;}).slice(0,10);}
  function init(inp){
    var w=inp.parentNode;w.style.position='relative';
    var box=document.createElement('ul');box.className='cidade-ac-box';w.appendChild(box);
    var tim,ok=false;
    function fecha(){box.innerHTML='';box.style.display='none';}
    function mostra(l){
      box.innerHTML='';if(!l.length){fecha();return;}
      l.forEach(function(c){
        var li=document.createElement('li');li.className='cidade-ac-item';
        li.innerHTML='<span class="cidade-ac-nome">'+c.nome+'</span><span class="cidade-ac-uf">'+c.uf+'</span>';
        li.addEventListener('mousedown',function(e){e.preventDefault();ok=true;inp.value=c.nome;fecha();inp.focus();ok=false;});
        box.appendChild(li);
      });
      box.style.display='block';
    }
    function busca(q){
      if(q.length<2){fecha();return;}
      if(cache){mostra(filtrar(cache,q));return;}
      fetch('/portal/membros/cidades_ibge.php').then(function(r){return r.json();}).then(function(d){
        cache=d;try{sessionStorage.setItem(KEY,JSON.stringify(d));}catch(_){}mostra(filtrar(d,q));
      }).catch(function(){});
    }
    inp.addEventListener('input',function(){clearTimeout(tim);var q=this.value.trim();tim=setTimeout(function(){busca(q);},220);});
    inp.addEventListener('focus',function(){if(this.value.trim().length>=2)busca(this.value.trim());});
    inp.addEventListener('blur',function(){if(!ok)setTimeout(fecha,160);});
    inp.addEventListener('keydown',function(e){
      var its=box.querySelectorAll('.cidade-ac-item'),at=box.querySelector('.cidade-ac-item.ativo'),ix=Array.prototype.indexOf.call(its,at);
      if(e.key==='ArrowDown'){e.preventDefault();if(at)at.classList.remove('ativo');var nx=its[ix+1]||its[0];if(nx)nx.classList.add('ativo');}
      else if(e.key==='ArrowUp'){e.preventDefault();if(at)at.classList.remove('ativo');var pv=its[ix-1]||its[its.length-1];if(pv)pv.classList.add('ativo');}
      else if(e.key==='Enter'&&at){e.preventDefault();inp.value=at.querySelector('.cidade-ac-nome').textContent;fecha();}
      else if(e.key==='Escape')fecha();
    });
  }
  document.addEventListener('DOMContentLoaded',function(){
    try{var s=sessionStorage.getItem(KEY);if(s)cache=JSON.parse(s);}catch(_){}
    document.querySelectorAll('[data-cidade-ac]').forEach(function(el){init(el);});
  });
})();

function previewFoto(input) {
  var file = input.files[0];
  if (!file) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('preview-img').src = e.target.result;
    document.getElementById('preview-img').style.display = 'block';
    document.getElementById('preview-inicial').style.display = 'none';
  };
  reader.readAsDataURL(file);
}
function atualizarInicial() {
  var nome = document.getElementById('nome-input').value.trim();
  var ini = nome ? nome.charAt(0).toUpperCase() : '?';
  document.getElementById('preview-inicial').textContent = ini;
}

var _webcamStream = null;
function abrirWebcam() {
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    alert('Seu navegador não suporta acesso à câmera.');
    return;
  }
  var modal = document.getElementById('webcam-modal');
  modal.style.display = 'flex';
  navigator.mediaDevices.getUserMedia({video: {facingMode: 'user', width: {ideal: 640}, height: {ideal: 480}}})
    .then(function(stream) {
      _webcamStream = stream;
      document.getElementById('webcam-video').srcObject = stream;
    })
    .catch(function(err) {
      alert('Não foi possível acessar a câmera: ' + (err.message || err));
      modal.style.display = 'none';
    });
}
function fecharWebcam() {
  if (_webcamStream) {
    _webcamStream.getTracks().forEach(function(t) { t.stop(); });
    _webcamStream = null;
  }
  document.getElementById('webcam-modal').style.display = 'none';
  document.getElementById('webcam-video').srcObject = null;
}
function abrirModalHabilidade() {
  var m = document.getElementById('modal-habilidade');
  m.style.display = 'flex';
  setTimeout(function(){ document.getElementById('nova-hab-nome').focus(); }, 50);
}
function fecharModalHabilidade() {
  document.getElementById('modal-habilidade').style.display = 'none';
  document.getElementById('nova-hab-nome').value = '';
  document.getElementById('nova-hab-desc').value = '';
  document.getElementById('nova-hab-cor').value = '#1a6b8a';
}
function salvarNovaHabilidade(btn) {
  var nome = document.getElementById('nova-hab-nome').value.trim();
  if (!nome) { document.getElementById('nova-hab-nome').focus(); return; }
  var cor  = document.getElementById('nova-hab-cor').value;
  var desc = document.getElementById('nova-hab-desc').value.trim();
  var csrf = document.querySelector('[name=csrf_token]').value;
  btn.disabled = true; btn.textContent = 'Criando…';
  fetch('/portal/membros/habilidades.php?ajax=1', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'csrf_token='+encodeURIComponent(csrf)+'&acao=criar&nome='+encodeURIComponent(nome)+'&cor='+encodeURIComponent(cor)+'&descricao='+encodeURIComponent(desc)
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    btn.disabled = false; btn.textContent = 'Criar habilidade';
    if (data.ok) {
      var lista = document.getElementById('habilidades-lista');
      var semHab = lista.querySelector('.sem-hab');
      if (semHab) semHab.remove();
      var lbl = document.createElement('label');
      lbl.style.cssText = 'display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)';
      var cb = document.createElement('input');
      cb.type = 'checkbox'; cb.name = 'habilidades[]'; cb.value = data.id; cb.checked = true;
      var dot = document.createElement('span');
      dot.style.cssText = 'width:10px;height:10px;border-radius:2px;transform:rotate(45deg);background:'+data.cor+';display:inline-block;flex-shrink:0';
      lbl.appendChild(cb);
      lbl.appendChild(dot);
      lbl.appendChild(document.createTextNode(data.nome));
      lista.appendChild(lbl);
      fecharModalHabilidade();
    } else {
      alert(data.erro || 'Erro ao criar habilidade.');
    }
  })
  .catch(function(){
    btn.disabled = false; btn.textContent = 'Criar habilidade';
    alert('Erro de conexão. Tente novamente.');
  });
}

function capturarFoto() {
  var video = document.getElementById('webcam-video');
  var canvas = document.getElementById('webcam-canvas');
  canvas.width = video.videoWidth || 640;
  canvas.height = video.videoHeight || 480;
  canvas.getContext('2d').drawImage(video, 0, 0);
  var dataUrl = canvas.toDataURL('image/jpeg', 0.92);
  document.getElementById('foto_webcam').value = dataUrl;
  document.getElementById('preview-img').src = dataUrl;
  document.getElementById('preview-img').style.display = 'block';
  document.getElementById('preview-inicial').style.display = 'none';
  fecharWebcam();
}
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
