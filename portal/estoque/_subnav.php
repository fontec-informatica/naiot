<?php
// Sub-navegação do ambiente Livraria. Cada página define $loja_secao antes de incluir este arquivo.
$loja_secoes = [
  'produtos'      => ['label' => 'Produtos',      'href' => '/portal/estoque/'],
  'categorias'    => ['label' => 'Categorias',    'href' => '/portal/estoque/categorias.php'],
  'configuracoes' => ['label' => 'Configurações', 'href' => '/portal/estoque/configuracoes.php'],
];
?>
<div class="loja-header">
  <div class="loja-titulo">
    <h1>Livraria</h1>
    <span>Cadastro de produtos, estoque e vendas</span>
  </div>
  <nav class="loja-subnav">
    <?php foreach ($loja_secoes as $chave => $s): ?>
      <a href="<?= $s['href'] ?>" class="<?= ($loja_secao ?? '') === $chave ? 'ativo' : '' ?>"><?= htmlspecialchars($s['label']) ?></a>
    <?php endforeach; ?>
  </nav>
</div>
