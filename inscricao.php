<?php
require_once __DIR__ . '/portal/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /'); exit; }

$ev_stmt = db()->prepare('SELECT * FROM eventos WHERE id = ? AND ativo = 1');
$ev_stmt->execute([$id]);
$evento = $ev_stmt->fetch();

$hoje = date('Y-m-d');

/* ── Lotes disponíveis ── */
$lotes = [];
if ($evento) {
    $ls = db()->prepare("
        SELECT l.*,
               (SELECT COUNT(*) FROM inscricoes i WHERE i.lote_id = l.id AND i.status != 'cancelado') AS inscritos
        FROM evento_lotes l
        WHERE l.evento_id = ? AND l.ativo = 1
          AND (l.data_inicio IS NULL OR l.data_inicio <= ?)
          AND (l.data_fim    IS NULL OR l.data_fim    >= ?)
        ORDER BY l.ordem ASC, l.id ASC
    ");
    $ls->execute([$id, $hoje, $hoje]);
    foreach ($ls->fetchAll() as $l) {
        if ($l['vagas'] === null || $l['inscritos'] < $l['vagas']) {
            $lotes[] = $l;
        }
    }
}

/* ── Total inscritos no evento ── */
$total_inscritos = 0;
if ($evento) {
    $cnt = db()->prepare("SELECT COUNT(*) FROM inscricoes WHERE evento_id = ? AND status != 'cancelado'");
    $cnt->execute([$id]);
    $total_inscritos = (int)$cnt->fetchColumn();
}

$evento_lotado = $evento && $evento['vagas'] && $total_inscritos >= $evento['vagas'];
$encerrado     = $evento && $evento['data_encerramento'] && $evento['data_encerramento'] < $hoje;
$pode_inscrever = $evento && $evento['inscricoes_abertas'] && !$evento_lotado && !$encerrado;

/* ── Tem valor? ── */
$tem_valor = false;
if ($evento) {
    $tem_valor = ($evento['valor'] > 0) || (!empty($lotes) && max(array_column($lotes, 'valor')) > 0);
}

/* ── Página de confirmação ── */
$inscricao_ok = null;
if (isset($_GET['ok'])) {
    $tok = preg_replace('/[^a-f0-9]/', '', (string)$_GET['ok']);
    if (strlen($tok) === 64) {
        $s = db()->prepare("
            SELECT i.*, e.titulo, e.data_evento, e.data_fim, e.local_evento, e.horario, l.nome AS lote_nome
            FROM inscricoes i
            JOIN eventos e ON i.evento_id = e.id
            LEFT JOIN evento_lotes l ON i.lote_id = l.id
            WHERE i.token = ? AND i.evento_id = ?
        ");
        $s->execute([$tok, $id]);
        $inscricao_ok = $s->fetch();
    }
}

/* ── Processar inscrição ── */
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pode_inscrever && !$inscricao_ok) {
    $nome     = mb_substr(trim(strip_tags($_POST['nome']     ?? '')), 0, 200);
    $email    = mb_strtolower(trim($_POST['email']           ?? ''));
    $telefone = mb_substr(trim($_POST['telefone']            ?? ''), 0, 30);
    $cpf      = mb_substr(trim($_POST['cpf']                 ?? ''), 0, 14);
    $dn       = $_POST['data_nascimento'] ?? '';
    $obs      = mb_substr(trim(strip_tags($_POST['observacoes'] ?? '')), 0, 1000);
    $lote_id  = (int)($_POST['lote_id']    ?? 0);
    $forma    = $_POST['forma_pagamento']  ?? 'gratuito';

    if (mb_strlen($nome) < 3) {
        $erro = 'Informe seu nome completo.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (mb_strlen(preg_replace('/\D/', '', $telefone)) < 8) {
        $erro = 'Informe seu telefone com DDD.';
    } else {
        $dup = db()->prepare("SELECT id FROM inscricoes WHERE evento_id = ? AND email = ? AND status != 'cancelado'");
        $dup->execute([$id, $email]);
        if ($dup->fetch()) {
            $erro = 'Este e-mail já possui inscrição ativa neste evento.';
        } else {
            $valor_pago    = 0.00;
            $lote_id_final = null;

            if (!empty($lotes) && $lote_id) {
                foreach ($lotes as $l) {
                    if ((int)$l['id'] === $lote_id) {
                        $valor_pago    = (float)$l['valor'];
                        $lote_id_final = (int)$l['id'];
                        break;
                    }
                }
                if (!$lote_id_final) { $erro = 'Selecione uma categoria de inscrição.'; }
            } elseif (empty($lotes) && $evento['valor'] > 0) {
                $valor_pago = (float)$evento['valor'];
            }

            if (!$erro && $valor_pago > 0 && !in_array($forma, ['pix','cartao','boleto'], true)) {
                $erro = 'Selecione uma forma de pagamento.';
            }
            if ($valor_pago == 0) { $forma = 'gratuito'; }

            if (!$erro) {
                $token      = bin2hex(random_bytes(32));
                $status_ini = ($valor_pago > 0) ? 'pendente' : 'confirmado';

                // inscricoes/evento_lotes são MyISAM (sem transação/row-lock), então
                // usamos LOCK TABLES para checar vagas e inserir de forma atômica e
                // evitar overselling por inscrições simultâneas na última vaga.
                db()->exec('LOCK TABLES inscricoes WRITE');
                try {
                    if ($evento['vagas']) {
                        $cnt = db()->prepare("SELECT COUNT(*) FROM inscricoes WHERE evento_id = ? AND status != 'cancelado'");
                        $cnt->execute([$id]);
                        if ((int)$cnt->fetchColumn() >= $evento['vagas']) {
                            $erro = 'As vagas deste evento acabaram de esgotar. Tente outro evento ou fale conosco.';
                        }
                    }

                    if (!$erro && $lote_id_final) {
                        foreach ($lotes as $l) {
                            if ((int)$l['id'] === $lote_id_final && $l['vagas'] !== null) {
                                $cnt = db()->prepare("SELECT COUNT(*) FROM inscricoes WHERE lote_id = ? AND status != 'cancelado'");
                                $cnt->execute([$lote_id_final]);
                                if ((int)$cnt->fetchColumn() >= $l['vagas']) {
                                    $erro = 'As vagas desta categoria acabaram de esgotar. Escolha outra categoria.';
                                }
                                break;
                            }
                        }
                    }

                    if (!$erro) {
                        db()->prepare("
                            INSERT INTO inscricoes
                                (evento_id, lote_id, nome, email, telefone, cpf, data_nascimento,
                                 valor_pago, forma_pagamento, status, observacoes, token, ip)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                        ")->execute([
                            $id, $lote_id_final, $nome, $email, $telefone,
                            $cpf ?: null, $dn ?: null, $valor_pago, $forma,
                            $status_ini, $obs ?: null, $token,
                            $_SERVER['REMOTE_ADDR'] ?? null,
                        ]);
                    }
                } finally {
                    db()->exec('UNLOCK TABLES');
                }

                if (!$erro) {
                    header("Location: /inscricao.php?id=$id&ok=$token");
                    exit;
                }
            }
        }
    }
}

