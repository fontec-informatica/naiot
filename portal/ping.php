<?php
echo 'PHP ' . PHP_VERSION . ' funcionando.<br>';
echo 'PDO disponivel: ' . (extension_loaded('pdo_mysql') ? 'SIM' : 'NAO') . '<br>';

require_once __DIR__ . '/config.php';
try {
    db()->query('SELECT 1');
    echo 'Banco de dados: CONECTADO';
} catch (Exception $e) {
    echo 'Banco de dados: ERRO - ' . htmlspecialchars($e->getMessage());
}
