<?php
require_once __DIR__ . '/auth.php';
requer_login();

$titulo       = 'Dashboard';
$pagina_ativa = 'dashboard';

$total_usuarios = db()->query('SELECT COUNT(*) FROM usuarios WHERE ativo = 1')->fetchColumn();

include __DIR__ . '/_layout.php';
?>

<div class="cards">
  <div class="card-stat">
    <h3>Usuários ativos</h3>
    <div class="val"><?= $total_usuarios ?></div>
  </div>
  <div class="card-stat ouro">
    <h3>Próximos eventos</h3>
    <div class="val">—</div>
  </div>
  <div class="card-stat verde">
    <h3>Inscrições</h3>
    <div class="val">—</div>
  </div>
</div>

<p style="color:var(--cinza3);font-size:.9rem">
  Os módulos de <strong>Eventos</strong>, <strong>Inscrições</strong> e
  <strong>Financeiro</strong> serão ativados nas próximas fases.
</p>

<?php include __DIR__ . '/_layout_end.php'; ?>
