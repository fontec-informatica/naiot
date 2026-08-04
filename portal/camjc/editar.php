<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("SELECT * FROM camjc_acolhidas WHERE id = ?");
$st->execute([$id]);
$a = $st->fetch();
if (!$a) { header('Location: /portal/camjc/'); exit; }

$st2 = db()->prepare("SELECT * FROM camjc_triagens WHERE acolhida_id = ? ORDER BY data_triagem DESC, id DESC LIMIT 1");
$st2->execute([$id]);
$t = $st2->fetch() ?: [];

$titulo       = 'Editar — ' . $a['nome'];
$pagina_ativa = 'camjc';
$erro = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        if (!$nome) {
            $erro = 'O nome da candidata é obrigatório.';
        } else {
            $status = $_POST['status'] ?? $a['status'];
            if (!isset(CAMJC_STATUS[$status])) $status = $a['status'];

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    UPDATE camjc_acolhidas SET
                        nome=?, data_nasc=?, estado_civil=?, rg=?, cpf=?, endereco=?, bairro=?, cep=?, cidade=?, estado=?,
                        telefone=?, celular=?, responsavel_nome=?, responsavel_endereco=?, responsavel_rg=?, responsavel_cpf=?,
                        responsavel_data_nasc=?, responsavel_telefone=?, status=?, data_acolhimento=?, atualizado_em=NOW()
                    WHERE id=?
                ")->execute([
                    $nome,
                    $_POST['data_nasc'] ?: null,
                    trim($_POST['estado_civil'] ?? '') ?: null,
                    trim($_POST['rg'] ?? '') ?: null,
                    preg_replace('/\D/', '', $_POST['cpf'] ?? '') ?: null,
                    trim($_POST['endereco'] ?? '') ?: null,
                    trim($_POST['bairro'] ?? '') ?: null,
                    trim($_POST['cep'] ?? '') ?: null,
                    trim($_POST['cidade'] ?? '') ?: null,
                    trim($_POST['estado'] ?? '') ?: null,
                    trim($_POST['telefone'] ?? '') ?: null,
                    trim($_POST['celular'] ?? '') ?: null,
                    trim($_POST['responsavel_nome'] ?? '') ?: null,
                    trim($_POST['responsavel_endereco'] ?? '') ?: null,
                    trim($_POST['responsavel_rg'] ?? '') ?: null,
                    preg_replace('/\D/', '', $_POST['responsavel_cpf'] ?? '') ?: null,
                    $_POST['responsavel_data_nasc'] ?: null,
                    trim($_POST['responsavel_telefone'] ?? '') ?: null,
                    $status,
                    $_POST['data_acolhimento'] ?: null,
                    $id,
                ]);

                $campos_perg  = array_keys($perguntas);
                $valores_perg = array_map(fn($k) => trim($_POST[$k] ?? '') ?: null, $campos_perg);
                $observacoes       = trim($_POST['observacoes'] ?? '') ?: null;
                $resp_triagem_nome = trim($_POST['responsavel_triagem_nome'] ?? '') ?: null;
                $data_triagem      = $_POST['data_triagem'] ?: date('Y-m-d');

                if (!empty($t['id'])) {
                    $sets = implode(', ', array_map(fn($c) => "$c=?", $campos_perg));
                    $pdo->prepare("UPDATE camjc_triagens SET data_triagem=?, $sets, observacoes=?, responsavel_triagem_nome=?, atualizado_em=NOW() WHERE id=?")
                        ->execute([$data_triagem, ...$valores_perg, $observacoes, $resp_triagem_nome, $t['id']]);
                } else {
                    $sql = "INSERT INTO camjc_triagens (acolhida_id, data_triagem, " . implode(', ', $campos_perg) . ", observacoes, responsavel_triagem_nome, criado_por)
                            VALUES (?, ?, " . implode(',', array_fill(0, count($campos_perg), '?')) . ", ?, ?, ?)";
                    $pdo->prepare($sql)->execute([
                        $id, $data_triagem, ...$valores_perg, $observacoes, $resp_triagem_nome, $_SESSION['usuario_id'] ?? null,
                    ]);
                }

                $pdo->commit();
                camjc_log('editou', $id);
                header("Location: /portal/camjc/ver.php?id={$id}&editado=1");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = 'Erro ao salvar: ' . $e->getMessage();
            }
        }
    }
}

// Dados para preencher o formulário: POST tem prioridade em caso de erro de validação
$v = fn($campo, $default = '') => htmlspecialchars($_POST[$campo] ?? $default ?? '');

