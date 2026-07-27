<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/recv_filter.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// ─── Company Info (LS Pavilions Development) — declared FIRST ───────────────
define('COMPANY_NAME',    'LS Pavilions Development Co Ltd');
define('COMPANY_ADDRESS', '31/1 Moo 6 Choengthale Sub Dist Rd. Choengthale, Thalang, Phuket 83110');
define('COMPANY_TEL',     '0-7631-7600');
define('COMPANY_TAX_ID',  '0835546002941');

$format = $_GET['format'] ?? 'excel'; // excel | pdf
$type   = $_GET['type']   ?? 'po_list'; // po_list | po_detail | vendor | dept_report

switch ($type) {
    case 'po_detail':   exportPoDetail($format);   break;
    case 'vendor':      exportVendor($format);      break;
    case 'dept_report': exportDeptReport($format);  break;
    case 'recv_list':   exportRecvList($format);    break;
    case 'recv_detail': exportRecvDetail($format);  break;
    case 'item_list':   exportItemList($format);    break;
    case 'item_detail': exportItemDetail($format);  break;
    default:            exportPoList($format);      break;
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

    // Full PO + vendor + computed totals
    $r_hdr = mysqli_query($conn, "
        SELECT h.*,
               v.VndName, v.VndTel, v.VndFax, v.VndEmail, v.VndTaxNo,
               v.VndAdd1, v.VndAdd2, v.VndAdd3, v.VndAdd4, v.VndAttn,
               v.VndPayee, v.VndTerm,
               (SELECT SUM(Amount)    FROM invpo1 WHERE SeqNo = h.SeqNo) AS TotalAmount,
               (SELECT SUM(NetAmount) FROM invpo1 WHERE SeqNo = h.SeqNo) AS SubTotal,
               (SELECT SUM(TaxAmt)    FROM invpo1 WHERE SeqNo = h.SeqNo) AS TaxTotal,
               (SELECT SUM(Discount)  FROM invpo1 WHERE SeqNo = h.SeqNo) AS DiscountTotal
        FROM invpo0 h LEFT JOIN gblvend v ON h.VndCode = v.VndCode
        WHERE h.SeqNo = $seq
    ");
    $po = mysqli_fetch_assoc($r_hdr);
    if (!$po) { http_response_code(404); exit; }

    // JOIN gblprod เพื่อดึงชื่อสินค้า (Remark มัก empty)
    $r_items = mysqli_query($conn, "
        SELECT d.DtlNo, d.PrdID, d.Remark, d.Qty, d.Unit,
               d.Price, d.NetAmount, d.TaxAmt, d.Discount, d.Amount,
               p.PrdDescE, p.PrdDescT, p.BaseUnit
        FROM invpo1 d
        LEFT JOIN gblprod p ON p.PrdId = d.PrdID
        WHERE d.SeqNo = $seq ORDER BY d.DtlNo
    ");
    $items = [];
    while ($i = mysqli_fetch_assoc($r_items)) $items[] = $i;

    if ($format === 'pdf') {
        outputPoDetailPdf($po, $items);
    } else {
        // Excel — simple table for data analysis
        $title   = 'PO: ' . $po['PoNo'] . ' | ' . db_str($po['VndName']);
        $headers = ['#','รหัสสินค้า','รายละเอียด','จำนวน','หน่วย','ราคา/หน่วย','ก่อนภาษี','VAT','รวม'];

        // Description fallback chain
        $descFor = function($i) {
            $d = trim(db_str($i['Remark'] ?? ''));
            if ($d === '' || strcasecmp($d, 'NULL') === 0) $d = trim(db_str($i['PrdDescT'] ?? ''));
            if ($d === '' || strcasecmp($d, 'NULL') === 0) $d = trim(db_str($i['PrdDescE'] ?? ''));
            return $d ?: '—';
        };

        $data = array_map(fn($i) => [
            $i['DtlNo'], $i['PrdID'], $descFor($i),
            (float)$i['Qty'], $i['Unit'],
            (float)$i['Price'], (float)$i['NetAmount'],
            (float)$i['TaxAmt'], (float)$i['Amount'],
        ], $items);
        outputExcel($title, $headers, $data, 'po_detail_' . $seq . '_' . date('Ymd'));
    }
}

// ─── PO Detail PDF (Pavilions style) ────────────────────────────────────────
function outputPoDetailPdf(array $po, array $items): void
{
    $statusMap = ['1' => 'Draft', '2' => 'Pending Approval', '6' => 'Approved'];
    $status = $statusMap[(string)($po['PoStatus'] ?? '')] ?? ($po['PoStatus'] ?: '-');

    $subTotal      = (float)($po['SubTotal']      ?? 0);
    $taxTotal      = (float)($po['TaxTotal']      ?? 0);
    $discountTotal = (float)($po['DiscountTotal'] ?? 0);
    $totalAmount   = (float)($po['TotalAmount']   ?? 0);

    // Helper: clean NULL / empty fields
    $clean = function($v) {
        $v = trim((string)$v);
        if ($v === '' || strcasecmp($v, 'NULL') === 0) return null;
        return $v;
    };
    $addr_parts = [];
    foreach (['VndAdd1','VndAdd2','VndAdd3','VndAdd4'] as $f) {
        $p = $clean(db_str($po[$f] ?? ''));
        if ($p !== null) $addr_parts[] = $p;
    }
    $vAddr  = implode(' ', $addr_parts);
    $vTel   = $clean($po['VndTel']   ?? '');
    $vFax   = $clean($po['VndFax']   ?? '');
    $vEmail = $clean($po['VndEmail'] ?? '');
    $vTax   = $clean($po['VndTaxNo'] ?? '');
    $vPayee = $clean(db_str($po['VndPayee'] ?? ''));
    $vTerm  = (int)($po['VndTerm']   ?? 0);
    $remark = $clean(db_str($po['Remark'] ?? ''));

    ob_start();
    ?>
    <style>
      body{font-family:'sarabun','dejavusans';font-size:9pt;color:#1f2937;line-height:1.55}
      .label{color:#6b7280;font-size:7.5pt;letter-spacing:.5px;text-transform:uppercase}
      .section-title{color:#1e3a5f;font-size:9pt;font-weight:bold;letter-spacing:1px;
        text-transform:uppercase;border-bottom:1px solid #e5e7eb;padding-bottom:4px;margin-bottom:6px}
      .items{width:100%;border-collapse:collapse;margin-top:4px}
      .items th{background:#1e3a5f;color:#fff;padding:9px 8px;font-size:8.5pt;font-weight:bold;
        letter-spacing:.5px;text-transform:uppercase}
      .items td{padding:9px 8px;border-bottom:1px solid #f3f4f6;font-size:9pt;vertical-align:middle}
      .items tbody tr:nth-child(even) td{background:#fafbfc}
      .items tbody tr td:first-child{color:#9ca3af;font-weight:bold}
      .totals td{padding:5px 0;font-size:9.5pt}
      .grand{color:#1e3a5f;font-weight:bold;font-size:13pt}
      .grand-top{border-top:2px solid #1e3a5f;padding-top:8px!important}
    </style>

    <!-- Header — official Pavilions logo (png/jpg/jpeg), fallback to text -->
    <?php
    $logoPath = '';
    foreach (['png','jpg','jpeg','gif'] as $ext) {
        $p = __DIR__ . '/assets/pavilions-logo.' . $ext;
        if (is_file($p)) { $logoPath = $p; break; }
    }
    $hasLogo = $logoPath !== '';
    ?>
    <!-- Header — Logo + Title + Status badge -->
    <table width="100%" style="border-bottom:2px solid #1e3a5f;padding-bottom:12px;margin-bottom:16px">
      <tr>
        <td width="85" style="vertical-align:middle;text-align:center">
          <?php if ($hasLogo): ?>
            <img src="<?= $logoPath ?>" style="width:72px;height:72px">
          <?php else: ?>
            <div style="width:55px;height:55px;background:#1e3a5f;color:#fff;text-align:center;font-weight:bold;font-size:20pt;border-radius:50%;line-height:55px;margin:auto">TP</div>
          <?php endif; ?>
        </td>
        <td style="text-align:center;vertical-align:middle">
          <div style="font-size:20pt;font-weight:bold;color:#1e3a5f;letter-spacing:4px">PURCHASE ORDER</div>
          <div style="font-size:12pt;font-weight:bold;color:#1f2937;margin-top:5px"><?= htmlspecialchars(COMPANY_NAME) ?></div>
          <div style="font-size:8pt;color:#6b7280;margin-top:2px"><?= htmlspecialchars(COMPANY_ADDRESS) ?></div>
          <div style="font-size:8pt;color:#6b7280">Tel <?= htmlspecialchars(COMPANY_TEL) ?>  ·  Tax ID <?= htmlspecialchars(COMPANY_TAX_ID) ?></div>
        </td>
        <td width="85" style="vertical-align:top;text-align:right;padding-top:6px">
          <div style="background:#1e3a5f;color:#fff;padding:5px 12px;border-radius:14px;font-size:8pt;font-weight:bold;letter-spacing:.5px;display:inline-block">
            <?= htmlspecialchars(strtoupper($status)) ?>
          </div>
        </td>
      </tr>
    </table>

    <!-- PO Info — clean grid, no frames -->
    <table width="100%" style="margin-bottom:18px">
      <tr>
        <td width="33%" style="padding:2px 0">
          <div class="label">PO Number</div>
          <div style="font-weight:bold;font-size:12pt;color:#1e3a5f;margin-top:2px"><?= htmlspecialchars($po['PoNo']) ?></div>
        </td>
        <td width="33%" style="padding:2px 0">
          <div class="label">PO Date</div>
          <div style="font-weight:bold;font-size:10pt;margin-top:2px"><?= fmt_date($po['PoDate']) ?></div>
        </td>
        <td width="34%" style="padding:2px 0">
          <div class="label">Delivery Date</div>
          <div style="font-weight:bold;font-size:10pt;color:#0ea5e9;margin-top:2px"><?= fmt_date($po['DeliveryDate']) ?></div>
        </td>
      </tr>
      <?php if (!empty($po['PrNo']) || !empty($po['RefNo']) || !empty($po['LocaCode'])): ?>
      <tr>
        <td style="padding:10px 0 2px 0;font-size:9pt">
          <?php if (!empty($po['PrNo'])): ?><span class="label">REQ No</span>  <strong><?= htmlspecialchars($po['PrNo']) ?></strong><?php endif; ?>
        </td>
        <td style="padding:10px 0 2px 0;font-size:9pt">
          <?php if (!empty($po['RefNo'])): ?><span class="label">Reference</span>  <strong><?= htmlspecialchars($po['RefNo']) ?></strong><?php endif; ?>
        </td>
        <td style="padding:10px 0 2px 0;font-size:9pt">
          <?php if (!empty($po['LocaCode'])): ?><span class="label">Branch</span>  <strong><?= htmlspecialchars($po['LocaCode']) ?></strong><?php endif; ?>
        </td>
      </tr>
      <?php endif; ?>
    </table>

    <!-- Delivery + Supplier — frameless, divider only -->
    <table width="100%" style="margin-bottom:16px">
      <tr>
        <td width="48%" style="vertical-align:top">
          <div class="section-title">Delivery to</div>
          <div style="font-weight:bold;font-size:10.5pt"><?= htmlspecialchars(COMPANY_NAME) ?></div>
          <div style="font-size:9pt;color:#4b5563;margin-top:3px"><?= htmlspecialchars(COMPANY_ADDRESS) ?></div>
          <div style="font-size:9pt;color:#4b5563;margin-top:5px">
            Tel <?= htmlspecialchars(COMPANY_TEL) ?><br>
            Tax ID <?= htmlspecialchars(COMPANY_TAX_ID) ?>
          </div>
          <?php if (!empty($po['CreateUser'])): ?>
          <div style="font-size:9pt;color:#4b5563;margin-top:5px">
            Attn  <strong><?= htmlspecialchars(db_str($po['CreateUser'])) ?></strong>
          </div>
          <?php endif; ?>
        </td>
        <td width="4%"></td>
        <td width="48%" style="vertical-align:top">
          <div class="section-title">Supplier</div>
          <div style="font-weight:bold;font-size:10.5pt"><?= htmlspecialchars(db_str($po['VndName'])) ?></div>
          <div style="font-size:8pt;color:#9ca3af">Code  <?= htmlspecialchars($po['VndCode']) ?></div>
          <?php if ($vAddr): ?>
          <div style="font-size:9pt;color:#4b5563;margin-top:3px"><?= htmlspecialchars($vAddr) ?></div>
          <?php endif; ?>
          <div style="font-size:9pt;color:#4b5563;margin-top:5px">
            <?php if ($vTel): ?>Tel <?= htmlspecialchars($vTel) ?><?php endif; ?>
            <?php if ($vFax): ?><?= $vTel ? '  ·  ' : '' ?>Fax <?= htmlspecialchars($vFax) ?><?php endif; ?>
            <?php if ($vEmail): ?><br><?= htmlspecialchars($vEmail) ?><?php endif; ?>
            <?php if ($vTax):   ?><br>Tax ID <?= htmlspecialchars($vTax) ?><?php endif; ?>
            <?php if ($vTerm > 0): ?><br>Payment Terms  <strong><?= $vTerm ?> Days</strong><?php endif; ?>
          </div>
        </td>
      </tr>
    </table>

    <?php if ($remark): ?>
    <div style="margin-bottom:14px;padding:10px 14px;background:#fef3c7;border-left:3px solid #f59e0b;font-size:9pt;color:#78350f;border-radius:0 4px 4px 0">
      <span class="label" style="color:#92400e">Subject</span><br>
      <span style="font-size:9.5pt"><?= nl2br(htmlspecialchars($remark)) ?></span>
    </div>
    <?php endif; ?>

    <!-- Items table -->
    <table class="items" style="margin-top:10px">
      <thead>
        <tr>
          <th width="22">#</th>
          <th width="75" style="text-align:left">Item Code</th>
          <th style="text-align:left">Product Description</th>
          <th width="55" style="text-align:right">Qty</th>
          <th width="40">Unit</th>
          <th width="65" style="text-align:right">Unit Price</th>
          <th width="60" style="text-align:right">Tax</th>
          <th width="80" style="text-align:right">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i => $item):
          // Description fallback: Remark > PrdDescT (Thai) > PrdDescE (English) > '-'
          $desc = trim(db_str($item['Remark'] ?? ''));
          if ($desc === '' || strcasecmp($desc, 'NULL') === 0) $desc = trim(db_str($item['PrdDescT'] ?? ''));
          if ($desc === '' || strcasecmp($desc, 'NULL') === 0) $desc = trim(db_str($item['PrdDescE'] ?? ''));
          if ($desc === '' || strcasecmp($desc, 'NULL') === 0) $desc = '—';
        ?>
        <tr<?= $i % 2 === 0 ? '' : ' style="background:#fafbfc"' ?>>
          <td style="text-align:center"><?= $i + 1 ?></td>
          <td style="font-family:monospace;font-size:8pt"><?= htmlspecialchars($item['PrdID']) ?></td>
          <td><?= htmlspecialchars($desc) ?></td>
          <td style="text-align:right"><?= number_format((float)$item['Qty'], 2) ?></td>
          <td style="text-align:center"><?= htmlspecialchars($item['Unit']) ?></td>
          <td style="text-align:right"><?= number_format((float)$item['Price'], 2) ?></td>
          <td style="text-align:right"><?= number_format((float)$item['TaxAmt'], 2) ?></td>
          <td style="text-align:right;font-weight:bold"><?= number_format((float)$item['Amount'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Totals — right-aligned, no frame -->
    <table width="100%" style="margin-top:14px">
      <tr>
        <td width="60%"></td>
        <td width="40%">
          <table width="100%" class="totals">
            <tr>
              <td style="color:#6b7280">Sub Total</td>
              <td style="text-align:right"><?= number_format($subTotal, 2) ?></td>
            </tr>
            <tr>
              <td style="color:#6b7280">VAT</td>
              <td style="text-align:right"><?= number_format($taxTotal, 2) ?></td>
            </tr>
            <?php if ($discountTotal > 0): ?>
            <tr>
              <td style="color:#6b7280">Discount</td>
              <td style="text-align:right;color:#dc2626">(<?= number_format($discountTotal, 2) ?>)</td>
            </tr>
            <?php endif; ?>
            <tr>
              <td class="grand grand-top">Total Amount Due</td>
              <td class="grand grand-top" style="text-align:right"><?= number_format($totalAmount, 2) ?></td>
            </tr>
            <tr>
              <td colspan="2" style="text-align:right;color:#9ca3af;font-size:8pt;padding-top:2px">Thai Baht (THB)</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>

    <!-- Footer notes -->
    <div style="margin-top:24px;padding-top:10px;border-top:1px solid #e5e7eb;font-size:7.5pt;color:#6b7280;line-height:1.6">
      <div style="color:#374151;font-weight:bold;font-size:8pt;letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px">Terms &amp; Conditions</div>
      <ol style="padding-left:14px;margin:0">
        <li>Tax Invoice / Delivery Order must accompany delivery and reference our PO Number above.</li>
        <li>Delivery accepted by Receiving Department, who returns duplicate of Invoice / Delivery Order duly stamped.</li>
        <li>A copy of this purchase order must be attached to the invoice when submitted.</li>
        <?php if (!empty($po['LocaCode'])): ?>
        <li>Delivery place at <?= htmlspecialchars($po['LocaCode']) ?> branch.</li>
        <?php endif; ?>
      </ol>
    </div>

    <!-- Signature blocks — generous space + breathing room + dotted date fields -->
    <?php
    $sigBlock = function($label) {
        $dot = '<span style="color:#d1d5db;letter-spacing:1px">.......</span>';
        return
            // signing area (empty space above line)
            '<div style="height:75px">&nbsp;</div>'.
            // signature line
            '<div style="border-top:1px solid #6b7280">&nbsp;</div>'.
            // label (small uppercase)
            '<div style="padding-top:12px;color:#9ca3af;font-size:7pt;letter-spacing:1.2px;text-transform:uppercase">'.
              'Authorised Signature'.
            '</div>'.
            // gap
            '<div style="height:8px">&nbsp;</div>'.
            // role name (bold prominent)
            '<div style="font-weight:bold;color:#1e3a5f;font-size:11pt;letter-spacing:.5px">'.
              $label.
            '</div>'.
            // gap
            '<div style="height:14px">&nbsp;</div>'.
            // date row (nowrap, shorter dots)
            '<div style="color:#6b7280;font-size:8.5pt;white-space:nowrap">'.
              'Date&nbsp;&nbsp;'.$dot.'&nbsp;/&nbsp;'.$dot.'&nbsp;/&nbsp;'.$dot.
            '</div>';
    };
    ?>
    <table width="100%" style="margin-top:42px;font-size:8.5pt;color:#374151">
      <tr>
        <td width="31%" align="center" valign="bottom" style="padding:0 8px">
          <?= $sigBlock('Purchasing') ?>
        </td>
        <td width="3.5%"></td>
        <td width="31%" align="center" valign="bottom" style="padding:0 8px">
          <?= $sigBlock('Finance Controller') ?>
        </td>
        <td width="3.5%"></td>
        <td width="31%" align="center" valign="bottom" style="padding:0 8px">
          <?= $sigBlock('General Manager') ?>
        </td>
      </tr>
    </table>

    <div style="text-align:center;color:#d1d5db;font-size:7pt;margin-top:24px;padding-top:8px;border-top:1px solid #f3f4f6">
      Generated by Inventorium  ·  <?= date('d/m/Y H:i') ?>  ·  PO #<?= htmlspecialchars($po['PoNo']) ?>  ·  Page 1
    </div>
    <?php
    $html = ob_get_clean();

    $mpdf = makeMpdf('A4');
    $mpdf->SetTitle('PO ' . $po['PoNo']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('PO_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $po['PoNo']) . '_' . date('Ymd') . '.pdf', 'D');
    exit;
}

// ─── Vendor Export ──────────────────────────────────────────────────────────
function exportVendor(string $format): void
{
    global $conn;

    $search     = trim($_GET['search'] ?? '');
    $vnd_from   = strtoupper(trim($_GET['vnd_from']   ?? ''));
    $vnd_to     = strtoupper(trim($_GET['vnd_to']     ?? ''));
    $vname_from = trim($_GET['vname_from'] ?? '');
    $vname_to   = trim($_GET['vname_to']   ?? '');

    $where = '1=1';
    if ($search !== '') {
        $s_ascii = db_escape($search);
        $s_tis   = db_search($search);
        $where .= " AND (VndName LIKE '%$s_tis%' OR VndCode LIKE '%$s_ascii%' OR VndAdd1 LIKE '%$s_tis%' OR VndTel LIKE '%$s_ascii%' OR VndTaxNo LIKE '%$s_ascii%')";
    }
    $where .= recv_range_clause('VndCode', $vnd_from, $vnd_to);
    $where .= recv_range_clause('VndName', $vname_from, $vname_to);

    $result = mysqli_query($conn, "
        SELECT VndCode, VndName, VndAdd1, VndAdd2, VndAdd3, VndAdd4,
               VndTel, VndEmail, VndTaxNo, VndCurBal, VndTerm, VndMobile, VndCatCode
        FROM gblvend
        WHERE $where
        ORDER BY VndName
    ");
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    // Filter out literal "NULL" strings from address fields
    $clean = function(?string $v): string {
        $v = trim(db_str($v ?? ''));
        return (strcasecmp($v, 'NULL') === 0 || $v === '') ? '' : $v;
    };

    $title = 'รายชื่อ Vendor — ' . fmt_date(date('Y-m-d'));
    if ($search !== '') $title .= ' (ค้นหา: ' . $search . ')';
    if ($vnd_from !== '' || $vnd_to !== '') $title .= ' [' . ($vnd_from ?: '…') . '-' . ($vnd_to ?: '…') . ']';
    $headers = ['รหัส','ชื่อ Vendor','ที่อยู่ 1','ที่อยู่ 2','ที่อยู่ 3','ที่อยู่ 4','โทรศัพท์','Email','Tax No','ยอดค้าง','เครดิต(วัน)','มือถือ','หมวด'];
    $data = array_map(fn($r) => [
        $r['VndCode'], db_str($r['VndName']),
        $clean($r['VndAdd1']), $clean($r['VndAdd2']),
        $clean($r['VndAdd3']), $clean($r['VndAdd4']),
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

// ─── Dept Report Export ─────────────────────────────────────────────────────
function exportDeptReport(string $format): void
{
    global $conn;
    $date_from = $_GET['date_from'] ?? date('Y-01-01');
    $date_to   = $_GET['date_to']   ?? date('Y-12-31');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-01-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-12-31');

    $result = mysqli_query($conn, "
        SELECT h.LocaCode AS DeptCode,
               COUNT(DISTINCT h.SeqNo) AS po_cnt,
               SUM(d.NetAmount)        AS total_abt,
               SUM(d.TaxAmt)           AS total_tax
        FROM invpo0 h
        LEFT JOIN invpo1 d ON d.SeqNo = h.SeqNo
        WHERE h.PoDate BETWEEN '" . db_escape($date_from) . "' AND '" . db_escape($date_to) . "'
        GROUP BY h.LocaCode
        ORDER BY total_abt DESC
    ");
    $rows = [];
    $grand = 0;
    while ($r = mysqli_fetch_assoc($result)) {
        $r['DeptCode'] = db_str($r['DeptCode']);
        $rows[] = $r;
        $grand += (float)$r['total_abt'];
    }

    $title   = 'รายงานตาม Location — ' . fmt_date($date_from) . ' ถึง ' . fmt_date($date_to);
    $headers = ['Location', 'จำนวน PO', 'ยอดก่อนภาษี (บาท)', 'VAT (บาท)', '% ของรวม'];
    $data = array_map(function($r) use ($grand) {
        $pct = $grand > 0 ? round((float)$r['total_abt'] / $grand * 100, 2) : 0;
        return [
            $r['DeptCode'] ?: '(ไม่ระบุ)',
            (int)$r['po_cnt'],
            (float)$r['total_abt'],
            (float)$r['total_tax'],
            $pct,
        ];
    }, $rows);

    if ($format === 'pdf') {
        outputPdf($title, $headers, $data, 'A4');
    } else {
        outputExcel($title, $headers, $data, 'dept_report_' . date('Ymd'));
    }
}

// ─── Receiving List Export ──────────────────────────────────────────────────
function exportRecvList(string $format): void
{
    global $conn;

    $year = (int)date('Y');
    $p     = recv_collect_params("$year-01-01", "$year-12-31");
    $where = recv_where_from_params($p);
    $date_from = $p['date_from'];
    $date_to   = $p['date_to'];

    $result = mysqli_query($conn, "
        SELECT h.RecvDate, h.RecvNo, h.RefNo, h.PoNo, h.VndCode, v.VndName,
               h.InvType, h.LocaCode, h.CreateUser, h.Remark,
               (SELECT SUM(Amount) FROM invrecv1 WHERE SeqNo = h.SeqNo) AS TAmt
        FROM invrecv0 h
        LEFT JOIN gblvend v ON h.VndCode = v.VndCode
        WHERE $where
        ORDER BY h.RecvDate DESC, h.SeqNo DESC
    ");

    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    $typeLabel = fn($t) => $t === 'I' ? 'Inventory' : ($t === 'D' ? 'Direct' : (string)$t);

    $title   = 'Receiving List ' . fmt_date($date_from) . ' - ' . fmt_date($date_to);
    $headers = ['วันที่รับ','เลขที่รับ','PO No','Ref','รหัส Vendor','ชื่อ Vendor','ประเภท','Location','ยอดรวม','ผู้บันทึก','หมายเหตุ'];
    $data = array_map(fn($r) => [
        fmt_date($r['RecvDate']),
        $r['RecvNo'],
        $r['PoNo'],
        $r['RefNo'],
        $r['VndCode'],
        db_str($r['VndName']),
        $typeLabel($r['InvType']),
        $r['LocaCode'],
        (float)$r['TAmt'],
        db_str($r['CreateUser']),
        db_str($r['Remark']),
    ], $rows);

    if ($format === 'pdf') {
        outputPdf($title, $headers, $data, 'A4-L');
    } else {
        outputExcel($title, $headers, $data, 'recv_list_' . date('Ymd'));
    }
}

// ─── Receiving Detail Export ────────────────────────────────────────────────
function exportRecvDetail(string $format): void
{
    global $conn;
    $seq = (int)($_GET['seq'] ?? 0);
    if ($seq <= 0) { http_response_code(400); exit; }

    $r_hdr = mysqli_query($conn, "
        SELECT h.*,
               v.VndName, v.VndTel, v.VndFax, v.VndEmail, v.VndTaxNo,
               v.VndAdd1, v.VndAdd2, v.VndAdd3, v.VndAdd4, v.VndPayee,
               (SELECT SUM(Amount)    FROM invrecv1 WHERE SeqNo = h.SeqNo) AS TotalAmount,
               (SELECT SUM(NetAmount) FROM invrecv1 WHERE SeqNo = h.SeqNo) AS SubTotal,
               (SELECT SUM(TaxAmt)    FROM invrecv1 WHERE SeqNo = h.SeqNo) AS TaxTotal
        FROM invrecv0 h LEFT JOIN gblvend v ON h.VndCode = v.VndCode
        WHERE h.SeqNo = $seq
    ");
    $rv = mysqli_fetch_assoc($r_hdr);
    if (!$rv) { http_response_code(404); exit; }

    $r_items = mysqli_query($conn, "
        SELECT d.DtlNo, d.PrdID, d.Remark, d.Qty, d.Unit, d.Cost, d.UnitRate,
               d.NetAmount, d.TaxAmt, d.Amount, d.DeptCode, d.AcCode,
               p.PrdDescE, p.PrdDescT
        FROM invrecv1 d
        LEFT JOIN gblprod p ON p.PrdId = d.PrdID
        WHERE d.SeqNo = $seq ORDER BY d.DtlNo
    ");
    $items = [];
    while ($i = mysqli_fetch_assoc($r_items)) $items[] = $i;

    if ($format === 'pdf') {
        outputRecvDetailPdf($rv, $items);
    } else {
        $descFor = function($i) {
            $d = trim(db_str($i['Remark'] ?? ''));
            if ($d === '' || strcasecmp($d, 'NULL') === 0) $d = trim(db_str($i['PrdDescT'] ?? ''));
            if ($d === '' || strcasecmp($d, 'NULL') === 0) $d = trim(db_str($i['PrdDescE'] ?? ''));
            return $d ?: '—';
        };
        $title   = 'RCV: ' . $rv['RecvNo'] . ' | ' . db_str($rv['VndName']);
        $headers = ['#','รหัสสินค้า','รายละเอียด','แผนก','บัญชี','จำนวน','หน่วย','ทุน/หน่วย','อัตรา','ก่อนภาษี','ภาษี','รวมสุทธิ'];
        $data = array_map(fn($i) => [
            $i['DtlNo'], $i['PrdID'], $descFor($i),
            $i['DeptCode'], $i['AcCode'],
            (float)$i['Qty'], $i['Unit'],
            (float)$i['Cost'], (float)$i['UnitRate'],
            (float)$i['NetAmount'], (float)$i['TaxAmt'], (float)$i['Amount'],
        ], $items);
        outputExcel($title, $headers, $data, 'recv_detail_' . $seq . '_' . date('Ymd'));
    }
}

// ─── Receiving Detail PDF (Pavilions letterhead) ────────────────────────────
function outputRecvDetailPdf(array $rv, array $items): void
{
    $typeLabel = ($rv['InvType'] ?? '') === 'I' ? 'Inventory' : 'Direct';

    $subTotal    = (float)($rv['SubTotal']    ?? 0);
    $taxTotal    = (float)($rv['TaxTotal']    ?? 0);
    $totalAmount = (float)($rv['TotalAmount'] ?? 0);
    $extCost     = (float)($rv['ExtCost']     ?? 0);

    $clean = function($v) {
        $v = trim((string)$v);
        if ($v === '' || strcasecmp($v, 'NULL') === 0) return null;
        return $v;
    };
    $addr_parts = [];
    foreach (['VndAdd1','VndAdd2','VndAdd3','VndAdd4'] as $f) {
        $p = $clean(db_str($rv[$f] ?? ''));
        if ($p !== null) $addr_parts[] = $p;
    }
    $vAddr  = implode(' ', $addr_parts);
    $vTel   = $clean($rv['VndTel']   ?? '');
    $vTax   = $clean($rv['VndTaxNo'] ?? '');
    $remark = $clean(db_str($rv['Remark'] ?? ''));

    ob_start();
    ?>
    <style>
      body{font-family:'sarabun','dejavusans';font-size:9pt;color:#1f2937;line-height:1.55}
      .label{color:#6b7280;font-size:7.5pt;letter-spacing:.5px;text-transform:uppercase}
      .section-title{color:#1e3a5f;font-size:9pt;font-weight:bold;letter-spacing:1px;
        text-transform:uppercase;border-bottom:1px solid #e5e7eb;padding-bottom:4px;margin-bottom:6px}
      .items{width:100%;border-collapse:collapse;margin-top:4px}
      .items th{background:#1e3a5f;color:#fff;padding:8px 5px;font-size:7.5pt;font-weight:bold;
        letter-spacing:.3px;text-transform:uppercase}
      .items td{padding:7px 5px;border-bottom:1px solid #f3f4f6;font-size:8.5pt;vertical-align:middle}
      .items tbody tr:nth-child(even) td{background:#fafbfc}
      .totals td{padding:5px 0;font-size:9.5pt}
      .grand{color:#1e3a5f;font-weight:bold;font-size:13pt}
      .grand-top{border-top:2px solid #1e3a5f;padding-top:8px!important}
    </style>

    <?php
    $logoPath = '';
    foreach (['png','jpg','jpeg','gif'] as $ext) {
        $p = __DIR__ . '/assets/pavilions-logo.' . $ext;
        if (is_file($p)) { $logoPath = $p; break; }
    }
    $hasLogo = $logoPath !== '';
    ?>
    <!-- Header -->
    <table width="100%" style="border-bottom:2px solid #1e3a5f;padding-bottom:12px;margin-bottom:16px">
      <tr>
        <td width="85" style="vertical-align:middle;text-align:center">
          <?php if ($hasLogo): ?>
            <img src="<?= $logoPath ?>" style="width:72px;height:72px">
          <?php else: ?>
            <div style="width:55px;height:55px;background:#1e3a5f;color:#fff;text-align:center;font-weight:bold;font-size:20pt;border-radius:50%;line-height:55px;margin:auto">TP</div>
          <?php endif; ?>
        </td>
        <td style="text-align:center;vertical-align:middle">
          <div style="font-size:18pt;font-weight:bold;color:#1e3a5f;letter-spacing:3px">RECEIVING REPORT</div>
          <div style="font-size:12pt;font-weight:bold;color:#1f2937;margin-top:5px"><?= htmlspecialchars(COMPANY_NAME) ?></div>
          <div style="font-size:8pt;color:#6b7280;margin-top:2px"><?= htmlspecialchars(COMPANY_ADDRESS) ?></div>
          <div style="font-size:8pt;color:#6b7280">Tel <?= htmlspecialchars(COMPANY_TEL) ?>  ·  Tax ID <?= htmlspecialchars(COMPANY_TAX_ID) ?></div>
        </td>
        <td width="85" style="vertical-align:top;text-align:right;padding-top:6px">
          <div style="background:#1e3a5f;color:#fff;padding:5px 12px;border-radius:14px;font-size:8pt;font-weight:bold;letter-spacing:.5px;display:inline-block">
            <?= htmlspecialchars(strtoupper($typeLabel)) ?>
          </div>
        </td>
      </tr>
    </table>

    <!-- Receiving Info -->
    <table width="100%" style="margin-bottom:18px">
      <tr>
        <td width="33%" style="padding:2px 0">
          <div class="label">Receiving No</div>
          <div style="font-weight:bold;font-size:12pt;color:#1e3a5f;margin-top:2px"><?= htmlspecialchars($rv['RecvNo']) ?></div>
        </td>
        <td width="33%" style="padding:2px 0">
          <div class="label">Receiving Date</div>
          <div style="font-weight:bold;font-size:10pt;margin-top:2px"><?= fmt_date($rv['RecvDate']) ?></div>
        </td>
        <td width="34%" style="padding:2px 0">
          <div class="label">PO Number</div>
          <div style="font-weight:bold;font-size:10pt;color:#0ea5e9;margin-top:2px"><?= htmlspecialchars($rv['PoNo'] ?: '-') ?></div>
        </td>
      </tr>
      <tr>
        <td style="padding:10px 0 2px 0;font-size:9pt">
          <?php if (!empty($rv['RefNo'])): ?><span class="label">Reference</span>  <strong><?= htmlspecialchars($rv['RefNo']) ?></strong><?php endif; ?>
        </td>
        <td style="padding:10px 0 2px 0;font-size:9pt">
          <?php if (!empty($rv['LocaCode'])): ?><span class="label">Location</span>  <strong><?= htmlspecialchars($rv['LocaCode']) ?></strong><?php endif; ?>
        </td>
        <td style="padding:10px 0 2px 0;font-size:9pt">
          <?php if (!empty($rv['CreateUser'])): ?><span class="label">Received By</span>  <strong><?= htmlspecialchars(db_str($rv['CreateUser'])) ?></strong><?php endif; ?>
        </td>
      </tr>
    </table>

    <!-- Supplier -->
    <table width="100%" style="margin-bottom:16px">
      <tr>
        <td width="48%" style="vertical-align:top">
          <div class="section-title">Received At</div>
          <div style="font-weight:bold;font-size:10.5pt"><?= htmlspecialchars(COMPANY_NAME) ?></div>
          <div style="font-size:9pt;color:#4b5563;margin-top:3px"><?= htmlspecialchars($rv['LocaCode'] ?: '') ?></div>
        </td>
        <td width="4%"></td>
        <td width="48%" style="vertical-align:top">
          <div class="section-title">Supplier</div>
          <div style="font-weight:bold;font-size:10.5pt"><?= htmlspecialchars(db_str($rv['VndName'])) ?></div>
          <div style="font-size:8pt;color:#9ca3af">Code  <?= htmlspecialchars($rv['VndCode']) ?></div>
          <?php if ($vAddr): ?><div style="font-size:9pt;color:#4b5563;margin-top:3px"><?= htmlspecialchars($vAddr) ?></div><?php endif; ?>
          <div style="font-size:9pt;color:#4b5563;margin-top:5px">
            <?php if ($vTel): ?>Tel <?= htmlspecialchars($vTel) ?><?php endif; ?>
            <?php if ($vTax): ?><br>Tax ID <?= htmlspecialchars($vTax) ?><?php endif; ?>
          </div>
        </td>
      </tr>
    </table>

    <?php if ($remark): ?>
    <div style="margin-bottom:14px;padding:10px 14px;background:#fef3c7;border-left:3px solid #f59e0b;font-size:9pt;color:#78350f;border-radius:0 4px 4px 0">
      <span class="label" style="color:#92400e">Remark</span><br>
      <span style="font-size:9.5pt"><?= nl2br(htmlspecialchars($remark)) ?></span>
    </div>
    <?php endif; ?>

    <!-- Items table -->
    <table class="items" style="margin-top:10px">
      <thead>
        <tr>
          <th width="20">#</th>
          <th width="62" style="text-align:left">Item Code</th>
          <th style="text-align:left">Description</th>
          <th width="58" style="text-align:left">Dep/Acc</th>
          <th width="34">Unit</th>
          <th width="42" style="text-align:right">Qty</th>
          <th width="48" style="text-align:right">Cost/Unit</th>
          <th width="60" style="text-align:right">Ex.Tax</th>
          <th width="48" style="text-align:right">Tax</th>
          <th width="62" style="text-align:right">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $idx => $item):
          $desc = trim(db_str($item['Remark'] ?? ''));
          if ($desc === '' || strcasecmp($desc, 'NULL') === 0) $desc = trim(db_str($item['PrdDescT'] ?? ''));
          if ($desc === '' || strcasecmp($desc, 'NULL') === 0) $desc = trim(db_str($item['PrdDescE'] ?? ''));
          if ($desc === '' || strcasecmp($desc, 'NULL') === 0) $desc = '—';
          $dep = trim((string)($item['DeptCode'] ?? ''));
          $acc = trim((string)($item['AcCode'] ?? ''));
          $depAcc = $dep . ($dep !== '' && $acc !== '' ? '/' : '') . $acc;
        ?>
        <tr<?= $idx % 2 === 0 ? '' : ' style="background:#fafbfc"' ?>>
          <td style="text-align:center"><?= $idx + 1 ?></td>
          <td style="font-family:monospace;font-size:7.5pt"><?= htmlspecialchars($item['PrdID']) ?></td>
          <td><?= htmlspecialchars($desc) ?></td>
          <td style="font-size:7.5pt;color:#6b7280"><?= htmlspecialchars($depAcc) ?></td>
          <td style="text-align:center"><?= htmlspecialchars($item['Unit']) ?></td>
          <td style="text-align:right"><?= number_format((float)$item['Qty'], 2) ?></td>
          <td style="text-align:right"><?= number_format((float)$item['Cost'], 2) ?></td>
          <td style="text-align:right"><?= number_format((float)$item['NetAmount'], 2) ?></td>
          <td style="text-align:right"><?= number_format((float)$item['TaxAmt'], 2) ?></td>
          <td style="text-align:right;font-weight:bold"><?= number_format((float)$item['Amount'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Totals -->
    <table width="100%" style="margin-top:14px">
      <tr>
        <td width="60%"></td>
        <td width="40%">
          <table width="100%" class="totals">
            <tr><td style="color:#6b7280">Sub Total (Ex.Tax)</td><td style="text-align:right"><?= number_format($subTotal, 2) ?></td></tr>
            <tr><td style="color:#6b7280">VAT</td><td style="text-align:right"><?= number_format($taxTotal, 2) ?></td></tr>
            <?php if ($extCost > 0): ?>
            <tr><td style="color:#6b7280">Extra Cost</td><td style="text-align:right"><?= number_format($extCost, 2) ?></td></tr>
            <?php endif; ?>
            <tr><td class="grand grand-top">Total Amount</td><td class="grand grand-top" style="text-align:right"><?= number_format($totalAmount, 2) ?></td></tr>
            <tr><td colspan="2" style="text-align:right;color:#9ca3af;font-size:8pt;padding-top:2px">Thai Baht (THB)</td></tr>
          </table>
        </td>
      </tr>
    </table>

    <div style="text-align:center;color:#d1d5db;font-size:7pt;margin-top:24px;padding-top:8px;border-top:1px solid #f3f4f6">
      Generated by Inventorium  ·  <?= date('d/m/Y H:i') ?>  ·  RCV #<?= htmlspecialchars($rv['RecvNo']) ?>  ·  Page 1
    </div>
    <?php
    $html = ob_get_clean();

    $mpdf = makeMpdf('A4');
    $mpdf->SetTitle('RCV ' . $rv['RecvNo']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('RCV_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $rv['RecvNo']) . '_' . date('Ymd') . '.pdf', 'D');
    exit;
}

// ─── Item List Export ───────────────────────────────────────────────────────
function exportItemList(string $format): void
{
    global $conn;

    $search   = trim($_GET['search'] ?? '');
    $prd_from = strtoupper(trim($_GET['prd_from'] ?? ''));
    $prd_to   = strtoupper(trim($_GET['prd_to']   ?? ''));
    $cat_from = strtoupper(trim($_GET['cat_from'] ?? ''));
    $cat_to   = strtoupper(trim($_GET['cat_to']   ?? ''));

    $where = '1=1';
    if ($search !== '') {
        $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $w) {
            $e_ascii = db_escape($w);
            $e_tis   = db_search($w);
            $where .= " AND (PrdId LIKE '%$e_ascii%' OR Barcode LIKE '%$e_ascii%' OR PrdDescE LIKE '%$e_tis%' OR PrdDescT LIKE '%$e_tis%' OR VndCode LIKE '%$e_ascii%')";
        }
    }
    $where .= recv_range_clause('PrdId',    $prd_from, $prd_to);
    $where .= recv_range_clause('CateCode', $cat_from, $cat_to);

    $result = mysqli_query($conn, "
        SELECT PrdId, Barcode, PrdDescE, PrdDescT, BaseUnit, LastCost, OnHand, Active
        FROM gblprod
        WHERE $where
        ORDER BY Active DESC, PrdId ASC
    ");
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    $title = 'รายการสินค้า — ' . fmt_date(date('Y-m-d'));
    if ($search !== '') $title .= ' (ค้นหา: ' . $search . ')';

    $headers = ['รหัสสินค้า','Barcode','ชื่อ (EN)','ชื่อ (TH)','หน่วย','ทุนล่าสุด','คงเหลือ','สถานะ'];
    $data = array_map(fn($r) => [
        $r['PrdId'], $r['Barcode'],
        db_str($r['PrdDescE']), db_str($r['PrdDescT']),
        $r['BaseUnit'], (float)$r['LastCost'], (float)$r['OnHand'],
        (int)$r['Active'] === 0 ? 'Inactive' : 'Active',
    ], $rows);

    if ($format === 'pdf') {
        outputPdf($title, $headers, $data, 'A4-L');
    } else {
        outputExcel($title, $headers, $data, 'item_list_' . date('Ymd'));
    }
}

// ─── Item Detail (PO History) Export ────────────────────────────────────────
function exportItemDetail(string $format): void
{
    global $conn;
    $prd_id = trim($_GET['prd_id'] ?? '');
    if ($prd_id === '') { http_response_code(400); exit; }
    $prd_id_safe = db_escape($prd_id);

    $r_item = mysqli_query($conn, "SELECT PrdDescE, PrdDescT FROM gblprod WHERE PrdId = '$prd_id_safe' LIMIT 1");
    $prod = $r_item ? mysqli_fetch_assoc($r_item) : null;
    $desc = $prod ? (db_str($prod['PrdDescT'] ?? '') ?: db_str($prod['PrdDescE'] ?? '')) : '';

    $result = mysqli_query($conn, "
        SELECT h.PoNo, h.PoDate, h.VndCode, v.VndName,
               d.Qty, d.Unit, d.Price, d.Discount, d.Amount
        FROM invpo1 d
        INNER JOIN invpo0 h ON h.SeqNo = d.SeqNo
        LEFT JOIN gblvend v ON h.VndCode = v.VndCode
        WHERE d.PrdID = '$prd_id_safe'
        ORDER BY h.PoDate DESC, h.SeqNo DESC
        LIMIT 200
    ");
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;

    $title   = 'ประวัติสั่งซื้อ: ' . $prd_id . ($desc !== '' ? ' — ' . $desc : '');
    $headers = ['วันที่ PO','เลข PO','รหัส Vendor','ชื่อ Vendor','จำนวน','หน่วย','ราคา/หน่วย','ส่วนลด','ยอดรวม'];
    $data = array_map(fn($r) => [
        fmt_date($r['PoDate']),
        $r['PoNo'],
        $r['VndCode'],
        db_str($r['VndName']),
        (float)$r['Qty'], $r['Unit'],
        (float)$r['Price'], (float)$r['Discount'], (float)$r['Amount'],
    ], $rows);

    if ($format === 'pdf') {
        outputPdf($title, $headers, $data, 'A4-L');
    } else {
        outputExcel($title, $headers, $data, 'item_detail_' . $prd_id . '_' . date('Ymd'));
    }
}

// ─── Excel output ───────────────────────────────────────────────────────────
function outputExcel(string $title, array $headers, array $data, string $filename): void
{
    $ss = new Spreadsheet();
    $ws = $ss->getActiveSheet();
    // Sheet title — strip invalid chars (\/*?:[]) + max 31 chars (Excel limit)
    $safe_sheet = preg_replace('/[\\\\\/\*\?:\[\]]/', '', $title);
    $safe_sheet = mb_substr($safe_sheet, 0, 31);
    if (trim($safe_sheet) === '') $safe_sheet = 'Report';
    $ws->setTitle($safe_sheet);

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
// ─── Shared mPDF factory (Sarabun Thai font + common config) ────────────────
function makeMpdf(string $format = 'A4'): \Mpdf\Mpdf
{
    $tmpDir = '/tmp/mpdf';
    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

    $fontDir = '/var/www/html/fonts';
    $isValidTtf = function(string $path): bool {
        if (!is_file($path) || filesize($path) < 10000) return false;
        $fh = @fopen($path, 'rb');
        if (!$fh) return false;
        $magic = fread($fh, 4); fclose($fh);
        return $magic === "\x00\x01\x00\x00";
    };
    $okR  = $isValidTtf($fontDir . '/Sarabun-Regular.ttf');
    $okB  = $isValidTtf($fontDir . '/Sarabun-Bold.ttf');
    $okI  = $isValidTtf($fontDir . '/Sarabun-Italic.ttf');
    $okBI = $isValidTtf($fontDir . '/Sarabun-BoldItalic.ttf');
    $hasSarabun = $okR && $okB;
    if (!$hasSarabun) {
        error_log("[mPDF] Sarabun TTFs invalid — falling back to dejavusans (R=$okR B=$okB I=$okI BI=$okBI)");
    }

    $defaultConfig   = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $defaultFontData = (new \Mpdf\Config\FontVariables())->getDefaults();

    $config = [
        'mode'             => 'utf-8',
        'format'           => $format,
        'margin_left'      => 12,
        'margin_right'     => 12,
        'margin_top'       => 12,
        'margin_bottom'    => 12,
        'default_font_size'=> 9,
        'autoScriptToLang' => true,
        'autoLangToFont'   => true,
        'useSubstitutions' => true,
        'tempDir'          => $tmpDir,
    ];

    if ($hasSarabun) {
        $config['fontDir']  = array_merge($defaultConfig['fontDir'], [$fontDir]);
        $config['fontdata'] = $defaultFontData['fontdata'] + [
            'sarabun' => array_filter([
                'R'  => 'Sarabun-Regular.ttf',
                'B'  => 'Sarabun-Bold.ttf',
                'I'  => $okI  ? 'Sarabun-Italic.ttf'     : null,
                'BI' => $okBI ? 'Sarabun-BoldItalic.ttf' : null,
                'useOTL'     => 0xFF,
                'useKashida' => 75,
            ], fn($v) => $v !== null),
        ];
        $config['default_font'] = 'sarabun';
    }

    return new \Mpdf\Mpdf($config);
}

function outputPdf(string $title, array $headers, array $data, string $format): void
{
    $mpdf = makeMpdf($format);
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
