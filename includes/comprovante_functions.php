<?php

require_once __DIR__ . '/config.php';

define('COMPROVANTE_DIR', __DIR__ . '/../storage/comprovantes/');

function getNextComprovanteNumber(): string
{
    if (!isset($GLOBALS['pdo'])) {
        include_once dirname(__DIR__) . '/../database/connection.php';
    }
    try {
        $GLOBALS['pdo']->exec("UPDATE e5_settings SET setting_value = COALESCE(setting_value, '0') + 1 WHERE setting_key = 'comprovante_counter'");
        $stmt = $GLOBALS['pdo']->query("SELECT setting_value FROM e5_settings WHERE setting_key = 'comprovante_counter'");
        $counter = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $counter = (int) @file_get_contents(__DIR__ . '/../storage/.counter') + 1;
        @file_put_contents(__DIR__ . '/../storage/.counter', (string) $counter);
    }
    return 'COMP-' . str_pad((string) $counter, 6, '0', STR_PAD_LEFT);
}

function buildComprovanteHtml(int $orderId): string
{
    if (!isset($GLOBALS['pdo'])) {
        include_once __DIR__ . '/../database/connection.php';
    }

    $order = $GLOBALS['pdo']->prepare('SELECT o.*, u.name AS user_name, u.email AS user_email, u.postal_code, u.street, u.number, u.complement, u.username FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1');
    $order->execute([':id' => $orderId]);
    $order = $order->fetch();

    if (!$order) {
        throw new RuntimeException('Pedido não encontrado.');
    }

    $items = $GLOBALS['pdo']->prepare('SELECT oi.*, p.name AS product_name, p.brand FROM e5_order_items oi INNER JOIN e5_products p ON p.id = oi.product_id WHERE oi.order_id = :oid');
    $items->execute([':oid' => $orderId]);
    $items = $items->fetchAll();

    $payLabel = ['pix' => 'Pix', 'boleto' => 'Boleto', 'credit' => 'Cartão de Crédito', 'delivery' => 'Pagamento na Entrega'];
    $shipMethod = $order['shipping_method'] ?? '—';

    $itemsHtml = '';
    foreach ($items as $it) {
        $itemsHtml .= '<tr>
            <td style="padding:10px;border-bottom:1px solid #eee;">' . htmlspecialchars($it['product_name'], ENT_QUOTES, 'UTF-8') . '</td>
            <td style="padding:10px;border-bottom:1px solid #eee;text-align:center;">' . (int) $it['quantity'] . '</td>
            <td style="padding:10px;border-bottom:1px solid #eee;text-align:right;">R$ ' . number_format((float) $it['unit_price'], 2, ',', '.') . '</td>
            <td style="padding:10px;border-bottom:1px solid #eee;text-align:right;font-weight:700;">R$ ' . number_format((float) $it['unit_price'] * (int) $it['quantity'], 2, ',', '.') . '</td>
        </tr>';
    }

    $subtotal = 0;
    foreach ($items as $it) {
        $subtotal += (float) $it['unit_price'] * (int) $it['quantity'];
    }
    $shippingCost = (float) $order['shipping_cost'];
    $discount = ($order['payment_method'] === 'pix') ? round($subtotal * 0.05, 2) : 0;
    $grandTotal = $subtotal + $shippingCost - $discount;

    $counter = getNextComprovanteNumber();

    return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Compra - ' . $counter . '</title>
    <style>
        @page { margin: 20mm; }
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; color: #1a1a1a; max-width: 800px; margin: 0 auto; padding: 20px; font-size: 14px; line-height: 1.6; }
        .header { text-align: center; border-bottom: 3px solid #d4af37; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #1a1a1a; font-size: 28px; margin: 0 0 5px; }
        .header .subtitle { color: #666; font-size: 14px; margin: 0; }
        .counter { background: #d4af37; color: #1a1a1a; display: inline-block; padding: 8px 20px; font-weight: 700; font-size: 18px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .warning strong { color: #856404; }
        .section { margin-bottom: 25px; }
        .section h3 { color: #d4af37; border-bottom: 1px solid #d4af37; padding-bottom: 5px; font-size: 16px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table th { background: #f8f8f8; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; font-size: 13px; }
        table td { padding: 10px; border-bottom: 1px solid #eee; }
        .total-row { font-weight: 700; font-size: 16px; background: #f9f9f9; }
        .total-row td { border-top: 2px solid #d4af37; padding: 15px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .info-item { padding: 8px 0; }
        .info-label { color: #666; font-size: 12px; text-transform: uppercase; }
        .info-value { font-weight: 600; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #999; font-size: 12px; }
        .brand-name { color: #d4af37; font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        <h1>COMPROVANTE DE COMPRA</h1>
        <p class="subtitle">Royal Tech - Sua Loja de Tecnologia Premium</p>
        <div class="counter">' . $counter . '</div>
    </div>

    <div class="warning">
        <strong>Aviso Importante:</strong> Este documento não possui valor fiscal e não substitui a Nota Fiscal Eletrônica. É apenas um comprovante de transação.
    </div>

    <div class="section">
        <h3>Dados do Pedido</h3>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Número do Pedido</div>
                <div class="info-value">#' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Data</div>
                <div class="info-value">' . date('d/m/Y H:i', strtotime($order['created_at'])) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Forma de Pagamento</div>
                <div class="info-value">' . htmlspecialchars($payLabel[$order['payment_method']] ?? $order['payment_method'], ENT_QUOTES, 'UTF-8') . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Status do Pagamento</div>
                <div class="info-value">' . htmlspecialchars($order['payment_status'] ?? '—', ENT_QUOTES, 'UTF-8') . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Frete</div>
                <div class="info-value">' . htmlspecialchars($shipMethod, ENT_QUOTES, 'UTF-8') . ' ' . ($shippingCost > 0 ? '(R$ ' . number_format($shippingCost, 2, ',', '.') . ')' : '(Grátis)') . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Código de Rastreamento</div>
                <div class="info-value">' . htmlspecialchars(!empty($order['tracking_code']) ? $order['tracking_code'] : '—', ENT_QUOTES, 'UTF-8') . '</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Itens do Pedido</h3>
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th style="text-align:center;">Qtd</th>
                    <th style="text-align:right;">Preço Unit.</th>
                    <th style="text-align:right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                ' . $itemsHtml . '
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" style="text-align:right;">Total</td>
                    <td style="text-align:right;">R$ ' . number_format($grandTotal, 2, ',', '.') . '</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="section">
        <h3>Dados de Entrega</h3>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Destinatário</div>
                <div class="info-value">' . htmlspecialchars($order['user_name'], ENT_QUOTES, 'UTF-8') . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">CPF/CNPJ</div>
                <div class="info-value">' . htmlspecialchars($order['username'] ?? '—', ENT_QUOTES, 'UTF-8') . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Endereço</div>
                <div class="info-value">' . htmlspecialchars($order['street'] ?? '', ENT_QUOTES, 'UTF-8') . ', ' . (int)($order['number'] ?? 0) . (!empty($order['complement']) ? ' - ' . htmlspecialchars($order['complement'], ENT_QUOTES, 'UTF-8') : '') . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Bairro / Cidade / UF</div>
                <div class="info-value">' . htmlspecialchars($order['shipping_neighborhood'] ?? '', ENT_QUOTES, 'UTF-8') . ' - ' . htmlspecialchars($order['shipping_city'] ?? '', ENT_QUOTES, 'UTF-8') . '/' . htmlspecialchars($order['shipping_state'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">CEP</div>
                <div class="info-value">' . htmlspecialchars($order['shipping_postal_code'] ?? $order['postal_code'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Dados do Comprador</h3>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Nome</div>
                <div class="info-value">' . htmlspecialchars($order['user_name'], ENT_QUOTES, 'UTF-8') . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">E-mail</div>
                <div class="info-value">' . htmlspecialchars($order['user_email'], ENT_QUOTES, 'UTF-8') . '</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p><strong class="brand-name">Royal Tech</strong> - Av. Paulista, 1000 - São Paulo, SP</p>
        <p>Este comprovante foi gerado automaticamente. Mantenha este documento para sua referência.</p>
        <p>Data de emissão: ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';
}

function gerarComprovante(int $orderId): array
{
    try {
        if (!isset($GLOBALS['pdo'])) {
            include_once __DIR__ . '/../database/connection.php';
        }

        if (!class_exists('\\Dompdf\\Dompdf')) {
            $autoload = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            if (!class_exists('\\Dompdf\\Dompdf')) {
                throw new RuntimeException('Dependência dompdf não instalada. Execute "composer install".');
            }
        }

        $html = buildComprovanteHtml($orderId);
        $counter = getNextComprovanteNumber();
        $filename = $counter . '.pdf';
        $filepath = COMPROVANTE_DIR . $filename;

        if (!is_dir(COMPROVANTE_DIR)) {
            if (!@mkdir(COMPROVANTE_DIR, 0775, true) && !is_dir(COMPROVANTE_DIR)) {
                throw new RuntimeException('Não foi possível criar o diretório de comprovantes.');
            }
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();

        if (file_put_contents($filepath, $pdfContent) === false) {
            throw new RuntimeException('Não foi possível gravar o comprovante em disco.');
        }

        $GLOBALS['pdo']->prepare('UPDATE e5_orders SET comprovante_filename = :fn WHERE id = :id')
            ->execute([':fn' => $filename, ':id' => $orderId]);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'html' => $html,
        ];
    } catch (Throwable $e) {
        error_log('Comprovante PDF error: ' . $e->getMessage());
        $fallbackHtml = '';
        try {
            $fallbackHtml = buildComprovanteHtml($orderId);
        } catch (Throwable $e2) {
            $fallbackHtml = '<p>Erro ao gerar comprovante.</p>';
        }
        return [
            'success' => false,
            'html' => $fallbackHtml,
            'error' => $e->getMessage(),
        ];
    }
}

function getComprovantePath(int $orderId): ?string
{
    if (!isset($GLOBALS['pdo'])) {
        include_once __DIR__ . '/../database/connection.php';
    }
    $order = $GLOBALS['pdo']->prepare('SELECT comprovante_filename FROM e5_orders WHERE id = :id LIMIT 1');
    $order->execute([':id' => $orderId]);
    $filename = $order->fetchColumn();
    if ($filename) {
        $filepath = COMPROVANTE_DIR . $filename;
        if (file_exists($filepath)) {
            return $filepath;
        }
    }
    return null;
}

function sendComprovanteEmail(int $orderId, string $to, string $comprovanteFilename): bool
{
    try {
        if (!isset($GLOBALS['pdo'])) {
            include_once __DIR__ . '/../database/connection.php';
        }
        if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
            error_log('PHPMailer autoload not found');
            return false;
        }

        require_once __DIR__ . '/../vendor/autoload.php';

        $order = $GLOBALS['pdo']->prepare('SELECT o.*, u.name AS user_name, u.email AS user_email FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1');
        $order->execute([':id' => $orderId]);
        $order = $order->fetch();

        if (!$order) {
            return false;
        }

        $payLabel = ['pix' => 'Pix', 'boleto' => 'Boleto', 'credit' => 'Cartão de Crédito', 'delivery' => 'Pagamento na Entrega'];
        $items = $GLOBALS['pdo']->prepare('SELECT oi.*, p.name AS product_name FROM e5_order_items oi INNER JOIN e5_products p ON p.id = oi.product_id WHERE oi.order_id = :oid');
        $items->execute([':oid' => $orderId]);
        $items = $items->fetchAll();

        $itemsHtml = '';
        foreach ($items as $it) {
            $itemsHtml .= '<tr><td>' . htmlspecialchars($it['product_name'], ENT_QUOTES, 'UTF-8') . '</td><td>' . (int) $it['quantity'] . '</td><td>R$ ' . number_format((float) $it['unit_price'], 2, ',', '.') . '</td></tr>';
        }

        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += (float) $it['unit_price'] * (int) $it['quantity'];
        }
        $shippingCost = (float) $order['shipping_cost'];
        $discount = ($order['payment_method'] === 'pix') ? round($subtotal * 0.05, 2) : 0;
        $grandTotal = $subtotal + $shippingCost - $discount;

        $body = '<h2>Seu Comprovante de Compra</h2>
        <p>Olá <strong>' . htmlspecialchars($order['user_name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>
        <p>Seu pedido foi confirmado com sucesso! Segue o comprovante:</p>
        <table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;"><thead><tr bgcolor="#d4af37"><th>Produto</th><th>Qtd</th><th>Preço</th></tr></thead><tbody>' . $itemsHtml . '</tbody></table>
        <p><strong>Total:</strong> R$ ' . number_format($grandTotal, 2, ',', '.') . '</p>
        <p><strong>Pagamento:</strong> ' . htmlspecialchars($payLabel[$order['payment_method']] ?? $order['payment_method'], ENT_QUOTES, 'UTF-8') . '</p>
        <p>Anexo: comprovante PDF em anexo.</p>';

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
        $mailFrom = !empty($_ENV['MAIL_FROM']) ? $_ENV['MAIL_FROM'] : store_config('store_email');
        $mail->setFrom($mailFrom, store_config('store_name'));
        $mail->addAddress($to);
        $mail->Subject = 'Seu comprovante de compra — Pedido #' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
        $mail->Body = $body;
        $alt = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $body)));
        $mail->AltBody = html_entity_decode($alt, ENT_QUOTES, 'UTF-8');

        $pdfPath = COMPROVANTE_DIR . $comprovanteFilename;
        if (file_exists($pdfPath)) {
            $mail->addAttachment($pdfPath);
        }

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Comprovante email error: ' . $e->getMessage());
        return false;
    }
}

function salvarStatusEmail(int $orderId, string $status, ?string $errorMessage = null): void
{
    if (!isset($GLOBALS['pdo'])) {
        include_once __DIR__ . '/../database/connection.php';
    }
    try {
        $stmt = $GLOBALS['pdo']->prepare('UPDATE e5_orders SET email_status = :status, email_error = :error WHERE id = :id');
        $stmt->execute([':status' => $status, ':error' => $errorMessage, ':id' => $orderId]);
    } catch (Throwable $e) {
        error_log('Failed to save email status: ' . $e->getMessage());
    }
}
