<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$titulo       = 'Controle de Ingressos';
$pagina_ativa = 'ingressos';
$erro         = '';

function ing_valor_post(string $campo): float {
    return (float)str_replace(',', '.', str_replace('.', '', preg_replace('/[^0-9,]/', '', $_POST[$campo] ?? '0')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
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

    } elseif ($acao === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) db()->prepare('UPDATE ingressos_campanhas SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
        header('Location: /portal/ingressos/');
        exit;

    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM ingressos_tickets WHERE campanha_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM ingressos_campanhas WHERE id = ?')->execute([$id]);
            $pdo->commit();
        }
        header('Location: /portal/ingressos/');
        exit;
    }
}

$campanhas = db()->query("
    SELECT c.*,
        (SELECT COUNT(*) FROM ingressos_tickets t WHERE t.campanha_id = c.id) AS total,
        (SELECT COUNT(*) FROM ingressos_tickets t WHERE t.campanha_id = c.id AND t.status='estoque')     AS qtd_estoque,
        (SELECT COUNT(*) FROM ingressos_tickets t WHERE t.campanha_id = c.id AND t.status='distribuido') AS qtd_distribuido,
        (SELECT COUNT(*) FROM ingressos_tickets t WHERE t.campanha_id = c.id AND t.status='pago')        AS qtd_pago,
        (SELECT COALESCE(SUM(COALESCE(t.valor_pago,t.valor)),0) FROM ingressos_tickets t WHERE t.campanha_id = c.id AND t.status='pago')        AS receita,
        (SELECT COALESCE(SUM(t.valor),0) FROM ingressos_tickets t WHERE t.campanha_id = c.id AND t.status='distribuido') AS a_receber
    FROM ingressos_campanhas c
    ORDER BY c.ativo DESC, COALESCE(c.data_evento,'9999-12-31') ASC, c.id DESC
")->fetchAll();

$tot = ['total'=>0,'estoque'=>0,'distribuido'=>0,'pago'=>0,'receita'=>0,'a_receber'=>0];
foreach ($campanhas as $c) {
    $tot['total']       += (int)$c['total'];
    $tot['estoque']     += (int)$c['qtd_estoque'];
    $tot['distribuido'] += (int)$c['qtd_distribuido'];
    $tot['pago']        += (int)$c['qtd_pago'];
    $tot['receita']     += (float)$c['receita'];
    $tot['a_receber']   += (float)$c['a_receber'];
}

include dirname(__DIR__) . '/_layout.php';
?>

<?php if (($_GET['ok'] ?? '') === 'criado'): ?><div class="alerta alerta-ok" style="margin-bottom:16px">✓ Campanha criada com sucesso.</div><?php endif; ?>

<div class="cards" style="margin-bottom:24px">
  <div class="card-stat"><h3>Ingressos emitidos</h3><div class="val"><?= (int)$tot['total'] ?></div></div>
  <div class="card-stat"><h3>Em estoque</h3><div class="val"><?= (int)$tot['estoque'] ?></div></div>
  <div class="card-stat ouro"><h3>Com servos</h3><div class="val"><?= (int)$tot['distribuido'] ?></div></div>
  <div class="card-stat verde"><h3>Pagos</h3><div class="val"><?= (int)$tot['pago'] ?></div></div>
</div>
<div style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap">
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 18px">
    <span style="font-size:.72rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:.06em">Recebido</span><br>
    <span style="font-size:1.1rem;font-weight:700;color:#15803d">R$ <?= number_format($tot['receita'], 2, ',', '.') ?></span>
  </div>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 18px">
    <span style="font-size:.72rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em">A receber</span><br>
    <span style="font-size:1.1rem;font-weight:700;color:#b45309">R$ <?= number_format($tot['a_receber'], 2, ',', '.') ?></span>
  </div>
</div>

<div class="split-layout">

  <div>
    <div class="tabela-wrap">
      <div class="tabela-header">
        <h2>Campanhas / Eventos</h2>
      </div>
      <?php if (empty($campanhas)): ?>
      <div style="padding:40px;text-align:center;color:var(--cinza3)">
        Nenhuma campanha cadastrada ainda. Crie a primeira ao lado →
      </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Campanha</th>
            <th style="text-align:center">Emitidos</th>
            <th style="text-align:center">Estoque</th>
            <th style="text-align:center">Com servos</th>
            <th style="text-align:center">Pagos</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($campanhas as $c): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($c['nome']) ?></strong>
            <?php if ($c['data_evento']): ?><br><small style="color:var(--cinza3)"><?= date('d/m/Y', strtotime($c['data_evento'])) ?></small><?php endif; ?>
          </td>
          <td style="text-align:center"><?= (int)$c['total'] ?></td>
          <td style="text-align:center"><?= (int)$c['qtd_estoque'] ?></td>
          <td style="text-align:center;color:#b45309;font-weight:600"><?= (int)$c['qtd_distribuido'] ?></td>
          <td style="text-align:center;color:var(--verde);font-weight:600"><?= (int)$c['qtd_pago'] ?></td>
          <td>
            <?php if ($c['ativo']): ?>
              <span style="color:var(--verde);font-size:.8rem">● Ativa</span>
            <?php else: ?>
              <span class="badge badge-inativo">Encerrada</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap">
            <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
              <a href="/portal/ingressos/gerenciar.php?id=<?= $c['id'] ?>" class="btn btn-ouro btn-sm">Gerenciar</a>
              <a href="/portal/ingressos/posicao.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">Posição</a>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button name="acao" value="toggle" class="btn btn-ghost btn-sm"><?= $c['ativo'] ? 'Encerrar' : 'Reativar' ?></button>
              </form>
              <?php if ((int)$c['total'] === 0): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Excluir esta campanha (sem ingressos gerados)?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button name="acao" value="excluir" class="btn btn-danger btn-sm">Excluir</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="form-wrap" style="max-width:none">
      <h2>Nova campanha / evento</h2>
      <p style="color:var(--cinza3);font-size:.85rem;margin-top:-10px;margin-bottom:16px">Ex: "Almoço Caipira 2026" — cada campanha tem sua própria numeração de ingressos.</p>
      <?php if ($erro): ?><div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="acao" value="criar">
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
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:14px">Criar campanha</button>
      </form>
    </div>
  </div>

</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
