<?php
// REMOVER APÓS O TESTE
require_once __DIR__ . '/config.php';

echo '<pre>';
echo 'SMTP_HOST: ' . SMTP_HOST . "\n";
echo 'SMTP_PORT: ' . SMTP_PORT . "\n";
echo 'SMTP_USER: ' . SMTP_USER . "\n";
echo 'SMTP_PASS: ' . (SMTP_PASS ? '*** definida (' . strlen(SMTP_PASS) . ' chars)' : '⚠️ VAZIA') . "\n\n";

$destino = 'caioalissonsousa@gmail.com';
echo "Enviando para: $destino\n\n";

require_once __DIR__ . '/lib/phpmailer/Exception.php';
require_once __DIR__ . '/lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/lib/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->AuthType   = 'PLAIN';
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

    echo 'AuthType definido: ' . $mail->AuthType . "\n\n";

    $mail->send();
    echo "\n✅ E-mail enviado com sucesso!\n";
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $mail->ErrorInfo . "\n";
}
echo '</pre>';
