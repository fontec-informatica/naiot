<?php
// REMOVER APÓS O TESTE
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

echo '<pre>';
echo 'SMTP_HOST: ' . SMTP_HOST . "\n";
echo 'SMTP_PORT: ' . SMTP_PORT . "\n";
echo 'SMTP_USER: ' . SMTP_USER . "\n";
echo 'SMTP_PASS: ' . (SMTP_PASS ? '*** definida (' . strlen(SMTP_PASS) . ' chars)' : '⚠️ VAZIA') . "\n\n";

// Verifica se os arquivos do PHPMailer existem
$files = [
    __DIR__ . '/lib/phpmailer/PHPMailer.php',
    __DIR__ . '/lib/phpmailer/SMTP.php',
    __DIR__ . '/lib/phpmailer/Exception.php',
];
foreach ($files as $f) {
    echo (file_exists($f) ? '✓' : '✗ FALTANDO') . ' ' . basename($f) . "\n";
}
echo "\n";

// Tenta enviar
$destino = 'caioalissonsousa@gmail.com';
echo "Enviando para: $destino\n";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug  = SMTP::DEBUG_SERVER; // mostra toda a conversa SMTP
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($destino);
    $mail->isHTML(true);
    $mail->Subject = 'Teste SMTP — Portal NAIOT';
    $mail->Body    = '<p>Se você recebeu este e-mail, o SMTP está funcionando!</p>';

    $mail->send();
    echo "\n✅ E-mail enviado com sucesso!\n";
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $mail->ErrorInfo . "\n";
}
echo '</pre>';
