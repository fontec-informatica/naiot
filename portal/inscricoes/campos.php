<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$evento_id = (int)($_GET['id'] ?? 0);
if (!$evento_id) { header('Location: /portal/inscricoes/'); exit; }

$ev_stmt = db()->prepare('SELECT * FROM eventos WHERE id = ?');
$ev_stmt->execute([$evento_id]);
$evento = $ev_stmt->fetch();
if (!$evento) { header('Location: /portal/inscricoes/'); exit; }

$titulo       = 'Campos do Formulário — ' . $evento['titulo'];
$pagina_ativa = 'inscricoes';
$ok   = '';
$erro = '';

/* ── Campos padrão do sistema ── */
$CAMPOS_PADRAO = [
    ['campo'=>'telefone',       'label'=>'Telefone / WhatsApp',  'tipo'=>'tel',      'obrigatorio'=>1, 'ativo'=>1],
    ['campo'=>'cpf',            'label'=>'CPF / Documento',      'tipo'=>'text',     'obrigatorio'=>0, 'ativo'=>1],
    ['campo'=>'data_nascimento','label'=>'Data de Nascimento',   'tipo'=>'date',     'obrigatorio'=>0, 'ativo'=>1],
    ['campo'=>'cidade',         'label'=>'Cidade',               'tipo'=>'text',     'obrigatorio'=>0, 'ativo'=>0],
    ['campo'=>'estado',         'label'=>'Estado',               'tipo'=>'text',     'obrigatorio'=>0, 'ativo'=>0],
    ['campo'=>'sexo',           'label'=>'Sexo',                 'tipo'=>'select',   'obrigatorio'=>0, 'ativo'=>0],
    ['campo'=>'cep',            'label'=>'CEP',                  'tipo'=>'text',     'obrigatorio'=>0, 'ativo'=>0],
    ['campo'=>'nome_parceiro',  'label'=>'Nome do Parceiro(a)',  'tipo'=>'text',     'obrigatorio'=>0, 'ativo'=>0],
    ['campo'=>'observacoes',    'label'=>'Observações',          'tipo'=>'textarea', 'obrigatorio'=>0, 'ativo'=>1],
];

/* ── Inicializar campos padrão se não existirem ── */
function inicializar_campos(int $evento_id, array $padrao): void {
    try {
        foreach ($padrao as $ord => $d) {
            db()->prepare("INSERT IGNORE INTO evento_campos (evento_id, campo, label, tipo, obrigatorio, ativo, ordem) VALUES (?,?,?,?,?,?,?)")
                ->execute([$evento_id, $d['campo'], $d['label'], $d['tipo'], $d['obrigatorio'], $d['ativo'], $ord]);
        }
    } catch (Exception $e) {}
}

/* ── Carregar campos ── */
function carregar_campos(int $evento_id): array {
    try {
        $s = db()->prepare("SELECT * FROM evento_campos WHERE evento_id = ? ORDER BY ordem ASC, id ASC");
        $s->execute([$evento_id]);
        return $s->fetchAll();
    } catch (Exception $e) { return []; }
}

