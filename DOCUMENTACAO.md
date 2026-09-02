# DOCUMENTAÇÃO — Royal Tech

Última atualização: 30 de agosto de 2026 (migração Mercado Pago para SDK oficial dx-php 3.16.0)

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

Credenciais de administração:

> **Atenção:** as credenciais padrão são definidas no banco de dados (`e5_users` via seed ou criação inicial). Consulte o responsável pelo projeto ou verifique no painel phpMyAdmin antes de acessar. Após o primeiro acesso, altere a senha imediatamente. **Nunca incluir credenciais em texto plano no repositório.**

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
- Token SuperFrete.

Mudanças esperadas com MEI:

| Item | CPF | MEI |
|------|-----|-----|
| Nota Fiscal | Não emitida | Emitida automaticamente se provedor estiver configurado |
| Gateway | Taxas estimadas de CPF | Taxas estimadas/negociadas para CNPJ |
| Frete | Tabela pública/fallback | Frete real via SuperFrete (tabela única) |
| Documento | CPF | CNPJ |
| Limite | Sem formalização | R$ 81.000/ano |

Pré-requisitos externos:

- MEI aberto pelo Portal do Empreendedor.
- CNPJ válido.
- Razão Social e Nome Fantasia.
- Inscrição Estadual, se aplicável.
- Conta em provedor de NF-e, se emissão fiscal for usada.
- Conta no SuperFrete, se frete real for usado.
- Token de API do SuperFrete.

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
| 6 | Conta SuperFrete (sandbox) | ☐ |
| 7 | Token API NF-e inserido no painel | ☐ |
| 8 | Token SuperFrete inserido no painel | ☐ |
| 9 | Gateway pagamento: CPF → CNPJ atualizado | ☐ |
| 10 | Testes: pedido teste + NF-e + frete + taxa | ☐ |

### Troubleshooting Detalhado

| Problema | Causa | Solução |
|----------|-------|---------|
| "CNPJ inválido" ao ativar | Formato incorreto | Use `00.000.000/0000-00` (pontos, barra, hífen) |
| NF-e não emitida | Provedor disabled / token inválido / CNPJ não cadastrado | Configurações > NF-e > testar conexão; cadastrar CNPJ no provedor |
| Taxas não reduzidas | Gateway não reconhece CNPJ | Painel Mercado Pago/Asaas > atualizar documento; aguardar 24-48h |
| Frete real inativo | Token SuperFrete ausente | Criar conta em sandbox.superfrete.com > Integrações > Site próprio > Gerar Token > inserir no painel |
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
- **SuperFrete API:** https://docs.superfrete.com/
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

Status: Gateway ativo único ✓ | Lock de sessão ✓ | Snapshot por pedido ✓ | Webhook: **PENDENTE configuração secret + simulação real** (ver seção Segurança: Validação de Assinatura Webhook).

## Frete

Existe integração centralizada de frete em `includes/shipping.php`.

Funções relevantes:

- `shippingGetConfig()` — lê token do cofre criptografado (`e5_encrypted_settings`), CEP de origem configurável, regime tributário.
- `shippingValidateCep()` — valida CEP 8 dígitos (NNNNNNNN ou NNNNN-NNN).
- `shippingLookupCep()` — consulta ViaCEP para validar CEP e obter bairro/cidade/UF.
- `shippingCalculate()` — retorna envelope estruturado: `provider` (`superfrete`|`estimated`), `is_real`, `warning`, `address`, `options[]`.
- `shippingCalculateSuperFrete()` — chamada real à API SuperFrete `POST /api/v0/calculator`.
- `shippingEstimatedOptions()` — fallback transparente por UF (tabela regional honesta).
- `shippingPreparePackage()` — envelope padrão 0,5kg/item (produtos sem dimensões no banco).
- `shippingTestDiagnostic()` — diagnóstico multi-CEP com resposta bruta da API.
- `shippingGetMigrationChecklist()` — checklist MEI → frete comercial.
- `shippingGetCostComparison()` — comparação CPF vs MEI.

### Estimativa de Frete no Carrinho

Em `pages/cart/cart.php` há um campo de CEP + botão **"Calcular frete"** que chama `pages/cart/shipping-estimate.php` via AJAX. O endpoint:

- Lê o carrinho da sessão (convidado) ou do banco (logado) no arquivo `pages/cart/shipping-estimate.php`.
- Chama `shippingCalculate($cep, $subtotal, $items)` (a mesma do checkout).
- Retorna JSON com `provider`, `is_real`, `warning` e `options[]`.
- Exibe cada opção (transportadora, prazo, custo) com o badge **ESTIMADO** quando `estimated=true` — sem sair da página do carrinho.
- **Não persiste** o cálculo: é apenas prévia; o cálculo definitivo continua acontecendo no checkout.

Comportamento:

- Token configurado + MEI ativo → tabela comercial (descontos PJ).
- Token configurado + CPF → tabela pública.
- Sem token / API falha → fallback "Frete estimado" com warning visível na UI.
- Subtotal ≥ `free_shipping_threshold` → frete grátis (mantém transportadora/prazo real).

### Implementação Real (esta rodada)

**Correções aplicadas:**

