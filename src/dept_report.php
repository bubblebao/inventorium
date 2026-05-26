<?php
require_once __DIR__ . '/config/db.php';
$page_title = t('dept_report_title');

$today     = date('Y-m-d');
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-t');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-t');

// invpo1 ไม่มี DeptCode — group by LocaCode (location/branch) จาก header แทน
$result = mysqli_query($conn, "
    SELECT h.LocaCode AS DeptCode,
           COUNT(DISTINCT h.SeqNo) AS po_cnt,
           SUM(d.NetAmount)        AS total_abt,
           SUM(d.TaxAmt)           AS total_tax
    FROM invpo0 h
    LEFT JOIN invpo1 d ON d.SeqNo = h.SeqNo
    WHERE h.PoDate BETWEEN '" . db_escape($date_from) . "' AND '" . db_escape($date_to) . "'
    GROUP BY h.LocaCode
    ORDER BY total_abt DESC
");

$rows = [];
$grand_total = 0;
if ($result) {
    while ($r = mysqli_fetch_assoc($result)) {
        $r['DeptCode'] = db_str($r['DeptCode']); // TIS-620 → UTF-8
        $rows[] = $r;
        $grand_total += (float)$r['total_abt'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Date Shortcut Bar -->
<div class="date-shortcuts mb-2">
  <span style="font-size:12px;color:var(--muted);line-height:2"><?= t('period') ?>:</span>
  <button type="button" class="btn btn-sm btn-inv-outline" onclick="applyDatePreset('month')"><?= t('month') ?></button>
  <button type="button" class="btn btn-sm btn-inv-outline" onclick="applyDatePreset('last_month')"><?= t('last_month') ?></button>
  <button type="button" class="btn btn-sm btn-inv-outline" onclick="applyDatePreset('3months')"><?= t('three_months') ?></button>
  <button type="button" class="btn btn-sm btn-inv-outline" onclick="applyDatePreset('year')"><?= t('year') ?></button>
</div>

<!-- Filter Card -->
<div class="card mb-3">
  <div class="card-header-inv"><i class="bi bi-funnel me-1"></i> <?= t('date_range') ?></div>
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
      <div class="col-auto d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-inv-primary"><i class="bi bi-search"></i> <?= t('view_report') ?></button>
        <a href="/dept_report.php" class="btn btn-sm btn-inv-outline"><?= t('reset') ?></a>
      </div>
    </form>
  </div>
</div>

<?php if (empty($rows)): ?>
  <div class="card">
    <div class="card-body text-center text-muted py-5"><?= t('no_data_period') ?></div>
  </div>
<?php else: ?>

<!-- Chart + Table -->
<div class="row g-3">
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header-inv"><i class="bi bi-pie-chart me-1"></i> <?= t('dept_pie') ?></div>
      <div class="card-body d-flex align-items-center justify-content-center" style="padding:20px">
        <canvas id="deptChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header-inv d-flex align-items-center justify-content-between">
        <span><i class="bi bi-bar-chart-line me-1"></i> <?= t('dept_report_by') ?>
          <?= htmlspecialchars(fmt_date($date_from)) ?> — <?= htmlspecialchars(fmt_date($date_to)) ?>
        </span>
        <span style="font-size:12px;opacity:.8"><?= t('subtotal') ?>: <?= fmt_number($grand_total) ?> <?= t('baht') ?></span>
      </div>
      <div class="card-body p-0">
        <table class="table-inv table mb-0">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th><?= t('location') ?></th>
              <th class="text-end"><?= t('col_po_count') ?></th>
              <th class="text-end"><?= t('col_before_tax') ?></th>
              <th class="text-end"><?= t('col_vat') ?></th>
              <th class="text-end"><?= t('col_total_pct') ?></th>
              <th><?= t('col_proportion') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $r):
            $pct = $grand_total > 0 ? round((float)$r['total_abt'] / $grand_total * 100, 1) : 0;
            $bar_w = min(100, $pct);
          ?>
            <tr>
              <td class="text-center" style="color:var(--muted)"><?= $i + 1 ?></td>
              <td class="fw-semibold">
                <?= htmlspecialchars($r['DeptCode'] ?: t('not_specified')) ?>
              </td>
              <td class="text-end"><?= number_format((int)$r['po_cnt']) ?></td>
              <td class="text-end fw-semibold"><?= fmt_number($r['total_abt']) ?></td>
              <td class="text-end" style="color:var(--muted)"><?= fmt_number($r['total_tax']) ?></td>
              <td class="text-end fw-semibold"><?= $pct ?>%</td>
              <td style="min-width:80px">
                <div style="height:8px;background:var(--border);border-radius:4px;overflow:hidden">
                  <div style="height:100%;width:<?= $bar_w ?>%;background:var(--accent);border-radius:4px"></div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot style="background:var(--surface-2);font-weight:600">
            <tr>
              <td colspan="3" class="text-end" style="color:var(--muted)"><?= t('col_total_all') ?></td>
              <td class="text-end" style="color:var(--primary)"><?= fmt_number($grand_total) ?></td>
              <td class="text-end" style="color:var(--muted)">
                <?= fmt_number(array_sum(array_column($rows, 'total_tax'))) ?>
              </td>
              <td class="text-end">100%</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
    var labels = <?= json_encode(array_map(fn($r) => $r['DeptCode'] ?: t('not_specified'), $rows), JSON_HEX_TAG) ?>;
    var totals = <?= json_encode(array_map(fn($r) => round((float)$r['total_abt'], 2), $rows), JSON_HEX_TAG) ?>;

    var palette = [
        '#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6',
        '#06b6d4','#84cc16','#f97316','#ec4899','#6366f1',
        '#14b8a6','#a78bfa'
    ];
    var colors = labels.map(function(_, i) { return palette[i % palette.length]; });

    new Chart(document.getElementById('deptChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: totals,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var v = ctx.raw;
                            return ' ' + ctx.label + ': ฿' + v.toLocaleString('th-TH', {minimumFractionDigits:2});
                        }
                    }
                }
            }
        }
    });
})();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
