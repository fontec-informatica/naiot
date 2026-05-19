<?php
// ============================================================
// CONFIGURAÇÃO
// ============================================================
$ao_vivo = false;
$youtube_video_id = ''; // ID do vídeo ao vivo quando necessário

// ============================================================
// FORMULÁRIOS
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

$oracoes     = array_reverse(file_exists('data/oracoes.json')     ? (json_decode(file_get_contents('data/oracoes.json'),     true) ?: []) : []);
$testemunhos = array_reverse(file_exists('data/testemunhos.json') ? (json_decode(file_get_contents('data/testemunhos.json'), true) ?: []) : []);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAIOT — Comunidade Católica Senhor Jesus</title>
    <meta name="description" content="Comunidade Católica Senhor Jesus — Campo Limpo de Goiás, GO.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=EB+Garamond:ital,wght@0,400;0,500;1,400;1,500&display=swap" rel="stylesheet">
    <style>
        :root {
            --green:      #1e6b35;
            --green-dark: #163d22;
            --green-pale: #f0f7f2;
            --gold:       #a87d28;
            --gold-light: #c9a84c;
            --white:      #ffffff;
            --off:        #f8f8f6;
            --border:     #e8e4dd;
            --text:       #222222;
            --muted:      #6e6e6e;
            --red:        #b83232;
            --shadow-sm:  0 2px 12px rgba(0,0,0,.07);
            --shadow:     0 4px 28px rgba(0,0,0,.10);
            --shadow-lg:  0 12px 48px rgba(0,0,0,.14);
            --r:          10px;
            --r-lg:       18px;
            --ease:       .35s cubic-bezier(.4,0,.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'EB Garamond', Georgia, serif;
            font-size: 17px;
            line-height: 1.75;
            color: var(--text);
            background: var(--white);
            overflow-x: hidden;
        }
        img { max-width: 100%; height: auto; display: block; }
        a   { text-decoration: none; color: inherit; }
        ul  { list-style: none; }

        /* ── TIPOGRAFIA ── */
        .cinzel { font-family: 'Cinzel', serif; }
        h1, h2, h3, h4 { font-family: 'Cinzel', serif; line-height: 1.25; }

        /* ── CONTAINER ── */
        .wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding: 0 28px; }

        /* ── SEÇÕES ── */
        .sec      { padding: 88px 0; }
        .sec.alt  { background: var(--off); }
        .sec.pale { background: var(--green-pale); }
        .sec.dark { background: var(--green-dark); }

        /* ── CABEÇALHO DE SEÇÃO ── */
        .sec-head { text-align: center; margin-bottom: 56px; }
        .deco {
            display: flex; align-items: center; justify-content: center;
            gap: 14px; margin-bottom: 14px; color: var(--gold);
        }
        .deco::before, .deco::after {
            content: ''; display: block;
            width: 52px; height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold-light));
            opacity: .7;
        }
        .deco::after { background: linear-gradient(90deg, var(--gold-light), transparent); }
        .deco span { font-size: 1rem; letter-spacing: .2em; }
        .sec-title {
            font-size: clamp(1.6rem, 3.2vw, 2.5rem);
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--green-dark);
        }
        .sec-title.light { color: #fff; }
        .sec-sub {
            margin-top: 12px;
            font-size: 1.05rem;
            font-style: italic;
            color: var(--muted);
        }
        .sec-sub.light { color: rgba(255,255,255,.6); }

        /* ══════════════════════════════
           HEADER
        ══════════════════════════════ */
        #hdr {
            position: fixed; top: 0; left: 0; right: 0; z-index: 900;
            background: rgba(255,255,255,.97);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            transition: box-shadow var(--ease);
        }
        #hdr.scrolled { box-shadow: 0 2px 24px rgba(0,0,0,.10); }
        .hdr-inner {
            display: flex; align-items: center;
            justify-content: space-between;
            height: 86px;
        }
        .hdr-logo img  { height: 72px; }
        .hdr-logo-txt  {
            display: none;
            font-family: 'Cinzel', serif; font-size: 1.3rem;
            font-weight: 700; color: var(--green-dark); letter-spacing: .1em;
        }
        nav { display: flex; align-items: center; gap: 2px; }
        nav a {
            font-family: 'Cinzel', serif;
            font-size: .72rem; font-weight: 500;
            letter-spacing: .08em; text-transform: uppercase;
            color: var(--green-dark);
            padding: 8px 13px; border-radius: 6px;
            transition: color var(--ease), background var(--ease);
        }
        nav a:hover { color: var(--green); background: var(--green-pale); }

        .hdr-social { display: flex; align-items: center; gap: 6px; margin-left: 10px; padding-left: 10px; border-left: 1px solid var(--border); }
        .hdr-social a {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--green-pale); border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            color: var(--green);
            transition: background var(--ease), transform var(--ease), border-color var(--ease), color var(--ease);
        }
        .hdr-social a svg { width: 15px; height: 15px; fill: currentColor; flex-shrink: 0; }
        .hdr-social a:hover { background: var(--green); border-color: var(--green); color: #fff; transform: translateY(-2px); }

        .btn-live {
            display: inline-flex !important; align-items: center; gap: 7px;
            background: var(--red) !important; color: #fff !important;
            border-radius: 4px !important; animation: pulse-red 2s infinite;
        }
        .btn-live::before {
            content: ''; width: 7px; height: 7px;
            background: #fff; border-radius: 50%; flex-shrink: 0;
        }
        @keyframes pulse-red {
            0%  { box-shadow: 0 0 0 0 rgba(184,50,50,.7); }
            70% { box-shadow: 0 0 0 9px rgba(184,50,50,0); }
            100%{ box-shadow: 0 0 0 0 rgba(184,50,50,0); }
        }

        /* hamburger */
        .burger {
            display: none; flex-direction: column; gap: 5px;
            background: none; border: none; cursor: pointer; padding: 8px;
        }
        .burger span {
            display: block; width: 24px; height: 2px;
            background: var(--green-dark); border-radius: 1px;
            transition: var(--ease);
        }
        .burger.open span:nth-child(1) { transform: rotate(45deg) translate(5px,5px); }
        .burger.open span:nth-child(2) { opacity: 0; }
        .burger.open span:nth-child(3) { transform: rotate(-45deg) translate(5px,-5px); }

        /* ══════════════════════════════
           HERO
        ══════════════════════════════ */
        .hero {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            text-align: center;
            padding: 110px 28px 72px;
            position: relative;
            overflow: hidden;
            background: var(--white);
        }
        /* Faixa verde sutil no topo */
        .hero::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, var(--green-dark), var(--green), var(--gold-light), var(--green), var(--green-dark));
        }
        /* Padrão de fundo muito sutil */
        .hero::after {
            content: '';
            position: absolute; inset: 0; pointer-events: none;
            background-image: radial-gradient(circle at 20% 80%, rgba(30,107,53,.04) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(30,107,53,.04) 0%, transparent 50%);
        }
        .hero-inner { position: relative; z-index: 1; max-width: 720px; }
        .hero-logo {
            width: min(200px, 48vw);
            margin: 0 auto 2.4rem;
            animation: hero-in .9s ease both;
        }
        @keyframes hero-in {
            from { opacity: 0; transform: translateY(-12px); }
            to   { opacity: 1; transform: none; }
        }
        .hero-deco {
            display: flex; align-items: center; justify-content: center;
            gap: 14px; margin-bottom: 1.8rem; color: var(--gold);
            animation: hero-in .9s .15s ease both;
        }
        .hero-deco::before, .hero-deco::after {
            content: ''; width: 64px; height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold-light));
        }
        .hero-deco::after { background: linear-gradient(90deg, var(--gold-light), transparent); }
        .hero h1 {
            font-size: clamp(1.6rem, 4vw, 3rem);
            font-weight: 700; letter-spacing: .04em;
            color: var(--green-dark);
            line-height: 1.3; text-transform: uppercase;
            margin-bottom: 1rem;
            animation: hero-in .9s .25s ease both;
        }
        .hero h1 em {
            font-style: italic; font-family: 'EB Garamond', serif;
            text-transform: none; color: var(--green);
            font-size: 1.12em; letter-spacing: .02em;
        }
        .hero p {
            font-size: 1.05rem; font-style: italic;
            color: var(--muted); letter-spacing: .03em;
            animation: hero-in .9s .35s ease both;
        }
        /* Scroll indicator */
        .hero-scroll {
            position: absolute; bottom: 28px; left: 50%;
            transform: translateX(-50%);
            display: flex; flex-direction: column; align-items: center; gap: 5px;
            color: #bbb;
            font-family: 'Cinzel', serif; font-size: .62rem; letter-spacing: .16em;
            animation: bob 2.5s ease-in-out infinite;
        }
        @keyframes bob {
            0%,100% { transform: translateX(-50%) translateY(0); }
            50%      { transform: translateX(-50%) translateY(7px); }
        }

        /* ══════════════════════════════
           AO VIVO
        ══════════════════════════════ */
        .live-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--red); color: #fff;
            font-family: 'Cinzel', serif; font-size: .74rem;
            font-weight: 600; letter-spacing: .12em;
            padding: 6px 18px; border-radius: 4px;
            margin-bottom: 16px; animation: pulse-red 2s infinite;
        }
        .live-badge::before {
            content: ''; width: 7px; height: 7px;
            background: #fff; border-radius: 50%;
        }
        .video-box {
            position: relative; padding-bottom: 56.25%;
            border-radius: var(--r-lg); overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        .video-box iframe {
            position: absolute; inset: 0;
            width: 100%; height: 100%; border: none;
        }
        .live-link {
            text-align: center; padding: 56px 40px;
            border: 1px solid rgba(255,255,255,.15);
            border-radius: var(--r-lg);
        }
        .live-link p { color: rgba(255,255,255,.6); font-style: italic; margin-bottom: 24px; }
        .btn-yt {
            display: inline-flex; align-items: center; gap: 10px;
            background: var(--red); color: #fff; padding: 13px 30px;
            border-radius: var(--r); font-family: 'Cinzel', serif;
            font-size: .82rem; font-weight: 600; letter-spacing: .08em;
            transition: opacity var(--ease), transform var(--ease);
        }
        .btn-yt:hover { opacity: .88; transform: translateY(-2px); }

        /* ══════════════════════════════
           PROGRAMAÇÃO
        ══════════════════════════════ */
        .prog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(256px, 1fr));
            gap: 22px;
        }
        .prog-card {
            background: var(--white);
            border-radius: var(--r-lg);
            padding: 30px 26px;
            box-shadow: var(--shadow-sm);
            border-top: 3px solid var(--gold-light);
            border-bottom: 1px solid var(--border);
            transition: transform var(--ease), box-shadow var(--ease);
        }
        .prog-card:hover { transform: translateY(-5px); box-shadow: var(--shadow); }
        .prog-icon {
            width: 46px; height: 46px;
            background: var(--green-pale);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; margin-bottom: 18px;
        }
        .prog-day {
            font-family: 'Cinzel', serif; font-size: .68rem;
            font-weight: 600; text-transform: uppercase;
            letter-spacing: .1em; color: var(--gold); margin-bottom: 3px;
        }
        .prog-time {
            font-family: 'Cinzel', serif; font-size: 1.9rem;
            font-weight: 700; color: var(--green-dark);
            line-height: 1; margin-bottom: 8px;
        }
        .prog-name {
            font-family: 'Cinzel', serif; font-size: .86rem;
            font-weight: 600; color: var(--green-dark); margin-bottom: 6px;
        }
        .prog-desc { font-size: .9rem; color: var(--muted); font-style: italic; }

        /* ══════════════════════════════
           REDES SOCIAIS
        ══════════════════════════════ */
        .redes-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }
        .rede-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 36px 20px;
            text-align: center;
            transition: transform var(--ease), box-shadow var(--ease), border-color var(--ease);
            box-shadow: var(--shadow-sm);
        }
        .rede-card:hover { transform: translateY(-6px); box-shadow: var(--shadow); }
        .rede-card.instagram:hover { border-color: #c13584; }
        .rede-card.whatsapp:hover  { border-color: #25D366; }
        .rede-card.youtube:hover   { border-color: #FF0000; }
        .rede-card.facebook:hover  { border-color: #1877F2; }
        .rede-icon-wrap {
            width: 62px; height: 62px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px; font-size: 1.6rem;
        }
        .instagram .rede-icon-wrap { background: #fdf0f8; }
        .whatsapp  .rede-icon-wrap { background: #f0fdf5; }
        .youtube   .rede-icon-wrap { background: #fff0f0; }
        .facebook  .rede-icon-wrap { background: #eff4ff; }
        .rede-name {
            font-family: 'Cinzel', serif; font-weight: 600;
            font-size: .88rem; letter-spacing: .04em;
            color: var(--green-dark); margin-bottom: 4px;
        }
        .rede-handle { font-size: .82rem; color: var(--muted); font-style: italic; }

        /* ══════════════════════════════
           CARROSSEL
        ══════════════════════════════ */
        .carousel-outer { position: relative; }
        .carousel-viewport {
            overflow: hidden;
            border-radius: var(--r-lg);
            border: 1px solid var(--border);
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }
        .carousel-track {
            display: flex;
            transition: transform .65s cubic-bezier(.4,0,.2,1);
        }
        .carousel-slide {
            min-width: 100%;
            padding: 38px 36px 32px;
        }
        .carousel-quote {
            font-size: 1.08rem;
            font-style: italic;
            line-height: 1.8;
            color: var(--text);
            margin-bottom: 18px;
            position: relative;
            padding-left: 20px;
            border-left: 3px solid var(--gold-light);
        }
        .carousel-date {
            font-family: 'Cinzel', serif;
            font-size: .7rem; letter-spacing: .07em;
            color: var(--muted); text-transform: uppercase;
        }
        .carousel-nav {
            display: flex; align-items: center;
            justify-content: center; gap: 7px; margin-top: 16px;
        }
        .c-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: #ccc; border: none; cursor: pointer; padding: 0;
            transition: background var(--ease), transform var(--ease);
        }
        .c-dot.on { background: var(--green); transform: scale(1.3); }
        /* Prev / Next */
        .c-prev, .c-next {
            position: absolute; top: 50%; transform: translateY(-50%);
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--white); border: 1px solid var(--border);
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            box-shadow: var(--shadow-sm); z-index: 2;
            transition: background var(--ease), border-color var(--ease);
            color: var(--green-dark); font-size: .9rem;
        }
        .c-prev { left: -18px; }
        .c-next { right: -18px; }
        .c-prev:hover, .c-next:hover { background: var(--green); color: #fff; border-color: var(--green); }

        /* ══════════════════════════════
           FORMULÁRIOS
        ══════════════════════════════ */
        .forms-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 44px; }
        .form-col { display: flex; flex-direction: column; gap: 28px; }

        .form-box {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 34px;
            box-shadow: var(--shadow-sm);
        }
        .form-box h3 {
            font-size: .92rem; font-weight: 700;
            letter-spacing: .06em; text-transform: uppercase;
            color: var(--green-dark); margin-bottom: 6px;
        }
        .form-box > p { font-size: .9rem; color: var(--muted); font-style: italic; margin-bottom: 18px; }
        .ok-msg {
            background: #edf7ef; color: #1a5c28;
            border-left: 3px solid var(--green);
            padding: 11px 15px; border-radius: 6px;
            font-size: .9rem; font-style: italic; margin-bottom: 16px;
        }
        textarea {
            width: 100%; border: 1px solid var(--border);
            border-radius: var(--r); padding: 13px 15px;
            font-family: 'EB Garamond', serif; font-size: 1rem;
            resize: vertical; min-height: 115px;
            color: var(--text); background: var(--off);
            transition: border-color var(--ease), box-shadow var(--ease), background var(--ease);
        }
        textarea:focus {
            outline: none; border-color: var(--green);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(30,107,53,.09);
        }
        .btn-sub {
            width: 100%; margin-top: 11px; padding: 13px;
            background: var(--green-dark); color: var(--white);
            border: none; border-radius: var(--r);
            font-family: 'Cinzel', serif; font-size: .78rem;
            font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
            cursor: pointer;
            transition: background var(--ease), transform var(--ease), box-shadow var(--ease);
        }
        .btn-sub:hover {
            background: var(--green);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(22,61,34,.2);
        }

        /* ══════════════════════════════
           MAPA
        ══════════════════════════════ */
        .map-box {
            height: 450px; border-radius: var(--r-lg);
            overflow: hidden; box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .map-box iframe { width: 100%; height: 100%; border: none; display: block; }

        /* ══════════════════════════════
           FOOTER
        ══════════════════════════════ */
        footer {
            background: var(--white);
            border-top: 3px solid var(--gold-light);
            padding: 52px 0 28px;
        }
        .foot-inner {
            display: flex; flex-direction: column;
            align-items: center; gap: 22px; text-align: center;
        }
        .foot-logo img  { height: 62px; margin: 0 auto; }
        .foot-logo-txt  {
            display: none; font-family: 'Cinzel', serif;
            font-size: 1.7rem; font-weight: 700; color: var(--green-dark); letter-spacing: .12em;
        }
        .foot-deco {
            display: flex; align-items: center; gap: 14px; color: var(--gold);
            font-size: .9rem; letter-spacing: .2em;
        }
        .foot-deco::before, .foot-deco::after {
            content: ''; width: 48px; height: 1px;
            background: rgba(168,125,40,.3);
        }
        .foot-redes { display: flex; gap: 10px; }
        .foot-rede {
            width: 40px; height: 40px; border-radius: 50%;
            background: var(--green-pale);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            color: var(--green);
            transition: background var(--ease), transform var(--ease), border-color var(--ease), color var(--ease);
        }
        .foot-rede svg { width: 17px; height: 17px; fill: currentColor; flex-shrink: 0; }
        .foot-rede:hover { background: var(--green); border-color: var(--green); color: #fff; transform: translateY(-3px); }
        .foot-hr { width: 100%; height: 1px; background: var(--border); }
        .foot-copy {
            font-size: .8rem; color: var(--muted);
            font-style: italic; letter-spacing: .03em;
        }
        .foot-copy strong { color: var(--green-dark); font-style: normal; }

        /* ══════════════════════════════
           ANIMAÇÕES
        ══════════════════════════════ */
        [data-a] {
            opacity: 0; transform: translateY(22px);
            transition: opacity .65s ease, transform .65s ease;
        }
        [data-a].in { opacity: 1; transform: none; }
        [data-a][data-d="1"] { transition-delay: .10s; }
        [data-a][data-d="2"] { transition-delay: .20s; }
        [data-a][data-d="3"] { transition-delay: .30s; }
        [data-a][data-d="4"] { transition-delay: .40s; }
        [data-a][data-d="5"] { transition-delay: .50s; }

        /* ══════════════════════════════
           RESPONSIVO
        ══════════════════════════════ */
        @media (max-width: 900px) {
            .redes-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .hdr-social { display: none; }
            nav {
                position: fixed; top: 70px; left: 0; right: 0;
                background: var(--white); flex-direction: column;
                padding: 14px; gap: 2px;
                transform: translateY(-110%); transition: transform var(--ease);
                border-bottom: 1px solid var(--border); z-index: 899;
                box-shadow: 0 8px 24px rgba(0,0,0,.08);
            }
            nav.open { transform: translateY(0); }
            nav a { width: 100%; text-align: center; padding: 12px; }
            .burger { display: flex; }
            .sec { padding: 64px 0; }
            .forms-grid { grid-template-columns: 1fr; gap: 28px; }
            .prog-grid { grid-template-columns: 1fr; }
            .c-prev { left: -10px; }
            .c-next { right: -10px; }
        }
        @media (max-width: 520px) {
            .redes-grid { grid-template-columns: 1fr 1fr; }
            .form-box { padding: 22px; }
            .map-box { height: 300px; border-radius: 0; }
            .carousel-slide { padding: 28px 22px; }
        }
    </style>
</head>
<body>

<!-- ═══════════ HEADER ═══════════ -->
<header id="hdr">
    <div class="wrap hdr-inner">
        <a href="#inicio" class="hdr-logo">
            <img src="assets/img/logo.png" alt="NAIOT"
                 onerror="this.style.display='none';document.querySelector('.hdr-logo-txt').style.display='block'">
            <span class="hdr-logo-txt">NAIOT</span>
        </a>

        <button class="burger" id="burger" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>

        <nav id="nav">
            <a href="#inicio">Início</a>
            <a href="#programacao">Programação</a>
            <?php if ($ao_vivo): ?><a href="#ao-vivo" class="btn-live">Ao Vivo</a><?php endif; ?>
            <a href="#oracao">Oração</a>
            <a href="#testemunhos">Testemunhos</a>
        </nav>

        <div class="hdr-social">
            <a href="https://www.instagram.com/naiot_oficial/" target="_blank" rel="noopener" title="Instagram">
                <svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            </a>
            <a href="https://whatsapp.com/channel/0029VaVPbi15Ui2Y5f23h22i" target="_blank" rel="noopener" title="WhatsApp">
                <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
            <a href="https://www.youtube.com/@naiot_oficial4299" target="_blank" rel="noopener" title="YouTube">
                <svg viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </a>
            <a href="https://www.facebook.com/comunidadenaiot/" target="_blank" rel="noopener" title="Facebook">
                <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
        </div>
    </div>
</header>

<!-- ═══════════ HERO ═══════════ -->
<section class="hero" id="inicio">
    <div class="hero-inner">
        <div class="hero-deco"><span>✝</span></div>
        <h1>Nossa missão é estar<br>aos pés de <em>Jesus</em></h1>
        <p>Comunidade Católica Senhor Jesus &mdash; Campo Limpo de Goiás, GO</p>
    </div>
    <div class="hero-scroll" aria-hidden="true">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
</section>

<!-- ═══════════ AO VIVO ═══════════ -->
<?php if ($ao_vivo): ?>
<section class="sec dark" id="ao-vivo">
    <div class="wrap">
        <div class="sec-head" data-a>
            <div class="live-badge">Transmissão ao Vivo</div>
            <h2 class="sec-title light">Ao Vivo</h2>
            <p class="sec-sub light">Acompanhe em tempo real</p>
        </div>
        <?php if (!empty($youtube_video_id)): ?>
        <div class="video-box" data-a>
            <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($youtube_video_id, ENT_QUOTES) ?>?autoplay=1&rel=0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
        </div>
        <?php else: ?>
        <div class="live-link" data-a>
            <p>Estamos ao vivo! Clique abaixo para assistir.</p>
            <a href="https://www.youtube.com/@naiot_oficial4299" target="_blank" rel="noopener" class="btn-yt">
                ▶&ensp;Assistir no YouTube
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════ PROGRAMAÇÃO ═══════════ -->
<section class="sec alt" id="programacao">
    <div class="wrap">
        <div class="sec-head" data-a>
            <div class="deco"><span>✝</span></div>
            <h2 class="sec-title">Programação</h2>
            <p class="sec-sub">Nossa agenda semanal de encontros e transmissões</p>
        </div>
        <div class="prog-grid">
            <div class="prog-card" data-a data-d="1">
                <div class="prog-icon">📺</div>
                <div class="prog-day">Terça-feira</div>
                <div class="prog-time">20h30</div>
                <div class="prog-name">Fortalecendo a Fé</div>
                <p class="prog-desc">Transmissão ao vivo em todas as redes sociais.</p>
            </div>
            <div class="prog-card" data-a data-d="2">
                <div class="prog-icon">🙏</div>
                <div class="prog-day">Quarta-feira</div>
                <div class="prog-time">09h00</div>
                <div class="prog-name">Grupo de Oração</div>
                <p class="prog-desc">Encontro de oração matinal em comunidade.</p>
            </div>
            <div class="prog-card" data-a data-d="3">
                <div class="prog-icon">👤</div>
                <div class="prog-day">Quarta-feira</div>
                <div class="prog-time">13h00</div>
                <div class="prog-name">Atendimento Individual</div>
                <p class="prog-desc">Por ordem de chegada. Sr. Toninho atende os primeiros 15.</p>
            </div>
            <div class="prog-card" data-a data-d="4">
                <div class="prog-icon">✨</div>
                <div class="prog-day">Quarta-feira</div>
                <div class="prog-time">19h00</div>
                <div class="prog-name">Grupo de Oração</div>
                <p class="prog-desc">Encontro noturno de louvor e gratidão.</p>
            </div>
            <div class="prog-card" data-a data-d="5">
                <div class="prog-icon">📅</div>
                <div class="prog-day">Finais de semana</div>
                <div class="prog-time">Eventos</div>
                <div class="prog-name">Conforme Calendário</div>
                <p class="prog-desc">Acompanhe as redes sociais para eventos especiais.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ CARROSSÉIS ═══════════ -->
<section class="sec pale" id="oracao">
    <div class="wrap">
        <div class="sec-head" data-a>
            <div class="deco"><span>✝</span></div>
            <h2 class="sec-title">Oração &amp; Testemunhos</h2>
            <p class="sec-sub">Palavras de fé que edificam e inspiram</p>
        </div>

        <div class="forms-grid" style="margin-bottom: 64px;">

            <!-- Carrossel Oração -->
            <div data-a data-d="1">
                <p style="font-family:'Cinzel',serif;font-size:.72rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--gold);margin-bottom:14px;">
                    ✝ &ensp;Pedidos de Oração
                </p>
                <?php if (!empty($oracoes)): ?>
                <div class="carousel-outer">
                    <button class="c-prev" data-target="c-oracao">&#8249;</button>
                    <button class="c-next" data-target="c-oracao">&#8250;</button>
                    <div class="carousel-viewport">
                        <div class="carousel-track" id="c-oracao">
                            <?php foreach ($oracoes as $item): ?>
                            <div class="carousel-slide">
                                <p class="carousel-quote"><?= nl2br(htmlspecialchars($item['texto'])) ?></p>
                                <span class="carousel-date"><?= htmlspecialchars($item['data']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="carousel-nav" id="dots-oracao">
                        <?php foreach ($oracoes as $i => $_): ?>
                        <button class="c-dot<?= $i===0?' on':'' ?>" data-i="<?=$i?>"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <p style="font-style:italic;color:var(--muted);">Nenhum pedido ainda.</p>
                <?php endif; ?>
            </div>

            <!-- Carrossel Testemunhos -->
            <div id="testemunhos" data-a data-d="2">
                <p style="font-family:'Cinzel',serif;font-size:.72rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--gold);margin-bottom:14px;">
                    ✝ &ensp;Testemunhos
                </p>
                <?php if (!empty($testemunhos)): ?>
                <div class="carousel-outer">
                    <button class="c-prev" data-target="c-test">&#8249;</button>
                    <button class="c-next" data-target="c-test">&#8250;</button>
                    <div class="carousel-viewport">
                        <div class="carousel-track" id="c-test">
                            <?php foreach ($testemunhos as $item): ?>
                            <div class="carousel-slide">
                                <p class="carousel-quote"><?= nl2br(htmlspecialchars($item['texto'])) ?></p>
                                <span class="carousel-date"><?= htmlspecialchars($item['data']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="carousel-nav" id="dots-test">
                        <?php foreach ($testemunhos as $i => $_): ?>
                        <button class="c-dot<?= $i===0?' on':'' ?>" data-i="<?=$i?>"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <p style="font-style:italic;color:var(--muted);">Nenhum testemunho ainda.</p>
                <?php endif; ?>
            </div>

        </div>

        <!-- Formulários -->
        <div class="forms-grid">
            <div class="form-col" data-a data-d="1">
                <div class="form-box">
                    <h3>Enviar Pedido de Oração</h3>
                    <p>Compartilhe sua intenção de forma anônima. Nossa comunidade orará por você.</p>
                    <?php if (isset($_GET['oracao']) && $_GET['oracao']==='ok'): ?>
                    <div class="ok-msg">Seu pedido foi recebido. Vamos orar por você!</div>
                    <?php endif; ?>
                    <form method="POST">
                        <textarea name="oracao" placeholder="Escreva seu pedido de oração..." required maxlength="1000"></textarea>
                        <button type="submit" class="btn-sub">Enviar Pedido</button>
                    </form>
                </div>
            </div>
            <div class="form-col" data-a data-d="2">
                <div class="form-box">
                    <h3>Compartilhar Testemunho</h3>
                    <p>Compartilhe as graças e milagres que Deus realizou em sua vida.</p>
                    <?php if (isset($_GET['testemunho']) && $_GET['testemunho']==='ok'): ?>
                    <div class="ok-msg">Seu testemunho foi compartilhado. Que Deus seja glorificado!</div>
                    <?php endif; ?>
                    <form method="POST">
                        <textarea name="testemunho" placeholder="Compartilhe seu testemunho..." required maxlength="2000"></textarea>
                        <button type="submit" class="btn-sub">Compartilhar Testemunho</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- ═══════════ MAPA ═══════════ -->
<section class="sec alt" id="mapa" style="padding-bottom: 0;">
    <div class="wrap">
        <div class="sec-head" data-a>
            <div class="deco"><span>✝</span></div>
            <h2 class="sec-title">Como Chegar</h2>
            <p class="sec-sub">Comunidade NAIOT &mdash; Campo Limpo de Goiás, GO</p>
        </div>
        <div class="map-box" data-a>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3830.1820033058525!2d-49.14189992509153!3d-16.26243988444542!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x935c276d0e8c03cf%3A0x7bfd94fb797d6e1b!2sComunidade%20NAIOT%20(Novo%20Acesso)!5e0!3m2!1spt-BR!2sbr!4v1754773032917!5m2!1spt-BR!2sbr"
                allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>

<!-- ═══════════ FOOTER ═══════════ -->
<footer>
    <div class="wrap foot-inner">
        <div class="foot-logo">
            <img src="assets/img/logo.png" alt="NAIOT"
                 onerror="this.style.display='none';document.querySelector('.foot-logo-txt').style.display='block'">
            <span class="foot-logo-txt">NAIOT</span>
        </div>
        <div class="foot-deco"><span>✝</span></div>
        <div class="foot-redes">
            <a href="https://www.instagram.com/naiot_oficial/" target="_blank" rel="noopener" class="foot-rede" title="Instagram">
                <svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            </a>
            <a href="https://whatsapp.com/channel/0029VaVPbi15Ui2Y5f23h22i" target="_blank" rel="noopener" class="foot-rede" title="WhatsApp">
                <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
            <a href="https://www.youtube.com/@naiot_oficial4299" target="_blank" rel="noopener" class="foot-rede" title="YouTube">
                <svg viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </a>
            <a href="https://www.facebook.com/comunidadenaiot/" target="_blank" rel="noopener" class="foot-rede" title="Facebook">
                <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
        </div>
        <div class="foot-hr"></div>
        <p class="foot-copy">© 2026 <strong>NAIOT</strong> — Comunidade Católica Senhor Jesus. Todos os direitos reservados.</p>
    </div>
</footer>

<!-- ═══════════ SCRIPTS ═══════════ -->
<script>
/* ── Menu mobile ── */
const burger = document.getElementById('burger');
const nav    = document.getElementById('nav');
burger.addEventListener('click', () => {
    burger.classList.toggle('open');
    nav.classList.toggle('open');
});
nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    burger.classList.remove('open');
    nav.classList.remove('open');
}));

/* ── Header shadow ── */
const hdr = document.getElementById('hdr');
window.addEventListener('scroll', () => hdr.classList.toggle('scrolled', scrollY > 30), { passive: true });

/* ── Scroll animations ── */
const io = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
}, { threshold: 0.08, rootMargin: '0px 0px -36px 0px' });
document.querySelectorAll('[data-a]').forEach(el => io.observe(el));

/* ── Carrossel ── */
class Carousel {
    constructor(trackId, dotsId, delay = 5500) {
        this.track  = document.getElementById(trackId);
        this.dotsEl = document.getElementById(dotsId);
        if (!this.track) return;
        this.slides = this.track.querySelectorAll('.carousel-slide');
        this.dots   = this.dotsEl ? this.dotsEl.querySelectorAll('.c-dot') : [];
        this.n      = this.slides.length;
        this.cur    = 0;
        this.timer  = null;
        this.delay  = delay;

        /* Dots */
        this.dots.forEach((d, i) => d.addEventListener('click', () => this.go(i)));

        /* Prev / Next */
        const outer = this.track.closest('.carousel-outer');
        outer.querySelector('.c-prev')?.addEventListener('click', () => this.go((this.cur - 1 + this.n) % this.n));
        outer.querySelector('.c-next')?.addEventListener('click', () => this.go((this.cur + 1) % this.n));

        /* Pause on hover */
        outer.addEventListener('mouseenter', () => clearInterval(this.timer));
        outer.addEventListener('mouseleave', () => this.startAuto());

        this.startAuto();
    }
    go(i) {
        this.cur = i;
        this.track.style.transform = `translateX(-${i * 100}%)`;
        this.dots.forEach((d, j) => d.classList.toggle('on', j === i));
        clearInterval(this.timer);
        this.startAuto();
    }
    startAuto() {
        this.timer = setInterval(() => this.go((this.cur + 1) % this.n), this.delay);
    }
}

new Carousel('c-oracao', 'dots-oracao', 5500);
new Carousel('c-test',   'dots-test',   6500);
</script>

</body>
</html>
