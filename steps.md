# 📋 Análise Completa — Royal Tech TCC
> Status atual: **Frontend quase completo · Backend incompleto · Segurança ausente**

---

## 1. 🗄️ Banco de Dados

### Tabelas existentes
- `users` — definida em `database/database.sql`

### Tabelas faltando (crítico)
| Tabela | Por quê é necessária |
|---|---|
| `products` | Nenhum produto é salvo/lido do banco |
| `categories` | Categorias são estáticas no HTML |
| `orders` | Pedidos exibidos no admin são mock |
| `order_items` | Itens de cada pedido |
| `cart` | Carrinho não persiste |
| `banners` | Banners são estáticos |
| `admins` | Login do admin não tem tabela própria |
| `product_images` | Upload de imagens não tem destino |

### Problemas no schema existente
- `postal_code` definido como `int(20)` — CEP deve ser `varchar(10)` (perde zeros à esquerda e hífens)
- Sem `created_at` / `updated_at` em `users`
- Sem `role` ou tabela separada para separar cliente de administrador

---

## 2. 🐛 Bugs e Erros de Código

### `index.php`
- **Linha 6:** `echo "Olá Mundo";` — debug esquecido, imprime texto antes do HTML

### `database/connection.php`
- `finally` executa `echo "<script>console.log('Sucesso!')</script>"` — injeta JavaScript em toda página que inclui este arquivo, quebrando headers e qualquer resposta JSON futura

### `pages/auth/insertion.php`
- Bloco `finally` executa o redirect **sempre**, inclusive quando a inserção falha (`PDOException`). O usuário é redirecionado mesmo com erro.

### `pages/auth/login.php`
- `id="indentifier"` (typo) no `<label for="indentifier">`, mas o `<input>` tem `id="identifier"` — label não funciona corretamente
- JavaScript busca `document.getElementById('password')` mas o campo senha tem `id="senha"`; o toggle de senha **não funciona**

### `pages/auth/register.php`
- Oito campos diferentes compartilham `id="username"` — viola HTML e quebra `<label for="">` e qualquer JS que busque por ID

### `components/product-card.php`
- `src="assets/img/placeholder-product.jpg"` — caminho sem `$base_path`, quebra em todas as páginas que não estão na raiz (ex: `pages/products/products.php`)

### `pages/admin/categories.php`
- Modal usa `style="display: none"` mas o botão tenta `classList.add('active')` — `display:none` não é sobrescrito por classe CSS; o modal **nunca abre**

### `components/header.php`
- Carrega `../../assets/css/admin.css` em **todas** as páginas públicas (index, produtos, contato, sobre), aumentando peso desnecessário

### `pages/admin/admin.php`
- Arquivo completamente vazio

---

## 3. 📁 Arquivos Faltando

| Arquivo | Referenciado em |
|---|---|
| `pages/products/product-detail.php` | `components/product-card.php` (link do produto) |
| `assets/img/placeholder-product.jpg` | Múltiplas páginas |
| `assets/img/placeholder-avatar.jpg` | `pages/admin/index.php` |
| `assets/img/hero-bg.jpg` | `assets/css/style.css` (`.hero-bg`) |
| `assets/img/banner-bg.jpg` | `assets/css/style.css` (`.banner-section::before`) |
| `.htaccess` ou `config.php` | URLs amigáveis / configuração global |

---

## 4. 🔐 Autenticação e Sessões

- **Nenhuma página usa `session_start()`** — após login bem-sucedido, nenhum dado de sessão é criado
- **Painel admin completamente desprotegido** — qualquer URL como `/pages/admin/index.php` é acessível sem login
- **Login do admin** (`pages/admin/login.php`) não tem `action` apontando para nenhum PHP de backend; o `method="POST"` vai para `index.php` que ignora a requisição
- **Sem middleware de autenticação** — não há nenhum `include 'auth_check.php'` em página alguma
- **Sem logout funcional** — o link "Sair" no dashboard admin aponta para `login.php` sem destruir sessão

---

## 5. 🚧 Funcionalidades Não Implementadas

