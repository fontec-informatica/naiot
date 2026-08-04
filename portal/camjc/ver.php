<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("SELECT a.*, u.nome AS unidade_nome FROM camjc_acolhidas a JOIN unidades u ON u.id = a.unidade_id WHERE a.id = ?");
$st->execute([$id]);
$a = $st->fetch();
if (!$a) { header('Location: /portal/camjc/'); exit; }

$titulo       = htmlspecialchars($a['nome']);
$pagina_ativa = 'camjc';
$erro = '';

// Upload de anexo (ex: triagem assinada e digitalizada)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido() && ($_POST['acao'] ?? '') === 'anexar') {
    if (empty($_FILES['arquivo']['tmp_name'])) {
        $erro = 'Selecione um arquivo.';
    } else {
        $f = $_FILES['arquivo'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $erro = 'Erro ao receber o arquivo.';
        } elseif ($f['size'] > 10 * 1024 * 1024) {
            $erro = 'Arquivo: máximo 10 MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($f['tmp_name']);
            $permitidos = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($permitidos[$mime])) {
                $erro = 'Formato não permitido. Use PDF, JPG, PNG ou WebP.';
            } else {
                $tipo_doc = trim($_POST['tipo_doc'] ?? '') ?: 'outro';
                $nome_arq = 'camjc_' . uniqid('', true) . '.' . $permitidos[$mime];
                $dir      = __DIR__ . '/uploads/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                if (move_uploaded_file($f['tmp_name'], $dir . $nome_arq)) {
                    db()->prepare("INSERT INTO camjc_anexos (acolhida_id, nome_original, nome_arquivo, tipo_mime, tamanho, tipo_doc, criado_por) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$id, $f['name'], $nome_arq, $mime, $f['size'], $tipo_doc, $_SESSION['usuario_id'] ?? null]);
                    camjc_log('anexou_documento', $id, $tipo_doc);
                    header("Location: /portal/camjc/ver.php?id={$id}&anexo_ok=1");
                    exit;
                } else {
                    $erro = 'Erro ao salvar o arquivo no servidor.';
                }
            }
        }
    }
}

// Excluir anexo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido() && ($_POST['acao'] ?? '') === 'del_anexo') {
    $anx_id = (int)($_POST['anx_id'] ?? 0);
    $anx = db()->prepare("SELECT nome_arquivo FROM camjc_anexos WHERE id=? AND acolhida_id=?");
    $anx->execute([$anx_id, $id]);
    $anx = $anx->fetch();
    if ($anx) {
        @unlink(__DIR__ . '/uploads/' . $anx['nome_arquivo']);
        db()->prepare("DELETE FROM camjc_anexos WHERE id=?")->execute([$anx_id]);
        camjc_log('removeu_documento', $id);
    }
    header("Location: /portal/camjc/ver.php?id={$id}&anexo_del=1");
    exit;
}

camjc_log('visualizou', $id);

$triagens = db()->prepare("SELECT * FROM camjc_triagens WHERE acolhida_id = ? ORDER BY data_triagem DESC, id DESC");
$triagens->execute([$id]);
$triagens = $triagens->fetchAll();

$anamneses = db()->prepare("SELECT * FROM camjc_anamneses WHERE acolhida_id = ? ORDER BY data_anamnese DESC, id DESC");
$anamneses->execute([$id]);
$anamneses = $anamneses->fetchAll();

$anexos = db()->prepare("SELECT * FROM camjc_anexos WHERE acolhida_id = ? ORDER BY criado_em DESC");
$anexos->execute([$id]);
$anexos = $anexos->fetchAll();

$idade = '';
if ($a['data_nasc']) {
    $nasc = new DateTime($a['data_nasc']); $hoje = new DateTime();
    $idade = $nasc->diff($hoje)->y . ' anos';
}

