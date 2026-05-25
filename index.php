<?php
$ao_vivo = false;
$youtube_video_id = '';

// Carrega banco de dados (necessário para orações, testemunhos e eventos)
$db_ok = false;
if (file_exists(__DIR__ . '/portal/config.php')) {
    try {
        require_once __DIR__ . '/portal/config.php';
        $db_ok = true;
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: bots preenchem este campo, humanos não veem
    if (!empty($_POST['_hp_website'])) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $base_url = strtok($_SERVER['REQUEST_URI'], '?');
    if (!empty($_POST['oracao']) && $db_ok) {
        $t = mb_substr(trim(strip_tags($_POST['oracao'])), 0, 1000);
        if (mb_strlen($t) >= 5) {
            db()->prepare("INSERT INTO oracoes (texto) VALUES (?)")->execute([$t]);
            header('Location: ' . $base_url . '?ok=oracao#oracao');
            exit;
        }
    }
    if (!empty($_POST['testemunho']) && $db_ok) {
        $t = mb_substr(trim(strip_tags($_POST['testemunho'])), 0, 2000);
        if (mb_strlen($t) >= 5) {
            db()->prepare("INSERT INTO testemunhos (texto) VALUES (?)")->execute([$t]);
            header('Location: ' . $base_url . '?ok=testemunho#testemunhos');
            exit;
        }
    }
}

// Lê somente registros aprovados para exibição pública
$oracoes = $testemunhos = [];
if ($db_ok) {
    try {
        $rows = db()->query("SELECT texto, criado_em FROM oracoes WHERE status='aprovado' ORDER BY criado_em DESC")->fetchAll();
        $oracoes = array_map(fn($r) => ['texto' => $r['texto'], 'data' => date('d/m/Y', strtotime($r['criado_em']))], $rows);

        $rows = db()->query("SELECT texto, criado_em FROM testemunhos WHERE status='aprovado' ORDER BY criado_em DESC")->fetchAll();
        $testemunhos = array_map(fn($r) => ['texto' => $r['texto'], 'data' => date('d/m/Y', strtotime($r['criado_em']))], $rows);
    } catch (Exception $e) {}
}

$eventos = [];
if ($db_ok) {
    try {
        $eventos = db()->query('SELECT * FROM eventos WHERE ativo = 1 ORDER BY ordem ASC, id ASC')->fetchAll();
    } catch (Exception $e) {}
}

function formatar_periodo(string $inicio, ?string $fim): string {
    $meses = ['','jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    $di = date('j', strtotime($inicio));
    $mi = (int)date('n', strtotime($inicio));
    $ai = date('Y', strtotime($inicio));
    if (!$fim || $fim === $inicio) {
        return $di . ' de ' . $meses[$mi] . '. de ' . $ai;
    }
    $df = date('j', strtotime($fim));
    $mf = (int)date('n', strtotime($fim));
    $af = date('Y', strtotime($fim));
    if ($mi === $mf && $ai === $af) {
        return $di . ' a ' . $df . ' de ' . $meses[$mf] . '. de ' . $af;
    }
    if ($ai === $af) {
        return $di . ' de ' . $meses[$mi] . ' a ' . $df . ' de ' . $meses[$mf] . '. de ' . $af;
    }
    return $di . '/' . $meses[$mi] . '/' . $ai . ' a ' . $df . '/' . $meses[$mf] . '/' . $af;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Comunidade Católica Senhor Jesus — Campo Limpo de Goiás, GO.">
<title>NAIOT — Comunidade Católica Senhor Jesus</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=EB+Garamond:ital,wght@0,400;0,500;1,400;1,500&display=swap" rel="stylesheet">
<style>
/* ╔══════════════════════════════╗
   ║  TOKENS & RESET              ║
   ╚══════════════════════════════╝ */
:root {
  --green:       #1e6b35;
  --green-dk:    #163d22;
  --green-pale:  #f0f7f2;
  --gold:        #a87d28;
  --gold-lt:     #c9a84c;
  --white:       #ffffff;
  --off:         #f8f8f6;
  --border:      #e2ddd6;
  --text:        #1f1f1f;
  --muted:       #6a6a6a;
  --red:         #b83232;
  --sh-sm:  0 2px 14px rgba(0,0,0,.07);
  --sh:     0 4px 32px rgba(0,0,0,.10);
  --sh-lg:  0 12px 56px rgba(0,0,0,.14);
  --r:  10px;
  --rl: 18px;
  --ease: .38s cubic-bezier(.4,0,.2,1);
  --hdr-h: 94px;
}

@media (max-width: 768px) { :root { --hdr-h: 72px; } }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; -webkit-tap-highlight-color: transparent; scroll-padding-top: calc(var(--hdr-h) - clamp(60px, 8vw, 100px) + 16px); }
body {
  font-family: 'EB Garamond', Georgia, serif;
  font-size: clamp(16px, 2vw, 18px);
  line-height: 1.78;
  color: var(--text);
  background: var(--white);
  overflow-x: hidden;
}
img { max-width: 100%; height: auto; display: block; }
a   { text-decoration: none; color: inherit; }
ul  { list-style: none; }
button { cursor: pointer; font: inherit; }

/* ╔══════════════════════════════╗
   ║  LAYOUT                      ║
   ╚══════════════════════════════╝ */
.wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding: 0 clamp(18px, 4vw, 36px); }
.sec      { padding: clamp(60px, 8vw, 100px) 0; }
.sec.alt  { background: var(--off); }
.sec.pale { background: var(--green-pale); }
.sec.dark { background: var(--green-dk); }

/* ╔══════════════════════════════╗
   ║  SECTION HEADERS             ║
   ╚══════════════════════════════╝ */
.sec-head { text-align: center; margin-bottom: clamp(36px, 5vw, 64px); }
.deco {
  display: flex; align-items: center; justify-content: center;
  gap: 16px; margin-bottom: 16px; color: var(--gold);
}
.deco::before, .deco::after {
  content: ''; flex: 0 0 clamp(32px, 5vw, 64px); height: 1px;
  background: linear-gradient(90deg, transparent, var(--gold-lt));
}
.deco::after { background: linear-gradient(90deg, var(--gold-lt), transparent); }
.deco-icon { font-size: clamp(.9rem, 1.5vw, 1.1rem); letter-spacing: .2em; font-variant-emoji: text; }
.foot-deco span, .hero-deco span { font-variant-emoji: text; }
.sec-title {
  font-family: 'Cinzel', serif;
  font-size: clamp(1.5rem, 3.5vw, 2.6rem);
  font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
  color: var(--green-dk);
}
.sec-title.lt { color: #fff; }
.sec-sub {
  margin-top: 12px;
  font-size: clamp(.95rem, 1.8vw, 1.1rem);
  font-style: italic; color: var(--muted);
}
.sec-sub.lt { color: rgba(255,255,255,.6); }

/* ╔══════════════════════════════╗
   ║  HEADER                      ║
   ╚══════════════════════════════╝ */
#hdr {
  position: fixed; top: 0; left: 0; right: 0; z-index: 900;
  background: rgba(255,255,255,.97);
  backdrop-filter: blur(16px) saturate(1.4);
  -webkit-backdrop-filter: blur(16px) saturate(1.4);
  border-bottom: 1px solid var(--border);
  transition: box-shadow var(--ease);
}
#hdr.scrolled { box-shadow: 0 2px 28px rgba(0,0,0,.10); }

.hdr-inner {
  display: flex; align-items: center;
  justify-content: space-between;
  height: var(--hdr-h);
  gap: 12px;
}

/* Logo — dentro da barra, sem padding desperdiçado */
.hdr-logo { flex-shrink: 0; display: flex; align-items: center; }
.hdr-logo img { height: clamp(42px, 5vw, 62px); width: auto; mix-blend-mode: multiply; }
.hdr-logo-txt {
  display: none; font-family: 'Cinzel', serif;
  font-size: clamp(1.2rem, 2.5vw, 1.8rem);
  font-weight: 700; color: var(--green-dk); letter-spacing: .12em;
}

/* Nav */
nav {
  display: flex; align-items: center;
  gap: clamp(2px, .5vw, 6px); flex-wrap: nowrap;
}
nav a {
  font-family: 'Cinzel', serif;
  font-size: clamp(.62rem, 1vw, .78rem);
  font-weight: 500; letter-spacing: .07em; text-transform: uppercase;
  color: var(--green-dk); white-space: nowrap;
  padding: clamp(6px, 1vw, 9px) clamp(8px, 1.2vw, 14px);
  border-radius: 6px;
  transition: color var(--ease), background var(--ease);
}
nav a:hover { color: var(--green); background: var(--green-pale); }

/* Social icons in header */
.hdr-social {
  display: flex; align-items: center;
  gap: clamp(4px, .6vw, 8px);
  padding-left: clamp(8px, 1.2vw, 14px);
  border-left: 1px solid var(--border);
  flex-shrink: 0;
}
.hdr-social a {
  width: clamp(30px, 3.5vw, 38px);
  height: clamp(30px, 3.5vw, 38px);
  border-radius: 50%;
  background: var(--green-pale);
  border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  color: var(--green);
  transition: background var(--ease), color var(--ease), transform var(--ease), border-color var(--ease);
}
.hdr-social a svg { width: clamp(13px, 1.5vw, 17px); height: clamp(13px, 1.5vw, 17px); fill: currentColor; }
.hdr-social a:hover { background: var(--green); color: #fff; border-color: var(--green); transform: translateY(-2px); }

/* Ao vivo button */
.btn-live {
  display: inline-flex !important; align-items: center; gap: 7px;
  background: var(--red) !important; color: #fff !important;
  border-radius: 4px !important; animation: pulse-red 2s infinite;
}
.btn-live::before { content: ''; width: 7px; height: 7px; background: #fff; border-radius: 50%; }
@keyframes pulse-red {
  0%  { box-shadow: 0 0 0 0 rgba(184,50,50,.7); }
  70% { box-shadow: 0 0 0 9px rgba(184,50,50,0); }
  100%{ box-shadow: 0 0 0 0 rgba(184,50,50,0); }
}

/* Hamburger */
.burger {
  display: none; flex-direction: column; gap: 5px;
  background: none; border: none; padding: 10px;
  -webkit-tap-highlight-color: transparent;
}
.burger span {
  display: block; width: 26px; height: 2px;
  background: var(--green-dk); border-radius: 1px; transition: var(--ease);
}
.burger.open span:nth-child(1) { transform: rotate(45deg) translate(5px,5px); }
.burger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.burger.open span:nth-child(3) { transform: rotate(-45deg) translate(5px,-5px); }

/* ╔══════════════════════════════╗
   ║  HERO                        ║
   ╚══════════════════════════════╝ */
.hero {
  min-height: 100svh;
  display: flex; align-items: center; justify-content: center;
  text-align: center; padding: calc(var(--hdr-h) + 50px) clamp(18px,4vw,36px) 60px;
  position: relative; overflow: hidden; background: var(--white);
}
.hero::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
  background: linear-gradient(90deg, var(--green-dk), var(--green), var(--gold-lt), var(--green), var(--green-dk));
}
.hero::after {
  content: ''; position: absolute; inset: 0; pointer-events: none;
  background: radial-gradient(ellipse 70% 50% at 50% 100%, rgba(30,107,53,.05) 0%, transparent 70%);
}
.hero-inner { position: relative; z-index: 1; max-width: 780px; }
.hero-deco {
  display: flex; align-items: center; justify-content: center;
  gap: 16px; margin-bottom: clamp(1.2rem, 2.5vw, 2rem); color: var(--gold);
  animation: fade-up .9s ease both;
}
.hero-deco::before, .hero-deco::after {
  content: ''; width: clamp(40px, 6vw, 80px); height: 1px;
  background: linear-gradient(90deg, transparent, var(--gold-lt));
}
.hero-deco::after { background: linear-gradient(90deg, var(--gold-lt), transparent); }
.hero h1 {
  font-size: clamp(1.6rem, 4.5vw, 3.2rem);
  font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
  color: var(--green-dk); line-height: 1.25;
  margin-bottom: clamp(.8rem, 1.5vw, 1.2rem);
  animation: fade-up .9s .1s ease both;
}
.hero h1 em {
  font-style: italic; font-family: 'EB Garamond', serif;
  text-transform: none; color: var(--green);
  font-size: 1.1em; letter-spacing: .02em;
}
.hero p {
  font-size: clamp(1rem, 2vw, 1.2rem);
  font-style: italic; color: var(--muted); letter-spacing: .03em;
  animation: fade-up .9s .2s ease both;
}
.hero-scroll {
  position: absolute; bottom: 24px; left: 50%;
  transform: translateX(-50%);
  color: #bbb; display: flex; flex-direction: column; align-items: center; gap: 4px;
  font-family: 'Cinzel', serif; font-size: .6rem; letter-spacing: .16em;
  animation: bob 2.5s ease-in-out infinite;
}
@keyframes fade-up { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
@keyframes bob { 0%,100% { transform: translateX(-50%) translateY(0); } 50% { transform: translateX(-50%) translateY(8px); } }

/* ╔══════════════════════════════╗
   ║  AO VIVO                     ║
   ╚══════════════════════════════╝ */
.live-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--red); color: #fff;
  font-family: 'Cinzel', serif; font-size: .74rem; font-weight: 600; letter-spacing: .12em;
  padding: 6px 20px; border-radius: 4px; margin-bottom: 16px;
  animation: pulse-red 2s infinite;
}
.live-badge::before { content: ''; width: 7px; height: 7px; background: #fff; border-radius: 50%; }
.video-box {
  position: relative; padding-bottom: 56.25%;
  border-radius: var(--rl); overflow: hidden; box-shadow: var(--sh-lg);
}
.video-box iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: none; }

/* ╔══════════════════════════════╗
   ║  PROGRAMAÇÃO                 ║
   ╚══════════════════════════════╝ */
.prog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
  gap: clamp(14px, 2vw, 24px);
}
.prog-card {
  background: var(--white);
  border-radius: var(--rl);
  padding: clamp(22px, 3vw, 32px) clamp(18px, 2.5vw, 28px);
  box-shadow: var(--sh-sm);
  border-top: 3px solid var(--gold-lt);
  border-bottom: 1px solid var(--border);
  transition: transform var(--ease), box-shadow var(--ease);
}
.prog-card:hover { transform: translateY(-5px); box-shadow: var(--sh); }
.prog-icon {
  width: 46px; height: 46px; background: var(--green-pale);
  border-radius: 10px; display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; margin-bottom: 16px;
}
.prog-day  { font-family: 'Cinzel', serif; font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .1em; color: var(--gold); margin-bottom: 2px; }
.prog-time { font-family: 'Cinzel', serif; font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 700; color: var(--green-dk); line-height: 1; margin-bottom: 8px; }
.prog-name { font-family: 'Cinzel', serif; font-size: .84rem; font-weight: 600; color: var(--green-dk); margin-bottom: 6px; }
.prog-desc { font-size: .9rem; color: var(--muted); font-style: italic; }

