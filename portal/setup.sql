-- Executar uma única vez no MySQL do servidor
-- Ajuste o nome do banco conforme sua hospedagem

CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(120)  NOT NULL,
    usuario       VARCHAR(50)   NULL UNIQUE,
    email         VARCHAR(180)  NOT NULL UNIQUE,
    senha_hash    VARCHAR(255)  NOT NULL,
    perfil        VARCHAR(255)  NOT NULL DEFAULT 'secretaria',
    ativo         TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME      NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuário admin inicial — TROQUE A SENHA APÓS O PRIMEIRO LOGIN
-- Senha padrão: Naiot@2024
INSERT INTO usuarios (nome, email, senha_hash, perfil)
VALUES (
    'Administrador',
    'admin@naiot.com.br',
    '$2y$12$RxNz5D1HfABxr.cXzJr3.O9bLZbHvSwWAJiMzF7KAHGfFAZGcTm.S',
    'admin'
);

-- Tabela para controle de tentativas de login (rate limiting / brute force)
CREATE TABLE IF NOT EXISTS login_tentativas (
    id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip  VARCHAR(45) NOT NULL,
    em  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip),
    INDEX idx_em (em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Códigos MFA enviados por e-mail (válidos por 10 minutos)
CREATE TABLE IF NOT EXISTS mfa_codigos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    expira_em   DATETIME     NOT NULL,
    usado       TINYINT(1)   NOT NULL DEFAULT 0,
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_expira  (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispositivos confiáveis (cookie válido por 30 dias)
CREATE TABLE IF NOT EXISTS mfa_dispositivos (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
