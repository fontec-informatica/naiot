<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin']);

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS membros (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(150) NOT NULL,
  foto          VARCHAR(255),
  data_nasc     DATE,
  endereco      VARCHAR(255),
  bairro        VARCHAR(100),
  cidade        VARCHAR(100),
  telefone      VARCHAR(30),
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS membros_grupos (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nome      VARCHAR(100) NOT NULL,
  descricao TEXT,
  cor       VARCHAR(7) NOT NULL DEFAULT '#1e6b35',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS membros_grupo_rel (
  grupo_id  INT NOT NULL,
  membro_id INT NOT NULL,
  PRIMARY KEY (grupo_id, membro_id),
  FOREIGN KEY (grupo_id)  REFERENCES membros_grupos(id) ON DELETE CASCADE,
  FOREIGN KEY (membro_id) REFERENCES membros(id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS membros_cargos (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nome      VARCHAR(100) NOT NULL,
  descricao TEXT,
  cor       VARCHAR(7) NOT NULL DEFAULT '#a87d28',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS membros_cargo_rel (
  cargo_id  INT NOT NULL,
  membro_id INT NOT NULL,
  PRIMARY KEY (cargo_id, membro_id),
  FOREIGN KEY (cargo_id)  REFERENCES membros_cargos(id) ON DELETE CASCADE,
  FOREIGN KEY (membro_id) REFERENCES membros(id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS membros_habilidades (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nome      VARCHAR(100) NOT NULL,
  descricao TEXT,
  cor       VARCHAR(7) NOT NULL DEFAULT '#1a6b8a',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS membros_habilidade_rel (
  habilidade_id INT NOT NULL,
  membro_id     INT NOT NULL,
  PRIMARY KEY (habilidade_id, membro_id),
  FOREIGN KEY (habilidade_id) REFERENCES membros_habilidades(id) ON DELETE CASCADE,
  FOREIGN KEY (membro_id)     REFERENCES membros(id)             ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS membros_pastoreio (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nome      VARCHAR(100) NOT NULL,
  descricao TEXT,
  cor       VARCHAR(7) NOT NULL DEFAULT '#8b44a8',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS membros_pastoreio_rel (
  pastoreio_id INT NOT NULL,
  membro_id    INT NOT NULL,
  PRIMARY KEY (pastoreio_id, membro_id),
  FOREIGN KEY (pastoreio_id) REFERENCES membros_pastoreio(id) ON DELETE CASCADE,
  FOREIGN KEY (membro_id)    REFERENCES membros(id)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo '<p style="font-family:sans-serif;padding:20px;color:green">✓ Tabelas criadas/verificadas com sucesso. <a href="/portal/membros/">Ir para Membros</a></p>';