/* ╔══════════════════════════════╗
   ║  CARROSSEL                   ║
   ╚══════════════════════════════╝ */
.carousel-center { max-width: 800px; margin: 0 auto clamp(40px, 5vw, 60px); }
.carousel-outer  { position: relative; }

.carousel-viewport {
  overflow: hidden;
  border-radius: var(--rl);
  border: 1px solid var(--border);
  background: var(--white);
  box-shadow: var(--sh-sm);
  touch-action: pan-y;
}
.carousel-track {
  display: flex;
  transition: transform .65s cubic-bezier(.4,0,.2,1);
  will-change: transform;
}
.carousel-slide {
  min-width: 100%; padding: clamp(28px, 4vw, 44px) clamp(22px, 4vw, 44px) clamp(22px, 3vw, 34px);
}
.carousel-quote {
  font-size: clamp(.98rem, 1.8vw, 1.1rem);
  font-style: italic; line-height: 1.82;
  color: var(--text); margin-bottom: 16px;
  padding-left: 18px;
  border-left: 3px solid var(--gold-lt);
}
.carousel-date {
  font-family: 'Cinzel', serif; font-size: .68rem;
  letter-spacing: .07em; color: var(--muted); text-transform: uppercase;
}

/* ── Carrossel de eventos (imagens) ── */
#eventos .sec-head { margin-bottom: clamp(16px, 2vw, 24px); }
.evt-slide  { padding: 0; background: transparent; }
.evt-inner  { width: 100%; }
.evt-inner > img { width: 100%; height: auto; display: block; }
@media (min-width: 769px) {
  .evt-slide  { display: flex; justify-content: center; align-items: flex-start; }
  .evt-inner  { display: inline-flex; flex-direction: column; width: auto; max-width: 100%; align-items: flex-start; border-radius: var(--rl); overflow: hidden; box-shadow: var(--sh); }
  .evt-inner > img { max-height: 480px; width: auto; max-width: 100%; }
}
.evt-carousel .carousel-viewport { background: var(--off); border-color: transparent; box-shadow: none; }
.evt-caption {
  padding: 14px 24px 18px; background: var(--white);
  border-top: 1px solid var(--border);
  display: flex; align-items: center; gap: 20px;
}
.evt-info   { flex-shrink: 0; }
.evt-titulo { font-family: 'Cinzel', serif; font-size: .88rem; font-weight: 600; color: var(--text); display: block; }
.evt-data   { font-size: .75rem; color: var(--muted); margin-top: 3px; display: block; }
.evt-desc   { font-size: .85rem; color: var(--muted); font-style: italic; line-height: 1.5; border-left: 2px solid var(--border); padding-left: 16px; }
.evt-cta-bar {
  display: block;
  background: var(--green-dk); color: #fff !important;
  text-align: center; padding: 13px 20px;
  font-family: 'Cinzel', serif; font-size: .7rem; font-weight: 700;
  letter-spacing: .12em; text-transform: uppercase;
  border-top: 1px solid rgba(255,255,255,.08);
  transition: background var(--ease);
}
.evt-cta-bar:hover { background: var(--green); }

