<?php
require_once __DIR__ . '/config/db.php';
$page_title = t('dashboard');

// ─── Historical Stats (cached 24h — data frozen) ──────────────────────────

// 1. Latest PO date (anchor สำหรับทุก period query)
$latest_date = cache_remember('latest_po_date', 86400, function() use ($conn) {
    $r = mysqli_query($conn, "
        SELECT MAX(PoDate) AS d FROM invpo0
        WHERE PoDate IS NOT NULL AND PoDate <> '0000-00-00'
    ");
    return ($r ? mysqli_fetch_assoc($r) : null)['d'] ?? date('Y-m-d');
});

// คำนวณช่วง 12 เดือนล่าสุดจาก latest_date
$period_end   = $latest_date;
$period_start = date('Y-m-d', strtotime($latest_date . ' -12 months'));

// 2. Total POs (all time)
$total_pos = cache_remember('total_pos_all', 86400, function() use ($conn) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM invpo0");
    return (int)(($r ? mysqli_fetch_assoc($r) : null)['c'] ?? 0);
});

// 3. Total Spending (all time) — heavy! cache 24h
$total_spending = cache_remember('total_spending_all', 86400, function() use ($conn) {
    $r = mysqli_query($conn, "SELECT SUM(Amount) AS s FROM invpo1");
    return (float)(($r ? mysqli_fetch_assoc($r) : null)['s'] ?? 0);
});

// 4. Active Vendors (มี PO ในช่วง 12 เดือนล่าสุดจาก latest)
$active_vendors = cache_remember("active_vendors_{$period_start}", 86400, function() use ($conn, $period_start, $period_end) {
    $r = mysqli_query($conn, "
        SELECT COUNT(DISTINCT VndCode) AS c FROM invpo0
        WHERE PoDate BETWEEN '$period_start' AND '$period_end'
    ");
    return (int)(($r ? mysqli_fetch_assoc($r) : null)['c'] ?? 0);
});

// Total vendors master
$total_vendors = cache_remember('total_vendors', 86400, function() use ($conn) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM gblvend");
    return (int)(($r ? mysqli_fetch_assoc($r) : null)['c'] ?? 0);
});

