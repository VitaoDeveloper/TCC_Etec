<?php
// Exporta a lista de clientes em CSV (compatível com Excel/LibreOffice).
// Respeita o filtro de busca "q" aplicado na tela de clientes.
include 'auth_check.php';
include '../../database/connection.php';

$search = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id, name, email, username, cpf, phone, postal_code, street, number,
               notify_email, notify_whatsapp, created_at
        FROM e5_users WHERE role = :role';
$params = [':role' => 'customer'];
if ($search !== '') {
    $sql .= ' AND (name LIKE :q_name OR email LIKE :q_email OR username LIKE :q_username)';
    $pattern = '%' . $search . '%';
    $params[':q_name'] = $pattern;
    $params[':q_email'] = $pattern;
    $params[':q_username'] = $pattern;
}
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'clientes-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');

// BOM UTF-8 para o Excel reconhecer acentuação.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['ID', 'Nome', 'E-mail', 'Usuário', 'CPF', 'Telefone', 'CEP', 'Endereço', 'Número', 'Notif. E-mail', 'Notif. WhatsApp', 'Cadastro'], ';');

foreach ($customers as $c) {
    fputcsv($out, [
        (int) $c['id'],
        $c['name'],
        $c['email'],
        $c['username'],
        (string) $c['cpf'],
        (string) $c['phone'],
        (string) $c['postal_code'],
        (string) $c['street'],
        (string) $c['number'],
        ((int) $c['notify_email'] === 1) ? 'Sim' : 'Não',
        ((int) $c['notify_whatsapp'] === 1) ? 'Sim' : 'Não',
        date('d/m/Y H:i', strtotime($c['created_at'])),
    ], ';');
}

fclose($out);
exit;
