<?php
$titulo       = 'Acesso negado';
$pagina_ativa = '';
include __DIR__ . '/_layout.php';
?>
<div style="text-align:center;padding:60px 0">
  <div style="font-size:3rem;margin-bottom:16px">🔒</div>
  <h2 style="color:var(--vermelho);margin-bottom:8px">Acesso negado</h2>
  <p style="color:var(--cinza3)">Você não tem permissão para acessar esta área.</p>
  <a href="/portal/" class="btn btn-ghost" style="margin-top:20px">Voltar ao Dashboard</a>
</div>
<?php include __DIR__ . '/_layout_end.php'; ?>
