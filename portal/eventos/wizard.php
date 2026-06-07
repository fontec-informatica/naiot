<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$pagina_ativa = 'inscricoes';
$evento_id    = (int)($_GET['id']   ?? 0);
$step         = max(1, min(4, (int)($_GET['step'] ?? 1)));
$erro         = '';

/* ── Carregar evento ── */
$evento = null;
if ($evento_id) {
    $s = db()->prepare('SELECT * FROM eventos WHERE id = ?');
    $s->execute([$evento_id]);
    $evento = $s->fetch();
    if (!$evento) { header('Location: /portal/eventos/wizard.php'); exit; }
}
if (!$evento_id && $step > 1) { header('Location: /portal/eventos/wizard.php'); exit; }

$titulo = $evento ? 'Configurar: ' . $evento['titulo'] : 'Novo Evento';

/* ══════════════════════════════════════════
   STEP 1 — Informações básicas
   ══════════════════════════════════════════ */
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $nome_ev    = trim($_POST['titulo']            ?? '');
    $descricao  = trim($_POST['descricao']         ?? '');
    $sobre      = trim($_POST['sobre']             ?? '');
    $data_ev    = $_POST['data_evento']            ?? '';
    $data_fim   = $_POST['data_fim']               ?? '';
    $local      = trim($_POST['local_evento']      ?? '');
    $horario    = trim($_POST['horario']           ?? '');
    $email_org  = trim($_POST['email_organizador'] ?? '');
    $whatsapp   = trim($_POST['whatsapp_contato']  ?? '');
    $vagas      = (int)($_POST['vagas']            ?? 0) ?: null;
    $valor      = (float)str_replace(',', '.', preg_replace('/[^0-9,]/', '', $_POST['valor'] ?? '0'));
    $data_enc   = $_POST['data_encerramento']      ?: null;
    $insc_ab    = isset($_POST['inscricoes_abertas']) ? 1 : 0;
    $mensagem   = trim($_POST['mensagem_inscrito'] ?? '');
    $link_grupo = trim($_POST['link_grupo']        ?? '');

    if (!$nome_ev) {
        $erro = 'O título do evento é obrigatório.';
    } else {
        /* Upload de imagem (opcional no wizard) */
        $img = $evento['imagem'] ?? '';
        if (!empty($_FILES['imagem']['tmp_name'])) {
            $finfo      = new finfo(FILEINFO_MIME_TYPE);
            $mime       = $finfo->file($_FILES['imagem']['tmp_name']);
            $permitidos = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
            if (!isset($permitidos[$mime])) {
                $erro = 'Formato inválido. Use JPG, PNG ou WebP.';
            } elseif ($_FILES['imagem']['size'] > 8 * 1024 * 1024) {
                $erro = 'Imagem muito grande (máximo 8 MB).';
            } else {
                $ext      = $permitidos[$mime];
                $filename = 'evt_' . uniqid() . '.' . $ext;
                $destino  = dirname(__DIR__, 2) . '/assets/img/eventos/' . $filename;
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
                    if ($img && file_exists(dirname(__DIR__, 2) . '/assets/img/eventos/' . $img)) {
                        @unlink(dirname(__DIR__, 2) . '/assets/img/eventos/' . $img);
                    }
                    $img = $filename;
                } else {
                    $erro = 'Erro ao salvar a imagem.';
                }
            }
        }

        if (!$erro) {
            if ($evento_id) {
                $params = [$nome_ev,$descricao?:null,$sobre?:null,$data_ev?:null,$data_fim?:null,$local?:null,$horario?:null,$email_org?:null,$whatsapp?:null,$vagas,$valor,$data_enc,$insc_ab,$mensagem?:null,$link_grupo?:null,$evento_id];
                $set = "titulo=?,descricao=?,sobre=?,data_evento=?,data_fim=?,local_evento=?,horario=?,email_organizador=?,whatsapp_contato=?,vagas=?,valor=?,data_encerramento=?,inscricoes_abertas=?,mensagem_inscrito=?,link_grupo=?";
                if ($img) { $set .= ",imagem=?"; array_splice($params, -1, 0, [$img]); }
                db()->prepare("UPDATE eventos SET $set WHERE id=?")->execute($params);
            } else {
                $img_final = $img ?: '';
                db()->prepare("INSERT INTO eventos (titulo,descricao,sobre,data_evento,data_fim,local_evento,horario,email_organizador,whatsapp_contato,vagas,valor,data_encerramento,inscricoes_abertas,mensagem_inscrito,link_grupo,imagem,ativo,ordem) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0)")
                    ->execute([$nome_ev,$descricao?:null,$sobre?:null,$data_ev?:null,$data_fim?:null,$local?:null,$horario?:null,$email_org?:null,$whatsapp?:null,$vagas,$valor,$data_enc,$insc_ab,$mensagem?:null,$link_grupo?:null,$img_final]);
                $evento_id = (int)db()->lastInsertId();
            }
            header("Location: /portal/eventos/wizard.php?id=$evento_id&step=2");
            exit;
        }
    }
}

