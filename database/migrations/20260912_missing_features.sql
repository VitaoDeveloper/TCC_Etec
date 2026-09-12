SET NAMES utf8mb4;

-- =====================================================================
-- Migração 2026-09-12 — Funcionalidades faltantes em Perfil e Carrinho
-- (endereços salvos, cartões salvos, telefone, reviews, rastreio,
--  estorno/cancelamento admin com restauração de estoque, NF)
-- =====================================================================

USE e5_royaltech;

-- ---------------------------------------------------------------
-- e5_users: colunas já usadas pela UI do perfil + telefone
-- ---------------------------------------------------------------
ALTER TABLE e5_users ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL;
ALTER TABLE e5_users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL;
ALTER TABLE e5_users ADD COLUMN IF NOT EXISTS notify_email TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE e5_users ADD COLUMN IF NOT EXISTS notify_whatsapp TINYINT(1) NOT NULL DEFAULT 1;

-- ---------------------------------------------------------------
-- e5_orders: rastreio/NF/estorno + proteção contra dupla devolução
-- ---------------------------------------------------------------
ALTER TABLE e5_orders ADD COLUMN IF NOT EXISTS stock_restored TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE e5_orders ADD COLUMN IF NOT EXISTS nf_number VARCHAR(30) NULL;
ALTER TABLE e5_orders ADD COLUMN IF NOT EXISTS nf_key VARCHAR(44) NULL;
ALTER TABLE e5_orders ADD COLUMN IF NOT EXISTS nf_emitted_at DATETIME NULL;
ALTER TABLE e5_orders ADD COLUMN IF NOT EXISTS refund_reason VARCHAR(255) NULL;
ALTER TABLE e5_orders ADD COLUMN IF NOT EXISTS refunded_at DATETIME NULL;
ALTER TABLE e5_orders ADD COLUMN IF NOT EXISTS refunded_by VARCHAR(80) NULL;

-- ---------------------------------------------------------------
-- Endereços salvos do cliente
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS e5_user_addresses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  label VARCHAR(40) NOT NULL DEFAULT 'Entrega',
  recipient VARCHAR(120) NULL,
  postal_code VARCHAR(10) NOT NULL,
  street VARCHAR(120) NOT NULL,
  number VARCHAR(10) NOT NULL DEFAULT '',
  complement VARCHAR(80) NULL,
  neighborhood VARCHAR(80) NULL,
  city VARCHAR(80) NOT NULL,
  state VARCHAR(40) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_addresses_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------
-- Avaliações / reviews de produto
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS e5_reviews (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_review (user_id, product_id),
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES e5_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------
-- Settings novos
-- ---------------------------------------------------------------
INSERT IGNORE INTO e5_settings (setting_key, setting_value) VALUES
('nf_counter', '0'),
('frete_fallback_cost', '25.90'),
('frete_fallback_days', '5-10');