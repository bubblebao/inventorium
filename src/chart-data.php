<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$type = $_GET['type'] ?? 'monthly';

if ($type === 'monthly') {
    // Anchor วันที่สำหรับ "12 เดือนล่าสุด" (default = today, แต่ Dashboard ส่ง latest_date มา)
    $anchor = $_GET['anchor'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) $anchor = date('Y-m-d');

    $d = new DateTime($anchor);
    $d->modify('first day of this month');
    $end = $d->format('Y-m-t');           // วันสุดท้ายของเดือน anchor
    $d->modify('-11 months');
    $from = $d->format('Y-m-d');          // 12 เดือนก่อน

    $result = mysqli_query($conn, "
        SELECT DATE_FORMAT(h.PoDate, '%Y-%m') AS month,
               COUNT(DISTINCT h.SeqNo) AS cnt,
               SUM(d.Amount) AS total
        FROM invpo0 h
        LEFT JOIN invpo1 d ON d.SeqNo = h.SeqNo
        WHERE h.PoDate BETWEEN '" . db_escape($from) . "' AND '" . db_escape($end) . "'
        GROUP BY DATE_FORMAT(h.PoDate, '%Y-%m')
        ORDER BY month ASC
    ");

    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) {
        $rows[] = [
            'month' => $r['month'],
            'cnt'   => (int)$r['cnt'],
            'total' => (float)$r['total'],
        ];
    }
    echo json_encode($rows);
} elseif ($type === 'dept') {
    // invpo1 ไม่มี DeptCode — group by LocaCode จาก header แทน
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to   = $_GET['date_to']   ?? date('Y-m-t');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-t');

    $result = mysqli_query($conn, "
        SELECT h.LocaCode AS DeptCode,
               COUNT(DISTINCT h.SeqNo) AS cnt,
               SUM(d.NetAmount) AS total
        FROM invpo0 h
        LEFT JOIN invpo1 d ON d.SeqNo = h.SeqNo
        WHERE h.PoDate BETWEEN '" . db_escape($date_from) . "' AND '" . db_escape($date_to) . "'
        GROUP BY h.LocaCode
        ORDER BY total DESC
        LIMIT 12
    ");

    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) {
        $rows[] = [
            'dept'  => db_str($r['DeptCode']),
            'cnt'   => (int)$r['cnt'],
            'total' => (float)$r['total'],
        ];
    }
    echo json_encode($rows);
} else {
    echo json_encode([]);
}
exit;
