<?php
require_once __DIR__ . '/config/db.php';

$seq = (int)($_GET['seq'] ?? 0);
if ($seq <= 0) {
    header('Location: /recv_list.php');
    exit;
}

$r_header = mysqli_query($conn, "
    SELECT h.*,
           (SELECT SUM(Amount)    FROM invrecv1 WHERE SeqNo = h.SeqNo) AS TAmt,
           (SELECT SUM(NetAmount) FROM invrecv1 WHERE SeqNo = h.SeqNo) AS SubTotal,
           (SELECT SUM(TaxAmt)    FROM invrecv1 WHERE SeqNo = h.SeqNo) AS TaxTotal,
           v.VndName, v.VndTel, v.VndEmail, v.VndTaxNo
    FROM invrecv0 h LEFT JOIN gblvend v ON h.VndCode = v.VndCode
    WHERE h.SeqNo = $seq
");
$rv = mysqli_fetch_assoc($r_header);
if (!$rv) {
    header('Location: /recv_list.php');
    exit;
}

$r_items = mysqli_query($conn, "
    SELECT d.DtlNo, d.PrdID, d.Remark, d.Qty, d.Unit, d.Cost, d.UnitRate,
           d.NetAmount, d.TaxAmt, d.Amount, d.DeptCode, d.AcCode,
           p.PrdDescE, p.PrdDescT
    FROM invrecv1 d
    LEFT JOIN gblprod p ON p.PrdId = d.PrdID
    WHERE d.SeqNo = $seq ORDER BY d.DtlNo
");

$page_title  = 'RCV: ' . htmlspecialchars($rv['RecvNo']);
$export_base = '/export.php?type=recv_detail&seq=' . $seq;

// Receiving Type
$is_inv = ($rv['InvType'] ?? '') === 'I';
$type_label = $is_inv ? t('recv_type_inv') : t('recv_type_direct');
$type_class = $is_inv ? 'badge-paid' : 'badge-src';

$ext_cost = (float)($rv['ExtCost'] ?? 0);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Action Bar -->
<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2 no-print">
  <a href="/recv_list.php" id="backToRecvList" class="btn btn-sm btn-inv-outline" data-loading>
    <i class="bi bi-arrow-left"></i> <?= t('back_to_recv') ?>
  </a>
  <script>
    (function(){
      var back = sessionStorage.getItem('recv_detail_back_url')
                 || sessionStorage.getItem('recv_list_last_url');
      var btn = document.getElementById('backToRecvList');
      if (back && back !== window.location.href) btn.href = back;
      sessionStorage.removeItem('recv_detail_back_url');
    })();
  </script>
  <div class="d-flex gap-1">
    <?php if (!empty($rv['PoNo'])): ?>
    <a href="/po_list.php?inv_no=<?= urlencode($rv['PoNo']) ?>&date_from=2000-01-01&date_to=<?= date('Y') ?>-12-31"
       class="btn btn-sm btn-inv-outline" data-loading title="ดู PO ต้นทาง">
      <i class="bi bi-file-text"></i> PO <?= htmlspecialchars($rv['PoNo']) ?>
    </a>
    <?php endif; ?>
    <a href="/vendor_detail.php?vn_code=<?= urlencode($rv['VndCode']) ?>"
       class="btn btn-sm btn-inv-outline" data-loading>
      <i class="bi bi-building"></i> <?= t('view_vendor') ?>
    </a>
    <a href="<?= htmlspecialchars($export_base . '&format=excel') ?>"
       class="btn btn-sm" style="background:#10b981;color:#fff" target="_blank">
      <i class="bi bi-file-earmark-excel me-1"></i> Excel
    </a>
    <a href="<?= htmlspecialchars($export_base . '&format=pdf') ?>"
       class="btn btn-sm" style="background:#ef4444;color:#fff" target="_blank">
      <i class="bi bi-file-earmark-pdf me-1"></i> PDF
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-inv-outline no-print">
      <i class="bi bi-printer"></i> <?= t('print') ?>
    </button>
  </div>
</div>

<!-- Receiving Header -->
<div class="card mb-3">
  <div class="card-header-inv d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span>
      <i class="bi bi-box-arrow-in-down me-1"></i>
      <?= t('recv_no') ?>: <strong><?= htmlspecialchars($rv['RecvNo']) ?></strong>
    </span>
    <div class="d-flex gap-2">
      <span class="badge-inv <?= $type_class ?>"><?= htmlspecialchars($type_label) ?></span>
      <span class="badge-inv badge-src"><?= htmlspecialchars($rv['LocaCode']) ?></span>
    </div>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <table class="table table-sm table-borderless mb-0" style="font-size:13px">
          <tr><th style="width:140px;color:var(--muted)"><?= t('recv_date') ?></th><td><?= fmt_date($rv['RecvDate']) ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('recv_type') ?></th><td><span class="badge-inv <?= $type_class ?>"><?= htmlspecialchars($type_label) ?></span></td></tr>
          <tr><th style="color:var(--muted)"><?= t('po_pr_no') ?> / PO</th><td><?= htmlspecialchars($rv['PoNo'] ?: '-') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('ref_no_label') ?></th><td><?= htmlspecialchars($rv['RefNo'] ?: '-') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('location') ?></th><td><?= htmlspecialchars($rv['LocaCode'] ?: '-') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('recorded_by') ?></th><td><?= htmlspecialchars(db_str($rv['CreateUser'] ?? '')) ?></td></tr>
          <?php if ($ext_cost > 0): ?>
          <tr><th style="color:var(--muted)"><?= t('recv_ext_cost') ?></th><td><?= fmt_number($ext_cost) ?> <?= t('baht') ?></td></tr>
          <?php endif; ?>
          <tr><th style="color:var(--muted)"><?= t('po_remark') ?></th><td><?= htmlspecialchars(db_str($rv['Remark'] ?? '')) ?></td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <div class="card vendor-card" style="background:var(--surface-2);border:1px solid var(--border);cursor:pointer;transition:all .15s"
             onclick="location.href='/vendor_detail.php?vn_code=<?= urlencode($rv['VndCode']) ?>'"
             onmouseover="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(14,165,233,.15)'"
             onmouseout="this.style.borderColor='var(--border)';this.style.transform='';this.style.boxShadow=''"
             title="คลิกเพื่อดูข้อมูล Vendor">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-1">
              <div class="fw-bold" style="color:var(--primary)">
                <i class="bi bi-building me-1"></i>
                <?= htmlspecialchars(db_str($rv['VndName']) ?: $rv['VndCode']) ?>
              </div>
              <i class="bi bi-arrow-up-right" style="color:var(--accent);font-size:14px"></i>
            </div>
            <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($rv['VndCode']) ?></div>
            <div class="mt-2" style="font-size:12px;color:var(--muted)">
              <?php if (!empty($rv['VndTel'])): ?>
                <div><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($rv['VndTel']) ?></div>
              <?php endif; ?>
              <?php if (!empty($rv['VndTaxNo'])): ?>
                <div><i class="bi bi-card-text me-1"></i>Tax No: <?= htmlspecialchars($rv['VndTaxNo']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Receiving Items -->
<div class="card mb-3">
  <div class="card-header-inv"><i class="bi bi-list-check me-1"></i> <?= t('recv_items') ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table-inv table mb-0">
        <thead>
          <tr>
            <th><?= t('col_seq') ?></th>
            <th><?= t('col_prdid') ?></th>
            <th><?= t('col_desc') ?></th>
            <th class="d-none d-lg-table-cell"><?= t('col_dep_acc') ?></th>
            <th><?= t('col_unit') ?></th>
            <th class="text-end"><?= t('col_qty') ?></th>
            <th class="text-end"><?= t('col_cost_unit') ?></th>
            <th class="text-end d-none d-xl-table-cell"><?= t('col_unit_rate') ?></th>
            <th class="text-end"><?= t('col_amt_extax') ?></th>
            <th class="text-end"><?= t('col_tax_amt') ?></th>
            <th class="text-end"><?= t('col_amount_incl') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php
        $sum_net = 0; $sum_tax = 0; $sum_amt = 0; $sum_qty = 0;
        while ($item = mysqli_fetch_assoc($r_items)):
            $sum_net += (float)$item['NetAmount'];
            $sum_tax += (float)$item['TaxAmt'];
            $sum_amt += (float)$item['Amount'];
            $sum_qty += (float)$item['Qty'];
            // Description fallback: Remark > PrdDescT > PrdDescE > —
            $desc = trim(db_str($item['Remark'] ?? ''));
            if ($desc === '' || strcasecmp($desc, 'NULL') === 0) $desc = trim(db_str($item['PrdDescT'] ?? ''));
            if ($desc === '' || strcasecmp($desc, 'NULL') === 0) $desc = trim(db_str($item['PrdDescE'] ?? ''));
            if ($desc === '' || strcasecmp($desc, 'NULL') === 0) $desc = '—';
            $dep = trim((string)($item['DeptCode'] ?? ''));
            $acc = trim((string)($item['AcCode'] ?? ''));
        ?>
          <tr style="cursor:pointer"
              onclick="sessionStorage.setItem('item_detail_back_url',window.location.href);location.href='/item_history.php?prd_id=<?= urlencode($item['PrdID']) ?>'"
              title="ดูประวัติการซื้อสินค้านี้">
            <td><?= htmlspecialchars($item['DtlNo']) ?></td>
            <td><code style="font-size:11px;color:var(--accent)"><?= htmlspecialchars($item['PrdID']) ?></code></td>
            <td><?= htmlspecialchars($desc) ?></td>
            <td class="d-none d-lg-table-cell" style="font-size:11.5px;color:var(--muted)">
              <?= htmlspecialchars($dep) ?><?= ($dep !== '' && $acc !== '') ? ' / ' : '' ?><?= htmlspecialchars($acc) ?>
            </td>
            <td><?= htmlspecialchars($item['Unit']) ?></td>
            <td class="text-end"><?= fmt_number($item['Qty']) ?></td>
            <td class="text-end"><?= fmt_number($item['Cost']) ?></td>
            <td class="text-end d-none d-xl-table-cell"><?= fmt_number($item['UnitRate']) ?></td>
            <td class="text-end"><?= fmt_number($item['NetAmount']) ?></td>
            <td class="text-end"><?= fmt_number($item['TaxAmt']) ?></td>
            <td class="text-end fw-semibold"><?= fmt_number($item['Amount']) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
        <tfoot style="background:var(--surface-2);font-weight:600">
          <tr>
            <td colspan="5" class="text-end" style="color:var(--muted)"><?= t('subtotal') ?></td>
            <td class="text-end"><?= fmt_number($sum_qty) ?></td>
            <td class="d-none d-xl-table-cell"></td>
            <td></td>
            <td class="text-end"><?= fmt_number($sum_net) ?></td>
            <td class="text-end"><?= fmt_number($sum_tax) ?></td>
            <td class="text-end"><?= fmt_number($sum_amt) ?></td>
          </tr>
          <tr>
            <td colspan="8" class="text-end" style="color:var(--muted)"><?= t('recv_total_all') ?></td>
            <td colspan="3" class="text-end" style="color:var(--primary);font-size:16px">
              <?= fmt_number($rv['TAmt']) ?> <?= t('baht') ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<script>
  if (window.Inv) Inv.trackView('recv', <?= (int)$seq ?>, <?= json_encode($rv['RecvNo'] . ' · ' . db_str($rv['VndName'] ?? ''), JSON_HEX_TAG) ?>);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
