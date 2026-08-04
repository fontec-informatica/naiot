<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$titulo       = 'Casa das Mulheres — Nova Candidata';
$pagina_ativa = 'camjc';
$erro = '';

$unidade = camjc_unidade_padrao();
if (empty($unidade)) {
    $erro = 'Nenhuma unidade cadastrada. Rode o setup do módulo primeiro: /portal/camjc/setup.php';
}

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

$perguntas = [
    'motivo_encaminhamento'          => 'Motivo do encaminhamento',
    'tipo_droga_padrao_uso'          => 'Tipo(s) de droga e padrão de uso que justificam o encaminhamento para comunidade terapêutica',
    'tentativas_tratamento'          => 'Tentativas de tratamento anteriores (bem ou mal sucedidas)? Já passou por Comunidade Terapêutica? Se sim, ano, período e nome do estabelecimento',
    'internacao_hospitalar'          => 'Possui período de internação hospitalar recente? Realiza tratamento ambulatorial?',
    'historico_sobriedade'           => 'Depois do início do uso contínuo, já ficou sóbria? Como conseguiu manter? Por quanto tempo? Por que recaiu?',
    'transtorno_mental'              => 'É pessoa com transtorno mental (ex: esquizofrenia, bipolaridade)? Há quanto tempo foi diagnosticado? Já tratou em Comunidade Terapêutica nos últimos 3 anos?',
    'comprometimento_biologico'      => 'Possui grave comprometimento biológico? Quais? Exige atenção médico-hospitalar contínua ou de emergência?',
    'deficiencia_fisica'             => 'Portadora de alguma deficiência física',
    'hematomas_fraturas'             => 'Possui hematomas ou fraturas neste momento?',
    'tatuagens_cicatriz_piercing'    => 'Possui tatuagens, cicatriz ou piercing?',
    'restricao_atividade_fisica'     => 'Possui alguma restrição que a impeça de fazer exercício físico ou outra atividade?',
    'tratamento_medico_outra_doenca' => 'Faz tratamento médico para alguma outra doença?',
    'comprometimento_psicologico'    => 'Possui grave comprometimento psicológico? Quais? Exige atenção médico-hospitalar contínua ou de emergência?',
    'tentativa_suicidio'             => 'Já tentou suicídio? Explique',
    'beneficio_loas_aposentadoria'   => 'É beneficiária do LOAS, recebe aposentadoria por invalidez, ou qualquer outro benefício?',
    'antecedentes_criminais'         => 'Já foi presa? Responde algum processo judicial?',
    'vinculos_familiares'            => 'Os vínculos familiares estão presentes? Quais pessoas têm mais vínculo e confiança da paciente?',
    'mora_com_quem'                  => 'Mora com quem?',
    'filhos'                         => 'Tem filhos? Listar nome, idade e com quem moram',
    'rede_apoio'                     => 'Quem irá participar do programa terapêutico junto com a acolhida? Listar quem poderá ligar/visitar — nome completo, parentesco e contato',
    'moradia_pos_tratamento'         => 'Terá onde morar após completar o tratamento para dependência química?',
];

$d = array_fill_keys(array_keys($perguntas), '');