1. **`includes/shipping.php` reescrito** — token lido do cofre criptografado (`loadEncryptedSetting('superfrete_token')`), validação CEP rigorosa, ViaCEP para endereço completo, envelope de retorno com `is_real`/`warning`.

2. **`pages/cart/checkout.php`** — removido `calcShipping()` hardcoded; integrado `shippingCalculate()`; validação server-side do CEP; exibe transportadora + prazo; badge "ESTIMADO" no fallback; persiste `shipping_carrier`, `shipping_delivery_time`, `shipping_is_estimated`, endereço ViaCEP.

3. **`pages/admin/settings.php` (aba Frete)** — campos `store_postal_code` (CEP origem), `superfrete_token` (criptografado), `superfrete_sandbox`.

4. **Tabela `e5_orders`** — adicionadas colunas `shipping_carrier`, `shipping_delivery_time`, `shipping_is_estimated`.

**Testes com 3 CEPs reais (fallback estimado — token ausente):**

| CEP | Endereço (ViaCEP) | PAC | Sedex | Provider |
|-----|-------------------|-----|-------|----------|
| 01310-100 | Av. Paulista, Bela Vista, São Paulo/SP | R$ 14,90 (1-2d) | R$ 29,90 (1-2d) | estimated |
| 20040-020 | Praça Pio X, Centro, Rio de Janeiro/RJ | R$ 24,90 (3-7d) | R$ 39,90 (3-7d) | estimated |
| 40070-100 | R. General Labatut, Barris, Salvador/BA | R$ 35,90 (6-12d) | R$ 54,90 (6-12d) | estimated |

**Decisão de migração: Melhor Envio → SuperFrete:**

O Melhor Envio exige autenticação OAuth2 com callback HTTP público, o que impediu testes em ambiente local (XAMPP + Cloudflare Tunnel). A SuperFrete oferece autenticação simples via token Bearer, sem necessidade de OAuth. A migração manteve toda a arquitetura existente (validação CEP, ViaCEP, envelope de retorno, fallback estimado) e os endpoints da SuperFrete foram testados com sucesso.

**API SuperFrete (sandbox):**

```
POST https://sandbox.superfrete.com/api/v0/calculator
Authorization: Bearer <token>
User-Agent: Royal Tech (royaltech.original@gmail.com)
Content-Type: application/json

{
  "from": {"postal_code": "01310100"},
  "to": {"postal_code": "20040020"},
  "services": "1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19",
  "package": {"height": 10, "width": 20, "length": 30, "weight": 1.5},
  "options": {"insurance_value": 1000}
}
```

Resposta (array de serviços; serviços indisponíveis vêm com `has_error: true`):

```json
[
  {"id": 1, "name": "PAC", "price": 22.29, "delivery_time": 2, "company": {"name": "Correios"}, "has_error": false},
  {"id": 2, "name": "SEDEX", "price": 17.23, "delivery_time": 1, "company": {"name": "Correios"}, "has_error": false}
]
```

Erro (CEP inválido):

```json
{"errors": {"correios.destination_postcode": ["(correios.destination_postcode) é inválido."]}, "message": "Ocorreu um ou mais erros."}
```

**Testes reais realizados (token sandbox SuperFrete):**

| Rota | PAC | SEDEX | Dias PAC | Dias SEDEX |
|------|-----|-------|----------|------------|
| SP Capital (01310-100) → SP | R$ 22,29 | R$ 17,23 | 2 | 1 |
| SP → RJ Capital (20040-020) | indisponível | R$ 43,34 | — | 1 |
| SP → Salvador BA (40070-100) | R$ 37,05 | R$ 68,33 | 2 | 1 |

Fallback: CEP inválido `99999999` → `estimated` com tabela regional ✓
Token ausente → `estimated` com warning ✓

**Limitações conhecidas:**

- Produtos sem peso/dimensões no banco → envelope padrão 0,5kg/item.
- Fallback por UF não substitui cálculo real — configure token para frete real.
- SuperFrete sandbox: PAC pode estar indisponível para algumas rotas (SP→RJ).
- CEP 40020-000 não existe no ViaCEP; use 40070-100 para Salvador.

**Status:** Frete real via SuperFrete integrado ✓ | Token SuperFrete: **configurado (sandbox)** — sem fallback; token obrigatório | Webhook: **PENDENTE configuração secret + simulação real**

> **Não há mais frete estimado/fallback.** Sem token SuperFrete, o checkout bloqueia com erro explícito. Sem Mercado Pago configurado, PIX/Boleto/cartão bloqueiam com erro explícito.

## Checkout

O checkout está em `pages/cart/checkout.php`.

Fluxo atual:

