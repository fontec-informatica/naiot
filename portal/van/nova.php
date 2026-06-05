<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$id = (int)($_GET['id'] ?? 0);
$viagem      = null;
$pass_db     = [];
$titulo      = 'Nova Viagem de Van';
$pagina_ativa= 'van';

if ($id) {
    $st = db()->prepare("SELECT * FROM van_viagens WHERE id=?");
    $st->execute([$id]);
    $viagem = $st->fetch();
    if (!$viagem) { header('Location: /portal/van/'); exit; }
    $titulo = 'Editar Viagem';
    $pst = db()->prepare("SELECT * FROM van_passageiros WHERE viagem_id=? ORDER BY ordem");
    $pst->execute([$id]);
    $pass_db = $pst->fetchAll();
}

// Membros motoristas
try {
    $motoristas = db()->query("
        SELECT m.id, m.nome, m.cpf
        FROM membros m
        JOIN membros_habilidade_rel r ON r.membro_id = m.id
        JOIN membros_habilidades h ON h.id = r.habilidade_id
        WHERE m.ativo = 1 AND LOWER(h.nome) LIKE '%motorista%'
        ORDER BY m.nome
    ")->fetchAll();
} catch (PDOException $e) { $motoristas = []; }


$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $destino         = trim($_POST['destino']         ?? '');
    $data_texto      = trim($_POST['data_texto']      ?? '');
    $data_tipo       = in_array($_POST['data_tipo'] ?? '', ['unico','bate_volta','periodo','livre']) ? $_POST['data_tipo'] : 'livre';
    $mot_tipo        = $_POST['motorista_tipo']        ?? 'membro';
    $mot_id          = (int)($_POST['motorista_id']   ?? 0) ?: null;
    $mot_nome        = trim($_POST['motorista_nome']  ?? '');
    $mot_cpf         = trim($_POST['motorista_cpf']   ?? '');
    $coord_id        = (int)($_POST['coordenador_id']   ?? 0) ?: null;
    $coord_nome      = trim($_POST['coordenador_nome'] ?? '');
    $coord_cpf       = trim($_POST['coordenador_cpf']  ?? '');
    $status          = in_array($_POST['status'] ?? '', ['rascunho','finalizada']) ? $_POST['status'] : 'rascunho';
    $pass_json       = $_POST['passageiros_json'] ?? '[]';
    $pass_arr        = json_decode($pass_json, true) ?: [];
    $acao            = $_POST['acao'] ?? 'salvar';

    if (!$destino)    $erros[] = 'Destino é obrigatório.';
    if (!$data_texto) $erros[] = 'Data é obrigatória.';

    // Preenche motorista a partir do membro
    if ($mot_tipo === 'membro' && $mot_id) {
        try {
            $row = db()->prepare("SELECT nome, cpf FROM membros WHERE id=?");
            $row->execute([$mot_id]);
            $row = $row->fetch();
            if ($row) { $mot_nome = $row['nome']; $mot_cpf = $row['cpf'] ?? ''; }
        } catch (PDOException $e) {}
    }


    if (!$erros) {
        $pdo = db();
        if (!$id) {
            $pdo->prepare("
                INSERT INTO van_viagens
                  (destino,data_texto,data_tipo,motorista_id,motorista_nome,motorista_cpf,
                   coordenador_id,coordenador_nome,coordenador_cpf,status)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ")->execute([$destino,$data_texto,$data_tipo,
                         $mot_id,$mot_nome?:null,$mot_cpf?:null,
                         $coord_id?:null,$coord_nome?:null,$coord_cpf?:null,$status]);
            $id = (int)$pdo->lastInsertId();
        } else {
            $pdo->prepare("
                UPDATE van_viagens SET
                  destino=?,data_texto=?,data_tipo=?,
                  motorista_id=?,motorista_nome=?,motorista_cpf=?,
                  coordenador_id=?,coordenador_nome=?,coordenador_cpf=?,status=?
                WHERE id=?
            ")->execute([$destino,$data_texto,$data_tipo,
                         $mot_id,$mot_nome?:null,$mot_cpf?:null,
                         $coord_id?:null,$coord_nome?:null,$coord_cpf?:null,$status,$id]);
        }

        // Salvar passageiros
        $pdo->prepare("DELETE FROM van_passageiros WHERE viagem_id=?")->execute([$id]);
        $ins = $pdo->prepare("INSERT INTO van_passageiros (viagem_id,ordem,membro_id,nome,cpf_rg,nota,tipo) VALUES (?,?,?,?,?,?,?)");
        foreach ($pass_arr as $i => $p) {
            $pnome = trim($p['nome'] ?? '');
            if (!$pnome) continue;
            $pmid  = !empty($p['membro_id']) ? (int)$p['membro_id'] : null;
            $pcpf  = trim($p['cpf_rg'] ?? '');
            $pnota = trim($p['nota']   ?? '');
            $ptipo = in_array($p['tipo'] ?? '', ['normal','cadeirinha','colo']) ? $p['tipo'] : 'normal';
            $ins->execute([$id,$i,$pmid,$pnome,$pcpf?:null,$pnota?:null,$ptipo]);
        }

        if ($acao === 'imprimir') {
            header("Location: /portal/van/imprimir.php?id=$id");
        } else {
            header("Location: /portal/van/nova.php?id=$id&ok=1");
        }
        exit;
    }
}

