#!/usr/bin/env bash
# Lokális: GitHub secrets + workflow indítás (Fly token a CI-ben van).
set -euo pipefail

REPO="${GITHUB_REPO:-kokenydaniel/penzpilot-backend}"
ENV_FILE="${1:-.env.storage.local}"

if [[ ! -f "$ENV_FILE" ]]; then
  cat <<EOF
Hozd létre: $ENV_FILE

SUPABASE_STORAGE_ACCESS_KEY=...
SUPABASE_STORAGE_SECRET_KEY=...
# opcionális:
# SUPABASE_STORAGE_BUCKET=attachments

Supabase: Storage → S3 Access Keys → New access key
EOF
  exit 1
fi

# shellcheck disable=SC1090
set -a
source "$ENV_FILE"
set +a

for key in SUPABASE_STORAGE_ACCESS_KEY SUPABASE_STORAGE_SECRET_KEY; do
  if [[ -z "${!key:-}" ]]; then
    echo "Hiányzik: $key az $ENV_FILE fájlban."
    exit 1
  fi
done

gh secret set SUPABASE_STORAGE_ACCESS_KEY --repo "$REPO" --body "$SUPABASE_STORAGE_ACCESS_KEY"
gh secret set SUPABASE_STORAGE_SECRET_KEY --repo "$REPO" --body "$SUPABASE_STORAGE_SECRET_KEY"
if [[ -n "${SUPABASE_STORAGE_BUCKET:-}" ]]; then
  gh secret set SUPABASE_STORAGE_BUCKET --repo "$REPO" --body "$SUPABASE_STORAGE_BUCKET"
fi

gh workflow run configure-supabase-storage.yml --repo "$REPO"
echo "Workflow elindítva. Követés: gh run watch --repo $REPO"
