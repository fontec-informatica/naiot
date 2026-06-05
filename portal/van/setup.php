<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin']);

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS van_viagens (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  destino          VARCHAR(200)  NOT NULL,
  data_texto       VARCHAR(100)  NOT NULL,
  data_tipo        ENUM('unico','bate_volta','periodo','livre') NOT NULL DEFAULT 'livre',
  motorista_id     INT           NULL,
  motorista_nome   VARCHAR(150)  NULL,
  motorista_cpf    VARCHAR(20)   NULL,
  coordenador_id   INT           NULL,
  coordenador_nome VARCHAR(150)  NULL,
  coordenador_cpf  VARCHAR(20)   NULL,
  status           ENUM('rascunho','finalizada') NOT NULL DEFAULT 'rascunho',
  criado_em        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (motorista_id)   REFERENCES membros(id) ON DELETE SET NULL,
  FOREIGN KEY (coordenador_id) REFERENCES membros(id) ON DELETE SET NULL
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
  nota      VARCHAR(80)  NULL,
  FOREIGN KEY (viagem_id) REFERENCES van_viagens(id) ON DELETE CASCADE,
  FOREIGN KEY (membro_id) REFERENCES membros(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Migrações para instalações anteriores
$migracoes = [
    "ALTER TABLE membros         ADD COLUMN cpf              VARCHAR(20)  NULL AFTER sexo",
    "ALTER TABLE van_viagens     ADD COLUMN data_tipo        ENUM('unico','bate_volta','periodo','livre') NOT NULL DEFAULT 'livre' AFTER data_texto",
    "ALTER TABLE van_viagens     ADD COLUMN coordenador_id   INT          NULL AFTER motorista_cpf",
    "ALTER TABLE van_viagens     ADD COLUMN coordenador_nome VARCHAR(150) NULL AFTER coordenador_id",
    "ALTER TABLE van_viagens     ADD COLUMN coordenador_cpf  VARCHAR(20)  NULL AFTER coordenador_nome",
    "ALTER TABLE van_passageiros ADD COLUMN nota             VARCHAR(80)  NULL AFTER cpf_rg",
];
foreach ($migracoes as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) { /* coluna já existe */ }
}

echo '<p style="font-family:sans-serif;padding:20px;color:green">✓ Tabelas criadas/atualizadas. <a href="/portal/van/">Ir para Van</a></p>';
