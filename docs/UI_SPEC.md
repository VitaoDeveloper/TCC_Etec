# Especificação Visual/Funcional — Área da Conta (Royal Tech)

**Fonte de verdade** — Este documento consolida as mensagens anteriores desta tarefa. Não improvisar layout, textos, cards ou navegação. Onde a especificação for omissa, perguntar ou seguir o esqueleto abaixo. Nada de funcionalidades "extras" visuais.

---

## 0. Regras Gerais
- **Sem improvisar**: seguir exatamente o esqueleto, textos, ordem e nomenclatura abaixo.
- **Um commit por etapa**.
- **Testes verdes** antes de declarar concluído.
- **Screenshots headless** (1440px e 390px) das 3 telas ao final.

---

## 1. Sidebar (todas as telas de conta) — exatamente assim

**Card 1 (identidade)**:
- Avatar circular (iniciais como fallback, anel dourado, badge de câmera dourado no canto — clicável para trocar a foto)
- Nome (quebra em 2 linhas, sem "…")
- E-mail
- Pill `ADMINISTRADOR` só para admin

**Card 2 (menu)** — 3 itens **nesta ordem**, SEM títulos de seção:
```
[Meu Perfil] [Meus Pedidos] [Sair]
```
- Sem "CONTA", sem "COMPRAS", sem "Sair da Conta"

**Breadcrumb** visível em largura total logo abaixo da nav (fundo `#2d2d2d`):
- "Início / Meu Perfil"
- "Início / Meus Pedidos"
- "Início / Detalhes do Pedido"

---

## 2. Perfil — REMOVER o card "Menu"/abas e o card "Meu avatar". Página única rolável.

**Título**: "Meu Perfil"  
**Subtítulo**: "Gerencie seus dados pessoais e preferências atualizadas"

**Esqueleto (apenas estas seções, nesta ordem)**:

```html
<main class="account-content">
  <header class="page-head">
    <h1>Meu Perfil</h1>
    <p>Gerencie seus dados pessoais e preferências atualizadas</p>
  </header>

  <section class="card">  <!-- Dados Pessoais -->
    <div class="card-header">
      <span class="card-icon"><i class="fas fa-id-card"></i></span>
      <div>
        <h2>Dados Pessoais</h2>
        <p class="card-subtitle">Informações da sua conta e identificação</p>
      </div>
    </div>
    <div class="card-body">
      <div class="grid">
        <label>NOME COMPLETO</label>
        <input type="text" name="name" required>
        
        <label>CPF</label>
        <input type="text" name="cpf" required>
        
        <label>E-MAIL</label>
        <input type="email" name="email" required>
        
        <label>NOME DE USUÁRIO</label>
        <input type="text" name="username" required>
        
        <label>TELEFONE / CELULAR</label>
        <input type="tel" name="phone">
        
        <label>SENHA</label>  <!-- rótulo "SENHA", NÃO "Senha atual" -->
        <input type="password" disabled value="••••••" readonly>
      </div>
    </div>
  </section>

  <section class="card">  <!-- Endereço -->
    <div class="card-header">
      <span class="card-icon"><i class="fas fa-map-marker-alt"></i></span>
      <div>
        <h2>Endereço</h2>
        <p class="card-subtitle">Onde você recebe as suas compras</p>
      </div>
    </div>
    <div class="card-body">
      <div class="grid">
        <label>CEP</label>
        <div class="input-group">
          <input type="text" name="postal_code">
          <button type="button" id="cepLookup">Buscar</button>
        </div>
        <label>NÚMERO</label>
        <input type="text" name="number" required>
        
        <label>RUA</label>
        <input type="text" name="street" required>
        
        <label>COMPLEMENTO</label>
        <input type="text" name="complement">
        
        <label>BAIRRO</label>
        <input type="text" name="neighborhood" required>
        
        <label>CIDADE</label>
        <input type="text" name="city" required>
        
        <label>UF</label>
        <select name="state" required>
          <option value="">—</option>
          <option value="AC">AC</option> ...etc
        </select>
      </div>
    </div>
  </section>

  <section class="card">  <!-- Endereços Salvos -->
    <div class="card-header">
      <span class="card-icon"><i class="fas fa-map-marked-alt"></i></span>
      <div>
        <h2>Endereços Salvos</h2>
        <p class="card-subtitle">Gerencie múltiplos endereços de entrega</p>
      </div>
    </div>
    <div class="card-body">
      <!-- empty-state tracejado + botão "+ Adicionar endereço" (modal) -->
      <div class="empty-state">
        <p>Nenhum endereço salvo.</p>
        <button type="button" class="btn-outline" data-modal="address">+ Adicionar endereço</button>
      </div>
    </div>
  </section>

  <section class="card">  <!-- Alterar Senha -->
    <div class="card-header">
      <span class="card-icon"><i class="fas fa-lock"></i></span>
      <div>
        <h2>Alterar Senha</h2>
        <p class="card-subtitle">Atualize sua senha de acesso</p>
      </div>
    </div>
    <div class="card-body">
      <div class="grid grid-3">
        <label>SENHA ATUAL</label>
        <input type="password" name="current_password" required>
        
        <label>NOVA SENHA</label>
        <div class="input-group">
          <input type="password" name="new_password" minlength="6" maxlength="72" required>
          <button type="button" class="toggle-visibility" aria-label="Mostrar/ocultar senha"><i class="fas fa-eye"></i></button>
        </div>
        
        <label>CONFIRMAR NOVA SENHA</label>
        <input type="password" name="confirm_password" minlength="6" maxlength="72" required>
      </div>
      <button type="submit" class="btn-primary" form="password-form">Alterar senha</button>
    </div>
  </section>

  <section class="card">  <!-- Preferências de Notificação -->
    <div class="card-header">
      <span class="card-icon"><i class="fas fa-bell"></i></span>
      <div>
        <h2>Notificações</h2>
        <p class="card-subtitle">Como você quer ser avisado</p>
      </div>
    </div>
    <div class="card-body">
      <!-- 2 toggles dourados que salvam sozinhos (AJAX), sem botão -->
      <div class="toggle-row">
        <span>Notificações por e-mail</span>
        <label class="toggle-gold"><input type="checkbox" name="notify_email" value="1"><span></span></label>
      </div>
      <div class="toggle-row">
        <span>Notificações por WhatsApp</span>
        <label class="toggle-gold"><input type="checkbox" name="notify_whatsapp" value="1"><span></span></label>
      </div>
    </div>
  </section>

  <div class="actions">
    <button type="submit" class="btn-gold" form="profile-form">Salvar Alterações</button>
    <a href="/TCC_Etec/pages/auth/orders.php" class="btn-outline">Meus Pedidos</a>
  </div>  <!-- grava Dados Pessoais + Endereço -->

  <section class="card">  <!-- Cartões Salvos -->
    <div class="card-header">
      <span class="card-icon"><i class="fas fa-credit-card"></i></span>
      <div>
        <h2>Cartões Salvos</h2>
        <p class="card-subtitle">Métodos de pagamento para compras rápidas</p>
      </div>
    </div>
    <div class="card-body">
      <!-- empty-state + "+ Adicionar cartão" (modal) -->
      <div class="empty-state">
        <p>Nenhum cartão salvo.</p>
        <button type="button" class="btn-outline" data-modal="card">+ Adicionar cartão</button>
      </div>
    </div>
  </section>
</main>
```

