<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAIOT — Links</title>
    <meta name="description" content="Todos os links oficiais da NAIOT — Comunidade Católica Senhor Jesus, Campo Limpo de Goiás, GO.">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:title" content="NAIOT — Links Oficiais">
    <meta property="og:description" content="Acesse nosso site, Instagram, canal no WhatsApp e localização.">
    <meta property="og:image" content="https://naiot.com.br/assets/img/logo.png">
    <meta property="og:url" content="https://naiot.com.br/links">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green:       #1e6b35;
            --green-dk:    #163d22;
            --green-pale:  #f0f7f2;
            --green-line:  #d7e8dc;
            --gold:        #a87d28;
            --gold-lt:     #c9a84c;
            --preto:       #222;
            --cinza:       #555;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--green-pale);
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
            background: radial-gradient(circle, rgba(30,107,53,.12) 0%, transparent 70%);
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
            box-shadow: 0 4px 24px rgba(30,107,53,.15);
            margin-bottom: 16px;
            border: 3px solid var(--green-line);
        }

        .logo-wrap img {
            width: 72px;
            height: auto;
        }

        .header h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 26px;
            color: var(--green-dk);
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
            background: var(--green);
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
            border: 1.5px solid var(--green-line);
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
            border-color: var(--green);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(30,107,53,.12);
            color: var(--green);
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
            color: var(--green);
        }

        /* Cores dos ícones */
        .icon-site      { background: #e8f3ec; color: var(--green); }
        .icon-whatsapp  { background: #e8f8ef; color: #25d366; }
        .icon-instagram { background: #fce4ec; color: #e1306c; }
        .icon-maps      { background: #fdf3e3; color: var(--gold); }

        /* Footer */
        .footer-links {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #aaa;
            animation: fadeUp .6s .8s ease both;
        }

        .footer-links a {
            color: var(--green);
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
    </style>
</head>
<body>

<div class="wrapper">

    <div class="header">
        <div class="logo-wrap">
            <img src="/assets/img/logo.png" alt="NAIOT">
        </div>
        <h1>Comunidade Naiot</h1>
        <p>Comunidade Católica Senhor Jesus</p>
        <span class="badge">✝ Links Oficiais</span>
    </div>

    <div class="links">

        <a class="link-btn" href="https://www.naiot.com.br" target="_blank">
            <div class="icon icon-site"><i class="fas fa-globe"></i></div>
            <div class="text">
                Site Oficial
                <span>www.naiot.com.br</span>
            </div>
            <i class="fas fa-chevron-right arrow"></i>
        </a>

        <a class="link-btn" href="https://www.instagram.com/naiot_oficial/" target="_blank">
            <div class="icon icon-instagram"><i class="fab fa-instagram"></i></div>
            <div class="text">
                Instagram
                <span>@naiot_oficial</span>
            </div>
            <i class="fas fa-chevron-right arrow"></i>
        </a>

        <a class="link-btn" href="https://whatsapp.com/channel/0029VaVPbi15Ui2Y5f23h22i" target="_blank">
            <div class="icon icon-whatsapp"><i class="fab fa-whatsapp"></i></div>
            <div class="text">
                Canal no WhatsApp
                <span>Acessar canal agora</span>
            </div>
            <i class="fas fa-chevron-right arrow"></i>
        </a>

        <a class="link-btn" href="https://maps.app.goo.gl/c8GWF7HL8VjpH3it8?g_st=ic" target="_blank">
            <div class="icon icon-maps"><i class="fas fa-location-dot"></i></div>
            <div class="text">
                Localização
                <span>Campo Limpo de Goiás, GO</span>
            </div>
            <i class="fas fa-chevron-right arrow"></i>
        </a>

    </div>

    <div class="footer-links">
        <p>© <?php echo date('Y'); ?> Comunidade Católica Senhor Jesus · Campo Limpo de Goiás, GO</p>
        <p style="margin-top:6px"><a href="https://www.naiot.com.br">naiot.com.br</a></p>
    </div>

</div>

</body>
</html>
