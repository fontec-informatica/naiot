<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Editar Membro';
$pagina_ativa = 'membros';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/membros/'); exit; }

$membro = db()->prepare("SELECT * FROM membros WHERE id=?");
$membro->execute([$id]);
$m = $membro->fetch();
if (!$m) { header('Location: /portal/membros/'); exit; }

$erros = [];
$dados = [
    'nome'        => $m['nome'],
    'telefone'    => $m['telefone'],
    'data_nasc'   => $m['data_nasc'],
    'estado_civil'=> $m['estado_civil'] ?? '',
    'sexo'        => $m['sexo']         ?? '',
    'endereco'    => $m['endereco'],
    'bairro'      => $m['bairro'],
    'cidade'      => $m['cidade'],
];

$grupos      = db()->query("SELECT * FROM membros_grupos    ORDER BY nome")->fetchAll();
$cargos      = db()->query("SELECT * FROM membros_cargos    ORDER BY nome")->fetchAll();
$habilidades = db()->query("SELECT * FROM membros_habilidades ORDER BY nome")->fetchAll();
$pastoreios  = db()->query("SELECT * FROM membros_pastoreio  ORDER BY nome")->fetchAll();

$st_rel = db()->prepare("SELECT grupo_id FROM membros_grupo_rel WHERE membro_id=?");
$st_rel->execute([$id]);
$grupos_do_membro = array_column($st_rel->fetchAll(), 'grupo_id');

$st_rel2 = db()->prepare("SELECT cargo_id FROM membros_cargo_rel WHERE membro_id=?");
$st_rel2->execute([$id]);
$cargos_do_membro = array_column($st_rel2->fetchAll(), 'cargo_id');

$st_rel3 = db()->prepare("SELECT habilidade_id FROM membros_habilidade_rel WHERE membro_id=?");
$st_rel3->execute([$id]);
$habilidades_do_membro = array_column($st_rel3->fetchAll(), 'habilidade_id');