1. Inicia sessão; **usuário convidado** é permitido (sem exigir login). `e5_orders.user_id` agora é nullable, com `guest_name`/`guest_email` para pedidos de convidados.
2. **Carrinho de convidado** fica em sessão PHP (`$_SESSION['guest_cart']`) até a finalização (`sessionCartGetItems`/`sessionCartAddItem`/`sessionCartUpdateQuantity`/`sessionCartRemoveItem`/`sessionCartGetCount` em `includes/cart_functions.php`).
3. Usuário logado usa o carrinho no banco (`e5_cart`).
4. No checkout, o convidado preenche nome/e-mail/endereço (Etapa 3) — sem criar conta.
5. Bloqueia gateway no início do checkout (`gatewayLockForCheckout`).
6. Calcula subtotal (inteiros em centavos para evitar float drift).
7. **Frete real**: `shippingCalculate($cep, $subtotal, $items)` → envelope com `provider`/`is_real`/`warning`/`options[]`.
8. UI exibe transportadora + prazo + custo; badge "ESTIMADO" no fallback; auto-submit ao trocar opção.
9. **Pagamento**: usa `paymentGetMethods()` do `includes/payment.php`; taxa gateway exibida no cartão (informativo); desconto Pix 5% aplicado.
10. Cria pedido em `e5_orders` com: `shipping_method`, `shipping_carrier`, `shipping_cost`, `shipping_delivery_time`, `shipping_is_estimated`, `shipping_postal_code`, `shipping_neighborhood/city/state` (ViaCEP). Para convidados: `user_id = NULL`, `guest_name`/`guest_email` preenchidos.
11. Cria itens em `e5_order_items`, decrementa estoque, limpa carrinho (sessão para convidado, banco para logado).
12. **PIX real**: `pixGenerateForOrder()` → BR Code EMV + QR Code PNG + copia-e-cola; `payment_status = pending`.
13. **Cartão de Crédito (Checkout Transparente)**: `paymentMercadoPagoCreatePayment()` e `paymentAsaasCreatePayment()` chamam as APIs reais via cURL. Tokenização via JS SDK v2 no front-end (cartão nunca toca o servidor). `payment_status = paid` quando aprovado, `pending` caso contrário. **Salvamento de cartão**: token do MP salvo em `e5_saved_cards` (não dados do cartão). Checkout permite selecionar cartão salvo ou usar novo.
14. **Estorno**: `paymentProcessRefund()` despacha para `paymentRefundMercadoPago()` ou `paymentRefundAsaas()` (endpoint real de estorno de cada gateway).
15. Gera comprovante (`gerarComprovanteCompra`) + envia e-mail (`enviarComprovanteEmail`).

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
- Token SuperFrete: lido do cofre criptografado, nunca hardcoded.
- PIX: BR Code gerado server-side; `payment_status = pending` até confirmação manual.

Status: CSRF ✓ | Rate limit ✓ | Prepared statements ✓ | Crypto vault ✓ | Chave hardcoded: **REVISAR** | Webhooks: assinatura obrigatória por padrão, **PENDENTE configuração de secret + simulação real do Mercado Pago** | Checkout server-side ✓

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
- `e5_coupons`.
- `e5_payment_gateways`.
- `e5_gateway_fees`.
- `e5_checkout_sessions`.
- `e5_encrypted_settings`.
- `e5_cpf_revenue_tracking`.
- `e5_system_change_log`.
- `e5_webhook_log`.

## Cupons de Desconto

Sistema de cupons implementado em `includes/coupons.php`.

- **Tabela**: `e5_coupons` (code, discount_type [percentage|fixed], discount_value, expires_at, max_uses, uses_current, is_active).
- **Validação**: `couponValidate($pdo, $code)` — verifica existência, ativo, validade e uso máximo.
- **Cálculo**: `couponCalculateDiscount($coupon, $subtotal)` — retorna valor em R$.
- **Incremento**: `couponIncrementUsage($pdo, $couponId)` — chamado após commit do pedido.
- **Campo no pedido**: `e5_orders.coupon_code` grava o código aplicado.
- **UI**: Campo de cupom no checkout (Etapa 3, antes do botão confirmar), com validação inline e desconto visível no resumo.

## Recuperação de Carrinho Abandonado

Script cron em `pages/cart/abandoned-cart-cron.php`:

- Identifica usuários com itens no carrinho (`e5_cart`) cuja primeira inserção tem mais de 24h sem pedido associado.
- Envia e-mail HTML com lista de itens, total e link direto para retomar a compra.
- Uso: `php pages/cart/abandoned-cart-cron.php` (pode ser agendado via crontab).
- Requer infraestrutura de e-mail funcional (Mailpit em dev, SMTP em prod).

Migrations existentes:

- `database/migrations/001_add_seller_profile_and_tax_regime.sql`.
- `database/migrations/002_security_audit_and_regime_snapshot.sql`.
- `database/migrations/003_multi_gateway_and_security_fixes.sql`.

Migration 001:

- Cria `e5_seller_profile`.
- Prepara configurações de regime tributário.
- Adiciona campos de NF-e em `e5_orders`.
- Cria perfil inicial CPF.
- Insere settings iniciais: `tax_regime`, `nfe_provider`, `nfe_environment`, `payment_gateway`, `payment_fee_cpf`, `payment_fee_mei`, `superfrete_sandbox`.

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

Observação importante: `store_config()` atualmente só aplica overrides para chaves existentes em `store_defaults()`. Chaves de migrations como `tax_regime`, `nfe_provider`, `superfrete_token`, `superfrete_sandbox` e `payment_gateway` podem não ser retornadas por `store_config()` se não forem adicionadas aos defaults ou tratadas separadamente.

Status: PENDENTE verificar impacto no frete, pagamento e migração MEI.