inicializar_campos($evento_id, $CAMPOS_PADRAO);
$campos = carregar_campos($evento_id);

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'save_campos') {
        try {
            foreach ($campos as $c) {
                $ativo      = isset($_POST['ativo_'    . $c['id']]) ? 1 : 0;
                $obrigatorio = isset($_POST['obrig_'   . $c['id']]) ? 1 : 0;
                db()->prepare("UPDATE evento_campos SET ativo=?, obrigatorio=? WHERE id=? AND evento_id=?")
                    ->execute([$ativo, $obrigatorio, $c['id'], $evento_id]);
            }
            $campos = carregar_campos($evento_id);
            $ok = 'Campos salvos com sucesso!';
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }

    } elseif ($acao === 'add_campo') {
        $label = trim($_POST['novo_label'] ?? '');
        $tipo  = $_POST['novo_tipo'] ?? 'text';
        $tipos_validos = ['text','tel','date','select','textarea','checkbox'];
        if (!in_array($tipo, $tipos_validos, true)) $tipo = 'text';
        $opcoes = trim($_POST['novo_opcoes'] ?? '');
        $ordem  = count($campos);
        $campo_key = 'custom_' . uniqid();

        if (!$label) {
            $erro = 'Informe o rótulo do campo.';
        } else {
            try {
                db()->prepare("INSERT INTO evento_campos (evento_id, campo, label, tipo, obrigatorio, ativo, opcoes, ordem) VALUES (?,?,?,?,0,1,?,?)")
                    ->execute([$evento_id, $campo_key, $label, $tipo, $opcoes ?: null, $ordem]);
                header("Location: /portal/inscricoes/campos.php?id=$evento_id" . (isset($_GET['wizard']) ? '&wizard=1' : '') . "&ok=1");
                exit;
            } catch (Exception $e) {
                $erro = 'Erro: ' . $e->getMessage();
            }
        }

    } elseif ($acao === 'del_campo') {
        $campo_id = (int)($_POST['campo_id'] ?? 0);
        if ($campo_id) {
            try {
                db()->prepare("DELETE FROM evento_campos WHERE id=? AND evento_id=? AND campo LIKE 'custom_%'")->execute([$campo_id, $evento_id]);
            } catch (Exception $e) {}
        }
        header("Location: /portal/inscricoes/campos.php?id=$evento_id" . (isset($_GET['wizard']) ? '&wizard=1' : ''));
        exit;
    }
}

if (isset($_GET['ok'])) $ok = 'Campo adicionado com sucesso!';

$wizard = isset($_GET['wizard']);
include dirname(__DIR__) . '/_layout.php';
?>

<?php if ($wizard): ?>
<!-- Wizard header -->
<div style="background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:20px 28px;margin-bottom:24px">
  <div style="display:flex;align-items:center;justify-content:center;gap:0;overflow-x:auto">
    <?php
    $steps = [
        ['n'=>1,'label'=>'Informações','done'=>true,'href'=>"/portal/eventos/editar.php?id=$evento_id&wizard=1"],
        ['n'=>2,'label'=>'Pagamento','done'=>true,'href'=>"/portal/eventos/lotes.php?id=$evento_id&wizard=1"],
        ['n'=>3,'label'=>'Formulário','done'=>false,'href'=>null],
        ['n'=>4,'label'=>'Página','done'=>false,'href'=>null],
    ];
    foreach ($steps as $s):
        $is_active = $s['n'] === 3;
    ?>
    <div style="display:flex;align-items:center;gap:0">
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:72px">
        <?php if ($s['href'] && $s['done']): ?><a href="<?= $s['href'] ?>" style="text-decoration:none"><?php endif; ?>
        <div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;<?= $is_active ? 'background:var(--azul2);color:#fff' : ($s['done'] ? 'background:#dcfce7;color:#166534;border:2px solid #86efac' : 'background:var(--cinza2);color:var(--cinza3)') ?>">
          <?= $s['done'] && !$is_active ? '✓' : $s['n'] ?>
        </div>
        <span style="font-size:.68rem;color:<?= $is_active ? 'var(--azul2)' : ($s['done'] ? '#166534' : 'var(--cinza3)') ?>;font-weight:<?= $is_active ? '700' : '400' ?>;white-space:nowrap"><?= $s['label'] ?></span>
        <?php if ($s['href'] && $s['done']): ?></a><?php endif; ?>
      </div>
      <?php if ($s['n'] < 4): ?><div style="width:32px;height:2px;background:<?= $s['done'] ? '#86efac' : 'var(--cinza2)' ?>;margin-bottom:20px"></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div style="margin-bottom:20px">
  <h2 style="font-size:1rem;font-weight:600"><?= htmlspecialchars($evento['titulo']) ?></h2>
  <a href="/portal/inscricoes/configurar.php?id=<?= $evento_id ?>" style="font-size:.82rem;color:var(--cinza3)">← Configurações do evento</a>
</div>

<?php if ($ok): ?><div class="alerta alerta-ok" style="margin-bottom:20px"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($erro): ?><div class="alerta alerta-erro" style="margin-bottom:20px"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start">

