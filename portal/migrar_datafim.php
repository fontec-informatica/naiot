<?php
/** Execute UMA ÚNICA VEZ, depois DELETE. */
require_once __DIR__ . '/config.php';
try {
    db()->exec("ALTER TABLE eventos ADD COLUMN data_fim DATE NULL AFTER data_evento");
    echo '<p style="color:green;font-size:1.2rem">&#10004; Coluna <strong>data_fim</strong> adicionada!</p>';
    echo '<p style="color:red;margin-top:16px"><strong>APAGUE ESTE ARQUIVO AGORA!</strong></p>';
} catch (Exception $e) {
    echo '<p style="color:orange">Aviso: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
