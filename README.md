# Royal Tech — E-commerce Premium

**Royal Tech** é um sistema de e-commerce completo desenvolvido como Trabalho de Conclusão de Curso (TCC) da ETEC. Trata-se de uma loja virtual de tecnologia premium com identidade visual sofisticada (preto, dourado e branco), painel administrativo completo e funcionalidades modernas de compra.

---

## Stack

| Camada | Tecnologia |
|--------|-----------|
| **Backend** | PHP 8+ (vanilla) |
| **Banco de Dados** | MySQL 8 (XAMPP) |
| **Frontend** | HTML5, CSS3, JavaScript (vanilla) |
| **Ícones** | Font Awesome 6.4 |
| **Tipografia** | Playfair Display + Rajdhani (Google Fonts) |

---

## Funcionalidades

### Públicas
- Catálogo de produtos com grid responsivo
- Busca por nome (SQL LIKE)
- Filtros por categoria, marca e faixa de preço
- Ordenação por menor preço, maior preço, A–Z e mais recentes
- Paginação (12 itens por página)
- Página de detalhes do produto com imagens
- Carrinho de compras (sessão persistente em banco)
- Checkout com criação de pedido (transação MySQL)
- Cadastro e login de clientes
- Perfil do usuário (editar dados e alterar senha)
- Histórico de pedidos com detalhes
- Recuperação de senha (token por e-mail)
- Formulário de contato
- Newsletter

### Administrativas
- Dashboard com receita real, total de pedidos, ticket médio, top produtos e categorias
- CRUD de produtos (nome, descrição, marca, preço, estoque, imagens)
- CRUD de categorias
- Gerenciamento de pedidos (filtro por status, atualização via select)
- Gerenciamento de clientes (busca, lista com iniciais dinâmicas)
- Gerenciamento de banners (criar, ativar/desativar, deletar)
- Relatórios com dados do banco
- Configurações do sistema (persistência em JSON)

---

## Banco de Dados

**Database:** `e5_royaltech` — 10 tabelas:

| Tabela | Finalidade |
|--------|-----------|
| `e5_users` | Clientes e administradores (`role ENUM`) |
| `e5_categories` | Categorias de produtos |
| `e5_products` | Produtos com preço, estoque, marca |
| `e5_product_images` | Imagens vinculadas a produtos |
| `e5_cart` | Carrinho por usuário (`UNIQUE user_id + product_id`) |
| `e5_orders` | Pedidos com status (`pending` a `canceled`) |
| `e5_order_items` | Itens de cada pedido |
| `e5_contacts` | Mensagens do formulário de contato |
| `e5_newsletter` | Inscrições na newsletter |
| `e5_password_reset_tokens` | Tokens de recuperação de senha |
| `e5_banners` | Banners da página inicial |

> O script completo de criação está em [`database/database.sql`](database/database.sql).

---

## Segurança

- **CSRF:** Token em todos os formulários POST (18 arquivos) — `includes/csrf.php`
- **Rate Limiting:** Máximo de 5 tentativas de login por IP a cada 15 minutos — `includes/rate_limit.php`
- **Credenciais:** Arquivo `config.php` externo (ignorado pelo Git); `config.example.php` disponível como modelo
- **Sanitização:** Todas as saídas passam por `htmlspecialchars` (84 ocorrências no projeto)
- **Prepared Statements:** Todas as queries utilizam prepared statements com MySQLi
- **Senhas:** Hash com `password_hash()` / `password_verify()`

---

## Instalação

### Pré-requisitos
- XAMPP (PHP 8+, MySQL 8)
- Git

### Passo a passo

```bash
# 1. Clone o repositório
git clone https://github.com/seu-usuario/TCC_Etec.git
cd TCC_Etec

# 2. Crie o banco de dados
#    Importe database/database.sql pelo phpMyAdmin ou MySQL CLI:
#    mysql -u root < database/database.sql

# 3. Configure as credenciais
cp config.example.php config.php
#    Edite config.php com seus dados de conexão

# 4. Inicie o servidor
#    Acesse http://localhost/TCC_Etec
```

### Credenciais padrão
- **Admin:** `admin` / `admin123` (ou cadastre-se e altere o `role` para `'admin'` no banco)

---

## Estrutura do Projeto

```
TCC_Etec/
├── assets/
│   ├── css/          # style.css, admin.css
│   ├── js/           # script.js
│   └── img/          # SVGs e uploads de produtos
├── components/
│   ├── header.php    # Navbar + search box + badge carrinho
│   ├── footer.php    # Rodapé institucional
│   └── product-card.php
├── database/
│   ├── database.sql  # Schema completo
│   ├── connection.php
│   └── settings.json # Configs do admin
├── includes/
│   ├── cart_functions.php
│   ├── csrf.php
│   └── rate_limit.php
├── pages/
│   ├── admin/        # Dashboard, CRUDs, relatórios
│   ├── auth/         # Login, registro, perfil, recuperação
│   ├── cart/         # Carrinho e checkout
│   └── products/     # Vitrine, categorias, contato
├── config.example.php
├── index.php
└── steps.md
```

---

## Commits Recentes

| Commit | Descrição |
|--------|-----------|
| `df4c7c5` | Segurança: CSRF, rate limiting, separação de credenciais |
| `5db95f6` | 10 funcionalidades: carrinho, checkout, busca, filtros, contato, newsletter, paginação, recuperação de senha, perfil, histórico |
| `a2bc9fb` | Admin pages conectadas ao banco de dados real |
| `46ced62` | Placeholders SVG + correção de caminhos de imagem |
| `9115792` | Skill de auditoria visual (web-inspector) |

---

## Licença

Projeto educacional — TCC ETEC.