<!-- ── Tabela de campos ── -->
<div>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="save_campos">

    <div class="tabela-wrap">
      <div class="tabela-header">
        <h2>Campos do formulário</h2>
      </div>
      <table>
        <thead>
          <tr>
            <th>Campo</th>
            <th style="text-align:center">Obrigatório</th>
            <th style="text-align:center">Ativo</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <!-- Campos fixos (sempre presentes) -->
          <tr style="background:var(--cinza1)">
            <td>
              <strong>Nome completo</strong>
              <div style="font-size:.75rem;color:var(--cinza3)">Obrigatório em todos os eventos</div>
            </td>
            <td style="text-align:center"><span style="color:var(--verde)">✓</span></td>
            <td style="text-align:center"><span style="color:var(--verde)">✓</span></td>
            <td></td>
          </tr>
          <tr style="background:var(--cinza1)">
            <td>
              <strong>E-mail</strong>
              <div style="font-size:.75rem;color:var(--cinza3)">Obrigatório em todos os eventos</div>
            </td>
            <td style="text-align:center"><span style="color:var(--verde)">✓</span></td>
            <td style="text-align:center"><span style="color:var(--verde)">✓</span></td>
            <td></td>
          </tr>

          <!-- Campos configuráveis -->
          <?php foreach ($campos as $c): ?>
          <tr>
            <td>
              <strong style="font-size:.88rem"><?= htmlspecialchars($c['label']) ?></strong>
              <div style="font-size:.74rem;color:var(--cinza3)"><?= htmlspecialchars($c['tipo']) ?></div>
            </td>
            <td style="text-align:center">
              <input type="checkbox" name="obrig_<?= $c['id'] ?>" value="1"
                     <?= $c['obrigatorio'] ? 'checked' : '' ?>
                     <?= !$c['ativo'] ? 'disabled' : '' ?>
                     style="accent-color:var(--azul2);width:16px;height:16px">
            </td>
            <td style="text-align:center">
              <label style="cursor:pointer;display:inline-flex;align-items:center;gap:6px">
                <input type="checkbox" name="ativo_<?= $c['id'] ?>" value="1"
                       <?= $c['ativo'] ? 'checked' : '' ?>
                       onchange="toggleObrig(this, <?= $c['id'] ?>)"
                       style="accent-color:var(--verde);width:16px;height:16px">
              </label>
            </td>
            <td>
              <?php if (str_starts_with($c['campo'], 'custom_')): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Excluir campo?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="acao" value="del_campo">
                <input type="hidden" name="campo_id" value="<?= $c['id'] ?>">
                <button class="btn btn-danger btn-sm">✕</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="display:flex;gap:12px;margin-top:16px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">Salvar campos</button>
      <?php if ($wizard): ?>
      <a href="/portal/eventos/pagina.php?id=<?= $evento_id ?>&wizard=1" class="btn btn-ghost">Próximo passo →</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ── Adicionar campo ── -->
<div class="form-wrap" style="max-width:none">
  <h2>Adicionar campo</h2>
  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="acao" value="add_campo">

    <div class="form-group">
      <label>Rótulo do campo <span style="color:var(--vermelho)">*</span></label>
      <input type="text" name="novo_label" placeholder="Ex: Igreja de origem" required value="<?= htmlspecialchars($_POST['novo_label'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label>Tipo de campo</label>
      <select name="novo_tipo">
        <option value="text">Texto</option>
        <option value="tel">Telefone</option>
        <option value="date">Data</option>
        <option value="select">Lista de opções</option>
        <option value="textarea">Texto longo</option>
        <option value="checkbox">Checkbox (aceite)</option>
      </select>
    </div>

    <div class="form-group">
      <label>Opções <span style="font-weight:400;color:var(--cinza3)">(para lista de opções, separar por vírgula)</span></label>
      <textarea name="novo_opcoes" rows="2" placeholder="Opção 1, Opção 2, Opção 3"><?= htmlspecialchars($_POST['novo_opcoes'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn-ouro" style="width:100%">Adicionar campo</button>
  </form>
</div>

</div>

<script>
function toggleObrig(checkbox, id) {
  const obrig = document.querySelector('input[name="obrig_' + id + '"]');
  if (obrig) obrig.disabled = !checkbox.checked;
}
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
