# DOCUMENTAÇÃO — Royal Tech

Última atualização: 28 de agosto de 2026 (atualizado após implementação Frete Real + Pix Real)

## Visão Geral

Royal Tech é um e-commerce premium desenvolvido como Trabalho de Conclusão de Curso (TCC) da ETEC. A loja virtual possui identidade visual sofisticada em preto, dourado e branco, catálogo de produtos de tecnologia, carrinho, checkout, área do cliente e painel administrativo.

Funcionalidades da loja:

- Catálogo responsivo.
- Busca, filtros e paginação.
- Carrinho.
- Checkout.
- Lista de desejos.
- Perfil do usuário.
- Histórico de pedidos.
- Recuperação de senha.
- Newsletter.
- Contato.

Funcionalidades administrativas:

- Dashboard com métricas.
- CRUD de produtos.
- CRUD de categorias.
- CRUD de banners.
- Gerenciamento de pedidos.
- Gerenciamento de clientes.
- Relatórios.
- Newsletter.
- Configurações do sistema.
- Central de Migração MEI.

Projeto educacional — TCC ETEC.

## Arquitetura

Stack principal:

| Camada | Tecnologia |
|--------|------------|
| Backend | PHP 8+ vanilla |
| Banco de Dados | MySQL 8 |
| Frontend | HTML5, CSS3, JavaScript vanilla |
| Ícones | Font Awesome 6.4 |
| Tipografia | Playfair Display + Rajdhani |
| Dependências | PHPMailer, DomPDF |

Estrutura principal:

```text
assets/             estilos, imagens e scripts
components/         header, footer e product-card
database/           conexão, schema base e migrations
includes/           config, csrf, mail, rate_limit, helpers, frete, pagamento, comprovante, segurança
pages/admin/        dashboard, CRUDs, relatórios, configurações e migração MEI
pages/auth/         login, registro, perfil e pedidos
pages/cart/         carrinho e checkout
pages/products/     vitrine, categorias, detalhes e contato
pages/wishlist/     lista de desejos
api/webhooks/       webhooks de gateways
```

Instalação Docker:

```bash
cp .env.example .env.prod
docker compose up -d
```

Serviços Docker documentados:

| Serviço | URL |
|---------|-----|
| Loja | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |
| Mailpit | http://localhost:8025 |

Instalação XAMPP:

```bash
mysql -u root -e "CREATE DATABASE e5_royaltech"
mysql -u root e5_royaltech < database/database.sql
cp .env.example .env
```

Acesso local documentado: `http://localhost/TCC_Etec`.

Credencial administrativa documentada no README original:

- Admin: `admin` / `admin123`

Credencial administrativa documentada no guia MEI:

- Login: `admin@royaltech.com`
- Senha: `password123`

Observação: existe divergência entre os documentos antigos sobre credenciais padrão. Deve ser validado no banco atual antes de uso em produção.

## Migração CPF → MEI

O sistema foi preparado para migração do regime CPF para MEI sem necessidade de alteração de código, usando o painel administrativo.

Fluxo administrativo documentado:

1. Acessar `pages/admin/login.php`.
2. Entrar no painel administrativo.
3. Navegar até a Central de Migração MEI em `pages/admin/mei-migration.php`.
4. Preencher dados obrigatórios e opcionais.
5. Ativar regime MEI.

Dados obrigatórios:

- CNPJ.
- Razão Social.

Dados opcionais:

- Nome Fantasia.
- Inscrição Estadual.

Configuração opcional/recomendada:

- Provedor de NF-e: Focus NFe ou NFe.io.
- Chave API do provedor fiscal.
- Token Melhor Envio.

Mudanças esperadas com MEI:

| Item | CPF | MEI |
|------|-----|-----|
| Nota Fiscal | Não emitida | Emitida automaticamente se provedor estiver configurado |
| Gateway | Taxas estimadas de CPF | Taxas estimadas/negociadas para CNPJ |
| Frete | Tabela pública/fallback | Tabela comercial quando houver token real |
| Documento | CPF | CNPJ |
| Limite | Sem formalização | R$ 81.000/ano |

Pré-requisitos externos:

