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
    'nome'      => $m['nome'],
    'telefone'  => $m['telefone'],
    'data_nasc' => $m['data_nasc'],
    'endereco'  => $m['endereco'],
    'bairro'    => $m['bairro'],
    'cidade'    => $m['cidade'],
];

$grupos = db()->query("SELECT * FROM membros_grupos ORDER BY nome")->fetchAll();
$cargos = db()->query("SELECT * FROM membros_cargos ORDER BY nome")->fetchAll();

$st_rel = db()->prepare("SELECT grupo_id FROM membros_grupo_rel WHERE membro_id=?");
$st_rel->execute([$id]);
$grupos_do_membro = array_column($st_rel->fetchAll(), 'grupo_id');

$st_rel2 = db()->prepare("SELECT cargo_id FROM membros_cargo_rel WHERE membro_id=?");
$st_rel2->execute([$id]);
$cargos_do_membro = array_column($st_rel2->fetchAll(), 'cargo_id');

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
    $dados['cidade']    = trim($_POST['cidade']    ?? '');
    $grupos_sel         = array_map('intval', (array)($_POST['grupos'] ?? []));
    $cargos_sel         = array_map('intval', (array)($_POST['cargos'] ?? []));

    if (!$dados['nome']) $erros[] = 'O nome é obrigatório.';

    $nova_foto = $m['foto'];
    if (!empty($_FILES['foto']['tmp_name'])) {
        $f   = $_FILES['foto'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
            $erros[] = 'Foto: somente JPG, PNG ou WEBP.';
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $erros[] = 'Foto: máximo 5 MB.';
        } else {
            $nova_foto = uniqid('mb_', true) . '.' . $ext;
        }
    }

    if (!$erros) {
        if ($nova_foto !== $m['foto'] && !empty($_FILES['foto']['tmp_name'])) {
            $dir_fotos = __DIR__ . '/fotos/';
            if (!is_dir($dir_fotos)) mkdir($dir_fotos, 0755, true);
            if ($m['foto']) @unlink($dir_fotos . $m['foto']);
            move_uploaded_file($_FILES['foto']['tmp_name'], $dir_fotos . $nova_foto);
        }
        db()->prepare("UPDATE membros SET nome=?,foto=?,data_nasc=?,endereco=?,bairro=?,cidade=?,telefone=? WHERE id=?")
           ->execute([$dados['nome'], $nova_foto, $dados['data_nasc'] ?: null, $dados['endereco'], $dados['bairro'], $dados['cidade'], $dados['telefone'], $id]);

        db()->prepare("DELETE FROM membros_grupo_rel WHERE membro_id=?")->execute([$id]);
        foreach ($grupos_sel as $gid) {
            if ($gid) db()->prepare("INSERT IGNORE INTO membros_grupo_rel (grupo_id,membro_id) VALUES (?,?)")->execute([$gid, $id]);
        }
        db()->prepare("DELETE FROM membros_cargo_rel WHERE membro_id=?")->execute([$id]);
        foreach ($cargos_sel as $cid) {
            if ($cid) db()->prepare("INSERT IGNORE INTO membros_cargo_rel (cargo_id,membro_id) VALUES (?,?)")->execute([$cid, $id]);
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
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
          <div style="width:90px;height:90px;border-radius:50%;border:2px solid var(--border);overflow:hidden;background:var(--green-pale);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?php if (!empty($m['foto'])): ?>
              <img id="preview-img" src="/portal/membros/fotos/<?= htmlspecialchars($m['foto']) ?>" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none';document.getElementById('preview-inicial').style.display='flex'">
            <?php else: ?>
              <span style="font-family:'Cinzel',serif;font-size:2rem;color:var(--green);opacity:.4" id="preview-inicial"><?= mb_strtoupper(mb_substr($m['nome'],0,1)) ?></span>
              <img id="preview-img" src="" style="display:none;width:100%;height:100%;object-fit:cover">
            <?php endif; ?>
          </div>
          <div>
            <input type="file" name="foto" id="foto-input" accept="image/*" style="display:none" onchange="previewFoto(this)">
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('foto-input').click()">Alterar foto</button>
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

      <div class="form-row">
        <div class="form-group">
          <label>Bairro</label>
          <input type="text" name="bairro" value="<?= htmlspecialchars($dados['bairro']) ?>" maxlength="100">
        </div>
        <div class="form-group">
          <label>Cidade</label>
          <input type="text" name="cidade" value="<?= htmlspecialchars($dados['cidade']) ?>" maxlength="100">
        </div>
      </div>

      <?php if ($grupos): ?>
      <div class="form-group">
        <label>Grupos</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0">
          <?php foreach ($grupos as $g): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)">
            <input type="checkbox" name="grupos[]" value="<?= $g['id'] ?>" <?= in_array($g['id'], $grupos_do_membro) ? 'checked' : '' ?>>
            <span style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($g['cor']) ?>;display:inline-block;flex-shrink:0"></span>
            <?= htmlspecialchars($g['nome']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($cargos): ?>
      <div class="form-group">
        <label>Cargos</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0">
          <?php foreach ($cargos as $c): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.83rem;font-weight:500;color:var(--txt)">
            <input type="checkbox" name="cargos[]" value="<?= $c['id'] ?>" <?= in_array($c['id'], $cargos_do_membro) ? 'checked' : '' ?>>
            <span style="width:10px;height:10px;border-radius:3px;background:<?= htmlspecialchars($c['cor']) ?>;display:inline-block;flex-shrink:0"></span>
            <?= htmlspecialchars($c['nome']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

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

<script>
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
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
