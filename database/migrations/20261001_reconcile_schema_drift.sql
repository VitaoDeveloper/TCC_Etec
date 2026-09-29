-- @raw
-- Migration: reconcilia o schema do banco de desenvolvimento com o codigo
-- Data: 2026-10-01
-- Autor: lote 1b / etapa 2 (reconciliacao de schema)
--
-- POR QUE ESTA MIGRATION EXISTE
-- -----------------------------
-- O codigo consulta colunas que o banco de desenvolvimento nao tinha, porque
-- ninguem mantinha registro do que ja tinha sido aplicado (ver
-- database/migrations/20260915_add_contact_replies.sql, nunca executada).
-- Consequencia observada: cart.php, checkout.php e package-sizes.php
-- respondiam HTTP 500 com "Unknown column 'p.package_size_id'".
--
-- ESCOPO (verificado por SHOW COLUMNS contra o banco local em 2026-09-29)
--   e5_products  faltavam: package_size_id, weight_kg, height_cm, width_cm, length_cm
--   e5_users     faltava:   cpf
--   superfrete_webhook_log  tabela inteira, usada por WebhookHandler.php
--
-- IDEMPOTENCIA
--   Rodar duas vezes nao faz nada e nao falha. MySQL 8.0 nao possui
--   "ADD COLUMN IF NOT EXISTS" nem "DROP COLUMN IF EXISTS" (verificado em
--   8.0.46), entao cada comando passa por uma verificacao em
--   information_schema antes de executar. Isso tambem torna a migration
--   segura em instalacao nova, onde database.sql ja criou as colunas.
--
-- PRE-REQUISITO
--   e5_package_sizes precisa existir para a chave estrangeira ser criada
--   (database.sql cria; em base legada, a migration aditiva equivalente
--   tambem). Se a tabela faltar, a migration NAO adiciona a FK em vez de
--   falhar — o resto do DDL continua valendo.
--
-- REVERSAO: 20261001_reconcile_schema_drift.down.sql
--   ATENCAO: o down descarta os dados dessas colunas. Use apenas em base de
--   desenvolvimento ou apos backup.

DROP PROCEDURE IF EXISTS tcc_coluna_ausente;

CREATE PROCEDURE tcc_coluna_ausente(
    IN p_tabela     VARCHAR(64),
    IN p_coluna     VARCHAR(64),
    IN p_definicao  TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_tabela
          AND COLUMN_NAME  = p_coluna
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_tabela, '` ADD COLUMN `', p_coluna, '` ', p_definicao);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END;

-- Medidas de envio: uma linha por produto, usada no calculo do frete.
CALL tcc_coluna_ausente('e5_products', 'package_size_id', 'INT NULL AFTER stock');
CALL tcc_coluna_ausente('e5_products', 'weight_kg',        'DECIMAL(5,2) NULL AFTER package_size_id');
CALL tcc_coluna_ausente('e5_products', 'height_cm',        'DECIMAL(5,1) NULL AFTER weight_kg');
CALL tcc_coluna_ausente('e5_products', 'width_cm',         'DECIMAL(5,1) NULL AFTER height_cm');
CALL tcc_coluna_ausente('e5_products', 'length_cm',        'DECIMAL(5,1) NULL AFTER width_cm');

-- Documento do cliente, usado na emissao de nota fiscal.
CALL tcc_coluna_ausente('e5_users', 'cpf', 'VARCHAR(14) NULL AFTER email');

DROP PROCEDURE IF EXISTS tcc_coluna_ausente;

DROP PROCEDURE IF EXISTS tcc_fk_package_size;

CREATE PROCEDURE tcc_fk_package_size()
BEGIN
    -- So adiciona se a FK ainda nao existe E a tabela referenciada existir.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME       = 'e5_products'
          AND CONSTRAINT_NAME  = 'fk_products_package_size'
    ) AND EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'e5_package_sizes'
    ) THEN
        SET @ddl_fk = 'ALTER TABLE e5_products ADD CONSTRAINT fk_products_package_size
                       FOREIGN KEY (package_size_id) REFERENCES e5_package_sizes(id)';
        PREPARE stmt FROM @ddl_fk;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END;

CALL tcc_fk_package_size();

DROP PROCEDURE IF EXISTS tcc_fk_package_size;

-- Idempotidade de webhooks: guarda event_id unico para rejeitar reentrega.
CREATE TABLE IF NOT EXISTS superfrete_webhook_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id      VARCHAR(128) NOT NULL,
    event_type    VARCHAR(64)  NOT NULL,
    order_id      VARCHAR(128) DEFAULT NULL,
    payload_hash  CHAR(64)     NOT NULL,
    processed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_event_id (event_id),
    KEY idx_order_id (order_id),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB;
