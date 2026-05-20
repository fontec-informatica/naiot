<?php
require_once __DIR__ . '/auth.php';
requer_login();

$titulo       = 'Dashboard';
$pagina_ativa = 'dashboard';

try {
    $total_usuarios   = (int)db()->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1")->fetchColumn();
    $total_eventos    = (int)db()->query("SELECT COUNT(*) FROM eventos WHERE ativo = 1")->fetchColumn();
    $total_inscricoes = (int)db()->query("SELECT COUNT(*) FROM inscricoes WHERE status != 'cancelado'")->fetchColumn();
    $inscricoes_hoje  = (int)db()->query("SELECT COUNT(*) FROM inscricoes WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $pendentes        = (int)db()->query("SELECT COUNT(*) FROM inscricoes WHERE status = 'pendente'")->fetchColumn();
    $eventos_abertos  = (int)db()->query("SELECT COUNT(*) FROM eventos WHERE inscricoes_abertas = 1")->fetchColumn();
} catch (Exception $e) {
    $total_usuarios = $total_eventos = $total_inscricoes = $inscricoes_hoje = $pendentes = $eventos_abertos = 0;
}

include __DIR__ . '/_layout.php';
?>

<div class="cards">
  <div class="card-stat">
    <h3>Eventos ativos</h3>
    <div class="val"><?= $total_eventos ?></div>
    <div class="val-sub">no carrossel do site</div>
  </div>
  <div class="card-stat verde">
    <h3>Inscrições</h3>
    <div class="val"><?= $total_inscricoes ?></div>
    <div class="val-sub"><?= $inscricoes_hoje ?> hoje · <?= $pendentes ?> pendente(s)</div>
  </div>
  <div class="card-stat ouro">
    <h3>Eventos abertos</h3>
    <div class="val"><?= $eventos_abertos ?></div>
    <div class="val-sub">com inscrições abertas</div>
  </div>
  <div class="card-stat">
    <h3>Usuários ativos</h3>
    <div class="val"><?= $total_usuarios ?></div>
    <div class="val-sub">com acesso ao portal</div>
  </div>
</div>

<!-- Acesso rápido -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:16px;margin-top:8px">

  <a href="/portal/inscricoes/" style="text-decoration:none">
    <div style="background:#fff;border:1px solid var(--border);border-top:3px solid var(--verde);border-radius:var(--rl);padding:20px 22px;transition:.15s" onmouseover="this.style.boxShadow='0 4px 18px rgba(0,0,0,.09)'" onmouseout="this.style.boxShadow=''">
      <div style="font-family:'Cinzel',serif;font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:10px">Inscrições</div>
      <div style="font-size:.88rem;color:var(--text)">Ver dashboard completo, últimas inscrições e gerenciar eventos.</div>
      <div style="margin-top:10px;font-size:.78rem;font-weight:600;color:var(--verde)">Abrir →</div>
    </div>
  </a>

  <a href="/portal/financeiro/" style="text-decoration:none">
    <div style="background:#fff;border:1px solid var(--border);border-top:3px solid var(--gold);border-radius:var(--rl);padding:20px 22px;transition:.15s" onmouseover="this.style.boxShadow='0 4px 18px rgba(0,0,0,.09)'" onmouseout="this.style.boxShadow=''">
      <div style="font-family:'Cinzel',serif;font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:10px">Financeiro</div>
      <div style="font-size:.88rem;color:var(--text)">Lançamentos do mês, balanço, recorrentes e exportação.</div>
      <div style="margin-top:10px;font-size:.78rem;font-weight:600;color:var(--gold)">Abrir →</div>
    </div>
  </a>

  <a href="/portal/eventos/" style="text-decoration:none">
    <div style="background:#fff;border:1px solid var(--border);border-top:3px solid var(--green);border-radius:var(--rl);padding:20px 22px;transition:.15s" onmouseover="this.style.boxShadow='0 4px 18px rgba(0,0,0,.09)'" onmouseout="this.style.boxShadow=''">
      <div style="font-family:'Cinzel',serif;font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:10px">Eventos</div>
      <div style="font-size:.88rem;color:var(--text)">Gerenciar eventos, datas, imagens e carrossel do site.</div>
      <div style="margin-top:10px;font-size:.78rem;font-weight:600;color:var(--green)">Abrir →</div>
    </div>
  </a>

</div>

<?php include __DIR__ . '/_layout_end.php'; ?>
