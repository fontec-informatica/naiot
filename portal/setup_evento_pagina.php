<?php
/**
 * Migração: Página pública do evento + Campos configuráveis
 * Execute UMA VEZ e delete depois.
 */
require_once __DIR__ . '/config.php';

$msgs = [];
function run_sql(string $sql, string $label): void {
    global $msgs;
    try { db()->exec($sql); $msgs[] = ['ok', "✓ $label"]; }
    catch (Exception $e) { $msgs[] = ['err', "✗ $label — " . $e->getMessage()]; }
}

/* ── Novas colunas em eventos ── */
$cols = db()->query("SHOW COLUMNS FROM eventos")->fetchAll(PDO::FETCH_COLUMN);
$novas = [
    'sobre'             => "ALTER TABLE eventos ADD COLUMN sobre TEXT NULL AFTER descricao",
    'email_organizador' => "ALTER TABLE eventos ADD COLUMN email_organizador VARCHAR(180) NULL",
    'whatsapp_contato'  => "ALTER TABLE eventos ADD COLUMN whatsapp_contato VARCHAR(30) NULL",
    'mensagem_inscrito' => "ALTER TABLE eventos ADD COLUMN mensagem_inscrito TEXT NULL",
    'link_grupo'        => "ALTER TABLE eventos ADD COLUMN link_grupo VARCHAR(255) NULL",
];
foreach ($novas as $col => $sql) {
    if (!in_array($col, $cols, true)) run_sql($sql, "Coluna `$col` em `eventos`");
    else $msgs[] = ['ok', "✓ Coluna `$col` já existe"];
}

/* ── Programação do evento ── */
run_sql("CREATE TABLE IF NOT EXISTS evento_programacao (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    horario   VARCHAR(80) NULL,
    titulo    VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    ordem     SMALLINT NOT NULL DEFAULT 0,
    KEY idx_ev (evento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tabela `evento_programacao`");

/* ── Campos configuráveis do formulário ── */
run_sql("CREATE TABLE IF NOT EXISTS evento_campos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    evento_id   INT NOT NULL,
    campo       VARCHAR(50) NOT NULL,
    label       VARCHAR(100) NOT NULL,
    tipo        ENUM('text','email','tel','date','select','textarea','checkbox') NOT NULL DEFAULT 'text',
    obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
    ativo       TINYINT(1) NOT NULL DEFAULT 1,
    opcoes      TEXT NULL,
    ordem       SMALLINT NOT NULL DEFAULT 0,
    UNIQUE KEY uk_ev_campo (evento_id, campo),
    KEY idx_ev (evento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tabela `evento_campos`");

$tem_erro = !empty(array_filter($msgs, fn($m) => $m[0] === 'err'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Setup Página do Evento</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 700px; margin: 48px auto; padding: 0 20px; background: #f4f6f9; }
  h1   { color: #1a3a5c; font-size: 1.3rem; margin-bottom: 20px; }
  .row { padding: 9px 14px; border-radius: 7px; margin: 5px 0; font-size: .9rem; }
  .ok  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
  .err { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
  .done { margin-top: 24px; padding: 18px; background: #fff; border-radius: 10px; border-left: 4px solid #16a34a; }
  a { color: #2563a8; }
</style>
</head>
<body>
<h1>Setup — Página Pública do Evento + Campos Configuráveis</h1>
<?php foreach ($msgs as [$t, $m]): ?>
  <div class="row <?= $t ?>"><?= htmlspecialchars($m) ?></div>
<?php endforeach; ?>
<?php if (!$tem_erro): ?>
  <div class="done">
    <strong>✅ Migração concluída com sucesso!</strong><br><br>
    <strong>APAGUE ESTE ARQUIVO DO SERVIDOR.</strong><br><br>
    <a href="/portal/inscricoes/">← Ir para Inscrições</a>
  </div>
<?php endif; ?>
</body>
</html>
