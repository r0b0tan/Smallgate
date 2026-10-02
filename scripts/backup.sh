#!/usr/bin/env bash
# Back up a running production installation (compose.prod.yaml):
#
#   scripts/backup.sh [target directory]      default: ./backups
#
# Writes two files per run:
#   smallgate-<UTC time>-db.dump          PostgreSQL, pg_dump custom format
#   smallgate-<UTC time>-storage.tar.gz   storage/app: thumbnails, static previews
#
# The database goes first: a thumbnail without a row is harmless, a row whose
# thumbnail is missing only shows the placeholder. Both files contain personal
# data, so they are created readable by the owner only. Restoring is described
# in the README, "Backup and restore".
set -euo pipefail

cd "$(dirname "$0")/.."

dest="${1:-backups}"
compose=(docker compose -f compose.prod.yaml)
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
db="$dest/smallgate-$stamp-db.dump"
files="$dest/smallgate-$stamp-storage.tar.gz"

umask 077
mkdir -p "$dest"

# A run that fails halfway must not leave a file that looks like a backup.
trap 'rm -f "$db.partial" "$files.partial"' EXIT

"${compose[@]}" exec -T db \
    sh -c 'pg_dump --format=custom --no-owner --username="$POSTGRES_USER" "$POSTGRES_DB"' \
    > "$db.partial"

"${compose[@]}" exec -T app \
    tar -czf - -C /var/www/html/storage app \
    > "$files.partial"

mv "$db.partial" "$db"
mv "$files.partial" "$files"

echo "$db"
echo "$files"
