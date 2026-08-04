<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("SELECT * FROM camjc_anamneses WHERE id = ?");
$st->execute([$id]);
$an = $st->fetch();
if (!$an) { header('Location: /portal/camjc/'); exit; }

$st2 = db()->prepare("SELECT * FROM camjc_acolhidas WHERE id = ?");
$st2->execute([$an['acolhida_id']]);
$a = $st2->fetch();
if (!$a) { header('Location: /portal/camjc/'); exit; }

$titulo       = 'Editar Anamnese — ' . $a['nome'];
$pagina_ativa = 'camjc';
$erro = '';

$campos = [
    'nascimento_complicacoes'        => 'História pessoal — nascimento (complicações)',
    'familia_obitos'                 => 'Alguém da família já morreu? Por qual motivo?',
    'familia_uso_substancias'        => 'Alguém da família tem ou teve problemas com uso/abuso de substâncias?',
    'familia_atitudes'               => 'Quais as atitudes dos familiares diante dessas questões?',
    'familia_ambiente'               => 'Como é o ambiente familiar?',
    'infancia'                       => 'Infância: doenças, ambiente familiar, separação dos pais, pobreza, traumas, lembranças, educação (problemas na escola com colegas e disciplinares)',
];

$campos2 = [
    'marital_sexual' => 'História marital/sexual: tem parceiro? É casada? Já se separou (motivo)? O parceiro faz uso de SPA? Como é a qualidade do relacionamento?',
    'filhos'         => 'Filhos: quantos? Idades e estados civis? Algum tem problema com uso de SPA? Qual a atitude deles diante do problema? Como é o relacionamento?',
];

$campos3 = [
    'historia_forense'               => 'História forense: já foi apreendida ou presa? Por qual motivo? Responde algum processo?',
    'droga_primeira_vez'             => 'Primeira droga que usou — como conseguiu?',
    'droga_evolucao'                 => 'Evolução de cada droga e como foi o primeiro contato',
    'droga_percepcao_problema'       => 'Quando começou a perceber que o hábito de beber/usar estava causando problemas?',
    'droga_padrao_atual'             => 'Quando começou a beber ou usar outras drogas no padrão atual?',
    'droga_abstinencia_primeira_vez' => 'Quando foi a primeira vez que teve sintomas de abstinência (tremores, náuseas, ânsias de vômito, sudorese)?',
    'droga_periodos_sobriedade'      => 'Em que período parou de beber ou usar drogas (dias, semanas, meses)? O que motivou a abstinência?',
    'droga_ultimo_uso'               => 'Qual foi a última vez que bebeu ou usou drogas?',
];

$todos_campos = $campos + $campos2 + $campos3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $data_anamnese  = $_POST['data_anamnese'] ?: $an['data_anamnese'];
        $parecer_equipe = trim($_POST['parecer_equipe'] ?? '') ?: null;
        $campos_nomes   = array_keys($todos_campos);
        $valores        = array_map(fn($k) => trim($_POST[$k] ?? '') ?: null, $campos_nomes);
        $sets = implode(', ', array_map(fn($c) => "$c=?", $campos_nomes));

        try {
            db()->prepare("UPDATE camjc_anamneses SET data_anamnese=?, $sets, parecer_equipe=?, atualizado_em=NOW() WHERE id=?")
                ->execute([$data_anamnese, ...$valores, $parecer_equipe, $id]);
            camjc_log('editou_anamnese', $an['acolhida_id'], 'Anamnese #' . $id);
            header("Location: /portal/camjc/ver.php?id={$an['acolhida_id']}&anamnese_editada=1");
            exit;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

$v = fn($campo) => htmlspecialchars($_POST[$campo] ?? $an[$campo] ?? '');

include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Editar Anamnese — <?= htmlspecialchars($a['nome']) ?></h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <div class="form-tabs">
    <button type="button" class="ativo" data-tab="pessoal">Pessoal e família</button>
    <button type="button" data-tab="relacoes">Relacionamentos</button>
    <button type="button" data-tab="uso">Forense e uso de drogas</button>
    <button type="button" data-tab="parecer">Parecer</button>
  </div>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="tab-pane ativo" data-tab-pane="pessoal">
      <div class="form-group">
        <label for="data_anamnese">Data da anamnese</label>
        <input type="date" id="data_anamnese" name="data_anamnese" value="<?= $v('data_anamnese') ?: htmlspecialchars($an['data_anamnese']) ?>">
      </div>
      <?php foreach ($campos as $campo => $label): ?>
      <div class="form-group">
        <label for="<?= $campo ?>"><?= htmlspecialchars($label) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="3"><?= $v($campo) ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="tab-pane" data-tab-pane="relacoes">
      <?php foreach ($campos2 as $campo => $label): ?>
      <div class="form-group">
        <label for="<?= $campo ?>"><?= htmlspecialchars($label) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="4"><?= $v($campo) ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="tab-pane" data-tab-pane="uso">
      <?php foreach ($campos3 as $campo => $label): ?>
      <div class="form-group">
        <label for="<?= $campo ?>"><?= htmlspecialchars($label) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="3"><?= $v($campo) ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="tab-pane" data-tab-pane="parecer">
      <div class="form-group">
        <label for="parecer_equipe">Parecer da equipe</label>
        <textarea id="parecer_equipe" name="parecer_equipe" rows="6"><?= $v('parecer_equipe') ?></textarea>
      </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/camjc/ver.php?id=<?= $an['acolhida_id'] ?>" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<script>
(function () {
  var botoes = document.querySelectorAll('.form-tabs button');
  var panes  = document.querySelectorAll('.tab-pane');
  botoes.forEach(function (btn) {
    btn.addEventListener('click', function () {
      botoes.forEach(function (b) { b.classList.remove('ativo'); });
      panes.forEach(function (p) { p.classList.remove('ativo'); });
      btn.classList.add('ativo');
      document.querySelector('.tab-pane[data-tab-pane="' + btn.dataset.tab + '"]').classList.add('ativo');
    });
  });
})();
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
