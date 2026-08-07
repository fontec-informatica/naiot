<?php
require_once dirname(__DIR__) . '/auth.php';

// Propositalmente NÃO usa requer_login() aqui — se a sessão estiver
// "inválida" (é justamente o que queremos inspecionar), requer_login()
// redirecionaria antes de mostrar qualquer coisa.
if (!usuario_logado()) { http_response_code(403); exit('Não logado.'); }

header('Content-Type: text/plain; charset=utf-8');

echo "usuario_id (sessão): " . ($_SESSION['usuario_id'] ?? '(vazio)') . "\n";
echo "sessao_token (sessão): " . ($_SESSION['sessao_token'] ?? '(vazio)') . "\n";
echo "sessao_token_valido(): " . (sessao_token_valido() ? 'true' : 'false') . "\n";
echo "PHP session_id: " . session_id() . "\n";

try {
    $st = db()->prepare("SELECT sessao_token FROM usuarios WHERE id = ?");
    $st->execute([$_SESSION['usuario_id']]);
    $atual = $st->fetchColumn();
    echo "sessao_token (banco): " . var_export($atual, true) . "\n";
} catch (Exception $e) {
    echo "Erro ao consultar banco: " . $e->getMessage() . "\n";
}

echo "Hora do servidor: " . date('Y-m-d H:i:s') . "\n";
