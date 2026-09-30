<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;

// Arquivos procedurais em includes/ (o projeto não os coloca no PSR-4).
require_once __DIR__ . '/../includes/validators.php';
require_once __DIR__ . '/../includes/order_repo.php';
require_once __DIR__ . '/../includes/order_state.php';

/**
 * Testes da máquina de estados do pedido — só de regra, sem banco.
 *
 * Aqui mora a regra que o cliente mais percebe numa tela de pedido:
 * o status do pagamento é consequência do status do pedido. Um pedido
 * "Entregue" mostrando "Aguardando pagamento", ou um cancelado
 * mostrando "Aguardando pagamento", são os dois defeitos que esta
 * classe existe para impedir.
 */
class OrderStateTest extends TestCase
{
    // =================================================================
    //  Transições permitidas
    // =================================================================

    public function testHappyPathTransitionsAreAllowed(): void
    {
        $this->assertTrue(order_can_transition('pending', 'paid'));
        $this->assertTrue(order_can_transition('paid', 'preparing'));
        $this->assertTrue(order_can_transition('preparing', 'shipped'));
        $this->assertTrue(order_can_transition('shipped', 'delivered'));
    }

    public function testCancelIsAllowedOnlyBeforeShipping(): void
    {
        $this->assertTrue(order_can_cancel(['status' => 'pending']));
        $this->assertTrue(order_can_cancel(['status' => 'paid']));
        $this->assertTrue(order_can_cancel(['status' => 'preparing']));

        // Depois do envio a mercadoria saiu da loja: o caminho passa a
        // ser devolução, não cancelamento.
        $this->assertFalse(order_can_cancel(['status' => 'shipped']));
        $this->assertFalse(order_can_cancel(['status' => 'delivered']));
        $this->assertFalse(order_can_cancel(['status' => 'canceled']));
    }

    public function testTerminalStatesAreAbsorbing(): void
    {
        foreach (['canceled', 'delivered'] as $from) {
            foreach (['pending', 'paid', 'preparing', 'shipped', 'delivered', 'canceled'] as $to) {
                if ($to === $from) {
                    continue;
                }
                $this->assertFalse(
                    order_can_transition($from, $to),
                    sprintf('%s -> %s deveria ser recusado', $from, $to)
                );
            }
        }
    }

    public function testStagesCannotBeSkippedOrReverted(): void
    {
        $this->assertFalse(order_can_transition('pending', 'delivered'), 'não pula etapa');
        $this->assertFalse(order_can_transition('pending', 'shipped'), 'não pula etapa');
        $this->assertFalse(order_can_transition('shipped', 'paid'), 'não volta etapa');
    }

    public function testUnknownOrderStatusHasNoTransitions(): void
    {
        $this->assertFalse(order_can_transition('nao_existe', 'paid'));
        $this->assertFalse(order_can_transition('paid', 'nao_existe'));
    }

    // =================================================================
    //  Coerência pedido -> pagamento
    // =================================================================

    public function testPaymentStatusFollowsOrderStatus(): void
    {
        $this->assertSame('pending', payment_status_required_for_order('pending'));
        $this->assertSame('paid', payment_status_required_for_order('paid'));
        $this->assertSame('paid', payment_status_required_for_order('preparing'));
        $this->assertSame('paid', payment_status_required_for_order('shipped'));
        $this->assertSame('paid', payment_status_required_for_order('delivered'));
        $this->assertSame('canceled', payment_status_required_for_order('canceled'));
    }

    public function testUnknownOrderStatusDemandsNoPaymentStatus(): void
    {
        // null sinaliza "não grava nada": inventar um status aqui
        // escreveria lixo em e5_orders.payment_status.
        $this->assertNull(payment_status_required_for_order('nao_existe'));
    }

    /**
     * O defeito central da tela: "Aguardando pagamento" só pode
     * aparecer em pedido pending.
     */
    public function testOnlyPendingOrderShowsWaitingForPayment(): void
    {
        $this->assertSame(
            'Aguardando pagamento',
            payment_status_meta(payment_status_required_for_order('pending'))['label']
        );

        foreach (['paid', 'preparing', 'shipped', 'delivered', 'canceled'] as $status) {
            $label = payment_status_meta(payment_status_required_for_order($status))['label'];
            $this->assertNotSame(
                'Aguardando pagamento',
                $label,
                sprintf('pedido %s não pode aparecer como aguardando pagamento', $status)
            );
        }
    }

    public function testCanceledOrderShowsCanceledNotRefunded(): void
    {
        // Nada foi pago, então não houve estorno: "Estornado" mentiria
        // para o cliente e quebraria a conciliação.
        $this->assertSame('Cancelado', payment_status_meta('canceled')['label']);
        $this->assertSame('Pago', payment_status_meta('paid')['label']);
        $this->assertSame('Estornado', payment_status_meta('refunded')['label']);
    }

