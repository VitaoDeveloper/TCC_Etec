<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail.php';

// Rótulos humanos dos assuntos gravados em e5_contacts.subject.
$contactSubjectLabels = [
    'support' => 'Suporte Técnico',
    'sales' => 'Vendas',
    'shipping' => 'Frete e Entrega',
    'returns' => 'Trocas e Devoluções',
    'other' => 'Outro',
];

// Status de resposta do contato -> rótulo + classe CSS (padrão de status_labels.php).
$contactStatusLabels = [
    'pending' => ['label' => 'Pendente', 'class' => 'status-pending'],
    'answered' => ['label' => 'Respondido', 'class' => 'status-active'],
];

// Persiste o status de envio do e-mail de resposta em e5_contacts,
// repetindo o padrão email_status/email_error já usado em e5_orders.
// O salvamento da resposta nunca quebra por falha de e-mail: erros caem
// aqui como response_email_status = 'failed' + mensagem em response_email_error.
function salvarStatusEmailContato(int $contactId, string $status, ?string $errorMessage = null): void
{
    if (!isset($GLOBALS['pdo'])) {
        include_once __DIR__ . '/../database/connection.php';
    }
    try {
        $stmt = $GLOBALS['pdo']->prepare('UPDATE e5_contacts SET response_email_status = :status, response_email_error = :error WHERE id = :id');
        $stmt->execute([':status' => $status, ':error' => $errorMessage, ':id' => $contactId]);
    } catch (Throwable $e) {
        error_log('Failed to save contact email status: ' . $e->getMessage());
    }
}

// Envia ao cliente o retorno do admin sobre o contato.
// Reutiliza o mecanismo de envio existente (sendMail em includes/mail.php).
function enviarRespostaContato(int $contactId, string $name, string $email, string $subject, string $responseMessage): bool
{
    $storeName = store_config('store_name');

    $body = '<h2>Resposta ao seu contato - ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . '</h2>';
    $body .= '<p>Olá <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>';
    $body .= '<p>Nossa equipe respondeu ao seu contato sobre <strong>' . htmlspecialchars($contactSubjectLabels[$subject] ?? $subject, ENT_QUOTES, 'UTF-8') . '</strong>:</p>';
    $body .= '<div style="border-left:4px solid #d4af37; padding:12px 16px; margin:16px 0; background:#f9f9f9;">' . nl2br(htmlspecialchars($responseMessage, ENT_QUOTES, 'UTF-8')) . '</div>';
    $body .= '<p>Você também pode acompanhar este retorno em <strong>Minha Conta → Meus Contatos</strong>.</p>';
    $body .= '<p>Atenciosamente,<br>' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . '</p>';

    return sendMail($email, 'Resposta ao seu contato - ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'), $body);
}