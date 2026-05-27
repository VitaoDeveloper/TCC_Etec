---
name: web-inspector
description: >
  Use when asked to review, inspect, analyze, or audit the visual appearance,
  layout, styling, or graphical consistency of web pages in the project.
  Also use when asked to find visual bugs, CSS issues, responsive problems,
  or design inconsistencies across pages. Activates on requests involving
  "visual", "layout", "CSS", "design", "responsive", "graphical",
  "inconsistências", "aparência", or "estilo".
---

# Web Inspector — Auditoria Visual

## Objetivo
Varrer as páginas do projeto e listar inconsistências gráficas entre elas.

## Workflow

### 1. Preparação
- Verificar se o servidor PHP está rodando (XAMPP ou `php -S localhost:8000`)
- Se não estiver, iniciar com: `php -S localhost:8000` na raiz do projeto

### 2. Listar páginas para inspecionar
Páginas públicas:
- `/index.php`
- `/pages/products/products.php`
- `/pages/products/product-detail.php?id=1`
- `/pages/products/categories.php`
- `/pages/products/about.php`
- `/pages/products/contact.php`
- `/pages/auth/login.php`
- `/pages/auth/register.php`

Páginas administrativas:
- `/pages/admin/login.php`
- `/pages/admin/index.php`
- `/pages/admin/products.php`
- `/pages/admin/product-form.php`
- `/pages/admin/categories.php`
- `/pages/admin/orders.php`
- `/pages/admin/customers.php`
- `/pages/admin/banners.php`
- `/pages/admin/settings.php`
- `/pages/admin/reports.php`

### 3. Para cada página, inspecionar:

**a) Estrutura e consistência do header/footer**
- O header tem a mesma altura e composição em todas as páginas?
- O footer está consistentemente posicionado?
- A navegação ativa está destacada corretamente?

**b) Tipografia**
- Os tamanhos de fonte são consistentes para elementos equivalentes?
- Cores de texto seguem um padrão?
- Hierarquia visual (h1, h2, h3) é respeitada?

**c) Espaçamento e alinhamento**
- Margens e paddings são consistentes entre cards/sections similares?
- Grid de produtos está alinhado?
- Botões têm o mesmo padding e altura?

**d) Cores**
- A paleta de cores é consistente?
- Botões primários/secundários seguem o mesmo padrão?
- Links têm cor definida e consistente?

**e) Imagens**
- Placeholder de produto carrega corretamente?
- Há imagens com caminhos quebrados?
- Proporção das imagens é consistente?

**f) Responsividade (viewport 375px, 768px, 1024px)**
- Elementos quebram ou sobrepõem em telas pequenas?
- Menu de navegação se adapta?
- Grid de produtos fica legível?

**g) Formulários**
- Inputs têm mesma altura e padding?
- Labels e placeholders seguem padrão?
- Mensagens de erro/sucesso têm estilo consistente?

### 4. Relatório final
Produzir um resumo marcado com:

```markdown
## Relatório de Auditoria Visual

### Inconsistências Encontradas

| # | Página | Tipo | Descrição | Severidade |
|---|--------|------|-----------|------------|
| 1 | products.php | Espaçamento | Cards com alturas diferentes | Média |
| ... | ... | ... | ... | ... |

### Estatísticas
- Total de páginas inspecionadas: X
- Inconsistências encontradas: X (Altas: X, Médias: X, Baixas: X)
```

### Critérios de severidade
- **Alta**: elemento quebrado, ilegível ou funcionalmente prejudicado
- **Média**: inconsistência perceptível mas não impeditiva
- **Baixa**: detalhe estético menor
