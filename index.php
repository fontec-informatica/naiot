<?php
// ============================================================
// CONFIGURAÇÃO — altere aqui quando for ao vivo
// ============================================================
$ao_vivo = false;
$youtube_video_id = ''; // Ex: 'dQw4w9WgXcQ' — cole o ID do vídeo ao vivo aqui

// ============================================================
// PROCESSAMENTO DOS FORMULÁRIOS
// ============================================================
function salvar_json(string $arquivo, string $texto): void {
    $dados = file_exists($arquivo) ? (json_decode(file_get_contents($arquivo), true) ?: []) : [];
    $dados[] = ['texto' => $texto, 'data' => date('d/m/Y \à\s H:i')];
    file_put_contents($arquivo, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['oracao'])) {
        $texto = mb_substr(trim(strip_tags($_POST['oracao'])), 0, 1000);
        if (mb_strlen($texto) >= 5) {
            salvar_json('data/oracoes.json', $texto);
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?oracao=ok#oracao');
            exit;
        }
    }
    if (!empty($_POST['testemunho'])) {
        $texto = mb_substr(trim(strip_tags($_POST['testemunho'])), 0, 2000);
        if (mb_strlen($texto) >= 5) {
            salvar_json('data/testemunhos.json', $texto);
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?testemunho=ok#testemunhos');
            exit;
        }
    }
}

