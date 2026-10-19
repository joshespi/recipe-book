#!/bin/bash
# Backs up the database and uploaded photos into one archive:
#   /backups/recipes-YYYYmmdd-HHMMSS.tar.gz
#
# Runs inside the app container (it needs mariadb-dump and the DB_* variables):
#   docker compose exec app bash scripts/backup.sh
# The scheduler runs it nightly (see routes/console.php).
#
# The optional first argument changes the archive prefix. restore.sh uses
# "pre-restore" for its safety copy, which has its own, shorter retention.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/html}"
BACKUP_DIR="${BACKUP_DIR:-/backups}"
KEEP="${BACKUP_KEEP:-14}"
PREFIX="${1:-recipes}"

export MYSQL_PWD="${DB_PASSWORD:?DB_PASSWORD is not set}"
DB_ARGS=(-h "${DB_HOST:?DB_HOST is not set}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:?DB_USERNAME is not set}")

stamp="$(date +%Y%m%d-%H%M%S)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

mkdir -p "$BACKUP_DIR"
[ -w "$BACKUP_DIR" ] || { echo "[backup] $BACKUP_DIR is not writable by $(id -un)" >&2; exit 1; }

echo "[backup] dumping database ${DB_DATABASE}"
mariadb-dump "${DB_ARGS[@]}" --single-transaction --routines --triggers --add-drop-table \
    "$DB_DATABASE" > "$work/database.sql"

echo "[backup] copying photos"
mkdir -p "$work/photos"
if [ -d "$APP_DIR/storage/app/public/photos" ]; then
    cp -a "$APP_DIR/storage/app/public/photos/." "$work/photos/"
fi

query() { mariadb "${DB_ARGS[@]}" -N -B "$DB_DATABASE" -e "$1" 2>/dev/null || echo ""; }

sha="unknown"
if command -v git >/dev/null 2>&1 && git -C "$APP_DIR" rev-parse --short HEAD >/dev/null 2>&1; then
    sha="$(git -C "$APP_DIR" rev-parse --short HEAD)"
fi

cat > "$work/manifest.json" <<JSON
{
  "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "app_git_sha": "$sha",
  "database": "${DB_DATABASE}",
  "latest_migration": "$(query 'SELECT migration FROM migrations ORDER BY id DESC LIMIT 1')",
  "recipes": $(query 'SELECT COUNT(*) FROM recipes' | grep -E '^[0-9]+$' || echo 0),
  "photos": $(query 'SELECT COUNT(*) FROM photos' | grep -E '^[0-9]+$' || echo 0),
  "photo_files": $(find "$work/photos" -type f | wc -l | tr -d ' ')
}
JSON

archive="$BACKUP_DIR/${PREFIX}-${stamp}.tar.gz"
tar -C "$work" -czf "$archive.partial" .
mv "$archive.partial" "$archive"
echo "[backup] wrote $archive ($(du -h "$archive" | cut -f1))"

# Retention: keep the newest N of this prefix.
limit="$KEEP"
[ "$PREFIX" = "recipes" ] || limit=5
# shellcheck disable=SC2012
ls -1t "$BACKUP_DIR/${PREFIX}-"*.tar.gz 2>/dev/null | tail -n +"$((limit + 1))" | while read -r old; do
    echo "[backup] removing old archive $old"
    rm -f -- "$old"
done
