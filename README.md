# Royal Tech - Site E-commerce Premium

## Descrição
Site de e-commerce profissional para loja de tecnologia chamada "Royal Tech", desenvolvido em PHP com tema premium nas cores preto, dourado e branco.

## Estrutura de Pastas

```
TCC_Etec/
├── assets/
│   ├── css/
│   │   ├── style.css       # Estilos principais do site
│   │   └── admin.css       # Estilos da área administrativa
│   ├── js/
│   │   └── script.js       # JavaScript principal
│   └── img/
│       └── placeholder-*.jpg
├── components/
│   ├── header.php          # Componente de cabeçalho
│   ├── footer.php          # Componente de rodapé
│   └── product-card.php    # Template de cartão de produto
├── pages/
│   ├── admin/
│   │   ├── index.php       # Dashboard administrativo
│   │   ├── login.php       # Página de login admin
│   │   ├── products.php    # Gerenciamento de produtos
│   │   ├── product-form.php # Formulário de produto
│   │   ├── categories.php  # Gerenciamento de categorias
│   │   ├── orders.php      # Gerenciamento de pedidos
│   │   ├── customers.php   # Gerenciamento de clientes
│   │   ├── banners.php     # Gerenciamento de banners
│   │   ├── reports.php      # Relatórios
│   │   └── settings.php    # Configurações
│   ├── products/
│   │   ├── products.php    # Listagem de produtos
│   │   └── categories.php  # Categorias (público)
│   ├── about.php           # Página sobre nós
│   └── contact.php         # Página de contato
└── index.php               # Página inicial
```

## Características

### Frontend
- Design premium com cores preto (#1a1a1a), dourado (#d4af37) e branco
- Layout responsivo e moderno
- Tipografia premium (Playfair Display + Rajdhani)
- Animações e transições suaves
- Placeholders para imagens
- Grid de produtos com filtros e ordenação
- Sistema de categorias
- Footer completo com informações de contato

### Área Administrativa
- Dashboard com estatísticas
- Gerenciamento de produtos (CRUD)
- Gerenciamento de categorias
- Gerenciamento de pedidos
- Gerenciamento de clientes
- Gerenciamento de banners
- Relatórios
- Configurações do sistema

## Como Usar

1. **Instalação**
   - Coloque todos os arquivos em um servidor PHP (XAMPP, WAMP, etc.)
   - Acesse `index.php` para ver o site público
   - Acesse `pages/admin/login.php` para acessar o painel administrativo

2. **Personalização**
   - Substitua os placeholders por imagens reais
   - Altere as cores no CSS conforme necessário
   - Configure as informações da empresa no footer

3. **Próximos Passos (Backend)**
   - Implementar conexão com banco de dados MySQL
   - Criar sistema de autenticação seguro
   - Implementar carrinho de compras
   - Criar sistema de pagamento
   - Implementar busca e filtros avançados

## Cores do Tema

- **Primário**: #d4af37 (Dourado)
- **Secundário**: #1a1a1a (Preto)
- **Fundo**: #000000 (Preto Escuro)
- **Texto**: #ffffff (Branco)
- **Texto Secundário**: #888888 (Cinza)

## Dependências Externas
- Font Awesome 6.4 (ícones)
- Google Fonts (Playfair Display, Rajdhani)
- Font Awesome (versão free)

## Licença
Este projeto é apenas para fins educacionais (TCC).
