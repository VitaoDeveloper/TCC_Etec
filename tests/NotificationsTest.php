<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Testes de renderização dos templates de notificação transacional.
 *
 * Exercitam notificationRender() sem banco: o contexto do pedido é montado
 * manualmente e a configuração da loja cai nos defaults estáticos.
 */
class NotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/includes/notifications_functions.php';
    }

    private function orderCtx(array $overrides = []): array
    {
        $order = array_merge([
            'id' => 42,
            'user_id' => 1,
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'pix',
            'total' => '1234.50',
            'shipping_cost' => '25.90',
            'tracking_code' => 'BR123456789BR',
            'refund_reason' => '',
            'payment_expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'user_name' => 'Maria Silva',
        ], $overrides);

        return [
            'order' => $order,
            'items' => [['quantity' => 2, 'price' => '617.25', 'name' => 'Produto Teste']],
            'number' => '#0042',
            'total' => 'R$ 1.234,50',
            'name' => 'Maria Silva',
        ];
    }

    public function testPaidTemplateHasSubjectAndOrderLink(): void
    {
        $rendered = \notificationRender('order_paid', $this->orderCtx());
        $this->assertNotNull($rendered);
        $this->assertStringContainsString('#0042', $rendered['subject']);
        $this->assertStringContainsString('Pagamento confirmado', $rendered['body']);
        $this->assertStringContainsString('order-detail.php?id=42', $rendered['body']);
        $this->assertStringContainsString('R$ 1.234,50', $rendered['body']);
    }

    public function testShippedTemplateIncludesTracking(): void
    {
        $rendered = \notificationRender('order_shipped', $this->orderCtx(['status' => 'shipped']), ['tracking' => 'BR999999999BR', 'carrier' => 'Correios']);
        $this->assertNotNull($rendered);
        $this->assertStringContainsString('BR999999999BR', $rendered['body']);
        $this->assertStringContainsString('Correios', $rendered['body']);
    }

    public function testReminderTemplateMentionsPendingPayment(): void
    {
        $rendered = \notificationRender('order_reminder', $this->orderCtx(['payment_status' => 'pending']));
        $this->assertNotNull($rendered);
        $this->assertStringContainsString('pendente', $rendered['body']);
        $this->assertStringContainsString('payment.php?id=42', $rendered['body']);
    }

    public function testWelcomeTemplateUsesCustomerName(): void
    {
        $rendered = \notificationRender('user_welcome', ['name' => 'João']);
        $this->assertNotNull($rendered);
        $this->assertStringContainsString('João', $rendered['body']);
        $this->assertStringContainsString('Bem-vindo', $rendered['subject']);
    }

    public function testContactTemplateIncludesSubjectLine(): void
    {
        $rendered = \notificationRender('contact_received', ['name' => 'Ana'], ['subject' => 'Dúvida sobre frete']);
        $this->assertNotNull($rendered);
        $this->assertStringContainsString('Dúvida sobre frete', $rendered['body']);
    }

    public function testUnknownTemplateReturnsNull(): void
    {
        $this->assertNull(\notificationRender('evento_inexistente', $this->orderCtx()));
    }

    public function testMoneyAndOrderNumberFormatting(): void
    {
        $this->assertSame('R$ 1.234,50', \notificationMoney(1234.5));
        $this->assertSame('#0042', \notificationOrderNumber(42));
        $this->assertSame('#1234', \notificationOrderNumber(1234));
    }
}
