<?php
require_once 'includes/layout.php';
require_login();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$s = $id ? one('SELECT * FROM staff WHERE id = ?', [$id]) : null;
if ($id && !$s) { flash('bad', 'Staff member not found.'); redirect('staff.php'); }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $d = [trim($_POST['name'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['designation'] ?? ''), trim($_POST['joining_date'] ?? ''), trim($_POST['address'] ?? ''), trim($_POST['bank_details'] ?? ''), ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active', trim($_POST['notes'] ?? '')];
    if ($d[0] === '') $err = 'Name is required.';
    elseif ($d[2] !== '' && !filter_var($d[2], FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email address.';
    elseif ($d[1] !== '' && !preg_match('/^[0-9+\-\s]{7,15}$/', $d[1])) $err = 'Enter a valid phone number.';
    elseif ($d[4] !== '' && !valid_date($d[4])) $err = 'Enter a valid joining date.';
    else {
        $d[4] = $d[4] ?: null;
        if ($id) { q('UPDATE staff SET name=?, phone=?, email=?, designation=?, joining_date=?, address=?, bank_details=?, status=?, notes=? WHERE id=?', array_merge($d, [$id])); flash('ok', 'Staff details saved.'); }
        else {
            q('INSERT INTO staff (name, phone, email, designation, joining_date, address, bank_details, status, notes) VALUES (?,?,?,?,?,?,?,?,?)', $d);
            $id = (int)db()->lastInsertId();
            q('UPDATE staff SET staff_code = ? WHERE id = ?', [sprintf('ST%03d', $id), $id]);
            flash('ok', 'Staff member added.');
        }
        redirect('staff_view.php?id=' . $id);
    }
    $s = array_combine(['name', 'phone', 'email', 'designation', 'joining_date', 'address', 'bank_details', 'status', 'notes'], $d);
}
$v = fn($k, $def = '') => e($s[$k] ?? $def);
layout_top($id ? 'Edit staff' : 'Add staff');
if ($err) echo '<div class="alert bad">' . e($err) . '</div>';
?>
<div class="card"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <div class="form-grid">
    <div><label for="name">Full name <span class="req">*</span></label><input id="name" name="name" value="<?= $v('name') ?>" required></div>
    <div><label for="designation">Designation / role</label><input id="designation" name="designation" value="<?= $v('designation') ?>"></div>
    <div><label for="phone">Phone</label><input id="phone" name="phone" inputmode="tel" value="<?= $v('phone') ?>"></div>
    <div><label for="email">Email</label><input id="email" type="email" name="email" value="<?= $v('email') ?>"></div>
    <div><label for="joining_date">Joining date</label><input id="joining_date" type="date" name="joining_date" value="<?= $v('joining_date', date('Y-m-d')) ?>"></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="Active">Active</option><option value="Inactive"<?= ($s['status'] ?? '') === 'Inactive' ? ' selected' : '' ?>>Inactive</option></select></div>
    <div class="full"><label for="address">Address</label><textarea id="address" name="address"><?= $v('address') ?></textarea></div>
    <div class="full"><label for="bank_details">Bank / UPI details</label><textarea id="bank_details" name="bank_details"><?= $v('bank_details') ?></textarea><div class="hint">Used when paying wages. Optional.</div></div>
    <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes"><?= $v('notes') ?></textarea></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary">Save staff</button><a class="btn btn-ghost" href="staff.php">Cancel</a></div>
</form></div>
<?php layout_bottom();
