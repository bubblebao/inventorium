<?php
require_once __DIR__ . '/config/db.php';
$page_title = t('vendor');

$search = $_GET['search'] ?? '';

$where = '';
if ($search !== '') {
    $s = db_escape($search);
    $where = "WHERE VndName LIKE '%$s%' OR VndCode LIKE '%$s%'";
}

$result = mysqli_query($conn, "
    SELECT VndCode, VndName, VndPayee, VndTel, VndTaxNo, VndCurBal, VndTerm,
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
      <span class="badge ms-1" style="background:rgba(255,255,255,.2)"><?= $result ? mysqli_num_rows($result) : 0 ?></span>
    </span>
    <a href="/export.php?type=vendor&format=excel" class="btn btn-sm"
       style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)" target="_blank">
      <i class="bi bi-file-earmark-excel me-1"></i> Export
    </a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table-inv table mb-0">
        <thead>
          <tr>
            <th><?= t('vendor_code') ?></th>
            <th><?= t('vendor_name') ?></th>
            <th><?= t('vendor_payee') ?></th>
            <th><?= t('vendor_phone') ?></th>
            <th><?= t('vendor_taxno') ?></th>
            <th class="text-end"><?= t('vendor_balance') ?></th>
            <th class="text-end"><?= t('vendor_credit') ?></th>
            <th><?= t('vendor_last_close') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$result || mysqli_num_rows($result) === 0): ?>
          <tr><td colspan="8" class="text-center text-muted py-4"><?= t('no_data') ?></td></tr>
        <?php else: ?>
          <?php while ($v = mysqli_fetch_assoc($result)): ?>
          <tr style="cursor:pointer"
              onclick="location.href='<?= htmlspecialchars('/vendor_detail.php?vn_code=' . urlencode($v['VndCode'])) ?>'">
            <td><code><?= htmlspecialchars($v['VndCode']) ?></code></td>
            <td class="fw-semibold"><?= htmlspecialchars(db_str($v['VndName'])) ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars(db_str($v['VndPayee'])) ?></td>
            <td><?= htmlspecialchars($v['VndTel']) ?></td>
            <td style="font-size:12px"><?= htmlspecialchars($v['VndTaxNo']) ?></td>
            <td class="text-end fw-semibold <?= (float)$v['VndCurBal'] > 0 ? 'text-danger' : '' ?>">
              <?= fmt_number($v['VndCurBal']) ?>
            </td>
            <td class="text-end"><?= (int)$v['VndTerm'] ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= fmt_date($v['VndLastCls']) ?></td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
