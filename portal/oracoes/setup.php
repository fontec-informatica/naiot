<?php
require_once dirname(__DIR__) . '/auth.php';
requer_admin();

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS oracoes (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  texto         TEXT NOT NULL,
  status        VARCHAR(20) NOT NULL DEFAULT 'pendente',
  criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS testemunhos (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  texto         TEXT NOT NULL,
  status        VARCHAR(20) NOT NULL DEFAULT 'pendente',
  criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$msgs = [];

// Migrar JSON de orações (somente se tabela vazia)
$json_o  = __DIR__ . '/../../data/oracoes.json';
$count_o = (int)$pdo->query("SELECT COUNT(*) FROM oracoes")->fetchColumn();
if ($count_o === 0 && file_exists($json_o)) {
    $dados = json_decode(file_get_contents($json_o), true) ?: [];
    if ($dados) {
        $st = $pdo->prepare("INSERT INTO oracoes (texto, status, criado_em) VALUES (?, 'pendente', ?)");
        foreach ($dados as $item) {
            $d  = DateTime::createFromFormat('d/m/Y \à\s H:i', $item['data'] ?? '');
            $ts = $d ? $d->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            $st->execute([$item['texto'], $ts]);
        }
        $msgs[] = '✓ ' . count($dados) . ' orações migradas do JSON — aguardando moderação.';
    }
} else {
    $msgs[] = 'Orações: ' . $count_o . ' registros já existentes — migração ignorada.';
}

// Migrar JSON de testemunhos (somente se tabela vazia)
$json_t  = __DIR__ . '/../../data/testemunhos.json';
$count_t = (int)$pdo->query("SELECT COUNT(*) FROM testemunhos")->fetchColumn();
if ($count_t === 0 && file_exists($json_t)) {
    $dados = json_decode(file_get_contents($json_t), true) ?: [];
    if ($dados) {
        $st = $pdo->prepare("INSERT INTO testemunhos (texto, status, criado_em) VALUES (?, 'pendente', ?)");
        foreach ($dados as $item) {
            $d  = DateTime::createFromFormat('d/m/Y \à\s H:i', $item['data'] ?? '');
            $ts = $d ? $d->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            $st->execute([$item['texto'], $ts]);
        }
        $msgs[] = '✓ ' . count($dados) . ' testemunhos migrados do JSON — aguardando moderação.';
    }
} else {
    $msgs[] = 'Testemunhos: ' . $count_t . ' registros já existentes — migração ignorada.';
}

foreach ($msgs as $msg) {
    echo '<p style="font-family:sans-serif;padding:4px 20px;color:green">' . htmlspecialchars($msg) . '</p>';
}
echo '<p style="font-family:sans-serif;padding:4px 20px;color:green">✓ Setup concluído. <a href="/portal/oracoes/">Ir para Orações &amp; Testemunhos</a></p>';