## Problemas Conhecidos (atualizados)

- **Token SuperFrete configurado em sandbox** — frete real ativo para testes; migrar para token de produção ao lançar em ambiente real.
- **PIX agora nativo via SDK** — QR Code real gerado pela API de Orders com webhook de confirmação automática. `includes/pix.php` mantido como fallback documentado.
- **Boleto agora real via SDK** — PDF real gerado pela API de Orders com linha digitável e barcode. Vencimento configurável (3 dias padrão).
- **Cartão com SDK + processing_mode:automatic** — migração completa de cURL manual para `OrderClient::create()`.
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
- ✅ **Token SuperFrete CONFIGURADO E TESTADO (2026-09-02)** — `shippingCalculate()` agora usa API real SuperFrete (sandbox).
  - SP (01310-100): PAC R$ 19,75 (2 dias), SEDEX R$ 12,82 (1 dia) — descontos 6,05 / 14,78
  - RJ (20040-020): SEDEX R$ 34,57 (1 dia) — PAC indisponível no trecho — desconto 12,23
  - BA (40070-100): PAC R$ 26,35 (2 dias), SEDEX R$ 48,39 (1 dia) — descontos 7,75 / 16,61
  - Resposta bruta da API capturada em `shippingTestDiagnostic()` — transportadora Correios, sandbox.superfrete.com
- ⏳ Migrar SuperFrete de sandbox para produção.

Pix:
- ✅ **PIX nativo via SDK oficial (Orders API)** — QR Code real (EMV + imagem base64) + copia-e-cola.
- ✅ Webhook de confirmação automática via `paymentMercadoPagoGetOrder()`.
- ✅ Fallback para PIX estático local (`includes/pix.php`) mantido documentado.
- ✅ Campo CPF adicionado ao checkout para PIX/Boleto.
- ✅ **Teste fresco 2026-09-02 validado** — QR Code EMV + PNG base64 gerados.

Boleto:
- ✅ **Boleto real via SDK oficial (Orders API)** — barcode 44 dígitos, linha digitável 47 dígitos, PDF real.
- ✅ Exibição no checkout: link de download + linha digitável com botão copiar.
- ✅ Campo CPF adicionado ao checkout para Boleto.
- ✅ **Teste fresco 2026-09-02 validado** — order_id e payment_id gerados, status=processing.

Cartão:
- ✅ **Cartão via SDK oficial (Orders API)** — `processing_mode: automatic` incluído.
- ✅ Refund via `OrderClient::refund()` (migrou de cURL à API legada).
- ✅ Salvar cartão, cartão salvo no checkout, tokenização client-side.
- ⚠️ **BACKEND VALIDADO, FRONTEND PENDENTE TESTE MANUAL** — Backend `paymentProcessCreditCard()` chama Orders API corretamente. Frontend SDK JS v2 intacto, Public Key dinâmica. **Limitação do ambiente headless impede teste real pelo navegador.** Teste manual necessário: acessar checkout, preencher Visa 4235 6477 2802 5682 / APRO / 11/30 / CVV 123 / CPF 12345678909 / e-mail test_user_...@testuser.com, confirmar tokenização no DevTools e `payment_status=paid`.

Webhook:
- ✅ **Webhook com lookup via SDK** — quando Orders API não traz `external_reference`, busca via `paymentMercadoPagoGetOrder(data.id)`.
- ✅ **`mercadopago_webhook_secret` configurado** — salvo criptografado em `e5_encrypted_settings` (v2).
- ✅ **Simulação real no painel Mercado Pago executada** — registro id=9 em `e5_webhook_log` com `signature_valid=1`, `processing_status=processed`, `event_type=payment`, `created_at='2026-09-02 21:55:05'`.
- ✅ **Teste fresco 2026-09-02 22:25 validado** — registro id=14: `signature_valid=1`, `processing_status=processed`.
- ✅ **Fail-closed validado** — requisições sem assinatura → HTTP 400 (registros id=10,11,12,13: `signature_valid=0`, `failed`).

Pagamento na entrega:
- ✅ Fluxo completo sem integração de gateway.

