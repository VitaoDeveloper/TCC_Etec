# ARQUITETURA.md — RoyalTech (TCC ETEC)

> Documento de diagnóstico e plano de evolução. Gerado na Etapa 0.
> Stack: PHP vanilla + PDO, MySQL (InnoDB), HTML/CSS/JS puros, Composer
> (dompdf, phpmailer, guzzle). XAMPP (`http://localhost/TCC_Etec`).

---

## 1. Executivo

O repositório já está **bem adiantado**: o fluxo de compra completo existe e
funciona (carrinho com AJAX, cupom, checkout com frete real SuperFrete,
pagamento simulado com Pix/boleto/cartão, comprovante PDF, perfil completo,
admin com CRUDs). Porém:

1. O tema visual é **escuro premium preto + dourado** (estilo "Royal"),
   com layout/estilo de referência do Mercado Livre. Decisão: **manter escuro**
   (não migrar para o tema claro ML).
2. Vários módulos têm **deficiências pontuais** (listadas abaixo).
3. **Notificações** (fila/templates/cron/gatilhos) e **envios SuperFrete**
   (etiqueta, cancelamento, rastreio, processamento de webhook) **já estão
   implementadas** (fases 9 e 10 concluídas). WhatsApp segue desativado por
   falta de provedor (canal enfileirado como `skipped`).
4. **Testes minuciosos + dados reais + README** (fase 11) **concluídos**:
   - E2E completo (cadastro→login→carrinho→checkout→pagamento→cancelamento)
   - Seed real: produtos com imagens, embalagens, pesos, cupons, banners SVG
   - README atualizado (stack PDO, 24 tabelas, credenciais corretas, testes 44)
   - Docker atualizado (Dockerfile multi-ext, compose com healthchecks, volumes)
5. Tabelas: `e5_order_status_history`, `e5_shippings`, `e5_notifications`,
   `e5_notifications_log` e `e5_admin_notifications` **já foram criadas** e
   refletidas em `database/database.sql` (a `superfrete_webhook_log` e a
   `e5_settings` já existiam).

---

## 2. Mapa do código

```
components/header.php             header ML (escuro) + busca + mega menu + breadcrumb
components/footer.php             rodapé institucional (links # mortos)
components/product-card.php       card de produto ML
index.php                         home (banners, destaques, novidades, newsletter)
pages/products/                   produtos/lista, detalhe, categorias, contato, sobre
pages/cart/                       carrinho, cupom, checkout, pagamento (+ajax add/update/remove)
pages/wishlist/                   lista de favoritos + toggle AJAX
pages/auth/                       login, register, profile, orders, order-detail,
                                  forgot/reset-password, comprovante-resend
pages/admin/                      dashboard + 14 módulos de gestão
pages/download-comprovante.php    stream do PDF
database/database.sql             schema completo (e5_*) + seeds (produtos, embalagens, cupons, banners)
database/connection.php           conexão + auto-provisionamento do schema
includes/*.php                    20+ helpers (carrinho, cupom, endereço, cartão, pedido,
                                  timeline, comprovante, nf, review, mail, csrf, rate-limit,
                                  shipping_functions, notifications_functions...) 
src/                              SuperFreteClient, SuperFreteException, WebhookHandler
webhook/superfrete.php            endpoint de webhook (HMAC + idempotência)
scripts/demo.php                  script de demonstração SuperFrete
scripts/notifications-worker.php  worker da fila de notificações (cron)
tests/                            PHPUnit 44 testes (SuperFrete + templates de notificação)
assets/css/                       style.css, mercadolivre-style.css, auth.css,
                                  account.css, admin.css (todos tema escuro)
assets/js/script.js               helpers globais + CSRF
.docker/vhost.conf                Apache vhost com security headers + cache estático
Dockerfile                        PHP 8.3 + PDO + GD + intl + opcache + zip
docker-compose.yml                app/db/phpmyadmin/mailpit com healthchecks
```

---

## 3. Banco de dados — estado e lacunas

### 3.1 Tabelas existentes (e5_*)
| Tabela | Observação |
|---|---|
| `e5_users` | Cliente/admin. Já tem avatar, preferências, CPF, telefone. |
| `e5_categories`, `e5_package_sizes`, `e5_products`, `e5_product_images` | CRUD completo |
| `e5_orders` + `e5_order_items` | Status + payment_status, NF, comprovante, rastreio |
| `e5_cart`, `e5_wishlist`, `e5_reviews` | Índices únicos por usuário/produto |
| `e5_user_addresses` | Multi-endereço (default, ViaCEP) |
| `e5_saved_cards` | Só bandeira + últimos 4 (sem dados sensíveis) |
| `e5_coupons` | percent/fixed, escopo new/vip/all, limites |
| `e5_contacts`, `e5_newsletter`, `e5_banners` | Contato, newsletter, banners |
| `e5_password_reset_tokens` | Reset de senha |
| `e5_settings` | Config da loja (fallback cost, thresholds, pix...) |
| `superfrete_webhook_log` | Idempotência de webhook (event_id único) |
| `e5_order_status_history` | Histórico/timeline de status do pedido (auditável) |
| `e5_shippings` | Envios SuperFrete: etiqueta, PDF, transportadora, rastreio, status, cancelamento |
| `e5_notifications` | Fila de notificações (canal, template, status, tentativas, dedupe) |
| `e5_notifications_log` | Log de cada tentativa de envio |
| `e5_admin_notifications` | Notificações persistidas do sino do admin (lido/não-lido, dedupe) |

