#!/bin/bash
# หาว่าข้อมูล invoice จริงอยู่ database ไหน table ไหน
REMOTE_HOST="192.168.1.12"
rd() { mysql -h "$REMOTE_HOST" -uroot --default-character-set=latin1 --connect-timeout=30 "$1" "${@:2}" 2>&1; }

echo "=== Carmen.invoiceh count ==="
rd Carmen -e "SELECT COUNT(*) FROM invoiceh"

echo ""
echo "=== Carmen.invoicep count ==="
rd Carmen -e "SELECT COUNT(*) FROM invoicep"

echo ""
echo "=== Carmen.invoiced count ==="
rd Carmen -e "SELECT COUNT(*) FROM invoiced"

echo ""
echo "=== backoffice tables (apinv*) ==="
rd backoffice -e "SHOW TABLES LIKE 'apinv%'"

echo ""
echo "=== backoffice tables ทั้งหมด ==="
rd backoffice -e "SHOW TABLES" | head -50

echo ""
echo "=== backoffice.apinvh count (ถ้ามี) ==="
rd backoffice -e "SELECT COUNT(*) FROM apinvh"

echo ""
echo "=== ดู Carmen.invoiceh sample 1 row ==="
rd Carmen --batch -e "SELECT * FROM invoiceh LIMIT 1"