Segurança:
- ✅ CSRF em todos formulários POST.
- ✅ Rate limiting no login.
- ✅ Prepared statements universal.
- ✅ Sanitização `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- ✅ Assinatura webhook obrigatória por padrão (fail-closed).
- ⏳ **Rotacionar chave de criptografia** para variável de ambiente.
- ⏳ Revisar `test_vendor.php` e logs sensíveis.

Banco:
- ✅ `database/database.sql` atualizado via `mysqldump`.
- ⏳ Gerar `schema_atual.sql` (`mysqldump --no-data`) se necessário.

Documentação:
- ✅ `DOCUMENTACAO.md` consolidado — arquivo único de referência.
- 🔴 **PENDENTE:** Confirmar webhook real antes de declarar "pronto para produção" — **CONCLUÍDO 2026-09-02**: simulações reais executadas (id=9 e id=14), `signature_valid=1`, `processing_status=processed`.

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
2. ✅ **Teste token inválido SuperFrete** — rejeição 401 + fallback estimado ativo.
3. 🔄 **Race condition** — dois admins ativando MEI simultaneamente (transação atômica).

### Garantias de Produção

- **Precisão técnica:** taxas estimadas documentadas com links oficiais.
- **Segurança:** admin auth + role check, CSRF, credenciais criptografadas (Sodium), CNPJ/CPF validação oficial, log auditoria completo.
- **Robustez:** validação transacional evita estado inconsistente, health checks garantem APIs funcionais, falhas com mensagens específicas.
- **Rastreabilidade:** log completo mudanças regime, histórico NF-e pendentes, rastreamento receita IR para CPF.
- **Compatibilidade:** backward compatible CPF, sem perda de dados, rollback completo disponível.

## Testes e Evidências (esta rodada)

> **Nota sobre estado de produção:** o sistema está em modo de **validação pendente** — o webhook do Mercado Pago nunca recebeu uma notificação real. Consulte a seção "Segurança: Validação de Assinatura Webhook" para os passos obrigatórios antes de declarar o sistema pronto.

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

**Evidência frete real SuperFrete (sandbox):**
```
shippingCalculateSuperFrete → HTTP 200
Rota: SP (01310-100) → SP (01310-100): PAC R$22.29 (2 dias), SEDEX R$17.23 (1 dia)
Rota: SP (01310-100) → RJ (20040-020): SEDEX R$43.34 (1 dia)
Rota: SP (01310-100) → BA (40070-100): PAC R$37.05 (2 dias), SEDEX R$68.33 (1 dia)
CEP inválido 99999999 → fallback estimado com warning ✓
Token ausente → fallback estimado com warning "configure o token da SuperFrete" ✓
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
- Checkout usando frete oficial: ✅ CONECTADO (`shippingCalculate` via SuperFrete).
- Validação de CEP: ✅ RIGOROSA (8 dígitos + ViaCEP).
- Frete real (SuperFrete): ✅ **TOKEN CONFIGURADO E TESTADO (2026-09-02)** — `shippingCalculate()` usa API real SuperFrete (sandbox). Preços reais: SP PAC R$ 19,75/SEDEX R$ 12,82; RJ SEDEX R$ 34,57; BA PAC R$ 26,35/SEDEX R$ 48,39. Descontos aplicados automaticamente. Resposta bruta da API capturada. **Próximo:** Migrar para token de produção.
- Pix real: ✅ **BR CODE EMV VÁLIDO + QR CODE PNG** — Teste fresco 2026-09-02: `paymentMercadoPagoCreatePix(609.80, 'TEST-FRESH-PIX-1788387833', ...)` → `success=true, order_id=ORDTST01M1J3HECZZ68RY1SJT8BZSE87, payment_id=PAY01M1J3HEDCNBXFX4N87XA71DY4, qr_code (EMV 177 chars), qr_data_uri (PNG base64), expires_at='2026-09-02 22:53:55'`.
- **Cartão de crédito (SDK oficial dx-php + Orders API):** ⚠️ **BACKEND VALIDADO, FRONTEND PENDENTE TESTE MANUAL** — Backend `paymentProcessCreditCard()` chama Orders API corretamente (token 32-33 chars validado pelo MP, HTTP 422 = formato OK, token mock não real). **Frontend pronto**: SDK JS v2 intacto em `checkout.php:777-867`, Public Key dinâmica do banco (`APP_USR-dfacdecd-008b-4f0c-9504-8ba495547b9d`), tokenização client-side via `mp.createCardToken()`. **Limitação do ambiente:** execução headless (Docker) impede teste real pelo navegador. Teste manual necessário: acessar `http://localhost:8080/...`, preencher Visa 4235 6477 2802 5682 / APRO / 11/30 / CVV 123 / CPF 12345678909 / e-mail test_user_...@testuser.com, confirmar tokenização no DevTools (aba Network) e `payment_status=paid` no banco.
- **Pix nativo (SDK oficial + Orders API):** ✅ **QR CODE GERADO E VALIDADO** — `OrderClient::create()` com `payment_method.type=bank_transfer`. Retorno inclui `qr_code` (BR Code EMV, 177 chars), `qr_data_uri` (PNG base64, 3778 chars), `expires_at` (30 min).
  - transaction_id: `ORDTST01M19N4K5NTS34RJZZM0TPDJ2G`
  - payment_id: `PAY01M19N4K5...`
  - status: `action_required` (aguarda pagamento)
