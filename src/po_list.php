<?php
require_once __DIR__ . '/config/db.php';
$page_title = t('po_list');

if (!defined('PER_PAGE')) define('PER_PAGE', 50);

$today = date('Y-m-d');

// ─── Year range จาก DB (cached 24h — data frozen) ──────────────────────────
$range = cache_remember('po_year_range', 86400, function() use ($conn) {
    $r = mysqli_query($conn, "
        SELECT MIN(YEAR(PoDate)) AS y_min, MAX(YEAR(PoDate)) AS y_max
        FROM invpo0
        WHERE PoDate IS NOT NULL AND PoDate <> '0000-00-00' AND YEAR(PoDate) > 1990
    ");
    $row = $r ? mysqli_fetch_assoc($r) : null;
    return ['min' => (int)($row['y_min'] ?? 2020), 'max' => (int)($row['y_max'] ?? date('Y'))];
});
$year_min = $range['min'];
$year_max = $range['max'];

// ── Filter inputs (default = latest year)──
$date_from = $_GET['date_from'] ?? "$year_max-01-01";
$date_to   = $_GET['date_to']   ?? "$year_max-12-31";
$vnd_code  = $_GET['vnd_code']  ?? '';
$inv_no    = $_GET['inv_no']    ?? '';
$source    = $_GET['source']    ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = "$year_max-01-01";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = "$year_max-12-31";

// Active year detection (for button highlight)
$active_year = null;
if (preg_match('/^(\d{4})-01-01$/', $date_from, $mf) && preg_match('/^(\d{4})-12-31$/', $date_to, $mt) && $mf[1] === $mt[1]) {
    $active_year = (int)$mf[1];
}
$is_all_time = ($date_from === "$year_min-01-01" && $date_to === "$year_max-12-31");

// ── Sort whitelist (SQL injection prevention) ──
$sort_map   = ['PoDate' => 'h.PoDate', 'TAmt' => 'TAmt',
               'VndName' => 'v.VndName', 'DeliveryDate' => 'h.DeliveryDate'];
$sort_key   = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : 'PoDate';
$sort_dir   = isset($_GET['dir'])  && strtoupper($_GET['dir']) === 'ASC'         ? 'ASC'         : 'DESC';
$order_by   = $sort_map[$sort_key] . ' ' . $sort_dir . ', h.SeqNo DESC';

// ── WHERE clause ──
$where = "h.PoDate BETWEEN '" . db_escape($date_from) . "' AND '" . db_escape($date_to) . "'";
if ($vnd_code !== '') $where .= " AND h.VndCode = '"     . db_escape($vnd_code) . "'";
if ($inv_no   !== '') $where .= " AND h.PoNo LIKE '%"    . db_escape($inv_no)   . "%'";
if ($source   !== '') $where .= " AND h.LocaCode = '"    . db_escape($source)   . "'";

// ── Count ──
$r_count    = mysqli_query($conn, "SELECT COUNT(*) AS total FROM invpo0 h WHERE $where");
$total_rows = (int)(($r_count ? mysqli_fetch_assoc($r_count) : null)['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_rows / PER_PAGE));
$page       = min($page, $total_pages);
$offset     = ($page - 1) * PER_PAGE;

// ── Main query — correlated subquery for total (fast for LIMIT 50) ──
$result = mysqli_query($conn, "
    SELECT h.SeqNo, h.PoDate, h.PoNo, h.RefNo, h.VndCode, v.VndName,
           (SELECT SUM(Amount) FROM invpo1 WHERE SeqNo = h.SeqNo) AS TAmt,
           h.LocaCode, h.CreateUser, h.DeliveryDate, h.Remark
    FROM invpo0 h
    LEFT JOIN gblvend v ON h.VndCode = v.VndCode
    WHERE $where
    ORDER BY $order_by
    LIMIT $offset, " . PER_PAGE . "
");

// ── Dropdown data ──
$r_vnd = mysqli_query($conn, "SELECT VndCode, VndName FROM gblvend ORDER BY VndName");
$r_src = mysqli_query($conn, "SELECT DISTINCT LocaCode FROM invpo0 WHERE LocaCode <> '' AND LocaCode IS NOT NULL ORDER BY LocaCode");

// ── Export URLs ──
$filter_params = ['date_from' => $date_from, 'date_to' => $date_to,
                  'vnd_code' => $vnd_code, 'inv_no' => $inv_no, 'source' => $source];
$export_excel = '/export.php?' . http_build_query(array_merge(['format'=>'excel','type'=>'po_list'], $filter_params));
$export_pdf   = '/export.php?' . http_build_query(array_merge(['format'=>'pdf',  'type'=>'po_list'], $filter_params));

// ── Base params for pagination/sort links ──
$base_params = array_filter(array_merge($filter_params, [
    'sort' => $sort_key !== 'PoDate' ? $sort_key : '',
    'dir'  => $sort_dir !== 'DESC'   ? $sort_dir  : '',
]));

// Count active filters (นอกเหนือจากวันที่) — โชว์ badge
$active_filters = ($vnd_code !== '' ? 1 : 0) + ($inv_no !== '' ? 1 : 0) + ($source !== '' ? 1 : 0);

require_once __DIR__ . '/includes/header.php';

// Helper: build sortable <th>
function sort_th(string $col, string $label, string $cur_sort, string $cur_dir, array $base): string {
    $is_active = $cur_sort === $col;
    $next_dir  = $is_active && $cur_dir === 'DESC' ? 'ASC' : 'DESC';
    $icon      = $is_active ? ($cur_dir === 'ASC' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up';
    $cls       = 'sortable' . ($is_active ? ' sort-active' : '');
    $url       = '?' . http_build_query(array_merge($base, ['sort' => $col, 'dir' => $next_dir, 'page' => 1]));
    $safe_url  = htmlspecialchars($url);
    return "<th class=\"$cls\"><a href=\"$safe_url\" style=\"color:inherit;text-decoration:none\">"
         . htmlspecialchars($label)
         . " <i class=\"bi $icon sort-icon\"></i></a></th>";
}
?>

<!-- Year Shortcut Bar -->
<?php
  // Preserve current filter values when switching year
  $keep = array_filter(['vnd_code' => $vnd_code, 'inv_no' => $inv_no, 'source' => $source], fn($v) => $v !== '');
  $build_year_url = function($from, $to) use ($keep) {
      return '?' . http_build_query(array_merge($keep, ['date_from' => $from, 'date_to' => $to]));
  };
?>
<div class="date-shortcuts mb-2" style="align-items:center;flex-wrap:wrap;gap:6px">
  <span style="font-size:12px;color:var(--muted);line-height:2"><?= t('period') ?>:</span>
  <?php for ($y = $year_max; $y >= $year_min; $y--):
    $is_active = ($active_year === $y);
    $cls = $is_active ? 'btn-inv-primary' : 'btn-inv-outline';
  ?>
    <a href="<?= htmlspecialchars($build_year_url("$y-01-01", "$y-12-31")) ?>"
       class="btn btn-sm <?= $cls ?>"
       data-loading
       style="<?= $is_active ? 'box-shadow:0 0 0 2px rgba(14,165,233,.3)' : '' ?>">
      <?php if ($y === $year_max): ?><i class="bi bi-star-fill" style="font-size:9px;color:#f59e0b"></i> <?php endif; ?><?= $y ?>
    </a>
  <?php endfor; ?>
  <a href="<?= htmlspecialchars($build_year_url("$year_min-01-01", "$year_max-12-31")) ?>"
     class="btn btn-sm <?= $is_all_time ? 'btn-inv-primary' : 'btn-inv-outline' ?>"
     data-loading
     style="<?= $is_all_time ? 'box-shadow:0 0 0 2px rgba(14,165,233,.3)' : '' ?>">
    <i class="bi bi-infinity" style="font-size:11px"></i> All
  </a>
</div>

<!-- Filter Card -->
<div class="card mb-3">
  <div class="card-header-inv d-flex justify-content-between align-items-center">
    <span><i class="bi bi-funnel me-1"></i> <?= t('filter') ?></span>
    <?php if ($active_filters > 0): ?><span class="badge" style="background:rgba(255,255,255,.25)"><?= $active_filters ?></span><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small fw-bold"><?= t('date_from') ?></label>
        <input type="date" id="date_from" name="date_from" class="form-control form-control-sm"
               value="<?= htmlspecialchars($date_from) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-bold"><?= t('date_to') ?></label>
        <input type="date" id="date_to" name="date_to" class="form-control form-control-sm"
               value="<?= htmlspecialchars($date_to) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-bold"><?= t('vendor') ?></label>
        <select name="vnd_code" class="form-select form-select-sm">
          <option value="">— <?= t('all') ?> —</option>
          <?php while ($v = mysqli_fetch_assoc($r_vnd)): ?>
            <option value="<?= htmlspecialchars($v['VndCode']) ?>"
              <?= $vnd_code === $v['VndCode'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($v['VndCode']) ?> — <?= htmlspecialchars(db_str($v['VndName'])) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-bold"><?= t('po_no_label') ?></label>
        <input type="text" name="inv_no" class="form-control form-control-sm"
               placeholder="<?= t('placeholder_search') ?>" value="<?= htmlspecialchars($inv_no) ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label small fw-bold"><?= t('location') ?></label>
        <select name="source" class="form-select form-select-sm">
          <option value=""><?= t('all') ?></option>
          <?php while ($s = mysqli_fetch_assoc($r_src)): ?>
            <option value="<?= htmlspecialchars($s['LocaCode']) ?>"
              <?= $source === $s['LocaCode'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['LocaCode']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-inv-primary"><i class="bi bi-search"></i> <?= t('search') ?></button>
        <a href="/po_list.php" class="btn btn-sm btn-inv-outline"><?= t('reset') ?></a>
      </div>
    </form>
  </div>
</div>

<!-- Results Card -->
<div class="card">
  <div class="card-header-inv d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>
      <i class="bi bi-list-ul me-1"></i> <?= t('po_list_records') ?>
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
            <?= sort_th('PoDate',       t('col_date'),     $sort_key, $sort_dir, $base_params) ?>
            <th><?= t('col_po_no') ?></th>
            <th class="d-none d-xl-table-cell"><?= t('col_ref') ?></th>
            <?= sort_th('VndName',       t('col_vendor'),    $sort_key, $sort_dir, $base_params) ?>
            <?= sort_th('TAmt',         t('col_total'),     $sort_key, $sort_dir, $base_params) ?>
            <?= sort_th('DeliveryDate', t('col_due_date'),  $sort_key, $sort_dir, $base_params) ?>
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
          <?php
            $due = $row['DeliveryDate'];
            $is_overdue  = $due && $due !== '0000-00-00' && $due <  $today;
            $is_due_soon = $due && $due !== '0000-00-00' && !$is_overdue
                        && $due <= date('Y-m-d', strtotime('+7 days'));
          ?>
          <tr style="cursor:pointer"
              onclick="sessionStorage.setItem('po_detail_back_url',window.location.href);location.href='/po_detail.php?seq=<?= (int)$row['SeqNo'] ?>'">
            <td><?= fmt_date($row['PoDate']) ?></td>
            <td><code style="font-size:11px"><?= htmlspecialchars($row['PoNo']) ?></code></td>
            <td class="text-muted d-none d-xl-table-cell" style="font-size:11.5px"><?= htmlspecialchars($row['RefNo']) ?></td>
            <td>
              <div><?= htmlspecialchars(db_str($row['VndName']) ?: $row['VndCode']) ?></div>
              <small style="color:var(--muted)"><?= htmlspecialchars($row['VndCode']) ?></small>
              <!-- Show LOC + ผู้บันทึก inline ตอนซ่อนคอลัมน์ -->
              <div class="d-lg-none" style="font-size:10.5px;color:var(--muted);margin-top:2px">
                <span class="badge-inv badge-src" style="font-size:9px;padding:1px 6px"><?= htmlspecialchars($row['LocaCode']) ?></span>
                <?php if (!empty($row['CreateUser'])): ?> · <?= htmlspecialchars(db_str($row['CreateUser'])) ?><?php endif; ?>
              </div>
            </td>
            <td class="text-end fw-semibold"><?= fmt_number($row['TAmt']) ?></td>
            <td>
              <?php if ($is_overdue): ?>
                <span class="badge-inv badge-overdue"><?= fmt_date($due) ?></span>
              <?php elseif ($is_due_soon): ?>
                <span class="badge-inv badge-due-soon"><?= fmt_date($due) ?></span>
              <?php else: ?>
                <span style="font-size:12px"><?= fmt_date($due) ?></span>
              <?php endif; ?>
            </td>
            <td class="d-none d-lg-table-cell"><span class="badge-inv badge-src"><?= htmlspecialchars($row['LocaCode']) ?></span></td>
            <td class="d-none d-xl-table-cell" style="font-size:11.5px;color:var(--muted)"><?= htmlspecialchars(db_str($row['CreateUser'])) ?></td>
            <td>
              <a href="/po_detail.php?seq=<?= (int)$row['SeqNo'] ?>"
                 class="btn btn-sm" style="border:1px solid var(--accent);color:var(--accent);border-radius:6px"
                 data-loading>
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
        for ($p = $start; $p <= $end; $p++):
            $p_url = '?' . http_build_query(array_merge($base_params, ['page' => $p]));
        ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= htmlspecialchars($p_url) ?>"><?= $p ?></a>
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
  // เก็บ URL ปัจจุบัน (filter + sort + page) ไว้ใช้ตอนกลับจาก PO Detail
  sessionStorage.setItem('po_list_last_url', window.location.href);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
