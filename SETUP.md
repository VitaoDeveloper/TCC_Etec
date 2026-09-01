# 🚀 Setup Completo - Royal Tech E-commerce

> **Última atualização:** 01/09/2026  
> **Versão:** 2.0 (sistema sem fallbacks)

---

## 📋 Índice

1. [Requisitos do Sistema](#requisitos)
2. [Instalação](#instalação)
3. [Configuração Obrigatória](#configuração-obrigatória)
4. [Configuração de Gateways](#gateways)
5. [Testes](#testes)
6. [Troubleshooting](#troubleshooting)
7. [Segurança em Produção](#segurança)

---

## 🔧 Requisitos do Sistema {#requisitos}

### Servidor
- **PHP:** ≥ 8.1
- **Banco de Dados:** MariaDB 10.4+ ou MySQL 5.7+
- **Servidor Web:** Apache 2.4+ com `mod_rewrite`

### Extensões PHP Obrigatórias
```bash
# Verificar no servidor
php -m | grep -E 'pdo|pdo_mysql|curl|openssl|mbstring|json'
```

- `pdo` e `pdo_mysql` - Banco de dados
- `curl` - Requisições HTTP (APIs)
- `openssl` - Criptografia (tokens)
- `mbstring` - Strings multibyte (UTF-8)
- `json` - Manipulação JSON

### Extensões PHP Recomendadas
- `gd` ou `imagick` - Processamento de imagens
- `zip` - Compactação
- `opcache` - Performance

---

## 📦 Instalação {#instalação}

### 1. Clone/Extraia o Projeto
```bash
cd /opt/lampp/htdocs/
# ou: cd /var/www/html/

# Projeto já existe em:
cd TCC_Etec
```

### 2. Instale Dependências Composer
```bash
composer install --no-dev --optimize-autoloader
```

**Dependências principais:**
- `mercadopago/dx-php` - SDK Mercado Pago
- `chillerlan/php-qrcode` - Geração QR Code PIX
- `dompdf/dompdf` - Geração de PDFs
- `phpmailer/phpmailer` - Envio de e-mails

### 3. Configure Banco de Dados

**Opção A: Importar dump completo**
```bash
mysql -u root -p < database/database.sql
```

**Opção B: Criar manualmente**
```sql
CREATE DATABASE e5_royaltech CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE e5_royaltech;
SOURCE database/database.sql;
```

### 4. Configure `.env`
```bash
cp .env.example .env
nano .env
```

```ini
# Database
DB_HOST=localhost
DB_NAME=e5_royaltech
DB_USER=root
DB_PASS=sua_senha_aqui
DB_CHARSET=utf8mb4

# SMTP (produção: use Gmail/SendGrid/Mailgun)
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM=noreply@royaltech.com.br
```

### 5. Gere Chave de Criptografia
```bash
# A chave já existe em .encryption_key
# Se precisar gerar nova:
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;" > .encryption_key
chmod 400 .encryption_key
```

### 6. Configure Permissões
```bash
chmod -R 755 storage/
chmod -R 755 assets/img/
chmod 400 .encryption_key
chmod 600 .env

chown -R www-data:www-data storage/
# ou no XAMPP:
chown -R daemon:daemon storage/
```

---

## ⚙️ Configuração Obrigatória {#configuração-obrigatória}

### ⚠️ IMPORTANTE: Sistema sem fallbacks

A partir da versão 2.0, **o sistema não aceita pedidos sem gateway e frete configurados**.

**Antes:**
- ✅ Pix sem Mercado Pago → Gerava QR Code estático (fallback)
- ✅ Frete sem SuperFrete → Usava tabela estimada (fallback)

**Agora:**
- ❌ Pix sem Mercado Pago → **ERRO: Gateway não configurado**
- ❌ Frete sem SuperFrete → **ERRO: Token não configurado**

### 1. Acesse o Painel Admin
```
http://localhost/TCC_Etec/pages/admin/
```

**Credenciais padrão:**
- E-mail: `admin@royaltech.com.br`
- Senha: `admin123` (altere após primeiro login!)

### 2. Configurações da Loja (Admin > Configurações > Loja)
- Nome da loja
- E-mail de contato
- **CEP de origem** (obrigatório para cálculo de frete)
- Limite frete grátis (padrão: R$ 500)

---

## 💳 Configuração de Gateways {#gateways}

### Mercado Pago (Obrigatório para PIX/Boleto/Cartão)

#### 1. Obter Credenciais

**Desenvolvimento (Sandbox):**
```
https://www.mercadopago.com.br/developers/panel/app/test-accounts
```

**Produção:**
```
https://www.mercadopago.com.br/developers/panel/app
```

**Você precisa de:**
- ✅ **Access Token** (secret key) - Servidor
- ✅ **Public Key** - Cliente (JS tokenização)

#### 2. Configurar no Admin

```
Admin > Configurações > Pagamentos > Mercado Pago

1. Cole o Access Token
2. Cole a Public Key
3. Clique em "Salvar Credenciais"
4. Teste a conexão
```

**Tokens são armazenados criptografados** na tabela `e5_encrypted_settings`.

#### 3. Configurar Webhook (Importante!)

URL do webhook:
```
https://seu-dominio.com/TCC_Etec/api/webhooks/mercadopago.php
```

**No painel Mercado Pago:**
1. Integrações > Notificações
2. Adicionar URL webhook
3. Eventos: `payment.created`, `payment.updated`
4. Copie a **chave secreta** do webhook
5. Cole no admin: Configurações > Pagamentos > Webhook Secret

**⚠️ HMAC obrigatório:** O webhook valida assinatura HMAC-SHA256 do Mercado Pago.

#### 4. Testar Pagamentos

**PIX (teste sandbox):**
- Gera QR Code válido
- Expira em 30 minutos
- Webhook atualiza status automaticamente

**Cartão (teste sandbox):**
```
Número: 5031 4332 1540 6351
CVV: 123
Validade: 11/25
Nome: APRO (aprovado) ou OTHE (recusado)
CPF: 123.456.789-00
```

---

### SuperFrete (Obrigatório para Frete Real)

#### 1. Criar Conta
```
https://sandbox.superfrete.com (teste)
https://superfrete.com (produção)
```

#### 2. Gerar Token API

```
Sandbox: https://sandbox.superfrete.com/integracao
Produção: https://superfrete.com/integracao

Menu > Integrações > Site próprio > Gerar Token
```

#### 3. Configurar no Admin

```
Admin > Configurações > Frete

1. Cole o Token SuperFrete
2. Marque "Modo Sandbox" (para testes)
3. Salve
4. Teste com CEPs reais
```

#### 4. Testar Frete

**CEPs para teste:**
- São Paulo (capital): `01310-100`
- Rio de Janeiro: `20040-020`
- Salvador: `40110-160`

**Retorna:**
- PAC (Correios)
- Sedex (Correios)
- Mini Envios
- Jadlog, Azul Cargo, etc.

**⚠️ Modo Sandbox:**
- Retorna dados reais mas não debita créditos
- Desative para produção!

---

## 🧪 Testes {#testes}

### Checklist de Validação

#### ✅ Teste 1: Carrinho Guest
```
1. Abra navegador anônimo
2. Adicione produto ao carrinho (sem login)
3. Calcule frete (CEP válido)
4. Verifique opções de frete
5. Finalize pedido (guest checkout)
```

**Esperado:**
- Carrinho persiste na sessão
- Frete exibe opções reais (SuperFrete)
- Checkout permite finalizar sem cadastro

#### ✅ Teste 2: Pagamento PIX
```
1. Selecione PIX no checkout
2. Informe CPF do pagador
3. Finalize pedido
```

**Esperado:**
- QR Code gerado (Mercado Pago)
- Código "copia-e-cola" exibido
- Pedido criado com status `pending`
- E-mail de confirmação enviado

#### ✅ Teste 3: Pagamento Cartão
```
1. Selecione Cartão no checkout
2. Preencha dados (use cartão teste)
3. Escolha parcelas
4. Finalize
```

**Esperado:**
- Token gerado via SDK JS
- Pagamento processado
- Status muda para `paid` (se aprovado)
- Comprovante gerado em PDF

#### ✅ Teste 4: Estoque e Concorrência
```
1. Produto com estoque = 1
2. Dois usuários adicionam ao carrinho
3. Ambos tentam finalizar
```

**Esperado:**
- Primeiro: sucesso
- Segundo: erro "Estoque insuficiente"
- `SELECT FOR UPDATE` previne overselling

#### ✅ Teste 5: Webhook
```
1. Crie pedido PIX
2. Pague no sandbox Mercado Pago
3. Aguarde notificação
```

**Esperado:**
- Webhook recebe POST do Mercado Pago
- Valida HMAC (se configurado)
- Atualiza status para `paid`
- Envia e-mail de confirmação

---

## 🔧 Troubleshooting {#troubleshooting}

### Erro: "Gateway não configurado"

**Causa:** Mercado Pago não configurado  
**Solução:**
```
Admin > Configurações > Pagamentos
→ Configure Access Token + Public Key
```

### Erro: "Cálculo de frete não configurado"

**Causa:** SuperFrete não configurado  
**Solução:**
```
Admin > Configurações > Frete
→ Configure Token SuperFrete
```

### Erro: "CSRF token inválido"

**Causa:** Sessão expirou ou cookies desabilitados  
**Solução:**
- Recarregue a página
- Verifique se cookies estão habilitados
- Limpe cache do navegador

### Erro: "Estoque insuficiente"

**Causa:** Race condition ou estoque realmente baixo  
**Solução:**
- Verificar estoque real no admin
- Sistema usa `SELECT FOR UPDATE` (já corrigido)

### Erro: "could not find driver"

**Causa:** PDO MySQL não instalado  
**Solução (Ubuntu/Debian):**
```bash
sudo apt install php-mysql
sudo systemctl restart apache2
```

**Solução (XAMPP):**
```bash
# Verificar php.ini
extension=pdo_mysql
# Reiniciar Apache
```

### Webhook não atualiza pedidos

**Diagnóstico:**
1. Verificar logs: `storage/logs/webhook.log`
2. Testar manualmente:
```bash
curl -X POST http://localhost/TCC_Etec/api/webhooks/mercadopago.php \
  -H "Content-Type: application/json" \
  -d '{"data":{"id":"123"}}'
```

**Causas comuns:**
- URL incorreta no Mercado Pago
- HMAC secret incorreto
- Firewall bloqueando IP do Mercado Pago

---

## 🔒 Segurança em Produção {#segurança}

### Checklist Antes de Deploy

- [ ] Alterar senha do admin
- [ ] Configurar HTTPS (SSL obrigatório)
- [ ] Desativar modo sandbox (SuperFrete)
- [ ] Usar tokens de **produção** (Mercado Pago)
- [ ] Configurar webhook com HMAC
- [ ] Revisar permissões de arquivos (`.env` = 600)
- [ ] Configurar backup automático do banco
- [ ] Ativar logs de erro (`storage/logs/`)
- [ ] Configurar SMTP real (não localhost)
- [ ] Adicionar rate limiting (já implementado)
- [ ] Revisar `.htaccess` (bloquear `.env`, `.git`)

### .htaccess de Produção

```apache
# Já configurado em .htaccess do projeto

# Bloquear acesso a arquivos sensíveis
<Files ".env">
    Require all denied
</Files>

<Files ".encryption_key">
    Require all denied
</Files>

# Forçar HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Headers de segurança
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
```

---

## 📊 Monitoramento

### Métricas Importantes

**Banco de dados:**
```sql
-- Pedidos por status
SELECT status, COUNT(*) FROM e5_orders GROUP BY status;

-- Revenue tracking (MEI)
SELECT * FROM e5_cpf_revenue_tracking WHERE month_year = DATE_FORMAT(NOW(), '%Y-%m');

-- Carrinhos abandonados (últimas 24h)
SELECT COUNT(*) FROM e5_cart 
WHERE updated_at BETWEEN NOW() - INTERVAL 24 HOUR AND NOW();
```

**Logs:**
```bash
# Erros PHP
tail -f storage/logs/error.log

# Webhook
tail -f storage/logs/webhook.log

# Apache
tail -f /var/log/apache2/error.log
```

---

## 🆘 Suporte

- **Documentação:** `DOCUMENTACAO.md`
- **Issues:** Criar issue no repositório Git
- **E-mail:** royaltech.original@gmail.com

---

**Versão do documento:** 2.0  
**Compatível com:** Royal Tech E-commerce 2.0+  
**Data:** 01/09/2026 22:16 BRT