/* ══════════════════════════════════════════
   STEP 2 — Lotes
   ══════════════════════════════════════════ */
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'add_lote') {
        $nome    = trim($_POST['nome']  ?? '');
        $valor   = (float)str_replace(',', '.', preg_replace('/[^0-9,]/', '', $_POST['valor'] ?? '0'));
        $vagas   = (int)($_POST['vagas'] ?? 0) ?: null;
        $data_i  = $_POST['data_inicio'] ?: null;
        $data_f  = $_POST['data_fim']    ?: null;
        if ($nome) {
            db()->prepare("INSERT INTO evento_lotes (evento_id,nome,valor,vagas,data_inicio,data_fim,ordem,ativo) VALUES (?,?,?,?,?,?,0,1)")
                ->execute([$evento_id,$nome,$valor,$vagas,$data_i,$data_f]);
        }
        header("Location: /portal/eventos/wizard.php?id=$evento_id&step=2");
        exit;
    } elseif ($acao === 'del_lote') {
        $lid = (int)($_POST['lote_id'] ?? 0);
        if ($lid) db()->prepare("DELETE FROM evento_lotes WHERE id=? AND evento_id=?")->execute([$lid,$evento_id]);
        header("Location: /portal/eventos/wizard.php?id=$evento_id&step=2");
        exit;
    } elseif ($acao === 'next') {
        header("Location: /portal/eventos/wizard.php?id=$evento_id&step=3");
        exit;
    }
}

/* ══════════════════════════════════════════
   STEP 3 — Campos do formulário
   ══════════════════════════════════════════ */
$CAMPOS_PADRAO = [
    ['campo'=>'telefone',       'label'=>'Telefone / WhatsApp', 'tipo'=>'tel',      'obrigatorio'=>1,'ativo'=>1],
    ['campo'=>'cpf',            'label'=>'CPF / Documento',     'tipo'=>'text',     'obrigatorio'=>0,'ativo'=>1],
    ['campo'=>'data_nascimento','label'=>'Data de Nascimento',  'tipo'=>'date',     'obrigatorio'=>0,'ativo'=>1],
    ['campo'=>'cidade',         'label'=>'Cidade',              'tipo'=>'text',     'obrigatorio'=>0,'ativo'=>0],
    ['campo'=>'estado',         'label'=>'Estado',              'tipo'=>'text',     'obrigatorio'=>0,'ativo'=>0],
    ['campo'=>'sexo',           'label'=>'Sexo',                'tipo'=>'select',   'obrigatorio'=>0,'ativo'=>0],
    ['campo'=>'cep',            'label'=>'CEP',                 'tipo'=>'text',     'obrigatorio'=>0,'ativo'=>0],
    ['campo'=>'nome_parceiro',  'label'=>'Nome do Parceiro(a)', 'tipo'=>'text',     'obrigatorio'=>0,'ativo'=>0],
    ['campo'=>'observacoes',    'label'=>'Observações',         'tipo'=>'textarea', 'obrigatorio'=>0,'ativo'=>1],
];

