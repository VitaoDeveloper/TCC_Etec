# 🚀 Guia de Migração CPF → MEI — Royal Tech

**Última atualização:** 27 de agosto de 2026

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Pré-requisitos](#pré-requisitos)
3. [Passo a Passo](#passo-a-passo)
4. [Checklist Completo](#checklist-completo)
5. [Economia Esperada](#economia-esperada)
6. [Troubleshooting](#troubleshooting)
7. [Rollback](#rollback)

---

## 🎯 Visão Geral

Este guia orienta a **migração do regime CPF para MEI** no sistema Royal Tech sem necessidade de alteração de código. Todo o processo é feito pelo painel administrativo.

### **O que muda com a migração?**

| Item | CPF (Atual) | MEI (Após migração) |
|------|-------------|---------------------|
| **Nota Fiscal** | ❌ Não emitida | ✅ Emitida automaticamente |
| **Taxa Gateway** | 3.99% | 2.99% (economia de R$ 500/mês) |
| **Frete** | Tabela pública | Tabela comercial (10-25% desconto) |
| **Documento** | CPF | CNPJ |
| **Limite de Faturamento** | Sem formalização | R$ 81.000/ano |

---

## ✅ Pré-requisitos

Antes de ativar o MEI no sistema, você precisa ter:

1. ✅ **MEI aberto** (via Portal do Empreendedor)
2. ✅ **CNPJ** em mãos
3. ✅ **Razão Social** e **Nome Fantasia**
4. ✅ **Inscrição Estadual** (se aplicável)
5. ⚠️ **Opcional (mas recomendado):**
   - Conta no provedor de NF-e (Focus NFe ou NFe.io)
   - Conta PJ no Melhor Envio
   - Token de API do Melhor Envio

---

## 🔧 Passo a Passo

### **1. Acessar o Painel Admin**

```
URL: http://localhost/TCC_Etec/pages/admin/login.php
Login: admin@royaltech.com
Senha: password123
```

### **2. Navegar para Central de Migração MEI**

Menu lateral → **"Central de Migração MEI"**  
ou acesse diretamente: `http://localhost/TCC_Etec/pages/admin/mei-migration.php`

### **3. Preencher o Formulário de Ativação**

#### **Dados Obrigatórios:**
- **CNPJ:** `12.345.678/0001-90`
- **Razão Social:** `Royal Tech Comércio de Eletrônicos LTDA`

#### **Dados Opcionais:**
- **Nome Fantasia:** `Royal Tech`
- **Inscrição Estadual:** `ISENTO` (ou número se aplicável)

#### **Configuração de NF-e:**
- **Provedor:** Focus NFe ou NFe.io (ou deixe "Não ativar agora")
- **Chave API:** Token fornecido pelo provedor

#### **Configuração de Frete:**
- **Token Melhor Envio:** (opcional, pode configurar depois)

### **4. Clicar em "Ativar Regime MEI"**

O sistema automaticamente:
- ✅ Atualiza o regime tributário para MEI
- ✅ Ativa a emissão de NF-e (se provedor configurado)
- ✅ Muda para tabela comercial de frete
- ✅ Aplica taxa de gateway PJ (2.99% em vez de 3.99%)

---

## 📊 Checklist Completo

Use este checklist para garantir que todos os passos externos foram concluídos:

### **Sistema Royal Tech (Automático)**
- [x] Tabela `e5_seller_profile` criada
- [x] Campos de invoice na tabela `e5_orders`
- [x] Função `emitirNotaFiscal()` implementada
- [x] Cálculo de frete com flag `tax_regime`
- [x] Taxas de pagamento dinâmicas
- [x] Painel de migração MEI

### **Ações Manuais (Você precisa fazer)**

#### **1. Provedor de NF-e (Focus NFe ou NFe.io)**
- [ ] Cadastrar conta no provedor
- [ ] Configurar CNPJ no painel do provedor
- [ ] Gerar token de API
- [ ] Inserir token no painel Royal Tech

#### **2. Melhor Envio (Frete)**
- [ ] Criar conta PJ no Melhor Envio
- [ ] Informar CNPJ: `12.345.678/0001-90`
- [ ] Gerar token de API (Configurações > API)
- [ ] Inserir token no painel Royal Tech > Configurações

#### **3. Gateway de Pagamento (Mercado Pago/Asaas)**
- [ ] Acessar painel do gateway
- [ ] Atualizar documento de CPF para CNPJ
- [ ] Verificar se taxa PJ foi aplicada (deve cair de ~3.99% para ~2.99%)
- [ ] Testar webhook de notificação após mudança

#### **4. Testes Finais**
- [ ] Criar pedido teste (R$ 100,00)
- [ ] Verificar se NF-e foi emitida automaticamente
- [ ] Checar se frete comercial está ativo (valores menores)
- [ ] Confirmar taxa de gateway reduzida

---

## 💰 Economia Esperada

### **Projeção para R$ 50.000/mês de faturamento:**

| Item | CPF | MEI | Economia |
|------|-----|-----|----------|
| Taxa de Gateway | 3.99% (R$ 1.995) | 2.99% (R$ 1.495) | **R$ 500/mês** |
| Frete médio | R$ 20,00 | R$ 17,00 | **R$ 3,00/pedido** |
| **Total Mensal** | **R$ 1.995** | **R$ 1.495** | **R$ 500** |
| **Total Anual** | **R$ 23.940** | **R$ 17.940** | **R$ 6.000** |

> 💡 **Dica:** A economia anual de R$ 6.000 cobre facilmente o custo de um contador especializado!

---

## 🔧 Troubleshooting

### **Problema 1: "CNPJ inválido" ao ativar MEI**
**Solução:** Verifique o formato: `00.000.000/0000-00` (com pontos, barra e hífen)

### **Problema 2: NF-e não está sendo emitida**
**Causas possíveis:**
1. Provedor de NF-e configurado como "disabled"
2. Token da API inválido ou expirado
3. CNPJ não cadastrado no provedor

**Solução:**
- Acesse: Configurações > NF-e
- Verifique o provedor selecionado
- Teste a conexão com a API do provedor

### **Problema 3: Taxas não foram reduzidas**
**Causa:** Gateway de pagamento ainda não reconhece o CNPJ

**Solução:**
- Acesse o painel do Mercado Pago/Asaas
- Atualize manualmente o documento para CNPJ
- Aguarde 24-48h para aplicação das taxas PJ

### **Problema 4: Frete comercial não está ativo**
**Causa:** Token do Melhor Envio não configurado

**Solução:**
1. Criar conta PJ no Melhor Envio
2. Gerar token: Configurações > API > Gerar Token
3. Inserir no painel: Configurações > Frete > Token Melhor Envio

### **Problema 5: Validação de token Focus NFe no Sandbox (Homologação)**
**Causa:** O Focus NFe pode retornar HTTP 401 para tokens inválidos em ambos os endpoints, mas o comportamento do sandbox difere do produção:

| Ambiente | Endpoint válido | Retorno token inválido | Retorno token válido |
|----------|----------------|----------------------|---------------------|
| **Homologação** (sandbox) | `POST /v2/nfe` | 401 | 422 (erro validação NFe) |
| **Produção** | `GET /v2/empresas` | 401 | 200 (dados empresa) |

> ⚠️ **Limitação do provedor:** O sandbox do Focus NFe **não suporta** o endpoint `/v2/empresas` — sempre retorna 404 independente da credencial. O health check corretamente usa `POST /v2/nfe` no sandbox para validar tokens.

**Como obter token sandbox:**
1. Acesse: https://homologacao.focusnfe.com.br/dashboard
2. Vá em: Configurações > API > Tokens de Acesso
3. Copie o token e insira no painel: Central de Migração MEI > Chave API do Provedor

**Validação:** O sistema valida tokens em tempo real antes de ativar. Se o token for inválido, a ativação é bloqueada automaticamente.

---

## ⏪ Rollback (Voltar para CPF)

Se precisar reverter a migração:

### **Via Painel Admin:**
1. Acesse: Central de Migração MEI
2. Role até o final da página
3. Clique em: **"Voltar para CPF"**
4. Confirme a ação

### **Via Banco de Dados (Manual):**
```sql
-- Reverter seller_profile
UPDATE e5_seller_profile 
SET tax_regime = 'CPF', 
    nfe_enabled = 0,
    document_type = 'CPF',
    document_number = '000.000.000-00',
    legal_name = NULL,
    trade_name = NULL
WHERE is_active = 1;

-- Reverter settings
UPDATE e5_settings SET setting_value = 'CPF' WHERE setting_key = 'tax_regime';
UPDATE e5_settings SET setting_value = 'disabled' WHERE setting_key = 'nfe_provider';
UPDATE e5_settings SET setting_value = 'public' WHERE setting_key = 'melhor_envio_table';
```

> ⚠️ **Atenção:** Pedidos com NF-e já emitida **não podem** ter a nota cancelada automaticamente. Isso deve ser feito manualmente no provedor dentro de 24h.

---

## 📞 Suporte

### **Provedores de NF-e:**
- **Focus NFe:** https://focusnfe.com.br/doc/
- **NFe.io:** https://nfe.io/docs/

### **Melhor Envio:**
- **Documentação API:** https://docs.melhorenvio.com.br/

### **Gateways de Pagamento:**
- **Mercado Pago:** https://www.mercadopago.com.br/developers/
- **Asaas:** https://docs.asaas.com/

---

## 🎓 Glossário

- **MEI:** Microempreendedor Individual
- **NF-e:** Nota Fiscal Eletrônica
- **CNPJ:** Cadastro Nacional da Pessoa Jurídica
- **Razão Social:** Nome empresarial registrado na Junta Comercial
- **Nome Fantasia:** Nome comercial (como a loja é conhecida)
- **Inscrição Estadual:** Registro estadual para ICMS (pode ser ISENTO)
- **Token de API:** Chave de acesso para integração com serviços externos

---

## 📄 Anexos

### **Documentos Necessários para Abertura do MEI:**
1. CPF
2. Título de Eleitor ou Declaração de Imposto de Renda
3. Comprovante de residência
4. Número do recibo da última declaração de IR (se aplicável)

### **CNAE Recomendado para E-commerce de Tecnologia:**
- **4751-2/01** - Comércio varejista especializado de equipamentos e suprimentos de informática
- **4789-0/99** - Comércio varejista de outros produtos não especificados anteriormente

### **Obrigações do MEI:**
- Pagamento mensal do DAS (Documento de Arrecadação do Simples Nacional)
- Declaração Anual de Faturamento (DASN-SIMEI)
- Limite de faturamento: R$ 81.000/ano (R$ 6.750/mês)

---

**Desenvolvido por:** Equipe Royal Tech  
**Data:** Agosto de 2026  
**Versão:** 1.0.0
