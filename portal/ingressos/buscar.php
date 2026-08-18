<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

$like = '%' . $q . '%';
try {
    $st = db()->prepare("
        SELECT id, nome, telefone
        FROM membros
        WHERE ativo = 1 AND (nome LIKE ? OR telefone LIKE ?)
        ORDER BY nome LIMIT 12
    ");
    $st->execute([$like, $like]);
    echo json_encode($st->fetchAll());
} catch (PDOException $e) {
    echo json_encode([]);
}
