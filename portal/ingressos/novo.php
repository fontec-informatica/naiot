<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Nova Campanha — Controle de Ingressos';
$pagina_ativa = 'ingressos';
$ing_secao    = 'campanhas';
$erro         = '';

function ing_valor_post(string $campo): float {
    return (float)str_replace(',', '.', str_replace('.', '', preg_replace('/[^0-9,]/', '', $_POST[$campo] ?? '0')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $nome  = trim($_POST['nome'] ?? '');
    $desc  = trim($_POST['descricao'] ?? '');
    $data  = $_POST['data_evento'] ?: null;
    $v_mesa = ing_valor_post('valor_mesa');
    $v_ind  = ing_valor_post('valor_individual');

    if (!$nome) {
        $erro = 'Informe o nome da campanha/evento.';
    } else {
        db()->prepare("INSERT INTO ingressos_campanhas (nome, descricao, data_evento, valor_mesa, valor_individual) VALUES (?,?,?,?,?)")
            ->execute([$nome, $desc ?: null, $data, $v_mesa, $v_ind]);
        $novo_id = (int)db()->lastInsertId();
        header("Location: /portal/ingressos/gerenciar.php?id=$novo_id&ok=criado");
        exit;
    }
}

include dirname(__DIR__) . '/_layout.php';
?>

<?php include __DIR__ . '/_subnav.php'; ?>

<div class="form-wrap">
  <h2>Nova campanha / evento</h2>
  <p style="color:var(--cinza3);font-size:.85rem;margin-top:-10px;margin-bottom:16px">Ex: "Almoço Caipira 2026" — cada campanha tem sua própria numeração de ingressos.</p>
  <?php if ($erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label>Nome</label>
      <input type="text" name="nome" placeholder="Ex: Almoço Caipira 2026" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Descrição <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="text" name="descricao" value="<?= htmlspecialchars($_POST['descricao'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Data do evento <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="date" name="data_evento" value="<?= htmlspecialchars($_POST['data_evento'] ?? '') ?>">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Valor padrão — Mesa (R$)</label>
        <input type="text" name="valor_mesa" placeholder="0,00">
      </div>
      <div class="form-group">
        <label>Valor padrão — Individual (R$)</label>
        <input type="text" name="valor_individual" placeholder="0,00">
      </div>
    </div>
    <span class="form-hint">Os valores padrão só pré-preenchem o formulário de geração de ingressos — dá para ajustar por faixa.</span>
    <div style="display:flex;gap:12px;margin-top:14px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">Criar campanha</button>
      <a href="/portal/ingressos/" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
