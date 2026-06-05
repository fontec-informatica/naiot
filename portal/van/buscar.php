<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'secretaria']);
header('Content-Type: application/json; charset=utf-8');

$tipo = $_GET['tipo'] ?? '';

if ($tipo === 'motoristas') {
    try {
        $rows = db()->query("
            SELECT m.id, m.nome, m.cpf
            FROM membros m
            JOIN membros_habilidade_rel r ON r.membro_id = m.id
            JOIN membros_habilidades h ON h.id = r.habilidade_id
            WHERE m.ativo = 1 AND LOWER(h.nome) LIKE '%motorista%'
            ORDER BY m.nome
        ")->fetchAll();
    } catch (PDOException $e) {
        $rows = [];
    }
    echo json_encode($rows);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

$like = '%' . $q . '%';
try {
    $st = db()->prepare("
        SELECT id, nome, cpf, telefone
        FROM membros
        WHERE ativo = 1 AND (nome LIKE ? OR cpf LIKE ? OR telefone LIKE ?)
        ORDER BY nome LIMIT 12
    ");
    $st->execute([$like, $like, $like]);
} catch (PDOException $e) {
    // cpf coluna ainda não existe — busca só por nome e telefone
    $st = db()->prepare("
        SELECT id, nome, '' AS cpf, telefone
        FROM membros
        WHERE ativo = 1 AND (nome LIKE ? OR telefone LIKE ?)
        ORDER BY nome LIMIT 12
    ");
    $st->execute([$like, $like]);
}
echo json_encode($st->fetchAll());