// Carrega dados (mais recentes primeiro)
$oracoes     = array_reverse(file_exists('data/oracoes.json')     ? (json_decode(file_get_contents('data/oracoes.json'),     true) ?: []) : []);
$testemunhos = array_reverse(file_exists('data/testemunhos.json') ? (json_decode(file_get_contents('data/testemunhos.json'), true) ?: []) : []);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAIOT — Comunidade Católica Senhor Jesus</title>
    <meta name="description" content="Comunidade Católica Senhor Jesus — Campo Limpo de Goiás, GO. Programação semanal, pedidos de oração e testemunhos.">
    <meta name="theme-color" content="#0d1b3e">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        /* ===== VARIÁVEIS ===== */
        :root {
            --navy:       #0d1b3e;
            --navy-light: #162348;
            --gold:       #c9a84c;
            --gold-light: #e2c87a;
            --bg:         #f4f6fb;
            --bg-alt:     #eaecf5;
            --white:      #ffffff;
            --text:       #1a1a2e;
            --muted:      #6b7280;
            --red:        #dc2626;
            --shadow:     0 4px 24px rgba(13,27,62,.10);
            --shadow-lg:  0 12px 48px rgba(13,27,62,.18);
            --radius:     12px;
            --radius-lg:  20px;
            --ease:       0.35s cubic-bezier(.4,0,.2,1);
        }

        /* ===== RESET ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.65;
            overflow-x: hidden;
        }
        img  { max-width: 100%; height: auto; display: block; }
        a    { text-decoration: none; color: inherit; }
        ul   { list-style: none; }

        /* ===== UTILITÁRIOS ===== */
        .container {
            width: 100%;
            max-width: 1160px;
            margin: 0 auto;
            padding: 0 24px;
        }
        h1, h2, h3, h4 { font-family: 'Syne', sans-serif; line-height: 1.2; }

        .section-wrap { padding: 96px 0; }
        .section-wrap.alt { background: var(--bg-alt); }
        .section-wrap.dark { background: var(--navy); }

        .sec-header { text-align: center; margin-bottom: 56px; }
        .gold-bar {
            width: 52px; height: 4px;
            background: var(--gold);
            border-radius: 2px;
            margin: 0 auto 16px;
        }
        .sec-title {
            font-size: clamp(1.9rem, 4vw, 2.8rem);
            font-weight: 800;
            color: var(--navy);
        }
        .sec-title.light { color: #fff; }
        .sec-sub {
            margin-top: 10px;
            color: var(--muted);
            font-size: 1.05rem;
        }
        .sec-sub.light { color: rgba(255,255,255,.6); }

        /* ===== HEADER ===== */
        #header {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 900;
            background: rgba(13,27,62,.96);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(201,168,76,.15);
            transition: box-shadow var(--ease);
        }
        #header.scrolled { box-shadow: 0 4px 32px rgba(0,0,0,.35); }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 68px;
        }
        .header-logo img {
            height: 42px;
            width: auto;
        }
        .header-logo-text {
            display: none;
            font-family: 'Syne', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--gold);
        }

        nav { display: flex; align-items: center; gap: 4px; }
        nav a {
            color: rgba(255,255,255,.82);
            font-size: .9rem;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: 8px;
            transition: color var(--ease), background var(--ease);
            letter-spacing: .02em;
            white-space: nowrap;
        }
        nav a:hover { color: var(--gold); background: rgba(201,168,76,.08); }

        .btn-live {
            display: inline-flex !important;
            align-items: center;
            gap: 7px;
            background: var(--red) !important;
            color: #fff !important;
            padding: 7px 16px !important;
            border-radius: 20px !important;
            font-weight: 700 !important;
            font-size: .82rem !important;
            letter-spacing: .04em !important;
            animation: pulse-live 2s ease infinite;
        }
        .btn-live::before {
            content: '';
            width: 8px; height: 8px;
            background: #fff;
            border-radius: 50%;
            flex-shrink: 0;
        }
        @keyframes pulse-live {
            0%   { box-shadow: 0 0 0 0 rgba(220,38,38,.75); }
            70%  { box-shadow: 0 0 0 10px rgba(220,38,38,0); }
            100% { box-shadow: 0 0 0 0 rgba(220,38,38,0); }
        }

        /* Hamburger */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            background: none;
            border: none;
            padding: 8px;
        }
        .hamburger span {
            display: block;
            width: 24px; height: 2px;
            background: #fff;
            border-radius: 2px;
            transition: var(--ease);
        }
        .hamburger.open span:nth-child(1) { transform: rotate(45deg) translate(5px,5px); }
        .hamburger.open span:nth-child(2) { opacity: 0; }
        .hamburger.open span:nth-child(3) { transform: rotate(-45deg) translate(5px,-5px); }

        /* ===== HERO ===== */
        .hero {
            min-height: 100vh;
            background: var(--navy);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            padding: 110px 24px 72px;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% 0%,   rgba(201,168,76,.10) 0%, transparent 65%),
                radial-gradient(circle at 15% 85%, rgba(201,168,76,.06) 0%, transparent 40%),
                radial-gradient(circle at 85% 15%, rgba(201,168,76,.06) 0%, transparent 40%);
            pointer-events: none;
        }
        /* Subtle cross pattern */
        .hero::after {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23c9a84c' fill-opacity='.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2zM6 4V0H4v4H0v2h4v4h2V6h4V4z'/%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        .hero-content { position: relative; z-index: 1; max-width: 780px; }

        .hero-logo {
            width: min(210px, 48vw);
            margin: 0 auto 2.5rem;
            filter: drop-shadow(0 8px 40px rgba(201,168,76,.35));
            animation: float 5s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-10px); }
        }

        .hero-divider {
            width: 64px; height: 3px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin: 0 auto 1.8rem;
        }
        .hero h1 {
            font-size: clamp(1.9rem, 4.5vw, 3.4rem);
            font-weight: 700;
            color: #fff;
            margin-bottom: 1.1rem;
            text-shadow: 0 2px 24px rgba(0,0,0,.25);
        }
        .hero h1 em {
            font-style: normal;
            color: var(--gold);
        }
        .hero p {
            color: rgba(255,255,255,.70);
            font-size: clamp(1rem, 2vw, 1.18rem);
            font-weight: 300;
            letter-spacing: .03em;
        }
        .hero-scroll {
            position: absolute;
            bottom: 28px; left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: rgba(255,255,255,.35);
            font-size: .72rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            animation: bounce 2.5s ease-in-out infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50%       { transform: translateX(-50%) translateY(7px); }
        }

        /* ===== AO VIVO ===== */
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--red);
            color: #fff;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: .82rem;
            font-weight: 700;
            letter-spacing: .06em;
            margin-bottom: 16px;
            animation: pulse-live 2s ease infinite;
        }
        .live-badge::before {
            content: '';
            width: 8px; height: 8px;
            background: #fff;
            border-radius: 50%;
        }
        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(0,0,0,.5);
        }
        .video-wrapper iframe {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border: none;
        }
        .live-fallback {
            background: rgba(255,255,255,.05);
            border: 2px dashed rgba(201,168,76,.3);
            border-radius: var(--radius-lg);
            padding: 60px 40px;
            text-align: center;
        }
        .live-fallback p { color: rgba(255,255,255,.6); margin-bottom: 24px; }
        .btn-channel {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--red);
            color: #fff;
            padding: 14px 28px;
            border-radius: var(--radius);
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            transition: opacity var(--ease), transform var(--ease);
        }
        .btn-channel:hover { opacity: .9; transform: translateY(-2px); }

        /* ===== PROGRAMAÇÃO ===== */
        .prog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
            gap: 24px;
        }
        .prog-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 32px 28px;
            box-shadow: var(--shadow);
            border-top: 4px solid var(--gold);
            position: relative;
            overflow: hidden;
            transition: transform var(--ease), box-shadow var(--ease);
        }
        .prog-card::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 110px; height: 110px;
            background: radial-gradient(circle, rgba(201,168,76,.07) 0%, transparent 70%);
            transform: translate(25%, -25%);
            pointer-events: none;
        }
        .prog-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
        .prog-icon {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
            flex-shrink: 0;
        }
        .prog-day {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--gold);
            margin-bottom: 4px;
        }
        .prog-time {
            font-family: 'Syne', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: var(--navy);
            line-height: 1;
            margin-bottom: 10px;
        }
        .prog-name {
            font-weight: 600;
            font-size: 1.02rem;
            color: var(--navy);
            margin-bottom: 8px;
        }
        .prog-desc { font-size: .88rem; color: var(--muted); line-height: 1.55; }

        /* ===== REDES SOCIAIS ===== */
        .redes-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .rede-card {
            border-radius: var(--radius-lg);
            padding: 40px 24px;
            text-align: center;
            color: #fff;
            transition: transform var(--ease), box-shadow var(--ease);
            box-shadow: var(--shadow);
            cursor: pointer;
        }
        .rede-card:hover { transform: translateY(-8px) scale(1.02); box-shadow: var(--shadow-lg); }
        .rede-card.instagram { background: linear-gradient(135deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); }
        .rede-card.whatsapp  { background: linear-gradient(135deg, #25D366, #128C7E); }
        .rede-card.youtube   { background: linear-gradient(135deg, #FF0000, #b30000); }
        .rede-card.facebook  { background: linear-gradient(135deg, #1877F2, #0a52cc); }
        .rede-icon { font-size: 2.6rem; margin-bottom: 14px; }
        .rede-name { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.08rem; margin-bottom: 4px; }
        .rede-handle { font-size: .82rem; opacity: .85; }

        /* ===== FORMS ===== */
        .forms-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: start;
        }
        .form-col { display: flex; flex-direction: column; gap: 24px; }
        .form-box {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 36px;
            box-shadow: var(--shadow);
        }
        .form-box h3 {
            font-size: 1.25rem;
            color: var(--navy);
            margin-bottom: 6px;
        }
        .form-box > p { font-size: .88rem; color: var(--muted); margin-bottom: 20px; }
        .success-msg {
            background: #d1fae5;
            color: #064e3b;
            border-left: 4px solid #10b981;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: .9rem;
            margin-bottom: 16px;
        }
        textarea {
            width: 100%;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px 16px;
            font-family: 'DM Sans', sans-serif;
            font-size: .95rem;
            resize: vertical;
            min-height: 120px;
            color: var(--text);
            background: #fafafa;
            transition: border-color var(--ease), box-shadow var(--ease), background var(--ease);
        }
        textarea:focus {
            outline: none;
            border-color: var(--gold);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(201,168,76,.12);
        }
        .btn-submit {
            width: 100%;
            margin-top: 12px;
            padding: 14px;
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: .98rem;
            cursor: pointer;
            letter-spacing: .02em;
            transition: opacity var(--ease), transform var(--ease), box-shadow var(--ease);
        }
        .btn-submit:hover {
            opacity: .92;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(13,27,62,.3);
        }

        /* Cards de submissões */
        .cards-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .cards-header h4 {
            font-size: 1rem;
            color: var(--navy);
        }
        .cards-count {
            background: var(--navy);
            color: var(--gold);
            font-size: .78rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .submissions-scroll {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-height: 420px;
            overflow-y: auto;
            padding-right: 6px;
        }
        .submissions-scroll::-webkit-scrollbar { width: 4px; }
        .submissions-scroll::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 2px; }
        .submissions-scroll::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 2px; }
        .sub-card {
            background: var(--bg);
            border-radius: var(--radius);
            padding: 18px 20px;
            border-left: 4px solid var(--gold);
        }
        .sub-card p { font-size: .93rem; color: var(--text); line-height: 1.6; margin-bottom: 8px; }
        .sub-card span { font-size: .76rem; color: var(--muted); }
        .empty-state {
            text-align: center;
            color: var(--muted);
            font-size: .88rem;
            padding: 28px;
            background: var(--bg);
            border-radius: var(--radius);
        }

        /* ===== MAPA ===== */
        .mapa-wrap {
            height: 460px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        .mapa-wrap iframe { width: 100%; height: 100%; border: none; display: block; }

        /* ===== FOOTER ===== */
        footer {
            background: var(--navy);
            padding: 64px 0 32px;
        }
        .footer-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 28px;
            text-align: center;
        }
        .footer-logo img { height: 52px; filter: brightness(0) invert(1); opacity: .88; }
        .footer-logo-text {
            display: none;
            font-family: 'Syne', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: var(--gold);
        }
        .footer-redes { display: flex; gap: 14px; }
        .footer-rede {
            width: 42px; height: 42px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(201,168,76,.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            transition: background var(--ease), transform var(--ease);
        }
        .footer-rede:hover { background: var(--gold); transform: translateY(-3px); }
        .footer-hr { width: 100%; height: 1px; background: rgba(255,255,255,.08); }
        .footer-copy {
            font-size: .83rem;
            color: rgba(255,255,255,.45);
        }
        .footer-copy strong { color: var(--gold); font-weight: 600; }

        /* ===== ANIMAÇÕES ===== */
        [data-anim] {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity .65s ease, transform .65s ease;
        }
        [data-anim].visible { opacity: 1; transform: translateY(0); }
        [data-anim][data-d="1"] { transition-delay: .10s; }
        [data-anim][data-d="2"] { transition-delay: .20s; }
        [data-anim][data-d="3"] { transition-delay: .30s; }
        [data-anim][data-d="4"] { transition-delay: .40s; }
        [data-anim][data-d="5"] { transition-delay: .50s; }

        /* ===== RESPONSIVO ===== */
        @media (max-width: 900px) {
            .redes-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            nav {
                position: fixed;
                top: 68px; left: 0; right: 0;
                background: var(--navy);
                flex-direction: column;
                padding: 16px;
                gap: 4px;
                transform: translateY(-110%);
                transition: transform var(--ease);
                border-bottom: 1px solid rgba(201,168,76,.15);
                z-index: 899;
            }
            nav.open { transform: translateY(0); }
            nav a { width: 100%; text-align: center; padding: 12px; }
            .hamburger { display: flex; }
            .forms-grid { grid-template-columns: 1fr; gap: 32px; }
            .section-wrap { padding: 72px 0; }
            .prog-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .redes-grid { grid-template-columns: 1fr 1fr; }
            .form-box { padding: 24px; }
            .mapa-wrap { height: 320px; border-radius: 0; }
        }
    </style>
</head>
<body>

<!-- ===================== HEADER ===================== -->
<header id="header">
    <div class="container header-inner">

        <a href="#inicio" class="header-logo">
            <img src="assets/img/logo.png" alt="NAIOT"
                 onerror="this.style.display='none'; document.querySelector('.header-logo-text').style.display='block'">
            <span class="header-logo-text">NAIOT</span>
        </a>

        <button class="hamburger" id="hamburger" aria-label="Abrir menu">
            <span></span><span></span><span></span>
        </button>

        <nav id="nav">
            <a href="#inicio">Início</a>
            <a href="#programacao">Programação</a>
            <?php if ($ao_vivo): ?>
            <a href="#ao-vivo" class="btn-live">AO VIVO</a>
            <?php endif; ?>
            <a href="#oracao">Oração</a>
            <a href="#testemunhos">Testemunhos</a>
        </nav>

    </div>
</header>

<!-- ===================== HERO ===================== -->
<section class="hero" id="inicio">
    <div class="hero-content">
        <img src="assets/img/logo.png" alt="NAIOT" class="hero-logo"
             onerror="this.style.display='none'">
        <div class="hero-divider"></div>
        <h1>Nossa missão é estar<br>aos pés de <em>Jesus</em></h1>
        <p>Comunidade Católica Senhor Jesus<br>Campo Limpo de Goiás — GO</p>
    </div>
    <div class="hero-scroll" aria-hidden="true">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
</section>

<!-- ===================== AO VIVO (condicional) ===================== -->
<?php if ($ao_vivo): ?>
<section class="section-wrap dark" id="ao-vivo">
    <div class="container">
        <div class="sec-header" data-anim>
            <div class="live-badge">AO VIVO AGORA</div>
            <h2 class="sec-title light">Transmissão ao Vivo</h2>
            <p class="sec-sub light">Acompanhe nossa programação em tempo real</p>
        </div>

        <?php if (!empty($youtube_video_id)): ?>
        <div class="video-wrapper" data-anim>
            <iframe
                src="https://www.youtube.com/embed/<?= htmlspecialchars($youtube_video_id, ENT_QUOTES) ?>?autoplay=1&rel=0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen>
            </iframe>
        </div>
        <?php else: ?>
        <div class="live-fallback" data-anim>
            <p>Estamos ao vivo! Clique para assistir no YouTube.</p>
            <a href="https://www.youtube.com/@naiot_oficial4299" target="_blank" rel="noopener" class="btn-channel">
                ▶ Assistir no YouTube
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===================== PROGRAMAÇÃO ===================== -->
<section class="section-wrap alt" id="programacao">
    <div class="container">
        <div class="sec-header" data-anim>
            <div class="gold-bar"></div>
            <h2 class="sec-title">Programação</h2>
            <p class="sec-sub">Nossa agenda semanal de encontros e transmissões</p>
        </div>
        <div class="prog-grid">

            <div class="prog-card" data-anim data-d="1">
                <div class="prog-icon">📺</div>
                <div class="prog-day">Terça-feira</div>
                <div class="prog-time">20h30</div>
                <div class="prog-name">Programa Fortalecendo a Fé</div>
                <p class="prog-desc">Transmissão ao vivo em todas as redes sociais. Participe e fortaleça sua fé!</p>
            </div>

            <div class="prog-card" data-anim data-d="2">
                <div class="prog-icon">🙏</div>
                <div class="prog-day">Quarta-feira</div>
                <div class="prog-time">09h00</div>
                <div class="prog-name">Grupo de Oração</div>
                <p class="prog-desc">Encontro de oração matinal. Venha louvar e adorar a Deus em comunidade.</p>
            </div>

            <div class="prog-card" data-anim data-d="3">
                <div class="prog-icon">👤</div>
                <div class="prog-day">Quarta-feira</div>
                <div class="prog-time">13h00</div>
                <div class="prog-name">Atendimento Individual</div>
                <p class="prog-desc">Por ordem de chegada. Sr. Toninho atende os primeiros 15 participantes.</p>
            </div>

            <div class="prog-card" data-anim data-d="4">
                <div class="prog-icon">✨</div>
                <div class="prog-day">Quarta-feira</div>
                <div class="prog-time">19h00</div>
                <div class="prog-name">Grupo de Oração</div>
                <p class="prog-desc">Encontro noturno de oração. Encerramos o dia em louvor e gratidão.</p>
            </div>

            <div class="prog-card" data-anim data-d="5">
                <div class="prog-icon">📅</div>
                <div class="prog-day">Finais de semana</div>
                <div class="prog-time">Eventos</div>
                <div class="prog-name">Conforme Calendário</div>
                <p class="prog-desc">Acompanhe nossas redes sociais para conferir os eventos especiais do final de semana.</p>
            </div>

        </div>
    </div>
</section>

<!-- ===================== REDES SOCIAIS ===================== -->
<section class="section-wrap dark" id="redes">
    <div class="container">
        <div class="sec-header" data-anim>
            <div class="gold-bar"></div>
            <h2 class="sec-title light">Nossas Redes</h2>
            <p class="sec-sub light">Siga e compartilhe nossa missão</p>
        </div>
        <div class="redes-grid">
            <a href="https://www.instagram.com/naiot_oficial/" target="_blank" rel="noopener"
               class="rede-card instagram" data-anim data-d="1">
                <div class="rede-icon">📷</div>
                <div class="rede-name">Instagram</div>
                <div class="rede-handle">@naiot_oficial</div>
            </a>
            <a href="https://whatsapp.com/channel/0029VaVPbi15Ui2Y5f23h22i" target="_blank" rel="noopener"
               class="rede-card whatsapp" data-anim data-d="2">
                <div class="rede-icon">💬</div>
                <div class="rede-name">WhatsApp</div>
                <div class="rede-handle">Canal Oficial</div>
            </a>
            <a href="https://www.youtube.com/@naiot_oficial4299" target="_blank" rel="noopener"
               class="rede-card youtube" data-anim data-d="3">
                <div class="rede-icon">▶️</div>
                <div class="rede-name">YouTube</div>
                <div class="rede-handle">@naiot_oficial4299</div>
            </a>
            <a href="https://www.facebook.com/comunidadenaiot/" target="_blank" rel="noopener"
               class="rede-card facebook" data-anim data-d="4">
                <div class="rede-icon">👥</div>
                <div class="rede-name">Facebook</div>
                <div class="rede-handle">comunidadenaiot</div>
            </a>
        </div>
    </div>
</section>

<!-- ===================== ORAÇÃO & TESTEMUNHOS ===================== -->
<section class="section-wrap" id="oracao">
    <div class="container">
        <div class="sec-header" data-anim>
            <div class="gold-bar"></div>
            <h2 class="sec-title">Oração &amp; Testemunhos</h2>
            <p class="sec-sub">Compartilhe suas intenções e as graças recebidas</p>
        </div>

        <div class="forms-grid">

            <!-- COLUNA: PEDIDO DE ORAÇÃO -->
            <div class="form-col">
                <div class="form-box" data-anim data-d="1">
                    <h3>🙏 Pedido de Oração</h3>
                    <p>Compartilhe sua intenção de forma anônima. Nossa comunidade orará por você.</p>

                    <?php if (isset($_GET['oracao']) && $_GET['oracao'] === 'ok'): ?>
                    <div class="success-msg">Seu pedido foi enviado. Vamos orar por você! 🙏</div>
                    <?php endif; ?>

                    <form method="POST">
                        <textarea name="oracao"
                                  placeholder="Escreva seu pedido de oração aqui..."
                                  required
                                  maxlength="1000"></textarea>
                        <button type="submit" class="btn-submit">Enviar Pedido de Oração</button>
                    </form>
                </div>

                <?php if (!empty($oracoes)): ?>
                <div class="form-box" data-anim data-d="2">
                    <div class="cards-header">
                        <h4>Intenções de Oração</h4>
                        <span class="cards-count"><?= count($oracoes) ?></span>
                    </div>
                    <div class="submissions-scroll">
                        <?php foreach ($oracoes as $item): ?>
                        <div class="sub-card">
                            <p><?= nl2br(htmlspecialchars($item['texto'])) ?></p>
                            <span>📅 <?= htmlspecialchars($item['data']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state" data-anim data-d="2">
                    Nenhum pedido de oração ainda. Seja o primeiro!
                </div>
                <?php endif; ?>
            </div>

            <!-- COLUNA: TESTEMUNHOS -->
            <div class="form-col" id="testemunhos">
                <div class="form-box" data-anim data-d="3">
                    <h3>✨ Compartilhar Testemunho</h3>
                    <p>Compartilhe as graças e milagres que Deus realizou em sua vida.</p>

                    <?php if (isset($_GET['testemunho']) && $_GET['testemunho'] === 'ok'): ?>
                    <div class="success-msg">Seu testemunho foi compartilhado. Que Deus seja glorificado! ✨</div>
                    <?php endif; ?>

                    <form method="POST">
                        <textarea name="testemunho"
                                  placeholder="Compartilhe seu testemunho aqui..."
                                  required
                                  maxlength="2000"></textarea>
                        <button type="submit" class="btn-submit">Compartilhar Testemunho</button>
                    </form>
                </div>

                <?php if (!empty($testemunhos)): ?>
                <div class="form-box" data-anim data-d="4">
                    <div class="cards-header">
                        <h4>Testemunhos</h4>
                        <span class="cards-count"><?= count($testemunhos) ?></span>
                    </div>
                    <div class="submissions-scroll">
                        <?php foreach ($testemunhos as $item): ?>
                        <div class="sub-card">
                            <p><?= nl2br(htmlspecialchars($item['texto'])) ?></p>
                            <span>📅 <?= htmlspecialchars($item['data']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state" data-anim data-d="4">
                    Nenhum testemunho ainda. Compartilhe o que Deus fez por você!
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<!-- ===================== MAPA ===================== -->
<section class="section-wrap alt" style="padding-bottom:0;" id="mapa">
    <div class="container">
        <div class="sec-header" data-anim>
            <div class="gold-bar"></div>
            <h2 class="sec-title">Como Chegar</h2>
            <p class="sec-sub">Comunidade NAIOT — Campo Limpo de Goiás, GO</p>
        </div>
        <div class="mapa-wrap" data-anim>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3830.1820033058525!2d-49.14189992509153!3d-16.26243988444542!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x935c276d0e8c03cf%3A0x7bfd94fb797d6e1b!2sComunidade%20NAIOT%20(Novo%20Acesso)!5e0!3m2!1spt-BR!2sbr!4v1754773032917!5m2!1spt-BR!2sbr"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>

<!-- ===================== FOOTER ===================== -->
<footer>
    <div class="container footer-body">

        <div class="footer-logo">
            <img src="assets/img/logo.png" alt="NAIOT"
                 onerror="this.style.display='none'; document.querySelector('.footer-logo-text').style.display='block'">
            <span class="footer-logo-text">NAIOT</span>
        </div>

        <div class="footer-redes">
            <a href="https://www.instagram.com/naiot_oficial/" target="_blank" rel="noopener"
               class="footer-rede" title="Instagram">📷</a>
            <a href="https://whatsapp.com/channel/0029VaVPbi15Ui2Y5f23h22i" target="_blank" rel="noopener"
               class="footer-rede" title="WhatsApp">💬</a>
            <a href="https://www.youtube.com/@naiot_oficial4299" target="_blank" rel="noopener"
               class="footer-rede" title="YouTube">▶️</a>
            <a href="https://www.facebook.com/comunidadenaiot/" target="_blank" rel="noopener"
               class="footer-rede" title="Facebook">👥</a>
        </div>

        <div class="footer-hr"></div>

        <p class="footer-copy">
            © 2026 <strong>NAIOT</strong> — Comunidade Católica Senhor Jesus.
            Todos os direitos reservados.
        </p>

    </div>
</footer>

<!-- ===================== SCRIPTS ===================== -->
<script>
    // Mobile menu
    const hamburger = document.getElementById('hamburger');
    const nav = document.getElementById('nav');
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('open');
        nav.classList.toggle('open');
    });
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
        hamburger.classList.remove('open');
        nav.classList.remove('open');
    }));

    // Header sombra no scroll
    const header = document.getElementById('header');
    window.addEventListener('scroll', () => {
        header.classList.toggle('scrolled', window.scrollY > 30);
    }, { passive: true });

    // Animações por Intersection Observer
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                observer.unobserve(e.target);
            }
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('[data-anim]').forEach(el => observer.observe(el));
</script>

</body>
</html>