function wiz_init_campos(int $evento_id, array $padrao): void {
    try {
        foreach ($padrao as $ord => $d) {
            db()->prepare("INSERT IGNORE INTO evento_campos (evento_id,campo,label,tipo,obrigatorio,ativo,ordem) VALUES (?,?,?,?,?,?,?)")
                ->execute([$evento_id,$d['campo'],$d['label'],$d['tipo'],$d['obrigatorio'],$d['ativo'],$ord]);
        }
    } catch (Exception $e) {}
}

if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'save_campos') {
        try {
            $cs = db()->prepare("SELECT id FROM evento_campos WHERE evento_id=?");
            $cs->execute([$evento_id]);
            foreach ($cs->fetchAll() as $c) {
                $ativo = isset($_POST['ativo_'.$c['id']]) ? 1 : 0;
                $obrig = isset($_POST['obrig_'.$c['id']]) ? 1 : 0;
                db()->prepare("UPDATE evento_campos SET ativo=?,obrigatorio=? WHERE id=? AND evento_id=?")
                    ->execute([$ativo,$obrig,$c['id'],$evento_id]);
            }
        } catch (Exception $e) {}
        header("Location: /portal/eventos/wizard.php?id=$evento_id&step=4");
        exit;
    }
}

/* ══════════════════════════════════════════
   STEP 4 — Publicar
   ══════════════════════════════════════════ */
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'publicar') {
        $ordem = (int)($_POST['ordem'] ?? 0);
        db()->prepare("UPDATE eventos SET ativo=1, ordem=? WHERE id=?")->execute([$ordem,$evento_id]);
        header("Location: /portal/inscricoes/evento.php?id=$evento_id&wizard_ok=1");
        exit;
    }
    if ($acao === 'save_sobre') {
        $sobre      = trim($_POST['sobre']             ?? '');
        $email_org  = trim($_POST['email_organizador'] ?? '');
        $whatsapp   = trim($_POST['whatsapp_contato']  ?? '');
        $mensagem   = trim($_POST['mensagem_inscrito'] ?? '');
        $link_grupo = trim($_POST['link_grupo']        ?? '');
        db()->prepare("UPDATE eventos SET sobre=?,email_organizador=?,whatsapp_contato=?,mensagem_inscrito=?,link_grupo=? WHERE id=?")
            ->execute([$sobre?:null,$email_org?:null,$whatsapp?:null,$mensagem?:null,$link_grupo?:null,$evento_id]);
        header("Location: /portal/eventos/wizard.php?id=$evento_id&step=4&ok=1");
        exit;
    }
    if ($acao === 'add_prog') {
        $horario = trim($_POST['prog_horario']  ?? '');
        $ptitulo = trim($_POST['prog_titulo']   ?? '');
        $pdesc   = trim($_POST['prog_descricao'] ?? '');
        if ($ptitulo) {
            try { db()->prepare("INSERT INTO evento_programacao (evento_id,horario,titulo,descricao,ordem) VALUES (?,?,?,?,0)")->execute([$evento_id,$horario?:null,$ptitulo,$pdesc?:null]); } catch(Exception $e) {}
        }
        header("Location: /portal/eventos/wizard.php?id=$evento_id&step=4");
        exit;
    }
    if ($acao === 'del_prog') {
        $pid = (int)($_POST['prog_id'] ?? 0);
        if ($pid) { try { db()->prepare("DELETE FROM evento_programacao WHERE id=? AND evento_id=?")->execute([$pid,$evento_id]); } catch(Exception $e) {} }
        header("Location: /portal/eventos/wizard.php?id=$evento_id&step=4");
        exit;
    }
}

/* ── Dados para cada step ── */
$lotes_list = [];
if ($step === 2 && $evento_id) {
    $ls = db()->prepare("SELECT l.*,(SELECT COUNT(*) FROM inscricoes i WHERE i.lote_id=l.id AND i.status!='cancelado') AS inscritos FROM evento_lotes l WHERE l.evento_id=? ORDER BY l.ordem ASC,l.id ASC");
    $ls->execute([$evento_id]);
    $lotes_list = $ls->fetchAll();
}