.carousel-nav {
  display: flex; align-items: center;
  justify-content: center; gap: 8px; margin-top: 14px;
}
.c-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: #ccc; border: none; padding: 0;
  transition: background var(--ease), transform var(--ease);
}
.c-dot.on { background: var(--green); transform: scale(1.35); }

.c-prev, .c-next {
  position: absolute; top: 50%; transform: translateY(-50%);
  width: 38px; height: 38px; border-radius: 50%;
  background: var(--white); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  box-shadow: var(--sh-sm); z-index: 2;
  color: var(--green-dk); font-size: 1.1rem;
  transition: background var(--ease), color var(--ease), border-color var(--ease);
}
.c-prev { left: -19px; }
.c-next { right: -19px; }
.c-prev:hover, .c-next:hover { background: var(--green); color: #fff; border-color: var(--green); }

@media (max-width: 520px) {
  .c-prev, .c-next { display: none; }
}

/* ╔══════════════════════════════╗
   ║  FORMULÁRIOS                 ║
   ╚══════════════════════════════╝ */
.form-center { max-width: 680px; margin: 0 auto; }
.form-box {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--rl);
  padding: clamp(24px, 4vw, 40px);
  box-shadow: var(--sh-sm);
}
.form-box h3 {
  font-family: 'Cinzel', serif; font-size: clamp(.8rem, 1.5vw, .92rem);
  font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
  color: var(--green-dk); margin-bottom: 6px;
}
.form-box > p { font-size: .92rem; color: var(--muted); font-style: italic; margin-bottom: 18px; }
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
  resize: vertical; min-height: 110px;
  color: var(--text); background: var(--off);
  transition: border-color var(--ease), box-shadow var(--ease), background var(--ease);
}
textarea:focus {
  outline: none; border-color: var(--green);
  background: var(--white);
  box-shadow: 0 0 0 3px rgba(30,107,53,.09);
}
.btn-sub {
  width: 100%; margin-top: 12px; padding: 14px;
  background: var(--green-dk); color: var(--white);
  border: none; border-radius: var(--r);
  font-family: 'Cinzel', serif; font-size: .78rem;
  font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
  transition: background var(--ease), transform var(--ease), box-shadow var(--ease);
}
.btn-sub:hover {
  background: var(--green); transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(22,61,34,.2);
}
.btn-sub:active { transform: translateY(0); }


