# Sessão opencode — Royal Tech (TCC_Etec)

**Stack:** PHP vanilla + MySQL (XAMPP) + CSS puro  
**Branch ativa:** `development`  
**Repositório:** `https://github.com/VitaoDeveloper/TCC_Etec`

---

## Dia 1 — 27/05/2026

### 1. Correção de Nomes de Tabelas (database.sql → e5_ prefix)

O commit `3c243cd` renomeou todas as tabelas com prefixo `e5_`. Arquivos PHP ainda usavam nomes antigos — corrigido em **11 arquivos** (27 substituições).

| Arquivo | O que foi alterado |
|---|---|
| `database/connection.php:5` | `"royaltech"` → `"e5_royaltech"` |
| `pages/auth/insertion.php:44` | `INSERT INTO users` → `INSERT INTO e5_users` |
| `pages/auth/authentication.php:30` | `FROM users` → `FROM e5_users` |
| `pages/admin/authenticate.php:13` | `FROM users` → `FROM e5_users` |
| `pages/products/products.php:14-16,31` | `product_images/products/categories` → `e5_*` |
| `pages/products/product-detail.php:12-14` | Mesmo padrão |
| `pages/products/categories.php:11` | `categories`/`products` → `e5_categories`/`e5_products` |
| `pages/services/product-image.php:11` | `product_images` → `e5_product_images` |
| `pages/admin/product-form.php:30,37,66,69,116,118,123,125,140` | 9 substituições |
| `pages/admin/products.php:12,22` | `products`/`categories` → `e5_*` |
| `pages/admin/categories.php:14,23,33` | `categories`/`products` → `e5_*` |

---

### 2. Criação da Skill web-inspector

Criada skill de auditoria visual em `.opencode/skills/web-inspector/SKILL.md`.  
Registrada via `opencode.json`.

**Funcionalidades:**
- Checklist de 7 categorias de inspeção visual
- Formato de relatório tabelado
- Workflow de preparação (servidor PHP → navegação → relatório)

---

### 3. Análise e Correção do steps.md

**Seção 1 — Banco de Dados** ✅ — Todas as 8 tabelas com prefixo `e5_`, schema corrigido.

**Seção 2 — Bugs e Erros** ✅ — 9 itens corrigidos: `echo "Olá Mundo"` removido, `finally` problemático em `connection.php`, typo `indentifier`, IDs duplicados em `register.php`, caminho de imagem em `product-card.php`, modal quebrado em `categories.php`, `admin.css` removido de `header.php`, `admin.php` preenchido.

**Seção 3 — Arquivos Faltando** ✅ — 3 SVGs criados (`placeholder-product.svg`, `placeholder-avatar.svg`, `banner-bg.svg`), referências `.jpg` → `.svg` em 8 arquivos.

**Seção 4 — Autenticação e Sessões** ✅ — `session_start()` em todas as páginas necessárias, `auth_check.php` em todas as 10 páginas admin.

**Seção 5 — Funcionalidades (CRUDs conectados ao DB)** ✅ — `customers.php`, `orders.php`, `banners.php`, `reports.php`, `settings.php` com queries reais.

---

### 4. Arquivos criados (Dia 1)

- `.opencode/skills/web-inspector/SKILL.md`
- `opencode.json`
- `assets/img/placeholder-product.svg`
- `assets/img/placeholder-avatar.svg`
- `assets/img/banner-bg.svg`
- `database/settings.json`
- `includes/csrf.php`
- `includes/rate_limit.php`
- `config.example.php`
- `includes/cart_functions.php`
- `pages/cart/add.php`
- `pages/cart/update.php`
- `pages/cart/remove.php`
- `pages/cart/cart.php`
- `pages/cart/checkout.php`
- `pages/auth/forgot-password.php`
- `pages/auth/reset-password.php`
- `pages/auth/profile.php`
- `pages/auth/orders.php`
- `pages/auth/order-detail.php`

### 5. Arquivos modificados (Dia 1)

