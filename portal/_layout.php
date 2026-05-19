<?php
// Uso: include _layout.php após definir $titulo e $pagina_ativa
$nome   = $_SESSION['usuario_nome']   ?? '';
$perfil = $_SESSION['usuario_perfil'] ?? '';

$menu = [
    'dashboard' => ['icon' => '◈', 'label' => 'Dashboard',    'href' => '/portal/',                   'perfis' => ['admin','financeiro','secretaria']],
    'eventos'   => ['icon' => '◉', 'label' => 'Próx. Eventos','href' => '/portal/eventos/',            'perfis' => ['admin','secretaria']],
    'inscricoes'=> ['icon' => '◎', 'label' => 'Inscrições',   'href' => '/portal/inscricoes/',         'perfis' => ['admin','secretaria']],
    'financeiro'=> ['icon' => '◈', 'label' => 'Financeiro',   'href' => '/portal/financeiro/',         'perfis' => ['admin','financeiro']],
    'usuarios'  => ['icon' => '◉', 'label' => 'Usuários',     'href' => '/portal/usuarios/',           'perfis' => ['admin']],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($titulo ?? 'Portal') ?> — NAIOT</title>
<link rel="stylesheet" href="/portal/assets/css/portal.css">
</head>
<body>
<div class="layout">

  <aside class="sidebar">
    <div class="sidebar-brand">
      <img src="/assets/img/logo.png" alt="NAIOT" onerror="this.style.display='none'">
      <span>NAIOT</span>
    </div>

    <span class="sidebar-label">Menu</span>
    <ul class="sidebar-nav">
      <?php foreach ($menu as $chave => $item): ?>
        <?php if (in_array($perfil, $item['perfis'], true)): ?>
          <li>
            <a href="<?= $item['href'] ?>"
               class="<?= ($pagina_ativa ?? '') === $chave ? 'ativo' : '' ?>">
              <?= $item['icon'] ?> <?= $item['label'] ?>
            </a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>

    <div class="sidebar-footer">
      <strong><?= htmlspecialchars($nome) ?></strong><br>
      <?= htmlspecialchars($perfil) ?><br><br>
      <a href="/portal/logout.php">Sair</a>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <span class="topbar-title"><?= htmlspecialchars($titulo ?? 'Portal') ?></span>
      <span class="topbar-user">Olá, <strong><?= htmlspecialchars($nome) ?></strong></span>
    </div>
    <div class="content">
