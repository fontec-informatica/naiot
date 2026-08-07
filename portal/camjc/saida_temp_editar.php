<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);
require_once __DIR__ . '/_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/camjc/'); exit; }

$st = db()->prepare("SELECT * FROM camjc_saidas_temporarias WHERE id = ?");
$st->execute([$id]);
$s = $st->fetch();
if (!$s) { header('Location: /portal/camjc/'); exit; }

$st2 = db()->prepare("SELECT * FROM camjc_acolhidas WHERE id = ?");
$st2->execute([$s['acolhida_id']]);
$a = $st2->fetch();
if (!$a) { header('Location: /portal/camjc/'); exit; }

$titulo       = 'Editar Saída Temporária — ' . $a['nome'];
$pagina_ativa = 'camjc';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $data_saida = $_POST['data_saida'] ?: $s['data_saida'];
        try {
            db()->prepare("
                UPDATE camjc_saidas_temporarias SET
                    data_saida=?, destino_motivo=?, responsavel_nome=?, responsavel_rg=?, responsavel_cpf=?,
                    responsavel_telefone=?, data_retorno_prevista=?, data_retorno_real=?, observacoes=?, atualizado_em=NOW()
                WHERE id=?
            ")->execute([
                $data_saida,
                trim($_POST['destino_motivo'] ?? '') ?: null,
                trim($_POST['responsavel_nome'] ?? '') ?: null,
                trim($_POST['responsavel_rg'] ?? '') ?: null,
                preg_replace('/\D/', '', $_POST['responsavel_cpf'] ?? '') ?: null,
                trim($_POST['responsavel_telefone'] ?? '') ?: null,
                $_POST['data_retorno_prevista'] ?: null,
                $_POST['data_retorno_real'] ?: null,
                trim($_POST['observacoes'] ?? '') ?: null,
                $id,
            ]);
            camjc_log('editou_saida_temporaria', $s['acolhida_id'], 'Saída temp. #' . $id);
            header("Location: /portal/camjc/ver.php?id={$s['acolhida_id']}&saida_editada=1");
            exit;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

$v = fn($campo) => htmlspecialchars($_POST[$campo] ?? $s[$campo] ?? '');

include dirname(__DIR__) . '/_layout.php';
?>
<div class="form-wrap">
  <h2>Editar Saída Temporária — <?= htmlspecialchars($a['nome']) ?></h2>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-row">
      <div class="form-group">
        <label for="data_saida">Data da saída</label>
        <input type="date" id="data_saida" name="data_saida" value="<?= $v('data_saida') ?>">
      </div>
      <div class="form-group">
        <label for="data_retorno_prevista">Retorno previsto</label>
        <input type="date" id="data_retorno_prevista" name="data_retorno_prevista" value="<?= $v('data_retorno_prevista') ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="destino_motivo">Destino / motivo da saída</label>
      <textarea id="destino_motivo" name="destino_motivo" rows="2"><?= $v('destino_motivo') ?></textarea>
    </div>

    <div class="form-group">
      <label for="responsavel_nome">Responsável pela saída</label>
      <input type="text" id="responsavel_nome" name="responsavel_nome" value="<?= $v('responsavel_nome') ?>">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="responsavel_rg">RG do responsável</label>
        <input type="text" id="responsavel_rg" name="responsavel_rg" value="<?= $v('responsavel_rg') ?>">
      </div>
      <div class="form-group">
        <label for="responsavel_cpf">CPF do responsável</label>
        <input type="text" id="responsavel_cpf" name="responsavel_cpf" value="<?= $v('responsavel_cpf') ?>" maxlength="14">
      </div>
    </div>
    <div class="form-group">
      <label for="responsavel_telefone">Telefone do responsável</label>
      <input type="text" id="responsavel_telefone" name="responsavel_telefone" value="<?= $v('responsavel_telefone') ?>">
    </div>

    <div class="form-group" style="background:var(--off);padding:12px 14px;border-radius:var(--r)">
      <label for="data_retorno_real">Data do retorno efetivo <span style="font-weight:400;color:var(--cinza3)">(preencher quando ela voltar)</span></label>
      <input type="date" id="data_retorno_real" name="data_retorno_real" value="<?= $v('data_retorno_real') ?>">
    </div>

    <div class="form-group">
      <label for="observacoes">Observações</label>
      <textarea id="observacoes" name="observacoes" rows="3"><?= $v('observacoes') ?></textarea>
    </div>

    <div class="form-acoes">
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <a href="/portal/camjc/ver.php?id=<?= $s['acolhida_id'] ?>" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<script src="/portal/assets/js/camjc-form.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/camjc-form.js') ?>"></script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
