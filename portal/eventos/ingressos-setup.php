<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin']);

$pdo = db();

try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS evento_ingressos (
      id                INT AUTO_INCREMENT PRIMARY KEY,
      evento_id         INT            NOT NULL,
      numero            SMALLINT UNSIGNED NOT NULL,
      tipo              ENUM('mesa','individual') NOT NULL DEFAULT 'individual',
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
      UNIQUE KEY uniq_evento_numero (evento_id, numero),
      INDEX idx_status (evento_id, status),
      INDEX idx_servo  (evento_id, servo_nome),
      INDEX idx_evento (evento_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo '<p style="font-family:sans-serif;padding:20px;color:green">✓ Tabela de ingressos criada/atualizada. <a href="/portal/eventos/">Ir para Eventos</a></p>';
} catch (PDOException $e) {
    http_response_code(200);
    echo '<pre style="font-family:sans-serif;padding:20px;color:#b00;white-space:pre-wrap">✗ Erro ao criar tabela:' . "\n\n" . htmlspecialchars($e->getMessage()) . '</pre>';
}