$campos_list = [];
if ($step === 3 && $evento_id) {
    wiz_init_campos($evento_id, $CAMPOS_PADRAO);
    try {
        $cs = db()->prepare("SELECT * FROM evento_campos WHERE evento_id=? ORDER BY ordem ASC,id ASC");
        $cs->execute([$evento_id]);
        $campos_list = $cs->fetchAll();
    } catch (Exception $e) {}
}

$prog_list = [];
if ($step === 4 && $evento_id) {
    try {
        $ps = db()->prepare("SELECT * FROM evento_programacao WHERE evento_id=? ORDER BY ordem ASC,id ASC");
        $ps->execute([$evento_id]);
        $prog_list = $ps->fetchAll();
    } catch (Exception $e) {}
    // Reload event
    $s = db()->prepare('SELECT * FROM eventos WHERE id = ?');
    $s->execute([$evento_id]);
    $evento = $s->fetch();
}

include dirname(__DIR__) . '/_layout.php';

/* ── Wizard step indicator ── */
$step_labels = ['Informações','Pagamento','Formulário','Publicar'];
$step_hrefs  = [
    1 => null,
    2 => $evento_id ? "/portal/eventos/wizard.php?id=$evento_id&step=2" : null,
    3 => $evento_id ? "/portal/eventos/wizard.php?id=$evento_id&step=3" : null,
    4 => $evento_id ? "/portal/eventos/wizard.php?id=$evento_id&step=4" : null,
];
?>

<!-- ── Wizard header ── -->
<div style="background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:20px 28px;margin-bottom:28px">
  <div style="display:flex;align-items:center;justify-content:center;gap:0;overflow-x:auto">
    <?php for ($n = 1; $n <= 4; $n++):
      $is_active = $n === $step;
      $is_done   = $n < $step;
      $href      = $is_done ? $step_hrefs[$n] : null;
    ?>
    <div style="display:flex;align-items:center;gap:0">
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:72px">
        <?php if ($href): ?><a href="<?= $href ?>" style="text-decoration:none"><?php endif; ?>
        <div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;
          <?= $is_active ? 'background:var(--azul2);color:#fff' : ($is_done ? 'background:#dcfce7;color:#166534;border:2px solid #86efac' : 'background:var(--cinza2);color:var(--cinza3)') ?>">
          <?= $is_done ? '✓' : $n ?>
        </div>
        <span style="font-size:.68rem;color:<?= $is_active ? 'var(--azul2)' : ($is_done ? '#166534' : 'var(--cinza3)') ?>;font-weight:<?= $is_active ? '700' : '400' ?>;white-space:nowrap">
          <?= $step_labels[$n - 1] ?>
        </span>
        <?php if ($href): ?></a><?php endif; ?>
      </div>
      <?php if ($n < 4): ?>
      <div style="width:36px;height:2px;background:<?= $is_done ? '#86efac' : 'var(--cinza2)' ?>;margin-bottom:20px"></div>
      <?php endif; ?>
    </div>
    <?php endfor; ?>
  </div>
</div>

<?php if ($erro): ?><div class="alerta alerta-erro" style="margin-bottom:20px"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="alerta alerta-ok" style="margin-bottom:20px">Salvo com sucesso!</div><?php endif; ?>

<?php /* ══════════════════════════════════════════
         RENDER STEP
         ══════════════════════════════════════════ */ ?>