- `database/connection.php`
- `pages/auth/insertion.php`
- `pages/auth/authentication.php`
- `pages/admin/authenticate.php`
- `pages/products/products.php`
- `pages/products/product-detail.php`
- `pages/products/categories.php`
- `pages/services/product-image.php`
- `pages/admin/product-form.php`
- `pages/admin/products.php`
- `pages/admin/categories.php`
- `pages/admin/customers.php`
- `pages/admin/orders.php`
- `pages/admin/banners.php`
- `pages/admin/reports.php`
- `pages/admin/settings.php`
- `components/product-card.php`
- `components/header.php`
- `assets/css/style.css`
- `assets/js/script.js`
- `index.php`
- `steps.md`
- `pages/admin/index.php`
- `pages/auth/login.php`
- `pages/admin/login.php`

---

### 6. Commits (Dia 1)

| Commit | Descrição |
|--------|----------|
| `9115792` | feat: add web-inspector skill for visual auditing |
| `46ced62` | fix: replace broken .jpg placeholders with .svg and fix image path resolution |
| `a2bc9fb` | feat: connect all admin pages to real database queries |
| `53e8c2d` | docs: update project status and add session summary |
| (não listado) | feat: implement cart, checkout, profile, password recovery, order history, filters, contact, newsletter |
| (não listado) | feat: add CSRF, rate limiting, config.example, sanitization |

---

## Dia 2 — 28/05/2026

### 7. README.md — Reescrita profissional

README.md reescrito do zero com: descrição do projeto (TCC ETEC, e-commerce premium), stack (tabela), funcionalidades (públicas + administrativas), banco de dados (10 tabelas), segurança (CSRF, rate limiting, prepared statements), instalação (passo a passo), estrutura de diretórios, commits recentes.

---

### 8. .htaccess + Página 404

`.htaccess` completo com:
- Segurança: bloqueio de diretórios sensíveis, `Options -Indexes`, headers (`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`)
- Cache: `mod_expires` (imagens 1 ano, CSS/JS 1 mês), `mod_deflate`
- URLs amigáveis: `/produto/123`, `/categoria/slug`, `/carrinho`, `/checkout`, `/login`, `/cadastro`, `/admin`, `/admin/produtos`, etc.
- Erro 404 personalizado → `pages/404.php`

`pages/404.php` — página com header/footer da loja, número 404 em dourado, 3 botões (Início, Produtos, Fale Conosco).

`steps.md` atualizado (.htaccess marcado como ✅).

---

### 9. SEO e Acessibilidade

**`components/header.php`:** `<meta name="description">` dinâmico, 5 tags Open Graph (`og:title`, `og:description`, `og:image`, `og:type`, `og:site_name`) com fallbacks.

**Criados:** `robots.txt`, `sitemap.xml`.

**`aria-label`:** Adicionados em 17+ botões (header busca/mobile, cart, admin ações, banners, customers, product-card).

**`alt` texts:** Corrigidos em 6 imagens (cart, order-detail, admin index).

`steps.md` — Seção 8 reescrita como ✅.

---

### 10. UI/UX — Inconsistências

**Designs de login unificados:** `login.css` reescrito com layout premium. `admin/login.php` e `auth/login.php` usam `.login-page`/`.login-container`/`.login-box`. `register.css` no mesmo padrão.

**Breadcrumb responsivo:** `padding` fixo → `clamp()`.

**Sidebar backdrop:** `.admin-sidebar-backdrop` + JS toggle + fechar no clique/Escape.

**CEP:** Já `type="text"` com `pattern`, apenas `steps.md` atualizado.

---

### 11. Auditoria Visual (web-inspector skill)

18 inconsistências encontradas (4 críticas, 8 médias, 6 baixas).

**🔴 Críticos:**
1. `--color-gold` inexistente em 5 arquivos
2. `.auth-feedback*` — 15 páginas sem `login.css` carregado
3. `.admin-form-group` — 5 páginas públicas sem `admin.css`
4. 27 classes duplicadas entre `style.css` e `admin.css`

**🟠 Médios:**
5. `object-fit` conflitante em product-card
6. Filtro products.php sem CSS de form
7–10. 4 classes de página sem definição CSS
11. `<style>` embutido em settings.php
12. 3 páginas admin sem sidebar

**🟡 Baixos:**
13. ~40 blocos de inline styles
14. style.css carregado 2x em about.php
15–17. Padrões inline sem classe
18. `.require-auth` sem prefixo `js-`

---

### 12. Correções da Auditoria Visual

