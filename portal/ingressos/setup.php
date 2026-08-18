<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin']);

$pdo = db();

try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS ingressos_campanhas (
      id            INT AUTO_INCREMENT PRIMARY KEY,
      nome          VARCHAR(200)  NOT NULL,
      descricao     VARCHAR(255)  NULL,
      data_evento   DATE          NULL,
      valor_mesa    DECIMAL(10,2) NOT NULL DEFAULT 0,
      valor_individual DECIMAL(10,2) NOT NULL DEFAULT 0,
      ativo         TINYINT(1)    NOT NULL DEFAULT 1,
      criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS ingressos_tickets (
      id                INT AUTO_INCREMENT PRIMARY KEY,
      campanha_id       INT            NOT NULL,
      numero            SMALLINT UNSIGNED NOT NULL,
      tipo              ENUM('mesa','individual') NOT NULL DEFAULT 'mesa',
      valor             DECIMAL(10,2)  NOT NULL DEFAULT 0,
      carimbado         TINYINT(1)     NOT NULL DEFAULT 0,
      assinado          TINYINT(1)     NOT NULL DEFAULT 0,
      status            ENUM('estoque','distribuido','pago','devolvido','cancelado') NOT NULL DEFAULT 'estoque',
      servo_id          INT            NULL,
      servo_nome        VARCHAR(150)   NULL,
      data_distribuicao DATE           NULL,
      forma_pagamento   ENUM('pix','transferencia','deposito','dinheiro','outro') NULL,
      valor_pago        DECIMAL(10,2)  NULL,
      data_pagamento    DATE           NULL,
      comprovante       VARCHAR(255)   NULL,
      observacao        VARCHAR(255)   NULL,
      criado_em         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      atualizado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_campanha_numero (campanha_id, numero),
      INDEX idx_status (campanha_id, status),
      INDEX idx_servo  (campanha_id, servo_nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $migracoes = [
        "ALTER TABLE ingressos_campanhas ADD COLUMN valor_mesa DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER data_evento",
        "ALTER TABLE ingressos_campanhas ADD COLUMN valor_individual DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER valor_mesa",
    ];
    foreach ($migracoes as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* coluna já existe */ }
    }

    echo '<p style="font-family:sans-serif;padding:20px;color:green">✓ Tabelas de Controle de Ingressos criadas/atualizadas. <a href="/portal/ingressos/">Ir para Controle de Ingressos</a></p>';
} catch (PDOException $e) {
    echo '<pre style="font-family:sans-serif;padding:20px;color:#b00;white-space:pre-wrap">✗ Erro ao criar tabelas:' . "\n\n" . htmlspecialchars($e->getMessage()) . '</pre>';
}