<?php if ($step === 1): ?>
<!-- ── Step 1: Informações básicas ── -->
<div class="form-wrap">
  <h2><?= $evento_id ? 'Editar informações' : 'Novo evento' ?></h2>
  <form method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label for="titulo">Nome do evento <span style="color:var(--vermelho)">*</span></label>
      <input type="text" id="titulo" name="titulo" required placeholder="Ex: 14º Resgate"
             value="<?= htmlspecialchars($_POST['titulo'] ?? $evento['titulo'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="descricao">Subtítulo / resumo <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="text" id="descricao" name="descricao" placeholder="Frase curta que aparece no carrossel"
             value="<?= htmlspecialchars($_POST['descricao'] ?? $evento['descricao'] ?? '') ?>">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="data_evento">Data de início</label>
        <input type="date" id="data_evento" name="data_evento"
               value="<?= htmlspecialchars($_POST['data_evento'] ?? $evento['data_evento'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="data_fim">Data de término</label>
        <input type="date" id="data_fim" name="data_fim"
               value="<?= htmlspecialchars($_POST['data_fim'] ?? $evento['data_fim'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="local_evento">Local</label>
        <input type="text" id="local_evento" name="local_evento" placeholder="Ex: Casa de Retiros São João"
               value="<?= htmlspecialchars($_POST['local_evento'] ?? $evento['local_evento'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="horario">Horário</label>
        <input type="text" id="horario" name="horario" placeholder="Ex: Sex 19h a Dom 12h"
               value="<?= htmlspecialchars($_POST['horario'] ?? $evento['horario'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="email_organizador">E-mail do organizador</label>
        <input type="email" id="email_organizador" name="email_organizador" placeholder="contato@naiot.com.br"
               value="<?= htmlspecialchars($_POST['email_organizador'] ?? $evento['email_organizador'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="whatsapp_contato">WhatsApp de contato</label>
        <input type="tel" id="whatsapp_contato" name="whatsapp_contato" placeholder="(99) 99999-9999"
               value="<?= htmlspecialchars($_POST['whatsapp_contato'] ?? $evento['whatsapp_contato'] ?? '') ?>">
      </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--cinza2);margin:8px 0 16px">
    <p style="font-size:.82rem;color:var(--cinza3);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Inscrições</p>

    <div class="form-row">
      <div class="form-group">
        <label for="vagas">Vagas totais <span style="font-weight:400;color:var(--cinza3)">(vazio = ilimitado)</span></label>
        <input type="number" id="vagas" name="vagas" min="1" placeholder="Ilimitado"
               value="<?= htmlspecialchars($_POST['vagas'] ?? $evento['vagas'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="valor">Valor base (R$) <span style="font-weight:400;color:var(--cinza3)">(0 = gratuito)</span></label>
        <input type="text" id="valor" name="valor" placeholder="0,00"
               value="<?= htmlspecialchars($_POST['valor'] ?? number_format($evento['valor'] ?? 0, 2, ',', '')) ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="data_encerramento">Encerrar inscrições em <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="date" id="data_encerramento" name="data_encerramento"
               value="<?= htmlspecialchars($_POST['data_encerramento'] ?? $evento['data_encerramento'] ?? '') ?>">
      </div>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="inscricoes_abertas" value="1"
               <?= (($_POST['inscricoes_abertas'] ?? $evento['inscricoes_abertas'] ?? 0) ? 'checked' : '') ?>>
        <span><strong>Inscrições abertas</strong> — exibe botão "Inscrever-se" no site</span>
      </label>
    </div>

    <div class="form-group">
      <label for="link_grupo">Link do grupo / comunidade <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="url" id="link_grupo" name="link_grupo" placeholder="https://chat.whatsapp.com/..."
             value="<?= htmlspecialchars($_POST['link_grupo'] ?? $evento['link_grupo'] ?? '') ?>">
      <span class="form-hint">Exibido na página do evento e após a inscrição.</span>
    </div>

    <div class="form-group">
      <label for="mensagem_inscrito">Mensagem para o inscrito <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <textarea id="mensagem_inscrito" name="mensagem_inscrito" rows="2"
                placeholder="Mensagem exibida na confirmação da inscrição."><?= htmlspecialchars($_POST['mensagem_inscrito'] ?? $evento['mensagem_inscrito'] ?? '') ?></textarea>
    </div>

    <hr style="border:none;border-top:1px solid var(--cinza2);margin:8px 0 16px">
    <p style="font-size:.82rem;color:var(--cinza3);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Imagem do evento</p>

    <?php if ($evento && $evento['imagem']): ?>
    <div class="form-group">
      <label>Imagem atual</label>
      <img src="/assets/img/eventos/<?= htmlspecialchars($evento['imagem']) ?>" alt="" style="max-height:140px;border-radius:8px;margin-bottom:8px">
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label for="imagem"><?= $evento ? 'Substituir imagem' : 'Imagem' ?> <span style="font-weight:400;color:var(--cinza3)"><?= $evento ? '(deixe vazio para manter)' : '(opcional — pode adicionar depois)' ?></span></label>
      <input type="file" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
      <span class="form-hint">JPG, PNG, WebP — máximo 8 MB. Recomendado: 1600×848 px.</span>
    </div>

    <button type="submit" class="btn btn-primary">Próximo passo →</button>
  </form>
