# RoyalTech — Diagnóstico e Arquitetura

**Projeto:** TCC ETEC — e-commerce completo
**Branch:** `FIx/perfil` @ `83ec920`
**Data do diagnóstico:** 2026-09-29

---

## 0. Escopo desta entrega

Este documento é a **Etapa 0** do plano:

1. Como rodar o projeto
2. Mapa de arquivos
3. Schema atual do banco
4. **Inventário completo do que não funciona** (o núcleo)
5. Bugs e vulnerabilidades por severidade
6. Plano de implementação

### Etapa cancelada

A **Etapa 1 (redesign visual no estilo Mercado Livre) foi cancelada** a pedido
do orientador. O tema escuro/dourado atual e o `assets/css/mercadolivre-style.css`
existente são **mantidos**. Nenhuma alteração visual de tema será feita.

### Correção a um relatório anterior

fraca e verificavel localmente com `password_verify()` contra o hash do proprio seed. **O valor nao e reproduzido aqui de proposito**: este arquivo vai para o repositorio publico, e publicar a senha daria acesso administrativo de graca. Rotacionar no primeiro deploy.
(verificado com `password_verify()`), não `password123`.

---

## 1. Como rodar

### Requisitos

| Item | Versão | Observação |
|---|---|---|
| PHP | 8.2+ | `pdo_mysql` **obrigatório** |
| MySQL/MariaDB | 8.0 / 10.6 | precisa suportar JSON e `utf8mb4` |
| Apache | 2.4 | com `mod_rewrite` e `AllowOverride All` |
| Composer | 2.x | só para instalar dependências |

### Dependências (já instaladas em `vendor/`)

```
dompdf/dompdf 3.1     -> comprovante PDF e relatorios
guzzlehttp/guzzle 7   -> cliente HTTP da SuperFrete
phpmailer/phpmailer 6 -> envio de e-mails
```

> `vlucas/phpdotenv` esta declarado no `composer.json` mas **nao e usado**.
> O projeto usa um parser proprio, `loadEnv()` em `includes/config.php:10`.

### Configuracao

```bash
cp .env.example .env
# editar .env com as credenciais
php -S localhost:8080 -t .     # servidor alternativo para testes
```

Variaveis obrigatorias (`.env`):

