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

    // Timeout de conexão/OI razoável (default do PHPMailer é 300s: um host SMTP
    // atrás de firewall segura a requisição por até 5 minutos "sem erro claro").
    $mail->Timeout = 15;

    // Alvo SMTP efetivo (host:porta) exposto para setMailError()/logs/debug —
    // sem credenciais. Ajuda a identificar "para onde" o PHPMailer tentou falar.
    $GLOBALS['mail_smtp_target'] = $mail->Host . ':' . $mail->Port;

    switch (strtolower(trim($_ENV['MAIL_ENCRYPTION'] ?? ''))) {
        case 'tls':
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            break;
        case 'ssl':
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            break;
        case 'none':
            // Escolha explícita por conexão sem criptografia (ex.: Mailpit local).
            $mail->SMTPAutoTLS = false;
            break;
        default:
            // MAIL_ENCRYPTION vazio: o PHPMailer negocia STARTTLS automaticamente
            // (SMTPAutoTLS padrão = true) quando o servidor anuncia suporte. Isso é
            // obrigatório para provedores reais com autenticação (Gmail/Outlook/SES,
            // porta 587); desligar o auto-TLS aqui era a causa de "FALHA NO ENVIO".
            break;
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

// Devolve uma dica acionável conforme a natureza da falha, sem expor
// credenciais. Mensagens da PHPMailer não contêm senha; o texto adicionado
// aqui também não.
function smtpFailureHint(string $message): string
{
    $m = mb_strtolower($message);
    if (str_contains($m, 'connection refused')
        || str_contains($m, 'failed to connect')
        || str_contains($m, 'smtp code: 111')
        || str_contains($m, 'smtp code: 110')) {
        return 'Dica: o servidor SMTP configurado esta inacessivel (fora do ar ou host/porta errados para este ambiente). Em Docker Compose, MAIL_HOST deve ser o nome do servico (ex.: mailpit), nao "localhost".';
    }
    if (str_contains($m, 'authenticate') || str_contains($m, 'authentication')) {
        return 'Dica: provavel problema de credencial ou metodo de autenticacao (verifique MAIL_USERNAME/MAIL_PASSWORD e MAIL_ENCRYPTION).';
    }
    if (str_contains($m, 'sender address rejected') || str_contains($m, 'from address')) {
        return 'Dica: o remetente (From) pode nao estar autorizado/verificado no provedor (revise MAIL_FROM / store_email).';
    }
    if (str_contains($m, 'recipient') || str_contains($m, 'mailbox unavailable')) {
        return 'Dica: o destinatario pode ter sido rejeitado (endereco invalido, dominio inexistente ou caixa cheia).';
    }
    return '';
}

// Centraliza o registro de falha de e-mail: grava no log do servidor e deixa o
// detalhe real (mensagem/código da PHPMailer) acessível via $GLOBALS['mail_last_error']
// para os fluxos consumidores persistirem em email_error / response_email_error.
// As mensagens da PHPMailer não contêm credenciais, então logá-las é seguro.
// Acrescenta o alvo SMTP (host:porta) e uma dica acionável — sem expor senha/usuario.
function setMailError(string $message): void
{
    $full = $message;
    $target = $GLOBALS['mail_smtp_target'] ?? '';
    if ($target !== '') {
        $full .= ' [alvo SMTP: ' . $target . ']';
    }
    $hint = smtpFailureHint($message);
    if ($hint !== '') {
        $full .= ' ' . $hint;
    }
    $GLOBALS['mail_last_error'] = $full;
    error_log('Email error: ' . $full);
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