### 3.2 Tabelas criadas (antes faltantes)
- `e5_order_status_history` ✅ — histórico de troca de status (timeline auditável).
- `e5_shippings` ✅ — envios SuperFrete: id da etiqueta, URL do PDF, transportadora,
  código de rastreio, custo, status na SuperFrete, id de cancelamento.
- `e5_notifications` ✅ — fila de notificações (canal email/whatsapp, template, status,
  tentativas, próximo envio, dedupe de eventos).
- `e5_notifications_log` ✅ — log bruto de cada tentativa (erro/resposta).
- `e5_admin_notifications` ✅ — sino do admin (pedido/contato/estoque baixo).

### 3.3 Problemas de chave estrangeira (risco de 500 ao excluir) — ✅ tratados
- `fk_orders_user` — cliente com pedido: delete bloqueado com aviso amigável.
- `fk_products_category` — categoria com produto: delete bloqueado com aviso.
- `fk_order_items_product` — produto com item de pedido: delete bloqueado com aviso.

**Tratamento feito:** em vez de `CASCADE` (que apagaria histórico), os
deletes de categoria/produto/cliente tratam a exceção de FK (`catch
PDOException` 23000) e devolvem mensagem amigável. Não há mais
`database/migrations/` — o schema canônico é `database/database.sql`.

---

## 4. Diagnóstico por módulo

> Legenda: ✅ funciona hoje · ⚠️ parcial / com bug · ❌ não implementado

### 4.1 Storefront / compra
| Módulo | Estado | Observação |
|---|---|---|
| Home | ✅ | Banners, destaques, novidades, newsletter (CSRF) |
| Lista de produtos | ✅ | Filtros, ordenação, paginação 12/pág, busca |
| Detalhe do produto | ✅ | Galeria, reviews (só compra paga), qty-stepper, estepe |
| Carrinho | ✅ | Qtd AJAX + validação de estoque, remover, cupom, recalc server |
| **Salvar para depois** | ✅ | `saved_for_later` no `e5_cart`; bloco "Salvos para depois", mover/restaurar com revalidação de estoque, some do total e do badge |
| **Checkout limpa só o comprado** | ✅ | `cartRemoveItems()` remove apenas os itens do pedido (antes apagava o carrinho inteiro) |
| Favoritos | ✅ | Toggle AJAX + contador no header; coração já vem preenchido (`is-active`) e, na página de favoritos, desfavoritar remove o card da lista |
| Checkout | ✅ | Frete real SuperFrete + fallback, cupom, endereço, Pix/boleto/cartão, transação, baixa estoque |
| Pagamento | ✅ | BR Code EMV real (TLV + CRC16-CCITT) gerado da chave/config; QR de verdade renderizado no navegador (lib local); polling e simulador admin funcionam. Linha digitável do boleto segue simulada |
| Detalhes do pedido | ✅ | Timeline + histórico persistido em `e5_order_status_history` (cliente e admin); total exibido usa `order.total` com linha de descontos; rastreio, PDF comprovante, reenviar e-mail, cancelar, comprar de novo |
| Perfil | ✅ | Dados, endereços (CRUD/ViaCEP/default), cartões, senha (password_hash), preferências, avatar (crop) |
| **Faltas perfil** | ✅ | CPF validado por dígitos verificadores; avatar validado por MIME real (finfo + getimagesize) e uploads sem execução de script |

