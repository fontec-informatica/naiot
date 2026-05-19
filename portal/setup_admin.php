<?php
/**
 * Execute UMA ÚNICA VEZ, depois DELETE este arquivo.
 */

require_once __DIR__ . '/config.php';

try {
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

    $email = 'admin@naiot.com.br';
    $senha = 'Naiot@2024';
    $hash  = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);

    if (!$stmt->fetch()) {
        db()->prepare('INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?,?,?,?)')
            ->execute(['Administrador', $email, $hash, 'admin']);
        echo '<p style="color:green;font-size:1.2rem">&#10004; Tabela criada e admin inserido!</p>';
    } else {
        echo '<p style="color:orange">&#9888; Admin ja existe. Nada alterado.</p>';
    }

    echo '<br><strong>E-mail:</strong> ' . $email . '<br>';
    echo '<strong>Senha:</strong> ' . $senha . '<br><br>';
    echo '<p style="color:red"><strong>APAGUE ESTE ARQUIVO AGORA!</strong></p>';

} catch (Exception $e) {
    echo '<p style="color:red"><strong>Erro de conexao com o banco:</strong><br>';
    echo htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Verifique as credenciais em <code>portal/config.php</code></p>';
}