</div>

<?php elseif ($step === 2): ?>
<!-- ── Step 2: Lotes ── -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start">

  <div>
    <div style="margin-bottom:16px">
      <h2 style="font-size:1rem;font-weight:600"><?= htmlspecialchars($evento['titulo']) ?></h2>
      <p style="font-size:.85rem;color:var(--cinza3)">Configure as modalidades/preços das inscrições. Pule esta etapa se o evento for gratuito com valor único.</p>
    </div>

    <?php if (empty($lotes_list)): ?>
    <div style="background:#fff;border-radius:10px;padding:32px;text-align:center;color:var(--cinza3);box-shadow:0 1px 4px rgba(0,0,0,.06)">
      Nenhum lote cadastrado. Adicione ao lado →<br>
      <small>Pule esta etapa se o evento for gratuito ou tiver preço único (configurado no passo anterior).</small>
    </div>
    <?php else: ?>
    <div class="tabela-wrap">
      <table>
        <thead><tr><th>Nome</th><th style="text-align:right">Valor</th><th>Período</th><th>Vagas</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lotes_list as $l): ?>
        <tr>
          <td><strong><?= htmlspecialchars($l['nome']) ?></strong></td>
          <td style="text-align:right;font-weight:700"><?= $l['valor'] > 0 ? 'R$ '.number_format($l['valor'],2,',','.') : '<span style="color:var(--verde)">Gratuito</span>' ?></td>
          <td style="font-size:.82rem;color:var(--cinza3)">
            <?= ($l['data_inicio'] || $l['data_fim']) ? (($l['data_inicio'] ? date('d/m/Y', strtotime($l['data_inicio'])) : '').' a '.($l['data_fim'] ? date('d/m/Y', strtotime($l['data_fim'])) : '')) : 'Sempre' ?>
          </td>
          <td><?= $l['vagas'] ?? '∞' ?></td>
          <td>
            <form method="post" style="display:inline" onsubmit="return confirm('Excluir lote?')">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="lote_id" value="<?= $l['id'] ?>">
              <button name="acao" value="del_lote" class="btn btn-danger btn-sm">✕</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <form method="post" style="margin-top:20px">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <button name="acao" value="next" class="btn btn-primary">Próximo passo →</button>
    </form>
  </div>

  <!-- Adicionar lote -->
  <div class="form-wrap" style="max-width:none">
    <h2>Novo lote</h2>
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="add_lote">

      <div class="form-group">
        <label>Nome <span style="color:var(--vermelho)">*</span></label>
        <input type="text" name="nome" placeholder="Ex: 1º Lote, Adulto, Criança..." required>
      </div>
      <div class="form-group">
        <label>Valor (R$)</label>
        <input type="text" name="valor" placeholder="0,00">
        <span class="form-hint">0,00 = gratuito</span>
      </div>
      <div class="form-row" style="gap:10px">
        <div class="form-group">
          <label>Válido de</label>
          <input type="date" name="data_inicio">
        </div>
        <div class="form-group">
          <label>Válido até</label>
          <input type="date" name="data_fim">
        </div>
      </div>
      <div class="form-group">
        <label>Vagas neste lote <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="number" name="vagas" min="1" placeholder="Ilimitado">
      </div>
      <button type="submit" class="btn btn-ouro" style="width:100%">Adicionar lote</button>
    </form>
  </div>
</div>

