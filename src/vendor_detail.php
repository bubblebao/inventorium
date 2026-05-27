<?php
require_once __DIR__ . '/config/db.php';

$vn_code = trim($_GET['vn_code'] ?? '');
if ($vn_code === '') {
    header('Location: /vendor_list.php');
    exit;
}
$vn_code_safe = db_escape($vn_code);

$r_vnd = mysqli_query($conn, "
    SELECT * FROM gblvend WHERE VndCode = '$vn_code_safe'
");
$vnd = mysqli_fetch_assoc($r_vnd);
if (!$vnd) {
    header('Location: /vendor_list.php');
    exit;
}

$page_title = 'Vendor: ' . htmlspecialchars(db_str($vnd['VndName']));

$today     = date('Y-m-d');
$first_mo  = date('Y-m-01');
$last_mo   = date('Y-m-t');
$first_yr  = date('Y-01-01');
$last_yr   = date('Y-12-31');

// Helper: stats for given date range
function vendor_stats($conn, $vn, $from, $to) {
    $where_date = $from ? "AND h.PoDate BETWEEN '$from' AND '$to'" : "";
    $r = mysqli_query($conn, "
        SELECT COUNT(DISTINCT h.SeqNo) AS cnt, SUM(d.Amount) AS total
        FROM invpo0 h
        LEFT JOIN invpo1 d ON d.SeqNo = h.SeqNo
        WHERE h.VndCode = '$vn' $where_date
    ");
    return mysqli_fetch_assoc($r) ?: ['cnt' => 0, 'total' => 0];
}

$stat_mo  = vendor_stats($conn, $vn_code_safe, $first_mo, $last_mo);
$stat_yr  = vendor_stats($conn, $vn_code_safe, $first_yr, $last_yr);
$stat_all = vendor_stats($conn, $vn_code_safe, '', '');

// PO History (latest 100)
$r_hist = mysqli_query($conn, "
    SELECT h.SeqNo, h.PoDate, h.PoNo, h.RefNo, h.DeliveryDate, h.VndTerm,
           h.LocaCode, h.CreateUser, h.Remark,
           (SELECT SUM(Amount) FROM invpo1 WHERE SeqNo = h.SeqNo) AS TAmt
    FROM invpo0 h WHERE h.VndCode = '$vn_code_safe'
    ORDER BY h.PoDate DESC, h.SeqNo DESC
    LIMIT 100
");

require_once __DIR__ . '/includes/header.php';
?>

<!-- Back + Export -->
<div class="mb-3 d-flex justify-content-between align-items-center no-print">
  <a href="/vendor_list.php" id="backToVendors" class="btn btn-sm btn-inv-outline" data-loading>
    <i class="bi bi-arrow-left"></i> <?= t('back_to_vendors') ?>
  </a>
  <script>
    (function(){
      var last = sessionStorage.getItem('vendor_list_last_url');
      if (last && last !== window.location.href) {
        document.getElementById('backToVendors').href = last;
      }
    })();
  </script>
  <div class="d-flex gap-1">
    <a href="/export.php?type=po_list&vnd_code=<?= urlencode($vn_code) ?>&date_from=2000-01-01&date_to=2099-12-31&format=excel"
       class="btn btn-sm" style="background:#10b981;color:#fff" target="_blank"
       title="Export ประวัติ PO ของ vendor นี้ (Excel)">
      <i class="bi bi-file-earmark-excel me-1"></i> Export PO History
    </a>
    <a href="/export.php?type=po_list&vnd_code=<?= urlencode($vn_code) ?>&date_from=2000-01-01&date_to=2099-12-31&format=pdf"
       class="btn btn-sm" style="background:#ef4444;color:#fff" target="_blank"
       title="Export ประวัติ PO ของ vendor นี้ (PDF)">
      <i class="bi bi-file-earmark-pdf me-1"></i> PDF
    </a>
  </div>
</div>

<!-- Vendor Info Card -->
<div class="card mb-3">
  <div class="card-header-inv">
    <i class="bi bi-building me-1"></i>
    <?= htmlspecialchars(db_str($vnd['VndName'])) ?>
    <span class="ms-2" style="font-size:12px;opacity:.75"><?= htmlspecialchars($vnd['VndCode']) ?></span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <table class="table table-sm table-borderless mb-0" style="font-size:13px">
          <tr><th style="width:140px;color:var(--muted)"><?= t('vendor_code') ?></th><td><code><?= htmlspecialchars($vnd['VndCode']) ?></code></td></tr>
          <tr><th style="color:var(--muted)"><?= t('vendor_name') ?></th><td class="fw-semibold"><?= htmlspecialchars(db_str($vnd['VndName'])) ?></td></tr>
          <tr><th style="color:var(--muted);vertical-align:top"><?= t('vendor_address') ?></th>
            <td>
              <?php
                $addr_lines = array_filter(array_map(
                    function($f) use ($vnd) {
                        $v = trim(db_str($vnd[$f] ?? ''));
                        return (strcasecmp($v, 'NULL') === 0 || $v === '') ? '' : $v;
                    },
                    ['VndAdd1','VndAdd2','VndAdd3','VndAdd4']
                ));
              ?>
              <?php if ($addr_lines): ?>
                <?php foreach ($addr_lines as $line): ?>
                  <div><?= htmlspecialchars($line) ?></div>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="color:var(--muted)">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php
            // Helper: แสดง field หรือ — ถ้าว่าง/NULL
            $vf = function($f) use ($vnd) {
                $v = trim(db_str($vnd[$f] ?? ''));
                return (strcasecmp($v,'NULL')===0||$v==='') ? '<span style="color:var(--muted)">—</span>' : htmlspecialchars($v);
            };
          ?>
          <tr><th style="color:var(--muted)"><?= t('vendor_phone') ?></th><td><?= $vf('VndTel') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('vendor_email') ?></th><td><?= $vf('VndEmail') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('vendor_taxno') ?></th><td><?= $vf('VndTaxNo') ?></td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <table class="table table-sm table-borderless mb-0" style="font-size:13px">
          <tr><th style="width:140px;color:var(--muted)"><?= t('po_credit') ?></th><td><?= (int)($vnd['VndTerm'] ?? 0) ?> <?= t('days') ?></td></tr>
          <tr>
            <th style="color:var(--muted)"><?= t('vendor_balance') ?></th>
            <td class="fw-semibold <?= (float)($vnd['VndCurBal'] ?? 0) > 0 ? 'text-danger' : '' ?>">
              <?= fmt_number($vnd['VndCurBal'] ?? 0) ?> <?= t('baht') ?>
            </td>
          </tr>
          <tr><th style="color:var(--muted)"><?= t('category') ?></th><td><?= $vf('VndCatCode') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('vendor_mobile') ?></th><td><?= $vf('VndMobile') ?></td></tr>
          <tr><th style="color:var(--muted)"><?= t('vendor_last_close') ?></th><td><?= fmt_date($vnd['VndLastCls'] ?? '') ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-3">
  <div class="col-sm-4">
    <div class="card stat-card" style="--card-color:var(--accent)">
      <div class="card-body">
        <div class="stat-label"><?= t('po_this_month') ?></div>
        <div class="stat-val"><?= number_format((int)$stat_mo['cnt']) ?></div>
        <div class="stat-label mt-1"><?= fmt_number($stat_mo['total'] ?? 0) ?> <?= t('baht') ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card stat-card" style="--card-color:var(--success)">
      <div class="card-body">
        <div class="stat-label"><?= t('po_this_year') ?></div>
        <div class="stat-val"><?= number_format((int)$stat_yr['cnt']) ?></div>
        <div class="stat-label mt-1"><?= fmt_number($stat_yr['total'] ?? 0) ?> <?= t('baht') ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card stat-card" style="--card-color:var(--muted)">
      <div class="card-body">
        <div class="stat-label"><?= t('po_all_time') ?></div>
        <div class="stat-val"><?= number_format((int)$stat_all['cnt']) ?></div>
        <div class="stat-label mt-1"><?= fmt_number($stat_all['total'] ?? 0) ?> <?= t('baht') ?></div>
      </div>
    </div>
  </div>
</div>

<!-- PO History Table -->
<div class="card">
  <div class="card-header-inv d-flex align-items-center justify-content-between">
    <span><i class="bi bi-clock-history me-1"></i> <?= t('po_history') ?>
      <span class="badge ms-1" style="background:rgba(255,255,255,.2)"><?= t('latest_100') ?></span>
    </span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table-inv table mb-0">
        <thead>
          <tr>
            <th><?= t('col_date') ?></th>
            <th><?= t('col_po_no') ?></th>
            <th><?= t('col_ref') ?></th>
            <th class="text-end"><?= t('col_total') ?></th>
            <th><?= t('col_due_date') ?></th>
            <th><?= t('col_loc') ?></th>
            <th><?= t('recorded_by') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$r_hist || mysqli_num_rows($r_hist) === 0): ?>
          <tr><td colspan="7" class="text-center text-muted py-4"><?= t('no_data') ?></td></tr>
        <?php else: ?>
          <?php while ($po = mysqli_fetch_assoc($r_hist)): ?>
          <?php
            $due = $po['DeliveryDate'] ?? '';
            $is_overdue  = $due && $due !== '0000-00-00' && $due < $today;
            $is_due_soon = $due && $due !== '0000-00-00' && !$is_overdue
                        && $due <= date('Y-m-d', strtotime('+7 days'));
          ?>
          <tr onclick="sessionStorage.setItem('po_detail_back_url',window.location.href);location.href='/po_detail.php?seq=<?= (int)$po['SeqNo'] ?>'" style="cursor:pointer">
            <td><?= fmt_date($po['PoDate']) ?></td>
            <td><code style="font-size:11px"><?= htmlspecialchars($po['PoNo']) ?></code></td>
            <td style="font-size:11.5px;color:var(--muted)"><?= htmlspecialchars($po['RefNo']) ?></td>
            <td class="text-end fw-semibold"><?= fmt_number($po['TAmt']) ?></td>
            <td>
              <?php if ($is_overdue): ?>
                <span class="badge-inv badge-overdue"><?= fmt_date($due) ?></span>
              <?php elseif ($is_due_soon): ?>
                <span class="badge-inv badge-due-soon"><?= fmt_date($due) ?></span>
              <?php else: ?>
                <span style="font-size:12px"><?= fmt_date($due) ?></span>
              <?php endif; ?>
            </td>
            <td><span class="badge-inv badge-src"><?= htmlspecialchars($po['LocaCode']) ?></span></td>
            <td style="font-size:11.5px;color:var(--muted)"><?= htmlspecialchars(db_str($po['CreateUser'])) ?></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  if (window.Inv) Inv.trackView('vendor', <?= json_encode($vnd['VndCode']) ?>, <?= json_encode(db_str($vnd['VndName'])) ?>);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