/* ╔══════════════════════════════╗
   ║  FOOTER                      ║
   ╚══════════════════════════════╝ */
footer {
  background: var(--white);
  border-top: 3px solid var(--gold-lt);
  padding: clamp(22px, 3.5vw, 36px) 0 clamp(14px, 2vw, 20px);
}
.foot-inner {
  display: flex; flex-direction: column;
  align-items: center; gap: clamp(10px, 1.8vw, 16px); text-align: center;
}
.foot-deco {
  display: flex; align-items: center; gap: 14px;
  color: var(--gold); font-size: .9rem; letter-spacing: .2em;
}
.foot-deco::before, .foot-deco::after {
  content: ''; width: clamp(32px, 4vw, 52px); height: 1px;
  background: rgba(168,125,40,.3);
}
.foot-redes { display: flex; gap: clamp(8px, 1.5vw, 12px); flex-wrap: wrap; justify-content: center; }
.foot-rede {
  width: clamp(38px, 5vw, 46px); height: clamp(38px, 5vw, 46px);
  border-radius: 50%; background: var(--green-pale);
  border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  color: var(--green);
  transition: background var(--ease), color var(--ease), transform var(--ease), border-color var(--ease);
}
.foot-rede svg { width: clamp(15px, 2vw, 19px); height: clamp(15px, 2vw, 19px); fill: currentColor; }
.foot-rede:hover { background: var(--green); color: #fff; border-color: var(--green); transform: translateY(-3px); }
.foot-hr { width: 100%; height: 1px; background: var(--border); }
.foot-copy { font-size: clamp(.75rem, 1.5vw, .85rem); color: var(--muted); font-style: italic; letter-spacing: .03em; }
.foot-copy strong { color: var(--green-dk); font-style: normal; }

/* ╔══════════════════════════════╗
   ║  ANIMAÇÕES                   ║
   ╚══════════════════════════════╝ */
[data-a] { opacity: 0; transform: translateY(24px); transition: opacity .7s ease, transform .7s ease; }
[data-a].in { opacity: 1; transform: none; }
[data-a][data-d="1"] { transition-delay: .10s; }
[data-a][data-d="2"] { transition-delay: .20s; }
[data-a][data-d="3"] { transition-delay: .30s; }
[data-a][data-d="4"] { transition-delay: .40s; }
[data-a][data-d="5"] { transition-delay: .50s; }

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
  [data-a] { opacity: 1 !important; transform: none !important; }
}

