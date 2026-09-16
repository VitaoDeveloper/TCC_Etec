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

    // Degrada com falha capturável em vez de fatal error se o composer não
    // foi executado no ambiente de destino.
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        throw new RuntimeException('PHPMailer não instalado. Execute "composer install".');
    }
    require_once __DIR__ . '/../vendor/autoload.php';

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

// Centraliza o registro de falha de e-mail: grava no log do servidor e deixa o
// detalhe real (mensagem/código da PHPMailer) acessível via $GLOBALS['mail_last_error']
// para os fluxos consumidores persistirem em email_error / response_email_error.
// As mensagens da PHPMailer não contêm credenciais, então logá-las é seguro.
function setMailError(string $message): void
{
    $GLOBALS['mail_last_error'] = $message;
    error_log('Email error: ' . $message);
}

// Envia um e-mail HTML reaproveitando o cliente persistente.
function sendMail(string $to, string $subject, string $body): bool
{
    $mail = null;
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
        $ok = $mail->send();

        // PHPMailer também pode retornar false sem lançar exceção.
        if (!$ok) {
            $msg = trim((string) $mail->ErrorInfo);
            setMailError($msg !== '' ? $msg : 'PHPMailer retornou false sem detalhes.');
        }
        return $ok;
    } catch (Throwable $e) {
        $msg = trim((string) $e->getMessage());
        $msg = $msg !== '' ? $msg : get_class($e);
        if ($mail !== null && trim((string) $mail->ErrorInfo) !== '') {
            $msg .= ' | SMTP ErrorInfo: ' . $mail->ErrorInfo;
        }
        setMailError($msg);
        return false;
    }
}
