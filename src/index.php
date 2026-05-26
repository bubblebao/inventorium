<?php
require_once __DIR__ . '/config/db.php';
$page_title = t('dashboard');

$today        = date('Y-m-d');
$first_day    = date('Y-m-01');
$last_day     = date('Y-m-t');
$due_soon_end = date('Y-m-d', strtotime('+7 days'));

// This month PO stats — JOIN invpo1 เพื่อ SUM ยอด
$r = mysqli_query($conn, "
    SELECT COUNT(DISTINCT h.SeqNo) AS cnt, SUM(d.Amount) AS total
    FROM invpo0 h
    LEFT JOIN invpo1 d ON d.SeqNo = h.SeqNo
    WHERE h.PoDate BETWEEN '$first_day' AND '$last_day'
");
$stats_month = ($r ? mysqli_fetch_assoc($r) : null) ?: ['cnt' => 0, 'total' => 0];

// Due soon count (delivery date in next 7 days)
$r2 = mysqli_query($conn, "
    SELECT COUNT(*) AS cnt FROM invpo0
    WHERE DeliveryDate >= '$today' AND DeliveryDate <= '$due_soon_end'
      AND DeliveryDate <> '0000-00-00'
");
$due_cnt = (int)(($r2 ? mysqli_fetch_assoc($r2) : null)['cnt'] ?? 0);

// Vendor total count
$r3 = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM gblvend");
$vnd_cnt = (int)(($r3 ? mysqli_fetch_assoc($r3) : null)['cnt'] ?? 0);

// Due soon list — uses correlated subquery for total
$r_due = mysqli_query($conn, "
    SELECT h.SeqNo, h.PoDate, h.PoNo, h.VndCode, v.VndName, h.DeliveryDate,
           (SELECT SUM(Amount) FROM invpo1 WHERE SeqNo = h.SeqNo) AS TAmt
    FROM invpo0 h LEFT JOIN gblvend v ON h.VndCode = v.VndCode
    WHERE h.DeliveryDate >= '$today' AND h.DeliveryDate <= '$due_soon_end'
      AND h.DeliveryDate <> '0000-00-00'
    ORDER BY h.DeliveryDate ASC
    LIMIT 8
");

// Top 5 vendors this month
$r_top = mysqli_query($conn, "
    SELECT h.VndCode, v.VndName,
           COUNT(DISTINCT h.SeqNo) AS cnt, SUM(d.Amount) AS total
    FROM invpo0 h
    LEFT JOIN gblvend v ON h.VndCode = v.VndCode
    LEFT JOIN invpo1 d ON d.SeqNo = h.SeqNo
    WHERE h.PoDate BETWEEN '$first_day' AND '$last_day'
    GROUP BY h.VndCode
    ORDER BY total DESC
    LIMIT 5
");

// Recent 10 POs — uses correlated subquery for total
$r_recent = mysqli_query($conn, "
    SELECT h.SeqNo, h.PoDate, h.PoNo, h.VndCode, v.VndName,
           h.LocaCode, h.DeliveryDate,
           (SELECT SUM(Amount) FROM invpo1 WHERE SeqNo = h.SeqNo) AS TAmt
    FROM invpo0 h LEFT JOIN gblvend v ON h.VndCode = v.VndCode
    ORDER BY h.PoDate DESC, h.SeqNo DESC
    LIMIT 10
");

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Stat Cards ── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card" style="--card-color:var(--accent)">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <div class="stat-label"><?= t('po_this_month') ?></div>
            <div class="stat-val"><?= number_format((int)$stats_month['cnt']) ?></div>
            <div class="stat-label mt-1"><?= t('records') ?></div>
          </div>
          <i class="bi bi-file-text stat-icon"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card" style="--card-color:var(--success)">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <div class="stat-label"><?= t('total_this_month') ?></div>
            <div class="stat-val" style="font-size:22px"><?= fmt_number($stats_month['total'] ?? 0) ?></div>
            <div class="stat-label mt-1"><?= t('baht') ?></div>
          </div>
          <i class="bi bi-currency-exchange stat-icon"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card" style="--card-color:var(--warning)">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <div class="stat-label"><?= t('due_in_7_days') ?></div>
            <div class="stat-val" style="color:<?= $due_cnt > 0 ? 'var(--warning)' : 'var(--primary)' ?>">
              <?= number_format($due_cnt) ?>
            </div>
            <div class="stat-label mt-1"><?= t('records') ?></div>
          </div>
          <i class="bi bi-clock-history stat-icon"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card" style="--card-color:var(--muted)">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <div class="stat-label"><?= t('total_vendors') ?></div>
            <div class="stat-val"><?= number_format($vnd_cnt) ?></div>
            <div class="stat-label mt-1"><?= $GLOBALS['LANG'] === 'th' ? 'ราย' : 'vendors' ?></div>
          </div>
          <i class="bi bi-building stat-icon"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Chart + Due Soon ── -->
<div class="row g-3 mb-4">
  <div class="col-xl-8">
    <div class="card h-100">
      <div class="card-header-inv d-flex align-items-center justify-content-between">
        <span><i class="bi bi-bar-chart-line me-1"></i> <?= t('monthly_po_chart') ?></span>
        <span class="badge" style="background:rgba(255,255,255,.15);font-weight:500;font-size:11px" id="chartToggle">
          <i class="bi bi-arrow-repeat"></i> <?= t('count_vs_total') ?>
        </span>
      </div>
      <div class="card-body" style="padding:16px">
        <canvas id="monthlyChart" height="90"></canvas>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header-inv">
        <i class="bi bi-alarm me-1"></i> <?= t('due_in_7') ?>
        <?php if ($due_cnt > 0): ?>
          <span class="badge ms-1" style="background:var(--warning);color:#fff"><?= $due_cnt ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0" style="overflow-y:auto;max-height:280px">
        <?php if (!$r_due || mysqli_num_rows($r_due) === 0): ?>
          <div class="text-center text-muted py-4" style="font-size:13px">
            <i class="bi bi-check-circle" style="color:var(--success);font-size:24px"></i><br>
            <?= t('no_due_po') ?>
          </div>
        <?php else: ?>
          <table class="table table-sm mb-0" style="font-size:12.5px">
            <tbody>
            <?php while ($d = mysqli_fetch_assoc($r_due)): ?>
              <?php
                $days_left = (int)round((strtotime($d['DeliveryDate']) - strtotime($today)) / 86400);
              ?>
              <tr onclick="location.href='/po_detail.php?seq=<?= (int)$d['SeqNo'] ?>'" style="cursor:pointer">
                <td class="ps-3">
                  <div class="fw-semibold"><?= htmlspecialchars($d['PoNo']) ?></div>
                  <div class="text-muted" style="font-size:11px"><?= htmlspecialchars(db_str($d['VndName']) ?: $d['VndCode']) ?></div>
                </td>
                <td class="text-end pe-3">
                  <div class="fw-semibold text-danger"><?= fmt_number($d['TAmt']) ?></div>
                  <span class="badge-inv badge-due-soon" style="font-size:10px">
                    <?= $days_left === 0 ? t('today_word') : ($days_left . ' ' . t('days')) ?>
                  </span>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
      <?php if ($due_cnt > 0): ?>
      <div class="card-footer p-0" style="border-top:1px solid var(--border)">
        <a href="/po_list.php?date_from=<?= $today ?>&date_to=<?= $due_soon_end ?>"
           class="d-block text-center py-2" style="font-size:12px;color:var(--accent);text-decoration:none">
          <?= t('view_all') ?> <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Top Vendors + Recent POs ── -->
<div class="row g-3">
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header-inv">
        <i class="bi bi-trophy me-1"></i> <?= t('top_5_vendors') ?>
      </div>
      <div class="card-body p-0">
        <?php if (!$r_top || mysqli_num_rows($r_top) === 0): ?>
          <div class="text-center text-muted py-4"><?= t('no_data') ?></div>
        <?php else: ?>
        <table class="table-inv table mb-0">
          <thead>
            <tr>
              <th style="width:28px">#</th>
              <th><?= t('col_vendor') ?></th>
              <th class="text-end">PO</th>
              <th class="text-end"><?= t('col_total') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php $rank = 1; while ($v = mysqli_fetch_assoc($r_top)): ?>
            <tr onclick="location.href='/vendor_detail.php?vn_code=<?= urlencode($v['VndCode']) ?>'" style="cursor:pointer">
              <td class="text-center">
                <?php if ($rank === 1): ?><i class="bi bi-trophy-fill" style="color:#f59e0b"></i>
                <?php elseif ($rank === 2): ?><i class="bi bi-trophy-fill" style="color:#94a3b8"></i>
                <?php elseif ($rank === 3): ?><i class="bi bi-trophy-fill" style="color:#b45309"></i>
                <?php else: ?><?= $rank ?>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold" style="font-size:12.5px"><?= htmlspecialchars(db_str($v['VndName']) ?: $v['VndCode']) ?></div>
                <div style="font-size:10.5px;color:var(--muted)"><?= htmlspecialchars($v['VndCode']) ?></div>
              </td>
              <td class="text-end"><?= number_format((int)$v['cnt']) ?></td>
              <td class="text-end fw-semibold"><?= fmt_number($v['total']) ?></td>
            </tr>
          <?php $rank++; endwhile; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card">
      <div class="card-header-inv d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clock-history me-1"></i> <?= t('recent_10_pos') ?></span>
        <a href="/po_list.php" class="btn btn-sm"
           style="background:rgba(255,255,255,.15);color:#fff;font-size:11px;padding:3px 10px" data-loading>
          <?= t('view_all') ?> <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table-inv table mb-0">
            <thead>
              <tr>
                <th><?= t('col_date') ?></th>
                <th><?= t('col_po_no') ?></th>
                <th><?= t('col_vendor') ?></th>
                <th class="text-end"><?= t('col_total') ?></th>
                <th><?= t('col_due_date') ?></th>
                <th><?= t('col_loc') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$r_recent || mysqli_num_rows($r_recent) === 0): ?>
              <tr><td colspan="6" class="text-center text-muted py-4"><?= t('no_data') ?></td></tr>
            <?php else: ?>
              <?php while ($po = mysqli_fetch_assoc($r_recent)): ?>
              <?php
                $is_overdue = $po['DeliveryDate'] && $po['DeliveryDate'] !== '0000-00-00' && $po['DeliveryDate'] < $today;
                $is_due_soon = !$is_overdue && $po['DeliveryDate'] && $po['DeliveryDate'] !== '0000-00-00' && $po['DeliveryDate'] <= $due_soon_end;
              ?>
              <tr onclick="location.href='/po_detail.php?seq=<?= (int)$po['SeqNo'] ?>'" style="cursor:pointer">
                <td><?= fmt_date($po['PoDate']) ?></td>
                <td><code style="font-size:11px"><?= htmlspecialchars($po['PoNo']) ?></code></td>
                <td>
                  <div style="font-size:12.5px"><?= htmlspecialchars(db_str($po['VndName']) ?: $po['VndCode']) ?></div>
                </td>
                <td class="text-end fw-semibold"><?= fmt_number($po['TAmt']) ?></td>
                <td>
                  <?php if ($is_overdue): ?>
                    <span class="badge-inv badge-overdue"><?= fmt_date($po['DeliveryDate']) ?></span>
                  <?php elseif ($is_due_soon): ?>
                    <span class="badge-inv badge-due-soon"><?= fmt_date($po['DeliveryDate']) ?></span>
                  <?php else: ?>
                    <span style="font-size:12px"><?= fmt_date($po['DeliveryDate']) ?></span>
                  <?php endif; ?>
                </td>
                <td><span class="badge-inv badge-src"><?= htmlspecialchars($po['LocaCode']) ?></span></td>
              </tr>
              <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
    var showTotal = true;
    var chartInstance = null;
    var LANG = <?= json_encode($GLOBALS['LANG']) ?>;
    var L = {
      total:  LANG === 'th' ? 'ยอดรวม (บาท)' : 'Total (THB)',
      count:  LANG === 'th' ? 'จำนวน PO'    : 'PO Count',
      records:LANG === 'th' ? 'รายการ'       : 'records',
      failed: LANG === 'th' ? 'โหลดกราฟไม่สำเร็จ' : 'Failed to load chart'
    };

    fetch('/chart-data.php?type=monthly')
        .then(function(r) { return r.json(); })
        .then(function(rows) {
            var labels = rows.map(function(r) {
                var parts = r.month.split('-');
                return parts[1] + '/' + parts[0].slice(2);
            });
            var cntData   = rows.map(function(r) { return r.cnt; });
            var totalData = rows.map(function(r) { return r.total; });

            var ctx = document.getElementById('monthlyChart').getContext('2d');
            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: L.total,
                        data: totalData,
                        backgroundColor: 'rgba(14,165,233,0.7)',
                        borderColor: 'rgba(14,165,233,1)',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var v = ctx.raw;
                                    return showTotal
                                        ? ' ฿' + v.toLocaleString('th-TH', {minimumFractionDigits:2})
                                        : ' ' + v + ' ' + L.records;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(v) {
                                    return showTotal
                                        ? (v >= 1000000 ? (v/1000000).toFixed(1)+'M' : (v >= 1000 ? (v/1000).toFixed(0)+'K' : v))
                                        : v;
                                }
                            }
                        }
                    }
                }
            });

            document.getElementById('chartToggle').style.cursor = 'pointer';
            document.getElementById('chartToggle').addEventListener('click', function() {
                showTotal = !showTotal;
                chartInstance.data.datasets[0].label = showTotal ? L.total : L.count;
                chartInstance.data.datasets[0].data  = showTotal ? totalData : cntData;
                chartInstance.data.datasets[0].backgroundColor = showTotal
                    ? 'rgba(14,165,233,0.7)' : 'rgba(16,185,129,0.7)';
                chartInstance.data.datasets[0].borderColor = showTotal
                    ? 'rgba(14,165,233,1)' : 'rgba(16,185,129,1)';
                chartInstance.update();
            });
        })
        .catch(function() {
            document.getElementById('monthlyChart').insertAdjacentHTML(
                'afterend', '<p class="text-muted text-center" style="font-size:12px">' + L.failed + '</p>'
            );
        });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
