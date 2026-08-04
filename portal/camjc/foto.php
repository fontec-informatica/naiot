<?php
require_once dirname(__DIR__) . '/auth.php';
requer_perfil(['admin', 'camjc']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit; }

$st = db()->prepare("SELECT foto FROM camjc_acolhidas WHERE id = ?");
$st->execute([$id]);
$a = $st->fetch();
if (!$a || !$a['foto']) { http_response_code(404); exit; }

$caminho = __DIR__ . '/fotos/' . basename($a['foto']);
if (!file_exists($caminho)) { http_response_code(404); exit; }

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($caminho);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($caminho));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

readfile($caminho);
exit;
