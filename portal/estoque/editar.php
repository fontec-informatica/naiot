<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'estoque']);

$titulo       = 'Editar Produto';
$pagina_ativa = 'estoque';
$erro = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/estoque/'); exit; }

$stmt = db()->prepare('SELECT * FROM estoque_produtos WHERE id = ?');
$stmt->execute([$id]);
$produto = $stmt->fetch();
if (!$produto) { header('Location: /portal/estoque/'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $nome           = trim($_POST['nome'] ?? '');
        $descricao      = trim($_POST['descricao'] ?? '');
        $categoria_id   = (int)($_POST['categoria_id'] ?? 0) ?: null;
        $codigo_barras  = trim($_POST['codigo_barras'] ?? '');
        $unidade        = trim($_POST['unidade'] ?? '') ?: 'un';
        $preco_custo    = (float)str_replace(',', '.', preg_replace('/[^0-9,]/', '', $_POST['preco_custo'] ?? '0'));
        $preco_venda    = (float)str_replace(',', '.', preg_replace('/[^0-9,]/', '', $_POST['preco_venda'] ?? '0'));
        $estoque_minimo = (int)($_POST['estoque_minimo'] ?? 0);
        $ativo          = isset($_POST['ativo']) ? 1 : 0;

        $codigo_duplicado = false;
        if ($codigo_barras !== '') {
            $st = db()->prepare('SELECT COUNT(*) FROM estoque_produtos WHERE codigo_barras = ? AND id != ?');
            $st->execute([$codigo_barras, $id]);
            $codigo_duplicado = (int)$st->fetchColumn() > 0;
        }

        if (!$nome) {
            $erro = 'O nome do produto é obrigatório.';
        } elseif ($codigo_duplicado) {
            $erro = 'Já existe outro produto cadastrado com este código de barras.';
        } else {
            $nova_imagem = $produto['imagem'];

            if (!empty($_FILES['imagem']['tmp_name'])) {
                $finfo      = new finfo(FILEINFO_MIME_TYPE);
                $mime       = $finfo->file($_FILES['imagem']['tmp_name']);
                $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

                if (!isset($permitidos[$mime])) {
                    $erro = 'Formato não permitido. Use JPG, PNG, WebP ou GIF.';
                } elseif ($_FILES['imagem']['size'] > 8 * 1024 * 1024) {
                    $erro = 'Imagem muito grande. Máximo 8MB.';
                } else {
                    $ext         = $permitidos[$mime];
                    $nova_imagem = 'prod_' . uniqid() . '.' . $ext;
                    $destino     = dirname(__DIR__, 2) . '/assets/img/estoque/produtos/' . $nova_imagem;

                    if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
                        $erro        = 'Erro ao salvar a imagem no servidor.';
                        $nova_imagem = $produto['imagem'];
                    } elseif ($produto['imagem']) {
                        $antiga = dirname(__DIR__, 2) . '/assets/img/estoque/produtos/' . $produto['imagem'];
                        if (file_exists($antiga)) unlink($antiga);
                    }
                }
            }

            if (!$erro) {
                db()->prepare('UPDATE estoque_produtos SET
                    categoria_id=?, nome=?, descricao=?, codigo_barras=?, unidade=?,
                    preco_custo=?, preco_venda=?, estoque_minimo=?, imagem=?, ativo=?
                    WHERE id=?')
                    ->execute([
                        $categoria_id,
                        $nome,
                        $descricao ?: null,
                        $codigo_barras ?: null,
                        $unidade,
                        $preco_custo,
                        $preco_venda,
                        $estoque_minimo,
                        $nova_imagem,
                        $ativo,
                        $id,
                    ]);
                header('Location: /portal/estoque/?editado=1');
                exit;
            }
        }
    }
}

$categorias = db()->query("SELECT * FROM estoque_categorias WHERE ativo = 1 OR id = " . (int)($produto['categoria_id'] ?? 0) . " ORDER BY nome")->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<div class="form-wrap">
  <h2>Editar produto</h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <div class="form-group" style="background:var(--off);padding:12px 14px;border-radius:var(--r);display:flex;justify-content:space-between;align-items:center">
    <span><strong>Código interno:</strong> <?= htmlspecialchars($produto['codigo_interno']) ?></span>
    <span><strong>Estoque atual:</strong> <?= (int)$produto['estoque_atual'] ?> <?= htmlspecialchars($produto['unidade']) ?></span>
  </div>

  <form method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label for="nome">Nome do produto</label>
      <input type="text" id="nome" name="nome"
             value="<?= htmlspecialchars($_POST['nome'] ?? $produto['nome']) ?>" required>
    </div>

    <div class="form-group">
      <label for="descricao">Descrição <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="text" id="descricao" name="descricao"
             value="<?= htmlspecialchars($_POST['descricao'] ?? $produto['descricao'] ?? '') ?>">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="categoria_id">Categoria</label>
        <select id="categoria_id" name="categoria_id">
          <option value="">Sem categoria</option>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (int)($_POST['categoria_id'] ?? $produto['categoria_id']) === (int)$c['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="unidade">Unidade</label>
        <select id="unidade" name="unidade">
          <?php foreach (['un' => 'Unidade', 'kg' => 'Quilo', 'cx' => 'Caixa', 'pct' => 'Pacote', 'lt' => 'Litro'] as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= ($_POST['unidade'] ?? $produto['unidade']) === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="codigo_barras">Código de barras <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="text" id="codigo_barras" name="codigo_barras"
             value="<?= htmlspecialchars($_POST['codigo_barras'] ?? $produto['codigo_barras'] ?? '') ?>">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="preco_custo">Preço de custo (R$)</label>
        <input type="text" id="preco_custo" name="preco_custo"
               value="<?= htmlspecialchars($_POST['preco_custo'] ?? number_format($produto['preco_custo'], 2, ',', '')) ?>">
      </div>
      <div class="form-group">
        <label for="preco_venda">Preço de venda (R$)</label>
        <input type="text" id="preco_venda" name="preco_venda"
               value="<?= htmlspecialchars($_POST['preco_venda'] ?? number_format($produto['preco_venda'], 2, ',', '')) ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="estoque_minimo">Estoque mínimo</label>
      <input type="number" id="estoque_minimo" name="estoque_minimo" min="0"
             value="<?= htmlspecialchars($_POST['estoque_minimo'] ?? $produto['estoque_minimo']) ?>">
      <span class="form-hint">Alerta de estoque baixo abaixo deste valor.</span>
    </div>

    <?php if ($produto['imagem']): ?>
    <div class="form-group">
      <label>Foto atual</label>
      <img src="/assets/img/estoque/produtos/<?= htmlspecialchars($produto['imagem']) ?>"
           alt="" style="max-height:120px;border-radius:6px;display:block;margin-bottom:8px">
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label for="imagem"><?= $produto['imagem'] ? 'Substituir foto' : 'Foto do produto' ?> <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="file" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
      <span class="form-hint">JPG, PNG, WebP ou GIF — máximo 8 MB.</span>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="ativo" value="1"
               <?= (($_POST['ativo'] ?? $produto['ativo']) ? 'checked' : '') ?>>
        Produto ativo
      </label>
      <span class="form-hint">Produtos inativos não aparecem na busca do PDV nem na listagem padrão.</span>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/estoque/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
