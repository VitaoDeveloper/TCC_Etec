-- =====================================================================
-- Pedido de demonstracao #0012 (id 12)
--
-- Cenario pedido pela especificacao da tela "Detalhes do Pedido":
-- um Pix que o cliente NAO pagou e cancelou. Serve para provar as
-- regras mais sensiveis da tela:
--   - pedido cancelado jamais mostra "Aguardando pagamento"
--   - o progresso nao avanca etapas
--   - o endereco e o item exibidos vem do snapshot, nao do cadastro
--     atual do cliente
--
-- Idempotente: pode rodar quantas vezes quiser. Nao mexe em estoque —
-- um pedido cancelado nao reserva mercadoria, e mexer aqui deixaria o
-- catalogo inconsistente com o resto do banco.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) O pedido
-- ---------------------------------------------------------------------
INSERT INTO e5_orders
    (id, user_id, status, payment_status, payment_method, total, shipping_method,
     shipping_cost, shipping_street, shipping_number, shipping_complement,
     shipping_neighborhood, shipping_city, shipping_state, shipping_postal_code,
     created_at, updated_at)
VALUES
    (12, 16, 'canceled', 'canceled', 'pix', 4599.90, 'PAC',
     0.00, 'Esplanada Santa Helena', 'S/N', NULL,
     'Esplanada Santa Helena', 'Taubaté', 'SP', '12053-831',
     '2026-09-16 18:47:00', '2026-09-16 18:52:00')
ON DUPLICATE KEY UPDATE
    user_id              = VALUES(user_id),
    status               = VALUES(status),
    payment_status       = VALUES(payment_status),
    payment_method       = VALUES(payment_method),
    total                = VALUES(total),
    shipping_method      = VALUES(shipping_method),
    shipping_cost        = VALUES(shipping_cost),
    shipping_street      = VALUES(shipping_street),
    shipping_number      = VALUES(shipping_number),
    shipping_complement  = VALUES(shipping_complement),
    shipping_neighborhood = VALUES(shipping_neighborhood),
    shipping_city        = VALUES(shipping_city),
    shipping_state       = VALUES(shipping_state),
    shipping_postal_code = VALUES(shipping_postal_code),
    updated_at           = VALUES(updated_at);

-- ---------------------------------------------------------------------
-- 2) O item, com snapshot de nome e preco
--
--    product_name/unit_price sao copia do momento da compra. Se o
--    catalogo mudar depois, a tela deste pedido continua mostrando o
--    que foi realmente vendido.
--
--    DELETE + INSERT (e nao ON DUPLICATE KEY) porque e5_order_items
--    so tem chave primaria no id: nao existe unicidade em
--    (order_id, product_id), entao o ON DUPLICATE nunca dispararia e
--    cada execucao do seed acrescentaria mais uma linha do mesmo item.
-- ---------------------------------------------------------------------
DELETE FROM e5_order_items WHERE order_id = 12;

INSERT INTO e5_order_items
    (order_id, product_id, product_name, product_image, quantity, unit_price)
VALUES
    (12, 1, 'Smartphone Galaxy S25 256GB', 'assets/img/products/galaxy-s25.jpg', 1, 4599.90);

-- ---------------------------------------------------------------------
-- 3) O pagamento, cancelado
--
--    'canceled' e nao 'refunded': nada foi pago, entao nao existe
--    estorno. E nao 'failed': nao houve erro de processamento — o
--    cliente desistiu. O rotulo "Cancelado" e o correto na ficha dele.
--
--    DELETE + INSERT pelo mesmo motivo do item acima, e por um motivo
--    a mais: o indice unico de e5_payments e (order_id, active_flag),
--    e active_flag de um pagamento cancelado e NULL. Em MySQL, NULLs
--    nao colidem num indice unico, entao o ON DUPLICATE KEY passaria
--    batido e cada execucao criaria outro pagamento para o pedido 12.
-- ---------------------------------------------------------------------
DELETE FROM e5_payments WHERE order_id = 12;

INSERT INTO e5_payments
    (order_id, method, status, amount, canceled_at, created_at, updated_at)
VALUES
    (12, 'pix', 'canceled', 4599.90, '2026-09-16 18:52:00', '2026-09-16 18:47:00', '2026-09-16 18:52:00');

-- ---------------------------------------------------------------------
-- 4) O historico do progresso
--
--    Duas etapas: o pedido existiu e depois foi cancelado. Nao ha
--    'paid' — foi isso que o pedido #0012 nunca teve.
--
--    INSERT IGNORE porque ha indice unico (order_id, status): rodar o
--    seed de novo nao duplica as etapas.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO e5_order_history (order_id, status, note, created_at)
VALUES (12, 'placed',   'Pedido realizado',           '2026-09-16 18:47:00');

INSERT IGNORE INTO e5_order_history (order_id, status, note, created_at)
VALUES (12, 'canceled', 'Cancelado pelo cliente',    '2026-09-16 18:52:00');

SELECT 'SEED PEDIDO #0012 OK' AS status;
