#!/bin/bash
# Debug script — ทดสอบ SELECT จาก Carmen remote
REMOTE_HOST="192.168.1.12"
r() { mysql -h "$REMOTE_HOST" -uroot --default-character-set=latin1 --connect-timeout=30 Carmen "$@" 2>&1; }

echo "=== apinvh count ==="
r -e "SELECT COUNT(*) FROM apinvh"

echo ""
echo "=== apinvd count ==="
r -e "SELECT COUNT(*) FROM apinvd"

echo ""
echo "=== gblvend count (ควบคุม) ==="
r -e "SELECT COUNT(*) FROM gblvend"

echo ""
echo "=== apinvh sample 2 rows ==="
r --batch --skip-column-names -e "SELECT * FROM apinvh LIMIT 2"

echo ""
echo "=== Permissions ของ root ==="
r -e "SHOW GRANTS"