$st_rel4 = db()->prepare("SELECT pastoreio_id FROM membros_pastoreio_rel WHERE membro_id=?");
$st_rel4->execute([$id]);
$pastoreios_do_membro = array_column($st_rel4->fetchAll(), 'pastoreio_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? 'salvar';

    if ($acao === 'excluir') {
        if ($m['foto'] && file_exists(__DIR__ . '/fotos/' . $m['foto'])) {
            @unlink(__DIR__ . '/fotos/' . $m['foto']);
        }
        db()->prepare("DELETE FROM membros WHERE id=?")->execute([$id]);
        header('Location: /portal/membros/?excluido=1');
        exit;
    }

    $dados['nome']      = trim($_POST['nome']      ?? '');
    $dados['telefone']  = trim($_POST['telefone']  ?? '');
    $dados['data_nasc'] = trim($_POST['data_nasc'] ?? '');
    $dados['endereco']  = trim($_POST['endereco']  ?? '');
    $dados['bairro']    = trim($_POST['bairro']    ?? '');
    $dados['cidade']       = trim($_POST['cidade']       ?? '');
    $dados['estado_civil'] = trim($_POST['estado_civil'] ?? '');
    $dados['sexo']         = trim($_POST['sexo']         ?? '');
    $grupos_sel      = array_map('intval', (array)($_POST['grupos']      ?? []));
    $cargos_sel      = array_map('intval', (array)($_POST['cargos']      ?? []));
    $habilidades_sel = array_map('intval', (array)($_POST['habilidades'] ?? []));
    $pastoreios_sel  = array_map('intval', (array)($_POST['pastoreios']  ?? []));

    if (!$dados['nome']) $erros[] = 'O nome é obrigatório.';

    $nova_foto = $m['foto'];
    $foto_b64 = trim($_POST['foto_webcam'] ?? '');
    if ($foto_b64 && preg_match('/^data:image\/(jpeg|png|webp);base64,/', $foto_b64, $m_ext)) {
        $img_data = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $foto_b64), true);
        $finfo_b64 = new finfo(FILEINFO_MIME_TYPE);
        $mime_b64  = $finfo_b64->buffer($img_data ?: '');
        $mimes_ok  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!$img_data || !isset($mimes_ok[$mime_b64])) {
            $erros[] = 'Foto inválida.';
        } else {
            $ext       = $mimes_ok[$mime_b64];
            $nova_foto = uniqid('mb_', true) . '.' . $ext;
            $dir_fotos = __DIR__ . '/fotos/';
            if (!is_dir($dir_fotos)) mkdir($dir_fotos, 0755, true);
            if ($m['foto'] && file_exists($dir_fotos . $m['foto'])) @unlink($dir_fotos . $m['foto']);
            file_put_contents($dir_fotos . $nova_foto, $img_data);
        }
    } elseif (!empty($_FILES['foto']['tmp_name'])) {
        $f = $_FILES['foto'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $erros[] = 'Erro ao receber a foto.';
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $erros[] = 'Foto: máximo 5 MB.';
        } else {
            $finfo_up = new finfo(FILEINFO_MIME_TYPE);
            $mime_up  = $finfo_up->file($f['tmp_name']);
            $mimes_ok = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($mimes_ok[$mime_up])) {
                $erros[] = 'Foto: somente JPG, PNG ou WEBP.';
            } else {
                $nova_foto = uniqid('mb_', true) . '.' . $mimes_ok[$mime_up];
            }
        }
    }

    if (!$erros) {
        if ($nova_foto !== $m['foto'] && !empty($_FILES['foto']['tmp_name']) && empty($foto_b64)) {
            $dir_fotos = __DIR__ . '/fotos/';
            if (!is_dir($dir_fotos)) mkdir($dir_fotos, 0755, true);
            if ($m['foto']) @unlink($dir_fotos . $m['foto']);
            move_uploaded_file($_FILES['foto']['tmp_name'], $dir_fotos . $nova_foto);
        }
        db()->prepare("UPDATE membros SET nome=?,foto=?,data_nasc=?,endereco=?,bairro=?,cidade=?,telefone=?,estado_civil=?,sexo=? WHERE id=?")
           ->execute([$dados['nome'], $nova_foto, $dados['data_nasc'] ?: null, $dados['endereco'], $dados['bairro'], $dados['cidade'], $dados['telefone'], $dados['estado_civil'] ?: null, $dados['sexo'] ?: null, $id]);

        db()->prepare("DELETE FROM membros_grupo_rel WHERE membro_id=?")->execute([$id]);
        foreach ($grupos_sel as $gid) {
            if ($gid) db()->prepare("INSERT IGNORE INTO membros_grupo_rel (grupo_id,membro_id) VALUES (?,?)")->execute([$gid, $id]);
        }
        db()->prepare("DELETE FROM membros_cargo_rel WHERE membro_id=?")->execute([$id]);
        foreach ($cargos_sel as $cid) {
            if ($cid) db()->prepare("INSERT IGNORE INTO membros_cargo_rel (cargo_id,membro_id) VALUES (?,?)")->execute([$cid, $id]);
        }
        db()->prepare("DELETE FROM membros_habilidade_rel WHERE membro_id=?")->execute([$id]);
        foreach ($habilidades_sel as $hid) {
            if ($hid) db()->prepare("INSERT IGNORE INTO membros_habilidade_rel (habilidade_id,membro_id) VALUES (?,?)")->execute([$hid, $id]);
        }
        db()->prepare("DELETE FROM membros_pastoreio_rel WHERE membro_id=?")->execute([$id]);
        foreach ($pastoreios_sel as $pid) {
            if ($pid) db()->prepare("INSERT IGNORE INTO membros_pastoreio_rel (pastoreio_id,membro_id) VALUES (?,?)")->execute([$pid, $id]);
        }

        header("Location: /portal/membros/ver.php?id={$id}&ok=1");
        exit;
    }
}

include dirname(__DIR__) . '/_layout.php';
?>

