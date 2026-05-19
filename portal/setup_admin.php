<?php
/**
 * ATENÇÃO: Execute este arquivo UMA ÚNICA VEZ pelo navegador no servidor,
 * depois DELETE-O imediatamente por segurança.
 * Acesse: https://seusite.com.br/portal/setup_admin.php
 */

require_once __DIR__ . '/config.php';

// Cria a tabela de usuários
db()->exec("
    CREATE TABLE IF NOT EXISTS usuarios (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nome          VARCHAR(120)  NOT NULL,
        email         VARCHAR(180)  NOT NULL UNIQUE,
        senha_hash    VARCHAR(255)  NOT NULL,
        perfil        ENUM('admin','financeiro','secretaria') NOT NULL DEFAULT 'secretaria',
        ativo         TINYINT(1)    NOT NULL DEFAULT 1,
        criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ultimo_acesso DATETIME      NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Cria o usuário admin inicial
$email  = 'admin@naiot.com.br';
$senha  = 'Naiot@2024';  // TROQUE APÓS O PRIMEIRO LOGIN
$hash   = password_hash($senha, PASSWORD_DEFAULT);

$existe = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
$existe->execute([$email]);

if (!$existe->fetch()) {
    db()->prepare('INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?,?,?,?)')
        ->execute(['Administrador', $email, $hash, 'admin']);
    echo '<p style="color:green;font-size:1.2rem">✔ Tabela criada e usuário admin inserido com sucesso!</p>';
} else {
    echo '<p style="color:orange">⚠ Usuário admin já existe. Nada foi alterado.</p>';
}

echo '<br><strong>E-mail:</strong> ' . $email . '<br>';
echo '<strong>Senha:</strong> ' . $senha . '<br><br>';
echo '<p style="color:red"><strong>APAGUE ESTE ARQUIVO AGORA!</strong><br>';
echo 'Delete <code>portal/setup_admin.php</code> do servidor.</p>';