// 5. Top 5 Locations (last 12 months from latest)
$top_locations = cache_remember("top_loc_{$period_start}", 3600, function() use ($conn, $period_start, $period_end) {
    $r = mysqli_query($conn, "
        SELECT h.LocaCode,
               COUNT(DISTINCT h.SeqNo) AS po_cnt,
               SUM(d.Amount)          AS total
        FROM invpo0 h
        LEFT JOIN invpo1 d ON d.SeqNo = h.SeqNo
        WHERE h.PoDate BETWEEN '$period_start' AND '$period_end'
          AND h.LocaCode IS NOT NULL AND h.LocaCode <> ''
        GROUP BY h.LocaCode
        ORDER BY total DESC
        LIMIT 5
    ");
    return $r ? mysqli_fetch_all($r, MYSQLI_ASSOC) : [];
});

// 6. Top 5 vendors (last 12 months from latest)
$top_vendors = cache_remember("top_vendors_{$period_start}", 3600, function() use ($conn, $period_start, $period_end) {
    $r = mysqli_query($conn, "
        SELECT h.VndCode, v.VndName,
               COUNT(DISTINCT h.SeqNo) AS cnt, SUM(d.Amount) AS total
        FROM invpo0 h
        LEFT JOIN gblvend v ON h.VndCode = v.VndCode
        LEFT JOIN invpo1 d ON d.SeqNo = h.SeqNo
        WHERE h.PoDate BETWEEN '$period_start' AND '$period_end'
        GROUP BY h.VndCode
        ORDER BY total DESC
        LIMIT 5
    ");
    return $r ? mysqli_fetch_all($r, MYSQLI_ASSOC) : [];
});

// 7. Recent 10 POs (always latest)
$r_recent = mysqli_query($conn, "
    SELECT h.SeqNo, h.PoDate, h.PoNo, h.VndCode, v.VndName,
           h.LocaCode, h.DeliveryDate,
           (SELECT SUM(Amount) FROM invpo1 WHERE SeqNo = h.SeqNo) AS TAmt
    FROM invpo0 h LEFT JOIN gblvend v ON h.VndCode = v.VndCode
    ORDER BY h.PoDate DESC, h.SeqNo DESC
    LIMIT 10
");

// ─── Helpers ──────────────────────────────────────────────────────────────

// Format large number → "1.5B" / "234M" / "12K"
function fmt_compact(float $n): string {
    if ($n >= 1e9) return number_format($n / 1e9, 2) . 'B';
    if ($n >= 1e6) return number_format($n / 1e6, 2) . 'M';
    if ($n >= 1e3) return number_format($n / 1e3, 1) . 'K';
    return number_format($n, 0);
}

// "3 months ago" หรือ "12 days ago" หรือ "recent"
function relative_time(string $date, string $now_date): string {
    $now    = strtotime($now_date);
    $then   = strtotime($date);
    $diff_d = (int)round(($now - $then) / 86400);
    if ($diff_d < 7)  return t('just_now');
    if ($diff_d < 60) return $diff_d . ' ' . t('days_ago');
    $months = (int)round($diff_d / 30);
    return $months . ' ' . t('months_ago');
}

$today = date('Y-m-d');
$latest_relative = relative_time($latest_date, $today);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Data Freeze Banner ── -->
<div class="alert mb-3" style="background:linear-gradient(90deg,#fef3c7 0%,#fffbeb 100%);border:1px solid #fcd34d;color:#92400e;border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:10px;font-size:13px">
  <i class="bi bi-info-circle-fill" style="font-size:16px"></i>
  <div>
    <strong><?= t('data_range') ?>:</strong> <?= fmt_date($latest_date) ?>
    <span style="opacity:.7;margin-left:8px">(<?= $latest_relative ?>)</span>
    <span style="margin-left:12px;font-size:11.5px;opacity:.8"><?= t('data_freeze_note') ?></span>
  </div>
</div>

<!-- ── Stat Cards (Historical) ── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card" style="--card-color:var(--accent)">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <div class="stat-label"><?= t('total_pos') ?></div>
            <div class="stat-val"><?= number_format($total_pos) ?></div>
            <div class="stat-label mt-1"><?= t('all_time') ?></div>
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
            <div class="stat-label"><?= t('total_spending') ?></div>
            <div class="stat-val" style="font-size:24px">฿<?= fmt_compact($total_spending) ?></div>
            <div class="stat-label mt-1"><?= t('all_time') ?> · <?= number_format($total_spending, 0) ?> <?= t('baht') ?></div>
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
            <div class="stat-label"><?= t('latest_po') ?></div>
            <div class="stat-val" style="font-size:22px"><?= fmt_date($latest_date) ?></div>
            <div class="stat-label mt-1"><?= $latest_relative ?></div>
          </div>
          <i class="bi bi-clock-history stat-icon"></i>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card" style="--card-color:var(--info)">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <div class="stat-label"><?= t('active_vendors') ?></div>
            <div class="stat-val"><?= number_format($active_vendors) ?></div>
            <div class="stat-label mt-1">/ <?= number_format($total_vendors) ?> <?= t('all_time') ?> · <?= t('last_12_months') ?></div>
          </div>
          <i class="bi bi-building stat-icon"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Chart + Top Locations ── -->
<div class="row g-3 mb-4">
  <div class="col-xl-8">
    <div class="card h-100">
      <div class="card-header-inv d-flex align-items-center justify-content-between">
        <span><i class="bi bi-bar-chart-line me-1"></i> <?= t('monthly_chart_12m') ?></span>
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
        <i class="bi bi-geo-alt me-1"></i> <?= t('top_5_locations') ?>
      </div>
      <div class="card-body p-0">
        <?php if (empty($top_locations)): ?>
          <div class="text-center text-muted py-4"><?= t('no_data') ?></div>
        <?php else:
          $loc_max = max(array_column($top_locations, 'total')) ?: 1;
        ?>
          <div class="p-3">
          <?php foreach ($top_locations as $i => $loc):
            $pct = $loc_max > 0 ? ($loc['total'] / $loc_max * 100) : 0;
            $colors = ['var(--accent)','var(--success)','var(--warning)','var(--info)','var(--primary-lt)'];
            $color  = $colors[$i % count($colors)];
          ?>
            <div class="mb-3" style="cursor:pointer" onclick="location.href='/po_list.php?date_from=<?= $period_start ?>&date_to=<?= $period_end ?>&source=<?= urlencode($loc['LocaCode']) ?>'">
              <div class="d-flex justify-content-between mb-1" style="font-size:12.5px">
                <span class="fw-semibold"><?= htmlspecialchars($loc['LocaCode']) ?></span>
                <span class="text-muted">฿<?= fmt_compact((float)$loc['total']) ?></span>
              </div>
              <div style="height:6px;background:var(--surface-2);border-radius:3px;overflow:hidden">
                <div style="height:100%;width:<?= round($pct, 1) ?>%;background:<?= $color ?>;border-radius:3px;transition:width .5s"></div>
              </div>
              <div style="font-size:10.5px;color:var(--muted);margin-top:3px"><?= number_format((int)$loc['po_cnt']) ?> PO</div>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php if (!empty($top_locations)): ?>
      <div class="card-footer p-0" style="border-top:1px solid var(--border)">
        <a href="/dept_report.php?date_from=<?= $period_start ?>&date_to=<?= $period_end ?>"
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
        <i class="bi bi-trophy me-1"></i> <?= t('top_5_vendors_12m') ?>
      </div>
      <div class="card-body p-0">
        <?php if (empty($top_vendors)): ?>
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
          <?php $rank = 1; foreach ($top_vendors as $v): ?>
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
              <td class="text-end fw-semibold">฿<?= fmt_compact((float)$v['total']) ?></td>
            </tr>
          <?php $rank++; endforeach; ?>
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
                <th><?= t('col_loc') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$r_recent || mysqli_num_rows($r_recent) === 0): ?>
              <tr><td colspan="5" class="text-center text-muted py-4"><?= t('no_data') ?></td></tr>
            <?php else: ?>
              <?php while ($po = mysqli_fetch_assoc($r_recent)): ?>
              <tr onclick="location.href='/po_detail.php?seq=<?= (int)$po['SeqNo'] ?>'" style="cursor:pointer">
                <td><?= fmt_date($po['PoDate']) ?></td>
                <td><code style="font-size:11px"><?= htmlspecialchars($po['PoNo']) ?></code></td>
                <td><div style="font-size:12.5px"><?= htmlspecialchars(db_str($po['VndName']) ?: $po['VndCode']) ?></div></td>
                <td class="text-end fw-semibold">฿<?= fmt_compact((float)$po['TAmt']) ?></td>
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

<!-- ── Yearly Spending Chart ── -->
<div class="row g-3 mt-1 mb-2">
  <div class="col-12">
    <div class="card">
      <div class="card-header-inv d-flex align-items-center justify-content-between">
        <span><i class="bi bi-graph-up-arrow me-1"></i> <?= t('yearly_chart') ?></span>
        <span class="d-flex align-items-center gap-2">
          <span style="font-size:11px;opacity:.75"><i class="bi bi-hand-index-thumb me-1"></i><?= t('click_to_drill') ?></span>
          <span class="badge" style="background:rgba(255,255,255,.15);cursor:pointer;font-size:11px" id="yearlyToggle">
            <i class="bi bi-arrow-repeat"></i> <?= t('count_vs_total') ?>
          </span>
        </span>
      </div>
      <div class="card-body" style="padding:16px">
        <canvas id="yearlyChart" height="55"></canvas>
        <p id="yearlyChartErr" class="text-muted text-center" style="font-size:12px;display:none"><?= $GLOBALS['LANG']==='th'?'โหลดกราฟไม่สำเร็จ':'Failed to load chart' ?></p>
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
      total:   LANG === 'th' ? 'ยอดรวม (บาท)' : 'Total (THB)',
      count:   LANG === 'th' ? 'จำนวน PO'    : 'PO Count',
      records: LANG === 'th' ? 'รายการ'      : 'records',
      failed:  LANG === 'th' ? 'โหลดกราฟไม่สำเร็จ' : 'Failed to load chart'
    };

    fetch('/chart-data.php?type=monthly&anchor=<?= $latest_date ?>')
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

// ── Yearly Spending Chart ────────────────────────────────────────────────
(function() {
    var showTotal = true;
    var yChart = null;
    var LANG = <?= json_encode($GLOBALS['LANG']) ?>;
    var L = {
        total:   LANG === 'th' ? 'ยอดรวม (บาท)' : 'Total (THB)',
        count:   LANG === 'th' ? 'จำนวน PO'    : 'PO Count',
        records: LANG === 'th' ? 'รายการ'       : 'records'
    };

    fetch('/chart-data.php?type=yearly')
        .then(function(r) { return r.json(); })
        .then(function(rows) {
            if (!rows || rows.length === 0) {
                document.getElementById('yearlyChartErr').style.display = '';
                return;
            }
            var labels    = rows.map(function(r) { return String(r.year); });
            var cntData   = rows.map(function(r) { return r.cnt; });
            var totalData = rows.map(function(r) { return r.total; });

            // Highlight peak year in gold, rest in blue
            function makeColors(data) {
                var mx = Math.max.apply(null, data);
                return data.map(function(v) {
                    return v === mx ? 'rgba(245,158,11,0.85)' : 'rgba(14,165,233,0.65)';
                });
            }

            var ctx = document.getElementById('yearlyChart').getContext('2d');
            yChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: L.total,
                        data: totalData,
                        backgroundColor: makeColors(totalData),
                        borderColor: makeColors(totalData).map(function(c) {
                            return c.replace('0.85','1').replace('0.65','1');
                        }),
                        borderWidth: 1,
                        borderRadius: 5,
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
                                    if (!showTotal) return v;
                                    if (v >= 1e9) return (v/1e9).toFixed(1)+'B';
                                    if (v >= 1e6) return (v/1e6).toFixed(1)+'M';
                                    if (v >= 1e3) return (v/1e3).toFixed(0)+'K';
                                    return v;
                                }
                            }
                        }
                    },
                    // Click bar → drill down to dept_report for that year
                    onClick: function(evt, elements) {
                        if (elements.length > 0) {
                            var yr = labels[elements[0].index];
                            location.href = '/dept_report.php?date_from=' + yr + '-01-01&date_to=' + yr + '-12-31';
                        }
                    }
                }
            });

            // Toggle count/total
            var toggle = document.getElementById('yearlyToggle');
            if (toggle) toggle.addEventListener('click', function() {
                showTotal = !showTotal;
                var d = showTotal ? totalData : cntData;
                yChart.data.datasets[0].label = showTotal ? L.total : L.count;
                yChart.data.datasets[0].data  = d;
                yChart.data.datasets[0].backgroundColor = makeColors(d);
                yChart.data.datasets[0].borderColor = makeColors(d).map(function(c) {
                    return c.replace('0.85','1').replace('0.65','1');
                });
                yChart.update();
            });
        })
        .catch(function() {
            document.getElementById('yearlyChartErr').style.display = '';
        });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
