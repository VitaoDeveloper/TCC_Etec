#!/usr/bin/env bash
#
# Atualiza os hashes SHA-256 esperados do easter egg "Modo Realeza" em
# .github/easter-egg-hashes.json a partir do estado ATUAL dos arquivos.
#
# Use apenas quando a alteração for INTENCIONAL e já esteja sendo revisada
# por @jotaomh (CODEOWNERS). Rodar este script cega o CI de proteção, então
# o review humano continua sendo a barreira — não rode "no automático".
#
# Uso: scripts/update-easter-egg-hashes.sh

set -euo pipefail

cd "$(dirname "$0")/.."

REF=".github/easter-egg-hashes.json"

if [ ! -f "$REF" ]; then
  echo "::error::Arquivo de referência não encontrado: $REF" >&2
  exit 1
fi

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

while IFS= read -r file; do
  type=$(jq -r --arg f "$file" '.files[$f].type' "$REF")
  start=$(jq -r --arg f "$file" '.files[$f].start_marker' "$REF")
  end=$(jq -r --arg f "$file" '.files[$f].end_marker' "$REF")

  if [ "$type" = "file" ]; then
    new=$(sha256sum "$file" | cut -d' ' -f1)
  else
    new=$(awk -v s="$start" -v e="$end" \
      '{t=$0; sub(/^[ \t]+/,"",t)} t==s{on=1} on{print} t==e{on=0}' "$file" \
      | sha256sum | cut -d' ' -f1)
  fi

  jq --arg f "$file" --arg h "$new" '.files[$f].sha256 = $h' "$REF" > "$TMP"
  mv "$TMP" "$REF"
  echo "Atualizado: $file -> $new"
done < <(jq -r '.files | keys[]' "$REF")

echo
echo "Hashes regenerados em $REF."
echo "Lembrete: o review obrigatório de @jotaomh (CODEOWNERS) segue valendo antes do merge."