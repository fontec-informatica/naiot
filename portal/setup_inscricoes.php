<?php
require_once __DIR__ . '/config.php';

$msgs = [];

function run_sql(string $sql, string $label): void {
    global $msgs;
    try {
        db()->exec($sql);
        $msgs[] = ['ok', "✓ $label"];
    } catch (Exception $e) {
        $msgs[] = ['err', "✗ $label — " . $e->getMessage()];
    }
}

// Colunas novas em eventos
$cols_atuais = db()->query("SHOW COLUMNS FROM eventos")->fetchAll(PDO::FETCH_COLUMN);
$novas_colunas = [
    'inscricoes_abertas' => "ALTER TABLE eventos ADD COLUMN inscricoes_abertas TINYINT(1) NOT NULL DEFAULT 0",
    'vagas'              => "ALTER TABLE eventos ADD COLUMN vagas INT DEFAULT NULL",
    'valor'              => "ALTER TABLE eventos ADD COLUMN valor DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    'data_encerramento'  => "ALTER TABLE eventos ADD COLUMN data_encerramento DATE DEFAULT NULL",
    'local_evento'       => "ALTER TABLE eventos ADD COLUMN local_evento VARCHAR(255) DEFAULT NULL",
    'horario'            => "ALTER TABLE eventos ADD COLUMN horario VARCHAR(80) DEFAULT NULL",
];
foreach ($novas_colunas as $col => $sql) {
    if (!in_array($col, $cols_atuais, true)) {
        run_sql($sql, "Coluna `$col` adicionada à tabela `eventos`");
    } else {
        $msgs[] = ['ok', "✓ Coluna `$col` já existe (pulada)"];
    }
}

// Tabela de lotes
run_sql("CREATE TABLE IF NOT EXISTS evento_lotes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  evento_id    INT NOT NULL,
  nome         VARCHAR(100) NOT NULL,
  descricao    VARCHAR(255) DEFAULT NULL,
  valor        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  vagas        INT DEFAULT NULL,
  data_inicio  DATE DEFAULT NULL,
  data_fim     DATE DEFAULT NULL,
  ativo        TINYINT(1) NOT NULL DEFAULT 1,
  ordem        INT NOT NULL DEFAULT 0,
  KEY idx_evento_id (evento_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4", "Tabela `evento_lotes` criada/verificada");

// Tabela de inscrições
run_sql("CREATE TABLE IF NOT EXISTS inscricoes (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  evento_id        INT NOT NULL,
  lote_id          INT DEFAULT NULL,
  nome             VARCHAR(200) NOT NULL,
  email            VARCHAR(200) NOT NULL,
  telefone         VARCHAR(30)  DEFAULT NULL,
  cpf              VARCHAR(14)  DEFAULT NULL,
  data_nascimento  DATE         DEFAULT NULL,
  valor_pago       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  forma_pagamento  ENUM('gratuito','pix','cartao','boleto') NOT NULL DEFAULT 'gratuito',
  status           ENUM('pendente','confirmado','cancelado','checkin') NOT NULL DEFAULT 'pendente',
  comprovante      VARCHAR(255) DEFAULT NULL,
  observacoes      TEXT         DEFAULT NULL,
  token            VARCHAR(64)  NOT NULL,
  checkin_at       DATETIME     DEFAULT NULL,
  ip               VARCHAR(45)  DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_token (token),
  KEY idx_evento   (evento_id),
  KEY idx_lote     (lote_id),
  KEY idx_status   (status)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4", "Tabela `inscricoes` criada/verificada");

$tem_erro = !empty(array_filter($msgs, fn($m) => $m[0] === 'err'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Setup Inscrições — NAIOT</title>
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
<h1>Setup — Módulo de Inscrições NAIOT</h1>
<?php foreach ($msgs as [$t, $m]): ?>
  <div class="row <?= $t ?>"><?= htmlspecialchars($m) ?></div>
<?php endforeach; ?>
<?php if (!$tem_erro): ?>
  <div class="done">
    <strong>✅ Migração concluída com sucesso!</strong><br><br>
    Você pode excluir este arquivo do servidor agora.<br><br>
    <a href="/portal/">← Ir para o portal administrativo</a>
  </div>
<?php endif; ?>
</body>
</html>
