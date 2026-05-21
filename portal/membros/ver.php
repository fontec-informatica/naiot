<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /portal/membros/'); exit; }

$st = db()->prepare("SELECT * FROM membros WHERE id=?");
$st->execute([$id]);
$m = $st->fetch();
if (!$m) { header('Location: /portal/membros/'); exit; }

$titulo       = htmlspecialchars($m['nome']);
$pagina_ativa = 'membros';

$st2 = db()->prepare("SELECT g.* FROM membros_grupos g JOIN membros_grupo_rel r ON r.grupo_id=g.id WHERE r.membro_id=? ORDER BY g.nome");
$st2->execute([$id]);
$grupos = $st2->fetchAll();

$st3 = db()->prepare("SELECT c.* FROM membros_cargos c JOIN membros_cargo_rel r ON r.cargo_id=c.id WHERE r.membro_id=? ORDER BY c.nome");
$st3->execute([$id]);
$cargos = $st3->fetchAll();

$st4 = db()->prepare("SELECT h.* FROM membros_habilidades h JOIN membros_habilidade_rel r ON r.habilidade_id=h.id WHERE r.membro_id=? ORDER BY h.nome");
$st4->execute([$id]);
$habilidades = $st4->fetchAll();

$idade = '';
if ($m['data_nasc']) {
    $nasc  = new DateTime($m['data_nasc']);
    $hoje  = new DateTime();
    $diff  = $nasc->diff($hoje);
    $idade = $diff->y . ' anos';
}

include dirname(__DIR__) . '/_layout.php';
?>

<?php if (isset($_GET['ok'])): ?>
<div class="alerta alerta-ok">Dados salvos com sucesso.</div>
<?php endif; ?>

