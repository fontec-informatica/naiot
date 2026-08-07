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

$titulo       = 'Nova Saída Temporária — ' . $a['nome'];
$pagina_ativa = 'camjc';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $data_saida = $_POST['data_saida'] ?: date('Y-m-d');
        try {
            db()->prepare("
                INSERT INTO camjc_saidas_temporarias
                    (acolhida_id, data_saida, destino_motivo, responsavel_nome, responsavel_rg, responsavel_cpf,
                     responsavel_telefone, data_retorno_prevista, observacoes, criado_por)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $acolhida_id, $data_saida,
                trim($_POST['destino_motivo'] ?? '') ?: null,
                trim($_POST['responsavel_nome'] ?? '') ?: null,
                trim($_POST['responsavel_rg'] ?? '') ?: null,
                preg_replace('/\D/', '', $_POST['responsavel_cpf'] ?? '') ?: null,
                trim($_POST['responsavel_telefone'] ?? '') ?: null,
                $_POST['data_retorno_prevista'] ?: null,
                trim($_POST['observacoes'] ?? '') ?: null,
                $_SESSION['usuario_id'] ?? null,
            ]);
            $novo_id = (int)db()->lastInsertId();
            camjc_log('criou_saida_temporaria', $acolhida_id, 'Saída temp. #' . $novo_id);
            header("Location: /portal/camjc/ver.php?id={$acolhida_id}&saida_ok=1");
            exit;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Nova Saída Temporária — <?= htmlspecialchars($a['nome']) ?></h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-row">
      <div class="form-group">
        <label for="data_saida">Data da saída</label>
        <input type="date" id="data_saida" name="data_saida" value="<?= htmlspecialchars($_POST['data_saida'] ?? date('Y-m-d')) ?>">
      </div>
      <div class="form-group">
        <label for="data_retorno_prevista">Retorno previsto</label>
        <input type="date" id="data_retorno_prevista" name="data_retorno_prevista" value="<?= htmlspecialchars($_POST['data_retorno_prevista'] ?? '') ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="destino_motivo">Destino / motivo da saída</label>
      <textarea id="destino_motivo" name="destino_motivo" rows="2" placeholder="Ex: consulta médica, compromisso familiar..."><?= htmlspecialchars($_POST['destino_motivo'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label for="responsavel_nome">Responsável pela saída</label>
      <input type="text" id="responsavel_nome" name="responsavel_nome" value="<?= htmlspecialchars($_POST['responsavel_nome'] ?? $a['responsavel_nome'] ?? '') ?>">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="responsavel_rg">RG do responsável</label>
        <input type="text" id="responsavel_rg" name="responsavel_rg" value="<?= htmlspecialchars($_POST['responsavel_rg'] ?? $a['responsavel_rg'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="responsavel_cpf">CPF do responsável</label>
        <input type="text" id="responsavel_cpf" name="responsavel_cpf" value="<?= htmlspecialchars($_POST['responsavel_cpf'] ?? $a['responsavel_cpf'] ?? '') ?>" maxlength="14">
      </div>
    </div>
    <div class="form-group">
      <label for="responsavel_telefone">Telefone do responsável</label>
      <input type="text" id="responsavel_telefone" name="responsavel_telefone" value="<?= htmlspecialchars($_POST['responsavel_telefone'] ?? $a['responsavel_telefone'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="observacoes">Observações</label>
      <textarea id="observacoes" name="observacoes" rows="3"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
    </div>

    <div class="form-acoes">
      <button type="submit" class="btn btn-primary">Salvar saída temporária</button>
      <a href="/portal/camjc/ver.php?id=<?= $acolhida_id ?>" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<script src="/portal/assets/js/camjc-form.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/camjc-form.js') ?>"></script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