- MEI aberto pelo Portal do Empreendedor.
- CNPJ válido.
- Razão Social e Nome Fantasia.
- Inscrição Estadual, se aplicável.
- Conta em provedor de NF-e, se emissão fiscal for usada.
- Conta PJ no Melhor Envio, se frete comercial for usado.
- Token de API do Melhor Envio.

Checklist do sistema originalmente documentado:

- Tabela `e5_seller_profile` criada.
- Campos de invoice na tabela `e5_orders`.
- Função `emitirNotaFiscal()` implementada.
- Cálculo de frete com flag `tax_regime`.
- Taxas de pagamento dinâmicas.
- Painel de migração MEI.

Rollback para CPF via painel:

1. Acessar Central de Migração MEI.
2. Ir até a seção final.
3. Clicar em “Voltar para CPF”.
4. Confirmar.

Rollback manual documentado:

```sql
UPDATE e5_seller_profile 
SET tax_regime = 'CPF', 
    nfe_enabled = 0,
    document_type = 'CPF',
    document_number = '000.000.000-00',
    legal_name = NULL,
    trade_name = NULL
WHERE is_active = 1;

UPDATE e5_settings SET setting_value = 'CPF' WHERE setting_key = 'tax_regime';
UPDATE e5_settings SET setting_value = 'disabled' WHERE setting_key = 'nfe_provider';
UPDATE e5_settings SET setting_value = 'public' WHERE setting_key = 'melhor_envio_table';
```

Atenção: pedidos com NF-e já emitida não podem ter nota cancelada automaticamente. Cancelamento fiscal deve ser feito manualmente no provedor dentro do prazo aplicável (24h).

### Checklist Visual de Ativação (Admin)

Use este checklist no painel `mei-migration.php`:

| Etapa | Item | Status |
|-------|------|--------|
| 1 | MEI aberto no Portal do Empreendedor | ☐ |
| 2 | CNPJ válido em mãos | ☐ |
| 3 | Razão Social e Nome Fantasia | ☐ |
| 4 | Inscrição Estadual (ou ISENTO) | ☐ |
| 5 | Conta provedor NF-e (Focus NFe / NFe.io) | ☐ |
| 6 | Conta PJ Melhor Envio | ☐ |
| 7 | Token API NF-e inserido no painel | ☐ |
| 8 | Token Melhor Envio inserido no painel | ☐ |
| 9 | Gateway pagamento: CPF → CNPJ atualizado | ☐ |
| 10 | Testes: pedido teste + NF-e + frete + taxa | ☐ |

### Troubleshooting Detalhado

| Problema | Causa | Solução |
|----------|-------|---------|
| "CNPJ inválido" ao ativar | Formato incorreto | Use `00.000.000/0000-00` (pontos, barra, hífen) |
| NF-e não emitida | Provedor disabled / token inválido / CNPJ não cadastrado | Configurações > NF-e > testar conexão; cadastrar CNPJ no provedor |
| Taxas não reduzidas | Gateway não reconhece CNPJ | Painel Mercado Pago/Asaas > atualizar documento; aguardar 24-48h |
| Frete comercial inativo | Token Melhor Envio ausente | Criar conta PJ > Configurações > API > Gerar Token > inserir no painel |
| Focus NFe sandbox: token válido mas erro | Sandbox não suporta `/v2/empresas` | Health check usa `POST /v2/nfe` no sandbox (retorna 422 = token válido); produção usa `GET /v2/empresas` |

### Rollback (Voltar para CPF)

**Via Painel Admin:** Central de Migração MEI → final da página → "Voltar para CPF" → Confirmar.

**Via Banco (Manual):**
```sql
UPDATE e5_seller_profile 
SET tax_regime = 'CPF', 
    nfe_enabled = 0,
    document_type = 'CPF',
    document_number = '000.000.000-00',
    legal_name = NULL,
    trade_name = NULL
WHERE is_active = 1;

UPDATE e5_settings SET setting_value = 'CPF' WHERE setting_key = 'tax_regime';
UPDATE e5_settings SET setting_value = 'disabled' WHERE setting_key = 'nfe_provider';
UPDATE e5_settings SET setting_value = 'public' WHERE setting_key = 'melhor_envio_table';
```

> ⚠️ Pedidos com NF-e já emitida **não podem** ter nota cancelada automaticamente. Cancelamento fiscal manual no provedor dentro de 24h.

