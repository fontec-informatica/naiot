<?php
require_once __DIR__ . '/portal/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /'); exit; }

$ev_stmt = db()->prepare('SELECT * FROM eventos WHERE id = ? AND ativo = 1');
$ev_stmt->execute([$id]);
$evento = $ev_stmt->fetch();

$hoje = date('Y-m-d');

$total_inscritos = 0;
if ($evento) {
    $cnt = db()->prepare("SELECT COUNT(*) FROM inscricoes WHERE evento_id = ? AND status != 'cancelado'");
    $cnt->execute([$id]);
    $total_inscritos = (int)$cnt->fetchColumn();
}

$evento_lotado  = $evento && $evento['vagas'] && $total_inscritos >= $evento['vagas'];
$encerrado      = $evento && !empty($evento['data_encerramento']) && $evento['data_encerramento'] < $hoje;
$pode_inscrever = $evento && $evento['inscricoes_abertas'] && !$evento_lotado && !$encerrado;

/* ── Programação ── */
$programacao = [];
if ($evento) {
    try {
        $ps = db()->prepare("SELECT * FROM evento_programacao WHERE evento_id = ? ORDER BY ordem ASC, id ASC");
        $ps->execute([$id]);
        $programacao = $ps->fetchAll();
    } catch (Exception $e) { $programacao = []; }
}

