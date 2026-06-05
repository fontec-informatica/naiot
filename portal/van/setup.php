<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin']);

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS van_viagens (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  destino        VARCHAR(200)  NOT NULL,
  data_texto     VARCHAR(100)  NOT NULL,
  motorista_id   INT           NULL,
  motorista_nome VARCHAR(150)  NULL,
  motorista_cpf  VARCHAR(20)   NULL,
  status         ENUM('rascunho','finalizada') NOT NULL DEFAULT 'rascunho',
  criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (motorista_id) REFERENCES membros(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS van_passageiros (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  viagem_id INT NOT NULL,
  ordem     SMALLINT     NOT NULL DEFAULT 0,
  membro_id INT          NULL,
  nome      VARCHAR(150) NOT NULL,
  cpf_rg    VARCHAR(30)  NULL,
  FOREIGN KEY (viagem_id) REFERENCES van_viagens(id) ON DELETE CASCADE,
  FOREIGN KEY (membro_id) REFERENCES membros(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

try {
    $pdo->exec("ALTER TABLE membros ADD COLUMN cpf VARCHAR(20) NULL DEFAULT NULL AFTER sexo");
} catch (PDOException $e) { /* coluna já existe */ }

echo '<p style="font-family:sans-serif;padding:20px;color:green">✓ Tabelas criadas/verificadas. <a href="/portal/van/">Ir para Van</a></p>';
