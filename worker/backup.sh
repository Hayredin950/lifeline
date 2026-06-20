#!/usr/bin/env bash
# LifeLine — MySQL backup script.
# Usage: ./worker/backup.sh [--dry-run]
# Schedule via cron: 0 2 * * * /path/to/lifeline-local/worker/backup.sh >> /var/log/lifeline-backup.log 2>&1

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

# Load .env if present (local dev)
ENV_FILE="$ROOT_DIR/lifeline/.env"
if [ -f "$ENV_FILE" ]; then
    set -o allexport
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +o allexport
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3307}"
DB_NAME="${DB_NAME:-lifeline_db_mysql}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/lifeline}"
RETAIN_DAYS="${RETAIN_DAYS:-30}"
DRY_RUN=0

if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=1
    echo "[backup] DRY RUN — no files will be written"
fi

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/lifeline_${DB_NAME}_${TIMESTAMP}.sql.gz"

if [ "$DRY_RUN" -eq 0 ]; then
    mkdir -p "$BACKUP_DIR"
fi

echo "[backup] Starting backup of $DB_NAME at $(date)"

if [ "$DRY_RUN" -eq 0 ]; then
    MYSQL_PWD="$DB_PASS" mysqldump \
        -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
        --single-transaction \
        --routines \
        --triggers \
        --set-gtid-purged=OFF \
        "$DB_NAME" \
    | gzip -9 > "$BACKUP_FILE"

    SIZE=$(du -sh "$BACKUP_FILE" | cut -f1)
    echo "[backup] Written: $BACKUP_FILE ($SIZE)"

    # Rotate: remove backups older than RETAIN_DAYS
    find "$BACKUP_DIR" -name "lifeline_${DB_NAME}_*.sql.gz" \
        -mtime "+${RETAIN_DAYS}" -delete \
        && echo "[backup] Rotated backups older than ${RETAIN_DAYS} days"
else
    echo "[backup] Would write: $BACKUP_FILE"
    echo "[backup] Would rotate files older than ${RETAIN_DAYS} days in $BACKUP_DIR"
fi

echo "[backup] Done at $(date)"
