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
    <meta name="theme-color" content="#163d22">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&display=swap" rel="stylesheet">
    <style>
        /* ===== VARIÁVEIS ===== */
        :root {
            --green-dark:  #163d22;
            --green:       #1e6b35;
            --green-mid:   #2a7d3f;
            --gold:        #b8962e;
            --gold-light:  #d4b050;
            --cream:       #f8f5ee;
            --cream-alt:   #efe9dc;
            --white:       #ffffff;
            --text:        #1c1c1c;
            --muted:       #5a5550;
            --red:         #c0392b;
            --shadow:      0 4px 24px rgba(22,61,34,.12);
            --shadow-lg:   0 14px 52px rgba(22,61,34,.22);
            --radius:      8px;
            --radius-lg:   16px;
            --ease:        0.35s cubic-bezier(.4,0,.2,1);
        }

        /* ===== RESET ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'EB Garamond', Georgia, serif;
            background: var(--cream);
            color: var(--text);
            line-height: 1.75;
            overflow-x: hidden;
            font-size: 17px;
        }
        img  { max-width: 100%; height: auto; display: block; }
        a    { text-decoration: none; color: inherit; }
        ul   { list-style: none; }

        /* ===== UTILITÁRIOS ===== */
        .container { width: 100%; max-width: 1140px; margin: 0 auto; padding: 0 28px; }
        h1, h2, h3, h4, nav a, .btn-submit, .btn-channel { font-family: 'Cinzel', serif; }

        .section-wrap { padding: 96px 0; }
        .section-wrap.alt  { background: var(--cream-alt); }
        .section-wrap.dark { background: var(--green-dark); }

        .sec-header { text-align: center; margin-bottom: 60px; }
        .ornament {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin-bottom: 18px;
            color: var(--gold);
            font-size: 1.1rem;
            letter-spacing: .2em;
        }
        .ornament::before, .ornament::after {
            content: '';
            width: 60px; height: 1px;
            background: var(--gold);
            opacity: .6;
        }
        .sec-title {
            font-size: clamp(1.7rem, 3.5vw, 2.6rem);
            font-weight: 700;
            color: var(--green-dark);
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .sec-title.light { color: var(--cream); }
        .sec-sub {
            margin-top: 14px;
            color: var(--muted);
            font-size: 1.05rem;
            font-style: italic;
        }
        .sec-sub.light { color: rgba(248,245,238,.65); }

        /* ===== HEADER ===== */
        #header {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 900;
            background: rgba(22,61,34,.97);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(184,150,46,.25);
            transition: box-shadow var(--ease);
        }
        #header.scrolled { box-shadow: 0 4px 32px rgba(0,0,0,.4); }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }
        .header-logo img {
            height: 46px;
            width: auto;
            filter: brightness(0) invert(1);
        }
        .header-logo-text {
            display: none;
            font-family: 'Cinzel', serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--cream);
            letter-spacing: .1em;
        }

        nav { display: flex; align-items: center; gap: 2px; }
        nav a {
            color: rgba(248,245,238,.80);
            font-size: .78rem;
            font-weight: 600;
            padding: 8px 14px;
            border-radius: 4px;
            transition: color var(--ease), background var(--ease);
            letter-spacing: .08em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        nav a:hover { color: var(--gold-light); background: rgba(184,150,46,.1); }

        .btn-live {
            display: inline-flex !important;
            align-items: center;
            gap: 7px;
            background: var(--red) !important;
            color: #fff !important;
            padding: 7px 16px !important;
            border-radius: 3px !important;
            font-size: .75rem !important;
            animation: pulse-live 2s ease infinite;
        }
        .btn-live::before {
            content: '';
            width: 7px; height: 7px;
            background: #fff;
            border-radius: 50%;
            flex-shrink: 0;
        }
        @keyframes pulse-live {
            0%   { box-shadow: 0 0 0 0 rgba(192,57,43,.75); }
            70%  { box-shadow: 0 0 0 10px rgba(192,57,43,0); }
            100% { box-shadow: 0 0 0 0 rgba(192,57,43,0); }
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
            background: var(--cream);
            border-radius: 1px;
            transition: var(--ease);
        }
        .hamburger.open span:nth-child(1) { transform: rotate(45deg) translate(5px,5px); }
        .hamburger.open span:nth-child(2) { opacity: 0; }
        .hamburger.open span:nth-child(3) { transform: rotate(-45deg) translate(5px,-5px); }

        /* ===== HERO ===== */
        .hero {
            min-height: 100vh;
            background: var(--green-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            padding: 120px 28px 80px;
        }
        /* Padrão de textura sutil */
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 90% 60% at 50% 0%,   rgba(184,150,46,.10) 0%, transparent 65%),
                radial-gradient(circle at 10% 90%, rgba(184,150,46,.07) 0%, transparent 40%),
                radial-gradient(circle at 90% 10%, rgba(184,150,46,.07) 0%, transparent 40%);
            pointer-events: none;
        }
        .hero::after {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='80' height='80' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23b8962e' fill-opacity='.04'%3E%3Cpath d='M40 0v80M0 40h80' stroke='%23b8962e' stroke-opacity='.04' stroke-width='1'/%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        .hero-content { position: relative; z-index: 1; max-width: 760px; }

        .hero-logo {
            width: min(220px, 50vw);
            margin: 0 auto 2.8rem;
            filter: brightness(0) invert(1);
            opacity: .95;
            animation: fade-down .9s ease both;
        }
        @keyframes fade-down {
            from { opacity: 0; transform: translateY(-16px); }
            to   { opacity: .95; transform: translateY(0); }
        }

        .hero-ornament {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 2rem;
            color: var(--gold);
            font-size: 1.2rem;
            letter-spacing: .3em;
            animation: fade-up .9s .2s ease both;
        }
        .hero-ornament::before, .hero-ornament::after {
            content: '';
            width: 70px; height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold));
            opacity: .7;
        }
        .hero-ornament::after { background: linear-gradient(90deg, var(--gold), transparent); }

        .hero h1 {
            font-size: clamp(1.7rem, 4vw, 3rem);
            font-weight: 700;
            color: var(--cream);
            margin-bottom: 1.2rem;
            letter-spacing: .05em;
            line-height: 1.3;
            text-transform: uppercase;
            animation: fade-up .9s .3s ease both;
        }
        .hero h1 em {
            font-style: italic;
            font-family: 'EB Garamond', serif;
            color: var(--gold-light);
            text-transform: none;
            font-size: 1.15em;
            letter-spacing: .03em;
        }
        .hero p {
            color: rgba(248,245,238,.65);
            font-size: clamp(1rem, 1.8vw, 1.15rem);
            font-style: italic;
            letter-spacing: .04em;
            animation: fade-up .9s .4s ease both;
        }
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero-scroll {
            position: absolute;
            bottom: 30px; left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: rgba(248,245,238,.3);
            font-family: 'Cinzel', serif;
            font-size: .65rem;
            letter-spacing: .18em;
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
            padding: 6px 20px;
            border-radius: 3px;
            font-family: 'Cinzel', serif;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .12em;
            margin-bottom: 18px;
            animation: pulse-live 2s ease infinite;
        }
        .live-badge::before {
            content: '';
            width: 7px; height: 7px;
            background: #fff;
            border-radius: 50%;
        }
        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(0,0,0,.5);
            border: 1px solid rgba(184,150,46,.2);
        }
        .video-wrapper iframe {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border: none;
        }
        .live-fallback {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(184,150,46,.25);
            border-radius: var(--radius-lg);
            padding: 64px 40px;
            text-align: center;
        }
        .live-fallback p { color: rgba(248,245,238,.6); margin-bottom: 28px; font-style: italic; }
        .btn-channel {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--red);
            color: #fff;
            padding: 14px 32px;
            border-radius: var(--radius);
            font-size: .85rem;
            font-weight: 600;
            letter-spacing: .08em;
            transition: opacity var(--ease), transform var(--ease);
        }
        .btn-channel:hover { opacity: .88; transform: translateY(-2px); }

        /* ===== PROGRAMAÇÃO ===== */
        .prog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }
        .prog-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 32px 28px;
            box-shadow: var(--shadow);
            border-top: 3px solid var(--gold);
            position: relative;
            overflow: hidden;
            transition: transform var(--ease), box-shadow var(--ease);
        }
        .prog-card::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 100px; height: 100px;
            background: radial-gradient(circle, rgba(30,107,53,.06) 0%, transparent 70%);
            transform: translate(30%, -30%);
            pointer-events: none;
        }
        .prog-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
        .prog-icon {
            width: 50px; height: 50px;
            background: var(--green-dark);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 20px;
        }
        .prog-day {
            font-family: 'Cinzel', serif;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--gold);
            margin-bottom: 4px;
        }
        .prog-time {
            font-family: 'Cinzel', serif;
            font-size: 1.9rem;
            font-weight: 900;
            color: var(--green-dark);
            line-height: 1;
            margin-bottom: 10px;
        }
        .prog-name {
            font-family: 'Cinzel', serif;
            font-weight: 600;
            font-size: .9rem;
            color: var(--green-dark);
            margin-bottom: 8px;
            letter-spacing: .03em;
        }
        .prog-desc { font-size: .92rem; color: var(--muted); line-height: 1.6; font-style: italic; }

        /* ===== REDES SOCIAIS ===== */
        .redes-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .rede-card {
            border-radius: var(--radius-lg);
            padding: 40px 20px;
            text-align: center;
            color: #fff;
            transition: transform var(--ease), box-shadow var(--ease);
            box-shadow: var(--shadow);
            cursor: pointer;
        }
        .rede-card:hover { transform: translateY(-7px) scale(1.02); box-shadow: var(--shadow-lg); }
        .rede-card.instagram { background: linear-gradient(135deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); }
        .rede-card.whatsapp  { background: linear-gradient(135deg, #25D366, #128C7E); }
        .rede-card.youtube   { background: linear-gradient(135deg, #FF0000, #b30000); }
        .rede-card.facebook  { background: linear-gradient(135deg, #1877F2, #0a52cc); }
        .rede-icon { font-size: 2.4rem; margin-bottom: 14px; }
        .rede-name {
            font-family: 'Cinzel', serif;
            font-weight: 700;
            font-size: .95rem;
            margin-bottom: 4px;
            letter-spacing: .05em;
        }
        .rede-handle { font-size: .82rem; opacity: .85; font-style: italic; }

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
            border-top: 3px solid var(--gold);
        }
        .form-box h3 {
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            color: var(--green-dark);
            margin-bottom: 6px;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .form-box > p { font-size: .95rem; color: var(--muted); margin-bottom: 20px; font-style: italic; }
        .success-msg {
            background: #d4edda;
            color: #155724;
            border-left: 3px solid #28a745;
            padding: 12px 16px;
            border-radius: var(--radius);
            font-size: .92rem;
            margin-bottom: 16px;
            font-style: italic;
        }
        textarea {
            width: 100%;
            border: 1px solid #d8cfc3;
            border-radius: var(--radius);
            padding: 14px 16px;
            font-family: 'EB Garamond', serif;
            font-size: 1rem;
            resize: vertical;
            min-height: 120px;
            color: var(--text);
            background: var(--cream);
            transition: border-color var(--ease), box-shadow var(--ease), background var(--ease);
        }
        textarea:focus {
            outline: none;
            border-color: var(--green);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(30,107,53,.10);
        }
        .btn-submit {
            width: 100%;
            margin-top: 12px;
            padding: 14px;
            background: var(--green-dark);
            color: var(--cream);
            border: none;
            border-radius: var(--radius);
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: .1em;
            text-transform: uppercase;
            transition: background var(--ease), transform var(--ease), box-shadow var(--ease);
        }
        .btn-submit:hover {
            background: var(--green);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(22,61,34,.25);
        }

        /* Cards de submissões */
        .cards-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .cards-header h4 {
            font-family: 'Cinzel', serif;
            font-size: .88rem;
            color: var(--green-dark);
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .cards-count {
            background: var(--green-dark);
            color: var(--gold-light);
            font-family: 'Cinzel', serif;
            font-size: .75rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 3px;
        }
        .submissions-scroll {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 6px;
        }
        .submissions-scroll::-webkit-scrollbar { width: 3px; }
        .submissions-scroll::-webkit-scrollbar-track { background: var(--cream-alt); }
        .submissions-scroll::-webkit-scrollbar-thumb { background: var(--gold); }
        .sub-card {
            background: var(--cream);
            border-radius: var(--radius);
            padding: 18px 20px;
            border-left: 3px solid var(--gold);
        }
        .sub-card p { font-size: .95rem; color: var(--text); line-height: 1.65; margin-bottom: 8px; font-style: italic; }
        .sub-card span { font-size: .76rem; color: var(--muted); font-family: 'Cinzel', serif; letter-spacing: .04em; }
        .empty-state {
            text-align: center;
            color: var(--muted);
            font-size: .95rem;
            padding: 28px;
            background: var(--white);
            border-radius: var(--radius);
            font-style: italic;
            border: 1px dashed #ccc4b4;
        }

        /* ===== MAPA ===== */
        .mapa-wrap {
            height: 460px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--cream-alt);
        }
        .mapa-wrap iframe { width: 100%; height: 100%; border: none; display: block; }

        /* ===== FOOTER ===== */
        footer {
            background: var(--green-dark);
            border-top: 1px solid rgba(184,150,46,.2);
            padding: 64px 0 32px;
        }
        .footer-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 28px;
            text-align: center;
        }
        .footer-logo img {
            height: 58px;
            filter: brightness(0) invert(1);
            opacity: .88;
            margin: 0 auto;
        }
        .footer-logo-text {
            display: none;
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--cream);
            letter-spacing: .12em;
        }
        .footer-ornament {
            display: flex;
            align-items: center;
            gap: 14px;
            color: var(--gold);
            font-size: 1rem;
            letter-spacing: .2em;
        }
        .footer-ornament::before, .footer-ornament::after {
            content: '';
            width: 50px; height: 1px;
            background: var(--gold);
            opacity: .4;
        }
        .footer-redes { display: flex; gap: 14px; }
        .footer-rede {
            width: 42px; height: 42px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(184,150,46,.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem;
            transition: background var(--ease), transform var(--ease);
        }
        .footer-rede:hover { background: var(--gold); transform: translateY(-3px); }
        .footer-hr { width: 100%; height: 1px; background: rgba(255,255,255,.07); }
        .footer-copy {
            font-size: .82rem;
            color: rgba(248,245,238,.4);
            font-style: italic;
            letter-spacing: .03em;
        }
        .footer-copy strong { color: var(--gold); font-style: normal; }

        /* ===== ANIMAÇÕES ===== */
        [data-anim] {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity .7s ease, transform .7s ease;
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
                top: 72px; left: 0; right: 0;
                background: var(--green-dark);
                flex-direction: column;
                padding: 16px;
                gap: 4px;
                transform: translateY(-110%);
                transition: transform var(--ease);
                border-bottom: 1px solid rgba(184,150,46,.15);
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
            <a href="#ao-vivo" class="btn-live">Ao Vivo</a>
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
        <div class="hero-ornament">✝</div>
        <h1>Nossa missão é estar<br>aos pés de <em>Jesus</em></h1>
        <p>Comunidade Católica Senhor Jesus &mdash; Campo Limpo de Goiás, GO</p>
    </div>
    <div class="hero-scroll" aria-hidden="true">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
</section>

<!-- ===================== AO VIVO (condicional) ===================== -->
<?php if ($ao_vivo): ?>
<section class="section-wrap dark" id="ao-vivo">
    <div class="container">
        <div class="sec-header" data-anim>
            <div class="live-badge">Ao Vivo</div>
            <h2 class="sec-title light">Transmissão ao Vivo</h2>
            <p class="sec-sub light">Acompanhe nossa programação em tempo real</p>
        </div>
        <?php if (!empty($youtube_video_id)): ?>
        <div class="video-wrapper" data-anim>
            <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($youtube_video_id, ENT_QUOTES) ?>?autoplay=1&rel=0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
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
<section class="section-wrap" id="programacao">
    <div class="container">
        <div class="sec-header" data-anim>
            <div class="ornament">✝</div>
            <h2 class="sec-title">Programação</h2>
            <p class="sec-sub">Nossa agenda semanal de encontros e transmissões</p>
        </div>
        <div class="prog-grid">

            <div class="prog-card" data-anim data-d="1">
                <div class="prog-icon">📺</div>
                <div class="prog-day">Terça-feira</div>
                <div class="prog-time">20h30</div>
                <div class="prog-name">Fortalecendo a Fé</div>
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
            <div class="ornament" style="color:var(--gold)">✝</div>
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
<section class="section-wrap alt" id="oracao">
    <div class="container">
        <div class="sec-header" data-anim>
            <div class="ornament">✝</div>
            <h2 class="sec-title">Oração &amp; Testemunhos</h2>
            <p class="sec-sub">Compartilhe suas intenções e as graças recebidas</p>
        </div>

        <div class="forms-grid">

            <!-- COLUNA: PEDIDO DE ORAÇÃO -->
            <div class="form-col">
                <div class="form-box" data-anim data-d="1">
                    <h3>Pedido de Oração</h3>
                    <p>Compartilhe sua intenção de forma anônima. Nossa comunidade orará por você.</p>
                    <?php if (isset($_GET['oracao']) && $_GET['oracao'] === 'ok'): ?>
                    <div class="success-msg">Seu pedido foi enviado. Vamos orar por você!</div>
                    <?php endif; ?>
                    <form method="POST">
                        <textarea name="oracao" placeholder="Escreva seu pedido de oração..." required maxlength="1000"></textarea>
                        <button type="submit" class="btn-submit">Enviar Pedido</button>
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
                            <span><?= htmlspecialchars($item['data']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state" data-anim data-d="2">Nenhum pedido ainda. Seja o primeiro a compartilhar.</div>
                <?php endif; ?>
            </div>

            <!-- COLUNA: TESTEMUNHOS -->
            <div class="form-col" id="testemunhos">
                <div class="form-box" data-anim data-d="3">
                    <h3>Compartilhar Testemunho</h3>
                    <p>Compartilhe as graças e milagres que Deus realizou em sua vida.</p>
                    <?php if (isset($_GET['testemunho']) && $_GET['testemunho'] === 'ok'): ?>
                    <div class="success-msg">Seu testemunho foi compartilhado. Que Deus seja glorificado!</div>
                    <?php endif; ?>
                    <form method="POST">
                        <textarea name="testemunho" placeholder="Compartilhe seu testemunho..." required maxlength="2000"></textarea>
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
                            <span><?= htmlspecialchars($item['data']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state" data-anim data-d="4">Nenhum testemunho ainda. Compartilhe o que Deus fez por você!</div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<!-- ===================== MAPA ===================== -->
<section class="section-wrap" id="mapa" style="padding-bottom: 0;">
    <div class="container">
        <div class="sec-header" data-anim>
            <div class="ornament">✝</div>
            <h2 class="sec-title">Como Chegar</h2>
            <p class="sec-sub">Comunidade NAIOT — Campo Limpo de Goiás, GO</p>
        </div>
        <div class="mapa-wrap" data-anim>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3830.1820033058525!2d-49.14189992509153!3d-16.26243988444542!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x935c276d0e8c03cf%3A0x7bfd94fb797d6e1b!2sComunidade%20NAIOT%20(Novo%20Acesso)!5e0!3m2!1spt-BR!2sbr!4v1754773032917!5m2!1spt-BR!2sbr"
                allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
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

        <div class="footer-ornament">✝</div>

        <div class="footer-redes">
            <a href="https://www.instagram.com/naiot_oficial/" target="_blank" rel="noopener" class="footer-rede" title="Instagram">📷</a>
            <a href="https://whatsapp.com/channel/0029VaVPbi15Ui2Y5f23h22i" target="_blank" rel="noopener" class="footer-rede" title="WhatsApp">💬</a>
            <a href="https://www.youtube.com/@naiot_oficial4299" target="_blank" rel="noopener" class="footer-rede" title="YouTube">▶️</a>
            <a href="https://www.facebook.com/comunidadenaiot/" target="_blank" rel="noopener" class="footer-rede" title="Facebook">👥</a>
        </div>

        <div class="footer-hr"></div>

        <p class="footer-copy">
            © 2026 <strong>NAIOT</strong> — Comunidade Católica Senhor Jesus. Todos os direitos reservados.
        </p>

    </div>
</footer>

<!-- ===================== SCRIPTS ===================== -->
<script>
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

    const header = document.getElementById('header');
    window.addEventListener('scroll', () => {
        header.classList.toggle('scrolled', window.scrollY > 30);
    }, { passive: true });

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
