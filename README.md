# Royal Tech — TCC E-commerce

Sistema de e-commerce desenvolvido como Trabalho de Conclusão de Curso (TCC) do curso técnico em Informática.

## Requisitos

- PHP 8.2+ com extensões: pdo_mysql, mbstring, json, gd, dom, xml
- MySQL/MariaDB 10.4+
- Composer 2.x
- XAMPP (recomendado para desenvolvimento local) ou Docker

## Instalação Rápida (XAMPP)

```bash
# 1. Clone o repositório dentro de htdocs
cd /opt/lampp/htdocs
git clone <repo-url> TCC_Etec
cd TCC_Etec

# 2. Instale dependências PHP
/opt/lampp/bin/php composer.phar install

# 3. Configure variáveis de ambiente
cp .env.example .env
# Edite .env com suas credenciais de banco, SMTP, SuperFrete, etc.

# 4. Importe o banco de dados
/opt/lampp/bin/mysql -u root -p e5_royaltech < database/database.sql

# 5. Inicie o Apache e MySQL no XAMPP Control Panel

# 6. Acesse http://localhost/TCC_Etec
```

## Instalação com Docker (Opcional)

```bash
# Requer docker compose com mailpit e mysql
docker compose up -d
# O arquivo .env já aponta para mailpit:1025
```

## Credenciais de Teste

Após importar o `database/database.sql`, o usuário de demonstração é:

- **E-mail**: maria.silva@email.com
- **Senha**: password

Este usuário possui 8 pedidos de exemplo cobrindo todos os estados:
- Pendente (Pix válido com countdown de 30 min)
- Pago
- Em preparação
- Enviado (com rastreio PAC)
- Entregue
- Cancelado por Pix expirado
- Cancelado pelo cliente
- Reembolsado

> **Importante**: Reimporte o `database/database.sql` antes da apresentação para que o countdown do Pix apareça "vivo" (expiração = NOW() + 30 minutos no momento do import).

## Comandos Úteis

```bash
# Rodar worker (expiração Pix + fila de e-mails) — roda uma vez e sai
/opt/lampp/bin/php worker.php --once

# Rodar worker em loop (para produção via cron/systemd)
/opt/lampp/bin/php worker.php

# Testes automatizados
/opt/lampp/bin/php vendor/bin/phpunit

# Lint PHP
find . -name "*.php" -not -path "./vendor/*" -exec /opt/lampp/bin/php -l {} \;

# Backup do projeto + banco
STAMP=$(date +%Y%m%d-%H%M%S)
DEST="/home/usuario/backups/TCC_Etec-$STAMP"
mkdir -p "$DEST"
zip -r "$DEST/TCC_Etec.zip" . -x "*.git/*" "vendor/*" "node_modules/*"
/opt/lampp/bin/mysqldump --single-transaction --default-character-set=utf8mb4 -u root e5_royaltech > "$DEST/e5_royaltech.sql"
```

## Estrutura do Projeto

```
├── api/                    # Endpoints REST (account/orders)
├── assets/                 # CSS, JS, imagens
├── components/             # Header, footer, componentes reutilizáveis
├── database/
│   ├── connection.php      # PDO connection
│   └── database.sql        # Schema + seed idempotente (fonte única)
├── includes/
│   ├── config.php          # Configurações, loadEnv, timezone
│   ├── mail.php            # PHPMailer + file transport (dev)
│   ├── order_repo.php      # Repository + batch helpers
│   ├── order_state.php     # Progress tracker canônico (5 etapas)
│   ├── comprovante_functions.php
│   └── notification_functions.php
├── pages/
│   ├── auth/               # Login, registro, perfil, pedidos, detalhe
│   ├── cart/               # Carrinho, checkout, pagamento
│   └── download-comprovante.php
├── storage/
│   ├── comprovantes/       # PDFs gerados
│   └── mail/               # .eml salvos (MAIL_TRANSPORT=file)
├── tests/                  # PHPUnit (unit + renderização)
├── worker.php              # Expiração Pix + fila e-mails (--once)
└── README.md
```

## Funcionalidades Principais

- **Meus Pedidos** (`pages/auth/orders.php`):
  - 4 cards de resumo (Totais, Em andamento, Entregues, Total comprado)
  - Busca por nº do pedido ou nome do produto
  - Ordenação: Mais recentes, Mais antigos, Maior valor
  - 8 chips de filtro (Todos, Pendente, Pagos, Em preparação, Enviados, Entregues, Cancelados, Reembolsados)
  - Paginação 10 por página, agrupamento mensal
  - Ações contextuais por estado (Pagar agora, Rastrear, Comprovante, Comprar novamente)
  - Tracker de 5 etapas (Pedido → Pagamento → Preparação → Enviado → Entregue)
  - Countdown Pix animado
  - Acessibilidade: foco visível, aria-labels, contraste

- **Worker** (`worker.php`):
  - Expiração preguiçosa de Pix pendente (usa `order_expire_pending_pix_lazy()` — cancela pedido, devolve estoque, grava histórico)
  - Fila de e-mails com retentativas e log
  - Opção `--once` para execução única (útil em testes e deploy)

- **Segurança**:
  - CSRF em todos formulários POST
  - Prepared statements em todo SQL
  - Autorização por SQL (owner/admin) — 404 para pedidos de outros usuários
  - Cartões salvos: só bandeira + 4 últimos dígitos + validade (nunca PAN/CVV)
  - Rate limit no reenvio de comprovante (1 a cada 2 min por pedido)

- **Acessibilidade**:
  - Foco visível global (`a:focus-visible, button:focus-visible, [tabindex]:focus-visible`)
  - Contraste WCAG AA
  - Labels ARIA, alt text, landmarks

## Deploy em Produção

1. Configure `.env` com credenciais reais (banco, SMTP real, SuperFrete produção)
2. Defina `MAIL_TRANSPORT=smtp` (remova `file`)
3. Configure cron para `worker.php` a cada 5 min:
   ```bash
   */5 * * * * /usr/bin/php /caminho/para/TCC_Etec/worker.php >> /var/log/worker.log 2>&1
   ```
4. Configure HTTPS, headers de segurança (CSP, HSTS), backup automático do banco

## Versionamento

- Tag de entrega: `entrega-tcc`
- Commits seguem Conventional Commits (`feat:`, `fix:`, `chore:`, etc.)

## Licença

Projeto acadêmico — uso educacional.
