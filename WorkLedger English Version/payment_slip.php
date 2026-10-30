<?php
require_once 'includes/layout.php';
require_login();
$p = one('SELECT p.*, s.name AS staff, s.staff_code, s.designation, u.name AS by_name FROM payments p JOIN staff s ON s.id=p.staff_id LEFT JOIN users u ON u.id=p.created_by WHERE p.id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$p) { flash('bad', 'Payment not found.'); redirect('payments.php'); }
$bal = staff_balance((int)$p['staff_id']);
layout_top('Payment slip', '<button class="btn btn-primary" onclick="window.print()">Print slip</button><a class="btn btn-ghost" href="payments.php">All payments</a>');
?>
<div class="slip">
  <h2><?= COMPANY_NAME ?></h2><div class="muted">Payment slip · PAY-<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?></div>
  <div class="amt"><?= inr($p['amount']) ?></div>
  <div class="row"><span class="muted">Paid to</span><b><?= e($p['staff']) ?> (<?= e($p['staff_code']) ?>)</b></div>
  <div class="row"><span class="muted">Date</span><b><?= dmy($p['pay_date']) ?></b></div>
  <div class="row"><span class="muted">Type</span><b><?= e($p['type']) ?></b></div>
  <div class="row"><span class="muted">Mode</span><b><?= e($p['mode']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></b></div>
  <?php if ($p['remarks']): ?><div class="row"><span class="muted">Remarks</span><b><?= e($p['remarks']) ?></b></div><?php endif; ?>
  <div class="row"><span class="muted">Balance due after this payment (as on <?= date('d M Y') ?>)</span><b><?= inr($bal) ?></b></div>
  <div class="row" style="margin-top:34px;border:0"><span class="muted">Paid by: <?= e($p['by_name'] ?: '—') ?></span><span class="muted">Receiver's signature: ____________________</span></div>
</div>
<?php layout_bottom();
