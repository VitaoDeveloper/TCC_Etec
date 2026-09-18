#!/usr/bin/env bash
#
# Verifica os hashes SHA-256 dos assets protegidos contra
# .github/asset-integrity.json — a MESMA lógica usada pelo CI
# (.github/workflows/asset-integrity.yml).
#
# Exit 0 = tudo confere | Exit 1 = algum asset protegido foi alterado ou integridade do JSON falhou.
#
# Em ambiente GitHub Actions, o script também exporta a lista de arquivos
# divergentes em $GITHUB_ENV (MISMATCHES) para o passo de notificação no PR.
#
# Uso: bash scripts/check-asset-integrity.sh

set -euo pipefail

# ======================================================================
# Fail-safe de dependência (A1): exigir jq e sha256sum
# ======================================================================
for cmd in jq sha256sum; do
  if ! command -v "$cmd" &>/dev/null; then
    echo "::error::Dependência obrigatória ausente: $cmd" >&2
    echo "Instale-o antes de rodar este script." >&2
    exit 1
  fi
done

cd "$(dirname "$0")/.."

REF=".github/asset-integrity.json"

if [ ! -f "$REF" ]; then
  echo "::error::Arquivo de referência não encontrado: $REF" >&2
  exit 1
fi

# ======================================================================
# Fail-safe de JSON (A2): validar que o JSON está bem formado
# ======================================================================
if ! jq empty "$REF" 2>/dev/null; then
  echo "::error::Arquivo JSON corrompido ou inválido: $REF" >&2
  echo "Rode: jq empty $REF  para ver o erro detalhado." >&2
  exit 1
fi

# ======================================================================
# Contagem mínima de entradas (A3): expected_file_count
# ======================================================================
expected_count=$(jq -r '.expected_file_count // empty' "$REF")
actual_count=$(jq '.files | length' "$REF")

if [ -z "$expected_count" ]; then
  echo "::error::Campo 'expected_file_count' ausente em $REF." >&2
  exit 1
fi

if [ "$actual_count" -eq 0 ]; then
  echo "::error::Nenhuma entrada em '.files' em $REF — arquivo de proteção vazio." >&2
  exit 1
fi

if [ "$actual_count" -ne "$expected_count" ]; then
  echo "::error::Contagem de entradas inconsistente: esperado $expected_count, encontrado $actual_count em $REF." >&2
  echo "Se você adicionou ou removeu um asset protegido, atualize 'expected_file_count' e rode scripts/update-asset-integrity.sh." >&2
  exit 1
fi

# ======================================================================
# Verificação de hashes (com falha explícita para arquivos ausentes — A4)
# ======================================================================
MISMATCH=""
while IFS= read -r file; do
  type=$(jq -r --arg f "$file" '.files[$f].type' "$REF")
  expected=$(jq -r --arg f "$file" '.files[$f].sha256' "$REF")

  case "$type" in
    file)
      if [ ! -f "$file" ]; then
        echo "::error::Arquivo protegido não encontrado no disco: $file" >&2
        MISMATCH="${MISMATCH} ${file}"
        continue
      fi
      current=$(sha256sum "$file" | cut -d' ' -f1)
      ;;
    snippet)
      if [ ! -f "$file" ]; then
        echo "::error::Arquivo contendo snippet protegido não encontrado: $file" >&2
        MISMATCH="${MISMATCH} ${file}"
        continue
      fi
      start=$(jq -r --arg f "$file" '.files[$f].start_marker' "$REF")
      end=$(jq -r --arg f "$file" '.files[$f].end_marker' "$REF")
      current=$(awk -v s="$start" -v e="$end" \
        '{t=$0; sub(/\r/,"",t); sub(/^[ \t]+/,"",t)} t==s{on=1} on{print} t==e{on=0}' "$file" \
        | sha256sum | cut -d' ' -f1)
      ;;
    *)
      echo "::error::Tipo de proteção desconhecido para $file: $type" >&2
      exit 1
      ;;
  esac

  if [ "$current" = "$expected" ]; then
    echo "OK: $file"
  else
    echo "MUDOU: $file (esperado $expected, atual $current)"
    MISMATCH="${MISMATCH} ${file}"
  fi
done < <(jq -r '.files | keys[]' "$REF")

if [ -n "$MISMATCH" ]; then
  if [ -n "${GITHUB_ENV:-}" ]; then
    echo "MISMATCHES=$MISMATCH" >> "$GITHUB_ENV"
  fi
  echo "::error::Verificação de hash falhou. Assets protegidos alterados:$MISMATCH" >&2
  exit 1
fi

echo "OK: todos os hashes conferem com $REF."
