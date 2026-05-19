<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Novo Evento';
$pagina_ativa = 'eventos';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $nome_ev    = trim($_POST['titulo'] ?? '');
        $descricao  = trim($_POST['descricao'] ?? '');
        $data_ev    = $_POST['data_evento'] ?? '';
        $ordem      = (int)($_POST['ordem'] ?? 0);

        if (!$nome_ev) {
            $erro = 'O título é obrigatório.';
        } elseif (empty($_FILES['imagem']['tmp_name'])) {
            $erro = 'Selecione uma imagem para o evento.';
        } else {
            $finfo     = new finfo(FILEINFO_MIME_TYPE);
            $mime      = $finfo->file($_FILES['imagem']['tmp_name']);
            $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

            if (!isset($permitidos[$mime])) {
                $erro = 'Formato não permitido. Use JPG, PNG, WebP ou GIF.';
            } elseif ($_FILES['imagem']['size'] > 8 * 1024 * 1024) {
                $erro = 'Imagem muito grande. Máximo 8MB.';
            } else {
                $ext      = $permitidos[$mime];
                $filename = 'evt_' . uniqid() . '.' . $ext;
                $destino  = dirname(__DIR__, 2) . '/assets/img/eventos/' . $filename;

                if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
                    $erro = 'Erro ao salvar a imagem no servidor.';
                } else {
                    db()->prepare('INSERT INTO eventos (titulo, descricao, data_evento, imagem, ordem) VALUES (?,?,?,?,?)')
                        ->execute([
                            $nome_ev,
                            $descricao ?: null,
                            $data_ev ?: null,
                            $filename,
                            $ordem,
                        ]);
                    header('Location: /portal/eventos/?criado=1');
                    exit;
                }
            }
        }
    }
}

include dirname(__DIR__) . '/_layout.php';
?>

<div class="form-wrap" style="max-width:640px">
  <h2>Novo evento</h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label for="titulo">Título do evento</label>
      <input type="text" id="titulo" name="titulo"
             value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label for="descricao">Descrição curta <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="text" id="descricao" name="descricao"
             value="<?= htmlspecialchars($_POST['descricao'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="data_evento">Data do evento <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="date" id="data_evento" name="data_evento"
             value="<?= htmlspecialchars($_POST['data_evento'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="ordem">Ordem de exibição</label>
      <input type="number" id="ordem" name="ordem" min="0"
             value="<?= htmlspecialchars($_POST['ordem'] ?? '0') ?>">
      <span class="form-hint">Menor número aparece primeiro no carrossel.</span>
    </div>

    <div class="form-group">
      <label for="imagem">Arte do evento</label>
      <input type="file" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif" required>
      <span class="form-hint">JPG, PNG, WebP ou GIF — máximo 8MB. Qualquer proporção é aceita.</span>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Publicar evento</button>
      <a href="/portal/eventos/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
