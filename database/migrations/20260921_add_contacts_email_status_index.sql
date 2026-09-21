-- Migration: índice para a regra "1 contato por e-mail até a devolutiva do admin"
-- Data: 2026-09-21
-- Consulta favorecida: SELECT ... FROM e5_contacts WHERE email = ? AND status = 'pending'
-- Aplicar em bases existentes com:
--   mysql -u root e5_royaltech < database/migrations/20260921_add_contacts_email_status_index.sql
-- (Instalações novas já recebem o índice via database.sql)
ALTER TABLE e5_contacts ADD INDEX idx_contacts_email_status (email, status);