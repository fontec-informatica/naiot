<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'estoque']);

$titulo       = 'Livraria — Produtos';
$pagina_ativa = 'estoque';
$loja_secao   = 'produtos';

$busca         = trim($_GET['q'] ?? '');
$categoria_id  = (int)($_GET['categoria'] ?? 0);
$estoque_baixo = !empty($_GET['baixo']);
$pagina_atual  = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina    = 50;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);
    if ($acao === 'toggle' && $id) {
        db()->prepare('UPDATE estoque_produtos SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
    }
    header('Location: /portal/estoque/' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

$where  = 'WHERE 1=1';
$params = [];

if ($busca !== '') {
    $where .= ' AND (p.nome LIKE ? OR p.codigo_barras LIKE ? OR p.codigo_interno LIKE ?)';
    $params[] = "%$busca%"; $params[] = "%$busca%"; $params[] = "%$busca%";
}
if ($categoria_id) {
    $where .= ' AND p.categoria_id = ?';
    $params[] = $categoria_id;
}
if ($estoque_baixo) {
    $where .= ' AND p.estoque_atual <= p.estoque_minimo';
}

$stmt_total = db()->prepare("SELECT COUNT(*) FROM estoque_produtos p $where");
$stmt_total->execute($params);
$total_produtos = (int)$stmt_total->fetchColumn();
$total_paginas  = max(1, (int)ceil($total_produtos / $por_pagina));
$pagina_atual   = min($pagina_atual, $total_paginas);
$offset         = ($pagina_atual - 1) * $por_pagina;

$stmt = db()->prepare("
    SELECT p.*, c.nome AS categoria_nome, c.cor AS categoria_cor
    FROM estoque_produtos p
    LEFT JOIN estoque_categorias c ON c.id = p.categoria_id
    $where
    ORDER BY p.nome ASC
    LIMIT $por_pagina OFFSET $offset
");
$stmt->execute($params);
$produtos = $stmt->fetchAll();

$categorias = db()->query('SELECT * FROM estoque_categorias ORDER BY nome')->fetchAll();

function loja_pagina_url(int $pagina): string {
    $params = $_GET;
    $params['pagina'] = $pagina;
    return '/portal/estoque/?' . http_build_query($params);
}

include dirname(__DIR__) . '/_layout.php';
?>

<?php include __DIR__ . '/_subnav.php'; ?>

<?php if (!empty($_GET['criado'])): ?>
  <div class="alerta alerta-ok" style="margin-bottom:16px">Produto cadastrado com sucesso.</div>
<?php endif; ?>
<?php if (!empty($_GET['editado'])): ?>
  <div class="alerta alerta-ok" style="margin-bottom:16px">Alterações salvas com sucesso.</div>
<?php endif; ?>

<div class="tabela-wrap">
  <div class="tabela-header">
    <h2>Produtos <span style="font-weight:400;text-transform:none;color:var(--muted)">(<?= $total_produtos ?>)</span></h2>
    <a href="/portal/estoque/novo.php" class="btn btn-primary btn-sm">+ Novo produto</a>
  </div>

  <form method="get" style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div style="flex:1;min-width:200px">
      <label style="font-size:.72rem">Buscar</label>
      <input type="text" name="q" placeholder="Nome, código de barras ou SKU" value="<?= htmlspecialchars($busca) ?>">
    </div>
    <div style="min-width:160px">
      <label style="font-size:.72rem">Categoria</label>
      <select name="categoria">
        <option value="">Todas</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $categoria_id === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.82rem;padding-bottom:8px">
        <input type="checkbox" name="baixo" value="1" <?= $estoque_baixo ? 'checked' : '' ?>>
        Só estoque baixo
      </label>
    </div>
    <button type="submit" class="btn btn-ghost btn-sm">Filtrar</button>
  </form>

  <?php if (empty($produtos)): ?>
    <div style="padding:40px;text-align:center;color:var(--cinza3)">
      Nenhum produto encontrado.
    </div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:52px"></th>
        <th>Produto</th>
        <th>Categoria</th>
        <th>Código (SKU)</th>
        <th>Cód. barras</th>
        <th>Unidade</th>
        <th style="text-align:right">Preço venda</th>
        <th style="text-align:center">Estoque</th>
        <th style="text-align:center">Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($produtos as $p): ?>
      <tr>
        <td>
          <?php if ($p['imagem']): ?>
            <img src="/assets/img/estoque/produtos/<?= htmlspecialchars($p['imagem']) ?>"
                 alt="" style="height:36px;width:36px;border-radius:6px;object-fit:cover">
          <?php else: ?>
            <div style="height:36px;width:36px;border-radius:6px;background:var(--off);border:1px solid var(--border)"></div>
          <?php endif; ?>
        </td>
        <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
        <td>
          <?php if ($p['categoria_nome']): ?>
            <span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:<?= htmlspecialchars($p['categoria_cor']) ?>;margin-right:6px"></span>
            <?= htmlspecialchars($p['categoria_nome']) ?>
          <?php else: ?>
            <span style="color:var(--cinza3)">—</span>
          <?php endif; ?>
        </td>
        <td style="font-size:.82rem;color:var(--cinza3)"><?= htmlspecialchars($p['codigo_interno']) ?></td>
        <td style="font-size:.82rem;color:var(--cinza3)"><?= $p['codigo_barras'] ? htmlspecialchars($p['codigo_barras']) : '—' ?></td>
        <td style="font-size:.82rem;color:var(--cinza3)"><?= htmlspecialchars($p['unidade']) ?></td>
        <td style="text-align:right;font-weight:600">R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></td>
        <td style="text-align:center">
          <?= (int)$p['estoque_atual'] ?>
          <?php if ($p['estoque_atual'] <= $p['estoque_minimo']): ?>
            <br><span class="badge" style="background:#fef2f2;color:var(--vermelho)">Estoque baixo</span>
          <?php endif; ?>
        </td>
        <td style="text-align:center">
          <?php if ($p['ativo']): ?>
            <span style="color:var(--verde);font-size:.82rem">● Ativo</span>
          <?php else: ?>
            <span class="badge badge-inativo">Inativo</span>
          <?php endif; ?>
        </td>
        <td style="display:flex;gap:8px">
          <a href="/portal/estoque/editar.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="toggle">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn btn-sm <?= $p['ativo'] ? 'btn-danger' : 'btn-ouro' ?>">
              <?= $p['ativo'] ? 'Desativar' : 'Ativar' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($total_paginas > 1): ?>
  <div class="paginacao">
    <?php if ($pagina_atual > 1): ?><a href="<?= loja_pagina_url($pagina_atual - 1) ?>">‹</a><?php endif; ?>
    <?php
      $inicio = max(1, $pagina_atual - 2);
      $fim    = min($total_paginas, $pagina_atual + 2);
      if ($inicio > 1) { echo '<a href="' . loja_pagina_url(1) . '">1</a>'; if ($inicio > 2) echo '<span class="reticencias">…</span>'; }
      for ($n = $inicio; $n <= $fim; $n++) {
          echo $n === $pagina_atual ? '<span class="ativa">' . $n . '</span>' : '<a href="' . loja_pagina_url($n) . '">' . $n . '</a>';
      }
      if ($fim < $total_paginas) { if ($fim < $total_paginas - 1) echo '<span class="reticencias">…</span>'; echo '<a href="' . loja_pagina_url($total_paginas) . '">' . $total_paginas . '</a>'; }
    ?>
    <?php if ($pagina_atual < $total_paginas): ?><a href="<?= loja_pagina_url($pagina_atual + 1) ?>">›</a><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
