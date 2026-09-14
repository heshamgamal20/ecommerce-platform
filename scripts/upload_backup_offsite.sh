#!/usr/bin/env bash
set -Eeuo pipefail

: "${BACKUP_FILE:?BACKUP_FILE is required}"
: "${BACKUP_OFFSITE_ENABLED:=false}"

if [[ "$BACKUP_OFFSITE_ENABLED" != "true" ]]; then
  exit 0
fi

: "${BACKUP_OFFSITE_BUCKET:?BACKUP_OFFSITE_BUCKET is required when off-site backups are enabled}"
: "${BACKUP_OFFSITE_PREFIX:=ecommerce-platform/database}"

if ! command -v aws >/dev/null 2>&1; then
  printf 'aws CLI is required when BACKUP_OFFSITE_ENABLED=true\n' >&2
  exit 1
fi

remote="s3://${BACKUP_OFFSITE_BUCKET%/}/${BACKUP_OFFSITE_PREFIX#/}/$(basename "$BACKUP_FILE")"
args=(s3 cp "$BACKUP_FILE" "$remote" --only-show-errors)
if [[ -n "${BACKUP_OFFSITE_ENDPOINT:-}" ]]; then
  args+=(--endpoint-url "$BACKUP_OFFSITE_ENDPOINT")
fi
aws "${args[@]}"

checksum="${BACKUP_FILE}.sha256"
if [[ -f "$checksum" ]]; then
  portable_checksum="$(mktemp)"
  trap 'rm -f "$portable_checksum"' EXIT
  printf '%s  %s\n' "$(sha256sum "$BACKUP_FILE" | awk '{print $1}')" "$(basename "$BACKUP_FILE")" > "$portable_checksum"
  args=(s3 cp "$portable_checksum" "${remote}.sha256" --only-show-errors)
  if [[ -n "${BACKUP_OFFSITE_ENDPOINT:-}" ]]; then
    args+=(--endpoint-url "$BACKUP_OFFSITE_ENDPOINT")
  fi
  aws "${args[@]}"
fi
printf 'Uploaded off-site backup: %s\n' "$remote"