<div style="margin-bottom:20px;display:flex;align-items:center;gap:10px">
  <a href="/portal/membros/ver.php?id=<?= $id ?>" class="btn btn-ghost btn-sm">← Voltar</a>
</div>

<?php if ($erros): ?>
<div class="alerta alerta-erro"><?= implode('<br>', array_map('htmlspecialchars', $erros)) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <div class="form-wrap">
      <h2>Editar — <?= htmlspecialchars($m['nome']) ?></h2>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="salvar">

      <!-- Foto -->
      <div class="form-group">
        <label>Foto</label>
        <input type="hidden" name="foto_webcam" id="foto_webcam">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
          <div id="preview-wrap" style="width:90px;height:90px;border-radius:50%;border:2px solid var(--border);overflow:hidden;background:var(--green-pale);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?php if (!empty($m['foto'])): ?>
              <img id="preview-img" src="/portal/membros/fotos/<?= htmlspecialchars($m['foto']) ?>" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none';document.getElementById('preview-inicial').style.display='flex'">
            <?php else: ?>
              <span style="font-family:'Cinzel',serif;font-size:2rem;color:var(--green);opacity:.4" id="preview-inicial"><?= mb_strtoupper(mb_substr($m['nome'],0,1)) ?></span>
              <img id="preview-img" src="" style="display:none;width:100%;height:100%;object-fit:cover">
            <?php endif; ?>
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

      <div class="form-group">
        <label>Nome completo <span style="color:var(--red)">*</span></label>
        <input type="text" name="nome" value="<?= htmlspecialchars($dados['nome']) ?>" required maxlength="150">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Data de nascimento</label>
          <input type="date" name="data_nasc" value="<?= htmlspecialchars($dados['data_nasc'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Telefone / WhatsApp</label>
          <input type="tel" name="telefone" value="<?= htmlspecialchars($dados['telefone']) ?>" maxlength="30" placeholder="(00) 90000-0000">
        </div>
      </div>

      <div class="form-group">
        <label>Endereço</label>
        <input type="text" name="endereco" value="<?= htmlspecialchars($dados['endereco']) ?>" maxlength="255" placeholder="Rua, número…">
      </div>

      <div class="form-group">
        <label>Sexo</label>
        <div style="display:flex;gap:24px;padding:6px 0">
          <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:.9rem;font-weight:500">
            <input type="radio" name="sexo" value="Masculino" <?= $dados['sexo']==='Masculino' ? 'checked' : '' ?>>
            Masculino
          </label>
          <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:.9rem;font-weight:500">
            <input type="radio" name="sexo" value="Feminino" <?= $dados['sexo']==='Feminino' ? 'checked' : '' ?>>
            Feminino
          </label>
        </div>
      </div>

      <div class="form-group">
        <label>Estado civil</label>
        <select name="estado_civil">
          <option value="">— Não informado —</option>
          <option value="Solteiro(a)"               <?= ($dados['estado_civil']==='Solteiro(a)')               ? 'selected' : '' ?>>Solteiro(a)</option>
          <option value="Casado(a)"                 <?= ($dados['estado_civil']==='Casado(a)')                 ? 'selected' : '' ?>>Casado(a)</option>
          <option value="Amasiado(a)"               <?= ($dados['estado_civil']==='Amasiado(a)')               ? 'selected' : '' ?>>Amasiado(a)</option>
          <option value="Separado(a) judicialmente" <?= ($dados['estado_civil']==='Separado(a) judicialmente') ? 'selected' : '' ?>>Separado(a) judicialmente</option>
          <option value="Divorciado(a)"             <?= ($dados['estado_civil']==='Divorciado(a)')             ? 'selected' : '' ?>>Divorciado(a)</option>
          <option value="Viúvo(a)"                  <?= ($dados['estado_civil']==='Viúvo(a)')                  ? 'selected' : '' ?>>Viúvo(a)</option>
        </select>
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
      <div class="form-group">
        <label style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px">
          Grupos
          <button type="button" onclick="abrirModalItem('grupo')" style="font-size:.72rem;padding:3px 10px;border:1px solid var(--green);border-radius:6px;background:none;color:var(--green);cursor:pointer;font-family:inherit;font-weight:600">+ Novo grupo</button>
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0" id="grupos-lista">
          <?php foreach ($grupos as $g): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)">
            <input type="checkbox" name="grupos[]" value="<?= $g['id'] ?>" <?= in_array($g['id'], $grupos_do_membro) ? 'checked' : '' ?>>
            <span style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($g['cor']) ?>;display:inline-block;flex-shrink:0"></span>
            <?= htmlspecialchars($g['nome']) ?>
          </label>
          <?php endforeach; ?>
          <?php if (empty($grupos)): ?>
            <span class="sem-item" style="font-size:.78rem;color:var(--muted)">Nenhum grupo criado.</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Cargos -->
      <div class="form-group">
        <label style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px">
          Cargos
          <button type="button" onclick="abrirModalItem('cargo')" style="font-size:.72rem;padding:3px 10px;border:1px solid var(--green);border-radius:6px;background:none;color:var(--green);cursor:pointer;font-family:inherit;font-weight:600">+ Novo cargo</button>
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0" id="cargos-lista">
          <?php foreach ($cargos as $c): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)">
            <input type="checkbox" name="cargos[]" value="<?= $c['id'] ?>" <?= in_array($c['id'], $cargos_do_membro) ? 'checked' : '' ?>>
            <span style="width:10px;height:10px;border-radius:3px;background:<?= htmlspecialchars($c['cor']) ?>;display:inline-block;flex-shrink:0"></span>
            <?= htmlspecialchars($c['nome']) ?>
          </label>
          <?php endforeach; ?>
          <?php if (empty($cargos)): ?>
            <span class="sem-item" style="font-size:.78rem;color:var(--muted)">Nenhum cargo criado.</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Habilidades -->
      <div class="form-group">
        <label style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px">
          Habilidades
          <button type="button" onclick="abrirModalItem('habilidade')" style="font-size:.72rem;padding:3px 10px;border:1px solid var(--green);border-radius:6px;background:none;color:var(--green);cursor:pointer;font-family:inherit;font-weight:600">+ Nova habilidade</button>
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0" id="habilidades-lista">
          <?php foreach ($habilidades as $h): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)">
            <input type="checkbox" name="habilidades[]" value="<?= $h['id'] ?>" <?= in_array($h['id'], $habilidades_do_membro) ? 'checked' : '' ?>>
            <span style="width:10px;height:10px;border-radius:2px;transform:rotate(45deg);background:<?= htmlspecialchars($h['cor']) ?>;display:inline-block;flex-shrink:0"></span>
            <?= htmlspecialchars($h['nome']) ?>
          </label>
          <?php endforeach; ?>
          <?php if (empty($habilidades)): ?>
            <span class="sem-item" style="font-size:.78rem;color:var(--muted)">Nenhuma habilidade criada.</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Pastoreio -->
      <div class="form-group">
        <label style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px">
          Pastoreio
          <button type="button" onclick="abrirModalItem('pastoreio')" style="font-size:.72rem;padding:3px 10px;border:1px solid var(--green);border-radius:6px;background:none;color:var(--green);cursor:pointer;font-family:inherit;font-weight:600">+ Novo pastoreio</button>
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0" id="pastoreio-lista">
          <?php foreach ($pastoreios as $p): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)">
            <input type="checkbox" name="pastoreios[]" value="<?= $p['id'] ?>" <?= in_array($p['id'], $pastoreios_do_membro) ? 'checked' : '' ?>>
            <span style="width:10px;height:10px;border-radius:50%;border:2px solid <?= htmlspecialchars($p['cor']) ?>;background:<?= htmlspecialchars($p['cor']) ?>22;display:inline-block;flex-shrink:0;box-sizing:border-box"></span>
            <?= htmlspecialchars($p['nome']) ?>
          </label>
          <?php endforeach; ?>
          <?php if (empty($pastoreios)): ?>
            <span class="sem-item" style="font-size:.78rem;color:var(--muted)">Nenhum pastoreio criado.</span>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:8px">
        <div style="display:flex;gap:10px">
          <button type="submit" class="btn btn-primary">Salvar alterações</button>
          <a href="/portal/membros/ver.php?id=<?= $id ?>" class="btn btn-ghost">Cancelar</a>
        </div>
        <button type="button" class="btn btn-danger btn-sm"
          onclick="if(confirm('Excluir <?= htmlspecialchars(addslashes($m['nome'])) ?> definitivamente?')){document.getElementById('form-excluir').submit()}">
          Excluir membro
        </button>
      </div>
    </div>
  </form>

  <form id="form-excluir" method="post" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="excluir">
  </form>

