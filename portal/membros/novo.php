<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Novo Membro';
$pagina_ativa = 'membros';

$grupo_id = (int)($_GET['grupo'] ?? 0);
$cargo_id = (int)($_GET['cargo'] ?? 0);
$erros    = [];
$dados    = ['nome'=>'','telefone'=>'','data_nasc'=>'','endereco'=>'','bairro'=>'','cidade'=>''];

$grupos = db()->query("SELECT * FROM membros_grupos ORDER BY nome")->fetchAll();
$cargos = db()->query("SELECT * FROM membros_cargos ORDER BY nome")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $dados['nome']      = trim($_POST['nome']      ?? '');
    $dados['telefone']  = trim($_POST['telefone']  ?? '');
    $dados['data_nasc'] = trim($_POST['data_nasc'] ?? '');
    $dados['endereco']  = trim($_POST['endereco']  ?? '');
    $dados['bairro']    = trim($_POST['bairro']    ?? '');
    $dados['cidade']    = trim($_POST['cidade']    ?? '');
    $grupos_sel         = array_map('intval', (array)($_POST['grupos'] ?? []));
    $cargos_sel         = array_map('intval', (array)($_POST['cargos'] ?? []));

    if (!$dados['nome']) $erros[] = 'O nome é obrigatório.';

    $foto_nome = null;
    if (!empty($_FILES['foto']['tmp_name'])) {
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
        if ($foto_nome) {
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
        $redir = $grupo_id ? "/portal/membros/?grupo={$grupo_id}" : ($cargo_id ? "/portal/membros/?cargo={$cargo_id}" : "/portal/membros/?ok=1");
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
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
          <div id="preview-wrap" style="width:90px;height:90px;border-radius:50%;border:2px dashed var(--border);overflow:hidden;background:var(--green-pale);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <span style="font-family:'Cinzel',serif;font-size:2rem;color:var(--green);opacity:.4" id="preview-inicial"><?= mb_strtoupper(mb_substr($dados['nome'],0,1)) ?: '?' ?></span>
            <img id="preview-img" src="" style="display:none;width:100%;height:100%;object-fit:cover">
          </div>
          <div>
            <input type="file" name="foto" id="foto-input" accept="image/*" style="display:none" onchange="previewFoto(this)">
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('foto-input').click()">Escolher foto</button>
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

      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="submit" class="btn btn-primary">Salvar membro</button>
        <a href="/portal/membros/<?= $grupo_id ? "?grupo={$grupo_id}" : '' ?>" class="btn btn-ghost">Cancelar</a>
      </div>
    </div>
  </form>

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
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
