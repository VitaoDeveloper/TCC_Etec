-- Migration: e5_contacts - suporte a resposta do admin (issue #68)
-- Data: 2026-09-15
-- Aplicar em bases existentes com: mysql -u root e5_royaltech < database/migrations/20260915_add_contact_replies.sql
-- (Instalações novas já recebem o schema completo via database.sql)

ALTER TABLE e5_contacts
  ADD COLUMN user_id INT NULL AFTER id,
  ADD COLUMN status ENUM('pending','answered') NOT NULL DEFAULT 'pending' AFTER message,
  ADD COLUMN response_message TEXT NULL AFTER status,
  ADD COLUMN responded_by INT NULL AFTER response_message,
  ADD COLUMN responded_at TIMESTAMP NULL AFTER responded_by,
  ADD COLUMN response_email_status ENUM('sent','failed','skipped') NULL AFTER responded_at,
  ADD COLUMN response_email_error TEXT NULL AFTER response_email_status,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER response_email_error,
  ADD CONSTRAINT fk_contacts_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_contacts_responded_by FOREIGN KEY (responded_by) REFERENCES e5_users(id) ON DELETE SET NULL;