if (!$erro && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        if (!$nome) {
            $erro = 'O nome da candidata é obrigatório.';
        } else {
            $data_nasc      = $_POST['data_nasc'] ?: null;
            $estado_civil   = trim($_POST['estado_civil'] ?? '') ?: null;
            $rg             = trim($_POST['rg'] ?? '') ?: null;
            $cpf            = preg_replace('/\D/', '', $_POST['cpf'] ?? '') ?: null;
            $endereco       = trim($_POST['endereco'] ?? '') ?: null;
            $bairro         = trim($_POST['bairro'] ?? '') ?: null;
            $cep            = trim($_POST['cep'] ?? '') ?: null;
            $cidade         = trim($_POST['cidade'] ?? '') ?: null;
            $estado         = trim($_POST['estado'] ?? '') ?: null;
            $telefone       = trim($_POST['telefone'] ?? '') ?: null;
            $celular        = trim($_POST['celular'] ?? '') ?: null;

            $resp_nome      = trim($_POST['responsavel_nome'] ?? '') ?: null;
            $resp_endereco  = trim($_POST['responsavel_endereco'] ?? '') ?: null;
            $resp_rg        = trim($_POST['responsavel_rg'] ?? '') ?: null;
            $resp_cpf       = preg_replace('/\D/', '', $_POST['responsavel_cpf'] ?? '') ?: null;
            $resp_data_nasc = $_POST['responsavel_data_nasc'] ?: null;
            $resp_telefone  = trim($_POST['responsavel_telefone'] ?? '') ?: null;

            $data_triagem      = $_POST['data_triagem'] ?: date('Y-m-d');
            $data_acolhimento  = $_POST['data_acolhimento'] ?: null;
            $status            = $data_acolhimento ? 'acolhida' : 'em_triagem';
            $resp_triagem_nome = trim($_POST['responsavel_triagem_nome'] ?? '') ?: null;
            $observacoes       = trim($_POST['observacoes'] ?? '') ?: null;

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO camjc_acolhidas
                        (unidade_id, nome, data_nasc, estado_civil, rg, cpf, endereco, bairro, cep, cidade, estado,
                         telefone, celular, responsavel_nome, responsavel_endereco, responsavel_rg, responsavel_cpf,
                         responsavel_data_nasc, responsavel_telefone, status, data_acolhimento, criado_por)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute([
                    $unidade['id'], $nome, $data_nasc, $estado_civil, $rg, $cpf, $endereco, $bairro, $cep, $cidade, $estado,
                    $telefone, $celular, $resp_nome, $resp_endereco, $resp_rg, $resp_cpf,
                    $resp_data_nasc, $resp_telefone, $status, $data_acolhimento, $_SESSION['usuario_id'] ?? null,
                ]);
                $acolhida_id = (int)$pdo->lastInsertId();

                $campos_perg = array_keys($perguntas);
                $valores_perg = array_map(fn($k) => trim($_POST[$k] ?? '') ?: null, $campos_perg);

                $sql = "INSERT INTO camjc_triagens (acolhida_id, data_triagem, " . implode(', ', $campos_perg) . ", observacoes, responsavel_triagem_nome, criado_por)
                        VALUES (?, ?, " . implode(',', array_fill(0, count($campos_perg), '?')) . ", ?, ?, ?)";
                $pdo->prepare($sql)->execute([
                    $acolhida_id, $data_triagem, ...$valores_perg, $observacoes, $resp_triagem_nome, $_SESSION['usuario_id'] ?? null,
                ]);

                $pdo->commit();
                camjc_log('criou', $acolhida_id, 'Cadastro e triagem inicial');
                header("Location: /portal/camjc/ver.php?id={$acolhida_id}&criado=1");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = 'Erro ao salvar: ' . $e->getMessage();
            }
        }
    }
}