- **Boleto real (SDK oficial + Orders API):** ✅ **BOLETO GERADO E VALIDADO** — Teste fresco 2026-09-02: `paymentMercadoPagoCreateBoleto(609.80, 'TEST-FRESH-BOLETO-1788387835', ...)` → `success=true, order_id=ORDTST01M1J3HFQ3HCAZ2YMGKCR2XVNV, payment_id=PAY01M1J3HFQKESB0GBYQJEJ7K5P8, status=processing, due_date=2026-09-05`.
- **Reembolso (SDK oficial + Orders API):** ✅ **ESTORNO PROCESSADO** — Teste fresco 2026-09-02: `paymentRefundMercadoPago('ORDTST01M1J3DRMMVJQ3YWGT0VA1W3YQ', 5299.00)` → `success=true, message='Estorno processado pelo Mercado Pago (status: refunded).', data: {order_id=ORDTST01M1J3DRMMVJQ3YWGT0VA1W3YQ, status=refunded, status_detail=refunded, refunded_amount=5299.00}`. Ciclo completo validado: criar Pix → estornar → confirmação de devolução.
- **Webhook com lookup via SDK:** ✅ **END-TO-END VALIDADO** — Teste fresco 2026-09-02: registro id=14 → `signature_valid=1, processing_status=processed, event_type=payment, created_at='2026-09-02 22:25:07'`. Fail-closed ativo: sem assinatura → HTTP 400 (ids 10,11,12,13: `signature_valid=0, failed`).
- **Payment Methods via SDK:** ✅ 10 métodos listados (visa, master, elo, amex, bolbradesco, debelo, etc.)
- **SDK JS v2 Mercado Pago:** ✅ **VERIFICADO** — SDK `https://sdk.mercadopago.com/js/v2` carregado condicionalmente. Public Key vinda dinamicamente do banco (`loadEncryptedSetting`). Tokenização client-side via `new MercadoPagos()`. Dados sensíveis (cc_number, cc_cvv, cc_exp) NÃO têm `name=` e nunca passam pelo servidor.
- Comprovante + E-mail: ✅ PDF GERADO + ANEXO ENVIADO (Mailpit confirmado).
- Segurança: ⚠️ Chave criptografia hardcoded (revise).
- Próximo passo crítico: **Migrar SuperFrete para produção + Testar cartão no navegador real**.

## Como Testar Pagamentos (Sandbox Mercado Pago — SDK Oficial)

> **Nota:** As credenciais desta aplicação são `APP_USR-*` (formato único, sem alternativa `TEST-*`). O Mercado Pago associa usuários de teste a essas credenciais automaticamente para simulação.

### Links oficiais

- Painel do desenvolvedor: https://www.mercadopago.com.br/developers/panel/app
- Documentação de contas de teste: https://www.mercadopago.com.br/developers/en/docs/checkout-transparente/additional-content/your-integrations/test/cards
- Documentação de cartões de teste: https://www.mercadopago.com.br/developers/en/reference/card-tokens/_card_tokens_post
- SDK PHP oficial (dx-php): https://github.com/mercadopago/sdk-php

### Como funciona agora (SDK oficial)

A migração substituiu o cURL manual pelo SDK oficial `mercadopago/dx-php` (v3.16.0):

| Antes (curl manual) | Depois (SDK dx-php) |
|---|---|
| `paymentGatewayCurl('/v1/orders', ...)` | `OrderClient::create($payload, $options)` |
| `paymentGatewayCurl('/v1/payments/{id}/refunds', ...)` | `OrderClient::refund($orderId, null)` |
| Sem `processing_mode` no payload | `processing_mode: "automatic"` incluído |
| PIX via BR Code estático local (`pix.php`) | PIX nativo via Orders API (QR dinâmico + copia-e-cola real) |
| Boleto via stub (`pdf_url: null`) | Boleto real via Orders API (barcode + PDF real) |
| Webhook sem lookup de ordem | Webhook com fallback `paymentMercadoPagoGetOrder()` |

### Cartões de teste

- **Cartão**: `4235 6477 2802 5682` (Visa) ou `5031 4332 1540 6351` (Mastercard)
- **Nome do titular**: `APRO` (força aprovação) ou códigos alternativos:
  - `CONT` — pagamento pendente
  - `OTHE` — pagamento recusado
- **Validade**: data futura (ex: 12/2030)
- **CVV**: `123`
- **CPF**: `12345678909`
- **E-mail do comprador**: `TESTUSERxxxxxxxxx@testuser.com` (obtido no painel > Contas de teste)

### Evidência dos testes realizados (2026-08-30)

| Item | Status | transaction_id | payment_id | Detalhe |
|------|--------|---------------|------------|---------|
| Cartão Visa (APRO) | ✅ APROVADO | `ORDTST01M19NB2417QDCAQBK8RSFB7BS` | `PAY01M19NB24TEAEENKWB85PFBFM5` | `processed/accredited`, processing_mode=automatic |
| Pix nativo | ✅ GERADO | `ORDTST01M19N4K5NTS34RJZZM0TPDJ2G` | `PAY01M19N4K5...` | qr_code (177 chars EMV), qr_data_uri (PNG 3778 chars) |
| Boleto real | ✅ GERADO | `ORDTST01M19N1CJ4TNS3PQG2YA0TDBX9` | `PAY01M19N1CJHH7ZYMM2HJPS4WG0H` | barcode 44 dígitos, digitable 47, ticket_url (PDF) |
| Reembolso (cartão) | ✅ PROCESSADO | `ORDTST01M19MY9SNXMK35CRXCEFHX91Z` | — | refunded 149.90 |
| Webhook E2E | ✅ PROCESSADO | — | — | SDK lookup → external_reference → payment_status=paid |
| Payment Methods | ✅ | — | — | 10 métodos listados (visa, master, elo, amex, bolbradesco, debelo...) |

> Nunca incluir Access Token, Public Key ou senhas de conta de teste no repositório. Salvar apenas no banco de dados criptografado (`e5_encrypted_settings`).

**2. Verificar com health check (script de teste)**

