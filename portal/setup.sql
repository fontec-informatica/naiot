-- Executar uma única vez no MySQL do servidor
-- Ajuste o nome do banco conforme sua hospedagem

CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(120)  NOT NULL,
    email         VARCHAR(180)  NOT NULL UNIQUE,
    senha_hash    VARCHAR(255)  NOT NULL,
    perfil        VARCHAR(255) NOT NULL DEFAULT 'secretaria',
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
