<?php

declare(strict_types=1);

namespace TCC\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use TCC\Webhook\WebhookHandler;

// As funções de envio e notificação são arquivos procedurais em includes/,
// carregados por require (o projeto não os coloca no PSR-4). O bootstrap do
// PHPUnit em phpunit.xml já puxa o autoload do Composer, mas não estes dois.
require_once __DIR__ . '/../includes/shipping_functions.php';
require_once __DIR__ . '/../includes/notification_functions.php';

/**
 * Testes do envio e da fila de avisos.
 *
 * Duas partes:
 *   1. Funções puras (parse de endereço, rótulo de serviço, link do
 *      WhatsApp, templates) — sem banco e sem HTTP.
 *   2. WebhookHandler::processEvent() gravando de verdade num SQLite em
 *      memória, com o mesmo esquema de e5_shipments/e5_orders. O SQLite
 *      aceita as tabelas que importam aqui; o resto do schema (ENUM,
 *      ENGINE) é específico do MySQL e não é testado aqui.
 *
 * Não há chamada à SuperFrete nem SMTP: o que se testa é a decisão da
 * aplicação, não a resposta do servidor.
 */
class ShippingNotificationTest extends TestCase
{
    // =====================================================================
    //  shipping_parse_store_address
    // =====================================================================

    public function testParsesStreetNumberCityAndState(): void
    {
        $r = \shipping_parse_store_address('Av. Paulista, 1000 - São Paulo, SP');

        $this->assertSame('Av. Paulista', $r['street']);
        $this->assertSame('1000', $r['number']);
        $this->assertSame('São Paulo', $r['city']);
        $this->assertSame('SP', $r['state']);
    }

    public function testParsesNeighborhoodWhenPresent(): void
    {
        $r = \shipping_parse_store_address('Av. Paulista, 1000 - Bela Vista, São Paulo/SP');

        $this->assertSame('Bela Vista', $r['district']);
        $this->assertSame('São Paulo', $r['city']);
        $this->assertSame('SP', $r['state']);
    }

    public function testParsesAddressWithSingleCityAndState(): void
    {
        $r = \shipping_parse_store_address('Rua das Flores, 45 - Centro, Campinas, SP');

        $this->assertSame('Centro', $r['district']);
        $this->assertSame('Campinas', $r['city']);
    }

    public function testAcceptsAddressWithoutNumber(): void
    {
        $r = \shipping_parse_store_address('Av. Paulista');

        $this->assertSame('Av. Paulista', $r['street']);
        $this->assertSame('', $r['number']);
        $this->assertSame('', $r['city']);
    }

    public function testEmptyAddressYieldsEmptyParts(): void
    {
        $r = \shipping_parse_store_address('   ');

        $this->assertSame('', $r['street']);
        $this->assertSame('', $r['district']);
        $this->assertSame('', $r['state']);
    }

    // =====================================================================
    //  Serviço
    // =====================================================================

    public function testServiceLabelAndIdAreInverses(): void
    {
        $this->assertSame('PAC', \superfrete_service_label('1'));
        $this->assertSame('Sedex', \superfrete_service_label('2'));

        // É isso que permite refazer a etiqueta com o mesmo serviço da que
        // foi cancelada, sem o cliente ver mudança de prazo sem aviso.
        $this->assertSame('1', \shipping_service_id('PAC'));
        $this->assertSame('2', \shipping_service_id('Sedex'));
    }

    public function testUnknownServiceLabelReturnsNull(): void
    {
        $this->assertNull(\shipping_service_id('Turbo'));
        $this->assertNull(\shipping_service_id(null));
    }

    // =====================================================================
    //  WhatsApp
    // =====================================================================

    public function testWhatsappLinkPrefixesCountryAndAreaCodeForTenDigits(): void
    {
        $link = \notification_whatsapp_link('1297814939', 'Olá');

        $this->assertStringStartsWith('https://wa.me/551297814939?', $link);
    }

    public function testWhatsappLinkKeepsThirteenDigitNumberAsIs(): void
    {
        $link = \notification_whatsapp_link('5512978149392', 'Olá');

        $this->assertStringStartsWith('https://wa.me/5512978149392?', $link);
    }

    public function testWhatsappLinkRejectsTooShortNumber(): void
    {
        // Número curto vira link wa.me para alguém real e aleatório.
        $this->assertSame('', \notification_whatsapp_link('12345', 'Olá'));
        $this->assertSame('', \notification_whatsapp_link('', 'Olá'));
    }

