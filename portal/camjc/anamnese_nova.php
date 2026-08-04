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

$titulo       = 'Nova Anamnese — ' . $a['nome'];
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
        $data_anamnese = $_POST['data_anamnese'] ?: date('Y-m-d');
        $parecer_equipe = trim($_POST['parecer_equipe'] ?? '') ?: null;
        $campos_nomes  = array_keys($todos_campos);
        $valores       = array_map(fn($k) => trim($_POST[$k] ?? '') ?: null, $campos_nomes);

        $sql = "INSERT INTO camjc_anamneses (acolhida_id, data_anamnese, " . implode(', ', $campos_nomes) . ", parecer_equipe, criado_por)
                VALUES (?, ?, " . implode(',', array_fill(0, count($campos_nomes), '?')) . ", ?, ?)";
        try {
            db()->prepare($sql)->execute([
                $acolhida_id, $data_anamnese, ...$valores, $parecer_equipe, $_SESSION['usuario_id'] ?? null,
            ]);
            $novo_id = (int)db()->lastInsertId();
            camjc_log('criou_anamnese', $acolhida_id, 'Anamnese #' . $novo_id);
            header("Location: /portal/camjc/ver.php?id={$acolhida_id}&anamnese_ok=1");
            exit;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Nova Anamnese — <?= htmlspecialchars($a['nome']) ?></h2>

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
        <input type="date" id="data_anamnese" name="data_anamnese" value="<?= htmlspecialchars($_POST['data_anamnese'] ?? date('Y-m-d')) ?>">
      </div>
      <?php foreach ($campos as $campo => $label): ?>
      <div class="form-group">
        <label for="<?= $campo ?>"><?= htmlspecialchars($label) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="3"><?= htmlspecialchars($_POST[$campo] ?? '') ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="tab-pane" data-tab-pane="relacoes">
      <?php foreach ($campos2 as $campo => $label): ?>
      <div class="form-group">
        <label for="<?= $campo ?>"><?= htmlspecialchars($label) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="4"><?= htmlspecialchars($_POST[$campo] ?? '') ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="tab-pane" data-tab-pane="uso">
      <?php foreach ($campos3 as $campo => $label): ?>
      <div class="form-group">
        <label for="<?= $campo ?>"><?= htmlspecialchars($label) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="3"><?= htmlspecialchars($_POST[$campo] ?? '') ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="tab-pane" data-tab-pane="parecer">
      <div class="form-group">
        <label for="parecer_equipe">Parecer da equipe</label>
        <textarea id="parecer_equipe" name="parecer_equipe" rows="6"><?= htmlspecialchars($_POST['parecer_equipe'] ?? '') ?></textarea>
      </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px;align-items:center">
      <button type="button" id="btn-voltar" class="btn btn-ghost" style="display:none">← Voltar</button>
      <button type="button" id="btn-proximo" class="btn btn-primary">Próximo →</button>
      <button type="submit" class="btn btn-primary">Salvar anamnese</button>
      <a href="/portal/camjc/ver.php?id=<?= $acolhida_id ?>" class="btn btn-ghost" style="margin-left:auto">Cancelar</a>
    </div>
  </form>
</div>

<script src="/portal/assets/js/camjc-form.js"></script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
