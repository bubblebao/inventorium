#!/bin/bash
# Carmen MySQL 4.0 → MySQL 5.5 sync
# ใช้ mysql CLI (ไม่ใช้ mysqldump เพราะไม่รองรับ MySQL 4.0)

REMOTE_HOST="${CARMEN_HOST:-192.168.1.12}"
REMOTE_USER="${CARMEN_USER:-root}"
REMOTE_DB="backoffice"
LOCAL_PASS="inv123"
LOCAL_DB="backoffice"
TABLES="invpo0 invpo1 gblvend gblprod"

r() { mysql -h "$REMOTE_HOST" -u "$REMOTE_USER" --default-character-set=latin1 --connect-timeout=15 "$REMOTE_DB" "$@"; }
l() { mysql --local-infile -u root -p"$LOCAL_PASS" "$LOCAL_DB" "$@"; }

echo "[SYNC] เริ่ม sync จาก Carmen $REMOTE_HOST..."

for TABLE in $TABLES; do
    echo "[SYNC] --- $TABLE ---"

    # 1. ดึง CREATE TABLE schema จาก Carmen
    CREATE_SQL=$(r --skip-column-names -e "SHOW CREATE TABLE \`$TABLE\`" | cut -f2-)
    if [ -z "$CREATE_SQL" ]; then
        echo "[SYNC] ERROR: ไม่สามารถดึง schema ของ $TABLE ได้"
        continue
    fi

    # 2. แปลง MySQL 4.0 syntax → 5.5 ก่อน import
    # TYPE=MyISAM (MySQL 4.0) → ENGINE=MyISAM (MySQL 5.5)
    CREATE_SQL=$(echo "$CREATE_SQL" | sed 's/ TYPE=/ ENGINE=/g; s/ type=/ ENGINE=/g')

    # 3. สร้าง table ใน local (drop ก่อนถ้ามีอยู่)
    echo "DROP TABLE IF EXISTS \`$TABLE\`;" | l
    echo "$CREATE_SQL;" | l
    echo "[SYNC] schema $TABLE: OK"

    # 4. Dump ข้อมูลเป็น TSV
    r --batch --quick --skip-column-names -e "SELECT * FROM \`$TABLE\`" > "/tmp/${TABLE}.tsv"
    ROWS=$(wc -l < "/tmp/${TABLE}.tsv" | tr -d ' ')
    echo "[SYNC] data $TABLE: $ROWS rows"

    # 5. Load TSV เข้า local MySQL
    if [ "$ROWS" -gt 0 ]; then
        l -e "LOAD DATA LOCAL INFILE '/tmp/${TABLE}.tsv' INTO TABLE \`${TABLE}\`;"
        echo "[SYNC] import $TABLE: OK"
    else
        echo "[SYNC] $TABLE: ไม่มีข้อมูล (ข้าม)"
    fi
done

echo "[SYNC] กำลังสร้าง index เพื่อ query เร็วขึ้น..."
l -e "CREATE INDEX idx_PrdID ON invpo1(PrdID);" 2>/dev/null && echo "[SYNC] index invpo1.PrdID: OK"
l -e "CREATE INDEX idx_PoDate ON invpo0(PoDate);" 2>/dev/null && echo "[SYNC] index invpo0.PoDate: OK"
l -e "CREATE INDEX idx_VndCode ON invpo0(VndCode);" 2>/dev/null && echo "[SYNC] index invpo0.VndCode: OK"

echo "[SYNC] เสร็จสมบูรณ์ ✓"