/* ╔══════════════════════════════╗
   ║  MOBILE                      ║
   ╚══════════════════════════════╝ */
@media (max-width: 768px) {
  /* Nav mobile */
  .burger { display: flex; order: 3; }
  .hdr-social {
    display: flex; order: 2;
    border-left: none; padding-left: 0;
    gap: 6px;
  }
  .hdr-social a { width: 32px; height: 32px; }
  .hdr-social a svg { width: 14px; height: 14px; }
  .hdr-social a:nth-child(n+3) { display: none; }
  .hdr-logo { order: 1; }

  nav {
    position: fixed; top: var(--hdr-h); left: 0; right: 0;
    background: rgba(255,255,255,.98);
    backdrop-filter: blur(12px);
    flex-direction: column; padding: 10px 16px 18px;
    gap: 2px; border-bottom: 1px solid var(--border);
    box-shadow: 0 8px 28px rgba(0,0,0,.08);
    transform: translateY(-110%) scaleY(.95);
    transform-origin: top;
    opacity: 0;
    transition: transform .35s cubic-bezier(.4,0,.2,1), opacity .35s ease;
    z-index: 899; pointer-events: none;
  }
  nav.open {
    transform: translateY(0) scaleY(1);
    opacity: 1; pointer-events: auto;
  }
  nav a {
    width: 100%; text-align: center; padding: 13px 16px;
    font-size: .82rem; border-radius: 8px;
  }

  /* Carrossel mobile: padding menor */
  .carousel-center { margin-bottom: 32px; }
  .carousel-outer { margin: 0 8px; }
  .c-prev,.c-next { display: none; }
}