<?php
$modais_inline = [
  'grupo'      => ['titulo'=>'Novo grupo',      'placeholder'=>'Ex.: Grupo do Caminho…', 'cor'=>'#1e6b35', 'btn'=>'Criar grupo'],
  'cargo'      => ['titulo'=>'Novo cargo',      'placeholder'=>'Ex.: Líder, Diácono…',   'cor'=>'#a87d28', 'btn'=>'Criar cargo'],
  'habilidade' => ['titulo'=>'Nova habilidade', 'placeholder'=>'Ex.: Musicalidade…',     'cor'=>'#1a6b8a', 'btn'=>'Criar habilidade'],
  'pastoreio'  => ['titulo'=>'Novo pastoreio',  'placeholder'=>'Ex.: Célula A…',         'cor'=>'#8b44a8', 'btn'=>'Criar pastoreio'],
];
foreach ($modais_inline as $tipo => $cfg): ?>
<div id="modal-<?= $tipo ?>" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:900;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)fecharModalItem('<?= $tipo ?>')">
  <div style="background:#fff;border-radius:14px;padding:24px;max-width:400px;width:100%;display:flex;flex-direction:column;gap:16px;box-shadow:0 8px 32px rgba(0,0,0,.25)">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <h3 style="margin:0;font-size:1rem;font-family:'Cinzel',serif;color:var(--green-dk)"><?= $cfg['titulo'] ?></h3>
      <button type="button" onclick="fecharModalItem('<?= $tipo ?>')" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--muted);line-height:1">×</button>
    </div>
    <div>
      <label style="font-size:.8rem;font-weight:600;color:var(--txt);display:block;margin-bottom:5px">Nome <span style="color:var(--red)">*</span></label>
      <input type="text" id="novo-<?= $tipo ?>-nome" placeholder="<?= $cfg['placeholder'] ?>" maxlength="100"
        style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;font-family:inherit;outline:none;box-sizing:border-box"
        onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('btn-criar-<?= $tipo ?>').click();}">
    </div>
    <div style="display:flex;align-items:center;gap:12px">
      <div>
        <label style="font-size:.8rem;font-weight:600;color:var(--txt);display:block;margin-bottom:5px">Cor</label>
        <input type="color" id="novo-<?= $tipo ?>-cor" value="<?= $cfg['cor'] ?>"
          style="width:44px;height:36px;padding:2px 4px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer">
      </div>
      <div style="flex:1">
        <label style="font-size:.8rem;font-weight:600;color:var(--txt);display:block;margin-bottom:5px">Descrição <span style="font-weight:400;color:var(--muted)">(opcional)</span></label>
        <input type="text" id="novo-<?= $tipo ?>-desc" placeholder="Observação breve…"
          style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;font-family:inherit;outline:none;box-sizing:border-box">
      </div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button type="button" class="btn btn-ghost btn-sm" onclick="fecharModalItem('<?= $tipo ?>')">Cancelar</button>
      <button type="button" id="btn-criar-<?= $tipo ?>" class="btn btn-primary btn-sm" onclick="salvarItemInline('<?= $tipo ?>',this)"><?= $cfg['btn'] ?></button>
    </div>
  </div>
