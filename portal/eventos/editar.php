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
        $nome_ev            = trim($_POST['titulo']          ?? '');
        $descricao          = trim($_POST['descricao']       ?? '');
        $data_ev            = $_POST['data_evento']          ?? '';
        $data_fim           = $_POST['data_fim']             ?? '';
        $local              = trim($_POST['local_evento']    ?? '');
        $horario            = trim($_POST['horario']         ?? '');
        $ordem              = (int)($_POST['ordem']          ?? 0);
        $ativo              = isset($_POST['ativo'])          ? 1 : 0;
        $inscricoes_abertas = isset($_POST['inscricoes_abertas']) ? 1 : 0;
        $vagas              = (int)($_POST['vagas']          ?? 0) ?: null;
        $valor              = (float)str_replace(',', '.', preg_replace('/[^0-9,]/', '', $_POST['valor'] ?? '0'));
        $data_encerramento  = $_POST['data_encerramento']    ?: null;

        if (!$nome_ev) {
            $erro = 'O título é obrigatório.';
        } else {
            $novo_filename = $ev['imagem'];

            if (!empty($_FILES['imagem']['tmp_name'])) {
                $finfo      = new finfo(FILEINFO_MIME_TYPE);
                $mime       = $finfo->file($_FILES['imagem']['tmp_name']);
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
                        $antiga = dirname(__DIR__, 2) . '/assets/img/eventos/' . $ev['imagem'];
                        if (file_exists($antiga)) unlink($antiga);
                    }
                }
            }

            if (!$erro) {
                db()->prepare('UPDATE eventos SET titulo=?, descricao=?, data_evento=?, data_fim=?, local_evento=?, horario=?, imagem=?, ordem=?, ativo=?, inscricoes_abertas=?, vagas=?, valor=?, data_encerramento=? WHERE id=?')
                    ->execute([
                        $nome_ev,
                        $descricao          ?: null,
                        $data_ev            ?: null,
                        $data_fim           ?: null,
                        $local              ?: null,
                        $horario            ?: null,
                        $novo_filename,
                        $ordem,
                        $ativo,
                        $inscricoes_abertas,
                        $vagas,
                        $valor,
                        $data_encerramento,
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

<div class="form-wrap">
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

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label for="data_evento">Data de início <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="date" id="data_evento" name="data_evento"
               value="<?= htmlspecialchars($_POST['data_evento'] ?? $ev['data_evento'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="data_fim">Data de término <span style="font-weight:400;color:var(--cinza3)">(vazio = 1 dia)</span></label>
        <input type="date" id="data_fim" name="data_fim"
               value="<?= htmlspecialchars($_POST['data_fim'] ?? $ev['data_fim'] ?? '') ?>">
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label for="local_evento">Local <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="text" id="local_evento" name="local_evento" placeholder="Ex: Igreja Matriz"
               value="<?= htmlspecialchars($_POST['local_evento'] ?? $ev['local_evento'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="horario">Horário <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="text" id="horario" name="horario" placeholder="Ex: 19h às 22h"
               value="<?= htmlspecialchars($_POST['horario'] ?? $ev['horario'] ?? '') ?>">
      </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--cinza2);margin:8px 0 16px">
    <p style="font-size:.82rem;color:var(--cinza3);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Inscrições</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label for="vagas">Vagas totais <span style="font-weight:400;color:var(--cinza3)">(vazio = ilimitado)</span></label>
        <input type="number" id="vagas" name="vagas" min="1"
               value="<?= htmlspecialchars($_POST['vagas'] ?? $ev['vagas'] ?? '') ?>" placeholder="Ilimitado">
      </div>
      <div class="form-group">
        <label for="valor">Valor base (R$) <span style="font-weight:400;color:var(--cinza3)">(0 = gratuito)</span></label>
        <input type="text" id="valor" name="valor"
               value="<?= htmlspecialchars($_POST['valor'] ?? number_format($ev['valor'] ?? 0, 2, ',', '')) ?>" placeholder="0,00">
        <span class="form-hint">Use lotes para múltiplos preços.</span>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label for="data_encerramento">Encerrar inscrições em <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="date" id="data_encerramento" name="data_encerramento"
               value="<?= htmlspecialchars($_POST['data_encerramento'] ?? $ev['data_encerramento'] ?? '') ?>">
      </div>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="inscricoes_abertas" value="1"
               <?= (($_POST['inscricoes_abertas'] ?? $ev['inscricoes_abertas'] ?? 0) ? 'checked' : '') ?>>
        <span><strong>Inscrições abertas</strong> — exibe botão "Inscrever-se" no site</span>
      </label>
    </div>

    <hr style="border:none;border-top:1px solid var(--cinza2);margin:8px 0 16px">
    <p style="font-size:.82rem;color:var(--cinza3);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Arte e carrossel</p>

    <div class="form-group">
      <label for="ordem">Ordem de exibição no carrossel</label>
      <input type="number" id="ordem" name="ordem" min="0"
             value="<?= htmlspecialchars($_POST['ordem'] ?? $ev['ordem']) ?>">
      <span class="form-hint">Menor número aparece primeiro.</span>
    </div>

    <div class="form-group">
      <label>Arte atual</label>
      <img src="/assets/img/eventos/<?= htmlspecialchars($ev['imagem']) ?>"
           alt="" style="max-height:160px;border-radius:6px;display:block;margin-bottom:8px">
    </div>

    <div class="form-group">
      <label for="imagem">Substituir imagem <span style="font-weight:400;color:var(--cinza3)">(deixe vazio para manter)</span></label>
      <input type="file" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
      <span class="form-hint">JPG, PNG, WebP ou GIF — máximo 8 MB.</span>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="ativo" value="1"
               <?= (($_POST['ativo'] ?? $ev['ativo']) ? 'checked' : '') ?>>
        Exibir no carrossel do site (Próximos Eventos)
      </label>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/eventos/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>

  <hr style="margin:28px 0;border:none;border-top:1px solid var(--cinza2)">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
    <div>
      <strong style="font-size:.95rem">Lotes de inscrição</strong>
      <p style="color:var(--cinza3);font-size:.85rem;margin-top:3px">Defina múltiplos preços ou categorias (Adulto, Criança, 1º Lote…)</p>
    </div>
    <a href="/portal/eventos/lotes.php?id=<?= $id ?>" class="btn btn-ouro btn-sm">Gerenciar lotes</a>
  </div>

  <hr style="margin:28px 0;border:none;border-top:1px solid var(--cinza2)">

  <form method="post" onsubmit="return confirm('Tem certeza? Esta ação não pode ser desfeita.')">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="deletar">
    <button type="submit" class="btn btn-danger">Excluir evento</button>
  </form>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
