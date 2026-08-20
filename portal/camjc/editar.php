<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

// Permissions-Policy global do portal bloqueia câmera (camera=()) — sem isso o
// navegador nem chega a pedir permissão ao usuário, já rejeita direto.
// Libera câmera só nesta página (mesmo padrão que o login.php usa pra CSP).
if (!headers_sent()) {
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=()');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("SELECT * FROM camjc_acolhidas WHERE id = ?");
$st->execute([$id]);
$a = $st->fetch();
if (!$a) { header('Location: /portal/camjc/'); exit; }
if (!empty($a['excluido_em'])) { header("Location: /portal/camjc/ver.php?id={$id}"); exit; }

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

            $data_acolhimento = $_POST['data_acolhimento'] ?: null;
            // Consistência nos dois sentidos entre status e data de acolhimento:
            // - Se marcou como Acolhida mas esqueceu a data, assume hoje.
            // - Se preencheu a data mas o status ainda está "Em triagem"/"Não
            //   admitida" (esqueceu de mudar o status), promove para Acolhida.
            if ($status === 'acolhida' && !$data_acolhimento) {
                $data_acolhimento = date('Y-m-d');
            } elseif ($data_acolhimento && in_array($status, ['em_triagem', 'nao_admitida'], true)) {
                $status = 'acolhida';
            }

            $data_saida   = $_POST['data_saida'] ?: null;
            $motivo_saida = trim($_POST['motivo_saida'] ?? '') ?: null;
            if (in_array($status, CAMJC_STATUS_SAIDA, true) && !$data_saida) {
                $data_saida = ($status === 'alta' && $data_acolhimento)
                    ? camjc_previsao_saida($data_acolhimento)
                    : date('Y-m-d');
            }

            // Foto — opcional na triagem, importante ao admitir (não obrigatória aqui)
            $nova_foto = $a['foto'];
            $foto_erro = '';
            $foto_b64  = trim($_POST['foto_webcam'] ?? '');
            if ($foto_b64 && preg_match('/^data:image\/(jpeg|png|webp);base64,/', $foto_b64)) {
                $img_data  = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $foto_b64), true);
                $finfo_b64 = new finfo(FILEINFO_MIME_TYPE);
                $mime_b64  = $finfo_b64->buffer($img_data ?: '');
                $mimes_ok  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!$img_data || !isset($mimes_ok[$mime_b64])) {
                    $foto_erro = 'Foto inválida.';
                } elseif (strlen($img_data) > 5 * 1024 * 1024) {
                    $foto_erro = 'Foto: máximo 5 MB.';
                } else {
                    $dir_fotos = __DIR__ . '/fotos/';
                    if (!is_dir($dir_fotos)) mkdir($dir_fotos, 0755, true);
                    $nova_foto = 'camjc_' . uniqid('', true) . '.' . $mimes_ok[$mime_b64];
                    file_put_contents($dir_fotos . $nova_foto, $img_data);
                }
            } elseif (!empty($_FILES['foto']['tmp_name'])) {
                $f = $_FILES['foto'];
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $foto_erro = 'Erro ao receber a foto.';
                } elseif ($f['size'] > 5 * 1024 * 1024) {
                    $foto_erro = 'Foto: máximo 5 MB.';
                } else {
                    $finfo_up = new finfo(FILEINFO_MIME_TYPE);
                    $mime_up  = $finfo_up->file($f['tmp_name']);
                    $mimes_ok = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    if (!isset($mimes_ok[$mime_up])) {
                        $foto_erro = 'Foto: somente JPG, PNG ou WebP.';
                    } else {
                        $dir_fotos = __DIR__ . '/fotos/';
                        if (!is_dir($dir_fotos)) mkdir($dir_fotos, 0755, true);
                        $nova_foto = 'camjc_' . uniqid('', true) . '.' . $mimes_ok[$mime_up];
                        move_uploaded_file($f['tmp_name'], $dir_fotos . $nova_foto);
                    }
                }
            }

            if ($foto_erro) { $erro = $foto_erro; goto fim_post; }

            $foto_antiga = ($nova_foto !== $a['foto']) ? $a['foto'] : null;

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    UPDATE camjc_acolhidas SET
                        nome=?, foto=?, data_nasc=?, estado_civil=?, rg=?, cpf=?, endereco=?, complemento=?, bairro=?, cep=?, cidade=?, estado=?,
                        telefone=?, celular=?, responsavel_nome=?, responsavel_endereco=?, responsavel_complemento=?, responsavel_rg=?, responsavel_cpf=?,
                        responsavel_data_nasc=?, responsavel_telefone=?, status=?, data_acolhimento=?, data_saida=?, motivo_saida=?, atualizado_em=NOW()
                    WHERE id=?
                ")->execute([
                    $nome,
                    $nova_foto,
                    $_POST['data_nasc'] ?: null,
                    trim($_POST['estado_civil'] ?? '') ?: null,
                    trim($_POST['rg'] ?? '') ?: null,
                    preg_replace('/\D/', '', $_POST['cpf'] ?? '') ?: null,
                    trim($_POST['endereco'] ?? '') ?: null,
                    trim($_POST['complemento'] ?? '') ?: null,
                    trim($_POST['bairro'] ?? '') ?: null,
                    trim($_POST['cep'] ?? '') ?: null,
                    trim($_POST['cidade'] ?? '') ?: null,
                    trim($_POST['estado'] ?? '') ?: null,
                    trim($_POST['telefone'] ?? '') ?: null,
                    trim($_POST['celular'] ?? '') ?: null,
                    trim($_POST['responsavel_nome'] ?? '') ?: null,
                    trim($_POST['responsavel_endereco'] ?? '') ?: null,
                    trim($_POST['responsavel_complemento'] ?? '') ?: null,
                    trim($_POST['responsavel_rg'] ?? '') ?: null,
                    preg_replace('/\D/', '', $_POST['responsavel_cpf'] ?? '') ?: null,
                    $_POST['responsavel_data_nasc'] ?: null,
                    trim($_POST['responsavel_telefone'] ?? '') ?: null,
                    $status,
                    $data_acolhimento,
                    $data_saida,
                    $motivo_saida,
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
                if ($foto_antiga) {
                    $caminho_antigo = __DIR__ . '/fotos/' . $foto_antiga;
                    if (file_exists($caminho_antigo)) unlink($caminho_antigo);
                }
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
fim_post:

// Dados para preencher o formulário: POST tem prioridade em caso de erro de validação
$v = fn($campo, $default = '') => htmlspecialchars($_POST[$campo] ?? $default ?? '');

include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
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

  <form method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="tab-pane ativo" data-tab-pane="pessoais">
      <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach (CAMJC_STATUS as $chave => $label): ?>
          <option value="<?= $chave ?>" <?= (($_POST['status'] ?? $a['status']) === $chave) ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <span class="form-hint">Ao selecionar "Acolhida", a data de acolhimento abaixo é preenchida automaticamente com a data de hoje (se estiver em branco) — você pode ajustar.</span>
      </div>
      <div class="form-group">
        <label for="nome">Nome completo <span style="color:var(--red)">*</span></label>
        <input type="text" id="nome" name="nome" value="<?= $v('nome', $a['nome']) ?>" required>
      </div>

      <div class="form-group">
        <label>Foto <span style="font-weight:400;color:var(--cinza3)">(não é necessária na triagem — importante ao admitir)</span></label>
        <div id="foto_preview_wrap" style="margin-bottom:8px<?= empty($a['foto']) ? ';display:none' : '' ?>">
          <img id="foto_preview_img" src="<?= !empty($a['foto']) ? '/portal/camjc/foto.php?id=' . $id : '' ?>" alt="" style="max-height:120px;border-radius:6px;display:block">
        </div>
        <input type="hidden" name="foto_webcam" id="foto_webcam">
        <input type="file" name="foto" id="foto_input" accept="image/jpeg,image/png,image/webp" style="display:none">
        <input type="file" id="foto_camera_input" accept="image/*" capture="environment" style="display:none">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button type="button" onclick="document.getElementById('foto_input').click()" class="btn btn-ghost btn-sm">📎 Selecionar arquivo</button>
          <button type="button" id="btn-abrir-camera" class="btn btn-ghost btn-sm">📷 Câmera</button>
        </div>
        <span class="form-hint">JPG, PNG ou WebP — máx. 5MB.</span>
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
      <div class="form-group">
        <label for="complemento">Complemento</label>
        <input type="text" id="complemento" name="complemento" value="<?= $v('complemento', $a['complemento'] ?? '') ?>" placeholder="Apto, bloco, casa, ponto de referência…">
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
          <input type="text" id="cidade" name="cidade" value="<?= $v('cidade', $a['cidade']) ?>" autocomplete="off" placeholder="Digite para buscar…" data-cidade-ac data-uf-alvo="estado">
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
          <span class="form-hint" id="previsao_saida_hint" style="display:none"></span>
        </div>
      </div>
      <div class="form-row" id="saida_wrap" style="display:none">
        <div class="form-group">
          <label for="data_saida">Data de saída</label>
          <input type="date" id="data_saida" name="data_saida" value="<?= $v('data_saida', $a['data_saida']) ?>">
          <span class="form-hint">Alta: sugerida automaticamente 9 meses após o acolhimento — ajuste se houver cerimônia em outra data. Demais saídas: sugerida a data de hoje.</span>
        </div>
        <div class="form-group">
          <label for="motivo_saida">Motivo / observações da saída</label>
          <input type="text" id="motivo_saida" name="motivo_saida" value="<?= $v('motivo_saida', $a['motivo_saida']) ?>">
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
      <div class="form-group">
        <label for="responsavel_complemento">Complemento</label>
        <input type="text" id="responsavel_complemento" name="responsavel_complemento" value="<?= $v('responsavel_complemento', $a['responsavel_complemento'] ?? '') ?>" placeholder="Apto, bloco, casa, ponto de referência…">
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

    <div class="form-acoes">
      <button type="button" id="btn-voltar" class="btn btn-ghost" style="display:none">← Voltar</button>
      <button type="button" id="btn-proximo" class="btn btn-primary">Próximo →</button>
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/camjc/ver.php?id=<?= $id ?>" class="btn btn-ghost" style="margin-left:auto">Cancelar</a>
    </div>
  </form>