include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap" style="max-width:820px">
  <h2>Nova candidata — Triagem</h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <?php if (!$erro || !empty($unidade)): ?>
  <div class="form-tabs">
    <button type="button" class="ativo" data-tab="pessoais">Dados pessoais</button>
    <button type="button" data-tab="responsavel">Responsável</button>
    <button type="button" data-tab="entrevista">Entrevista de perfil</button>
    <button type="button" data-tab="obs">Observações</button>
  </div>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <!-- ── Dados pessoais ── -->
    <div class="tab-pane ativo" data-tab-pane="pessoais">
      <div class="form-group">
        <label for="nome">Nome completo <span style="color:var(--red)">*</span></label>
        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="data_nasc">Data de nascimento</label>
          <input type="date" id="data_nasc" name="data_nasc" value="<?= htmlspecialchars($_POST['data_nasc'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="estado_civil">Estado civil</label>
          <select id="estado_civil" name="estado_civil">
            <option value="">— Selecione —</option>
            <?php foreach (['Solteira','Casada','Divorciada','Viúva','União estável','Outro'] as $ec): ?>
            <option value="<?= $ec ?>" <?= ($_POST['estado_civil'] ?? '') === $ec ? 'selected' : '' ?>><?= $ec ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="rg">RG</label>
          <input type="text" id="rg" name="rg" value="<?= htmlspecialchars($_POST['rg'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="cpf">CPF</label>
          <input type="text" id="cpf" name="cpf" value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>" maxlength="14">
        </div>
      </div>
      <div class="form-group">
        <label for="endereco">Endereço</label>
        <input type="text" id="endereco" name="endereco" value="<?= htmlspecialchars($_POST['endereco'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="bairro">Bairro</label>
          <input type="text" id="bairro" name="bairro" value="<?= htmlspecialchars($_POST['bairro'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="cep">CEP</label>
          <input type="text" id="cep" name="cep" value="<?= htmlspecialchars($_POST['cep'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="cidade">Cidade atual</label>
          <input type="text" id="cidade" name="cidade" value="<?= htmlspecialchars($_POST['cidade'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="estado">Estado</label>
          <select id="estado" name="estado">
            <option value="">— UF —</option>
            <?php foreach ($ufs as $uf): ?>
            <option value="<?= $uf ?>" <?= ($_POST['estado'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="telefone">Telefone fixo</label>
          <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="celular">Celular</label>
          <input type="text" id="celular" name="celular" value="<?= htmlspecialchars($_POST['celular'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="data_triagem">Data da triagem</label>
          <input type="date" id="data_triagem" name="data_triagem" value="<?= htmlspecialchars($_POST['data_triagem'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-group">
          <label for="data_acolhimento">Data de acolhimento <span style="font-weight:400;color:var(--cinza3)">(deixe em branco se ainda em triagem)</span></label>
          <input type="date" id="data_acolhimento" name="data_acolhimento" value="<?= htmlspecialchars($_POST['data_acolhimento'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- ── Responsável ── -->
    <div class="tab-pane" data-tab-pane="responsavel">
      <div class="form-group">
        <label for="responsavel_nome">Nome do responsável</label>
        <input type="text" id="responsavel_nome" name="responsavel_nome" value="<?= htmlspecialchars($_POST['responsavel_nome'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="responsavel_endereco">Endereço</label>
        <input type="text" id="responsavel_endereco" name="responsavel_endereco" value="<?= htmlspecialchars($_POST['responsavel_endereco'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="responsavel_rg">RG</label>
          <input type="text" id="responsavel_rg" name="responsavel_rg" value="<?= htmlspecialchars($_POST['responsavel_rg'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="responsavel_cpf">CPF</label>
          <input type="text" id="responsavel_cpf" name="responsavel_cpf" value="<?= htmlspecialchars($_POST['responsavel_cpf'] ?? '') ?>" maxlength="14">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="responsavel_data_nasc">Data de nascimento</label>
          <input type="date" id="responsavel_data_nasc" name="responsavel_data_nasc" value="<?= htmlspecialchars($_POST['responsavel_data_nasc'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="responsavel_telefone">Telefone</label>
          <input type="text" id="responsavel_telefone" name="responsavel_telefone" value="<?= htmlspecialchars($_POST['responsavel_telefone'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- ── Entrevista de perfil ── -->
    <div class="tab-pane" data-tab-pane="entrevista">
      <?php foreach ($perguntas as $campo => $label): ?>
      <div class="form-group">
        <label for="<?= $campo ?>"><?= htmlspecialchars($label) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="3"><?= htmlspecialchars($_POST[$campo] ?? '') ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Observações ── -->
    <div class="tab-pane" data-tab-pane="obs">
      <div class="form-group">
        <label for="observacoes">Observações</label>
        <textarea id="observacoes" name="observacoes" rows="6"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label for="responsavel_triagem_nome">Responsável pela triagem</label>
        <input type="text" id="responsavel_triagem_nome" name="responsavel_triagem_nome" value="<?= htmlspecialchars($_POST['responsavel_triagem_nome'] ?? '') ?>">
        <span class="form-hint">As assinaturas físicas (responsável, candidata e responsável pela triagem) são colhidas no documento impresso e depois anexadas de volta ao sistema.</span>
      </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Salvar cadastro e triagem</button>
      <a href="/portal/camjc/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
  <?php endif; ?>
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
