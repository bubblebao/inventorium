<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/recv_filter.php';
$page_title = t('recv_list_title');

if (!defined('PER_PAGE')) define('PER_PAGE', 50);

// ─── Year range จาก DB (cached 24h — กรอง garbage year ออก: มีข้อมูลถึงปี 6533) ──
$range = cache_remember('recv_year_range', 86400, function() use ($conn) {
    $r = mysqli_query($conn, "
        SELECT MIN(YEAR(RecvDate)) AS y_min, MAX(YEAR(RecvDate)) AS y_max
        FROM invrecv0
        WHERE RecvDate IS NOT NULL AND RecvDate <> '0000-00-00'
          AND YEAR(RecvDate) BETWEEN 2000 AND 2100
    ");
    $row = $r ? mysqli_fetch_assoc($r) : null;
    return ['min' => (int)($row['y_min'] ?? 2020), 'max' => (int)($row['y_max'] ?? date('Y'))];
});
$year_min = $range['min'];
$year_max = $range['max'];

// ── Filters (ช่วง From/To ทุกช่อง) ──
$p = recv_collect_params("$year_max-01-01", "$year_max-12-31");
$where = recv_where_from_params($p);
$page  = max(1, (int)($_GET['page'] ?? 1));

// Active year detection (ไฮไลต์ปุ่ม) — เฉพาะตอนไม่มี filter ช่วงอื่น
$active_year = null;
if (preg_match('/^(\d{4})-01-01$/', $p['date_from'], $mf) && preg_match('/^(\d{4})-12-31$/', $p['date_to'], $mt) && $mf[1] === $mt[1]) {
    $active_year = (int)$mf[1];
}
$is_all_time = ($p['date_from'] === "$year_min-01-01" && $p['date_to'] === "$year_max-12-31");

// ── Sort whitelist (กัน SQL injection) ──
$sort_map = ['RecvDate' => 'h.RecvDate', 'TAmt' => 'TAmt',
             'VndName'  => 'v.VndName',  'RecvNo' => 'h.RecvNo'];
$sort_key = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : 'RecvDate';
$sort_dir = isset($_GET['dir'])  && strtoupper($_GET['dir']) === 'ASC' ? 'ASC' : 'DESC';
$order_by = $sort_map[$sort_key] . ' ' . $sort_dir . ', h.SeqNo DESC';

// ── Count ──
$r_count    = mysqli_query($conn, "SELECT COUNT(*) AS total FROM invrecv0 h WHERE $where");
$total_rows = (int)(($r_count ? mysqli_fetch_assoc($r_count) : null)['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_rows / PER_PAGE));
$page       = min($page, $total_pages);
$offset     = ($page - 1) * PER_PAGE;

// ── Main query ──
$result = mysqli_query($conn, "
    SELECT h.SeqNo, h.RecvDate, h.RecvNo, h.RefNo, h.PoNo, h.VndCode, v.VndName,
           (SELECT SUM(Amount) FROM invrecv1 WHERE SeqNo = h.SeqNo) AS TAmt,
           h.LocaCode, h.InvType, h.CreateUser, h.Remark
    FROM invrecv0 h
    LEFT JOIN gblvend v ON h.VndCode = v.VndCode
    WHERE $where
    ORDER BY $order_by
    LIMIT $offset, " . PER_PAGE . "
");

// ── Datalist data (พิมพ์ค้นหาได้ — ดีกว่า dropdown ของ Carmen) ──
$r_vnd = mysqli_query($conn, "SELECT VndCode, VndName FROM gblvend ORDER BY VndCode");
$r_loc = mysqli_query($conn, "SELECT DISTINCT LocaCode FROM invrecv0 WHERE LocaCode <> '' AND LocaCode IS NOT NULL ORDER BY LocaCode");
$r_cat = mysqli_query($conn, "SELECT CateCode, PrdDescE FROM gblprod WHERE CateCode <> '' AND CateCode IS NOT NULL GROUP BY CateCode ORDER BY CateCode");
$r_prd = mysqli_query($conn, "SELECT PrdId, PrdDescE FROM gblprod WHERE Active = 1 ORDER BY PrdId");

// ── Export + link params ──
$filter_params = recv_nonempty_params($p);
$export_excel = '/export.php?' . http_build_query(array_merge(['format'=>'excel','type'=>'recv_list'], $filter_params));
$export_pdf   = '/export.php?' . http_build_query(array_merge(['format'=>'pdf',  'type'=>'recv_list'], $filter_params));

$base_params = array_filter(array_merge($filter_params, [
    'sort' => $sort_key !== 'RecvDate' ? $sort_key : '',
    'dir'  => $sort_dir !== 'DESC'     ? $sort_dir  : '',
]), fn($v) => $v !== '');

// Receiving Type badge
function recv_type_badge(?string $t): string {
    if ($t === 'I') return '<span class="badge-inv badge-paid">' . t('recv_type_inv') . '</span>';
    if ($t === 'D') return '<span class="badge-inv badge-src">'  . t('recv_type_direct') . '</span>';
    return '<span class="badge-inv badge-src">' . htmlspecialchars((string)$t) . '</span>';
}

require_once __DIR__ . '/includes/header.php';

// Helper: sortable <th>
function sort_th(string $col, string $label, string $cur_sort, string $cur_dir, array $base): string {
    $is_active = $cur_sort === $col;
    $next_dir  = $is_active && $cur_dir === 'DESC' ? 'ASC' : 'DESC';
    $icon      = $is_active ? ($cur_dir === 'ASC' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up';
    $cls       = 'sortable' . ($is_active ? ' sort-active' : '');
    $url       = '?' . http_build_query(array_merge($base, ['sort' => $col, 'dir' => $next_dir, 'page' => 1]));
    return "<th class=\"$cls\"><a href=\"" . htmlspecialchars($url) . "\" style=\"color:inherit;text-decoration:none\">"
         . htmlspecialchars($label) . " <i class=\"bi $icon sort-icon\"></i></a></th>";
}

// Count active range filters (โชว์ badge บอกว่ากรองกี่เงื่อนไข)
$active_filters = 0;
foreach (['vnd_from','vnd_to','loc_from','loc_to','cat_from','cat_to','prd_from','prd_to','ref_from','ref_to','inv_type'] as $k) {
    if ($p[$k] !== '') { $active_filters++; }
}
?>

<!-- Year Shortcut Bar -->
<?php
  $keep = recv_nonempty_params(array_diff_key($p, ['date_from'=>1, 'date_to'=>1]));
  $build_year_url = fn($from, $to) => '?' . http_build_query(array_merge($keep, ['date_from' => $from, 'date_to' => $to]));
?>
<div class="date-shortcuts mb-2" style="align-items:center;flex-wrap:wrap;gap:6px">
  <span style="font-size:12px;color:var(--muted);line-height:2"><?= t('period') ?>:</span>
  <?php for ($y = $year_max; $y >= $year_min; $y--):
    $is_active = ($active_year === $y);
  ?>
    <a href="<?= htmlspecialchars($build_year_url("$y-01-01", "$y-12-31")) ?>"
       class="btn btn-sm <?= $is_active ? 'btn-inv-primary' : 'btn-inv-outline' ?>" data-loading
       style="<?= $is_active ? 'box-shadow:0 0 0 2px rgba(14,165,233,.3)' : '' ?>">
      <?php if ($y === $year_max): ?><i class="bi bi-star-fill" style="font-size:9px;color:#f59e0b"></i> <?php endif; ?><?= $y ?>
    </a>
  <?php endfor; ?>
  <a href="<?= htmlspecialchars($build_year_url("$year_min-01-01", "$year_max-12-31")) ?>"
     class="btn btn-sm <?= $is_all_time ? 'btn-inv-primary' : 'btn-inv-outline' ?>" data-loading
     style="<?= $is_all_time ? 'box-shadow:0 0 0 2px rgba(14,165,233,.3)' : '' ?>">
    <i class="bi bi-infinity" style="font-size:11px"></i> All
  </a>
</div>

<!-- Filter Card — range From/To ทุกช่อง -->
<div class="card mb-3">
  <div class="card-header-inv d-flex justify-content-between align-items-center">
    <span><i class="bi bi-funnel me-1"></i> <?= t('filter') ?> <span style="font-weight:400;opacity:.8;font-size:12px">— <?= t('date_from') ?> / <?= t('date_to') ?></span></span>
    <?php if ($active_filters > 0): ?><span class="badge" style="background:rgba(255,255,255,.25)"><?= $active_filters ?></span><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">

      <!-- Date -->
      <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-calendar3 me-1 text-muted"></i><?= t('recv_date') ?></label>
        <div class="d-flex gap-1">
          <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($p['date_from']) ?>">
          <span class="align-self-center text-muted small">→</span>
          <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($p['date_to']) ?>">
        </div>
      </div>

      <!-- Vendor range -->
      <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-building me-1 text-muted"></i><?= t('vendor') ?></label>
        <div class="d-flex gap-1">
          <input type="text" name="vnd_from" list="dl_vnd" class="form-control form-control-sm" placeholder="From" value="<?= htmlspecialchars($p['vnd_from']) ?>" style="text-transform:uppercase">
          <span class="align-self-center text-muted small">→</span>
          <input type="text" name="vnd_to" list="dl_vnd" class="form-control form-control-sm" placeholder="To" value="<?= htmlspecialchars($p['vnd_to']) ?>" style="text-transform:uppercase">
        </div>
      </div>

      <!-- Product range -->
      <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-box-seam me-1 text-muted"></i><?= t('product_label') ?></label>
        <div class="d-flex gap-1">
          <input type="text" name="prd_from" list="dl_prd" class="form-control form-control-sm" placeholder="From" value="<?= htmlspecialchars($p['prd_from']) ?>" style="text-transform:uppercase">
          <span class="align-self-center text-muted small">→</span>
          <input type="text" name="prd_to" list="dl_prd" class="form-control form-control-sm" placeholder="To" value="<?= htmlspecialchars($p['prd_to']) ?>" style="text-transform:uppercase">
        </div>
      </div>

      <!-- Location range -->
      <div class="col-6 col-md-6 col-xl-3">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-geo-alt me-1 text-muted"></i><?= t('location') ?></label>
        <div class="d-flex gap-1">
          <input type="text" name="loc_from" list="dl_loc" class="form-control form-control-sm" placeholder="From" value="<?= htmlspecialchars($p['loc_from']) ?>" style="text-transform:uppercase">
          <span class="align-self-center text-muted small">→</span>
          <input type="text" name="loc_to" list="dl_loc" class="form-control form-control-sm" placeholder="To" value="<?= htmlspecialchars($p['loc_to']) ?>" style="text-transform:uppercase">
        </div>
      </div>

      <!-- Category range -->
      <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-tags me-1 text-muted"></i><?= t('category') ?></label>
        <div class="d-flex gap-1">
          <input type="text" name="cat_from" list="dl_cat" class="form-control form-control-sm" placeholder="From" value="<?= htmlspecialchars($p['cat_from']) ?>" style="text-transform:uppercase">
          <span class="align-self-center text-muted small">→</span>
          <input type="text" name="cat_to" list="dl_cat" class="form-control form-control-sm" placeholder="To" value="<?= htmlspecialchars($p['cat_to']) ?>" style="text-transform:uppercase">
        </div>
      </div>

      <!-- Reference# range -->
      <div class="col-12 col-md-6 col-xl-3">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-hash me-1 text-muted"></i><?= t('ref_no_label') ?></label>
        <div class="d-flex gap-1">
          <input type="text" name="ref_from" class="form-control form-control-sm" placeholder="From" value="<?= htmlspecialchars($p['ref_from']) ?>" style="text-transform:uppercase">
          <span class="align-self-center text-muted small">→</span>
          <input type="text" name="ref_to" class="form-control form-control-sm" placeholder="To" value="<?= htmlspecialchars($p['ref_to']) ?>" style="text-transform:uppercase">
        </div>
      </div>

      <!-- Receiving Type (single) -->
      <div class="col-6 col-md-6 col-xl-2">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-arrow-down-square me-1 text-muted"></i><?= t('recv_type') ?></label>
        <select name="inv_type" class="form-select form-select-sm">
          <option value=""><?= t('all') ?></option>
          <option value="I" <?= $p['inv_type'] === 'I' ? 'selected' : '' ?>><?= t('recv_type_inv') ?></option>
          <option value="D" <?= $p['inv_type'] === 'D' ? 'selected' : '' ?>><?= t('recv_type_direct') ?></option>
        </select>
      </div>

      <!-- Actions -->
      <div class="col-12 col-xl-4 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-inv-primary"><i class="bi bi-search"></i> <?= t('search') ?></button>
        <a href="/recv_list.php" class="btn btn-sm btn-inv-outline"><i class="bi bi-x-lg"></i> <?= t('reset') ?></a>
        <span class="small text-muted align-self-center ms-1">💡 เว้น "To" = ค่าเดียว · ใส่ทั้งคู่ = ช่วง</span>
      </div>
    </form>
  </div>
</div>

<!-- Datalists -->
<datalist id="dl_vnd"><?php while ($v = mysqli_fetch_assoc($r_vnd)): ?><option value="<?= htmlspecialchars($v['VndCode']) ?>"><?= htmlspecialchars(db_str($v['VndName'])) ?></option><?php endwhile; ?></datalist>
<datalist id="dl_loc"><?php while ($s = mysqli_fetch_assoc($r_loc)): ?><option value="<?= htmlspecialchars($s['LocaCode']) ?>"></option><?php endwhile; ?></datalist>
<datalist id="dl_cat"><?php while ($c = mysqli_fetch_assoc($r_cat)): ?><option value="<?= htmlspecialchars($c['CateCode']) ?>"><?= htmlspecialchars(db_str($c['PrdDescE'])) ?></option><?php endwhile; ?></datalist>
<datalist id="dl_prd"><?php while ($pr = mysqli_fetch_assoc($r_prd)): ?><option value="<?= htmlspecialchars($pr['PrdId']) ?>"><?= htmlspecialchars(db_str($pr['PrdDescE'])) ?></option><?php endwhile; ?></datalist>

<!-- Results Card -->
<div class="card">
  <div class="card-header-inv d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>
      <i class="bi bi-box-arrow-in-down me-1"></i> <?= t('recv_records') ?>
      <span class="badge ms-1" style="background:rgba(255,255,255,.2)"><?= number_format($total_rows) ?> <?= t('records') ?></span>
    </span>
    <div class="d-flex gap-1">
      <a href="<?= htmlspecialchars($export_excel) ?>" class="btn btn-sm"
         style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)" target="_blank">
        <i class="bi bi-file-earmark-excel me-1"></i> Excel
      </a>
      <a href="<?= htmlspecialchars($export_pdf) ?>" class="btn btn-sm"
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
            <?= sort_th('RecvDate', t('col_date'),    $sort_key, $sort_dir, $base_params) ?>
            <?= sort_th('RecvNo',   t('col_recv_no'),  $sort_key, $sort_dir, $base_params) ?>
            <th class="d-none d-xl-table-cell"><?= t('col_po_ref') ?></th>
            <?= sort_th('VndName',  t('col_vendor'),   $sort_key, $sort_dir, $base_params) ?>
            <th><?= t('recv_type') ?></th>
            <?= sort_th('TAmt',     t('col_total'),    $sort_key, $sort_dir, $base_params) ?>
            <th class="d-none d-lg-table-cell"><?= t('col_loc') ?></th>
            <th class="d-none d-xl-table-cell"><?= t('recorded_by') ?></th>
            <th data-nofilter></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$result || mysqli_num_rows($result) === 0): ?>
          <tr class="inv-no-filter"><td colspan="9" class="text-center text-muted py-4"><?= t('no_data') ?></td></tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <tr style="cursor:pointer"
              onclick="sessionStorage.setItem('recv_detail_back_url',window.location.href);location.href='/recv_detail.php?seq=<?= (int)$row['SeqNo'] ?>'">
            <td><?= fmt_date($row['RecvDate']) ?></td>
            <td><code style="font-size:11px"><?= htmlspecialchars($row['RecvNo']) ?></code></td>
            <td class="text-muted d-none d-xl-table-cell" style="font-size:11.5px">
              <?php if (!empty($row['PoNo'])): ?><div><i class="bi bi-file-text" style="font-size:10px"></i> <?= htmlspecialchars($row['PoNo']) ?></div><?php endif; ?>
              <?php if (!empty($row['RefNo'])): ?><div><?= htmlspecialchars($row['RefNo']) ?></div><?php endif; ?>
            </td>
            <td>
              <div><?= htmlspecialchars(db_str($row['VndName']) ?: $row['VndCode']) ?></div>
              <small style="color:var(--muted)"><?= htmlspecialchars($row['VndCode']) ?></small>
              <div class="d-lg-none" style="font-size:10.5px;color:var(--muted);margin-top:2px">
                <span class="badge-inv badge-src" style="font-size:9px;padding:1px 6px"><?= htmlspecialchars($row['LocaCode']) ?></span>
                <?php if (!empty($row['CreateUser'])): ?> · <?= htmlspecialchars(db_str($row['CreateUser'])) ?><?php endif; ?>
              </div>
            </td>
            <td><?= recv_type_badge($row['InvType']) ?></td>
            <td class="text-end fw-semibold"><?= fmt_number($row['TAmt']) ?></td>
            <td class="d-none d-lg-table-cell"><span class="badge-inv badge-src"><?= htmlspecialchars($row['LocaCode']) ?></span></td>
            <td class="d-none d-xl-table-cell" style="font-size:11.5px;color:var(--muted)"><?= htmlspecialchars(db_str($row['CreateUser'])) ?></td>
            <td>
              <a href="/recv_detail.php?seq=<?= (int)$row['SeqNo'] ?>"
                 class="btn btn-sm" style="border:1px solid var(--accent);color:var(--accent);border-radius:6px" data-loading>
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($total_pages > 1): ?>
  <div class="card-footer" style="background:var(--surface-2);border-top:1px solid var(--border)">
    <nav class="d-flex justify-content-between align-items-center">
      <small style="color:var(--muted)">
        <?= $GLOBALS['LANG'] === 'th' ? 'หน้า' : 'Page' ?> <?= $page ?> / <?= $total_pages ?> (<?= number_format($total_rows) ?> <?= t('records') ?>)
      </small>
      <ul class="pagination pagination-sm mb-0">
        <?php
        $prev_url = '?' . http_build_query(array_merge($base_params, ['page' => $page - 1]));
        $next_url = '?' . http_build_query(array_merge($base_params, ['page' => $page + 1]));
        $prev_label = $GLOBALS['LANG'] === 'th' ? '‹ ก่อน' : '‹ Prev';
        $next_label = $GLOBALS['LANG'] === 'th' ? 'ถัดไป ›' : 'Next ›';
        ?>
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= htmlspecialchars($prev_url) ?>"><?= $prev_label ?></a>
        </li>
        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        for ($pg = $start; $pg <= $end; $pg++):
            $p_url = '?' . http_build_query(array_merge($base_params, ['page' => $pg]));
        ?>
          <li class="page-item <?= $pg === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= htmlspecialchars($p_url) ?>"><?= $pg ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= htmlspecialchars($next_url) ?>"><?= $next_label ?></a>
        </li>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>

<script>
  sessionStorage.setItem('recv_list_last_url', window.location.href);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