$perguntas = [
    'motivo_encaminhamento'          => 'Motivo do encaminhamento',
    'tipo_droga_padrao_uso'          => 'Tipo(s) de droga e padrão de uso',
    'tentativas_tratamento'          => 'Tentativas de tratamento anteriores',
    'internacao_hospitalar'          => 'Internação hospitalar recente / tratamento ambulatorial',
    'historico_sobriedade'           => 'Histórico de sobriedade',
    'transtorno_mental'              => 'Transtorno mental',
    'comprometimento_biologico'      => 'Comprometimento biológico grave',
    'deficiencia_fisica'             => 'Deficiência física',
    'hematomas_fraturas'             => 'Hematomas ou fraturas atuais',
    'tatuagens_cicatriz_piercing'    => 'Tatuagens, cicatriz ou piercing',
    'restricao_atividade_fisica'     => 'Restrição para atividade física',
    'tratamento_medico_outra_doenca' => 'Tratamento médico para outra doença',
    'comprometimento_psicologico'    => 'Comprometimento psicológico grave',
    'tentativa_suicidio'             => 'Tentativa de suicídio',
    'beneficio_loas_aposentadoria'   => 'Benefício LOAS / aposentadoria por invalidez',
    'antecedentes_criminais'         => 'Antecedentes criminais / processo judicial',
    'vinculos_familiares'            => 'Vínculos familiares',
    'mora_com_quem'                  => 'Mora com quem',
    'filhos'                         => 'Filhos',
    'rede_apoio'                     => 'Rede de apoio (visitas / participação no programa)',
    'moradia_pos_tratamento'         => 'Moradia após o tratamento',
];

include dirname(__DIR__) . '/_layout.php';
?>
<style>
.cj-ver-grid{display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start}
@media(max-width:860px){.cj-ver-grid{grid-template-columns:1fr}}
.cj-card{background:#fff;border:1px solid var(--border);border-radius:var(--rl);overflow:hidden;box-shadow:var(--sh-sm)}
.cj-card-head{padding:14px 20px;background:var(--off);border-bottom:1px solid var(--border)}
.cj-card-head h3{font-family:'Cinzel',serif;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted)}
.cj-campo{display:flex;flex-direction:column;gap:2px;padding:11px 20px;border-bottom:1px solid var(--border)}
.cj-campo:last-child{border-bottom:none}
.cj-campo-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}
.cj-campo-val{font-size:.88rem;color:var(--txt);white-space:pre-line}
.cj-campo-vazio{color:var(--muted);font-style:italic;font-size:.82rem}
.cj-triagem-block{margin-bottom:18px}
.anx-item{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--off);border-radius:8px;border:1px solid var(--border);margin-bottom:8px}
.anx-info{flex:1;min-width:0}
.anx-nome{font-size:.82rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.anx-meta{font-size:.7rem;color:var(--muted)}
</style>

<div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
  <a href="/portal/camjc/" style="font-size:.78rem;color:var(--muted)">← Casa das Mulheres</a>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="/portal/camjc/editar.php?id=<?= $id ?>" class="btn btn-ghost btn-sm">Editar</a>
    <a href="/portal/camjc/anamnese_nova.php?acolhida_id=<?= $id ?>" class="btn btn-ghost btn-sm">+ Nova anamnese</a>
    <?php if ($triagens): ?>
    <a href="/portal/camjc/imprimir.php?id=<?= $triagens[0]['id'] ?>" target="_blank" class="btn btn-primary btn-sm">🖨 Imprimir triagem</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($_GET['criado'])): ?><div class="alerta alerta-ok" style="margin-bottom:16px">Cadastro e triagem salvos com sucesso.</div><?php endif; ?>
