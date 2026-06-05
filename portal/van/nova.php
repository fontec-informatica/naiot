<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$id = (int)($_GET['id'] ?? 0);
$viagem     = null;
$passageiros_db = [];
$titulo     = 'Nova Viagem de Van';
$pagina_ativa = 'van';

if ($id) {
    $st = db()->prepare("SELECT * FROM van_viagens WHERE id=?");
    $st->execute([$id]);
    $viagem = $st->fetch();
    if (!$viagem) { header('Location: /portal/van/'); exit; }
    $titulo = 'Editar Viagem';
    $pst = db()->prepare("SELECT * FROM van_passageiros WHERE viagem_id=? ORDER BY ordem");
    $pst->execute([$id]);
    $passageiros_db = $pst->fetchAll();
}

// Motoristas (membros com habilidade "motorista")
try {
    $motoristas = db()->query("
        SELECT m.id, m.nome, m.cpf
        FROM membros m
        JOIN membros_habilidade_rel r ON r.membro_id = m.id
        JOIN membros_habilidades h ON h.id = r.habilidade_id
        WHERE m.ativo = 1 AND LOWER(h.nome) LIKE '%motorista%'
        ORDER BY m.nome
    ")->fetchAll();
} catch (PDOException $e) {
    $motoristas = [];
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $destino        = trim($_POST['destino'] ?? '');
    $data_texto     = trim($_POST['data_texto'] ?? '');
    $motorista_tipo = $_POST['motorista_tipo'] ?? 'membro';
    $motorista_id   = (int)($_POST['motorista_id'] ?? 0) ?: null;
    $motorista_nome = trim($_POST['motorista_nome'] ?? '');
    $motorista_cpf  = trim($_POST['motorista_cpf'] ?? '');
    $status         = in_array($_POST['status'] ?? '', ['rascunho','finalizada']) ? $_POST['status'] : 'rascunho';
    $pass_json      = $_POST['passageiros_json'] ?? '[]';
    $pass_arr       = json_decode($pass_json, true) ?: [];
    $acao           = $_POST['acao'] ?? 'salvar';

    if (!$destino)    $erros[] = 'Destino é obrigatório.';
    if (!$data_texto) $erros[] = 'Data é obrigatória.';

    // Preenche motorista a partir do membro selecionado
    if ($motorista_tipo === 'membro' && $motorista_id) {
        try {
            $mot = db()->prepare("SELECT nome, cpf FROM membros WHERE id=?");
            $mot->execute([$motorista_id]);
            $mot = $mot->fetch();
            if ($mot) {
                $motorista_nome = $mot['nome'];
                $motorista_cpf  = $mot['cpf'] ?? '';
            }
        } catch (PDOException $e) {}
    }

    if (!$erros) {
        $pdo = db();
        if (!$id) {
            $pdo->prepare("
                INSERT INTO van_viagens (destino, data_texto, motorista_id, motorista_nome, motorista_cpf, status)
                VALUES (?,?,?,?,?,?)
            ")->execute([$destino, $data_texto, $motorista_id, $motorista_nome ?: null, $motorista_cpf ?: null, $status]);
            $id = (int)$pdo->lastInsertId();
        } else {
            $pdo->prepare("
                UPDATE van_viagens SET destino=?, data_texto=?, motorista_id=?, motorista_nome=?, motorista_cpf=?, status=? WHERE id=?
            ")->execute([$destino, $data_texto, $motorista_id, $motorista_nome ?: null, $motorista_cpf ?: null, $status, $id]);
        }

        $pdo->prepare("DELETE FROM van_passageiros WHERE viagem_id=?")->execute([$id]);
        $ins = $pdo->prepare("INSERT INTO van_passageiros (viagem_id, ordem, membro_id, nome, cpf_rg) VALUES (?,?,?,?,?)");
        foreach ($pass_arr as $i => $p) {
            $pnome = trim($p['nome'] ?? '');
            if (!$pnome) continue;
            $pmid  = !empty($p['membro_id']) ? (int)$p['membro_id'] : null;
            $pcpf  = trim($p['cpf_rg'] ?? '');
            $ins->execute([$id, $i, $pmid, $pnome, $pcpf ?: null]);
        }

        if ($acao === 'imprimir') {
            header("Location: /portal/van/imprimir.php?id=$id");
        } else {
            header("Location: /portal/van/nova.php?id=$id&ok=1");
        }
        exit;
    }
}

// Passageiros existentes → JSON para o JS
$pass_inicial = array_map(fn($p) => [
    'membro_id' => $p['membro_id'],
    'nome'      => $p['nome'],
    'cpf_rg'    => $p['cpf_rg'] ?? '',
], $passageiros_db);

include dirname(__DIR__) . '/_layout.php';
?>

<style>
.van-section { background:var(--off);border:1.5px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px }
.van-section h3 { margin:0 0 16px;font-size:.95rem;font-weight:700;color:var(--green-dk) }

.pass-lista { list-style:none;margin:0;padding:0 }
.pass-item  { display:grid;grid-template-columns:28px 1fr auto;gap:8px;align-items:center;
              padding:8px 10px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px;
              background:#fff;min-width:0 }
.pass-num   { font-weight:700;color:var(--muted);font-size:.85rem;text-align:center }
.pass-info  { min-width:0 }
.pass-nome  { font-weight:600;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis }
.pass-cpf   { font-size:.75rem;color:var(--muted) }

.search-wrap { position:relative }
.search-drop { position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:99;
               background:#fff;border:1.5px solid var(--border);border-radius:8px;
               box-shadow:0 4px 16px rgba(0,0,0,.1);max-height:260px;overflow-y:auto;display:none }
.search-drop.aberto { display:block }
.search-item { padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border) }
.search-item:last-child { border-bottom:none }
.search-item:hover { background:var(--green-lt) }
.search-item strong { display:block;font-size:.88rem }
.search-item span   { font-size:.75rem;color:var(--muted) }

.toggle-tipo { display:flex;gap:0;border:1.5px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:14px }
.toggle-tipo label { flex:1;text-align:center;padding:8px;font-size:.83rem;font-weight:600;cursor:pointer;
                     color:var(--muted);background:#fff;transition:background .15s,color .15s }
.toggle-tipo input[type=radio] { display:none }
.toggle-tipo input:checked + span { background:var(--green-dk);color:#fff }
.toggle-tipo label:has(input:checked) { background:var(--green-dk);color:#fff }

.manual-add { border:1.5px dashed var(--border);border-radius:8px;padding:14px;margin-top:10px;display:none }
.manual-add.aberto { display:block }

@media (max-width:600px) {
  .pass-item { grid-template-columns:24px 1fr auto }
}
</style>

<div style="max-width:780px">

  <div style="margin-bottom:16px">
    <a href="/portal/van/" class="btn btn-ghost btn-sm">← Voltar para viagens</a>
  </div>

  <?php if ($erros): ?>
    <div class="alerta alerta-erro"><?= implode('<br>', array_map('htmlspecialchars', $erros)) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['ok'])): ?>
    <div class="alerta alerta-ok">Viagem salva. <a href="/portal/van/imprimir.php?id=<?= $id ?>" target="_blank">Abrir documento para impressão</a></div>
  <?php endif; ?>

  <form method="post" id="formViagem">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="passageiros_json" id="passageirosJson" value="<?= htmlspecialchars(json_encode($pass_inicial)) ?>">
    <input type="hidden" name="status" id="inputStatus" value="<?= htmlspecialchars($viagem['status'] ?? 'rascunho') ?>">
    <input type="hidden" name="acao" id="inputAcao" value="salvar">

    <!-- Destino + Data -->
    <div class="van-section">
      <h3>Dados da viagem</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;min-width:0">
        <div class="form-group" style="margin:0">
          <label>Destino <span style="color:var(--red)">*</span></label>
          <input type="text" name="destino" required maxlength="200"
            placeholder="Ex.: GURUPI - TO"
            value="<?= htmlspecialchars($viagem['destino'] ?? '') ?>">
        </div>
        <div class="form-group" style="margin:0">
          <label>Data <span style="color:var(--red)">*</span></label>
          <input type="text" name="data_texto" required maxlength="100"
            placeholder="Ex.: 05-06-07/06/2026"
            value="<?= htmlspecialchars($viagem['data_texto'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- Motorista -->
    <div class="van-section">
      <h3>Motorista (posição 1)</h3>

      <div class="toggle-tipo" id="toggleMotorista">
        <label>
          <input type="radio" name="motorista_tipo" value="membro" <?= (!$viagem || $viagem['motorista_id']) ? 'checked' : '' ?>>
          <span>Membro da comunidade</span>
        </label>
        <label>
          <input type="radio" name="motorista_tipo" value="externo" <?= ($viagem && !$viagem['motorista_id'] && $viagem['motorista_nome']) ? 'checked' : '' ?>>
          <span>Não é membro</span>
        </label>
      </div>

      <div id="secaoMotMembro">
        <div class="form-group" style="margin:0">
          <label>Selecionar motorista</label>
          <select name="motorista_id" id="selectMotorista" style="width:100%">
            <option value="">— Selecione —</option>
            <?php foreach ($motoristas as $m): ?>
              <option value="<?= $m['id'] ?>"
                data-cpf="<?= htmlspecialchars($m['cpf'] ?? '') ?>"
                <?= ($viagem && $viagem['motorista_id'] == $m['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($m['nome']) ?>
                <?php if ($m['cpf']): ?>— CPF: <?= htmlspecialchars($m['cpf']) ?><?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($motoristas)): ?>
            <small style="color:var(--muted)">Nenhum membro com habilidade "Motorista" cadastrado.
              <a href="/portal/membros/habilidades.php">Cadastrar habilidade</a></small>
          <?php endif; ?>
        </div>
      </div>

      <div id="secaoMotExterno" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;min-width:0">
          <div class="form-group" style="margin:0">
            <label>Nome completo</label>
            <input type="text" name="motorista_nome" maxlength="150"
              placeholder="Nome do motorista"
              value="<?= htmlspecialchars(($viagem && !$viagem['motorista_id']) ? ($viagem['motorista_nome'] ?? '') : '') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label>CPF</label>
            <input type="text" name="motorista_cpf" maxlength="20"
              placeholder="000.000.000-00"
              value="<?= htmlspecialchars(($viagem && !$viagem['motorista_id']) ? ($viagem['motorista_cpf'] ?? '') : '') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- Passageiros -->
    <div class="van-section">
      <h3>Passageiros (posições 2 a 20)</h3>

      <div class="search-wrap" style="margin-bottom:12px">
        <input type="text" id="buscaPassageiro" placeholder="Buscar membro por nome, CPF ou telefone…"
          autocomplete="off" style="width:100%;box-sizing:border-box">
        <div class="search-drop" id="searchDrop"></div>
      </div>

      <button type="button" class="btn btn-ghost btn-sm" id="btnManual" style="margin-bottom:12px">
        + Adicionar pessoa sem cadastro
      </button>

      <div class="manual-add" id="manualAdd">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;min-width:0;margin-bottom:10px">
          <div class="form-group" style="margin:0">
            <label>Nome completo</label>
            <input type="text" id="manualNome" maxlength="150" placeholder="Nome do passageiro">
          </div>
          <div class="form-group" style="margin:0">
            <label>RG / CPF <span style="font-weight:400;color:var(--muted)">(opcional)</span></label>
            <input type="text" id="manualCpf" maxlength="30" placeholder="000.000.000-00">
          </div>
        </div>
        <button type="button" class="btn btn-primary btn-sm" id="btnAdicionarManual">Adicionar</button>
        <button type="button" class="btn btn-ghost btn-sm" id="btnCancelarManual">Cancelar</button>
      </div>

      <div id="listaAviso" style="font-size:.8rem;color:var(--muted);margin-bottom:8px;display:none">
        Limite de 19 passageiros atingido.
      </div>

      <ul class="pass-lista" id="passLista"></ul>

      <div id="passVazio" style="padding:20px;text-align:center;color:var(--muted);font-size:.85rem;border:1.5px dashed var(--border);border-radius:8px">
        Nenhum passageiro adicionado ainda.
      </div>
    </div>

    <!-- Ações -->
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button type="submit" class="btn btn-ghost" onclick="setAcao('salvar','rascunho')">Salvar rascunho</button>
      <button type="submit" class="btn btn-primary" onclick="setAcao('salvar','finalizada')">Salvar e finalizar</button>
      <button type="submit" class="btn btn-primary" onclick="setAcao('imprimir','finalizada')"
        style="background:var(--green-dk);border-color:var(--green-dk)">
        Salvar e imprimir documento
      </button>
    </div>
  </form>
</div>

<script>
(function(){

const MAX_PASS = 19;
let passengers = <?= json_encode($pass_inicial) ?>;

const $lista    = document.getElementById('passLista');
const $vazio    = document.getElementById('passVazio');
const $aviso    = document.getElementById('listaAviso');
const $json     = document.getElementById('passageirosJson');
const $busca    = document.getElementById('buscaPassageiro');
const $drop     = document.getElementById('searchDrop');
const $manual   = document.getElementById('manualAdd');
const $btnMan   = document.getElementById('btnManual');

function esc(s){ return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }

function render(){
  $json.value = JSON.stringify(passengers);
  $lista.innerHTML = '';
  passengers.forEach(function(p,i){
    const li = document.createElement('li');
    li.className = 'pass-item';
    li.innerHTML =
      '<span class="pass-num">'+(i+2)+'</span>' +
      '<div class="pass-info">' +
        '<div class="pass-nome">'+esc(p.nome)+'</div>' +
        (p.cpf_rg ? '<div class="pass-cpf">'+esc(p.cpf_rg)+'</div>' : '') +
      '</div>' +
      '<button type="button" title="Remover" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:1.1rem;padding:4px 6px" data-idx="'+i+'">✕</button>';
    $lista.appendChild(li);
  });
  $vazio.style.display = passengers.length ? 'none' : '';
  $aviso.style.display = passengers.length >= MAX_PASS ? '' : 'none';
  $busca.disabled = passengers.length >= MAX_PASS;
}

$lista.addEventListener('click', function(e){
  const btn = e.target.closest('[data-idx]');
  if(!btn) return;
  passengers.splice(parseInt(btn.dataset.idx), 1);
  render();
});

function addPassenger(p){
  if(passengers.length >= MAX_PASS) return;
  passengers.push(p);
  render();
  $drop.classList.remove('aberto');
  $busca.value = '';
}

// Busca autocomplete
let searchTimer;
$busca.addEventListener('input', function(){
  clearTimeout(searchTimer);
  const q = this.value.trim();
  if(q.length < 2){ $drop.classList.remove('aberto'); return; }
  searchTimer = setTimeout(function(){
    fetch('/portal/van/buscar.php?q='+encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if(!data.length){ $drop.classList.remove('aberto'); return; }
        $drop.innerHTML = data.map(function(m){
          return '<div class="search-item" data-id="'+m.id+'" data-nome="'+esc(m.nome)+'" data-cpf="'+esc(m.cpf||'')+'">' +
            '<strong>'+esc(m.nome)+'</strong>' +
            '<span>'+(m.cpf ? 'CPF: '+esc(m.cpf) : (m.telefone ? 'Tel: '+esc(m.telefone) : ''))+'</span>' +
            '</div>';
        }).join('');
        $drop.classList.add('aberto');
      });
  }, 280);
});

$drop.addEventListener('click', function(e){
  const item = e.target.closest('.search-item');
  if(!item) return;
  addPassenger({
    membro_id: parseInt(item.dataset.id),
    nome:      item.dataset.nome,
    cpf_rg:    item.dataset.cpf || ''
  });
});

document.addEventListener('click', function(e){
  if(!$drop.contains(e.target) && e.target !== $busca) $drop.classList.remove('aberto');
});

// Adicionar manual
$btnMan.addEventListener('click', function(){
  $manual.classList.toggle('aberto');
  if($manual.classList.contains('aberto')) document.getElementById('manualNome').focus();
});
document.getElementById('btnCancelarManual').addEventListener('click', function(){
  $manual.classList.remove('aberto');
  document.getElementById('manualNome').value = '';
  document.getElementById('manualCpf').value = '';
});
document.getElementById('btnAdicionarManual').addEventListener('click', function(){
  const nome = document.getElementById('manualNome').value.trim();
  if(!nome){ document.getElementById('manualNome').focus(); return; }
  addPassenger({ membro_id: null, nome: nome, cpf_rg: document.getElementById('manualCpf').value.trim() });
  document.getElementById('manualNome').value = '';
  document.getElementById('manualCpf').value = '';
  $manual.classList.remove('aberto');
});

// Toggle motorista membro/externo
const radios = document.querySelectorAll('[name=motorista_tipo]');
function syncMotorista(){
  const val = document.querySelector('[name=motorista_tipo]:checked')?.value;
  document.getElementById('secaoMotMembro').style.display  = val === 'membro'   ? '' : 'none';
  document.getElementById('secaoMotExterno').style.display = val === 'externo'  ? '' : 'none';
}
radios.forEach(function(r){ r.addEventListener('change', syncMotorista); });
syncMotorista();

render();

})();

function setAcao(acao, status){
  document.getElementById('inputAcao').value   = acao;
  document.getElementById('inputStatus').value = status;
}
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
