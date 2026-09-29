-- Reversao de: 20260915_add_contact_replies.sql
-- Data: 2026-09-15 (reversao adicionada em 2026-10-01, lote 1b / etapa 2)
--
-- Remove o fluxo de resposta do admin em e5_contacts (colunas + FKs).
-- DESTRUTIVO: apaga status de resposta, mensagem, autor e log de e-mail.
-- Use apenas em base de desenvolvimento ou apos backup.

ALTER TABLE e5_contacts DROP FOREIGN KEY fk_contacts_responded_by;
ALTER TABLE e5_contacts DROP FOREIGN KEY fk_contacts_user;

ALTER TABLE e5_contacts DROP COLUMN response_email_error;
ALTER TABLE e5_contacts DROP COLUMN response_email_status;
ALTER TABLE e5_contacts DROP COLUMN responded_at;
ALTER TABLE e5_contacts DROP COLUMN responded_by;
ALTER TABLE e5_contacts DROP COLUMN response_message;
ALTER TABLE e5_contacts DROP COLUMN status;
ALTER TABLE e5_contacts DROP COLUMN user_id;
ALTER TABLE e5_contacts DROP COLUMN updated_at;