</div>

<!-- Modal câmera -->
<div id="foto_webcam_modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:14px;padding:20px;width:min(420px,94vw);box-shadow:0 8px 40px rgba(0,0,0,.35)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <strong style="font-family:'Cinzel',serif;font-size:.82rem;color:var(--green-dk)">📷 Câmera</strong>
      <button type="button" onclick="camjcFecharCamera()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--muted);line-height:1">×</button>
    </div>
    <video id="foto_video" autoplay playsinline muted style="width:100%;border-radius:10px;background:#000;display:block"></video>
    <canvas id="foto_canvas" style="display:none"></canvas>
    <p id="foto_cam_erro" style="color:var(--red);font-size:.78rem;margin-top:10px;display:none"></p>
    <div style="display:flex;gap:8px;margin-top:14px">
      <button type="button" onclick="camjcTirarFoto()" class="btn btn-primary btn-sm">Tirar foto</button>
      <button type="button" onclick="camjcFecharCamera()" class="btn btn-ghost btn-sm">Cancelar</button>
    </div>
  </div>
</div>

<script src="/portal/assets/js/camjc-form.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/camjc-form.js') ?>"></script>
<script src="/portal/assets/js/cidade-autocomplete.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/cidade-autocomplete.js') ?>"></script>
<script>
(function () {
  // ── Status ↔ Data de acolhimento — sincronizados nos dois sentidos (visível, editável) ──
  var selStatus = document.getElementById('status');
  var campoData = document.getElementById('data_acolhimento');
  var STATUS_PRE_ACOLHIMENTO = ['em_triagem', 'nao_admitida'];

  function hojeISO() {
    var hoje = new Date();
    return hoje.getFullYear() + '-' + String(hoje.getMonth() + 1).padStart(2, '0') + '-' + String(hoje.getDate()).padStart(2, '0');
  }

  // ── Status → Data de saída + previsão de conclusão (9 meses) ──
  var STATUS_SAIDA = ['alta', 'evasao', 'transferencia', 'nao_admitida'];
  var wrapSaida     = document.getElementById('saida_wrap');
  var campoSaida    = document.getElementById('data_saida');
  var hintPrevisao  = document.getElementById('previsao_saida_hint');

  function somarMeses(dataISO, meses) {
    var p = dataISO.split('-');
    var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    d.setMonth(d.getMonth() + meses);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function fmtBR(dataISO) {
    var p = dataISO.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
  }

  function atualizarPrevisao() {
    if (selStatus.value === 'acolhida' && campoData.value) {
      hintPrevisao.textContent = 'Previsão de conclusão do programa (9 meses): ' + fmtBR(somarMeses(campoData.value, 9));
      hintPrevisao.style.display = '';
    } else {
      hintPrevisao.style.display = 'none';
    }
  }

  function atualizarSaida() {
    var mostrar = STATUS_SAIDA.indexOf(selStatus.value) !== -1;
    wrapSaida.style.display = mostrar ? '' : 'none';
    if (mostrar && !campoSaida.value) {
      if (selStatus.value === 'alta') {
        campoSaida.value = somarMeses(campoData.value || hojeISO(), 9);
      } else {
        campoSaida.value = hojeISO();
      }
    }
  }

  if (selStatus && campoData) {
    selStatus.addEventListener('change', function () {
      if (selStatus.value === 'acolhida' && !campoData.value) {
        campoData.value = hojeISO();
      }
      atualizarPrevisao();
      atualizarSaida();
    });
    campoData.addEventListener('change', function () {
      if (campoData.value && STATUS_PRE_ACOLHIMENTO.indexOf(selStatus.value) !== -1) {
        selStatus.value = 'acolhida';
      }
      atualizarPrevisao();
    });
    atualizarPrevisao();
    atualizarSaida();
  }

  // ── Foto: seleção de arquivo mostra preview ──
  var fotoInput = document.getElementById('foto_input');
  var fotoWebcamCampo = document.getElementById('foto_webcam');
  var previewWrap = document.getElementById('foto_preview_wrap');
  var previewImg  = document.getElementById('foto_preview_img');

  function mostrarPreview(src) {
    previewImg.src = src;
    previewWrap.style.display = '';
  }

  if (fotoInput) {
    fotoInput.addEventListener('change', function () {
      if (!fotoInput.files[0]) return;
      fotoWebcamCampo.value = '';
      var reader = new FileReader();
      reader.onload = function (e) { mostrarPreview(e.target.result); };
      reader.readAsDataURL(fotoInput.files[0]);
    });
  }

  var camInput = document.getElementById('foto_camera_input');
  if (camInput) {
    camInput.addEventListener('change', function () {
      if (!camInput.files[0]) return;
      fotoWebcamCampo.value = '';
      if (fotoInput) {
        var dt = new DataTransfer();
        dt.items.add(camInput.files[0]);
        fotoInput.files = dt.files;
      }
      var reader = new FileReader();
      reader.onload = function (e) { mostrarPreview(e.target.result); };
      reader.readAsDataURL(camInput.files[0]);
    });
  }

  // ── Câmera (desktop via getUserMedia, mobile via input nativo) ──
  var _stream = null;
  window.camjcAbrirCamera = function () {
    if (/Mobi|Android/i.test(navigator.userAgent)) {
      camInput.click();
      return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert('Seu navegador não suporta acesso à câmera. Use um navegador moderno com HTTPS.');
      return;
    }
    document.getElementById('foto_webcam_modal').style.display = 'flex';
    document.getElementById('foto_cam_erro').style.display = 'none';
    camjcIniciarStream();
  };

  async function camjcIniciarStream() {
    if (_stream) { _stream.getTracks().forEach(function (t) { t.stop(); }); _stream = null; }
    try {
      _stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
      var v = document.getElementById('foto_video');
      v.srcObject = _stream;
    } catch (e) {
      var msg = e.name === 'NotAllowedError'
        ? 'Permissão negada. Clique no cadeado na barra de endereço e permita o acesso à câmera.'
        : 'Erro ao acessar câmera: ' + e.message;
      var el = document.getElementById('foto_cam_erro');
      el.textContent = msg; el.style.display = 'block';
    }
  }

  window.camjcTirarFoto = function () {
    var video  = document.getElementById('foto_video');
    var canvas = document.getElementById('foto_canvas');
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0);
    var dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    fotoWebcamCampo.value = dataUrl;
    if (fotoInput) fotoInput.value = '';
    mostrarPreview(dataUrl);
    camjcFecharCamera();
  };

  window.camjcFecharCamera = function () {
    if (_stream) { _stream.getTracks().forEach(function (t) { t.stop(); }); _stream = null; }
    document.getElementById('foto_webcam_modal').style.display = 'none';
    document.getElementById('foto_video').srcObject = null;
  };

  var btnAbrirCamera = document.getElementById('btn-abrir-camera');
  if (btnAbrirCamera) btnAbrirCamera.addEventListener('click', camjcAbrirCamera);
})();
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
