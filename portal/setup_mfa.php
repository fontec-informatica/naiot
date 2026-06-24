<?php
// SCRIPT DE USO ÚNICO — APAGUE APÓS EXECUTAR
// Acesse: https://naiot.com.br/portal/setup_mfa.php?chave=instalar-mfa-2025
require_once __DIR__ . '/config.php';

if (($_GET['chave'] ?? '') !== 'instalar-mfa-2025') {
    http_response_code(403);
    exit('Acesso negado.');
}

$sqls = [
    "CREATE TABLE IF NOT EXISTS mfa_codigos (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id  INT UNSIGNED NOT NULL,
        codigo_hash VARCHAR(255) NOT NULL,
        expira_em   DATETIME     NOT NULL,
        usado       TINYINT(1)   NOT NULL DEFAULT 0,
        criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario (usuario_id),
        INDEX idx_expira  (expira_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS mfa_dispositivos (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id  INT UNSIGNED NOT NULL,
        token_hash  VARCHAR(64)  NOT NULL,
        ip          VARCHAR(45)  NOT NULL,
        user_agent  VARCHAR(500) NOT NULL DEFAULT '',
        expira_em   DATETIME     NOT NULL,
        criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario (usuario_id),
        INDEX idx_token   (token_hash),
        INDEX idx_expira  (expira_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$ok = true;
foreach ($sqls as $sql) {
    try {
        db()->exec($sql);
        echo "✅ OK: " . substr(trim($sql), 0, 50) . "...<br>";
    } catch (Exception $e) {
        echo "❌ ERRO: " . htmlspecialchars($e->getMessage()) . "<br>";
        $ok = false;
    }
}

if ($ok) {
    echo "<br><strong>✅ Tabelas criadas com sucesso! Apague este arquivo agora.</strong>";
} else {
    echo "<br><strong>❌ Houve erros. Verifique acima.</strong>";
}
