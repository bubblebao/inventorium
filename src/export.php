<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$format = $_GET['format'] ?? 'excel'; // excel | pdf
$type   = $_GET['type']   ?? 'po_list'; // po_list | po_detail | vendor

switch ($type) {
    case 'po_detail': exportPoDetail($format); break;
    case 'vendor':    exportVendor($format);   break;
    default:          exportPoList($format);   break;
}

// ─── PO List Export ─────────────────────────────────────────────────────────
function exportPoList(string $format): void
{
    global $conn;

    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to   = $_GET['date_to']   ?? date('Y-m-t');
    $vnd_code  = $_GET['vnd_code']  ?? '';
    $inv_no    = $_GET['inv_no']    ?? '';
    $source    = $_GET['source']    ?? '';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-t');

    $where = "h.PoDate BETWEEN '" . db_escape($date_from) . "' AND '" . db_escape($date_to) . "'";
    if ($vnd_code !== '') $where .= " AND h.VndCode = '"   . db_escape($vnd_code) . "'";
    if ($inv_no   !== '') $where .= " AND h.PoNo LIKE '%"  . db_escape($inv_no)   . "%'";
    if ($source   !== '') $where .= " AND h.LocaCode = '"  . db_escape($source)   . "'";

    $result = mysqli_query($conn, "
        SELECT h.PoDate, h.PoNo, h.RefNo, h.VndCode, v.VndName,
               (SELECT SUM(Amount) FROM invpo1 WHERE SeqNo = h.SeqNo) AS TAmt,
               h.LocaCode, h.DeliveryDate, h.VndTerm, h.CreateUser, h.Remark
        FROM invpo0 h
        LEFT JOIN gblvend v ON h.VndCode = v.VndCode
        WHERE $where
        ORDER BY h.PoDate DESC, h.SeqNo DESC
    ");

    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    $title = 'รายการ PO ' . fmt_date($date_from) . ' ถึง ' . fmt_date($date_to);
    $headers = ['วันที่','เลข PO','Ref','รหัส Vendor','ชื่อ Vendor','ยอดรวม','ครบกำหนด','เครดิต','Location','ผู้บันทึก','หมายเหตุ'];

    $data = array_map(fn($r) => [
        fmt_date($r['PoDate']),
        $r['PoNo'],
        $r['RefNo'],
        $r['VndCode'],
        db_str($r['VndName']),
        (float)$r['TAmt'],
        fmt_date($r['DeliveryDate']),
        (int)$r['VndTerm'],
        $r['LocaCode'],
        db_str($r['CreateUser']),
        db_str($r['Remark']),
    ], $rows);

    $number_cols = [6]; // ยอดรวม column index (1-based header offset +1)

    if ($format === 'pdf') {
        outputPdf($title, $headers, $data, 'A4-L');
    } else {
        outputExcel($title, $headers, $data, 'po_list_' . date('Ymd'));
    }
}

// ─── PO Detail Export ───────────────────────────────────────────────────────
function exportPoDetail(string $format): void
{
    global $conn;
    $seq = (int)($_GET['seq'] ?? 0);
    if ($seq <= 0) { http_response_code(400); exit; }

    $r_hdr = mysqli_query($conn, "
        SELECT h.*, v.VndName, v.VndTel, v.VndTaxNo
        FROM invpo0 h LEFT JOIN gblvend v ON h.VndCode = v.VndCode
        WHERE h.SeqNo = $seq
    ");
    $po = mysqli_fetch_assoc($r_hdr);
    if (!$po) { http_response_code(404); exit; }

    $r_items = mysqli_query($conn, "
        SELECT d.DtlNo, d.PrdID, d.Remark, d.Qty, d.Unit,
               d.Price, d.NetAmount, d.TaxAmt, d.Amount
        FROM invpo1 d WHERE d.SeqNo = $seq ORDER BY d.DtlNo
    ");
    $items = [];
    while ($i = mysqli_fetch_assoc($r_items)) $items[] = $i;

    $title   = 'PO: ' . $po['PoNo'] . ' | ' . db_str($po['VndName']);
    $headers = ['#','รหัสสินค้า','รายละเอียด','จำนวน','หน่วย','ราคา/หน่วย','ก่อนภาษี','VAT','รวม'];
    $data = array_map(fn($i) => [
        $i['DtlNo'],
        $i['PrdID'],
        db_str($i['Remark']),
        (float)$i['Qty'],
        $i['Unit'],
        (float)$i['Price'],
        (float)$i['NetAmount'],
        (float)$i['TaxAmt'],
        (float)$i['Amount'],
    ], $items);

    if ($format === 'pdf') {
        outputPdf($title, $headers, $data, 'A4');
    } else {
        outputExcel($title, $headers, $data, 'po_detail_' . $seq . '_' . date('Ymd'));
    }
}

// ─── Vendor Export ──────────────────────────────────────────────────────────
function exportVendor(string $format): void
{
    global $conn;
    $result = mysqli_query($conn, "
        SELECT VndCode, VndName, VndPayee, VndTel, VndEmail, VndTaxNo, VndCurBal, VndTerm, VndMobile, VndCatCode
        FROM gblvend ORDER BY VndName
    ");
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    $title   = 'รายชื่อ Vendor — ' . fmt_date(date('Y-m-d'));
    $headers = ['รหัส','ชื่อ Vendor','ผู้รับเงิน','โทรศัพท์','Email','Tax No','ยอดค้าง','เครดิต(วัน)','มือถือ','หมวด'];
    $data = array_map(fn($r) => [
        $r['VndCode'], db_str($r['VndName']), db_str($r['VndPayee']),
        $r['VndTel'], $r['VndEmail'], $r['VndTaxNo'],
        (float)$r['VndCurBal'], (int)$r['VndTerm'],
        $r['VndMobile'], $r['VndCatCode'],
    ], $rows);

    if ($format === 'pdf') {
        outputPdf($title, $headers, $data, 'A4-L');
    } else {
        outputExcel($title, $headers, $data, 'vendor_' . date('Ymd'));
    }
}

// ─── Excel output ───────────────────────────────────────────────────────────
function outputExcel(string $title, array $headers, array $data, string $filename): void
{
    $ss = new Spreadsheet();
    $ws = $ss->getActiveSheet();
    $ws->setTitle(mb_substr($title, 0, 31));

    // Title row
    $ws->setCellValue('A1', $title);
    $ws->mergeCells('A1:' . chr(64 + count($headers)) . '1');
    $ws->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 13],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $ws->getRowDimension(1)->setRowHeight(20);

    // Header row
    $col = 'A';
    foreach ($headers as $h) {
        $ws->setCellValue($col . '2', $h);
        $col++;
    }
    $header_range = 'A2:' . chr(64 + count($headers)) . '2';
    $ws->getStyle($header_range)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A5C']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $ws->freezePane('A3');

    // Data rows
    $row = 3;
    foreach ($data as $dataRow) {
        $col = 'A';
        foreach ($dataRow as $idx => $val) {
            $ws->setCellValue($col . $row, $val);
            if (is_float($val)) {
                $ws->getStyle($col . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $col++;
        }
        if ($row % 2 === 0) {
            $ws->getStyle('A' . $row . ':' . chr(64 + count($headers)) . $row)
               ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F4F8');
        }
        $row++;
    }

    // Auto width
    foreach (range('A', chr(64 + count($headers))) as $c) {
        $ws->getColumnDimension($c)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($ss);
    $writer->save('php://output');
    exit;
}

// ─── PDF output ─────────────────────────────────────────────────────────────
function outputPdf(string $title, array $headers, array $data, string $format): void
{
    $mpdf = new \Mpdf\Mpdf([
        'mode'        => 'utf-8',
        'format'      => $format,
        'margin_top'  => 15,
        'margin_bottom' => 15,
    ]);

    $mpdf->SetTitle($title);

    $th_style = 'background:#1a3a5c;color:#fff;font-weight:bold;padding:6px 4px;font-size:11px;';
    $td_style = 'padding:5px 4px;font-size:10px;border-bottom:1px solid #ddd;';
    $td_alt   = 'background:#f0f4f8;' . $td_style;

    $head_html = '<thead><tr>' . implode('', array_map(fn($h) => "<th style=\"$th_style\">$h</th>", $headers)) . '</tr></thead>';

    $body_html = '<tbody>';
    foreach ($data as $idx => $row) {
        $style = $idx % 2 === 0 ? $td_style : $td_alt;
        $body_html .= '<tr>' . implode('', array_map(function ($val) use ($style) {
            $align = is_float($val) || is_int($val) ? 'text-align:right;' : '';
            $display = is_float($val) ? number_format($val, 2) : htmlspecialchars((string)$val);
            return "<td style=\"$style$align\">$display</td>";
        }, $row)) . '</tr>';
    }
    $body_html .= '</tbody>';

    $html = "
        <h2 style='color:#1a3a5c;font-size:14px;margin-bottom:8px;'>" . htmlspecialchars($title) . "</h2>
        <table style='width:100%;border-collapse:collapse;'>$head_html$body_html</table>
        <p style='font-size:9px;color:#999;margin-top:8px;'>พิมพ์เมื่อ: " . date('d/m/Y H:i') . "</p>
    ";

    $mpdf->WriteHTML($html);
    $mpdf->Output('report_' . date('Ymd') . '.pdf', 'D');
    exit;
}