<?php if (!empty($_GET['editado'])): ?><div class="alerta alerta-ok" style="margin-bottom:16px">Alterações salvas com sucesso.</div><?php endif; ?>
<?php if (!empty($_GET['anexo_ok'])): ?><div class="alerta alerta-ok" style="margin-bottom:16px">Documento anexado com sucesso.</div><?php endif; ?>
<?php if (!empty($_GET['anexo_del'])): ?><div class="alerta alerta-ok" style="margin-bottom:16px">Documento removido.</div><?php endif; ?>
<?php if (!empty($_GET['anamnese_ok'])): ?><div class="alerta alerta-ok" style="margin-bottom:16px">Anamnese salva com sucesso.</div><?php endif; ?>
<?php if (!empty($_GET['anamnese_editada'])): ?><div class="alerta alerta-ok" style="margin-bottom:16px">Anamnese atualizada com sucesso.</div><?php endif; ?>
<?php if ($erro): ?><div class="alerta alerta-erro" style="margin-bottom:16px"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div class="cj-ver-grid">

  <!-- ── Coluna lateral ── -->
  <div style="display:flex;flex-direction:column;gap:18px">
    <div class="cj-card">
      <div class="cj-card-head" style="display:flex;align-items:center;justify-content:space-between">
        <h3>Dados pessoais</h3>
        <span class="cj-badge" style="color:<?= camjc_status_cor($a['status']) ?>;background:<?= camjc_status_cor($a['status']) ?>18;font-size:.62rem;padding:3px 9px;border-radius:20px;font-weight:700;text-transform:uppercase">
          <?= htmlspecialchars(camjc_status_label($a['status'])) ?>
        </span>
      </div>
      <?php if (!empty($a['foto'])): ?>
      <div style="padding:16px 20px 0">
        <img src="/portal/camjc/foto.php?id=<?= $id ?>" alt="" style="width:100%;max-height:220px;object-fit:cover;border-radius:8px;display:block">
      </div>
      <?php endif; ?>
      <div class="cj-campo"><div class="cj-campo-label">Nome</div><div class="cj-campo-val"><?= htmlspecialchars($a['nome']) ?></div></div>
      <?php if ($idade): ?><div class="cj-campo"><div class="cj-campo-label">Idade</div><div class="cj-campo-val"><?= $idade ?> <span style="color:var(--muted);font-size:.78rem">(<?= date('d/m/Y', strtotime($a['data_nasc'])) ?>)</span></div></div><?php endif; ?>
      <?php if ($a['estado_civil']): ?><div class="cj-campo"><div class="cj-campo-label">Estado civil</div><div class="cj-campo-val"><?= htmlspecialchars($a['estado_civil']) ?></div></div><?php endif; ?>
      <?php if ($a['rg'] || $a['cpf']): ?><div class="cj-campo"><div class="cj-campo-label">RG / CPF</div><div class="cj-campo-val"><?= htmlspecialchars($a['rg'] ?? '—') ?> / <?= htmlspecialchars($a['cpf'] ?? '—') ?></div></div><?php endif; ?>
      <?php $end = implode(', ', array_filter([$a['endereco'], $a['bairro'], $a['cidade'], $a['estado']])); ?>
      <?php if ($end): ?><div class="cj-campo"><div class="cj-campo-label">Endereço</div><div class="cj-campo-val"><?= htmlspecialchars($end) ?><?= $a['cep'] ? ' — CEP ' . htmlspecialchars($a['cep']) : '' ?></div></div><?php endif; ?>
      <?php if ($a['telefone'] || $a['celular']): ?><div class="cj-campo"><div class="cj-campo-label">Contato</div><div class="cj-campo-val"><?= htmlspecialchars(implode(' / ', array_filter([$a['telefone'], $a['celular']]))) ?></div></div><?php endif; ?>
      <?php if ($a['data_acolhimento']): ?><div class="cj-campo"><div class="cj-campo-label">Data de acolhimento</div><div class="cj-campo-val"><?= date('d/m/Y', strtotime($a['data_acolhimento'])) ?></div></div><?php endif; ?>
      <?php if ($a['status'] === 'acolhida' && $a['data_acolhimento']): ?>
      <div class="cj-campo"><div class="cj-campo-label">Previsão de conclusão (9 meses)</div><div class="cj-campo-val"><?= date('d/m/Y', strtotime(camjc_previsao_saida($a['data_acolhimento']))) ?></div></div>
      <?php endif; ?>
      <?php if ($a['data_saida']): ?>
      <div class="cj-campo"><div class="cj-campo-label">Data de saída</div><div class="cj-campo-val"><?= date('d/m/Y', strtotime($a['data_saida'])) ?></div></div>
      <?php endif; ?>
      <?php if ($a['motivo_saida']): ?>
      <div class="cj-campo"><div class="cj-campo-label">Motivo da saída</div><div class="cj-campo-val"><?= nl2br(htmlspecialchars($a['motivo_saida'])) ?></div></div>
      <?php endif; ?>
      <div class="cj-campo"><div class="cj-campo-label">Unidade</div><div class="cj-campo-val"><?= htmlspecialchars($a['unidade_nome']) ?></div></div>
    </div>

    <?php if ($a['responsavel_nome']): ?>
    <div class="cj-card">
      <div class="cj-card-head"><h3>Responsável</h3></div>
      <div class="cj-campo"><div class="cj-campo-label">Nome</div><div class="cj-campo-val"><?= htmlspecialchars($a['responsavel_nome']) ?></div></div>
      <?php if ($a['responsavel_telefone']): ?><div class="cj-campo"><div class="cj-campo-label">Telefone</div><div class="cj-campo-val"><?= htmlspecialchars($a['responsavel_telefone']) ?></div></div><?php endif; ?>
      <?php if ($a['responsavel_rg'] || $a['responsavel_cpf']): ?><div class="cj-campo"><div class="cj-campo-label">RG / CPF</div><div class="cj-campo-val"><?= htmlspecialchars($a['responsavel_rg'] ?? '—') ?> / <?= htmlspecialchars($a['responsavel_cpf'] ?? '—') ?></div></div><?php endif; ?>
      <?php if ($a['responsavel_endereco']): ?><div class="cj-campo"><div class="cj-campo-label">Endereço</div><div class="cj-campo-val"><?= htmlspecialchars($a['responsavel_endereco']) ?></div></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Documentos -->
    <div class="cj-card">
      <div class="cj-card-head"><h3>Documentos anexados</h3></div>
      <div style="padding:16px 20px">
        <?php if (empty($anexos)): ?>
          <p class="cj-campo-vazio">Nenhum documento anexado ainda.</p>
        <?php else: foreach ($anexos as $anx): ?>
        <div class="anx-item">
          <div class="anx-info">
            <div class="anx-nome"><?= htmlspecialchars($anx['nome_original']) ?></div>
            <div class="anx-meta"><?= htmlspecialchars($anx['tipo_doc'] ?? 'outro') ?> · <?= round($anx['tamanho']/1024) ?>KB · <?= date('d/m/Y', strtotime($anx['criado_em'])) ?></div>
          </div>
          <a href="/portal/camjc/arquivo.php?id=<?= $anx['id'] ?>" target="_blank" class="btn btn-ghost btn-sm">Ver</a>
          <form method="post" onsubmit="return confirm('Excluir este documento?')" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao" value="del_anexo">
            <input type="hidden" name="anx_id" value="<?= $anx['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">✕</button>
          </form>
        </div>
        <?php endforeach; endif; ?>

        <form method="post" enctype="multipart/form-data" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="acao" value="anexar">
          <div class="form-group">
            <label for="tipo_doc" style="font-size:.78rem">Tipo de documento</label>
            <select name="tipo_doc" id="tipo_doc" style="width:100%">
              <option value="triagem_assinada">Triagem assinada (digitalizada)</option>
              <option value="documento_identidade">Documento de identidade</option>
              <option value="laudo_medico">Laudo médico</option>
              <option value="termo">Termo de consentimento</option>
              <option value="outro">Outro</option>
            </select>
          </div>
          <div class="form-group">
            <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
            <span class="form-hint">PDF, JPG, PNG ou WebP — máx. 10MB.</span>
          </div>
          <button type="submit" class="btn btn-ghost btn-sm">📎 Anexar documento</button>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Coluna principal: anamneses + triagens ── -->
  <div>
    <?php if (!empty($anamneses)): foreach ($anamneses as $an): ?>
    <div class="cj-card cj-triagem-block">
      <div class="cj-card-head" style="display:flex;justify-content:space-between;align-items:center">
        <h3>Anamnese — <?= date('d/m/Y', strtotime($an['data_anamnese'])) ?></h3>
        <div style="display:flex;gap:12px">
          <a href="/portal/camjc/anamnese_editar.php?id=<?= $an['id'] ?>" style="font-size:.75rem;color:var(--muted)">Editar</a>
          <a href="/portal/camjc/anamnese_imprimir.php?id=<?= $an['id'] ?>" target="_blank" style="font-size:.75rem;color:var(--green)">🖨 Imprimir</a>
        </div>
      </div>
      <?php
        $anamnese_perguntas = [
          'nascimento_complicacoes'        => 'Nascimento (complicações)',
          'familia_obitos'                 => 'Óbitos na família',
          'familia_uso_substancias'        => 'Uso/abuso de substâncias na família',
          'familia_atitudes'               => 'Atitudes dos familiares',
          'familia_ambiente'               => 'Ambiente familiar',
          'infancia'                       => 'Infância',
          'marital_sexual'                 => 'História marital/sexual',
          'filhos'                         => 'Filhos',
          'historia_forense'               => 'História forense',
          'droga_primeira_vez'             => 'Primeira droga / como conseguiu',
          'droga_evolucao'                 => 'Evolução de cada droga',
          'droga_percepcao_problema'       => 'Percepção do problema',
          'droga_padrao_atual'             => 'Padrão atual de uso',
          'droga_abstinencia_primeira_vez' => 'Primeira abstinência',
          'droga_periodos_sobriedade'      => 'Períodos de sobriedade',
          'droga_ultimo_uso'               => 'Último uso',
          'parecer_equipe'                 => 'Parecer da equipe',
        ];
      ?>
      <?php foreach ($anamnese_perguntas as $campo => $label): ?>
      <div class="cj-campo">
        <div class="cj-campo-label"><?= htmlspecialchars($label) ?></div>
        <?php if (!empty($an[$campo])): ?>
          <div class="cj-campo-val"><?= nl2br(htmlspecialchars($an[$campo])) ?></div>
        <?php else: ?>
          <div class="cj-campo-vazio">Não informado</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; endif; ?>

    <?php if (empty($triagens)): ?>
      <div class="cj-card"><div style="padding:24px;text-align:center;color:var(--muted)">Nenhuma triagem registrada.</div></div>
    <?php else: foreach ($triagens as $t): ?>
    <div class="cj-card cj-triagem-block">
      <div class="cj-card-head" style="display:flex;justify-content:space-between;align-items:center">
        <h3>Triagem — <?= date('d/m/Y', strtotime($t['data_triagem'])) ?></h3>
        <a href="/portal/camjc/imprimir.php?id=<?= $t['id'] ?>" target="_blank" style="font-size:.75rem;color:var(--green)">🖨 Imprimir</a>
      </div>
      <?php foreach ($perguntas as $campo => $label): ?>
      <div class="cj-campo">
        <div class="cj-campo-label"><?= htmlspecialchars($label) ?></div>
        <?php if (!empty($t[$campo])): ?>
          <div class="cj-campo-val"><?= nl2br(htmlspecialchars($t[$campo])) ?></div>
        <?php else: ?>
          <div class="cj-campo-vazio">Não informado</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if (!empty($t['observacoes'])): ?>
      <div class="cj-campo">
        <div class="cj-campo-label">Observações</div>
        <div class="cj-campo-val"><?= nl2br(htmlspecialchars($t['observacoes'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if (!empty($t['responsavel_triagem_nome'])): ?>
      <div class="cj-campo">
        <div class="cj-campo-label">Responsável pela triagem</div>
        <div class="cj-campo-val"><?= htmlspecialchars($t['responsavel_triagem_nome']) ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
  </div>

</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
