<?php
// Sub-navegação em abas do Controle de Ingressos.
// Cada página define $ing_secao (e, quando dentro de uma campanha, $ing_campanha) antes de incluir este arquivo.
if (!function_exists('tem_modulo') || !tem_modulo('ingressos')) {
    http_response_code(403);
    exit;
}
?>
<div class="loja-header">
  <div class="loja-titulo">
    <h1>Controle de Ingressos</h1>
    <span>
      <?php if (isset($ing_campanha)): ?>
        <a href="/portal/ingressos/" style="color:inherit">← Campanhas</a> · <?= htmlspecialchars($ing_campanha['nome']) ?>
      <?php else: ?>
        Distribuição, acertos e posição de ingressos por servo
      <?php endif; ?>
    </span>
  </div>
  <nav class="loja-subnav">
    <a href="/portal/ingressos/" class="<?= ($ing_secao ?? '') === 'campanhas' ? 'ativo' : '' ?>">Campanhas</a>
    <?php if (isset($ing_campanha)): ?>
    <a href="/portal/ingressos/gerenciar.php?id=<?= $ing_campanha['id'] ?>" class="<?= ($ing_secao ?? '') === 'ingressos' ? 'ativo' : '' ?>">Ingressos</a>
    <a href="/portal/ingressos/gerar.php?id=<?= $ing_campanha['id'] ?>" class="<?= ($ing_secao ?? '') === 'gerar' ? 'ativo' : '' ?>">Gerar</a>
    <a href="/portal/ingressos/distribuir.php?id=<?= $ing_campanha['id'] ?>" class="<?= ($ing_secao ?? '') === 'distribuir' ? 'ativo' : '' ?>">Distribuir</a>
    <a href="/portal/ingressos/posicao.php?id=<?= $ing_campanha['id'] ?>" class="<?= ($ing_secao ?? '') === 'posicao' ? 'ativo' : '' ?>">Posição</a>
    <?php endif; ?>
  </nav>
</div>
