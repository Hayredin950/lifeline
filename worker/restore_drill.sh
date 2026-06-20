#!/usr/bin/env bash
# LifeLine — Monthly restore drill (NFR-06).
# Restores the latest backup to a temp DB and verifies key table row counts.
# Usage: ./worker/restore_drill.sh [--backup-file /path/to/file.sql.gz]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

ENV_FILE="$ROOT_DIR/lifeline/.env"
if [ -f "$ENV_FILE" ]; then
    set -o allexport
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +o allexport
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3307}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/lifeline}"
DRILL_DB="lifeline_restore_drill_$$"

# Allow explicit backup file via argument
BACKUP_FILE="${2:-}"

if [[ -z "$BACKUP_FILE" ]]; then
    # Use the most recent backup
    BACKUP_FILE=$(ls -t "$BACKUP_DIR"/lifeline_*.sql.gz 2>/dev/null | head -1)
fi

if [[ -z "$BACKUP_FILE" || ! -f "$BACKUP_FILE" ]]; then
    echo "[restore-drill] ERROR: No backup file found in $BACKUP_DIR"
    exit 1
fi

echo "[restore-drill] Starting restore drill at $(date)"
echo "[restore-drill] Source: $BACKUP_FILE"
echo "[restore-drill] Target: $DRILL_DB on $DB_HOST:$DB_PORT"

# Create drill DB
MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
    -e "CREATE DATABASE IF NOT EXISTS \`$DRILL_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Restore
echo "[restore-drill] Restoring..."
MYSQL_PWD="$DB_PASS" zcat "$BACKUP_FILE" \
    | mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DRILL_DB"

echo "[restore-drill] Restore complete. Verifying row counts..."

VERIFY=$(MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DRILL_DB" -s -e "
    SELECT 'users'              , COUNT(*) FROM users
    UNION ALL
    SELECT 'donor_profiles'     , COUNT(*) FROM donor_profiles
    UNION ALL
    SELECT 'hospital_profiles'  , COUNT(*) FROM hospital_profiles
    UNION ALL
    SELECT 'blood_requests'     , COUNT(*) FROM blood_requests
    UNION ALL
    SELECT 'audit_logs'         , COUNT(*) FROM audit_logs
    UNION ALL
    SELECT 'schema_migrations'  , COUNT(*) FROM schema_migrations;
")

echo "[restore-drill] Row counts:"
echo "$VERIFY" | while IFS=$'\t' read -r tbl cnt; do
    echo "  $tbl: $cnt rows"
done

# Drop drill DB
MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
    -e "DROP DATABASE IF EXISTS \`$DRILL_DB\`;"

echo "[restore-drill] Drill DB cleaned up."
echo "[restore-drill] PASS — restore drill complete at $(date)"
