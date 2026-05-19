<?php
$nome   = $_SESSION['usuario_nome']   ?? '';
$perfil = $_SESSION['usuario_perfil'] ?? '';
$inicial = mb_strtoupper(mb_substr(trim($nome), 0, 1)) ?: 'U';
$perfis_label = ['admin' => 'Administrador', 'financeiro' => 'Financeiro', 'secretaria' => 'Secretaria'];
$perfil_label = $perfis_label[$perfil] ?? ucfirst($perfil);

/* ── SVG icons ── */
$icons = [
  'dashboard'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
  'eventos'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
  'inscricoes' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
  'financeiro' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>',
  'usuarios'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
];

$menu = [
  'dashboard'  => ['icon' => $icons['dashboard'],  'label' => 'Dashboard',     'href' => '/portal/',            'perfis' => ['admin','financeiro','secretaria']],
  'eventos'    => ['icon' => $icons['eventos'],    'label' => 'Próx. Eventos', 'href' => '/portal/eventos/',    'perfis' => ['admin','secretaria']],
  'inscricoes' => ['icon' => $icons['inscricoes'], 'label' => 'Inscrições',    'href' => '/portal/inscricoes/', 'perfis' => ['admin','secretaria']],
  'financeiro' => ['icon' => $icons['financeiro'], 'label' => 'Financeiro',    'href' => '/portal/financeiro/', 'perfis' => ['admin','financeiro']],
  'usuarios'   => ['icon' => $icons['usuarios'],   'label' => 'Usuários',      'href' => '/portal/usuarios/',   'perfis' => ['admin']],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($titulo ?? 'Portal') ?> — NAIOT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/portal/assets/css/portal.css">
</head>
<body>

<div class="sidebar-overlay" id="overlay"></div>

<div class="layout">

  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
      <img src="/assets/img/logo.png" alt="NAIOT"
           onerror="this.style.display='none';document.querySelector('.sidebar-brand-name').textContent='NAIOT'">
      <div class="sidebar-brand-txt">
        <span class="sidebar-brand-name">NAIOT</span>
        <span class="sidebar-brand-sub">Portal Administrativo</span>
      </div>
    </div>

    <span class="sidebar-section">Menu</span>
    <ul class="sidebar-nav">
      <?php foreach ($menu as $chave => $item): ?>
        <?php if (in_array($perfil, $item['perfis'], true)): ?>
          <li>
            <a href="<?= $item['href'] ?>"
               class="<?= ($pagina_ativa ?? '') === $chave ? 'ativo' : '' ?>">
              <?php if (($pagina_ativa ?? '') === $chave): ?>
                <span class="nav-indicator"></span>
              <?php endif; ?>
              <?= $item['icon'] ?>
              <?= $item['label'] ?>
            </a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>

    <div class="sidebar-spacer"></div>

    <div class="sidebar-footer">
      <div class="sidebar-avatar"><?= htmlspecialchars($inicial) ?></div>
      <div class="sidebar-user-info">
        <span class="sidebar-user-name"><?= htmlspecialchars($nome) ?></span>
        <span class="sidebar-user-role"><?= htmlspecialchars($perfil_label) ?></span>
      </div>
      <a href="/portal/logout.php" class="sidebar-logout" title="Sair">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
      </a>
    </div>

  </aside>

  <!-- ══ MAIN ══ -->
  <div class="main">

    <header class="topbar">
      <div class="topbar-left">
        <button class="topbar-burger" id="burger" aria-label="Abrir menu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M3 12h18M3 6h18M3 18h18"/>
          </svg>
        </button>
        <div class="topbar-breadcrumb">
          <span class="bc-root">NAIOT</span>
          <span class="bc-sep">›</span>
          <span class="topbar-title"><?= htmlspecialchars($titulo ?? 'Portal') ?></span>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-user-pill">
          <div class="topbar-avatar"><?= htmlspecialchars($inicial) ?></div>
          <span class="topbar-name"><?= htmlspecialchars(explode(' ', trim($nome))[0]) ?></span>
          <span class="topbar-role"><?= htmlspecialchars($perfil_label) ?></span>
        </div>
      </div>
    </header>

    <div class="content">
<?php /* ── conteúdo da página começa aqui ── */ ?>
