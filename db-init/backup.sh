#!/bin/bash
# ───────────────────────────────────────────────────────────────────────────
# Inventorium Backup Script
# Backup: DB (mysqldump) + audit.log
# Rotation: keep last 30 days, delete older
# ───────────────────────────────────────────────────────────────────────────
#
# Usage:
#   bash backup.sh
#
# Schedule via Synology Task Scheduler:
#   Control Panel → Task Scheduler → Create → Scheduled Task → User-defined script
#   User: root
#   Schedule: Daily at 02:00
#   Run command:
#     bash /volume1/docker/inventorium/db-init/backup.sh
# ───────────────────────────────────────────────────────────────────────────

set -e

# ─── Config ──────────────────────────────────────────────────────────────
BACKUP_ROOT="${BACKUP_ROOT:-/volume1/backup/inventorium}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
DB_CONTAINER="${DB_CONTAINER:-inventorium-db-bridge-1}"
WEB_CONTAINER="${WEB_CONTAINER:-inventorium-web-1}"
DB_NAME="${DB_NAME:-backoffice}"
DB_PASS="${DB_PASS:-inv123}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DATE_FOLDER="$(date +%Y-%m-%d)"

BACKUP_DIR="$BACKUP_ROOT/$DATE_FOLDER"
mkdir -p "$BACKUP_DIR"

log() { echo "[$(date +'%H:%M:%S')] $1"; }

# ─── 1. Backup Database (mysqldump) ──────────────────────────────────────
log "▶ Database dump → $BACKUP_DIR/db_${TIMESTAMP}.sql.gz"

if docker exec "$DB_CONTAINER" mysqldump \
    -u root -p"$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --skip-lock-tables \
    "$DB_NAME" 2>/dev/null | gzip > "$BACKUP_DIR/db_${TIMESTAMP}.sql.gz"; then
    SIZE=$(du -h "$BACKUP_DIR/db_${TIMESTAMP}.sql.gz" | cut -f1)
    log "  ✓ DB backup: $SIZE"
else
    log "  ✗ DB backup FAILED"
    exit 1
fi

# ─── 2. Backup audit.log ─────────────────────────────────────────────────
log "▶ Audit log → $BACKUP_DIR/audit_${TIMESTAMP}.log.gz"

if docker exec "$WEB_CONTAINER" cat /var/www/html/logs/audit.log 2>/dev/null | gzip > "$BACKUP_DIR/audit_${TIMESTAMP}.log.gz"; then
    SIZE=$(du -h "$BACKUP_DIR/audit_${TIMESTAMP}.log.gz" | cut -f1)
    log "  ✓ Audit log: $SIZE"
else
    log "  ⚠ Audit log skipped (no file or empty)"
fi

# ─── 3. Backup users.php (encrypted passwords) ───────────────────────────
log "▶ users.php → $BACKUP_DIR/users_${TIMESTAMP}.php"

if cp /volume1/docker/inventorium/src/config/users.php "$BACKUP_DIR/users_${TIMESTAMP}.php" 2>/dev/null; then
    chmod 600 "$BACKUP_DIR/users_${TIMESTAMP}.php"  # protect — owner only
    log "  ✓ users.php (chmod 600)"
else
    log "  ⚠ users.php skipped"
fi

# ─── 4. Rotation — delete folders older than N days ──────────────────────
log "▶ Rotation: keeping last $RETENTION_DAYS days"

find "$BACKUP_ROOT" -maxdepth 1 -type d -mtime +$RETENTION_DAYS -exec rm -rf {} \; 2>/dev/null
REMAINING=$(find "$BACKUP_ROOT" -maxdepth 1 -type d | wc -l)
log "  ✓ Folders remaining: $((REMAINING - 1))"

# ─── Summary ─────────────────────────────────────────────────────────────
TOTAL_SIZE=$(du -sh "$BACKUP_ROOT" | cut -f1)
log "════════════════════════════════════════"
log "✅ Backup complete — Total: $TOTAL_SIZE"
log "📁 Location: $BACKUP_DIR"
log "════════════════════════════════════════"
