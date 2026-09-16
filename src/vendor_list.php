<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/recv_filter.php';
$page_title = t('vendor');

$search = $_GET['search'] ?? '';

// ── Filter — From/To range (Vendor Code / Vendor Name) ──
$vnd_from   = strtoupper(trim($_GET['vnd_from']   ?? ''));
$vnd_to     = strtoupper(trim($_GET['vnd_to']     ?? ''));
$vname_from = trim($_GET['vname_from'] ?? '');
$vname_to   = trim($_GET['vname_to']   ?? '');
$cat_from   = strtoupper(trim($_GET['cat_from'] ?? ''));
$subcat_from = strtoupper(trim($_GET['subcat_from'] ?? ''));

$where = '1=1';
if ($search !== '') {
    $s_ascii = db_escape($search);   // VndCode, VndTel, VndTaxNo
    $s_tis   = db_search($search);   // VndName, VndAdd1 (TIS-620)
    $where .= " AND (VndName LIKE '%$s_tis%' OR VndCode LIKE '%$s_ascii%' OR VndAdd1 LIKE '%$s_tis%' OR VndTel LIKE '%$s_ascii%' OR VndTaxNo LIKE '%$s_ascii%')";
}
$where .= recv_range_clause('VndCode', $vnd_from, $vnd_to);
// VndName is TIS-620 — use db_search() (charset-converting) instead of recv_range_clause's plain db_escape
if ($vname_from !== '' || $vname_to !== '') {
    $vnf = db_search($vname_from);
    $vnt = db_search($vname_to);
    if ($vname_from !== '' && $vname_to !== '') $where .= " AND VndName BETWEEN '$vnf' AND '$vnt'";
    elseif ($vname_from !== '') $where .= " AND VndName = '$vnf'";
    else $where .= " AND VndName <= '$vnt'";
}

// Vendor master has its own vendor grouping.  Product Category/Subcategory here
// intentionally means products the vendor has supplied on a PO.
$vendor_product_where = [];
$cat_where = recv_range_inner('p.CateCode', $cat_from, '');
if ($cat_where !== '') $vendor_product_where[] = $cat_where;
$subcat_where = recv_range_inner('p.SubCatCode', $subcat_from, '');
if ($subcat_where !== '') $vendor_product_where[] = $subcat_where;
if ($vendor_product_where) {
    $where .= " AND VndCode IN (SELECT DISTINCT h.VndCode FROM invpo0 h JOIN invpo1 d ON d.SeqNo = h.SeqNo JOIN gblprod p ON p.PrdId = d.PrdID WHERE " . implode(' AND ', $vendor_product_where) . ")";
}

$active_filters = ($vnd_from !== '' ? 1 : 0) + ($vnd_to !== '' ? 1 : 0) + ($vname_from !== '' ? 1 : 0) + ($vname_to !== '' ? 1 : 0) + ($cat_from !== '' ? 1 : 0) + ($subcat_from !== '' ? 1 : 0);

