<?php
require_once __DIR__ . '/config/db.php';
require_role('admin');

$page_title = 'Audit Log';

$LOG_FILE = __DIR__ . '/logs/audit.log';

$lines = [];
if (is_file($LOG_FILE)) {
    $raw = @file($LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_reverse(array_slice($raw, -500)); // ล่าสุด 500 บรรทัด
}

// Parse line: "YYYY-MM-DD HH:MM:SS | user | ip | action | details"
$rows = [];
foreach ($lines as $l) {
    $parts = array_map('trim', explode('|', $l, 5));
    if (count($parts) >= 4) {
        $rows[] = [
            'time'    => $parts[0],
            'user'    => $parts[1],
            'ip'      => $parts[2],
            'action'  => $parts[3],
            'details' => $parts[4] ?? '',
        ];
    }
}

// Stats
$total = count($rows);
$today = date('Y-m-d');
$today_count = count(array_filter($rows, fn($r) => substr($r['time'], 0, 10) === $today));
$fail_count = count(array_filter($rows, fn($r) => $r['action'] === 'LOGIN_FAIL'));

require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats -->
<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card stat-card" style="--card-color:var(--accent)">
      <div class="card-body">
        <div class="stat-label"><?= $GLOBALS['LANG']==='th'?'Activities ทั้งหมด':'Total Activities' ?></div>
        <div class="stat-val"><?= number_format($total) ?></div>
        <div class="stat-label mt-1"><?= $GLOBALS['LANG']==='th'?'events (500 ล่าสุด)':'events (latest 500)' ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card" style="--card-color:var(--success)">
      <div class="card-body">
        <div class="stat-label"><?= t('today') ?></div>
        <div class="stat-val"><?= number_format($today_count) ?></div>
        <div class="stat-label mt-1">events</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card" style="--card-color:var(--danger)">
      <div class="card-body">
        <div class="stat-label"><?= $GLOBALS['LANG']==='th'?'Login ล้มเหลว':'Failed Logins' ?></div>
        <div class="stat-val"><?= number_format($fail_count) ?></div>
        <div class="stat-label mt-1"><?= $GLOBALS['LANG']==='th'?'ครั้ง':'times' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Log Table -->
<div class="card">
  <div class="card-header-inv">
    <i class="bi bi-shield-check me-1"></i> <?= $GLOBALS['LANG']==='th'?'บันทึกการใช้งาน':'Activity Log' ?>
    <span class="badge ms-1" style="background:rgba(255,255,255,.2)"><?= count($rows) ?> <?= t('records') ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table-inv table mb-0">
        <thead>
          <tr>
            <th style="width:160px"><?= $GLOBALS['LANG']==='th'?'เวลา':'Time' ?></th>
            <th>User</th>
            <th style="width:140px">IP</th>
            <th>Action</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="5" class="text-center text-muted py-4"><?= t('no_data') ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r):
            $action_colors = [
                'LOGIN'      => 'badge-paid',
                'LOGIN_FAIL' => 'badge-overdue',
                'LOGOUT'     => 'badge-src',
            ];
            $action_color = $action_colors[$r['action']] ?? 'badge-normal';
          ?>
          <tr>
            <td style="font-family:ui-monospace,monospace;font-size:12px"><?= htmlspecialchars($r['time']) ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($r['user']) ?></td>
            <td style="font-family:ui-monospace,monospace;font-size:12px;color:var(--muted)"><?= htmlspecialchars($r['ip']) ?></td>
            <td><span class="badge-inv <?= $action_color ?>"><?= htmlspecialchars($r['action']) ?></span></td>
            <td style="font-size:12.5px;color:var(--muted)"><?= htmlspecialchars($r['details']) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
