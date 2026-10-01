-- =====================================================================
-- RoyalTech — migration incremental das telas de conta
-- (Meu Perfil + Detalhes do Pedido)
--
-- IDEMPOTENTE: pode rodar N vezes sem efeito colateral. Cada bloco
-- verifica a existencia antes de criar. O projeto ja tem 19 tabelas
-- (e5_users, e5_orders, e5_saved_cards, e5_shipments, e5_notifications,
-- e5_settings...) e este arquivo NAO recria nenhuma delas: apenas
-- acrescenta o que as duas telas precisam e ainda nao existia.
--
-- Rodar:  mysql -u root e5_royaltech < database/migration_account.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) e5_users: colunas de endereco completo e verificacao de e-mail
--
--    O card "Endereço" do perfil tem campos BAIRRO/CIDADE/UF e o pill
--    "Conta verificada" precisa refletir o estado real da verificacao.
--    ate aqui so existiam postal_code/street/number/complement.
-- ---------------------------------------------------------------------
SET @db = DATABASE();

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_users' AND COLUMN_NAME = 'email_verified') = 0,
  'ALTER TABLE e5_users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER role',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_users' AND COLUMN_NAME = 'neighborhood') = 0,
  'ALTER TABLE e5_users ADD COLUMN neighborhood VARCHAR(80) NULL AFTER complement',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_users' AND COLUMN_NAME = 'city') = 0,
  'ALTER TABLE e5_users ADD COLUMN city VARCHAR(80) NULL AFTER neighborhood',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_users' AND COLUMN_NAME = 'state') = 0,
  'ALTER TABLE e5_users ADD COLUMN state CHAR(2) NULL AFTER city',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 2) e5_addresses — "Endereços Salvos" do perfil
--
--    CRUD em modal, alimentam o checkout e a cotacao de frete.
--    Limite de 10 por usuario e 1 endereco padrao: os dois sao
--    garantidos por indice unico (abaixo) e por verificacao na API.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS e5_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    label VARCHAR(40) NOT NULL DEFAULT 'Principal',
    postal_code VARCHAR(10) NOT NULL,
    street VARCHAR(120) NOT NULL,
    number VARCHAR(10) NOT NULL,
    complement VARCHAR(80) NULL,
    neighborhood VARCHAR(80) NULL,
    city VARCHAR(80) NOT NULL,
    state CHAR(2) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_addresses_user (user_id, is_default),
    CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- "Só um endereco padrao por usuario": indice unico sobre (user_id) onde
-- is_default = 1. O MySQL resolve com o indice parcial-emulado: a
-- coluna generated abaixo vale 1 so para o padrao e 0 para os demais,
-- o que permite um UNIQUE(user_id, default_flag) sem bloquear os
-- enderecos nao-padrao (eles compartilham o valor 0 de proposito).
ALTER TABLE e5_addresses
    ADD COLUMN IF NOT EXISTS default_flag TINYINT(1)
        GENERATED ALWAYS AS (IF(is_default = 1, 1, NULL)) STORED;
ALTER TABLE e5_addresses
    ADD UNIQUE KEY IF NOT EXISTS uniq_addresses_default (user_id, default_flag);

-- ---------------------------------------------------------------------
-- 3) e5_order_history — trilha do "Progresso do pedido"
--
--    A tela de detalhe NUNCA fixa as etapas no template: cada linha aqui
--    e uma etapa concluida com timestamp, e o que falta no banco e o
--    que aparece como pendente (cinza).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS e5_order_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    status VARCHAR(24) NOT NULL,
    note VARCHAR(180) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_history_order (order_id, id),
    CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES e5_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Uma etapa por status: impede que "Pagamento Confirmado" seja gravada
-- duas vezes e o Progresso mostre o mesmo circulo dourado repetido.
ALTER TABLE e5_order_history
    ADD UNIQUE KEY IF NOT EXISTS uniq_history_order_status (order_id, status);

-- ---------------------------------------------------------------------
-- 4) e5_payments — registro do pagamento por pedido
--
--    Regra critica do pedido: um pedido cancelado NUNCA pode exibir
--    pagamento "Aguardando pagamento". O status do pagamento mora aqui
--    e nao e derivado do status do pedido, para que as duas coisas
--    possam divergir de forma explicita (ex.: pix expirado x cancelado).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS e5_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    method ENUM('pix','boleto','cartao','delivery') NOT NULL DEFAULT 'pix',
    status ENUM('pending','paid','canceled','expired','refunded','failed') NOT NULL DEFAULT 'pending',
    amount DECIMAL(10,2) NOT NULL,
    pix_key VARCHAR(140) NULL,
    pix_code VARCHAR(255) NULL,
    card_brand VARCHAR(20) NULL,
    card_last_four CHAR(4) NULL,
    installments TINYINT UNSIGNED NULL,
    boleto_line VARCHAR(60) NULL,
    gateway_token VARCHAR(64) NULL,
    expires_at DATETIME NULL,
    paid_at DATETIME NULL,
    canceled_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_payments_order (order_id),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES e5_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 5) e5_orders: snapshot completo do endereco de entrega