### Documentos para Abertura do MEI

1. CPF
2. Título de Eleitor ou Declaração de IR
3. Comprovante de residência
4. Recibo da última declaração de IR (se aplicável)

### CNAEs Recomendados (E-commerce Tecnologia)

- **4751-2/01** — Comércio varejista especializado de equipamentos e suprimentos de informática
- **4789-0/99** — Comércio varejista de outros produtos não especificados anteriormente

### Obrigações do MEI

- Pagamento mensal do DAS (Documento de Arrecadação do Simples Nacional)
- Declaração Anual de Faturamento (DASN-SIMEI)
- Limite de faturamento: R$ 81.000/ano (R$ 6.750/mês)

### Links de Suporte

- **Focus NFe:** https://focusnfe.com.br/doc/
- **NFe.io:** https://nfe.io/docs/
- **Melhor Envio API:** https://docs.melhorenvio.com.br/
- **Mercado Pago Developers:** https://www.mercadopago.com.br/developers/
- **Asaas Docs:** https://docs.asaas.com/
- Limite de faturamento: R$ 81.000/ano.

## Multi-Gateway de Pagamento

O sistema possui infraestrutura para múltiplos gateways em `includes/gateways.php` e migration `003_multi_gateway_and_security_fixes.sql`.

Gateways previstos:

- Mercado Pago.
- Asaas.

Recursos previstos:

- Tabela `e5_payment_gateways`.
- Gateway ativo único para novas vendas.
- Credenciais criptografadas em `e5_encrypted_settings`.
- Health check de gateway.
- Lock do gateway no início do checkout para evitar troca durante a compra.
- Snapshot do gateway usado no pedido (`gateway_used`).
- Log de webhook em `e5_webhook_log`.
- Processamento de webhooks mesmo se gateway estiver desativado para novas vendas.

Taxas:

- A documentação anterior afirma que valores fixos foram substituídos por tabela `e5_gateway_fees`.
- Os valores de CPF/CNPJ são estimativas e devem ser verificados com o gateway real.
- As taxas dependem de tipo de conta, volume, negociação, método de pagamento e política comercial do gateway.
- Não há garantia de diferenciação automática CPF/CNPJ pelo gateway.

Evidências registradas em `test-evidence-20260827.txt`:

- Ativação de gateway com credenciais inválidas retornou falha esperada.
- Ativação com credenciais sandbox válidas retornou sucesso para Mercado Pago.
- Lock de sessão de checkout registrou gateway travado.
- Snapshot de gateway persistido no pedido.
- Webhook para gateway desativado respondeu `{\"status\":\"ok\"}` em teste anterior.

Status atual: PENDENTE validar novamente no ambiente atual antes de considerar pronto para produção.

## Frete

Existe integração centralizada de frete em `includes/shipping.php`.

Funções relevantes:

- `shippingGetConfig()` — lê token do cofre criptografado (`e5_encrypted_settings`), CEP de origem configurável, regime tributário.
- `shippingValidateCep()` — valida CEP 8 dígitos (NNNNNNNN ou NNNNN-NNN).
- `shippingLookupCep()` — consulta ViaCEP para validar CEP e obter bairro/cidade/UF.
- `shippingCalculate()` — retorna envelope estruturado: `provider` (`melhor_envio`|`estimated`), `is_real`, `warning`, `address`, `options[]`.
- `shippingCalculateMelhorEnvio()` — chamada real à API Melhor Envio `/api/v2/me/shipment/calculate`.
- `shippingEstimatedOptions()` — fallback transparente por UF (tabela regional honesta).
- `shippingPreparePackage()` — envelope padrão 0,5kg/item (produtos sem dimensões no banco).
- `shippingTestDiagnostic()` — diagnóstico multi-CEP com resposta bruta da API.
- `shippingGetMigrationChecklist()` — checklist MEI → frete comercial.
- `shippingGetCostComparison()` — comparação CPF vs MEI.

Comportamento:

- Token configurado + MEI ativo → tabela comercial (descontos PJ).
- Token configurado + CPF → tabela pública.
- Sem token / API falha → fallback "Frete estimado" com warning visível na UI.
- Subtotal ≥ `free_shipping_threshold` → frete grátis (mantém transportadora/prazo real).