    // =================================================================
    //  Rótulos e metadados tolerantes a valor inesperado
    // =================================================================

    public function testPaymentMetaToleratesNullAndUnknownValues(): void
    {
        // Rótulo é exibido na tela: um valor inesperado não pode virar
        // erro fatal, tem que cair no fallback.
        foreach ([null, '', 'inexistente'] as $value) {
            $meta = payment_status_meta($value);
            $this->assertIsArray($meta);
            $this->assertArrayHasKey('label', $meta);
            $this->assertArrayHasKey('tone', $meta);
        }
    }

    public function testOrderStatusMetaCoversEveryEnumValue(): void
    {
        // A lista vem do ENUM de e5_orders.status. Se um dia alguém
        // acrescentar um valor ao banco, este teste avisa que a tela
        // ainda não sabe rotulá-lo.
        foreach (['pending', 'paid', 'preparing', 'shipped', 'delivered', 'canceled'] as $status) {
            $meta = order_status_meta($status);
            $this->assertNotSame($status, $meta['label'], sprintf('%s sem rótulo próprio', $status));
        }
    }

    public function testPaymentMethodLabels(): void
    {
        $this->assertSame('Pix', payment_method_label('pix'));
        $this->assertSame('Boleto', payment_method_label('boleto'));
        $this->assertSame('Cartão de crédito', payment_method_label('cartao'));
    }

    // =================================================================
    //  Progresso
    // =================================================================

    public function testTimelineHasFiveStepsInOrder(): void
    {
        $this->assertSame(
            ['placed', 'paid', 'preparing', 'shipped', 'delivered'],
            array_keys(order_timeline_steps())
        );
    }

    public function testCanceledIsNotATimelineStep(): void
    {
        // Cancelar é um desvio do caminho feliz, não uma etapa: se
        // entrasse na linha do tempo, o cliente leria o cancelamento
        // como se fosse uma fase da compra.
        $this->assertArrayNotHasKey('canceled', order_timeline_steps());
        $this->assertNotContains('canceled', order_status_sequence());
    }

    public function testOrderStatusMapsToTimelineStep(): void
    {
        $this->assertSame('placed', order_status_to_step('pending'));
        $this->assertSame('paid', order_status_to_step('paid'));
        $this->assertSame('preparing', order_status_to_step('preparing'));
        $this->assertSame('shipped', order_status_to_step('shipped'));
        $this->assertSame('delivered', order_status_to_step('delivered'));
        $this->assertNull(order_status_to_step('canceled'));
        $this->assertNull(order_status_to_step('nao_existe'));
    }

    // =================================================================
    //  Ações oferecidas na tela
    // =================================================================

    public function testCanceledOrderOffersNeitherPaymentNorCancel(): void
    {
        $actions = order_actions_available([
            'status'         => 'canceled',
            'payment_status' => 'canceled',
            'tracking_code'  => '',
        ]);

        $this->assertFalse($actions['pay']);
        $this->assertFalse($actions['cancel']);
        $this->assertTrue($actions['rebuy']);
    }

    public function testPendingOrderOffersPaymentAndCancel(): void
    {
        $actions = order_actions_available([
            'status'         => 'pending',
            'payment_status' => 'pending',
            'tracking_code'  => '',
        ]);

        $this->assertTrue($actions['pay']);
        $this->assertTrue($actions['cancel']);
        $this->assertFalse($actions['track'], 'sem etiqueta não há o que rastrear');
    }

    public function testTrackingIsOfferedOnlyWithTrackingCode(): void
    {
        $withCode = order_actions_available([
            'status'         => 'shipped',
            'payment_status' => 'paid',
            'tracking_code'  => 'BR123456789BR',
        ]);
        $this->assertTrue($withCode['track']);

        $withoutCode = order_actions_available([
            'status'         => 'shipped',
            'payment_status' => 'paid',
            'tracking_code'  => '   ',
        ]);
        $this->assertFalse($withoutCode['track']);
    }

    // =================================================================
    //  Pagamento: transições
    // =================================================================

    public function testPaidPaymentCanOnlyBeRefunded(): void
    {
        $this->assertTrue(payment_can_transition('paid', 'refunded'));
        $this->assertFalse(payment_can_transition('paid', 'canceled'));
    }

    public function testTerminalPaymentsAreAbsorbing(): void
    {
        foreach (['canceled', 'expired', 'refunded', 'failed'] as $from) {
            $this->assertSame([], payment_allowed_transitions()[$from]);
        }
    }
}
