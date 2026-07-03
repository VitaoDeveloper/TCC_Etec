<?php
// ponytail: uses PHP mail() — works on production servers with sendmail/ SMTP.
// Upgrade path: replace with PHPMailer + SMTP credentials from settings.json.
function sendMail($to, $subject, $body) {
    $headers = 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
        . 'From: ' . ($_ENV['MAIL_FROM'] ?? 'contato@royaltech.com.br') . "\r\n"
        . 'X-Mailer: PHP/' . phpversion();
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}
