<?php
/**
 * Comprovante de Compra (Não Fiscal) — PDF + HTML + E-mail
 *
 * Gera comprovante de compra como PDF real (via dompdf), com fallback HTML.
 * Envia automaticamente por e-mail ao cliente após confirmação do pedido.
 *
 * NÃO usa terminologia fiscal (Nota Fiscal, NF-e, DANFE) no corpo do documento.
 * NÃO chama nenhuma API de provedor de NF-e.
 */

require_once __DIR__ . '/config.php';

/**
 * Obtém o próximo número sequencial para comprovantes (COMP-XXXXXX).
 */
function obterProximoNumeroComprovante(PDO $pdo): string
{
    try {
        $row = $pdo->query("SELECT setting_value FROM e5_settings WHERE setting_key = 'comprovante_counter'")->fetch();
        $counter = $row ? ((int)$row['setting_value']) + 1 : 1;
    } catch (Throwable $e) {
        $counter = 1;
    }

    $stmt = $pdo->prepare('
        INSERT INTO e5_settings (setting_key, setting_value)
        VALUES (:key, :val)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ');
    $stmt->execute([':key' => 'comprovante_counter', ':val' => (string)$counter]);

    return 'COMP-' . str_pad((string)$counter, 6, '0', STR_PAD_LEFT);
}

/**
 * Gera o HTML completo do Comprovante de Compra (template reutilizável).
 */
function montarHtmlComprovante(array $order, array $items, string $numero): string
{
    $storeName    = store_config('store_name') ?: 'Royal Tech';
    $storeEmail   = store_config('store_email') ?: '';
    $storeAddress = store_config('store_address') ?: '';

    $payLabels = [
        'pix' => 'Pix', 'boleto' => 'Boleto Bancário',
        'credit' => 'Cartão de Crédito', 'delivery' => 'Pagamento na Entrega',
    ];
    $payLabel = $payLabels[$order['payment_method']] ?? $order['payment_method'] ?? '—';

    $addr = $order['user_street'] ?? '';
    if (!empty($order['user_number'])) $addr .= ', ' . $order['user_number'];
    if (!empty($order['user_complement'])) $addr .= ' — ' . $order['user_complement'];
    $cityState = trim(($order['shipping_city'] ?? '') . '/' . ($order['shipping_state'] ?? ''));
    $cep = $order['shipping_postal_code'] ?? $order['user_postal_code'] ?? '';

    $itensHtml = '';
    foreach ($items as $it) {
        $sub = (float)$it['unit_price'] * (int)$it['quantity'];
        $itensHtml .= '<tr>
            <td style="padding:10px 12px; border-bottom:1px solid #e0e0e0;">'
                . htmlspecialchars($it['name'] ?? 'Produto', ENT_QUOTES, 'UTF-8')
                . ($it['brand'] ? ' <small style="color:#777;">(' . htmlspecialchars($it['brand'], ENT_QUOTES, 'UTF-8') . ')</small>' : '') .
            '</td>
            <td style="padding:10px 12px; border-bottom:1px solid #e0e0e0; text-align:center;">' . (int)$it['quantity'] . '</td>
            <td style="padding:10px 12px; border-bottom:1px solid #e0e0e0; text-align:right;">R$ ' . number_format((float)$it['unit_price'], 2, ',', '.') . '</td>
            <td style="padding:10px 12px; border-bottom:1px solid #e0e0e0; text-align:right; font-weight:600;">R$ ' . number_format($sub, 2, ',', '.') . '</td>
        </tr>';
    }

    $freteLinha = ((float)$order['shipping_cost'] > 0)
        ? '<tr><td style="padding:8px 12px; color:#555;">Frete (' . htmlspecialchars($order['shipping_method'] ?? '—', ENT_QUOTES, 'UTF-8') . ')</td><td style="text-align:right; padding:8px 12px;">R$ ' . number_format((float)$order['shipping_cost'], 2, ',', '.') . '</td></tr>'
        : '<tr><td style="padding:8px 12px; color:#555;">Frete</td><td style="text-align:right; padding:8px 12px; color:#2e7d32;">Grátis</td></tr>';

    $pixDesconto = 0;
    $subtotalItensCents = 0;
    if (($order['payment_method'] ?? '') === 'pix') {
        foreach ($items as $it) $subtotalItensCents += (int)round((float)$it['unit_price'] * 100) * (int)$it['quantity'];
        $pixDesconto = (int)round($subtotalItensCents * 0.05) / 100;
    }
    $descontoLinha = $pixDesconto > 0
        ? '<tr><td style="padding:8px 12px; color:#2e7d32;">Desconto Pix (5%)</td><td style="text-align:right; padding:8px 12px; color:#2e7d32;">− R$ ' . number_format($pixDesconto, 2, ',', '.') . '</td></tr>'
        : '';

    return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Comprovante de Compra ' . $numero . ' — ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . '</title>
<style>
  @media print { body { margin: 0; } .no-print { display: none !important; } }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "Segoe UI", Arial, sans-serif; background: #f5f5f5; color: #333; }
  .container { max-width: 700px; margin: 30px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.08); overflow: hidden; }
  .header { background: #1a1a1a; color: #d4af37; padding: 30px; text-align: center; }
  .header h1 { font-size: 1.5rem; letter-spacing: 2px; margin-bottom: 4px; }
  .header .numero { font-size: 0.9rem; color: #ccc; }
  .disclaimer { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 20px; font-size: 0.85rem; color: #856404; margin: 20px 24px 0; border-radius: 4px; }
  .section { padding: 20px 24px; }
  .section h2 { font-size: 0.95rem; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 6px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .grid p { font-size: 0.9rem; line-height: 1.5; }
  .grid .label { color: #999; font-size: 0.8rem; text-transform: uppercase; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
  table.items th { background: #f9f9f9; padding: 10px 12px; text-align: left; font-size: 0.8rem; color: #888; text-transform: uppercase; }
  table.items th:nth-child(2), table.items th:nth-child(3), table.items th:nth-child(4) { text-align: right; }
  .totals { padding: 16px 24px; border-top: 2px solid #eee; }
  .totals table { width: 100%; }
  .totals td { padding: 6px 0; font-size: 0.95rem; }
  .totals .total-row td { font-size: 1.1rem; font-weight: 700; color: #1a1a1a; border-top: 2px solid #1a1a1a; padding-top: 10px; }
  .footer { background: #f9f9f9; padding: 20px 24px; font-size: 0.8rem; color: #888; text-align: center; border-top: 1px solid #eee; }
  .btn-print { display: inline-block; margin: 20px auto; padding: 10px 24px; background: #d4af37; color: #1a1a1a; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.95rem; }
  .btn-print:hover { background: #c9a22e; }
  .actions { text-align: center; padding: 10px 24px 24px; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>COMPROVANTE DE COMPRA</h1>
    <div class="numero">' . $numero . '</div>
  </div>
  <div class="disclaimer">
    <strong>Este documento não possui valor fiscal e não substitui a Nota Fiscal Eletrônica.</strong><br>
    Comprovante de compra válido como recibo de transação entre as partes.
  </div>
  <div class="section">
    <h2>Dados da Compra</h2>
    <div class="grid">
      <div>
        <p><span class="label">Número do Pedido</span><br><strong>#' . str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT) . '</strong></p>
        <p style="margin-top:8px;"><span class="label">Data da Compra</span><br>' . date('d/m/Y \à\s H:i', strtotime($order['created_at'])) . '</p>
        <p style="margin-top:8px;"><span class="label">Forma de Pagamento</span><br>' . $payLabel . '</p>
      </div>
      <div>
        <p><span class="label">Status do Pedido</span><br>' . ucfirst($order['status']) . '</p>
        <p style="margin-top:8px;"><span class="label">Status do Pagamento</span><br>'
          . ($order['payment_status'] === 'paid' ? '<span style="color:#2e7d32;">Pago</span>' : 'Pendente') . '</p>
      </div>
    </div>
  </div>
  <div class="section">
    <h2>Itens</h2>
    <table class="items">
      <thead><tr><th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Subtotal</th></tr></thead>
      <tbody>' . $itensHtml . '</tbody>
    </table>
  </div>
  <div class="totals">
    <table>' . $freteLinha . $descontoLinha . '
      <tr class="total-row"><td>Total</td><td style="text-align:right;">R$ ' . number_format((float)$order['total'], 2, ',', '.') . '</td></tr>
    </table>
  </div>
  <div class="section">
    <h2>Endereço de Entrega</h2>
    <p style="font-size:0.9rem; line-height:1.6;">'
      . htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') . '<br>'
      . htmlspecialchars($addr, ENT_QUOTES, 'UTF-8') . '<br>'
      . htmlspecialchars($cityState, ENT_QUOTES, 'UTF-8') . '<br>'
      . 'CEP: ' . htmlspecialchars($cep, ENT_QUOTES, 'UTF-8') . '</p>
  </div>
  <div class="section">
    <h2>Vendedor</h2>
    <p style="font-size:0.9rem; line-height:1.6;"><strong>' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . '</strong><br>'
      . ($storeEmail ? htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8') . '<br>' : '')
      . ($storeAddress ? htmlspecialchars($storeAddress, ENT_QUOTES, 'UTF-8') : '') . '</p>
  </div>
  <div class="actions no-print">
    <button class="btn-print" onclick="window.print();"><i class="fas fa-print"></i> Imprimir / Salvar PDF</button>
  </div>
  <div class="footer">
    ' . $numero . ' — ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . '<br>
    Gerado em ' . date('d/m/Y \à\s H:i:s') . '<br>
    <em>Este documento não possui valor fiscal e não substitui a Nota Fiscal Eletrônica.</em>
  </div>
</div>
</body>
</html>';
}

/**
 * Gera o PDF do comprovante via dompdf. Retorna binário ou false em caso de falha.
 */
function gerarPdfComprovante(string $html): string|false
{
    if (!file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
        error_log('dompdf: vendor/autoload.php ausente');
        return false;
    }
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    try {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    } catch (Throwable $e) {
        error_log('dompdf render failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Gera comprovante completo (HTML + PDF opcional) e salva em storage/.
 *
 * @return array ['html' => string, 'numero' => string, 'pdf' => string|null]
 */
function gerarComprovanteCompra(PDO $pdo, int $orderId, bool $salvar = true): array
{
    $stmt = $pdo->prepare('
        SELECT o.*, u.name AS customer_name, u.email AS customer_email,
               u.postal_code AS user_postal_code, u.street AS user_street,
               u.number AS user_number, u.complement AS user_complement
        FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id
        WHERE o.id = :id LIMIT 1
    ');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) throw new RuntimeException('Pedido #' . $orderId . ' não encontrado.');

    $stmtItems = $pdo->prepare('SELECT oi.*, p.name, p.brand FROM e5_order_items oi INNER JOIN e5_products p ON p.id = oi.product_id WHERE oi.order_id = :oid');
    $stmtItems->execute([':oid' => $orderId]);
    $items = $stmtItems->fetchAll();

    $numero = obterProximoNumeroComprovante($pdo);
    $html = montarHtmlComprovante($order, $items, $numero);

    // Salvar + gerar PDF
    $pdfBinary = null;
    if ($salvar) {
        $dir = dirname(__DIR__) . '/storage/comprovantes';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/' . $numero . '_pedido' . $orderId . '.html', $html);
        $pdf = gerarPdfComprovante($html);
        if ($pdf !== false) {
            file_put_contents($dir . '/' . $numero . '_pedido' . $orderId . '.pdf', $pdf);
            $pdfBinary = $pdf;
        }
    }

    $pdo->prepare('UPDATE e5_orders SET invoice_number = :num, invoice_status = :status WHERE id = :id')
        ->execute([':num' => $numero, ':status' => 'issued', ':id' => $orderId]);

    return ['html' => $html, 'numero' => $numero, 'pdf' => $pdfBinary];
}

/**
 * Envia o comprovante de compra por e-mail ao cliente (PHPMailer, via mail.php).
 *
 * NÃO trava a transação — retorna true/false silenciosamente.
 */
function enviarComprovanteEmail(PDO $pdo, int $orderId): bool
{
    if (!file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
        error_log('enviarComprovanteEmail: vendor ausente, email ignorado');
        return false;
    }

    require_once __DIR__ . '/mail.php';

    $stmt = $pdo->prepare('SELECT o.*, u.name AS customer_name, u.email AS customer_email FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order || empty($order['customer_email'])) return false;

    $numero = $order['invoice_number'] ?? null;
    if (!$numero) return false;

    $storeName = store_config('store_name') ?: 'Royal Tech';
    $numeroFmt = '#' . str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT);

    $body = '<h2 style="color:#1a1a1a;">Seu comprovante de compra</h2>'
        . '<p>Olá ' . htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>O comprovante da sua compra foi gerado com sucesso:</p>'
        . '<table style="width:100%; border-collapse:collapse; margin:16px 0;">'
        . '<tr><td style="padding:8px; border-bottom:1px solid #eee; color:#888;">Pedido</td><td style="padding:8px; border-bottom:1px solid #eee;"><strong>' . $numeroFmt . '</strong></td></tr>'
        . '<tr><td style="padding:8px; border-bottom:1px solid #eee; color:#888;">Comprovante</td><td style="padding:8px; border-bottom:1px solid #eee;">' . $numero . '</td></tr>'
        . '<tr><td style="padding:8px; border-bottom:1px solid #eee; color:#888;">Total</td><td style="padding:8px; border-bottom:1px solid #eee; font-weight:700; color:#d4af37;">R$ ' . number_format((float)$order['total'], 2, ',', '.') . '</td></tr>'
        . '<tr><td style="padding:8px; color:#888;">Pagamento</td><td style="padding:8px;">' . ucfirst($order['payment_method'] ?? '—') . '</td></tr>'
        . '</table>'
        . '<p style="font-size:0.85rem; color:#888; margin-top:20px;">O PDF do comprovante está anexado a este e-mail.<br>'
        . 'Acesse sua conta para visualizar: <a href="http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/TCC_Etec/pages/auth/order-detail.php?id=' . $orderId . '">Ver pedido</a></p>'
        . '<hr style="border:none; border-top:1px solid #eee; margin:20px 0;">'
        . '<p style="font-size:0.8rem; color:#aaa; text-align:center;">Este documento não possui valor fiscal e não substitui a Nota Fiscal Eletrônica.</p>';

    $subject = 'Seu comprovante de compra — Pedido ' . $numeroFmt . ' — ' . $storeName;

    // Caminho do PDF salvo
    $pdfPath = dirname(__DIR__) . '/storage/comprovantes/' . $numero . '_pedido' . $orderId . '.pdf';

    try {
        $mail = mailer();
        $mail->clearAllRecipients();
        $mail->clearReplyTos();
        $mail->clearAttachments();
        $mail->addAddress($order['customer_email']);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $body));
        $mail->isHTML(true);

        if (file_exists($pdfPath)) {
            $mail->addAttachment($pdfPath, $numero . '.pdf');
        }

        $result = $mail->send();

        // Atualizar status no pedido
        $pdo->prepare("UPDATE e5_orders SET invoice_error_message = :msg WHERE id = :id")
            ->execute([':msg' => $result ? 'email_sent' : 'email_failed', ':id' => $orderId]);

        return $result;
    } catch (Throwable $e) {
        error_log('enviarComprovanteEmail failed order#' . $orderId . ': ' . $e->getMessage());
        $pdo->prepare("UPDATE e5_orders SET invoice_error_message = :msg WHERE id = :id")
            ->execute([':msg' => 'email_error: ' . substr($e->getMessage(), 0, 200), ':id' => $orderId]);
        return false;
    }
}
