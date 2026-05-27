<?php
$conn = @mysqli_connect('db-bridge', 'root', 'inv123', 'backoffice', 3306);
if (!$conn) die('CONNECT_FAIL: ' . mysqli_connect_error() . "\n");
mysqli_query($conn, 'SET NAMES latin1');

$pid = 'EN13-0865';
$safe = mysqli_real_escape_string($conn, $pid);

echo "=== gblprod ===\n";
$r = mysqli_query($conn, "SELECT PrdId, PrdDescE, PrdDescT, Active FROM gblprod WHERE PrdId = '$safe' LIMIT 1");
if (!$r) { echo 'FAIL: ' . mysqli_error($conn) . "\n"; }
else {
    $row = mysqli_fetch_assoc($r);
    if (!$row) { echo "NO ROW IN gblprod\n"; }
    else {
        foreach ($row as $k => $v) {
            $hex = bin2hex((string)($v ?? ''));
            $utf = @iconv('TIS-620', 'UTF-8//IGNORE', (string)($v ?? ''));
            echo "$k hex=[$hex] utf8=[$utf]\n";
        }
    }
}

echo "\n=== invpo1 Remark (latest 3) ===\n";
$r3 = mysqli_query($conn, "SELECT d.Remark FROM invpo1 d INNER JOIN invpo0 h ON h.SeqNo=d.SeqNo WHERE d.PrdID='$safe' ORDER BY h.PoDate DESC LIMIT 3");
if (!$r3) { echo 'FAIL: ' . mysqli_error($conn) . "\n"; }
else {
    $n = 0;
    while ($rm = mysqli_fetch_assoc($r3)) {
        $n++;
        $hex = bin2hex((string)($rm['Remark'] ?? ''));
        $utf = @iconv('TIS-620', 'UTF-8//IGNORE', (string)($rm['Remark'] ?? ''));
        echo "row$n hex=[$hex] utf8=[$utf]\n";
    }
    if (!$n) echo "no rows\n";
}
echo "DONE\n";
