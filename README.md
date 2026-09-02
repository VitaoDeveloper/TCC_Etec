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

> **Atenção:** as credenciais de administração são definidas no banco de dados. Consulte o phpMyAdmin ou o responsável pelo projeto. Após o primeiro acesso, altere a senha. **Nunca incluir credenciais em texto plano no repositório.**

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
