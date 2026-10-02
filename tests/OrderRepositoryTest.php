<?php

declare(strict_types=1);

namespace TCC\Tests;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/validators.php';
require_once __DIR__ . '/../includes/order_repo.php';
require_once __DIR__ . '/../includes/order_state.php';

/**
 * Testes de integração da tela de detalhe do pedido, contra o MySQL real.
 *
 * Usa o schema de verdade (ENUM, índice único gerado, INSERT IGNORE) —
 * coisas que um banco em memória não reproduziria. Por isso roda em
 * SQLite falha: o que se quer testar aqui é justamente a física das
 * tabelas.
 *
 * Tudo o que é criado fica dentro de uma transação que é revertida no
 * tearDown. Nenhum teste aqui suja o banco.
 */
class OrderRepositoryTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        $this->pdo = self::connect();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->pdo = null;
    }

    /**
     * Conecta usando a mesma configuração de database/connection.php.
     * Sem banco disponível o teste é pulado em vez de quebrado: a suíte
     * de regra (OrderStateTest) não depende de MySQL e deve continuar
     * rodando em qualquer máquina.
     */
    private static function connect(): PDO
    {
        require_once __DIR__ . '/../database/connection.php';

        if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
            throw new PDOException('sem conexão');
        }

        return $GLOBALS['pdo'];
    }

    private function db(): PDO
    {
        if ($this->pdo === null) {
            $this->markTestSkipped('MySQL indisponível: teste de integração ignorado');
        }

        return $this->pdo;
    }

    /** Cria um pedido mínimo e devolvolve o id. */
    private function makeOrder(string $status = 'pending', string $paymentStatus = 'pending'): int
    {
        $pdo = $this->db();
        $st  = $pdo->prepare(
            "INSERT INTO e5_orders
                (user_id, status, payment_status, payment_method, total,
                 shipping_method, shipping_cost, shipping_street, shipping_number,
                 shipping_neighborhood, shipping_city, shipping_state,
                 shipping_postal_code, created_at, updated_at)
             VALUES (1, :st, :ps, 'pix', 100.00, 'PAC', 0.00,
                     'Rua Teste', '123', 'Centro', 'Taubaté', 'SP', '12053831',
                     '2026-09-16 18:47:00', NOW())"
        );
        $st->execute([':st' => $status, ':ps' => $paymentStatus]);

        return (int) $pdo->lastInsertId();
    }

    private function firstProductId(): int
    {
        $id = $this->db()->query('SELECT id FROM e5_products ORDER BY id LIMIT 1')->fetchColumn();

        return $id === false ? 0 : (int) $id;
    }

    private function productStock(int $productId): int
    {
        $st = $this->db()->prepare('SELECT stock FROM e5_products WHERE id = :id');
        $st->execute([':id' => $productId]);

        return (int) $st->fetchColumn();
    }

    // =================================================================
    //  Autorização
    // =================================================================

    public function testOwnerSeesOwnOrder(): void
    {
        $id = $this->makeOrder();
        $this->assertNotNull(order_repo_find_authorized($this->db(), $id, 1, false));
    }

    public function testAdminSeesAnyOrder(): void
    {
        $id = $this->makeOrder();
        $this->assertNotNull(order_repo_find_authorized($this->db(), $id, 999, true));
    }

    public function testAnotherCustomerGetsNullSoPageCanReturn404(): void
    {
        $id = $this->makeOrder();

        // Não distingue "não existe" de "não é seu": é o que evita
        // revelar que o pedido de outra pessoa existe.
        $this->assertNull(order_repo_find_authorized($this->db(), $id, 999, false));
        $this->assertNull(order_repo_find_authorized($this->db(), 999999, 1, false));
    }

    // =================================================================
    //  Cancelamento
    // =================================================================

    public function testCancelingPendingOrderAlsoCancelsPayment(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder();
        $pdo->prepare(
            "INSERT INTO e5_payments (order_id, method, status, amount, created_at, updated_at)
             VALUES (:o, 'pix', 'pending', 100.00, NOW(), NOW())"
        )->execute([':o' => $id]);

        $r = order_apply_status($pdo, $id, 'canceled');
        $this->assertTrue($r['ok']);

        $order = order_repo_find($pdo, $id);
        $this->assertSame('canceled', $order['status']);
        $this->assertSame('canceled', $order['payment_status']);
        $this->assertSame('canceled', order_repo_payment($pdo, $id)['status']);

        // A regra que a tela depende.
        $this->assertNotSame(
            'Aguardando pagamento',
            payment_status_meta($order['payment_status'])['label']
        );
    }

    public function testCancelingPaidOrderRefundsInsteadOfCanceling(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder('paid', 'paid');
        $pdo->prepare(
            "INSERT INTO e5_payments (order_id, method, status, amount, paid_at, created_at, updated_at)
             VALUES (:o, 'pix', 'paid', 100.00, NOW(), NOW(), NOW())"
        )->execute([':o' => $id]);

        $r = order_apply_status($pdo, $id, 'canceled');
        $this->assertTrue($r['ok']);

        // O dinheiro já tinha entrado: o desfecho é estorno. Marcar
        // "cancelado" mentiria para o cliente e quebraria a conciliação.
        $order = order_repo_find($pdo, $id);
        $this->assertSame('refunded', $order['payment_status']);
        $this->assertSame('Estornado', payment_status_meta($order['payment_status'])['label']);
    }

    public function testCanceledOrderRefusesFurtherTransitions(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder('canceled', 'canceled');

        foreach (['paid', 'shipped', 'delivered', 'preparing'] as $target) {
            $r = order_apply_status($pdo, $id, $target);
            $this->assertFalse($r['ok'], sprintf('canceled -> %s deveria falhar', $target));
        }

        $this->assertSame('canceled', order_repo_find($pdo, $id)['status']);
    }

    public function testShippingAnOrderIsNotCancellable(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder('shipped', 'paid');

        $r = order_apply_status($pdo, $id, 'canceled');
        $this->assertFalse($r['ok']);
        $this->assertSame('shipped', order_repo_find($pdo, $id)['status']);
    }

    // =================================================================
    //  Coerência pagamento em todo o caminho feliz
    // =================================================================

    public function testPaymentNeverStaysPendingAlongTheHappyPath(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder();

        foreach (['paid', 'preparing', 'shipped', 'delivered'] as $step) {
            $r = order_apply_status($pdo, $id, $step);
            $this->assertTrue($r['ok'], "transição para {$step}");

            $order = order_repo_find($pdo, $id);

            // A invariante é "o pagamento segue o pedido", não "o
            // pagamento repete o status do pedido": em 'preparing' o
            // pedido está em preparo mas o pagamento continua 'paid'.
            $this->assertSame(
                payment_status_required_for_order($order['status']),
                $order['payment_status'],
                sprintf('pedido %s com pagamento %s', $order['status'], $order['payment_status'])
            );
            $this->assertNotSame(
                'Aguardando pagamento',
                payment_status_meta($order['payment_status'])['label']
            );
        }
    }

    public function testDeliveredOrderWithoutPaymentRowStillReportsPaid(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder();

        // Pedido sem linha em e5_payments: a coluna desnormalizada de
        // e5_orders ainda tem de acompanhar o pedido, senão a tela
        // mostraria "Aguardando pagamento" numa compra entregue.
        foreach (['paid', 'preparing', 'shipped', 'delivered'] as $step) {
            order_apply_status($pdo, $id, $step);
        }

        $this->assertNull(order_repo_payment($pdo, $id));
        $this->assertSame('paid', order_repo_find($pdo, $id)['payment_status']);
    }

    // =================================================================
    //  Estoque
    // =================================================================

    public function testCancelingRestoresStock(): void
    {
        $pdo       = $this->db();
        $productId = $this->firstProductId();
        if ($productId === 0) {
            $this->markTestSkipped('sem produtos cadastrados');
        }

        $id = $this->makeOrder();
        $pdo->prepare(
            'INSERT INTO e5_order_items (order_id, product_id, product_name, quantity, unit_price)
             VALUES (:o, :p, :n, 3, 33.33)'
        )->execute([':o' => $id, ':p' => $productId, ':n' => 'Produto Teste']);

        // Simula a baixa feita no checkout.
        $pdo->prepare('UPDATE e5_products SET stock = stock - 3 WHERE id = :p')->execute([':p' => $productId]);
        $afterCheckout = $this->productStock($productId);

        $r = order_apply_status($pdo, $id, 'canceled');

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['restocked']);
        $this->assertSame($afterCheckout + 3, $this->productStock($productId));
    }

    public function testShippingAnOrderDoesNotTouchStock(): void
    {
        $pdo       = $this->db();
        $productId = $this->firstProductId();
        if ($productId === 0) {
            $this->markTestSkipped('sem produtos cadastrados');
        }

        $id = $this->makeOrder();
        $pdo->prepare(
            'INSERT INTO e5_order_items (order_id, product_id, product_name, quantity, unit_price)
             VALUES (:o, :p, :n, 2, 50.00)'
        )->execute([':o' => $id, ':p' => $productId, ':n' => 'Produto Teste']);

        $before = $this->productStock($productId);
        order_apply_status($pdo, $id, 'paid');
        order_apply_status($pdo, $id, 'preparing');
        order_apply_status($pdo, $id, 'shipped');

        // O envio não devolve estoque: a mercadoria saiu da loja.
        $this->assertSame($before, $this->productStock($productId));
    }

    // =================================================================
    //  Histórico / progresso
    // =================================================================

    public function testHistoryRecordsEachStageOnce(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder();

        foreach (['paid', 'preparing', 'shipped', 'delivered'] as $step) {
            order_apply_status($pdo, $id, $step);
        }

        $history = array_column(order_repo_history($pdo, $id), 'status');
        $this->assertSame($history, array_unique($history), 'etapa repetida no histórico');
        foreach (['paid', 'preparing', 'shipped', 'delivered'] as $step) {
            $this->assertContains($step, $history);
        }
    }

    public function testHistoryWritesTimelineVocabularyNotRawStatus(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder();

        order_repo_add_history($pdo, $id, 'pending', 'pedido criado');

        // 'pending' (status do banco) vira 'placed' (etapa da tela). São
        // dois vocabulários que só coincidem a partir do 2º passo, e
        // é essa coincidência que faria um pedido parecer completo.
        $this->assertSame(['placed'], array_column(order_repo_history($pdo, $id), 'status'));
    }

    public function testProgressMarksEveryStageUpToCurrentStatus(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder();

        foreach (['paid', 'preparing', 'shipped'] as $step) {
            order_apply_status($pdo, $id, $step);
        }

        $progress = order_progress($pdo, order_repo_find($pdo, $id));
        $done     = array_values(array_filter($progress['steps'], static fn($s) => $s['done']));

        $this->assertCount(4, $done, 'até "Enviado" são 4 etapas');
        $this->assertFalse($progress['canceled']);
    }

    public function testCanceledOrderShowsNoCompletedStage(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder();

        order_apply_status($pdo, $id, 'canceled');

        $progress = order_progress($pdo, order_repo_find($pdo, $id));
        $this->assertTrue($progress['canceled']);
        $this->assertCount(0, array_filter($progress['steps'], static fn($s) => $s['done']));
        $this->assertNotNull($progress['canceled_at']);
    }

    public function testLegacyOrderWithoutHistoryStillRendersTimeline(): void
    {
        $pdo = $this->db();
        $id  = $this->makeOrder('shipped', 'paid');

        // Pedido herdado, anterior a esta tela: sem nenhuma linha em
        // e5_order_history. O progresso é reconstituído a partir do
        // próprio status, para a linha do tempo não nascer quebrada.
        $this->assertSame([], order_repo_history($pdo, $id));

        $progress = order_progress($pdo, order_repo_find($pdo, $id));
        $steps    = $progress['steps'];

        $this->assertTrue($steps[0]['done'], '"Pedido Realizado" reconstituído');
        $this->assertTrue($steps[1]['done']);
        $this->assertTrue($steps[2]['done']);
        $this->assertTrue($steps[3]['done']);
        $this->assertFalse($steps[4]['done'], '"Entregue" continua pendente');
    }

    // =================================================================
    //  Seed do pedido de demonstração
    // =================================================================

    // =================================================================
    //  Seed de demonstração idempotente (database/database.sql)
    // =================================================================

    public function testDemoUserSeedIsIdempotentAndCoherent(): void
    {
        $pdo = $this->db();
        $sql = file_get_contents(__DIR__ . '/../database/database.sql');
        $this->assertIsString($sql);

        // Rodar duas vezes seguidas não pode duplicar nada
        $pdo->exec($sql);
        $pdo->exec($sql);

        $count = static function (PDO $db, string $table, string $col, int $userId): int {
            $st = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$col} = ?");
            $st->execute([$userId]);
            return (int) $st->fetchColumn();
        };

        // O seed do usuário 1 cria 8 pedidos (ids 1001-1008)
        $this->assertSame(8, (int)$pdo->query("SELECT COUNT(*) FROM e5_orders WHERE user_id = 1")->fetchColumn());
        $this->assertSame(16, (int)$pdo->query("SELECT COUNT(*) FROM e5_order_items WHERE order_id IN (SELECT id FROM e5_orders WHERE user_id = 1)")->fetchColumn());
        $this->assertSame(8, (int)$pdo->query("SELECT COUNT(*) FROM e5_payments WHERE order_id IN (SELECT id FROM e5_orders WHERE user_id = 1)")->fetchColumn());
        $this->assertSame(24, (int)$pdo->query("SELECT COUNT(*) FROM e5_order_history WHERE order_id IN (SELECT id FROM e5_orders WHERE user_id = 1)")->fetchColumn());
        // Verifica cada estado existe exatamente uma vez
        $states = $pdo->query("SELECT status, payment_status, COUNT(*) as c FROM e5_orders WHERE user_id = 1 GROUP BY status, payment_status")->fetchAll(PDO::FETCH_ASSOC);
        $expected = [
            ['status' => 'pending', 'payment_status' => 'pending', 'c' => 1],
            ['status' => 'paid', 'payment_status' => 'paid', 'c' => 1],
            ['status' => 'preparing', 'payment_status' => 'paid', 'c' => 1],
            ['status' => 'shipped', 'payment_status' => 'paid', 'c' => 1],
            ['status' => 'delivered', 'payment_status' => 'paid', 'c' => 1],
            ['status' => 'canceled', 'payment_status' => 'expired', 'c' => 1],
            ['status' => 'canceled', 'payment_status' => 'canceled', 'c' => 1],
            ['status' => 'canceled', 'payment_status' => 'refunded', 'c' => 1],
        ];

        foreach ($expected as $exp) {
            $found = false;
            foreach ($states as $s) {
                if ($s['status'] === $exp['status'] && $s['payment_status'] === $exp['payment_status']) {
                    $this->assertSame($exp['c'], (int)$s['c'], "Estado {$exp['status']}/{$exp['payment_status']} deve ter {$exp['c']} pedido(s)");
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Estado {$exp['status']}/{$exp['payment_status']} deve existir");
        }

        // Totais coerentes: subtotal - discount + shipping = total
        $orders = $pdo->query("SELECT id, total, discount_amount, shipping_cost FROM e5_orders WHERE user_id = 1")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orders as $o) {
            $items = $pdo->prepare("SELECT SUM(unit_price * quantity) AS subtotal FROM e5_order_items WHERE order_id = ?");
            $items->execute([$o['id']]);
            $subtotal = (float)$items->fetchColumn();
            $discount = (float)$o['discount_amount'];
            $shipping = (float)$o['shipping_cost'];
            $total = (float)$o['total'];
            $calculated = $subtotal - $discount + $shipping;
            $this->assertEqualsWithDelta($calculated, $total, 0.01, "Pedido {$o['id']}: subtotal ($subtotal) - discount ($discount) + shipping ($shipping) = $calculated, mas total salvo é $total");
        }
    }
}