### 4.2 Admin
| Módulo | Estado | Observação |
|---|---|---|
| Dashboard | ✅ | Agregados; sem recorte de período |
| Produtos | ✅ | CRUD ok; upload valida MIME real + limite 2 MB; delete FK-safe (bloqueia vínculo em pedido); busca nome/marca + paginação |
| Categorias | ✅ | Delete FK-safe (bloqueia categoria com produtos); busca + paginação |
| Embalagens | ✅ | CRUD ok |
| Cupons | ✅ | CRUD ok; busca por código + paginação |
| Pedidos | ✅ | Busca (#ID/cliente/e-mail) + paginação preservando filtro de status; toda transição grava histórico e `paid` sincroniza `payment_status`; **falta envio de e-mail por status (fase 10)** |
| Clientes | ✅ | Busca + paginação; exportação CSV real (`customers-export.php`); delete FK-safe (bloqueia cliente com pedidos) |
| Contatos / Newsletter | ✅ | Listagem + delete; exportação CSV real (newsletter) |
| Banners | ✅ | CRUD ok; upload valida MIME real + limite 2 MB |
| Relatórios | ✅ | Exportação PDF real via Dompdf (`reports-export.php`); **falta filtro de período** |
| Configurações | ✅ | `frete_fallback_*` no form; uploads logo/favicon com MIME real + limite 2 MB; save atualiza só chaves enviadas |
| Notificações do sino | ✅ | Persistidas em `e5_admin_notifications` (novos pedidos/contatos/estoque baixo, dedupe); lido/não-lido; página `notifications.php`, abrir (`notification-open.php`) e marcar todas (`notifications-read-all.php`) |
| Paginação/busca | ✅ | Helper `includes/pagination.php` + `assets/css/admin.css` reutilizado nas listagens |

### 4.3 SuperFrete
| Item | Estado |
|---|---|
| Cliente HTTP (Guzzle) | ✅ `src/SuperFreteClient.php` (endpoints v0 completos) |
| Cotação no checkout | ✅ `calculateShipping` + fallback + embalagens (helper em `includes/shipping_functions.php`) |
| Cache de cotação | ✅ curto (5 min) por origem+CEP+produtos em `storage/cache/shipping/` |
| Retry com backoff | ✅ 3 tentativas (1+2) com backoff exponencial em erros transitórios (timeout/5xx/429) |
| **Geração de etiqueta** | ✅ admin `order-detail.php` → `shippingCreateLabel` (POST /cart), grava em `e5_shippings` |
| **Pagamento da etiqueta** | ✅ `shippingPayLabel` (POST /checkout, saldo da carteira) |
| **Impressão (PDF)** | ✅ `shippingPrintLabel` (POST /tag/print) + link da etiqueta na UI |
| **Cancelamento de etiqueta** | ✅ `shippingCancelLabel` (POST /order/cancel) |
| **Rastreamento pós-envio** | ✅ `shippingRefresh` (GET /order/info) + sync de status/tracking do pedido |
| Webhook receive | ✅ HMAC-SHA256 + idempotência atômica (log + processamento em transação) |
| **Webhook `processEvent`** | ✅ mapeia evento → `e5_shippings` + `e5_orders` + histórico (posted/delivered/canceled) |
| **webhook/superfrete.php status HTTP** | ✅ respeita 400/401/500 do handler (não mascara erro; permite retry) |

### 4.4 Notificações / e-mail
| Gatilho | Estado |
|---|---|
| Pedido criado (comprovante) | ✅ (e-mail com PDF) |
| Reset de senha | ✅ |
| Reenvio manual | ✅ (admin e cliente) — reutiliza o PDF existente e usa o cliente SMTP único (`sendMailWithAttachment`) |
| **Pagamento aprovado** | ✅ `order_paid` (admin confirmar pagamento / lista de pedidos / simulação) |
| **Pagamento recusado/expirado** | ✅ `order_failed` e `order_expired` |
| **Mudança de status (admin)** | ✅ `order_shipped` (com rastreio), `order_delivered`, `order_canceled`, `order_refunded`; também via webhook SuperFrete (posted/delivered/cancelled) |
| **Lembrete de Pix/boleto (cron)** | ✅ `order_reminder` (janela de 24h, dedupe diário) |
| **Boas-vindas / contato** | ✅ `user_welcome` (cadastro) e `contact_received` (auto-resposta) |
| Fila/templates dedicados | ✅ `includes/notifications_functions.php` + worker `scripts/notifications-worker.php` (enfileira, envia, retry com backoff, log) |
| Fallback do comprovante | ✅ `order_created` enfileirado quando o comprovante não pôde ser enviado |
| Prévia no painel | ✅ painel `notifications.php` mostra stats + últimas entradas da fila |
| WhatsApp | ⏸️ desativado (sem provedor); preferência gera registro `skipped` rastreável |

### 4.5 Bugs e "botões mortos" confirmados (lista de cortesia p/ correção)
> Diagnóstico original. Estado atual de cada módulo: ver §4.2/§4.3/§4.4.
| Onde | Problema |
|---|---|
| `footer.php` | Links institucionais `#` (Trabalhe Conosco, Termos, Privacidade, FAQ, Frete, Trocas) |
| `pages/admin/customers.php:55` | ✅ Resolvido — exportação CSV real (`customers-export.php`) |
| `pages/admin/reports.php` | ✅ Exportação PDF real (`reports-export.php`); **falta filtro de período** |
| `pages/admin/orders.php:8-20` | ✅ Sync de `payment_status` + envio de e-mail por status (fase 10) |
| `webhook/superfrete.php:49-51` | ✅ Resolvido — devolve o status do handler (400/401/500/200) |
| `src/Webhook/WebhookHandler.php:216-228` | ✅ Resolvido — `processEvent` sincroniza envio/pedido/histórico |
| `pages/admin/banners.php` | ✅ Resolvido — upload valida MIME real + 2 MB |
| `includes/config.php` | ✅ Resolvido — `serialize_precision=-1` evita ruído em JSON monetário |
| `pages/auth/forgot-password.php` | ✅ Resolvido — link de reset usa `app_base_url()` (HTTP em dev) |
| `database/database.sql` | ✅ Resolvido — seed real: produtos com embalagens/pesos, cupons, banners SVG |
| Newsletter pública | Sem formulário de inscrição no site (tabela `e5_newsletter` só lida no admin) — gatilho de e-mail não se aplica ainda |
| `pages/cart/payment.php` | QR Pix decorativo (sem cobrança real) |
| `includes/comprovante_functions.php:59,354` | Pix fixo em 5% (ignora `pix_discount_percent`) |
| `includes/comprovante_functions.php:365-384` | SMTP duplicado (usar `mailer()`) |
| CSS | Tema escuro/dourado em todos os arquivos (ver Etapa 1) |

---

## 5. Plano de execução (fases)

| Fase | Escopo | Saída |
|---|---|---|
| 0 | Este diagnóstico | ✅ ARQUITETURA.md |
| 1 | Design system (tokens/base/componentes/página) + header/footer | **Tema escuro premium (preto #1a1a1a + dourado #d4af37)** com layout ML; tokens em `assets/css/tokens.css` |
| 2 | Banco: `order_status_history`, `shippings`, `notifications`, `notifications_log` + migrations reversíveis | SQL + migração |
| 3 | Carrinho: salvar p/ depois, recalcs, robustez | ✅ Carrinho 100% |
| 4 | Favoritos: ajustes finos (estado ativo + remoção na lista) | ✅ Favoritos 100% |
| 5 | Perfil: validação real de CPF, MIME no avatar | ✅ Perfil 100% |
| 6 | Checkout/pagamento: Pix mais realista, fluxos de erro | ✅ Checkout 100% |
| 7 | Detalhes do pedido: timeline persistida, PDF/reenvio consistentes | ✅ Pedido 100% |
| 8 | Admin: paginação, busca, exportações reais, deletes com FK tratada, settings completas, notificações persistidas | ✅ Admin 100% |
| 9 | SuperFrete: geração/cancelamento de etiqueta, rastreio, cache+retry, webhook processando eventos | ✅ SuperFrete 100% |
| 10 | Notificações: tabelas, fila, templates, cron, todos os gatilhos | ✅ Notificações 100% (e-mail; WhatsApp desativado) |
| 11 | Testes minuciosos + dados reais + README | ✅ Validação final (E2E, seed real, README, Docker) |

---

## 6. Regras técnicas vigentes (manter)
- PDO + prepared statements em 100% das queries.
- `htmlspecialchars` em toda saída; token CSRF em todo POST/AJAX.
- Senhas com `password_hash / password_verify`.
- Sessão admin checada em todas as páginas do painel.
- Comentários em português (TCC legível em banca).
- Sem CDN indevido; sem dados falsos em tela (estado vazio real).
- Credenciais só em `.env` (fora do versionamento) — nada hardcoded.
- Fuso horário único: PHP em `America/Sao_Paulo` (`includes/config.php`) e sessão MySQL em `-03:00` (`database/connection.php`), para que datas geradas em PHP e `NOW()`/`created_at` sejam comparáveis.

---

## 7. Operação — fila de notificações (cron)
- O site apenas **enfileira** eventos em `e5_notifications`; o envio ocorre no worker.
- Execução manual/ testes:
  `php scripts/notifications-worker.php --dry-run` (não envia) e `php scripts/notifications-worker.php --limit=50`.
- Crontab recomendado (a cada 5 min):
  `*/5 * * * * /opt/lampp/bin/php /opt/lampp/htdocs/TCC_Etec/scripts/notifications-worker.php >> /opt/lampp/htdocs/TCC_Etec/storage/logs/notifications-cron.log 2>&1`
- Acesso HTTP ao worker é bloqueado (403) — só roda em CLI.
- Configurações em **Admin → Configurações**: URL do site (`store_url`, usada nos links dos e-mails) e o toggle "Enviar notificações transacionais por e-mail" (`notif_email_enabled`).
- Falha de envio: backoff exponencial (60s/120s/240s) até `max_attempts` (3), virando `failed`; cada tentativa é registrada em `e5_notifications_log`.