**Regras de comportamento**:
- **UM único botão "Salvar Alterações"** para Dados Pessoais + Endereço. Remover "Salvar dados", "Salvar endereço", "Salvar preferências".
- A troca de senha continua com botão próprio; notificações salvam ao alternar (AJAX).
- **Remover o campo duplicado "SENHA ATUAL"** que aparece dentro do card de segurança como campo desabilitado; só existe o desabilitado "SENHA" em Dados Pessoais.
- **Remover textos de ajuda fixos** sob os campos ("JPG, PNG ou WebP...", "Sua senha nunca aparece por aqui..."). Erros só aparecem em validação.
- **Foto**: clique no badge de câmera da sidebar (sem botão "Trocar foto" e sem card de avatar).
- **Toggle do WhatsApp**: rótulo "Notificações por WhatsApp"; e-mail: "Notificações por e-mail" (sem textos longos).
- **CONSERTAR o CSS de `.card-header`**: ícone (quadrado 34×34 dourado translúcido) | bloco com título Playfair alinhado à **ESQUERDA** e subtítulo muted abaixo. Hoje o título está centralizado e distante do ícone — bug de alinhamento (provavelmente justify/flex mal aplicado).

---

## 3. Meus Pedidos — REMOVER os 4 cards de política de cancelamento

**Esqueleto**:
- Título "Meus Pedidos" + contador "N pedido(s)" à direita
- Chips de filtro por status com contadores (manter o que já existe)
- Campo de busca por nº
- **UMA tabela dentro de UM único card** (sem card dentro de card):

```
PEDIDO | DATA | ITENS | TOTAL | STATUS | AÇÃO (olho → order-detail.php?id=N)
```

- Paginação 10/página
- Sem botões "Meu Perfil"/"Meus Contatos" no rodapé
- Status sempre pelo mapa central em português
- A regra de cancelamento (quando pode cancelar) fica **só como informação discreta** dentro de `order-detail.php` ao lado do botão "Cancelar pedido", em uma frase.

---

## 4. Detalhes do Pedido e restante (ainda NÃO entregue — confirmar o status de cada item)

**order-detail.php completo** com:
- Progresso alimentado pelo histórico (timeline)
- Faixa vermelha "Pedido cancelado" quando aplicável
- Itens com snapshot (nome, qtd, preço unitário, total)
- Cards **Entrega** e **Pagamento** lado a lado
- Botões: **Voltar** / **Baixar comprovante** / **Reenviar por e-mail** + **Pagar agora** / **Cancelar** / **Comprar novamente** / **Rastrear** conforme o estado

**PDF do comprovante** (dompdf)

**Reenvio de e-mail** com rate limit

**Modais de endereços e cartões** ligados às APIs

**worker.php** (fila de e-mails + expiração de Pix em 30 min + lock contra execução dupla)

**E-mails por mudança de status** respeitando preferências

---

## 5. Verificação obrigatória antes de dizer "concluído"

**Checklist**:
- [ ] Testes verdes (`php vendor/bin/phpunit`)
- [ ] Título "Meu Perfil"
- [ ] Sem card Menu/abas/avatar na página de perfil
- [ ] Sidebar com 3 itens sem seções
- [ ] Breadcrumb presente nas 3 telas
- [ ] Um único "Salvar Alterações" no perfil
- [ ] Nenhum campo "SENHA ATUAL" duplicado
- [ ] Card-header alinhado (ícone à esquerda, título Playfair à esquerda)
- [ ] Meus Pedidos sem cards de política e sem card aninhado
- [ ] Nenhum texto de status em inglês
- [ ] order-detail?id=12 igual à especificação
- [ ] worker.php roda via CLI

**Screenshots headless** (1440px e 390px) das 3 telas, com descrição comparada ao checklist.

**Um commit por etapa**. Não declarar "concluído" se algum item do checklist falhar.