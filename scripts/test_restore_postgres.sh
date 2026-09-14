#!/usr/bin/env bash
set -Eeuo pipefail

: "${RESTORE_DATABASE_URL:?RESTORE_DATABASE_URL must target an isolated disposable database}"
backup="${1:-}"
if [[ -z "$backup" || ! -f "$backup" ]]; then
  printf 'Usage: RESTORE_DATABASE_URL=... %s /path/to/backup.dump\n' "$0" >&2
  exit 2
fi

if [[ "${CONFIRM_ISOLATED_RESTORE:-}" != "yes" ]]; then
  printf 'Set CONFIRM_ISOLATED_RESTORE=yes to permit a restore into the target database.\n' >&2
  exit 2
fi

pg_restore --clean --if-exists --no-owner --no-privileges --exit-on-error \
  --dbname="$RESTORE_DATABASE_URL" "$backup"
printf 'Restore test succeeded for: %s\n' "$backup"