/* ── Helpers de data ── */
$meses = ['','jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
function fmt_periodo_pub($ini, $fim) {
    global $meses;
    if (!$ini) return '';
    $di = date('j', strtotime($ini));  $mi = (int)date('n', strtotime($ini));  $ai = date('Y', strtotime($ini));
    if (!$fim || $fim === $ini) return "$di de {$meses[$mi]}. de $ai";
    $df = date('j', strtotime($fim));  $mf = (int)date('n', strtotime($fim));  $af = date('Y', strtotime($fim));
    if ($mi === $mf && $ai === $af) return "$di a $df de {$meses[$mf]}. de $af";
    return "$di de {$meses[$mi]} a $df de {$meses[$mf]}. de $af";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $evento ? 'Inscrição — ' . htmlspecialchars($evento['titulo']) . ' — ' : '' ?>NAIOT</title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
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
  --r:  10px; --rl: 18px;
  --sh-sm: 0 2px 14px rgba(0,0,0,.07);
  --sh:    0 4px 32px rgba(0,0,0,.10);
  --ease: .28s ease;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'EB Garamond', Georgia, serif;
  font-size: clamp(15px, 2vw, 17px);
  line-height: 1.75;
  color: var(--text);
  background: var(--off);
  min-height: 100vh;
  display: flex; flex-direction: column;
}
a   { text-decoration: none; color: inherit; }
img { max-width: 100%; height: auto; display: block; }

/* ── Header ── */
.insc-hdr {
  background: var(--white);
  border-top: 3px solid var(--green-dk);
  border-bottom: 1px solid var(--border);
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
  position: sticky; top: 0; z-index: 100;
}
.insc-hdr-inner {
  max-width: 860px; margin: 0 auto; padding: 0 20px;
  height: 66px;
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
}
.insc-logo-wrap {
  display: flex; align-items: center; gap: 10px;
}
.insc-logo { height: 46px; width: auto; mix-blend-mode: multiply; }
.insc-logo-txt {
  display: none;
  font-family: 'Cinzel', serif; font-size: 1.3rem; font-weight: 700;
  color: var(--green-dk); letter-spacing: .1em;
}
.insc-nome {
  font-family: 'Cinzel', serif; font-size: .6rem; font-weight: 600;
  letter-spacing: .09em; text-transform: uppercase; color: var(--muted);
}
@media (max-width: 420px) { .insc-nome { display: none; } }
.insc-back {
  display: inline-flex; align-items: center; gap: 5px;
  font-family: 'Cinzel', serif; font-size: .66rem; font-weight: 600;
  letter-spacing: .06em; color: var(--green); white-space: nowrap;
  padding: 6px 13px; border: 1.5px solid rgba(30,107,53,.3);
  border-radius: 6px; transition: all var(--ease);
}
.insc-back:hover { color: #fff; background: var(--green); border-color: var(--green); }

/* ── Wrap ── */
.wrap { max-width: 860px; margin: 0 auto; padding: 32px 20px 64px; flex: 1; }

/* ── Event card ── */
.ev-card  { background: var(--white); border-radius: var(--rl); overflow: hidden; box-shadow: var(--sh); margin-bottom: 28px; }
.ev-img   { width: 100%; height: auto; display: block; }
@media (min-width: 769px) {
  .ev-card { max-width: 50%; margin-left: auto; margin-right: auto; }
}
.ev-body  { padding: 24px 28px 26px; }
.ev-title {
  font-family: 'Cinzel', serif;
  font-size: clamp(1.1rem, 3vw, 1.5rem); font-weight: 700;
  color: var(--green-dk); line-height: 1.3; margin-bottom: 14px;
}
.ev-meta  { display: flex; flex-wrap: wrap; gap: 8px 18px; margin-bottom: 12px; }
.ev-meta-i {
  display: flex; align-items: center; gap: 6px;
  font-size: .88rem; color: var(--muted);
}
.ev-meta-i svg { width: 14px; height: 14px; flex-shrink: 0; color: var(--green); }
.ev-desc  { font-size: .9rem; color: var(--muted); font-style: italic; border-top: 1px solid var(--border); padding-top: 12px; }

/* ── Badges ── */
.badge-open {
  display: inline-flex; align-items: center; gap: 5px;
  background: var(--green-pale); color: var(--green);
  border: 1px solid rgba(30,107,53,.25);
  padding: 4px 13px; border-radius: 20px;
  font-family: 'Cinzel', serif; font-size: .65rem; font-weight: 700; letter-spacing: .1em;
  margin-bottom: 12px;
}
.badge-open::before {
  content: ''; width: 7px; height: 7px;
  background: var(--green); border-radius: 50%;
  animation: pulse-dot 2s infinite;
}
@keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.35} }

