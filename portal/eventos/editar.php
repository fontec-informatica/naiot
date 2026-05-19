<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Editar Evento';
$pagina_ativa = 'eventos';
$erro = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/eventos/'); exit; }

$stmt = db()->prepare('SELECT * FROM eventos WHERE id = ?');
$stmt->execute([$id]);
$ev = $stmt->fetch();
if (!$ev) { header('Location: /portal/eventos/'); exit; }

// Exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'deletar' && csrf_valido()) {
    $img_path = dirname(__DIR__, 2) . '/assets/img/eventos/' . $ev['imagem'];
    if (file_exists($img_path)) unlink($img_path);
    db()->prepare('DELETE FROM eventos WHERE id = ?')->execute([$id]);
    header('Location: /portal/eventos/?deletado=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') !== 'deletar') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $nome_ev   = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $data_ev   = $_POST['data_evento'] ?? '';
        $ordem     = (int)($_POST['ordem'] ?? 0);
        $ativo     = isset($_POST['ativo']) ? 1 : 0;

        if (!$nome_ev) {
            $erro = 'O título é obrigatório.';
        } else {
            $novo_filename = $ev['imagem'];

            // Troca de imagem
            if (!empty($_FILES['imagem']['tmp_name'])) {
                $finfo     = new finfo(FILEINFO_MIME_TYPE);
                $mime      = $finfo->file($_FILES['imagem']['tmp_name']);
                $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

                if (!isset($permitidos[$mime])) {
                    $erro = 'Formato não permitido. Use JPG, PNG, WebP ou GIF.';
                } elseif ($_FILES['imagem']['size'] > 8 * 1024 * 1024) {
                    $erro = 'Imagem muito grande. Máximo 8MB.';
                } else {
                    $ext           = $permitidos[$mime];
                    $novo_filename = 'evt_' . uniqid() . '.' . $ext;
                    $destino       = dirname(__DIR__, 2) . '/assets/img/eventos/' . $novo_filename;

                    if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
                        $erro = 'Erro ao salvar a imagem no servidor.';
                        $novo_filename = $ev['imagem'];
                    } else {
                        // Remove imagem antiga
                        $antiga = dirname(__DIR__, 2) . '/assets/img/eventos/' . $ev['imagem'];
                        if (file_exists($antiga)) unlink($antiga);
                    }
                }
            }

            if (!$erro) {
                db()->prepare('UPDATE eventos SET titulo=?, descricao=?, data_evento=?, imagem=?, ordem=?, ativo=? WHERE id=?')
                    ->execute([
                        $nome_ev,
                        $descricao ?: null,
                        $data_ev ?: null,
                        $novo_filename,
                        $ordem,
                        $ativo,
                        $id,
                    ]);
                header('Location: /portal/eventos/?editado=1');
                exit;
            }
        }
    }
}

include dirname(__DIR__) . '/_layout.php';
?>

<div class="form-wrap" style="max-width:640px">
  <h2>Editar evento</h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label for="titulo">Título do evento</label>
      <input type="text" id="titulo" name="titulo"
             value="<?= htmlspecialchars($_POST['titulo'] ?? $ev['titulo']) ?>" required>
    </div>

    <div class="form-group">
      <label for="descricao">Descrição curta <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="text" id="descricao" name="descricao"
             value="<?= htmlspecialchars($_POST['descricao'] ?? $ev['descricao'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="data_evento">Data do evento <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="date" id="data_evento" name="data_evento"
             value="<?= htmlspecialchars($_POST['data_evento'] ?? $ev['data_evento'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="ordem">Ordem de exibição</label>
      <input type="number" id="ordem" name="ordem" min="0"
             value="<?= htmlspecialchars($_POST['ordem'] ?? $ev['ordem']) ?>">
    </div>

    <div class="form-group">
      <label>Arte atual</label>
      <img src="/assets/img/eventos/<?= htmlspecialchars($ev['imagem']) ?>"
           alt="" style="max-height:160px;border-radius:6px;margin-bottom:8px">
    </div>

    <div class="form-group">
      <label for="imagem">Substituir imagem <span style="font-weight:400;color:var(--cinza3)">(deixe vazio para manter)</span></label>
      <input type="file" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="ativo" value="1"
               <?= ($ev['ativo'] ? 'checked' : '') ?>>
        Evento ativo (visível no site)
      </label>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/eventos/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>

  <hr style="margin:28px 0;border:none;border-top:1px solid var(--cinza2)">

  <form method="post" onsubmit="return confirm('Tem certeza? Esta ação não pode ser desfeita.')">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="deletar">
    <button type="submit" class="btn btn-danger">Excluir evento</button>
  </form>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