    public function testWhatsappLinkUrlEncodesBody(): void
    {
        $link = \notification_whatsapp_link('5512978149392', 'Pedido #0001 entregue');

        $this->assertStringNotContainsString(' ', $link);
        $this->assertStringContainsString('%20', $link);
    }

    // =====================================================================
    //  Templates
    // =====================================================================

    public function testEachEventHasSubjectAndBody(): void
    {
        foreach (['payment_confirmed', 'shipment_created', 'order_delivered', 'order_canceled'] as $event) {
            $tpl = \notification_templates($event, ['order_ref' => '0001', 'customer_name' => 'Maria']);

            $this->assertArrayHasKey('Assunto', $tpl, "evento $event");
            $this->assertArrayHasKey('Corpo', $tpl, "evento $event");
            $this->assertStringContainsString('0001', $tpl['Assunto']);
            $this->assertStringContainsString('Maria', $tpl['Corpo']);
        }
    }

    public function testTrackingAppearsInShipmentTemplates(): void
    {
        $tpl = \notification_templates('shipment_created', [
            'order_ref' => '0007',
            'customer_name' => 'João',
            'tracking'  => 'BR123456789BR',
            'service'   => 'Sedex',
        ]);

        $this->assertStringContainsString('BR123456789BR', $tpl['Corpo']);
        $this->assertStringContainsString('Sedex', $tpl['Corpo']);
    }

    // =====================================================================
    //  Dígitos
    // =====================================================================

    public function testNormalizePhoneAcceptsCountryCodeFromSignup(): void
    {
        // O cadastro guarda com +55; a SuperFrete quer DDD + número. Sem esta
        // normalização, todo cliente que digita o código do país no perfil
        // tem o envio recusado.
        $this->assertSame('12978149392', \TCC\SuperFreteClient::normalizePhone('5512978149392'));
        $this->assertSame('12978149392', \TCC\SuperFreteClient::normalizePhone('(12) 97814-9392'));
        $this->assertSame('12978149392', \TCC\SuperFreteClient::normalizePhone('12 97814 9392'));
    }

    public function testNormalizePhoneStillRejectsShortNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \TCC\SuperFreteClient::normalizePhone('12345');
    }

    public function testOnlyDigitsStripsPunctuation(): void
    {
        $this->assertSame('01310100', \shipping_only_digits('01310-100'));
        $this->assertSame('12345678901234', \shipping_only_digits('12.345.678/9012-34'));
        $this->assertSame('', \shipping_only_digits(null));
    }

    // =====================================================================
    //  processEvent gravando no banco
    // =====================================================================

    public function testWebhookWritesTrackingToShipmentAndOrder(): void
    {
        [$pdo, $orderId] = $this->makeDb();
        $sfId = $this->insertShipment($pdo, $orderId);

        $this->handler($pdo)->run('order.posted', ['id' => $sfId, 'tracking_code' => 'BR999888777BR']);

        $this->assertSame('BR999888777BR', $this->shipmentRow($pdo, $orderId)['tracking_code']);
        $this->assertSame('BR999888777BR', $this->orderRow($pdo, $orderId)['tracking_code']);
        $this->assertSame('shipped', $this->orderRow($pdo, $orderId)['status']);
    }

    public function testWebhookAcceptsSelfTrackingFieldName(): void
    {
        [$pdo, $orderId] = $this->makeDb();
        $sfId = $this->insertShipment($pdo, $orderId);

        // A SuperFrete não usa o mesmo nome de campo em todos os eventos.
        $this->handler($pdo)->run('order.released', ['id' => $sfId, 'self_tracking' => 'AA111222333AA']);

        $this->assertSame('AA111222333AA', $this->shipmentRow($pdo, $orderId)['tracking_code']);
    }

    public function testWebhookMarksOrderDelivered(): void
    {
        [$pdo, $orderId] = $this->makeDb();
        $sfId = $this->insertShipment($pdo, $orderId);

        $this->handler($pdo)->run('order.delivered', ['id' => $sfId, 'tracking_code' => 'BR111111111BR']);

        $row = $this->orderRow($pdo, $orderId);
        $this->assertSame('delivered', $row['status']);
        $this->assertSame('delivered', $this->shipmentRow($pdo, $orderId)['status']);
    }

    public function testWebhookStoresDeliveryEstimate(): void
    {
        [$pdo, $orderId] = $this->makeDb();
        $sfId = $this->insertShipment($pdo, $orderId);

        $this->handler($pdo)->run('order.released', [
            'id'               => $sfId,
            'self_tracking'    => 'BR222222222BR',
            'delivery_min_days' => 2,
            'delivery_max_days' => 6,
        ]);

        $row = $this->shipmentRow($pdo, $orderId);
        $this->assertSame(2, (int) $row['delivery_min_days']);
        $this->assertSame(6, (int) $row['delivery_max_days']);
    }

    public function testWebhookIgnoresLabelWithoutLocalShipment(): void
    {
        [$pdo] = $this->makeDb();

        // Etiqueta criada direto no painel da SuperFrete, fora do site: o
        // pedido 2 está "pending" e nada pode mudar, porque não há linha em
        // e5_shipments que case com a etiqueta.
        $this->handler($pdo)->run('order.delivered', ['id' => '999999', 'tracking_code' => 'BR000']);

        $this->assertSame('pending', $this->orderRow($pdo, 2)['status']);
        $this->assertNull($this->orderRow($pdo, 2)['tracking_code']);
    }

    public function testWebhookQueuesNotificationOnDelivery(): void
    {
        [$pdo, $orderId] = $this->makeDb();
        $sfId = $this->insertShipment($pdo, $orderId);

        $this->handler($pdo)->run('order.delivered', ['id' => $sfId, 'tracking_code' => 'BR555555555BR']);

        $st = $pdo->prepare("SELECT * FROM e5_notifications WHERE order_id = :o AND channel = 'email'");
        $st->execute([':o' => $orderId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows, 'deve enfileirar um e-mail de entrega');
        $this->assertSame('order_delivered', $rows[0]['event_type']);
        $this->assertSame('maria@exemplo.com', $rows[0]['recipient']);
        $this->assertStringContainsString('BR555555555BR', (string) $rows[0]['body']);
    }

    public function testWhatsappNoticeIsNeverMarkedAsSent(): void
    {
        [$pdo, $orderId] = $this->makeDb();
        $sfId = $this->insertShipment($pdo, $orderId);

        $this->handler($pdo)->run('order.delivered', ['id' => $sfId, 'tracking_code' => 'BR666666666BR']);

        $st = $pdo->prepare("SELECT * FROM e5_notifications WHERE order_id = :o AND channel = 'whatsapp'");
        $st->execute([':o' => $orderId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        // Sem provedor de WhatsApp, marcar como enviado seria afirmar uma
        // entrega que a loja não fez. Fica "skipped" com o link pronto.
        $this->assertSame('skipped', $row['status']);
        $this->assertStringContainsString('wa.me/5512978149392', (string) $row['body']);
    }

    public function testRespectsCustomerOptOutOfEmail(): void
    {
        [$pdo, $orderId] = $this->makeDb();
        $sfId = $this->insertShipment($pdo, $orderId);

        $pdo->exec('UPDATE e5_users SET notify_email = 0 WHERE id = 1');

        $this->handler($pdo)->run('order.delivered', ['id' => $sfId, 'tracking_code' => 'BR777777777BR']);

        $st = $pdo->prepare("SELECT COUNT(*) FROM e5_notifications WHERE order_id = :o AND channel = 'email'");
        $st->execute([':o' => $orderId]);

        $this->assertSame(0, (int) $st->fetchColumn());
    }

    public function testWebhookDoesNotRepeatDeliveredState(): void
    {
        [$pdo, $orderId] = $this->makeDb();
        $sfId = $this->insertShipment($pdo, $orderId);
        $handler = $this->handler($pdo);

        $handler->run('order.delivered', ['id' => $sfId, 'tracking_code' => 'BR333333333BR']);
        $handler->run('order.delivered', ['id' => $sfId, 'tracking_code' => 'BR333333333BR']);

        // A SuperFrete reenvia webhooks. O pedido já está "delivered", então
        // o cliente não pode receber um segundo e-mail de entrega.
        $this->assertSame('delivered', $this->orderRow($pdo, $orderId)['status']);
    }

    public function testWebhookFillsTrackingThatArrivesLater(): void
    {
        [$pdo, $orderId] = $this->makeDb();
        $sfId = $this->insertShipment($pdo, $orderId);
        $handler = $this->handler($pdo);

        $handler->run('order.released', ['id' => $sfId]);
        $this->assertNull($this->orderRow($pdo, $orderId)['tracking_code']);

        $handler->run('order.released', ['id' => $sfId, 'self_tracking' => 'BR444444444BR']);
        $this->assertSame('BR444444444BR', $this->orderRow($pdo, $orderId)['tracking_code']);
    }

    public function testWebhookCancelsShipmentWithoutCancelingOrder(): void
    {
        [$pdo, $orderId] = $this->makeDb();
        $sfId = $this->insertShipment($pdo, $orderId);

        $this->handler($pdo)->run('order.cancelled', ['id' => $sfId]);

        // Cancelamento na transportadora não é pedido cancelado: o cliente
        // já pagou. O envio fica cancelado e o pedido segue "shipped" para
        // o painel oferecer a etiqueta substituta.
        $this->assertSame('canceled', $this->shipmentRow($pdo, $orderId)['status']);
        $this->assertSame('shipped', $this->orderRow($pdo, $orderId)['status']);
    }

    // =====================================================================
    //  Montagem do ambiente
    // =====================================================================

    /** Banco SQLite com o trecho de esquema que processEvent usa. */
    private function makeDb(): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE e5_orders (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            order_code TEXT,
            status TEXT NOT NULL DEFAULT "pending",
            tracking_code TEXT
        )');
        $pdo->exec('CREATE TABLE e5_shipments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            superfrete_id TEXT,
            tracking_code TEXT,
            status TEXT NOT NULL DEFAULT "pending",
            delivery_min_days INTEGER,
            delivery_max_days INTEGER
        )');

        $pdo->exec("INSERT INTO e5_orders (id, user_id, order_code, status) VALUES (1, 1, 'RT00000001', 'shipped')");
        $pdo->exec("INSERT INTO e5_orders (id, user_id, order_code, status) VALUES (2, 1, 'RT00000002', 'pending')");
        // e5_users e e5_notifications existem para que a fila de avisos possa
        // rodar. A tabela de avisos é a que o webhook realmente escreve; sem
        // ela o teste passaria por um caminho que em produção não existe.
        $pdo->exec('CREATE TABLE e5_users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            email TEXT,
            phone TEXT,
            notify_email INTEGER NOT NULL DEFAULT 1,
            notify_whatsapp INTEGER NOT NULL DEFAULT 1
        )');
        $pdo->exec('CREATE TABLE e5_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            user_id INTEGER,
            channel TEXT NOT NULL,
            event_type TEXT NOT NULL,
            recipient TEXT NOT NULL,
            subject TEXT,
            body TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            attempts INTEGER NOT NULL DEFAULT 0,
            error_message TEXT,
            sent_at TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec("INSERT INTO e5_users (id, name, email, phone, notify_email, notify_whatsapp)
                    VALUES (1, 'Maria Silva', 'maria@exemplo.com', '5512978149392', 1, 1)");
        $pdo->exec('CREATE TABLE superfrete_webhook_log (
            event_id TEXT PRIMARY KEY,
            event_type TEXT,
            order_id TEXT,
            payload_hash TEXT
        )');

        return [$pdo, 1];
    }

    private function insertShipment(PDO $pdo, int $orderId): string
    {
        $sfId = 'sf_' . $orderId . '_' . substr(hash('sha256', (string) random_int(1, 1_000_000)), 0, 8);

        $st = $pdo->prepare(
            'INSERT INTO e5_shipments (order_id, superfrete_id, status) VALUES (:o, :sf, "pending")'
        );
        $st->execute([':o' => $orderId, ':sf' => $sfId]);

        return $sfId;
    }

    private function shipmentRow(PDO $pdo, int $orderId): array
    {
        $st = $pdo->prepare('SELECT * FROM e5_shipments WHERE order_id = :o');
        $st->execute([':o' => $orderId]);
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    private function orderRow(PDO $pdo, int $orderId): array
    {
        $st = $pdo->prepare('SELECT * FROM e5_orders WHERE id = :o');
        $st->execute([':o' => $orderId]);
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    /** Handler que expõe o processEvent protegido, sem HMAC nem log. */
    private function handler(PDO $pdo): object
    {
        return new class ($pdo, 'segredo') extends WebhookHandler {
            public function run(string $eventType, array $data): void
            {
                $this->processEvent($eventType, $data);
            }
        };
    }
}
