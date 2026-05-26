<?php
require_once __DIR__ . '/config/db.php';
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
        $data = array_map(fn($i) => [
            $i['DtlNo'], $i['PrdID'], db_str($i['Remark']),
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

    <!-- Signature blocks — proper space for actual signing (~50mm height) -->
    <table width="100%" style="margin-top:36px;font-size:8.5pt;color:#374151">
      <tr>
        <td width="32%" style="vertical-align:bottom">
          <div style="height:55px"></div>
          <div style="border-top:1px solid #6b7280;padding:6px 4px 0;text-align:center;margin:0 6px">
            <div style="color:#9ca3af;font-size:7.5pt;letter-spacing:.5px;text-transform:uppercase">Authorised Signature</div>
            <div style="font-weight:bold;color:#1e3a5f;margin-top:2px;font-size:9pt">Purchasing</div>
            <div style="color:#9ca3af;font-size:7.5pt;margin-top:2px">Date: ____________</div>
          </div>
        </td>
        <td width="2%"></td>
        <td width="32%" style="vertical-align:bottom">
          <div style="height:55px"></div>
          <div style="border-top:1px solid #6b7280;padding:6px 4px 0;text-align:center;margin:0 6px">
            <div style="color:#9ca3af;font-size:7.5pt;letter-spacing:.5px;text-transform:uppercase">Authorised Signature</div>
            <div style="font-weight:bold;color:#1e3a5f;margin-top:2px;font-size:9pt">Finance Controller</div>
            <div style="color:#9ca3af;font-size:7.5pt;margin-top:2px">Date: ____________</div>
          </div>
        </td>
        <td width="2%"></td>
        <td width="32%" style="vertical-align:bottom">
          <div style="height:55px"></div>
          <div style="border-top:1px solid #6b7280;padding:6px 4px 0;text-align:center;margin:0 6px">
            <div style="color:#9ca3af;font-size:7.5pt;letter-spacing:.5px;text-transform:uppercase">Authorised Signature</div>
            <div style="font-weight:bold;color:#1e3a5f;margin-top:2px;font-size:9pt">General Manager</div>
            <div style="color:#9ca3af;font-size:7.5pt;margin-top:2px">Date: ____________</div>
          </div>
        </td>
      </tr>
    </table>

    <div style="text-align:center;color:#d1d5db;font-size:7pt;margin-top:24px;padding-top:8px;border-top:1px solid #f3f4f6">
      Generated by Inventorium  ·  <?= date('d/m/Y H:i') ?>  ·  PO #<?= htmlspecialchars($po['PoNo']) ?>  ·  Page 1
    </div>
    <?php
    $html = ob_get_clean();

    $tmpDir = '/tmp/mpdf';
    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

    // Load Sarabun (Thai font) if available
    $fontDir = '/var/www/html/fonts';
    $hasSarabun = is_file($fontDir . '/Sarabun-Regular.ttf');
    $defaultConfig    = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $defaultFontData  = (new \Mpdf\Config\FontVariables())->getDefaults();
    $config = [
        'mode'             => 'utf-8',
        'format'           => 'A4',
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
        $config['fontDir']      = array_merge($defaultConfig['fontDir'], [$fontDir]);
        $config['fontdata']     = $defaultFontData['fontdata'] + [
            'sarabun' => [
                'R' => 'Sarabun-Regular.ttf',
                'B' => 'Sarabun-Bold.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ],
        ];
        $config['default_font'] = 'sarabun';
    }
    $mpdf = new \Mpdf\Mpdf($config);
    $mpdf->SetTitle('PO ' . $po['PoNo']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('PO_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $po['PoNo']) . '_' . date('Ymd') . '.pdf', 'D');
    exit;
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
function outputPdf(string $title, array $headers, array $data, string $format): void
{
    // mPDF needs writable tmpDir — use /tmp (always writable in container)
    $tmpDir = '/tmp/mpdf';
    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

    $mpdf = new \Mpdf\Mpdf([
        'mode'        => 'utf-8',
        'format'      => $format,
        'margin_top'  => 15,
        'margin_bottom' => 15,
        'tempDir'     => $tmpDir,
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
