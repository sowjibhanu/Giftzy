<?php
require __DIR__ . '/lib/init.php';
csrf_check();

$id = (int) q('id');
$row = $id ? one($db, 'SELECT * FROM expenses WHERE id = ?', [$id]) : null;
if ($id && !$row) {
    redirect('expenses.php', 'That expense no longer exists.');
}

$categories = array_column(rows($db, "SELECT DISTINCT category FROM expenses
                                      WHERE category IS NOT NULL AND category <> ''
                                      ORDER BY category"), 'category');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'expense_date' => post_date('expense_date'),
        'purpose' => post_str('purpose', false),
        'category' => post_str('category_new') ?? post_str('category'),
        'amount' => post_num('amount'),
        'fund_source' => in_array($_POST['fund_source'] ?? '', FUND_SOURCES, true)
            ? $_POST['fund_source'] : 'Account',
        'settled' => isset($_POST['settled']) ? 1 : 0,
        'comments' => post_str('comments'),
    ];
    if ($data['purpose'] === null) {
        $error = 'Purpose is required.';
    } else {
        $cols = array_keys($data);
        if ($id) {
            $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
            $st = $db->prepare("UPDATE expenses SET $set WHERE id = ?");
            $st->execute([...array_values($data), $id]);
            redirect('expenses.php', 'Expense updated.');
        }
        $st = $db->prepare('INSERT INTO expenses (' . implode(',', $cols) . ') VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')');
        $st->execute(array_values($data));
        redirect('expense_form.php', 'Expense added.');
    }
    $row = $_POST + ['id' => $id];
}

$v = fn($k, $d = '') => h($row[$k] ?? $d);
$title = $id ? 'Edit expense' : 'Add expense';
require __DIR__ . '/partials/header.php';
?>
<h1 class="title"><?= h($title) ?></h1>
<?php if (!empty($error)): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="card form-card">
  <?= csrf_field() ?>
  <div class="fields">
    <div class="field">
      <label for="expense_date">Date</label>
      <input class="input" type="date" name="expense_date" id="expense_date" value="<?= $v('expense_date', date('Y-m-d')) ?>">
    </div>
    <div class="field wide">
      <label for="purpose">Purpose</label>
      <input class="input" name="purpose" id="purpose" value="<?= $v('purpose') ?>" required>
    </div>
    <div class="field narrow">
      <label for="amount">Amount</label>
      <input class="input" name="amount" id="amount" value="<?= $v('amount', '0') ?>">
    </div>
    <div class="field">
      <label for="fund_source">Paid from</label>
      <?= select_field('fund_source', FUND_SOURCES, $row['fund_source'] ?? 'Account') ?>
    </div>
    <div class="field">
      <label for="category">Category</label>
      <?= select_field('category', $categories, $row['category'] ?? '', '(none)') ?>
    </div>
    <div class="field">
      <label for="category_new">Or new category</label>
      <input class="input" name="category_new" id="category_new" value="">
    </div>
  </div>
  <p class="lede">Sowji / Lavanya = own money, Account = sale account, Cash = cash box.</p>
  <label class="check" for="settled">
    <input type="checkbox" name="settled" id="settled" <?= !empty($row['settled']) ? 'checked' : '' ?>>
    Settled between partners
  </label>
  <div class="fields" style="margin-top:20px">
    <div class="field wide">
      <label for="comments">Comments</label>
      <input class="input" name="comments" id="comments" value="<?= $v('comments') ?>">
    </div>
  </div>
  <div class="actions">
    <button class="btn primary">Save</button>
    <a class="btn link" href="expenses.php">Back to expenses</a>
  </div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
