-- Reversao de: 20260921_add_contacts_email_status_index.sql
-- Data: 2026-09-21 (reversao adicionada em 2026-10-01, lote 1b / etapa 2)
--
-- Remove o indice que serve a regra "1 contato por e-mail ate a devolutiva
-- do admin". Sem dados afetados, so o indice de leitura.

ALTER TABLE e5_contacts DROP INDEX idx_contacts_email_status;