@media (max-width: 480px) {
  .prog-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ═══ HEADER ═══ -->
<header id="hdr">
  <div class="wrap hdr-inner">

    <a href="#inicio" class="hdr-logo">
      <img src="assets/img/logo.png" alt="NAIOT"
           onerror="this.style.display='none';document.querySelector('.hdr-logo-txt').style.display='block'">
      <span class="hdr-logo-txt">NAIOT</span>
    </a>

    <button class="burger" id="burger" aria-label="Abrir menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

    <nav id="nav" aria-label="Menu principal">
      <a href="#inicio">Início</a>
      <?php if (!empty($eventos)): ?><a href="#eventos">Próximos Eventos</a><?php endif; ?>
      <a href="#programacao">Programação</a>
      <?php if ($ao_vivo): ?><a href="#ao-vivo" class="btn-live">Ao Vivo</a><?php endif; ?>
      <a href="#oracao">Oração</a>
      <a href="#testemunhos">Testemunhos</a>
    </nav>

    <div class="hdr-social" aria-label="Redes sociais">
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

<!-- ═══ HERO ═══ -->
<section class="hero" id="inicio">
  <div class="hero-inner">
    <div class="hero-deco"><span>&#x271D;&#xFE0E;</span></div>
    <h1>Nossa missão é estar<br>aos pés de <em>Jesus</em></h1>
    <p>Comunidade Católica Senhor Jesus &mdash; Campo Limpo de Goiás, GO</p>
  </div>
  <div class="hero-scroll" aria-hidden="true">
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
    </svg>
  </div>
</section>

<!-- ═══ PRÓXIMOS EVENTOS ═══ -->
<?php if (!empty($eventos)): ?>
<section class="sec alt" id="eventos">
  <div class="wrap">
    <div class="sec-head" data-a>
      <div class="deco"><span class="deco-icon">&#x271D;&#xFE0E;</span></div>
      <h2 class="sec-title">Próximos Eventos</h2>
      <p class="sec-sub">Venha participar e viver momentos de fé com nossa comunidade.</p>
    </div>
    <div class="carousel-center" data-a>
      <div class="carousel-outer evt-carousel">
        <button class="c-prev" aria-label="Anterior">&#8249;</button>
        <button class="c-next" aria-label="Próximo">&#8250;</button>
        <div class="carousel-viewport">
          <div class="carousel-track" id="ce">
            <?php foreach ($eventos as $ev): ?>
            <div class="carousel-slide evt-slide">
              <div class="evt-inner">
                <img src="/assets/img/eventos/<?= htmlspecialchars($ev['imagem']) ?>"
                     alt="<?= htmlspecialchars($ev['titulo']) ?>" loading="lazy">
                <?php if ($ev['titulo'] || $ev['data_evento'] || $ev['descricao']): ?>
                <div class="evt-caption">
                  <div class="evt-info">
                    <?php if ($ev['titulo']): ?><span class="evt-titulo"><?= htmlspecialchars($ev['titulo']) ?></span><?php endif; ?>
                    <?php if ($ev['data_evento']): ?><span class="evt-data"><?= formatar_periodo($ev['data_evento'], $ev['data_fim'] ?? null) ?></span><?php endif; ?>
                  </div>
                  <?php if ($ev['descricao']): ?><span class="evt-desc"><?= htmlspecialchars($ev['descricao']) ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($ev['inscricoes_abertas'])): ?>
                <a href="/inscricao.php?id=<?= $ev['id'] ?>" class="evt-cta-bar">Inscrever-se neste evento &rarr;</a>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="carousel-nav" id="dots-ce" aria-label="Eventos">
          <?php foreach ($eventos as $i => $_): ?>
          <button class="c-dot<?= $i===0?' on':'' ?>" data-i="<?=$i?>" aria-label="Evento <?=$i+1?>"></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ AO VIVO ═══ -->
<?php if ($ao_vivo): ?>
<section class="sec dark" id="ao-vivo">
  <div class="wrap">
    <div class="sec-head" data-a>
      <div class="live-badge">Transmissão ao Vivo</div>
      <h2 class="sec-title lt">Ao Vivo</h2>
    </div>
    <?php if (!empty($youtube_video_id)): ?>
    <div class="video-box" data-a>
      <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($youtube_video_id,ENT_QUOTES) ?>?autoplay=1&rel=0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:56px 0;" data-a>
      <p style="color:rgba(255,255,255,.6);font-style:italic;margin-bottom:24px;">Estamos ao vivo! Clique abaixo para assistir.</p>
      <a href="https://www.youtube.com/@naiot_oficial4299" target="_blank" rel="noopener"
         style="display:inline-flex;align-items:center;gap:10px;background:var(--red);color:#fff;padding:14px 32px;border-radius:var(--r);font-family:'Cinzel',serif;font-size:.82rem;font-weight:600;letter-spacing:.08em;">
        ▶&ensp;Assistir no YouTube
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- ═══ PROGRAMAÇÃO ═══ -->
<section class="sec alt" id="programacao">
  <div class="wrap">
    <div class="sec-head" data-a>
      <div class="deco"><span class="deco-icon">&#x271D;&#xFE0E;</span></div>
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
        <p class="prog-desc">Acompanhe as redes para eventos especiais.</p>
      </div>
    </div>
  </div>
