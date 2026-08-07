<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$acolhida_id = (int)($_GET['acolhida_id'] ?? 0);
if (!$acolhida_id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("SELECT * FROM camjc_acolhidas WHERE id = ?");
$st->execute([$acolhida_id]);
$a = $st->fetch();
if (!$a) { header('Location: /portal/camjc/'); exit; }

$titulo       = 'Nova Evolução / PAS — ' . $a['nome'];
$pagina_ativa = 'camjc';
$erro = '';

$dias_na_ct = '';
if ($a['data_acolhimento']) {
    $dias_na_ct = (new DateTime($a['data_acolhimento']))->diff(new DateTime())->days;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $data_avaliacao = $_POST['data_avaliacao'] ?: date('Y-m-d');

        $atividades = [];
        foreach (CAMJC_ATIVIDADES_PAS as $chave => $label) {
            $v = $_POST['ativ_' . $chave] ?? '';
            if (in_array($v, ['participa', 'dispensado'], true)) $atividades[$chave] = $v;
        }
        $encaminhamentos = array_values(array_intersect($_POST['encam'] ?? [], array_keys(CAMJC_ENCAMINHAMENTOS_PAS)));

        $campos_aval = [];
        foreach (array_keys(CAMJC_AVALIACAO_ACOLHIDA) as $c) {
            $v = $_POST[$c] ?? '';
            $campos_aval[$c] = isset(CAMJC_ESCALA_SATISFACAO[$v]) ? $v : null;
        }

        $campos_percepcao = [];
        foreach (array_keys(CAMJC_PERCEPCAO_AREAS) as $area) {
            $v = $_POST['percepcao_' . $area] ?? '';
            $campos_percepcao['percepcao_' . $area] = isset(CAMJC_ESCALA_SATISFACAO[$v]) ? $v : null;
            $campos_percepcao['percepcao_' . $area . '_melhorar'] = trim($_POST['percepcao_' . $area . '_melhorar'] ?? '') ?: null;
        }

        $vinculo_situacao = $_POST['vinculo_situacao'] ?? '';
        if (!isset(CAMJC_VINCULO_SITUACOES[$vinculo_situacao])) $vinculo_situacao = null;
        $vinculo_qualidade = $_POST['vinculo_qualidade'] ?? '';
        if (!isset(CAMJC_ESCALA_SATISFACAO[$vinculo_qualidade])) $vinculo_qualidade = null;

        $importancia = ($_POST['importancia_mudanca'] ?? '') !== '' ? max(0, min(10, (int)$_POST['importancia_mudanca'])) : null;
        $confianca   = ($_POST['confianca_abstinencia'] ?? '') !== '' ? max(0, min(10, (int)$_POST['confianca_abstinencia'])) : null;

        $campos = [
            'houve_alteracao_medicacao' => in_array($_POST['houve_alteracao_medicacao'] ?? '', ['sim', 'nao'], true) ? $_POST['houve_alteracao_medicacao'] : null,
            'medicamentos'              => trim($_POST['medicamentos'] ?? '') ?: null,
            'vinculo_situacao'          => $vinculo_situacao,
            'vinculo_qualidade'         => $vinculo_qualidade,
            'familiares_contato'        => trim($_POST['familiares_contato'] ?? '') ?: null,
            'atividades'                => json_encode($atividades, JSON_UNESCAPED_UNICODE),
            'encaminhamentos'           => json_encode($encaminhamentos, JSON_UNESCAPED_UNICODE),
            'importancia_mudanca'       => $importancia,
            'confianca_abstinencia'     => $confianca,
            'requerimentos_acolhida'    => trim($_POST['requerimentos_acolhida'] ?? '') ?: null,
            'parecer_profissional'      => trim($_POST['parecer_profissional'] ?? '') ?: null,
            'profissional_nome'         => trim($_POST['profissional_nome'] ?? '') ?: null,
        ] + $campos_aval + $campos_percepcao;

        $nomes  = array_keys($campos);
        $valores = array_values($campos);

        $sql = "INSERT INTO camjc_pas (acolhida_id, data_avaliacao, " . implode(', ', $nomes) . ", criado_por)
                VALUES (?, ?, " . implode(',', array_fill(0, count($nomes), '?')) . ", ?)";
        try {
            db()->prepare($sql)->execute([$acolhida_id, $data_avaliacao, ...$valores, $_SESSION['usuario_id'] ?? null]);
            $novo_id = (int)db()->lastInsertId();
            camjc_log('criou_pas', $acolhida_id, 'Evolução/PAS #' . $novo_id);
            header("Location: /portal/camjc/ver.php?id={$acolhida_id}&pas_ok=1");
            exit;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

include dirname(__DIR__) . '/_layout.php';
?>
<style>
.esc-grid{display:grid;grid-template-columns:1fr 220px;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)}
.esc-grid:last-child{border-bottom:none}
.esc-grid label{font-size:.85rem;color:var(--txt)}
.esc-grid select{width:100%}
.ativ-row{display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:.83rem}
.ativ-row:last-child{border-bottom:none}
.ativ-row label{display:flex;align-items:center;gap:5px;font-size:.8rem;color:var(--muted);cursor:pointer}
.encam-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px}
.encam-check{display:flex;align-items:center;gap:8px;padding:9px 12px;background:var(--off);border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;cursor:pointer}
.percepcao-block{border-bottom:1px solid var(--border);padding:14px 0}
.percepcao-block:last-child{border-bottom:none}
@media(max-width:860px){
  .esc-grid{grid-template-columns:1fr;gap:6px}
  .ativ-row{grid-template-columns:1fr;gap:6px}
}
.slider-wrap{display:flex;align-items:center;gap:12px}
.slider-wrap input[type=range]{flex:1}
.slider-val{font-family:'Cinzel',serif;font-weight:700;color:var(--green-dk);min-width:24px;text-align:center}
</style>

<div class="form-wrap">
  <h2>Nova Evolução Mensal / PAS — <?= htmlspecialchars($a['nome']) ?></h2>
  <p style="font-size:.8rem;color:var(--muted);margin-top:-14px;margin-bottom:20px">
    <?= $dias_na_ct !== '' ? $dias_na_ct . ' dias na CT' : 'Sem data de acolhimento registrada' ?>
  </p>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <div class="form-tabs">
    <button type="button" class="ativo" data-tab="geral">Geral / Medicação</button>
    <button type="button" data-tab="vinculo">Vínculos familiares</button>
    <button type="button" data-tab="avaliacao">Avaliação</button>
    <button type="button" data-tab="atividades">Atividades</button>
    <button type="button" data-tab="percepcao">Percepção</button>
    <button type="button" data-tab="parecer">Confiança / Parecer</button>
  </div>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <!-- ── Geral / Medicação ── -->
    <div class="tab-pane ativo" data-tab-pane="geral">
      <div class="form-group">
        <label for="data_avaliacao">Data da avaliação</label>
        <input type="date" id="data_avaliacao" name="data_avaliacao" value="<?= htmlspecialchars($_POST['data_avaliacao'] ?? date('Y-m-d')) ?>">
      </div>
      <div class="form-group">
        <label>Houve alterações na medicação?</label>
        <div style="display:flex;gap:16px;margin-top:4px">
          <label style="display:flex;align-items:center;gap:6px;font-size:.85rem"><input type="radio" name="houve_alteracao_medicacao" value="sim"> Sim</label>
          <label style="display:flex;align-items:center;gap:6px;font-size:.85rem"><input type="radio" name="houve_alteracao_medicacao" value="nao" checked> Não</label>
        </div>
      </div>
      <div class="form-group">
        <label for="medicamentos">Medicamentos em uso <span style="font-weight:400;color:var(--cinza3)">(um por linha — ex: Fluoxetina 20mg — manhã — observações)</span></label>
        <textarea id="medicamentos" name="medicamentos" rows="5"><?= htmlspecialchars($_POST['medicamentos'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- ── Vínculos familiares ── -->
    <div class="tab-pane" data-tab-pane="vinculo">
      <div class="form-group">
        <label for="vinculo_situacao">Situação do vínculo familiar</label>
        <select id="vinculo_situacao" name="vinculo_situacao">
          <option value="">— Selecione —</option>
          <?php foreach (CAMJC_VINCULO_SITUACOES as $chave => $label): ?>
          <option value="<?= $chave ?>" <?= (($_POST['vinculo_situacao'] ?? '') === $chave) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="vinculo_qualidade">Qualidade dos vínculos familiares</label>
        <select id="vinculo_qualidade" name="vinculo_qualidade">
          <option value="">— Selecione —</option>
          <?php foreach (CAMJC_ESCALA_SATISFACAO as $chave => $label): ?>
          <option value="<?= $chave ?>" <?= (($_POST['vinculo_qualidade'] ?? '') === $chave) ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="familiares_contato">Familiares com quem a acolhida manteve contato</label>
        <textarea id="familiares_contato" name="familiares_contato" rows="3"><?= htmlspecialchars($_POST['familiares_contato'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- ── Avaliação sobre a acolhida ── -->
    <div class="tab-pane" data-tab-pane="avaliacao">
      <?php foreach (CAMJC_AVALIACAO_ACOLHIDA as $chave => $label): ?>
      <div class="esc-grid">
        <label for="<?= $chave ?>"><?= htmlspecialchars($label) ?></label>
        <select id="<?= $chave ?>" name="<?= $chave ?>">
          <option value="">— Selecione —</option>
          <?php foreach (CAMJC_ESCALA_SATISFACAO as $ec => $el): ?>
          <option value="<?= $ec ?>" <?= (($_POST[$chave] ?? '') === $ec) ? 'selected' : '' ?>><?= $el ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Atividades e encaminhamentos ── -->
    <div class="tab-pane" data-tab-pane="atividades">
      <label style="font-family:'Cinzel',serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--green-dk);font-weight:700;display:block;margin-bottom:10px">Participação em atividades</label>
      <?php foreach (CAMJC_ATIVIDADES_PAS as $chave => $label): ?>
      <div class="ativ-row">
        <span><?= htmlspecialchars($label) ?></span>
        <label><input type="radio" name="ativ_<?= $chave ?>" value="participa" <?= (($_POST['ativ_' . $chave] ?? '') === 'participa') ? 'checked' : '' ?>> Participa</label>
        <label><input type="radio" name="ativ_<?= $chave ?>" value="dispensado" <?= (($_POST['ativ_' . $chave] ?? '') === 'dispensado') ? 'checked' : '' ?>> Dispensado</label>
      </div>
      <?php endforeach; ?>

      <label style="font-family:'Cinzel',serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--green-dk);font-weight:700;display:block;margin:22px 0 10px">Encaminhamentos e referenciamentos</label>
      <div class="encam-grid">
        <?php foreach (CAMJC_ENCAMINHAMENTOS_PAS as $chave => $label): ?>
        <label class="encam-check">
          <input type="checkbox" name="encam[]" value="<?= $chave ?>" <?= in_array($chave, $_POST['encam'] ?? [], true) ? 'checked' : '' ?>>
          <?= htmlspecialchars($label) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Percepção sobre as áreas da vida ── -->
    <div class="tab-pane" data-tab-pane="percepcao">
      <p style="font-size:.8rem;color:var(--muted);margin-bottom:14px">Como a acolhida se percebe em cada área — auto-avaliação.</p>
      <?php foreach (CAMJC_PERCEPCAO_AREAS as $area => $label): ?>
      <div class="percepcao-block">
        <div class="form-group">
          <label for="percepcao_<?= $area ?>"><?= htmlspecialchars($label) ?></label>
          <select id="percepcao_<?= $area ?>" name="percepcao_<?= $area ?>">
            <option value="">— Selecione —</option>
            <?php foreach (CAMJC_ESCALA_SATISFACAO as $ec => $el): ?>
            <option value="<?= $ec ?>" <?= (($_POST['percepcao_' . $area] ?? '') === $ec) ? 'selected' : '' ?>><?= $el ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="percepcao_<?= $area ?>_melhorar">O que pode melhorar?</label>
          <input type="text" id="percepcao_<?= $area ?>_melhorar" name="percepcao_<?= $area ?>_melhorar" value="<?= htmlspecialchars($_POST['percepcao_' . $area . '_melhorar'] ?? '') ?>">
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Confiança / Parecer ── -->
    <div class="tab-pane" data-tab-pane="parecer">
      <div class="form-group">
        <label>Quão importante é para ela fazer uma mudança sobre o uso de álcool/drogas agora? <span id="lbl_importancia" style="font-weight:700;color:var(--green-dk)">5</span>/10</label>
        <div class="slider-wrap">
          <input type="range" name="importancia_mudanca" min="0" max="10" value="<?= htmlspecialchars($_POST['importancia_mudanca'] ?? '5') ?>" oninput="document.getElementById('lbl_importancia').textContent=this.value">
        </div>
      </div>
      <div class="form-group">
        <label>Quão confiante ela está de que pode ficar sem beber/usar substâncias? <span id="lbl_confianca" style="font-weight:700;color:var(--green-dk)">5</span>/10</label>
        <div class="slider-wrap">
          <input type="range" name="confianca_abstinencia" min="0" max="10" value="<?= htmlspecialchars($_POST['confianca_abstinencia'] ?? '5') ?>" oninput="document.getElementById('lbl_confianca').textContent=this.value">
        </div>
      </div>
      <div class="form-group">
        <label for="requerimentos_acolhida">Requerimentos e solicitações da acolhida</label>
        <textarea id="requerimentos_acolhida" name="requerimentos_acolhida" rows="3"><?= htmlspecialchars($_POST['requerimentos_acolhida'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label for="parecer_profissional">Parecer do profissional</label>
        <textarea id="parecer_profissional" name="parecer_profissional" rows="5"><?= htmlspecialchars($_POST['parecer_profissional'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label for="profissional_nome">Profissional responsável pelo preenchimento</label>
        <input type="text" id="profissional_nome" name="profissional_nome" value="<?= htmlspecialchars($_POST['profissional_nome'] ?? '') ?>">
      </div>
    </div>

    <div class="form-acoes">
      <button type="button" id="btn-voltar" class="btn btn-ghost" style="display:none">← Voltar</button>
      <button type="button" id="btn-proximo" class="btn btn-primary">Próximo →</button>
      <button type="submit" class="btn btn-primary">Salvar evolução</button>
      <a href="/portal/camjc/ver.php?id=<?= $acolhida_id ?>" class="btn btn-ghost" style="margin-left:auto">Cancelar</a>
    </div>
  </form>
</div>

<script src="/portal/assets/js/camjc-form.js"></script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
