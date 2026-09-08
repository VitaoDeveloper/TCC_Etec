<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;
use TCC\SuperFreteClient;
use TCC\Webhook\WebhookHandler;

/**
 * Suite de testes da integração SuperFrete.
 *
 * Cobre:
 *   1. SuperFreteClient — utilitários de normalização e mascaramento
 *      (lógica pura, SEM chamadas reais à API).
 *   2. WebhookHandler — HMAC-SHA256 (X-ME-Signature), idempotência,
 *      eventos desconhecidos e fluxo completo handle() (fake em memória,
 *      SEM banco e SEM HTTP).
 *   3. Fixtures — validam o corpus de evidências reais do sandbox em
 *      tests/fixtures/superfrete/.
 */
class SuperFreteClientTest extends TestCase
{
    private string $secret = 'chave-secreta-teste';

    private const FIXTURES_DIR = __DIR__ . '/fixtures/superfrete';

    // =====================================================================
    //  SuperFreteClient — métodos utilitários
    // =====================================================================

    public function testNormalizePostalCodeRemovesHyphen(): void
    {
        $this->assertSame('01310100', SuperFreteClient::normalizePostalCode('01310-100'));
        $this->assertSame('01310100', SuperFreteClient::normalizePostalCode('01310100'));
    }

    public function testNormalizePostalCodeRemovesAnyNonDigit(): void
    {
        $this->assertSame('01310100', SuperFreteClient::normalizePostalCode(' 01310.100 '));
    }

    public function testEnsureFullNamePrefixesShop(): void
    {
        // Nome de loja com 1 palavra → prefixa "Loja "
        $this->assertSame('Loja SuperFrete', SuperFreteClient::ensureFullName('SuperFrete'));
    }

    public function testEnsureFullNameLeavesNameWithLastName(): void
    {
        $this->assertSame('João Silva', SuperFreteClient::ensureFullName('João Silva'));
        $this->assertSame('Maria Ana Souza', SuperFreteClient::ensureFullName('Maria Ana Souza'));
    }

    public function testEnsureFullNameTrimsInput(): void
    {
        $this->assertSame('Loja RoyalTech', SuperFreteClient::ensureFullName('  RoyalTech  '));
    }

    public function testNormalizePhoneReturns11Digits(): void
    {
        $this->assertSame('11999999999', SuperFreteClient::normalizePhone('(11) 99999-9999'));
        $this->assertSame('11999999999', SuperFreteClient::normalizePhone('11 99999-9999'));
    }

    public function testNormalizePhoneRejectsInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SuperFreteClient::normalizePhone('119999999');
    }

    public function testMaskTokenShowsOnlyLastFour(): void
    {
        $masked = SuperFreteClient::maskToken('abcdefgh1234');
        $this->assertStringEndsWith('1234', $masked);
        $this->assertStringNotContainsString('abcdef', $masked);
        $this->assertSame('****', SuperFreteClient::maskToken('abcd'));
        $this->assertSame('****', SuperFreteClient::maskToken(''));
    }

    public function testFakeTokenNeverLeaksInMask(): void
    {
        $token = 'secreto-total-9999';
        $masked = SuperFreteClient::maskToken($token);
        $this->assertStringNotContainsString('secreto', $masked);
        $this->assertStringEndsWith('9999', $masked);
    }

    // =====================================================================
    //  WebhookHandler — HMAC e idempotência (fake em memória)
    // =====================================================================

    public function testValidateSignatureAcceptsCorrectHmac(): void
    {
        $handler = $this->makeFakeHandler($this->secret);
        $rawBody = json_encode($this->samplePayload());

        $signature = hash_hmac('sha256', $rawBody, $this->secret);
        $this->assertTrue($handler->validateSignature($rawBody, $signature));
    }

    public function testValidateSignatureRejectsWrongSecret(): void
    {
        $handler = $this->makeFakeHandler($this->secret);
        $rawBody = json_encode($this->samplePayload());

        $badSignature = hash_hmac('sha256', $rawBody, 'outra-chave');
        $this->assertFalse($handler->validateSignature($rawBody, $badSignature));
    }

    public function testValidateSignatureRejectsTamperedBody(): void
    {
        $handler = $this->makeFakeHandler($this->secret);
        $rawBody = json_encode($this->samplePayload());

        $signature = hash_hmac('sha256', $rawBody, $this->secret);
        $tampered  = str_replace('DG048745602BR', 'DG048745602BX', $rawBody);
        $this->assertNotSame($rawBody, $tampered);
        $this->assertFalse($handler->validateSignature($tampered, $signature));
    }

    public function testValidateSignatureRejectsEmpty(): void
    {
        $handler = $this->makeFakeHandler($this->secret);
        $rawBody = json_encode($this->samplePayload());

        // Assinatura ausente no header → rejeita
        $this->assertFalse($handler->validateSignature($rawBody, ''));

        // Secret vazio → rejeita mesmo com assinatura preenchida
        $emptySecretHandler = $this->makeFakeHandler('');
        $sig = hash_hmac('sha256', $rawBody, $this->secret);
        $this->assertFalse($emptySecretHandler->validateSignature($rawBody, $sig));
    }

    public function testHandleRejectsInvalidSignature(): void
    {
        $handler = $this->makeFakeHandler($this->secret);
        $rawBody = json_encode($this->samplePayload());
        $handler->setInputBody($rawBody);
        $handler->setServerSignature('assinatura-invalida');

        $this->expectOutputString('');
        $result = $handler->handle();

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Assinatura', $result['message']);
    }

    public function testHandleProcessesValidEventOnce(): void
    {
        $handler = $this->makeFakeHandler($this->secret);
        $rawBody = json_encode($this->samplePayload());
        $signature = hash_hmac('sha256', $rawBody, $this->secret);

        $handler->setInputBody($rawBody);
        $handler->setServerSignature($signature);

        $first = $handler->handle();
        $this->assertSame('processed', $first['status']);

        // Segundo recebimento do mesmo event_id → duplicata, não reprocessa
        $second = $handler->handle();
        $this->assertSame('duplicate', $second['status']);

        // processEvent só foi chamado UMA vez
        $this->assertCount(1, $handler->processedEvents);
    }

    public function testHandleIgnoresUnknownEvent(): void
    {
        $handler = $this->makeFakeHandler($this->secret);
        $rawBody = json_encode($this->samplePayload('order.desconhecido'));
        $signature = hash_hmac('sha256', $rawBody, $this->secret);

        $handler->setInputBody($rawBody);
        $handler->setServerSignature($signature);

        $result = $handler->handle();
        $this->assertSame('ignored', $result['status']);
        $this->assertCount(0, $handler->processedEvents);
    }

    public function testGenerateSignatureMatchesHandlerValidation(): void
    {
        $rawBody = '{"event":"order.created","data":{"id":"xyz"}}';
        $expected = hash_hmac('sha256', $rawBody, $this->secret);
        $this->assertSame($expected, WebhookHandler::generateSignature($rawBody, $this->secret));
    }

    public function testHandleRejectsEmptyBody(): void
    {
        $handler = $this->makeFakeHandler($this->secret);
        $handler->setInputBody('');
        $handler->setServerSignature('qualquer');

        $result = $handler->handle();
        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Body vazio', $result['message']);
    }

    public function testValidEventsReturned(): void
    {
        $events = WebhookHandler::getValidEvents();
        foreach ([
            'order.created',
            'order.released',
            'order.generated',
            'order.posted',
            'order.delivered',
            'order.cancelled',
        ] as $expected) {
            $this->assertContains($expected, $events);
        }
    }

    public function testSecretTokenNeverAppearsInResults(): void
    {
        $handler = $this->makeFakeHandler($this->secret);
        $rawBody = json_encode($this->samplePayload());
        $signature = hash_hmac('sha256', $rawBody, $this->secret);

        $handler->setInputBody($rawBody);
        $handler->setServerSignature($signature);

        $result = $handler->handle();
        $json = json_encode([$result, $handler->logged, $handler->processedEvents]);

        $this->assertStringNotContainsString($this->secret, $json);
    }

    public function testLoggedWebhookStoresPayloadHashOnly(): void
    {
        $handler = $this->makeFakeHandler($this->secret);
        $rawBody = json_encode($this->samplePayload());
        $signature = hash_hmac('sha256', $rawBody, $this->secret);

        $handler->setInputBody($rawBody);
        $handler->setServerSignature($signature);

        $result = $handler->handle();
        $this->assertSame('processed', $result['status']);

        // O log interno deve conter o hash SHA-256 do body, não o JSON em claro
        $logged = reset($handler->logged);
        $this->assertArrayHasKey('payload_hash', $logged);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $logged['payload_hash']);
        $this->assertSame(hash('sha256', $rawBody), $logged['payload_hash']);
        $this->assertArrayNotHasKey('payload', $logged);
    }

    // =====================================================================
    //  Fixtures reais (evidências do sandbox)
    // =====================================================================

    public function testHealthCheckFixtureExistsAndIsValid(): void
    {
        $data = $this->load('me_orders/health_check_success.json');
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('total', $data['meta']);
        $this->assertIsArray($data['data']);
    }

    public function testCalculatorSuccessFixtureStructure(): void
    {
        $data = $this->load('calculator/quote_products_success.json');
        $this->assertIsArray($data);
        if (count($data) > 0) {
            $first = $data[0];
            $this->assertArrayHasKey('id', $first);
            $this->assertArrayHasKey('name', $first);
            // Pode conter erro por trecho OU preço (SEDEX com pacote)
            $this->assertTrue(
                array_key_exists('price', $first) || array_key_exists('error', $first),
                'Resultado de cotação deve ter price OU error'
            );
        }
    }

    public function testCalculatorErrorFixtureCaptured(): void
    {
        $data = $this->load('calculator/quote_error_body.json');
        $this->assertArrayHasKey('message', $data);
        // A API retorna a estrutura {message, errors:{campo:[msgs]}}
    }

    public function testCartSuccessFixtureStructure(): void
    {
        $data = $this->load('cart/cart_success.json');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertSame('pending', $data['status']);
    }

    public function testCartRequestFixtureContainsMandatoryFields(): void
    {
        $data = $this->load('cart/cart_request.json');
        $this->assertArrayHasKey('from', $data);
        $this->assertArrayHasKey('to', $data);
        $this->assertArrayHasKey('service', $data);
        $this->assertArrayHasKey('volumes', $data);
        $this->assertArrayHasKey('platform', $data);

        // Campos obrigatórios: documento destinatário, CEP padronizado, nome completo
        $this->assertNotEmpty($data['to']['document']);
        $this->assertMatchesRegularExpression('/^\d{8}$/', $data['from']['postal_code']);
        $this->assertMatchesRegularExpression('/^\d{8}$/', $data['to']['postal_code']);
        $this->assertMatchesRegularExpression('/\S+\s+\S+/', $data['to']['name']);
        // Telefone com 11 dígitos (regra J&T)
        $this->assertMatchesRegularExpression('/^\d{11}$/', $data['to']['phone']);
    }

    public function testOrderInfoSuccessFixtureStructure(): void
    {
        $data = $this->load('order_info/order_info_success.json');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('from', $data);
        $this->assertArrayHasKey('to', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('service_id', $data);
    }

    public function testTagPrintSuccessFixtureStructure(): void
    {
        $data = $this->load('tag_print/tag_print_success.json');
        $this->assertArrayHasKey('url', $data);
        $this->assertStringStartsWith('http', $data['url']);
    }

    public function testListOrdersFilteredFixtureStructure(): void
    {
        $data = $this->load('me_orders/list_orders_filtered_success.json');
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        foreach ($data['data'] as $order) {
            $this->assertArrayHasKey('order_id', $order);
            $this->assertArrayHasKey('status', $order);
            $this->assertArrayHasKey('price', $order);
        }
    }

    public function testCancelSuccessFixtureStructure(): void
    {
        $data = $this->load('cancel/cancel_success.json');
        $this->assertNotEmpty($data);
        foreach ($data as $orderId => $result) {
            $this->assertArrayHasKey('canceled', $result);
            $this->assertTrue($result['canceled']);
        }
    }

    public function testCheckoutFixtureDocumentsLimitation(): void
    {
        $data = $this->load('checkout/checkout_error_body.json');
        // A limitação REAL: checkout exige saldo na carteira
        $this->assertArrayHasKey('limitation', $data);
        $this->assertStringContainsString('NÃO TESTÁVEL', strtoupper($data['limitation']));
        $this->assertArrayHasKey('http_status', $data);
        $this->assertSame(409, $data['http_status']);
    }

    public function testWebhookCreateFixtureMasksSecret(): void
    {
        $data = $this->load('webhook/webhook_create_success.json');
        $this->assertArrayHasKey('secret_token', $data);
        // O secret_token NUNCA pode aparecer em claro no repositorio
        $this->assertStringContainsString('MASKED', $data['secret_token']);
        $this->assertValidWebhookEvents($data['events'] ?? []);
    }

    public function testWebhookListAndUpdateFixturesExist(): void
    {
        $list = $this->load('webhook/webhook_list_success.json');
        $this->assertIsArray($list);

        $update = $this->load('webhook/webhook_update_success.json');
        $this->assertArrayHasKey('id', $update);
        $this->assertArrayHasKey('name', $update);
    }

    // =====================================================================
    //  Helpers
    // =====================================================================

    private function load(string $relativePath): array
    {
        $file = self::FIXTURES_DIR . '/' . $relativePath;
        $this->assertFileExists($file, "Fixture ausente: $relativePath");
        $data = json_decode((string) file_get_contents($file), true);
        $this->assertIsArray($data, "Fixture inválida (JSON): $relativePath");
        return $data;
    }

    private function assertValidWebhookEvents(array $events): void
    {
        $valid = [
            'order.created',
            'order.released',
            'order.generated',
            'order.posted',
            'order.delivered',
            'order.cancelled',
        ];
        foreach ($events as $event) {
            $this->assertContains($event, $valid, "Evento de webhook inválido: $event");
        }
    }

    private function samplePayload(string $event = 'order.delivered', string $orderId = 'abc123'): array
    {
        return [
            'event' => $event,
            'data'  => [
                'id'           => $orderId,
                'status'       => 'delivered',
                'tracking'     => 'DG048745602BR',
                'tracking_url' => 'rastreio.superfrete.com/#/tracking/x',
                'created_at'   => '2026-09-08T12:00:00+00:00',
                'delivered_at' => '2026-09-08T12:30:00+00:00',
                'tags'         => [['tag' => '3101481', 'url' => 'https://loja.com/pedido/3101481']],
            ],
        ];
    }

    /**
     * Cria um WebhookHandler fake (storage em memória) para testar o fluxo
     * completo sem PDO/banco e sem chamadas HTTP reais.
     */
    private function makeFakeHandler(string $secret): WebhookHandler
    {
        return new class($secret) extends WebhookHandler
        {
            public array $processed = [];

            public array $logged = [];

            public array $processedEvents = [];

            private string $inputBody = '';

            public function __construct(string $secretToken)
            {
                $this->initSecret($secretToken);
            }

            public function setInputBody(string $body): void
            {
                $this->inputBody = $body;
            }

            public function setServerSignature(string $signature): void
            {
                $_SERVER['HTTP_X_ME_SIGNATURE'] = $signature;
            }

            protected function readInput(): string
            {
                return $this->inputBody;
            }

            protected function isDuplicate(string $eventId): bool
            {
                return isset($this->processed[$eventId]);
            }

            protected function logEvent(string $eventId, string $eventType, string $orderId, array $payload): void
            {
                $this->logged[$eventId] = [
                    'event_type'   => $eventType,
                    'order_id'     => $orderId,
                    'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE)),
                ];
                $this->processed[$eventId] = $this->logged[$eventId];
            }

            protected function processEvent(string $eventType, array $eventData): void
            {
                $this->processedEvents[] = ['event' => $eventType, 'data' => $eventData];
            }

            private function initSecret(string $secret): void
            {
                $prop = new \ReflectionProperty(WebhookHandler::class, 'secretToken');
                $prop->setValue($this, $secret);
            }
        };
    }
}