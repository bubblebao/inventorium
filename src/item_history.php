<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/recv_filter.php';

$prd_id = trim($_GET['prd_id'] ?? '');
$search = trim($_GET['search'] ?? '');

// ── Detail mode: ?prd_id=XXX ──
if ($prd_id !== '') {
    $prd_id_safe = db_escape($prd_id);

    // Item master — เอาทุก field ที่มี (SELECT *)
    $r_item = mysqli_query($conn, "SELECT * FROM gblprod WHERE PrdId = '$prd_id_safe' LIMIT 1");
    $prod = $r_item ? mysqli_fetch_assoc($r_item) : null;

    // Fallback: ถ้าไม่มีใน gblprod ลองหาจาก invpo1 (มี PO history)
    if (!$prod) {
        $r_check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM invpo1 WHERE PrdID = '$prd_id_safe'");
        $row = $r_check ? mysqli_fetch_assoc($r_check) : null;
        if (!$row || (int)$row['c'] === 0) {
            header('Location: /item_history.php');
            exit;
        }
        // ไม่มี master record แต่มี PO history — สร้าง placeholder
        $prod = ['PrdId' => $prd_id];
    }

    // Pull fallback data from invpo1 — get first non-empty for each field
    $latest_remark = ''; $latest_unit = ''; $latest_vendor = ''; $latest_po_date = '';
    $latest_price = 0.0; $latest_vendor_code = '';

    // Latest PO context (unit / price / date / vendor) — most recent row
    $r_fb = mysqli_query($conn, "
        SELECT d.Unit, d.Price, h.PoDate, h.VndCode, v.VndName
        FROM invpo1 d
        INNER JOIN invpo0 h ON h.SeqNo = d.SeqNo
        LEFT JOIN gblvend v ON v.VndCode = h.VndCode
        WHERE d.PrdID = '$prd_id_safe'
        ORDER BY h.PoDate DESC, h.SeqNo DESC
        LIMIT 20
    ");
    if ($r_fb) {
        while ($fb = mysqli_fetch_assoc($r_fb)) {
            if ($latest_unit   === '' && trim($fb['Unit']   ?? '') !== '') $latest_unit   = $fb['Unit'];
            if ($latest_po_date === '' && !empty($fb['PoDate']) && $fb['PoDate'] !== '0000-00-00') {
                $latest_po_date = $fb['PoDate'];
                $latest_price   = (float)($fb['Price'] ?? 0);
                $latest_vendor_code = $fb['VndCode'] ?? '';
            }
            if ($latest_vendor === '' && trim($fb['VndName'] ?? '') !== '') $latest_vendor = db_str($fb['VndName']);
            if ($latest_unit && $latest_po_date && $latest_vendor) break;
        }
    }

    // Description fallback — search ALL PO history, prefer most recent row with non-empty Remark
    $r_remark = mysqli_query($conn, "
        SELECT d.Remark, p.PrdDescT, p.PrdDescE
        FROM invpo1 d
        LEFT JOIN gblprod p ON p.PrdId = d.PrdID
        INNER JOIN invpo0 h ON h.SeqNo = d.SeqNo
        WHERE d.PrdID = '$prd_id_safe'
        ORDER BY
            IF(d.Remark IS NULL OR TRIM(d.Remark) = '' OR d.Remark = 'NULL', 1, 0) ASC,
            h.PoDate DESC, h.SeqNo DESC
        LIMIT 1
    ");
    if ($r_remark && ($rm = mysqli_fetch_assoc($r_remark))) {
        $remark_raw = trim($rm['Remark'] ?? '');
        if ($remark_raw !== '' && strcasecmp($remark_raw, 'NULL') !== 0) {
            $latest_remark = db_str($remark_raw);
        }
        // Also patch item master if gblprod returned empty but JOIN found values
        if ((empty($prod['PrdDescE']) || strcasecmp(trim($prod['PrdDescE'] ?? ''), 'NULL') === 0)
            && !empty($rm['PrdDescE'])) {
            $prod['PrdDescE'] = $rm['PrdDescE'];
        }
        if ((empty($prod['PrdDescT']) || strcasecmp(trim($prod['PrdDescT'] ?? ''), 'NULL') === 0)
            && !empty($rm['PrdDescT'])) {
            $prod['PrdDescT'] = $rm['PrdDescT'];
        }
    }

    // Top vendor by purchase count (เป็น "default vendor" fallback)
    $r_top1 = mysqli_query($conn, "
        SELECT h.VndCode, v.VndName, COUNT(*) AS cnt
        FROM invpo1 d
        INNER JOIN invpo0 h ON h.SeqNo = d.SeqNo
        LEFT JOIN gblvend v ON v.VndCode = h.VndCode
        WHERE d.PrdID = '$prd_id_safe'
        GROUP BY h.VndCode
        ORDER BY cnt DESC LIMIT 1
    ");
    $top1 = $r_top1 ? mysqli_fetch_assoc($r_top1) : null;
    $top_vendor_code = $top1['VndCode'] ?? '';
    $top_vendor_name = $top1 ? db_str($top1['VndName'] ?? '') : '';
    $top_vendor_cnt  = (int)($top1['cnt'] ?? 0);

    // Helper: clean & fallback
    $val = function($v, $fallback = '—') {
        $v = trim((string)$v);
        if ($v === '' || strcasecmp($v, 'NULL') === 0 || $v === '0' || $v === '0.00') return $fallback;
        return $v;
    };

    $page_title = ($GLOBALS['LANG']==='th'?'สินค้า: ':'Item: ') . htmlspecialchars($prod['PrdId'] ?? $prd_id);

    // Stats
    $r_stat = mysqli_query($conn, "
        SELECT COUNT(*) AS times,
               COUNT(DISTINCT h.VndCode) AS vendors,
               MIN(d.Price) AS min_price,
               AVG(d.Price) AS avg_price,
               MAX(d.Price) AS max_price,
               SUM(d.Qty)   AS total_qty,
               SUM(d.Amount) AS total_amt
        FROM invpo1 d
        INNER JOIN invpo0 h ON h.SeqNo = d.SeqNo
        WHERE d.PrdID = '$prd_id_safe'
    ");
    $stat = ($r_stat ? mysqli_fetch_assoc($r_stat) : null) ?:
            ['times'=>0,'vendors'=>0,'min_price'=>0,'avg_price'=>0,'max_price'=>0,'total_qty'=>0,'total_amt'=>0];

    // Top 5 vendors for this item
    $r_top = mysqli_query($conn, "
        SELECT h.VndCode, v.VndName,
               COUNT(*) AS times,
               SUM(d.Qty) AS qty,
               AVG(d.Price) AS avg_price,
               MIN(d.Price) AS min_price,
               MAX(d.Price) AS max_price
        FROM invpo1 d
        INNER JOIN invpo0 h ON h.SeqNo = d.SeqNo
        LEFT JOIN gblvend v ON h.VndCode = v.VndCode
        WHERE d.PrdID = '$prd_id_safe'
        GROUP BY h.VndCode
        ORDER BY times DESC
        LIMIT 5
    ");

    // PO history (latest 200)
    $r_hist = mysqli_query($conn, "
        SELECT h.SeqNo, h.PoNo, h.PoDate, h.VndCode, v.VndName,
               d.Qty, d.Unit, d.Price, d.Amount, d.NetAmount, d.Discount
        FROM invpo1 d
        INNER JOIN invpo0 h ON h.SeqNo = d.SeqNo
        LEFT JOIN gblvend v ON h.VndCode = v.VndCode
        WHERE d.PrdID = '$prd_id_safe'
        ORDER BY h.PoDate DESC, h.SeqNo DESC
        LIMIT 200
    ");

    require_once __DIR__ . '/includes/header.php';
    ?>

    <div class="mb-3 d-flex justify-content-between align-items-center no-print">
      <a href="/item_history.php" id="backToSearch" class="btn btn-sm btn-inv-outline" data-loading>
        <i class="bi bi-arrow-left"></i> <span id="backLabel"><?= t('back_to_search') ?></span>
      </a>
    </div>
    <script>
      (function() {
        var btn  = document.getElementById('backToSearch');
        var lbl  = document.getElementById('backLabel');
        // Priority: came from po_detail → item_detail_back_url
        var fromPo   = sessionStorage.getItem('item_detail_back_url');
        var fromSearch = sessionStorage.getItem('item_last_search');

        if (fromPo && fromPo !== window.location.href) {
          btn.href = fromPo;
          if (lbl) lbl.textContent = ' Back to PO';
          btn.querySelector('i').className = 'bi bi-file-text';
          sessionStorage.removeItem('item_detail_back_url');
        } else if (fromSearch && fromSearch !== window.location.href) {
          btn.href = fromSearch;
        }
      })();
    </script>

    <!-- Item Info Card -->
    <div class="card mb-3">
      <div class="card-header-inv">
        <i class="bi bi-box-seam me-1"></i>
        <code style="color:#fff;background:rgba(255,255,255,.15);padding:2px 8px;border-radius:4px"><?= htmlspecialchars($prod['PrdId'] ?? $prd_id) ?></code>
        <span class="ms-2"><?= htmlspecialchars(db_str(($prod['PrdDescT'] ?? '') ?: ($prod['PrdDescE'] ?? ''))) ?></span>
        <?php if ((int)($prod['Active'] ?? 1) === 0): ?>
          <span class="badge ms-1" style="background:var(--danger)">Inactive</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <table class="table table-sm table-borderless mb-0" style="font-size:13px">
              <?php
                $eng     = $val(db_str($prod['PrdDescE'] ?? ''), '');
                $thai    = $val(db_str($prod['PrdDescT'] ?? ''), '');
                $barcode = $val($prod['Barcode'] ?? '', '');
                $bunit   = $val($prod['BaseUnit'] ?? '', $latest_unit ?: '—');
                $catCode = trim($prod['CateCode'] ?? '');
                $subCode = trim($prod['SubCatCode'] ?? '');

                // Fallback: parse category from PrdId prefix (e.g. "AS02-0001" → "AS02")
                if ($catCode === '') {
                    if (preg_match('/^([A-Za-z]+\d*)/', (string)($prod['PrdId'] ?? $prd_id), $mm)) {
                        $catCode = $mm[1];
                        $cat_from_code = true;
                    }
                }
                $cat = $catCode . ($subCode ? ' / ' . $subCode : '');
                $cat = $cat !== '' ? $cat : '';

                // Name fallback to latest PO Remark when master empty
                $name_fallback_label = $GLOBALS['LANG']==='th' ? ' (จาก PO)' : ' (from PO)';
              ?>
              <tr><th style="width:140px;color:var(--muted)"><?= t('item_eng') ?></th>
                <td>
                  <?php if ($eng): ?>
                    <?= htmlspecialchars($eng) ?>
                  <?php elseif ($latest_remark): ?>
                    <?= htmlspecialchars($latest_remark) ?><span style="font-size:10px;color:var(--accent);font-style:italic"><?= $name_fallback_label ?></span>
                  <?php else: ?>
                    <span style="color:var(--muted)">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr><th style="color:var(--muted)"><?= t('item_thai') ?></th>
                <td>
                  <?php if ($thai): ?>
                    <?= htmlspecialchars($thai) ?>
                  <?php elseif ($latest_remark): ?>
                    <?= htmlspecialchars($latest_remark) ?><span style="font-size:10px;color:var(--accent);font-style:italic"><?= $name_fallback_label ?></span>
                  <?php else: ?>
                    <span style="color:var(--muted)">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr><th style="color:var(--muted)">Barcode</th><td><?= $barcode ? '<code>' . htmlspecialchars($barcode) . '</code>' : '<span style="color:var(--muted)">—</span>' ?></td></tr>
              <tr><th style="color:var(--muted)"><?= t('base_unit') ?></th><td><?= htmlspecialchars($bunit) ?></td></tr>
              <tr><th style="color:var(--muted)"><?= t('category') ?></th>
                <td>
                  <?php if ($cat !== ''): ?>
                    <?= htmlspecialchars($cat) ?>
                    <?php if (!empty($cat_from_code)): ?><span style="font-size:10px;color:var(--accent);font-style:italic"> (<?= $GLOBALS['LANG']==='th' ? 'จากรหัส' : 'from code' ?>)</span><?php endif; ?>
                  <?php else: ?>
                    <span style="color:var(--muted)">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm table-borderless mb-0" style="font-size:13px">
              <?php
                $defVendor = trim($prod['VndCode'] ?? '');
                $defPrice  = (float)($prod['DefaultPrice'] ?? 0);
                $lastCost  = (float)($prod['LastCost'] ?? 0);
                $onHand    = (float)($prod['OnHand'] ?? 0);
                $lastUpd   = $prod['LastUpdate'] ?? '';
                $avgPrice  = (float)($stat['avg_price'] ?? 0);

                // Fallback chain (จาก master → จาก PO data)
                $showVendor = $defVendor ?: $top_vendor_code;
                $showVendorName = $defVendor ? '' : ($top_vendor_name ?: '');
                $showPrice    = $defPrice > 0 ? $defPrice : $avgPrice;
                $priceLabel   = $defPrice > 0 ? '' : ' (avg from PO)';
                $showCost     = $lastCost > 0 ? $lastCost : $latest_price;
                $costLabel    = $lastCost > 0 ? '' : ' (latest PO)';
                $showUpdate   = ($lastUpd && $lastUpd !== '0000-00-00') ? $lastUpd : $latest_po_date;
                $updateLabel  = ($lastUpd && $lastUpd !== '0000-00-00') ? '' : ' (last PO)';
              ?>
              <tr><th style="width:140px;color:var(--muted)"><?= t('default_vendor') ?></th>
                <td>
                  <?php if ($showVendor): ?>
                    <?= htmlspecialchars($showVendor) ?><?php if ($showVendorName): ?> <span style="color:var(--muted)">— <?= htmlspecialchars($showVendorName) ?></span><?php endif; ?>
                    <?php if (!$defVendor && $top_vendor_cnt): ?><span style="font-size:10px;color:var(--accent);font-style:italic">(top: <?= $top_vendor_cnt ?> PO)</span><?php endif; ?>
                  <?php else: ?>
                    <span style="color:var(--muted)">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr><th style="color:var(--muted)"><?= t('default_price') ?></th>
                <td>
                  <?php if ($showPrice > 0): ?>
                    <?= fmt_number($showPrice) ?> <?= t('baht') ?>
                    <?php if ($priceLabel): ?><span style="font-size:10px;color:var(--accent);font-style:italic"><?= $priceLabel ?></span><?php endif; ?>
                  <?php else: ?>
                    <span style="color:var(--muted)">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr><th style="color:var(--muted)"><?= t('last_cost') ?></th>
                <td>
                  <?php if ($showCost > 0): ?>
                    <strong style="color:var(--accent)"><?= fmt_number($showCost) ?></strong> <?= t('baht') ?>
                    <?php if ($costLabel): ?><span style="font-size:10px;color:var(--muted);font-style:italic"><?= $costLabel ?></span><?php endif; ?>
                  <?php else: ?>
                    <span style="color:var(--muted)">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr><th style="color:var(--muted)"><?= t('on_hand') ?></th>
                <td><?= $onHand != 0 ? fmt_number($onHand) . ' ' . htmlspecialchars($bunit) : '<span style="color:var(--muted)">—</span>' ?></td>
              </tr>
              <tr><th style="color:var(--muted)"><?= t('last_update') ?></th>
                <td>
                  <?php if ($showUpdate): ?>
                    <?= fmt_date($showUpdate) ?>
                    <?php if ($updateLabel): ?><span style="font-size:10px;color:var(--muted);font-style:italic"><?= $updateLabel ?></span><?php endif; ?>
                  <?php else: ?>
                    <span style="color:var(--muted)">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-3">
      <div class="col-md-3 col-sm-6">
        <div class="card stat-card" style="--card-color:var(--accent)">
          <div class="card-body">
            <div class="stat-label"><?= t('total_purchase') ?></div>
            <div class="stat-val"><?= number_format((int)$stat['times']) ?></div>
            <div class="stat-label mt-1"><?= t('times') ?> / <?= number_format((int)$stat['vendors']) ?> vendor</div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card stat-card" style="--card-color:var(--success)">
          <div class="card-body">
            <div class="stat-label"><?= t('total_qty') ?></div>
            <div class="stat-val"><?= number_format((float)$stat['total_qty'], 0) ?></div>
            <div class="stat-label mt-1"><?= htmlspecialchars($prod['BaseUnit'] ?? '') ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card stat-card" style="--card-color:var(--warning)">
          <div class="card-body">
            <div class="stat-label"><?= t('price_min_avg_max') ?></div>
            <div class="stat-val" style="font-size:18px;line-height:1.4">
              <?= fmt_number($stat['min_price']) ?> /
              <span style="color:var(--accent)"><?= fmt_number($stat['avg_price']) ?></span> /
              <?= fmt_number($stat['max_price']) ?>
            </div>
            <div class="stat-label mt-1"><?= t('baht') ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card stat-card" style="--card-color:var(--muted)">
          <div class="card-body">
            <div class="stat-label"><?= t('total_value') ?></div>
            <div class="stat-val" style="font-size:22px"><?= fmt_number($stat['total_amt']) ?></div>
            <div class="stat-label mt-1"><?= t('baht') ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Top Vendors -->
    <?php if ($r_top && mysqli_num_rows($r_top) > 0): ?>
    <div class="card mb-3">
      <div class="card-header-inv"><i class="bi bi-trophy me-1"></i> <?= t('top_vendors_item') ?></div>
      <div class="card-body p-0">
        <table class="table-inv table mb-0">
          <thead>
            <tr>
              <th><?= t('col_vendor') ?></th>
              <th class="text-end"><?= t('total_purchase') ?></th>
              <th class="text-end"><?= t('total_qty') ?></th>
              <th class="text-end">Min</th>
              <th class="text-end">Avg</th>
              <th class="text-end">Max</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($v = mysqli_fetch_assoc($r_top)): ?>
            <tr onclick="location.href='/vendor_detail.php?vn_code=<?= urlencode($v['VndCode']) ?>'" style="cursor:pointer">
              <td>
                <div class="fw-semibold"><?= htmlspecialchars(db_str($v['VndName']) ?: $v['VndCode']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($v['VndCode']) ?></div>
              </td>
              <td class="text-end fw-semibold"><?= number_format((int)$v['times']) ?> <?= t('times') ?></td>
              <td class="text-end"><?= fmt_number($v['qty']) ?></td>
              <td class="text-end" style="color:var(--success)"><?= fmt_number($v['min_price']) ?></td>
              <td class="text-end fw-semibold"><?= fmt_number($v['avg_price']) ?></td>
              <td class="text-end" style="color:var(--danger)"><?= fmt_number($v['max_price']) ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- PO History -->
    <div class="card">
      <div class="card-header-inv d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span>
          <i class="bi bi-clock-history me-1"></i> <?= t('purchase_history') ?>
          <span class="badge ms-1" style="background:rgba(255,255,255,.2)"><?= t('latest_200') ?></span>
        </span>
        <div class="d-flex gap-1">
          <a href="<?= htmlspecialchars('/export.php?' . http_build_query(['type' => 'item_detail', 'format' => 'excel', 'prd_id' => $prd_id])) ?>" class="btn btn-sm"
             style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)" target="_blank">
            <i class="bi bi-file-earmark-excel me-1"></i> Excel
          </a>
          <a href="<?= htmlspecialchars('/export.php?' . http_build_query(['type' => 'item_detail', 'format' => 'pdf', 'prd_id' => $prd_id])) ?>" class="btn btn-sm"
             style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i> PDF
          </a>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table-inv table mb-0">
            <thead>
              <tr>
                <th><?= t('col_date') ?></th>
                <th><?= t('col_po_no') ?></th>
                <th><?= t('col_vendor') ?></th>
                <th class="text-end"><?= t('col_qty') ?></th>
                <th><?= t('col_unit') ?></th>
                <th class="text-end"><?= t('col_price') ?></th>
                <th class="text-end"><?= t('col_discount') ?></th>
                <th class="text-end"><?= t('col_net') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$r_hist || mysqli_num_rows($r_hist) === 0): ?>
              <tr><td colspan="8" class="text-center text-muted py-4"><?= t('no_history') ?></td></tr>
            <?php else: ?>
              <?php
              $avg = (float)$stat['avg_price'];
              while ($h = mysqli_fetch_assoc($r_hist)):
                $p = (float)$h['Price'];
                $price_cls = $avg > 0 ? ($p < $avg * 0.95 ? 'text-success' : ($p > $avg * 1.05 ? 'text-danger' : '')) : '';
              ?>
              <tr onclick="sessionStorage.setItem('po_detail_back_url',window.location.href);location.href='/po_detail.php?seq=<?= (int)$h['SeqNo'] ?>'" style="cursor:pointer">
                <td><?= fmt_date($h['PoDate']) ?></td>
                <td><code style="font-size:11px"><?= htmlspecialchars($h['PoNo']) ?></code></td>
                <td><?= htmlspecialchars(db_str($h['VndName']) ?: $h['VndCode']) ?></td>
                <td class="text-end"><?= fmt_number($h['Qty']) ?></td>
                <td><?= htmlspecialchars($h['Unit']) ?></td>
                <td class="text-end fw-semibold <?= $price_cls ?>"><?= fmt_number($h['Price']) ?></td>
                <td class="text-end" style="color:var(--muted)"><?= fmt_number($h['Discount']) ?></td>
                <td class="text-end fw-semibold"><?= fmt_number($h['Amount']) ?></td>
              </tr>
              <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer" style="background:var(--surface-2);font-size:11px;color:var(--muted)">
        <i class="bi bi-info-circle"></i> <?= t('price_legend') ?>
      </div>
    </div>

    <script>
      if (window.Inv) Inv.trackView('item', <?= json_encode($prod['PrdId'] ?? $prd_id) ?>, <?= json_encode(db_str($prod['PrdDescE'] ?? $prod['PrdDescT'] ?? '') ?: ($prod['PrdId'] ?? $prd_id)) ?>);
    </script>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <?php
    exit;
}

// ── Search/list mode ──
$page_title = t('item_history_title');

// Total active items (สำหรับแสดง stat)
$r_total = mysqli_query($conn, "SELECT COUNT(*) AS c FROM gblprod");
$total_items = (int)(($r_total ? mysqli_fetch_assoc($r_total) : null)['c'] ?? 0);

$LIMIT = 100;
$results = null;
$is_default = false;

// ── Filter — From/To range (Item Code / Category) ──
$prd_from = strtoupper(trim($_GET['prd_from'] ?? ''));
$prd_to   = strtoupper(trim($_GET['prd_to']   ?? ''));
$cat_from = strtoupper(trim($_GET['cat_from'] ?? ''));
$cat_to   = strtoupper(trim($_GET['cat_to']   ?? ''));

$range_where  = recv_range_clause('PrdId',    $prd_from, $prd_to);
$range_where .= recv_range_clause('CateCode', $cat_from, $cat_to);
$active_filters = ($prd_from !== '' ? 1 : 0) + ($prd_to !== '' ? 1 : 0) + ($cat_from !== '' ? 1 : 0) + ($cat_to !== '' ? 1 : 0);

// ── Datalist data (พิมพ์ค้นหาได้ — ไม่ผูกกับผลค้นหาปัจจุบัน) ──
$r_prd_dl = mysqli_query($conn, "SELECT PrdId, PrdDescE FROM gblprod WHERE Active = 1 ORDER BY PrdId");
$r_cat_dl = mysqli_query($conn, "SELECT CateCode, PrdDescE FROM gblprod WHERE CateCode <> '' AND CateCode IS NOT NULL GROUP BY CateCode ORDER BY CateCode");

if ($search === '') {
    // Default: แสดงสินค้าทั้งหมดเรียงตาม PrdId (Active ขึ้นก่อน)
    $is_default = true;
    $results = mysqli_query($conn, "
        SELECT PrdId, Barcode, PrdDescE, PrdDescT, BaseUnit, LastCost, OnHand, Active
        FROM gblprod
        WHERE 1=1 $range_where
        ORDER BY Active DESC, PrdId ASC
        LIMIT $LIMIT
    ");
} else {
    // Smart search: split words → AND match across multiple fields
    // ใช้ db_search() แปลง UTF-8 → TIS-620 อัตโนมัติเมื่อพิมพ์ไทย
    $words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY);
    $conds = [];
    foreach ($words as $w) {
        $e_ascii = db_escape($w);          // สำหรับ field ที่เป็น ASCII (PrdId, Barcode, VndCode)
        $e_tis   = db_search($w);          // สำหรับ field ที่อาจเป็น TIS-620 (PrdDescT, PrdDescE)
        $conds[] = "(PrdId LIKE '%$e_ascii%' OR Barcode LIKE '%$e_ascii%' OR PrdDescE LIKE '%$e_tis%' OR PrdDescT LIKE '%$e_tis%' OR VndCode LIKE '%$e_ascii%')";
    }
    $where = implode(' AND ', $conds) . $range_where;

    // Relevance ranking — exact PrdId > startswith > contains
    $first_word = db_escape($words[0] ?? '');
    $first_word_tis = db_search($words[0] ?? '');
    $results = mysqli_query($conn, "
        SELECT PrdId, Barcode, PrdDescE, PrdDescT, BaseUnit, LastCost, OnHand, Active,
               CASE
                 WHEN PrdId = '$first_word'             THEN 1
                 WHEN PrdId   LIKE '$first_word%'       THEN 2
                 WHEN Barcode = '$first_word'           THEN 3
                 WHEN PrdDescE LIKE '$first_word_tis%'  THEN 4
                 WHEN PrdDescT LIKE '$first_word_tis%'  THEN 5
                 ELSE 9
               END AS rank
        FROM gblprod
        WHERE $where
        ORDER BY rank ASC, Active DESC, PrdId ASC
        LIMIT 200
    ");
}

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
        <label class="form-label small fw-bold mb-1"><i class="bi bi-search me-1 text-muted"></i><?= t('item_search_label') ?></label>
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="<?= t('item_search_ph') ?>" autofocus
               value="<?= htmlspecialchars($search) ?>">
      </div>

      <!-- Item Code range -->
      <div class="col-12 col-md-6 col-xl-4">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-upc-scan me-1 text-muted"></i><?= t('item_code') ?></label>
        <div class="d-flex gap-1">
          <input type="text" name="prd_from" list="dl_prd" class="form-control form-control-sm" placeholder="From" value="<?= htmlspecialchars($prd_from) ?>" style="text-transform:uppercase">
          <span class="align-self-center text-muted small">→</span>
          <input type="text" name="prd_to" list="dl_prd" class="form-control form-control-sm" placeholder="To" value="<?= htmlspecialchars($prd_to) ?>" style="text-transform:uppercase">
        </div>
      </div>

      <!-- Category range -->
      <div class="col-12 col-md-6 col-xl-4">
        <label class="form-label small fw-bold mb-1"><i class="bi bi-tags me-1 text-muted"></i><?= t('category') ?></label>
        <div class="d-flex gap-1">
          <input type="text" name="cat_from" list="dl_cat" class="form-control form-control-sm" placeholder="From" value="<?= htmlspecialchars($cat_from) ?>" style="text-transform:uppercase">
          <span class="align-self-center text-muted small">→</span>
          <input type="text" name="cat_to" list="dl_cat" class="form-control form-control-sm" placeholder="To" value="<?= htmlspecialchars($cat_to) ?>" style="text-transform:uppercase">
        </div>
      </div>

      <div class="col-12 d-flex gap-1">
        <button type="submit" class="btn btn-inv-primary"><i class="bi bi-search"></i> <?= t('search') ?></button>
        <a href="/item_history.php" class="btn btn-inv-outline"><i class="bi bi-x-lg"></i> <?= t('reset') ?></a>
        <span class="small text-muted align-self-center ms-1">💡 เว้น "To" = ค่าเดียว · ใส่ทั้งคู่ = ช่วง</span>
      </div>
    </form>
  </div>
</div>

<!-- Datalists -->
<datalist id="dl_prd"><?php while ($pr = mysqli_fetch_assoc($r_prd_dl)): ?><option value="<?= htmlspecialchars($pr['PrdId']) ?>"><?= htmlspecialchars(db_str($pr['PrdDescE'])) ?></option><?php endwhile; ?></datalist>
<datalist id="dl_cat"><?php while ($c = mysqli_fetch_assoc($r_cat_dl)): ?><option value="<?= htmlspecialchars($c['CateCode']) ?>"><?= htmlspecialchars(db_str($c['PrdDescE'])) ?></option><?php endwhile; ?></datalist>

<?php
$result_count = $results ? mysqli_num_rows($results) : 0;
$showing_max  = $is_default && $result_count >= $LIMIT;
?>
<div class="card">
  <div class="card-header-inv d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span>
      <?php if ($is_default): ?>
        <i class="bi bi-box-seam me-1"></i>
        <?= $GLOBALS['LANG']==='th' ? 'รายการสินค้าทั้งหมด' : 'All Items' ?>
        <span class="badge ms-1" data-inv-count style="background:rgba(255,255,255,.2)">
          <?= number_format($result_count) ?> / <?= number_format($total_items) ?>
        </span>
      <?php else: ?>
        <i class="bi bi-search me-1"></i> <?= t('item_results') ?> "<?= htmlspecialchars($search) ?>"
        <span class="badge ms-1" data-inv-count style="background:rgba(255,255,255,.2)">
          <?= number_format($result_count) ?> <?= t('records') ?>
        </span>
      <?php endif; ?>
    </span>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <?php if ($showing_max): ?>
        <span style="font-size:11px;opacity:.85">
          <i class="bi bi-info-circle"></i>
          <?= $GLOBALS['LANG']==='th'
            ? 'แสดง '.$LIMIT.' แรก — ใช้ช่องค้นหาเพื่อกรองให้แคบลง'
            : 'Showing first '.$LIMIT.' — use search to filter further' ?>
        </span>
      <?php endif; ?>
      <?php
        $item_export_params = array_filter([
            'search' => $search, 'prd_from' => $prd_from, 'prd_to' => $prd_to,
            'cat_from' => $cat_from, 'cat_to' => $cat_to,
        ], fn($v) => $v !== '');
      ?>
      <div class="d-flex gap-1">
        <a href="<?= htmlspecialchars('/export.php?' . http_build_query(array_merge(['type' => 'item_list', 'format' => 'excel'], $item_export_params))) ?>" class="btn btn-sm"
           style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)" target="_blank">
          <i class="bi bi-file-earmark-excel me-1"></i> Excel
        </a>
        <a href="<?= htmlspecialchars('/export.php?' . http_build_query(array_merge(['type' => 'item_list', 'format' => 'pdf'], $item_export_params))) ?>" class="btn btn-sm"
           style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)" target="_blank">
          <i class="bi bi-file-earmark-pdf me-1"></i> PDF
        </a>
      </div>
    </div>
  </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table-inv table mb-0" data-inv-table>
          <thead>
            <tr>
              <th data-sort="text"><?= t('item_code') ?></th>
              <th data-sort="text">Barcode</th>
              <th data-sort="text">Name (EN)</th>
              <th data-sort="text">Name (TH)</th>
              <th data-sort="text"><?= t('col_unit') ?></th>
              <th data-sort="num" class="text-end"><?= t('last_cost') ?></th>
              <th data-sort="num" class="text-end"><?= t('on_hand') ?></th>
              <th data-nosort></th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$results || mysqli_num_rows($results) === 0): ?>
            <tr class="inv-no-filter"><td colspan="8" class="text-center text-muted py-4"><?= t('no_items') ?></td></tr>
          <?php else: ?>
            <?php while ($p = mysqli_fetch_assoc($results)):
              $name_en = db_str($p['PrdDescE'] ?? '');
              $name_th = db_str($p['PrdDescT'] ?? '');
              $is_inactive = (int)$p['Active'] === 0;
            ?>
            <tr onclick="location.href='/item_history.php?prd_id=<?= urlencode($p['PrdId']) ?>'"
                style="cursor:pointer;<?= $is_inactive ? 'opacity:.5' : '' ?>">
              <td><code style="font-size:11.5px"><?= htmlspecialchars($p['PrdId']) ?></code></td>
              <td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($p['Barcode']) ?></td>
              <td><?= $name_en !== '' ? htmlspecialchars($name_en) : '<span style="color:var(--muted)">—</span>' ?></td>
              <td>
                <?= $name_th !== '' ? htmlspecialchars($name_th) : '<span style="color:var(--muted)">—</span>' ?>
                <?php if ($is_inactive): ?><span class="badge ms-1" style="background:var(--danger);font-size:9px">Inactive</span><?php endif; ?>
              </td>
              <td><?= htmlspecialchars($p['BaseUnit']) ?></td>
              <td class="text-end"><?= fmt_number($p['LastCost']) ?></td>
              <td class="text-end"><?= fmt_number($p['OnHand']) ?></td>
              <td>
                <a href="/item_history.php?prd_id=<?= urlencode($p['PrdId']) ?>"
                   class="btn btn-sm" style="border:1px solid var(--accent);color:var(--accent);border-radius:6px"
                   data-loading>
                  <i class="bi bi-graph-up"></i> <?= t('view_history') ?>
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php if ($search !== ''): ?>
<script>
  // เก็บ URL ค้นหาไว้ใน sessionStorage — ใช้เป็น back URL ตอนเข้าดู item detail
  sessionStorage.setItem('item_last_search', window.location.href);
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
