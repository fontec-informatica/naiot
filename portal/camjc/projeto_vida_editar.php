<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("SELECT * FROM camjc_projetos_vida WHERE id = ?");
$st->execute([$id]);
$pv = $st->fetch();
if (!$pv) { header('Location: /portal/camjc/'); exit; }

$st2 = db()->prepare("SELECT * FROM camjc_acolhidas WHERE id = ?");
$st2->execute([$pv['acolhida_id']]);
$a = $st2->fetch();
if (!$a) { header('Location: /portal/camjc/'); exit; }

$titulo       = 'Editar Projeto de Vida — ' . $a['nome'];
$pagina_ativa = 'camjc';
$erro = '';

$campos_auto = [
    'valores_base'         => 'Quais são meus valores de base?',
    'pontos_fortes'        => 'Quais são meus pontos fortes?',
    'pontos_melhorar'      => 'Em que preciso melhorar?',
    'oportunidades'        => 'Quais as oportunidades que vejo em minha vida?',
    'ameacas'              => 'O que ameaça meus planos?',
    'tempo_planejamento'   => 'Quanto tempo dedico para planejar o futuro?',
    'recursos_financeiros' => 'Tenho algum recurso financeiro para colocar meus projetos em prática?',
    'missao'               => 'Missão para minha vida',
];

$campos_fisica = [
    'saude_fisica_atual'       => 'O que anda fazendo para manter o corpo saudável?',
    'saude_fisica_alimentacao' => 'Como está sua alimentação?',
    'saude_fisica_sono'        => 'Como está seu sono?',
    'saude_fisica_meta'        => 'O que precisa melhorar nesse aspecto? Qual sua meta?',
    'saude_fisica_pratica'     => 'O que precisa fazer para colocá-lo em prática?',
];

$campos_espiritual = [
    'saude_espiritual_estabilidade' => 'O que anda fazendo para manter-se estável emocionalmente?',
    'saude_espiritual_interior'     => 'O que está fazendo para entrar em contato com suas questões interiores?',
    'saude_espiritual_capela'       => 'Como tem sido seu comportamento na Capela?',
    'saude_espiritual_oracao'       => 'Tem procurado por conta própria momentos de oração pessoal, ou só vai à capela por ser programa da Casa?',
    'saude_espiritual_metas'        => 'Quais suas metas para a manutenção da vida espiritual?',
];

$campos_intelectual = [
    'saude_intelectual_leituras' => 'Você tem lido? Essas leituras estão te ajudando em quê?',
    'saude_intelectual_curso'    => 'Está fazendo ou pretende fazer algum curso relacionado a alguma área de sua vida?',
    'saude_intelectual_estudo'   => 'O quanto tem estudado ultimamente?',
];

$campos_familiar = [
    'saude_familiar_tratamento'    => 'Como você tem tratado seus familiares?',
    'saude_familiar_impedimento'   => 'O que está impedindo de ter um relacionamento mais amoroso e harmonioso com eles?',
    'saude_familiar_reconciliacao' => 'Quais pessoas da família pretende se reconciliar? Qual sua meta para se aproximar?',
];

$campos_social = [
    'saude_social_sociedade' => 'O que você tem feito para viver numa sociedade mais justa?',
    'saude_social_ajuda'     => 'O que tem feito para ajudar o próximo?',
    'saude_social_cidadania' => 'Como você exerce sua cidadania?',
];

$campos_financeira = [
    'saude_financeira_recursos'     => 'De que forma pretende obter recursos financeiros para o seu sustento?',
    'saude_financeira_profissao'    => 'Você está na profissão que deseja? Se não, qual profissão tem desejo de realizar?',
    'saude_financeira_planejamento' => 'Tem planejado como vai administrar o seu dinheiro? Como?',
];

$todos_campos = $campos_auto + $campos_fisica + $campos_espiritual + $campos_intelectual + $campos_familiar + $campos_social + $campos_financeira;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $data_projeto = $_POST['data_projeto'] ?: $pv['data_projeto'];
        $metas        = trim($_POST['metas'] ?? '') ?: null;
        $nomes   = array_keys($todos_campos);
        $valores = array_map(fn($k) => trim($_POST[$k] ?? '') ?: null, $nomes);
        $sets = implode(', ', array_map(fn($c) => "$c=?", $nomes));

        try {
            db()->prepare("UPDATE camjc_projetos_vida SET data_projeto=?, $sets, metas=?, atualizado_em=NOW() WHERE id=?")
                ->execute([$data_projeto, ...$valores, $metas, $id]);
            camjc_log('editou_projeto_vida', $pv['acolhida_id'], 'Projeto de Vida #' . $id);
            header("Location: /portal/camjc/ver.php?id={$pv['acolhida_id']}&projeto_editado=1");
            exit;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

$v = fn($campo) => htmlspecialchars($_POST[$campo] ?? $pv[$campo] ?? '');

include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Editar Projeto de Vida — <?= htmlspecialchars($a['nome']) ?></h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <div class="form-tabs">
    <button type="button" class="ativo" data-tab="auto">Autoconhecimento</button>
    <button type="button" data-tab="fisica">Saúde física</button>
    <button type="button" data-tab="espiritual">Saúde espiritual</button>
    <button type="button" data-tab="intelectual">Saúde intelectual</button>
    <button type="button" data-tab="familiar">Saúde familiar</button>
    <button type="button" data-tab="social">Saúde social</button>
    <button type="button" data-tab="financeira">Saúde financeira</button>
    <button type="button" data-tab="metas">Metas</button>
  </div>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="tab-pane ativo" data-tab-pane="auto">
      <div class="form-group">
        <label for="data_projeto">Data</label>
        <input type="date" id="data_projeto" name="data_projeto" value="<?= $v('data_projeto') ?: htmlspecialchars($pv['data_projeto']) ?>">
      </div>
      <?php foreach ($campos_auto as $campo => $label): ?>
      <div class="form-group">
        <label for="<?= $campo ?>"><?= htmlspecialchars($label) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="3"><?= $v($campo) ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>

    <?php foreach ([
      'fisica' => $campos_fisica, 'espiritual' => $campos_espiritual, 'intelectual' => $campos_intelectual,
      'familiar' => $campos_familiar, 'social' => $campos_social, 'financeira' => $campos_financeira,
    ] as $tab_id => $grupo): ?>
    <div class="tab-pane" data-tab-pane="<?= $tab_id ?>">
      <?php foreach ($grupo as $campo => $label): ?>
      <div class="form-group">
        <label for="<?= $campo ?>"><?= htmlspecialchars($label) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="3"><?= $v($campo) ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div class="tab-pane" data-tab-pane="metas">
      <div class="form-group">
        <label for="metas">Metas — onde quero chegar, como vou chegar lá e em quanto tempo</label>
        <textarea id="metas" name="metas" rows="10"><?= $v('metas') ?></textarea>
      </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px;align-items:center">
      <button type="button" id="btn-voltar" class="btn btn-ghost" style="display:none">← Voltar</button>
      <button type="button" id="btn-proximo" class="btn btn-primary">Próximo →</button>
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/camjc/ver.php?id=<?= $pv['acolhida_id'] ?>" class="btn btn-ghost" style="margin-left:auto">Cancelar</a>
    </div>
  </form>
</div>

<script src="/portal/assets/js/camjc-form.js"></script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
