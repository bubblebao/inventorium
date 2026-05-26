<?php
require_once __DIR__ . '/config/db.php';
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Status — Inventorium</title>
<link rel="icon" type="image/png" href="/assets/inventorium-logo.png">
<style>body{font-family:ui-monospace,Menlo,monospace;background:#0f172a;color:#e2e8f0;padding:24px;max-width:720px;margin:auto}h1{color:#0ea5e9;display:flex;align-items:center;gap:12px}img{width:40px;height:40px;border-radius:8px}pre{background:#1e293b;padding:16px;border-radius:8px;border-left:3px solid #0ea5e9}</style>
</head>
<body>
<h1><img src="/assets/inventorium-logo.png" alt=""> Inventorium Status</h1>
<pre><?php
echo "=== Inventorium Status ===\n";
echo "Time : " . date('Y-m-d H:i:s') . "\n";
echo "DB   : " . (getenv('DB_HOST') ?: 'db-bridge') . "\n\n";

$tables = ['invpo0', 'invpo1', 'gblvend', 'gblprod'];
$all_ok = true;

foreach ($tables as $t) {
    $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM `$t`");
    if ($r) {
        $cnt = mysqli_fetch_assoc($r)['cnt'];
        echo "Table $t : $cnt rows " . ($cnt > 0 ? "✓" : "⚠ EMPTY") . "\n";
        if ($cnt == 0) $all_ok = false;
    } else {
        echo "Table $t : NOT FOUND ✗ (" . mysqli_error($conn) . ")\n";
        $all_ok = false;
    }
}

echo "\nStatus: " . ($all_ok ? "✅ READY" : "⚠ SYNC NEEDED") . "\n";

// Apache + PHP version
echo "\nPHP   : " . phpversion() . "\n";
echo "mysqli: " . mysqli_get_server_info($conn) . "\n";
?></pre>
</body>
</html>