### Implementação Real (esta rodada)

**Correções aplicadas:**

1. **`includes/shipping.php` reescrito** — token lido do cofre criptografado (`loadEncryptedSetting('melhor_envio_token')`), validação CEP rigorosa, ViaCEP para endereço completo, envelope de retorno com `is_real`/`warning`.

2. **`pages/cart/checkout.php`** — removido `calcShipping()` hardcoded; integrado `shippingCalculate()`; validação server-side do CEP; exibe transportadora + prazo; badge "ESTIMADO" no fallback; persiste `shipping_carrier`, `shipping_delivery_time`, `shipping_is_estimated`, endereço ViaCEP.

3. **`pages/admin/settings.php` (aba Frete)** — campos `store_postal_code` (CEP origem) e `melhor_envio_table` (pública/comercial).

4. **Tabela `e5_orders`** — adicionadas colunas `shipping_carrier`, `shipping_delivery_time`, `shipping_is_estimated`.

**Testes com 3 CEPs reais (fallback estimado — token ausente):**

| CEP | Endereço (ViaCEP) | PAC | Sedex | Provider |
|-----|-------------------|-----|-------|----------|
| 01310-100 | Av. Paulista, Bela Vista, São Paulo/SP | R$ 14,90 (1-2d) | R$ 29,90 (1-2d) | estimated |
| 20040-020 | Praça Pio X, Centro, Rio de Janeiro/RJ | R$ 24,90 (3-7d) | R$ 39,90 (3-7d) | estimated |
| 40070-100 | R. General Labatut, Barris, Salvador/BA | R$ 35,90 (6-12d) | R$ 54,90 (6-12d) | estimated |

**Evidência de bloqueio — API Melhor Envio sem token:**

```
GET https://melhorenvio.com.br/api/v2/me → HTTP 401 {"message":"Unauthenticated."}
POST https://melhorenvio.com.br/api/v2/me/shipment/calculate → HTTP 401 {"message":"Unauthenticated."}
```

> **BLOQUEADOR**: Token Melhor Envio não configurado. O banco só tem `melhor_envio_client_id/secret/redirect_uri` (OAuth app). Falta `melhor_envio_token` (access token). Configure no admin → MEI Migration → "Token Melhor Envio" após criar conta no Melhor Envio.

**Limitações conhecidas:**

- Produtos sem peso/dimensões no banco → envelope padrão 0,5kg/item.
- Fallback por UF não substitui cálculo real — configure token para frete real.
- CEP 40020-000 não existe no ViaCEP; use 40070-100 para Salvador.

**Status:** Checkout conectado à implementação oficial ✓ | Token Melhor Envio: **PENDENTE configuração** | Fallback transparente com warning ✓

## Checkout

O checkout está em `pages/cart/checkout.php`.

Fluxo atual:

1. Inicia sessão, exige usuário logado.
2. Carrega itens do carrinho.
3. Bloqueia gateway no início do checkout (`gatewayLockForCheckout`).
4. Calcula subtotal (inteiros em centavos para evitar float drift).
5. **Frete real**: `shippingCalculate($cep, $subtotal, $items)` → envelope com `provider`/`is_real`/`warning`/`options[]`.
6. UI exibe transportadora + prazo + custo; badge "ESTIMADO" no fallback; auto-submit ao trocar opção.
7. **Pagamento**: usa `paymentGetMethods()` do `includes/payment.php`; taxa gateway exibida no cartão (informativo); desconto Pix 5% aplicado.
7. Cria pedido em `e5_orders` com: `shipping_method`, `shipping_carrier`, `shipping_cost`, `shipping_delivery_time`, `shipping_is_estimated`, `shipping_postal_code`, `shipping_neighborhood/city/state` (ViaCEP).
8. Cria itens em `e5_order_items`, decrementa estoque, limpa carrinho.
9. **PIX real**: `pixGenerateForOrder()` → BR Code EMV + QR Code PNG + copia-e-cola; `payment_status = pending`.
10. Boleto/Cartão/Entrega: placeholders (integração gateway TODO).
11. Gera comprovante (`gerarComprovanteCompra`) + envia e-mail (`enviarComprovanteEmail`).

Campos de frete no pedido:

- `shipping_method`, `shipping_carrier`, `shipping_cost`, `shipping_delivery_time`, `shipping_is_estimated`.
- `shipping_postal_code`, `shipping_neighborhood`, `shipping_city`, `shipping_state`.

Status: Frete real integrado ✓ | Pix real (BR Code) integrado ✓ | Taxas transparentes ✓ | Pedido abaixo R$ 500 gravando frete ✓

## Comprovante de Compra

O comprovante de compra está implementado em `includes/comprovante.php`.

Características:

- Documento **não fiscal** — não substitui NF-e.
- Gera HTML + PDF real via DomPDF (requer `vendor/autoload.php`).
- Salva em `storage/comprovantes/COMP-XXXXXX_pedidoNN.pdf`.
- Envia por e-mail via PHPMailer (`includes/mail.php`) com PDF anexado.
- Atualiza `e5_orders`: `invoice_number`, `invoice_status = 'issued'`.

Funções relevantes:

- `obterProximoNumeroComprovante()` — contador persistido em `e5_settings`.
- `montarHtmlComprovante()` — HTML formatado.
- `gerarPdfComprovante()` — DomPDF com papel A4, margens, header/footer.
- `gerarComprovanteCompra($pdo, $orderId, $salvar)` — retorna `['html','numero','pdf']`.
- `enviarComprovanteEmail($pdo, $orderId)` — retorna bool.

**Validação real desta rodada:**

- PDF gerado: `COMP-000024_pedido21.pdf` (6185 bytes, header `%PDF-` confirmado).
- E-mail enviado via Mailpit (SMTP localhost:1025) — **Attachment confirmado**: `COMP-000024.pdf` (6185 bytes, `application/pdf`, SHA256 `7357b5...`).
- Mailpit UI: http://localhost:8025 — mensagem ID `768CXdpBM7y2xHVRu9wXEu`.

Status: PDF geração ✓ | E-mail com anexo ✓ | Fluxo checkout→comprovante→e-mail ✓

## Segurança

Recursos documentados:

- CSRF token em formulários POST (`includes/csrf.php`).
- Rate limiting no login (`includes/rate_limit.php`).
- Prepared statements em todas as queries.
- Sanitização de saída com `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Senhas com `password_hash()` / `password_verify()` (bcrypt).
- Credenciais em `.env`/`.env.prod`, excluído do Git.
- Autenticação admin com `requireAdmin()`.
- Validação de CNPJ com dígito verificador (`validateCNPJ`).
- Armazenamento criptografado de credenciais em `e5_encrypted_settings` (AES-256-GCM via OpenSSL).
- Log de mudança de regime/gateway em `e5_system_change_log`.

Pontos sensíveis:

- `includes/security.php`: chave derivada de string hardcoded — **revise antes de produção** (use variável de ambiente para master key).
- Webhooks: verificação de assinatura implementada, mas **validar contra specs reais** dos gateways.
- Checkout recalcula subtotal/frete/total no servidor (centavos) — não confia no cliente.
- Token Melhor Envio: lido do cofre criptografado, nunca hardcoded.
- PIX: BR Code gerado server-side; `payment_status = pending` até confirmação manual.

Status: CSRF ✓ | Rate limit ✓ | Prepared statements ✓ | Crypto vault ✓ | Chave hardcoded: **REVISAR** | Webhooks: **VALIDAR SPECS** | Checkout server-side ✓

## Banco de Dados

Schema base em `database/database.sql`.

Tabelas principais do schema base:

- `e5_users`.
- `e5_categories`.
- `e5_products`.
- `e5_product_images`.
- `e5_orders`.
- `e5_order_items`.
- `e5_cart`.
- `e5_contacts`.
- `e5_newsletter`.
- `e5_password_reset_tokens`.
- `e5_banners`.
- `e5_wishlist`.
- `e5_settings`.

Migrations existentes:

- `database/migrations/001_add_seller_profile_and_tax_regime.sql`.
- `database/migrations/002_security_audit_and_regime_snapshot.sql`.
- `database/migrations/003_multi_gateway_and_security_fixes.sql`.

Migration 001:

- Cria `e5_seller_profile`.
- Prepara configurações de regime tributário.
- Adiciona campos de NF-e em `e5_orders`.
- Cria perfil inicial CPF.
- Insere settings iniciais: `tax_regime`, `nfe_provider`, `nfe_environment`, `payment_gateway`, `payment_fee_cpf`, `payment_fee_mei`, `melhor_envio_table`.

Migration 002:

- Adiciona `tax_regime_snapshot` e `regime_captured_at` em `e5_orders`.
- Cria log de mudança de regime `e5_regime_change_log`.
- Cria `e5_encrypted_settings`.
- Cria view `v_pending_nfe_orders`.
- Cria `e5_cpf_revenue_tracking`.
- Cria `e5_gateway_fees`.
- Renomeia settings antigas de taxa para sufixo `_ESTIMATE`.
- Adiciona disclaimer de taxas.

Migration 003:

- Cria `e5_payment_gateways`.
- Adiciona snapshot de gateway em `e5_orders`.
- Ajusta log sistêmico para mudanças de gateway/credenciais/taxa.
- Cria `e5_webhook_log`.
- Adiciona status de verificação em `e5_gateway_fees`.
- Insere gateways Mercado Pago e Asaas.

Atenção: a migration 003 altera `e5_system_change_log`, mas a migration 002 cria `e5_regime_change_log`. Deve ser verificado no banco atual se `e5_system_change_log` existe antes de afirmar que a migration 003 é aplicável sem erro.

Status: PENDENTE verificar migrations aplicadas no banco atual e, se possível, gerar `schema_atual.sql` via `mysqldump --no-data`.

## Configurações

Arquivo `.env.example` possui:

```text
DB_HOST=localhost
DB_NAME=e5_royaltech
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM=
```

Configurações padrão em `includes/config.php`:

- `store_name`.
- `store_email`.
- `store_phone`.
- `store_address`.
- `store_cnpj`.
- `store_currency`.
- `store_description`.
- Redes sociais.
- `store_logo`.
- `store_favicon`.
- `pix_key`.
- `boleto_days`.
- `free_shipping_threshold`.

Observação importante: `store_config()` atualmente só aplica overrides para chaves existentes em `store_defaults()`. Chaves de migrations como `tax_regime`, `nfe_provider`, `melhor_envio_token`, `melhor_envio_table` e `payment_gateway` podem não ser retornadas por `store_config()` se não forem adicionadas aos defaults ou tratadas separadamente.

Status: PENDENTE verificar impacto no frete, pagamento e migração MEI.

## Problemas Conhecidos (atualizados)

- **Token Melhor Envio não configurado** — frete real bloqueado; fallback estimado ativo com warning.
- **PIX estático (manual)** — BR Code + QR Code funcionais, mas sem webhook de gateway; admin deve confirmar pagamento manualmente via "Marcar como Pago" em `order-detail.php`.
- **Boleto/Cartão** — placeholders; integração real com Mercado Pago/Asaas pendente.
- **Produtos sem peso/dimensões** — envelope padrão 0,5kg/item no frete.
- **CEP 40020-000** não existe no ViaCEP; usar 40070-100 para Salvador.
- **Chave de criptografia hardcoded** em `includes/security.php` — revise antes de produção.
- **`test_vendor.php`** script de teste acessível — remova em produção.
- Inconsistência `e5_regime_change_log` vs `e5_system_change_log` — verificar.
- `store_config()` limitado a chaves em `store_defaults()` — chaves de migration precisam ser adicionadas aos defaults.

## Pendências (atualizadas)

Frete:
- ✅ Checkout conectado a `includes/shipping.php`.
- ✅ Validação de CEP rigorosa (8 dígitos + ViaCEP).
- ✅ Fallback exibido como "Frete estimado — configure o token...".
- ✅ Testes CEPs `01310-100`, `20040-020`, `40070-100` executados (fallback).
- ⏳ **Configurar token Melhor Envio** no admin → MEI Migration → validar frete real multi-transportadora.

Pix:
- ✅ BR Code EMV válido (CRC16-CCITT) com chave `royaltech.original@gmail.com`.
- ✅ QR Code PNG gerado via `chillerlan/php-qrcode` (composer).
- ✅ Copia-e-cola exibido no checkout + admin.
- ✅ `payment_status = pending` + botão "Marcar como Pago" no admin.
- ⏳ **Validar BR Code com leitor real** (app banco: Nubank, Itaú, etc.).
- ⏳ Integrar webhook PIX real via Mercado Pago/Asaas para automação.

Segurança:
- ✅ CSRF em todos formulários POST.
- ✅ Rate limiting no login.
- ✅ Prepared statements universal.
- ✅ Sanitização `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- ⏳ **Rotacionar chave de criptografia** para variável de ambiente.
- ⏳ Validar webhooks contra specs Mercado Pago/Asaas.
- ⏳ Revisar `test_vendor.php` e logs sensíveis.

