<?php
// ─── Admin: Re-Snapshot Database ──────────────────────────────────────────
// Admin-only page — dumps local DB to a downloadable .sql.gz file
// The downloaded file should replace db-init/snapshot.sql.gz in the repo
require_once __DIR__ . '/config/db.php';
require_role('admin');

// ─── Handle dump request ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dump') {
    audit_log('SNAPSHOT', 'Admin triggered DB snapshot download');

    set_time_limit(300);                // 5 min — large dataset
    ini_set('memory_limit', '512M');

    $tables = ['invpo0', 'invpo1', 'gblvend', 'gblprod'];

    // Write to temp file (avoid buffering 600K rows in memory)
    $tmp = tempnam(sys_get_temp_dir(), 'inv_snap_');
    $gz  = gzopen($tmp, 'wb9');
    if (!$gz) {
        http_response_code(500);
        die('<p>Cannot create temp file — check /tmp permissions</p>');
    }

    gzwrite($gz, "-- Inventorium DB Snapshot\n");
    gzwrite($gz, "-- Generated : " . date('Y-m-d H:i:s') . "\n");
    gzwrite($gz, "-- Tables    : " . implode(', ', $tables) . "\n\n");
    gzwrite($gz, "SET NAMES latin1;\n");
    gzwrite($gz, "SET foreign_key_checks = 0;\n\n");

    foreach ($tables as $table) {
        // ── Schema ──
        $r_create = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
        if (!$r_create) continue;
        $schema_row = mysqli_fetch_row($r_create);
        $create_sql = $schema_row[1] ?? '';
        mysqli_free_result($r_create);

        gzwrite($gz, "-- ----------------------------\n");
        gzwrite($gz, "-- Table: $table\n");
        gzwrite($gz, "-- ----------------------------\n");
        gzwrite($gz, "DROP TABLE IF EXISTS `$table`;\n");
        gzwrite($gz, $create_sql . ";\n\n");

        // ── Column list (SELECT LIMIT 0 — no data transfer) ──
        $r_meta = mysqli_query($conn, "SELECT * FROM `$table` LIMIT 0");
        $n_fields = mysqli_num_fields($r_meta);
        $col_names = [];
        for ($i = 0; $i < $n_fields; $i++) {
            $col_names[] = '`' . mysqli_fetch_field_direct($r_meta, $i)->name . '`';
        }
        $col_list = implode(', ', $col_names);
        mysqli_free_result($r_meta);

        // ── Data — stream row by row via unbuffered query ──
        mysqli_real_query($conn, "SELECT * FROM `$table`");
        $r_data = mysqli_use_result($conn);   // streaming — does NOT buffer all rows

        $batch      = [];
        $batch_size = 500;
        while ($row = mysqli_fetch_row($r_data)) {
            $vals = array_map(
                fn($v) => $v === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'",
                $row
            );
            $batch[] = '(' . implode(', ', $vals) . ')';

            if (count($batch) >= $batch_size) {
                gzwrite($gz, "INSERT INTO `$table` ($col_list) VALUES\n"
                           . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            gzwrite($gz, "INSERT INTO `$table` ($col_list) VALUES\n"
                       . implode(",\n", $batch) . ";\n");
        }
        mysqli_free_result($r_data);
        gzwrite($gz, "\n");
    }

    gzwrite($gz, "SET foreign_key_checks = 1;\n");
    gzclose($gz);

    // ── Stream download ──
    $filename = 'snapshot_' . date('Ymd_His') . '.sql.gz';
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store, no-cache');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

// ─── Page: show stats + dump button ───────────────────────────────────────
$page_title = t('snapshot_title');

// Quick row counts (cached 5 min — not critical)
$stats = [];
foreach (['invpo0' => 'PO Headers', 'invpo1' => 'PO Lines', 'gblvend' => 'Vendors', 'gblprod' => 'Products'] as $tbl => $label) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM `$tbl`");
    $stats[$label] = $r ? (int)mysqli_fetch_assoc($r)['c'] : 0;
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Info Banner ── -->
<div class="alert mb-3" style="background:linear-gradient(90deg,#fef3c7 0%,#fffbeb 100%);border:1px solid #fcd34d;color:#92400e;border-radius:10px;padding:14px 18px;font-size:13px">
  <div class="d-flex align-items-start gap-2">
    <i class="bi bi-exclamation-triangle-fill" style="font-size:18px;flex-shrink:0;margin-top:1px"></i>
    <div>
      <strong><?= t('snapshot_warning') ?></strong><br>
      <span style="opacity:.8"><?= t('snapshot_note') ?></span>
    </div>
  </div>
</div>

<!-- ── Current Stats Card ── -->
<div class="card mb-3">
  <div class="card-header-inv"><i class="bi bi-database me-1"></i> <?= t('snapshot_stats') ?></div>
  <div class="card-body">
    <div class="row g-3">
      <?php
      $colors = ['var(--accent)', 'var(--success)', 'var(--warning)', 'var(--info)'];
      $i = 0;
      foreach ($stats as $label => $cnt):
        $c = $colors[$i++ % count($colors)];
      ?>
      <div class="col-sm-6 col-lg-3">
        <div class="card stat-card h-100" style="--card-color:<?= $c ?>">
          <div class="card-body py-3">
            <div class="stat-label"><?= htmlspecialchars($label) ?></div>
            <div class="stat-val"><?= number_format($cnt) ?></div>
            <div class="stat-label mt-1"><?= t('records') ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-3" style="font-size:12px;color:var(--muted)">
      <i class="bi bi-info-circle me-1"></i> <?= t('snapshot_tables') ?>
    </div>
  </div>
</div>

<!-- ── Dump Button Card ── -->
<div class="card">
  <div class="card-header-inv"><i class="bi bi-database-down me-1"></i> <?= t('snapshot_title') ?></div>
  <div class="card-body">
    <p style="font-size:13.5px;margin-bottom:20px">
      คลิกปุ่มด้านล่างเพื่อ dump ข้อมูลจาก DB ปัจจุบัน (db-bridge) เป็นไฟล์ <code>snapshot.sql.gz</code>
      พร้อมดาวน์โหลดอัตโนมัติ<br>
      <span style="color:var(--muted);font-size:12px">จากนั้นให้นำไฟล์ที่ได้ไปแทนที่ <code>db-init/snapshot.sql.gz</code> ใน repo แล้ว push ขึ้น git ตามปกติ</span>
    </p>

    <form method="POST" id="snapshotForm">
      <input type="hidden" name="action" value="dump">
      <button type="submit" class="btn btn-inv-primary" id="snapshotBtn" style="padding:10px 28px;font-size:15px">
        <i class="bi bi-database-down me-2"></i> <?= t('snapshot_btn') ?>
      </button>
      <span id="snapshotStatus" class="ms-3" style="font-size:13px;display:none">
        <span class="spinner-border spinner-border-sm text-primary me-1"></span>
        <?= t('snapshot_running') ?>
      </span>
    </form>

    <!-- Step instructions -->
    <div class="mt-4 p-3" style="background:var(--surface-2);border-radius:8px;font-size:12.5px;border:1px solid var(--border)">
      <div class="fw-semibold mb-2" style="color:var(--primary)">📋 ขั้นตอนหลังดาวน์โหลด</div>
      <ol style="margin:0;padding-left:20px;line-height:1.9">
        <li>นำไฟล์ <code>snapshot_YYYYMMDD_HHMMSS.sql.gz</code> ที่ได้ไปเปลี่ยนชื่อเป็น <code>snapshot.sql.gz</code></li>
        <li>วางไว้ที่ <code>db-init/snapshot.sql.gz</code> ใน repo (แทนที่ไฟล์เดิม)</li>
        <li>Push ขึ้น git: <code>git add db-init/snapshot.sql.gz &amp;&amp; git commit -m "update snapshot" &amp;&amp; git push</code></li>
        <li>บน NAS: <code>git pull origin main &amp;&amp; docker compose down -v &amp;&amp; docker compose up -d</code></li>
        <li><strong>หมายเหตุ:</strong> <code>-v</code> จะลบ volume เก่า ทำให้ init script รันใหม่จาก snapshot ใหม่</li>
      </ol>
    </div>
  </div>
</div>

<script>
document.getElementById('snapshotForm').addEventListener('submit', function() {
    var btn = document.getElementById('snapshotBtn');
    var status = document.getElementById('snapshotStatus');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> <?= t('snapshot_running') ?>';
    status.style.display = 'inline';
    // Form submits normally — browser will download the file
    // Re-enable after delay (in case user stays on page)
    setTimeout(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-database-down me-2"></i> <?= t('snapshot_btn') ?>';
        status.style.display = 'none';
    }, 180000); // 3 min timeout
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