$result = mysqli_query($conn, "
    SELECT VndCode, VndName, VndAdd1, VndAdd2, VndTel, VndTaxNo, VndCurBal, VndTerm,
           VndLastCls
    FROM gblvend
    WHERE $where
    ORDER BY VndName
");

// ── Datalist data (พิมพ์ค้นหาได้ — ไม่ผูกกับผลค้นหาปัจจุบัน) ──
$r_vnd_dl     = mysqli_query($conn, "SELECT VndCode, VndName FROM gblvend ORDER BY VndCode");
$r_vndname_dl = mysqli_query($conn, "SELECT VndCode, VndName FROM gblvend ORDER BY VndName");
$r_lookup_table = mysqli_query($conn, "SHOW TABLES LIKE 'lookups'");
$has_lookups = $r_lookup_table && mysqli_num_rows($r_lookup_table) > 0;
$lookup_name = $has_lookups ? 'l.TbVal1' : "''";
$lookup_join = $has_lookups ? "LEFT JOIN lookups l ON l.TbName = 'CATE' AND l.TbKey = p.CateCode" : '';
$r_cat = mysqli_query($conn, "SELECT DISTINCT p.CateCode, $lookup_name AS CateName FROM gblprod p $lookup_join WHERE p.CateCode <> '' AND p.CateCode IS NOT NULL ORDER BY p.CateCode");
$lookup_join = $has_lookups ? "LEFT JOIN lookups l ON l.TbName = 'SCAT' AND l.TbKey = p.SubCatCode" : '';
$r_subcat = mysqli_query($conn, "SELECT DISTINCT p.SubCatCode, p.CateCode, $lookup_name AS SubCatName FROM gblprod p $lookup_join WHERE p.SubCatCode <> '' AND p.SubCatCode IS NOT NULL ORDER BY p.SubCatCode");

require_once __DIR__ . '/includes/header.php';
?>

<div class="card mb-3">
  <div class="card-header-inv d-flex justify-content-between align-items-center">
    <span><i class="bi bi-funnel me-1"></i> <?= t('filter') ?> <span style="font-weight:400;opacity:.8;font-size:12px">— <?= t('date_from') ?> / <?= t('date_to') ?></span></span>
    <?php if ($active_filters > 0): ?><span class="badge" style="background:rgba(255,255,255,.25)"><?= $active_filters ?></span><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-12 col-md-6 col-xl-4">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-search me-1 text-muted"></i><?= t('vendor_search') ?></label>
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="<?= t('vendor_search_ph') ?>"
               value="<?= htmlspecialchars($search) ?>">
      </div>

      <!-- Vendor Code range -->
      <div class="col-12 col-md-6 col-xl-4">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-upc me-1 text-muted"></i><?= t('vendor_code') ?></label>
        <div class="d-flex gap-1">
          <input type="text" name="vnd_from" list="dl_vnd" class="form-control form-control-sm" placeholder="From" value="<?= htmlspecialchars($vnd_from) ?>" style="text-transform:uppercase">
          <span class="align-self-center text-muted small">→</span>
          <input type="text" name="vnd_to" list="dl_vnd" class="form-control form-control-sm" placeholder="To" value="<?= htmlspecialchars($vnd_to) ?>" style="text-transform:uppercase">
        </div>
      </div>

      <!-- Vendor Name range -->
      <div class="col-12 col-md-6 col-xl-4">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-building me-1 text-muted"></i><?= t('vendor_name') ?></label>
        <div class="d-flex gap-1">
          <input type="text" name="vname_from" list="dl_vndname" class="form-control form-control-sm" placeholder="From" value="<?= htmlspecialchars($vname_from) ?>">
          <span class="align-self-center text-muted small">→</span>
          <input type="text" name="vname_to" list="dl_vndname" class="form-control form-control-sm" placeholder="To" value="<?= htmlspecialchars($vname_to) ?>">
        </div>
      </div>

      <div class="col-12 col-md-6 col-xl-4">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-tags me-1 text-muted"></i><?= t('category') ?> (<?= $GLOBALS['LANG'] === 'th' ? 'สินค้าที่เคยซื้อ' : 'Purchased products' ?>)</label>
        <select name="cat_from" data-category-from class="form-select form-select-sm">
          <option value="">— <?= t('all') ?> —</option>
          <?php while ($c = mysqli_fetch_assoc($r_cat)): ?>
            <option value="<?= htmlspecialchars($c['CateCode']) ?>" <?= $cat_from === $c['CateCode'] ? 'selected' : '' ?>><?= htmlspecialchars($c['CateCode']) ?> — <?= htmlspecialchars(db_str($c['CateName'])) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-12 col-md-6 col-xl-4">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-tags me-1 text-muted"></i><?= t('subcategory') ?> (<?= $GLOBALS['LANG'] === 'th' ? 'สินค้าที่เคยซื้อ' : 'Purchased products' ?>)</label>
        <select name="subcat_from" data-subcategory-filter class="form-select form-select-sm">
          <option value="">— <?= t('all') ?> —</option>
          <?php while ($s = mysqli_fetch_assoc($r_subcat)): ?>
            <option value="<?= htmlspecialchars($s['SubCatCode']) ?>" data-category="<?= htmlspecialchars($s['CateCode']) ?>" <?= $subcat_from === $s['SubCatCode'] ? 'selected' : '' ?>><?= htmlspecialchars($s['SubCatCode']) ?> — <?= htmlspecialchars(db_str($s['SubCatName']) ?: $s['CateCode']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-12 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-inv-primary"><i class="bi bi-search"></i> <?= t('search') ?></button>
        <a href="/vendor_list.php" class="btn btn-sm btn-inv-outline"><i class="bi bi-x-lg"></i> <?= t('reset') ?></a>
        <span class="small text-muted align-self-center ms-1">💡 เว้น "To" = ค่าเดียว · ใส่ทั้งคู่ = ช่วง</span>
      </div>
    </form>
  </div>
</div>

<!-- Datalists -->
<datalist id="dl_vnd"><?php while ($v = mysqli_fetch_assoc($r_vnd_dl)): ?><option value="<?= htmlspecialchars($v['VndCode']) ?>"><?= htmlspecialchars(db_str($v['VndName'])) ?></option><?php endwhile; ?></datalist>
<datalist id="dl_vndname"><?php while ($v = mysqli_fetch_assoc($r_vndname_dl)): $vn = db_str($v['VndName']); if ($vn === '' || strcasecmp($vn, 'NULL') === 0) continue; ?><option value="<?= htmlspecialchars($vn) ?>"><?= htmlspecialchars($v['VndCode']) ?></option><?php endwhile; ?></datalist>

<div class="card">
  <div class="card-header-inv d-flex align-items-center justify-content-between">
    <span>
      <i class="bi bi-building me-1"></i> <?= t('vendor_list_title') ?>
      <span class="badge ms-1" data-inv-count style="background:rgba(255,255,255,.2)"><?= $result ? mysqli_num_rows($result) : 0 ?></span>
    </span>
    <?php
      $vnd_export_params = array_filter([
          'search' => $search, 'vnd_from' => $vnd_from, 'vnd_to' => $vnd_to,
          'vname_from' => $vname_from, 'vname_to' => $vname_to,
          'cat_from' => $cat_from, 'subcat_from' => $subcat_from,
      ], fn($v) => $v !== '');
    ?>
    <div class="d-flex gap-1">
      <a href="<?= htmlspecialchars('/export.php?' . http_build_query(array_merge(['type' => 'vendor', 'format' => 'excel'], $vnd_export_params))) ?>" class="btn btn-sm"
         style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)" target="_blank">
        <i class="bi bi-file-earmark-excel me-1"></i> Excel
      </a>
      <a href="<?= htmlspecialchars('/export.php?' . http_build_query(array_merge(['type' => 'vendor', 'format' => 'pdf'], $vnd_export_params))) ?>" class="btn btn-sm"
         style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)" target="_blank">
        <i class="bi bi-file-earmark-pdf me-1"></i> PDF
      </a>
    </div>
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