Banco:
- ✅ `database/database.sql` atualizado via `mysqldump`.
- ⏳ Gerar `schema_atual.sql` (`mysqldump --no-data`) se necessário.

Documentação:
- ✅ `DOCUMENTACAO.md` consolidado com evidências desta rodada.
- ⏳ Remover `.md` antigos após confirmação.

## Auditoria MEI — Checklist Final (de `README_MEI_AUDIT.md`)

### ✅ Itens Completados (10/10)

| # | Item | Evidência / Arquivo |
|---|------|---------------------|
| 1 | Taxas reais de gateway — valores fixos removidos, tabela `e5_gateway_fees` com estimativas documentadas (Mercado Pago/Asaas URLs oficiais) | `database/migrations/002_security_audit_and_regime_snapshot.sql` |
| 2 | Segurança formulário migração — CSRF, admin auth, criptografia Sodium, sanitização | `includes/security.php` |
| 3 | Validação CNPJ com dígito verificador — `validateCNPJ()` | `includes/security.php` |
| 4 | Tratamento pedidos pré-existentes — view `v_pending_nfe_orders` + seção no painel | `mei-migration.php` |
| 5 | Ativação transacional — `activateMEITransactional()` / `deactivateMEITransactional()` com health checks, row locking, error logging | `includes/transactional_activation.php` |
| 6 | Snapshot regime por pedido — coluna `tax_regime_snapshot` em `e5_orders` + backfill | Migration 002 |
| 7 | Log auditoria mudança regime — tabela `e5_regime_change_log` (user, regime_anterior/novo, IP) | Migration 002 |
| 8 | Documentação atualizada — guia passo a passo, checklist, campos, segurança | `GUIA_MIGRACAO_MEI.md` (mesclado aqui) |
| 9 | Testes validação — CNPJ checksum, CSRF, encrypt/decrypt, health checks NF-e/ME | Inline |
| 10 | Interface corrigida — badge regime, checklist validação, contagem pendentes, status integrações | `mei-migration.php` |

### ⚠️ Itens Pendentes (3)

1. 🔄 **Teste de timeout NF-e** — testar retry com backoff quando Focus NFe retorna timeout.
2. 🔄 **Teste token inválido Melhor Envio** — rejeição na ativação + fallback silencioso pós-ativação.
3. 🔄 **Race condition** — dois admins ativando MEI simultaneamente (transação atômica).

### Garantias de Produção

- **Precisão técnica:** taxas estimadas documentadas com links oficiais.
- **Segurança:** admin auth + role check, CSRF, credenciais criptografadas (Sodium), CNPJ/CPF validação oficial, log auditoria completo.
- **Robustez:** validação transacional evita estado inconsistente, health checks garantem APIs funcionais, falhas com mensagens específicas.
- **Rastreabilidade:** log completo mudanças regime, histórico NF-e pendentes, rastreamento receita IR para CPF.
- **Compatibilidade:** backward compatible CPF, sem perda de dados, rollback completo disponível.

## Testes e Evidências (esta rodada)

### Frete Real — Diagnóstico + 3 CEPs

```json
{
  "config": {
    "provider": "simple",
    "has_token": false,
    "price_table": "public",
    "origin_postal_code": "01310-100",
    "tax_regime": "CPF"
  },
  "tests": [
    {"cep": "01310-100", "address": {"uf":"SP","cidade":"São Paulo"}, "options": {"pac":{"cost":14.90,"days":"1-2 dias úteis"},"sedex":{"cost":29.90,"days":"1-2 dias úteis"}}, "provider":"estimated"},
    {"cep": "20040-020", "address": {"uf":"RJ","cidade":"Rio de Janeiro"}, "options": {"pac":{"cost":24.90,"days":"3-7 dias úteis"},"sedex":{"cost":39.90,"days":"3-7 dias úteis"}}, "provider":"estimated"},
    {"cep": "40070-100", "address": {"uf":"BA","cidade":"Salvador"}, "options": {"pac":{"cost":35.90,"days":"6-12 dias úteis"},"sedex":{"cost":54.90,"days":"6-12 dias úteis"}}, "provider":"estimated"}
  ]
}
```

