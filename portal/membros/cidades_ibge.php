<?php
require_once dirname(__DIR__) . '/auth.php';
requer_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$cache_file = __DIR__ . '/cidades_cache.json';
$cache_ttl  = 30 * 24 * 3600; // 30 dias

// Retorna cache se ainda válido
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    readfile($cache_file);
    exit;
}

// Busca na API do IBGE
$url  = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios?orderBy=nome';
$json = false;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $json = curl_exec($ch);
    curl_close($ch);
} else {
    $ctx  = stream_context_create(['http' => ['timeout' => 15]]);
    $json = @file_get_contents($url, false, $ctx);
}

if (!$json) {
    // Falhou: se tiver cache antigo, usa mesmo assim
    if (file_exists($cache_file)) { readfile($cache_file); exit; }
    http_response_code(503);
    echo '[]';
    exit;
}

$raw     = json_decode($json, true);
$cidades = [];
foreach ($raw as $m) {
    $cidades[] = [
        'nome' => $m['nome'],
        'uf'   => $m['microrregiao']['mesorregiao']['UF']['sigla'],
    ];
}

$out = json_encode($cidades, JSON_UNESCAPED_UNICODE);
file_put_contents($cache_file, $out);
echo $out;
