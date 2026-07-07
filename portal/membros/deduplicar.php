<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);

$pdo = db();

// ── Helpers ──────────────────────────────────────────────────────────────

function normalizar(string $nome): string {
    $nome = mb_strtolower(trim($nome), 'UTF-8');
    $nome = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
    return preg_replace('/\s+/', ' ', $nome);
}

function detectar_grupos(array $todos): array {
    $grupos = [];
    $usados = [];
    for ($i = 0; $i < count($todos); $i++) {
        if (isset($usados[$todos[$i]['id']])) continue;
        $grupo = [$todos[$i]];
        $normI = normalizar($todos[$i]['nome']);
        for ($j = $i + 1; $j < count($todos); $j++) {
            if (isset($usados[$todos[$j]['id']])) continue;
            similar_text($normI, normalizar($todos[$j]['nome']), $pct);
            if ($pct >= 85) {
                $grupo[] = $todos[$j];
                $usados[$todos[$j]['id']] = true;
            }
        }
        if (count($grupo) > 1) {
            $usados[$todos[$i]['id']] = true;
            $grupos[] = $grupo;
        }
    }
    return $grupos;
}

// Pontuação de completude: quanto mais campos preenchidos, mais alto
function pontuar(array $m): int {
    $s = 0;
    foreach (['telefone','data_nasc','endereco','bairro','cidade','estado_civil','sexo'] as $f) {
        if (!empty($m[$f])) $s++;
    }
    if (!empty($m['grupos'])) $s += 2;
    if (!empty($m['cargos'])) $s += 2;
    return $s;
}

