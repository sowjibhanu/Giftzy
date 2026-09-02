<?php
require __DIR__ . '/lib/init.php';
csrf_check();

$id = (int) q('id');
$row = $id ? one($db, 'SELECT * FROM sales WHERE id = ?', [$id]) : null;
if ($id && !$row) {
    redirect('sales.php', 'That sale entry no longer exists.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qty = post_num('qty');
    $sp = post_num('selling_price');
    $cp = post_num('cost_price');
    $data = [
        'sale_date' => post_date('sale_date'),
        'item' => post_str('item', false),
        'qty' => $qty,
        'selling_price' => $sp,
        'cost_price' => $cp,
        'total_amount' => round($qty * $sp, 2),
        'profit' => round($qty * ($sp - $cp), 2),
        'customer' => post_str('customer'),
        'payment_type' => in_array($_POST['payment_type'] ?? '', PAYMENT_TYPES, true)
            ? $_POST['payment_type'] : 'Other',
        'pending_amount' => post_num('pending_amount'),
        'notes' => post_str('notes'),
    ];
    if ($data['item'] === null) {
        $error = 'Item name is required.';
    } else {
        $cols = array_keys($data);
        if ($id) {
            $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
            $st = $db->prepare("UPDATE sales SET $set WHERE id = ?");
            $st->execute([...array_values($data), $id]);
            redirect('sales.php', 'Sale entry updated.');
        }
        $st = $db->prepare('INSERT INTO sales (' . implode(',', $cols) . ') VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')');
        $st->execute(array_values($data));
        redirect('sale_form.php', 'Sale entry added.');
    }
    $row = $_POST + ['id' => $id];
}

$v = fn($k, $d = '') => h($row[$k] ?? $d);
$title = $id ? 'Edit sale' : 'Add sale';
require __DIR__ . '/partials/header.php';
?>
<h1 class="title"><?= h($title) ?></h1>
<?php if (!empty($error)): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="card form-card">
  <?= csrf_field() ?>
  <div class="fields">
    <div class="field">
      <label for="sale_date">Date</label>
      <input class="input" type="date" name="sale_date" id="sale_date" value="<?= $v('sale_date', date('Y-m-d')) ?>">
    </div>
    <div class="field wide">
      <label for="item">Item</label>
      <input class="input" name="item" id="item" value="<?= $v('item') ?>" required>
    </div>
    <div class="field narrow">
      <label for="qty">Quantity</label>
      <input class="input" name="qty" id="qty" value="<?= $v('qty', '1') ?>">
    </div>
    <div class="field">
      <label for="selling_price">Selling price (per unit)</label>
      <input class="input" name="selling_price" id="selling_price" value="<?= $v('selling_price', '0') ?>">
    </div>
    <div class="field">
      <label for="cost_price">Cost price (per unit)</label>
      <input class="input" name="cost_price" id="cost_price" value="<?= $v('cost_price', '0') ?>">
    </div>
    <div class="field narrow">
      <label for="payment_type">Payment</label>
      <?= select_field('payment_type', PAYMENT_TYPES, $row['payment_type'] ?? 'Online') ?>
    </div>
    <div class="field narrow">
      <label for="pending_amount">Pending amount</label>
      <input class="input" name="pending_amount" id="pending_amount" value="<?= $v('pending_amount', '0') ?>">
    </div>
    <div class="field">
      <label for="customer">Customer</label>
      <input class="input" name="customer" id="customer" value="<?= $v('customer') ?>">
    </div>
    <div class="field wide">
      <label for="notes">Notes</label>
      <input class="input" name="notes" id="notes" value="<?= $v('notes') ?>">
    </div>
  </div>
  <p class="lede">Total and profit are calculated from quantity, selling price and cost price.</p>
  <div class="actions">
    <button class="btn primary">Save</button>
    <a class="btn link" href="sales.php">Back to sales</a>
  </div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
