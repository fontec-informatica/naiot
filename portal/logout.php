<?php
require_once __DIR__ . '/auth.php';

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
