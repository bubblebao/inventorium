<?php
require_once __DIR__ . '/config/db.php';

$seq = (int)($_GET['seq'] ?? 0);
if ($seq <= 0) {
    header('Location: /po_list.php');
    exit;
}

$r_header = mysqli_query($conn, "
    SELECT h.*,
           (SELECT SUM(Amount) FROM invpo1 WHERE SeqNo = h.SeqNo) AS TAmt,
           v.VndName, v.VndTel, v.VndEmail, v.VndTaxNo
    FROM invpo0 h LEFT JOIN gblvend v ON h.VndCode = v.VndCode
    WHERE h.SeqNo = $seq
");
$po = mysqli_fetch_assoc($r_header);
if (!$po) {
    header('Location: /po_list.php');
    exit;
}

$r_items = mysqli_query($conn, "
    SELECT d.DtlNo, d.PrdID, d.Remark, d.Qty, d.Unit,
           d.Price, d.NetAmount, d.Amount, d.TaxAmt, d.Discount, d.Remain
    FROM invpo1 d WHERE d.SeqNo = $seq ORDER BY d.DtlNo
");

$page_title  = 'PO: ' . htmlspecialchars($po['PoNo']);
$export_base = '/export.php?type=po_detail&seq=' . $seq;

$today = date('Y-m-d');
$due   = $po['DeliveryDate'] ?? '';
$is_overdue  = $due && $due !== '0000-00-00' && $due < $today;
$is_due_soon = $due && $due !== '0000-00-00' && !$is_overdue
            && $due <= date('Y-m-d', strtotime('+7 days'));

// PO Status badge
$status_map = [
    '1' => ['Draft',    'badge-src'],
    '2' => ['Pending',  'badge-due-soon'],
    '6' => ['Approved', 'badge-paid'],
];
$status_info = $status_map[(string)($po['PoStatus'] ?? '')] ?? [($po['PoStatus'] ?? '-'), 'badge-src'];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Action Bar -->
<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2 no-print">
  <a href="/po_list.php" class="btn btn-sm btn-inv-outline" data-loading>
    <i class="bi bi-arrow-left"></i> <?= t('back_to_po_list') ?>
  </a>
  <div class="d-flex gap-1">
    <a href="/vendor_detail.php?vn_code=<?= urlencode($po['VndCode']) ?>"
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

<!-- PO Header -->
<div class="card mb-3">
  <div class="card-header-inv d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span>
      <i class="bi bi-file-earmark-text me-1"></i>
      <?= t('po_invoice') ?>: <strong><?= htmlspecialchars($po['PoNo']) ?></strong>
    </span>
    <div class="d-flex gap-2">
      <span class="badge-inv <?= $status_info[1] ?>"><?= htmlspecialchars($status_info[0]) ?></span>
      <?php if ($is_overdue): ?>
        <span class="badge-inv badge-overdue"><?= t('po_overdue') ?></span>
      <?php elseif ($is_due_soon): ?>
        <span class="badge-inv badge-due-soon"><?= t('po_due_soon') ?></span>
      <?php else: ?>
        <span class="badge-inv badge-src"><?= htmlspecialchars($po['LocaCode']) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <table class="table table-sm table-borderless mb-0" style="font-size:13px">
          <tr><th style="width:140px;color:var(--muted)"><?= t('po_order_date') ?></th><td><?= fmt_date($po['PoDate']) ?></td></tr>
          <tr>
            <th style="color:var(--muted)"><?= t('po_delivery_date') ?></th>
            <td>
              <?php if ($is_overdue): ?>
                <span class="badge-inv badge-overdue"><?= fmt_date($due) ?></span>
              <?php elseif ($is_due_soon): ?>
                <span class="badge-inv badge-due-soon"><?= fmt_date($due) ?></span>
              <?php else: ?>
                <?= fmt_date($due) ?>
              <?php endif; ?>
            </td>
          </tr>
          <tr><th style="color:var(--muted)"><?= t('po_credit') ?></th><td><?= (int)($po['VndTerm'] ?? 0) ?> <?= t('days') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('po_reference') ?></th><td><?= htmlspecialchars($po['RefNo'] ?? '-') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('po_pr_no') ?></th><td><?= htmlspecialchars($po['PrNo'] ?? '-') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('location') ?></th><td><?= htmlspecialchars($po['LocaCode'] ?? '-') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('recorded_by') ?></th><td><?= htmlspecialchars(db_str($po['CreateUser'] ?? '')) ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('po_remark') ?></th><td><?= htmlspecialchars(db_str($po['Remark'] ?? '')) ?></td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <div class="card" style="background:var(--surface-2);border:1px solid var(--border)">
          <div class="card-body">
            <div class="fw-bold mb-1" style="color:var(--primary)">
              <i class="bi bi-building me-1"></i>
              <a href="/vendor_detail.php?vn_code=<?= urlencode($po['VndCode']) ?>"
                 style="color:var(--primary);text-decoration:none" data-loading>
                <?= htmlspecialchars(db_str($po['VndName'])) ?>
              </a>
            </div>
            <div style="font-size:12px;color:var(--muted)">
              <?= htmlspecialchars($po['VndCode']) ?>
            </div>
            <div class="mt-2" style="font-size:12px;color:var(--muted)">
              <?php if (!empty($po['VndTel'])): ?>
                <div><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($po['VndTel']) ?></div>
              <?php endif; ?>
              <?php if (!empty($po['VndTaxNo'])): ?>
                <div><i class="bi bi-card-text me-1"></i>Tax No: <?= htmlspecialchars($po['VndTaxNo']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- PO Items -->
<div class="card mb-3">
  <div class="card-header-inv"><i class="bi bi-list-check me-1"></i> <?= t('po_items') ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table-inv table mb-0">
        <thead>
          <tr>
            <th><?= t('col_seq') ?></th>
            <th><?= t('col_prdid') ?></th>
            <th><?= t('col_desc') ?></th>
            <th class="text-end"><?= t('col_qty') ?></th>
            <th><?= t('col_unit') ?></th>
            <th class="text-end"><?= t('col_price') ?></th>
            <th class="text-end"><?= t('col_before_tax') ?></th>
            <th class="text-end"><?= t('col_vat') ?></th>
            <th class="text-end"><?= t('subtotal') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php
        $sum_net = 0; $sum_tax = 0; $sum_amt = 0;
        while ($item = mysqli_fetch_assoc($r_items)):
            $sum_net += (float)$item['NetAmount'];
            $sum_tax += (float)$item['TaxAmt'];
            $sum_amt += (float)$item['Amount'];
        ?>
          <tr>
            <td><?= htmlspecialchars($item['DtlNo']) ?></td>
            <td>
              <a href="/item_history.php?prd_id=<?= urlencode($item['PrdID']) ?>"
                 style="text-decoration:none" title="ดูประวัติการซื้อสินค้านี้">
                <code style="font-size:11px;color:var(--accent)"><?= htmlspecialchars($item['PrdID']) ?></code>
              </a>
            </td>
            <td><?= htmlspecialchars(db_str($item['Remark'] ?? '')) ?></td>
            <td class="text-end"><?= fmt_number($item['Qty']) ?></td>
            <td><?= htmlspecialchars($item['Unit']) ?></td>
            <td class="text-end"><?= fmt_number($item['Price']) ?></td>
            <td class="text-end"><?= fmt_number($item['NetAmount']) ?></td>
            <td class="text-end"><?= fmt_number($item['TaxAmt']) ?></td>
            <td class="text-end fw-semibold"><?= fmt_number($item['Amount']) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
        <tfoot style="background:var(--surface-2);font-weight:600">
          <tr>
            <td colspan="6" class="text-end" style="color:var(--muted)"><?= t('subtotal') ?></td>
            <td class="text-end"><?= fmt_number($sum_net) ?></td>
            <td class="text-end"><?= fmt_number($sum_tax) ?></td>
            <td class="text-end"><?= fmt_number($sum_amt) ?></td>
          </tr>
          <tr>
            <td colspan="6" class="text-end" style="color:var(--muted)"><?= t('po_total_all') ?></td>
            <td colspan="3" class="text-end" style="color:var(--primary);font-size:16px">
              <?= fmt_number($po['TAmt']) ?> <?= t('baht') ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
