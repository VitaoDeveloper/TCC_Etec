-- Migration: Add seller profile and tax regime support
-- Purpose: Prepare database for CPF→MEI transition without schema changes
-- Date: 2026-08-27

-- ======================================================================
-- SELLER PROFILE TABLE (supports both CPF and MEI/CNPJ)
-- ======================================================================
CREATE TABLE IF NOT EXISTS e5_seller_profile (
  id INT PRIMARY KEY AUTO_INCREMENT,
  
  -- Document info (nullable until MEI is opened)
  document_type ENUM('CPF','CNPJ') NOT NULL DEFAULT 'CPF',
  document_number VARCHAR(18) NOT NULL COMMENT 'CPF: 000.000.000-00 | CNPJ: 00.000.000/0000-00',
  
  -- Legal entity info (nullable for CPF, required for MEI)
  legal_name VARCHAR(120) NULL COMMENT 'Razão Social (MEI only)',
  trade_name VARCHAR(120) NULL COMMENT 'Nome Fantasia (MEI only)',
  state_registration VARCHAR(20) NULL COMMENT 'Inscrição Estadual (optional)',
  
  -- Tax regime
  tax_regime ENUM('CPF','MEI') NOT NULL DEFAULT 'CPF',
  
  -- Operational flags
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  nfe_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'NF-e emission active',
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY unique_document (document_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ======================================================================
-- EXTEND SETTINGS TABLE (tax regime configuration)
-- ======================================================================
-- New settings keys (auto-created via store_config_save):
-- - tax_regime: 'CPF' | 'MEI'
-- - nfe_provider: 'focus' | 'nfeio' | 'disabled'
-- - nfe_api_key: string (encrypted in production)
-- - nfe_environment: 'homologacao' | 'producao'
-- - payment_gateway: 'mercadopago' | 'asaas'
-- - payment_fee_cpf: decimal (percentage)
-- - payment_fee_mei: decimal (percentage)
-- - melhor_envio_token: string (encrypted)
-- - melhor_envio_table: 'public' | 'commercial'

-- ======================================================================
-- EXTEND ORDERS TABLE (invoice tracking)
-- ======================================================================
ALTER TABLE e5_orders 
ADD COLUMN invoice_number VARCHAR(20) NULL COMMENT 'NF-e number' AFTER payment_status,
ADD COLUMN invoice_key VARCHAR(44) NULL COMMENT 'NF-e access key' AFTER invoice_number,
ADD COLUMN invoice_pdf_url VARCHAR(255) NULL COMMENT 'NF-e PDF download URL' AFTER invoice_key,
ADD COLUMN invoice_xml_url VARCHAR(255) NULL COMMENT 'NF-e XML download URL' AFTER invoice_pdf_url,
ADD COLUMN invoice_status ENUM('pending','issued','error','canceled') NULL DEFAULT 'pending' AFTER invoice_xml_url,
ADD COLUMN invoice_error_message TEXT NULL COMMENT 'Error details if emission failed' AFTER invoice_status;

-- ======================================================================
-- SEED: Initial seller profile (CPF mode by default)
-- ======================================================================
INSERT INTO e5_seller_profile (document_type, document_number, legal_name, trade_name, tax_regime, nfe_enabled) VALUES
('CPF', '000.000.000-00', NULL, NULL, 'CPF', 0);

-- ======================================================================
-- SEED: Tax regime settings
-- ======================================================================
INSERT INTO e5_settings (setting_key, setting_value) VALUES
('tax_regime', 'CPF'),
('nfe_provider', 'disabled'),
('nfe_environment', 'homologacao'),
('payment_gateway', 'mercadopago'),
('payment_fee_cpf', '3.99'),
('payment_fee_mei', '2.99'),
('melhor_envio_table', 'public')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
