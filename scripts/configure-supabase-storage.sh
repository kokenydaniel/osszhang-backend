#!/usr/bin/env bash
set -euo pipefail

REPO="${GITHUB_REPO:-kokenydaniel/penzpilot-backend}"
ENV_FILE="${1:-.env.storage.local}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE"
  exit 1
fi

set -a
source "$ENV_FILE"
set +a

for key in SUPABASE_STORAGE_ACCESS_KEY SUPABASE_STORAGE_SECRET_KEY; do
  if [[ -z "${!key:-}" ]]; then
    echo "Missing $key in $ENV_FILE"
    exit 1
  fi
done

gh secret set SUPABASE_STORAGE_ACCESS_KEY --repo "$REPO" --body "$SUPABASE_STORAGE_ACCESS_KEY"
gh secret set SUPABASE_STORAGE_SECRET_KEY --repo "$REPO" --body "$SUPABASE_STORAGE_SECRET_KEY"
if [[ -n "${SUPABASE_STORAGE_BUCKET:-}" ]]; then
  gh secret set SUPABASE_STORAGE_BUCKET --repo "$REPO" --body "$SUPABASE_STORAGE_BUCKET"
fi

gh workflow run configure-supabase-storage.yml --repo "$REPO"
echo "Workflow started: gh run watch --repo $REPO"
