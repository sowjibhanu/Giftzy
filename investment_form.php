<?php
require __DIR__ . '/lib/init.php';
csrf_check();

$id = (int) q('id');
$row = $id ? one($db, 'SELECT * FROM investments WHERE id = ?', [$id]) : null;
if ($id && !$row) {
    redirect('investments.php', 'That investment no longer exists.');
}

$categories = array_column(rows($db, "SELECT DISTINCT category FROM investments
                                      WHERE category IS NOT NULL AND category <> ''
                                      ORDER BY category"), 'category');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'inv_date' => post_date('inv_date'),
        'purpose' => post_str('purpose', false),
        'category' => post_str('category_new') ?? post_str('category'),
        'amount' => post_num('amount'),
        'fund_source' => in_array($_POST['fund_source'] ?? '', FUND_SOURCES, true)
            ? $_POST['fund_source'] : 'Other',
        'notes' => post_str('notes'),
    ];
    if ($data['purpose'] === null) {
        $error = 'Purpose is required.';
    } else {
        $cols = array_keys($data);
        if ($id) {
            $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
            $st = $db->prepare("UPDATE investments SET $set WHERE id = ?");
            $st->execute([...array_values($data), $id]);
            redirect('investments.php', 'Investment updated.');
        }
        $st = $db->prepare('INSERT INTO investments (' . implode(',', $cols) . ') VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')');
        $st->execute(array_values($data));
        redirect('investment_form.php', 'Investment added.');
    }
    $row = $_POST + ['id' => $id];
}

$v = fn($k, $d = '') => h($row[$k] ?? $d);
$title = $id ? 'Edit investment' : 'Add investment';
require __DIR__ . '/partials/header.php';
?>
<h1 class="title"><?= h($title) ?></h1>
<?php if (!empty($error)): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="card form-card">
  <?= csrf_field() ?>
  <div class="fields">
    <div class="field">
      <label for="inv_date">Date</label>
      <input class="input" type="date" name="inv_date" id="inv_date" value="<?= $v('inv_date', date('Y-m-d')) ?>">
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
      <label for="fund_source">Source</label>
      <?= select_field('fund_source', FUND_SOURCES, $row['fund_source'] ?? 'Sowji') ?>
    </div>
    <div class="field">
      <label for="category">Category</label>
      <?= select_field('category', $categories, $row['category'] ?? '', '(none)') ?>
    </div>
    <div class="field">
      <label for="category_new">Or new category</label>
      <input class="input" name="category_new" id="category_new" value="">
    </div>
    <div class="field wide">
      <label for="notes">Notes</label>
      <input class="input" name="notes" id="notes" value="<?= $v('notes') ?>">
    </div>
  </div>
  <div class="actions">
    <button class="btn primary">Save</button>
    <a class="btn link" href="investments.php">Back to investments</a>
  </div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