</div>
<?php endforeach; ?>

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

var _modalCfg = {
  grupo:      {endpoint:'/portal/membros/grupos.php?ajax=1',      lista:'grupos-lista',     arr:'grupos[]',      defaultCor:'#1e6b35', dot:'width:10px;height:10px;border-radius:50%;background:{cor};display:inline-block;flex-shrink:0'},
  cargo:      {endpoint:'/portal/membros/cargos.php?ajax=1',      lista:'cargos-lista',     arr:'cargos[]',      defaultCor:'#a87d28', dot:'width:10px;height:10px;border-radius:3px;background:{cor};display:inline-block;flex-shrink:0'},
  habilidade: {endpoint:'/portal/membros/habilidades.php?ajax=1', lista:'habilidades-lista',arr:'habilidades[]', defaultCor:'#1a6b8a', dot:'width:10px;height:10px;border-radius:2px;transform:rotate(45deg);background:{cor};display:inline-block;flex-shrink:0'},
  pastoreio:  {endpoint:'/portal/membros/pastoreio.php?ajax=1',   lista:'pastoreio-lista',  arr:'pastoreios[]',  defaultCor:'#8b44a8', dot:'width:10px;height:10px;border-radius:50%;border:2px solid {cor};background:{cor}22;display:inline-block;flex-shrink:0;box-sizing:border-box'}
};
function abrirModalItem(tipo) {
  document.getElementById('modal-'+tipo).style.display='flex';
  setTimeout(function(){document.getElementById('novo-'+tipo+'-nome').focus();},50);
}
function fecharModalItem(tipo) {
  var c=_modalCfg[tipo];
  document.getElementById('modal-'+tipo).style.display='none';
  document.getElementById('novo-'+tipo+'-nome').value='';
  document.getElementById('novo-'+tipo+'-desc').value='';
  document.getElementById('novo-'+tipo+'-cor').value=c.defaultCor;
}
function salvarItemInline(tipo,btn) {
  var c=_modalCfg[tipo];
  var nome=document.getElementById('novo-'+tipo+'-nome').value.trim();
  if(!nome){document.getElementById('novo-'+tipo+'-nome').focus();return;}
  var cor=document.getElementById('novo-'+tipo+'-cor').value;
  var desc=document.getElementById('novo-'+tipo+'-desc').value.trim();
  var csrf=document.querySelector('[name=csrf_token]').value;
  var origTxt=btn.textContent; btn.disabled=true; btn.textContent='Criando…';
  fetch(c.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'csrf_token='+encodeURIComponent(csrf)+'&acao=criar&nome='+encodeURIComponent(nome)+'&cor='+encodeURIComponent(cor)+'&descricao='+encodeURIComponent(desc)})
  .then(function(r){return r.json();})
  .then(function(d){
    btn.disabled=false; btn.textContent=origTxt;
    if(d.ok){
      var lista=document.getElementById(c.lista);
      var sem=lista.querySelector('.sem-item'); if(sem) sem.remove();
      var lbl=document.createElement('label');
      lbl.style.cssText='display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)';
      var cb=document.createElement('input'); cb.type='checkbox'; cb.name=c.arr; cb.value=d.id; cb.checked=true;
      var dot=document.createElement('span'); dot.style.cssText=c.dot.replace(/\{cor\}/g,d.cor);
      lbl.appendChild(cb); lbl.appendChild(dot); lbl.appendChild(document.createTextNode(d.nome));
      lista.appendChild(lbl);
      fecharModalItem(tipo);
    } else { alert(d.erro||'Erro ao criar.'); }
  })
  .catch(function(){btn.disabled=false;btn.textContent=origTxt;alert('Erro de conexão.');});
}

function previewFoto(input) {
  var file = input.files[0];
  if (!file) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('preview-img').src = e.target.result;
    document.getElementById('preview-img').style.display = 'block';
    var ini = document.getElementById('preview-inicial');
    if (ini) ini.style.display = 'none';
  };
  reader.readAsDataURL(file);
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
  var ini = document.getElementById('preview-inicial');
  if (ini) ini.style.display = 'none';
  fecharWebcam();
}
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