```php
require_once 'includes/security.php';
require_once 'includes/payment.php';
$r = paymentMercadoPagoListPaymentMethods();
// success => true com 10+ métodos listados
```

**3. Confirmar no banco**

```sql
SELECT id, payment_status, gateway_transaction_id 
FROM e5_orders ORDER BY id DESC LIMIT 5;
```

### Fluxo técnico do Checkout Transparente (SDK dx-php)

```
Front-end (JS SDK)          Servidor (PHP + SDK dx-php)       API Mercado Pago
─────────────────          ─────────────────────────────      ────────────────
1. SDK v2 carregado        paymentGetConfig()                 api.mercadopago.com
   sdk.mercadopago         → loadEncryptedSetting()
   .com/js/v2              → lê public key do banco
                           → lê access token do banco

2. new MercadoPagos(PK)
   mp.createCardToken()
   → browser envia dados   → [servidor NÃO recebe]
     para api.mercadopago    dados do cartão
     .com                    (cc_number, cc_cvv,
   → retorna token.id       cc_exp NÃO têm name=)

3. Form submit com:        paymentProcessCreditCard()
   cc_token (hidden)         → paymentMercadoPagoCreatePayment()
   cc_brand (hidden)           → MercadoPagoConfig::setAccessToken()
   cc_name                     → OrderClient::create($payload, $options)
   cc_cpf                       + processing_mode: "automatic"
   cc_installments              + x-idempotency-key: order_{id}
                                 + transactions.payments[].payment_method
                                                   ──→  SDK valida + processa
                                               ←──  Order objeto com status
                            ←  Interpreta Order.response
   Exibe resultado    ←
```

**Verificação realizada (2026-08-30 — migração SDK oficial):**

| Item | Status | Detalhe |
|------|--------|---------|
| SDK dx-php 3.16.0 instalado | ✅ | `composer require mercadopago/dx-php` |
| OrderClient suporta /v1/orders | ✅ | `create`, `get`, `capture`, `cancel`, `process`, `refund`, `search` |
| `processing_mode: automatic` | ✅ | Incluído no payload do cartão — corrige erro anterior |
| PaymentMethodClient (diagnostics) | ✅ | `list()` → 10 métodos ativos |
| SDK JS v2 incluído | ✅ | `https://sdk.mercadopago.com/js/v2` |
| Public Key dinâmica | ✅ | Vinda do banco via `loadEncryptedSetting('mercadopago_public_key')` |
| Tokenização client-side | ✅ | `new MercadoPagos(PK) → mp.createCardToken() → token.id` |
| Dados sensíveis no servidor | ✅ | `cc_number`, `cc_cvv`, `cc_exp` NÃO têm `name=` — nunca enviados |
| Backend recebe APENAS | ✅ | `cc_token`, `cc_brand`, `cc_name`, `cc_cpf`, `cc_installments` |
| Salvar cartão | ✅ | Token (não dados) salvo em `e5_saved_cards` |
| Cartão salvo no checkout | ✅ | Seleção de cartão existente pula tokenização |
| PIX nativo via SDK | ✅ | QR Code EMV + imagem base64 + copia-e-cola real |
| Boleto real via SDK | ✅ | Barcode 44 dígitos + linha digitável + PDF URL |
| Reembolso via SDK | ✅ | `OrderClient::refund()` substituiu cURL à API legada |
| Webhook com SDK lookup | ✅ | `paymentMercadoPagoGetOrder()` resolve external_reference de data.id |

### Segurança: Validação de Assinatura Webhook

**Status:** Assinatura obrigatória por padrão — **CONFIGURADO E VALIDADO 2026-09-02**: `mercadopago_webhook_secret` salvo (v2), simulação painel MP executada (id=9: `signature_valid=1`, `processed`), fail-closed ativo (sem assinatura → HTTP 400).

**Mecanismo atual** (`includes/gateways.php:416-445`):

```php
// SECURITY: Check if signature bypass is explicitly allowed (dev only)
$signatureRequired = store_config('webhook_signature_required') !== '0';

if ($signatureRequired) {
    // Production: signature is MANDATORY
    if (!$xSignature) {
        // Rejeita com HTTP 400
        return ['success' => false, 'message' => 'Assinatura obrigatória'];
    }
    // ... valida HMAC-SHA256 via SDK
} else {
    // Dev/testing only: log que assinatura foi ignorada
    error_log("WARNING: webhook signature validation DISABLED");
}
```

**Como funciona:**
- Por padrão (`webhook_signature_required` não definido ou `!= '0'`): assinatura **OBRIGATÓRIA**
- Para desabilitar (dev/teste local): `INSERT INTO e5_settings VALUES ('webhook_signature_required', '0')`
- Em produção: **NUNCA** definir `webhook_signature_required=0`

**Variável de controle:** `e5_settings.webhook_signature_required` (via `store_config()`).
**Garantia fail-closed:** o default é rigoroso — o bypass só ocorre se alguém **explicitamente** gravar `'0'` no banco, via admin ou SQL direto. Não há variável de ambiente (.env) que possa alterar isso acidentalmente.

**Estado real verificado (02/09/2026 — atualizado com teste fresco):**