**🔴 Críticos resolvidos:**
1. `--color-gold` adicionado em `:root`
2. `.auth-feedback*` movido para `style.css` (global)
3. `admin.css` adicionado ao `header.php` (global)
4. CSS duplicados removidos de about.php, contact.php, 404.php

**🟠 Médios resolvidos:**
5. `object-fit` unificado para `contain` + `width/height:100%`
6. `admin.css` global via header
7–10. 9 classes faltantes adicionadas (`.category-count`, `.contact-form`, `.contact-info-cards`, `.contact-info-card`, `.team-member`, `.cart-qty`, `.cart-subtotal`, `.cart-remove`, `.cart-total`)
11. `<style>` de settings.php movido para `admin.css`
12. Flat layout mantido (intencional para CRUDs)

**🟡 Baixos resolvidos:**
13. Classes utilitárias extraídas: `.grid-2col`, `.grid-4col`, `.icon-box`, `.btn-block`, `.mt-20`, `.mx-auto`, `.max-w-480`, `.newsletter-input`
14. style.css duplicado removido de about.php
15. `.newsletter-input` criada + aplicada
16. `.contact-info-card` + `.icon-box` aplicados
17. `.btn-block` e `.js-require-auth` aplicados em product-detail
18. `.require-auth` → `.js-require-auth` (JS + 3 templates)

**Arquivos:** `style.css`, `admin.css`, `login.css`, `header.php`, `script.js`, `index.php`, `product-card.php`, `product-detail.php`, `about.php`, `contact.php`, `404.php`, `settings.php`, `forgot-password.php`, `reset-password.php`, `profile.php`, `checkout.php`

---

### 13. Hotfix: COUNT SQL com subquery

`products.php:43` usava `strpos($sql, 'FROM')` para extrair a cláusula FROM da query principal. O SELECT continha uma subquery (`SELECT ... FROM e5_product_images ...`) cujo `FROM` era encontrado primeiro, gerando SQL inválido.

**Solução:** `strpos` → `strrpos` (último `FROM`, que é o da query principal).

---

### 14. Auditoria visual + correção UI/UX (Categorias e Produtos admin)

**Problemas:**

| # | Página | Problema | Severidade |
|---|--------|----------|------------|
| 1 | categories.php, products.php, product-form.php | Sidebar ausente — navegação some, layout quebrado | Alta |
| 2 | categories.php, products.php, product-form.php | Font Awesome e Google Fonts ausentes | Alta |
| 3 | categories.php, product-form.php | Formulários sem labels, só placeholders | Média |
| 4 | categories.php | Inputs sem classes CSS | Média |
| 5 | categories.php | Tabela sem coluna Descrição | Média |
| 6 | categories.php, products.php | Sem empty state | Baixa |
| 7 | products.php | Ações editar/excluir desalinhadas | Média |
| 8 | product-form.php | Upload/imagem misturados, sem estrutura | Média |
| 9 | Todos | Sem botão de logout | Média |

**Correções:**
1. `sidebar_inc.php` criado — include compartilhado com nav items + logout + `$activePage` dinâmico
2. `categories.php` reescrito: sidebar, labels, suporte a edição, coluna Descrição, empty state, table-actions com ícones
3. `products.php` reescrito: sidebar, table-actions com ícones, empty state
4. `product-form.php` reescrito: sidebar, form groups, grid 2 colunas, labels + hints
5. `admin.css`: classe `.empty-state` adicionada

---

### 15. Banners admin — Upload de imagem + frontend dinâmico

**`banners.php` reescrito:**
- Upload de imagem via `$_FILES` com validação (JPG/PNG/WEBP), criação dinâmica de `assets/img/banners/`
- Campo `image_path` manual + file upload (funcionamento igual aos produtos)
- Suporte a edição de banners (antes só criava/excluía/toggle)
- Formulário com `admin-form-group`, labels, hints
- Cards exibem a imagem real com fallback para ícone
- Status badge sobreposto na imagem
- Sidebar reutilizável (`sidebar_inc.php`)

**`index.php` atualizado:**
- Consulta banner ativo mais recente (`WHERE is_active = 1 LIMIT 1`)
- Exibe dinamicamente: título, subtítulo, imagem, link
- Fallback para layout estático antigo

`assets/img/banners/.gitkeep` — diretório versionado.

