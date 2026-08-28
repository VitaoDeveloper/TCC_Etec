-- Migration 002: Security, Auditing and Tax Regime Snapshot
-- Purpose: Add missing security, audit trail and per-order tax regime tracking
-- Date: 2026-08-27

-- ======================================================================
-- 1. TAX REGIME SNAPSHOT PER ORDER
-- ======================================================================
-- Prevents inconsistency when regime changes mid-order processing
ALTER TABLE e5_orders 
ADD COLUMN tax_regime_snapshot ENUM('CPF','MEI') NOT NULL DEFAULT 'CPF' AFTER payment_status,
ADD COLUMN regime_captured_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER tax_regime_snapshot;

-- Backfill existing orders with current regime
UPDATE e5_orders 
SET tax_regime_snapshot = (
    SELECT COALESCE(setting_value, 'CPF') 
    FROM e5_settings 
    WHERE setting_key = 'tax_regime' 
    LIMIT 1
)
WHERE tax_regime_snapshot = 'CPF';

-- ======================================================================
-- 2. REGIME CHANGE AUDIT LOG
-- ======================================================================
CREATE TABLE IF NOT EXISTS e5_regime_change_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  regime_anterior ENUM('CPF','MEI') NOT NULL,
  regime_novo ENUM('CPF','MEI') NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent TEXT NULL,
  success TINYINT(1) NOT NULL DEFAULT 1,
  error_message TEXT NULL,
  cnpj VARCHAR(18) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_regime_log_user FOREIGN KEY (user_id) REFERENCES e5_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ======================================================================
-- 3. ENCRYPTED CREDENTIALS STORAGE
-- ======================================================================
-- Replace plain text API keys with encrypted storage
ALTER TABLE e5_settings 
MODIFY COLUMN setting_value MEDIUMTEXT NULL COMMENT 'Encrypted for sensitive keys (nfe_api_key, melhor_envio_token)';

-- Add encryption metadata
CREATE TABLE IF NOT EXISTS e5_encrypted_settings (
  setting_key VARCHAR(64) PRIMARY KEY,
  encrypted_value TEXT NOT NULL,
  encryption_version VARCHAR(10) NOT NULL DEFAULT 'v1',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ======================================================================
-- 4. PENDING ORDERS TRACKING VIEW
-- ======================================================================
-- View to identify orders requiring retroactive NF-e emission
CREATE OR REPLACE VIEW v_pending_nfe_orders AS
SELECT 
    o.id,
    o.user_id,
    o.total,
    o.tax_regime_snapshot,
    o.status,
    o.payment_status,
    o.invoice_status,
    o.created_at,
    DATEDIFF(NOW(), o.created_at) as days_pending,
    u.name as customer_name,
    u.email as customer_email
FROM e5_orders o
INNER JOIN e5_users u ON u.id = o.user_id
WHERE o.invoice_status = 'pending'
  AND o.payment_status = 'paid'
  AND o.status IN ('paid', 'shipped', 'delivered')
  AND o.tax_regime_snapshot = 'MEI'
ORDER BY o.created_at ASC;

-- ======================================================================
-- 5. IR WITHHOLDING MONITORING (CPF MODE)
-- ======================================================================
CREATE TABLE IF NOT EXISTS e5_cpf_revenue_tracking (
  id INT PRIMARY KEY AUTO_INCREMENT,
  month_year VARCHAR(7) NOT NULL COMMENT 'Format: YYYY-MM',
  total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  order_count INT NOT NULL DEFAULT 0,
  last_order_id INT NULL,
  alert_threshold_reached TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_month (month_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ======================================================================
-- 6. GATEWAY FEE DOCUMENTATION
-- ======================================================================
-- Track actual gateway fees (not estimates)
CREATE TABLE IF NOT EXISTS e5_gateway_fees (
  id INT PRIMARY KEY AUTO_INCREMENT,
  gateway_name VARCHAR(50) NOT NULL COMMENT 'mercadopago, asaas, etc',
  document_type ENUM('CPF','CNPJ') NOT NULL,
  fee_percentage DECIMAL(5,2) NOT NULL,
  fee_fixed DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  source_url VARCHAR(255) NULL COMMENT 'Link to official pricing page',
  verified_at TIMESTAMP NULL,
  is_estimate TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed with documented estimates (to be verified)
INSERT INTO e5_gateway_fees (gateway_name, document_type, fee_percentage, fee_fixed, is_estimate, notes) VALUES
('mercadopago', 'CPF', 3.99, 0.00, 1, 'Estimativa - verificar em https://www.mercadopago.com.br/costs'),
('mercadopago', 'CNPJ', 2.99, 0.00, 1, 'Estimativa - verificar com representante comercial'),
('asaas', 'CPF', 3.49, 0.00, 1, 'Estimativa - verificar em https://www.asaas.com/precos'),
('asaas', 'CNPJ', 2.99, 0.00, 1, 'Estimativa - verificar com representante comercial');

-- ======================================================================
-- 7. UPDATE EXISTING SETTINGS WITH WARNINGS
-- ======================================================================
UPDATE e5_settings 
SET setting_key = CONCAT(setting_key, '_ESTIMATE')
WHERE setting_key IN ('payment_fee_cpf', 'payment_fee_mei')
  AND setting_key NOT LIKE '%_ESTIMATE';

-- Add disclaimer setting
INSERT INTO e5_settings (setting_key, setting_value) VALUES
('fee_disclaimer', 'Taxas são estimativas. Confirme com seu gateway de pagamento.')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
