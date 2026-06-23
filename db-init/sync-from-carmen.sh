#!/bin/bash
# ───────────────────────────────────────────────────────────────────────────
# Sync tables: Carmen MySQL 4.0 (192.168.1.12) → local backoffice (MySQL 5.5)
# ───────────────────────────────────────────────────────────────────────────
# RUNS INSIDE the inv-dev-db container (has mysql 5.5 client + reaches 1.12).
#   docker cp sync-from-carmen.sh inv-dev-db:/tmp/
#   docker exec inv-dev-db sh /tmp/sync-from-carmen.sh invrecv0 invrecv1
#
# READ-ONLY on remote 1.12 (SHOW CREATE + SELECT only). Never writes to Carmen.
# Local: DROP + CREATE + LOAD per table (idempotent — safe to re-run).
#
# Charset: Carmen stores TIS-620 bytes in latin1 columns → keep bytes intact
#          with --default-character-set=latin1 on both ends. PHP iconv at render.
# Why mysql CLI not mysqldump: mysqldump does not support MySQL 4.0 reliably.
# ───────────────────────────────────────────────────────────────────────────
set -e

REMOTE_HOST="${CARMEN_HOST:-192.168.1.12}"
REMOTE_USER="${CARMEN_USER:-root}"
REMOTE_DB="${CARMEN_DB:-backoffice}"
LOCAL_DB="${LOCAL_DB:-backoffice}"
LOCAL_PASS="${LOCAL_PASS:-inv123}"

# default tables = receiving header + detail
TABLES="${*:-invrecv0 invrecv1}"

r() { mysql -h "$REMOTE_HOST" -u "$REMOTE_USER" --default-character-set=latin1 --connect-timeout=15 "$REMOTE_DB" "$@"; }
l() { mysql --local-infile=1 --default-character-set=latin1 -u root -p"$LOCAL_PASS" "$LOCAL_DB" "$@"; }

echo "[SYNC] Carmen $REMOTE_HOST → local $LOCAL_DB | tables: $TABLES"

for T in $TABLES; do
    echo "[SYNC] ───── $T ─────"

    # 1. schema from Carmen, MySQL 4.0 → 5.5 (TYPE= → ENGINE=)
    CREATE_SQL=$(r --skip-column-names -e "SHOW CREATE TABLE \`$T\`" | cut -f2-)
    if [ -z "$CREATE_SQL" ]; then
        echo "[SYNC] ERROR: no schema for $T — skipped"
        continue
    fi
    CREATE_SQL=$(echo "$CREATE_SQL" | sed 's/ TYPE=/ ENGINE=/g; s/ type=/ ENGINE=/g')

    # 2. recreate local table (idempotent)
    echo "DROP TABLE IF EXISTS \`$T\`;" | l
    echo "$CREATE_SQL;" | l
    echo "[SYNC] schema $T: OK"

    # 3. dump data → TSV (mysql --batch escapes tab/newline/backslash; \N = NULL)
    r --batch --quick --skip-column-names -e "SELECT * FROM \`$T\`" > "/tmp/${T}.tsv"
    SRC_ROWS=$(wc -l < "/tmp/${T}.tsv" | tr -d ' ')
    echo "[SYNC] dumped $T: $SRC_ROWS rows"

    # 4. load into local
    if [ "$SRC_ROWS" -gt 0 ]; then
        l -e "LOAD DATA LOCAL INFILE '/tmp/${T}.tsv' INTO TABLE \`$T\`;"
        DST_ROWS=$(l --skip-column-names -e "SELECT COUNT(*) FROM \`$T\`")
        echo "[SYNC] loaded $T: $DST_ROWS rows (src=$SRC_ROWS)"
        [ "$SRC_ROWS" = "$DST_ROWS" ] && echo "[SYNC] $T: ✓ MATCH" || echo "[SYNC] $T: ⚠ COUNT MISMATCH"
    else
        echo "[SYNC] $T: empty — skipped load"
    fi
    rm -f "/tmp/${T}.tsv"
done

echo "[SYNC] done ✓"
