# Release Notes — Módulo de easter eggs & proteção de CI

**PR:** [#84](https://github.com/VitaoDeveloper/TCC_Etec/pull/84) — `feat/КоринтЪианс-Имортал` → `development`
**Data:** 2026-09-16
**Autor:** jotaomh (easter egg "Modo Realeza" e temas visuais)

---

## Visão geral

Este PR consolida **duas entregas**:

1. **Endurecimento da proteção de CI** do easter egg existente ("Modo Realeza"), corrigindo 6 furos que permitiam sabotagem passar em silêncio.
2. **Novo módulo de efeitos visuais do tema** (`theme-extras`) com gatilho por sequência de teclas, protegido pela **mesma infraestrutura** de checksum + testes + CODEOWNERS.

Tudo foi verificado localmente: testes PHPUnit passando (52 testes, 230 assertions) e os cenários de sabotagem reproduzidos um a um com exit code confirmado.

---

## Commit 1 — `fix(ci)`: endurece proteção de hashes (easter egg "Modo Realeza")

Arquivos alterados: `scripts/check-easter-egg-hashes.sh`, `scripts/update-easter-egg-hashes.sh`, `.github/workflows/phpunit.yml`, `.github/easter-egg-hashes.json`.

### Problemas corrigidos no `check-easter-egg-hashes.sh`

| # | Furo antigo | Comportamento novo |
|---|-------------|--------------------|
| 1 | Sem `jq` no ambiente → script **aprovava tudo** com exit 0 | `set -euo pipefail` + checagem explícita de `jq` e `sha256sum` → **exit 1** com mensagem clara |
| 2 | JSON corrompido → script **aprovava tudo** | Validação `jq empty` antes de qualquer uso → **exit 1** |
| 3 | Entrada removida do JSON → arquivo deixava de ser verificado em silêncio | Novo campo `expected_file_count`: mismatch de contagem ou `.files` vazio → **exit 1** |
| 4 | Arquivo protegido ausente no disco → hash vazio (falso positivo) | Falha explícita "arquivo não encontrado" → **exit 1** |
| 5 | Snippets com CRLF (`components/footer.php` usa `\r\n`) → extração vazia | Extração por marcadores tolerante a CRLF (awk normaliza `\r`) |
| 6 | `.github/easter-egg-hashes.json` sem contagem mínima | Campo `expected_file_count` presente e validado |

### Workflow `phpunit.yml`

- `composer install --no-interaction --prefer-dist --no-progress --no-scripts` (evita que scripts pós-instalação que dependem de banco quebrem o job sem rodar nenhum teste).
- Novo passo **obrigatório e separado**: `vendor/bin/phpunit --filter EasterEggTest` — garante que o teste do easter egg realmente executa no CI.

### Cenários de sabotagem testados manualmente (todos → exit 1)

| Cenário | Resultado |
|---------|-----------|
| Baseline (nada alterado) | exit **0** |
| PNG `corinthians.png` alterado | exit **1** |
| Snippet do `header.php` alterado | exit **1** |
| Entrada removida do JSON | exit **1** |
| `.files` vazio | exit **1** |
| JSON corrompido | exit **1** |
| Arquivo protegido removido do disco | exit **1** |

---

## Commit 2 — `feat(theme)`: módulo de efeitos visuais do tema (`theme-extras`)

Um **segundo easter egg independente** do "Modo Realeza", com gatilho secreto **sem nenhuma dica visual na interface**.

### Gatilho

- Digitar a sequência **`timao`** (case-insensitive) em qualquer página pública da loja.
- Fora de campos de formulário (`INPUT`/`TEXTAREA`/`contenteditable`).
- Sequência completa em **menos de 3 segundos**.
- Nenhum botão, texto ou asset anuncia a existência do efeito.

### Efeito (duração ~4s)

1. Overlay de **gradiente preto-branco pulsante** sobre a viewport.
2. **Mosaico curto** de ícones (marca de acento) cruzando a tela na horizontal com rotação.
3. **Toast discreto** no canto inferior direito.

### Arquivos

| Arquivo | Papel |
|---------|-------|
| `assets/js/theme-extras.js` | Registra o listener global de teclado, janela de 3s e orquestra o efeito (classes `tx-fx*`) |
| `assets/css/theme-extras.css` | Regras e keyframes próprios: `tx-fx-pulse`, `tx-fx-cross`, `tx-fx-toast` |
| `assets/img/theme/accent-mark.png` | Imagem usada no mosaico (nome neutro) |
| `components/footer.php` | Carrega CSS + JS (trecho protegido por marcadores) |

Observações de implementação:
- Código **limpo e legível**, no estilo vanilla ES5 do projeto.
- **Sem comentários** rotulando o módulo como "easter egg", "secreto" ou citando o time — apenas comentários técnicos neutros de "efeitos visuais do tema".
- O efeito **reaproveita o padrão** do overlay/toast do "Modo Realeza", mas em classes próprias (`tx-fx*`).

### Proteção (mesma infra do item A, com rótulos neutros)

- **`.github/easter-egg-hashes.json`**: 4 novas entradas (`theme-extras.js`, `theme-extras.css`, `accent-mark.png`, snippet do `footer.php`) com `note` genérico `"Integridade de efeitos visuais do tema"` — sem citar easter egg/time. `expected_file_count` atualizado para **7**.
- **`.github/CODEOWNERS`**: novos arquivos sob **review obrigatório de @jotaomh** (mesma regra).
- **`tests/ThemeExtrasTest.php`**: valida hash dos 4 assets, presença das classes/trigger, validade do PNG e consistência de `expected_file_count`.
- **`.gitignore`**: exceção para versionar `assets/img/theme/accent-mark.png`.

---

## Testes

```bash
vendor/bin/phpunit            # 52 testes, 230 assertions — OK
vendor/bin/phpunit --filter EasterEggTest   # 8 testes — OK (passo obrigatório no CI)
bash scripts/check-easter-egg-hashes.sh     # todos os hashes conferem — exit 0
```

---

## Ações pendentes no GitHub (manuais — fora do código)

Para a proteção de fato "travar" o merge, é preciso habilitar **branch protection** na branch `development` (e, se a idéia for abranger `main`):

1. **Required status checks**:
   - `Easter Egg Guard`
   - `PHPUnit`
2. **Branch rules**:
   - Require branches to be up-to-date before merging (recommendado)
   - Require review from Code Owners (CODEOWNERS já aponta para @jotaomh)

Sem isso, o CI roda, mas um merge por bypass/force não é bloqueado automaticamente.