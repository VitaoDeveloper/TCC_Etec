<?php

require_once __DIR__ . '/config.php';
loadEnv(__DIR__ . '/../.env');

// Acesso ao cliente persistente. A conexão SMTP fica aberta entre envios
// da mesma requisição (SMTPKeepAlive), como o PDO mantém sua conexão.
function mailer(): \PHPMailer\PHPMailer\PHPMailer
{
    if (isset($GLOBALS['mailer'])) {
        return $GLOBALS['mailer'];
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new RuntimeException('PHPMailer não instalado: rode "composer install" (vendor/autoload.php ausente).');
    }
    require_once $autoload;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(false);
    $mail->isSMTP();
    $mail->Host = $_ENV['MAIL_HOST'] ?? 'localhost';
    $mail->Port = (int) ($_ENV['MAIL_PORT'] ?? 1025);

    if (($_ENV['MAIL_USERNAME'] ?? '') !== '') {
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['MAIL_USERNAME'];
        $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
    }

    switch (strtolower(trim($_ENV['MAIL_ENCRYPTION'] ?? ''))) {
        case 'tls':
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            break;
        case 'ssl':
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            break;
        default:
            $mail->SMTPAutoTLS = false;
    }

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->SMTPKeepAlive = true;
    $mail->setFrom(
        $_ENV['MAIL_FROM'] ?? store_config('store_email'),
        store_config('store_name')
    );

    $GLOBALS['mailer'] = $mail;
    return $mail;
}

// Envia um e-mail HTML reaproveitando o cliente persistente.
function sendMail(string $to, string $subject, string $body): bool
{
    try {
        $mail = mailer();
        // Reset do estado por-mensagem; a conexão TCP permanece aberta.
        $mail->clearAllRecipients();
        $mail->clearReplyTos();
        $mail->clearAttachments();
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $alt = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $body)));
        $mail->AltBody = html_entity_decode($alt, ENT_QUOTES, 'UTF-8');
        return $mail->send();
    } catch (Throwable $e) {
        error_log('Mail send failed: ' . $e->getMessage());
        return false;
    }
}