### Backend (sem nenhuma lógica PHP)
- [ ] **Carrinho de compras** — botão existe, efeito visual existe, mas nada é salvo
- [ ] **Checkout e pagamento** — inexistente
- [ ] **Busca de produtos** — campo existe, sem resultado real
- [ ] **Filtros e ordenação** — frontend only, sem query no banco
- [ ] **Formulário de contato** — sem envio de e-mail ou gravação
- [ ] **Newsletter** — sem backend
- [ ] **Paginação** — hardcoded no HTML
- [ ] **Recuperação de senha** — link existe, sem página
- [ ] **Perfil do usuário logado** — inexistente
- [ ] **Histórico de pedidos do cliente** — inexistente

### CRUD Admin (todos exibem dados mockados)
- [ ] Listagem de produtos do banco
- [ ] Criação / edição / exclusão de produtos
- [ ] Upload de imagem de produto
- [ ] Gerenciamento de categorias
- [ ] Gerenciamento de pedidos (atualização de status)
- [ ] Gerenciamento de clientes
- [ ] Gerenciamento de banners
- [ ] Relatórios com dados reais
- [ ] Configurações persistentes no banco

---

## 6. 🔒 Segurança

| Problema | Risco |
|---|---|
| Credenciais do banco hardcoded em `connection.php` | Exposição se o arquivo vazar |
| Nenhuma proteção CSRF nos formulários POST | Ataques cross-site |
| Sem sanitização de saída (htmlspecialchars) | XSS em dados vindos do banco |
| Erro do banco exibido diretamente ao usuário (`die($e->getMessage())`) | Exposição de estrutura interna |
| Sem rate limiting no login | Brute force |
| Sem validação server-side no formulário de produto (admin) | Injeção de dados |
| Senhas são hasheadas corretamente com `password_hash` ✅ | — |

---

## 7. 🎨 Inconsistências de UI/UX

- **Dois designs de login diferentes**: `pages/admin/login.php` e `pages/auth/login.php` têm aparências distintas sem motivo claro
- **Breadcrumb** usa `padding: 120px 0 40px` fixo — em mobile, onde o header é menor, gera espaço excessivo
- **Sidebar mobile do admin** abre mas não tem overlay/backdrop clicável para fechar
- **`product-detail.php`** não existe, então todos os cards de produto têm links quebrados
- **CEP no cadastro**: campo `type="number"` apaga zeros à esquerda (ex: `01310100` vira `1310100`)

---

## 8. 🔍 SEO e Acessibilidade

- Nenhuma página tem `<meta name="description">`
- Sem Open Graph (`og:title`, `og:image`) para compartilhamento
- Sem `sitemap.xml` e `robots.txt`
- Botões de ação (favorito, carrinho, visualizar) sem `aria-label` — ilegíveis por leitores de tela
- Imagens placeholder sem `alt` significativo

---

## 9. ✅ O que está funcionando bem

- Design visual premium consistente (cores, tipografia, componentes)
- CSS responsivo com breakpoints definidos
- Estrutura de pastas organizada e clara
- Hash de senha com `password_hash` / `password_verify` correto
- Componente `product-card.php` reutilizável
- `header.php` / `footer.php` como componentes separados
- JavaScript de UX (sticky header, toggle carrinho, wishlist animado)
- PDO com prepared statements (previne SQL Injection) ✅
- Página de produtos com sidebar de filtros bem estruturada (frontend)

---

## 🗺️ Prioridade de Implementação Sugerida

```
FASE 1 — Fundação (sem isso nada funciona)
  1. Criar todas as tabelas no database.sql
  2. Implementar session_start() e autenticação com sessão
  3. Proteger rotas do admin
  4. Corrigir bugs críticos (echo debug, finally, IDs duplicados)

FASE 2 — CRUD Core
  5. Listagem de produtos do banco na loja
  6. Página product-detail.php
  7. CRUD de produtos no admin
  8. CRUD de categorias

FASE 3 — Fluxo de Compra
  9. Carrinho com sessão
  10. Checkout (endereço + resumo)
  11. Integração com gateway de pagamento

FASE 4 — Complementos
  12. Formulário de contato com envio de e-mail
  13. Perfil do usuário e histórico de pedidos
  14. Busca e filtros reais
  15. Relatórios com dados reais
```
