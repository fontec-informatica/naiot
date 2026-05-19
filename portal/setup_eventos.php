<?php
/**
 * Execute UMA ÚNICA VEZ, depois DELETE este arquivo.
 */
require_once __DIR__ . '/config.php';

try {
    db()->exec("
        CREATE TABLE IF NOT EXISTS eventos (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            titulo      VARCHAR(200)  NOT NULL,
            descricao   VARCHAR(500)  NULL,
            data_evento DATE          NULL,
            imagem      VARCHAR(255)  NOT NULL,
            ordem       SMALLINT      NOT NULL DEFAULT 0,
            ativo       TINYINT(1)    NOT NULL DEFAULT 1,
            criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo '<p style="color:green;font-size:1.2rem">&#10004; Tabela <strong>eventos</strong> criada com sucesso!</p>';
    echo '<p style="color:red;margin-top:16px"><strong>APAGUE ESTE ARQUIVO AGORA!</strong></p>';
} catch (Exception $e) {
    echo '<p style="color:red">Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
