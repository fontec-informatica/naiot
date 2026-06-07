<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/eventos/'); exit; }

$stmt = db()->prepare('SELECT * FROM eventos WHERE id = ?');
$stmt->execute([$id]);
$ev = $stmt->fetch();
if (!$ev) { header('Location: /portal/eventos/'); exit; }

$titulo       = 'Página do Evento — ' . $ev['titulo'];
$pagina_ativa = 'inscricoes';
$ok   = '';
$erro = '';

/* ── Carregar programação ── */
$prog_list = [];
try {
    $ps = db()->prepare("SELECT * FROM evento_programacao WHERE evento_id = ? ORDER BY ordem ASC, id ASC");
    $ps->execute([$id]);
    $prog_list = $ps->fetchAll();
} catch (Exception $e) {}

/* ── POST handlers ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'save_info') {
        $sobre      = trim($_POST['sobre']             ?? '');
        $email_org  = trim($_POST['email_organizador'] ?? '');
        $whatsapp   = trim($_POST['whatsapp_contato']  ?? '');
        $mensagem   = trim($_POST['mensagem_inscrito'] ?? '');
        $link_grupo = trim($_POST['link_grupo']        ?? '');

        db()->prepare("UPDATE eventos SET sobre=?, email_organizador=?, whatsapp_contato=?, mensagem_inscrito=?, link_grupo=? WHERE id=?")
            ->execute([$sobre ?: null, $email_org ?: null, $whatsapp ?: null, $mensagem ?: null, $link_grupo ?: null, $id]);

        // Reload
        $stmt->execute([$id]);
        $ev = $stmt->fetch();
        $ok = 'Informações salvas com sucesso!';

    } elseif ($acao === 'add_prog') {
        $horario  = trim($_POST['prog_horario']  ?? '');
        $ptitulo  = trim($_POST['prog_titulo']   ?? '');
        $pdesc    = trim($_POST['prog_descricao'] ?? '');
        $ordem    = (int)($_POST['prog_ordem']   ?? count($prog_list));

        if ($ptitulo) {
            try {
                db()->prepare("INSERT INTO evento_programacao (evento_id, horario, titulo, descricao, ordem) VALUES (?,?,?,?,?)")
                    ->execute([$id, $horario ?: null, $ptitulo, $pdesc ?: null, $ordem]);
                header("Location: /portal/eventos/pagina.php?id=$id&ok=prog");
                exit;
            } catch (Exception $e) {
                $erro = 'Erro: ' . $e->getMessage();
            }
        } else {
            $erro = 'Informe o título da atividade.';
        }

    } elseif ($acao === 'del_prog') {
        $prog_id = (int)($_POST['prog_id'] ?? 0);
        if ($prog_id) {
            try {
                db()->prepare("DELETE FROM evento_programacao WHERE id = ? AND evento_id = ?")->execute([$prog_id, $id]);
            } catch (Exception $e) {}
        }
        header("Location: /portal/eventos/pagina.php?id=$id");
        exit;

    } elseif ($acao === 'reorder_prog') {
        $ids = array_map('intval', explode(',', $_POST['ordem_ids'] ?? ''));
        foreach ($ids as $ord => $prog_id) {
            if ($prog_id) {
                db()->prepare("UPDATE evento_programacao SET ordem=? WHERE id=? AND evento_id=?")->execute([$ord, $prog_id, $id]);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }
}

if (isset($_GET['ok'])) {
    $ok = $_GET['ok'] === 'prog' ? 'Atividade adicionada com sucesso!' : 'Salvo com sucesso!';
}

$wizard = isset($_GET['wizard']);
include dirname(__DIR__) . '/_layout.php';
?>

<?php if ($wizard): ?>
<!-- Wizard header -->
<div style="background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:20px 28px;margin-bottom:24px">
  <div style="display:flex;align-items:center;justify-content:center;gap:0;overflow-x:auto">
    <?php
    $steps = [
        ['n'=>1,'label'=>'Informações','done'=>true,'href'=>"/portal/eventos/editar.php?id=$id&wizard=1"],
        ['n'=>2,'label'=>'Pagamento','done'=>true,'href'=>"/portal/eventos/lotes.php?id=$id&wizard=1"],
        ['n'=>3,'label'=>'Formulário','done'=>true,'href'=>"/portal/inscricoes/campos.php?id=$id&wizard=1"],
        ['n'=>4,'label'=>'Página','done'=>false,'href'=>null],
    ];
    foreach ($steps as $s):
        $is_active = $s['n'] === 4;
    ?>
    <div style="display:flex;align-items:center;gap:0">
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:72px">
        <?php if ($s['href'] && $s['done']): ?>
        <a href="<?= $s['href'] ?>" style="text-decoration:none">
        <?php endif; ?>
        <div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;
          <?= $is_active ? 'background:var(--azul2);color:#fff' : ($s['done'] ? 'background:#dcfce7;color:#166534;border:2px solid #86efac' : 'background:var(--cinza2);color:var(--cinza3)') ?>">
          <?= $s['done'] && !$is_active ? '✓' : $s['n'] ?>
        </div>
        <span style="font-size:.68rem;color:<?= $is_active ? 'var(--azul2)' : ($s['done'] ? '#166534' : 'var(--cinza3)') ?>;font-weight:<?= $is_active ? '700' : '400' ?>;white-space:nowrap"><?= $s['label'] ?></span>
        <?php if ($s['href'] && $s['done']): ?></a><?php endif; ?>
      </div>
      <?php if ($s['n'] < 4): ?>
      <div style="width:32px;height:2px;background:<?= $s['done'] ? '#86efac' : 'var(--cinza2)' ?>;margin-bottom:20px"></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div style="margin-bottom:20px">
  <a href="/portal/inscricoes/configurar.php?id=<?= $id ?>" style="color:var(--cinza3);font-size:.85rem;text-decoration:none">← Configurações do evento</a>
</div>

<?php if ($ok): ?>
<div class="alerta alerta-ok" style="margin-bottom:20px"><?= htmlspecialchars($ok) ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="alerta alerta-erro" style="margin-bottom:20px"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<!-- Abas -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--cinza2);margin-bottom:24px;overflow-x:auto">
  <?php
  $aba = $_GET['aba'] ?? 'info';
  $abas = ['info'=>'Sobre & Contato','prog'=>'Programação'];
  foreach ($abas as $k=>$l):
  ?>
  <a href="?id=<?= $id ?><?= $wizard ? '&wizard=1' : '' ?>&aba=<?= $k ?>"
     style="padding:10px 20px;font-size:.88rem;font-weight:600;white-space:nowrap;border-bottom:2px solid transparent;margin-bottom:-2px;color:<?= $aba===$k ? 'var(--azul2)' : 'var(--cinza3)' ?>;border-bottom-color:<?= $aba===$k ? 'var(--azul2)' : 'transparent' ?>">
    <?= $l ?>
  </a>
  <?php endforeach; ?>
  <a href="/evento.php?id=<?= $id ?>" target="_blank"
     style="padding:10px 20px;font-size:.88rem;font-weight:600;white-space:nowrap;color:var(--verde);margin-left:auto">
    ↗ Pré-visualizar
  </a>
</div>

<?php if ($aba === 'info'): ?>
<!-- ── Sobre & Contato ── -->
<div class="form-wrap">
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="save_info">

    <div class="form-group">
      <label for="sobre">Sobre o evento <span style="font-weight:400;color:var(--cinza3)">(descrição completa exibida na página pública)</span></label>
      <textarea id="sobre" name="sobre" rows="8" style="resize:vertical" placeholder="Descreva o evento com todos os detalhes: palestrantes, proposta, o que esperar..."><?= htmlspecialchars($ev['sobre'] ?? '') ?></textarea>
      <span class="form-hint">Use quebras de linha para separar parágrafos.</span>
    </div>

    <hr style="border:none;border-top:1px solid var(--cinza2);margin:8px 0 16px">
    <p style="font-size:.82rem;color:var(--cinza3);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Contato do organizador</p>

    <div class="form-row">
      <div class="form-group">
        <label for="email_organizador">E-mail de contato <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="email" id="email_organizador" name="email_organizador"
               placeholder="contato@naiot.com.br"
               value="<?= htmlspecialchars($ev['email_organizador'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="whatsapp_contato">WhatsApp <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="tel" id="whatsapp_contato" name="whatsapp_contato"
               placeholder="(99) 99999-9999"
               value="<?= htmlspecialchars($ev['whatsapp_contato'] ?? '') ?>">
      </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--cinza2);margin:8px 0 16px">
    <p style="font-size:.82rem;color:var(--cinza3);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Para o inscrito</p>

    <div class="form-group">
      <label for="link_grupo">Link do grupo / comunidade <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <input type="url" id="link_grupo" name="link_grupo"
             placeholder="https://chat.whatsapp.com/..."
             value="<?= htmlspecialchars($ev['link_grupo'] ?? '') ?>">
      <span class="form-hint">Exibido na página do evento e ao final da inscrição.</span>
    </div>

    <div class="form-group">
      <label for="mensagem_inscrito">Mensagem para o inscrito <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
      <textarea id="mensagem_inscrito" name="mensagem_inscrito" rows="3"
                placeholder="Esta mensagem aparece na confirmação da inscrição."><?= htmlspecialchars($ev['mensagem_inscrito'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">Salvar</button>
      <?php if ($wizard): ?>
      <a href="/portal/inscricoes/evento.php?id=<?= $id ?>" class="btn btn-ghost">Concluir configuração</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php else: ?>
<!-- ── Programação ── -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start">
  <div>
    <?php if (empty($prog_list)): ?>
    <div style="background:#fff;border-radius:10px;padding:32px;text-align:center;color:var(--cinza3);box-shadow:0 1px 4px rgba(0,0,0,.06)">
      Nenhuma atividade cadastrada ainda. Adicione ao lado →
    </div>
    <?php else: ?>
    <div class="tabela-wrap">
      <div class="tabela-header"><h2>Atividades</h2></div>
      <table>
        <thead><tr><th style="width:90px">Horário</th><th>Título</th><th>Descrição</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($prog_list as $p): ?>
        <tr>
          <td style="font-weight:600;color:var(--verde);font-size:.85rem"><?= htmlspecialchars($p['horario'] ?? '—') ?></td>
          <td><strong style="font-size:.88rem"><?= htmlspecialchars($p['titulo']) ?></strong></td>
          <td style="color:var(--cinza3);font-size:.82rem"><?= $p['descricao'] ? mb_strimwidth(htmlspecialchars($p['descricao']), 0, 60, '…') : '—' ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Excluir esta atividade?')">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="acao" value="del_prog">
              <input type="hidden" name="prog_id" value="<?= $p['id'] ?>">
              <button class="btn btn-danger btn-sm">Excluir</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="form-wrap" style="max-width:none">
    <h2>Nova atividade</h2>
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao" value="add_prog">

      <div class="form-group">
        <label>Horário <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <input type="text" name="prog_horario" placeholder="Ex: 19h30 ou Sábado 14h" value="<?= htmlspecialchars($_POST['prog_horario'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Título <span style="color:var(--vermelho)">*</span></label>
        <input type="text" name="prog_titulo" placeholder="Ex: Missa de abertura" required value="<?= htmlspecialchars($_POST['prog_titulo'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Descrição <span style="font-weight:400;color:var(--cinza3)">(opcional)</span></label>
        <textarea name="prog_descricao" rows="2" placeholder="Detalhes da atividade..."><?= htmlspecialchars($_POST['prog_descricao'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label>Ordem <span style="font-weight:400;color:var(--cinza3)">(menor = primeiro)</span></label>
        <input type="number" name="prog_ordem" min="0" value="<?= htmlspecialchars($_POST['prog_ordem'] ?? count($prog_list)) ?>">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%">Adicionar atividade</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