<style>
.ver-layout{display:grid;grid-template-columns:260px 1fr;gap:24px;align-items:start}
.ver-card{background:#fff;border:1px solid var(--border);border-radius:var(--rl);overflow:hidden;box-shadow:var(--sh-sm)}
.ver-foto{width:100%;aspect-ratio:1;background:var(--green-pale);display:flex;align-items:center;justify-content:center;overflow:hidden}
.ver-foto img{width:100%;height:100%;object-fit:cover}
.ver-inicial{font-family:'Cinzel',serif;font-size:4rem;font-weight:700;color:var(--green);opacity:.4}
.ver-nome-wrap{padding:18px 18px 14px;border-bottom:1px solid var(--border);text-align:center}
.ver-nome{font-size:1.05rem;font-weight:700;color:var(--green-dk);line-height:1.3}
.ver-grupos{display:flex;flex-wrap:wrap;gap:5px;justify-content:center;margin-top:10px}
.ver-gtag{font-size:.68rem;font-weight:600;padding:3px 10px;border-radius:20px;color:#fff}
.ver-acoes{padding:14px 18px;display:flex;flex-direction:column;gap:6px;overflow:hidden}
.ver-acoes .btn{justify-content:center;min-width:0;width:100%;box-sizing:border-box}
.ver-acao-link{overflow:hidden}
.ver-acao-txt{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:100%}

.ver-info{background:#fff;border:1px solid var(--border);border-radius:var(--rl);overflow:hidden;box-shadow:var(--sh-sm)}
.ver-info-head{padding:14px 20px;background:var(--off);border-bottom:1px solid var(--border)}
.ver-info-head h3{font-family:'Cinzel',serif;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted)}
.ver-campos{padding:8px 0}
.ver-campo{display:flex;align-items:flex-start;gap:12px;padding:12px 20px;border-bottom:1px solid var(--border)}
.ver-campo:last-child{border-bottom:none}
.ver-campo-icon{width:32px;height:32px;border-radius:8px;background:var(--green-pale);flex-shrink:0;display:flex;align-items:center;justify-content:center;margin-top:1px}
.ver-campo-icon svg{width:14px;height:14px;stroke:var(--green);stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.ver-campo-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:2px}
.ver-campo-val{font-size:.9rem;color:var(--txt);line-height:1.5}
.ver-campo-vazio{color:var(--muted);font-style:italic;font-size:.82rem}

@media(max-width:780px){.ver-layout{grid-template-columns:1fr}}
</style>

<div style="margin-bottom:20px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
  <a href="/portal/membros/" class="btn btn-ghost btn-sm">← Membros</a>
</div>

<div class="ver-layout">

  <!-- card lateral -->
  <div class="ver-card">
    <div class="ver-foto">
      <?php if (!empty($m['foto'])): ?>
        <img src="/portal/membros/fotos/<?= htmlspecialchars($m['foto']) ?>" alt="<?= htmlspecialchars($m['nome']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <?php else: ?>
        <span class="ver-inicial"><?= mb_strtoupper(mb_substr($m['nome'],0,1)) ?></span>
      <?php endif; ?>
    </div>
    <div class="ver-nome-wrap">
      <div class="ver-nome"><?= htmlspecialchars($m['nome']) ?></div>
      <?php if ($cargos || $grupos || $habilidades): ?>
      <div class="ver-grupos">
        <?php foreach ($cargos as $c): ?>
          <a href="/portal/membros/?cargo=<?= $c['id'] ?>" style="display:inline-block;font-size:.68rem;font-weight:700;padding:3px 10px;border-radius:4px;border:1.5px solid <?= htmlspecialchars($c['cor']) ?>;color:<?= htmlspecialchars($c['cor']) ?>;background:<?= htmlspecialchars($c['cor']) ?>18;text-decoration:none;white-space:nowrap"><?= htmlspecialchars($c['nome']) ?></a>
        <?php endforeach; ?>
        <?php foreach ($grupos as $g): ?>
          <a href="/portal/membros/?grupo=<?= $g['id'] ?>" class="ver-gtag" style="background:<?= htmlspecialchars($g['cor']) ?>"><?= htmlspecialchars($g['nome']) ?></a>
        <?php endforeach; ?>
        <?php foreach ($habilidades as $h): ?>
          <a href="/portal/membros/?habilidade=<?= $h['id'] ?>" style="display:inline-block;font-size:.68rem;font-weight:700;padding:3px 10px;border-radius:10px;border:1.5px solid <?= htmlspecialchars($h['cor']) ?>;color:<?= htmlspecialchars($h['cor']) ?>;background:<?= htmlspecialchars($h['cor']) ?>18;text-decoration:none;white-space:nowrap"><?= htmlspecialchars($h['nome']) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="ver-acoes">
      <a href="/portal/membros/editar.php?id=<?= $id ?>" class="btn btn-primary btn-sm">Editar dados</a>
      <?php foreach ($cargos as $c): ?>
        <a href="/portal/membros/?cargo=<?= $c['id'] ?>"
           class="btn btn-ghost btn-sm ver-acao-link"
           style="border-color:<?= htmlspecialchars($c['cor']) ?>;color:<?= htmlspecialchars($c['cor']) ?>"
           title="Ver cargo: <?= htmlspecialchars($c['nome']) ?>">
          <span class="ver-acao-txt">Ver cargo: <?= htmlspecialchars($c['nome']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php foreach ($grupos as $g): ?>
        <a href="/portal/membros/?grupo=<?= $g['id'] ?>"
           class="btn btn-ghost btn-sm ver-acao-link"
           style="border-color:<?= htmlspecialchars($g['cor']) ?>;color:<?= htmlspecialchars($g['cor']) ?>"
           title="Ver grupo: <?= htmlspecialchars($g['nome']) ?>">
          <span class="ver-acao-txt">Ver grupo: <?= htmlspecialchars($g['nome']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php foreach ($habilidades as $h): ?>
        <a href="/portal/membros/?habilidade=<?= $h['id'] ?>"
           class="btn btn-ghost btn-sm ver-acao-link"
           style="border-color:<?= htmlspecialchars($h['cor']) ?>;color:<?= htmlspecialchars($h['cor']) ?>"
           title="Ver habilidade: <?= htmlspecialchars($h['nome']) ?>">
          <span class="ver-acao-txt">Ver habilidade: <?= htmlspecialchars($h['nome']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- informações -->
  <div>
    <div class="ver-info">
      <div class="ver-info-head"><h3>Informações pessoais</h3></div>
      <div class="ver-campos">

        <div class="ver-campo">
          <div class="ver-campo-icon">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div>
            <div class="ver-campo-label">Nome</div>
            <div class="ver-campo-val"><?= htmlspecialchars($m['nome']) ?></div>
          </div>
        </div>

        <div class="ver-campo">
          <div class="ver-campo-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          </div>
          <div>
            <div class="ver-campo-label">Data de nascimento</div>
            <div class="ver-campo-val">
              <?php if ($m['data_nasc']): ?>
                <?= date('d/m/Y', strtotime($m['data_nasc'])) ?>
                <?php if ($idade): ?> &nbsp;<span style="color:var(--muted);font-size:.8rem">(<?= $idade ?>)</span><?php endif; ?>
              <?php else: ?>
                <span class="ver-campo-vazio">Não informado</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="ver-campo">
          <div class="ver-campo-icon">
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
          </div>
          <div>
            <div class="ver-campo-label">Telefone</div>
            <div class="ver-campo-val">
              <?php if ($m['telefone']): ?>
                <a href="tel:<?= preg_replace('/\D/','',$m['telefone']) ?>" style="color:var(--green)"><?= htmlspecialchars($m['telefone']) ?></a>
              <?php else: ?>
                <span class="ver-campo-vazio">Não informado</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="ver-campo">
          <div class="ver-campo-icon">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
          <div>
            <div class="ver-campo-label">Endereço</div>
            <div class="ver-campo-val">
              <?php
                $endereco_completo = implode(', ', array_filter([$m['endereco'], $m['bairro'], $m['cidade']]));
              ?>
              <?php if ($endereco_completo): ?>
                <?= htmlspecialchars($endereco_completo) ?>
              <?php else: ?>
                <span class="ver-campo-vazio">Não informado</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Cargos -->
    <?php if ($cargos): ?>
    <div class="ver-info" style="margin-top:18px">
      <div class="ver-info-head"><h3>Cargos</h3></div>
      <div style="padding:14px 20px;display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($cargos as $c): ?>
          <a href="/portal/membros/?cargo=<?= $c['id'] ?>"
             style="display:inline-flex;align-items:center;gap:7px;padding:6px 14px;border-radius:6px;background:<?= htmlspecialchars($c['cor']) ?>18;border:1.5px solid <?= htmlspecialchars($c['cor']) ?>;color:<?= htmlspecialchars($c['cor']) ?>;font-size:.82rem;font-weight:600;text-decoration:none">
            <span style="width:9px;height:9px;border-radius:2px;background:<?= htmlspecialchars($c['cor']) ?>"></span>
            <?= htmlspecialchars($c['nome']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Grupos -->
    <?php if ($grupos): ?>
    <div class="ver-info" style="margin-top:18px">
      <div class="ver-info-head"><h3>Grupos</h3></div>
      <div style="padding:14px 20px;display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($grupos as $g): ?>
          <a href="/portal/membros/?grupo=<?= $g['id'] ?>"
             style="display:inline-flex;align-items:center;gap:7px;padding:6px 14px;border-radius:20px;background:<?= htmlspecialchars($g['cor']) ?>18;border:1.5px solid <?= htmlspecialchars($g['cor']) ?>;color:<?= htmlspecialchars($g['cor']) ?>;font-size:.82rem;font-weight:600;text-decoration:none">
            <span style="width:9px;height:9px;border-radius:50%;background:<?= htmlspecialchars($g['cor']) ?>"></span>
            <?= htmlspecialchars($g['nome']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Habilidades -->
    <?php if ($habilidades): ?>
    <div class="ver-info" style="margin-top:18px">
      <div class="ver-info-head"><h3>Habilidades</h3></div>
      <div style="padding:14px 20px;display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($habilidades as $h): ?>
          <a href="/portal/membros/?habilidade=<?= $h['id'] ?>"
             style="display:inline-flex;align-items:center;gap:7px;padding:6px 14px;border-radius:10px;background:<?= htmlspecialchars($h['cor']) ?>18;border:1.5px solid <?= htmlspecialchars($h['cor']) ?>;color:<?= htmlspecialchars($h['cor']) ?>;font-size:.82rem;font-weight:600;text-decoration:none">
            <span style="width:8px;height:8px;border-radius:2px;transform:rotate(45deg);background:<?= htmlspecialchars($h['cor']) ?>;flex-shrink:0"></span>
            <?= htmlspecialchars($h['nome']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div style="margin-top:14px;font-size:.72rem;color:var(--muted)">
      Cadastrado em <?= date('d/m/Y', strtotime($m['criado_em'])) ?>
      · Atualizado em <?= date('d/m/Y H:i', strtotime($m['atualizado_em'])) ?>
    </div>
  </div>

</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
