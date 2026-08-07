<?php
require_once dirname(__DIR__) . '/auth.php';
requer_login();

header('Content-Type: application/json; charset=utf-8');

$cep = preg_replace('/\D/', '', $_GET['cep'] ?? '');
if (strlen($cep) !== 8) {
    http_response_code(400);
    echo json_encode(['erro' => true]);
    exit;
}

$url  = "https://viacep.com.br/ws/{$cep}/json/";
$json = false;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $json = curl_exec($ch);
    curl_close($ch);
} else {
    $ctx  = stream_context_create(['http' => ['timeout' => 8]]);
    $json = @file_get_contents($url, false, $ctx);
}

if (!$json) {
    http_response_code(503);
    echo json_encode(['erro' => true]);
    exit;
}

$dados = json_decode($json, true);
if (!is_array($dados) || !empty($dados['erro'])) {
    echo json_encode(['erro' => true]);
    exit;
}

echo json_encode([
    'erro'       => false,
    'logradouro' => $dados['logradouro'] ?? '',
    'bairro'     => $dados['bairro'] ?? '',
    'localidade' => $dados['localidade'] ?? '',
    'uf'         => $dados['uf'] ?? '',
], JSON_UNESCAPED_UNICODE);
