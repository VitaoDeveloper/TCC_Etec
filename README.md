# Royal Tech

E-commerce premium desenvolvido como Trabalho de Conclusão de Curso (TCC) da ETEC. Loja virtual de tecnologia com identidade visual sofisticada (preto, dourado e branco) e painel administrativo completo.

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 8+ (vanilla) |
| Banco de Dados | MySQL 8 |
| Frontend | HTML5, CSS3, JavaScript vanilla |
| Ícones | Font Awesome 6.4 |
| Tipografia | Playfair Display + Rajdhani |
| Dependências | PHPMailer, DomPDF |

## Funcionalidades

**Loja:** catálogo responsivo, busca, filtros, paginação, carrinho, checkout, lista de desejos, perfil do usuário, histórico de pedidos, recuperação de senha, newsletter e contato.

**Admin:** dashboard com métricas, CRUD de produtos/categorias/banners, gerenciamento de pedidos/clientes, relatórios, newsletter e configurações do sistema.

## Upload e exibição de imagens

- As imagens enviadas pela área administrativa são salvas fisicamente em `assets/img/products/` (produtos) e `assets/img/banners/` (banners), e o caminho correspondente é gravado no banco (`e5_product_images.image_path` / `e5_banners.image_path`).
- Na tela de produto/banner existem **dois campos de imagem**:
  1. **Caminho da imagem** (campo manual) — usado quando nenhum arquivo é enviado no upload.
  2. **Upload de imagem** — ao enviar um arquivo, ele **substitui** o caminho manual.
- O formulário **edita sem enviar imagem sem quebrar**: a imagem/caminho já existente é preservado.
- Formatos aceitos: JPG, JPEG, PNG e WEBP (máx. 2 MB).
- Os diretórios de upload são criados automaticamente, mas a pasta `assets/img` precisa ter permissão de escrita para o usuário do servidor web (ex.: `daemon` no XAMPP/Apache).
- A exibição usa fallback para uma imagem padrão (`assets/img/placeholder-product.svg`) quando o arquivo não existe em disco — evitando ícone de imagem quebrada. Helpers em [`includes/image_helpers.php`](includes/image_helpers.php) (`renderProductImage`, `imageAvailable`, `uploadErrorMessage`).

## Configuração

### Variáveis de Ambiente
Antes de executar é importante definir como as variáveis de ambiente (arquivos .env e .env.prod) serão configuradas

#### Rodando com XAMPP (criar arquivo .env)
```env
# Database credentials
DB_HOST=localhost
DB_NAME=e5_royaltech
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# SMTP (Mail server) credentials
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
# Opcional: remetente padrao. Se vazio, usa store_email da configuracao da loja.
MAIL_FROM=
```

#### Rodando com Docker (criar arquivo .env.prod)
```env
# Database credentials
DB_HOST=db
DB_NAME=e5_royaltech
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# SMTP (Mail server) credentials
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
# Opcional: remetente padrao. Se vazio, usa store_email da configuracao da loja.
MAIL_FROM=
```
O serviço para teste de envio de emails via servidor SMTP utilizado é o Mailpit. Caso você esteja rodando com o XAMPP, será necessário instalar o Mailpit localmente e incializar o seu processo, para que ele possa ser acessado com http://localhost:1025 (para uso do serivço pela aplicação) e http://localhost:8025 (para visualização em interface dos emails enviados). <br><br>
Se estiver rodando com Docker, o Mailpit já vem empacotado junto do compose, sem precisar instalar nada.

## Executando

### Docker (recomendado)

```bash
git clone https://github.com/seu-usuario/TCC_Etec.git
cd TCC_Etec
cp .env.example .env.prod
docker compose up -d
```

| Serviço | URL |
|---------|-----|
| Loja | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |
| Mailpit | http://localhost:8025 |

### XAMPP

```bash
git clone https://github.com/seu-usuario/TCC_Etec.git
cd TCC_Etec
mysql -u root -e "CREATE DATABASE e5_royaltech"
mysql -u root e5_royaltech < database/database.sql
cp .env.example .env
# Edite .env com suas credenciais
```

Acesse `http://localhost/TCC_Etec`.

### Credenciais padrão

- **Admin:** `admin` / `admin123`

## Segurança

- CSRF token em formulários POST
- Rate limiting no login (5 tentativas / 15 min por IP)
- Prepared statements (MySQLi)
- Sanitização de saída (`htmlspecialchars`)
- Senhas com `password_hash()` / `password_verify()`
- Credenciais em `.env` (excluído do Git)

## Banco de Dados

