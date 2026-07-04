<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'estoque']);

$titulo       = 'Livraria — Novo Produto';
$pagina_ativa = 'estoque';
$loja_secao   = 'produtos';
$erro = '';

$config = db()->query('SELECT * FROM estoque_config LIMIT 1')->fetch();
$sku_preview = ($config['prefixo_codigo_interno'] ?? 'INT') . str_pad((string)($config['proximo_codigo_interno'] ?? 1), 4, '0', STR_PAD_LEFT);

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
        $estoque_inicial = (int)($_POST['estoque_inicial'] ?? 0);
        $ativo          = isset($_POST['ativo']) ? 1 : 0;

        $codigo_duplicado = false;
        if ($codigo_barras !== '') {
            $st = db()->prepare('SELECT COUNT(*) FROM estoque_produtos WHERE codigo_barras = ?');
            $st->execute([$codigo_barras]);
            $codigo_duplicado = (int)$st->fetchColumn() > 0;
        }

        if (!$nome) {
            $erro = 'O nome do produto é obrigatório.';
        } elseif ($codigo_duplicado) {
            $erro = 'Já existe um produto cadastrado com este código de barras.';
        } else {
            $imagem = null;

            if (!empty($_FILES['imagem']['tmp_name'])) {
                $finfo      = new finfo(FILEINFO_MIME_TYPE);
                $mime       = $finfo->file($_FILES['imagem']['tmp_name']);
                $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

                if (!isset($permitidos[$mime])) {
                    $erro = 'Formato não permitido. Use JPG, PNG, WebP ou GIF.';
                } elseif ($_FILES['imagem']['size'] > 8 * 1024 * 1024) {
                    $erro = 'Imagem muito grande. Máximo 8MB.';
                } else {
                    $ext     = $permitidos[$mime];
                    $imagem  = 'prod_' . uniqid() . '.' . $ext;
                    $destino = dirname(__DIR__, 2) . '/assets/img/estoque/produtos/' . $imagem;
                    if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
                        $erro   = 'Erro ao salvar a imagem no servidor.';
                        $imagem = null;
                    }
                }
            }

            if (!$erro) {
                if ($codigo_barras !== '') {
                    $codigo_interno = $codigo_barras;
                } else {
                    $prefixo  = $config['prefixo_codigo_interno'] ?? 'INT';
                    $proximo  = (int)($config['proximo_codigo_interno'] ?? 1);
                    $codigo_interno = $prefixo . str_pad((string)$proximo, 4, '0', STR_PAD_LEFT);
                    db()->prepare('UPDATE estoque_config SET proximo_codigo_interno = proximo_codigo_interno + 1 WHERE id = ?')
                        ->execute([$config['id']]);
                }

                db()->prepare('INSERT INTO estoque_produtos
                    (categoria_id, nome, descricao, codigo_barras, codigo_interno, unidade, preco_custo, preco_venda, estoque_atual, estoque_minimo, imagem, ativo)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([
                        $categoria_id,
                        $nome,
                        $descricao ?: null,
                        $codigo_barras ?: null,
                        $codigo_interno,
                        $unidade,
                        $preco_custo,
                        $preco_venda,
                        $estoque_inicial,
                        $estoque_minimo,
                        $imagem,
                        $ativo,
                    ]);

                header('Location: /portal/estoque/?criado=1');
                exit;
            }
        }
    }
}

$categorias = db()->query("SELECT * FROM estoque_categorias WHERE ativo = 1 ORDER BY nome")->fetchAll();

include dirname(__DIR__) . '/_layout.php';
?>

<?php include __DIR__ . '/_subnav.php'; ?>

