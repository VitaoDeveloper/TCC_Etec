-- @raw
-- Reversao de: 20261001_reconcile_schema_drift.sql
-- Data: 2026-10-01
--
-- ATENCAO — DESTRUTIVO
--   Remove as 5 colunas de medida de e5_products e a coluna cpf de
--   e5_users, junto com os dados nelas. E o inverso honesto do que a
--   migration faz; use apenas em base de desenvolvimento ou apos backup.
--   Em producao, o caminho correto e um novo up que neutralize o que a
--   migration fez, nao um down destrutivo.
--
-- Idempotente: cada remocao e verificada em information_schema antes.

DROP PROCEDURE IF EXISTS tcc_remover_coluna;

CREATE PROCEDURE tcc_remover_coluna(
    IN p_tabela VARCHAR(64),
    IN p_coluna VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_tabela
          AND COLUMN_NAME  = p_coluna
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_tabela, '` DROP COLUMN `', p_coluna, '`');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END;

-- A FK precisa sair antes das colunas que ela referencia.
DROP PROCEDURE IF EXISTS tcc_remover_fk;

CREATE PROCEDURE tcc_remover_fk()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME       = 'e5_products'
          AND CONSTRAINT_NAME  = 'fk_products_package_size'
    ) THEN
        ALTER TABLE e5_products DROP FOREIGN KEY fk_products_package_size;
    END IF;
END;

CALL tcc_remover_fk();

-- Ordem inversa da criacao, respeitando as dependencias.
CALL tcc_remover_coluna('e5_products', 'length_cm');
CALL tcc_remover_coluna('e5_products', 'width_cm');
CALL tcc_remover_coluna('e5_products', 'height_cm');
CALL tcc_remover_coluna('e5_products', 'weight_kg');
CALL tcc_remover_coluna('e5_products', 'package_size_id');
CALL tcc_remover_coluna('e5_users',    'cpf');

DROP PROCEDURE IF EXISTS tcc_remover_fk;
DROP PROCEDURE IF EXISTS tcc_remover_coluna;

-- A tabela e recriavel a partir de database.sql / da migration up.
DROP TABLE IF EXISTS superfrete_webhook_log;