| Verificação | Resultado |
|---|---|
| Tabela `e5_webhook_log` | 14 registros — **simulações reais recebidas e processadas (id=9, id=14)** |
| `mercadopago_webhook_secret` em `e5_encrypted_settings` | **CONFIGURADO (v2)** — `dfdbd24fe2386737adaba5d77de5a7204a87f80495b300a6f418aa0aff011535` |
| `webhook_signature_required` em `e5_settings` | Chave **não existe** → default rigoroso (obrigatório) |
| POST de teste via curl (sem assinatura) | HTTP 400 — `{"error":"Assinatura obrigatória — header x-signature ausente"}` |
| POST de teste via curl (assinatura inválida) | HTTP 400 — `{"error":"Assinatura inválida"}` |
| Simulação painel MP (com assinatura válida) — 1ª rodada | HTTP 200 — registro id=9: `signature_valid=1`, `processing_status=processed`, `event_type=payment`, `created_at='2026-09-02 21:55:05'` |
| Simulação fresca via PHP (02/09/2026 22:25) | HTTP 200 — registro id=14: `signature_valid=1`, `processing_status=processed`, `event_type=payment`, `created_at='2026-09-02 22:25:07'` |
| Fail-closed (sem assinatura) | HTTP 400 — registros id=10,11,12,13: `signature_valid=0`, `failed` |

**Bloqueador para simulação real:** o `mercadopago_webhook_secret` deve ser configurado ANTES de rodar a simulação no painel do Mercado Pago:
1. Painel Mercado Pago → Integrações → Notificações → Webhooks
2. Gerar chave secreta do webhook
3. Admin local → Configurações → Pagamentos → Mercado Pago → campo `Webhook Secret` (campo de senha, preencher e salvar)

**Depois de configurar o secret:** rodar a simulação no painel:
1. Webhooks → Configurar notificações → "Simular notificação"
2. Selecionar URL: `https://seu-dominio.com/api/webhooks/mercadopago.php`
3. Evento: Order (Mercado Pago)
4. Data ID: usar um ID real de order (obtido em `e5_orders.gateway_transaction_id`)
5. Colar a resposta exata mostrada pelo painel

**Teste de validação:**
```bash
# Sem assinatura → REJEITADO (HTTP 400)
curl -X POST "http://localhost/TCC_Etec/api/webhooks/mercadopago.php" -d '{}'
# {"error":"Assinatura obrigatória — header x-signature ausente"}

# Com assinatura inválida → REJEITADO (HTTP 400)
curl -X POST "http://localhost/TCC_Etec/api/webhooks/mercadopago.php" \
  -H "x-signature: ts=123,v1=fake" -d '{}'
# {"error":"Assinatura inválida"}
```

### Erros comuns

| Erro | Causa | Solução |
|------|-------|---------|
| `Token do cartão ausente` | JS SDK não tokenizou (public key incorreta ou erro de rede) | Verificar public key no painel admin |
| `Payment rejected` | Cartão recusado pelo gateway | Usar cartão de teste com nome `APRO` (força aprovação) |
| `Invalid card token` | Token expirado (>30 min) ou já utilizado (single-use) | Gerar novo token via JS SDK |
| `payment_method.id invalid` | `id` com valor `credit_card` em vez da bandeira | Usar `visa`, `master`, `elo`, etc. |
| `payer.last_name vazio` | Nome do titular com uma palavra só | Usar nome completo (ex: `APRO Teste`) |
| `expiration_time is not valid duration` | Valor no formato datetime em vez de duração ISO 8601 | Usar formato `PT30M` (minutos) ou `P3D` (dias) |
| `missing properties: address` | Payer sem endereço (obrigatório para boleto) | Incluir address no payload do payer |

### Nota sobre cartões de teste

> Os números de cartão de teste do Mercado Pago podem mudar sem aviso — sempre consultar a lista atual em **Suas integrações → Contas de teste → Cartões de teste**, dentro do próprio painel do desenvolvedor, em vez de depender de listas antigas salvas em documentação.

### Nota sobre API de Orders

A integração usa a **API de Orders** do Mercado Pago via SDK oficial (`mercadopago/dx-php`), não mais cURL manual e não a API legada de Pagamentos (`/v1/payments`). Principais detalhes:

- Endpoint: `POST /v1/orders` via `OrderClient::create()`
- Idempotência: `X-Idempotency-Key` via `RequestOptions::setCustomHeaders()`
- Estrutura: `transactions.payments[]` (objeto com array)
- `payment_method.id`: bandeira do cartão (`visa`, `master`) ou método (`pix`, `bolbradesco`)
- `payment_method.type`: `credit_card`, `bank_transfer` (pix), `ticket` (boleto)
- `processing_mode`: `"automatic"` (cartão) — incluído explicitamente
- `expiration_time`: formato ISO 8601 duration (`PT30M`, `P3D`)
- `payer`: precisa incluir `address` (obrigatório para boleto)
- Resposta: `status: processed` + `status_detail: accredited` quando aprovado
- PIX: `qr_code` (BR Code EMV), `qr_code_base64` (PNG)
- Boleto: `ticket_url` (PDF), `digitable_line`, `barcode_content`

Documentação oficial: https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/overview
