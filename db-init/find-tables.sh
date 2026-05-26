#!/bin/bash
# หา table ที่เกี่ยวกับ AP invoice ใน Carmen
REMOTE_HOST="192.168.1.12"
r() { mysql -h "$REMOTE_HOST" -uroot --default-character-set=latin1 --connect-timeout=30 Carmen "$@" 2>&1; }

echo "=== Tables ที่ขึ้นต้นด้วย apinv ==="
r -e "SHOW TABLES LIKE 'apinv%'"

echo ""
echo "=== Tables ที่ขึ้นต้นด้วย ap ==="
r -e "SHOW TABLES LIKE 'ap%'"

echo ""
echo "=== Databases ทั้งหมด ==="
mysql -h "$REMOTE_HOST" -uroot --default-character-set=latin1 --connect-timeout=30 -e "SHOW DATABASES" 2>&1

echo ""
echo "=== Tables ทั้งหมดที่มี 'inv' ==="
r -e "SHOW TABLES LIKE '%inv%'"