include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap" style="max-width:820px">
  <h2>Editar — <?= htmlspecialchars($a['nome']) ?></h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <div class="form-tabs">
    <button type="button" class="ativo" data-tab="pessoais">Dados pessoais</button>
    <button type="button" data-tab="responsavel">Responsável</button>
    <button type="button" data-tab="entrevista">Entrevista de perfil</button>
    <button type="button" data-tab="obs">Observações</button>
  </div>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="tab-pane ativo" data-tab-pane="pessoais">
      <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach (CAMJC_STATUS as $chave => $label): ?>
          <option value="<?= $chave ?>" <?= (($_POST['status'] ?? $a['status']) === $chave) ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="nome">Nome completo <span style="color:var(--red)">*</span></label>
        <input type="text" id="nome" name="nome" value="<?= $v('nome', $a['nome']) ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="data_nasc">Data de nascimento</label>
          <input type="date" id="data_nasc" name="data_nasc" value="<?= $v('data_nasc', $a['data_nasc']) ?>">
        </div>
        <div class="form-group">
          <label for="estado_civil">Estado civil</label>
          <select id="estado_civil" name="estado_civil">
            <option value="">— Selecione —</option>
            <?php foreach (['Solteira','Casada','Divorciada','Viúva','União estável','Outro'] as $ec): ?>
            <option value="<?= $ec ?>" <?= (($_POST['estado_civil'] ?? $a['estado_civil']) === $ec) ? 'selected' : '' ?>><?= $ec ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="rg">RG</label>
          <input type="text" id="rg" name="rg" value="<?= $v('rg', $a['rg']) ?>">
        </div>
        <div class="form-group">
          <label for="cpf">CPF</label>
          <input type="text" id="cpf" name="cpf" value="<?= $v('cpf', $a['cpf']) ?>" maxlength="14">
        </div>
      </div>
      <div class="form-group">
        <label for="endereco">Endereço</label>
        <input type="text" id="endereco" name="endereco" value="<?= $v('endereco', $a['endereco']) ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="bairro">Bairro</label>
          <input type="text" id="bairro" name="bairro" value="<?= $v('bairro', $a['bairro']) ?>">
        </div>
        <div class="form-group">
          <label for="cep">CEP</label>
          <input type="text" id="cep" name="cep" value="<?= $v('cep', $a['cep']) ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="cidade">Cidade atual</label>
          <input type="text" id="cidade" name="cidade" value="<?= $v('cidade', $a['cidade']) ?>">
        </div>
        <div class="form-group">
          <label for="estado">Estado</label>
          <select id="estado" name="estado">
            <option value="">— UF —</option>
            <?php foreach ($ufs as $uf): ?>
            <option value="<?= $uf ?>" <?= (($_POST['estado'] ?? $a['estado']) === $uf) ? 'selected' : '' ?>><?= $uf ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="telefone">Telefone fixo</label>
          <input type="text" id="telefone" name="telefone" value="<?= $v('telefone', $a['telefone']) ?>">
        </div>
        <div class="form-group">
          <label for="celular">Celular</label>
          <input type="text" id="celular" name="celular" value="<?= $v('celular', $a['celular']) ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="data_triagem">Data da triagem</label>
          <input type="date" id="data_triagem" name="data_triagem" value="<?= $v('data_triagem', $t['data_triagem'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-group">
          <label for="data_acolhimento">Data de acolhimento</label>
          <input type="date" id="data_acolhimento" name="data_acolhimento" value="<?= $v('data_acolhimento', $a['data_acolhimento']) ?>">
        </div>
      </div>
    </div>

    <div class="tab-pane" data-tab-pane="responsavel">
      <div class="form-group">
        <label for="responsavel_nome">Nome do responsável</label>
        <input type="text" id="responsavel_nome" name="responsavel_nome" value="<?= $v('responsavel_nome', $a['responsavel_nome']) ?>">
      </div>
      <div class="form-group">
        <label for="responsavel_endereco">Endereço</label>
        <input type="text" id="responsavel_endereco" name="responsavel_endereco" value="<?= $v('responsavel_endereco', $a['responsavel_endereco']) ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="responsavel_rg">RG</label>
          <input type="text" id="responsavel_rg" name="responsavel_rg" value="<?= $v('responsavel_rg', $a['responsavel_rg']) ?>">
        </div>
        <div class="form-group">
          <label for="responsavel_cpf">CPF</label>
          <input type="text" id="responsavel_cpf" name="responsavel_cpf" value="<?= $v('responsavel_cpf', $a['responsavel_cpf']) ?>" maxlength="14">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="responsavel_data_nasc">Data de nascimento</label>
          <input type="date" id="responsavel_data_nasc" name="responsavel_data_nasc" value="<?= $v('responsavel_data_nasc', $a['responsavel_data_nasc']) ?>">
        </div>
        <div class="form-group">
          <label for="responsavel_telefone">Telefone</label>
          <input type="text" id="responsavel_telefone" name="responsavel_telefone" value="<?= $v('responsavel_telefone', $a['responsavel_telefone']) ?>">
        </div>
      </div>
    </div>

    <div class="tab-pane" data-tab-pane="entrevista">
      <?php foreach ($perguntas as $campo => $label): ?>
      <div class="form-group">
        <label for="<?= $campo ?>"><?= htmlspecialchars($label) ?></label>
        <textarea id="<?= $campo ?>" name="<?= $campo ?>" rows="3"><?= $v($campo, $t[$campo] ?? '') ?></textarea>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="tab-pane" data-tab-pane="obs">
      <div class="form-group">
        <label for="observacoes">Observações</label>
        <textarea id="observacoes" name="observacoes" rows="6"><?= $v('observacoes', $t['observacoes'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label for="responsavel_triagem_nome">Responsável pela triagem</label>
        <input type="text" id="responsavel_triagem_nome" name="responsavel_triagem_nome" value="<?= $v('responsavel_triagem_nome', $t['responsavel_triagem_nome'] ?? '') ?>">
      </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/camjc/ver.php?id=<?= $id ?>" class="btn btn-ghost">Cancelar</a>
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