/* ── Vagas ── */
.vagas-wrap  { margin-top: 18px; background: var(--off); border-radius: 8px; padding: 12px 16px; }
.vagas-label {
  font-size: .8rem; color: var(--muted); margin-bottom: 6px;
  display: flex; justify-content: space-between; align-items: center;
}
.vagas-label strong { color: var(--text); }
.vagas-track { height: 7px; background: var(--border); border-radius: 4px; overflow: hidden; }
.vagas-fill  {
  height: 100%;
  background: linear-gradient(90deg, var(--green), var(--green-dk));
  border-radius: 4px; transition: width .6s;
}

/* ── Section label ── */
.sec-label {
  font-family: 'Cinzel', serif; font-size: .68rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase; color: var(--gold);
  margin-bottom: 12px; display: flex; align-items: center; gap: 10px;
}
.sec-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ── Lotes ── */
.lotes-wrap { margin-bottom: 24px; }
.lotes-grid { display: grid; gap: 10px; }
.lote-item  {
  display: flex; align-items: center; gap: 14px;
  background: var(--white); border: 2px solid var(--border);
  border-radius: var(--r); padding: 14px 18px; cursor: pointer;
  transition: border-color var(--ease), background var(--ease), box-shadow var(--ease);
}
.lote-item:has(input:checked) {
  border-color: var(--green); background: var(--green-pale);
  box-shadow: 0 0 0 3px rgba(30,107,53,.08);
}
.lote-item input[type=radio] { accent-color: var(--green); width: 17px; height: 17px; flex-shrink: 0; cursor: pointer; }
.lote-info  { flex: 1; min-width: 0; }
.lote-nome  { font-family: 'Cinzel', serif; font-size: .82rem; font-weight: 700; color: var(--green-dk); }
.lote-desc  { font-size: .78rem; color: var(--muted); font-style: italic; margin-top: 2px; }
.lote-sub   { font-size: .73rem; color: var(--muted); margin-top: 2px; }
.lote-preco { font-family: 'Cinzel', serif; font-weight: 700; color: var(--green-dk); white-space: nowrap; text-align: right; font-size: .92rem; }
.lote-preco.free { color: var(--green); }

