-- Migration 003: Multi-Gateway Payment Support + Security Fixes (CORRECTED)
-- Purpose: Enable multiple payment gateways (Mercado Pago + Asaas) with proper snapshot per order
-- Date: 2026-08-27

-- ======================================================================
-- PART A: MULTI-GATEWAY INFRASTRUCTURE
-- ======================================================================

-- 1. Payment Gateways Configuration Table
CREATE TABLE IF NOT EXISTS e5_payment_gateways (
  id INT PRIMARY KEY AUTO_INCREMENT,
  gateway_name VARCHAR(50) NOT NULL UNIQUE COMMENT 'mercadopago, asaas',
  display_name VARCHAR(100) NOT NULL COMMENT 'Nome amigável para exibição',
  
  -- Encrypted credentials (referenced from encrypted_settings table)
  access_token_key VARCHAR(100) NULL COMMENT 'Reference to encrypted token in e5_encrypted_settings',
  public_key_key VARCHAR(100) NULL COMMENT 'Reference to encrypted public key in e5_encrypted_settings',
  webhook_secret_key VARCHAR(100) NULL COMMENT 'Reference to encrypted webhook secret in e5_encrypted_settings',
  
  -- Status and configuration
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  is_configured TINYINT(1) NOT NULL DEFAULT 0,
  last_health_check TIMESTAMP NULL,
  health_check_status ENUM('success','failure','pending') NULL,
  health_check_message TEXT NULL,
  
  -- Fee configuration flags (actual fees stored in e5_gateway_fees)
  supports_cpf TINYINT(1) NOT NULL DEFAULT 1,
  supports_cnpj TINYINT(1) NOT NULL DEFAULT 1,
  
  -- Operational metadata
  webhook_url VARCHAR(255) NULL,
  api_base_url VARCHAR(255) NULL,
  sandbox_mode TINYINT(1) NOT NULL DEFAULT 1,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_active (is_active),
  INDEX idx_configured (is_configured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Update existing orders with gateway_used snapshot
ALTER TABLE e5_orders 
ADD COLUMN gateway_used VARCHAR(50) NULL AFTER payment_method,
ADD COLUMN gateway_transaction_id VARCHAR(255) NULL AFTER gateway_used,
ADD COLUMN gateway_captured_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER gateway_transaction_id,
ADD INDEX idx_gateway_used (gateway_used);

-- Backfill existing orders with default gateway
UPDATE e5_orders 
SET gateway_used = 'mercadopago',
    gateway_captured_at = created_at
WHERE gateway_used IS NULL;

-- 3. Generalize system change log to support multiple change types
ALTER TABLE e5_system_change_log
MODIFY COLUMN change_type ENUM('regime','gateway','credentials','fee') NOT NULL DEFAULT 'regime' AFTER id,
ADD COLUMN gateway_name VARCHAR(50) NULL AFTER cnpj,
ADD COLUMN config_before TEXT NULL AFTER gateway_name,
ADD COLUMN config_after TEXT NULL AFTER config_before,
ADD INDEX idx_change_type (change_type);

-- ======================================================================
-- PART B: WEBHOOK INFRASTRUCTURE
-- ======================================================================

-- 6. Webhook processing log (always active for both gateways)
CREATE TABLE IF NOT EXISTS e5_webhook_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  gateway_name VARCHAR(50) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  event_id VARCHAR(255) NULL,
  payload JSON NOT NULL,
  signature VARCHAR(512) NULL,
  signature_valid TINYINT(1) NULL,
  processing_status ENUM('pending','processed','failed','ignored') NOT NULL DEFAULT 'pending',
  processing_attempts INT NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  order_id INT NULL,
  processed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_gateway (gateway_name),
  INDEX idx_event_type (event_type),
  INDEX idx_status (processing_status),
  INDEX idx_order (order_id),
  FOREIGN KEY (order_id) REFERENCES e5_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Add verification status tracking to gateway fees
ALTER TABLE e5_gateway_fees
ADD COLUMN last_verified_at TIMESTAMP NULL AFTER verified_at,
ADD COLUMN verification_status ENUM('current','outdated','unverified') NOT NULL DEFAULT 'unverified' AFTER last_verified_at;

-- Update existing records
UPDATE e5_gateway_fees 
SET last_verified_at = arr.created_at,
    verification_status = 'unverified'
WHERE last_verified_at IS NULL;

-- Seed payment gateways
INSERT INTO e5_payment_gateways (gateway_name, display_name, webhook_url, api_base_url, sandbox_mode, supports_cpf, supports_cnpj) VALUES
('mercadopago', 'Mercado Pago', '/api/webhooks/mercadopago', 'https://api.mercadopago.com', 1, 1, 1),
('asaas', 'Asaas', '/api/webhooks/asaas', 'https://www.asaas.com/api/v3', 1, 1, 1);

-- Set Mercado Pago as default active (can be changed in admin panel)
UPDATE e5_payment_gateways 
SET is_active = 1 
WHERE gateway_name = 'mercadopago';

-- ======================================================================
-- PART C: CHECKOUT SESSION MANAGEMENT
-- ======================================================================

-- 8. Auto-manage checkout session workshop
-- Use existing session expiration mechanism e5_session_timeout (already exists)
-- For checkout-specific sessions, use e5_checkout_sessions table

-- 9. Aggregate fee configurations from multiple sources
-- No schema changes needed - use existing e5_gateway_fees with updated data structure

-- ======================================================================
-- VERIFICATION QUERIES
-- ======================================================================

-- Check active gateway
-- SELECT * FROM e5_payment_gateways WHERE is_active = 1;

-- Check gateway fees verification status
-- SELECT * FROM e5_gateway_fees;

-- Check recent gateway changes
-- SELECT * FROM e5_system_change_log WHERE change_type = 'gateway' ORDER BY created_at DESC LIMIT 10;

-- Check webhook processing status
-- SELECT gateway_name, processing_status, COUNT(*) as count FROM e5_webhook_log GROUP BY gateway_name, processing_status;
