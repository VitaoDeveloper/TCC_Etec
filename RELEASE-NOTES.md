# Release Notes — Módulo de easter eggs & proteção de CI

**PR:** [#84](https://github.com/VitaoDeveloper/TCC_Etec/pull/84) — `feat/КоринтЪианс-Имортал` → `development`
**Data:** 2026-09-16
**Autor:** jotaomh (easter egg "Modo Realeza" e temas visuais)

---

## Visão geral

Este PR consolida as entregas:

1. **Endurecimento da proteção de CI** do easter egg existente ("Modo Realeza"), corrigindo 6 furos que permitiam sabotagem passar em silêncio.
2. **Novo módulo de efeitos visuais do tema** (`theme-extras`) com gatilho por sequência de teclas, protegido pela **mesma infraestrutura** de checksum + testes + CODEOWNERS.
3. **Ícone sem fundo (transparente)**: escudo do Corinthians regenerado com alpha real e sem margem sobrando, nos dois assets (Modo Realeza e theme-extras).
4. **Novo efeito do theme-extras**: escudo piscando em posições aleatórias, sem texto/overlay na tela, com suporte a `prefers-reduced-motion`.
5. **Rodada de review**: correções (imagem distinta, spawn até `EFFECT_MS`, preload, poda do array), **barrinha de status** com contagem de ativações, **modo combinado** ("Modo Realeza" ativo), **gatilho por data** (1º/09) e polimento (Esc cancela, pausa em aba escondida, reduced-motion na barrinha).

Tudo verificado localmente: testes PHPUnit passando (62 testes, 289 assertions) e o script de hashes com exit 0.

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

### Efeito (atual — duração ~6s)

1. **Escudos piscando** em posições **aleatórias** do viewport (top/left aleatórios, com margem nas bordas).
2. Cada escudo faz **fade-in rápido → curto momento visível → fade-out**, com opacidade oscilante (2–3 ciclos rápidos).
3. **Tamanhos aleatórios** (~40–110px de largura) e **rotação leve** (−15° a +15°).
4. Surgem em **intervalos aleatórios** (150–450ms), **máx. 6 simultâneos**.
5. `pointer-events: none`, z-index alto, e **limpeza completa** no fim (nenhum nó órfão).
6. **`prefers-reduced-motion: reduce`**: poucos escudos (4) com fade suave, sem piscar rápido.
7. **Sem toast e sem texto na tela** — efeito puramente visual.
8. **Sem overlay/estado no `body`** — nada de `body.tx-fx` ou alteração de fundo.

### Arquivos

| Arquivo | Papel |
|---------|-------|
| `assets/js/theme-extras.js` | Listener global de teclado, janela de 3s, spawn aleatório, cap de 6, reduced-motion e limpeza (`tx-fx-shield*`) |
| `assets/css/theme-extras.css` | Regras e keyframes próprios: `tx-fx-blink`, `tx-fx-fade`, classe `tx-fx-shield` / `--calm` |
| `assets/img/theme/accent-mark.png` | Escudo transparente usado no efeito (nome neutro) |
| `components/footer.php` | Carrega CSS + JS (trecho protegido por marcadores) |

Observações de implementação:
- Código **limpo e legível**, no estilo vanilla ES5 do projeto.
- **Sem comentários** rotulando o módulo como "easter egg", "secreto" ou citando o time — apenas comentários técnicos neutros de "efeitos visuais do tema".
- Imagem referenciada via `<img>` (basePath lido de `data-base-path`).

### Proteção (mesma infra do item A, com rótulos neutros)

- **`.github/easter-egg-hashes.json`**: entradas do `theme-extras` (`theme-extras.js`, `theme-extras.css`, `accent-mark.png`, snippet do `footer.php`) com `note` genérico `"Integridade de efeitos visuais do tema"` — sem citar easter egg/time. `expected_file_count` = **7**.
- **`.github/CODEOWNERS`**: todos os arquivos sob **review obrigatório de @jotaomh** (mesma regra).
- **`tests/ThemeExtrasTest.php`**: valida hash dos 4 assets, presença das classes/trigger, ausência das regras antigas (`tx-fx-pulse`, `tx-fx-cross`, `tx-fx-toast`) e consistência de `expected_file_count`.

---

## Commit 3 — `chore(assets)`: ícone sem fundo + ajustes

- PNG novo (500x500, RGBA com transparência real) processado com **`convert -trim`** para o bounding box do escudo (crop original ~110,69–390,431) e **`-resize x96`**, mantendo o alpha (PNG32). Duas versões geradas:
  - `assets/img/corinthians.png` (Modo Realeza)
  - `assets/img/theme/accent-mark.png` (theme-extras)
- **Sem fundo branco adicionado** (alpha preservado).
- Removidos `border-radius` que só existiam por causa do fundo antigo:
  - `.royal-troll` em `assets/css/mercadolivre-style.css`
  - `.tx-fx-icon` (regra removida junto com o efeito antigo) em `assets/css/theme-extras.css`
- Arquivo-fonte `novo-corinthians.png` (na prática `assets/img/novo_corinthians-removebg-preview.png`) **removido** após gerar as versões finais.
- Hashes do JSON atualizados (imagens e snippet CSS do Modo Realeza).

---

## Commit 4 — `fix(theme)`: corrige revisão do efeito (imagem distinta, spawn até `EFFECT_MS`, preload e poda)

Correções apontadas na revisão do PR:

- **Imagem distinta**: `accent-mark.png` regenerado em altura 120px (93x120), com hash SHA-256 diferente de `corinthians.png` — antes os dois assets eram idênticos.
- **Spawn**: para de criar escudos em `EFFECT_MS` e espera a última animação terminar antes de limpar tudo (sem render "nascer/remover" abrupto no fim).
- **Preload**: a imagem é pré-carregada no load (`new Image()`), evitando primeiro blink vazio.
- **Poda**: o array interno `shields` remove elementos já descartados do DOM em vez de só checar `document.body.contains` para contar.

---

## Commit 5 — `feat(theme)`: barrinha de status no canto com contagem de ativações

- **Barrinha fixa no canto inferior esquerdo** enquanto o efeito estiver ativo: fundo escuro `#111`, borda branca, sem captura de clique (`pointer-events: none`), fade-in/fade-out.
- Texto: `⚫⚪ MODO TIMÃO ATIVO · ativação #N`.
- **Barra de countdown** fina que esvazia ao longo da duração do efeito.
- **Contador de ativações** persistido em `localStorage` na chave neutra **`tx_fx_runs`** (com `try/catch` caindo para contagem em memória quando o storage não está disponível).

---

## Commit 6 — `feat(theme)`: modo combinado + gatilho por data + polimento

### Modo combinado (quando o "Modo Realeza" está ativo)

- Duração **dobrada (~12s)**, **máx. 10 escudos** simultâneos e **spawn mais rápido** (90–260ms).
- Barrinha muda para **`⚫⚪ MODO TIMÃO IMORTAL`** com **pulsar na borda**.
- Se o efeito já estiver rodando e a sequência for digitada de novo, a duração é **renovada** (sem empilhar)

### Gatilho por data (1º de setembro)

- Na carga da página, mostra **somente** a barrinha `⚫⚪ 1910` por ~5s — sem escudos e sem countdown.

### Polimento

- **Esc** cancela/limpa tudo imediatamente.
- **`visibilitychange`**: aba escondida pausa o spawn e remove escudos "congelados"; ao voltar, retoma de onde parou, sem escudos congelados.
- **`prefers-reduced-motion`** também desativa o pulsar e o countdown animado da barrinha (`.tx-fx-status--calm`), além do piscar dos escudos.

---

## Testes

```bash
vendor/bin/phpunit            # 62 testes, 289 assertions — OK
vendor/bin/phpunit --filter EasterEggTest   # OK (passo obrigatório no CI)
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