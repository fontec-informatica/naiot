<?php
// SCRIPT DE USO ÚNICO — APAGUE APÓS EXECUTAR
// Acesse: https://naiot.com.br/portal/setup_senha_resets.php?chave=instalar-reset-2025
require_once __DIR__ . '/config.php';

if (($_GET['chave'] ?? '') !== 'instalar-reset-2025') {
    http_response_code(403); exit('Acesso negado.');
}

try {
    db()->exec("
        CREATE TABLE IF NOT EXISTS senha_resets (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id  INT UNSIGNED NOT NULL,
            token_hash  VARCHAR(64)  NOT NULL,
            expira_em   DATETIME     NOT NULL,
            usado       TINYINT(1)   NOT NULL DEFAULT 0,
            criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (usuario_id),
            INDEX idx_token   (token_hash),
            INDEX idx_expira  (expira_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo '✅ Tabela <strong>senha_resets</strong> criada com sucesso! Apague este arquivo agora.';
} catch (Exception $e) {
    echo '❌ ERRO: ' . htmlspecialchars($e->getMessage());
}