<?php elseif ($step === 3): ?>
<!-- ── Step 3: Campos do formulário ── -->
<div style="display:grid;grid-template-columns:1fr 280px;gap:24px;align-items:start">
  <div>
    <div style="margin-bottom:16px">
      <h2 style="font-size:1rem;font-weight:600">Campos do formulário de inscrição</h2>
      <p style="font-size:.85rem;color:var(--cinza3)">Configure quais dados serão solicitados ao participante. Nome e e-mail são sempre obrigatórios.</p>
    </div>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="save_campos">

      <div class="tabela-wrap">
        <table>
          <thead><tr><th>Campo</th><th style="text-align:center">Obrigatório</th><th style="text-align:center">Ativo</th></tr></thead>
          <tbody>
            <tr style="background:var(--cinza1)">
              <td><strong>Nome completo</strong> <small style="color:var(--cinza3)">— sempre obrigatório</small></td>
              <td style="text-align:center"><span style="color:var(--verde)">✓</span></td>
              <td style="text-align:center"><span style="color:var(--verde)">✓</span></td>
            </tr>
            <tr style="background:var(--cinza1)">
              <td><strong>E-mail</strong> <small style="color:var(--cinza3)">— sempre obrigatório</small></td>
              <td style="text-align:center"><span style="color:var(--verde)">✓</span></td>
              <td style="text-align:center"><span style="color:var(--verde)">✓</span></td>
            </tr>
            <?php foreach ($campos_list as $c): ?>
            <tr>
              <td><strong style="font-size:.88rem"><?= htmlspecialchars($c['label']) ?></strong></td>
              <td style="text-align:center">
                <input type="checkbox" name="obrig_<?= $c['id'] ?>" value="1" <?= $c['obrigatorio'] ? 'checked' : '' ?> <?= !$c['ativo'] ? 'disabled' : '' ?> style="accent-color:var(--azul2);width:16px;height:16px">
              </td>
              <td style="text-align:center">
                <input type="checkbox" name="ativo_<?= $c['id'] ?>" value="1" <?= $c['ativo'] ? 'checked' : '' ?>
                       onchange="document.querySelector('[name=obrig_<?= $c['id'] ?>]') && (document.querySelector('[name=obrig_<?= $c['id'] ?>]').disabled=!this.checked)"
                       style="accent-color:var(--verde);width:16px;height:16px">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top:16px">Salvar e continuar →</button>
    </form>
  </div>

  <div style="background:#fff;border-radius:10px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.07)">
    <p style="font-weight:600;font-size:.88rem;margin-bottom:12px">Dica</p>
    <p style="font-size:.83rem;color:var(--cinza3);line-height:1.6">
      Ative apenas os campos que você realmente precisa. Formulários mais curtos têm maior taxa de conclusão.<br><br>
      Para eventos gratuitos, considere pedir apenas Nome, E-mail e Telefone.
    </p>
    <hr style="margin:14px 0;border:none;border-top:1px solid var(--cinza2)">
    <p style="font-size:.8rem;color:var(--cinza3)">Pode alterar os campos a qualquer momento em <a href="/portal/inscricoes/campos.php?id=<?= $evento_id ?>" style="color:var(--azul2)">Configurar campos</a>.</p>
  </div>
</div>

