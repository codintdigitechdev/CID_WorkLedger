<?php
require_once 'includes/layout.php';
require_login();
$defs = report_list();
$r = $_GET['r'] ?? 'work_staff';
if (!isset($defs[$r])) $r = 'work_staff';
$from = valid_date($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = valid_date($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$sid = (int)($_GET['staff_id'] ?? 0);
$useDates = in_array($r, ['work_staff', 'work_task', 'work_detail', 'wages', 'payments', 'ledger'], true);
$useStaff = in_array($r, ['work_detail', 'wages', 'payments', 'ledger'], true);
$cols = []; $rows = []; $sum = []; $note = '';
/* column: [key, label, type] type = text|money|num|date */
switch ($r) {
    case 'staff':
        $cols = [['staff_code', 'Code', 'text'], ['name', 'Name', 'text'], ['phone', 'Phone', 'text'], ['designation', 'Designation', 'text'], ['joining_date', 'Joined', 'date'], ['status', 'Status', 'text']];
        $rows = all('SELECT staff_code, name, phone, designation, joining_date, status FROM staff ORDER BY name'); break;
    case 'tasks':
        $cols = [['name', 'Job / task', 'text'], ['unit', 'Charged', 'text'], ['rate', 'Rate', 'money'], ['status', 'Status', 'text'], ['used', 'Entries', 'num']];
        $rows = all('SELECT t.name, t.unit, t.rate, t.status, (SELECT COUNT(*) FROM work_entries w WHERE w.task_id=t.id) AS used FROM tasks t ORDER BY t.name'); break;
    case 'work_staff':
        $cols = [['staff_code', 'Code', 'text'], ['name', 'Staff', 'text'], ['entries', 'Entries', 'num'], ['approved', 'Approved amount', 'money'], ['pending', 'Pending amount', 'money']];
        $rows = all("SELECT s.staff_code, s.name, COUNT(w.id) AS entries, COALESCE(SUM(CASE WHEN w.status='Approved' THEN w.amount END),0) AS approved, COALESCE(SUM(CASE WHEN w.status='Pending' THEN w.amount END),0) AS pending FROM staff s JOIN work_entries w ON w.staff_id=s.id WHERE w.work_date BETWEEN ? AND ? GROUP BY s.id, s.staff_code, s.name ORDER BY s.name", [$from, $to]);
        $sum = ['entries', 'approved', 'pending']; break;
    case 'work_task':
        $cols = [['name', 'Job / task', 'text'], ['unit', 'Charged', 'text'], ['entries', 'Entries', 'num'], ['qty', 'Total quantity', 'num'], ['approved', 'Approved amount', 'money'], ['pending', 'Pending amount', 'money']];
        $rows = all("SELECT t.name, t.unit, COUNT(w.id) AS entries, SUM(w.quantity) AS qty, COALESCE(SUM(CASE WHEN w.status='Approved' THEN w.amount END),0) AS approved, COALESCE(SUM(CASE WHEN w.status='Pending' THEN w.amount END),0) AS pending FROM tasks t JOIN work_entries w ON w.task_id=t.id WHERE w.work_date BETWEEN ? AND ? GROUP BY t.id, t.name, t.unit ORDER BY t.name", [$from, $to]);
        $sum = ['entries', 'approved', 'pending']; break;
    case 'work_detail':
        $cols = [['work_date', 'Date', 'date'], ['staff', 'Staff', 'text'], ['task', 'Job / task', 'text'], ['quantity', 'Qty', 'num'], ['rate', 'Rate', 'money'], ['amount', 'Amount', 'money'], ['status', 'Status', 'text']];
        $rows = all('SELECT w.work_date, s.name AS staff, t.name AS task, w.quantity, w.rate, w.amount, w.status FROM work_entries w JOIN staff s ON s.id=w.staff_id JOIN tasks t ON t.id=w.task_id WHERE w.work_date BETWEEN ? AND ?' . ($sid ? ' AND w.staff_id=' . $sid : '') . ' ORDER BY w.work_date, w.id', [$from, $to]);
        $sum = ['amount']; break;
    case 'wages':
        $cols = [['name', 'Staff', 'text'], ['entries', 'Approved entries', 'num'], ['earned', 'Earned', 'money'], ['pending', 'Awaiting approval', 'money'], ['paid', 'Paid', 'money'], ['balance', 'Balance due (all time)', 'money']];
        $rows = array_values(array_filter(wage_rows($from, $to, $sid), function ($x) { return $x['status'] === 'Active' || abs($x['balance']) > 0.004 || $x['earned'] > 0; }));
        $sum = ['entries', 'earned', 'pending', 'paid', 'balance']; break;
    case 'payments':
        $cols = [['slip', 'Slip no.', 'text'], ['pay_date', 'Date', 'date'], ['staff', 'Staff', 'text'], ['type', 'Type', 'text'], ['mode', 'Mode', 'text'], ['reference', 'Reference', 'text'], ['amount', 'Amount', 'money']];
        $rows = all('SELECT p.id, p.pay_date, s.name AS staff, p.type, p.mode, p.reference, p.amount FROM payments p JOIN staff s ON s.id=p.staff_id WHERE p.pay_date BETWEEN ? AND ?' . ($sid ? ' AND p.staff_id=' . $sid : '') . ' ORDER BY p.pay_date, p.id', [$from, $to]);
        foreach ($rows as &$x) $x['slip'] = 'PAY-' . str_pad($x['id'], 4, '0', STR_PAD_LEFT); unset($x);
        $sum = ['amount']; break;
    case 'dues':
        $cols = [['staff_code', 'Code', 'text'], ['name', 'Staff', 'text'], ['designation', 'Designation', 'text'], ['pending', 'Awaiting approval', 'money'], ['balance', 'Balance due', 'money']];
        $rows = array_values(array_filter(wage_rows('1970-01-01', date('Y-m-d')), function ($x) { return $x['balance'] > 0.004; }));
        $sum = ['pending', 'balance']; $note = 'Balance due as on ' . date('d M Y') . ': approved work minus payments.'; break;
    case 'ledger':
        $cols = [['date', 'Date', 'date'], ['particulars', 'Particulars', 'text'], ['earned', 'Earned', 'money'], ['paid', 'Paid', 'money'], ['balance', 'Balance', 'money']];
        if ($sid) {
            $open = (float)val("SELECT (SELECT COALESCE(SUM(amount),0) FROM work_entries WHERE staff_id=? AND status='Approved' AND work_date < ?) - (SELECT COALESCE(SUM(amount),0) FROM payments WHERE staff_id=? AND pay_date < ?)", [$sid, $from, $sid, $from]);
            $ev = [];
            foreach (all("SELECT w.id, w.work_date AS d, t.name, w.quantity, w.rate, w.amount FROM work_entries w JOIN tasks t ON t.id=w.task_id WHERE w.staff_id=? AND w.status='Approved' AND w.work_date BETWEEN ? AND ?", [$sid, $from, $to]) as $w)
                $ev[] = ['date' => $w['d'], 'o' => 0, 'id' => $w['id'], 'particulars' => $w['name'] . ' (' . qty($w['quantity']) . ' × ' . inr($w['rate']) . ')', 'earned' => $w['amount'], 'paid' => 0];
            foreach (all('SELECT id, pay_date AS d, type, mode, amount FROM payments WHERE staff_id=? AND pay_date BETWEEN ? AND ?', [$sid, $from, $to]) as $y)
                $ev[] = ['date' => $y['d'], 'o' => 1, 'id' => $y['id'], 'particulars' => 'Payment · ' . $y['type'] . ' · ' . $y['mode'], 'earned' => 0, 'paid' => $y['amount']];
            usort($ev, function ($a, $b) { return [$a['date'], $a['o'], $a['id']] <=> [$b['date'], $b['o'], $b['id']]; });
            $bal = $open;
            $rows[] = ['date' => $from, 'particulars' => 'Opening balance', 'earned' => 0, 'paid' => 0, 'balance' => $open];
            foreach ($ev as $x) { $bal += $x['earned'] - $x['paid']; $x['balance'] = $bal; $rows[] = $x; }
            $sum = ['earned', 'paid'];
        } else $note = 'Select a staff member to see the ledger.';
        break;
}
$totals = [];
foreach ($sum as $k) { $totals[$k] = 0; foreach ($rows as $x) $totals[$k] += (float)$x[$k]; }
if ($r === 'ledger' && $rows) $totals['balance'] = end($rows)['balance'];

function fmt_cell($v, $type) {
    if ($type === 'money') return inr($v);
    if ($type === 'num') return qty($v);
    if ($type === 'date') return dmy($v);
    return (string)$v;
}
if (!empty($_GET['export']) && $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $r . '_' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, array_map(function ($c) { return __($c[1]); }, $cols));
    foreach ($rows as $x) fputcsv($o, array_map(function ($c) use ($x) { $v = $x[$c[0]] ?? ''; return $c[2] === 'money' || $c[2] === 'num' ? (float)$v : ($c[2] === 'date' ? $v : __((string)$v)); }, $cols));
    exit;
}
layout_top($defs[$r], '<button class="btn btn-ghost" onclick="window.print()">Print</button>' . ($rows ? '<a class="btn btn-primary" href="reports.php?' . e(http_build_query(array_merge($_GET, ['r' => $r, 'export' => 1]))) . '">Download CSV</a>' : ''));
?>
<form class="filters" method="get"><input type="hidden" name="r" value="<?= e($r) ?>">
  <?php if ($useDates): ?><div><label for="from">From</label><input id="from" type="date" name="from" value="<?= e($from) ?>"></div><div><label for="to">To</label><input id="to" type="date" name="to" value="<?= e($to) ?>"></div><?php endif; ?>
  <?php if ($useStaff): ?><div><label><?= $r === 'ledger' ? 'Staff' : 'Staff' ?></label><?= staff_select('staff_id', $sid, false, $r !== 'ledger') ?></div><?php endif; ?>
  <div class="btns"><button class="btn btn-primary">Show report</button></div>
</form>
<?php if ($useDates): ?><p class="muted no-print" style="margin-top:-6px">Period: <?= dmy($from) ?> to <?= dmy($to) ?></p><?php endif; ?>
<?php if ($note): ?><div class="alert info" style="word-break:normal"><?= e($note) ?></div><?php endif; ?>
<div class="card flush"><div class="table-wrap"><table class="stack">
<thead><tr><?php foreach ($cols as $c): ?><th<?= in_array($c[2], ['money', 'num'], true) ? ' class="num"' : '' ?>><?= e($c[1]) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($rows as $x): ?><tr><?php foreach ($cols as $c): $v = $x[$c[0]] ?? ''; ?><td<?= in_array($c[2], ['money', 'num'], true) ? ' class="num"' : '' ?>><?= e(fmt_cell($v, $c[2])) ?></td><?php endforeach; ?></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="<?= count($cols) ?>" class="empty">No data for this report and period.</td></tr><?php endif; ?>
</tbody>
<?php if ($rows && $totals): ?><tfoot><tr><?php foreach ($cols as $i => $c): ?><td<?= in_array($c[2], ['money', 'num'], true) ? ' class="num"' : '' ?>><?= $i === 0 ? 'Total' : (isset($totals[$c[0]]) ? e(fmt_cell($totals[$c[0]], $c[2])) : '') ?></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
</table></div></div>
<?php layout_bottom();