---

### 16. Correção de paths de imagem (produtos e banners)

**Causa raiz:** `normalizeImagePath()` armazenava caminhos com `/` inicial (`/assets/img/products/x.jpg`). Renderizadores não tratavam esse caso — o path passava direto sem `$base_path`, quebrando em subdiretório (`/TCC_Etec/`).

**Correções:**

| Arquivo | Mudança |
|---|---|
| `pages/admin/product-form.php` | `normalizeImagePath`: `/assets/img/` → `assets/img/` |
| `pages/admin/banners.php` | `normalizeBannerPath`: `/assets/img/` → `assets/img/` |
| `components/product-card.php` | `preg_match('#^/#')` → strip `/` + prefix `$base_path` |
| `pages/products/product-detail.php` | Mesmo pattern |
| `pages/cart/cart.php` | `if ($img === '')` + elif `/` + elif relative |
| `pages/auth/order-detail.php` | Mesmo pattern |
| `pages/products/products.php` | Fallback `../../assets/img/` → `assets/img/` |

**Compatibilidade:** paths antigos no DB (`/assets/img/products/x.jpg`) continuam funcionando — o renderizador detecta o `/` e aplica `$base_path`.

---

### 17. CSS — Imagem do product card

`object-fit: contain` → `cover`, `padding: 30px` removido, `position: absolute` com `inset: 0`. A imagem agora preenche todo o card sem bordas brancas.

---

### 18. 404.php — Links independentes da URL

`$base_path` alterado de `'../'` (relativo ao arquivo) para detecção dinâmica via `$_SERVER['SCRIPT_NAME']`:

```php
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = rtrim(dirname($scriptDir), '/\\') . '/';
```

Resultado: `/TCC_Etec/` (absoluto do domínio) — funciona independente da URL que gerou o 404.

Includes trocados de `'../components/...'` para `__DIR__ . '/../components/...'`.

---

## Arquivos criados (total)

| Arquivo | Dia |
|---------|-----|
| `.opencode/skills/web-inspector/SKILL.md` | 1 |
| `opencode.json` | 1 |
| `assets/img/placeholder-product.svg` | 1 |
| `assets/img/placeholder-avatar.svg` | 1 |
| `assets/img/banner-bg.svg` | 1 |
| `database/settings.json` | 1 |
| `includes/csrf.php` | 1 |
| `includes/rate_limit.php` | 1 |
| `config.example.php` | 1 |
| `includes/cart_functions.php` | 1 |
| `pages/cart/add.php` | 1 |
| `pages/cart/update.php` | 1 |
| `pages/cart/remove.php` | 1 |
| `pages/cart/cart.php` | 1 |
| `pages/cart/checkout.php` | 1 |
| `pages/auth/forgot-password.php` | 1 |
| `pages/auth/reset-password.php` | 1 |
| `pages/auth/profile.php` | 1 |
| `pages/auth/orders.php` | 1 |
| `pages/auth/order-detail.php` | 1 |
| `.htaccess` | 2 |
| `pages/404.php` | 2 |
| `robots.txt` | 2 |
| `sitemap.xml` | 2 |
| `pages/admin/sidebar_inc.php` | 2 |
| `assets/img/banners/.gitkeep` | 2 |

## Commits (Dia 2)

| Commit | Descrição |
|--------|----------|
| `feat: add .htaccess with security, URL rewriting, cache and custom 404 page` |
| `feat: add SEO meta tags, Open Graph, sitemap, robots.txt, aria-labels and alt texts` |
| `fix: unify login/register designs, add sidebar backdrop, fix breadcrumb responsiveness` |
| `docs: rewrite README.md with professional and cohesive project documentation` |
| `style: add responsive utility classes, remove duplicate CSS, add missing CSS classes` |
| `fix: use strrpos instead of strpos in count SQL to avoid subquery FROM conflict` |
| `fix: audit visual e correção UI/UX das telas admin de categorias e produtos` |
| `feat: upload de imagem em banners admin + exibição dinâmica no frontend` |
| `fix: corrige paths de imagem com / inicial quebravam em subdiretorio` |
| `fix: ajusta css do product-image para object-fit cover sem padding` |
| `fix: 404.php usa base_path absoluto via SCRIPT_NAME + __DIR__ includes` |
