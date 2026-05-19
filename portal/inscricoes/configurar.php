<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Configurar Inscrições';
$pagina_ativa = 'inscricoes';
$erro  = '';
$ok    = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/inscricoes/'); exit; }

$stmt = db()->prepare('SELECT * FROM eventos WHERE id = ?');
$stmt->execute([$id]);
$ev = $stmt->fetch();
if (!$ev) { header('Location: /portal/inscricoes/'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erro = 'Token inválido. Recarregue a página.';
    } else {
        $inscricoes_abertas = isset($_POST['inscricoes_abertas']) ? 1 : 0;
        $vagas              = (int)($_POST['vagas'] ?? 0) ?: null;
        $valor              = (float)str_replace(',', '.', preg_replace('/[^0-9,]/', '', $_POST['valor'] ?? '0'));
        $data_encerramento  = $_POST['data_encerramento'] ?: null;

        db()->prepare('UPDATE eventos SET inscricoes_abertas=?, vagas=?, valor=?, data_encerramento=? WHERE id=?')
            ->execute([$inscricoes_abertas, $vagas, $valor, $data_encerramento, $id]);

        $ev['inscricoes_abertas'] = $inscricoes_abertas;
        $ev['vagas']              = $vagas;
        $ev['valor']              = $valor;
        $ev['data_encerramento']  = $data_encerramento;
        $ok = 'Configurações salvas com sucesso!';
    }
}

$total_inscritos = db()->prepare('SELECT COUNT(*) FROM inscricoes WHERE evento_id = ? AND status != ?');
$total_inscritos->execute([$id, 'cancelado']);
$inscritos = (int)$total_inscritos->fetchColumn();

include dirname(__DIR__) . '/_layout.php';
?>

<div class="form-wrap">
  <div style="margin-bottom:20px">
    <a href="/portal/inscricoes/" style="color:var(--cinza3);font-size:.85rem;text-decoration:none">← Voltar para Inscrições</a>
  </div>

  <h2>Configurar inscrições</h2>
  <p style="color:var(--cinza3);margin-top:4px;margin-bottom:20px;font-size:.9rem">
    Evento: <strong style="color:var(--texto)"><?= htmlspecialchars($ev['titulo']) ?></strong>
  </p>

  <?php if ($erro): ?>
    <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>
  <?php if ($ok): ?>
    <div class="alerta alerta-ok"><?= htmlspecialchars($ok) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="inscricoes_abertas" value="1"
               <?= ($ev['inscricoes_abertas'] ? 'checked' : '') ?>>
        <span><strong>Inscrições abertas</strong> — exibe botão "Inscrever-se" no site</span>
      </label>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label for="vagas">Vagas totais <span style="font-weight:400;color:var(--cinza3)">(vazio = ilimitado)</span></label>
        <input type="number" id="vagas" name="vagas" min="1"
               value="<?= htmlspecialchars($ev['vagas'] ?? '') ?>" placeholder="Ilimitado">
        <?php if ($ev['vagas']): ?>
          <span class="form-hint"><?= $inscritos ?> inscritos de <?= $ev['vagas'] ?> vagas</span>
        <?php else: ?>
          <span class="form-hint"><?= $inscritos ?> inscrito(s) até agora</span>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label for="valor">Valor base (R$) <span style="font-weight:400;color:var(--cinza3)">(0 = gratuito)</span></label>
        <input type="text" id="valor" name="valor"
               value="<?= htmlspecialchars(number_format($ev['valor'] ?? 0, 2, ',', '')) ?>" placeholder="0,00">
        <span class="form-hint">Use lotes para múltiplos preços.</span>
      </div>
    </div>

    <div class="form-group">
      <label for="data_encerramento">Encerrar inscrições automaticamente em <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="date" id="data_encerramento" name="data_encerramento"
             value="<?= htmlspecialchars($ev['data_encerramento'] ?? '') ?>">
      <span class="form-hint">Deixe vazio para não encerrar automaticamente por data.</span>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">Salvar configurações</button>
      <a href="/portal/inscricoes/evento.php?id=<?= $id ?>" class="btn btn-ghost">Ver inscritos</a>
    </div>
  </form>

  <hr style="margin:28px 0;border:none;border-top:1px solid var(--cinza2)">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
    <div>
      <strong style="font-size:.95rem">Lotes de inscrição</strong>
      <p style="color:var(--cinza3);font-size:.85rem;margin-top:3px">Defina múltiplos preços ou categorias (Adulto, Criança, 1º Lote…)</p>
    </div>
    <a href="/portal/eventos/lotes.php?id=<?= $id ?>" class="btn btn-ouro btn-sm">Gerenciar lotes</a>
  </div>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
