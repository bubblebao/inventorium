#!/bin/bash
# ──────────────────────────────────────────────────────────
# Inventorium — DB Restore from Local Snapshot
# ไม่ต้องต่อ Carmen อีกแล้ว — restore จาก snapshot.sql.gz
# ──────────────────────────────────────────────────────────

LOCAL_PASS="inv123"
LOCAL_DB="backoffice"
SNAP="/docker-entrypoint-initdb.d/snapshot.sql.gz"

echo "[RESTORE] เริ่ม restore จาก snapshot..."

if [ ! -f "$SNAP" ]; then
    echo "[RESTORE] ERROR: ไม่พบ $SNAP — abort"
    exit 1
fi

SIZE=$(du -h "$SNAP" | cut -f1)
echo "[RESTORE] ไฟล์: $SNAP ($SIZE)"

# Import snapshot
zcat "$SNAP" | mysql --default-character-set=latin1 -uroot -p"$LOCAL_PASS" "$LOCAL_DB" 2>&1
if [ $? -ne 0 ]; then
    echo "[RESTORE] ERROR: import ล้มเหลว"
    exit 1
fi

echo "[RESTORE] สร้าง index เพื่อ query เร็วขึ้น..."
mysql -uroot -p"$LOCAL_PASS" "$LOCAL_DB" 2>/dev/null <<'SQL'
CREATE INDEX IF NOT EXISTS idx_PrdID   ON invpo1(PrdID);
CREATE INDEX IF NOT EXISTS idx_PoDate  ON invpo0(PoDate);
CREATE INDEX IF NOT EXISTS idx_VndCode ON invpo0(VndCode);
SQL

echo "[RESTORE] ✅ เสร็จสมบูรณ์ — Inventorium พร้อมใช้งานโดยไม่ต้อง Carmen"
