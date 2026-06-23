<?php
// ───────────────────────────────────────────────────────────────────────────
// Shared filter builder for Receiving report — recv_list.php + export.php
// รองรับการค้นหาเป็น "ช่วง" From/To ทุกช่อง (เหมือน Carmen Receiving Detail Report)
//   Date · Vendor · Location · Category · Product · Reference#  → range
//   Receiving Type (I/D) → single
// ต้อง require หลัง config/db.php (ใช้ db_escape)
// ───────────────────────────────────────────────────────────────────────────

// คืน inner condition สำหรับช่วง (ไม่มี "AND " นำหน้า) — ใช้ฝังใน subquery ได้
function recv_range_inner(string $col, string $from, string $to): string {
    $from = trim($from); $to = trim($to);
    if ($from !== '' && $to !== '')
        return "$col BETWEEN '" . db_escape($from) . "' AND '" . db_escape($to) . "'";
    if ($from !== '') return "$col >= '" . db_escape($from) . "'";
    if ($to   !== '') return "$col <= '" . db_escape($to)   . "'";
    return '';
}

// คืน clause พร้อม " AND " นำหน้า (หรือ '' ถ้าไม่ระบุช่วง)
function recv_range_clause(string $col, string $from, string $to): string {
    $inner = recv_range_inner($col, $from, $to);
    return $inner === '' ? '' : " AND $inner";
}

// อ่าน + normalize ทุก filter param จาก $_GET ($def_from/$def_to = ค่า default ของวันที่)
function recv_collect_params(string $def_from, string $def_to): array {
    $g = fn($k) => trim((string)($_GET[$k] ?? ''));
    $up = fn($k) => strtoupper($g($k)); // code ใน DB เป็นตัวพิมพ์ใหญ่ → forgiving

    $date_from = $g('date_from');
    $date_to   = $g('date_to');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = $def_from;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = $def_to;

    $inv_type = $g('inv_type');
    if (!in_array($inv_type, ['I', 'D'], true)) $inv_type = '';

    return [
        'date_from' => $date_from, 'date_to' => $date_to,
        'vnd_from'  => $up('vnd_from'), 'vnd_to' => $up('vnd_to'),
        'loc_from'  => $up('loc_from'), 'loc_to' => $up('loc_to'),
        'cat_from'  => $up('cat_from'), 'cat_to' => $up('cat_to'),
        'prd_from'  => $up('prd_from'), 'prd_to' => $up('prd_to'),
        'ref_from'  => $up('ref_from'), 'ref_to' => $up('ref_to'),
        'inv_type'  => $inv_type,
    ];
}

// สร้าง WHERE จาก params (alias header = h, ใช้ subquery สำหรับ product/category)
function recv_where_from_params(array $p): string {
    $w  = "h.RecvDate BETWEEN '" . db_escape($p['date_from']) . "' AND '" . db_escape($p['date_to']) . "'";
    $w .= recv_range_clause('h.VndCode',  $p['vnd_from'], $p['vnd_to']);
    $w .= recv_range_clause('h.LocaCode', $p['loc_from'], $p['loc_to']);
    $w .= recv_range_clause('h.RefNo',    $p['ref_from'], $p['ref_to']);
    if ($p['inv_type'] !== '') $w .= " AND h.InvType = '" . db_escape($p['inv_type']) . "'";

    // Product range → ข้าม table (DB เป็น MySQL 5.5 รองรับ subquery)
    $prd = recv_range_inner('PrdID', $p['prd_from'], $p['prd_to']);
    if ($prd !== '') $w .= " AND h.SeqNo IN (SELECT SeqNo FROM invrecv1 WHERE $prd)";

    // Category range → join gblprod
    $cat = recv_range_inner('p.CateCode', $p['cat_from'], $p['cat_to']);
    if ($cat !== '') $w .= " AND h.SeqNo IN (SELECT r.SeqNo FROM invrecv1 r JOIN gblprod p ON p.PrdId = r.PrdID WHERE $cat)";

    return $w;
}

// params ที่ไม่ว่าง — สำหรับ query string (export/pagination/sort links)
function recv_nonempty_params(array $p): array {
    return array_filter($p, fn($v) => $v !== '');
}