</section>

<!-- ═══ PEDIDOS DE ORAÇÃO ═══ -->
<section class="sec pale" id="oracao">
  <div class="wrap">
    <div class="sec-head" data-a>
      <div class="deco"><span class="deco-icon">&#x271D;&#xFE0E;</span></div>
      <h2 class="sec-title">Pedidos de Oração</h2>
      <p class="sec-sub">Confie suas intenções à nossa comunidade. Vamos orar por você.</p>
    </div>

    <?php if (!empty($oracoes)): ?>
    <div class="carousel-center" data-a>
      <div class="carousel-outer">
        <button class="c-prev" data-target="co" aria-label="Anterior">&#8249;</button>
        <button class="c-next" data-target="co" aria-label="Próximo">&#8250;</button>
        <div class="carousel-viewport">
          <div class="carousel-track" id="co">
            <?php foreach ($oracoes as $item): ?>
            <div class="carousel-slide">
              <p class="carousel-quote"><?= nl2br(htmlspecialchars($item['texto'])) ?></p>
              <span class="carousel-date"><?= htmlspecialchars($item['data']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="carousel-nav" id="dots-co" aria-label="Pedidos">
          <?php foreach ($oracoes as $i => $_): ?>
          <button class="c-dot<?= $i===0?' on':'' ?>" data-i="<?=$i?>" aria-label="Pedido <?=$i+1?>"></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="form-center" data-a>
      <div class="form-box">
        <h3>Enviar meu Pedido</h3>
        <p>Anônimo e sigiloso. Nossa comunidade intercederá por você.</p>
        <?php if (isset($_GET['ok']) && $_GET['ok']==='oracao'): ?>
        <div class="ok-msg">Seu pedido foi recebido. Vamos orar por você! 🙏</div>
        <?php endif; ?>
        <form method="POST">
          <input type="text" name="_hp_website" style="display:none" tabindex="-1" autocomplete="off">
          <textarea name="oracao" placeholder="Escreva sua intenção de oração..." required maxlength="1000"></textarea>
          <button type="submit" class="btn-sub">Enviar Pedido de Oração</button>
        </form>
      </div>
    </div>
  </div>
</section>

<!-- ═══ TESTEMUNHOS ═══ -->
<section class="sec" id="testemunhos">
  <div class="wrap">
    <div class="sec-head" data-a>
      <div class="deco"><span class="deco-icon">&#x271D;&#xFE0E;</span></div>
      <h2 class="sec-title">Testemunhos</h2>
      <p class="sec-sub">Graças e milagres que edificam e fortalecem a fé de toda a comunidade.</p>
    </div>

    <?php if (!empty($testemunhos)): ?>
    <div class="carousel-center" data-a>
      <div class="carousel-outer">
        <button class="c-prev" data-target="ct" aria-label="Anterior">&#8249;</button>
        <button class="c-next" data-target="ct" aria-label="Próximo">&#8250;</button>
        <div class="carousel-viewport">
          <div class="carousel-track" id="ct">
            <?php foreach ($testemunhos as $item): ?>
            <div class="carousel-slide">
              <p class="carousel-quote"><?= nl2br(htmlspecialchars($item['texto'])) ?></p>
              <span class="carousel-date"><?= htmlspecialchars($item['data']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="carousel-nav" id="dots-ct" aria-label="Testemunhos">
          <?php foreach ($testemunhos as $i => $_): ?>
          <button class="c-dot<?= $i===0?' on':'' ?>" data-i="<?=$i?>" aria-label="Testemunho <?=$i+1?>"></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="form-center" data-a>
      <div class="form-box">
        <h3>Compartilhar Testemunho</h3>
        <p>Divida as graças que Deus realizou em sua vida. Que Ele seja glorificado!</p>
        <?php if (isset($_GET['ok']) && $_GET['ok']==='testemunho'): ?>
        <div class="ok-msg">Seu testemunho foi compartilhado. Que Deus seja glorificado! ✨</div>
        <?php endif; ?>
        <form method="POST">
          <input type="text" name="_hp_website" style="display:none" tabindex="-1" autocomplete="off">
          <textarea name="testemunho" placeholder="Compartilhe o que Deus fez por você..." required maxlength="2000"></textarea>
          <button type="submit" class="btn-sub">Compartilhar Testemunho</button>
        </form>
      </div>
    </div>
  </div>
</section>

<!-- ═══ MAPA ═══ -->

<!-- ═══ FOOTER ═══ -->
<footer>
  <div class="wrap foot-inner">
    <div class="foot-deco"><span>&#x271D;&#xFE0E;</span></div>
    <div class="foot-redes" aria-label="Redes sociais">
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

<script>
'use strict';

/* ── Menu mobile ── */
const burger = document.getElementById('burger');
const nav    = document.getElementById('nav');

burger.addEventListener('click', () => {
  const open = burger.classList.toggle('open');
  nav.classList.toggle('open', open);
  burger.setAttribute('aria-expanded', open);
});
nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
  burger.classList.remove('open');
  nav.classList.remove('open');
  burger.setAttribute('aria-expanded', 'false');
}));
document.addEventListener('click', e => {
  if (!burger.contains(e.target) && !nav.contains(e.target)) {
    burger.classList.remove('open');
    nav.classList.remove('open');
    burger.setAttribute('aria-expanded', 'false');
  }
});

