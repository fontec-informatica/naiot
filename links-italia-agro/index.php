<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Itália Agro — Links</title>
    <meta name="description" content="Todos os links oficiais da Itália Agro — implementos agrícolas fabricados em Anápolis, GO.">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:title" content="Itália Agro — Links Oficiais">
    <meta property="og:description" content="Acesse nosso site, Instagram, WhatsApp e catálogo de produtos.">
    <meta property="og:image" content="https://www.italiaagro.com.br/logo.png">
    <meta property="og:url" content="https://naiot.com.br/links-italia-agro">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --verde:       #2e7d32;
            --verde-mid:   #388e3c;
            --verde-claro: #4caf50;
            --verde-pale:  #f4fbf4;
            --verde-line:  #dcedc8;
            --preto:       #222;
            --cinza:       #555;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--verde-pale);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 48px 20px 60px;
        }

        /* Background decorativo */
        body::before {
            content: '';
            position: fixed;
            top: -120px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(76,175,80,.15) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .wrapper {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeDown .6s ease both;
        }

        .logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 96px;
            height: 96px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 4px 24px rgba(46,125,50,.15);
            margin-bottom: 16px;
            border: 3px solid var(--verde-line);
        }

        .logo-wrap img {
            width: 72px;
            height: auto;
        }

        .header h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 26px;
            color: var(--preto);
            margin-bottom: 6px;
        }

        .header p {
            font-size: 14px;
            color: var(--cinza);
            font-weight: 400;
        }

        .badge {
            display: inline-block;
            margin-top: 10px;
            background: var(--verde);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 20px;
        }

        /* Links */
        .links {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .link-btn {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #fff;
            border: 1.5px solid var(--verde-line);
            border-radius: 16px;
            padding: 16px 20px;
            text-decoration: none;
            color: var(--preto);
            font-weight: 500;
            font-size: 15px;
            transition: all .25s;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
            opacity: 0;
            transform: translateY(16px);
        }

        .link-btn:hover {
            border-color: var(--verde);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(46,125,50,.12);
            color: var(--verde);
        }

        .link-btn .icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .link-btn .text {
            flex: 1;
        }

        .link-btn .text span {
            display: block;
            font-size: 12px;
            font-weight: 400;
            color: #999;
            margin-top: 1px;
        }

        .link-btn .arrow {
            font-size: 14px;
            color: #ccc;
            transition: transform .2s, color .2s;
        }

        .link-btn:hover .arrow {
            transform: translateX(3px);
            color: var(--verde);
        }

        /* Cores dos ícones */
        .icon-site     { background: #e8f5e9; color: #2e7d32; }
        .icon-whatsapp { background: #e8f8ef; color: #25d366; }
        .icon-instagram{ background: #fce4ec; color: #e1306c; }
        .icon-canal    { background: #e8f8ef; color: #25d366; }
        .icon-catalogo { background: #fff8e1; color: #f57c00; }

        /* Destaque catálogo removido — padrão igual aos demais */

        /* Footer */
        .footer-links {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #aaa;
            animation: fadeUp .6s .8s ease both;
        }

        .footer-links a {
            color: var(--verde);
            text-decoration: none;
            font-weight: 500;
        }

        .footer-links a:hover { text-decoration: underline; }

        /* Animations */
        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            to { opacity: 1; transform: translateY(0); }
        }

        .link-btn:nth-child(1) { animation: slideIn .4s .15s ease forwards; }
        .link-btn:nth-child(2) { animation: slideIn .4s .25s ease forwards; }
        .link-btn:nth-child(3) { animation: slideIn .4s .35s ease forwards; }
        .link-btn:nth-child(4) { animation: slideIn .4s .45s ease forwards; }
        .link-btn:nth-child(5) { animation: slideIn .4s .55s ease forwards; }
        .link-btn:nth-child(6) { animation: slideIn .4s .65s ease forwards; }
    </style>
</head>
<body>

<div class="wrapper">

    <div class="header">
        <div class="logo-wrap">
            <img src="https://www.italiaagro.com.br/logo.png" alt="Itália Agro">
        </div>
        <h1>Itália Agro</h1>
        <p>Implementos Agrícolas — Anápolis, GO</p>
        <span class="badge">🌿 Links Oficiais</span>
    </div>

    <div class="links">

        <a class="link-btn" href="https://www.italiaagro.com.br" target="_blank">
            <div class="icon icon-site"><i class="fas fa-globe"></i></div>
            <div class="text">
                Site Oficial
                <span>www.italiaagro.com.br</span>
            </div>
            <i class="fas fa-chevron-right arrow"></i>
        </a>

        <a class="link-btn" href="https://wa.me/5562999212921?text=Olá,%20vim%20pelo%20link%20e%20gostaria%20de%20mais%20informações." target="_blank">
            <div class="icon icon-whatsapp"><i class="fab fa-whatsapp"></i></div>
            <div class="text">
                Falar com vendedor Rodrigo
                <span>(62) 99921-2921</span>
            </div>
            <i class="fas fa-chevron-right arrow"></i>
        </a>

        <a class="link-btn" href="https://wa.me/5562996801587?text=Olá,%20vim%20pelo%20link%20e%20gostaria%20de%20mais%20informações." target="_blank">
            <div class="icon icon-whatsapp"><i class="fab fa-whatsapp"></i></div>
            <div class="text">
                Falar com vendedor Rafael
                <span>(62) 99680-1587</span>
            </div>
            <i class="fas fa-chevron-right arrow"></i>
        </a>

        <a class="link-btn" href="https://www.instagram.com/industriaitalia/" target="_blank">
            <div class="icon icon-instagram"><i class="fab fa-instagram"></i></div>
            <div class="text">
                Instagram
                <span>@industriaitalia</span>
            </div>
            <i class="fas fa-chevron-right arrow"></i>
        </a>

        <a class="link-btn" href="https://whatsapp.com/channel/0029Vb6xknd9MF957LIYVV3I" target="_blank">
            <div class="icon icon-canal"><i class="fab fa-whatsapp"></i></div>
            <div class="text">
                Canal no WhatsApp
                <span>Acessar canal agora</span>
            </div>
            <i class="fas fa-chevron-right arrow"></i>
        </a>

        <a class="link-btn" href="https://www.italiaagro.com.br/catalogo-italia-agro.pdf" target="_blank">
            <div class="icon icon-catalogo"><i class="fas fa-book"></i></div>
            <div class="text">
                Catálogo de Produtos
                <span>Abrir PDF completo</span>
            </div>
            <i class="fas fa-chevron-right arrow"></i>
        </a>

    </div>

    <div class="footer-links">
        <p>© <?php echo date('Y'); ?> Itália Agro · Rod. GO-330, KM 32 · Anápolis – GO</p>
        <p style="margin-top:6px"><a href="https://www.italiaagro.com.br">italiaagro.com.br</a></p>
    </div>

</div>

</body>
</html>