<?php elseif ($step === 4): ?>
<!-- ── Step 4: Publicar ── -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">

  <div>
    <div style="margin-bottom:20px">
      <h2 style="font-size:1rem;font-weight:600">Conteúdo da página pública</h2>
      <p style="font-size:.85rem;color:var(--cinza3)">Configure a página que os participantes verão antes de se inscrever.</p>
    </div>

    <!-- Abas internas -->
    <div style="display:flex;border-bottom:2px solid var(--cinza2);margin-bottom:20px">
      <?php $aba4 = $_GET['aba'] ?? 'sobre'; ?>
      <a href="?id=<?= $evento_id ?>&step=4&aba=sobre"  style="padding:8px 18px;font-size:.85rem;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-2px;color:<?= $aba4==='sobre' ? 'var(--azul2)' : 'var(--cinza3)' ?>;border-bottom-color:<?= $aba4==='sobre' ? 'var(--azul2)' : 'transparent' ?>">Sobre & Contato</a>
      <a href="?id=<?= $evento_id ?>&step=4&aba=prog"   style="padding:8px 18px;font-size:.85rem;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-2px;color:<?= $aba4==='prog' ? 'var(--azul2)' : 'var(--cinza3)' ?>;border-bottom-color:<?= $aba4==='prog' ? 'var(--azul2)' : 'transparent' ?>">Programação</a>
    </div>

    <?php if ($aba4 === 'sobre'): ?>
    <form method="post" class="form-wrap" style="max-width:none;padding:0;box-shadow:none;background:transparent">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="save_sobre">

      <div class="form-group">
        <label>Sobre o evento</label>
        <textarea name="sobre" rows="7" style="resize:vertical" placeholder="Descreva o evento com detalhes..."><?= htmlspecialchars($evento['sobre'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>E-mail de contato</label>
          <input type="email" name="email_organizador" placeholder="contato@naiot.com.br" value="<?= htmlspecialchars($evento['email_organizador'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>WhatsApp de contato</label>
          <input type="tel" name="whatsapp_contato" placeholder="(99) 99999-9999" value="<?= htmlspecialchars($evento['whatsapp_contato'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Link do grupo <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="url" name="link_grupo" placeholder="https://chat.whatsapp.com/..." value="<?= htmlspecialchars($evento['link_grupo'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Mensagem para o inscrito <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <textarea name="mensagem_inscrito" rows="2" placeholder="Exibida na confirmação da inscrição."><?= htmlspecialchars($evento['mensagem_inscrito'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-ouro">Salvar conteúdo</button>
    </form>

    <?php else: ?>
    <div style="display:grid;grid-template-columns:1fr 260px;gap:16px;align-items:start">
      <div>
        <?php if (empty($prog_list)): ?>
        <div style="background:#fff;border-radius:10px;padding:24px;text-align:center;color:var(--cinza3);font-size:.88rem">Nenhuma atividade. Adicione ao lado →</div>
        <?php else: ?>
        <div class="tabela-wrap">
          <table>
            <thead><tr><th style="width:80px">Horário</th><th>Título</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($prog_list as $p): ?>
            <tr>
              <td style="color:var(--verde);font-weight:600;font-size:.85rem"><?= htmlspecialchars($p['horario'] ?? '—') ?></td>
              <td><?= htmlspecialchars($p['titulo']) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Excluir?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="acao" value="del_prog">
                  <input type="hidden" name="prog_id" value="<?= $p['id'] ?>">
                  <button class="btn btn-danger btn-sm">✕</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
      <div class="form-wrap" style="max-width:none">
        <form method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="add_prog">
          <div class="form-group"><label>Horário</label><input type="text" name="prog_horario" placeholder="Ex: 19h30"></div>
          <div class="form-group"><label>Título <span style="color:var(--vermelho)">*</span></label><input type="text" name="prog_titulo" required></div>
          <div class="form-group"><label>Descrição <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label><textarea name="prog_descricao" rows="2"></textarea></div>
          <button type="submit" class="btn btn-primary" style="width:100%">Adicionar</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Publicar card -->
  <div style="background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:24px;border-top:3px solid var(--verde)">
    <p style="font-weight:700;font-size:.95rem;margin-bottom:8px">Publicar evento</p>
    <p style="font-size:.83rem;color:var(--cinza3);margin-bottom:16px;line-height:1.6">
      Ao publicar, o evento aparecerá no carrossel do site e a página pública ficará disponível.
    </p>
    <a href="/evento.php?id=<?= $evento_id ?>" target="_blank" class="btn btn-ghost btn-sm" style="display:block;text-align:center;margin-bottom:12px">↗ Pré-visualizar página</a>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="publicar">
      <div class="form-group">
        <label>Ordem no carrossel <span style="font-weight:400;color:var(--cinza3)">(menor = primeiro)</span></label>
        <input type="number" name="ordem" min="0" value="<?= $evento['ordem'] ?? 0 ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">Publicar evento ✓</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