/* ── Header shadow ── */
const hdr = document.getElementById('hdr');
const onScroll = () => hdr.classList.toggle('scrolled', scrollY > 20);
window.addEventListener('scroll', onScroll, { passive: true });
onScroll();

/* ── Scroll animations ── */
const io = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
}, { threshold: 0.07, rootMargin: '0px 0px -32px 0px' });
document.querySelectorAll('[data-a]').forEach(el => io.observe(el));

/* ── Carousel ── */
class Carousel {
  constructor(trackId, dotsId, delay = 5500) {
    this.track = document.getElementById(trackId);
    if (!this.track) return;
    this.dotsEl = document.getElementById(dotsId);
    this.slides = [...this.track.querySelectorAll('.carousel-slide')];
    this.dots   = this.dotsEl ? [...this.dotsEl.querySelectorAll('.c-dot')] : [];
    this.n      = this.slides.length;
    this.cur    = 0;
    this.delay  = delay;
    this.timer  = null;

    /* Dots */
    this.dots.forEach((d, i) => d.addEventListener('click', () => this.go(i)));

    /* Prev / Next buttons */
    const outer = this.track.closest('.carousel-outer');
    outer.querySelector('.c-prev')?.addEventListener('click', () => this.go((this.cur - 1 + this.n) % this.n));
    outer.querySelector('.c-next')?.addEventListener('click', () => this.go((this.cur + 1) % this.n));

    /* Touch / pointer swipe */
    let startX = 0, startY = 0, dragging = false;
    const vp = this.track.closest('.carousel-viewport');
    vp.addEventListener('pointerdown', e => { startX = e.clientX; startY = e.clientY; dragging = true; });
    vp.addEventListener('pointermove', e => { if (!dragging) return; e.preventDefault(); }, { passive: false });
    vp.addEventListener('pointerup',   e => {
      if (!dragging) return; dragging = false;
      const dx = e.clientX - startX, dy = e.clientY - startY;
      if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 44) {
        dx < 0 ? this.go((this.cur + 1) % this.n) : this.go((this.cur - 1 + this.n) % this.n);
      }
    });
    vp.addEventListener('pointercancel', () => { dragging = false; });

    /* Keyboard */
    outer.setAttribute('tabindex', '0');
    outer.addEventListener('keydown', e => {
      if (e.key === 'ArrowLeft')  this.go((this.cur - 1 + this.n) % this.n);
      if (e.key === 'ArrowRight') this.go((this.cur + 1) % this.n);
    });

    /* Pause on hover / focus */
    outer.addEventListener('mouseenter', () => this.stop());
    outer.addEventListener('mouseleave', () => this.start());
    outer.addEventListener('focusin',    () => this.stop());
    outer.addEventListener('focusout',   () => this.start());

    this.start();
  }

  go(i) {
    this.cur = (i + this.n) % this.n;
    this.track.style.transform = `translateX(-${this.cur * 100}%)`;
    this.dots.forEach((d, j) => { d.classList.toggle('on', j === this.cur); });
    this.stop(); this.start();
  }

  start() { if (this.n > 1) this.timer = setInterval(() => this.go(this.cur + 1), this.delay); }
  stop()  { clearInterval(this.timer); }
}

new Carousel('co', 'dots-co', 5500);
new Carousel('ct', 'dots-ct', 6800);
new Carousel('ce', 'dots-ce', 4500);

/* ── Alinha barra de evento à largura real da imagem ── */
function syncEvtCaptions() {
  var mobile = window.innerWidth <= 768;
  document.querySelectorAll('.evt-inner').forEach(function(el) {
    var img  = el.querySelector('img');
    var cap  = el.querySelector('.evt-caption');
    var cta  = el.querySelector('.evt-cta-bar');
    if (!img) return;
    if (mobile) {
      if (cap) cap.style.width = '';
      if (cta) cta.style.width = '';
      return;
    }
    var w = img.offsetWidth;
    if (cap) cap.style.width = w + 'px';
    if (cta) cta.style.width = w + 'px';
    var outer = el.closest('.carousel-outer');
    if (outer) {
      var gap = (outer.offsetWidth - w) / 2;
      var prev = outer.querySelector('.c-prev');
      var next = outer.querySelector('.c-next');
      if (prev) prev.style.left  = Math.max(0, gap - 22) + 'px';
      if (next) next.style.right = Math.max(0, gap - 22) + 'px';
    }
  });
}
document.querySelectorAll('.evt-inner img').forEach(function(img) {
  img.complete ? syncEvtCaptions() : img.addEventListener('load', syncEvtCaptions);
});
window.addEventListener('resize', syncEvtCaptions);

</script>
</body>
</html>
