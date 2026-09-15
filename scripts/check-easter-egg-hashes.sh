#!/usr/bin/env bash
#
# Verifica os hashes SHA-256 do easter egg "Modo Realeza" contra
# .github/easter-egg-hashes.json — a MESMA lógica usada pelo CI
# (.github/workflows/easter-egg-guard.yml).
#
# Exit 0 = tudo confere | Exit 1 = algum arquivo protegido foi alterado.
#
# Em ambiente GitHub Actions, o script também exporta a lista de arquivos
# divergentes em $GITHUB_ENV (MISMATCHES) para o passo de notificação no PR.
#
# Uso: bash scripts/check-easter-egg-hashes.sh

cd "$(dirname "$0")/.."

REF=".github/easter-egg-hashes.json"

if [ ! -f "$REF" ]; then
  echo "::error::Arquivo de referência não encontrado: $REF" >&2
  exit 1
fi

MISMATCH=""
while IFS= read -r file; do
  type=$(jq -r --arg f "$file" '.files[$f].type' "$REF")
  expected=$(jq -r --arg f "$file" '.files[$f].sha256' "$REF")

  case "$type" in
    file)
      current=$(sha256sum "$file" | cut -d' ' -f1)
      ;;
    snippet)
      start=$(jq -r --arg f "$file" '.files[$f].start_marker' "$REF")
      end=$(jq -r --arg f "$file" '.files[$f].end_marker' "$REF")
      current=$(awk -v s="$start" -v e="$end" \
        '{t=$0; sub(/^[ \t]+/,"",t)} t==s{on=1} on{print} t==e{on=0}' "$file" \
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
  echo "::error::Verificação de hash falhou. Easter egg 'Modo Realeza' alterado em:$MISMATCH" >&2
  exit 1
fi

echo "OK: todos os hashes do easter egg conferem com $REF."