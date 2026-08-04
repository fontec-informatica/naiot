<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_ressoc_perguntas.php';

$acolhida_id = (int)($_GET['acolhida_id'] ?? 0);
if (!$acolhida_id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("SELECT * FROM camjc_acolhidas WHERE id = ?");
$st->execute([$acolhida_id]);
$a = $st->fetch();
if (!$a) { header('Location: /portal/camjc/'); exit; }

$titulo       = 'Nova Avaliação de Ressocialização — ' . $a['nome'];
$pagina_ativa = 'camjc';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $respostas = [];
        foreach (CAMJC_RESSOC_GRUPOS as $grupo) {
            foreach ($grupo['secoes'] as $secao) {
                foreach ($secao['perguntas'] as $chave => $p) {
                    $v = trim($_POST['q_' . $chave] ?? '');
                    if ($v === '') continue;
                    if (isset($p['opcoes']) && !isset($p['opcoes'][$v])) continue;
                    $respostas[$chave] = $v;
                }
            }
        }

        try {
            db()->prepare("
                INSERT INTO camjc_ressocializacao
                    (acolhida_id, nome_familiar, grau_parentesco, numero_visita, data_resposta, respostas, observacoes_finais, criado_por)
                VALUES (?,?,?,?,?,?,?,?)
            ")->execute([
                $acolhida_id,
                trim($_POST['nome_familiar'] ?? '') ?: null,
                trim($_POST['grau_parentesco'] ?? '') ?: null,
                trim($_POST['numero_visita'] ?? '') ?: null,
                $_POST['data_resposta'] ?: date('Y-m-d'),
                json_encode($respostas, JSON_UNESCAPED_UNICODE),
                trim($_POST['q_23_1'] ?? '') ?: null,
                $_SESSION['usuario_id'] ?? null,
            ]);
            $novo_id = (int)db()->lastInsertId();
            camjc_log('criou_ressocializacao', $acolhida_id, 'Avaliação #' . $novo_id);
            header("Location: /portal/camjc/ver.php?id={$acolhida_id}&ressoc_ok=1");
            exit;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

include dirname(__DIR__) . '/_layout.php';
?>
<style>
.q-block{border-bottom:1px solid var(--border);padding:12px 0}
.q-block:last-child{border-bottom:none}
.q-block label{font-size:.83rem;color:var(--txt);display:block;margin-bottom:6px}
.sec-titulo-form{font-family:'Cinzel',serif;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--green-dk);margin:22px 0 6px;padding-bottom:6px;border-bottom:1.5px solid var(--border)}
.sec-titulo-form:first-child{margin-top:0}
</style>

<div class="form-wrap">
  <h2>Nova Avaliação de Ressocialização — <?= htmlspecialchars($a['nome']) ?></h2>
  <p style="font-size:.8rem;color:var(--muted);margin-top:-14px;margin-bottom:20px">Respondida pela família após visita/saída de ressocialização.</p>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <div class="form-tabs">
    <button type="button" class="ativo" data-tab="dados">Identificação</button>
    <?php foreach (CAMJC_RESSOC_GRUPOS as $chave => $grupo): ?>
    <button type="button" data-tab="<?= $chave ?>"><?= htmlspecialchars($grupo['label']) ?></button>
    <?php endforeach; ?>
  </div>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="tab-pane ativo" data-tab-pane="dados">
      <div class="form-group">
        <label for="nome_familiar">Nome do familiar</label>
        <input type="text" id="nome_familiar" name="nome_familiar" value="<?= htmlspecialchars($_POST['nome_familiar'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="grau_parentesco">Grau de parentesco</label>
          <input type="text" id="grau_parentesco" name="grau_parentesco" value="<?= htmlspecialchars($_POST['grau_parentesco'] ?? '') ?>" placeholder="Ex: mãe, irmã, esposo...">
        </div>
        <div class="form-group">
          <label for="numero_visita">Número da visita</label>
          <select id="numero_visita" name="numero_visita">
            <option value="">— Selecione —</option>
            <?php foreach (['1ª','2ª','3ª','4ª','5ª ou mais'] as $nv): ?>
            <option value="<?= $nv ?>" <?= (($_POST['numero_visita'] ?? '') === $nv) ? 'selected' : '' ?>><?= $nv ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label for="data_resposta">Data da resposta do questionário</label>
        <input type="date" id="data_resposta" name="data_resposta" value="<?= htmlspecialchars($_POST['data_resposta'] ?? date('Y-m-d')) ?>">
      </div>
      <p class="form-hint">Lembramos que a total sinceridade é essencial para trabalhar com a acolhida os fatores de risco à sua sobriedade.</p>
    </div>

    <?php foreach (CAMJC_RESSOC_GRUPOS as $chave_grupo => $grupo): ?>
    <div class="tab-pane" data-tab-pane="<?= $chave_grupo ?>">
      <?php foreach ($grupo['secoes'] as $secao): ?>
        <div class="sec-titulo-form"><?= htmlspecialchars($secao['titulo']) ?></div>
        <?php foreach ($secao['perguntas'] as $chave_p => $p): ?>
        <div class="q-block">
          <label for="q_<?= $chave_p ?>"><?= htmlspecialchars($p['texto']) ?></label>
          <?php if (isset($p['tipo']) && $p['tipo'] === 'texto'): ?>
            <textarea id="q_<?= $chave_p ?>" name="q_<?= $chave_p ?>" rows="2"><?= htmlspecialchars($_POST['q_' . $chave_p] ?? '') ?></textarea>
          <?php else: ?>
            <select id="q_<?= $chave_p ?>" name="q_<?= $chave_p ?>">
              <option value="">— Não respondida —</option>
              <?php foreach ($p['opcoes'] as $opv => $opl): ?>
              <option value="<?= $opv ?>" <?= (($_POST['q_' . $chave_p] ?? '') === $opv) ? 'selected' : '' ?>><?= htmlspecialchars($opl) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div style="display:flex;gap:12px;margin-top:8px;align-items:center">
      <button type="button" id="btn-voltar" class="btn btn-ghost" style="display:none">← Voltar</button>
      <button type="button" id="btn-proximo" class="btn btn-primary">Próximo →</button>
      <button type="submit" class="btn btn-primary">Salvar avaliação</button>
      <a href="/portal/camjc/ver.php?id=<?= $acolhida_id ?>" class="btn btn-ghost" style="margin-left:auto">Cancelar</a>
    </div>
  </form>
</div>

<script src="/portal/assets/js/camjc-form.js"></script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
