<?php
// AJAX endpoint — returns HTML table only (no layout)
require_once __DIR__ . '/config/db.php';

$vn_code = db_escape($_GET['vn_code'] ?? '');
if ($vn_code === '') { echo '<div class="alert alert-warning m-3">' . t('no_data') . '</div>'; exit; }

$result = mysqli_query($conn, "
    SELECT h.SeqNo, h.PoDate, h.PoNo, h.RefNo, h.LocaCode, h.CreateUser,
           (SELECT SUM(Amount) FROM invpo1 WHERE SeqNo = h.SeqNo) AS TAmt
    FROM invpo0 h
    WHERE h.VndCode = '$vn_code'
    ORDER BY h.PoDate DESC
    LIMIT 100
");

if (!$result || mysqli_num_rows($result) === 0) {
    echo '<div class="text-center text-muted py-4"><i class="bi bi-inbox fs-1"></i><br>' . t('no_history') . '</div>';
    exit;
}

$total = 0;
$rows  = [];
while ($r = mysqli_fetch_assoc($result)) {
    $total += (float)$r['TAmt'];
    $rows[]  = $r;
}
?>
<div class="p-2">
    <div class="alert alert-info py-2 mb-2">
        <?= count($rows) ?> <?= t('records') ?> | <?= t('col_total') ?>: <strong><?= fmt_number($total) ?></strong> <?= t('baht') ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped table-hover">
            <thead>
                <tr>
                    <th><?= t('col_date') ?></th>
                    <th><?= t('col_po_no') ?></th>
                    <th><?= t('col_ref') ?></th>
                    <th class="text-end"><?= t('col_total') ?></th>
                    <th><?= t('col_loc') ?></th>
                    <th><?= t('recorded_by') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= fmt_date($r['PoDate']) ?></td>
                    <td><code><?= htmlspecialchars($r['PoNo']) ?></code></td>
                    <td class="text-muted small"><?= htmlspecialchars($r['RefNo']) ?></td>
                    <td class="text-end fw-bold"><?= fmt_number($r['TAmt']) ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($r['LocaCode']) ?></span></td>
                    <td class="small text-muted"><?= htmlspecialchars(db_str($r['CreateUser'])) ?></td>
                    <td>
                        <a href="/po_detail.php?seq=<?= (int)$r['SeqNo'] ?>"
                           class="btn btn-outline-primary btn-sm" target="_blank">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