/* ── Form ── */
.form-box {
  background: var(--white); border-radius: var(--rl);
  box-shadow: var(--sh-sm); padding: clamp(22px, 4vw, 36px);
  border-top: 3px solid var(--gold-lt);
}
.form-title {
  font-family: 'Cinzel', serif; font-size: clamp(.82rem, 2vw, .94rem);
  font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
  color: var(--green-dk); margin-bottom: 22px; padding-bottom: 14px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 8px;
}
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 520px) { .form-grid { grid-template-columns: 1fr; } }
.fg      { margin-bottom: 0; }
.fg.full { grid-column: 1 / -1; }
.lbl {
  display: block; font-family: 'Cinzel', serif; font-size: .64rem;
  font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
  color: var(--green-dk); margin-bottom: 6px;
}
.lbl .opt { font-weight: 400; color: var(--muted); font-style: italic; text-transform: none; letter-spacing: 0; }
input[type=text],
input[type=email],
input[type=tel],
input[type=date],
textarea {
  width: 100%; border: 1.5px solid var(--border); border-radius: var(--r);
  padding: 10px 13px; font-family: 'EB Garamond', serif; font-size: 1rem;
  color: var(--text); background: var(--off);
  transition: border-color var(--ease), box-shadow var(--ease), background var(--ease);
}
input:focus, textarea:focus {
  outline: none; border-color: var(--green);
  background: var(--white);
  box-shadow: 0 0 0 3px rgba(30,107,53,.09);
}
textarea { min-height: 88px; resize: vertical; }

/* ── Pagamento ── */
.pay-wrap {
  margin: 22px 0 18px; background: var(--off);
  border: 1px solid var(--border); border-radius: var(--rl); padding: 20px;
}
.pay-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin-top: 12px; }
@media (max-width: 380px) { .pay-grid { grid-template-columns: 1fr 1fr; } }
.pay-opt {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  border: 2px solid var(--border); border-radius: var(--r);
  padding: 14px 8px; cursor: pointer; text-align: center;
  background: var(--white);
  transition: border-color var(--ease), background var(--ease), box-shadow var(--ease);
}
.pay-opt:has(input:checked) {
  border-color: var(--green); background: var(--green-pale);
  box-shadow: 0 0 0 3px rgba(30,107,53,.08);
}
.pay-opt input { accent-color: var(--green); }
.pay-icon  { font-size: 1.4rem; line-height: 1; }
.pay-label {
  font-family: 'Cinzel', serif; font-size: .66rem; font-weight: 700;
  letter-spacing: .07em; text-transform: uppercase; color: var(--green-dk);
}
.pay-note { margin-top: 12px; font-size: .8rem; color: var(--muted); font-style: italic; text-align: center; }

/* ── Total ── */
.total-row {
  display: flex; justify-content: space-between; align-items: center;
  background: var(--green-pale); border-radius: var(--r);
  padding: 12px 16px; margin: 18px 0 14px;
  border: 1px solid rgba(30,107,53,.18);
}
.total-row .tl {
  font-family: 'Cinzel', serif; font-size: .7rem; font-weight: 700;
  letter-spacing: .08em; text-transform: uppercase; color: var(--green-dk);
}
.total-row .tp { font-family: 'Cinzel', serif; font-size: 1.3rem; font-weight: 700; color: var(--green-dk); }
.total-row .tp.free { color: var(--green); }

/* ── Botão principal ── */
.btn-sub {
  width: 100%; padding: 15px; background: var(--green-dk); color: #fff;
  border: none; border-radius: var(--r); cursor: pointer;
  font-family: 'Cinzel', serif; font-size: .78rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase; margin-top: 6px;
  transition: background var(--ease), transform var(--ease), box-shadow var(--ease);
}
.btn-sub:hover  { background: var(--green); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(22,61,34,.22); }
.btn-sub:active { transform: none; }

/* ── Alerta de erro ── */
.alert-err {
  background: #fee2e2; color: var(--red);
  border: 1px solid rgba(184,50,50,.3); border-left: 3px solid var(--red);
  padding: 12px 15px; border-radius: var(--r); margin-bottom: 16px; font-size: .9rem;
}