**Evidência bloqueio API Melhor Envio:**
```
GET /api/v2/me → HTTP 401 {"message":"Unauthenticated."}
POST /api/v2/me/shipment/calculate → HTTP 401 {"message":"Unauthenticated."}
```

### Pix Real — BR Code + QR Code

**BR Code gerado (pedido #1, R$ 123,45):**
```
0020126660014BR.GOV.BCB.PIX0128royaltech.original@gmail.com0212Pedido #000152400005339865406123.45582BR5910ROYAL TECH6009SAO PAULO62240520PED00000001_8FAD630E6304AC5A
```

**Validação estrutural EMV:**
- 00 Payload Format: `01` ✓
- 26 MAI: GUI `BR.GOV.BCB.PIX` + Chave `royaltech.original@gmail.com` + Desc `Pedido #0001` ✓
- 52 MCC: `0000` ✓
- 53 Currency: `986` (BRL) ✓
- 54 Amount: `123.45` ✓
- 58 Country: `BR` ✓
- 59 Merchant Name: `ROYAL TECH` (len 10 ≤ 25) ✓
- 60 Merchant City: `SAO PAULO` (len 9 ≤ 15) ✓
- 62 Additional Data: TXID `PED00000001_8FAD630E` ✓
- 63 CRC16-CCITT: `AC5A` **VÁLIDO ✓**

**QR Code:** Data URI PNG gerado (5272 chars base64) — `chillerlan/php-qrcode` v5.0.5.

### Comprovante + E-mail

- PDF: `COMP-000024_pedido21.pdf` — 6185 bytes — header `%PDF-` confirmado.
- Mailpit: http://localhost:8025 — mensagem ID `768CXdpBM7y2xHVRu9wXEu`
- Attachment: `COMP-000024.pdf` — 6185 bytes — `application/pdf` — SHA256 `7357b531534630bbde4eedde03df109af22dc87743056f7aacee81bad53c0a09`

## Template de Pull Request

O projeto possui template de Pull Request em `.github/PULL_REQUEST_TEMPLATE.md` com os campos:

- Descrição.
- Tipo de mudança: Feature, Fix, Hotfix, Audit, Release, Chore.
- Checklist: testes passando e revisão do líder.

## Glossário

- MEI: Microempreendedor Individual.
- NF-e: Nota Fiscal Eletrônica.
- CNPJ: Cadastro Nacional da Pessoa Jurídica.
- CPF: Cadastro de Pessoa Física.
- Razão Social: nome empresarial registrado.
- Nome Fantasia: nome comercial da loja.
- Inscrição Estadual: registro estadual para ICMS.
- Token de API: chave de acesso para integração com serviços externos.
- Fallback de frete: cálculo local usado quando API externa não está disponível.

## Estado Real Atual (pós-implementação desta rodada)

- Documentação consolidada: ✅ VALIDADO neste arquivo.
- Arquivos `.md` antigos: MANTIDOS, aguardando confirmação antes de remoção.
- Migrations: MANTIDAS, ainda não reorganizadas.
- Schema atual: ✅ `database/database.sql` atualizado via `mysqldump`.
- Frete real via Melhor Envio: ⚠️ **BLOQUEADO — token não configurado** (fallback estimado ativo + warning).
- Checkout usando frete oficial: ✅ CONECTADO (`shippingCalculate`).
- Validação de CEP: ✅ RIGOROSA (8 dígitos + ViaCEP).
- Pix real: ✅ **BR CODE EMV VÁLIDO + QR CODE PNG** (estático/manual; `payment_status=pending`).
- Comprovante + E-mail: ✅ PDF GERADO + ANEXO ENVIADO (Mailpit confirmado).
- Segurança: ⚠️ Chave criptografia hardcoded (revise), webhooks (validar specs).
- Próximo passo crítico: **Criar conta Melhor Envio + gerar token + configurar no admin**.