// Passageiros iniciais para o JS
$pass_inicial = array_map(fn($p) => [
    'membro_id' => $p['membro_id'],
    'nome'      => $p['nome'],
    'cpf_rg'    => $p['cpf_rg'] ?? '',
    'nota'      => $p['nota']   ?? '',
    'tipo'      => $p['tipo']   ?? 'normal',
], $pass_db);

include dirname(__DIR__) . '/_layout.php';
?>

<style>
.van-card { background:var(--off);border:1.5px solid var(--border);border-radius:12px;padding:20px;margin-bottom:18px }
.van-card h3 { margin:0 0 16px;font-size:.93rem;font-weight:700;color:var(--green-dk);display:flex;align-items:center;gap:8px }

/* Toggle membro/externo */
.tog { display:flex;border:1.5px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:14px;max-width:340px }
.tog label { flex:1;text-align:center;padding:7px 10px;font-size:.82rem;font-weight:600;cursor:pointer;color:var(--muted);background:#fff;transition:background .12s,color .12s;user-select:none }
.tog input[type=radio] { display:none }
.tog label:has(input:checked) { background:var(--green-dk);color:#fff }

/* Data tipo pills */
.dtipo { display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px }
.dtipo label { padding:6px 14px;border-radius:20px;border:1.5px solid var(--border);font-size:.82rem;font-weight:600;cursor:pointer;color:var(--muted);background:#fff;transition:background .12s,color .12s,border-color .12s;user-select:none }
.dtipo input[type=radio] { display:none }
.dtipo label:has(input:checked) { background:var(--green-dk);color:#fff;border-color:var(--green-dk) }

/* Autocomplete destino */
.dest-wrap { position:relative }
.dest-drop { position:absolute;top:calc(100% + 3px);left:0;right:0;z-index:99;
             background:#fff;border:1.5px solid var(--border);border-radius:8px;
             box-shadow:0 4px 16px rgba(0,0,0,.1);max-height:220px;overflow-y:auto;display:none }
.dest-drop.aberto { display:block }
.dest-item { padding:8px 12px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;font-size:.87rem;border-bottom:1px solid var(--border) }
.dest-item:last-child { border-bottom:none }
.dest-item:hover,.dest-item.ativo { background:var(--green-lt) }
.dest-item .d-uf { font-size:.75rem;color:var(--muted);font-weight:600 }

/* Passageiros */
.pass-lista { list-style:none;margin:0;padding:0 }
.pass-item  { display:grid;grid-template-columns:28px 1fr auto;gap:8px;align-items:start;
              padding:10px 10px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px;
              background:#fff;min-width:0 }
.pass-num   { font-weight:700;color:var(--muted);font-size:.85rem;text-align:center;padding-top:6px }
.pass-info  { min-width:0 }
.pass-nome  { font-weight:600;font-size:.88rem;margin-bottom:4px }
.pass-inputs{ display:grid;grid-template-columns:1fr 1fr;gap:6px }
.pass-inputs input { padding:4px 8px;border:1px solid var(--border);border-radius:6px;font-size:.78rem;font-family:inherit;background:var(--off);width:100%;box-sizing:border-box }
.pass-inputs input:focus { outline:none;border-color:var(--green-dk);background:#fff }
.pass-rem   { background:none;border:none;cursor:pointer;color:var(--muted);font-size:1rem;padding:4px 6px;line-height:1;align-self:flex-start;margin-top:4px }
.pass-rem:hover { color:var(--red) }

/* Tipo de passageiro */
.tipo-pills { display:flex;gap:4px;margin-bottom:6px;flex-wrap:wrap }
.tipo-pill  { padding:2px 9px;border-radius:20px;border:1.5px solid var(--border);font-size:.72rem;font-weight:600;cursor:pointer;color:var(--muted);background:#fff;line-height:1.5;user-select:none;transition:background .1s,color .1s,border-color .1s }
.tipo-pill:hover { border-color:var(--green-dk);color:var(--green-dk) }
.tipo-pill.ativo[data-tipo=normal]     { background:#e8f5ec;color:#1e6b35;border-color:#1e6b35 }
.tipo-pill.ativo[data-tipo=cadeirinha] { background:#fff3cd;color:#a87d28;border-color:#a87d28 }
.tipo-pill.ativo[data-tipo=colo]       { background:#e8f0fb;color:#3b6cb7;border-color:#3b6cb7 }

/* Aviso de limite */
.aviso-limite { background:#fff3cd;border:1.5px solid #a87d28;border-radius:8px;padding:10px 14px;font-size:.83rem;color:#7a5c1a;margin-bottom:10px;display:none }
.aviso-limite strong { color:#a87d28 }

/* Busca passageiros */
.srch-wrap { position:relative;margin-bottom:10px }
.srch-drop { position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:99;
             background:#fff;border:1.5px solid var(--border);border-radius:8px;
             box-shadow:0 4px 16px rgba(0,0,0,.1);max-height:260px;overflow-y:auto;display:none }
.srch-drop.aberto { display:block }
.srch-item { padding:9px 14px;cursor:pointer;border-bottom:1px solid var(--border) }
.srch-item:last-child { border-bottom:none }
.srch-item:hover { background:var(--green-lt) }
.srch-item strong { display:block;font-size:.87rem }
.srch-item span   { font-size:.74rem;color:var(--muted) }

.manual-box { border:1.5px dashed var(--border);border-radius:8px;padding:14px;margin-top:10px;display:none }
.manual-box.aberto { display:block }

@media (max-width:600px) {
  .pass-item { grid-template-columns:22px 1fr auto }
  .pass-inputs { grid-template-columns:1fr }
}
</style>

<div style="margin-bottom:16px">
  <a href="/portal/van/" class="btn btn-ghost btn-sm">← Voltar para viagens</a>
</div>

<?php if ($erros): ?>
  <div class="alerta alerta-erro"><?= implode('<br>', array_map('htmlspecialchars', $erros)) ?></div>
<?php endif; ?>
<?php if (isset($_GET['ok'])): ?>
  <div class="alerta alerta-ok">
    Viagem salva. <a href="/portal/van/imprimir.php?id=<?= $id ?>" target="_blank">Abrir documento para impressão →</a>
  </div>
<?php endif; ?>

<form method="post" id="formViagem">
  <input type="hidden" name="csrf_token"       value="<?= csrf_token() ?>">
  <input type="hidden" name="passageiros_json" id="passJson"     value="<?= htmlspecialchars(json_encode($pass_inicial)) ?>">
  <input type="hidden" name="status"           id="inputStatus"  value="<?= htmlspecialchars($viagem['status'] ?? 'rascunho') ?>">
  <input type="hidden" name="acao"             id="inputAcao"    value="salvar">

  <!-- ── Dados da viagem ── -->
  <div class="van-card">
    <h3>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
      Dados da viagem
    </h3>

    <!-- Destino com autocomplete IBGE -->
    <div class="form-group">
      <label>Destino <span style="color:var(--red)">*</span></label>
      <div class="dest-wrap">
        <input type="text" name="destino" id="inputDestino" required maxlength="200"
          autocomplete="off" placeholder="Cidade - UF ou destino livre (ex: EUA)"
          value="<?= htmlspecialchars($viagem['destino'] ?? '') ?>">
        <div class="dest-drop" id="destDrop"></div>
      </div>
    </div>

    <!-- Tipo de data -->
    <div class="form-group" style="margin-bottom:10px">
      <label>Tipo de data</label>
      <div class="dtipo" id="dtipo">
        <?php $dtipo = $viagem['data_tipo'] ?? 'livre'; ?>
        <label><input type="radio" name="data_tipo" value="unico"      <?= $dtipo==='unico'      ? 'checked' : '' ?>> Dia único</label>
        <label><input type="radio" name="data_tipo" value="bate_volta" <?= $dtipo==='bate_volta' ? 'checked' : '' ?>> Ida e volta</label>
        <label><input type="radio" name="data_tipo" value="periodo"    <?= $dtipo==='periodo'    ? 'checked' : '' ?>> Período</label>
        <label><input type="radio" name="data_tipo" value="livre"      <?= $dtipo==='livre'      ? 'checked' : '' ?>> Livre</label>
      </div>
    </div>

    <!-- Campos de data dinâmicos -->
    <div id="secaoDatas">

      <div id="sdUnico"     style="display:none">
        <div class="form-group" style="max-width:200px;margin:0">
          <label>Data da viagem</label>
          <input type="date" id="d1Unico">
        </div>
      </div>

      <div id="sdBate"      style="display:none">
        <div class="form-group" style="max-width:200px;margin:0">
          <label>Data (ida e volta)</label>
          <input type="date" id="d1Bate">
        </div>
      </div>

      <div id="sdPeriodo"   style="display:none">
        <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
          <div class="form-group" style="margin:0">
            <label>Data de saída</label>
            <input type="date" id="d1Per">
          </div>
          <div class="form-group" style="margin:0">
            <label>Data de retorno</label>
            <input type="date" id="d2Per">
          </div>
          <div id="prevData" style="font-size:.82rem;color:var(--muted);padding-bottom:8px"></div>
        </div>
      </div>

      <div id="sdLivre"     style="display:none">
        <div class="form-group" style="margin:0">
          <label>Data (texto livre)</label>
          <input type="text" name="data_texto" id="inputDataLivre" maxlength="100"
            placeholder="Ex.: 05-06-07/06/2026"
            value="<?= htmlspecialchars($viagem['data_texto'] ?? '') ?>">
        </div>
      </div>

      <!-- Campo oculto que recebe o valor final de data -->
      <input type="hidden" name="data_texto" id="inputDataFinal" value="<?= htmlspecialchars($viagem['data_texto'] ?? '') ?>">
    </div>

  </div>

  <!-- ── Motorista ── -->
  <div class="van-card">
    <h3>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
      Motorista <span style="font-weight:400;color:var(--muted);font-size:.82rem">(posição 1)</span>
    </h3>

    <div class="tog" id="togMot">
      <label><input type="radio" name="motorista_tipo" value="membro"  <?= (!$viagem || $viagem['motorista_id']) ? 'checked' : '' ?>> <span>Membro da comunidade</span></label>
      <label><input type="radio" name="motorista_tipo" value="externo" <?= ($viagem && !$viagem['motorista_id'] && $viagem['motorista_nome']) ? 'checked' : '' ?>> <span>Não é membro</span></label>
    </div>

    <div id="smMembro">
      <div class="form-group" style="margin:0">
        <label>Selecionar motorista</label>
        <select name="motorista_id" id="selMot">
          <option value="">— Selecione —</option>
          <?php foreach ($motoristas as $m): ?>
            <option value="<?= $m['id'] ?>" data-cpf="<?= htmlspecialchars($m['cpf'] ?? '') ?>"
              <?= ($viagem && $viagem['motorista_id'] == $m['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($m['nome']) ?><?php if($m['cpf']): ?> — CPF: <?= htmlspecialchars($m['cpf']) ?><?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($motoristas)): ?>
          <small style="color:var(--muted)">Nenhum membro com habilidade "Motorista". <a href="/portal/membros/habilidades.php">Cadastrar</a></small>
        <?php endif; ?>
      </div>
    </div>

    <div id="smExterno" style="display:none">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;min-width:0">
        <div class="form-group" style="margin:0">
          <label>Nome completo</label>
          <input type="text" name="motorista_nome" maxlength="150" placeholder="Nome do motorista"
            value="<?= htmlspecialchars(($viagem && !$viagem['motorista_id']) ? ($viagem['motorista_nome'] ?? '') : '') ?>">
        </div>
        <div class="form-group" style="margin:0">
          <label>CPF</label>
          <input type="text" name="motorista_cpf" maxlength="20" placeholder="000.000.000-00"
            value="<?= htmlspecialchars(($viagem && !$viagem['motorista_id']) ? ($viagem['motorista_cpf'] ?? '') : '') ?>">
        </div>
      </div>
    </div>

    <!-- CPF exibido quando membro tem CPF -->
    <div id="motCpfDisplay" style="font-size:.8rem;color:var(--muted);margin-top:8px;display:none"></div>
  </div>

  <!-- ── Coordenador da missão ── -->
  <div class="van-card">
    <h3>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      Coordenador da missão
      <span style="font-size:.75rem;font-weight:400;color:var(--muted)">(opcional)</span>
    </h3>

    <div class="srch-wrap" style="margin-bottom:12px">
      <input type="text" id="buscaCoord"
        placeholder="Buscar por nome, CPF ou telefone…"
        autocomplete="off" style="width:100%;box-sizing:border-box">
      <div class="srch-drop" id="coordDrop"></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;min-width:0">
      <div class="form-group" style="margin:0">
        <label>Nome completo</label>
        <input type="text" name="coordenador_nome" id="coordNome" maxlength="150"
          placeholder="Nome do coordenador"
          value="<?= htmlspecialchars($viagem['coordenador_nome'] ?? '') ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label>CPF <span style="font-weight:400;color:var(--muted);font-size:.78rem">(opcional)</span></label>
        <input type="text" name="coordenador_cpf" id="coordCpf" maxlength="20"
          placeholder="000.000.000-00"
          value="<?= htmlspecialchars($viagem['coordenador_cpf'] ?? '') ?>">
      </div>
    </div>
    <input type="hidden" name="coordenador_id" id="coordId"
      value="<?= (int)($viagem['coordenador_id'] ?? 0) ?: '' ?>">
  </div>

  <!-- ── Passageiros ── -->
  <div class="van-card">
    <h3>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Passageiros
      <span style="font-size:.75rem;font-weight:400;color:var(--muted)" id="passCount">(0)</span>
    </h3>

    <div class="srch-wrap">
      <input type="text" id="buscaPass" placeholder="Buscar membro por nome, CPF ou telefone…"
        autocomplete="off" style="width:100%;box-sizing:border-box">
      <div class="srch-drop" id="srchDrop"></div>
    </div>

    <button type="button" class="btn btn-ghost btn-sm" id="btnManual" style="margin-bottom:12px">
      + Adicionar pessoa sem cadastro
    </button>

    <div class="manual-box" id="manualBox">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:10px;min-width:0">
        <div class="form-group" style="margin:0">
          <label style="font-size:.78rem">Nome</label>
          <input type="text" id="mNome" maxlength="150" placeholder="Nome completo">
        </div>
        <div class="form-group" style="margin:0">
          <label style="font-size:.78rem">CPF / RG <small style="color:var(--muted)">(opcional)</small></label>
          <input type="text" id="mCpf" maxlength="30" placeholder="000.000.000-00">
        </div>
        <div class="form-group" style="margin:0">
          <label style="font-size:.78rem">Observação <small style="color:var(--muted)">(ex: no colo)</small></label>
          <input type="text" id="mNota" maxlength="80" placeholder="no colo…">
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-primary btn-sm" id="btnAddManual">Adicionar</button>
        <button type="button" class="btn btn-ghost btn-sm"  id="btnCancelManual">Cancelar</button>
      </div>
    </div>

    <div class="aviso-limite" id="avisoLimite">
      <strong>Limite de 19 assentos atingido.</strong>
      Novos passageiros serão adicionados automaticamente como <strong>No colo</strong>.
      Você pode alterar o tipo em cada linha.
    </div>

    <ul class="pass-lista" id="passLista" style="margin-top:6px"></ul>
    <div id="passVazio" style="padding:20px;text-align:center;color:var(--muted);font-size:.85rem;
      border:1.5px dashed var(--border);border-radius:8px;margin-top:8px">
      Nenhum passageiro adicionado ainda.
    </div>
  </div>

  <!-- ── Ações ── -->
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button type="submit" class="btn btn-ghost"   onclick="setAcao('salvar','rascunho')">Salvar rascunho</button>
    <button type="submit" class="btn btn-primary" onclick="setAcao('salvar','finalizada')">Salvar e finalizar</button>
    <button type="submit" class="btn btn-primary" onclick="setAcao('imprimir','finalizada')"
      style="background:var(--green-dk);border-color:var(--green-dk)">
      Salvar e imprimir documento
    </button>
  </div>
</form>

<script>
(function(){

/* ════════════════════════════════════════════
   Autocomplete de destino (IBGE)
════════════════════════════════════════════ */
var cidCache = null;
var RE_COMB = new RegExp('['+String.fromCharCode(768)+'-'+String.fromCharCode(879)+']','g');
function norm(s){ return s.toLowerCase().normalize('NFD').replace(RE_COMB,''); }

var $dest = document.getElementById('inputDestino');
var $drop = document.getElementById('destDrop');
var destTimer, destClicando = false;

function destFecha(){ $drop.classList.remove('aberto'); $drop.innerHTML = ''; }
function destMostra(lista){
  $drop.innerHTML = '';
  if (!lista.length){ destFecha(); return; }
  lista.slice(0,12).forEach(function(c){
    var d = document.createElement('div');
    d.className = 'dest-item';
    d.innerHTML = '<span>'+c.nome+'</span><span class="d-uf">'+c.uf+'</span>';
    d.addEventListener('mousedown', function(e){
      e.preventDefault(); destClicando = true;
      $dest.value = c.nome + ' - ' + c.uf;
      destFecha(); $dest.focus(); destClicando = false;
    });
    $drop.appendChild(d);
  });
  $drop.classList.add('aberto');
}
function destBusca(q){
  if (q.length < 2){ destFecha(); return; }
  var nq = norm(q);
  function filtra(l){
    var ini = l.filter(function(c){ return norm(c.nome).indexOf(nq)===0; });
    return ini.length ? ini : l.filter(function(c){ return norm(c.nome).indexOf(nq)!==-1; });
  }
  if (cidCache){ destMostra(filtra(cidCache)); return; }
  fetch('/portal/membros/cidades_ibge.php')
    .then(function(r){ return r.json(); })
    .then(function(d){ cidCache = d; destMostra(filtra(d)); })
    .catch(function(){});
}
$dest.addEventListener('input', function(){
  clearTimeout(destTimer);
  var q = this.value.trim();
  destTimer = setTimeout(function(){ destBusca(q); }, 200);
});
$dest.addEventListener('focus', function(){ if(this.value.trim().length>=2) destBusca(this.value.trim()); });
$dest.addEventListener('blur',  function(){ if(!destClicando) setTimeout(destFecha, 160); });
$dest.addEventListener('keydown', function(e){
  var its = $drop.querySelectorAll('.dest-item');
  var at  = $drop.querySelector('.dest-item.ativo');
  var ix  = Array.prototype.indexOf.call(its, at);
  if (e.key === 'ArrowDown'){ e.preventDefault(); if(at) at.classList.remove('ativo'); var nx=its[ix+1]||its[0]; if(nx)nx.classList.add('ativo'); }
  else if (e.key === 'ArrowUp'){ e.preventDefault(); if(at) at.classList.remove('ativo'); var pv=its[ix-1]||its[its.length-1]; if(pv)pv.classList.add('ativo'); }
  else if (e.key === 'Enter' && at){ e.preventDefault(); $dest.value = at.querySelector('span').textContent + ' - ' + at.querySelector('.d-uf').textContent; destFecha(); }
  else if (e.key === 'Escape') destFecha();
});
document.addEventListener('click', function(e){ if(!$drop.contains(e.target)&&e.target!==$dest) destFecha(); });

/* ════════════════════════════════════════════
   Seletor de tipo de data
════════════════════════════════════════════ */
var $dataFinal = document.getElementById('inputDataFinal');
var $dataLivre = document.getElementById('inputDataLivre');

function pad(n){ return String(n).padStart(2,'0'); }
function gerarDataTexto(){
  var tipo = (document.querySelector('[name=data_tipo]:checked') || {}).value || 'livre';
  if (tipo === 'livre'){
    $dataFinal.value = $dataLivre.value;
    return;
  }
  if (tipo === 'unico'){
    var d = document.getElementById('d1Unico').value;
    if (!d){ $dataFinal.value=''; return; }
    var [y,m,dd] = d.split('-');
    $dataFinal.value = dd+'/'+m+'/'+y;
    return;
  }
  if (tipo === 'bate_volta'){
    var d = document.getElementById('d1Bate').value;
    if (!d){ $dataFinal.value=''; return; }
    var [y,m,dd] = d.split('-');
    $dataFinal.value = dd+'/'+m+'/'+y+' (Ida e Volta)';
    return;
  }
  if (tipo === 'periodo'){
    var d1 = document.getElementById('d1Per').value;
    var d2 = document.getElementById('d2Per').value;
    if (!d1){ $dataFinal.value=''; document.getElementById('prevData').textContent=''; return; }
    if (!d2) d2 = d1;
    var start = new Date(d1+'T00:00:00');
    var end   = new Date(d2+'T00:00:00');
    if (end < start) end = start;
    var dias = [];
    var cur = new Date(start);
    while(cur <= end){ dias.push(new Date(cur)); cur.setDate(cur.getDate()+1); }
    var [y1,m1,dd1] = d1.split('-');
    var [y2,m2,dd2] = d2.split('-');
    var texto = '';
    if (m1 === m2 && y1 === y2){
      texto = dias.map(function(d){ return pad(d.getDate()); }).join('-') + '/' + m1 + '/' + y1;
    } else {
      texto = dd1+'/'+m1+'-'+dd2+'/'+m2+'/'+y1;
    }
    $dataFinal.value = texto;
    document.getElementById('prevData').textContent = 'Texto: ' + texto;
    return;
  }
}

function syncTipoDatas(){
  var tipo = (document.querySelector('[name=data_tipo]:checked') || {}).value || 'livre';
  document.getElementById('sdUnico').style.display    = tipo==='unico'      ? '' : 'none';
  document.getElementById('sdBate').style.display     = tipo==='bate_volta' ? '' : 'none';
  document.getElementById('sdPeriodo').style.display  = tipo==='periodo'    ? '' : 'none';
  document.getElementById('sdLivre').style.display    = tipo==='livre'      ? '' : 'none';
  // remove o name do input escondido se livre para não duplicar
  document.getElementById('inputDataLivre').name = tipo==='livre' ? 'data_texto_livre_nao_usar' : '';
  gerarDataTexto();
}

document.querySelectorAll('[name=data_tipo]').forEach(function(r){ r.addEventListener('change', syncTipoDatas); });
['d1Unico','d1Bate','d1Per','d2Per'].forEach(function(id){
  var el = document.getElementById(id);
  if(el) el.addEventListener('change', gerarDataTexto);
});
$dataLivre.addEventListener('input', function(){ $dataFinal.value = this.value; });
syncTipoDatas();

// Pré-preencher datas existentes se editando
(function(){
  var val = <?= json_encode($viagem['data_texto'] ?? '') ?>;
  var tipo = <?= json_encode($viagem['data_tipo'] ?? 'livre') ?>;
  if (!val) return;
  if (tipo === 'unico' || tipo === 'bate_volta') {
    // tenta extrair date: DD/MM/YYYY
    var m = val.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
    if (m) {
      var iso = m[3]+'-'+m[2]+'-'+m[1];
      if (tipo==='unico')     document.getElementById('d1Unico').value = iso;
      if (tipo==='bate_volta') document.getElementById('d1Bate').value  = iso;
    }
  } else if (tipo === 'periodo'){
    // "DD-...-DD/MM/YYYY" ou "DD/MM-DD/MM/YYYY"
    var m2 = val.match(/^(\d{2})[^\d].*?(\d{2})\/(\d{2})\/(\d{4})/);
    if (m2) {
      document.getElementById('d1Per').value = m2[4]+'-'+m2[3]+'-'+m2[1];
    }
    var m3 = val.match(/(\d{2})\/(\d{2})$/);
    if (!m3) {
      var m4 = val.match(/(\d{2})\/(\d{2})\/(\d{4})/g);
      if (m4 && m4.length >= 2) {
        var last = m4[m4.length-1].match(/(\d{2})\/(\d{2})\/(\d{4})/);
        if(last) document.getElementById('d2Per').value = last[3]+'-'+last[2]+'-'+last[1];
      }
    }
  }
  gerarDataTexto();
})();

/* ════════════════════════════════════════════
   Toggle motorista (membro / externo)
════════════════════════════════════════════ */
function syncToggle(nomeRadio, secMembro, secExterno){
  var val = (document.querySelector('[name='+nomeRadio+']:checked') || {}).value || 'membro';
  document.getElementById(secMembro).style.display  = val==='membro'  ? '' : 'none';
  document.getElementById(secExterno).style.display = val==='externo' ? '' : 'none';
}
document.querySelectorAll('[name=motorista_tipo]').forEach(function(r){
  r.addEventListener('change', function(){ syncToggle('motorista_tipo','smMembro','smExterno'); });
});
syncToggle('motorista_tipo','smMembro','smExterno');

/* ════════════════════════════════════════════
   Busca coordenador (igual passageiros)
════════════════════════════════════════════ */
var $cBusca = document.getElementById('buscaCoord');
var $cDrop  = document.getElementById('coordDrop');
var cTimer;

$cBusca.addEventListener('input', function(){
  clearTimeout(cTimer);
  var q = this.value.trim();
  if (q.length < 2){ $cDrop.classList.remove('aberto'); return; }
  cTimer = setTimeout(function(){
    fetch('/portal/van/buscar.php?q='+encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.length){ $cDrop.classList.remove('aberto'); return; }
        $cDrop.innerHTML = data.map(function(m){
          var info = m.cpf ? 'CPF: '+esc(m.cpf) : (m.telefone ? 'Tel: '+esc(m.telefone) : '');
          return '<div class="srch-item" data-id="'+m.id+'" data-nome="'+esc(m.nome)+'" data-cpf="'+esc(m.cpf||'')+'">'+
            '<strong>'+esc(m.nome)+'</strong>'+
            (info ? '<span>'+info+'</span>' : '')+
            '</div>';
        }).join('');
        $cDrop.classList.add('aberto');
      });
  }, 260);
});
$cDrop.addEventListener('click', function(e){
  var item = e.target.closest('.srch-item');
  if (!item) return;
  document.getElementById('coordId').value   = item.dataset.id;
  document.getElementById('coordNome').value = item.dataset.nome;
  document.getElementById('coordCpf').value  = item.dataset.cpf || '';
  $cBusca.value = '';
  $cDrop.classList.remove('aberto');
});
document.addEventListener('click', function(e){
  if (!$cDrop.contains(e.target) && e.target !== $cBusca) $cDrop.classList.remove('aberto');
});

/* Mostra CPF do motorista selecionado */
document.getElementById('selMot').addEventListener('change', function(){
  var opt = this.options[this.selectedIndex];
  var cpf = opt ? opt.dataset.cpf : '';
  var div = document.getElementById('motCpfDisplay');
  if (cpf){ div.textContent = 'CPF: '+cpf; div.style.display=''; }
  else div.style.display='none';
});
(function(){ // init
  var opt = document.getElementById('selMot').options[document.getElementById('selMot').selectedIndex];
  var cpf = opt ? opt.dataset.cpf : '';
  var div = document.getElementById('motCpfDisplay');
  if(cpf){ div.textContent='CPF: '+cpf; div.style.display=''; }
})();

/* ════════════════════════════════════════════
   Gerenciador de passageiros
════════════════════════════════════════════ */
var MAX_ASSENTOS = 19;
var passengers = <?= json_encode($pass_inicial) ?>;
var $lista   = document.getElementById('passLista');
var $vazio   = document.getElementById('passVazio');
var $pjson   = document.getElementById('passJson');
var $count   = document.getElementById('passCount');
var $aviso   = document.getElementById('avisoLimite');
var $busca   = document.getElementById('buscaPass');
var $sdrop   = document.getElementById('srchDrop');
var $manual  = document.getElementById('manualBox');

function esc(s){ return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }

function contarAssentos(){ return passengers.filter(function(p){ return p.tipo !== 'colo'; }).length; }

function render(){
  sincJson(); // salva inputs antes de rerender
  $lista.innerHTML = '';
  var seatNum = 2;
  passengers.forEach(function(p, i){
    var isColo = (p.tipo === 'colo');
    var numHtml = isColo
      ? '<span style="font-size:.68rem;background:#e8f0fb;color:#3b6cb7;border-radius:4px;padding:2px 5px;font-weight:700">colo</span>'
      : '<span>'+(seatNum++)+'</span>';
    var li = document.createElement('li');
    li.className = 'pass-item';
    li.dataset.idx = i;
    li.innerHTML =
      '<span class="pass-num">'+numHtml+'</span>'+
      '<div class="pass-info">'+
        '<div class="pass-nome">'+esc(p.nome)+'</div>'+
        '<div class="tipo-pills">'+
          '<span class="tipo-pill'+(p.tipo==='normal'?' ativo':'')+'" data-tipo="normal" data-idx="'+i+'">Normal</span>'+
          '<span class="tipo-pill'+(p.tipo==='cadeirinha'?' ativo':'')+'" data-tipo="cadeirinha" data-idx="'+i+'">Cadeirinha</span>'+
          '<span class="tipo-pill'+(p.tipo==='colo'?' ativo':'')+'" data-tipo="colo" data-idx="'+i+'">No colo</span>'+
        '</div>'+
        '<div class="pass-inputs">'+
          '<input type="text" class="pi-cpf"  placeholder="CPF / RG (opcional)" value="'+esc(p.cpf_rg||'')+'">'+
          '<input type="text" class="pi-nota" placeholder="Obs. (opcional)"     value="'+esc(p.nota||'')+'">'+
        '</div>'+
      '</div>'+
      '<button type="button" class="pass-rem" data-idx="'+i+'" title="Remover">✕</button>';
    $lista.appendChild(li);
  });
  var assentos = contarAssentos();
  var colo     = passengers.filter(function(p){ return p.tipo === 'colo'; }).length;
  $vazio.style.display  = passengers.length ? 'none' : '';
  $aviso.style.display  = assentos >= MAX_ASSENTOS ? '' : 'none';
  $count.textContent    = assentos
    ? '(' + assentos + ' com assento' + (colo ? ', ' + colo + ' no colo' : '') + ')'
    : '(0)';
  $pjson.value = JSON.stringify(passengers);
}

function sincJson(){
  $lista.querySelectorAll('.pass-item').forEach(function(li){
    var idx = parseInt(li.dataset.idx);
    if (!passengers[idx]) return;
    passengers[idx].cpf_rg = (li.querySelector('.pi-cpf')  || {}).value || passengers[idx].cpf_rg || '';
    passengers[idx].nota   = (li.querySelector('.pi-nota') || {}).value || passengers[idx].nota   || '';
  });
}

$lista.addEventListener('click', function(e){
  // Remover passageiro
  var rem = e.target.closest('.pass-rem');
  if (rem){ sincJson(); passengers.splice(parseInt(rem.dataset.idx),1); render(); return; }
  // Mudar tipo
  var pill = e.target.closest('.tipo-pill');
  if (pill){
    sincJson();
    var idx  = parseInt(pill.dataset.idx);
    var tipo = pill.dataset.tipo;
    // Verifica limite antes de mudar para tipo com assento
    if (tipo !== 'colo' && passengers[idx].tipo === 'colo') {
      var outros = passengers.filter(function(p,i){ return i !== idx && p.tipo !== 'colo'; }).length;
      if (outros >= MAX_ASSENTOS){
        alert('Limite de '+MAX_ASSENTOS+' assentos atingido. Remova outro passageiro com assento antes.');
        return;
      }
    }
    passengers[idx].tipo = tipo;
    render();
  }
});
$lista.addEventListener('input', function(){ sincJson(); $pjson.value = JSON.stringify(passengers); });

function addPass(p){
  p.tipo = p.tipo || 'normal';
  // Auto-atribui "colo" se limite atingido
  if (p.tipo !== 'colo' && contarAssentos() >= MAX_ASSENTOS) {
    p.tipo = 'colo';
  }
  passengers.push(p);
  render();
  $sdrop.classList.remove('aberto');
  $busca.value = '';
}

/* Busca de membros */
var srchTimer;
$busca.addEventListener('input', function(){
  clearTimeout(srchTimer);
  var q = this.value.trim();
  if (q.length < 2){ $sdrop.classList.remove('aberto'); return; }
  srchTimer = setTimeout(function(){
    fetch('/portal/van/buscar.php?q='+encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.length){ $sdrop.classList.remove('aberto'); return; }
        $sdrop.innerHTML = data.map(function(m){
          var info = m.cpf ? 'CPF: '+esc(m.cpf) : (m.telefone ? 'Tel: '+esc(m.telefone) : '');
          return '<div class="srch-item" data-id="'+m.id+'" data-nome="'+esc(m.nome)+'" data-cpf="'+esc(m.cpf||'')+'">'+
            '<strong>'+esc(m.nome)+'</strong>'+
            (info ? '<span>'+info+'</span>' : '')+
            '</div>';
        }).join('');
        $sdrop.classList.add('aberto');
      });
  }, 260);
});
$sdrop.addEventListener('click', function(e){
  var item = e.target.closest('.srch-item');
  if (!item) return;
  addPass({ membro_id: parseInt(item.dataset.id), nome: item.dataset.nome, cpf_rg: item.dataset.cpf||'', nota:'' });
});
document.addEventListener('click', function(e){
  if (!$sdrop.contains(e.target) && e.target !== $busca) $sdrop.classList.remove('aberto');
});

/* Adicionar manual */
document.getElementById('btnManual').addEventListener('click', function(){
  $manual.classList.toggle('aberto');
  if ($manual.classList.contains('aberto')) document.getElementById('mNome').focus();
});
document.getElementById('btnCancelManual').addEventListener('click', function(){
  $manual.classList.remove('aberto');
  document.getElementById('mNome').value = '';
  document.getElementById('mCpf').value  = '';
  document.getElementById('mNota').value = '';
});
document.getElementById('btnAddManual').addEventListener('click', function(){
  var nome = document.getElementById('mNome').value.trim();
  if (!nome){ document.getElementById('mNome').focus(); return; }
  addPass({ membro_id:null, nome:nome, cpf_rg: document.getElementById('mCpf').value.trim(), nota: document.getElementById('mNota').value.trim() });
  document.getElementById('mNome').value = '';
  document.getElementById('mCpf').value  = '';
  document.getElementById('mNota').value = '';
  $manual.classList.remove('aberto');
});

render();

})(); // fim IIFE

function setAcao(acao, status){
  // Força sincronização antes de submeter
  document.getElementById('passJson').value; // já sincronizado pelo listener input
  document.getElementById('inputAcao').value   = acao;
  document.getElementById('inputStatus').value = status;
  // Garante que o campo data_texto final está preenchido
  var tipo = (document.querySelector('[name=data_tipo]:checked') || {}).value || 'livre';
  if (tipo === 'livre') {
    document.getElementById('inputDataFinal').value = document.getElementById('inputDataLivre').value;
  }
}
</script>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