<div class="form-wrap">
  <h2>Novo produto</h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <div class="form-tabs">
    <button type="button" class="ativo" data-tab="gerais">Dados gerais</button>
    <button type="button" data-tab="precos">Preços</button>
    <button type="button" data-tab="estoque">Estoque</button>
  </div>

  <form method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="tab-pane ativo" data-tab-pane="gerais">

      <div class="form-row">
        <div class="form-group">
          <label for="nome">Nome do produto</label>
          <input type="text" id="nome" name="nome"
                 value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="codigo_barras">Código de barras <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
          <input type="text" id="codigo_barras" name="codigo_barras"
                 value="<?= htmlspecialchars($_POST['codigo_barras'] ?? '') ?>" placeholder="Leia com o leitor ou digite">
        </div>
      </div>

      <div class="form-group">
        <label for="descricao">Descrição <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="text" id="descricao" name="descricao"
               value="<?= htmlspecialchars($_POST['descricao'] ?? '') ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="categoria_id">Categoria</label>
          <select id="categoria_id" name="categoria_id">
            <option value="">Sem categoria</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= $c['id'] ?>" <?= (int)($_POST['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">Gerencie categorias em <a href="/portal/estoque/categorias.php">Categorias</a>.</span>
        </div>
        <div class="form-group">
          <label for="unidade">Unidade</label>
          <select id="unidade" name="unidade">
            <?php foreach (['un' => 'Unidade', 'kg' => 'Quilo', 'cx' => 'Caixa', 'pct' => 'Pacote', 'lt' => 'Litro'] as $val => $lbl): ?>
              <option value="<?= $val ?>" <?= ($_POST['unidade'] ?? 'un') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="sku_preview">Código (SKU)</label>
        <input type="text" id="sku_preview" value="<?= htmlspecialchars($sku_preview) ?>" disabled>
        <span class="form-hint">Gerado automaticamente ao salvar. Se um código de barras for informado, o SKU será igual a ele.</span>
      </div>

      <div class="form-group">
        <label for="imagem">Foto do produto <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="file" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
        <span class="form-hint">JPG, PNG, WebP ou GIF — máximo 8 MB.</span>
      </div>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="ativo" value="1"
                 <?= (!isset($_POST['ativo']) || $_POST['ativo']) ? 'checked' : '' ?>>
          Produto ativo
        </label>
      </div>

    </div>

    <div class="tab-pane" data-tab-pane="precos">
      <div class="form-row">
        <div class="form-group">
          <label for="preco_custo">Preço de custo (R$)</label>
          <input type="text" id="preco_custo" name="preco_custo" class="money-input" inputmode="numeric"
                 value="<?= htmlspecialchars($_POST['preco_custo'] ?? '0,00') ?>">
        </div>
        <div class="form-group">
          <label for="preco_venda">Preço de venda (R$)</label>
          <input type="text" id="preco_venda" name="preco_venda" class="money-input" inputmode="numeric"
                 value="<?= htmlspecialchars($_POST['preco_venda'] ?? '0,00') ?>">
        </div>
      </div>
    </div>

    <div class="tab-pane" data-tab-pane="estoque">
      <div class="form-row">
        <div class="form-group">
          <label for="estoque_inicial">Estoque inicial</label>
          <input type="number" id="estoque_inicial" name="estoque_inicial" min="0"
                 value="<?= htmlspecialchars($_POST['estoque_inicial'] ?? '0') ?>">
        </div>
        <div class="form-group">
          <label for="estoque_minimo">Estoque mínimo</label>
          <input type="number" id="estoque_minimo" name="estoque_minimo" min="0"
                 value="<?= htmlspecialchars($_POST['estoque_minimo'] ?? ($config['estoque_minimo_padrao'] ?? 5)) ?>">
          <span class="form-hint">Alerta de estoque baixo abaixo deste valor.</span>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Cadastrar produto</button>
      <a href="/portal/estoque/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<script src="/portal/assets/js/moeda-mask.js"></script>
<script>
(function () {
  var botoes = document.querySelectorAll('.form-tabs button');
  var panes  = document.querySelectorAll('.tab-pane');
  botoes.forEach(function (btn) {
    btn.addEventListener('click', function () {
      botoes.forEach(function (b) { b.classList.remove('ativo'); });
      panes.forEach(function (p) { p.classList.remove('ativo'); });
      btn.classList.add('ativo');
      document.querySelector('.tab-pane[data-tab-pane="' + btn.dataset.tab + '"]').classList.add('ativo');
    });
  });

  var barras = document.getElementById('codigo_barras');
  var sku    = document.getElementById('sku_preview');
  var skuGerado = sku.value;
  barras.addEventListener('input', function () {
    sku.value = this.value.trim() !== '' ? this.value.trim() : skuGerado;
  });
})();
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
