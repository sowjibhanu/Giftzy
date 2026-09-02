<?php
require __DIR__ . '/lib/init.php';
csrf_check();

if (($_POST['action'] ?? '') === 'delete') {
    $st = $db->prepare('DELETE FROM expenses WHERE id = ?');
    $st->execute([(int) $_POST['id']]);
    redirect('expenses.php?' . http_build_query($_GET), 'Expense deleted.');
}

$period = q('period');
$search = q('search');
$source = q('fund_source');
$category = q('category');

[$where, $params] = build_where([
    ["DATE_FORMAT(expense_date,'%Y-%m') = ?", $period],
    ['(purpose LIKE ? OR comments LIKE ?)', $search === '' ? '' : ["%$search%", "%$search%"]],
    ['fund_source = ?', $source],
    ['category = ?', $category],
]);

$perPage = 100;
$page = max(1, (int) q('page', 1));
$count = (int) scalar($db, "SELECT COUNT(*) FROM expenses$where", $params);
$pages = max(1, (int) ceil($count / $perPage));
$page = min($page, $pages);

$list = rows($db, "SELECT * FROM expenses$where ORDER BY expense_date DESC, id DESC
                   LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$sum = scalar($db, "SELECT COALESCE(SUM(amount),0) FROM expenses$where", $params);
$bySource = rows($db, "SELECT fund_source, SUM(amount) t FROM expenses$where GROUP BY fund_source
                       ORDER BY t DESC", $params);
$categories = array_column(rows($db, "SELECT DISTINCT category FROM expenses
                                      WHERE category IS NOT NULL AND category <> ''
                                      ORDER BY category"), 'category');

$title = 'Expenses';
require __DIR__ . '/partials/header.php';
?>
<div class="page-head">
  <h1 class="title">Expenses</h1>
  <a class="btn primary" href="expense_form.php">Add expense</a>
</div>

<form class="filters" method="get">
  <?= select_field('period', month_options($db, 'expenses', 'expense_date'), $period, 'All months') ?>
  <?= select_field('fund_source', FUND_SOURCES, $source, 'Any source') ?>
  <?= select_field('category', $categories, $category, 'Any category') ?>
  <div class="grow">
    <input class="input" name="search" value="<?= h($search) ?>" placeholder="Purpose or comment">
  </div>
  <button class="btn">Filter</button>
</form>

<div class="hint">
  <?= number_format($count) ?> entries &middot; total <strong><?= money($sum) ?></strong>
  <?php foreach ($bySource as $r): ?>
    &middot; <?= h($r['fund_source']) ?> <?= money($r['t']) ?>
  <?php endforeach; ?>
</div>

<div class="table-card"><div class="table-scroll">
  <table class="data">
    <thead><tr>
      <th>Date</th><th>Purpose</th><th>Category</th><th class="num">Amount</th>
      <th>Paid from</th><th>Settled</th><th>Comments</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr>
        <td class="dim nowrap"><?= h($r['expense_date']) ?></td>
        <td class="wide"><?= h($r['purpose']) ?></td>
        <td><?= h($r['category']) ?></td>
        <td class="num strong"><?= money($r['amount']) ?></td>
        <td><?= pill($r['fund_source']) ?></td>
        <td><?= $r['settled'] ? 'Yes' : '' ?></td>
        <td class="dim"><?= h($r['comments']) ?></td>
        <td class="nowrap">
          <a class="icon-btn" href="expense_form.php?id=<?= (int) $r['id'] ?>" title="Edit" aria-label="Edit">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M14.85 2.85a1.5 1.5 0 012.12 2.12L6 15.94 2.5 17l1.06-3.5L14.85 2.85z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
          </a>
          <form method="post" class="inline-form" onsubmit="return confirm('Delete this entry?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="icon-btn danger" title="Delete" aria-label="Delete">
              <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M4 6h12M8 6V4h4v2M6 6l1 10h6l1-10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div>

<?php if ($pages > 1): ?>
<nav class="pager">
  <?php for ($p = 1; $p <= $pages; $p++): $qs = $_GET; $qs['page'] = $p; ?>
    <a class="<?= $p === $page ? 'active' : '' ?>" href="?<?= h(http_build_query($qs)) ?>"><?= $p ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