/* ── Página de sucesso ── */
.suc-card { background: var(--white); border-radius: var(--rl); box-shadow: var(--sh); overflow: hidden; }
.suc-head {
  background: var(--green-dk); padding: 36px 28px; color: #fff; text-align: center;
  position: relative; overflow: hidden;
}
.suc-head::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse at 50% 120%, rgba(201,168,76,.18) 0%, transparent 70%);
}
.suc-icon  { font-size: 2.6rem; margin-bottom: 12px; line-height: 1; position: relative; }
.suc-title {
  font-family: 'Cinzel', serif; font-size: 1.2rem; font-weight: 700;
  letter-spacing: .06em; text-transform: uppercase; margin-bottom: 8px; position: relative;
}
.suc-sub   { font-size: .92rem; opacity: .75; font-style: italic; position: relative; }
.suc-body  { padding: 28px; }
.suc-num-l {
  font-family: 'Cinzel', serif; font-size: .64rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase; color: var(--muted);
}
.suc-num   { font-family: 'Cinzel', serif; font-size: 2.2rem; font-weight: 700; color: var(--green-dk); margin-bottom: 22px; line-height: 1; }
.suc-rows  { display: grid; gap: 8px; margin-bottom: 22px; }
.suc-row   {
  display: flex; gap: 10px; align-items: baseline;
  background: var(--off); border-radius: 8px; padding: 10px 14px; font-size: .9rem;
}
.suc-key   {
  font-family: 'Cinzel', serif; font-size: .62rem; font-weight: 700;
  letter-spacing: .07em; text-transform: uppercase; color: var(--muted);
  white-space: nowrap; flex-shrink: 0; min-width: 72px;
}
.suc-val   { color: var(--text); }
.sb-pend   {
  background: #fef9ec; color: #92400e;
  border: 1px solid #fcd34d; border-left: 3px solid var(--gold);
  border-radius: var(--r); padding: 13px 16px; font-size: .86rem; margin-bottom: 18px;
}
.st-badge  {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 12px; border-radius: 20px;
  font-family: 'Cinzel', serif; font-size: .66rem; font-weight: 700; letter-spacing: .08em;
}
.st-confirmado { background: #dcfce7; color: #166534; }
.st-pendente   { background: #fef3c7; color: #92400e; }
.suc-btns  { display: flex; flex-direction: column; gap: 10px; }
.btn-wpp   {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 14px; background: #25d366; color: #fff; border-radius: var(--r);
  font-family: 'Cinzel', serif; font-size: .72rem; font-weight: 700;
  letter-spacing: .08em; text-transform: uppercase;
  transition: opacity .2s, transform .2s;
}
.btn-wpp:hover { opacity: .88; transform: translateY(-1px); }
.btn-home  {
  display: block; text-align: center; padding: 12px;
  border: 1.5px solid var(--border); border-radius: var(--r);
  color: var(--muted); font-family: 'Cinzel', serif; font-size: .7rem;
  font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
  transition: border-color var(--ease), color var(--ease);
}
.btn-home:hover { border-color: var(--green); color: var(--green); }

/* ── Info box (fechado/lotado/não encontrado) ── */
.info-box { background: var(--white); border-radius: var(--rl); box-shadow: var(--sh-sm); padding: 44px; text-align: center; }
.info-box .info-icon { font-size: 2.2rem; margin-bottom: 16px; }
.info-box h2 { font-family: 'Cinzel', serif; font-size: 1.1rem; color: var(--green-dk); margin-bottom: 10px; }
.info-box p  { color: var(--muted); font-style: italic; }
.info-box a  { display: inline-flex; align-items: center; gap: 5px; margin-top: 20px; color: var(--green); font-family: 'Cinzel', serif; font-size: .72rem; font-weight: 600; letter-spacing: .06em; }
.info-box a:hover { color: var(--green-dk); }

/* ── Footer ── */
.insc-footer {
  background: var(--white); border-top: 1px solid var(--border);
  padding: 18px 20px; text-align: center;
}
.insc-footer p { font-family: 'Cinzel', serif; font-size: .64rem; letter-spacing: .06em; color: var(--muted); }
.insc-footer strong { color: var(--green-dk); font-style: normal; }
</style>
</head>
<body>

<!-- ═══ HEADER ═══ -->
<header class="insc-hdr">
  <div class="insc-hdr-inner">
    <a href="/" class="insc-logo-wrap">
      <img src="/assets/img/logo.png" alt="NAIOT" class="insc-logo"
           onerror="this.style.display='none';document.querySelector('.insc-logo-txt').style.display='block'">
      <span class="insc-logo-txt">NAIOT</span>
      <span class="insc-nome">Comunidade Católica Senhor Jesus</span>
    </a>
    <?php if ($evento): ?>
    <a href="/evento.php?id=<?= $evento['id'] ?>" class="insc-back">&#8592; Voltar ao evento</a>
    <?php else: ?>
    <a href="/" class="insc-back">&#8592; Voltar ao site</a>
    <?php endif; ?>
  </div>
</header>

<div class="wrap">

<?php if (!$evento): ?>
<!-- ═══ Evento não encontrado ═══ -->
<div class="info-box">
  <div class="info-icon">&#x271D;&#xFE0E;</div>
  <h2>Evento não encontrado</h2>
  <p>O evento que você procura não está disponível.</p>
  <a href="/">&#8592; Voltar ao site da NAIOT</a>
</div>

<?php elseif ($inscricao_ok): ?>
<!-- ═══ SUCESSO ═══ -->
<div class="suc-card">
  <div class="suc-head">
    <div class="suc-icon">&#x271D;&#xFE0E;</div>
    <div class="suc-title"><?= $inscricao_ok['status'] === 'confirmado' ? 'Inscrição Confirmada!' : 'Inscrição Recebida!' ?></div>
    <div class="suc-sub">
      <?= $inscricao_ok['status'] === 'confirmado'
        ? 'Sua participação está confirmada. Nos vemos em breve!'
        : 'Recebemos seu pedido. Aguarde a confirmação do pagamento.' ?>
    </div>
  </div>
  <div class="suc-body">
    <div class="suc-num-l">Número da inscrição</div>
    <div class="suc-num">#<?= str_pad($inscricao_ok['id'], 5, '0', STR_PAD_LEFT) ?></div>

    <div class="suc-rows">
      <div class="suc-row"><span class="suc-key">Evento</span><span class="suc-val"><?= htmlspecialchars($inscricao_ok['titulo']) ?></span></div>
      <?php if ($inscricao_ok['data_evento']): ?>
      <div class="suc-row"><span class="suc-key">Data</span><span class="suc-val"><?= fmt_periodo_pub($inscricao_ok['data_evento'], $inscricao_ok['data_fim'] ?? null) ?></span></div>
      <?php endif; ?>
      <?php if ($inscricao_ok['local_evento']): ?>
      <div class="suc-row"><span class="suc-key">Local</span><span class="suc-val"><?= htmlspecialchars($inscricao_ok['local_evento']) ?></span></div>
      <?php endif; ?>
      <?php if ($inscricao_ok['horario']): ?>
      <div class="suc-row"><span class="suc-key">Horário</span><span class="suc-val"><?= htmlspecialchars($inscricao_ok['horario']) ?></span></div>
      <?php endif; ?>
      <div class="suc-row"><span class="suc-key">Nome</span><span class="suc-val"><?= htmlspecialchars($inscricao_ok['nome']) ?></span></div>
      <div class="suc-row"><span class="suc-key">E-mail</span><span class="suc-val"><?= htmlspecialchars($inscricao_ok['email']) ?></span></div>
      <?php if ($inscricao_ok['lote_nome']): ?>
      <div class="suc-row"><span class="suc-key">Categoria</span><span class="suc-val"><?= htmlspecialchars($inscricao_ok['lote_nome']) ?></span></div>
      <?php endif; ?>
      <?php if ($inscricao_ok['valor_pago'] > 0): ?>
      <div class="suc-row"><span class="suc-key">Valor</span><span class="suc-val">R$ <?= number_format($inscricao_ok['valor_pago'], 2, ',', '.') ?> — <?= ucfirst($inscricao_ok['forma_pagamento']) ?></span></div>
      <?php endif; ?>
      <div class="suc-row">
        <span class="suc-key">Status</span>
        <span class="suc-val">
          <span class="st-badge st-<?= $inscricao_ok['status'] ?>">
            <?= ['pendente'=>'Aguardando confirmação','confirmado'=>'Confirmado','checkin'=>'Check-in realizado'][$inscricao_ok['status']] ?? $inscricao_ok['status'] ?>
          </span>
        </span>
      </div>
    </div>

    <?php if ($inscricao_ok['status'] === 'pendente'): ?>
    <div class="sb-pend">
      ⚠️ Após a confirmação do pagamento, seu status será atualizado. Guarde o número
      <strong>#<?= str_pad($inscricao_ok['id'], 5, '0', STR_PAD_LEFT) ?></strong> para consultas.
    </div>
    <?php endif; ?>

    <div class="suc-btns">
      <?php
        $wpp_txt = 'Me inscrevi no evento "' . $inscricao_ok['titulo'] . '" da Comunidade NAIOT! Inscrição #' . str_pad($inscricao_ok['id'], 5, '0', STR_PAD_LEFT);
      ?>
      <a href="https://wa.me/?text=<?= urlencode($wpp_txt) ?>" target="_blank" class="btn-wpp">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Compartilhar no WhatsApp
      </a>
      <a href="/" class="btn-home">&#8592; Voltar ao site da NAIOT</a>
    </div>
  </div>
</div>

<?php elseif (!$pode_inscrever): ?>
<!-- ═══ Fechado / Lotado ═══ -->
<?php if ($evento['imagem']): ?>
<div class="ev-card" style="margin-bottom:20px">
  <img src="/assets/img/eventos/<?= htmlspecialchars($evento['imagem']) ?>"
       alt="<?= htmlspecialchars($evento['titulo']) ?>" class="ev-img">
</div>
<?php endif; ?>
<div class="info-box">
  <div class="info-icon">
    <?php if ($evento_lotado): ?>🚫
    <?php elseif ($encerrado): ?>⏰
    <?php else: ?>🔒
    <?php endif; ?>
  </div>
  <h2>
    <?php if ($evento_lotado): ?>Vagas esgotadas
    <?php elseif ($encerrado): ?>Inscrições encerradas
    <?php else: ?>Inscrições não abertas
    <?php endif; ?>
  </h2>
  <p>
    <?php if ($evento_lotado): ?>Todas as vagas foram preenchidas. Fique atento a novas turmas.
    <?php elseif ($encerrado): ?>O prazo para inscrições neste evento já encerrou.
    <?php else: ?>As inscrições para este evento ainda não foram abertas.
    <?php endif; ?>
  </p>
  <a href="/">&#8592; Voltar ao site</a>
</div>

<?php else: ?>
<!-- ═══ FORMULÁRIO DE INSCRIÇÃO ═══ -->

<!-- Card do evento -->
<div class="ev-card">
  <?php if ($evento['imagem']): ?>
  <img src="/assets/img/eventos/<?= htmlspecialchars($evento['imagem']) ?>"
       alt="<?= htmlspecialchars($evento['titulo']) ?>" class="ev-img">
  <?php endif; ?>
  <div class="ev-body">
    <span class="badge-open">Inscrições abertas</span>
    <div class="ev-title"><?= htmlspecialchars($evento['titulo']) ?></div>
    <div class="ev-meta">
      <?php if ($evento['data_evento']): ?>
      <div class="ev-meta-i">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
        <?= fmt_periodo_pub($evento['data_evento'], $evento['data_fim'] ?? null) ?>
      </div>
      <?php endif; ?>
      <?php if ($evento['horario']): ?>
      <div class="ev-meta-i">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
        <?= htmlspecialchars($evento['horario']) ?>
      </div>
      <?php endif; ?>
      <?php if ($evento['local_evento']): ?>
      <div class="ev-meta-i">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
        <?= htmlspecialchars($evento['local_evento']) ?>
      </div>
      <?php endif; ?>
      <?php if ($evento['data_encerramento']): ?>
      <div class="ev-meta-i">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
        Inscrições até <?= date('d/m/Y', strtotime($evento['data_encerramento'])) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($evento['descricao']): ?>
    <div class="ev-desc"><?= nl2br(htmlspecialchars($evento['descricao'])) ?></div>
    <?php endif; ?>
    <?php if ($evento['vagas']): ?>
    <div class="vagas-wrap">
      <?php $pct = min(100, round($total_inscritos / $evento['vagas'] * 100)); ?>
      <div class="vagas-label">
        <span><?= $total_inscritos ?> de <?= $evento['vagas'] ?> vagas preenchidas</span>
        <strong><?= $evento['vagas'] - $total_inscritos ?> disponíveis</strong>
      </div>
      <div class="vagas-track"><div class="vagas-fill" style="width:<?= $pct ?>%"></div></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<form method="POST" novalidate>

  <!-- Lotes -->
  <?php if (!empty($lotes)): ?>
  <div class="lotes-wrap">
    <div class="sec-label">Selecione uma categoria</div>
    <div class="lotes-grid">
      <?php foreach ($lotes as $idx => $l): ?>
      <label class="lote-item">
        <input type="radio" name="lote_id" value="<?= $l['id'] ?>"
               <?= ($idx === 0 ? 'checked' : '') ?> required onchange="atualizarTotal()">
        <div class="lote-info">
          <div class="lote-nome"><?= htmlspecialchars($l['nome']) ?></div>
          <?php if ($l['descricao']): ?><div class="lote-desc"><?= htmlspecialchars($l['descricao']) ?></div><?php endif; ?>
          <?php if ($l['data_fim']): ?><div class="lote-sub">até <?= date('d/m/Y', strtotime($l['data_fim'])) ?></div><?php endif; ?>
          <?php if ($l['vagas']): ?><div class="lote-sub"><?= $l['vagas'] - $l['inscritos'] ?> vagas disponíveis</div><?php endif; ?>
        </div>
        <div class="lote-preco <?= $l['valor'] == 0 ? 'free' : '' ?>" data-valor="<?= $l['valor'] ?>">
          <?= $l['valor'] > 0 ? 'R$ ' . number_format($l['valor'], 2, ',', '.') : 'Gratuito' ?>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Dados pessoais -->
  <div class="form-box">
    <div class="form-title">Dados do participante</div>

    <?php if ($erro): ?>
    <div class="alert-err"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="form-grid">
      <div class="fg full">
        <label class="lbl" for="nome">Nome completo</label>
        <input type="text" id="nome" name="nome" placeholder="Seu nome completo" required autocomplete="name"
               value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
      </div>
      <div class="fg">
        <label class="lbl" for="email">E-mail</label>
        <input type="email" id="email" name="email" placeholder="seu@email.com" required autocomplete="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="fg">
        <label class="lbl" for="telefone">Telefone / WhatsApp</label>
        <input type="tel" id="telefone" name="telefone" placeholder="(00) 00000-0000" required autocomplete="tel"
               value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
      </div>
      <div class="fg">
        <label class="lbl" for="cpf">CPF <span class="opt">(opcional)</span></label>
        <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" autocomplete="off"
               value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>">
      </div>
      <div class="fg">
        <label class="lbl" for="data_nascimento">Data de nascimento <span class="opt">(opcional)</span></label>
        <input type="date" id="data_nascimento" name="data_nascimento"
               value="<?= htmlspecialchars($_POST['data_nascimento'] ?? '') ?>">
      </div>
      <div class="fg full">
        <label class="lbl" for="observacoes">Observações <span class="opt">(opcional)</span></label>
        <textarea id="observacoes" name="observacoes"
                  placeholder="Necessidades especiais, dúvidas, recados..."><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Pagamento -->
    <div id="pay-box" class="pay-wrap" style="<?= $tem_valor ? '' : 'display:none' ?>">
      <div class="sec-label" style="margin-bottom:0">Forma de pagamento</div>
      <div class="pay-grid">
        <label class="pay-opt">
          <input type="radio" name="forma_pagamento" value="pix" <?= (($_POST['forma_pagamento'] ?? '') === 'pix' ? 'checked' : '') ?>>
          <span class="pay-icon">⚡</span><span class="pay-label">PIX</span>
        </label>
        <label class="pay-opt">
          <input type="radio" name="forma_pagamento" value="cartao" <?= (($_POST['forma_pagamento'] ?? '') === 'cartao' ? 'checked' : '') ?>>
          <span class="pay-icon">💳</span><span class="pay-label">Cartão</span>
        </label>
        <label class="pay-opt">
          <input type="radio" name="forma_pagamento" value="boleto" <?= (($_POST['forma_pagamento'] ?? '') === 'boleto' ? 'checked' : '') ?>>
          <span class="pay-icon">🎫</span><span class="pay-label">Boleto</span>
        </label>
      </div>
      <div class="pay-note">As instruções de pagamento serão enviadas por e-mail após a inscrição.</div>
    </div>

    <!-- Total -->
    <?php $val_base = empty($lotes) ? ($evento['valor'] ?? 0) : ($lotes[0]['valor'] ?? 0); ?>
    <div id="total-box" class="total-row" <?= ($val_base == 0 && empty($lotes)) ? 'style="display:none"' : '' ?>>
      <span class="tl">Total</span>
      <span class="tp <?= $val_base == 0 ? 'free' : '' ?>" id="total-val">
        <?= $val_base > 0 ? 'R$ ' . number_format($val_base, 2, ',', '.') : 'Gratuito' ?>
      </span>
    </div>

    <button type="submit" class="btn-sub">Confirmar inscrição</button>

    <p style="margin-top:14px;font-size:.79rem;color:var(--muted);text-align:center;font-style:italic">
      Seus dados são tratados com sigilo. Ao se inscrever você concorda com os termos da NAIOT.
    </p>
  </div>
</form>
<?php endif; ?>

</div><!-- /wrap -->

<footer class="insc-footer">
  <p>© 2026 <strong>NAIOT</strong> — Comunidade Católica Senhor Jesus. Todos os direitos reservados.</p>
</footer>

<script>
/* Máscara CPF */
document.getElementById('cpf')?.addEventListener('input', function() {
  var v = this.value.replace(/\D/g,'').slice(0,11);
  v = v.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
  this.value = v;
});
/* Máscara telefone */
document.getElementById('telefone')?.addEventListener('input', function() {
  var v = this.value.replace(/\D/g,'').slice(0,11);
  this.value = v.length <= 10
    ? v.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3').replace(/-$/,'')
    : v.replace(/(\d{2})(\d{5})(\d{0,4})/,'($1) $2-$3').replace(/-$/,'');
});
/* Atualizar total ao mudar lote */
function atualizarTotal() {
  var sel = document.querySelector('input[name=lote_id]:checked');
  if (!sel) return;
  var val = parseFloat(sel.closest('.lote-item').querySelector('[data-valor]').dataset.valor || '0');
  document.getElementById('total-box').style.display = '';
  document.getElementById('total-val').textContent = val > 0 ? 'R$ ' + val.toLocaleString('pt-BR',{minimumFractionDigits:2}) : 'Gratuito';
  document.getElementById('total-val').className = 'tp' + (val > 0 ? '' : ' free');
  document.getElementById('pay-box').style.display = val > 0 ? '' : 'none';
}
</script>
</body>
</html>