| Chave | Uso |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET` | conexao PDO |
| `SUPERFRETE_TOKEN` | Bearer da API SuperFrete |
| `SUPERFRETE_BASE_URL` | `https://sandbox.superfrete.com/` ou `https://api.superfrete.com` |
| `SUPERFRETE_USER_AGENT` | **obrigatorio pela SuperFrete** — nome da loja + e-mail |
| `SUPERFRETE_ORIGIN_POSTAL_CODE` | CEP de origem do remetente |
| `SUPERFRETE_WEBHOOK_SECRET` | HMAC do webhook |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM` | SMTP |

> **Atencao ao ambiente de desenvolvimento:** o MySQL do XAMPP desta maquina
> escuta em **`127.0.0.1:3307`**, nao em 3306. O `.env` com `DB_HOST=localhost`
> falha com `SQLSTATE[HY000] [2002] Connection refused`. Use
> `DB_HOST=127.0.0.1` + `DB_PORT=3307` ou corrija a porta no XAMPP.

### Primeiro acesso

O banco `e5_royaltech` e criado e populado **automaticamente**: se a conexao
falhar com erro 1049 (banco inexistente) ou 1146 (tabela inexistente),
`database/connection.php:29-38` executa todo o `database/database.sql`.

> Isso significa que **todo deploy novo recebe automaticamente uma conta
> de administrador com credencial conhecida e publica**. Ver secao 6,
> item CRITICO-2.

### Usuarios de teste

O seed cria ~1 conta `admin` e varias contas `customer`, todas com a **mesma**
senha padrao fraca. E-mails e senhas **nao sao listados aqui**: sao dados de
pessoas reais e credenciais em um repo publico. Para auditar localmente:

```sql
SELECT id, name, email, role FROM e5_users ORDER BY role, id;
```

### Testes automatizados

```bash
composer install
./vendor/bin/phpunit
```

**Estado atual: 62 testes, 289 asercoes, todos passando.** Cobertura: apenas
utilitarios estaticos, fixtures JSON e asserts de hash de assets.
**Zero testes de integracao** — nem HTTP, nem banco, nem regras de negocio.

---

## 2. Mapa de arquivos

```
TCC_Etec/
├── index.php                     home (carrossel, banners, destaques, categorias)
├── .htaccess                     rotas amigaveis, bloqueia .env/includes/database
│
├── components/
│   ├── header.php                topo, busca, menu, contadores carrinho/favoritos
│   ├── footer.php                rodape, links institucionais, scripts
│   └── product-card.php          cartao de produto reutilizavel
│
├── includes/
│   ├── config.php                loadEnv(), store_defaults(), store_config()
│   ├── cart_functions.php        CRUD do carrinho
│   ├── coupon_functions.php      validacao de cupom (regra completa)
│   ├── wishlist_functions.php    favoritos
│   ├── csrf.php                  token CSRF
│   ├── rate_limit.php            limitador por IP/sessao
│   ├── mail.php                  PHPMailer + SMTP + diagnostico de erro
│   ├── comprovante_functions.php comprovante HTML/PDF + envio
│   ├── status_labels.php         rotulos e classes de status
│   ├── image_helpers.php         normalizacao e upload de imagem
│   ├── contact_functions.php     contato (envio + bloqueio por e-mail)
│   ├── category_icons.php        icones por slug de categoria
│   └── logout.php
│
├── src/
│   ├── SuperFreteClient.php      client HTTP (12 endpoints) — SEM camada de negocio
│   ├── Webhook/WebhookHandler.php validacao HMAC + idempotencia (processEvent vazio)
│   └── Exception/SuperFreteException.php
│
├── pages/
│   ├── cart/                     add, update, remove, coupon, cart, checkout
│   ├── wishlist/                 toggle, wishlist
│   ├── auth/                     login, register, insertion, authentication,
│   │                             forgot/reset-password, profile, orders,
│   │                             order-detail, contacts, comprovante-resend, logout
│   ├── products/                 products, product-detail, categories, contact, about
│   ├── conteudo/                 especial
│   ├── admin/                    index, products, product-form, categories,
│   │                             package-sizes, coupons, banners, orders,
│   │                             order-detail, customers, contacts, contact-detail,
│   │                             newsletter, reports, settings, auth_check,
│   │                             head/sidebar/header_user includes
│   ├── 404.php
│   └── download-comprovante.php
│
├── webhook/superfrete.php        endpoint do webhook
├── scripts/demo.php              demo da API SuperFrete via CLI
├── assets/css/                   style, mercadolivre-style, admin, auth,
│                                 login*, register*, theme-extras
├── assets/js/                    script, theme-extras
├── assets/vendor/qrcodejs/       qrcode.min.js — NAO REFERENCIADO
├── storage/                      comprovantes (PDF), cache, logs
├── database/
│   ├── database.sql              schema + seed
│   └── migrations/               2 migrations aplicadas
└── tests/                        3 arquivos, 62 testes
```

**Estoque de codigo:** 11.230 linhas de PHP + 8.298 linhas de CSS/JS.

### Arquivos mortos ou orfaos

| Arquivo | Situacao |
|---|---|
| `assets/css/login.css` (211 l.) | **nao linkado por nenhum arquivo** |
| `assets/css/register.css` (194 l.) | **nao linkado por nenhum arquivo** |
| `assets/vendor/qrcodejs/qrcode.min.js` | existe, **nunca e carregado** — nenhum QR e gerado |
| `storage/cache/shipping/*.json` | residuo de `fix/cart-enchancements`; **nenhum codigo le** |
| `storage/comprovantes/COMP-00000N.pdf` | 5 PDFs reais, **publicamente acessiveis** |
| `composer.json` -> `vlucas/phpdotenv` | declarado, nunca usado |

### Camadas de CSS hoje

| Folhas | Situacao |
|---|---|
| `style.css` (1759 l.) | tema escuro/dourado legado — **ainda carregado e ainda necessario** (rodape, grid de categorias, toast, contato) |
| `mercadolivre-style.css` (4050 l.) | camada de componentes `.ml-*` |
| `admin.css` (777 l.) | painel |
| `auth.css` (338 l.) | telas de auth |
| `theme-extras.css` (130 l.) | efeitos/"easter eggs" (protegido por hash em `.github/asset-integrity.json`) |

> **Atencao:** `mercadolivre-style.css` **nao** e um tema Mercado Livre. E uma
> camada de componentes *sobre* o tema escuro. As duas folhas sao carregadas
> juntas em `components/header.php:75-80`. Apagar qualquer uma quebra paginas.

### Classes usadas pelo PHP sem nenhum CSS correspondente

`ml-cart-link`, `ml-wishlist-link` (icones do header) · `ml-btn-sm`
(order-detail) · `ml-reply-box` (contatos) · `checkout-left` (checkout) ·
`item-check` (checkbox do carrinho) · `btn-sm` e `btn-danger` (cupons) ·
`ml-card`, `ml-btn` (order-detail admin) · `popular-product`, `checkbox-label`

---

## 3. Schema atual do banco

**17 tabelas.** Todas InnoDB? **Nao.** Apenas 9 declaram `ENGINE=InnoDB`; as
outras 8 herdam o padrao do servidor — e entre elas estao **`e5_orders`** e
**`e5_cart`**, que dependem de transacao.

| Tabela | Colunas principais | Chave |
|---|---|---|
| `e5_users` | id, name, email, **cpf**, username, password, role, **postal_code, street, number, complement** | UNIQUE email, username |
| `e5_categories` | id, name, slug, description | UNIQUE name, slug |
| `e5_package_sizes` | id, name, height_cm, width_cm, length_cm, max_weight_kg, is_active | UNIQUE name |
| `e5_products` | id, category_id, name, slug, description, brand, price, old_price, stock, is_featured, package_size_id, weight_kg, height_cm, width_cm, length_cm | FK categoria, embalagem |
| `e5_product_images` | id, product_id, image_path, is_primary | FK produto CASCADE |
| `e5_orders` | id, user_id, **status**, total, shipping_method, shipping_cost, **payment_method**, coupon_code, payment_card_last_four, **payment_status**, **shipping_neighborhood, shipping_city, shipping_state, shipping_postal_code**, **tracking_code**, comprovante_filename, email_status, email_error, created_at, updated_at | FK usuario |
| `e5_order_items` | id, order_id, product_id, quantity, unit_price | FK pedido CASCADE, FK produto |
| `e5_cart` | id, user_id, product_id, quantity | UNIQUE (user_id, product_id) |
| `e5_contacts` | id, user_id, name, email, phone, subject, message, status, response_message, responded_by, responded_at, response_email_status, response_email_error | FKs SET NULL |
| `e5_newsletter` | id, email | UNIQUE email |
| `e5_password_reset_tokens` | id, user_id, token, expires_at, used | UNIQUE token |
| `e5_banners` | id, title, subtitle, image_path, link_url, is_active | — |
| `e5_wishlist` | id, user_id, product_id | UNIQUE (user_id, product_id) |
| `e5_settings` | setting_key (PK), setting_value, updated_at | — |
| `e5_saved_cards` | id, user_id, card_brand, holder_name, last_four, exp_month, exp_year, max_installments, is_active | FK usuario CASCADE |
| `e5_coupons` | id, code, type, value, min_amount, max_discount, valid_from, valid_until, active, customer_scope, max_uses, used_count | UNIQUE code |
| `superfrete_webhook_log` | id, **event_id**, event_type, order_id, **payload_hash**, **processed_at (nunca gravado)**, created_at | UNIQUE event_id |

### Como status sao armazenados

`ENUM` de string, nunca inteiro.

| Campo | Valores |
|---|---|
| `e5_orders.status` | `pending`, `paid`, `shipped`, `delivered`, `canceled` |
| `e5_orders.payment_status` | `pending`, `paid`, `refunded` |
| `e5_contacts.status` | `pending`, `answered` |
| `e5_coupons.type` | `percent`, `fixed` |
| `e5_users.role` | `customer`, `admin` |

Rotulos e classes CSS ficam em `includes/status_labels.php:2-8`.
Whitelist de escrita duplicada em `pages/admin/orders.php:12`.
**Tres lugares precisam mudar juntos** se entrar um status novo.

### Tabelas exigidas pelo escopo — situacao atual

| Exigida | Existe? | Avaliacao |
|---|---|---|
| `enderecos` | ❌ **nao** | Endereco esta **embutido em `e5_users`** (4 colunas). Um por usuario, sem rotulo (Casa/Trabalho), sem historico, sem padrao, e `number INT` perde "S/N", "85-A", bloco. |
| `cartoes` | ✅ `e5_saved_cards` | **Adequado**: guarda bandeira + ultimos 4 + validade, **nunca o numero completo nem CVV** (PCI-safe). Mas esta **orfa** — nenhum codigo insere nem le. |
| `pedido_status_historico` | ❌ **nao** | Zero auditoria. `status` e sobrescrito no lugar. |
| `envios` | ❌ **nao** | **A lacuna mais grave.** Nada persiste `superfrete_order_id`, transportadora, URL da etiqueta, `tracking_code` (a coluna existe mas **nunca e gravada**). |
| `notificacoes` | ❌ **nao** | Sem fila. E-mail e enviado **sincronamente** dentro do request. |
| `notificacoes_log` | ❌ **nao** | Idem. |
| `configuracoes` | ✅ `e5_settings` | **Adequado**: chave/valor com override do banco sobre defaults estaticos, escrita com allow-list. |
| `webhooks_log` | ⚠️ parcial | `superfrete_webhook_log` tem idempotencia e `payload_hash`, mas **nao grava o JSON bruto** (decisao de LGPD) e `processed_at` nunca e preenchido — um crash entre gravar e processar e indistinguivel de sucesso. |

**Placar: 2 de 8 atendidos, 1 parcial, 5 ausentes.**

---

## 4. Diagnostico — o que nao funciona

### 4.1 CRITICO: o pagamento nao existe

Este e o achado mais importante do diagnostico.

**`pages/cart/payment.php` NAO EXISTE.** O arquivo nunca foi criado. Ainda assim
o checkout "finaliza a compra" — e o resultado e uma compra que nao pode ser paga.

| O que o escopo pede | Realidade |
|---|---|
| Pagina de pagamento | ❌ arquivo inexistente |
| Redirecionar para pagamento | ❌ o checkout renderiza a confirmacao **inline**, sem PRG |
| QR Code Pix | ❌ `qrcode.min.js` esta no disco e **nao e carregado por ninguem** |
| Codigo copia-e-cola | ❌ **fake** — `checkout.php:416` monta uma string com CRC inventado. Nenhum app de banco le. |
| Contador de expiracao | ❌ nao existe |
| Polling de status | ❌ nao existe (o unico `setInterval` do projeto e o carrossel da home) |
| Simulador (aprovar/erro/expirar) | ❌ nao existe |
| Persistir codigo Pix | ❌ `e5_orders` nao tem colunas de Pix/boleto |
| "Pagar depois" | ❌ nao existe |
| Boleto | ❌ **fake** — `checkout.php:423` concatena digitos literais |
| Confirmacao de pagamento | ⚠️ so **manual**, pelo admin (`admin/order-detail.php:33-34`) |

**Consequencia pratica:** todo pedido Pix/cartao fica em `pending` para sempre,
e o cliente nao tem como pagar. O status so muda se um admin clicar em
"Confirmar pagamento" a mao.

**Agravante:** nao ha PRG nem chave de idempotencia no checkout. **Atualizar a
pagina (F5) apos confirmar re-executa o `confirm_order` e cria um segundo pedido.**

### 4.2 CRITICO: integracao SuperFrete — existe o client, nao existe a integracao

`src/SuperFreteClient.php` tem 12 endpoints prontos e testados por forma de
fixture. **Nenhum e chamado pela aplicacao**, exceto a cotacao:

| Endpoint | Client | Chamado por |
|---|---|---|
| `POST /api/v0/calculator` | ✅ | `checkout.php:105` (unico) |
| `POST /api/v0/cart` (criar etiqueta) | ✅ | ❌ **ninguem** |
| `POST /api/v0/checkout` (pagar etiqueta) | ✅ | ❌ **ninguem** |
| `POST /api/v0/tag/print` (imprimir) | ✅ | ❌ **ninguem** |
| `GET /api/v0/order/info/{id}` | ✅ | ❌ **ninguem** |
| `POST /api/v0/order/cancel` | ✅ | ❌ **ninguem** |
| `GET /api/v0/me/orders` | ✅ | ❌ **ninguem** |
| Webhooks (CRUD) | ✅ | ❌ **ninguem** |
| **Rastreamento** | ❌ **nao existe** | — |

Faltam, por completo:

- **Camada de servico.** O client e um adaptador de transporte 1:1. Toda a
  logica de negocio (escolher embalagem, somar volumes, filtrar PAC/SEDEX,
  extrair preco) esta **embutida no controller** `checkout.php:71-144`.
- **Cache de cotacao.** Nao existe neste branch. `checkout.php:200-206` chama a
  API **a cada render** e, em qualquer falha, cai num fallback com **precos
  inventados** (`R$ 14,90 / 29,90 / 9,90 / 19,90 / 24,90 / 39,90`) apresentados
  ao cliente como se fossem reais.
- **Retry/backoff.** Nenhum. Um 5xx momentaneo = falha na hora.
- **Logging.** Desligado em producao — `checkout.php:78` nao passa caminho de log.
- **Mapeamento de status.** `WebhookHandler::processEvent()` (`:216-228`) e um
  **no-op** que so chama `error_log()`. Um webhook `order.posted` **nao muda
  nada no banco**. Nao ha tabela de mapeamento.
- **`e5_orders.superfrete_order_id`** nao existe -> mesmo que o webhook fosse
  implementado, nao haveria chave de correlacao.

### 4.3 CRITICO: notificacoes — nenhum gatilho por status

O escopo pede 10 gatilhos automaticos por mudanca de status. **Nenhum existe.**

O e-mail e enviado de forma **sincronamente** (bloqueia a resposta HTTP ate 15s)
apenas em dois lugares: comprovante de pedido e resposta de contato.
O resultado fica desnormalizado em `email_status`/`email_error` na linha do pai.
**Sem fila, sem retentativa, sem dead-letter.** Uma falha de SMTP perde o e-mail
para sempre — e como `contact_functions.php:43-56` bloqueia novos contatos do
mesmo e-mail ate a resposta, um e-mail perdido **prende o cliente indefinidamente**.

| Gatilho exigido | Existe? |
|---|---|
| Pedido criado (na hora) | ❌ |
| Lembrete Pix 30 min / 2 h antes | ❌ |
| Lembrete boleto 1 dia antes | ❌ |
| Pagamento aprovado | ❌ |
| Pagamento recusado/expirado | ❌ |
| Pedido cancelado (com motivo) | ❌ |
| Em preparacao / separacao | ❌ |
| Postado/enviado (com rastreio) | ❌ |
| Em transito (via webhook) | ❌ |
| Entregue (com pedido de avaliacao) | ❌ |
| Reembolso/estorno | ❌ |
| Boas-vindas no cadastro | ❌ |
| Recuperacao de senha | ⚠️ **parcial** — envia link, mas sem template |
| Resposta de contato | ✅ (o unico que existe) |
| Confirmacao de newsletter | ❌ |

### 4.4 ALTO: telas da loja

| Recurso | Situacao | Detalhe |
|---|---|---|
| Alterar quantidade | ⚠️ funciona | `cart/update.php` valida estoque ✅, **sem CSRF** |
| Remover item | ⚠️ funciona | sem CSRF |
| Salvar para depois | ❌ **nao existe** | |
| Cupom | ⚠️ **parcial** | `coupon_functions.php` valida tudo certo, mas o recalculo AJAX em `coupon.php:21-71` **confia no `$_SESSION`** e nao reconsulta o banco. Cupom expirado, desativado ou esgotado **continua valendo** por esse caminho. |
| Subtotal/frete/total AJAX | ⚠️ **parcial** | `cart.php` recalcula **so no cliente**. Nunca e autoritativo nem revalidado no checkout. |
| Carrinho para logado | ✅ | `e5_cart` com UNIQUE (user_id, product_id) |
| Checkbox de selecao | ❌ **decorativo** | As caixas existem (`cart.php:115,265`) mas o checkout compra **tudo** |
| Favoritar via AJAX | ⚠️ funciona | sem CSRF; **nao remove o card do DOM** ao desfavoritar; **nao marca como favorito no load** |
| Mover para o carrinho | ❌ **nao existe** | `product-card.php` nao tem botao de compra |
| Contador do header | ✅ | |
| Salvar dados pessoais | ✅ | CPF validado no servidor ✅ |
| Validar CPF | ✅ | servidor + mascara JS |
| **Mascara de telefone** | ❌ **nao existe** | |
| Trocar senha | ✅ | exige senha atual + `password_hash()` ✅ |
| **Adicionar endereco** | ❌ **nao existe** | sem tabela, sem UI |
| **Adicionar cartao** | ❌ **nao existe** | tabela existe, **nenhum insert** |
| **Preferencias de notificacao** | ❌ **nao existe** | sem coluna, sem form |
| **Upload de foto de perfil** | ❌ **nao existe** | sem coluna, sem handler |
| CEP com autocompletar (ViaCEP) | ⚠️ **so cliente** | sem validacao de CEP no servidor |
| Busca / filtros / ordenacao | ✅ | `products.php` funciona |
| **Paginacao** | ✅ | so na loja |
| Baixar comprovante | ✅ | `download-comprovante.php` valida dono-ou-admin ✅, PDF com dompdf ✅ |
| Reenviar comprovante | 🐛 **quebrado** | `auth/comprovante-resend.php:41` — `$emailSent` so e atribuido no caminho de sucesso e **lido no de falha** -> warning antes do `header()`. Mensagem nunca aparece. Sem rate limit. |

**Bug do CEP no checkout:** existem **dois** campos `shipping_cep` — o visivel
(`checkout.php:601`) e um hidden (`:804`). O PHP fica com o ultimo, e o ViaCEP JS
atualiza so o primeiro. Resultado: **trocar o CEP cotar frete do CEP anterior.**

### 4.5 ALTO: admin

O nucleo CRUD e solido: produto, categoria, embalagem, cupom e banner funcionam
com CSRF, prepared statements e escape consistentes.

| Recurso | Situacao |
|---|---|
| CRUD produtos + upload | ✅ funciona (valida **so extensao**, nunca MIME) |
| CRUD categorias / embalagens / cupons / banners | ✅ funciona |
| Gestao de pedidos + troca de status | ✅ a UI existe e funciona |
| **Emissao de etiqueta SuperFrete** | ❌ **nao existe** — zero mencao a `superfrete`/`etiqueta` em `pages/admin/` |
| **Envio de rastreio** | ❌ **nao existe** — a coluna existe, nenhum campo de escrita |
| Clientes (busca) | ✅ busca funciona |
| Clientes — botao "Exportar" | 🐛 **botao morto**, sem handler nenhum |
| Clientes — excluir | 🐛 **tela branca** se o cliente tiver pedidos (FK sem `ON DELETE`, excecao nao capturada) |
| Contatos e newsletter | ✅ funcionam; newsletter **exporta CSV de verdade** |
| **Relatorios — filtro de periodo** | ❌ **nao existe** — `reports.php` nao le `$_GET`. Só "sempre" vs "30 dias" |
| **Relatorios — exportar PDF** | 🐛 **botao morto** |
| **Relatorios — exportar CSV** | ❌ nao existe |
| **Configuracoes — SMTP** | ⚠️ **somente leitura** — exibe `$_ENV`, o texto admite que nao da para alterar |
| **Configuracoes — credenciais SuperFrete** | ❌ **nao existe** |
| Sino de notificacoes | ✅ **dados reais** (3 queries), mas conta contatos ja respondidos |
| **Paginacao** | ❌ **nao existe em nenhuma listagem do admin** — todas fazem `fetchAll()` |
| Busca | ⚠️ so em `customers.php` |
| Filtros | ⚠️ so `orders.php` e `contacts.php` (por status) |
| Ordenacao por coluna | ❌ **nenhum `<th>` e link de ordenacao** — todas sao fixas |

**Relatorios — bugs de calculo:** `$avgTicket` (`:10`) divide receita **sem**
cancelados por total **com** cancelados -> subestimado. `$topProducts` (`:24`) e
`categorySales` (`:35`) **nao** filtram cancelados, ao contrario de `:6` e
`:13` -> os KPIs da mesma pagina discordam entre si.

### 4.6 MEDIO: bugs de UI que quebram visualmente

| Onde | Bug |
|---|---|
| `admin/order-detail.php:147-149` | **tres `<td>` para uma coluna** quando ha imagem — HTML malformado, a tabela desalinha |
| `admin/newsletter.php:82` | `<a>` dentro de `<button>` — HTML invalido, o `onsubmit` e fragil |
| `admin/banners.php:167` | editando um banner, clicar em "Novo Banner" **esconde o form**. Com `?edit=999` inexistente, **nao ha volta** — estado sem saida |
| `admin/index.php:157` | link `products.php?action=add` **ignorado** pelo `products.php` (so trata `action=delete`) — cai na listagem |
| `admin/index.php:90` | card de estoque baixo tem `cursor:pointer` mas **nao e clicavel** |
| `footer.php` | icone com classe duplicada `fab fa fa-barcode` |
| `footer.php:24-26` | 5 links institucionais sao `#` (Trabalhe, Termos, Politica, Privacidade, FAQ) |
| `footer.php` | WhatsApp `wa.me/5511999999999` **falso** |
| 6 de 12 paginas admin | sem botao `.sidebar-toggle` -> **menu inacessivel no mobile** |
| `components/product-card.php:39-40` | **Pix 5% e frete gratis R$ 500 hardcoded**, ignorando o admin. `product-card.php` e `checkout.php` divergem |
| `includes/config.php:25-45` | **`pix_discount_percent` nao esta em `store_defaults()`** -> o admin grava mas **ninguem le**. O checkout sempre cai em 5% |
| `status_labels.php` | faltam `cartao` e `refunded`; o seed usa `payment_method='cartao'` e renderiza a string crua |
| `pages/products/products.php:34` | `sort=newest` **tambem filtra os ultimos 30 dias** — uma ordenacao que filtra |
| `admin/order-detail.php` | botoes `.ml-btn`/`.ml-btn-sm` nao existem em nenhum CSS |

---

## 5. O que ja funciona bem (nao quebrar)

Registrado para garantir que as mudancas sejam incrementais:

- **Todos os CRUDs do admin** (produto, categoria, embalagem, cupom, banner)
- **Comprovante PDF** com dompdf + download com checagem dono-ou-admin
- **Validacao de CPF** no servidor
- **Troca de senha** com verificacao da senha atual
- **Busca, filtros e paginacao da loja** (`products.php`)
- **Cupom — a regra** em `coupon_functions.php` (janela, minimo, limite de usos, publico)
- **CSRF** — token com `random_bytes(32)` e comparacao com `hash_equals()`,
  aplicado em **todos** os formularios HTML do admin
- **PHPMailer** — 100% via env, com diagnostico de falha em 4 categorias
  (`mail.php:74-93`) e `AltBody` em texto puro
- **Helper de webhook** — HMAC-SHA256 com `hash_equals()`, falha fechada,
  token mascarado nos logs
- **PRG** nos handlers do admin
- **Zero concatenacao de input em SQL** em todo o projeto (verificado)
- **Rate limit** em login, cadastro e contato

---

## 6. Bugs e vulnerabilidades por severidade

### CRITICO

| # | Achado | Onde | Acao |
|---|---|---|---|
| 1 | **`.env.tcc` com senha real de banco no historico do git**, alcancavel de `origin/main` e **todas as 14 branches** (commits `9fa6abe`, `45c7a9d`). O `.gitignore` cobre so `.env` e `.env.prod` — **nao** `.env.tcc`. | blob `79ec2d7f` | **Rotacionar a senha do banco** e purgar o historico (`git filter-repo` + force-push). Adicionar `.env*` ao `.gitignore`. |
| 2 | **Conta de `admin` com credencial padrao fraca, semeada em toda instalacao nova**, e `connection.php:29-38` roda o seed automaticamente. | `database.sql:243`, `connection.php:29-38` | Remover do seed; exigir troca de senha no 1o login; ou desligar o seed automatico. |
| 3 | **`scripts/demo.php` e acessivel pela web e nao exige autenticacao.** Instancia o client real e chama `createShipping()`, `checkout()` (gasta saldo), `createWebhook()`, `deleteWebhook()` e `cancelOrder()`. | `scripts/demo.php` | Bloquear `scripts/` no `.htaccess` e travar por `PHP_SAPI === 'cli'`. |

### ALTO

| # | Achado | Onde |
|---|---|---|
| 4 | **`storage/` nao bloqueado** — `storage/comprovantes/*.pdf` (nome, e-mail, endereco, valores) e `storage/logs/` com corpos de request da SuperFrete (CPF, endereco, telefone) sao **publicamente baixaveis**. `storage/` e `777`. | `.htaccess` |
| 5 | **A integracao SuperFrete nao persiste nada.** Sem tabela `envios`, sem `superfrete_order_id`, `tracking_code` nunca gravado, `processEvent()` e no-op -> **o status da transportadora nunca chega ao pedido**. | secao 4.2 |
| 6 | **Sem `pedido_status_historico`** — estado sobrescrito, sem autor, sem motivo, sem timestamp. | `database.sql` |
| 7 | **`e5_orders` descarta rua/numero/complemento.** O checkout coleta os 6 campos do ViaCEP (`checkout.php:180-187`) mas o INSERT (`:377`) grava **so 3**. O endereco do cliente **e perdido** — inviavel etiqueta e NF-e. | `checkout.php:377` vs `database.sql:92-95` |
| 8 | **Dados de Pix/boleto nunca persistidos** — gerados, mostrados uma vez e perdidos. Sem webhook de pagamento, o pedido e irreconciliavel. | `checkout.php:412-414` |
| 9 | **Open redirect no login** — `if (!str_starts_with($next, '/'))` aceita URL absoluta (`https://evil.com`). E descarta o `REQUEST_URI` legitimo, que sempre comeca com `/`. | `auth/authentication.php:69-70` |
| 10 | **Sem CSRF nos 5 endpoints JSON** que mudam estado: `cart/add`, `cart/update`, `cart/remove`, `cart/coupon`, `wishlist/toggle`. O cookie de sessao basta para forcar a requisicao. | secao 4.4 |

### MEDIO

| # | Achado | Onde |
|---|---|---|
| 11 | **8 de 17 tabelas sem `ENGINE=InnoDB`** — incluindo **`e5_orders` e `e5_cart`**. Em servidor com padrao MyISAM, o checkout perde transacao **sem erro**. `superfrete_webhook_log` usa collation diferente das outras. | `database.sql:29,41,70,79,103,113,125,173` |
| 12 | **Cancelamento de pedido sem transacao nem idempotencia** — devolve estoque e muda status em statements separados; cancelar em paralelo **devolve estoque duas vezes**. | `auth/order-detail.php:20-35` |
| 13 | **Sem PRG no checkout** — F5 apos confirmar cria pedido duplicado. | `checkout.php:337+` |
| 14 | **Idempotencia do webhook e TOCTOU** — `SELECT` e `INSERT` separados, sem transacao. Duas entregas simultaneas: uma toma o indice unico e estoura `PDOException` sem `try/catch` -> **HTTP 500** -> a SuperFrete retenta 5x. | `WebhookHandler.php:178-204` |
| 15 | **Sem `session_regenerate_id()`** em lugar nenhum — risco de fixacao de sessao no login. | `authentication.php:58-59` |
| 16 | **Tres violacoes de FK sem tratamento -> tela branca:** excluir produto vendido, excluir categoria com produtos, excluir cliente com pedidos. | `admin/products.php:15`, `admin/categories.php:38`, `admin/customers.php:11` |
| 17 | **`uploadErrorMessage()` nao existe** em `admin/settings.php:37` — `image_helpers.php` nao e carregado. Um upload truncado por `max_post_size` gera **erro fatal**. | `admin/settings.php:37` |
| 18 | **Precos de frete inventados no fallback** — se a SuperFrete cair, o cliente ve tabela fixa apresentada como real. | `checkout.php:153-165` |
| 19 | **Limite de frete gratis compara com o subtotal ANTES do desconto** — carrinho de R$600 com 30% off (R$420) ganha frete gratis, enquanto o banner anuncia "R$ 499". | `checkout.php:315` |
| 20 | **Webhook sem protecao contra replay** (sem timestamp/nonce na assinatura) e **sem limite de tamanho** em `php://input`. | `WebhookHandler.php:121,141` |
| 21 | **Rota do webhook divergente:** pasta e `webhook/` (singular), nao existe `webhooks/`, nao ha rewrite, e `.env.example:60` documenta a URL **sem `.php`** -> 404. | `.htaccess`, `.env.example:60` |
| 22 | **`ErrorDocument 500` aponta para a pagina 404** — falha de servidor vira HTTP 404, mascarando outage e confundindo o retry da SuperFrete. | `.htaccess:59` |
| 23 | **Paginacao inexistente no admin** — todas as listagens fazem `fetchAll()`. | `pages/admin/*` |
| 24 | **Indices faltando** em caminhos quentes: `e5_orders.status`, `.created_at`, `.tracking_code`, `e5_users.cpf`, `e5_coupons(active, valid_until)`, `e5_products.price`. So 5 indices explicitos em 17 tabelas. | `database.sql` |

### BAIXO / HIGIENE

| # | Achado |
|---|---|
| 25 | `loadEnv()` (`config.php:10-21`) nao remove aspas, nao trata `export`, e **sobrescreve `$_ENV` incondicionalmente** — quebra env injetado por container (`docker-compose.yml` usa `env_file` **e** o arquivo presente; o arquivo vence). |
| 26 | `vlucas/phpdotenv` declarado e nunca usado. |
| 27 | Hardcoded `/TCC_Etec/` nos `ErrorDocument` — quebra se o app mudar de pasta. |
| 28 | Sem CSP/HSTS; `X-XSS-Protection` obsoleto; sem `php_flag engine off` em `storage/`. |
| 29 | `docker-compose.yml`: phpMyAdmin e MySQL em `0.0.0.0`, `MYSQL_ALLOW_EMPTY_PASSWORD=yes`, container roda como root. |
| 30 | Testes: 62 metodos, **0% de integracao**. `request()`, os 12 endpoints, o SQL real do webhook, `mail.php`, `csrf.php`, `rate_limit.php` e o caminho de frete de producao **nao sao testados**. CI roda com `coverage: none`, sem auditoria de dependencias nem varredura de segredos. O README diz "32 testes" (atual: 62). |
| 31 | Upload valida **so extensao**, nunca conteudo (`getimagesize`/`finfo`). |
| 32 | Slug com `time()` (`product-form.php:64`, `categories.php:18,30`) — colisao de UNIQUE em criacoes no mesmo segundo. |
| 33 | `auth_check.php` confia so no `$_SESSION['user_role']` — admin rebaixado no banco mantem acesso ate a sessao expirar. |

---

## 7. Plano de implementacao

> Etapa 1 (redesign) cancelada. O tema atual e mantido.

### Etapa 1b — Correcoes criticas de seguranca (antes de qualquer feature)

Sem mudanca de comportamento visivel, entao e seguro fazer primeiro.

1. Trocar a senha do banco; purgar `.env.tcc` do historico; `.gitignore` -> `.env*`
2. Remover o admin semeado; exigir troca de senha no 1o acesso
3. Bloquear `scripts/` e `storage/` no `.htaccess`; travar `demo.php` por `PHP_SAPI`
4. Corrigir o open redirect (`authentication.php:69-70`)
5. CSRF nos 5 endpoints JSON (enviar o token em header)
6. `session_regenerate_id(true)` no login
7. `ENGINE=InnoDB` + collation uniforme nas 8 tabelas
8. `try/catch` nas 3 exclusoes com FK
9. `require_once image_helpers.php` no `settings.php`
10. Remover o fallback de frete inventado -> mensagem honesta de indisponibilidade
11. `ErrorDocument 500` para pagina de erro real

### Etapa 2 — Banco de dados

Migrations versionadas em `database/migrations/`, InnoDB, FKs, indices,
transacoes, e **cada uma com um script reversivel**.

| # | Migration | Conteudo |
|---|---|---|
| 1 | `..._add_innodb_and_collation` | normaliza motor e collation das 8 tabelas |
| 2 | `..._add_indexes` | `e5_orders(status, created_at)`, `tracking_code`, `e5_users(cpf)`, `e5_coupons(active, valid_until)`, `e5_products(price)` |
| 3 | `..._create_enderecos` | tabela de enderecos (rotulo, padrao, complemento texto livre) + migracao do endereco unico atual |
| 4 | `..._create_pedido_status_historico` | de/para, autor, motivo, origem (admin/webhook/sistema) |
| 5 | `..._create_envios` | superfrete_order_id, transportadora, servico, etiqueta, PDF, rastreio, volumes, status |
| 6 | `..._add_order_shipping_address` | `shipping_street`, `shipping_number`, `shipping_complement`, `shipping_recipient_name`, `shipping_recipient_document`, `shipping_phone` |
| 7 | `..._add_order_payment_data` | `pix_code`, `pix_qrcode`, `pix_txid`, `pix_expires_at`, `boleto_url`, `boleto_due_date`, `paid_at`, `cancelled_at`, `cancel_reason` |
| 8 | `..._create_notificacoes` | fila: evento, pedido, destinatario, canal, status, tentativas, erro, enviado_em, payload |
| 9 | `..._create_notificacoes_log` | historico de cada tentativa |
| 10 | `..._add_user_preferences` | preferencias de notificacao + avatar |
| 11 | `..._extend_webhook_log` | colunas `payload`, `signature`, `source_ip`, `http_status`, `processed`, `error` |
| 12 | `..._fix_pix_discount_config` | adiciona `pix_discount_percent` a `store_defaults()` (hoje a config e inoperante) |

### Etapa 3 — Telas da loja (uma por vez, testavel)

Ordem: **carrinho -> favoritos -> perfil -> checkout -> pagamento -> detalhes do pedido -> admin**

- **Carrinho** — recalculo autoritativo via AJAX, selecao de itens real,
  "salvar para depois", cupom revalidado no servidor, CSRF
- **Favoritos** — estado ativo no load, remocao do DOM, "mover para o carrinho",
  contador AJAX
- **Perfil** — CRUD de enderecos, preferencias gravadas, cartoes
  (bandeira + 4 ultimos + token fake, **nunca o numero**), upload de avatar,
  mascara de telefone, validacao de CEP no servidor
- **Checkout** — PRG + chave de idempotencia, endereco completo persistido,
  transacao com baixa de estoque, escolha de entrega (SuperFrete ou retirada),
  validacao de CPF do titular
- **Pagamento** — **criar `pages/cart/payment.php`**: Pix com QR (reaproveitando
  `qrcode.min.js`) e copia-e-cola, expiracao real do banco, polling, simulador
  (aprovar/erro/expirar) que muda o status de verdade, "pagar depois", cancelar
- **Detalhes do pedido** — timeline real (via status historico), link de
  rastreio, comprovante PDF, reenvio funcionando
- **Admin** — emissao de etiqueta e envio de rastreio, filtro de periodo +
  CSV/PDF nos relatorios, configuracao de SMTP e SuperFrete, paginacao/busca/
  ordenacao em todas as listagens, corrigir os 3 botoes mortos

### Etapa 4 — SuperFrete

- `src/SuperFrete/` — manter `SuperFreteClient` (transporte) e **criar a camada
  de servico** com as regras: escolha de embalagem, soma de volumes, filtro de
  servico, regra de frete gratis
- Cache curto de cotacao (chave = hash de CEP + impressao dos itens),
  gravando no `storage/cache/shipping/` ja existente
- Fluxo de etiqueta: **criar -> pagar -> imprimir**, persistindo
  `superfrete_order_id`, URL do PDF e codigo de rastreio
- Cancelamento de etiqueta
- Rastreamento (endpoint novo, confirmar na documentacao)
- Webhook: mapeamento `pending|released|posted|delivered|canceled` ->
  status interno, **idempotente** (`INSERT ... ON DUPLICATE KEY UPDATE`),
  log com payload bruto, resposta em <5s
- Retry com backoff, timeout de conexao e leitura separados, log em tabela
- **Fallback honesto**: se a API cair, o checkout **nao trava** e o cliente ve
  "calculando..." com opcao de calcular sob demanda — nunca preco inventado

### Etapa 5 — Notificacoes

- `src/Notificacoes/` — templates HTML com tabelas (Gmail/Outlook) + versao
  texto puro, header amarelo com a logo, resumo do pedido, **um** botao azul,
  rodape com contato e descadastro
- Fila em `notificacoes`; envio pela CLI (cron), nao dentro do request
- Respeitar as preferencias do perfil
- **Nunca disparar duas vezes o mesmo evento para o mesmo pedido** —
  `UNIQUE (order_id, event)` na fila resolve no banco
- Cron: `scripts/cron-notificacoes.php` — lembretes de Pix (30 min e 2 h antes),
  boleto (1 dia antes), expiracao, e avanco de status

### Etapa 6 — Testes e documentacao

- Testes de integracao para os caminhos criticos (calculo de frete, regra de
  frete gratis, transacao do checkout, mapeamento de status do webhook)
- `scripts/demo.php` -> virar seed de cenario completo
- README de instalacao do zero

---

## 8. Decisoes de projeto

| Tema | Decisao | Motivo |
|---|---|---|
| Tema visual | **Manter o atual** | Etapa 1 cancelada |
| Pagamento | **Simulador** (aprovado/erro/expirado) | E TCC; nao ha gateway real contratado. O simulador muda o status **de verdade** no banco |
| Pix | **BR Code gerado e persistido** | Sem dependencia externa, o QR e real e funciona |
| E-mail | **Fila + cron** | Nao bloquear a resposta HTTP; permite retentativa |
| Frete | **SuperFrete real, com cache** | Escopo exige; sandbox disponivel |
| Etiqueta | **Manual pelo admin** | `/checkout` da SuperFrete exige saldo pre-pago na conta |
| Rastreio | **Via webhook + consulta sob demanda** | Webhook e push e nao depende do navegador aberto |
| Segredos | **So em `.env` / `e5_settings`** | Nada hardcoded |
| Cartao salvo | **Bandeira + 4 ultimos + token fake** | **Nunca** o numero completo — escopo e boa pratica |

---

## 9. Pendencias que exigem decisao do orientador

1. **Senha do banco exposta no git.** O blob `.env.tcc` esta em todas as
   branches. A rotacao da senha depende do acesso ao servidor do orientador —
   **nao da para fazer daqui**.
2. **`/checkout` da SuperFrete exige saldo pre-pago na conta.** Se nao houver
   saldo no sandbox, a emissao de etiqueta nao pode ser testada de ponta a
   ponta. Confirmar se ha saldo ou se usamos apenas o sandbox de calculo.
3. **Gateway de pagamento real.** O escopo foi aprovado com **simulador**.
   Se a banca exigir gateway real, e preciso contratar (Mercado Pago, PagSeguro)
   — muda o escopo da Etapa 3.
4. **Volume de notificacoes.** A fila + cron assume acesso a um agendador
   (cron do sistema, ou Task Scheduler no Windows). Em Windows/XAMPP o
   agendamento precisa ser configurado manualmente.
5. **Endereco de destino do webhook.** Precisa de HTTPS publico. Em
   desenvolvimento local, o webhook so pode ser testado com um tunel
   (ngrok, cloudflared) ou chamando o endpoint por `curl`.

---

## 10. Resumo executivo para a banca

O projeto tem **base solida**: CRUDs do admin functioning, prepared statements
em 100% das queries, CSRF com `hash_equals()`, comprovante PDF com dompdf,
validacao de CPF e senha no servidor, e 62 testes automatizados passando.

Porem, **tres areas do escopo praticamente nao existem**:

1. **Pagamento** — a pagina de pagamento nunca foi criada. O checkout "finaliza"
   a compra, mas nao ha como pagar, nem QR, nem expiracao, nem polling.
2. **SuperFrete** — existe um client HTTP completo e testado, mas a aplicacao
   so chama a cotacao. Nao ha emissao de etiqueta, nem cancelamento, nem
   rastreamento, e o webhook nao muda nada no banco porque **nao existe tabela
   `envios`**.
3. **Notificacoes** — nenhum dos 10 gatilhos por status existe. O e-mail e
   enviado de forma sincrona, sem fila e sem retentativa.

Somando a isso, ha **3 vulnerabilidades criticas** (segredo de banco no
historico do git, admin com senha padrao em toda instalacao, e um script que
executa a API da transportadora sem autenticacao) e **10 de alta severidade**
(incluindo `storage/` expondo comprovantes com dados pessoais e um open redirect
no login).

O plano proposedo comeca por corrigir as vulnerabilidades (Etapa 1b), depois
migra o banco (Etapa 2), depois implementa as telas na ordem do fluxo de
compra (Etapa 3), e so entao conecta SuperFrete e notificacoes (Etapas 4 e 5),
que dependem das tabelas criadas na Etapa 2.

---

## 11. Registro de execucao

### ETAPA 1b - Lote 1: exposicao de arquivos, 500 e conexao com o banco

Commit `cf77855` (branch `FIx/perfil`).

**1. Diretorios sensiveis estavam abertos (critico).** O `.htaccess` bloqueava
com `RedirectMatch 403 ^/includes/.*$`. Esse padrao esta ancorado na raiz do
servidor, mas o app roda no subdiretorio `/TCC_Etec` — logo ele comparava
`^/includes/` contra `/TCC_Etec/includes/` e nunca casava. As regras eram
codigo morto: `database/database.sql` (schema completo e hashes de senha),
`includes/`, `vendor/` e `storage/` seguiam baixaveis. Trocado por `(^|/)`,
que casa em qualquer nivel, e cada diretorio ganhou o proprio `.htaccess` com
`Require all denied` (defesa em profundidade, vale inclusive se o `.htaccess`
da raiz deixar de ser lido).

Verificado no Apache local, os 12 caminhos passaram a responder 403:
`database/database.sql`, `database/connection.php`, `includes/config.php`,
`includes/csrf.php`, `storage/.htaccess`, `storage/comprovantes/`,
`scripts/demo.php`, `vendor/autoload.php`, `tests/`, `.env`, `.env.tcc`,
`.gitignore`.

**2. `scripts/demo.php` executava a SuperFrete real pelo navegador.** O script
cria pedido, gera etiqueta, consulta e cancela na API. Aberto no navegador,
executava tudo a cada F5. Agora exige `PHP_SAPI === 'cli'`, com a guarda antes
de qualquer `require` para o 403 sair mesmo se o autoload falhar.

**3. `ErrorDocument 500` apontava para a propria 404.** Criado `pages/500.php`,
autocontido de proposito (nao usa `header.php`, `footer.php`, `session_start()`
nem `includes/`) porque precisa renderizar justamente quando a falha veio de um
desses arquivos. Detalhe tecnico so aparece com `APP_DEBUG` ligado; em
producao mostra orientacao e contato, sem stack trace nem caminho absoluto.

**4. `.gitignore` nao cobria `.env.tcc`.** Listava apenas `.env` e `.env.prod`,
o que deixou o arquivo de credenciais reais ser commitado. Agora usa `.env.*`
com excecao para `.env.example`. Detalhe: o `storage/.htaccess` precisou sair
da excecao de `storage/*`, senao a regra de bloqueio nunca seria versionada.

**5. `database/connection.php` ignorava `DB_PORT`.** O DSN ficava sempre na
3306, entao apontar o `.env` para um MySQL em outra porta dava
`SQLSTATE[HY000] [2002] Connection refused` e pagina em branco — o `.env`
dizia `localhost` e o servidor real estava em `127.0.0.1:3307`. A porta so e
acrescentada ao DSN quando `DB_PORT` existe, preservando o comportamento por
socket unix quando `DB_HOST` e `localhost`.

Resultado: as paginas publicas voltaram a responder 200 (home 200 com 45 KB de
conteudo real, listagem, detalhe de produto, login e cadastro), e o PHPUnit
segue verde com 62 testes e 289 assertions.

### Pendencias criticas que NAO foram resolvidas aqui

These exigem decisao do owner do repositorio, nao sao correcao de codigo:

- **`.env.tcc` continua no historico do GitHub.** Confirmado nos commits
  `9fa6abe` e `45c7a9d` (2026-08-18), alcancaveis de **todas as 14 branches**,
  incluindo todas as `origin/*`. O arquivo nao existe mais no HEAD, entao o
  `.gitignore` impede recorrencia — mas quem clonar ainda recebe a credencial.
  Resolver exige, nesta ordem: (1) **rotacionar a credencial exposta**,
  (2) reescrever o historico (`git filter-repo`/BFG), (3) force-push
  coordenado. O passo 1 e o unico que realmente fecha o problema; os outros
  sao higiene. Force-push em repo compartilhado precisa de aval.
- **Conta admin com senha padrao** em `database/database.sql:243`, executada
  automaticamente por `connection.php` a cada instalacao nova.
- **Este arquivo foi higienizado antes de ser commitado:** a senha do seed e
  os e-mails dos usuarios de teste nao sao mais reproduzidos aqui, por irem
  parar em repositorio publico. Os valores continuam no `database.sql` e no
  historico do Git — a correcao e no seed, nao no documento.

### Ordem restante

Lote 2 da 1b (login/sessao, CSRF nos endpoints JSON, FKs, frete falso, seed
admin), depois Etapa 2 (migrations) e as demais conforme a secao 7.

### ETAPA 1b - Lote 2: sessao, CSRF e o que a verificacao revelou

Commit `83b0d1d`. Detalhes na secao 11. Resumo do que foi corrigido:
open redirect invertido no login, ausencia de rotacao de sessao, cookie
de sessao sem `httponly`/`SameSite`, e os cinco endpoints de carrinho e
wishlist sem CSRF nem checagem de metodo.

Para proteger os endpoints comecou a ser necessario **expor o token ao
JavaScript** (`<meta name="csrf-token">`), porque eles sao chamados por
`fetch()` e nao por formulario — nao havia campo hidden para ler. O token
agora e aceito pelo cabecalho `X-CSRF-Token`, pelo campo `_csrf_token` do
POST e pelo corpo JSON.

### ACHADO CRITICO: o schema nao tem fonte unica de verdade

A verificacao no navegador do lote 2 caiu sobre uma falha que **nao e do
lote 2**: `cart.php`, `checkout.php` e `admin/package-sizes.php`
respondem 500 para qualquer usuario autenticado, com
`Unknown column 'p.package_size_id'`.

O banco de desenvolvimento e o `database/database.sql` divergem **nos dois
sentidos**. O schema declara coisas que o banco nao tem:

| Tabela | Colunas que o codigo usa e o banco nao tem |
|---|---|
| `e5_contacts` | `user_id`, `status`, `response_message`, `responded_by`, `responded_at`, `response_email_status`, `response_email_error`, `updated_at` |
| `e5_products` | `package_size_id`, `weight_kg`, `height_cm`, `width_cm`, `length_cm` |
| `e5_users` | `cpf` |
| `superfrete_webhook_log` | **tabela inteira ausente** (7 colunas) |

**21 colunas e 1 tabela** ausentes — contagem conferida com `SHOW COLUMNS`,
nao por diff de texto.

E o banco tem coisas que o schema nao declara:

| Objeto | Quem usa no codigo |
|---|---|
| `e5_users.avatar_path`, `e5_users.notify_email`, `e5_users.notify_whatsapp` | **nada** |
| `e5_orders.payment_details`, `e5_orders.payment_expires_at` | **nada** |
| `e5_user_addresses` (tabela inteira) | **nada** |

> **Correcao de registro.** A versao anterior deste documento afirmava que
> `e5_users.status` era consultada pelo codigo sem existir em `database.sql`
> nem no banco. **Isso era falso** — veio de uma consulta ad hoc, nao de
> leitura de codigo. `grep -ri "status"` nao encontra `e5_users.status` em
> nenhum arquivo PHP. A coluna nao existe em lugar nenhum e nao e requisito
> de nada.

> **Correcao de registro 2.** `e5_coupons.customer_scope` aparecia aqui como
> "so no banco". Errado: a coluna esta declarada no `database.sql` e apareceu
> no banco quando a tabela foi recriada a partir dele. Nao e divergencia.

Os 6 objetos da tabela acima sao lixo legado do banco de desenvolvimento,
nao requisito de codigo. Ficaram registrados e **nao foram removidos** —
drop em coluna/tabela pode destruir dado, entao essa decisao e do owner.

Isso e a causa-raiz por tras de varias "features prontas" da etapa 3.
**Nao da para continuar a etapa 3 antes de reconciliar isso** — construir
telas sobre um schema instavel produz codigo que passa no happy path e
quebra em producao.

O que ja foi feito no ambiente local, de forma aditiva e sem apagar
dado: criadas `e5_package_sizes`, `e5_saved_cards` e `e5_coupons` a partir
do proprio `database.sql` (as tres faltavam inteiras e sao referenciadas
por 4, 3 e 1 arquivos respectivamente).

Estado das paginas com usuario autenticado na auditoria: 200 em home,
listagem e detalhe de produto, favoritos, perfil, meus pedidos e o painel
admin (index, produtos, pedidos, cupons); 500 nas 6 paginas listadas
acima.

---

## RESOLVIDO — Lote 1b / Etapa 2: o schema passou a ter fonte unica de verdade

### O que era o problema

`database/migrations/` tinha 2 arquivos `.sql` e **nenhum registro do que
ja tinha sido aplicado**. Ninguem sabia que `20260915_add_contact_replies.sql`
nunca rodou, e o banco ficou 21 colunas atrasado em relacao ao codigo. O
sintoma era `Unknown column 'p.package_size_id'` em 6 paginas.

O modo de descobrir o que faltava era: remembering um `SHOW COLUMNS` na mão
e comparar de memoria com o SQL. Nao escala e ja produziu um erro de
documentacao (ver correcoes acima).

### O que foi feito

**1. Runner com registro — `bin/migrate.php`**

```
php bin/migrate.php status          # o que ja foi aplicado e o que falta
php bin/migrate.php up              # aplica as pendentes, em ordem
php bin/migrate.php down            # reverte a ultima aplicada
php bin/migrate.php down <arquivo>  # reverte uma especifica
```

Cria e mantem `e5_schema_migrations (filename, checksum, applied_at)`.
Decisoes deliberadas:

- **Recusa `down` sem `.down.sql`.** Uma migration que nao sabe se desfazer
  e recusada em vez de fingir que reverteu. Isso e o que impede a etapa 2
  de accumulating mudancas irreversiveis.
- **Guarda checksum.** Se um arquivo ja aplicado mudar no disco, o `status`
  marca `<-- ARQUIVO MUDOU DEPOIS DE APLICADO` — evita a situacao classica
  de migration editada depois de aplicada em outros ambientes.
- **Modo `-- @raw`.** Migrations com `CREATE PROCEDURE` sao enviadas em um
  unico `exec()`, porque o `BEGIN...END` contem `;` que o splitter trataria
  como fim de comando.
- **Sem transaction no `up`.** MySQL nao tem DDL transacional: todo
  `CREATE`/`ALTER` faz commit implicito. O runner diz isso explicitamente
  quando algo falha, em vez de sugerir rollback automatico que nao existe.

**2. Migration `20261001_reconcile_schema_drift.sql` (+ `.down.sql`)**

Cobre a divergencia real: 5 colunas de medida em `e5_products` + a FK
`fk_products_package_size`, `cpf` em `e5_users` e a tabela
`superfrete_webhook_log`.

E **idempotente**: MySQL 8.0.46 nao tem `ADD COLUMN IF NOT EXISTS` nem
`DROP COLUMN IF EXISTS` (verificado nesta versao, nao assumido), entao cada
comando passa por um stored procedure que consulta `information_schema`
antes de executar. Consequencia pratica: rodar duas vezes nao faz nada e
nao falha, e a migration e segura tambem em instalacao nova, onde
`database.sql` ja criou as colunas.

**3. Reversoes para as 2 migrations legadas**

`20260915_add_contact_replies.down.sql` e
`20260921_add_contacts_email_status_index.down.sql` — sem eles, o `down`
das duas ficaria bloqueado para sempre.

### Como foi verificado

Nao bastava o `up` passar. O ciclo completo foi exercitado:

| passo | resultado |
|---|---|
| `up` (3 migrations) | 3 aplicadas |
| `up` de novo | `Nada pendente` — idempotencia confirmada |
| as 6 paginas quebradas, autenticado | **6/6 em 200** (eram 500) |
| `down 20261001` | removeu as 5 colunas, `cpf` e a tabela do webhook |
| `cart.php` com schema revertido | **500** — confirma que a migration era a causa, e nao coincidencia |
| `up` de novo | restaurado, `cart.php` **200** |
| `down` de migration sem `.down.sql` | recusado com aviso, exit 1 |
| `phpunit` | **OK (62 tests, 289 assertions)** |

### Divergencias deliberadamente NAO resolvidas

`e5_users.{avatar_path,notify_email,notify_whatsapp}`,
`e5_orders.{payment_details,payment_expires_at}` e a tabela
`e5_user_addresses` existem no banco de desenvolvimento e **nao sao
referenciadas por nenhum arquivo do repositorio**. Ficaram como estao:
sao residuo, nao requisito. Nao vao para `database.sql` (aumentaria uma
tabela que ninguem le) e nao vao para um `down` (destruiria dado sem
pedido). Decisao do owner — ver checklist de pendencias.

### Estado das paginas

Com usuario autenticado, apos a migration: **200** em
`admin/contact-detail.php`, `admin/contacts.php`, `auth/contacts.php`,
`admin/package-sizes.php`, `cart/cart.php` e `cart/checkout.php`.
Nenhuma pagina em 500 por divergencia de schema.