function mesclar_e_remover(PDO $pdo, int $manter_id, int $remover_id): void {
    $pdo->beginTransaction();
    try {
        // Transfere relações
        foreach ([
            ['membros_grupo_rel',      'grupo_id'],
            ['membros_cargo_rel',      'cargo_id'],
            ['membros_habilidade_rel', 'habilidade_id'],
            ['membros_pastoreio_rel',  'pastoreio_id'],
        ] as [$tabela, $col]) {
            $pdo->prepare("INSERT IGNORE INTO {$tabela} ({$col}, membro_id)
                SELECT {$col}, ? FROM {$tabela} WHERE membro_id=?")
                ->execute([$manter_id, $remover_id]);
        }
        // Preenche campos em branco do mantido com dados do removido
        $pdo->prepare("UPDATE membros m JOIN membros dup ON dup.id=?
            SET
              m.telefone    = IF(m.telefone    IS NULL OR m.telefone='',    dup.telefone,    m.telefone),
              m.data_nasc   = IF(m.data_nasc   IS NULL,                     dup.data_nasc,   m.data_nasc),
              m.endereco    = IF(m.endereco    IS NULL OR m.endereco='',    dup.endereco,    m.endereco),
              m.bairro      = IF(m.bairro      IS NULL OR m.bairro='',      dup.bairro,      m.bairro),
              m.cidade      = IF(m.cidade      IS NULL OR m.cidade='',      dup.cidade,      m.cidade),
              m.estado_civil= IF(m.estado_civil IS NULL OR m.estado_civil='',dup.estado_civil,m.estado_civil),
              m.sexo        = IF(m.sexo        IS NULL OR m.sexo='',        dup.sexo,        m.sexo)
            WHERE m.id=?")
            ->execute([$remover_id, $manter_id]);
        // Inativa duplicata
        $pdo->prepare("UPDATE membros SET ativo=0 WHERE id=?")->execute([$remover_id]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ── Query base ───────────────────────────────────────────────────────────

function buscar_membros(PDO $pdo): array {
    return $pdo->query("
        SELECT m.id, m.nome, m.telefone, m.data_nasc, m.endereco, m.bairro, m.cidade,
               m.estado_civil, m.sexo, m.criado_em,
               GROUP_CONCAT(DISTINCT g.nome ORDER BY g.nome SEPARATOR ', ') AS grupos,
               GROUP_CONCAT(DISTINCT c.nome ORDER BY c.nome SEPARATOR ', ') AS cargos
        FROM membros m
        LEFT JOIN membros_grupo_rel gr ON gr.membro_id = m.id
        LEFT JOIN membros_grupos g      ON g.id = gr.grupo_id
        LEFT JOIN membros_cargo_rel cr  ON cr.membro_id = m.id
        LEFT JOIN membros_cargos c      ON c.id = cr.cargo_id
        WHERE m.ativo = 1
        GROUP BY m.id
        ORDER BY m.nome
    ")->fetchAll();
}

// ── Ações POST ───────────────────────────────────────────────────────────

$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    $acao = $_POST['acao'] ?? '';

    // ── Corrigir tudo automaticamente ────────────────────────────────────
    if ($acao === 'corrigir_tudo') {
        $todos      = buscar_membros($pdo);
        $grupos_dup = detectar_grupos($todos);
        $resolvidos = 0;
        $pulados    = 0;

        // Nome parecido (85%) sozinho não é confiável o bastante para mesclar
        // automaticamente sem revisão humana (ex.: pai e filho com nomes parecidos).
        // Exige também telefone OU data de nascimento iguais entre os dois registros.
        $confirma = function(array $a, array $b): bool {
            if (!empty($a['telefone']) && !empty($b['telefone']) && $a['telefone'] === $b['telefone']) return true;
            if (!empty($a['data_nasc']) && !empty($b['data_nasc']) && $a['data_nasc'] === $b['data_nasc']) return true;
            return false;
        };

        foreach ($grupos_dup as $grupo) {
            // Ordena: maior pontuação primeiro, menor ID como desempate
            usort($grupo, function($a, $b) {
                $pa = pontuar($a); $pb = pontuar($b);
                return $pa !== $pb ? $pb - $pa : $a['id'] - $b['id'];
            });
            $manter = $grupo[0];
            for ($i = 1; $i < count($grupo); $i++) {
                if (!$confirma($manter, $grupo[$i])) {
                    $pulados++;
                    continue;
                }
                mesclar_e_remover($pdo, $manter['id'], $grupo[$i]['id']);
                $resolvidos++;
            }
        }

        $msg = ['ok', "Correção automática concluída: {$resolvidos} duplicata(s) removida(s) e mesclada(s)."
            . ($pulados ? " {$pulados} par(es) com nome parecido foram deixados para revisão manual (sem telefone/nascimento em comum para confirmar)." : '')];

    // ── Resolver duplicata individual ─────────────────────────────────────
    } elseif ($acao === 'resolver') {
        $manter_id  = (int)($_POST['manter_id']  ?? 0);
        $remover_id = (int)($_POST['remover_id'] ?? 0);
        $mesclar    = !empty($_POST['mesclar']);

        if ($manter_id && $remover_id && $manter_id !== $remover_id) {
            $stCheck = $pdo->prepare("SELECT id FROM membros WHERE id IN (?,?) AND ativo=1");
            $stCheck->execute([$manter_id, $remover_id]);
            if ($stCheck->rowCount() === 2) {
                if ($mesclar) {
                    mesclar_e_remover($pdo, $manter_id, $remover_id);
                } else {
                    $pdo->prepare("UPDATE membros SET ativo=0 WHERE id=?")->execute([$remover_id]);
                }
                $msg = ['ok', 'Duplicata removida com sucesso.'];
            } else {
                $msg = ['erro', 'IDs inválidos.'];
            }
        }
    }
}

// ── Carrega estado atual ──────────────────────────────────────────────────
$todos      = buscar_membros($pdo);
$grupos_dup = detectar_grupos($todos);

$titulo       = 'Deduplicar Membros';
$pagina_ativa = 'membros';
include dirname(__DIR__) . '/_layout.php';
?>

<style>
.dup-wrap{max-width:960px;margin:0 auto;padding:0 4px}
.dup-top{display:flex;align-items:flex-start;gap:12px;margin-bottom:24px;flex-wrap:wrap}
.dup-title{font-family:'Cinzel',serif;font-size:.85rem;font-weight:700;color:var(--green-dk);text-transform:uppercase;letter-spacing:.06em}
.dup-sub{font-size:.76rem;color:var(--muted);margin-top:2px}
.dup-grupo{background:#fff;border:1px solid var(--border);border-radius:var(--rl);margin-bottom:20px;overflow:hidden;box-shadow:var(--sh-sm)}
.dup-grupo-head{background:var(--off);padding:12px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.dup-badge{background:#dc2626;color:#fff;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:20px}
.dup-cards{display:grid;gap:0}
.dup-card{padding:16px 18px;border-bottom:1px solid var(--border)}
.dup-card:last-child{border-bottom:none}
.dup-nome{font-size:.97rem;font-weight:700;color:var(--green-dk);margin-bottom:8px}
.dup-dados{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:4px 14px;margin-bottom:10px}
.dup-campo{font-size:.78rem;color:var(--muted)}
.dup-campo strong{color:var(--txt);font-weight:600}
.dup-tags{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px}
.dup-tag{font-size:.67rem;font-weight:600;padding:2px 8px;border-radius:20px;background:var(--green-pale);color:var(--green-dk)}
.dup-acoes{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.dup-form{display:contents}
.dup-mesclar{font-size:.78rem;color:var(--muted);display:flex;align-items:center;gap:5px;cursor:pointer;user-select:none}
.dup-mesclar input{cursor:pointer}
.dup-vazio{text-align:center;padding:48px 20px;color:var(--muted);font-size:.88rem}
.dup-vazio strong{display:block;font-size:1.1rem;color:var(--green-dk);margin-bottom:8px}
.dup-corrigir-box{background:#fffbeb;border:1px solid #f5d87a;border-radius:var(--rl);padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.dup-corrigir-txt{font-size:.82rem;color:#92400e}
.dup-corrigir-txt strong{font-weight:700}
@media(min-width:640px){
  .dup-cards{grid-template-columns:1fr 1fr}
  .dup-card{border-bottom:none;border-right:1px solid var(--border)}
  .dup-card:last-child{border-right:none}
  .dup-cards.trio .dup-card{border-right:1px solid var(--border)}
  .dup-cards.trio .dup-card:last-child{border-right:none}
}
</style>

<div class="dup-wrap">

  <div class="dup-top">
    <div style="flex:1;min-width:0">
      <div class="dup-title">Deduplicar Membros</div>
      <div class="dup-sub">
        <?= count($grupos_dup) ?> grupo<?= count($grupos_dup) !== 1 ? 's' : '' ?> de duplicata<?= count($grupos_dup) !== 1 ? 's' : '' ?> encontrado<?= count($grupos_dup) !== 1 ? 's' : '' ?>
        · Similaridade de nome ≥ 85%
      </div>
    </div>
    <a href="/portal/membros/" class="btn btn-ghost btn-sm">← Voltar</a>
  </div>

  <?php if ($msg): ?>
  <div class="alerta alerta-<?= $msg[0] ?>" style="margin-bottom:16px"><?= htmlspecialchars($msg[1]) ?></div>
  <?php endif; ?>

  <?php if (!empty($grupos_dup)): ?>
  <div class="dup-corrigir-box">
    <div class="dup-corrigir-txt">
      <strong>Corrigir tudo automaticamente:</strong>
      Para cada grupo, mantém o registro com mais dados preenchidos,
      mescla grupos/cargos/habilidades/pastoreio e remove as cópias.
    </div>
    <form method="post"
          onsubmit="return confirm('Confirma a correção automática de todas as <?= count($grupos_dup) ?> duplicata(s)? Esta ação mesclará e inativará os registros duplicados.')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="acao"       value="corrigir_tudo">
      <button type="submit" class="btn btn-primary btn-sm">⚡ Corrigir tudo</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($grupos_dup)): ?>
  <div class="dup-vazio">
    <strong>Nenhuma duplicata encontrada</strong>
    Todos os membros possuem nomes distintos.
  </div>
  <?php else: ?>

  <?php foreach ($grupos_dup as $grupo): ?>
  <div class="dup-grupo">
    <div class="dup-grupo-head">
      <span class="dup-badge"><?= count($grupo) ?>×</span>
      <span style="font-size:.83rem;font-weight:600;color:var(--green-dk)"><?= htmlspecialchars($grupo[0]['nome']) ?></span>
    </div>
    <div class="dup-cards <?= count($grupo) >= 3 ? 'trio' : '' ?>">
      <?php foreach ($grupo as $m): ?>
      <div class="dup-card">
        <div class="dup-nome"><?= htmlspecialchars($m['nome']) ?></div>
        <div class="dup-dados">
          <?php
            $campos = [
              'Cadastro' => $m['criado_em'] ? (new DateTime($m['criado_em']))->format('d/m/Y H:i') : '—',
              'Nasc.'    => $m['data_nasc'] ? (new DateTime($m['data_nasc']))->format('d/m/Y') : '—',
              'Telefone' => $m['telefone'] ?: '—',
              'Cidade'   => $m['cidade']   ?: '—',
              'Sexo'     => $m['sexo']     ?: '—',
            ];
            foreach ($campos as $label => $val):
          ?>
          <div class="dup-campo"><strong><?= $label ?>:</strong> <?= htmlspecialchars($val) ?></div>
          <?php endforeach; ?>
        </div>
        <?php if ($m['grupos'] || $m['cargos']): ?>
        <div class="dup-tags">
          <?php foreach (array_filter(explode(', ', $m['grupos'] ?? '')) as $g): ?>
            <span class="dup-tag"><?= htmlspecialchars($g) ?></span>
          <?php endforeach; ?>
          <?php foreach (array_filter(explode(', ', $m['cargos'] ?? '')) as $c): ?>
            <span class="dup-tag" style="background:var(--off);color:var(--muted)"><?= htmlspecialchars($c) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="dup-acoes">
          <?php foreach ($grupo as $outro):
            if ($outro['id'] === $m['id']) continue; ?>
          <form method="post" class="dup-form"
                onsubmit="return confirm('Confirma manter «<?= htmlspecialchars(addslashes($m['nome'])) ?>» e remover a outra entrada?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="acao"       value="resolver">
            <input type="hidden" name="manter_id"  value="<?= $m['id'] ?>">
            <input type="hidden" name="remover_id" value="<?= $outro['id'] ?>">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <button type="submit" class="btn btn-primary btn-sm">✓ Manter este</button>
              <label class="dup-mesclar">
                <input type="checkbox" name="mesclar" value="1" checked>
                Mesclar dados do outro
              </label>
            </div>
          </form>
          <?php endforeach; ?>
          <a href="/portal/membros/ver.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-sm" target="_blank">Ver</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/_layout_end.php'; ?>
