<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin','financeiro']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit('Arquivo não encontrado.'); }

$st = db()->prepare("SELECT * FROM financeiro_anexos WHERE id=?");
$st->execute([$id]);
$anx = $st->fetch();
if (!$anx) { http_response_code(404); exit('Arquivo não encontrado.'); }

$caminho = __DIR__ . '/uploads/' . basename($anx['nome_arquivo']);
if (!file_exists($caminho)) { http_response_code(404); exit('Arquivo não encontrado no disco.'); }

$inline_types = ['application/pdf','image/jpeg','image/png','image/webp','image/gif'];
$disposition  = in_array($anx['tipo_mime'], $inline_types) ? 'inline' : 'attachment';

header('Content-Type: ' . $anx['tipo_mime']);
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($anx['nome_original']) . '"');
header('Content-Length: ' . $anx['tamanho']);
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($caminho);
exit;
