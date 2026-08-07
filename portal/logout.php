<?php
require_once __DIR__ . '/auth.php';

// Limpa o token de sessão única — evita reaproveitamento caso o cookie de
// sessão do navegador ainda exista de alguma forma após o logout
if (!empty($_SESSION['usuario_id'])) {
    try {
        db()->prepare("UPDATE usuarios SET sessao_token = NULL WHERE id = ?")->execute([$_SESSION['usuario_id']]);
    } catch (Exception $e) {}
}

// Revoga o dispositivo confiável (cookie de bypass de MFA) além da sessão
$token = $_COOKIE[MFA_COOKIE] ?? '';
if ($token) {
    try {
        db()->prepare("DELETE FROM mfa_dispositivos WHERE token_hash = ?")->execute([hash('sha256', $token)]);
    } catch (Exception $e) {}
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

setcookie(MFA_COOKIE, '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Strict',
]);

$_SESSION = [];
session_destroy();
header('Location: /portal/login.php');
exit;