/* ── Lotes disponíveis ── */
$lotes = [];
if ($evento) {
    $ls = db()->prepare("
        SELECT l.*, (SELECT COUNT(*) FROM inscricoes i WHERE i.lote_id = l.id AND i.status != 'cancelado') AS inscritos
        FROM evento_lotes l
        WHERE l.evento_id = ? AND l.ativo = 1
          AND (l.data_inicio IS NULL OR l.data_inicio <= ?)
          AND (l.data_fim    IS NULL OR l.data_fim    >= ?)
        ORDER BY l.ordem ASC, l.id ASC
    ");
    $ls->execute([$id, $hoje, $hoje]);
    foreach ($ls->fetchAll() as $l) {
        if ($l['vagas'] === null || $l['inscritos'] < $l['vagas']) $lotes[] = $l;
    }
}

/* ── Preço mínimo para exibir ── */
$preco_min = null;
if (!empty($lotes)) {
    $valores = array_column($lotes, 'valor');
    $preco_min = min($valores);
} elseif ($evento && ($evento['valor'] ?? 0) > 0) {
    $preco_min = (float)$evento['valor'];
}

$meses = ['','jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
function fmt_periodo_ev(string $ini, ?string $fim): string {
    global $meses;
    $di = date('j', strtotime($ini)); $mi = (int)date('n', strtotime($ini)); $ai = date('Y', strtotime($ini));
    if (!$fim || $fim === $ini) return "$di de {$meses[$mi]}. de $ai";
    $df = date('j', strtotime($fim)); $mf = (int)date('n', strtotime($fim)); $af = date('Y', strtotime($fim));
    if ($mi === $mf && $ai === $af) return "$di a $df de {$meses[$mf]}. de $af";
    return "$di de {$meses[$mi]} a $df de {$meses[$mf]}. de $af";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $evento ? htmlspecialchars($evento['titulo']) . ' — ' : '' ?>NAIOT</title>
<link rel="icon" href="/assets/img/logo.png" type="image/png">
<?php if ($evento && $evento['imagem']): ?>
<meta property="og:image" content="/assets/img/eventos/<?= htmlspecialchars($evento['imagem']) ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=EB+Garamond:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
:root {
  --green:     #1e6b35;
  --green-dk:  #163d22;
  --green-pale:#f0f7f2;
  --gold:      #a87d28;
  --gold-lt:   #c9a84c;
  --white:     #ffffff;
  --off:       #f8f8f6;
  --border:    #e2ddd6;
  --text:      #1f1f1f;
  --muted:     #6a6a6a;
  --red:       #b83232;
  --r: 10px; --rl: 18px;
  --sh-sm: 0 2px 14px rgba(0,0,0,.07);
  --sh:    0 4px 32px rgba(0,0,0,.10);
  --ease: .25s ease;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'EB Garamond', Georgia, serif; font-size: clamp(15px,2vw,17px); line-height: 1.75; color: var(--text); background: var(--off); min-height: 100vh; display: flex; flex-direction: column; }
a { text-decoration: none; color: inherit; }
img { max-width: 100%; height: auto; display: block; }

/* ── Header ── */
.ev-hdr { background: var(--white); border-top: 3px solid var(--green-dk); border-bottom: 1px solid var(--border); box-shadow: 0 2px 12px rgba(0,0,0,.06); position: sticky; top: 0; z-index: 100; }
.ev-hdr-inner { max-width: 960px; margin: 0 auto; padding: 0 20px; height: 64px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.ev-logo-wrap { display: flex; align-items: center; gap: 10px; }
.ev-logo { height: 44px; width: auto; mix-blend-mode: multiply; }
.ev-logo-txt { display: none; font-family: 'Cinzel', serif; font-size: 1.3rem; font-weight: 700; color: var(--green-dk); letter-spacing: .1em; }
.ev-back { display: inline-flex; align-items: center; gap: 5px; font-family: 'Cinzel', serif; font-size: .66rem; font-weight: 600; letter-spacing: .06em; color: var(--green); white-space: nowrap; padding: 6px 13px; border: 1.5px solid rgba(30,107,53,.3); border-radius: 6px; transition: all var(--ease); }
.ev-back:hover { color: #fff; background: var(--green); border-color: var(--green); }

/* ── Hero ── */
.ev-hero { position: relative; max-height: 420px; overflow: hidden; background: var(--green-dk); }
.ev-hero-img { width: 100%; max-height: 420px; object-fit: cover; object-position: center; display: block; opacity: .85; }
.ev-hero-grad { position: absolute; inset: 0; background: linear-gradient(to top, rgba(16,38,22,.85) 0%, rgba(16,38,22,.2) 60%, transparent 100%); }
.ev-hero-info { position: absolute; bottom: 0; left: 0; right: 0; padding: 28px 24px; }
.ev-hero-info-inner { max-width: 960px; margin: 0 auto; }
.ev-hero-badge { display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,.15); backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,.3); color: #fff; padding: 3px 12px; border-radius: 20px; font-family: 'Cinzel', serif; font-size: .62rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; margin-bottom: 10px; }
.ev-hero-badge.open::before { content: ''; width: 7px; height: 7px; background: #4ade80; border-radius: 50%; animation: pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.35} }
.ev-hero-title { font-family: 'Cinzel', serif; font-size: clamp(1.3rem, 4vw, 2.2rem); font-weight: 700; color: #fff; line-height: 1.25; text-shadow: 0 2px 12px rgba(0,0,0,.4); }

/* ── Hero placeholder (sem imagem) ── */
.ev-hero-placeholder { height: 280px; background: linear-gradient(135deg, var(--green-dk) 0%, #2d5a3d 100%); display: flex; flex-direction: column; align-items: center; justify-content: flex-end; padding-bottom: 32px; }

/* ── Layout ── */
.ev-wrap { max-width: 960px; margin: 0 auto; padding: 32px 20px 64px; flex: 1; display: grid; grid-template-columns: 1fr 320px; gap: 28px; align-items: start; }
@media (max-width: 700px) { .ev-wrap { grid-template-columns: 1fr; } }

/* ── Sidebar card ── */
.ev-sidebar { display: flex; flex-direction: column; gap: 16px; }
@media (max-width: 700px) { .ev-sidebar { order: -1; } }

.insc-card { background: var(--white); border-radius: var(--rl); box-shadow: var(--sh); overflow: hidden; border-top: 3px solid var(--gold-lt); }
.insc-card-body { padding: 22px; }
.insc-card-title { font-family: 'Cinzel', serif; font-size: .68rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: var(--muted); margin-bottom: 14px; }
.badge-open { display: inline-flex; align-items: center; gap: 5px; background: var(--green-pale); color: var(--green); border: 1px solid rgba(30,107,53,.25); padding: 4px 12px; border-radius: 20px; font-family: 'Cinzel', serif; font-size: .63rem; font-weight: 700; letter-spacing: .1em; margin-bottom: 14px; }
.badge-open::before { content: ''; width: 7px; height: 7px; background: var(--green); border-radius: 50%; animation: pulse-dot 2s infinite; }
.badge-closed { display: inline-flex; align-items: center; gap: 5px; background: #f3f4f6; color: var(--muted); border: 1px solid var(--border); padding: 4px 12px; border-radius: 20px; font-family: 'Cinzel', serif; font-size: .63rem; font-weight: 700; letter-spacing: .1em; margin-bottom: 14px; }

/* Vagas bar */
.vagas-wrap { background: var(--off); border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; }
.vagas-label { font-size: .8rem; color: var(--muted); margin-bottom: 6px; display: flex; justify-content: space-between; }
.vagas-label strong { color: var(--text); }
.vagas-track { height: 7px; background: var(--border); border-radius: 4px; overflow: hidden; }
.vagas-fill { height: 100%; background: linear-gradient(90deg, var(--green), var(--green-dk)); border-radius: 4px; }

/* Lotes mini */
.lotes-mini { margin-bottom: 14px; display: flex; flex-direction: column; gap: 7px; }
.lote-mini { display: flex; justify-content: space-between; align-items: center; padding: 9px 13px; background: var(--off); border-radius: 8px; border: 1px solid var(--border); }
.lote-mini-nome { font-family: 'Cinzel', serif; font-size: .75rem; font-weight: 700; color: var(--green-dk); }
.lote-mini-preco { font-family: 'Cinzel', serif; font-size: .82rem; font-weight: 700; color: var(--green-dk); }
.lote-mini-preco.free { color: var(--green); }

/* Preco */
.preco-destaque { text-align: center; margin-bottom: 14px; }
.preco-label { font-family: 'Cinzel', serif; font-size: .62rem; letter-spacing: .08em; color: var(--muted); text-transform: uppercase; }
.preco-val { font-family: 'Cinzel', serif; font-size: 1.6rem; font-weight: 700; color: var(--green-dk); line-height: 1.2; }
.preco-val.free { font-size: 1.2rem; color: var(--green); }

/* CTA button */
.btn-insc { display: block; text-align: center; padding: 14px 20px; background: var(--green-dk); color: #fff; border-radius: var(--r); font-family: 'Cinzel', serif; font-size: .8rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; margin-bottom: 12px; transition: background var(--ease), transform var(--ease), box-shadow var(--ease); }
.btn-insc:hover { background: var(--green); color: #fff; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(22,61,34,.22); }
.btn-insc.disabled { background: var(--muted); cursor: not-allowed; }
.btn-insc.disabled:hover { transform: none; box-shadow: none; }
.enc-note { font-size: .79rem; color: var(--muted); text-align: center; font-style: italic; }

/* Meta card */
.meta-card { background: var(--white); border-radius: var(--rl); box-shadow: var(--sh-sm); padding: 20px; }
.meta-item { display: flex; align-items: flex-start; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--border); }
.meta-item:last-child { border-bottom: none; }
.meta-icon { width: 34px; height: 34px; background: var(--green-pale); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.meta-icon svg { width: 16px; height: 16px; color: var(--green); }
.meta-lbl { font-family: 'Cinzel', serif; font-size: .6rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
.meta-val { font-size: .88rem; color: var(--text); font-weight: 500; }

/* ── Conteúdo principal ── */
.ev-content { display: flex; flex-direction: column; gap: 24px; }

.ev-section { background: var(--white); border-radius: var(--rl); box-shadow: var(--sh-sm); padding: 28px; }
.ev-section-title { font-family: 'Cinzel', serif; font-size: .8rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--gold); margin-bottom: 16px; display: flex; align-items: center; gap: 12px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }
.ev-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* Sobre */
.ev-sobre { font-size: .97rem; line-height: 1.85; color: var(--text); white-space: pre-line; }

/* Programação */
.prog-list { display: flex; flex-direction: column; gap: 0; }
.prog-item { display: flex; gap: 16px; padding: 14px 0; border-bottom: 1px solid var(--border); position: relative; }
.prog-item:last-child { border-bottom: none; }
.prog-horario { font-family: 'Cinzel', serif; font-size: .72rem; font-weight: 700; color: var(--green); white-space: nowrap; min-width: 68px; padding-top: 3px; }
.prog-info { flex: 1; }
.prog-titulo { font-family: 'Cinzel', serif; font-size: .88rem; font-weight: 700; color: var(--green-dk); margin-bottom: 3px; }
.prog-desc { font-size: .84rem; color: var(--muted); font-style: italic; }

/* Contato */
.contato-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 500px) { .contato-grid { grid-template-columns: 1fr; } }
.contato-item { display: flex; align-items: center; gap: 12px; padding: 13px 16px; background: var(--off); border-radius: var(--r); border: 1px solid var(--border); text-decoration: none; transition: border-color var(--ease), box-shadow var(--ease); }
.contato-item:hover { border-color: var(--green); box-shadow: 0 0 0 3px rgba(30,107,53,.07); }
.contato-item-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.contato-item-icon.email { background: #eff6ff; }
.contato-item-icon.wpp { background: #f0fdf4; }
.contato-item-icon svg { width: 18px; height: 18px; }
.contato-item-info { min-width: 0; }
.contato-item-lbl { font-family: 'Cinzel', serif; font-size: .6rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
.contato-item-val { font-size: .84rem; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Footer */
.ev-footer { background: var(--white); border-top: 1px solid var(--border); padding: 18px 20px; text-align: center; }
.ev-footer p { font-family: 'Cinzel', serif; font-size: .64rem; letter-spacing: .06em; color: var(--muted); }
.ev-footer strong { color: var(--green-dk); }

/* Not found */
.not-found { max-width: 500px; margin: 60px auto; padding: 48px; text-align: center; background: var(--white); border-radius: var(--rl); box-shadow: var(--sh-sm); }
.not-found h2 { font-family: 'Cinzel', serif; font-size: 1.1rem; color: var(--green-dk); margin: 16px 0 8px; }
.not-found p { color: var(--muted); font-style: italic; }
.not-found a { display: inline-flex; align-items: center; gap: 5px; margin-top: 20px; color: var(--green); font-family: 'Cinzel', serif; font-size: .72rem; font-weight: 600; letter-spacing: .06em; }
</style>
</head>
<body>

<!-- ═══ HEADER ═══ -->
<header class="ev-hdr">
  <div class="ev-hdr-inner">
    <a href="/" class="ev-logo-wrap">
      <img src="/assets/img/logo.png" alt="NAIOT" class="ev-logo"
           onerror="this.style.display='none';document.querySelector('.ev-logo-txt').style.display='block'">
      <span class="ev-logo-txt">NAIOT</span>
    </a>
    <a href="/" class="ev-back">&#8592; Voltar ao site</a>
  </div>
</header>

<?php if (!$evento): ?>
<!-- Evento não encontrado -->
<div style="flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div class="not-found">
    <div style="font-size:2.5rem">✝</div>
    <h2>Evento não encontrado</h2>
    <p>O evento que você procura não está disponível.</p>
    <a href="/">&#8592; Voltar ao site da NAIOT</a>
  </div>
</div>

<?php else: ?>

<!-- ═══ HERO ═══ -->
<?php if ($evento['imagem'] && file_exists(__DIR__ . '/assets/img/eventos/' . $evento['imagem'])): ?>
<div class="ev-hero">
  <img src="/assets/img/eventos/<?= htmlspecialchars($evento['imagem']) ?>"
       alt="<?= htmlspecialchars($evento['titulo']) ?>" class="ev-hero-img">
  <div class="ev-hero-grad"></div>
  <div class="ev-hero-info">
    <div class="ev-hero-info-inner">
      <?php if ($pode_inscrever): ?>
        <div class="ev-hero-badge open">Inscrições abertas</div>
      <?php elseif ($evento_lotado): ?>
        <div class="ev-hero-badge">Vagas esgotadas</div>
      <?php elseif ($encerrado): ?>
        <div class="ev-hero-badge">Inscrições encerradas</div>
      <?php else: ?>
        <div class="ev-hero-badge">Em breve</div>
      <?php endif; ?>
      <div class="ev-hero-title"><?= htmlspecialchars($evento['titulo']) ?></div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="ev-hero-placeholder">
  <div class="ev-hero-info-inner" style="width:100%;max-width:960px;padding:0 24px">
    <?php if ($pode_inscrever): ?>
      <div class="ev-hero-badge open">Inscrições abertas</div>
    <?php endif; ?>
    <div class="ev-hero-title"><?= htmlspecialchars($evento['titulo']) ?></div>
  </div>
</div>
<?php endif; ?>

<!-- ═══ CONTEÚDO ═══ -->
<div class="ev-wrap">

  <!-- ══ CONTEÚDO PRINCIPAL ══ -->
  <div class="ev-content">

    <?php $texto_principal = !empty($evento['sobre']) ? $evento['sobre'] : ($evento['descricao'] ?? ''); ?>
    <?php if ($texto_principal): ?>
    <div class="ev-section">
      <div class="ev-section-title">Sobre o Evento</div>
      <div class="ev-sobre"><?= nl2br(htmlspecialchars($texto_principal)) ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($programacao)): ?>
    <div class="ev-section">
      <div class="ev-section-title">Programação</div>
      <div class="prog-list">
        <?php foreach ($programacao as $p): ?>
        <div class="prog-item">
          <?php if ($p['horario']): ?>
          <div class="prog-horario"><?= htmlspecialchars($p['horario']) ?></div>
          <?php endif; ?>
          <div class="prog-info">
            <div class="prog-titulo"><?= htmlspecialchars($p['titulo']) ?></div>
            <?php if ($p['descricao']): ?>
            <div class="prog-desc"><?= nl2br(htmlspecialchars($p['descricao'])) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($evento['email_organizador']) || !empty($evento['whatsapp_contato'])): ?>
    <div class="ev-section">
      <div class="ev-section-title">Fale com o Organizador</div>
      <div class="contato-grid">
        <?php if (!empty($evento['email_organizador'])): ?>
        <a href="mailto:<?= htmlspecialchars($evento['email_organizador']) ?>" class="contato-item">
          <div class="contato-item-icon email">
            <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </div>
          <div class="contato-item-info">
            <div class="contato-item-lbl">E-mail</div>
            <div class="contato-item-val"><?= htmlspecialchars($evento['email_organizador']) ?></div>
          </div>
        </a>
        <?php endif; ?>
        <?php if (!empty($evento['whatsapp_contato'])): ?>
        <?php $wpp_num = preg_replace('/\D/', '', $evento['whatsapp_contato']); ?>
        <a href="https://wa.me/55<?= $wpp_num ?>" target="_blank" rel="noopener" class="contato-item">
          <div class="contato-item-icon wpp">
            <svg viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </div>
          <div class="contato-item-info">
            <div class="contato-item-lbl">WhatsApp</div>
            <div class="contato-item-val"><?= htmlspecialchars($evento['whatsapp_contato']) ?></div>
          </div>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /ev-content -->

  <!-- ══ SIDEBAR ══ -->
  <div class="ev-sidebar">

    <!-- Card de inscrição -->
    <div class="insc-card">
      <div class="insc-card-body">
        <div class="insc-card-title">Inscrição</div>

        <?php if ($pode_inscrever): ?>
          <span class="badge-open">Inscrições abertas</span>
        <?php elseif ($evento_lotado): ?>
          <span class="badge-closed">Vagas esgotadas</span>
        <?php elseif ($encerrado): ?>
          <span class="badge-closed">Encerrado</span>
        <?php else: ?>
          <span class="badge-closed">Em breve</span>
        <?php endif; ?>

        <?php if ($evento['vagas']): ?>
        <div class="vagas-wrap">
          <?php $pct = min(100, round($total_inscritos / $evento['vagas'] * 100)); ?>
          <div class="vagas-label">
            <span><?= $total_inscritos ?>/<?= $evento['vagas'] ?> vagas</span>
            <strong><?= $evento['vagas'] - $total_inscritos ?> disponíveis</strong>
          </div>
          <div class="vagas-track"><div class="vagas-fill" style="width:<?= $pct ?>%"></div></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($lotes)): ?>
        <div class="lotes-mini">
          <?php foreach ($lotes as $l): ?>
          <div class="lote-mini">
            <span class="lote-mini-nome"><?= htmlspecialchars($l['nome']) ?></span>
            <span class="lote-mini-preco <?= $l['valor'] == 0 ? 'free' : '' ?>">
              <?= $l['valor'] > 0 ? 'R$ ' . number_format($l['valor'], 2, ',', '.') : 'Gratuito' ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php elseif ($preco_min !== null): ?>
        <div class="preco-destaque">
          <div class="preco-label">Valor</div>
          <div class="preco-val <?= $preco_min == 0 ? 'free' : '' ?>">
            <?= $preco_min > 0 ? 'R$ ' . number_format($preco_min, 2, ',', '.') : 'Gratuito' ?>
          </div>
        </div>
        <?php else: ?>
        <div class="preco-destaque">
          <div class="preco-val free">Gratuito</div>
        </div>
        <?php endif; ?>

        <?php if ($pode_inscrever): ?>
          <a href="/inscricao.php?id=<?= $id ?>" class="btn-insc">Fazer minha inscrição &rarr;</a>
        <?php elseif ($evento_lotado): ?>
          <span class="btn-insc disabled">Vagas esgotadas</span>
        <?php elseif ($encerrado): ?>
          <span class="btn-insc disabled">Inscrições encerradas</span>
        <?php else: ?>
          <span class="btn-insc disabled">Em breve</span>
        <?php endif; ?>

        <?php if (!empty($evento['data_encerramento']) && !$encerrado): ?>
        <div class="enc-note">Inscrições até <?= date('d/m/Y', strtotime($evento['data_encerramento'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($evento['link_grupo']) && $pode_inscrever): ?>
        <div style="text-align:center;margin-top:10px">
          <a href="<?= htmlspecialchars($evento['link_grupo']) ?>" target="_blank" rel="noopener"
             style="font-family:'Cinzel',serif;font-size:.65rem;color:var(--green);letter-spacing:.06em">
            Entrar no grupo &rarr;
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Meta info -->
    <?php $tem_meta = $evento['data_evento'] || $evento['horario'] || $evento['local_evento']; ?>
    <?php if ($tem_meta): ?>
    <div class="meta-card">
      <?php if ($evento['data_evento']): ?>
      <div class="meta-item">
        <div class="meta-icon">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
        </div>
        <div>
          <div class="meta-lbl">Data</div>
          <div class="meta-val"><?= fmt_periodo_ev($evento['data_evento'], $evento['data_fim'] ?? null) ?></div>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($evento['horario']): ?>
      <div class="meta-item">
        <div class="meta-icon">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
        </div>
        <div>
          <div class="meta-lbl">Horário</div>
          <div class="meta-val"><?= htmlspecialchars($evento['horario']) ?></div>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($evento['local_evento']): ?>
      <div class="meta-item">
        <div class="meta-icon">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
        </div>
        <div>
          <div class="meta-lbl">Local</div>
          <div class="meta-val"><?= htmlspecialchars($evento['local_evento']) ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /sidebar -->

</div><!-- /ev-wrap -->

<?php endif; ?>

<footer class="ev-footer">
  <p>© <?= date('Y') ?> <strong>NAIOT</strong> — Comunidade Católica Senhor Jesus. Todos os direitos reservados.</p>
</footer>

</body>
</html>