`e5_royaltech` — 11 tabelas: `e5_users`, `e5_categories`, `e5_products`, `e5_product_images`, `e5_cart`, `e5_orders`, `e5_order_items`, `e5_contacts`, `e5_newsletter`, `e5_password_reset_tokens`, `e5_banners`.

Schema completo em [`database/database.sql`](database/database.sql).

## Estrutura

```
├── assets/
│   ├── css/          # estilos
│   ├── img/          # imagens
│   └── js/           # scripts
├── components/       # header, footer, product-card
├── database/
│   ├── connection.php
│   └── database.sql  # schema completo
├── includes/         # config, csrf, mail, rate_limit, helpers
├── pages/
│   ├── admin/        # dashboard, CRUDs, relatórios
│   ├── auth/         # login, registro, perfil, pedidos
│   ├── cart/         # carrinho e checkout
│   ├── products/     # vitrine, categorias, contato
│   └── wishlist/     # lista de desejos
├── .env.example      # modelo de variáveis de ambiente
├── docker-compose.yml
├── Dockerfile
└── index.php
```

## Licença

Projeto educacional — TCC ETEC.

---

# Integração SuperFrete (API v0)

Integração completa com a API [SuperFrete](https://superfrete.readme.io/) em PHP vanilla, cobrindo cotação, criação de frete, checkout, consulta, impressão de etiqueta, listagem, cancelamento e CRUD de webhooks + receptor seguro com validação HMAC e idempotência.

## Stack da integração

| Dependência | Uso |
|-------------|-----|
| `guzzlehttp/guzzle` | Todas as requisições HTTP |
| `phpmailer/phpmailer` | Envio de e-mail (SMTP) |
| `dompdf/dompdf` | Geração de comprovantes em PDF |
| `phpunit/phpunit` | Testes automatizados (dev) |

## Arquivos entregáveis

```
src/
  SuperFreteClient.php          # Client principal com todos os endpoints
  Exception/SuperFreteException.php
  Webhook/WebhookHandler.php    # Receptor + validação HMAC + idempotência
sql/
  superfrete_webhook_log.sql    # Tabela de idempotência de webhooks
webhook/
  superfrete.php                # Endpoint público receptor de webhooks
tests/
  unit/                         # Testes unitários (utils + HMAC + idempotência)
  fixture-data/                 # Validação das fixtures reais
  fixtures/superfrete/          # JSONs reais de sucesso e erro (sandbox)
composer.json
.env.example
README.md
```

## Configuração (.env)

```env
SUPERFRETE_TOKEN=SEU_TOKEN_AQUI
SUPERFRETE_BASE_URL=https://sandbox.superfrete.com/   # produção: https://api.superfrete.com/
SUPERFRETE_USER_AGENT=RoyalTech 1.0 (integracao@superfrete.com)
SUPERFRETE_WEBHOOK_SECRET=                              # devolvido ao criar webhook (POST /webhook)
SUPERFRETE_WEBHOOK_URL=https://seudominio.com/webhook/superfrete
```

Trocar sandbox ↔ produção: basta alterar `SUPERFRETE_BASE_URL` no `.env`. O token é diferente por ambiente.

## Uso básico

```php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/config.php';
loadEnv(__DIR__ . '/../.env');

use TCC\SuperFreteClient;

$client = new SuperFreteClient([
    'SUPERFRETE_TOKEN'     => $_ENV['SUPERFRETE_TOKEN'],
    'SUPERFRETE_BASE_URL'  => $_ENV['SUPERFRETE_BASE_URL'],
    'SUPERFRETE_USER_AGENT'=> $_ENV['SUPERFRETE_USER_AGENT'],
]);
```

### 1) Health Check (valida token + User-Agent)

```php
$orders = $client->healthCheck(); // GET /me/orders?page=1&per_page=1
```

### 2) Cotação de frete (`POST /calculator`)

> **No e-commerce:** o checkout (`pages/cart/checkout.php`) já consome a cotação real desta API
> (`SUPERFRETE_ORIGIN_POSTAL_CODE` no `.env` = CEP de origem da loja), com fallback para a
> regra local simulada caso a API esteja indisponível ou sem token.
>
> **Embalagem por produto:** cada produto pode ter uma embalagem pré-definida (painel admin →
> *Embalagens*, tabela `e5_package_sizes`) ou medidas personalizadas (altura/largura/comprimento
> e peso). O checkout usa esses dados na cotação; produtos sem cadastro caem para a caixa
> padrão (`15×10×20 cm / 0,5 kg`). Se a API falhar, é exibida uma mensagem amigável ao cliente
> informando que o serviço está temporariamente indisponível e mostrando um valor estimado.

```php
$quote = $client->calculateShipping([
    'from'      => ['postal_code' => '01310100'],
    'to'        => ['postal_code' => '20020050'],
    'services'  => '1,2,17',            // 1=PAC, 2=SEDEX, 17=Mini Envios, 3=Jadlog, 33=J&T
    'options'   => [
        'own_hand' => false, 'receipt' => false,
        'insurance_value' => 0, 'use_insurance_value' => false,
    ],
    'products'  => [
        ['quantity' => 1, 'height' => 15, 'width' => 10, 'length' => 20, 'weight' => 0.5],
    ],
]);
// O campo packages[].dimensions de cada opção devolve a CAIXA IDEAL:
// use height/width/length/weight dela no /cart, NUNCA as dimensões dos produtos.
```

### 3) Criar frete (`POST /cart`)

```php
$order = $client->createShipping([
    'from' => [
        'name'       => 'Royal Tech',                        // nome + sobrenome (loja 1 palavra vira "Loja Nome")
        'address'    => 'Av Paulista', 'number' => '1000',
        'district'   => 'Bela Vista', 'city' => 'Sao Paulo',
        'state_abbr' => 'SP', 'postal_code' => '01310100',
        'document'   => '49698132805',
    ],
    'to' => [
        'name'       => 'Cliente Teste',                     // nome + sobrenome
        'address'    => 'Rua B', 'number' => '2',
        'district'   => 'Centro', 'city' => 'Rio de Janeiro',
        'state_abbr' => 'RJ', 'postal_code' => '20020050',
        'email'      => 'cliente@teste.com.br',
        'phone'      => '11999999999',                       // exatamente 11 dígitos sem máscara (J&T exige)
        'document'   => '11144477735',                       // CPF/CNPJ obrigatório
    ],
    'service'  => 2,
    'products' => [
        ['name' => 'Notebook Gamer', 'quantity' => 1, 'unitary_value' => 350.00],
        ['name' => 'Mouse Gamer',    'quantity' => 2, 'unitary_value' => 45.50],
    ],
    'volumes'  => ['height' => 15, 'width' => 16, 'length' => 24, 'weight' => 1.1], // caixa ideal do /calculator
    'options'  => ['insurance_value' => 441.00, 'receipt' => false, 'own_hand' => false],
    'platform' => 'RoyalTech',
]);
// $order['id'] = 'vnyoREXJ0HuG4O3bzdJg', status 'pending'
```

### 4) Checkout (exige saldo na carteira)

```php
$result = $client->checkout(['orders' => [$orderId]]);
// ⚠️ Requer saldo. Sem saldo, a API retorna HTTP 409:
//   "Sem saldo na carteira! Utilize o app para recarregar a carteira ou pagar a etiqueta com cartão de crédito."
```

### 5) Informações do pedido / Impressão / Listagem / Cancelamento

```php
$info  = $client->getOrderInfo($orderId);                 // GET /order/info/{id}
$print = $client->getPrintLink(['orders' => [$orderId]]); // POST /tag/print → ['url' => '...pdf']
$list  = $client->listOrders(['status' => 'pending', 'page' => 1, 'per_page' => 20]);
$cancel= $client->cancelOrder($orderId, 'Cancelado pelo cliente'); // só antes de postar
```

Filtros de `listOrders()`: `status` (pending;released;posted;delivered;canceled), `page`, `per_page`, `order` (asc|desc), `sort_by` (created_at|updated_at).

### 6) Webhooks (CRUD completo)

```php
$wh   = $client->createWebhook('TCC RoyalTech', 'https://seudominio.com/webhook/superfrete', ['order.created']);
$whId = $wh['id'];
$secret = $wh['secret_token'];            // guarde no .env: SUPERFRETE_WEBHOOK_SECRET

$client->listWebhooks();                                   // GET /webhook
$client->updateWebhook($whId, ['name' => 'Novo nome']);    // PUT /webhook/{id} (parcial)
$client->deleteWebhook($whId);                             // DELETE /webhook/{id}
```

Eventos: `order.created`, `order.released`, `order.generated`, `order.posted`, `order.delivered`, `order.cancelled`.

### 7) Receptor de webhooks (`webhook/superfrete.php`)

O endpoint público lê o `php://input`, valida a assinatura `X-ME-Signature` (HMAC-SHA256 com o `secret_token`), checa idempotência na tabela `superfrete_webhook_log` e responde rápido (timeout SuperFrete = 30s; retries a cada 15 min até 5x).

A tabela guarda **somente** o `payload_hash` (SHA-256 do body JSON recebido) — nunca o body em claro nem dados pessoais do destinatário (LGPD — minimização, Art. 46). O `event_id` é `UNIQUE`: se já existir, o receptor responde `200` (duplicate) sem reprocessar, mesmo sob eventos concorrentes.

Criar a tabela antes:

```bash
mysql -u root e5_royaltech < database/database.sql
```

Para testar localmente, exponha a URL com um túnel (ex.: `ngrok http 80`) e cadastre-a via `createWebhook()`.

## Regras críticas

1. **Nome (remetente/destinatário):** precisa de nome + sobrenome. Loja com 1 palavra → prefixar `"Loja "`. Helper: `SuperFreteClient::ensureFullName()`.
2. **Telefone:** exatamente 11 dígitos, sem máscara (obrigatório J&T). `normalizePhone()`.
3. **CEP:** aceita com ou sem hífen; padronizar para 8 dígitos sem hífen. `normalizePostalCode()`.
4. **volumes (/cart):** usar sempre as dimensões da *caixa ideal* retornada pelo `/calculator` quando se envia `products[]`.
5. **Documento do destinatário:** CPF/CNPJ obrigatório para todas as transportadoras.

## Tratamento de erros

Toda chamada lança `TCC\Exception\SuperFreteException` com:

```php
try {
    $client->calculateShipping($data);
} catch (SuperFreteException $e) {
    $e->getGrpcCode();      // ex.: 'invalid-argument', 'failed-precondition', 'unavailable'
    $e->getHttpStatus();    // ex.: 400, 409
    $e->getResponseBody();  // body JSON completo
    $e->getMessage();
}
```

Códigos gRPC possíveis: `cancelled, unknown, invalid-argument, deadline-exceeded, not-found, already-exists, permission-denied, resource-exhausted, failed-precondition, aborted, out-of-range, unimplemented, internal, unavailable, data-loss, unauthenticated`.

## Segurança

- **Token e secret_token jamais em logs/stack traces/mensagens.** Helper `maskToken()` mostra apenas os últimos 4 caracteres (ex.: `...76d8`).
- Log de debug opcional: `new SuperFreteClient($env, '/caminho/log.txt')` — o client já mascara o token automaticamente.
- Validação de assinatura usa `hash_equals()` (proteção contra timing attack).
- Variáveis de ambiente nunca são hardcoded.

## Evidência real (sandbox)

Todos os endpoints foram testados contra `https://sandbox.superfrete.com/` e as respostas **brutas reais** (sucesso e erro) foram salvas em `tests/fixtures/superfrete/`:

| Endpoint | Status | Evidência |
|----------|--------|-----------|
| GET `/me/orders` (health check) | ✅ HTTP 200 | `me_orders/health_check_success.json` |
| POST `/calculator` | ✅ HTTP 200 | `calculator/quote_products_success.json` · erro: `calculator/quote_error_body.json` |
| POST `/cart` | ✅ HTTP 200 (status pending) | `cart/cart_success.json` + `cart_request.json` |
| POST `/checkout` | ⚠️ **Limitação**: exige saldo na carteira (HTTP 409) | `checkout/checkout_error_body.json` |
| GET `/order/info/{id}` | ✅ HTTP 200 | `order_info/order_info_success.json` (dados pessoais mascarados) |
| POST `/tag/print` | ✅ HTTP 200 | `tag_print/tag_print_success.json` |
| GET `/me/orders` (filtros) | ✅ HTTP 200 | `me_orders/list_orders_filtered_success.json` |
| POST `/order/cancel` | ✅ HTTP 200 | `cancel/cancel_success.json` |
| Webhooks CRUD | ✅ 201/200/200/204 | `webhook/webhook_*_success.json` (secret mascarado) |

**Limitação reportada (não presumida):** o `/checkout` não pôde ser concluído porque a carteira do sandbox não possui saldo. O erro real HTTP 409 foi capturado e documentado como evidência. Para testar, adicione saldo em *Sandbox → Perfil → Carteira → Recarregar PIX* (simulado colando o código PIX no navegador), conforme a documentação da SuperFrete.

Para reexecutar as chamadas reais:

```bash
php scripts/demo.php   # exemplo interativo (ver seção abaixo)
```

## Testes

```bash
composer dump-autoload
vendor/bin/phpunit
```

Suíte atual: **32 testes, 132 asserções** (utils, HMAC, idempotência, fixtures reais). Os testes não dependem de chamadas reais à API — usam fixtures salvas e um `FakeWebhookHandler` em memória.

> Observação: para o `/checkout`, considere mockar/relaxar o teste ao adicionar saldo, ou manter o fixture de erro 409 como comportamento esperado "sem saldo".
