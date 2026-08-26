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

## Instalação

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