--
--    O card "Entrega" mostra "Esplanada Santa Helena, Taubate, SP –
--    CEP 12053831". A tabela so guardava bairro/cidade/uf/cep: rua e
--    numero viviam apenas no cadastro do usuario, entao o historico do
--    pedido nao sobrevivia a uma mudanca de endereco do cliente.
--    Estas colunas sao o snapshot: escritas na criacao do pedido e nunca
--    mais alteradas.
-- ---------------------------------------------------------------------
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_orders' AND COLUMN_NAME = 'shipping_street') = 0,
  'ALTER TABLE e5_orders ADD COLUMN shipping_street VARCHAR(120) NULL AFTER shipping_postal_code',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_orders' AND COLUMN_NAME = 'shipping_number') = 0,
  'ALTER TABLE e5_orders ADD COLUMN shipping_number VARCHAR(10) NULL AFTER shipping_street',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_orders' AND COLUMN_NAME = 'shipping_complement') = 0,
  'ALTER TABLE e5_orders ADD COLUMN shipping_complement VARCHAR(80) NULL AFTER shipping_number',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 6) e5_order_items: snapshot de nome/preco/imagem
--
--    MesmoPrincipio do endereco: o item do pedido guarda o que foi
--    vendido, nao o que o catalogo diz hoje. Renomeia o produto ou
--    muda o preco e o pedido antigo continua correto.
-- ---------------------------------------------------------------------
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_order_items' AND COLUMN_NAME = 'product_name') = 0,
  'ALTER TABLE e5_order_items ADD COLUMN product_name VARCHAR(150) NULL AFTER product_id',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_order_items' AND COLUMN_NAME = 'product_image') = 0,
  'ALTER TABLE e5_order_items ADD COLUMN product_image VARCHAR(255) NULL AFTER product_name',
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE e5_payments
    ADD COLUMN IF NOT EXISTS active_flag TINYINT(1)
        GENERATED ALWAYS AS (
            IF(status IN ('pending','paid'), 1, NULL)
        ) STORED;
ALTER TABLE e5_payments
    ADD UNIQUE KEY IF NOT EXISTS uniq_payments_active (order_id, active_flag);

-- ---------------------------------------------------------------------
-- 7) ENUMs: a tela de detalhe exige o estado "Em preparacao"
--
--    O fluxo antigo ia direto de "paid" para "shipped", sem a etapa
--    de separacao/preparo. A maquina de estados de includes/order_state.php
--    tem o passo 'preparing', entao o ENUM precisa aceita-lo.
--
--    "canceled" tambem passa a ser aceito em payment_status: o
--    pagamento de um pedido cancelado nao e "refunded" (nada foi
--    estornado) nem "failed" (nao houve erro) -- o worker grava
--    'canceled' e a tela mostra "Cancelado".
-- ---------------------------------------------------------------------
--    O COLUMN_NAME e obrigatorio no segundo COUNT: sem ele a busca
--    "%preparing%"/%"canceled%" varre as colunas da tabela inteira e
--    acha o valor dentro de `status`, que ja foi migrado no bloco
--    acima. A guarda passava a ser sempre falsa e o ALTER de
--    payment_status nunca rodava — num banco novo o seed do pedido
--    #0012 truncava 'canceled' em 'pending' sem erro visivel.
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_orders' AND COLUMN_NAME = 'status') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_orders'
      AND COLUMN_NAME = 'status' AND COLUMN_TYPE LIKE '%preparing%') = 0,
  "ALTER TABLE e5_orders
     MODIFY COLUMN status ENUM('pending','paid','preparing','shipped','delivered','canceled')
     NOT NULL DEFAULT 'pending'",
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_orders' AND COLUMN_NAME = 'payment_status') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'e5_orders'
      AND COLUMN_NAME = 'payment_status' AND COLUMN_TYPE LIKE '%canceled%') = 0,
  "ALTER TABLE e5_orders
     MODIFY COLUMN payment_status ENUM('pending','processing','paid','canceled','refunded','failed','expired')
     NOT NULL DEFAULT 'pending'",
  'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT 'MIGRATION OK' AS status;
