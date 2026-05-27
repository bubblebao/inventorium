<?php
require_once __DIR__ . '/config/db.php';
$page_title = t('vendor');

$search = $_GET['search'] ?? '';

$where = '';
if ($search !== '') {
    $s_ascii = db_escape($search);   // VndCode, VndTel, VndTaxNo
    $s_tis   = db_search($search);   // VndName, VndAdd1 (TIS-620)
    $where = "WHERE VndName LIKE '%$s_tis%' OR VndCode LIKE '%$s_ascii%' OR VndAdd1 LIKE '%$s_tis%' OR VndTel LIKE '%$s_ascii%' OR VndTaxNo LIKE '%$s_ascii%'";
}

$result = mysqli_query($conn, "
    SELECT VndCode, VndName, VndAdd1, VndAdd2, VndTel, VndTaxNo, VndCurBal, VndTerm,
           VndLastCls
    FROM gblvend
    $where
    ORDER BY VndName
");

require_once __DIR__ . '/includes/header.php';
?>

<div class="card mb-3">
  <div class="card-header-inv"><i class="bi bi-search me-1"></i> <?= t('vendor_search') ?></div>
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="<?= t('vendor_search_ph') ?>"
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-auto d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-inv-primary"><i class="bi bi-search"></i> <?= t('search') ?></button>
        <a href="/vendor_list.php" class="btn btn-sm btn-inv-outline"><?= t('reset') ?></a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header-inv d-flex align-items-center justify-content-between">
    <span>
      <i class="bi bi-building me-1"></i> <?= t('vendor_list_title') ?>
      <span class="badge ms-1" data-inv-count style="background:rgba(255,255,255,.2)"><?= $result ? mysqli_num_rows($result) : 0 ?></span>
    </span>
    <a href="/export.php?type=vendor&format=excel" class="btn btn-sm"
       style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)" target="_blank">
      <i class="bi bi-file-earmark-excel me-1"></i> Export
    </a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table-inv table mb-0" data-inv-table>
        <thead>
          <tr>
            <th data-sort="text"><?= t('vendor_code') ?></th>
            <th data-sort="text"><?= t('vendor_name') ?></th>
            <th data-sort="text" class="d-none d-xl-table-cell"><?= t('vendor_address') ?></th>
            <th data-sort="text" class="d-none d-lg-table-cell"><?= t('vendor_phone') ?></th>
            <th data-sort="text" class="d-none d-xl-table-cell"><?= t('vendor_taxno') ?></th>
            <th data-sort="num" class="text-end"><?= t('vendor_balance') ?></th>
            <th data-sort="num" class="text-end d-none d-md-table-cell"><?= t('vendor_credit') ?></th>
            <th data-sort="date" class="d-none d-xl-table-cell"><?= t('vendor_last_close') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$result || mysqli_num_rows($result) === 0): ?>
          <tr class="inv-no-filter"><td colspan="8" class="text-center text-muted py-4"><?= t('no_data') ?></td></tr>
        <?php else: ?>
          <?php while ($v = mysqli_fetch_assoc($result)): ?>
          <tr style="cursor:pointer"
              onclick="location.href='<?= htmlspecialchars('/vendor_detail.php?vn_code=' . urlencode($v['VndCode'])) ?>'">
            <td><code><?= htmlspecialchars($v['VndCode']) ?></code></td>
            <td class="fw-semibold">
              <div><?= htmlspecialchars(db_str($v['VndName'])) ?></div>
              <!-- Show phone+address on small screens inline (where columns hidden) -->
              <div class="d-lg-none" style="font-size:11px;color:var(--muted);margin-top:2px">
                <?php if ($v['VndTel']): ?><i class="bi bi-telephone" style="font-size:9px"></i> <?= htmlspecialchars($v['VndTel']) ?><?php endif; ?>
                <?php $addr1 = trim(db_str($v['VndAdd1'] ?? '')); if ($addr1): ?> · <?= htmlspecialchars($addr1) ?><?php endif; ?>
              </div>
            </td>
            <td class="d-none d-xl-table-cell" style="font-size:12px;color:var(--muted)">
              <?php
                $clean = function($s) { $v = trim(db_str($s ?? '')); return strcasecmp($v,'NULL')===0 ? '' : $v; };
                $a1 = $clean($v['VndAdd1'] ?? '');
                $a2 = $clean($v['VndAdd2'] ?? '');
              ?>
              <?php if ($a1 || $a2): ?>
                <?php if ($a1): ?><div><?= htmlspecialchars($a1) ?></div><?php endif; ?>
                <?php if ($a2): ?><div style="font-size:11px;opacity:.8"><?= htmlspecialchars($a2) ?></div><?php endif; ?>
              <?php else: ?>
                <span style="opacity:.4">—</span>
              <?php endif; ?>
            </td>
            <td class="d-none d-lg-table-cell"><?= htmlspecialchars($v['VndTel']) ?></td>
            <td class="d-none d-xl-table-cell" style="font-size:12px"><?= htmlspecialchars($v['VndTaxNo']) ?></td>
            <td class="text-end fw-semibold <?= (float)$v['VndCurBal'] > 0 ? 'text-danger' : '' ?>">
              <?= fmt_number($v['VndCurBal']) ?>
            </td>
            <td class="text-end d-none d-md-table-cell"><?= (int)$v['VndTerm'] ?></td>
            <td class="d-none d-xl-table-cell" style="font-size:12px;color:var(--muted)"><?= fmt_date($v['VndLastCls']) ?></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  // เก็บ URL ค้นหาไว้ใช้ตอนกลับจาก Vendor Detail
  sessionStorage.setItem('vendor_list_last_url', window.location.href);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
